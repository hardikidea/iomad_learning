<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Session-key-protected to-do actions.
 *
 * @package    block_iomaddashboard
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
require_sesskey();

$action = required_param('action', PARAM_ALPHA);
$returnurl = optional_param('returnurl', '/my/', PARAM_LOCALURL);
$repository = new \block_iomaddashboard\local\todo_repository();
$scope = new \block_iomaddashboard\local\tenant_scope();

switch ($action) {
    case 'add':
        $repository->create(
            $USER->id,
            $scope->get_companyid(),
            required_param('tasktext', PARAM_TEXT),
        );
        break;
    case 'complete':
        $repository->set_completed(required_param('id', PARAM_INT), $USER->id, true);
        break;
    case 'reopen':
        $repository->set_completed(required_param('id', PARAM_INT), $USER->id, false);
        break;
    case 'delete':
        $repository->delete(required_param('id', PARAM_INT), $USER->id);
        break;
    default:
        throw new invalid_parameter_exception('Unsupported task action.');
}

redirect(new moodle_url($returnurl));
