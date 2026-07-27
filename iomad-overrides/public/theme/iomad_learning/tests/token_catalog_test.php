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
        $this->assertSame('none', $definitions['contentmaxwidth']['default']);
        $this->assertSame('none', $definitions['coursecontentmaxwidth']['default']);
        $this->assertSame('1', $definitions['shownavigationicons']['default']);
        $this->assertSame('#172033', $definitions['navbarbackground']['default']);
        $this->assertSame('#f6f8fb', $definitions['navbartext']['default']);
        $this->assertSame('#8a3145', $definitions['primarycolor']['default']);
        $this->assertSame('#4f5b6b', $definitions['secondarycolor']['default']);
        $this->assertSame('1.75', $definitions['iconstrokewidth']['default']);
        $this->assertSame('1.125rem', $definitions['iconsize']['default']);
    }

    /**
     * Invalid values fail to a safe typed default.
     */
    public function test_invalid_values_fall_back_to_defaults(): void {
        $this->resetAfterTest(true);

        $this->assertSame('#8a3145', token_catalog::normalize('primarycolor', 'red;display:none'));
        $this->assertSame('1rem', token_catalog::normalize('basefontsize', 'calc(1px);color:red'));
        $this->assertSame('0', token_catalog::normalize('disablemotion', 'yes'));
        $this->assertSame('none', token_catalog::normalize('contentmaxwidth', 'calc(100% - 1px)'));
    }

    /**
     * Header colours cannot become indistinguishable from their background.
     */
    public function test_header_colours_are_contrast_safe(): void {
        $this->assertSame('#172033', token_catalog::ensure_contrast('#ffffff', '#ffffff'));
        $this->assertSame('#cbd5e1', token_catalog::ensure_contrast('#cbd5e1', '#172033', 3.0));
        $this->assertGreaterThanOrEqual(
            4.5,
            token_catalog::contrast_ratio(
                token_catalog::ensure_contrast('#172033', '#172033'),
                '#172033'
            )
        );
    }
}
