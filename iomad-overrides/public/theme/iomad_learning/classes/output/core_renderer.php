<?php
// This file is part of Moodle - http://moodle.org/

namespace theme_iomad_learning\output;

use theme_iomad_learning\local\footer_content;
use theme_iomad_learning\local\tenant_branding;

/**
 * Preserve IOMAD company assets and provide theme fallbacks.
 *
 * @package    theme_iomad_learning
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class core_renderer extends \theme_boost\output\core_renderer {
    /**
     * Add active-company design variables and supported IOMAD company CSS.
     *
     * @return string
     */
    public function standard_head_html() {
        $output = parent::standard_head_html();

        try {
            $companyid = \local_iomad\iomad::get_my_companyid(\context_system::instance(), false);
            if ($companyid <= 0) {
                return $output;
            }
            $company = new \local_iomad\company($companyid);
            $branding = $company->get([
                'bgcolor_header',
                'bgcolor_content',
                'maincolor',
                'headingcolor',
                'linkcolor',
                'customcss',
            ], true);
            $css = tenant_branding::build_css($branding);
            if ($css !== '') {
                $output .= \html_writer::tag('style', $css, [
                    'data-theme' => 'iomad-learning-tenant',
                ]);
            }
        } catch (\Throwable $exception) {
            debugging('Unable to load IOMAD company theme settings: ' . $exception->getMessage(), DEBUG_DEVELOPER);
        }

        return $output;
    }

    /**
     * Add the theme-managed footer before Moodle's standard operational links.
     *
     * @return string
     */
    public function standard_footer_html() {
        $platform = parent::standard_footer_html();
        return footer_content::render()
            . \html_writer::div($platform, 'iomad-learning-footer-platform');
    }

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
