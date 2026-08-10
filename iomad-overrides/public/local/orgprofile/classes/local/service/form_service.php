<?php
// This file is part of Moodle - https://moodle.org/

namespace local_orgprofile\local\service;

use coding_exception;
use invalid_parameter_exception;
use stdClass;

/**
 * Profile form, category, and field-library operations.
 *
 * @package local_orgprofile
 */
final class form_service {

    /** Create or update a form. */
    public function save_form(stdClass $data): int {
        global $DB;
        $orgtypeid = (int) $data->orgtypeid;
        $DB->get_record('local_orgprofile_orgtype', ['id' => $orgtypeid], 'id', MUST_EXIST);
        $usertypeid = empty($data->usertypeid) ? null : (int) $data->usertypeid;
        if ($usertypeid) {
            $usertype = $DB->get_record('local_orgprofile_usertype', ['id' => $usertypeid], '*', MUST_EXIST);
            if ((int) $usertype->orgtypeid !== $orgtypeid) {
                throw new invalid_parameter_exception(get_string('invalidrelationship', 'local_orgprofile'));
            }
        }
        $record = (object) [
            'orgtypeid' => $orgtypeid,
            'usertypeid' => $usertypeid,
            'name' => clean_param($data->name, PARAM_TEXT),
            'shortname' => $this->clean_shortname($data->shortname),
            'description' => clean_param($data->description ?? '', PARAM_CLEANHTML),
            'enabled' => empty($data->enabled) ? 0 : 1,
        ];
        if (!empty($data->id)) {
            $existing = $DB->get_record('local_orgprofile_form', ['id' => (int) $data->id], '*', MUST_EXIST);
            if (((int) $existing->orgtypeid !== $orgtypeid || (int) $existing->usertypeid !== (int) $usertypeid) &&
                    ($DB->record_exists('local_orgprofile_company', ['defaultformid' => $existing->id]) ||
                    $DB->record_exists('local_orgprofile_user', ['formid' => $existing->id]))) {
                throw new invalid_parameter_exception(get_string('cannotdeleteinuse', 'local_orgprofile'));
            }
        }
        return $this->upsert('local_orgprofile_form', $record, empty($data->id) ? 0 : (int) $data->id);
    }

    /** Create or update a category. */
    public function save_category(stdClass $data): int {
        global $DB;
        $formid = (int) $data->formid;
        $DB->get_record('local_orgprofile_form', ['id' => $formid], 'id', MUST_EXIST);
        $record = (object) [
            'formid' => $formid,
            'name' => clean_param($data->name, PARAM_TEXT),
            'shortname' => $this->clean_shortname($data->shortname),
            'sortorder' => (int) ($data->sortorder ?? 0),
            'collapsed' => empty($data->collapsed) ? 0 : 1,
        ];
        if (!empty($data->id)) {
            $existing = $DB->get_record('local_orgprofile_category', ['id' => (int) $data->id], '*', MUST_EXIST);
            if ((int) $existing->formid !== $formid &&
                    $DB->record_exists('local_orgprofile_formfield', ['categoryid' => $existing->id])) {
                throw new invalid_parameter_exception(get_string('cannotdeleteinuse', 'local_orgprofile'));
            }
        }
        return $this->upsert('local_orgprofile_category', $record, empty($data->id) ? 0 : (int) $data->id);
    }

    /** Create or update a reusable field. */
    public function save_field(stdClass $data): int {
        global $DB;
        $validation = new validation_service();
        $errors = $validation->configuration_errors($data);
        if ($errors) {
            throw new invalid_parameter_exception(reset($errors));
        }
        $scope = $data->uniquescope ?? 'none';
        $record = (object) [
            'name' => clean_param($data->name, PARAM_TEXT),
            'shortname' => $this->clean_shortname($data->shortname),
            'datatype' => $data->datatype,
            'corefield' => empty($data->corefield) ? null : $data->corefield,
            'description' => clean_param($data->description ?? '', PARAM_CLEANHTML),
            'defaultvalue' => isset($data->defaultvalue) ? clean_param($data->defaultvalue, PARAM_TEXT) : null,
            'required' => empty($data->required) ? 0 : 1,
            'uniquevalue' => $scope === 'none' ? 0 : 1,
            'uniquescope' => $scope,
            'readonly' => empty($data->readonly) ? 0 : 1,
            'visible' => empty($data->visible) ? 0 : 1,
            'sensitive' => empty($data->sensitive) ? 0 : 1,
            'optionsjson' => empty($data->optionsjson) ? null : trim($data->optionsjson),
            'validationjson' => empty($data->validationjson) ? null : trim($data->validationjson),
            'enabled' => empty($data->enabled) ? 0 : 1,
        ];
        if (!empty($data->id) && $DB->record_exists('local_orgprofile_value', ['fieldid' => (int) $data->id])) {
            $existing = $DB->get_record('local_orgprofile_field', ['id' => (int) $data->id], '*', MUST_EXIST);
            foreach (['datatype', 'corefield', 'uniquescope'] as $property) {
                if (($existing->{$property} ?? null) !== ($record->{$property} ?? null)) {
                    throw new invalid_parameter_exception(get_string('cannotdeleteinuse', 'local_orgprofile'));
                }
            }
        }
        return $this->upsert('local_orgprofile_field', $record, empty($data->id) ? 0 : (int) $data->id);
    }

    /** Attach or update a field placement on a form. */
    public function save_form_field(stdClass $data): int {
        global $DB;
        $formid = (int) $data->formid;
        $category = $DB->get_record('local_orgprofile_category', ['id' => (int) $data->categoryid], '*', MUST_EXIST);
        if ((int) $category->formid !== $formid) {
            throw new invalid_parameter_exception(get_string('invalidrelationship', 'local_orgprofile'));
        }
        $field = $DB->get_record('local_orgprofile_field', ['id' => (int) $data->fieldid], '*', MUST_EXIST);
        if (!empty($data->id)) {
            $existing = $DB->get_record('local_orgprofile_formfield', ['id' => (int) $data->id], '*', MUST_EXIST);
            if ((int) $existing->formid !== $formid) {
                throw new invalid_parameter_exception(get_string('invalidrelationship', 'local_orgprofile'));
            }
        }
        if (!empty($field->corefield)) {
            $placements = $DB->get_records('local_orgprofile_formfield', ['formid' => $formid], '', 'id,fieldid');
            foreach ($placements as $placement) {
                if (!empty($data->id) && (int) $placement->id === (int) $data->id) {
                    continue;
                }
                $placedfield = $DB->get_record('local_orgprofile_field', ['id' => $placement->fieldid], 'id,corefield');
                if ($placedfield && $placedfield->corefield === $field->corefield) {
                    throw new invalid_parameter_exception(get_string('invalidrelationship', 'local_orgprofile'));
                }
            }
        }
        $record = (object) [
            'formid' => $formid,
            'categoryid' => (int) $data->categoryid,
            'fieldid' => (int) $data->fieldid,
            'sortorder' => (int) ($data->sortorder ?? 0),
            'requiredoverride' => $this->nullable_bool($data->requiredoverride ?? ''),
            'readonlyoverride' => $this->nullable_bool($data->readonlyoverride ?? ''),
            'visibleoverride' => $this->nullable_bool($data->visibleoverride ?? ''),
        ];
        return $this->upsert('local_orgprofile_formfield', $record, empty($data->id) ? 0 : (int) $data->id);
    }

    /** Resolve the effective enabled form for a company-scoped user assignment. */
    public function resolve_form(int $companyid, int $userid): stdClass {
        global $DB;
        $mapping = $DB->get_record('local_orgprofile_company', ['companyid' => $companyid], '*', MUST_EXIST);
        $assignment = $DB->get_record('local_orgprofile_user', [
            'companyid' => $companyid,
            'userid' => $userid,
            'status' => 'active',
        ], '*', MUST_EXIST);
        $usertype = $DB->get_record('local_orgprofile_usertype', [
            'id' => $assignment->usertypeid,
            'enabled' => 1,
        ], '*', MUST_EXIST);
        if ((int) $usertype->orgtypeid !== (int) $mapping->orgtypeid) {
            throw new invalid_parameter_exception(get_string('invalidrelationship', 'local_orgprofile'));
        }
        foreach ([$assignment->formid, $mapping->defaultformid] as $formid) {
            if ($formid) {
                $form = $DB->get_record('local_orgprofile_form', ['id' => $formid, 'enabled' => 1]);
                if ($form && $this->form_matches($form, $mapping, $assignment)) {
                    return $form;
                }
            }
        }
        $forms = $DB->get_records('local_orgprofile_form', [
            'orgtypeid' => $mapping->orgtypeid,
            'enabled' => 1,
        ], 'id ASC');
        $generic = null;
        foreach ($forms as $form) {
            if ((int) $form->usertypeid === (int) $assignment->usertypeid) {
                return $form;
            }
            if (empty($form->usertypeid) && $generic === null) {
                $generic = $form;
            }
        }
        if ($generic) {
            return $generic;
        }
        throw new coding_exception(get_string('formnotresolved', 'local_orgprofile'));
    }

    /** Resolve the enabled form used while creating a new company user. */
    public function resolve_form_for_user_type(int $companyid, int $usertypeid): stdClass {
        global $DB;
        $mapping = $DB->get_record('local_orgprofile_company', ['companyid' => $companyid], '*', MUST_EXIST);
        $usertype = $DB->get_record('local_orgprofile_usertype', [
            'id' => $usertypeid,
            'orgtypeid' => $mapping->orgtypeid,
            'enabled' => 1,
        ], '*', MUST_EXIST);
        if (!empty($mapping->defaultformid)) {
            $form = $DB->get_record('local_orgprofile_form', [
                'id' => $mapping->defaultformid,
                'orgtypeid' => $mapping->orgtypeid,
                'enabled' => 1,
            ]);
            if ($form && (empty($form->usertypeid) || (int) $form->usertypeid === (int) $usertype->id)) {
                return $form;
            }
        }
        $forms = $DB->get_records('local_orgprofile_form', [
            'orgtypeid' => $mapping->orgtypeid,
            'enabled' => 1,
        ], 'id ASC');
        $generic = null;
        foreach ($forms as $form) {
            if ((int) $form->usertypeid === (int) $usertype->id) {
                return $form;
            }
            if (empty($form->usertypeid) && $generic === null) {
                $generic = $form;
            }
        }
        if ($generic) {
            return $generic;
        }
        throw new coding_exception(get_string('formnotresolved', 'local_orgprofile'));
    }

    /**
     * Load categories and effective field placement rules in deterministic order.
     *
     * @return stdClass[] Categories with a fields property.
     */
    public function get_form_structure(int $formid): array {
        global $DB;
        $DB->get_record('local_orgprofile_form', ['id' => $formid], 'id', MUST_EXIST);
        $categories = $DB->get_records('local_orgprofile_category', ['formid' => $formid], 'sortorder ASC, id ASC');
        foreach ($categories as $category) {
            $category->fields = [];
            $placements = $DB->get_records('local_orgprofile_formfield', [
                'formid' => $formid,
                'categoryid' => $category->id,
            ], 'sortorder ASC, id ASC');
            foreach ($placements as $placement) {
                $field = $DB->get_record('local_orgprofile_field', ['id' => $placement->fieldid, 'enabled' => 1]);
                if (!$field) {
                    continue;
                }
                $field->placementid = $placement->id;
                $field->effective_required = $placement->requiredoverride === null
                    ? (int) $field->required : (int) $placement->requiredoverride;
                $field->effective_readonly = $placement->readonlyoverride === null
                    ? (int) $field->readonly : (int) $placement->readonlyoverride;
                $field->effective_visible = $placement->visibleoverride === null
                    ? (int) $field->visible : (int) $placement->visibleoverride;
                $category->fields[] = $field;
            }
        }
        return array_values($categories);
    }

    /** Delete an unreferenced form, category, field, or placement. */
    public function delete(string $entity, int $id): void {
        global $DB;
        $dependencies = [
            'form' => [
                ['local_orgprofile_category', 'formid'], ['local_orgprofile_formfield', 'formid'],
                ['local_orgprofile_company', 'defaultformid'], ['local_orgprofile_user', 'formid'],
            ],
            'category' => [['local_orgprofile_formfield', 'categoryid']],
            'field' => [['local_orgprofile_formfield', 'fieldid'], ['local_orgprofile_value', 'fieldid']],
            'formfield' => [],
        ];
        $tables = [
            'form' => 'local_orgprofile_form',
            'category' => 'local_orgprofile_category',
            'field' => 'local_orgprofile_field',
            'formfield' => 'local_orgprofile_formfield',
        ];
        if (!isset($tables[$entity])) {
            throw new invalid_parameter_exception('Unsupported form entity.');
        }
        foreach ($dependencies[$entity] as [$table, $column]) {
            if ($DB->record_exists($table, [$column => $id])) {
                throw new coding_exception(get_string('cannotdeleteinuse', 'local_orgprofile'));
            }
        }
        $DB->delete_records($tables[$entity], ['id' => $id]);
    }

    /** Insert/update with common timestamps. */
    private function upsert(string $table, stdClass $record, int $id): int {
        global $DB;
        $now = time();
        if ($id) {
            $record->id = $id;
            $record->timemodified = $now;
            $DB->update_record($table, $record);
            return $id;
        }
        $record->timecreated = $now;
        $record->timemodified = $now;
        return $DB->insert_record($table, $record);
    }

    /** Validate shortnames used in configuration URLs and manifests. */
    private function clean_shortname(string $shortname): string {
        $shortname = \core_text::strtolower(trim($shortname));
        if (!preg_match('/^[a-z0-9_]+$/', $shortname)) {
            throw new invalid_parameter_exception(get_string('invalidshortname', 'local_orgprofile'));
        }
        return $shortname;
    }

    /** Convert a tri-state form selection to nullable database boolean. */
    private function nullable_bool(mixed $value): ?int {
        return $value === '' ? null : (empty($value) ? 0 : 1);
    }

    /** Confirm a form belongs to the resolved organization/user type. */
    private function form_matches(stdClass $form, stdClass $mapping, stdClass $assignment): bool {
        return (int) $form->orgtypeid === (int) $mapping->orgtypeid &&
            (empty($form->usertypeid) || (int) $form->usertypeid === (int) $assignment->usertypeid);
    }
}
