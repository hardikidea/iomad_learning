<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\local;

/**
 * Academic master application service.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class master_service {
    /**
     * Constructor.
     *
     * @param master_repository $repository Repository.
     * @param queue_service $queue Queue.
     * @param audit_service $audit Audit.
     */
    public function __construct(
        private readonly master_repository $repository = new master_repository(),
        private readonly queue_service $queue = new queue_service(),
        private readonly audit_service $audit = new audit_service(),
    ) {
    }

    /**
     * Save an academic master and enqueue native projections.
     *
     * @param object $data Data.
     * @return object
     */
    public function save(object $data): object {
        if (!array_key_exists((string)$data->mastertype, catalog::MASTER_TYPES)) {
            throw new \invalid_parameter_exception('Invalid academic master type.');
        }
        if (
            !catalog::valid_external_key((string)$data->externalid)
                || !catalog::valid_external_key((string)$data->code)
        ) {
            throw new \invalid_parameter_exception('Invalid stable code or external ID.');
        }
        $data->payloadjson = $data->payloadjson ?: '{}';
        json::decode_object($data->payloadjson);
        $record = $this->repository->save($data);

        foreach ($this->modules_for_type((string)$record->mastertype) as $module) {
            $this->queue->mark_dirty(
                (int)$record->tenantid,
                $module,
                'local_tenantmaster_master',
                (int)$record->id,
                'master_saved',
            );
            if (in_array($module, ['assessments', 'attendance', 'certificates'], true)) {
                $this->queue->queue_company_courses((int)$record->tenantid, $module, 'policy_saved');
            }
        }
        $this->audit->record(
            (int)$record->tenantid,
            'academic.master.saved',
            'success',
            ['mastertype' => $record->mastertype, 'externalid' => $record->externalid],
            ['entitytable' => 'local_tenantmaster_master', 'entityid' => (int)$record->id],
        );
        return $record;
    }

    /**
     * Determine projection modules for a type.
     *
     * @param string $mastertype Type.
     * @return string[]
     */
    private function modules_for_type(string $mastertype): array {
        return match ($mastertype) {
            'assessment_policy' => ['assessments'],
            'attendance_policy' => ['attendance'],
            'certificate_rule' => ['certificates'],
            'progression_rule' => ['progression'],
            'course_template' => ['courses'],
            'subject', 'grade', 'programme', 'semester', 'stream', 'specialisation',
                'division', 'board', 'medium', 'credit' => ['academic', 'categories', 'courses'],
            default => ['academic'],
        };
    }
}
