<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_tenantform;

use mod_tenantform\local\definition_validator;
use mod_tenantform\local\template_catalog;

/**
 * Template catalog and schema validation tests.
 *
 * @package    mod_tenantform
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_tenantform\local\definition_validator
 * @covers     \mod_tenantform\local\template_catalog
 */
final class template_validator_test extends \advanced_testcase {
    /**
     * Every maintained template is valid and non-empty.
     */
    public function test_all_templates_are_valid(): void {
        $validator = new definition_validator();
        $this->assertCount(9, template_catalog::names());
        foreach (array_keys(template_catalog::names()) as $key) {
            $definition = $validator->validate(template_catalog::definition($key));
            $this->assertSame(1, $definition['schema_version']);
            $this->assertNotEmpty($definition['pages']);
        }
    }

    /**
     * Conditions may only reference an earlier field.
     */
    public function test_forward_condition_reference_is_rejected(): void {
        $this->expectException(\invalid_parameter_exception::class);
        (new definition_validator())->validate([
            'schema_version' => 1,
            'pages' => [[
                'id' => 'one',
                'title' => 'One',
                'fields' => [[
                    'id' => 'dependent',
                    'type' => 'text',
                    'label' => 'Dependent',
                    'condition' => [
                        'field' => 'future',
                        'operator' => 'equals',
                        'value' => 'Yes',
                    ],
                ], [
                    'id' => 'future',
                    'type' => 'text',
                    'label' => 'Future',
                ]],
            ]],
        ]);
    }

    /**
     * Duplicate option values are rejected.
     */
    public function test_duplicate_options_are_rejected(): void {
        $this->expectException(\invalid_parameter_exception::class);
        (new definition_validator())->validate([
            'schema_version' => 1,
            'pages' => [[
                'id' => 'one',
                'title' => 'One',
                'fields' => [[
                    'id' => 'choice',
                    'type' => 'select',
                    'label' => 'Choice',
                    'options' => ['Same', 'Same'],
                ]],
            ]],
        ]);
    }
}
