<?php
// This file is part of IOMAD - http://www.iomad.org/

namespace local_institutionpack;

defined('MOODLE_INTERNAL') || die();

/**
 * Supported cache purge and deterministic theme rebuild.
 */
final class cache_manager {
    /**
     * Build a cache operation plan.
     *
     * @param string $scope all or theme.
     * @param string $theme Installed theme name.
     * @return array
     */
    public function plan(string $scope, string $theme): array {
        $scope = trim($scope);
        $theme = trim($theme);
        if (!in_array($scope, ['all', 'theme'], true)) {
            throw new \InvalidArgumentException('Cache scope must be all or theme.');
        }
        if (
            clean_param($theme, PARAM_THEME) !== $theme
            || \core_component::get_plugin_directory('theme', $theme) === null
        ) {
            throw new \InvalidArgumentException('The requested theme is not installed.');
        }

        return [
            'ok' => true,
            'mode' => 'plan',
            'action' => 'purge-and-build',
            'scope' => $scope,
            'theme' => $theme,
            'directions' => ['ltr', 'rtl'],
        ];
    }

    /**
     * Purge supported caches and compile the selected theme.
     *
     * @param string $scope all or theme.
     * @param string $theme Installed theme name.
     * @return array
     */
    public function apply(string $scope, string $theme): array {
        global $CFG;

        $result = $this->plan($scope, $theme);
        require_once($CFG->libdir . '/csslib.php');
        require_once($CFG->libdir . '/outputlib.php');

        if ($scope === 'all') {
            purge_all_caches();
        } else {
            theme_reset_all_caches();
        }
        theme_build_css_for_themes(
            [\theme_config::load($theme)],
            ['ltr', 'rtl'],
            true,
            true
        );

        $result['mode'] = 'apply';
        $result['action'] = 'purged-and-built';
        $result['audit_report'] = audit_log::write('cache-rebuild', $result);
        return $result;
    }
}
