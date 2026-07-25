<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Configurable tenant-aware dashboard block.
 *
 * @package    block_iomaddashboard
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * One multi-instance block exposing the maintained dashboard modes.
 */
class block_iomaddashboard extends block_base {
    /**
     * Initialise the block.
     */
    public function init(): void {
        $this->title = get_string('pluginname', 'block_iomaddashboard');
    }

    /**
     * Set the configured widget title.
     */
    public function specialization(): void {
        $widget = $this->config->widget ?? 'courseprogress';
        $catalog = \block_iomaddashboard\local\widget_catalog::all();
        $this->title = $catalog[$widget] ?? get_string('pluginname', 'block_iomaddashboard');
    }

    /**
     * Multiple dashboard widgets may be placed on one page.
     *
     * @return bool
     */
    public function instance_allow_multiple(): bool {
        return true;
    }

    /**
     * The block is useful on dashboards and course pages.
     *
     * @return array
     */
    public function applicable_formats(): array {
        return [
            'my' => true,
            'course-view' => true,
            'site-index' => true,
        ];
    }

    /**
     * Build role-filtered widget content.
     *
     * @return \stdClass
     */
    public function get_content(): \stdClass {
        global $OUTPUT, $USER;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new \stdClass();
        $this->content->footer = '';
        if (!isloggedin() || isguestuser()) {
            $this->content->text = '';
            return $this->content;
        }

        $widget = $this->config->widget ?? 'courseprogress';
        $limit = min(20, max(3, (int)($this->config->limit ?? 5)));
        $service = new \block_iomaddashboard\local\dashboard_service(
            $this->page->course,
            $this->page->context,
            $USER,
            $limit,
        );
        $data = $service->build($widget);
        $data['returnurl'] = $this->page->url->out_as_local_url(false);
        $data['sesskey'] = sesskey();
        $data['todourl'] = (new moodle_url('/blocks/iomaddashboard/todo.php'))->out(false);
        $this->content->text = $OUTPUT->render_from_template('block_iomaddashboard/widget', $data);
        return $this->content;
    }

    /**
     * This block does not expose global settings.
     *
     * @return bool
     */
    public function has_config(): bool {
        return false;
    }
}
