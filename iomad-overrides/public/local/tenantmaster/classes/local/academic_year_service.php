<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\local;

/**
 * Tenant academic-year lifecycle service.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class academic_year_service {
    /**
     * List years.
     *
     * @param int $tenantid Tenant.
     * @return array<int, object>
     */
    public function list(int $tenantid): array {
        global $DB;
        return $DB->get_records('local_tenantmaster_acadyear', ['tenantid' => $tenantid], 'startdate DESC, code');
    }

    /**
     * Save and optionally select one current year.
     *
     * @param object $data Data.
     * @return object
     */
    public function save(object $data): object {
        global $DB;

        if (
            !catalog::valid_external_key((string)$data->externalid)
                || !catalog::valid_external_key((string)$data->code)
        ) {
            throw new \invalid_parameter_exception('Invalid academic-year key.');
        }
        if ((int)$data->startdate >= (int)$data->enddate) {
            throw new \invalid_parameter_exception('Academic-year end date must follow its start date.');
        }
        $now = time();
        $transaction = $DB->start_delegated_transaction();
        if (!empty($data->iscurrent)) {
            $DB->set_field('local_tenantmaster_acadyear', 'iscurrent', 0, ['tenantid' => $data->tenantid]);
        }
        $data->payloadjson = $data->payloadjson ?? '{}';
        $data->timemodified = $now;
        if (!empty($data->id)) {
            $DB->update_record('local_tenantmaster_acadyear', $data);
            $id = (int)$data->id;
        } else {
            $data->timecreated = $now;
            $id = (int)$DB->insert_record('local_tenantmaster_acadyear', $data);
        }
        if (!empty($data->iscurrent)) {
            $DB->set_field('local_tenantmaster_tenant', 'activeyearid', $id, ['id' => $data->tenantid]);
        }
        $transaction->allow_commit();
        (new queue_service())->mark_dirty(
            (int)$data->tenantid,
            'categories',
            'local_tenantmaster_acadyear',
            $id,
            'academic_year_saved',
        );
        return $DB->get_record('local_tenantmaster_acadyear', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Ensure the current locally relevant academic year.
     *
     * @param object $tenant Tenant.
     * @return object
     */
    public function ensure_current(object $tenant): object {
        global $DB;

        $current = $DB->get_record('local_tenantmaster_acadyear', [
            'tenantid' => $tenant->id,
            'iscurrent' => 1,
        ]);
        if ($current) {
            return $current;
        }
        $now = getdate();
        $startyear = $now['mon'] >= 4 ? $now['year'] : $now['year'] - 1;
        $endyear = $startyear + 1;
        return $this->save((object)[
            'tenantid' => $tenant->id,
            'externalid' => 'AY_' . $startyear . '_' . $endyear,
            'code' => $startyear . '-' . substr((string)$endyear, -2),
            'name' => $startyear . '-' . $endyear,
            'startdate' => make_timestamp($startyear, 4, 1),
            'enddate' => make_timestamp($endyear, 3, 31, 23, 59, 59),
            'iscurrent' => 1,
            'status' => 'active',
            'payloadjson' => '{}',
        ]);
    }
}
