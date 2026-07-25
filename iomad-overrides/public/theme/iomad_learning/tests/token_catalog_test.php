<?php
// This file is part of Moodle - http://moodle.org/

namespace theme_iomad_learning;

use theme_iomad_learning\local\token_catalog;

/**
 * Theme design-token contract tests.
 *
 * @package    theme_iomad_learning
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \theme_iomad_learning\local\token_catalog
 */
final class token_catalog_test extends \advanced_testcase {
    /**
     * At least 150 typed options are available and defaults are valid.
     */
    public function test_catalog_has_complete_typed_defaults(): void {
        $this->resetAfterTest(true);
        $definitions = token_catalog::definitions();

        $this->assertGreaterThanOrEqual(150, count($definitions));
        $this->assertCount(count(array_unique(array_keys($definitions))), $definitions);
        foreach ($definitions as $key => $definition) {
            $this->assertArrayHasKey($definition['group'], token_catalog::groups());
            $this->assertSame($definition['default'], token_catalog::normalize($key, $definition['default']));
            $this->assertStringStartsWith('--iomad-learning-', token_catalog::css_name($key));
        }
    }

    /**
     * Invalid values fail to a safe typed default.
     */
    public function test_invalid_values_fall_back_to_defaults(): void {
        $this->resetAfterTest(true);

        $this->assertSame('#2454a6', token_catalog::normalize('primarycolor', 'red;display:none'));
        $this->assertSame('1rem', token_catalog::normalize('basefontsize', 'calc(1px);color:red'));
        $this->assertSame('0', token_catalog::normalize('disablemotion', 'yes'));
    }
}
