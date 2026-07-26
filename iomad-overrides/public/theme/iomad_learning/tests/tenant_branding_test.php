<?php
// This file is part of Moodle - http://moodle.org/

namespace theme_iomad_learning;

use theme_iomad_learning\local\tenant_branding;

/**
 * Tenant runtime branding contract tests.
 *
 * @package    theme_iomad_learning
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \theme_iomad_learning\local\tenant_branding
 */
final class tenant_branding_test extends \advanced_testcase {
    /**
     * Valid IOMAD company link colours control links and navigation icons.
     */
    public function test_company_link_colour_builds_tenant_variables(): void {
        $company = (object)[
            'linkcolor' => '#0F7B6C',
            'customcss' => '.tenant-contact { display: block; }',
        ];

        $css = tenant_branding::build_css($company);

        $this->assertStringContainsString('--iomad-learning-linkcolor: #0f7b6c', $css);
        $this->assertStringContainsString('--iomad-learning-navigationiconcolor: #0f7b6c', $css);
        $this->assertStringContainsString('--iomad-learning-navigationiconactive: #0c6559', $css);
        $this->assertStringContainsString('.tenant-contact { display: block; }', $css);
    }

    /**
     * Invalid colours cannot inject CSS and custom CSS cannot close the style.
     */
    public function test_invalid_colour_and_style_termination_are_contained(): void {
        $company = (object)[
            'linkcolor' => 'red; display: none',
            'customcss' => '</style><script>alert(1)</script><style>.safe { color: red; }',
        ];

        $css = tenant_branding::build_css($company);

        $this->assertStringNotContainsString('--iomad-learning-linkcolor', $css);
        $this->assertStringNotContainsString('</style', strtolower($css));
        $this->assertStringNotContainsString('<style', strtolower($css));
        $this->assertStringContainsString('.safe { color: red; }', $css);
    }
}
