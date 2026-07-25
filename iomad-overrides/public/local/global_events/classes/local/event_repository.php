<?php
// This file is part of Moodle - http://moodle.org/

namespace local_global_events\local;

/**
 * Tenant-aware global event repository.
 *
 * @package local_global_events
 */
final class event_repository {
    /**
     * Create or update one event and its company allowlist.
     *
     * @param tenant_scope $scope Owning scope.
     * @param array $data Event data.
     * @param int[] $companyids Allowed companies.
     * @param int $actorid Actor.
     * @return object
     */
    public function upsert(tenant_scope $scope, array $data, array $companyids, int $actorid): object {
        global $DB;

        $idnumber = trim((string)($data['idnumber'] ?? ''));
        $name = trim((string)($data['name'] ?? ''));
        $visibility = (string)($data['visibility'] ?? 'companies');
        $status = (string)($data['status'] ?? 'draft');
        $courseid = (int)($data['courseid'] ?? 0);
        $timestart = (int)($data['timestart'] ?? 0);
        $timeend = (int)($data['timeend'] ?? 0);
        if (
            !preg_match('/^[A-Za-z0-9_.:-]{3,100}$/', $idnumber)
            || $name === ''
            || !in_array($visibility, ['all', 'companies'], true)
            || !in_array($status, ['draft', 'published', 'cancelled'], true)
            || $courseid < 0
            || $timestart < 0
            || $timeend < 0
            || ($timeend > 0 && $timeend <= $timestart)
        ) {
            throw new \invalid_parameter_exception('Invalid global event definition.');
        }
        if ($courseid > 0 && !$scope->contains_course($courseid)) {
            throw new \invalid_parameter_exception('The event course is outside the owner company.');
        }
        $companyids = array_values(array_unique(array_map('intval', $companyids)));
        if ($visibility === 'companies' && !$companyids) {
            throw new \invalid_parameter_exception('A company-visible event requires at least one company.');
        }
        foreach ($companyids as $companyid) {
            if (!$DB->record_exists('local_iomad_companies', ['id' => $companyid])) {
                throw new \invalid_parameter_exception('The event company allowlist is invalid.');
            }
        }
        $transaction = $DB->start_delegated_transaction();
        $record = $DB->get_record('local_ge_event', ['idnumber' => $idnumber]);
        $now = time();
        $values = (object)[
            'idnumber' => $idnumber,
            'name' => mb_substr($name, 0, 255),
            'description' => clean_text((string)($data['description'] ?? ''), FORMAT_PLAIN),
            'ownercompanyid' => $scope->companyid(),
            'courseid' => $courseid,
            'visibility' => $visibility,
            'status' => $status,
            'timestart' => $timestart,
            'timeend' => $timeend,
            'createdby' => $actorid,
            'timemodified' => $now,
        ];
        if ($record) {
            if ((int)$record->ownercompanyid !== $scope->companyid()) {
                throw new \invalid_parameter_exception('The event ID belongs to another company.');
            }
            $values->id = $record->id;
            $values->timecreated = $record->timecreated;
            $DB->update_record('local_ge_event', $values);
        } else {
            $values->timecreated = $now;
            $values->id = $DB->insert_record('local_ge_event', $values);
        }
        $DB->delete_records('local_ge_event_company', ['eventid' => $values->id]);
        if ($visibility === 'companies') {
            foreach ($companyids as $companyid) {
                $DB->insert_record('local_ge_event_company', (object)[
                    'eventid' => $values->id,
                    'companyid' => $companyid,
                ]);
            }
        }
        $transaction->allow_commit();
        return $DB->get_record('local_ge_event', ['id' => $values->id], '*', MUST_EXIST);
    }

    /**
     * Published events visible to one company.
     *
     * @param tenant_scope $scope Scope.
     * @param int $limit Limit.
     * @return object[]
     */
    public function available(tenant_scope $scope, int $limit = 50): array {
        global $DB;

        $now = time();
        $sql = "SELECT e.*
                  FROM {local_ge_event} e
                 WHERE e.status = :status
                   AND (e.timestart = 0 OR e.timestart <= :nowstart)
                   AND (e.timeend = 0 OR e.timeend >= :nowend)
                   AND (
                       e.visibility = :allvisibility
                       OR e.ownercompanyid = :ownercompanyid
                       OR EXISTS (
                           SELECT 1
                             FROM {local_ge_event_company} ec
                            WHERE ec.eventid = e.id
                              AND ec.companyid = :companyid
                       )
                   )
              ORDER BY e.timestart ASC, e.id ASC";
        return array_values($DB->get_records_sql($sql, [
            'status' => 'published',
            'nowstart' => $now,
            'nowend' => $now,
            'allvisibility' => 'all',
            'ownercompanyid' => $scope->companyid(),
            'companyid' => $scope->companyid(),
        ], 0, max(1, min(100, $limit))));
    }

    /**
     * Require one event visible to the company.
     *
     * @param tenant_scope $scope Scope.
     * @param int $eventid Event.
     * @return object
     */
    public function get_visible(tenant_scope $scope, int $eventid): object {
        foreach ($this->available($scope, 100) as $event) {
            if ((int)$event->id === $eventid) {
                return $event;
            }
        }
        throw new \required_capability_exception(
            \context_system::instance(),
            'local/global_events:view',
            'nopermissions',
            '',
        );
    }
}
