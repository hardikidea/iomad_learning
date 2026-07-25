<?php
// This file is part of Moodle - http://moodle.org/

namespace format_iomadvideo\output;

use core_courseformat\output\section_renderer;
use moodle_page;

/**
 * Renderer for the video-first course format.
 *
 * @package    format_iomadvideo
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends section_renderer {
    /**
     * Set editing capabilities used by section controls.
     *
     * @param moodle_page $page Page instance.
     * @param string $target Rendering target.
     */
    public function __construct(moodle_page $page, $target) {
        parent::__construct($page, $target);
        $page->set_other_editing_capability('moodle/course:setcurrentsection');
    }

    /**
     * Render a linked section title.
     *
     * @param \section_info|\stdClass $section Section.
     * @param \stdClass $course Course.
     * @return string
     */
    public function section_title($section, $course) {
        return $this->render(course_get_format($course)->inplace_editable_render_section_name($section));
    }

    /**
     * Render a section title without a link.
     *
     * @param \section_info|\stdClass $section Section.
     * @param int|\stdClass $course Course.
     * @return string
     */
    public function section_title_without_link($section, $course) {
        return $this->render(course_get_format($course)->inplace_editable_render_section_name($section, false));
    }
}
