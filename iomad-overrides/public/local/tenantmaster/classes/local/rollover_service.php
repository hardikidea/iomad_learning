<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\local;

/**
 * Previewed and resumable academic rollover.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class rollover_service {
    /**
     * Create a non-destructive rollover plan.
     *
     * @param object $tenant Tenant.
     * @param int $fromyearid Source year.
     * @param int $toyearid Target year.
     * @return object Plan.
     */
    public function plan(object $tenant, int $fromyearid, int $toyearid): object {
        global $DB, $USER;

        $from = $DB->get_record('local_tenantmaster_acadyear', [
            'id' => $fromyearid,
            'tenantid' => $tenant->id,
        ], '*', MUST_EXIST);
        $to = $DB->get_record('local_tenantmaster_acadyear', [
            'id' => $toyearid,
            'tenantid' => $tenant->id,
        ], '*', MUST_EXIST);
        if ($to->startdate <= $from->startdate) {
            throw new \invalid_parameter_exception('Target academic year must follow the source year.');
        }
        $existing = $DB->get_record('local_tenantmaster_rollover', [
            'tenantid' => $tenant->id,
            'fromyearid' => $fromyearid,
            'toyearid' => $toyearid,
        ]);
        $masters = $DB->get_records('local_tenantmaster_master', [
            'tenantid' => $tenant->id,
            'acadyearid' => $fromyearid,
            'active' => 1,
        ]);
        $planjson = json::encode([
            'copy_master_ids' => array_map('intval', array_keys($masters)),
            'archive_source_courses' => false,
            'delete_native_records' => false,
            'target_year' => $to->externalid,
        ]);
        $record = (object)[
            'tenantid' => $tenant->id,
            'fromyearid' => $fromyearid,
            'toyearid' => $toyearid,
            'status' => 'planned',
            'planjson' => $planjson,
            'backupref' => null,
            'actorid' => (int)($USER->id ?? 0),
            'approvedby' => 0,
            'timeapproved' => 0,
            'timefinished' => 0,
        ];
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('local_tenantmaster_rollover', $record);
            $id = (int)$existing->id;
            $DB->delete_records('local_tenantmaster_rollitem', ['rolloverid' => $id]);
        } else {
            $record->timecreated = time();
            $id = (int)$DB->insert_record('local_tenantmaster_rollover', $record);
        }
        foreach ($masters as $master) {
            $DB->insert_record('local_tenantmaster_rollitem', (object)[
                'rolloverid' => $id,
                'entitytype' => 'academic_master',
                'sourceid' => $master->id,
                'targetid' => 0,
                'action' => 'copy',
                'status' => 'planned',
                'message' => null,
                'timecreated' => time(),
                'timemodified' => time(),
            ]);
        }
        return $DB->get_record('local_tenantmaster_rollover', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Apply a plan after explicit backup evidence.
     *
     * @param object $tenant Tenant.
     * @param int $rolloverid Plan.
     * @param string $backupref Verified recovery-set reference.
     * @return object Applied plan.
     */
    public function apply(object $tenant, int $rolloverid, string $backupref): object {
        global $DB, $USER;

        if (trim($backupref) === '') {
            throw new \invalid_parameter_exception('Backup evidence is required.');
        }
        $rollover = $DB->get_record('local_tenantmaster_rollover', [
            'id' => $rolloverid,
            'tenantid' => $tenant->id,
            'status' => 'planned',
        ], '*', MUST_EXIST);
        $items = $DB->get_records('local_tenantmaster_rollitem', ['rolloverid' => $rolloverid], 'id');
        $repository = new master_repository();
        $service = new master_service();
        $sourceitems = [];
        $targets = [];
        $remaining = [];
        foreach ($items as $item) {
            $sourceitems[(int)$item->sourceid] = $item;
            if ($item->status === 'completed' && (int)$item->targetid > 0) {
                $targets[(int)$item->sourceid] = (int)$item->targetid;
            } else {
                $remaining[(int)$item->id] = $item;
            }
        }

        do {
            $progress = false;
            foreach ($remaining as $itemid => $item) {
                $source = $repository->get((int)$tenant->id, (int)$item->sourceid);
                $targetparentid = (int)$source->parentid;
                if (isset($sourceitems[$targetparentid])) {
                    if (!isset($targets[$targetparentid])) {
                        continue;
                    }
                    $targetparentid = $targets[$targetparentid];
                }

                try {
                    $targetexternalid = $source->externalid . ':' . $rollover->toyearid;
                    $existing = $DB->get_record('local_tenantmaster_master', [
                        'tenantid' => $tenant->id,
                        'mastertype' => $source->mastertype,
                        'externalid' => $targetexternalid,
                    ]);
                    $target = $service->save((object)[
                        'id' => (int)($existing->id ?? 0),
                        'tenantid' => (int)$tenant->id,
                        'acadyearid' => (int)$rollover->toyearid,
                        'parentid' => $targetparentid,
                        'mastertype' => (string)$source->mastertype,
                        'externalid' => $targetexternalid,
                        'code' => substr($source->code . '_' . $rollover->toyearid, 0, 100),
                        'name' => (string)$source->name,
                        'description' => (string)($source->description ?? ''),
                        'payloadjson' => (string)$source->payloadjson,
                        'active' => (int)$source->active,
                        'sortorder' => (int)$source->sortorder,
                    ]);
                    $targets[(int)$source->id] = (int)$target->id;
                    $item->targetid = $target->id;
                    $item->status = 'completed';
                    $item->message = null;
                    $item->timemodified = time();
                    $DB->update_record('local_tenantmaster_rollitem', $item);
                    unset($remaining[$itemid]);
                    $progress = true;
                } catch (\Throwable $exception) {
                    $item->status = 'failed';
                    $item->message = substr($exception->getMessage(), 0, 2000);
                    $item->timemodified = time();
                    $DB->update_record('local_tenantmaster_rollitem', $item);
                    unset($remaining[$itemid]);
                }
            }
        } while ($remaining && $progress);

        foreach ($remaining as $item) {
            $item->status = 'failed';
            $item->message = 'A source parent could not be projected into the target academic year.';
            $item->timemodified = time();
            $DB->update_record('local_tenantmaster_rollitem', $item);
        }
        $remainingcount = $DB->count_records_select(
            'local_tenantmaster_rollitem',
            'rolloverid = :rolloverid AND status <> :status',
            ['rolloverid' => $rolloverid, 'status' => 'completed'],
        );
        $rollover->status = $remainingcount ? 'completed_with_errors' : 'completed';
        $rollover->backupref = $backupref;
        $rollover->approvedby = (int)($USER->id ?? 0);
        $rollover->timeapproved = time();
        $rollover->timefinished = time();
        $DB->update_record('local_tenantmaster_rollover', $rollover);
        return $rollover;
    }
}
