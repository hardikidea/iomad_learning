<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster;

use local_tenantmaster\local\import_service;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/tenantmaster_testcase.php');

/**
 * Immutable UI package pipeline tests.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class import_service_test extends tenantmaster_testcase {
    /**
     * A checksummed package plans, applies and resumes idempotently.
     *
     * @covers \local_tenantmaster\local\import_service
     */
    public function test_package_apply_is_idempotent(): void {
        global $CFG, $DB;

        $this->resetAfterTest();
        $tenant = $this->create_tenant();
        $csv = "mastertype,externalid,code,name,active\nsubject,SUBJECT_ROBOTICS,ROBOTICS,Robotics,1\n";
        $manifest = [
            'schema_version' => '1.0',
            'tenant' => ['trust_code' => $tenant->trustcode],
            'files' => [[
                'path' => 'academic_masters.csv',
                'entity' => 'academic_masters',
                'rows' => 1,
                'sha256' => hash('sha256', $csv),
            ]],
        ];
        $path = tempnam($CFG->tempdir, 'tenantmaster-test-');
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
        $zip->addFromString('academic_masters.csv', $csv);
        $zip->close();
        $content = file_get_contents($path);
        unlink($path);

        $service = new import_service();
        $batch = $service->inspect($tenant, 'package.zip', $content, 'merge');
        $applied = $service->apply($tenant, (int)$batch->id);
        $samebatch = $service->inspect($tenant, 'renamed.zip', $content, 'merge');

        $this->assertSame('completed', $applied->status);
        $this->assertSame((int)$batch->id, (int)$samebatch->id);
        $this->assertSame(1, $DB->count_records('local_tenantmaster_master', [
            'tenantid' => $tenant->id,
            'mastertype' => 'subject',
            'externalid' => 'SUBJECT_ROBOTICS',
        ]));
    }
}
