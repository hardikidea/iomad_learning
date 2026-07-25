<?php
// This file is part of Moodle - http://moodle.org/

namespace format_iomadvideo;

/**
 * Course-format option tests.
 *
 * @package    format_iomadvideo
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_iomadvideo
 */
final class format_test extends \advanced_testcase {
    /**
     * The format exposes six maintained layouts and safe defaults.
     */
    public function test_format_options(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['format' => 'iomadvideo']);
        $format = course_get_format($course);

        $this->assertInstanceOf(\format_iomadvideo::class, $format);
        $this->assertFalse($format->uses_indentation());
        $this->assertTrue($format->uses_sections());

        $options = $format->course_format_options(true);
        $layouts = $options['videolayout']['element_attributes'][0];
        $this->assertCount(6, $layouts);
        $this->assertSame('cinema', $options['videolayout']['default']);
        $this->assertSame(0, $options['autoadvance']['default']);
    }
}
