<?php
// This file is part of Moodle - http://moodle.org/

namespace theme_iomad_learning\local;

/**
 * Build safe runtime CSS from supported IOMAD company branding fields.
 *
 * @package    theme_iomad_learning
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tenant_branding {
    /**
     * Build the tenant-specific CSS appended after shared theme tokens.
     *
     * @param object|null $company Company branding fields.
     * @return string
     */
    public static function build_css(?object $company): string {
        if ($company === null) {
            return '';
        }

        $variables = [];

        $headercolour = self::normalise_colour((string)($company->bgcolor_header ?? ''));
        if ($headercolour !== '') {
            $contrast = self::contrast($headercolour);
            $variables['navbarbackground'] = $headercolour;
            $variables['navbartext'] = $contrast;
            $variables['headericoncolor'] = $contrast;
            $variables['headericonactive'] = $contrast;
        }

        $contentcolour = self::normalise_colour((string)($company->bgcolor_content ?? ''));
        if ($contentcolour !== '') {
            $variables['pagebackground'] = $contentcolour;
            $variables['dashboardbackground'] = $contentcolour;
        }

        $maincolour = self::normalise_colour((string)($company->maincolor ?? ''));
        if ($maincolour !== '') {
            $variables['primarycolor'] = $maincolour;
            $variables['primaryhover'] = self::darken($maincolour);
            $variables['primarycontrast'] = self::contrast($maincolour);
            $variables['iconactive'] = $maincolour;
            $variables['navigationiconactive'] = $maincolour;
        }

        $headingcolour = self::normalise_colour((string)($company->headingcolor ?? ''));
        if ($headingcolour !== '') {
            $variables['headingcolor'] = self::readable_on_light_surface($headingcolour);
        }

        $linkcolour = self::normalise_colour((string)($company->linkcolor ?? ''));
        if ($linkcolour !== '') {
            $linkcolour = self::readable_on_light_surface($linkcolour);
            $variables['linkcolor'] = $linkcolour;
            $variables['linkhover'] = self::darken($linkcolour);
            $variables['navigationiconcolor'] = $linkcolour;
        }

        $css = '';
        if ($variables !== []) {
            $css = ":root {\n";
            foreach ($variables as $key => $value) {
                $css .= "  --iomad-learning-{$key}: {$value};\n";
            }
            $css .= "}\n";
        }

        $customcss = self::sanitise_custom_css((string)($company->customcss ?? ''));
        if ($customcss !== '') {
            $css .= $customcss . "\n";
        }

        return $css;
    }

    /**
     * Accept only six-digit CSS colours from IOMAD's company colour picker.
     *
     * @param string $colour Colour.
     * @return string
     */
    public static function normalise_colour(string $colour): string {
        $colour = trim($colour);
        return preg_match('/^#[a-f0-9]{6}$/i', $colour) ? strtolower($colour) : '';
    }

    /**
     * Prevent company CSS from terminating the generated style element.
     *
     * Company CSS remains administrator-authored and otherwise unmodified.
     *
     * @param string $css CSS.
     * @return string
     */
    public static function sanitise_custom_css(string $css): string {
        $css = str_replace("\0", '', trim($css));
        return trim((string)preg_replace('/<\s*\/?\s*style\b/i', '', $css));
    }

    /**
     * Produce a deterministic hover colour.
     *
     * @param string $colour Valid six-digit colour.
     * @return string
     */
    private static function darken(string $colour): string {
        $channels = [
            hexdec(substr($colour, 1, 2)),
            hexdec(substr($colour, 3, 2)),
            hexdec(substr($colour, 5, 2)),
        ];
        return sprintf(
            '#%02x%02x%02x',
            (int)round($channels[0] * 0.82),
            (int)round($channels[1] * 0.82),
            (int)round($channels[2] * 0.82),
        );
    }

    /**
     * Choose a readable foreground for a company header colour.
     *
     * @param string $colour Valid six-digit colour.
     * @return string
     */
    private static function contrast(string $colour): string {
        $light = self::contrast_ratio('#ffffff', $colour);
        $dark = self::contrast_ratio('#172033', $colour);
        if (max($light, $dark) >= 4.5) {
            return $light > $dark ? '#ffffff' : '#172033';
        }
        return '#000000';
    }

    /**
     * Darken a tenant text colour until it meets WCAG AA on standard surfaces.
     *
     * @param string $colour Valid six-digit colour.
     * @return string
     */
    private static function readable_on_light_surface(string $colour): string {
        for ($attempt = 0; $attempt < 8 && self::contrast_ratio($colour, '#ffffff') < 4.5; $attempt++) {
            $colour = self::darken($colour);
        }
        return self::contrast_ratio($colour, '#ffffff') >= 4.5 ? $colour : '#172033';
    }

    /**
     * Calculate the WCAG contrast ratio for two six-digit colours.
     *
     * @param string $foreground Foreground.
     * @param string $background Background.
     * @return float
     */
    private static function contrast_ratio(string $foreground, string $background): float {
        $lighter = max(self::luminance($foreground), self::luminance($background));
        $darker = min(self::luminance($foreground), self::luminance($background));
        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /**
     * Convert a six-digit colour to relative luminance.
     *
     * @param string $colour Colour.
     * @return float
     */
    private static function luminance(string $colour): float {
        $channels = [
            hexdec(substr($colour, 1, 2)),
            hexdec(substr($colour, 3, 2)),
            hexdec(substr($colour, 5, 2)),
        ];
        $linear = array_map(static function (int $channel): float {
            $value = $channel / 255;
            return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, $channels);
        return (0.2126 * $linear[0]) + (0.7152 * $linear[1]) + (0.0722 * $linear[2]);
    }
}
