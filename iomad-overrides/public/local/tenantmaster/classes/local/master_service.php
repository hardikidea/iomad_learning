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
        global $DB;

        $tenantid = (int)$data->tenantid;
        $DB->get_record('local_tenantmaster_tenant', ['id' => $tenantid], '*', MUST_EXIST);
        if (!array_key_exists((string)$data->mastertype, catalog::MASTER_TYPES)) {
            throw new \invalid_parameter_exception('Invalid academic master type.');
        }
        if (
            !catalog::valid_external_key((string)$data->externalid)
                || !catalog::valid_external_key((string)$data->code)
        ) {
            throw new \invalid_parameter_exception('Invalid stable code or external ID.');
        }
        if (!empty($data->id)) {
            $current = $this->repository->get($tenantid, (int)$data->id);
            if (
                $current->mastertype !== (string)$data->mastertype
                    || $current->externalid !== (string)$data->externalid
                    || $current->code !== (string)$data->code
            ) {
                throw new \invalid_parameter_exception(
                    'Master type, external ID and code cannot change after creation.'
                );
            }
        }
        if (
            !empty($data->acadyearid)
                && !$DB->record_exists('local_tenantmaster_acadyear', [
                    'id' => (int)$data->acadyearid,
                    'tenantid' => $tenantid,
                ])
        ) {
            throw new \invalid_parameter_exception('Academic year belongs to another tenant.');
        }
        $this->require_valid_parent($tenantid, (int)($data->id ?? 0), (int)($data->parentid ?? 0));
        $data->payloadjson = $data->payloadjson ?? '{}';
        if ($data->payloadjson === '') {
            $data->payloadjson = '{}';
        }
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
     * Ensure a parent is tenant-owned and cannot introduce a hierarchy cycle.
     *
     * @param int $tenantid Tenant.
     * @param int $recordid Record being saved.
     * @param int $parentid Requested parent.
     */
    private function require_valid_parent(int $tenantid, int $recordid, int $parentid): void {
        if ($parentid <= 0) {
            return;
        }
        $visited = [];
        while ($parentid > 0) {
            if ($parentid === $recordid || isset($visited[$parentid])) {
                throw new \invalid_parameter_exception('Academic master hierarchy cannot contain a cycle.');
            }
            $visited[$parentid] = true;
            $parent = $this->repository->get($tenantid, $parentid);
            $parentid = (int)$parent->parentid;
        }
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
