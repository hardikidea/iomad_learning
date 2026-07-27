<?php
// This file is part of Moodle - http://moodle.org/

namespace theme_iomad_learning\local;

/**
 * Build the theme-managed footer content.
 *
 * @package    theme_iomad_learning
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class footer_content {
    /**
     * Render configured footer content using escaped values.
     *
     * @return string
     */
    public static function render(): string {
        $brand = self::setting('footerbrand', 'IOMAD Learning');
        $tagline = self::setting('footertagline', '');
        $contact = clean_param(self::setting('footercontact', ''), PARAM_EMAIL);
        $phone = clean_param(self::setting('footerphone', ''), PARAM_TEXT);
        $address = clean_param(self::setting('footeraddress', ''), PARAM_TEXT);
        $supporthours = clean_param(self::setting('footersupporthours', ''), PARAM_TEXT);
        $legal = self::setting('footerlegal', '');

        $identity = \html_writer::tag('strong', s($brand), ['class' => 'iomad-learning-footer-brand']);
        if ($tagline !== '') {
            $identity .= \html_writer::tag('span', s($tagline), ['class' => 'iomad-learning-footer-tagline']);
        }

        $links = [];
        foreach (
            [
                'footerhelpurl' => get_string('footerhelp', 'theme_iomad_learning'),
                'footerprivacyurl' => get_string('footerprivacy', 'theme_iomad_learning'),
                'footertermsurl' => get_string('footerterms', 'theme_iomad_learning'),
            ] as $key => $label
        ) {
            $url = clean_param(self::setting($key, ''), PARAM_URL);
            if ($url !== '') {
                $links[] = \html_writer::link($url, $label);
            }
        }
        if ($contact !== '') {
            $links[] = \html_writer::link('mailto:' . $contact, $contact);
        }

        $contactdetails = [];
        if ($address !== '') {
            $contactdetails[] = \html_writer::tag('span', s($address));
        }
        if ($phone !== '') {
            $phonehref = preg_replace('/[^0-9+]/', '', $phone);
            if ($phonehref !== '') {
                $contactdetails[] = \html_writer::link('tel:' . $phonehref, s($phone));
            }
        }
        if ($supporthours !== '') {
            $contactdetails[] = \html_writer::tag('span', s($supporthours));
        }
        $contactcontent = $contactdetails
            ? \html_writer::div(implode('', $contactdetails), 'iomad-learning-footer-contact')
            : '';

        $navigation = $links === []
            ? ''
            : \html_writer::tag('nav', implode('', $links), [
                'class' => 'iomad-learning-footer-links',
                'aria-label' => get_string('footernavigation', 'theme_iomad_learning'),
            ]);

        $sociallinks = [];
        foreach (
            [
                'footerfacebookurl' => ['facebook', 'socialfacebook'],
                'footerinstagramurl' => ['instagram', 'socialinstagram'],
                'footerlinkedinurl' => ['linkedin', 'sociallinkedin'],
                'footerxurl' => ['xSocial', 'socialx'],
                'footeryoutubeurl' => ['youtube', 'socialyoutube'],
                'footerwhatsappurl' => ['whatsapp', 'socialwhatsapp'],
            ] as $key => [$icon, $labelkey]
        ) {
            $url = clean_param(self::setting($key, ''), PARAM_URL);
            if ($url === '') {
                continue;
            }
            $label = get_string($labelkey, 'theme_iomad_learning');
            $sociallinks[] = \html_writer::link(
                $url,
                self::icon($icon) . \html_writer::span($label),
                [
                    'rel' => 'noopener noreferrer',
                    'target' => '_blank',
                    'aria-label' => $label,
                ],
            );
        }
        $social = $sociallinks
            ? \html_writer::tag('nav', implode('', $sociallinks), [
                'class' => 'iomad-learning-footer-social',
                'aria-label' => get_string('socialnavigation', 'theme_iomad_learning'),
            ])
            : '';

        $legalcontent = '&copy; ' . date('Y') . ' ' . s($brand);
        if ($legal !== '') {
            $legalcontent .= ' ' . s($legal);
        }

        return \html_writer::tag(
            'div',
            \html_writer::div($identity, 'iomad-learning-footer-identity')
                . $contactcontent
                . $navigation
                . $social
                . \html_writer::div($legalcontent, 'iomad-learning-footer-legal'),
            ['class' => 'iomad-learning-footer'],
        );
    }

    /**
     * Render one inert symbol from the theme sprite.
     *
     * @param string $name Symbol.
     * @return string
     */
    private static function icon(string $name): string {
        $use = \html_writer::empty_tag('use', [
            'href' => svg_icon_library::sprite_url()->out(false) . '#' . $name,
        ]);
        return \html_writer::tag('svg', $use, [
            'class' => 'iomad-learning-svg-icon',
            'viewBox' => '0 0 24 24',
            'fill' => 'none',
            'stroke' => 'currentColor',
            'stroke-linecap' => 'round',
            'stroke-linejoin' => 'round',
            'focusable' => 'false',
            'aria-hidden' => 'true',
        ]);
    }

    /**
     * Read a scalar theme setting.
     *
     * @param string $key Setting key.
     * @param string $default Default.
     * @return string
     */
    private static function setting(string $key, string $default): string {
        $value = get_config('theme_iomad_learning', $key);
        return $value === false ? $default : trim((string)$value);
    }
}
