<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Course view for the IOMAD video format.
 *
 * @package    format_iomadvideo
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->libdir . '/filelib.php');

$format = course_get_format($course);
$course = $format->get_course();

course_create_sections_if_missing($course, 0);

if (!is_null($displaysection)) {
    $format->set_sectionnum($displaysection);
}

$renderer = $PAGE->get_renderer('format_iomadvideo');
$outputclass = $format->get_output_classname('content');
$widget = new $outputclass($format);
echo $renderer->render($widget);
