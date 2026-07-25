<?php
// This file is part of IOMAD - http://www.iomad.org/

namespace local_aicoursecreator;

defined('MOODLE_INTERNAL') || die();

/**
 * Transactional state machine for tenant course drafts.
 */
final class draft_repository {
    private const TRANSITIONS = [
        'draft' => ['queued'],
        'failed' => ['queued'],
        'queued' => ['generating', 'failed'],
        'generating' => ['review', 'failed'],
        'review' => ['review', 'approved'],
        'approved' => ['published'],
        'published' => [],
    ];

    public function create(array $input, int $companyid, int $userid): \stdClass {
        global $DB;

        if ($companyid <= 0) {
            throw new \invalid_parameter_exception('AI drafts require an IOMAD company.');
        }
        $title = trim(strip_tags((string)($input['title'] ?? '')));
        $brief = trim((string)($input['brief'] ?? ''));
        if ($title === '' || \core_text::strlen($title) > 254 || $brief === '') {
            throw new \invalid_parameter_exception('A title and course brief are required.');
        }
        $options = [
            'audience' => trim(strip_tags((string)($input['audience'] ?? ''))),
            'tone' => trim(strip_tags((string)($input['tone'] ?? 'professional'))),
            'sectioncount' => min(30, max(1, (int)($input['sectioncount'] ?? 5))),
        ];
        $now = time();
        $record = (object)[
            'uuid' => \core\uuid::generate(),
            'companyid' => $companyid,
            'title' => $title,
            'brief' => $brief,
            'optionsjson' => json_encode($options, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            'status' => 'draft',
            'credits' => 0,
            'createdby' => $userid,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $transaction = $DB->start_delegated_transaction();
        $record->id = $DB->insert_record('local_aicoursecreator_draft', $record);
        $this->audit($record, 'created', $userid, ['schema_version' => 1]);
        $transaction->allow_commit();
        return $this->get($record->id, $companyid);
    }

    public function get(int $id, int $companyid): \stdClass {
        global $DB;

        $record = $DB->get_record('local_aicoursecreator_draft', ['id' => $id], '*', MUST_EXIST);
        if ((int)$record->companyid !== $companyid) {
            throw new \required_capability_exception(
                tenant_context::context($companyid),
                'local/aicoursecreator:manage',
                'nopermissions',
                ''
            );
        }
        return $record;
    }

    public function list_for_company(int $companyid): array {
        global $DB;

        return array_values($DB->get_records(
            'local_aicoursecreator_draft',
            ['companyid' => $companyid],
            'timemodified DESC'
        ));
    }

    public function queue(int $id, int $companyid, int $userid): \stdClass {
        return $this->transition($id, $companyid, 'queued', $userid, [], [
            'timequeued' => time(),
        ]);
    }

    public function mark_generating(int $id, int $companyid, int $userid, int $credits): \stdClass {
        return $this->transition($id, $companyid, 'generating', $userid, ['credits' => $credits], [
            'credits' => $credits,
        ]);
    }

    public function save_generated(
        int $id,
        int $companyid,
        int $userid,
        array $definition,
        ?string $provider,
        ?string $model
    ): \stdClass {
        global $DB;

        $definition = (new course_schema_validator())->validate($definition);
        $json = json_encode($definition, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $transaction = $DB->start_delegated_transaction();
        $record = $this->get_locked($id, $companyid);
        $record->definition = $json;
        $record->checksum = hash('sha256', $json);
        $record->provider = $provider;
        $record->model = $model;
        $record = $this->transition_record($record, 'review', $userid, [
            'checksum' => $record->checksum,
            'provider' => $provider,
            'model' => $model,
        ]);
        $transaction->allow_commit();
        return $record;
    }

    public function save_review(int $id, int $companyid, int $userid, string $json): \stdClass {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        $record = $this->get_locked($id, $companyid);
        if ($record->status !== 'review') {
            $this->invalid_transition($record->status, 'review');
        }
        $definition = (new course_schema_validator())->from_json($json);
        $normalised = json_encode(
            $definition,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        if (hash_equals((string)$record->checksum, hash('sha256', $normalised))) {
            $transaction->allow_commit();
            return $record;
        }
        $record->definition = $normalised;
        $record->checksum = hash('sha256', $normalised);
        $record->timemodified = time();
        $this->update($record);
        $this->audit($record, 'review_edited', $userid, ['checksum' => $record->checksum]);
        $transaction->allow_commit();
        return $record;
    }

    public function approve(int $id, int $companyid, int $userid): \stdClass {
        return $this->transition($id, $companyid, 'approved', $userid, [], [
            'reviewedby' => $userid,
            'timereviewed' => time(),
        ]);
    }

    public function mark_published(int $id, int $companyid, int $userid, int $courseid): \stdClass {
        return $this->transition($id, $companyid, 'published', $userid, ['courseid' => $courseid], [
            'courseid' => $courseid,
            'publishedby' => $userid,
            'timepublished' => time(),
        ]);
    }

    public function mark_failed(int $id, int $companyid, int $userid, string $errorclass): \stdClass {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        $record = $this->get_locked($id, $companyid);
        if (!in_array('failed', self::TRANSITIONS[$record->status] ?? [], true)) {
            $transaction->allow_commit();
            return $record;
        }
        $record = $this->transition_record($record, 'failed', $userid, [
            'errorclass' => clean_param($errorclass, PARAM_ALPHANUMEXT),
        ]);
        $transaction->allow_commit();
        return $record;
    }

    public function definition(\stdClass $record): array {
        if (empty($record->definition)) {
            throw new \moodle_exception('invaliddefinition', 'local_aicoursecreator', '', 'No definition exists.');
        }
        return (new course_schema_validator())->from_json($record->definition);
    }

    private function transition(
        int $id,
        int $companyid,
        string $to,
        int $userid,
        array $metadata = [],
        array $updates = []
    ): \stdClass {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        $record = $this->get_locked($id, $companyid);
        foreach ($updates as $field => $value) {
            $record->{$field} = $value;
        }
        $record = $this->transition_record($record, $to, $userid, $metadata);
        $transaction->allow_commit();
        return $record;
    }

    private function transition_record(\stdClass $record, string $to, int $userid, array $metadata = []): \stdClass {
        if (!in_array($to, self::TRANSITIONS[$record->status] ?? [], true)) {
            $this->invalid_transition($record->status, $to);
        }
        $from = $record->status;
        $record->status = $to;
        $record->timemodified = time();
        $this->update($record);
        $this->audit($record, "{$from}_to_{$to}", $userid, $metadata);
        return $record;
    }

    private function update(\stdClass $record): void {
        global $DB;
        $DB->update_record('local_aicoursecreator_draft', $record);
    }

    private function get_locked(int $id, int $companyid): \stdClass {
        global $DB;

        $record = $DB->get_record_sql(
            'SELECT * FROM {local_aicoursecreator_draft} WHERE id = :id FOR UPDATE',
            ['id' => $id],
            MUST_EXIST
        );
        if ((int)$record->companyid !== $companyid) {
            throw new \required_capability_exception(
                tenant_context::context($companyid),
                'local/aicoursecreator:manage',
                'nopermissions',
                ''
            );
        }
        return $record;
    }

    private function audit(\stdClass $record, string $event, int $actorid, array $metadata = []): void {
        global $DB;
        $DB->insert_record('local_aicoursecreator_audit', (object)[
            'draftid' => $record->id,
            'companyid' => $record->companyid,
            'eventname' => clean_param($event, PARAM_ALPHANUMEXT),
            'actorid' => $actorid,
            'metadatajson' => $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR),
            'timecreated' => time(),
        ]);
    }

    private function invalid_transition(string $from, string $to): never {
        throw new \moodle_exception('invalidtransition', 'local_aicoursecreator', '', (object)[
            'from' => $from,
            'to' => $to,
        ]);
    }
}
