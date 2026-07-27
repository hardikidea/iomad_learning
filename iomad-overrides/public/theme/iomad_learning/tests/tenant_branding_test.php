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
     * Native IOMAD company colours control every supported theme surface.
     */
    public function test_company_colours_build_tenant_variables(): void {
        $company = (object)[
            'bgcolor_header' => '#172033',
            'bgcolor_content' => '#F8FAFC',
            'maincolor' => '#2454A6',
            'headingcolor' => '#243047',
            'linkcolor' => '#0F7B6C',
            'customcss' => '.tenant-contact { display: block; }',
        ];

        $css = tenant_branding::build_css($company);

        $this->assertStringContainsString('--iomad-learning-navbarbackground: #172033', $css);
        $this->assertStringContainsString('--iomad-learning-navbartext: #ffffff', $css);
        $this->assertStringContainsString('--iomad-learning-pagebackground: #f8fafc', $css);
        $this->assertStringContainsString('--iomad-learning-primarycolor: #2454a6', $css);
        $this->assertStringContainsString('--iomad-learning-primarycontrast: #ffffff', $css);
        $this->assertStringContainsString('--iomad-learning-headingcolor: #243047', $css);
        $this->assertStringContainsString('--iomad-learning-linkcolor: #0f7b6c', $css);
        $this->assertStringContainsString('--iomad-learning-navigationiconcolor: #0f7b6c', $css);
        $this->assertStringContainsString('--iomad-learning-navigationiconactive: #2454a6', $css);
        $this->assertStringContainsString('.tenant-contact { display: block; }', $css);
    }

    /**
     * Light company headers receive a dark accessible foreground.
     */
    public function test_light_header_uses_dark_foreground(): void {
        $css = tenant_branding::build_css((object)['bgcolor_header' => '#ffffff']);

        $this->assertStringContainsString('--iomad-learning-navbartext: #172033', $css);
        $this->assertStringContainsString('--iomad-learning-headericoncolor: #172033', $css);
    }

    /**
     * Light tenant action and text colours receive accessible foregrounds.
     */
    public function test_light_tenant_colours_are_made_readable(): void {
        $css = tenant_branding::build_css((object)[
            'maincolor' => '#fef3c7',
            'headingcolor' => '#d1d5db',
            'linkcolor' => '#93c5fd',
        ]);

        $this->assertStringContainsString('--iomad-learning-primarycontrast: #172033', $css);
        $this->assertStringNotContainsString('--iomad-learning-headingcolor: #d1d5db', $css);
        $this->assertStringNotContainsString('--iomad-learning-linkcolor: #93c5fd', $css);
    }

    /**
     * Invalid colours cannot inject CSS and custom CSS cannot close the style.
     */
    public function test_invalid_colour_and_style_termination_are_contained(): void {
        $company = (object)[
            'linkcolor' => 'red; display: none',
            'bgcolor_header' => 'url(javascript:alert(1))',
            'customcss' => '</style><script>alert(1)</script><style>.safe { color: red; }',
        ];

        $css = tenant_branding::build_css($company);

        $this->assertStringNotContainsString('--iomad-learning-linkcolor', $css);
        $this->assertStringNotContainsString('--iomad-learning-navbarbackground', $css);
        $this->assertStringNotContainsString('</style', strtolower($css));
        $this->assertStringNotContainsString('<style', strtolower($css));
        $this->assertStringContainsString('.safe { color: red; }', $css);
    }
}
