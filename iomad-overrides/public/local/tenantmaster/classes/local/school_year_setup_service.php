<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\local;

/**
 * Idempotently expand shared school masters into one native academic year.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class school_year_setup_service {
    /**
     * Generate board > medium > grade > optional stream > subject definitions.
     *
     * @return array{created: int, existing: int, courses: int}
     */
    public function generate(object $tenant, object $data): array {
        global $DB;

        if ((string)$tenant->tenanttype !== 'school') {
            throw new \invalid_parameter_exception('School-year setup requires a school tenant.');
        }
        $year = $DB->get_record('local_tenantmaster_acadyear', [
            'id' => (int)$data->setupyearid,
            'tenantid' => $tenant->id,
        ], '*', MUST_EXIST);
        $board = $this->source($tenant, (int)$data->setupboardid, 'board');
        $medium = $this->source($tenant, (int)$data->setupmediumid, 'medium');
        $grades = $this->sources($tenant, (array)$data->setupgradeids, 'grade');
        $subjects = $this->sources($tenant, (array)$data->setupsubjectids, 'subject');
        $stream = (int)($data->setupstreamid ?? 0) > 0
            ? $this->source($tenant, (int)$data->setupstreamid, 'stream')
            : null;
        if (!$grades || !$subjects) {
            throw new \invalid_parameter_exception('Select at least one grade and subject.');
        }

        $result = ['created' => 0, 'existing' => 0, 'courses' => 0];
        $yearkey = (string)$year->externalid;
        $boardcopy = $this->copy(
            $tenant,
            $year,
            $board,
            0,
            $yearkey . ':BOARD:' . $board->externalid,
            $result,
        );
        $mediumcopy = $this->copy(
            $tenant,
            $year,
            $medium,
            (int)$boardcopy->id,
            $yearkey . ':MEDIUM:' . $medium->externalid,
            $result,
        );
        foreach ($grades as $grade) {
            $gradecopy = $this->copy(
                $tenant,
                $year,
                $grade,
                (int)$mediumcopy->id,
                $yearkey . ':GRADE:' . $grade->externalid,
                $result,
            );
            $subjectparent = $gradecopy;
            if ($stream) {
                $subjectparent = $this->copy(
                    $tenant,
                    $year,
                    $stream,
                    (int)$gradecopy->id,
                    $yearkey . ':GRADE:' . $grade->externalid . ':STREAM:' . $stream->externalid,
                    $result,
                );
            }
            foreach ($subjects as $subject) {
                $this->copy(
                    $tenant,
                    $year,
                    $subject,
                    (int)$subjectparent->id,
                    $yearkey . ':GRADE:' . $grade->externalid
                        . ($stream ? ':STREAM:' . $stream->externalid : '')
                        . ':SUBJECT:' . $subject->externalid,
                    $result,
                );
                $result['courses']++;
            }
        }
        (new audit_service())->record(
            (int)$tenant->id,
            'school.year_structure.generated',
            'success',
            $result,
            ['entitytable' => 'local_tenantmaster_acadyear', 'entityid' => (int)$year->id],
        );
        return $result;
    }

    /**
     * Copy one shared definition while retaining its stable source identity.
     */
    private function copy(
        object $tenant,
        object $year,
        object $source,
        int $parentid,
        string $externalid,
        array &$result,
    ): object {
        global $DB;

        $externalid = $this->bounded_key($externalid);
        $existing = $DB->get_record('local_tenantmaster_master', [
            'tenantid' => $tenant->id,
            'mastertype' => $source->mastertype,
            'externalid' => $externalid,
        ]);
        if ($existing) {
            $result['existing']++;
            return $existing;
        }
        $payload = json::decode_object((string)$source->payloadjson);
        $payload['_tenantmaster_source_externalid'] =
            $payload['_tenantmaster_source_externalid'] ?? (string)$source->externalid;
        $payload['_tenantmaster_source_masterid'] = (int)$source->id;
        $record = (new master_service())->save((object)[
            'id' => 0,
            'tenantid' => (int)$tenant->id,
            'acadyearid' => (int)$year->id,
            'parentid' => $parentid,
            'mastertype' => (string)$source->mastertype,
            'externalid' => $externalid,
            'code' => $this->bounded_key($year->code . ':' . $source->code . ':' . $parentid),
            'name' => (string)$source->name,
            'description' => (string)($source->description ?? ''),
            'payloadjson' => json::encode($payload),
            'active' => 1,
            'sortorder' => (int)$source->sortorder,
        ]);
        $result['created']++;
        return $record;
    }

    /**
     * Require one active tenant-owned source master.
     */
    private function source(object $tenant, int $id, string $type): object {
        global $DB;

        return $DB->get_record('local_tenantmaster_master', [
            'id' => $id,
            'tenantid' => $tenant->id,
            'mastertype' => $type,
            'active' => 1,
        ], '*', MUST_EXIST);
    }

    /**
     * Resolve a non-empty unique list of source masters.
     *
     * @return array<int, object>
     */
    private function sources(object $tenant, array $ids, string $type): array {
        $records = [];
        foreach (array_unique(array_map('intval', $ids)) as $id) {
            if ($id > 0) {
                $records[$id] = $this->source($tenant, $id, $type);
            }
        }
        return $records;
    }

    /**
     * Bounded stable external key accepted by the master service.
     */
    private function bounded_key(string $value): string {
        $value = preg_replace('/[^A-Za-z0-9._:-]/', '_', $value);
        return strlen($value) <= 100
            ? $value
            : substr($value, 0, 67) . ':' . substr(hash('sha256', $value), 0, 32);
    }
}
