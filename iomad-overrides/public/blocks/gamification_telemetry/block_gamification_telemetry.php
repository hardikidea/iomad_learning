<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Tenant-safe gamification feedback block.
 *
 * @package block_gamification_telemetry
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Render the current learner's own progress.
 */
class block_gamification_telemetry extends block_base {
    /**
     * Initialise.
     */
    public function init(): void {
        $this->title = get_string('pluginname', 'block_gamification_telemetry');
    }

    /**
     * Dashboard and course placement.
     *
     * @return array
     */
    public function applicable_formats(): array {
        return ['my' => true, 'course-view' => true, 'site-index' => true];
    }

    /**
     * Content.
     *
     * @return stdClass
     */
    public function get_content(): stdClass {
        global $OUTPUT, $USER;

        if ($this->content !== null) {
            return $this->content;
        }
        $this->content = new stdClass();
        $this->content->footer = '';
        if (!isloggedin() || isguestuser()) {
            $this->content->text = '';
            return $this->content;
        }
        try {
            $scope = \local_global_events\local\tenant_scope::current();
            $dashboard = (new \local_global_events\local\dashboard_service())->learner(
                $scope,
                (int)$USER->id,
            );
            $progress = $dashboard['progress'];
            $rootid = 'gamification-telemetry-' . (int)($this->instance->id ?? 0);
            $data = $progress + [
                'rootid' => $rootid,
                'haslevel' => $progress['levelname'] !== '',
                'hasnextlevel' => $progress['nextlevelname'] !== '',
                'hasbadges' => $dashboard['badges'] !== [],
                'badges' => array_slice($dashboard['badges'], 0, 3),
                'dashboardurl' => (new moodle_url('/local/global_events/index.php'))->out(false),
            ];
            $this->content->text = $OUTPUT->render_from_template(
                'block_gamification_telemetry/telemetry_block',
                $data,
            );
            $this->page->requires->js_call_amd(
                'block_gamification_telemetry/dashboard_effects',
                'init',
                [$rootid, $progress['points']],
            );
        } catch (\Throwable) {
            $this->content->text = '';
        }
        return $this->content;
    }

    /**
     * No global settings.
     *
     * @return bool
     */
    public function has_config(): bool {
        return false;
    }
}
