<?php
// This file is part of Moodle - https://moodle.org/

namespace local_orgprofile\local\service;

use coding_exception;
use invalid_parameter_exception;
use local_orgprofile\event\company_mapping_changed;
use stdClass;

/**
 * Organization type, user type, and company mapping operations.
 *
 * @package local_orgprofile
 */
final class organization_service {

    /** Create or update an organization type. */
    public function save_organization_type(stdClass $data): int {
        global $DB;
        $record = $this->prepare_named_record($data);
        $record->description = clean_param($data->description ?? '', PARAM_CLEANHTML);
        $record->enabled = empty($data->enabled) ? 0 : 1;
        $record->sortorder = (int) ($data->sortorder ?? 0);
        $now = time();
        if (!empty($data->id)) {
            $record->id = (int) $data->id;
            $record->timemodified = $now;
            $DB->update_record('local_orgprofile_orgtype', $record);
            return $record->id;
        }
        $record->timecreated = $now;
        $record->timemodified = $now;
        return $DB->insert_record('local_orgprofile_orgtype', $record);
    }

    /** Create or update a user type. */
    public function save_user_type(stdClass $data): int {
        global $DB;
        $orgtypeid = (int) ($data->orgtypeid ?? 0);
        $DB->get_record('local_orgprofile_orgtype', ['id' => $orgtypeid], 'id', MUST_EXIST);
        $record = $this->prepare_named_record($data);
        $record->orgtypeid = $orgtypeid;
        $record->enabled = empty($data->enabled) ? 0 : 1;
        $record->sortorder = (int) ($data->sortorder ?? 0);
        $now = time();
        if (!empty($data->id)) {
            $existing = $DB->get_record('local_orgprofile_usertype', ['id' => (int) $data->id], '*', MUST_EXIST);
            if ((int) $existing->orgtypeid !== $orgtypeid &&
                    ($DB->record_exists('local_orgprofile_form', ['usertypeid' => $existing->id]) ||
                    $DB->record_exists('local_orgprofile_user', ['usertypeid' => $existing->id]))) {
                throw new invalid_parameter_exception(get_string('cannotdeleteinuse', 'local_orgprofile'));
            }
            $record->id = (int) $data->id;
            $record->timemodified = $now;
            $DB->update_record('local_orgprofile_usertype', $record);
            return $record->id;
        }
        $record->timecreated = $now;
        $record->timemodified = $now;
        return $DB->insert_record('local_orgprofile_usertype', $record);
    }

    /**
     * Map a verified IOMAD company to an organization type and optional default form.
     */
    public function map_company(int $companyid, int $orgtypeid, ?int $defaultformid = null,
            ?string $configjson = null): int {
        global $DB;
        $DB->get_record('local_iomad_companies', ['id' => $companyid], 'id', MUST_EXIST);
        $DB->get_record('local_orgprofile_orgtype', ['id' => $orgtypeid], 'id', MUST_EXIST);
        if ($defaultformid) {
            $form = $DB->get_record('local_orgprofile_form', ['id' => $defaultformid], '*', MUST_EXIST);
            if ((int) $form->orgtypeid !== $orgtypeid) {
                throw new invalid_parameter_exception(get_string('invalidrelationship', 'local_orgprofile'));
            }
        }
        $assignments = $DB->get_records('local_orgprofile_user', ['companyid' => $companyid], '', 'id,usertypeid');
        foreach ($assignments as $assignment) {
            $assignedtype = $DB->get_record('local_orgprofile_usertype', ['id' => $assignment->usertypeid],
                'id,orgtypeid', MUST_EXIST);
            if ((int) $assignedtype->orgtypeid !== $orgtypeid) {
                throw new invalid_parameter_exception(get_string('invalidrelationship', 'local_orgprofile'));
            }
        }
        if ($configjson !== null && trim($configjson) !== '') {
            (new validation_service())->decode_json($configjson);
        }
        $now = time();
        $record = $DB->get_record('local_orgprofile_company', ['companyid' => $companyid]);
        if ($record) {
            if ((int) $record->orgtypeid !== $orgtypeid) {
                throw new invalid_parameter_exception(get_string('orgtypeimmutable', 'local_orgprofile'));
            }
            $record->defaultformid = $defaultformid ?: null;
            $record->configjson = $configjson ?: null;
            $record->timemodified = $now;
            $DB->update_record('local_orgprofile_company', $record);
            $id = (int) $record->id;
        } else {
            $id = $DB->insert_record('local_orgprofile_company', (object) [
                'companyid' => $companyid,
                'orgtypeid' => $orgtypeid,
                'defaultformid' => $defaultformid ?: null,
                'configjson' => $configjson ?: null,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }
        company_mapping_changed::create_for_mapping($id, $companyid)->trigger();
        return $id;
    }

    /** Return the company mapping, failing closed when it is absent. */
    public function get_company_mapping(int $companyid): stdClass {
        global $DB;
        return $DB->get_record('local_orgprofile_company', ['companyid' => $companyid], '*', MUST_EXIST);
    }

    /** Delete an unreferenced organization or user type. */
    public function delete(string $entity, int $id): void {
        global $DB;
        if ($entity === 'orgtype') {
            foreach (['local_orgprofile_usertype', 'local_orgprofile_form', 'local_orgprofile_company'] as $table) {
                if ($DB->record_exists($table, ['orgtypeid' => $id])) {
                    throw new coding_exception(get_string('cannotdeleteinuse', 'local_orgprofile'));
                }
            }
            $DB->delete_records('local_orgprofile_orgtype', ['id' => $id]);
            return;
        }
        if ($entity === 'usertype') {
            foreach (['local_orgprofile_form', 'local_orgprofile_user'] as $table) {
                if ($DB->record_exists($table, ['usertypeid' => $id])) {
                    throw new coding_exception(get_string('cannotdeleteinuse', 'local_orgprofile'));
                }
            }
            $DB->delete_records('local_orgprofile_usertype', ['id' => $id]);
            return;
        }
        throw new invalid_parameter_exception('Unsupported organization entity.');
    }

    /** Prepare safe common name fields. */
    private function prepare_named_record(stdClass $data): stdClass {
        $shortname = \core_text::strtolower(trim((string) ($data->shortname ?? '')));
        if (!preg_match('/^[a-z0-9_]+$/', $shortname)) {
            throw new invalid_parameter_exception(get_string('invalidshortname', 'local_orgprofile'));
        }
        return (object) [
            'name' => clean_param($data->name ?? '', PARAM_TEXT),
            'shortname' => $shortname,
        ];
    }
}
