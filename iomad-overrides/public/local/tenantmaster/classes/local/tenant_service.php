<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\local;

/**
 * Tenant profile application service.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tenant_service {
    /**
     * Constructor.
     *
     * @param tenant_repository $repository Repository.
     * @param queue_service $queue Queue.
     * @param audit_service $audit Audit.
     */
    public function __construct(
        private readonly tenant_repository $repository = new tenant_repository(),
        private readonly queue_service $queue = new queue_service(),
        private readonly audit_service $audit = new audit_service(),
    ) {
    }

    /**
     * Save validated tenant-owned fields and automatically synchronize.
     *
     * @param object $data Form data.
     * @return object
     */
    public function save(object $data): object {
        if (!array_key_exists((string)$data->tenanttype, catalog::TENANT_TYPES)) {
            throw new \invalid_parameter_exception('Invalid tenant type.');
        }
        if (!catalog::valid_external_key((string)$data->trustcode)) {
            throw new \invalid_parameter_exception('Invalid trust code.');
        }
        $record = $this->repository->save($data);
        $this->queue->mark_dirty((int)$record->id, 'tenant', 'local_tenantmaster_tenant', (int)$record->id, 'profile_saved');
        $this->audit->record(
            (int)$record->id,
            'tenant.profile.saved',
            'success',
            ['sourcehash' => $record->sourcehash],
            ['entitytable' => 'local_tenantmaster_tenant', 'entityid' => (int)$record->id],
        );
        return $record;
    }
}
