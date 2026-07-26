<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\local;

/**
 * Reviewed student progression planning and application.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class student_progression_service {
    /** @var string[] */
    public const DECISIONS = ['promote', 'repeat', 'conditional', 'transferred', 'withdrawn', 'graduated'];

    /**
     * Create or replace a non-destructive progression plan.
     */
    public function plan(object $tenant, object $data): object {
        global $DB, $USER;

        $source = $DB->get_record('local_tenantmaster_placement', [
            'id' => (int)$data->sourceplaceid,
            'tenantid' => $tenant->id,
        ], '*', MUST_EXIST);
        $targetyear = $DB->get_record('local_tenantmaster_acadyear', [
            'id' => (int)$data->toyearid,
            'tenantid' => $tenant->id,
        ], '*', MUST_EXIST);
        $sourceyear = $DB->get_record('local_tenantmaster_acadyear', [
            'id' => $source->acadyearid,
            'tenantid' => $tenant->id,
        ], '*', MUST_EXIST);
        if ((int)$targetyear->startdate <= (int)$sourceyear->startdate) {
            throw new \invalid_parameter_exception('Target academic year must follow the source year.');
        }
        $decision = (string)$data->decision;
        if (!in_array($decision, self::DECISIONS, true)) {
            throw new \invalid_parameter_exception('Invalid progression decision.');
        }
        $needsplacement = in_array($decision, ['promote', 'repeat'], true);
        foreach (['targetgradeid' => 'grade', 'targetdivisionid' => 'division'] as $field => $type) {
            $id = (int)($data->{$field} ?? 0);
            if ($needsplacement && $id <= 0) {
                throw new \invalid_parameter_exception('A target grade and division are required.');
            }
            if ($id > 0) {
                $this->require_master($tenant, $id, $type, $targetyear);
            }
        }
        if ((int)($data->targetstreamid ?? 0) > 0) {
            $this->require_master($tenant, (int)$data->targetstreamid, 'stream', $targetyear);
        }

        $existing = $DB->get_record('local_tenantmaster_progress', [
            'sourceplaceid' => $source->id,
            'toyearid' => $targetyear->id,
        ]);
        $record = (object)[
            'tenantid' => (int)$tenant->id,
            'sourceplaceid' => (int)$source->id,
            'toyearid' => (int)$targetyear->id,
            'decision' => $decision,
            'targetgradeid' => (int)($data->targetgradeid ?? 0),
            'targetstreamid' => (int)($data->targetstreamid ?? 0),
            'targetdivisionid' => (int)($data->targetdivisionid ?? 0),
            'targetplaceid' => 0,
            'status' => 'planned',
            'reason' => trim((string)($data->reason ?? '')),
            'createdby' => (int)($USER->id ?? 0),
            'approvedby' => 0,
            'timecreated' => time(),
            'timeapproved' => 0,
            'timefinished' => 0,
        ];
        if ($existing) {
            if ((string)$existing->status === 'completed') {
                throw new \invalid_parameter_exception('A completed progression cannot be replaced.');
            }
            $record->id = $existing->id;
            $DB->update_record('local_tenantmaster_progress', $record);
        } else {
            $record->id = $DB->insert_record('local_tenantmaster_progress', $record);
        }
        (new audit_service())->record(
            (int)$tenant->id,
            'school.progression.planned',
            'success',
            ['decision' => $decision],
            ['entitytable' => 'local_tenantmaster_progress', 'entityid' => (int)$record->id],
        );
        return $DB->get_record('local_tenantmaster_progress', ['id' => $record->id], '*', MUST_EXIST);
    }

    /**
     * Apply one approved plan without rewriting prior-year outcomes.
     */
    public function apply(object $tenant, int $progressid): object {
        global $DB, $USER;

        $plan = $DB->get_record('local_tenantmaster_progress', [
            'id' => $progressid,
            'tenantid' => $tenant->id,
            'status' => 'planned',
        ], '*', MUST_EXIST);
        if ((string)$plan->decision === 'conditional') {
            throw new \invalid_parameter_exception('A conditional recommendation must be changed before application.');
        }
        $source = $DB->get_record('local_tenantmaster_placement', [
            'id' => $plan->sourceplaceid,
            'tenantid' => $tenant->id,
        ], '*', MUST_EXIST);
        $targetplaceid = 0;
        if (in_array((string)$plan->decision, ['promote', 'repeat'], true)) {
            $targetyear = $DB->get_record('local_tenantmaster_acadyear', [
                'id' => $plan->toyearid,
                'tenantid' => $tenant->id,
            ], '*', MUST_EXIST);
            $target = (new student_placement_service())->save($tenant, (object)[
                'id' => 0,
                'userid' => (int)$source->userid,
                'acadyearid' => (int)$plan->toyearid,
                'boardid' => $this->equivalent_master_id(
                    $tenant,
                    (int)$source->boardid,
                    'board',
                    $targetyear,
                ),
                'mediumid' => $this->equivalent_master_id(
                    $tenant,
                    (int)$source->mediumid,
                    'medium',
                    $targetyear,
                ),
                'gradeid' => (int)$plan->targetgradeid,
                'streamid' => (int)$plan->targetstreamid,
                'divisionid' => (int)$plan->targetdivisionid,
                'rollnumber' => '',
                'status' => 'active',
            ]);
            $targetplaceid = (int)$target->id;
            $source->status = 'completed';
            $source->enddate = time();
        } else {
            $source->status = (string)$plan->decision;
            $source->enddate = time();
        }
        $source->modifiedby = (int)($USER->id ?? 0);
        $source->timemodified = time();
        $DB->update_record('local_tenantmaster_placement', $source);

        $plan->targetplaceid = $targetplaceid;
        $plan->status = 'completed';
        $plan->approvedby = (int)($USER->id ?? 0);
        $plan->timeapproved = time();
        $plan->timefinished = time();
        $DB->update_record('local_tenantmaster_progress', $plan);
        (new audit_service())->record(
            (int)$tenant->id,
            'school.progression.applied',
            'success',
            ['decision' => $plan->decision],
            [
                'entitytable' => 'local_tenantmaster_progress',
                'entityid' => (int)$plan->id,
                'targetcomponent' => 'local_tenantmaster/placement',
                'targetid' => $targetplaceid,
            ],
        );
        return $plan;
    }

    /**
     * Resolve the target-year equivalent of a source academic master.
     */
    private function equivalent_master_id(
        object $tenant,
        int $sourceid,
        string $type,
        object $targetyear,
    ): int {
        global $DB;

        if ($sourceid <= 0) {
            return 0;
        }
        $source = $DB->get_record('local_tenantmaster_master', [
            'id' => $sourceid,
            'tenantid' => $tenant->id,
            'mastertype' => $type,
        ], '*', MUST_EXIST);
        if ((int)$source->acadyearid === 0 || (int)$source->acadyearid === (int)$targetyear->id) {
            return (int)$source->id;
        }
        $sourcepayload = json::decode_object((string)$source->payloadjson);
        $identity = (string)($sourcepayload['_tenantmaster_source_externalid'] ?? $source->externalid);
        $candidates = $DB->get_records_select(
            'local_tenantmaster_master',
            'tenantid = :tenantid AND mastertype = :mastertype AND active = 1
                 AND (acadyearid = :targetyearid OR acadyearid = 0)',
            [
                'tenantid' => $tenant->id,
                'mastertype' => $type,
                'targetyearid' => $targetyear->id,
            ],
            'acadyearid DESC, id',
        );
        foreach ($candidates as $candidate) {
            $payload = json::decode_object((string)$candidate->payloadjson);
            $candidateidentity = (string)($payload['_tenantmaster_source_externalid'] ?? $candidate->externalid);
            if ($candidateidentity === $identity || $candidate->name === $source->name) {
                return (int)$candidate->id;
            }
        }
        throw new \invalid_parameter_exception('No equivalent target-year ' . $type . ' is configured.');
    }

    /**
     * Require one target-year-compatible master.
     */
    private function require_master(object $tenant, int $id, string $type, object $year): object {
        global $DB;

        $master = $DB->get_record('local_tenantmaster_master', [
            'id' => $id,
            'tenantid' => $tenant->id,
            'mastertype' => $type,
            'active' => 1,
        ], '*', MUST_EXIST);
        if ((int)$master->acadyearid !== 0 && (int)$master->acadyearid !== (int)$year->id) {
            throw new \invalid_parameter_exception('Target academic selection belongs to another year.');
        }
        return $master;
    }
}
