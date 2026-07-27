<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster;

use local_tenantmaster\local\import_service;
use local_tenantmaster\local\import_schema;
use local_tenantmaster\local\import_template_service;

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

    /**
     * Downloaded starter packages are tenant-matched, safe no-op imports.
     *
     * @covers \local_tenantmaster\local\import_template_service
     * @covers \local_tenantmaster\local\import_schema
     */
    public function test_download_template_is_a_valid_empty_package(): void {
        global $CFG;

        $this->resetAfterTest();
        $tenant = $this->create_tenant();
        $templateservice = new import_template_service();
        $content = $templateservice->build_zip($tenant);
        $path = tempnam($CFG->tempdir, 'tenantmaster-template-test-');
        file_put_contents($path, $content);

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path));
        $manifest = json_decode(
            (string)$zip->getFromName('manifest.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $this->assertSame(import_schema::VERSION, $manifest['schema_version']);
        $this->assertSame($tenant->trustcode, $manifest['tenant']['trust_code']);
        $this->assertCount(count(import_schema::entities()), $manifest['files']);
        foreach ($manifest['files'] as $file) {
            $csv = (string)$zip->getFromName($file['path']);
            $this->assertSame(0, $file['rows']);
            $this->assertSame(hash('sha256', $csv), $file['sha256']);
            $this->assertNotFalse($zip->locateName('examples/' . $file['path']));
        }
        $this->assertNotFalse($zip->locateName('field-guide.csv'));
        $this->assertNotFalse($zip->locateName('README.txt'));
        $zip->close();
        unlink($path);

        $batch = (new import_service())->inspect(
            $tenant,
            $templateservice->zip_filename($tenant),
            $content,
            'merge',
        );
        $this->assertSame('planned', $batch->status);
        $this->assertSame(0, (int)$batch->rowcount);
        $this->assertSame(0, (int)$batch->errorcount);
    }
}
