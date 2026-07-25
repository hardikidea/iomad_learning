<?php
// This file is part of IOMAD - http://www.iomad.org/

namespace local_aicoursecreator;

defined('MOODLE_INTERNAL') || die();

final class scorm_exporter_test extends \advanced_testcase {
    public function test_export_contains_scorm_manifest_and_static_learning_assets(): void {
        global $CFG;

        $pathname = make_temp_directory('aicoursecreator_test') . '/sample-scorm.zip';
        (new scorm_exporter())->export_to_path(sample_definition::create('scorm'), $pathname);
        $files = get_file_packer('application/zip')->list_files($pathname);
        $names = array_column($files, 'pathname');

        $this->assertFileExists($pathname);
        $this->assertContains('imsmanifest.xml', $names);
        $this->assertContains('scorm-api.js', $names);
        $this->assertContains('content/section-02-quiz-01.html', $names);
        $this->assertGreaterThan(1000, filesize($pathname));
    }
}
