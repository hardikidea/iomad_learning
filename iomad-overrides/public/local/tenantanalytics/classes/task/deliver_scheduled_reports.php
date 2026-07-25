<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantanalytics\task;

use local_iomad\custom_context\context_company;
use local_iomad\iomad;
use local_tenantanalytics\local\access;
use local_tenantanalytics\local\exporter;
use local_tenantanalytics\local\report_catalog;
use local_tenantanalytics\local\report_engine;
use local_tenantanalytics\local\schedule_repository;

/**
 * Deliver due tenant reports to each schedule owner.
 *
 * @package    local_tenantanalytics
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class deliver_scheduled_reports extends \core\task\scheduled_task {
    /**
     * Return task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskscheduledreports', 'local_tenantanalytics');
    }

    /**
     * Execute due deliveries.
     */
    public function execute(): void {
        global $DB, $USER;

        $repository = new schedule_repository();
        $now = time();
        foreach ($repository->claim_due($now) as $schedule) {
            $status = 'failed';
            $rowcount = 0;
            $checksum = '';
            $filepath = null;
            $originaluser = $USER;
            try {
                $user = $DB->get_record(
                    'user',
                    ['id' => $schedule->userid, 'deleted' => 0, 'suspended' => 0],
                    '*',
                    MUST_EXIST
                );
                \core\session\manager::set_user($user);
                $context = context_company::instance((int)$schedule->companyid);
                if (
                    !iomad::has_capability(
                        'local/tenantanalytics:manageschedules',
                        $context,
                        (int)$schedule->companyid
                    )
                ) {
                    $status = 'skipped';
                } else {
                    $scope = access::company_scope_for_user($user, (int)$schedule->companyid, $context);
                    $filters = $repository->filters_for_run($schedule, $now);
                    $result = (new report_engine())->generate($schedule->reportkey, $scope, $filters);
                    $rowcount = count($result->get_rows());
                    $checksum = $result->get_checksum();
                    $basename = 'tenant-report-' . $schedule->reportkey . '-' . gmdate('Ymd-His', $now);
                    $filepath = (new exporter())->write($basename, $schedule->dataformat, $result);
                    $reportname = report_catalog::all()[$schedule->reportkey];
                    $subject = get_string('scheduledsubject', 'local_tenantanalytics', $reportname);
                    $body = get_string('scheduledbody', 'local_tenantanalytics', (object)[
                        'report' => $reportname,
                        'rows' => $rowcount,
                        'generated' => userdate($now),
                    ]);
                    $status = email_to_user(
                        $user,
                        get_noreply_user(),
                        $subject,
                        $body,
                        '',
                        $filepath,
                        basename($filepath)
                    ) ? 'sent' : 'failed';
                }
            } catch (\Throwable $exception) {
                unset($exception);
                $status = 'failed';
            } finally {
                if ($filepath && is_file($filepath)) {
                    unlink($filepath);
                }
                \core\session\manager::set_user($originaluser);
            }
            $repository->complete($schedule, $status, $rowcount, $checksum, time());
            mtrace("Tenant analytics schedule {$schedule->id}: {$status}");
        }
    }
}
