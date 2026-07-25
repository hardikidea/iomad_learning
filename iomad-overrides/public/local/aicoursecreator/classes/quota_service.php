<?php
// This file is part of IOMAD - http://www.iomad.org/

namespace local_aicoursecreator;

defined('MOODLE_INTERNAL') || die();

/**
 * Concurrency-safe monthly company credit accounting.
 */
final class quota_service {
    public function consume(int $companyid, int $credits, ?string $periodkey = null): \stdClass {
        global $DB;

        if ($companyid <= 0 || $credits <= 0) {
            throw new \invalid_parameter_exception('A company and positive credit amount are required.');
        }
        $periodkey = $periodkey ?? gmdate('Y-m');
        if (!preg_match('/^\d{4}-\d{2}$/', $periodkey)) {
            throw new \invalid_parameter_exception('Invalid quota period.');
        }
        $transaction = $DB->start_delegated_transaction();
        $record = $DB->get_record('local_aicoursecreator_quota', [
            'companyid' => $companyid,
            'periodkey' => $periodkey,
        ]);
        if (!$record) {
            $limit = max(0, (int)(get_config('local_aicoursecreator', 'defaultcredits') ?: 300));
            try {
                $id = $DB->insert_record('local_aicoursecreator_quota', (object)[
                    'companyid' => $companyid,
                    'periodkey' => $periodkey,
                    'creditlimit' => $limit,
                    'creditsused' => 0,
                    'timemodified' => time(),
                ]);
                $record = $DB->get_record('local_aicoursecreator_quota', ['id' => $id], '*', MUST_EXIST);
            } catch (\dml_exception $exception) {
                $record = $DB->get_record('local_aicoursecreator_quota', [
                    'companyid' => $companyid,
                    'periodkey' => $periodkey,
                ], '*', MUST_EXIST);
            }
        }
        $record = $DB->get_record_sql(
            'SELECT * FROM {local_aicoursecreator_quota} WHERE id = :id FOR UPDATE',
            ['id' => $record->id],
            MUST_EXIST
        );
        if ((int)$record->creditsused + $credits > (int)$record->creditlimit) {
            throw new \moodle_exception('quotareached', 'local_aicoursecreator', '', $periodkey);
        }
        $record->creditsused = (int)$record->creditsused + $credits;
        $record->timemodified = time();
        $DB->update_record('local_aicoursecreator_quota', $record);
        $transaction->allow_commit();
        return $record;
    }

    public function set_limit(int $companyid, int $limit, ?string $periodkey = null): \stdClass {
        global $DB;

        if ($companyid <= 0 || $limit < 0) {
            throw new \invalid_parameter_exception('Invalid company credit limit.');
        }
        $periodkey = $periodkey ?? gmdate('Y-m');
        $record = $DB->get_record('local_aicoursecreator_quota', [
            'companyid' => $companyid,
            'periodkey' => $periodkey,
        ]);
        if (!$record) {
            $record = (object)[
                'companyid' => $companyid,
                'periodkey' => $periodkey,
                'creditlimit' => $limit,
                'creditsused' => 0,
                'timemodified' => time(),
            ];
            $record->id = $DB->insert_record('local_aicoursecreator_quota', $record);
        } else {
            if ($limit < (int)$record->creditsused) {
                throw new \invalid_parameter_exception('Credit limit cannot be lower than current usage.');
            }
            $record->creditlimit = $limit;
            $record->timemodified = time();
            $DB->update_record('local_aicoursecreator_quota', $record);
        }
        return $DB->get_record('local_aicoursecreator_quota', ['id' => $record->id], '*', MUST_EXIST);
    }
}
