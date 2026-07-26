<?php
// This file is part of Moodle - http://moodle.org/

namespace theme_iomad_learning\output;

use theme_iomad_learning\local\icon_catalog;
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
     * Render project SVG icons which do not have an accurate Font Awesome equivalent.
     *
     * @param \core\output\pix_icon $icon Icon.
     * @return string
     */
    protected function render_pix_icon(\core\output\pix_icon $icon) {
        $customclasses = icon_catalog::custom_icon_classes($icon->component, $icon->pix);
        if ($customclasses === null) {
            return parent::render_pix_icon($icon);
        }

        $attributes = $icon->attributes;
        $classes = trim('icon fa-fw ' . $customclasses . ' ' . ($attributes['class'] ?? ''));
        $alt = trim((string)($attributes['alt'] ?? ''));
        unset($attributes['class'], $attributes['alt']);
        $attributes['class'] = $classes;

        if (!empty($attributes['aria-hidden']) || $alt === '') {
            $attributes['aria-hidden'] = 'true';
            unset($attributes['aria-label'], $attributes['role']);
        } else {
            $attributes['role'] = 'img';
            $attributes['aria-label'] = $alt;
        }

        return \html_writer::tag('span', '', $attributes);
    }

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
            $branding = $company->get(['linkcolor', 'customcss'], true);
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
