<?php
// This file is part of Moodle - http://moodle.org/

namespace theme_iomad_learning\output;

/**
 * Preserve IOMAD company assets and provide theme fallbacks.
 *
 * @package    theme_iomad_learning
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class core_renderer extends \theme_boost\output\core_renderer {
    /**
     * Site or company logo.
     *
     * @param int|null $maxwidth Width.
     * @param int|null $maxheight Height.
     * @return \moodle_url|false
     */
    public function get_logo_url($maxwidth = null, $maxheight = 200) {
        $url = parent::get_logo_url($maxwidth, $maxheight);
        return $url ?: $this->page->theme->setting_file_url('logo', 'logo');
    }

    /**
     * Compact logo.
     *
     * @param int|null $maxwidth Width.
     * @param int|null $maxheight Height.
     * @return \moodle_url|false
     */
    public function get_compact_logo_url($maxwidth = 300, $maxheight = 300) {
        $url = parent::get_compact_logo_url($maxwidth, $maxheight);
        return $url ?: $this->page->theme->setting_file_url('compactlogo', 'compactlogo');
    }

    /**
     * Company, site, or theme favicon.
     *
     * @return \moodle_url
     */
    public function favicon() {
        global $SESSION;

        $companyfavicon = !empty($SESSION->currenteditingcompany)
            ? get_config('core_admin', 'favicon' . $SESSION->currenteditingcompany)
            : '';
        if ($companyfavicon || get_config('core_admin', 'favicon')) {
            return parent::favicon();
        }
        if (get_config('theme_iomad_learning', 'favicon')) {
            $configured = $this->page->theme->setting_file_url('favicon', 'favicon');
            if ($configured) {
                return $configured;
            }
        }
        return new \moodle_url('/theme/iomad_learning/pix/favicon.svg', [
            'v' => theme_get_revision(),
        ]);
    }
}
