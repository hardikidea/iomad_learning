<?php
// This file is part of Moodle - http://moodle.org/

namespace local_global_events\local;

/**
 * Immutable gamification ledger repository.
 *
 * @package local_global_events
 */
final class ledger_repository {
    /**
     * Insert once by company-scoped idempotency key.
     *
     * @param array $record Validated record.
     * @return array Record and insertion status.
     */
    public function insert_once(array $record): array {
        global $DB;

        $conditions = [
            'companyid' => (int)$record['companyid'],
            'idempotencykey' => (string)$record['idempotencykey'],
        ];
        $existing = $DB->get_record('local_ge_ledger', $conditions);
        if ($existing) {
            $this->require_same_payload($existing, $record);
            return ['record' => $existing, 'inserted' => false];
        }
        try {
            $record['id'] = $DB->insert_record('local_ge_ledger', (object)$record);
        } catch (\dml_write_exception $exception) {
            $existing = $DB->get_record('local_ge_ledger', $conditions);
            if (!$existing) {
                throw $exception;
            }
            $this->require_same_payload($existing, $record);
            return ['record' => $existing, 'inserted' => false];
        }
        return ['record' => (object)$record, 'inserted' => true];
    }

    /**
     * Reject an idempotency key reused with different content.
     *
     * @param object $existing Existing record.
     * @param array $record Candidate.
     */
    private function require_same_payload(object $existing, array $record): void {
        foreach (
            [
                'userid', 'courseid', 'cmid', 'pointstype', 'points',
                'sourcecomponent', 'sourceevent', 'metadatahash',
            ] as $field
        ) {
            if ((string)$existing->{$field} !== (string)$record[$field]) {
                throw new \invalid_parameter_exception(
                    'A gamification event key cannot be reused with different content.',
                );
            }
        }
    }

    /**
     * User XP total within one company.
     *
     * @param int $companyid Company.
     * @param int $userid User.
     * @return int
     */
    public function total(int $companyid, int $userid): int {
        global $DB;

        return (int)$DB->get_field_sql(
            "SELECT COALESCE(SUM(points), 0)
               FROM {local_ge_ledger}
              WHERE companyid = :companyid
                AND userid = :userid
                AND pointstype = :pointstype",
            ['companyid' => $companyid, 'userid' => $userid, 'pointstype' => 'xp'],
        );
    }

    /**
     * Aggregate totals only, with no learner records.
     *
     * @param int[] $companyids Company IDs.
     * @return array
     */
    public function company_totals(array $companyids): array {
        global $DB;

        if (!$companyids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($companyids, SQL_PARAMS_NAMED, 'company');
        $records = $DB->get_records_sql(
            "SELECT companyid,
                    COALESCE(SUM(points), 0) AS points,
                    COUNT(DISTINCT userid) AS activelearners,
                    COUNT(id) AS awards
               FROM {local_ge_ledger}
              WHERE companyid {$insql}
                AND pointstype = :pointstype
           GROUP BY companyid
           ORDER BY companyid",
            $params + ['pointstype' => 'xp'],
        );
        return array_map(static fn(object $record): array => [
            'companyid' => (int)$record->companyid,
            'points' => (int)$record->points,
            'activelearners' => (int)$record->activelearners,
            'awards' => (int)$record->awards,
        ], array_values($records));
    }
}
