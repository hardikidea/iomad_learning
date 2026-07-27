<?php
// This file is part of Moodle - http://moodle.org/

namespace theme_iomad_learning\output;

use core\output\pix_icon;
use core\output\renderer_base;
use theme_iomad_learning\local\icon_catalog;
use theme_iomad_learning\local\svg_icon_library;

/**
 * Accessible SVG sprite icon system.
 *
 * @package    theme_iomad_learning
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class icon_system_svg extends \core\output\icon_system {
    /**
     * Use Moodle's standard client icon loader. Dynamically inserted image
     * icons are upgraded to this SVG system by the theme icon adapter.
     *
     * @return string
     */
    #[\Override]
    public function get_amd_name() {
        return 'core/icon_system_standard';
    }

    /**
     * Render a Moodle pix icon from the theme-owned sprite.
     *
     * @param renderer_base $output Renderer.
     * @param pix_icon $icon Icon.
     * @return string
     */
    #[\Override]
    public function render_pix_icon(renderer_base $output, pix_icon $icon) {
        $name = icon_catalog::resolve($icon->component, $icon->pix);
        $attributes = $icon->attributes;
        $alt = trim((string)($attributes['alt'] ?? ''));
        $sourceclasses = preg_split('/\s+/', trim((string)($attributes['class'] ?? ''))) ?: [];
        $classes = array_filter($sourceclasses, static function (string $class): bool {
            if (in_array($class, ['fa', 'fas', 'far', 'fab'], true)) {
                return false;
            }
            if (str_starts_with($class, 'fa-')) {
                return in_array($class, ['fa-action', 'fa-fw', 'fa-spin', 'fa-topic'], true);
            }
            return $class !== '';
        });
        $classes[] = 'icon';
        $classes[] = 'iomad-learning-svg-icon';

        unset($attributes['alt']);
        $attributes['class'] = implode(' ', array_unique($classes));
        $attributes['viewBox'] = '0 0 24 24';
        $attributes['fill'] = 'none';
        $attributes['stroke'] = 'currentColor';
        $attributes['stroke-linecap'] = 'round';
        $attributes['stroke-linejoin'] = 'round';
        $attributes['focusable'] = 'false';
        $attributes['data-icon'] = $name;
        $attributes['data-component'] = (string)($icon->component ?: 'core');
        $attributes['data-pix'] = $icon->pix;

        if ($alt === '') {
            $attributes['aria-hidden'] = 'true';
            unset($attributes['aria-label'], $attributes['role']);
        } else {
            unset($attributes['aria-hidden']);
            $attributes['role'] = 'img';
            $attributes['aria-label'] = $alt;
        }

        $href = svg_icon_library::sprite_url()->out(false) . '#' . $name;
        $use = \html_writer::tag('use', '', ['href' => $href]);
        return \html_writer::tag('svg', $use, $attributes);
    }
}
