<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\local;

use local_tenantmaster\task\propagate_catalogue_item;

/**
 * Global master catalogue and tenant-safe inheritance.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class catalogue_service {
    /** @var array<string, string> */
    public const SCOPES = [
        'shared' => 'cataloguescope_shared',
        'school' => 'tenanttype_school',
        'university' => 'tenanttype_university',
        'college' => 'tenanttype_college',
        'training' => 'tenanttype_training',
    ];

    /**
     * Constructor.
     *
     * @param catalogue_repository $repository Repository.
     */
    public function __construct(
        private readonly catalogue_repository $repository = new catalogue_repository(),
    ) {
    }

    /**
     * Seed the editable catalogue from reviewed built-in defaults.
     */
    public function ensure_seeded(): void {
        $defaults = new default_service();
        foreach (array_keys(self::SCOPES) as $scope) {
            foreach ($defaults->builtin_scope_items($scope) as $sortorder => $item) {
                if ($this->repository->find($scope, (string)$item['type'], (string)$item['externalid'])) {
                    continue;
                }
                $payloadjson = json::encode($item['payload'] ?? []);
                $record = (object)[
                    'scope' => $scope,
                    'mastertype' => $item['type'],
                    'externalid' => $item['externalid'],
                    'code' => $item['code'],
                    'name' => $item['name'],
                    'description' => $item['description'] ?? null,
                    'payloadjson' => $payloadjson,
                    'parentexternalid' => $item['parentexternalid'] ?? null,
                    'active' => 1,
                    'sortorder' => $sortorder + 1,
                    'version' => 1,
                    'propagationstate' => 'complete',
                    'propagationjson' => json::encode([
                        'created' => 0,
                        'updated' => 0,
                        'unchanged' => 0,
                        'conflicts' => 0,
                    ]),
                    'lastpropagated' => 0,
                ];
                $record->managedhash = self::catalogue_hash($record);
                $this->repository->save($record);
            }
        }
    }

    /**
     * List catalogue items.
     *
     * @param string $scope Scope.
     * @param string $mastertype Type.
     * @return array<int, object>
     */
    public function list(string $scope = '', string $mastertype = ''): array {
        $this->ensure_seeded();
        return $this->repository->list($scope, $mastertype);
    }

    /**
     * Get an item.
     *
     * @param int $id Item ID.
     * @return object
     */
    public function get(int $id): object {
        $this->ensure_seeded();
        return $this->repository->get($id);
    }

    /**
     * Save a global template and enqueue safe propagation.
     *
     * @param object $data Submitted data.
     * @return object
     */
    public function save(object $data): object {
        global $DB;

        $scope = (string)$data->scope;
        $mastertype = (string)$data->mastertype;
        if (!array_key_exists($scope, self::SCOPES)) {
            throw new \invalid_parameter_exception('Invalid catalogue scope.');
        }
        if (!array_key_exists($mastertype, catalog::MASTER_TYPES)) {
            throw new \invalid_parameter_exception('Invalid academic master type.');
        }
        if (
            !catalog::valid_external_key((string)$data->externalid)
                || !catalog::valid_external_key((string)$data->code)
        ) {
            throw new \invalid_parameter_exception('Invalid stable code or external ID.');
        }
        $payloadjson = trim((string)($data->payloadjson ?? '{}')) ?: '{}';
        json::decode_object($payloadjson);

        $current = !empty($data->id) ? $this->repository->get((int)$data->id) : null;
        if ($current && (
            (string)$current->scope !== $scope
                || (string)$current->mastertype !== $mastertype
                || (string)$current->externalid !== (string)$data->externalid
                || (string)$current->code !== (string)$data->code
        )) {
            throw new \invalid_parameter_exception(
                'Scope, master type, external ID and code cannot change after creation.',
            );
        }

        $duplicateparams = [
            'scope' => $scope,
            'mastertype' => $mastertype,
        ];
        $duplicatesql = 'scope = :scope AND mastertype = :mastertype'
            . ' AND (externalid = :externalid OR code = :code)';
        $duplicateparams['externalid'] = (string)$data->externalid;
        $duplicateparams['code'] = (string)$data->code;
        if ($current) {
            $duplicatesql .= ' AND id <> :id';
            $duplicateparams['id'] = (int)$current->id;
        }
        if ($DB->record_exists_select('local_tenantmaster_catitem', $duplicatesql, $duplicateparams)) {
            throw new \invalid_parameter_exception('Catalogue external ID and code must be unique in the scope.');
        }

        $parentexternalid = null;
        $parentitemid = (int)($data->parentitemid ?? 0);
        if ($parentitemid > 0) {
            $parent = $this->repository->get($parentitemid);
            if (
                (string)$parent->scope !== $scope
                    || (string)$parent->mastertype !== $mastertype
                    || ($current && (int)$parent->id === (int)$current->id)
            ) {
                throw new \invalid_parameter_exception('Catalogue parent must be in the same scope and master type.');
            }
            $this->require_acyclic_parent($current ? (int)$current->id : 0, $parent);
            $parentexternalid = (string)$parent->externalid;
        }

        $record = (object)[
            'id' => $current->id ?? 0,
            'scope' => $scope,
            'mastertype' => $mastertype,
            'externalid' => (string)$data->externalid,
            'code' => (string)$data->code,
            'name' => trim((string)$data->name),
            'description' => trim((string)($data->description ?? '')) ?: null,
            'payloadjson' => $payloadjson,
            'parentexternalid' => $parentexternalid,
            'active' => !empty($data->active) ? 1 : 0,
            'sortorder' => max(0, (int)($data->sortorder ?? 0)),
            'propagationstate' => 'queued',
            'propagationjson' => json::encode([]),
            'lastpropagated' => (int)($current->lastpropagated ?? 0),
        ];
        $record->managedhash = self::catalogue_hash($record);
        $saved = $this->repository->save($record);
        $this->queue_propagation((int)$saved->id);
        return $saved;
    }

    /**
     * Set active state through the same propagation pipeline.
     *
     * @param int $id Item ID.
     * @param bool $active Active state.
     * @return object
     */
    public function set_active(int $id, bool $active): object {
        $current = $this->get($id);
        return $this->save((object)[
            'id' => $current->id,
            'scope' => $current->scope,
            'mastertype' => $current->mastertype,
            'externalid' => $current->externalid,
            'code' => $current->code,
            'name' => $current->name,
            'description' => $current->description,
            'payloadjson' => $current->payloadjson,
            'parentitemid' => $this->parent_item_id($current),
            'active' => $active ? 1 : 0,
            'sortorder' => $current->sortorder,
        ]);
    }

    /**
     * Return active catalogue items in default-service array format.
     *
     * @param string $tenanttype Tenant type.
     * @return array<int, array<string, mixed>>
     */
    public function default_items_for_tenant(string $tenanttype): array {
        $this->ensure_seeded();
        $items = [];
        foreach ($this->repository->applicable($tenanttype) as $record) {
            $items[] = [
                'type' => $record->mastertype,
                'externalid' => $record->externalid,
                'code' => $record->code,
                'name' => $record->name,
                'description' => $record->description,
                'payload' => json::decode_object((string)$record->payloadjson),
                'parentexternalid' => $record->parentexternalid,
                'catalogitemid' => (int)$record->id,
                'catalogversion' => (int)$record->version,
                'inheritedhash' => (string)$record->managedhash,
                'sortorder' => (int)$record->sortorder,
            ];
        }
        return $items;
    }

    /**
     * Version identifier for one institution type.
     *
     * @param string $tenanttype Tenant type.
     * @return string
     */
    public function version_for_tenant(string $tenanttype): string {
        return 'cat-' . substr(json::hash($this->default_items_for_tenant($tenanttype)), 0, 16);
    }

    /**
     * Propagate one item to all applicable tenants.
     *
     * Tenant edits are detected by comparing the current record with the last
     * inherited hash. Conflicts are never overwritten.
     *
     * @param int $catalogitemid Catalogue item ID.
     * @return array{created: int, updated: int, unchanged: int, conflicts: int}
     */
    public function propagate(int $catalogitemid): array {
        global $DB;

        $factory = \core\lock\lock_config::get_lock_factory('local_tenantmaster');
        $lock = $factory->get_lock('catalogue:' . $catalogitemid, 0);
        if (!$lock) {
            throw new \moodle_exception('cataloguepropagationlocked', 'local_tenantmaster');
        }
        try {
            $item = $this->repository->get($catalogitemid);
            $item->propagationstate = 'running';
            $item->propagationjson = json::encode([]);
            $item->timemodified = time();
            $DB->update_record('local_tenantmaster_catitem', $item);

            $result = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'conflicts' => 0];
            $tenants = (string)$item->scope === 'shared'
                ? $DB->get_records('local_tenantmaster_tenant')
                : $DB->get_records('local_tenantmaster_tenant', ['tenanttype' => $item->scope]);
            foreach ($tenants as $tenant) {
                $this->propagate_to_tenant($item, $tenant, $result);
            }

            $item = $this->repository->get($catalogitemid);
            $item->propagationstate = 'complete';
            $item->propagationjson = json::encode($result);
            $item->lastpropagated = time();
            $item->timemodified = time();
            $DB->update_record('local_tenantmaster_catitem', $item);
            return $result;
        } catch (\Throwable $exception) {
            $DB->set_field('local_tenantmaster_catitem', 'propagationstate', 'failed', ['id' => $catalogitemid]);
            throw $exception;
        } finally {
            $lock->release();
        }
    }

    /**
     * Link legacy built-in copies when they still exactly match the catalogue.
     */
    public function link_existing_inherited_records(): void {
        global $DB;

        $this->ensure_seeded();
        foreach ($DB->get_records('local_tenantmaster_tenant') as $tenant) {
            foreach ($this->repository->applicable((string)$tenant->tenanttype) as $item) {
                $master = $DB->get_record('local_tenantmaster_master', [
                    'tenantid' => $tenant->id,
                    'mastertype' => $item->mastertype,
                    'externalid' => $item->externalid,
                ]);
                if (!$master || (int)$master->catalogitemid > 0) {
                    continue;
                }
                if (self::master_hash($master) !== (string)$item->managedhash) {
                    continue;
                }
                $master->catalogitemid = (int)$item->id;
                $master->catalogversion = (int)$item->version;
                $master->inheritedhash = (string)$item->managedhash;
                $master->timemodified = time();
                $DB->update_record('local_tenantmaster_master', $master);
            }
        }
    }

    /**
     * Stable managed-field hash for a catalogue item.
     *
     * @param object $item Item.
     * @return string
     */
    public static function catalogue_hash(object $item): string {
        return json::hash([
            'mastertype' => (string)$item->mastertype,
            'externalid' => (string)$item->externalid,
            'code' => (string)$item->code,
            'name' => (string)$item->name,
            'description' => (string)($item->description ?? ''),
            'payload' => json::decode_object((string)($item->payloadjson ?? '{}')),
            'parentexternalid' => (string)($item->parentexternalid ?? ''),
            'active' => (int)$item->active,
            'sortorder' => (int)$item->sortorder,
        ]);
    }

    /**
     * Stable managed-field hash for an inherited tenant record.
     *
     * @param object $master Master.
     * @return string
     */
    public static function master_hash(object $master): string {
        global $DB;

        $parentexternalid = '';
        if ((int)$master->parentid > 0) {
            $parentexternalid = (string)$DB->get_field(
                'local_tenantmaster_master',
                'externalid',
                ['id' => $master->parentid, 'tenantid' => $master->tenantid],
            );
        }
        return json::hash([
            'mastertype' => (string)$master->mastertype,
            'externalid' => (string)$master->externalid,
            'code' => (string)$master->code,
            'name' => (string)$master->name,
            'description' => (string)($master->description ?? ''),
            'payload' => json::decode_object((string)($master->payloadjson ?? '{}')),
            'parentexternalid' => $parentexternalid,
            'active' => (int)$master->active,
            'sortorder' => (int)$master->sortorder,
        ]);
    }

    /**
     * Propagate to one tenant.
     *
     * @param object $item Catalogue item.
     * @param object $tenant Tenant.
     * @param array<string, int> $result Result accumulator.
     */
    private function propagate_to_tenant(object $item, object $tenant, array &$result): void {
        global $DB;

        $master = $DB->get_record('local_tenantmaster_master', [
            'tenantid' => $tenant->id,
            'mastertype' => $item->mastertype,
            'externalid' => $item->externalid,
        ]);
        $parentid = $this->tenant_parent_id($tenant, $item, $result);
        if (!$master) {
            $master = (new master_repository())->save((object)[
                'tenantid' => $tenant->id,
                'acadyearid' => 0,
                'parentid' => $parentid,
                'mastertype' => $item->mastertype,
                'externalid' => $item->externalid,
                'code' => $item->code,
                'name' => $item->name,
                'description' => $item->description,
                'payloadjson' => $item->payloadjson,
                'active' => $item->active,
                'sortorder' => $item->sortorder,
                'catalogitemid' => $item->id,
                'catalogversion' => $item->version,
                'inheritedhash' => $item->managedhash,
            ]);
            (new queue_service())->sync_master((int)$tenant->id, (int)$master->id, 'catalogue_created');
            $result['created']++;
            $this->audit_propagation($tenant, $item, $master, 'created');
            return;
        }

        $currenthash = self::master_hash($master);
        if ($currenthash === (string)$item->managedhash) {
            if (
                (int)$master->catalogitemid !== (int)$item->id
                    || (int)$master->catalogversion !== (int)$item->version
                    || (string)$master->inheritedhash !== (string)$item->managedhash
            ) {
                $master->catalogitemid = (int)$item->id;
                $master->catalogversion = (int)$item->version;
                $master->inheritedhash = (string)$item->managedhash;
                $master->timemodified = time();
                $DB->update_record('local_tenantmaster_master', $master);
            }
            $result['unchanged']++;
            return;
        }

        $safe = (int)$master->catalogitemid === (int)$item->id
            && (string)$master->inheritedhash !== ''
            && $currenthash === (string)$master->inheritedhash;
        if (!$safe) {
            $result['conflicts']++;
            $this->audit_propagation($tenant, $item, $master, 'conflict');
            return;
        }

        $master->parentid = $parentid;
        $master->name = $item->name;
        $master->description = $item->description;
        $master->payloadjson = $item->payloadjson;
        $master->active = $item->active;
        $master->sortorder = $item->sortorder;
        $master->catalogversion = $item->version;
        $master->inheritedhash = $item->managedhash;
        $saved = (new master_repository())->save($master);
        (new queue_service())->sync_master((int)$tenant->id, (int)$saved->id, 'catalogue_updated');
        $result['updated']++;
        $this->audit_propagation($tenant, $item, $saved, 'updated');
    }

    /**
     * Resolve an applicable parent within one tenant.
     *
     * @param object $tenant Tenant.
     * @param object $item Item.
     * @param array<string, int> $result Result accumulator.
     * @return int
     */
    private function tenant_parent_id(object $tenant, object $item, array &$result): int {
        global $DB;

        if (empty($item->parentexternalid)) {
            return 0;
        }
        $parentid = (int)$DB->get_field('local_tenantmaster_master', 'id', [
            'tenantid' => $tenant->id,
            'mastertype' => $item->mastertype,
            'externalid' => $item->parentexternalid,
        ]);
        if ($parentid > 0) {
            return $parentid;
        }
        $parent = $this->repository->find(
            (string)$item->scope,
            (string)$item->mastertype,
            (string)$item->parentexternalid,
        );
        if ($parent) {
            $this->propagate_to_tenant($parent, $tenant, $result);
            return (int)$DB->get_field('local_tenantmaster_master', 'id', [
                'tenantid' => $tenant->id,
                'mastertype' => $item->mastertype,
                'externalid' => $item->parentexternalid,
            ]);
        }
        return 0;
    }

    /**
     * Resolve catalogue parent ID.
     *
     * @param object $item Item.
     * @return int
     */
    private function parent_item_id(object $item): int {
        if (empty($item->parentexternalid)) {
            return 0;
        }
        $parent = $this->repository->find(
            (string)$item->scope,
            (string)$item->mastertype,
            (string)$item->parentexternalid,
        );
        return (int)($parent->id ?? 0);
    }

    /**
     * Prevent catalogue hierarchy cycles.
     *
     * @param int $itemid Item being edited.
     * @param object $parent Proposed parent.
     */
    private function require_acyclic_parent(int $itemid, object $parent): void {
        $visited = [];
        while ($parent) {
            if ((int)$parent->id === $itemid || isset($visited[$parent->id])) {
                throw new \invalid_parameter_exception('Catalogue hierarchy cannot contain a cycle.');
            }
            $visited[$parent->id] = true;
            if (empty($parent->parentexternalid)) {
                return;
            }
            $parent = $this->repository->find(
                (string)$parent->scope,
                (string)$parent->mastertype,
                (string)$parent->parentexternalid,
            );
        }
    }

    /**
     * Queue one debounced propagation task.
     *
     * @param int $catalogitemid Item ID.
     */
    private function queue_propagation(int $catalogitemid): void {
        $task = new propagate_catalogue_item();
        $task->set_custom_data((object)['catalogitemid' => $catalogitemid]);
        $task->set_next_run_time(time() + 2);
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * Record tenant-level propagation evidence.
     *
     * @param object $tenant Tenant.
     * @param object $item Catalogue item.
     * @param object $master Master.
     * @param string $result Result.
     */
    private function audit_propagation(object $tenant, object $item, object $master, string $result): void {
        (new audit_service())->record(
            (int)$tenant->id,
            'catalogue.item.propagated',
            $result,
            [
                'scope' => $item->scope,
                'mastertype' => $item->mastertype,
                'externalid' => $item->externalid,
                'catalogversion' => (int)$item->version,
            ],
            ['entitytable' => 'local_tenantmaster_master', 'entityid' => (int)$master->id],
        );
    }
}
