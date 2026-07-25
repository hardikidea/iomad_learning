<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\local;

/**
 * Idempotent automatic projection processor.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class projection_service {
    /**
     * Constructor.
     *
     * @param projection_adapter $adapter IOMAD adapter.
     * @param tenant_repository $tenants Tenant repository.
     * @param master_repository $masters Master repository.
     * @param mapping_repository $mappings Mapping repository.
     * @param audit_service $audit Audit.
     */
    public function __construct(
        private readonly projection_adapter $adapter = new iomad_501_adapter(),
        private readonly tenant_repository $tenants = new tenant_repository(),
        private readonly master_repository $masters = new master_repository(),
        private readonly mapping_repository $mappings = new mapping_repository(),
        private readonly audit_service $audit = new audit_service(),
    ) {
    }

    /**
     * Process pending work for one tenant and optional module.
     *
     * @param int $tenantid Tenant ID.
     * @param string $module Optional module.
     * @param int $limit Work item limit.
     * @return object Job record.
     */
    public function process(int $tenantid, string $module = '', int $limit = 100): object {
        global $DB, $USER;

        $tenant = $this->tenants->get($tenantid);
        $factory = \core\lock\lock_config::get_lock_factory('local_tenantmaster');
        // Module-specific and Sync All workers must never process the same
        // tenant concurrently.
        $lock = $factory->get_lock('tenant:' . $tenantid, 0);
        if (!$lock) {
            throw new \moodle_exception('projectionlocked', 'local_tenantmaster');
        }

        try {
            $params = ['tenantid' => $tenantid, 'now' => time()];
            $modulesql = '';
            if ($module !== '') {
                $modulesql = 'AND module = :module';
                $params['module'] = $module;
            }
            $dirtyrecords = $DB->get_records_sql(
                "SELECT *
                   FROM {local_tenantmaster_dirty}
                  WHERE tenantid = :tenantid
                    AND state IN ('dirty', 'retryable')
                    AND availabletime <= :now
                        $modulesql
               ORDER BY id ASC",
                $params,
                0,
                $limit,
            );
            $jobid = (int)$DB->insert_record('local_tenantmaster_job', (object)[
                'tenantid' => $tenantid,
                'module' => $module ?: 'all',
                'mode' => 'apply',
                'status' => 'running',
                'actorid' => (int)($USER->id ?? 0),
                'totalitems' => count($dirtyrecords),
                'completeditems' => 0,
                'faileditems' => 0,
                'currentstep' => 'projection',
                'backupref' => null,
                'imageid' => null,
                'message' => null,
                'timecreated' => time(),
                'timestarted' => time(),
                'timefinished' => 0,
            ]);

            $completed = 0;
            $failed = 0;
            foreach ($dirtyrecords as $dirty) {
                try {
                    $this->process_record($tenant, $dirty, $jobid);
                    $completed++;
                } catch (\Throwable $exception) {
                    $failed++;
                    $this->fail_record($dirty, $jobid, $exception);
                }
            }
            $job = $DB->get_record('local_tenantmaster_job', ['id' => $jobid], '*', MUST_EXIST);
            $job->completeditems = $completed;
            $job->faileditems = $failed;
            $job->status = $failed > 0 ? 'completed_with_errors' : 'completed';
            $job->currentstep = 'complete';
            $job->timefinished = time();
            $DB->update_record('local_tenantmaster_job', $job);
            return $job;
        } finally {
            $lock->release();
        }
    }

    /**
     * Project one dirty record.
     *
     * @param object $tenant Tenant.
     * @param object $dirty Dirty.
     * @param int $jobid Job.
     */
    private function process_record(object $tenant, object $dirty, int $jobid): void {
        global $DB;

        $dirty->state = 'running';
        $dirty->attempts = (int)$dirty->attempts + 1;
        $dirty->timemodified = time();
        $DB->update_record('local_tenantmaster_dirty', $dirty);

        $result = null;
        $masterid = 0;
        if ($dirty->entitytable === 'local_tenantmaster_tenant') {
            if ($dirty->module === 'tenant') {
                $result = $this->adapter->project_tenant($tenant);
            }
        } else if ($dirty->entitytable === 'local_tenantmaster_master') {
            $master = $this->masters->get((int)$tenant->id, (int)$dirty->entityid);
            $masterid = (int)$master->id;
            $result = $this->adapter->project_master($tenant, $master, (string)$dirty->module);
        } else if ($dirty->entitytable === 'local_tenantmaster_acadyear' && $dirty->module === 'categories') {
            $academicyear = $DB->get_record('local_tenantmaster_acadyear', [
                'id' => $dirty->entityid,
                'tenantid' => $tenant->id,
            ], '*', MUST_EXIST);
            $result = $this->adapter->project_academic_year($tenant, $academicyear);
        } else if (
            $dirty->entitytable === 'course'
                && in_array($dirty->module, ['assessments', 'attendance'], true)
        ) {
            (new course_configuration_service())->apply($tenant, (int)$dirty->entityid);
        } else if ($dirty->entitytable === 'course' && $dirty->module === 'certificates') {
            (new certificate_service())->ensure($tenant, (int)$dirty->entityid);
        }
        if ($result) {
            $this->mappings->save((int)$tenant->id, $masterid, $result);
            if ($result->component === 'core/course') {
                $queue = new queue_service();
                foreach (['assessments', 'attendance', 'certificates'] as $coursemodule) {
                    $queue->mark_dirty(
                        (int)$tenant->id,
                        $coursemodule,
                        'course',
                        $result->targetid,
                        'course_projected',
                    );
                }
            }
        }

        $dirty->state = 'synced';
        $dirty->locktoken = null;
        $dirty->lasterror = null;
        $dirty->timemodified = time();
        $DB->update_record('local_tenantmaster_dirty', $dirty);
        $this->save_job_item($jobid, $dirty, 'synced', $result);
        $this->audit->record(
            (int)$tenant->id,
            'projection.completed',
            'success',
            ['module' => $dirty->module],
            [
                'jobid' => $jobid,
                'entitytable' => $dirty->entitytable,
                'entityid' => (int)$dirty->entityid,
                'targetcomponent' => $result?->component ?? '',
                'targetid' => $result?->targetid ?? 0,
            ],
        );
    }

    /**
     * Record a retryable or blocked failure.
     *
     * @param object $dirty Dirty.
     * @param int $jobid Job.
     * @param \Throwable $exception Failure.
     */
    private function fail_record(object $dirty, int $jobid, \Throwable $exception): void {
        global $DB;

        $retrylimit = max(1, (int)(get_config('local_tenantmaster', 'retrylimit') ?: 5));
        $dirty->state = (int)$dirty->attempts >= $retrylimit ? 'blocked' : 'retryable';
        $dirty->availabletime = time() + min(3600, 2 ** min(10, (int)$dirty->attempts));
        $dirty->locktoken = null;
        $dirty->lasterror = substr($exception->getMessage(), 0, 2000);
        $dirty->timemodified = time();
        $DB->update_record('local_tenantmaster_dirty', $dirty);
        $this->save_job_item($jobid, $dirty, $dirty->state, null, $dirty->lasterror);
        $this->audit->record(
            (int)$dirty->tenantid,
            'projection.failed',
            $dirty->state,
            ['module' => $dirty->module, 'exception' => get_class($exception)],
            [
                'jobid' => $jobid,
                'entitytable' => $dirty->entitytable,
                'entityid' => (int)$dirty->entityid,
            ],
        );
    }

    /**
     * Save per-item job output.
     *
     * @param int $jobid Job.
     * @param object $dirty Dirty.
     * @param string $status Status.
     * @param projection_result|null $result Result.
     * @param string|null $message Message.
     */
    private function save_job_item(
        int $jobid,
        object $dirty,
        string $status,
        ?projection_result $result,
        ?string $message = null,
    ): void {
        global $DB;

        $now = time();
        $existing = $DB->get_record('local_tenantmaster_jobitem', [
            'jobid' => $jobid,
            'dirtyid' => $dirty->id,
        ]);
        $record = (object)[
            'jobid' => $jobid,
            'dirtyid' => $dirty->id,
            'entitytable' => $dirty->entitytable,
            'entityid' => $dirty->entityid,
            'status' => $status,
            'targetcomponent' => $result?->component,
            'targetid' => $result?->targetid ?? 0,
            'message' => $message,
            'timemodified' => $now,
        ];
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('local_tenantmaster_jobitem', $record);
        } else {
            $record->timecreated = $now;
            $DB->insert_record('local_tenantmaster_jobitem', $record);
        }
    }
}
