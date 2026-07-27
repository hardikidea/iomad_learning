<?php
// This file is part of Moodle - http://moodle.org/

namespace theme_iomad_learning;

use theme_iomad_learning\local\footer_content;

/**
 * Theme-managed footer contract tests.
 *
 * @package    theme_iomad_learning
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \theme_iomad_learning\local\footer_content
 */
final class footer_content_test extends \advanced_testcase {
    /**
     * Configured footer fields are rendered and escaped.
     */
    public function test_footer_renders_safe_configured_content(): void {
        $this->resetAfterTest(true);
        set_config('footerbrand', '<School & University>', 'theme_iomad_learning');
        set_config('footertagline', 'Tenant learning', 'theme_iomad_learning');
        set_config('footercontact', 'support@example.test', 'theme_iomad_learning');
        set_config('footerphone', '+91 20 5555 0101', 'theme_iomad_learning');
        set_config('footeraddress', 'Pune, India', 'theme_iomad_learning');
        set_config('footerhelpurl', 'https://example.test/help', 'theme_iomad_learning');
        set_config('footerlinkedinurl', 'https://example.test/linkedin', 'theme_iomad_learning');
        set_config('footerlegal', '<script>alert(1)</script>', 'theme_iomad_learning');

        $html = footer_content::render();

        $this->assertStringContainsString('&lt;School &amp; University&gt;', $html);
        $this->assertStringContainsString('Tenant learning', $html);
        $this->assertStringContainsString('mailto:support@example.test', $html);
        $this->assertStringContainsString('tel:+912055550101', $html);
        $this->assertStringContainsString('Pune, India', $html);
        $this->assertStringContainsString('https://example.test/help', $html);
        $this->assertStringContainsString('https://example.test/linkedin', $html);
        $this->assertStringContainsString('#linkedin', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString((string)date('Y'), $html);
    }

    /**
     * Optional links are absent when they are not configured.
     */
    public function test_footer_omits_empty_navigation(): void {
        $this->resetAfterTest(true);

        $html = footer_content::render();

        $this->assertStringNotContainsString('iomad-learning-footer-links', $html);
        $this->assertStringContainsString('IOMAD Learning', $html);
    }
}
