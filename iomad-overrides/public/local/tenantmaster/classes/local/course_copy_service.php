<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\local;

/**
 * User-free content copy between tenant-owned native Moodle courses.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_copy_service {
    /**
     * Copy activities, blocks and filters without users or historical outcomes.
     */
    public function copy(object $tenant, int $sourcecourseid, int $targetcourseid, bool $replace = false): object {
        global $CFG, $DB, $USER;

        if ($sourcecourseid === $targetcourseid) {
            throw new \invalid_parameter_exception('Source and target courses must differ.');
        }
        foreach ([$sourcecourseid, $targetcourseid] as $courseid) {
            if (
                !$DB->record_exists('local_iomad_company_courses', [
                'companyid' => $tenant->companyid,
                'courseid' => $courseid,
                ])
            ) {
                throw new \invalid_parameter_exception('Both courses must belong to the selected tenant.');
            }
        }
        if (
            $replace && $DB->record_exists_sql(
                "SELECT 1
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = :courseid",
                ['courseid' => $targetcourseid],
            )
        ) {
            throw new \invalid_parameter_exception('A course with enrolments cannot have its content replaced.');
        }
        $source = $DB->get_record('course', ['id' => $sourcecourseid], '*', MUST_EXIST);
        $sourcehash = hash('sha256', implode(':', [
            $sourcecourseid,
            $source->timemodified,
            $DB->count_records('course_modules', ['course' => $sourcecourseid]),
        ]));
        $existing = $DB->get_record('local_tenantmaster_crscopy', [
            'tenantid' => $tenant->id,
            'targetcourseid' => $targetcourseid,
        ]);
        if ($existing && $existing->status === 'completed' && $existing->sourcehash === $sourcehash) {
            return $existing;
        }
        if ($existing && $existing->status === 'completed' && !$replace) {
            throw new \invalid_parameter_exception('Target content was already copied; explicitly replace an empty target.');
        }

        $record = (object)[
            'tenantid' => (int)$tenant->id,
            'sourcecourseid' => $sourcecourseid,
            'targetcourseid' => $targetcourseid,
            'sourcehash' => $sourcehash,
            'status' => 'running',
            'message' => null,
            'createdby' => (int)($USER->id ?? 0),
            'timecreated' => time(),
            'timefinished' => 0,
        ];
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('local_tenantmaster_crscopy', $record);
        } else {
            $record->id = $DB->insert_record('local_tenantmaster_crscopy', $record);
        }

        require_once($CFG->dirroot . '/course/externallib.php');
        $originaluser = $USER;
        try {
            $USER = get_admin();
            \core_course_external::import_course(
                $sourcecourseid,
                $targetcourseid,
                $replace ? 1 : 0,
                [
                    ['name' => 'activities', 'value' => 1],
                    ['name' => 'blocks', 'value' => 1],
                    ['name' => 'filters', 'value' => 1],
                ],
            );
            $record->status = 'completed';
            $record->timefinished = time();
            $DB->update_record('local_tenantmaster_crscopy', $record);
        } catch (\Throwable $exception) {
            $record->status = 'failed';
            $record->message = substr($exception->getMessage(), 0, 2000);
            $record->timefinished = time();
            $DB->update_record('local_tenantmaster_crscopy', $record);
            throw $exception;
        } finally {
            $USER = $originaluser;
        }
        (new audit_service())->record(
            (int)$tenant->id,
            'courses.content.copied',
            'success',
            ['replace' => $replace],
            [
                'entitytable' => 'local_tenantmaster_crscopy',
                'entityid' => (int)$record->id,
                'targetcomponent' => 'core/course',
                'targetid' => $targetcourseid,
            ],
        );
        return $record;
    }
}
