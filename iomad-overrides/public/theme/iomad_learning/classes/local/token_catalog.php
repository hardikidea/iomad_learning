<?php
// This file is part of Moodle - http://moodle.org/

namespace theme_iomad_learning\local;

/**
 * Typed design-token catalogue.
 *
 * @package    theme_iomad_learning
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class token_catalog {
    /**
     * Token groups.
     *
     * @return array
     */
    public static function groups(): array {
        return [
            'colours' => 'Colours',
            'typography' => 'Typography',
            'spacing' => 'Spacing and sizing',
            'shape' => 'Borders and elevation',
            'navigation' => 'Navigation',
            'login' => 'Login',
            'courses' => 'Courses',
            'dashboard' => 'Dashboard',
            'accessibility' => 'Accessibility and motion',
        ];
    }

    /**
     * All token definitions.
     *
     * @return array
     */
    public static function definitions(): array {
        $definitions = [];
        self::add($definitions, 'colours', 'colour', [
            'primarycolor' => '#8a3145',
            'primaryhover' => '#6f2536',
            'primarycontrast' => '#ffffff',
            'secondarycolor' => '#4f5b6b',
            'secondaryhover' => '#3e4856',
            'secondarycontrast' => '#ffffff',
            'accentcolor' => '#b74d2a',
            'successcolor' => '#217a45',
            'warningcolor' => '#a65c00',
            'dangercolor' => '#b42318',
            'infocolor' => '#176b87',
            'bodytext' => '#1d2433',
            'mutedtext' => '#5e6878',
            'inversetext' => '#ffffff',
            'pagebackground' => '#f4f6f9',
            'surface' => '#ffffff',
            'surfacealternate' => '#eef2f7',
            'bordercolor' => '#d6dce6',
            'borderstrong' => '#9ca8b8',
            'linkcolor' => '#364152',
            'linkhover' => '#1f2937',
            'headingcolor' => '#1d2433',
            'iconcolor' => '#3f4b5f',
            'iconmuted' => '#6b7688',
            'iconactive' => '#8a3145',
            'navigationiconcolor' => '#5e6878',
            'navigationiconactive' => '#8a3145',
            'headericoncolor' => '#cbd5e1',
            'headericonactive' => '#ffffff',
            'focuscolor' => '#ffbf47',
            'selectionbackground' => '#f3e5e8',
            'selectiontext' => '#172033',
            'navbarbackground' => '#172033',
            'navbartext' => '#f6f8fb',
            'navbarborder' => '#2d3748',
            'sidebarbackground' => '#202936',
            'sidebartext' => '#f6f8fb',
            'sidebaractive' => '#dca8b5',
            'footerbackground' => '#202936',
            'footertext' => '#f6f8fb',
            'footerlink' => '#f1d8de',
            'footerborder' => '#3c4655',
            'loginbackground' => '#e8edf4',
            'loginsurface' => '#ffffff',
            'coursecardbackground' => '#ffffff',
            'coursecardtext' => '#1d2433',
            'dashboardbackground' => '#f4f6f9',
            'inputbackground' => '#ffffff',
            'inputtext' => '#1d2433',
            'inputborder' => '#9ca8b8',
            'tableheaderbackground' => '#eef2f7',
            'tableheadertext' => '#1d2433',
            'overlaycolor' => '#111827',
            'coursecomplete' => '#217a45',
            'courseinprogress' => '#176b87',
            'courseoverdue' => '#b42318',
        ]);
        self::add($definitions, 'typography', 'font', [
            'fontfamily' => 'system',
            'headingfont' => 'system',
            'monofont' => 'mono',
        ]);
        self::add($definitions, 'typography', 'fontsize', [
            'basefontsize' => '1rem',
            'smallfontsize' => '0.875rem',
            'largefontsize' => '1.125rem',
            'h1fontsize' => '2.25rem',
            'h2fontsize' => '1.75rem',
            'h3fontsize' => '1.5rem',
            'h4fontsize' => '1.25rem',
            'h5fontsize' => '1.125rem',
            'h6fontsize' => '1rem',
            'navfontsize' => '0.9375rem',
            'buttonfontsize' => '0.9375rem',
            'tablefontsize' => '0.9375rem',
            'captionfontsize' => '0.8125rem',
            'courseheadingfontsize' => '1.125rem',
            'dashboardmetricfontsize' => '2rem',
        ]);
        self::add($definitions, 'typography', 'weight', [
            'bodyweight' => '400',
            'headingweight' => '700',
            'navweight' => '600',
            'buttonweight' => '600',
            'courseheadingweight' => '600',
            'loginheadingweight' => '700',
        ]);
        self::add($definitions, 'typography', 'lineheight', [
            'bodylineheight' => '1.5',
            'headinglineheight' => '1.25',
            'compactlineheight' => '1.25',
        ]);
        self::add($definitions, 'typography', 'letterspacing', [
            'bodyletterspacing' => '0',
            'headingletterspacing' => '0',
            'navletterspacing' => '0',
            'buttonletterspacing' => '0',
        ]);
        self::add($definitions, 'typography', 'texttransform', [
            'buttontexttransform' => 'none',
            'navtexttransform' => 'none',
            'coursecategorytransform' => 'none',
        ]);
        self::add($definitions, 'spacing', 'spacing', [
            'space1' => '0.25rem',
            'space2' => '0.5rem',
            'space3' => '0.75rem',
            'space4' => '1rem',
            'space5' => '1.5rem',
            'space6' => '2rem',
            'space7' => '3rem',
            'space8' => '4rem',
            'pagepaddingx' => '1rem',
            'pagepaddingy' => '0.75rem',
            'sectiongap' => '2rem',
            'gridgap' => '1rem',
            'cardpadding' => '1rem',
            'cardgap' => '0.75rem',
            'inputgap' => '0.75rem',
            'tablecellx' => '0.75rem',
            'tablecelly' => '0.625rem',
            'modalpadding' => '1.5rem',
            'loginpadding' => '2rem',
            'courseactivitygap' => '0.75rem',
            'coursesectionpadding' => '1.25rem',
            'coursesectiongap' => '1rem',
            'dashboardwidgetgap' => '1rem',
            'dashboardwidgetpadding' => '1rem',
            'dashboardlistgap' => '0.5rem',
        ]);
        self::add($definitions, 'spacing', 'size', [
            'navbarheight' => '4rem',
            'sidebarwidth' => '18rem',
            'drawerwidth' => '20rem',
            'inputheight' => '2.75rem',
            'buttonheight' => '2.5rem',
            'footerheight' => '8rem',
            'headerlogoheight' => '2.5rem',
            'headerlogomobileheight' => '2rem',
            'courseindexwidth' => '20rem',
            'loginmaxwidth' => '28rem',
            'loginminheight' => '40rem',
            'loginlogoheight' => '4rem',
            'coursecardminwidth' => '16rem',
            'courseimageheight' => '10rem',
            'courseprogressheight' => '0.5rem',
            'courseplayersidebarwidth' => '22rem',
            'dashboardminwidth' => '16rem',
            'dashboardchartheight' => '16rem',
            'todochecksize' => '1.25rem',
            'targetsize' => '2.75rem',
            'readingwidth' => '70ch',
            'sidebariconsize' => '1.125rem',
            'navigationiconsize' => '1rem',
            'iconsize' => '1.125rem',
            'adminiconsize' => '3rem',
            'adminactioniconsize' => '1.25rem',
            'breadcrumbheight' => '2.5rem',
        ]);
        self::add($definitions, 'spacing', 'maxwidth', [
            'contentmaxwidth' => 'none',
            'coursecontentmaxwidth' => 'none',
        ]);
        self::add($definitions, 'shape', 'radius', [
            'radiusxs' => '0.125rem',
            'radiussm' => '0.25rem',
            'radiusmd' => '0.375rem',
            'radiuslg' => '0.5rem',
            'radiuspill' => '999rem',
            'buttonradius' => '0.375rem',
            'inputradius' => '0.375rem',
            'cardradius' => '0.5rem',
            'modalradius' => '0.5rem',
            'avatarradius' => '999rem',
            'loginradius' => '0.5rem',
            'coursesectionradius' => '0.5rem',
            'courseimageradius' => '0.375rem',
            'dashboardwidgetradius' => '0.5rem',
        ]);
        self::add($definitions, 'shape', 'borderwidth', [
            'borderwidth' => '1px',
            'focuswidth' => '3px',
            'loginborderwidth' => '1px',
            'sidebaractiveborder' => '3px',
            'tabindicatorheight' => '3px',
        ]);
        self::add($definitions, 'shape', 'strokewidth', [
            'iconstrokewidth' => '1.75',
        ]);
        self::add($definitions, 'shape', 'shadow', [
            'shadowsm' => 'sm',
            'shadowmd' => 'md',
            'shadowlg' => 'lg',
            'navbarshadow' => 'none',
            'cardshadow' => 'sm',
            'modalshadow' => 'lg',
            'loginshadow' => 'md',
            'dashboardwidgetshadow' => 'sm',
        ]);
        self::add($definitions, 'shape', 'borderstyle', [
            'dividerstyle' => 'solid',
            'focusstyle' => 'solid',
        ]);
        self::add($definitions, 'shape', 'spacing', [
            'focusoffset' => '0.125rem',
            'coursehovertranslate' => '0.125rem',
        ]);
        self::add($definitions, 'navigation', 'spacing', [
            'navitempaddingx' => '0.75rem',
            'navitempaddingy' => '0.5rem',
            'navitemgap' => '0.25rem',
            'sidebaritemgap' => '0.25rem',
            'sidebarindent' => '1rem',
            'breadcrumbgap' => '0.5rem',
            'courseindexitemgap' => '0.25rem',
        ]);
        self::add($definitions, 'navigation', 'size', [
            'sidebaritemheight' => '2.75rem',
            'tabheight' => '2.75rem',
            'usermenuavatar' => '2rem',
        ]);
        self::add($definitions, 'navigation', 'opacity', [
            'draweroverlayopacity' => '0.5',
        ]);
        self::add($definitions, 'navigation', 'boolean', [
            'stickyheader' => '1',
            'compactnavigation' => '0',
            'showbreadcrumbs' => '1',
            'showfooter' => '1',
            'showcourseindex' => '1',
            'showblockdrawer' => '1',
            'shownavigationicons' => '1',
        ]);
        self::add($definitions, 'navigation', 'align', [
            'navigationalign' => 'start',
            'footeralign' => 'start',
        ]);
        self::add($definitions, 'login', 'spacing', [
            'logininputgap' => '1rem',
            'logincontentgap' => '1.5rem',
        ]);
        self::add($definitions, 'login', 'opacity', [
            'loginoverlayopacity' => '0.45',
        ]);
        self::add($definitions, 'login', 'backgroundposition', [
            'loginbackgroundposition' => 'center',
        ]);
        self::add($definitions, 'login', 'backgroundsize', [
            'loginbackgroundsize' => 'cover',
        ]);
        self::add($definitions, 'login', 'align', [
            'loginalign' => 'center',
        ]);
        self::add($definitions, 'login', 'boolean', [
            'loginbuttonfullwidth' => '1',
            'loginshowfooter' => '1',
            'loginshowlanguage' => '1',
            'loginshowremember' => '1',
            'loginshowguest' => '1',
        ]);
        self::add($definitions, 'login', 'density', [
            'loginformdensity' => 'comfortable',
        ]);
        self::add($definitions, 'courses', 'columns', [
            'coursegridcolumns' => 'auto',
        ]);
        self::add($definitions, 'courses', 'aspect', [
            'coursecardaspect' => '16/9',
            'courseplayeraspect' => '16/9',
        ]);
        self::add($definitions, 'courses', 'fontsize', [
            'courseindexfontsize' => '0.9375rem',
            'coursemetadatafontsize' => '0.875rem',
            'courseprogressfontsize' => '0.8125rem',
        ]);
        self::add($definitions, 'courses', 'spacing', [
            'coursemetadatagap' => '0.5rem',
            'coursebadgepaddingx' => '0.5rem',
            'coursebadgepaddingy' => '0.25rem',
        ]);
        self::add($definitions, 'courses', 'radius', [
            'coursebadgeradius' => '999rem',
        ]);
        self::add($definitions, 'courses', 'boolean', [
            'courseimagevisible' => '1',
            'courseprogressvisible' => '1',
            'coursecategoryvisible' => '1',
            'courselastaccessvisible' => '1',
            'coursefocusavailable' => '1',
            'courseautoplay' => '0',
            'coursecollapsiblesections' => '1',
        ]);
        self::add($definitions, 'courses', 'density', [
            'courselistdensity' => 'comfortable',
        ]);
        self::add($definitions, 'dashboard', 'columns', [
            'dashboardcolumns' => 'auto',
        ]);
        self::add($definitions, 'dashboard', 'fontsize', [
            'dashboardheadingfontsize' => '1.125rem',
            'dashboardlabelfontsize' => '0.875rem',
        ]);
        self::add($definitions, 'dashboard', 'opacity', [
            'todocompletedopacity' => '0.6',
            'dashboardmutedopacity' => '0.72',
        ]);
        self::add($definitions, 'dashboard', 'boolean', [
            'dashboardshowicons' => '1',
            'dashboardshowcharts' => '1',
            'dashboardshowempty' => '1',
            'dashboardcompactmobile' => '1',
        ]);
        self::add($definitions, 'dashboard', 'density', [
            'dashboarddensity' => 'comfortable',
        ]);
        self::add($definitions, 'accessibility', 'opacity', [
            'focusringopacity' => '1',
            'tablezebraopacity' => '0.5',
        ]);
        self::add($definitions, 'accessibility', 'duration', [
            'motionduration' => '150ms',
            'slowmotionduration' => '300ms',
        ]);
        self::add($definitions, 'accessibility', 'spacing', [
            'motiondistance' => '0.125rem',
            'skiplinktop' => '0.5rem',
            'skiplinkleft' => '0.5rem',
        ]);
        self::add($definitions, 'accessibility', 'boolean', [
            'reducetransparency' => '0',
            'underlinelinks' => '0',
            'rtlmirroricons' => '1',
            'forcereadablewidth' => '0',
            'highcontrastborders' => '0',
            'alwaysshowfocus' => '0',
            'disablemotion' => '0',
        ]);
        self::add($definitions, 'accessibility', 'currentstyle', [
            'currentitemstyle' => 'border',
        ]);
        return $definitions;
    }

    /**
     * Return the current normalized token value.
     *
     * @param string $key Key.
     * @return string
     */
    public static function value(string $key): string {
        $definitions = self::definitions();
        if (!isset($definitions[$key])) {
            throw new \coding_exception('Unknown theme design token.');
        }
        $configured = get_config('theme_iomad_learning', $key);
        return self::normalize($key, $configured === false ? $definitions[$key]['default'] : (string)$configured);
    }

    /**
     * Validate a token value.
     *
     * @param string $key Key.
     * @param string $value Value.
     * @return string
     */
    public static function normalize(string $key, string $value): string {
        $definitions = self::definitions();
        if (!isset($definitions[$key])) {
            throw new \invalid_parameter_exception('Unknown theme design token.');
        }
        $definition = $definitions[$key];
        if ($definition['type'] === 'colour') {
            return preg_match('/^#[a-f0-9]{6}$/i', $value) ? strtolower($value) : $definition['default'];
        }
        if ($definition['type'] === 'boolean') {
            return $value === '1' ? '1' : '0';
        }
        return array_key_exists($value, $definition['options']) ? $value : $definition['default'];
    }

    /**
     * CSS variable name.
     *
     * @param string $key Key.
     * @return string
     */
    public static function css_name(string $key): string {
        return '--iomad-learning-' . str_replace('_', '-', $key);
    }

    /**
     * CSS-ready value, resolving symbolic options.
     *
     * @param string $key Key.
     * @return string
     */
    public static function css_value(string $key): string {
        $definition = self::definitions()[$key];
        $value = self::value($key);
        return (string)($definition['options'][$value] ?? $value);
    }

    /**
     * Ensure that a configured foreground remains readable on its background.
     *
     * @param string $foreground Preferred foreground colour.
     * @param string $background Background colour.
     * @param float $minimum Minimum WCAG contrast ratio.
     * @return string
     */
    public static function ensure_contrast(
        string $foreground,
        string $background,
        float $minimum = 4.5
    ): string {
        if (self::contrast_ratio($foreground, $background) >= $minimum) {
            return strtolower($foreground);
        }

        $alternatives = ['#ffffff', '#172033'];
        usort($alternatives, static function(string $left, string $right) use ($background): int {
            return self::contrast_ratio($right, $background) <=> self::contrast_ratio($left, $background);
        });
        return $alternatives[0];
    }

    /**
     * Calculate the WCAG contrast ratio for two six-digit hexadecimal colours.
     *
     * @param string $foreground Foreground colour.
     * @param string $background Background colour.
     * @return float
     */
    public static function contrast_ratio(string $foreground, string $background): float {
        $lighter = max(self::relative_luminance($foreground), self::relative_luminance($background));
        $darker = min(self::relative_luminance($foreground), self::relative_luminance($background));
        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /**
     * Convert a six-digit hexadecimal colour to relative luminance.
     *
     * @param string $colour Colour.
     * @return float
     */
    private static function relative_luminance(string $colour): float {
        if (!preg_match('/^#[a-f0-9]{6}$/i', $colour)) {
            throw new \invalid_parameter_exception('Contrast colours must use six-digit hexadecimal notation.');
        }
        $channels = [
            hexdec(substr($colour, 1, 2)) / 255,
            hexdec(substr($colour, 3, 2)) / 255,
            hexdec(substr($colour, 5, 2)) / 255,
        ];
        $channels = array_map(static function(float $channel): float {
            return $channel <= 0.04045
                ? $channel / 12.92
                : (($channel + 0.055) / 1.055) ** 2.4;
        }, $channels);
        return (0.2126 * $channels[0]) + (0.7152 * $channels[1]) + (0.0722 * $channels[2]);
    }

    /**
     * Add a typed token list.
     *
     * @param array $definitions Definitions.
     * @param string $group Group.
     * @param string $type Type.
     * @param array $tokens Key/default map.
     */
    private static function add(array &$definitions, string $group, string $type, array $tokens): void {
        $options = self::options($type);
        foreach ($tokens as $key => $default) {
            $definitions[$key] = [
                'group' => $group,
                'type' => $type,
                'label' => ucfirst(str_replace('_', ' ', $key)),
                'default' => (string)$default,
                'options' => $options,
            ];
        }
    }

    /**
     * Safe option sets.
     *
     * @param string $type Type.
     * @return array
     */
    private static function options(string $type): array {
        return match ($type) {
            'font' => [
                'system' => 'Inter, system-ui, -apple-system, "Segoe UI", sans-serif',
                'humanist' => '"Noto Sans", "Segoe UI", Arial, sans-serif',
                'serif' => 'Georgia, "Times New Roman", serif',
                'mono' => '"SFMono-Regular", Consolas, "Liberation Mono", monospace',
            ],
            'fontsize' => array_combine(
                ['0.75rem', '0.8125rem', '0.875rem', '0.9375rem', '1rem', '1.125rem', '1.25rem',
                    '1.5rem', '1.75rem', '2rem', '2.25rem', '2.5rem', '3rem'],
                ['0.75rem', '0.8125rem', '0.875rem', '0.9375rem', '1rem', '1.125rem', '1.25rem',
                    '1.5rem', '1.75rem', '2rem', '2.25rem', '2.5rem', '3rem'],
            ),
            'weight' => ['300' => '300', '400' => '400', '500' => '500', '600' => '600', '700' => '700', '800' => '800'],
            'lineheight' => ['1.1' => '1.1', '1.25' => '1.25', '1.4' => '1.4', '1.5' => '1.5', '1.6' => '1.6', '1.75' => '1.75'],
            'letterspacing' => ['0' => '0', '0.01em' => '0.01em', '0.025em' => '0.025em', '0.05em' => '0.05em'],
            'texttransform' => ['none' => 'none', 'uppercase' => 'uppercase', 'capitalize' => 'capitalize'],
            'spacing' => self::same(['0', '0.125rem', '0.25rem', '0.5rem', '0.75rem', '1rem', '1.25rem',
                '1.5rem', '2rem', '3rem', '4rem', '5rem']),
            'size' => self::same(['1rem', '1.125rem', '1.25rem', '2rem', '2.5rem', '2.75rem', '3rem',
                '4rem', '5rem', '10rem', '16rem', '18rem', '20rem', '22rem', '24rem', '28rem',
                '32rem', '40rem', '60rem', '70ch', '75rem', '80rem', '90rem']),
            'maxwidth' => self::same(['none', '60rem', '75rem', '80rem', '90rem']),
            'radius' => self::same(['0', '0.125rem', '0.25rem', '0.375rem', '0.5rem', '0.75rem', '1rem', '999rem']),
            'borderwidth' => self::same(['0', '1px', '2px', '3px', '4px']),
            'strokewidth' => self::same(['1.5', '1.75', '2', '2.25']),
            'shadow' => [
                'none' => 'none',
                'sm' => '0 1px 2px rgba(17, 24, 39, 0.12)',
                'md' => '0 4px 12px rgba(17, 24, 39, 0.16)',
                'lg' => '0 12px 28px rgba(17, 24, 39, 0.2)',
            ],
            'borderstyle' => ['solid' => 'solid', 'dashed' => 'dashed', 'dotted' => 'dotted', 'double' => 'double'],
            'opacity' => self::same(['0', '0.25', '0.4', '0.45', '0.5', '0.6', '0.72', '0.8', '0.9', '1']),
            'boolean' => ['0' => '0', '1' => '1'],
            'align' => ['start' => 'start', 'center' => 'center', 'end' => 'end'],
            'backgroundposition' => ['center' => 'center', 'top' => 'center top', 'bottom' => 'center bottom', 'left' => 'left center', 'right' => 'right center'],
            'backgroundsize' => ['cover' => 'cover', 'contain' => 'contain', 'auto' => 'auto'],
            'density' => ['compact' => '0.85', 'comfortable' => '1', 'spacious' => '1.2'],
            'columns' => [
                'auto' => 'repeat(auto-fit, minmax(16rem, 1fr))',
                'one' => '1fr',
                'two' => 'repeat(2, minmax(0, 1fr))',
                'three' => 'repeat(3, minmax(0, 1fr))',
                'four' => 'repeat(4, minmax(0, 1fr))',
            ],
            'aspect' => ['16/9' => '16 / 9', '4/3' => '4 / 3', '3/2' => '3 / 2', '1/1' => '1 / 1'],
            'duration' => self::same(['0ms', '100ms', '150ms', '200ms', '300ms', '500ms']),
            'currentstyle' => ['border' => 'inset 3px 0 currentColor', 'background' => 'inset 0 0 0 999px rgba(127, 127, 127, 0.12)', 'bold' => 'inset 0 0 0 1px currentColor'],
            default => [],
        };
    }

    /**
     * Build an identity option map.
     *
     * @param array $values Values.
     * @return array
     */
    private static function same(array $values): array {
        return array_combine($values, $values);
    }
}
