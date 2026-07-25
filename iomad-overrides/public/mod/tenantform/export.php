<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Download tenant form entries using Moodle dataformat writers.
 *
 * @package    mod_tenantform
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$id = required_param('id', PARAM_INT);
$format = required_param('format', PARAM_ALPHANUMEXT);
require_sesskey();

$cm = get_coursemodule_from_id('tenantform', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$form = $DB->get_record('tenantform', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);
require_course_login($course, false, $cm);
require_capability('mod/tenantform:exportentries', $context);
\mod_tenantform\local\tenant_access::require_manage($form, $context);

$definition = (new \mod_tenantform\local\definition_validator())->from_json($form->definitionjson);
$entries = (new \mod_tenantform\local\entry_repository())->all((int)$form->id);
(new \mod_tenantform\local\entry_exporter())->download($form, $definition, $entries, $format);
