<?php
// This file is part of IOMAD - http://www.iomad.org/

namespace local_iomadpagebuilder;

defined('MOODLE_INTERNAL') || die();

final class catalog_test extends \advanced_testcase {
    public function test_catalog_has_exactly_requested_counts_and_unique_keys(): void {
        $presets = catalog::presets();
        $templates = catalog::templates();

        $this->assertCount(140, $presets);
        $this->assertCount(140, array_unique(array_keys($presets)));
        $this->assertCount(30, $templates);
        $this->assertCount(30, array_unique(array_keys($templates)));
    }

    public function test_every_template_validates(): void {
        $validator = new definition_validator();
        foreach (catalog::templates() as $template) {
            $normalised = $validator->validate($template['definition']);
            $this->assertNotEmpty($normalised['sections']);
        }
    }

    public function test_validator_rejects_scripts_and_insecure_urls(): void {
        $validator = new definition_validator();
        $definition = catalog::template('school_home');
        $definition['sections'][0]['body'] = '<script>alert(1)</script><p>Allowed</p>';
        $definition['sections'][0]['primarylabel'] = 'Continue';
        $definition['sections'][0]['primaryurl'] = 'http://insecure.example.test/';

        $this->expectException(\invalid_parameter_exception::class);
        $validator->validate($definition);
    }

    public function test_validator_cleans_executable_markup(): void {
        $validator = new definition_validator();
        $definition = catalog::template('school_home');
        $definition['sections'][0]['body'] = '<script>alert(1)</script><p>Allowed</p>';
        $normalised = $validator->validate($definition);

        $this->assertStringNotContainsString('<script', $normalised['sections'][0]['body']);
        $this->assertStringContainsString('Allowed', $normalised['sections'][0]['body']);
    }
}
