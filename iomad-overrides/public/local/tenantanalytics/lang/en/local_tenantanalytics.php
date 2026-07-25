<?php
// This file is part of Moodle - http://moodle.org/

/**
 * English strings for tenant analytics.
 *
 * @package    local_tenantanalytics
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Tenant analytics';
$string['activity'] = 'Activity';
$string['allocated'] = 'Allocated';
$string['allocatedusers'] = 'Allocated users';
$string['allcourses'] = 'All courses';
$string['company'] = 'Company';
$string['completed'] = 'Completed';
$string['completionrate'] = 'Completion rate';
$string['completions'] = 'Completions';
$string['confirmscheduledelete'] = 'Delete this scheduled report and its delivery audit records?';
$string['course'] = 'Course';
$string['courses'] = 'Courses';
$string['daily'] = 'Daily';
$string['deleteduser'] = 'Deleted user';
$string['enabled'] = 'Enabled';
$string['enrolled'] = 'Enrolled';
$string['eventlimit'] = 'The report exceeds the safe processing limit of {$a} events. Reduce the date range or filters.';
$string['events'] = 'Events';
$string['estimatedtime'] = 'Estimated active time';
$string['expires'] = 'Expires';
$string['expirydate'] = 'Expiry date';
$string['exportformat'] = 'Export format';
$string['firstactivity'] = 'First activity';
$string['frequency'] = 'Frequency';
$string['from'] = 'From';
$string['inprogress'] = 'In progress';
$string['inuse'] = 'In use';
$string['issued'] = 'Issued';
$string['lastactivity'] = 'Last activity';
$string['lastndays'] = 'Last {$a} days';
$string['learner'] = 'Learner';
$string['learners'] = 'Learners';
$string['license'] = 'License';
$string['lookback'] = 'Rolling report window';
$string['manageschedules'] = 'Scheduled reports';
$string['members'] = 'Members';
$string['monthly'] = 'Monthly';
$string['never'] = 'Never';
$string['nextrun'] = 'Next run';
$string['notstarted'] = 'Not started';
$string['previewlimit'] = 'The browser preview is limited to {$a} rows. Export the report to receive every row.';
$string['remaining'] = 'Remaining';
$string['report'] = 'Report';
$string['reportcohortgroup'] = 'Cohort and group analysis';
$string['reportcompletion'] = 'Completion and recertification';
$string['reportcourseengagement'] = 'Course engagement';
$string['reportlearner'] = 'Learner report';
$string['reportlicenseusage'] = 'License usage';
$string['reportstudentengagement'] = 'Student engagement';
$string['reporttimeactivity'] = 'Time on activities';
$string['reporttimecourse'] = 'Time on courses';
$string['reporttimesite'] = 'Time on LMS';
$string['reportvisits'] = 'LMS visits';
$string['resultsummary'] = '{$a} rows generated.';
$string['riskcritical'] = 'Critical: no activity in range';
$string['riskflag'] = 'Deterministic risk flag';
$string['riskhigh'] = 'High: inactive for 14 days';
$string['risklow'] = 'Low';
$string['riskmedium'] = 'Medium: low activity and no completion';
$string['scheduledeleted'] = 'Scheduled report deleted.';
$string['schedulesaved'] = 'Scheduled report saved.';
$string['scheduledbody'] = 'Your scheduled {$a->report} report is attached.'
    . "\n\n" . 'Rows: {$a->rows}' . "\n" . 'Generated: {$a->generated}' . "\n";
$string['scheduledsubject'] = 'Scheduled report: {$a}';
$string['score'] = 'Score';
$string['startdate'] = 'Start date';
$string['started'] = 'Started';
$string['taskscheduledreports'] = 'Deliver tenant analytics reports';
$string['timeestimatornotice'] = 'Active time is an estimate based on consecutive standard-log events, with each gap capped at 30 minutes. It is not a stopwatch measurement.';
$string['to'] = 'To';
$string['type'] = 'Type';
$string['uniquelearners'] = 'Unique learners';
$string['unknownactivity'] = 'Unknown activity';
$string['unknowncourse'] = 'Unknown course';
$string['used'] = 'Used';
$string['userlimit'] = 'The report exceeds the safe processing limit of {$a} users. Apply a cohort, group, course, or date filter.';
$string['viewreport'] = 'View report';
$string['weekly'] = 'Weekly';
$string['local/tenantanalytics:manageschedules'] = 'Manage own scheduled tenant reports';
$string['local/tenantanalytics:viewcompany'] = 'View company-scoped analytics';
$string['local/tenantanalytics:viewpii'] = 'View learner identity in company analytics';
$string['maskedlearner'] = 'Learner {$a}';
$string['pseudonymkeymissing'] = 'The analytics pseudonym key is missing. Complete the plugin upgrade before generating masked reports.';
$string['local/tenantanalytics:viewown'] = 'View own learning analytics';

$string['privacy:metadata:run'] = 'Non-content audit data for scheduled report deliveries.';
$string['privacy:metadata:run:checksum'] = 'A checksum of the delivered report.';
$string['privacy:metadata:run:companyid'] = 'The company boundary used for the report.';
$string['privacy:metadata:run:reportkey'] = 'The generated report type.';
$string['privacy:metadata:run:rowcount'] = 'The number of generated report rows.';
$string['privacy:metadata:run:status'] = 'The delivery outcome.';
$string['privacy:metadata:run:timecreated'] = 'When delivery was attempted.';
$string['privacy:metadata:run:userid'] = 'The schedule owner.';
$string['privacy:metadata:schedule'] = 'A user-owned scheduled tenant report.';
$string['privacy:metadata:schedule:companyid'] = 'The company boundary used for the report.';
$string['privacy:metadata:schedule:dataformat'] = 'The selected export format.';
$string['privacy:metadata:schedule:enabled'] = 'Whether the schedule is enabled.';
$string['privacy:metadata:schedule:filters'] = 'Non-personal report filter identifiers and lookback period.';
$string['privacy:metadata:schedule:frequency'] = 'The delivery frequency.';
$string['privacy:metadata:schedule:lastrun'] = 'When the schedule last ran.';
$string['privacy:metadata:schedule:nextrun'] = 'When the schedule will next run.';
$string['privacy:metadata:schedule:reportkey'] = 'The selected report type.';
$string['privacy:metadata:schedule:userid'] = 'The schedule owner.';
