<?php
// This file is part of Moodle - http://moodle.org/

namespace theme_iomad_learning\local;

/**
 * Canonical product icon mappings for the IOMAD Learning theme.
 *
 * @package    theme_iomad_learning
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class icon_catalog {
    /**
     * Project components which must always have a reviewed semantic icon.
     */
    private const PROJECT_COMPONENTS = [
        'block_dash' => 'fa-gauge-high',
        'block_gamification_telemetry' => 'fa-trophy',
        'block_iomaddashboard' => 'fa-table-cells-large',
        'block_iomadpagebuilder' => 'fa-object-group',
        'block_tenantform' => 'fa-file-signature',
        'format_designer' => 'fa-swatchbook',
        'format_iomadvideo' => 'fa-circle-play',
        'local_aicoursecreator' => 'fa-wand-magic-sparkles',
        'local_global_events' => 'fa-bolt',
        'local_institutionpack' => 'fa-boxes-stacked',
        'local_iomad' => 'fa-building',
        'local_iomad_h5p_bridge' => 'fa-bridge',
        'local_iomad_scorm_gen' => 'fa-box-open',
        'local_iomadcommerce' => 'fa-cart-shopping',
        'local_iomadconnect' => 'fa-link',
        'local_iomadpagebuilder' => 'fa-object-group',
        'local_rapidgrader' => 'fa-table-list',
        'local_tenantanalytics' => 'fa-chart-line',
        'local_tenantmaster' => 'fa-building-columns',
        'mod_tenantform' => 'fa-file-signature',
        'theme_iomad_learning' => 'fa-palette',
        'tool_courserating' => 'fa-star',
        'tool_iomadmonitor' => 'fa-heart-pulse',
    ];

    /**
     * Semantic icons for Moodle activities and high-use IOMAD components.
     */
    private const COMPONENT_ICONS = [
        'auth_iomadoidc' => 'fa-building-shield',
        'availability_company' => 'fa-building-columns',
        'block_iomad_company_admin' => 'fa-building',
        'block_iomad_learningpath' => 'fa-route',
        'block_iomad_microlearning' => 'fa-layer-group',
        'block_iomad_mycourses' => 'fa-graduation-cap',
        'block_iomad_reports' => 'fa-chart-column',
        'enrol_license' => 'fa-key',
        'mod_assign' => 'fa-file-pen',
        'mod_bigbluebuttonbn' => 'fa-video',
        'mod_book' => 'fa-book-open',
        'mod_choice' => 'fa-list-check',
        'mod_data' => 'fa-database',
        'mod_feedback' => 'fa-clipboard-question',
        'mod_folder' => 'fa-folder-open',
        'mod_forum' => 'fa-comments',
        'mod_glossary' => 'fa-book',
        'mod_h5pactivity' => 'fa-cubes',
        'mod_imscp' => 'fa-box-archive',
        'mod_iomadcertificate' => 'fa-certificate',
        'mod_label' => 'fa-tag',
        'mod_lesson' => 'fa-person-chalkboard',
        'mod_lti' => 'fa-puzzle-piece',
        'mod_page' => 'fa-file-lines',
        'mod_qbank' => 'fa-circle-question',
        'mod_quiz' => 'fa-list-check',
        'mod_resource' => 'fa-file-arrow-down',
        'mod_scorm' => 'fa-box-open',
        'mod_subsection' => 'fa-list',
        'mod_trainingevent' => 'fa-calendar-day',
        'mod_url' => 'fa-link',
        'mod_wiki' => 'fa-book-open',
        'mod_workshop' => 'fa-people-group',
        'repository_dropbox' => 'fa-brands fa-dropbox',
        'repository_nextcloud' => 'fa-cloud',
        'repository_onedrive' => 'fa-brands fa-microsoft',
    ];

    /**
     * Fallback icons by Moodle plugin type.
     */
    private const TYPE_FALLBACKS = [
        'aiplacement' => 'fa-wand-magic-sparkles',
        'aiprovider' => 'fa-brain',
        'antivirus' => 'fa-shield-virus',
        'assignfeedback' => 'fa-comment-dots',
        'assignsubmission' => 'fa-file-arrow-up',
        'auth' => 'fa-key',
        'availability' => 'fa-filter',
        'block' => 'fa-table-cells-large',
        'booktool' => 'fa-book',
        'cachestore' => 'fa-database',
        'communication' => 'fa-comments',
        'contenttype' => 'fa-file',
        'customfield' => 'fa-sliders',
        'datafield' => 'fa-table-list',
        'dataformat' => 'fa-file-export',
        'editor' => 'fa-pen-to-square',
        'enrol' => 'fa-user-plus',
        'factor' => 'fa-shield-halved',
        'fileconverter' => 'fa-file-export',
        'filter' => 'fa-filter',
        'format' => 'fa-list',
        'gradeexport' => 'fa-file-export',
        'gradeimport' => 'fa-file-import',
        'gradepenalty' => 'fa-scale-balanced',
        'gradereport' => 'fa-chart-column',
        'gradingform' => 'fa-clipboard-check',
        'local' => 'fa-plug',
        'logstore' => 'fa-clock-rotate-left',
        'media' => 'fa-circle-play',
        'message' => 'fa-message',
        'mlbackend' => 'fa-brain',
        'mod' => 'fa-puzzle-piece',
        'paygw' => 'fa-credit-card',
        'portfolio' => 'fa-briefcase',
        'profilefield' => 'fa-id-card',
        'qbank' => 'fa-circle-question',
        'qbehaviour' => 'fa-list-check',
        'qformat' => 'fa-file-import',
        'qtype' => 'fa-circle-question',
        'quiz' => 'fa-list-check',
        'quizaccess' => 'fa-shield-halved',
        'report' => 'fa-chart-line',
        'repository' => 'fa-folder-open',
        'scormreport' => 'fa-chart-column',
        'search' => 'fa-magnifying-glass',
        'smsgateway' => 'fa-comment-sms',
        'theme' => 'fa-palette',
        'tiny' => 'fa-pen-to-square',
        'tool' => 'fa-screwdriver-wrench',
        'webservice' => 'fa-code',
        'workshopallocation' => 'fa-people-arrows',
        'workshopeval' => 'fa-square-poll-vertical',
        'workshopform' => 'fa-clipboard-list',
    ];

    /**
     * Specific non-component icon mappings.
     */
    private const ICON_OVERRIDES = [
        'block_iomad_learningpath:learningpath' => 'fa-route',
        'block_iomad_mycourses:courses' => 'fa-graduation-cap',
        'booktool_print:book' => 'fa-book-open',
        'booktool_print:chapter' => 'fa-file-lines',
        'enrol_license:withkey' => 'fa-key',
        'enrol_license:withoutkey' => 'fa-unlock-keyhole',
        'mod_book:add' => 'fa-file-circle-plus',
        'mod_book:chapter' => 'fa-file-lines',
        'mod_scorm:browsed' => 'fa-eye',
        'mod_scorm:completed' => 'fa-circle-check text-success',
        'mod_scorm:failed' => 'fa-circle-xmark text-danger',
        'mod_scorm:incomplete' => 'fa-circle-half-stroke text-warning',
        'mod_scorm:notattempted' => 'fa-regular fa-circle',
        'mod_scorm:passed' => 'fa-circle-check text-success',
        'mod_scorm:suspend' => 'fa-circle-pause text-warning',
        'tool_courserating:star' => 'fa-star',
        'tool_courserating:star-half' => 'fa-regular fa-star-half-stroke',
        'tool_courserating:star-o' => 'fa-regular fa-star',
    ];

    /**
     * Components rendered with a project SVG when Font Awesome has no precise semantic match.
     */
    private const CUSTOM_ICONS = [
        'availability_company:icon' => 'iomad-learning-icon-custom iomad-learning-icon-institution',
        'availability_company:monologo' => 'iomad-learning-icon-custom iomad-learning-icon-institution',
        'local_tenantmaster:icon' => 'iomad-learning-icon-custom iomad-learning-icon-institution',
        'local_tenantmaster:monologo' => 'iomad-learning-icon-custom iomad-learning-icon-institution',
    ];

    /**
     * Build the complete map used by the theme icon system.
     *
     * Every installed plugin receives deterministic icon and monologo fallbacks.
     * Reviewed component and state mappings then replace those generic defaults.
     *
     * @return array<string, string>
     */
    public static function fontawesome_map(): array {
        $map = [];
        foreach (array_keys(\core_component::get_plugin_types()) as $type) {
            $fallback = self::TYPE_FALLBACKS[$type] ?? 'fa-puzzle-piece';
            foreach (array_keys(\core_component::get_plugin_list($type)) as $name) {
                $component = \core_component::normalize_componentname($type . '_' . $name);
                $map[$component . ':icon'] = $fallback;
                $map[$component . ':monologo'] = $fallback;
            }
        }

        foreach (self::COMPONENT_ICONS + self::PROJECT_COMPONENTS as $component => $icon) {
            $map[$component . ':icon'] = $icon;
            $map[$component . ':monologo'] = $icon;
        }

        return array_replace($map, self::ICON_OVERRIDES);
    }

    /**
     * Return project component mappings for contract tests and documentation.
     *
     * @return array<string, string>
     */
    public static function project_components(): array {
        return self::PROJECT_COMPONENTS;
    }

    /**
     * Return custom SVG classes for a pix icon.
     *
     * @param string|null $component Moodle component.
     * @param string $pix Pix icon name.
     * @return string|null
     */
    public static function custom_icon_classes(?string $component, string $pix): ?string {
        if ($component === null || $component === '' || $component === 'moodle') {
            $component = 'core';
        } else if ($component !== 'theme') {
            $component = \core_component::normalize_componentname($component);
        }

        return self::CUSTOM_ICONS[$component . ':' . $pix] ?? null;
    }

    /**
     * Return the component map used to replace image-based activity icons.
     *
     * Moodle intentionally renders activity monologos as images in several views,
     * so the browser adapter receives the same reviewed semantic assignments.
     *
     * @return array<string, string>
     */
    public static function client_component_map(): array {
        $map = self::COMPONENT_ICONS + self::PROJECT_COMPONENTS;
        foreach (array_keys(\core_component::get_plugin_list('mod')) as $name) {
            $component = \core_component::normalize_componentname('mod_' . $name);
            $map[$component] ??= self::TYPE_FALLBACKS['mod'];
        }
        foreach (self::CUSTOM_ICONS as $key => $classes) {
            [$component] = explode(':', $key, 2);
            $map[$component] = $classes;
        }
        ksort($map);

        return $map;
    }
}
