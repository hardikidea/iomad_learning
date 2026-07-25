<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Export tenant-filtered grades.
 *
 * @package    local_rapidgrader
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
require_sesskey();
$companyid = optional_param('companyid', 0, PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);
$format = required_param('format', PARAM_ALPHANUMEXT);
$scope = \local_rapidgrader\local\course_scope::resolve($companyid);
$course = $scope->require_course($courseid);
$context = context_course::instance($courseid);
if (!has_capability('local/rapidgrader:export', $context)) {
    require_capability('moodle/grade:export', $context);
}
$service = new \local_rapidgrader\local\gradebook_service($scope, $course);
(new \local_rapidgrader\local\exporter())->download($course, $service, $format);
