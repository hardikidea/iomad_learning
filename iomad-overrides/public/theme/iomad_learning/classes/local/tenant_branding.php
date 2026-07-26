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

        $css = '';
        $linkcolour = self::normalise_colour((string)($company->linkcolor ?? ''));
        if ($linkcolour !== '') {
            $hovercolour = self::darken($linkcolour);
            $css .= ":root {\n"
                . "  --iomad-learning-linkcolor: {$linkcolour};\n"
                . "  --iomad-learning-linkhover: {$hovercolour};\n"
                . "  --iomad-learning-navigationiconcolor: {$linkcolour};\n"
                . "  --iomad-learning-navigationiconactive: {$hovercolour};\n"
                . "}\n";
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
}
