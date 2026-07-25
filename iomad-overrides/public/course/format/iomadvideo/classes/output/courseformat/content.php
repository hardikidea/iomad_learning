<?php
// This file is part of Moodle - http://moodle.org/

namespace format_iomadvideo\output\courseformat;

use core_courseformat\output\local\content as content_base;
use format_iomadvideo\local\playlist_service;
use renderer_base;

/**
 * Course content augmented with an accessible video playlist.
 *
 * @package    format_iomadvideo
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content extends content_base {
    /**
     * Use the format-specific wrapper template.
     *
     * @param renderer_base $renderer Renderer.
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'format_iomadvideo/courseformat/content';
    }

    /**
     * Export course and playlist data.
     *
     * @param renderer_base $output Renderer.
     * @return \stdClass
     */
    public function export_for_template(renderer_base $output) {
        global $PAGE;

        $data = parent::export_for_template($output);
        $course = $this->format->get_course();
        $options = $this->format->get_format_options();
        $service = new playlist_service($course);
        $items = $service->get_items();

        if ($items) {
            $first = true;
            foreach ($items as &$item) {
                $item['active'] = $first;
                $first = false;
            }
            unset($item);

            $layout = $options['videolayout'] ?? 'cinema';
            $allowedlayouts = ['cinema', 'classroom', 'split', 'theatre', 'compact', 'minimal'];
            if (!in_array($layout, $allowedlayouts, true)) {
                $layout = 'cinema';
            }

            $data->videoplaylist = (object)[
                'items' => $items,
                'count' => count($items),
                'layoutclass' => 'iomadvideo-layout-' . $layout,
                'collapsed' => !empty($options['playlistcollapsed']),
                'autoadvance' => !empty($options['autoadvance']),
                'focusmode' => !empty($options['focusmode']),
                'courseid' => $course->id,
            ];
            $PAGE->requires->js_call_amd('format_iomadvideo/player', 'init');
        }

        return $data;
    }
}
