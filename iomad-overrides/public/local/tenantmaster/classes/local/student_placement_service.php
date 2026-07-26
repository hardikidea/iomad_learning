<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\local;

/**
 * School student placement projected to native cohorts, groups and enrolments.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class student_placement_service {
    /** @var string[] */
    public const STATUSES = ['active', 'completed', 'transferred', 'withdrawn', 'graduated'];

    /**
     * List tenant placements with current native and academic labels.
     *
     * @param object $tenant Tenant.
     * @return array<int, object>
     */
    public function list(object $tenant): array {
        global $DB;

        return $DB->get_records_sql(
            "SELECT p.*, u.firstname, u.lastname, u.idnumber AS useridnumber,
                    y.name AS yearname, g.name AS gradename, d.name AS divisionname,
                    m.name AS mediumname, s.name AS streamname, b.name AS boardname
               FROM {local_tenantmaster_placement} p
               JOIN {user} u ON u.id = p.userid AND u.deleted = 0
               JOIN {local_tenantmaster_acadyear} y ON y.id = p.acadyearid
               JOIN {local_tenantmaster_master} g ON g.id = p.gradeid
               JOIN {local_tenantmaster_master} d ON d.id = p.divisionid
          LEFT JOIN {local_tenantmaster_master} m ON m.id = p.mediumid
          LEFT JOIN {local_tenantmaster_master} s ON s.id = p.streamid
          LEFT JOIN {local_tenantmaster_master} b ON b.id = p.boardid
              WHERE p.tenantid = :tenantid
           ORDER BY y.startdate DESC, g.sortorder, d.sortorder, u.lastname, u.firstname",
            ['tenantid' => $tenant->id],
        );
    }

    /**
     * Save one year-scoped placement and immediately reconcile native access.
     *
     * @param object $tenant Tenant.
     * @param object $data Validated form data.
     * @return object
     */
    public function save(object $tenant, object $data): object {
        global $DB, $USER;

        if ((string)$tenant->tenanttype !== 'school') {
            throw new \invalid_parameter_exception('Class placements are available only to school tenants.');
        }
        $userid = (int)$data->userid;
        if (
            !$DB->record_exists('local_iomad_company_users', [
            'companyid' => $tenant->companyid,
            'userid' => $userid,
            ])
        ) {
            throw new \invalid_parameter_exception('Student belongs to another tenant.');
        }
        $year = $DB->get_record('local_tenantmaster_acadyear', [
            'id' => (int)$data->acadyearid,
            'tenantid' => $tenant->id,
        ], '*', MUST_EXIST);
        $grade = $this->master($tenant, (int)$data->gradeid, 'grade', $year);
        $division = $this->master($tenant, (int)$data->divisionid, 'division', $year);
        $board = $this->optional_master($tenant, (int)($data->boardid ?? 0), 'board', $year);
        $medium = $this->optional_master($tenant, (int)($data->mediumid ?? 0), 'medium', $year);
        $stream = $this->optional_master($tenant, (int)($data->streamid ?? 0), 'stream', $year);
        $status = (string)($data->status ?? 'active');
        if (!in_array($status, self::STATUSES, true)) {
            throw new \invalid_parameter_exception('Invalid placement status.');
        }

        $recordid = (int)($data->id ?? 0);
        $current = null;
        if ($recordid > 0) {
            $current = $DB->get_record('local_tenantmaster_placement', [
                'id' => $recordid,
                'tenantid' => $tenant->id,
            ], '*', MUST_EXIST);
            if ((int)$current->userid !== $userid || (int)$current->acadyearid !== (int)$year->id) {
                throw new \invalid_parameter_exception('Student and academic year cannot change after placement creation.');
            }
        } else if (
            $DB->record_exists('local_tenantmaster_placement', [
            'tenantid' => $tenant->id,
            'userid' => $userid,
            'acadyearid' => $year->id,
            ])
        ) {
            throw new \invalid_parameter_exception('The student already has a placement for this academic year.');
        }

        $classkey = $this->class_key($year, $board, $medium, $grade, $stream, $division);
        $courseids = [];
        $groupids = [];
        $enrolmentids = [];
        $cohortid = (int)($current->cohortid ?? 0);
        if ($status === 'active') {
            $access = new learning_access_service();
            $cohortid = $access->ensure_cohort(
                $tenant,
                $classkey,
                $this->class_name($year, $medium, $grade, $stream, $division),
                'Year-scoped class cohort managed by Tenant Master.',
            );
            $access->add_cohort_member($tenant, $cohortid, $userid);
            $studentroleid = (int)$DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
            $courseids = $this->applicable_course_ids(
                $tenant,
                $year,
                $board,
                $medium,
                $grade,
                $stream,
                $division,
            );
            foreach ($courseids as $courseid) {
                $groupid = $access->ensure_group(
                    $tenant,
                    $courseid,
                    $classkey,
                    $this->class_name($year, $medium, $grade, $stream, $division),
                );
                $groupids[$courseid] = $groupid;
                $enrolmentids[$courseid] = $access->ensure_cohort_enrolment(
                    $tenant,
                    $courseid,
                    $cohortid,
                    $studentroleid,
                    $groupid,
                );
            }
        }

        $now = time();
        $record = (object)[
            'tenantid' => (int)$tenant->id,
            'userid' => $userid,
            'acadyearid' => (int)$year->id,
            'externalid' => $current->externalid ?? $this->placement_external_id($userid, $year),
            'boardid' => (int)($board->id ?? 0),
            'mediumid' => (int)($medium->id ?? 0),
            'gradeid' => (int)$grade->id,
            'streamid' => (int)($stream->id ?? 0),
            'divisionid' => (int)$division->id,
            'cohortid' => $cohortid,
            'rollnumber' => trim((string)($data->rollnumber ?? '')),
            'status' => $status,
            'startdate' => (int)($data->startdate ?? $year->startdate),
            'enddate' => (int)($data->enddate ?? 0),
            'payloadjson' => json::encode([
                'courseids' => $courseids,
                'groupids' => $groupids,
                'cohortenrolmentids' => $enrolmentids,
            ]),
            'modifiedby' => (int)($USER->id ?? 0),
            'timemodified' => $now,
        ];
        if ($current) {
            $record->id = $current->id;
            $DB->update_record('local_tenantmaster_placement', $record);
        } else {
            $record->createdby = (int)($USER->id ?? 0);
            $record->timecreated = $now;
            $record->id = $DB->insert_record('local_tenantmaster_placement', $record);
        }

        if (
            $current && (int)$current->cohortid > 0 && (int)$current->cohortid !== $cohortid
                && (int)$current->acadyearid === (int)$year->id
        ) {
            (new learning_access_service())->remove_cohort_member(
                $tenant,
                (int)$current->cohortid,
                $userid,
            );
        }
        (new audit_service())->record(
            (int)$tenant->id,
            'school.placement.saved',
            'success',
            ['status' => $status, 'coursecount' => count($courseids)],
            [
                'entitytable' => 'local_tenantmaster_placement',
                'entityid' => (int)$record->id,
                'targetcomponent' => 'core/cohort',
                'targetid' => $cohortid,
            ],
        );
        $saved = $DB->get_record('local_tenantmaster_placement', ['id' => $record->id], '*', MUST_EXIST);
        $saved->provisionedcourses = count($courseids);
        return $saved;
    }

    /**
     * Return year-specific subject courses matching the placement hierarchy.
     *
     * A subject without a grade ancestor is deliberately excluded from
     * automatic school enrolment; it can still be assigned manually.
     *
     * @return int[]
     */
    private function applicable_course_ids(
        object $tenant,
        object $year,
        ?object $board,
        ?object $medium,
        object $grade,
        ?object $stream,
        object $division,
    ): array {
        global $DB;

        $selected = array_filter([
            'board' => $board,
            'medium' => $medium,
            'grade' => $grade,
            'stream' => $stream,
            'division' => $division,
        ]);
        $subjects = $DB->get_records('local_tenantmaster_master', [
            'tenantid' => $tenant->id,
            'acadyearid' => $year->id,
            'mastertype' => 'subject',
            'active' => 1,
        ]);
        $courseids = [];
        foreach ($subjects as $subject) {
            $constraints = [];
            $parentid = (int)$subject->parentid;
            while ($parentid > 0) {
                $parent = $DB->get_record('local_tenantmaster_master', [
                    'id' => $parentid,
                    'tenantid' => $tenant->id,
                ]);
                if (!$parent) {
                    break;
                }
                if (
                    in_array(
                        (string)$parent->mastertype,
                        ['board', 'medium', 'grade', 'stream', 'division'],
                        true,
                    )
                ) {
                    $constraints[(string)$parent->mastertype] = $parent;
                }
                $parentid = (int)$parent->parentid;
            }
            if (!isset($constraints['grade'])) {
                continue;
            }
            $matches = true;
            foreach ($constraints as $type => $constraint) {
                if (
                    !isset($selected[$type])
                        || $this->identity($constraint) !== $this->identity($selected[$type])
                ) {
                    $matches = false;
                    break;
                }
            }
            if (!$matches) {
                continue;
            }
            $courseid = (int)$DB->get_field('local_tenantmaster_mapping', 'targetid', [
                'tenantid' => $tenant->id,
                'masterid' => $subject->id,
                'component' => 'core/course',
                'status' => 'synced',
            ]);
            if (
                $courseid > 0 && $DB->record_exists('local_iomad_company_courses', [
                'companyid' => $tenant->companyid,
                'courseid' => $courseid,
                ])
            ) {
                $courseids[] = $courseid;
            }
        }
        sort($courseids);
        return array_values(array_unique($courseids));
    }

    /**
     * Resolve one tenant master and validate its optional year scope.
     */
    private function master(object $tenant, int $id, string $type, object $year): object {
        global $DB;

        $master = $DB->get_record('local_tenantmaster_master', [
            'id' => $id,
            'tenantid' => $tenant->id,
            'mastertype' => $type,
            'active' => 1,
        ], '*', MUST_EXIST);
        if ((int)$master->acadyearid !== 0 && (int)$master->acadyearid !== (int)$year->id) {
            throw new \invalid_parameter_exception('Academic selection belongs to another year.');
        }
        return $master;
    }

    /**
     * Resolve an optional tenant master.
     */
    private function optional_master(object $tenant, int $id, string $type, object $year): ?object {
        return $id > 0 ? $this->master($tenant, $id, $type, $year) : null;
    }

    /**
     * Stable comparison identity retained across annual master copies.
     */
    private function identity(object $master): string {
        $payload = json::decode_object((string)$master->payloadjson);
        return (string)($payload['_tenantmaster_source_externalid'] ?? $master->externalid);
    }

    /**
     * Stable class key.
     */
    private function class_key(
        object $year,
        ?object $board,
        ?object $medium,
        object $grade,
        ?object $stream,
        object $division,
    ): string {
        $parts = [
            $year->externalid,
            $board->code ?? 'NOBOARD',
            $medium->code ?? 'NOMEDIUM',
            $grade->code,
            $stream->code ?? 'NOSTREAM',
            $division->code,
        ];
        $key = implode(':', array_map(
            static fn(string $part): string => preg_replace('/[^A-Za-z0-9._:-]/', '_', $part),
            $parts,
        ));
        return strlen($key) <= 100
            ? $key
            : substr($key, 0, 67) . ':' . substr(hash('sha256', $key), 0, 32);
    }

    /**
     * Human-readable class name.
     */
    private function class_name(
        object $year,
        ?object $medium,
        object $grade,
        ?object $stream,
        object $division,
    ): string {
        return implode(' - ', array_filter([
            $year->name,
            $medium->name ?? '',
            $grade->name,
            $stream->name ?? '',
            $division->name,
        ]));
    }

    /**
     * Immutable placement external ID.
     */
    private function placement_external_id(int $userid, object $year): string {
        $value = 'STUDENT_' . $userid . ':' . $year->externalid;
        return strlen($value) <= 100
            ? $value
            : substr($value, 0, 67) . ':' . substr(hash('sha256', $value), 0, 32);
    }
}
