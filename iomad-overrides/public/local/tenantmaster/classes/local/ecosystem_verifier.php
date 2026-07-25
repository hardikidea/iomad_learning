<?php
// This file is part of Moodle - http://moodle.org/

declare(strict_types=1);

namespace local_tenantmaster\local;

use local_iomad\custom_context\context_company;
use local_institutionpack\tenant_auditor;
use local_tenantanalytics\local\report_catalog;
use local_tenantanalytics\local\report_engine;
use local_tenantanalytics\local\tenant_scope as analytics_scope;

/**
 * Read-only, context-aware product ecosystem verification.
 *
 * Results contain aggregate counts, timings, memory deltas and remediation
 * identifiers only. User IDs, names, email addresses and record payloads are
 * deliberately excluded.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ecosystem_verifier {
    /** @var array<int,array<string,mixed>> */
    private array $results = [];

    /**
     * Run the complete read-only verification suite.
     *
     * @param string[] $companyshortnames Optional exact company filters.
     * @param int $maxreportms Maximum accepted generation time per report.
     * @param string $flociurl Optional Floci health endpoint.
     * @return array{generatedat:string,readonly:bool,summary:array,results:array}
     */
    public function run(
        array $companyshortnames = [],
        int $maxreportms = 5000,
        string $flociurl = ''
    ): array {
        $this->results = [];
        $maxreportms = max(100, min(60000, $maxreportms));
        $this->verify_platform();
        $this->verify_indexes();
        $this->verify_tasks();
        $this->verify_integrations($flociurl);
        $this->verify_global_isolation();

        $tenants = $this->tenants($companyshortnames);
        $this->check(
            'tenantmaster',
            'active_tenant_selection',
            'platform',
            static fn(): array => [
                'status' => $tenants ? 'pass' : 'fail',
                'metric' => 'selected=' . count($tenants),
                'remediation' => 'TM-TENANT-SELECTION',
            ]
        );
        foreach ($tenants as $tenant) {
            $label = (string)$tenant->companyshortname;
            $this->verify_tenant_identity($tenant, $label);
            $this->verify_role_mappings($tenant, $label);
            $this->verify_personas($tenant, $tenants, $label);
            $this->verify_relationships($tenant, $label);
            $this->verify_projection_state($tenant, $label);
            $this->verify_telemetry($tenant, $label);
            $this->verify_reports($tenant, $label, $maxreportms);
        }

        $summary = ['pass' => 0, 'warn' => 0, 'fail' => 0];
        foreach ($this->results as $result) {
            $summary[$result['status']]++;
        }
        $summary['total'] = count($this->results);
        return [
            'generatedat' => gmdate(DATE_ATOM),
            'readonly' => true,
            'summary' => $summary,
            'results' => $this->results,
        ];
    }

    /**
     * Verify installed service boundaries and required native tables.
     */
    private function verify_platform(): void {
        global $DB;

        $components = [
            'local_tenantmaster',
            'local_institutionpack',
            'local_tenantanalytics',
            'local_global_events',
            'local_iomad_h5p_bridge',
            'local_iomad_scorm_gen',
            'local_rapidgrader',
            'tool_iomadmonitor',
        ];
        foreach ($components as $component) {
            $this->check('platform', 'plugin_' . $component, 'platform', static function () use ($component): array {
                $version = (int)get_config($component, 'version');
                return [
                    'status' => $version > 0 ? 'pass' : 'fail',
                    'metric' => 'installed=' . ($version > 0 ? 'yes' : 'no'),
                    'remediation' => 'PLATFORM-PLUGIN-INSTALL',
                ];
            });
        }

        $tables = [
            'local_tenantmaster_tenant',
            'local_tenantmaster_mapping',
            'local_tenantmaster_dirty',
            'local_tenantmaster_job',
            'local_tanalytics_schedule',
            'local_tanalytics_run',
            'local_ge_ledger',
            'local_iomad_tracks',
        ];
        foreach ($tables as $table) {
            $this->check('database', 'table_' . $table, 'platform', static function () use ($DB, $table): array {
                $exists = $DB->get_manager()->table_exists($table);
                return [
                    'status' => $exists ? 'pass' : 'fail',
                    'metric' => 'exists=' . ($exists ? 'yes' : 'no'),
                    'remediation' => 'PLATFORM-SCHEMA-UPGRADE',
                ];
            });
        }
    }

    /**
     * Verify the indexes used by queues, tenant boundaries and reporting.
     */
    private function verify_indexes(): void {
        $requirements = [
            ['local_ge_ledger', ['companyid', 'userid'], false],
            ['local_ge_ledger', ['companyid', 'courseid'], false],
            ['local_ge_ledger', ['companyid', 'idempotencykey'], true],
            ['local_tenantmaster_dirty', ['state', 'availabletime'], false],
            ['local_tenantmaster_dirty', ['tenantid', 'state'], false],
            ['local_tenantmaster_mapping', ['tenantid', 'status'], false],
            ['local_tanalytics_schedule', ['enabled', 'nextrun'], false],
            ['local_tanalytics_schedule', ['companyid', 'userid'], false],
            ['local_iomad_company_users', ['companyid', 'userid'], false],
            ['local_iomad_company_courses', ['companyid', 'courseid'], false],
        ];
        foreach ($requirements as [$table, $columns, $unique]) {
            $checkname = $table . '_' . implode('_', $columns);
            $this->check('database', 'index_' . $checkname, 'platform', function () use (
                $table,
                $columns,
                $unique,
                $checkname
            ): array {
                $found = $this->has_index($table, $columns, $unique);
                return [
                    'status' => $found ? 'pass' : 'fail',
                    'metric' => 'columns=' . implode(',', $columns) . ';unique=' . ($unique ? 'yes' : 'no'),
                    'remediation' => 'DB-INDEX-' . strtoupper(str_replace('_', '-', $checkname)),
                ];
            });
        }
    }

    /**
     * Verify scheduled and ad-hoc task health without executing mutations.
     */
    private function verify_tasks(): void {
        global $DB;

        $classes = [
            '\local_tenantmaster\task\process_dirty_records',
            '\local_tenantmaster\task\detect_drift',
            '\local_tenantmaster\task\validate_tenants',
            '\local_tenantanalytics\task\deliver_scheduled_reports',
            '\local_global_events\task\process_messages',
            '\local_global_events\task\prune_webhooks',
            '\mod_scorm\task\cron_task',
        ];
        foreach ($classes as $classname) {
            $this->check(
                'tasks',
                'scheduled_' . ltrim(str_replace('\\', '_', $classname), '_'),
                'platform',
                static function () use ($classname): array {
                    $task = \core\task\manager::get_scheduled_task($classname);
                    if (!$task) {
                        return [
                            'status' => 'fail',
                            'metric' => 'registered=no',
                            'remediation' => 'TASK-REGISTER',
                        ];
                    }
                    return [
                        'status' => !$task->get_disabled() && (int)$task->get_fail_delay() === 0
                            ? 'pass'
                            : 'fail',
                        'metric' => 'disabled=' . ($task->get_disabled() ? 'yes' : 'no')
                            . ';faildelay=' . $task->get_fail_delay()
                            . ';lastrun=' . (int)$task->get_last_run_time(),
                        'remediation' => 'TASK-RECOVER',
                    ];
                }
            );
        }
        $this->check('tasks', 'failed_adhoc_queue', 'platform', static function () use ($DB): array {
            $failed = $DB->count_records_select('task_adhoc', 'faildelay > 0');
            return [
                'status' => $failed === 0 ? 'pass' : 'fail',
                'metric' => 'failed=' . $failed,
                'remediation' => 'TASK-ADHOC-FAILURE',
            ];
        });
    }

    /**
     * Verify SCORM/H5P recovery contracts, route guards and optional Floci.
     *
     * @param string $flociurl Optional live endpoint.
     */
    private function verify_integrations(string $flociurl): void {
        global $CFG;

        $scormfile = $CFG->dirroot . '/local/iomad_scorm_gen/classes/package_builder.php';
        $this->check('integrations', 'scorm_offline_recovery', 'platform', static function () use ($scormfile): array {
            $source = is_file($scormfile) ? (string)file_get_contents($scormfile) : '';
            $valid = str_contains($source, 'localStorage')
                && str_contains($source, "addEventListener('online'")
                && str_contains($source, 'LMSCommit')
                && !str_contains($source, 'fetch(');
            return [
                'status' => $valid ? 'pass' : 'fail',
                'metric' => 'localqueue=' . ($valid ? 'verified' : 'invalid'),
                'remediation' => 'SCORM-OFFLINE-CONTRACT',
            ];
        });

        $h5pevents = $CFG->dirroot . '/local/iomad_h5p_bridge/db/events.php';
        $this->check('integrations', 'h5p_statement_observer', 'platform', static function () use ($h5pevents): array {
            $source = is_file($h5pevents) ? (string)file_get_contents($h5pevents) : '';
            $valid = str_contains($source, '\mod_h5pactivity\event\statement_received')
                && str_contains($source, '\local_iomad_h5p_bridge\observer::statement_received');
            return [
                'status' => $valid ? 'pass' : 'fail',
                'metric' => 'observer=' . ($valid ? 'registered' : 'missing'),
                'remediation' => 'H5P-OBSERVER-CONTRACT',
            ];
        });

        $indexfile = $CFG->dirroot . '/local/tenantmaster/index.php';
        $this->check('navigation', 'tenantmaster_route_guards', 'platform', static function () use ($indexfile): array {
            $source = is_file($indexfile) ? (string)file_get_contents($indexfile) : '';
            $sections = [
                'dashboard', 'profile', 'organisation', 'academic', 'courses', 'people', 'access',
                'assessments', 'certificates', 'progression', 'imports', 'sync', 'validation', 'audit',
            ];
            $valid = str_contains($source, 'require_login()')
                && str_contains($source, 'require_sesskey()')
                && str_contains($source, '$access->require(');
            foreach ($sections as $section) {
                $valid = $valid && str_contains($source, "'" . $section . "'");
            }
            return [
                'status' => $valid ? 'pass' : 'fail',
                'metric' => 'sections=' . count($sections) . ';guards=' . ($valid ? 'verified' : 'invalid'),
                'remediation' => 'NAV-TENANTMASTER-GUARDS',
            ];
        });

        $this->check('integrations', 'floci_endpoint', 'platform', static function () use ($flociurl): array {
            if ($flociurl === '') {
                return [
                    'status' => 'warn',
                    'metric' => 'livecheck=not-requested',
                    'remediation' => 'FLOCI-RUN-LOCAL-CLOUD-VALIDATE',
                ];
            }
            $context = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]);
            $headers = @get_headers($flociurl, true, $context);
            $reachable = is_array($headers);
            return [
                'status' => $reachable ? 'pass' : 'fail',
                'metric' => 'reachable=' . ($reachable ? 'yes' : 'no'),
                'remediation' => 'FLOCI-ENDPOINT-RECOVER',
            ];
        });
    }

    /**
     * Run the maintained cross-company auditor.
     */
    private function verify_global_isolation(): void {
        $this->check('security', 'strict_cross_company_audit', 'platform', static function (): array {
            $audit = (new tenant_auditor())->run(0, false);
            return [
                'status' => $audit['ok'] ? 'pass' : 'fail',
                'metric' => 'anomalies=' . (int)$audit['anomalies']
                    . ';checks=' . count($audit['checks']),
                'remediation' => 'SECURITY-TENANT-ISOLATION',
            ];
        });
    }

    /**
     * Verify one-to-one tenant identity.
     *
     * @param object $tenant Tenant.
     * @param string $label Sanitized company shortname.
     */
    private function verify_tenant_identity(object $tenant, string $label): void {
        global $DB;

        $this->check('tenantmaster', 'tenant_identity', $label, static function () use ($DB, $tenant): array {
            $duplicates = $DB->count_records('local_tenantmaster_tenant', ['companyid' => $tenant->companyid]);
            $companyexists = $DB->record_exists('local_iomad_companies', ['id' => $tenant->companyid]);
            $validtype = array_key_exists((string)$tenant->tenanttype, catalog::TENANT_TYPES);
            $valid = $companyexists && $duplicates === 1 && $validtype;
            return [
                'status' => $valid ? 'pass' : 'fail',
                'metric' => 'company=' . ($companyexists ? 'yes' : 'no')
                    . ';tenant_records=' . $duplicates
                    . ';type=' . ($validtype ? 'valid' : 'invalid'),
                'remediation' => 'TM-TENANT-IDENTITY',
            ];
        });
    }

    /**
     * Verify canonical role mappings and native manager-type consistency.
     *
     * @param object $tenant Tenant.
     * @param string $label Company label.
     */
    private function verify_role_mappings(object $tenant, string $label): void {
        global $DB;

        $this->check('roles', 'canonical_mappings', $label, static function () use ($DB, $tenant): array {
            $invalid = 0;
            foreach (role_service::DEFAULTS as $rolekey => [$shortname, $managertype, $scope]) {
                $mapping = $DB->get_record('local_tenantmaster_rolemap', [
                    'tenantid' => $tenant->id,
                    'rolekey' => $rolekey,
                    'active' => 1,
                ]);
                $actualshortname = $mapping
                    ? (string)$DB->get_field('role', 'shortname', ['id' => $mapping->roleid])
                    : '';
                if (
                    !$mapping
                    || $actualshortname !== $shortname
                    || (int)$mapping->managertype !== $managertype
                    || (string)$mapping->scope !== $scope
                ) {
                    $invalid++;
                }
            }
            return [
                'status' => $invalid === 0 ? 'pass' : 'fail',
                'metric' => 'mappings=' . count(role_service::DEFAULTS) . ';invalid=' . $invalid,
                'remediation' => 'ROLE-CANONICAL-MAPPING',
            ];
        });

        $this->check('roles', 'native_manager_types', $label, static function () use ($DB, $tenant): array {
            $mismatches = (int)$DB->get_field_sql(
                "SELECT COUNT(DISTINCT ra.id)
                   FROM {role_assignments} ra
                   JOIN {role} r ON r.id = ra.roleid
                   JOIN {context} ctx
                     ON ctx.id = ra.contextid
                    AND ctx.contextlevel = :contextlevel
                   JOIN {local_iomad_company_users} cu
                     ON cu.userid = ra.userid
                    AND cu.companyid = ctx.instanceid
                  WHERE ctx.instanceid = :companyid
                    AND (
                        (r.shortname = :manager AND cu.managertype <> 1)
                        OR (r.shortname = :departmentmanager AND cu.managertype <> 2)
                        OR (r.shortname = :reporter AND cu.managertype <> 4)
                        OR (r.shortname = :itcoordinator AND cu.managertype <> 0)
                    )",
                [
                    'contextlevel' => CONTEXT_COMPANY,
                    'companyid' => $tenant->companyid,
                    'manager' => 'companymanager',
                    'departmentmanager' => 'companydepartmentmanager',
                    'reporter' => 'companyreporter',
                    'itcoordinator' => 'institutionitcoordinator',
                ]
            );
            return [
                'status' => $mismatches === 0 ? 'pass' : 'fail',
                'metric' => 'mismatches=' . $mismatches,
                'remediation' => 'ROLE-NATIVE-MANAGER-TYPE',
            ];
        });

        $this->check('roles', 'guardian_role_consolidation', $label, static function () use ($DB, $tenant): array {
            $legacy = (int)$DB->get_field_sql(
                "SELECT COUNT(ra.id)
                   FROM {role_assignments} ra
                   JOIN {role} r ON r.id = ra.roleid AND r.shortname = :legacy
                   JOIN {context} ctx ON ctx.id = ra.contextid
                   JOIN {local_iomad_company_users} guardian
                     ON guardian.userid = ra.userid
                    AND guardian.companyid = :companyid
                   JOIN {local_iomad_company_users} learner
                     ON learner.userid = ctx.instanceid
                    AND learner.companyid = :companyid2",
                [
                    'legacy' => 'parentguardian',
                    'companyid' => $tenant->companyid,
                    'companyid2' => $tenant->companyid,
                ]
            );
            $canonical = (int)$DB->get_field_sql(
                "SELECT COUNT(ra.id)
                   FROM {role_assignments} ra
                   JOIN {role} r ON r.id = ra.roleid AND r.shortname = :canonical
                   JOIN {context} ctx
                     ON ctx.id = ra.contextid
                    AND ctx.contextlevel = :contextlevel
                   JOIN {local_iomad_company_users} guardian
                     ON guardian.userid = ra.userid
                    AND guardian.companyid = :companyid
                   JOIN {local_iomad_company_users} learner
                     ON learner.userid = ctx.instanceid
                    AND learner.companyid = :companyid2",
                [
                    'canonical' => 'tenantguardian',
                    'contextlevel' => CONTEXT_USER,
                    'companyid' => $tenant->companyid,
                    'companyid2' => $tenant->companyid,
                ]
            );
            return [
                'status' => $legacy === 0 ? ($canonical > 0 ? 'pass' : 'warn') : 'fail',
                'metric' => 'canonical=' . $canonical . ';legacy=' . $legacy,
                'remediation' => 'ROLE-GUARDIAN-CONSOLIDATE',
            ];
        });
    }

    /**
     * Verify actual persona permissions and unrelated-company denial.
     *
     * @param object $tenant Tenant.
     * @param object[] $tenants Selected tenants.
     * @param string $label Company label.
     */
    private function verify_personas(object $tenant, array $tenants, string $label): void {
        $companycontext = context_company::instance((int)$tenant->companyid);
        $othercontext = null;
        foreach ($tenants as $other) {
            if ((int)$other->companyid !== (int)$tenant->companyid) {
                $othercontext = context_company::instance((int)$other->companyid);
                break;
            }
        }
        $personas = [
            'principal' => [
                'role' => 'companymanager',
                'allow' => ['local/tenantmaster:sync', 'local/tenantanalytics:viewpii'],
                'deny' => [],
            ],
            'trustee' => [
                'role' => 'companyreporter',
                'allow' => ['local/tenantmaster:viewaudit', 'local/tenantanalytics:viewcompany'],
                'deny' => [
                    'local/tenantmaster:sync',
                    'local/tenantanalytics:viewpii',
                    'local/global_events:award',
                ],
            ],
            'it_coordinator' => [
                'role' => 'institutionitcoordinator',
                'allow' => ['local/tenantmaster:managepeople'],
                'deny' => ['local/tenantmaster:sync', 'local/tenantmaster:import'],
            ],
            'hod_dean' => [
                'role' => 'companydepartmentmanager',
                'allow' => ['local/tenantmaster:manageacademic', 'local/tenantmaster:managepeople'],
                'deny' => ['local/tenantmaster:sync'],
            ],
        ];
        foreach ($personas as $persona => $definition) {
            $this->check('personas', $persona, $label, function () use (
                $tenant,
                $companycontext,
                $othercontext,
                $definition
            ): array {
                $userid = $this->company_role_user(
                    (int)$tenant->companyid,
                    (string)$definition['role']
                );
                if ($userid <= 0) {
                    return [
                        'status' => 'warn',
                        'metric' => 'representative=missing',
                        'remediation' => 'PERSONA-SEED-ROLE',
                    ];
                }
                $valid = !is_siteadmin($userid);
                foreach ($definition['allow'] as $capability) {
                    $valid = $valid && has_capability($capability, $companycontext, $userid);
                }
                foreach ($definition['deny'] as $capability) {
                    $valid = $valid && !has_capability($capability, $companycontext, $userid);
                }
                if ($othercontext) {
                    $valid = $valid
                        && !has_capability('local/tenantmaster:sync', $othercontext, $userid)
                        && !has_capability('local/tenantmaster:managepeople', $othercontext, $userid);
                }
                return [
                    'status' => $valid ? 'pass' : 'fail',
                    'metric' => 'representative=yes;cross_company='
                        . ($othercontext ? 'checked' : 'not-applicable'),
                    'remediation' => 'PERSONA-CAPABILITY-MATRIX',
                ];
            });
        }
        $this->verify_course_persona($tenant, $label, 'teacher', 'editingteacher', true);
        $this->verify_course_persona($tenant, $label, 'student', 'student', false);
    }

    /**
     * Verify teacher or student course-context permissions.
     *
     * @param object $tenant Tenant.
     * @param string $label Company label.
     * @param string $persona Persona label.
     * @param string $roleshortname Native course role.
     * @param bool $teacher Teacher expectations.
     */
    private function verify_course_persona(
        object $tenant,
        string $label,
        string $persona,
        string $roleshortname,
        bool $teacher
    ): void {
        global $DB;

        $this->check('personas', $persona, $label, static function () use (
            $DB,
            $tenant,
            $roleshortname,
            $teacher
        ): array {
            $assignment = $DB->get_record_sql(
                "SELECT ra.id, ra.userid, ctx.instanceid AS courseid
                   FROM {role_assignments} ra
                   JOIN {role} r ON r.id = ra.roleid AND r.shortname = :roleshortname
                   JOIN {context} ctx
                     ON ctx.id = ra.contextid
                    AND ctx.contextlevel = :contextlevel
                   JOIN {local_iomad_company_courses} cc
                     ON cc.courseid = ctx.instanceid
                    AND cc.companyid = :companyid
               ORDER BY ra.id",
                [
                    'roleshortname' => $roleshortname,
                    'contextlevel' => CONTEXT_COURSE,
                    'companyid' => $tenant->companyid,
                ],
                IGNORE_MULTIPLE
            );
            if (!$assignment) {
                return [
                    'status' => 'warn',
                    'metric' => 'representative=missing',
                    'remediation' => 'PERSONA-SEED-COURSE-ROLE',
                ];
            }
            $coursecontext = \context_course::instance((int)$assignment->courseid);
            $companycontext = context_company::instance((int)$tenant->companyid);
            $valid = !is_siteadmin((int)$assignment->userid)
                && !has_capability('local/tenantmaster:sync', $companycontext, (int)$assignment->userid);
            if ($teacher) {
                $valid = $valid
                    && has_capability('local/rapidgrader:view', $coursecontext, (int)$assignment->userid)
                    && has_capability('local/rapidgrader:grade', $coursecontext, (int)$assignment->userid)
                    && !has_capability(
                        'local/global_events:award',
                        $companycontext,
                        (int)$assignment->userid
                    );
            } else {
                $valid = $valid
                    && has_capability(
                        'local/tenantanalytics:viewown',
                        \context_system::instance(),
                        (int)$assignment->userid
                    )
                    && !has_capability('local/rapidgrader:grade', $coursecontext, (int)$assignment->userid);
            }
            return [
                'status' => $valid ? 'pass' : 'fail',
                'metric' => 'representative=yes;course_scope=checked',
                'remediation' => 'PERSONA-COURSE-CAPABILITIES',
            ];
        });
    }

    /**
     * Verify native and telemetry relationships for a tenant.
     *
     * @param object $tenant Tenant.
     * @param string $label Company label.
     */
    private function verify_relationships(object $tenant, string $label): void {
        global $DB;

        $queries = [
            'user_department_company' =>
                "SELECT COUNT(cu.id)
                   FROM {local_iomad_company_users} cu
                   JOIN {local_iomad_company_departments} d ON d.id = cu.departmentid
                  WHERE cu.companyid = :companyid AND d.companyid <> cu.companyid",
            'course_department_company' =>
                "SELECT COUNT(cc.id)
                   FROM {local_iomad_company_courses} cc
                   JOIN {local_iomad_company_departments} d ON d.id = cc.departmentid
                  WHERE cc.companyid = :companyid AND d.companyid <> cc.companyid",
            'ledger_user_company' =>
                "SELECT COUNT(l.id)
                   FROM {local_ge_ledger} l
                  WHERE l.companyid = :companyid
                    AND NOT EXISTS (
                        SELECT 1 FROM {local_iomad_company_users} cu
                         WHERE cu.companyid = l.companyid
                           AND cu.userid = l.userid
                           AND cu.suspended = 0
                    )",
            'ledger_course_company' =>
                "SELECT COUNT(l.id)
                   FROM {local_ge_ledger} l
                  WHERE l.companyid = :companyid
                    AND l.courseid > 0
                    AND NOT EXISTS (
                        SELECT 1 FROM {local_iomad_company_courses} cc
                         WHERE cc.companyid = l.companyid AND cc.courseid = l.courseid
                    )",
        ];
        foreach ($queries as $name => $sql) {
            $this->check('relationships', $name, $label, static function () use ($DB, $tenant, $sql): array {
                $anomalies = (int)$DB->get_field_sql($sql, ['companyid' => $tenant->companyid]);
                return [
                    'status' => $anomalies === 0 ? 'pass' : 'fail',
                    'metric' => 'anomalies=' . $anomalies,
                    'remediation' => 'RELATIONSHIP-TENANT-SCOPE',
                ];
            });
        }
    }

    /**
     * Verify queues, mappings, drift and validation state.
     *
     * @param object $tenant Tenant.
     * @param string $label Company label.
     */
    private function verify_projection_state(object $tenant, string $label): void {
        global $DB;

        $this->check('synchronization', 'projection_state', $label, static function () use ($DB, $tenant): array {
            $dirty = $DB->count_records_select(
                'local_tenantmaster_dirty',
                'tenantid = :tenantid AND state NOT IN (:synced, :superseded)',
                ['tenantid' => $tenant->id, 'synced' => 'synced', 'superseded' => 'superseded']
            );
            $mappingerrors = $DB->count_records_select(
                'local_tenantmaster_mapping',
                'tenantid = :tenantid AND status IN (:error, :conflict)',
                ['tenantid' => $tenant->id, 'error' => 'error', 'conflict' => 'conflict']
            );
            $drift = $DB->count_records('local_tenantmaster_drift', [
                'tenantid' => $tenant->id,
                'status' => 'open',
            ]);
            $blocking = $DB->count_records('local_tenantmaster_valissue', [
                'tenantid' => $tenant->id,
                'status' => 'open',
                'blocking' => 1,
            ]);
            $failures = $dirty + $mappingerrors + $drift + $blocking;
            return [
                'status' => $failures === 0 ? 'pass' : 'fail',
                'metric' => "dirty={$dirty};mapping={$mappingerrors};drift={$drift};blocking={$blocking}",
                'remediation' => 'TM-PROJECTION-RECONCILE',
            ];
        });
    }

    /**
     * Verify ledger isolation, uniqueness and aggregate service accuracy.
     *
     * @param object $tenant Tenant.
     * @param string $label Company label.
     */
    private function verify_telemetry(object $tenant, string $label): void {
        global $DB;

        $this->check('telemetry', 'ledger_aggregate_accuracy', $label, static function () use ($DB, $tenant): array {
            $raw = $DB->get_record_sql(
                "SELECT COALESCE(SUM(points), 0) AS points,
                        COUNT(DISTINCT userid) AS activelearners,
                        COUNT(id) AS awards
                   FROM {local_ge_ledger}
                  WHERE companyid = :companyid AND pointstype = :pointstype",
                ['companyid' => $tenant->companyid, 'pointstype' => 'xp']
            );
            $service = (new \local_global_events\local\ledger_repository())
                ->company_totals([(int)$tenant->companyid]);
            $service = $service[0] ?? ['points' => 0, 'activelearners' => 0, 'awards' => 0];
            $valid = (int)$raw->points === (int)$service['points']
                && (int)$raw->activelearners === (int)$service['activelearners']
                && (int)$raw->awards === (int)$service['awards'];
            return [
                'status' => $valid ? 'pass' : 'fail',
                'metric' => 'awards=' . (int)$raw->awards
                    . ';learners=' . (int)$raw->activelearners
                    . ';points=' . (int)$raw->points,
                'remediation' => 'TELEMETRY-AGGREGATE-DRIFT',
            ];
        });
        $this->check('telemetry', 'ledger_idempotency', $label, static function () use ($DB, $tenant): array {
            $duplicates = (int)$DB->get_field_sql(
                "SELECT COUNT(*)
                   FROM (
                         SELECT companyid, idempotencykey
                           FROM {local_ge_ledger}
                          WHERE companyid = :companyid
                       GROUP BY companyid, idempotencykey
                         HAVING COUNT(*) > 1
                        ) duplicatekeys",
                ['companyid' => $tenant->companyid]
            );
            return [
                'status' => $duplicates === 0 ? 'pass' : 'fail',
                'metric' => 'duplicate_keys=' . $duplicates,
                'remediation' => 'TELEMETRY-IDEMPOTENCY',
            ];
        });
    }

    /**
     * Generate every report twice and verify scope, masking and performance.
     *
     * @param object $tenant Tenant.
     * @param string $label Company label.
     * @param int $maxreportms Maximum duration.
     */
    private function verify_reports(object $tenant, string $label, int $maxreportms): void {
        $engine = new report_engine();
        $filters = ['since' => time() - (30 * DAYSECS), 'until' => time()];
        $visible = new analytics_scope((int)$tenant->companyid, (int)get_admin()->id, false);
        $masked = new analytics_scope((int)$tenant->companyid, (int)get_admin()->id, false, [], false);
        foreach (array_keys(report_catalog::all()) as $reportkey) {
            $this->check('reports', $reportkey, $label, static function () use (
                $engine,
                $filters,
                $visible,
                $masked,
                $maxreportms,
                $reportkey
            ): array {
                $start = hrtime(true);
                $first = $engine->generate($reportkey, $visible, $filters);
                $second = $engine->generate($reportkey, $visible, $filters);
                $maskedresult = $engine->generate($reportkey, $masked, $filters);
                $duration = (hrtime(true) - $start) / 1_000_000;
                $maskedvalid = true;
                foreach ($maskedresult->get_rows() as $row) {
                    if (array_key_exists('email', $row) && (string)$row['email'] !== '') {
                        $maskedvalid = false;
                    }
                    if (
                        array_key_exists('learner', $row)
                        && !preg_match('/^Learner [A-F0-9]{12}$/', (string)$row['learner'])
                    ) {
                        $maskedvalid = false;
                    }
                }
                $valid = hash_equals($first->get_checksum(), $second->get_checksum())
                    && $maskedvalid
                    && $duration <= $maxreportms;
                return [
                    'status' => $valid ? 'pass' : 'fail',
                    'metric' => 'rows=' . count($first->get_rows())
                        . ';deterministic='
                        . (hash_equals($first->get_checksum(), $second->get_checksum()) ? 'yes' : 'no')
                        . ';masked=' . ($maskedvalid ? 'yes' : 'no')
                        . ';budget_ms=' . $maxreportms,
                    'remediation' => $duration > $maxreportms
                        ? 'REPORT-PERFORMANCE-BUDGET'
                        : 'REPORT-SCOPE-OR-MASKING',
                ];
            });
        }
    }

    /**
     * Return selected tenant records.
     *
     * @param string[] $companyshortnames Filters.
     * @return object[]
     */
    private function tenants(array $companyshortnames): array {
        $filters = array_values(array_unique(array_filter(array_map('trim', $companyshortnames))));
        return array_values(array_filter(
            (new tenant_repository())->list(),
            static fn(object $tenant): bool => (string)$tenant->status === 'active'
                && (!$filters || in_array((string)$tenant->companyshortname, $filters, true))
        ));
    }

    /**
     * Find one company-context representative without returning identity data.
     *
     * @param int $companyid Company.
     * @param string $roleshortname Role.
     * @return int User ID, internal only.
     */
    private function company_role_user(int $companyid, string $roleshortname): int {
        global $DB;

        return (int)$DB->get_field_sql(
            "SELECT MIN(ra.userid)
               FROM {role_assignments} ra
               JOIN {role} r ON r.id = ra.roleid AND r.shortname = :roleshortname
               JOIN {context} ctx
                 ON ctx.id = ra.contextid
                AND ctx.contextlevel = :contextlevel
              WHERE ctx.instanceid = :companyid",
            [
                'roleshortname' => $roleshortname,
                'contextlevel' => CONTEXT_COMPANY,
                'companyid' => $companyid,
            ]
        );
    }

    /**
     * Check a database index by ordered leading columns.
     *
     * @param string $table Table.
     * @param string[] $columns Columns.
     * @param bool $unique Require unique.
     * @return bool
     */
    private function has_index(string $table, array $columns, bool $unique): bool {
        global $DB;

        foreach ($DB->get_indexes($table) as $index) {
            $indexcolumns = array_values(array_map('strtolower', $index['columns']));
            if (
                array_slice($indexcolumns, 0, count($columns)) === $columns
                && (!$unique || !empty($index['unique']))
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Measure and record one sanitized check result.
     *
     * @param string $component Component.
     * @param string $check Check name.
     * @param string $company Company shortname or platform.
     * @param callable $callback Check callback.
     */
    private function check(string $component, string $check, string $company, callable $callback): void {
        $start = hrtime(true);
        $memorystart = memory_get_usage(true);
        try {
            $outcome = $callback();
            $status = in_array($outcome['status'] ?? '', ['pass', 'warn', 'fail'], true)
                ? (string)$outcome['status']
                : 'fail';
            $metric = (string)($outcome['metric'] ?? '');
            $remediation = (string)($outcome['remediation'] ?? 'REVIEW-CHECK');
        } catch (\Throwable $exception) {
            $status = 'fail';
            $metric = 'exception=' . get_class($exception);
            $remediation = 'REVIEW-EXCEPTION';
        }
        $this->results[] = [
            'status' => $status,
            'company' => clean_param($company, PARAM_ALPHANUMEXT),
            'component' => clean_param($component, PARAM_ALPHANUMEXT),
            'check' => clean_param($check, PARAM_ALPHANUMEXT),
            'metric' => $metric,
            'durationms' => round((hrtime(true) - $start) / 1_000_000, 2),
            'memorybytes' => memory_get_usage(true) - $memorystart,
            'remediation' => $remediation,
        ];
    }
}
