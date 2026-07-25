<?php
// This file is part of Moodle - http://moodle.org/

namespace block_iomaddashboard\local;

/**
 * Maintained dashboard widget catalogue.
 *
 * @package    block_iomaddashboard
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class widget_catalog {
    /**
     * Return all supported widget modes.
     *
     * @return array
     */
    public static function all(): array {
        return [
            'courseprogress' => get_string('widgetcourseprogress', 'block_iomaddashboard'),
            'enrolledusers' => get_string('widgetenrolledusers', 'block_iomaddashboard'),
            'quizattempts' => get_string('widgetquizattempts', 'block_iomaddashboard'),
            'courseanalytics' => get_string('widgetcourseanalytics', 'block_iomaddashboard'),
            'latestmembers' => get_string('widgetlatestmembers', 'block_iomaddashboard'),
            'addnotes' => get_string('widgetaddnotes', 'block_iomaddashboard'),
            'recentfeedback' => get_string('widgetrecentfeedback', 'block_iomaddashboard'),
            'recentforums' => get_string('widgetrecentforums', 'block_iomaddashboard'),
            'managecourse' => get_string('widgetmanagecourse', 'block_iomaddashboard'),
            'todo' => get_string('widgettodo', 'block_iomaddashboard'),
        ];
    }

    /**
     * Check a widget identifier without accepting arbitrary method names.
     *
     * @param string $widget Widget identifier.
     * @return bool
     */
    public static function exists(string $widget): bool {
        return array_key_exists($widget, self::all());
    }
}
