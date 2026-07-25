<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Tenant form activity callbacks.
 *
 * @package    mod_tenantform
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_iomad\iomad;
use mod_tenantform\local\definition_validator;
use mod_tenantform\local\template_catalog;

defined('MOODLE_INTERNAL') || die();

/**
 * Declare module capabilities.
 *
 * @param string $feature Feature.
 * @return mixed
 */
function tenantform_supports(string $feature): mixed {
    return match ($feature) {
        FEATURE_MOD_ARCHETYPE => MOD_ARCHETYPE_OTHER,
        FEATURE_MOD_INTRO => true,
        FEATURE_SHOW_DESCRIPTION => true,
        FEATURE_GROUPS => true,
        FEATURE_GROUPINGS => true,
        FEATURE_COMPLETION_TRACKS_VIEWS => true,
        FEATURE_BACKUP_MOODLE2 => true,
        default => null,
    };
}

/**
 * Add a form activity.
 *
 * @param object $data Form data.
 * @param moodleform_mod|null $mform Form.
 * @return int
 */
function tenantform_add_instance(object $data, ?moodleform_mod $mform = null): int {
    global $DB;

    unset($mform);
    $now = time();
    $template = (string)$data->formtype;
    $definition = trim((string)$data->definitionjson);
    if ($definition === '') {
        $definition = json_encode(
            template_catalog::definition($template),
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
    }
    (new definition_validator())->from_json($definition);
    $data->companyid = \mod_tenantform\local\tenant_access::resolve_company_for_course(
        (int)$data->course,
        context_system::instance(),
    );
    if (
        !empty($data->autoenrol)
        && !\mod_tenantform\local\tenant_access::course_in_company(
            (int)$data->targetcourseid,
            (int)$data->companyid,
        )
    ) {
        throw new invalid_parameter_exception('The target course is outside the active company.');
    }
    $data->definitionjson = $definition;
    $data->brandingjson = json_encode([
        'accent' => (string)$data->accent,
        'density' => (string)$data->density,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    $data->timecreated = $now;
    $data->timemodified = $now;
    return $DB->insert_record('tenantform', $data);
}

/**
 * Update a form activity.
 *
 * @param object $data Form data.
 * @param moodleform_mod|null $mform Form.
 * @return bool
 */
function tenantform_update_instance(object $data, ?moodleform_mod $mform = null): bool {
    global $DB;

    unset($mform);
    $data->id = $data->instance;
    $current = $DB->get_record('tenantform', ['id' => $data->id], 'id,companyid', MUST_EXIST);
    $data->companyid = $current->companyid;
    if (
        !empty($data->autoenrol)
        && !\mod_tenantform\local\tenant_access::course_in_company(
            (int)$data->targetcourseid,
            (int)$data->companyid,
        )
    ) {
        throw new invalid_parameter_exception('The target course is outside the form company.');
    }
    $definition = trim((string)$data->definitionjson);
    if ($definition === '') {
        $definition = json_encode(
            template_catalog::definition((string)$data->formtype),
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
    }
    (new definition_validator())->from_json($definition);
    $data->definitionjson = $definition;
    $data->brandingjson = json_encode([
        'accent' => (string)$data->accent,
        'density' => (string)$data->density,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    $data->timemodified = time();
    return $DB->update_record('tenantform', $data);
}

/**
 * Delete a form activity and plugin-owned records.
 *
 * @param int $id Form ID.
 * @return bool
 */
function tenantform_delete_instance(int $id): bool {
    global $DB;

    $form = $DB->get_record('tenantform', ['id' => $id]);
    if (!$form) {
        return false;
    }
    $cm = get_coursemodule_from_instance('tenantform', $id, $form->course, false, IGNORE_MISSING);
    $transaction = $DB->start_delegated_transaction();
    $DB->delete_records('tenantform_audit', ['tenantformid' => $id]);
    $DB->delete_records('tenantform_entry', ['tenantformid' => $id]);
    $DB->delete_records('tenantform', ['id' => $id]);
    $transaction->allow_commit();
    if ($cm) {
        get_file_storage()->delete_area_files(context_module::instance($cm->id)->id, 'mod_tenantform');
    }
    return true;
}

/**
 * Return browser file metadata.
 *
 * @param stdClass $course Course.
 * @param stdClass $cm Course module.
 * @param stdClass $context Context.
 * @param string $filearea File area.
 * @param array $args Path arguments.
 * @param bool $forcedownload Force download.
 * @param array $options Options.
 * @return bool
 */
function tenantform_pluginfile(
    stdClass $course,
    stdClass $cm,
    stdClass $context,
    string $filearea,
    array $args,
    bool $forcedownload,
    array $options = []
): bool {
    global $DB, $USER;

    if ($context->contextlevel !== CONTEXT_MODULE || $filearea !== 'entry') {
        return false;
    }
    require_login($course, true, $cm);
    $entryid = (int)array_shift($args);
    $entry = $DB->get_record('tenantform_entry', ['id' => $entryid, 'tenantformid' => $cm->instance]);
    if (!$entry) {
        return false;
    }
    $canmanage = has_capability('mod/tenantform:manageentries', $context);
    if (!$canmanage && (int)$entry->userid !== (int)$USER->id) {
        return false;
    }
    $filename = array_pop($args);
    $filepath = '/' . ($args ? implode('/', $args) . '/' : '');
    $file = get_file_storage()->get_file(
        $context->id,
        'mod_tenantform',
        'entry',
        $entryid,
        $filepath,
        $filename
    );
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 0, 0, $forcedownload, $options);
    return true;
}
