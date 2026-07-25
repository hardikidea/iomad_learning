<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Embed a configured tenant form activity on supported pages.
 *
 * @package    block_tenantform
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Tenant form block.
 */
final class block_tenantform extends block_base {
    /**
     * Initialise title.
     */
    public function init(): void {
        $this->title = get_string('pluginname', 'block_tenantform');
    }

    /**
     * Use selected form name as title.
     */
    public function specialization(): void {
        global $DB;

        $cmid = (int)($this->config->cmid ?? 0);
        if (!$cmid) {
            return;
        }
        $sql = "SELECT tf.name
                  FROM {tenantform} tf
                  JOIN {course_modules} cm ON cm.instance = tf.id
                  JOIN {modules} m ON m.id = cm.module
                 WHERE cm.id = :cmid AND m.name = :module";
        $name = $DB->get_field_sql($sql, ['cmid' => $cmid, 'module' => 'tenantform']);
        if ($name) {
            $this->title = format_string($name);
        }
    }

    /**
     * Allow forms on dashboards, site home and course pages.
     *
     * @return array
     */
    public function applicable_formats(): array {
        return ['my' => true, 'site-index' => true, 'course-view' => true];
    }

    /**
     * Multiple distinct forms may be embedded.
     *
     * @return bool
     */
    public function instance_allow_multiple(): bool {
        return true;
    }

    /**
     * Build block content.
     *
     * @return \stdClass
     */
    public function get_content(): \stdClass {
        global $DB, $USER;

        if ($this->content !== null) {
            return $this->content;
        }
        $this->content = (object)['text' => '', 'footer' => ''];
        $cmid = (int)($this->config->cmid ?? 0);
        if (!$cmid) {
            if ($this->page->user_is_editing()) {
                $this->content->text = get_string('selectform', 'block_tenantform');
            }
            return $this->content;
        }
        $cm = get_coursemodule_from_id('tenantform', $cmid);
        if (!$cm || !$cm->visible) {
            return $this->content;
        }
        $form = $DB->get_record('tenantform', ['id' => $cm->instance]);
        if (!$form || (isguestuser() && empty($form->allowguest))) {
            return $this->content;
        }
        $context = \context_module::instance($cm->id);
        if (!has_capability('mod/tenantform:submit', $context)) {
            return $this->content;
        }
        if (!isguestuser()) {
            try {
                \mod_tenantform\local\tenant_access::require_company($form, $context, $USER);
            } catch (\required_capability_exception) {
                return $this->content;
            }
        }
        $definition = (new \mod_tenantform\local\definition_validator())->from_json($form->definitionjson);
        $this->page->requires->js_call_amd('mod_tenantform/form', 'init');
        $this->content->text = (new \mod_tenantform\output\form_renderer())->render(
            $form,
            $definition,
            random_string(48),
            [],
            [],
            new \moodle_url('/mod/tenantform/view.php', ['id' => $cm->id]),
        );
        return $this->content;
    }
}
