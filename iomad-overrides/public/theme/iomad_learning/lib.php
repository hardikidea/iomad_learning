<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

use theme_iomad_learning\local\token_catalog;

/**
 * Main SCSS.
 *
 * @param theme_config $theme Theme.
 * @return string
 */
function theme_iomad_learning_get_main_scss_content($theme): string {
    global $CFG;

    return file_get_contents($CFG->dirroot . '/theme/boost/scss/preset/default.scss')
        . "\n"
        . file_get_contents(__DIR__ . '/scss/preset.scss');
}

/**
 * Compile core Bootstrap variables from typed tokens.
 *
 * @param theme_config $theme Theme.
 * @return string
 */
function theme_iomad_learning_get_pre_scss($theme): string {
    return '$primary: ' . token_catalog::css_value('primarycolor') . ";\n"
        . '$secondary: ' . token_catalog::css_value('secondarycolor') . ";\n"
        . '$font-family-sans-serif: ' . token_catalog::css_value('fontfamily') . ";\n";
}

/**
 * Add runtime tokens, uploaded assets, and supported feature switches.
 *
 * @param theme_config $theme Theme.
 * @return string
 */
function theme_iomad_learning_get_extra_scss($theme): string {
    $variables = [];
    foreach (token_catalog::definitions() as $key => $definition) {
        $variables[] = token_catalog::css_name($key) . ': ' . token_catalog::css_value($key);
    }
    $scss = ":root {\n  " . implode(";\n  ", $variables) . ";\n}\n";

    $loginbackground = $theme->setting_file_url('loginbackgroundimage', 'loginbackgroundimage');
    if ($loginbackground) {
        $scss .= 'body.pagelayout-login { background-image: linear-gradient('
            . 'rgba(17, 24, 39, var(--iomad-learning-loginoverlayopacity)), '
            . 'rgba(17, 24, 39, var(--iomad-learning-loginoverlayopacity))), '
            . "url('{$loginbackground}'); }\n";
    }
    $customfont = $theme->setting_file_url('customfont', 'customfont');
    if ($customfont) {
        $scss .= "@font-face { font-family: 'IOMAD Tenant Font'; src: url('{$customfont}') format('woff2'); "
            . "font-display: swap; }\n"
            . ":root { --iomad-learning-fontfamily: 'IOMAD Tenant Font', system-ui, sans-serif; }\n";
    }

    $switches = [
        'showbreadcrumbs' => '.breadcrumb',
        'showfooter' => '#page-footer',
        'showcourseindex' => '.drawer-left',
        'showblockdrawer' => '.drawer-right',
        'loginshowfooter' => 'body.pagelayout-login #page-footer',
        'loginshowlanguage' => 'body.pagelayout-login .login-languagemenu',
        'loginshowremember' => 'body.pagelayout-login .rememberpass',
        'loginshowguest' => 'body.pagelayout-login .login-guest',
        'courseimagevisible' => '.dashboard-card .dashboard-card-img',
        'courseprogressvisible' => '.dashboard-card .progress',
        'coursecategoryvisible' => '.dashboard-card .text-muted.categoryname',
        'courselastaccessvisible' => '.dashboard-card .text-muted',
        'dashboardshowicons' => '.block_iomaddashboard .iomaddashboard-icon',
        'dashboardshowcharts' => '.block_iomaddashboard .iomaddashboard-chart',
        'dashboardshowempty' => '.block_iomaddashboard .iomaddashboard-empty',
    ];
    foreach ($switches as $key => $selector) {
        if (token_catalog::value($key) === '0') {
            $scss .= "{$selector} { display: none !important; }\n";
        }
    }
    if (token_catalog::value('stickyheader') === '0') {
        $scss .= '.navbar.fixed-top { position: static !important; } body { padding-top: 0 !important; }' . "\n";
    }
    if (token_catalog::value('compactnavigation') === '1') {
        $scss .= '.primary-navigation .navigation .nav-link { padding-block: .25rem !important; }' . "\n";
    }
    if (token_catalog::value('underlinelinks') === '1') {
        $scss .= 'a:not(.btn):not(.nav-link) { text-decoration: underline; text-underline-offset: .15em; }' . "\n";
    }
    if (token_catalog::value('forcereadablewidth') === '1') {
        $scss .= '.activity-description, .book_content, .resourcecontent { max-width: var(--iomad-learning-readingwidth); }' . "\n";
    }
    if (token_catalog::value('highcontrastborders') === '1') {
        $scss .= '.card, .form-control, .btn, .dropdown-menu { border-width: 2px !important; }' . "\n";
    }
    if (token_catalog::value('reducetransparency') === '1') {
        $scss .= '.modal-backdrop, .drawer-backdrop { opacity: 1 !important; }' . "\n";
    }
    if (token_catalog::value('disablemotion') === '1') {
        $scss .= '*, *::before, *::after { animation-duration: .001ms !important; '
            . 'transition-duration: .001ms !important; scroll-behavior: auto !important; }' . "\n";
    }
    if (token_catalog::value('alwaysshowfocus') === '1') {
        $scss .= 'a, button, input, select, textarea { outline: var(--iomad-learning-focuswidth) '
            . 'var(--iomad-learning-focusstyle) transparent; }' . "\n";
    }
    return $scss . (get_config('theme_iomad_learning', 'customscss') ?: '');
}

/**
 * Load focus mode.
 *
 * @param moodle_page $page Page.
 */
function theme_iomad_learning_page_init(moodle_page $page): void {
    $page->requires->js_call_amd('theme_iomad_learning/focus', 'init');
}

/**
 * Serve uploaded theme assets.
 *
 * @param stdClass $course Course.
 * @param stdClass|null $cm Module.
 * @param context $context Context.
 * @param string $filearea File area.
 * @param array $args Arguments.
 * @param bool $forcedownload Force download.
 * @param array $options Options.
 * @return bool
 */
function theme_iomad_learning_pluginfile(
    $course,
    $cm,
    $context,
    $filearea,
    $args,
    $forcedownload,
    array $options = [],
): bool {
    $areas = ['logo', 'compactlogo', 'favicon', 'loginbackgroundimage', 'customfont'];
    if ($context->contextlevel !== CONTEXT_SYSTEM || !in_array($filearea, $areas, true)) {
        send_file_not_found();
    }
    $theme = theme_config::load('iomad_learning');
    $options['cacheability'] = 'public';
    return $theme->setting_file_serve($filearea, $args, $forcedownload, $options);
}
