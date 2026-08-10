<?php
// This file is part of Moodle - https://moodle.org/

namespace local_orgprofile\output;

use plugin_renderer_base;

/** Plugin renderer. */
final class renderer extends plugin_renderer_base {
    /** Render the administration dashboard Mustache template. */
    public function admin_dashboard(array $data): string {
        return $this->render_from_template('local_orgprofile/admin_dashboard', $data);
    }
}
