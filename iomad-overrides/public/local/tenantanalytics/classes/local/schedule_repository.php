<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantanalytics\local;

/**
 * Owner-only report schedules and immutable delivery audits.
 *
 * @package    local_tenantanalytics
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class schedule_repository {
    /** @var string[] */
    private const FREQUENCIES = ['daily', 'weekly', 'monthly'];

    /**
     * Return supported schedule frequencies.
     *
     * @return array<string,string>
     */
    public static function frequencies(): array {
        return [
            'daily' => get_string('daily'),
            'weekly' => get_string('weekly', 'local_tenantanalytics'),
            'monthly' => get_string('monthly', 'local_tenantanalytics'),
        ];
    }

    /**
     * Create or update an owner schedule.
     *
     * @param object $data Form data.
     * @param access $access Resolved access.
     * @return object
     */
    public function save(object $data, access $access): object {
        global $DB, $USER;

        if (!$access->can_manage_schedules() || $access->get_companyid() <= 0) {
            throw new \required_capability_exception(
                $access->get_context(),
                'local/tenantanalytics:manageschedules',
                'nopermissions',
                ''
            );
        }
        $reportkey = (string)$data->reportkey;
        $dataformat = (string)$data->dataformat;
        $frequency = (string)$data->frequency;
        if (!report_catalog::exists($reportkey)) {
            throw new \invalid_parameter_exception('Unknown report key.');
        }
        if (!array_key_exists($dataformat, report_catalog::formats())) {
            throw new \invalid_parameter_exception('Unsupported export format.');
        }
        if (!in_array($frequency, self::FREQUENCIES, true)) {
            throw new \invalid_parameter_exception('Unsupported schedule frequency.');
        }
        $lookbackdays = (int)$data->lookbackdays;
        if (!in_array($lookbackdays, [7, 30, 90, 365], true)) {
            throw new \invalid_parameter_exception('Unsupported report lookback.');
        }
        $filters = [
            'lookbackdays' => $lookbackdays,
            'courseid' => max(0, (int)$data->courseid),
            'cohortid' => max(0, (int)$data->cohortid),
            'groupid' => max(0, (int)$data->groupid),
        ];
        $filtersjson = json_encode($filters, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $now = time();
        $record = (object)[
            'companyid' => $access->get_companyid(),
            'userid' => (int)$USER->id,
            'reportkey' => $reportkey,
            'dataformat' => $dataformat,
            'frequency' => $frequency,
            'filtersjson' => $filtersjson,
            'enabled' => empty($data->enabled) ? 0 : 1,
            'nextrun' => $this->next_run($frequency, $now),
            'lastrun' => 0,
            'lockeduntil' => 0,
            'locktoken' => '',
            'timemodified' => $now,
        ];

        if (!empty($data->id)) {
            $existing = $this->get_owned((int)$data->id, (int)$USER->id);
            $record->id = $existing->id;
            $record->timecreated = $existing->timecreated;
            $record->lastrun = $existing->lastrun;
            $DB->update_record('local_tanalytics_schedule', $record);
        } else {
            $record->timecreated = $now;
            $record->id = $DB->insert_record('local_tanalytics_schedule', $record);
        }
        return $DB->get_record('local_tanalytics_schedule', ['id' => $record->id], '*', MUST_EXIST);
    }

    /**
     * List schedules owned by a user.
     *
     * @param int $userid Owner.
     * @return object[]
     */
    public function list_for_owner(int $userid): array {
        global $DB;

        return array_values($DB->get_records(
            'local_tanalytics_schedule',
            ['userid' => $userid],
            'timecreated DESC, id DESC'
        ));
    }

    /**
     * Load a schedule only when owned by a user.
     *
     * @param int $id Schedule.
     * @param int $userid Owner.
     * @return object
     */
    public function get_owned(int $id, int $userid): object {
        global $DB;

        return $DB->get_record(
            'local_tanalytics_schedule',
            ['id' => $id, 'userid' => $userid],
            '*',
            MUST_EXIST
        );
    }

    /**
     * Delete an owned schedule and audits.
     *
     * @param int $id Schedule.
     * @param int $userid Owner.
     */
    public function delete_owned(int $id, int $userid): void {
        global $DB;

        $schedule = $this->get_owned($id, $userid);
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('local_tanalytics_run', ['scheduleid' => $schedule->id]);
        $DB->delete_records('local_tanalytics_schedule', ['id' => $schedule->id, 'userid' => $userid]);
        $transaction->allow_commit();
    }

    /**
     * Atomically claim due schedules for one task invocation.
     *
     * @param int $now Current time.
     * @param int $limit Maximum schedules.
     * @return object[]
     */
    public function claim_due(int $now, int $limit = 50): array {
        global $DB;

        $candidates = $DB->get_records_select(
            'local_tanalytics_schedule',
            'enabled = :enabled AND nextrun <= :runnow AND lockeduntil < :locknow',
            ['enabled' => 1, 'runnow' => $now, 'locknow' => $now],
            'nextrun ASC, id ASC',
            '*',
            0,
            $limit
        );
        if (!$candidates) {
            return [];
        }

        $token = bin2hex(random_bytes(24));
        $ids = array_map('intval', array_keys($candidates));
        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'schedule');
        $params['now'] = $now;
        $params['lockeduntil'] = $now + HOURSECS;
        $params['locktoken'] = $token;
        $sql = "UPDATE {local_tanalytics_schedule}
                   SET lockeduntil = :lockeduntil, locktoken = :locktoken
                 WHERE id {$insql}
                   AND lockeduntil < :now";
        $DB->execute($sql, $params);
        return array_values($DB->get_records(
            'local_tanalytics_schedule',
            ['locktoken' => $token],
            'nextrun ASC, id ASC'
        ));
    }

    /**
     * Complete a claimed delivery and record a non-PII audit.
     *
     * @param object $schedule Schedule.
     * @param string $status sent or failed.
     * @param int $rowcount Row count.
     * @param string $checksum Report checksum.
     * @param int $now Completion time.
     */
    public function complete(
        object $schedule,
        string $status,
        int $rowcount,
        string $checksum,
        int $now
    ): void {
        global $DB;

        if (!in_array($status, ['sent', 'failed', 'skipped'], true)) {
            throw new \invalid_parameter_exception('Unsupported delivery status.');
        }
        $transaction = $DB->start_delegated_transaction();
        $DB->insert_record('local_tanalytics_run', (object)[
            'scheduleid' => $schedule->id,
            'companyid' => $schedule->companyid,
            'userid' => $schedule->userid,
            'reportkey' => $schedule->reportkey,
            'rowcount' => max(0, $rowcount),
            'checksum' => $checksum,
            'status' => $status,
            'timecreated' => $now,
        ]);
        $DB->set_field('local_tanalytics_schedule', 'lastrun', $now, ['id' => $schedule->id]);
        $DB->set_field(
            'local_tanalytics_schedule',
            'nextrun',
            $this->next_run($schedule->frequency, $now),
            ['id' => $schedule->id]
        );
        $DB->set_field('local_tanalytics_schedule', 'lockeduntil', 0, ['id' => $schedule->id]);
        $DB->set_field('local_tanalytics_schedule', 'locktoken', '', ['id' => $schedule->id]);
        $transaction->allow_commit();
    }

    /**
     * Materialize a rolling date range at delivery time.
     *
     * @param object $schedule Schedule.
     * @param int $now Current time.
     * @return array
     */
    public function filters_for_run(object $schedule, int $now): array {
        $filters = json_decode($schedule->filtersjson, true, 8, JSON_THROW_ON_ERROR);
        $lookbackdays = (int)($filters['lookbackdays'] ?? 30);
        return [
            'since' => $now - ($lookbackdays * DAYSECS),
            'until' => $now,
            'courseid' => max(0, (int)($filters['courseid'] ?? 0)),
            'cohortid' => max(0, (int)($filters['cohortid'] ?? 0)),
            'groupid' => max(0, (int)($filters['groupid'] ?? 0)),
        ];
    }

    /**
     * Calculate next execution time.
     *
     * @param string $frequency Frequency.
     * @param int $from Reference time.
     * @return int
     */
    public function next_run(string $frequency, int $from): int {
        return match ($frequency) {
            'daily' => $from + DAYSECS,
            'weekly' => $from + WEEKSECS,
            'monthly' => (int)strtotime('+1 month', $from),
            default => throw new \invalid_parameter_exception('Unsupported schedule frequency.'),
        };
    }
}
