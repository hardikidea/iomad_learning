<?php
// This file is part of IOMAD - http://www.iomad.org/

namespace local_aicoursecreator;

defined('MOODLE_INTERNAL') || die();

final class course_schema_validator_test extends \advanced_testcase {
    public function test_sample_definition_is_normalised(): void {
        $definition = (new course_schema_validator())->validate(sample_definition::create('schema'));

        $this->assertSame(1, $definition['schema_version']);
        $this->assertCount(2, $definition['sections']);
        $this->assertSame('multichoice', $definition['sections'][1]['quizzes'][0]['questions'][0]['type']);
        $this->assertSame('https://www.cisa.gov/secure-our-world', $definition['sections'][0]['items'][1]['url']);
    }

    public function test_executable_markup_is_removed(): void {
        $definition = sample_definition::create('markup');
        $definition['sections'][0]['items'][0]['content'] = '<p>Safe</p><script>alert(1)</script>';
        $normalised = (new course_schema_validator())->validate($definition);

        $this->assertStringContainsString('<p>Safe</p>', $normalised['sections'][0]['items'][0]['content']);
        $this->assertStringNotContainsString('<script', $normalised['sections'][0]['items'][0]['content']);
    }

    public function test_external_urls_must_use_https(): void {
        $definition = sample_definition::create('url');
        $definition['sections'][0]['items'][1]['url'] = 'http://example.test/resource';

        $this->expectException(\invalid_parameter_exception::class);
        (new course_schema_validator())->validate($definition);
    }

    public function test_definition_ids_are_unique(): void {
        $definition = sample_definition::create('ids');
        $definition['sections'][1]['id'] = $definition['sections'][0]['id'];

        $this->expectException(\invalid_parameter_exception::class);
        (new course_schema_validator())->validate($definition);
    }
}
