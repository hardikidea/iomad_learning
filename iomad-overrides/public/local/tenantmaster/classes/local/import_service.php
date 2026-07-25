<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\local;

/**
 * Versioned, resumable and auditable in-plugin import pipeline.
 *
 * Packages contain manifest.json plus normalized CSV files. User rows carry
 * stable native IDs only; credentials and identity attributes are rejected.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class import_service {
    private const SCHEMA_VERSION = '1.0';

    /** @var array<string, string[]> */
    private const REQUIRED_COLUMNS = [
        'academic_years' => ['externalid', 'code', 'name', 'startdate', 'enddate', 'iscurrent'],
        'academic_masters' => ['mastertype', 'externalid', 'code', 'name'],
        'departments' => ['externalid', 'shortname', 'name', 'parent_shortname'],
        'cohorts' => ['externalid', 'name'],
        'cohort_members' => ['cohort_externalid', 'user_externalid'],
        'groups' => ['externalid', 'name', 'course_idnumber'],
        'group_members' => ['group_externalid', 'course_idnumber', 'user_externalid'],
        'user_roles' => ['user_externalid', 'rolekey', 'department_shortname', 'course_idnumber'],
        'guardian_links' => ['guardian_externalid', 'learner_externalid'],
    ];

    /** @var string[] */
    private const FORBIDDEN_COLUMNS = [
        'password',
        'newpassword',
        'token',
        'secret',
        'firstname',
        'lastname',
        'email',
        'phone',
        'address',
    ];

    /**
     * Inspect a package and persist an immutable normalized plan.
     *
     * @param object $tenant Tenant.
     * @param string $filename File name.
     * @param string $content ZIP bytes.
     * @param string $mode create_only, merge, update or deactivate_missing.
     * @return object Batch.
     */
    public function inspect(object $tenant, string $filename, string $content, string $mode): object {
        global $CFG, $DB, $USER;

        if (!in_array($mode, ['create_only', 'merge', 'update', 'deactivate_missing'], true)) {
            throw new \invalid_parameter_exception('Invalid import mode.');
        }
        if (!str_ends_with(strtolower($filename), '.zip')) {
            throw new \invalid_parameter_exception('Tenant Master imports must be ZIP packages.');
        }
        if (!class_exists(\ZipArchive::class)) {
            throw new \moodle_exception('ziprequired', 'local_tenantmaster');
        }
        $checksum = hash('sha256', $content);
        $existing = $DB->get_record('local_tenantmaster_batch', [
            'tenantid' => $tenant->id,
            'checksum' => $checksum,
        ]);
        if ($existing) {
            return $existing;
        }

        $path = tempnam($CFG->tempdir, 'tenantmaster-');
        if ($path === false) {
            throw new \moodle_exception('cannotcreatetempfile');
        }
        file_put_contents($path, $content, LOCK_EX);
        $zip = new \ZipArchive();
        try {
            if ($zip->open($path) !== true) {
                throw new \invalid_parameter_exception('Unable to open import ZIP.');
            }
            $manifestcontent = $zip->getFromName('manifest.json');
            if ($manifestcontent === false) {
                throw new \invalid_parameter_exception('manifest.json is required at the package root.');
            }
            $manifest = json::decode_object($manifestcontent);
            $this->validate_manifest($tenant, $manifest);
            $rows = [];
            $seen = [];
            foreach ($manifest['files'] as $file) {
                $pathinzip = (string)$file['path'];
                $entity = (string)$file['entity'];
                $csvcontent = $zip->getFromName($pathinzip);
                if ($csvcontent === false) {
                    throw new \invalid_parameter_exception('Manifest file is missing: ' . $pathinzip);
                }
                if (!hash_equals((string)$file['sha256'], hash('sha256', $csvcontent))) {
                    throw new \invalid_parameter_exception('Checksum mismatch: ' . $pathinzip);
                }
                $parsed = $this->parse_csv($entity, $csvcontent);
                if (count($parsed) !== (int)$file['rows']) {
                    throw new \invalid_parameter_exception('Row count mismatch: ' . $pathinzip);
                }
                foreach ($parsed as $rownumber => $row) {
                    $externalid = $this->row_externalid($entity, $row);
                    $duplicatekey = $entity . ':' . $externalid;
                    if (isset($seen[$duplicatekey])) {
                        throw new \invalid_parameter_exception('Duplicate external key in package: ' . $duplicatekey);
                    }
                    $seen[$duplicatekey] = true;
                    $rows[] = [
                        'filekey' => $pathinzip,
                        'rownumber' => $rownumber + 2,
                        'entitytype' => $entity,
                        'externalid' => $externalid,
                        'payload' => $row,
                    ];
                }
            }
            $maxrows = max(1, (int)(get_config('local_tenantmaster', 'importmaxrows') ?: 25000));
            if (count($rows) > $maxrows) {
                throw new \invalid_parameter_exception('Package exceeds the configured row limit.');
            }

            $transaction = $DB->start_delegated_transaction();
            $batchid = (int)$DB->insert_record('local_tenantmaster_batch', (object)[
                'tenantid' => $tenant->id,
                'schemaversion' => self::SCHEMA_VERSION,
                'mode' => $mode,
                'status' => 'inspected',
                'manifestjson' => json::encode($manifest),
                'checksum' => $checksum,
                'actorid' => (int)($USER->id ?? 0),
                'rowcount' => count($rows),
                'appliedcount' => 0,
                'errorcount' => 0,
                'timecreated' => time(),
                'timeapproved' => 0,
                'timefinished' => 0,
            ]);
            foreach ($rows as $row) {
                $DB->insert_record('local_tenantmaster_batchrow', (object)[
                    'batchid' => $batchid,
                    'filekey' => $row['filekey'],
                    'rownumber' => $row['rownumber'],
                    'entitytype' => $row['entitytype'],
                    'externalid' => $row['externalid'],
                    'checksum' => json::hash($row['payload']),
                    'status' => 'inspected',
                    'payloadjson' => json::encode($row['payload']),
                    'errorjson' => '{}',
                    'timecreated' => time(),
                    'timemodified' => time(),
                ]);
            }
            $transaction->allow_commit();
            return $this->plan($tenant, $batchid);
        } finally {
            if ($zip->status === \ZipArchive::ER_OK) {
                $zip->close();
            }
            @unlink($path);
        }
    }

    /**
     * Calculate a non-mutating row plan.
     *
     * @param object $tenant Tenant.
     * @param int $batchid Batch.
     * @return object Batch.
     */
    public function plan(object $tenant, int $batchid): object {
        global $DB;

        $batch = $DB->get_record('local_tenantmaster_batch', [
            'id' => $batchid,
            'tenantid' => $tenant->id,
        ], '*', MUST_EXIST);
        $errors = 0;
        foreach ($DB->get_records('local_tenantmaster_batchrow', ['batchid' => $batchid], 'id') as $row) {
            try {
                $payload = json::decode_object($row->payloadjson);
                $this->assert_dependencies($tenant, $batchid, (string)$row->entitytype, $payload);
                $action = $this->planned_action($tenant, (string)$row->entitytype, $payload, (string)$batch->mode);
                $row->status = 'planned';
                $row->errorjson = json::encode(['action' => $action]);
            } catch (\Throwable $exception) {
                $errors++;
                $row->status = 'error';
                $row->errorjson = json::encode([
                    'code' => get_class($exception),
                    'message' => $exception->getMessage(),
                ]);
            }
            $row->timemodified = time();
            $DB->update_record('local_tenantmaster_batchrow', $row);
        }
        $batch->status = $errors ? 'invalid' : 'planned';
        $batch->errorcount = $errors;
        $DB->update_record('local_tenantmaster_batch', $batch);
        return $batch;
    }

    /**
     * Apply or resume a planned package.
     *
     * @param object $tenant Tenant.
     * @param int $batchid Batch.
     * @return object Batch.
     */
    public function apply(object $tenant, int $batchid): object {
        global $DB, $USER;

        $batch = $DB->get_record('local_tenantmaster_batch', [
            'id' => $batchid,
            'tenantid' => $tenant->id,
        ], '*', MUST_EXIST);
        if (!in_array($batch->status, ['planned', 'applying', 'completed_with_errors'], true)) {
            throw new \invalid_parameter_exception('Only a valid planned batch can be applied.');
        }
        $batch->status = 'applying';
        $batch->timeapproved = $batch->timeapproved ?: time();
        $batch->actorid = (int)($USER->id ?? 0);
        $DB->update_record('local_tenantmaster_batch', $batch);
        $applied = 0;
        $errors = 0;
        $rows = $DB->get_records_select(
            'local_tenantmaster_batchrow',
            'batchid = :batchid AND status <> :applied',
            ['batchid' => $batchid, 'applied' => 'applied'],
            'id',
        );
        $order = array_flip([
            'academic_years',
            'academic_masters',
            'departments',
            'cohorts',
            'groups',
            'cohort_members',
            'group_members',
            'user_roles',
            'guardian_links',
        ]);
        $departmentparents = [];
        $masterparents = [];
        foreach ($rows as $candidate) {
            $payload = json::decode_object((string)$candidate->payloadjson);
            if ($candidate->entitytype === 'departments') {
                $departmentparents[(string)$payload['shortname']] = (string)$payload['parent_shortname'];
            } else if ($candidate->entitytype === 'academic_masters') {
                $masterparents[(string)$payload['externalid']] = (string)($payload['parent_externalid'] ?? '');
            }
        }
        $depth = static function (string $key, array $parents): int {
            $seen = [];
            $value = $key;
            $level = 0;
            while (($parents[$value] ?? '') !== '') {
                if (isset($seen[$value])) {
                    return 999;
                }
                $seen[$value] = true;
                $value = $parents[$value];
                $level++;
            }
            return $level;
        };
        uasort($rows, static function (
            object $left,
            object $right
        ) use (
            $order,
            $depth,
            $departmentparents,
            $masterparents
        ): int {
            $leftorder = $order[$left->entitytype] ?? 999;
            $rightorder = $order[$right->entitytype] ?? 999;
            if ($left->entitytype === 'departments') {
                $leftpayload = json::decode_object((string)$left->payloadjson);
                $leftorder += $depth((string)$leftpayload['shortname'], $departmentparents) / 100;
            } else if ($left->entitytype === 'academic_masters') {
                $leftpayload = json::decode_object((string)$left->payloadjson);
                $leftorder += $depth((string)$leftpayload['externalid'], $masterparents) / 100;
            }
            if ($right->entitytype === 'departments') {
                $rightpayload = json::decode_object((string)$right->payloadjson);
                $rightorder += $depth((string)$rightpayload['shortname'], $departmentparents) / 100;
            } else if ($right->entitytype === 'academic_masters') {
                $rightpayload = json::decode_object((string)$right->payloadjson);
                $rightorder += $depth((string)$rightpayload['externalid'], $masterparents) / 100;
            }
            return ($leftorder <=> $rightorder)
                ?: ((int)$left->id <=> (int)$right->id);
        });
        foreach ($rows as $row) {
            try {
                $payload = json::decode_object($row->payloadjson);
                $transaction = $DB->start_delegated_transaction();
                try {
                    $this->apply_row($tenant, (string)$row->entitytype, $payload);
                    $transaction->allow_commit();
                } catch (\Throwable $exception) {
                    $transaction->rollback($exception);
                }
                $row->status = 'applied';
                $row->errorjson = '{}';
                $applied++;
            } catch (\Throwable $exception) {
                $row->status = 'error';
                $row->errorjson = json::encode([
                    'code' => get_class($exception),
                    'message' => $exception->getMessage(),
                ]);
                $errors++;
            }
            $row->timemodified = time();
            $DB->update_record('local_tenantmaster_batchrow', $row);
        }
        if ($batch->mode === 'deactivate_missing' && $errors === 0) {
            $this->deactivate_missing_masters($tenant, $batchid);
        }
        $batch->appliedcount = $DB->count_records('local_tenantmaster_batchrow', [
            'batchid' => $batchid,
            'status' => 'applied',
        ]);
        $batch->errorcount = $DB->count_records('local_tenantmaster_batchrow', [
            'batchid' => $batchid,
            'status' => 'error',
        ]);
        $batch->status = $batch->errorcount ? 'completed_with_errors' : 'completed';
        $batch->timefinished = time();
        $DB->update_record('local_tenantmaster_batch', $batch);
        (new audit_service())->record(
            (int)$tenant->id,
            'import.batch.applied',
            (string)$batch->status,
            [
                'checksum' => $batch->checksum,
                'rowcount' => $batch->rowcount,
                'appliedcount' => $batch->appliedcount,
                'errorcount' => $batch->errorcount,
            ],
            ['entitytable' => 'local_tenantmaster_batch', 'entityid' => $batchid],
        );
        return $batch;
    }

    /**
     * Validate immutable manifest structure.
     *
     * @param object $tenant Tenant.
     * @param array<string, mixed> $manifest Manifest.
     */
    private function validate_manifest(object $tenant, array $manifest): void {
        if (($manifest['schema_version'] ?? '') !== self::SCHEMA_VERSION) {
            throw new \invalid_parameter_exception('Unsupported import schema version.');
        }
        if (($manifest['tenant']['trust_code'] ?? '') !== $tenant->trustcode) {
            throw new \invalid_parameter_exception('Manifest tenant does not match the selected company.');
        }
        if (empty($manifest['files']) || !is_array($manifest['files'])) {
            throw new \invalid_parameter_exception('Manifest files list is required.');
        }
        $seenpaths = [];
        foreach ($manifest['files'] as $file) {
            if (!is_array($file)) {
                throw new \invalid_parameter_exception('Invalid manifest file entry.');
            }
            $path = (string)($file['path'] ?? '');
            $entity = (string)($file['entity'] ?? '');
            if (
                $path === '' || str_contains($path, '..') || str_starts_with($path, '/')
                    || str_contains($path, '\\')
            ) {
                throw new \invalid_parameter_exception('Unsafe package path.');
            }
            if (!array_key_exists($entity, self::REQUIRED_COLUMNS)) {
                throw new \invalid_parameter_exception('Unsupported import entity: ' . $entity);
            }
            if (isset($seenpaths[$path]) || !preg_match('/^[a-f0-9]{64}$/', (string)($file['sha256'] ?? ''))) {
                throw new \invalid_parameter_exception('Invalid or duplicate manifest file.');
            }
            if (!isset($file['rows']) || (int)$file['rows'] < 0) {
                throw new \invalid_parameter_exception('Manifest row count is required.');
            }
            $seenpaths[$path] = true;
        }
    }

    /**
     * Parse and validate normalized CSV.
     *
     * @param string $entity Entity.
     * @param string $content CSV.
     * @return array<int, array<string, string>>
     */
    private function parse_csv(string $entity, string $content): array {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);
        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);
            throw new \invalid_parameter_exception('CSV header is required.');
        }
        $headers = array_map(static fn(string $header): string =>
            trim(preg_replace('/^\xEF\xBB\xBF/', '', $header)), $headers);
        if (count($headers) !== count(array_unique($headers))) {
            fclose($handle);
            throw new \invalid_parameter_exception('CSV headers must be unique.');
        }
        foreach (self::FORBIDDEN_COLUMNS as $forbidden) {
            if (in_array($forbidden, $headers, true)) {
                fclose($handle);
                throw new \invalid_parameter_exception('Sensitive column is forbidden: ' . $forbidden);
            }
        }
        foreach (self::REQUIRED_COLUMNS[$entity] as $required) {
            if (!in_array($required, $headers, true)) {
                fclose($handle);
                throw new \invalid_parameter_exception('Missing required column: ' . $required);
            }
        }
        $rows = [];
        while (($values = fgetcsv($handle)) !== false) {
            if (!array_filter($values, static fn(mixed $value): bool => trim((string)$value) !== '')) {
                continue;
            }
            if (count($values) !== count($headers)) {
                fclose($handle);
                throw new \invalid_parameter_exception('Every CSV row must match the header column count.');
            }
            $row = [];
            foreach ($headers as $index => $header) {
                $row[$header] = trim((string)($values[$index] ?? ''));
            }
            $rows[] = $row;
        }
        fclose($handle);
        return $rows;
    }

    /**
     * Stable row identity.
     *
     * @param string $entity Entity.
     * @param array<string, string> $row Row.
     * @return string
     */
    private function row_externalid(string $entity, array $row): string {
        $value = match ($entity) {
            'cohort_members' => $row['cohort_externalid'] . ':' . $row['user_externalid'],
            'group_members' => $row['course_idnumber'] . ':' . $row['group_externalid'] . ':' . $row['user_externalid'],
            'user_roles' => $row['user_externalid'] . ':' . $row['rolekey'] . ':' . $row['department_shortname'],
            'guardian_links' => $row['guardian_externalid'] . ':' . $row['learner_externalid'],
            default => $row['externalid'] ?? '',
        };
        if ($value === '') {
            throw new \invalid_parameter_exception('A stable external ID is required.');
        }
        return strlen($value) <= 100 ? $value : hash('sha256', $value);
    }

    /**
     * Calculate a create/update/noop action.
     *
     * @param object $tenant Tenant.
     * @param string $entity Entity.
     * @param array<string, mixed> $payload Payload.
     * @param string $mode Mode.
     * @return string
     */
    private function planned_action(object $tenant, string $entity, array $payload, string $mode): string {
        global $DB;

        $exists = match ($entity) {
            'academic_years' => $DB->record_exists('local_tenantmaster_acadyear', [
                'tenantid' => $tenant->id,
                'externalid' => $payload['externalid'],
            ]),
            'academic_masters' => $DB->record_exists('local_tenantmaster_master', [
                'tenantid' => $tenant->id,
                'mastertype' => $payload['mastertype'],
                'externalid' => $payload['externalid'],
            ]),
            'departments' => $DB->record_exists('local_iomad_company_departments', [
                'companyid' => $tenant->companyid,
                'shortname' => $payload['shortname'],
            ]),
            'cohorts' => $DB->record_exists('cohort', [
                'idnumber' => $this->native_key($tenant, 'COHORT', (string)$payload['externalid']),
            ]),
            'groups' => $DB->record_exists('groups', [
                'idnumber' => $this->native_key($tenant, 'GROUP', (string)$payload['externalid']),
            ]),
            default => false,
        };
        if ($mode === 'create_only' && $exists) {
            throw new \invalid_parameter_exception('Create-only import conflicts with an existing record.');
        }
        if (
            $mode === 'update' && !$exists
                && in_array($entity, ['academic_years', 'academic_masters', 'departments', 'cohorts', 'groups'], true)
        ) {
            throw new \invalid_parameter_exception('Update import cannot find the target record.');
        }
        return $exists ? 'update' : 'create';
    }

    /**
     * Validate stable IDs, dates, native scope and package foreign keys.
     *
     * @param object $tenant Tenant.
     * @param int $batchid Batch.
     * @param string $entity Entity.
     * @param array<string, mixed> $payload Payload.
     */
    private function assert_dependencies(object $tenant, int $batchid, string $entity, array $payload): void {
        global $DB;

        foreach ($payload as $field => $value) {
            if (
                ($field === 'externalid' || str_ends_with((string)$field, '_externalid'))
                    && $value !== '' && !catalog::valid_external_key((string)$value)
            ) {
                throw new \invalid_parameter_exception('Invalid stable external key in field: ' . $field);
            }
        }
        if ($entity === 'academic_years') {
            $start = strtotime((string)$payload['startdate']);
            $end = strtotime((string)$payload['enddate']);
            if ($start === false || $end === false || $start >= $end) {
                throw new \invalid_parameter_exception('Academic-year dates are invalid.');
            }
        } else if ($entity === 'academic_masters') {
            if (
                !array_key_exists((string)$payload['mastertype'], catalog::MASTER_TYPES)
                    || !catalog::valid_external_key((string)$payload['code'])
            ) {
                throw new \invalid_parameter_exception('Academic master type or code is invalid.');
            }
            if (isset($payload['configurationjson']) && $payload['configurationjson'] !== '') {
                json::decode_object((string)$payload['configurationjson']);
            }
            if (($payload['parent_externalid'] ?? '') !== '') {
                $parentexists = $DB->record_exists('local_tenantmaster_master', [
                    'tenantid' => $tenant->id,
                    'externalid' => $payload['parent_externalid'],
                ]) || $this->package_has(
                    $batchid,
                    'academic_masters',
                    static fn(array $row): bool =>
                        ($row['externalid'] ?? '') === $payload['parent_externalid'],
                );
                if (!$parentexists) {
                    throw new \invalid_parameter_exception('Academic parent cannot be resolved.');
                }
                if (
                    $this->package_parent_cycle(
                        $batchid,
                        'academic_masters',
                        (string)$payload['externalid'],
                        'externalid',
                        'parent_externalid',
                    )
                ) {
                    throw new \invalid_parameter_exception('Academic parent hierarchy contains a cycle.');
                }
            }
        } else if ($entity === 'departments' && $payload['parent_shortname'] !== '') {
            $parentexists = $DB->record_exists('local_iomad_company_departments', [
                'companyid' => $tenant->companyid,
                'shortname' => $payload['parent_shortname'],
            ]) || $this->package_has(
                $batchid,
                'departments',
                static fn(array $row): bool => ($row['shortname'] ?? '') === $payload['parent_shortname'],
            );
            if (!$parentexists) {
                throw new \invalid_parameter_exception('Department parent cannot be resolved.');
            }
            if (
                $this->package_parent_cycle(
                    $batchid,
                    'departments',
                    (string)$payload['shortname'],
                    'shortname',
                    'parent_shortname',
                )
            ) {
                throw new \invalid_parameter_exception('Department hierarchy contains a cycle.');
            }
        } else if ($entity === 'groups' || $entity === 'group_members' || $entity === 'user_roles') {
            if (($payload['course_idnumber'] ?? '') !== '') {
                $this->course_id($tenant, (string)$payload['course_idnumber']);
            }
        }

        if (in_array($entity, ['cohort_members', 'group_members', 'user_roles'], true)) {
            $this->require_company_user_external($tenant, (string)$payload['user_externalid']);
        } else if ($entity === 'guardian_links') {
            $this->require_company_user_external($tenant, (string)$payload['guardian_externalid']);
            $this->require_company_user_external($tenant, (string)$payload['learner_externalid']);
        }
        if ($entity === 'cohort_members') {
            $exists = $DB->record_exists('cohort', [
                'idnumber' => $this->native_key($tenant, 'COHORT', (string)$payload['cohort_externalid']),
            ]) || $this->package_has(
                $batchid,
                'cohorts',
                static fn(array $row): bool =>
                    ($row['externalid'] ?? '') === $payload['cohort_externalid'],
            );
            if (!$exists) {
                throw new \invalid_parameter_exception('Cohort membership target cannot be resolved.');
            }
        }
        if ($entity === 'group_members') {
            $exists = $this->package_has(
                $batchid,
                'groups',
                static fn(array $row): bool =>
                    ($row['externalid'] ?? '') === $payload['group_externalid']
                    && ($row['course_idnumber'] ?? '') === $payload['course_idnumber'],
            );
            if (!$exists) {
                $courseid = $this->course_id($tenant, (string)$payload['course_idnumber']);
                $exists = $DB->record_exists('groups', [
                    'courseid' => $courseid,
                    'idnumber' => $this->native_key($tenant, 'GROUP', (string)$payload['group_externalid']),
                ]);
            }
            if (!$exists) {
                throw new \invalid_parameter_exception('Group membership target cannot be resolved.');
            }
        }
        if ($entity === 'user_roles') {
            if (
                !array_key_exists((string)$payload['rolekey'], catalog::ROLE_KEYS)
                    || !$DB->record_exists('local_tenantmaster_rolemap', [
                        'tenantid' => $tenant->id,
                        'rolekey' => $payload['rolekey'],
                        'active' => 1,
                    ])
            ) {
                throw new \invalid_parameter_exception('Business role cannot be resolved.');
            }
        }
    }

    /**
     * Find a matching normalized row in the same immutable package.
     *
     * @param int $batchid Batch.
     * @param string $entity Entity.
     * @param callable $matches Row matcher.
     * @return bool
     */
    private function package_has(int $batchid, string $entity, callable $matches): bool {
        global $DB;

        foreach (
            $DB->get_records('local_tenantmaster_batchrow', [
            'batchid' => $batchid,
            'entitytype' => $entity,
            ]) as $record
        ) {
            if ($matches(json::decode_object((string)$record->payloadjson))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Detect a cycle in package-local parent references.
     *
     * @param int $batchid Batch.
     * @param string $entity Entity.
     * @param string $startkey Starting key.
     * @param string $keyfield Key field.
     * @param string $parentfield Parent field.
     * @return bool
     */
    private function package_parent_cycle(
        int $batchid,
        string $entity,
        string $startkey,
        string $keyfield,
        string $parentfield,
    ): bool {
        global $DB;

        $parents = [];
        foreach (
            $DB->get_records('local_tenantmaster_batchrow', [
            'batchid' => $batchid,
            'entitytype' => $entity,
            ]) as $record
        ) {
            $payload = json::decode_object((string)$record->payloadjson);
            $parents[(string)($payload[$keyfield] ?? '')] = (string)($payload[$parentfield] ?? '');
        }
        $seen = [];
        $current = $startkey;
        while (($parents[$current] ?? '') !== '') {
            if (isset($seen[$current])) {
                return true;
            }
            $seen[$current] = true;
            $current = $parents[$current];
        }
        return false;
    }

    /**
     * Require an existing native user membership by stable idnumber.
     *
     * @param object $tenant Tenant.
     * @param string $externalid User idnumber.
     */
    private function require_company_user_external(object $tenant, string $externalid): void {
        global $DB;

        $userid = $this->user_id($externalid);
        if (
            !$DB->record_exists('local_iomad_company_users', [
                'companyid' => $tenant->companyid,
                'userid' => $userid,
            ])
        ) {
            throw new \invalid_parameter_exception('Referenced user is outside the selected tenant.');
        }
    }

    /**
     * Apply one normalized row through shared application services.
     *
     * @param object $tenant Tenant.
     * @param string $entity Entity.
     * @param array<string, mixed> $payload Payload.
     */
    private function apply_row(object $tenant, string $entity, array $payload): void {
        global $DB;

        switch ($entity) {
            case 'academic_years':
                $existing = $DB->get_record('local_tenantmaster_acadyear', [
                    'tenantid' => $tenant->id,
                    'externalid' => $payload['externalid'],
                ]);
                (new academic_year_service())->save((object)[
                    'id' => $existing->id ?? 0,
                    'tenantid' => $tenant->id,
                    'externalid' => $payload['externalid'],
                    'code' => $payload['code'],
                    'name' => $payload['name'],
                    'startdate' => strtotime((string)$payload['startdate']),
                    'enddate' => strtotime((string)$payload['enddate']),
                    'iscurrent' => (int)$payload['iscurrent'],
                    'status' => $payload['status'] ?? 'active',
                    'payloadjson' => '{}',
                ]);
                break;
            case 'academic_masters':
                $existing = $DB->get_record('local_tenantmaster_master', [
                    'tenantid' => $tenant->id,
                    'mastertype' => $payload['mastertype'],
                    'externalid' => $payload['externalid'],
                ]);
                $parentid = 0;
                if (($payload['parent_externalid'] ?? '') !== '') {
                    $parentid = (int)$DB->get_field('local_tenantmaster_master', 'id', [
                        'tenantid' => $tenant->id,
                        'externalid' => $payload['parent_externalid'],
                    ], MUST_EXIST);
                }
                (new master_service())->save((object)[
                    'id' => $existing->id ?? 0,
                    'tenantid' => $tenant->id,
                    'acadyearid' => 0,
                    'parentid' => $parentid,
                    'mastertype' => $payload['mastertype'],
                    'externalid' => $payload['externalid'],
                    'code' => $payload['code'],
                    'name' => $payload['name'],
                    'description' => $payload['description'] ?? '',
                    'payloadjson' => $payload['configurationjson'] ?? '{}',
                    'active' => (int)($payload['active'] ?? 1),
                    'sortorder' => (int)($payload['sortorder'] ?? 0),
                ]);
                break;
            case 'departments':
                $existing = $DB->get_record('local_iomad_company_departments', [
                    'companyid' => $tenant->companyid,
                    'shortname' => $payload['shortname'],
                ]);
                $parentid = (int)$DB->get_field('local_iomad_company_departments', 'id', [
                    'companyid' => $tenant->companyid,
                    'shortname' => $payload['parent_shortname'],
                ]);
                if ($parentid <= 0 && $payload['parent_shortname'] === '') {
                    $parentid = (int)\local_iomad\company::get_company_parentnode((int)$tenant->companyid)->id;
                } else if ($parentid <= 0) {
                    throw new \invalid_parameter_exception('Department parent is not available during apply.');
                }
                (new organisation_service())->save($tenant, (object)[
                    'id' => $existing->id ?? 0,
                    'name' => $payload['name'],
                    'shortname' => $payload['shortname'],
                    'parentid' => $parentid,
                ]);
                break;
            case 'cohorts':
                (new learning_access_service())->ensure_cohort(
                    $tenant,
                    (string)$payload['externalid'],
                    (string)$payload['name'],
                    (string)($payload['description'] ?? ''),
                );
                break;
            case 'cohort_members':
                $cohortid = (int)$DB->get_field('cohort', 'id', [
                    'idnumber' => $this->native_key($tenant, 'COHORT', (string)$payload['cohort_externalid']),
                ], MUST_EXIST);
                (new learning_access_service())->add_cohort_member(
                    $tenant,
                    $cohortid,
                    $this->user_id((string)$payload['user_externalid']),
                );
                break;
            case 'groups':
                (new learning_access_service())->ensure_group(
                    $tenant,
                    $this->course_id($tenant, (string)$payload['course_idnumber']),
                    (string)$payload['externalid'],
                    (string)$payload['name'],
                );
                break;
            case 'group_members':
                $courseid = $this->course_id($tenant, (string)$payload['course_idnumber']);
                $groupid = (int)$DB->get_field('groups', 'id', [
                    'courseid' => $courseid,
                    'idnumber' => $this->native_key($tenant, 'GROUP', (string)$payload['group_externalid']),
                ], MUST_EXIST);
                (new learning_access_service())->add_group_member(
                    $tenant,
                    $groupid,
                    $this->user_id((string)$payload['user_externalid']),
                );
                break;
            case 'user_roles':
                $departmentid = (int)$DB->get_field('local_iomad_company_departments', 'id', [
                    'companyid' => $tenant->companyid,
                    'shortname' => $payload['department_shortname'],
                ]);
                $courseid = $payload['course_idnumber'] !== ''
                    ? $this->course_id($tenant, (string)$payload['course_idnumber'])
                    : 0;
                (new people_service())->assign_role(
                    $tenant,
                    $this->user_id((string)$payload['user_externalid']),
                    (string)$payload['rolekey'],
                    $departmentid,
                    $courseid,
                );
                break;
            case 'guardian_links':
                (new people_service())->link_guardian(
                    $tenant,
                    $this->user_id((string)$payload['guardian_externalid']),
                    $this->user_id((string)$payload['learner_externalid']),
                );
                break;
        }
    }

    /**
     * Deactivate tenant-owned masters absent from an approved package.
     *
     * @param object $tenant Tenant.
     * @param int $batchid Batch.
     */
    private function deactivate_missing_masters(object $tenant, int $batchid): void {
        global $DB;

        $included = $DB->get_fieldset_select(
            'local_tenantmaster_batchrow',
            'externalid',
            'batchid = :batchid AND entitytype = :entitytype AND status = :status',
            ['batchid' => $batchid, 'entitytype' => 'academic_masters', 'status' => 'applied'],
        );
        if (!$included) {
            return;
        }
        [$notinsql, $params] = $DB->get_in_or_equal($included, SQL_PARAMS_NAMED, 'externalid', false);
        $params['tenantid'] = $tenant->id;
        foreach (
            $DB->get_records_select(
                'local_tenantmaster_master',
                "tenantid = :tenantid AND externalid $notinsql AND active = 1",
                $params,
            ) as $master
        ) {
            $master->active = 0;
            (new master_service())->save($master);
        }
    }

    /**
     * Resolve a user by stable native ID.
     *
     * @param string $externalid User idnumber.
     * @return int
     */
    private function user_id(string $externalid): int {
        global $DB;
        return (int)$DB->get_field('user', 'id', ['idnumber' => $externalid, 'deleted' => 0], MUST_EXIST);
    }

    /**
     * Resolve a tenant course by native idnumber.
     *
     * @param object $tenant Tenant.
     * @param string $idnumber Course ID number.
     * @return int
     */
    private function course_id(object $tenant, string $idnumber): int {
        global $DB;

        $courseid = (int)$DB->get_field('course', 'id', ['idnumber' => $idnumber], MUST_EXIST);
        if (
            !$DB->record_exists('local_iomad_company_courses', [
                'companyid' => $tenant->companyid,
                'courseid' => $courseid,
            ])
        ) {
            throw new \invalid_parameter_exception('Course belongs to another tenant.');
        }
        return $courseid;
    }

    /**
     * Stable bounded native ID.
     *
     * @param object $tenant Tenant.
     * @param string $type Type.
     * @param string $externalid External ID.
     * @return string
     */
    private function native_key(object $tenant, string $type, string $externalid): string {
        if (!catalog::valid_external_key($externalid)) {
            throw new \invalid_parameter_exception('Invalid stable external ID.');
        }
        $key = 'TM:' . $tenant->trustcode . ':' . $type . ':' . $externalid;
        return strlen($key) <= 100
            ? $key
            : substr($key, 0, 67) . ':' . substr(hash('sha256', $key), 0, 32);
    }
}
