<?php
// This file is part of Moodle - http://moodle.org/

namespace local_iomad_scorm_gen;

/**
 * SCORM package validation tests.
 *
 * @package local_iomad_scorm_gen
 * @covers \local_iomad_scorm_gen\package_builder
 * @covers \local_iomad_scorm_gen\package_definition
 */
final class package_builder_test extends \basic_testcase {
    /**
     * Package contains a standard manifest and offline core commit runtime.
     */
    public function test_builds_self_contained_package(): void {
        $target = make_request_directory() . '/lesson.zip';
        $result = (new package_builder())->build([
            'idnumber' => 'demo:scorm:lesson',
            'title' => 'Demo lesson',
            'sections' => [
                ['id' => 'intro', 'title' => 'Introduction', 'body' => 'Sanitized learning content.'],
                ['id' => 'review', 'title' => 'Review', 'body' => 'Review the lesson.'],
            ],
        ], $target);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($target) === true);
        $manifest = $zip->getFromName('imsmanifest.xml');
        $runtime = $zip->getFromName('assets/runtime.js');
        $this->assertStringContainsString('adlcp:scormtype="sco"', $manifest);
        $this->assertStringContainsString("LMSSetValue('cmi.core.lesson_location'", $runtime);
        $this->assertStringContainsString("LMSCommit('')", $runtime);
        $this->assertStringContainsString('localStorage', $runtime);
        $this->assertStringNotContainsString('fetch(', $runtime);
        $this->assertSame(2, $result['sections']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $result['sha256']);
        $zip->close();
    }

    /**
     * Duplicate checkpoint IDs are rejected.
     */
    public function test_rejects_duplicate_checkpoint_ids(): void {
        $this->expectException(\invalid_parameter_exception::class);
        (new package_definition())->validate([
            'idnumber' => 'demo:duplicate',
            'title' => 'Duplicate',
            'sections' => [
                ['id' => 'same', 'title' => 'One', 'body' => 'One'],
                ['id' => 'same', 'title' => 'Two', 'body' => 'Two'],
            ],
        ]);
    }
}
