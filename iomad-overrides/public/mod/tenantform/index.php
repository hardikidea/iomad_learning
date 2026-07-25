<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Redirect to the core course activity overview.
 *
 * @package    mod_tenantform
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$courseid = required_param('id', PARAM_INT);
\core_courseformat\activityoverviewbase::redirect_to_overview_page($courseid, 'tenantform');
