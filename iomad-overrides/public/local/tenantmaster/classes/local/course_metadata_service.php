<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\local;

use core_course\customfield\course_handler;
use core_customfield\field_controller;

/**
 * Project Tenant Master course identity into native Moodle custom fields.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_metadata_service {
    /** @var array<string, string> Native shortname to language string. */
    public const FIELDS = [
        'tm_company_code' => 'coursefield_companycode',
        'tm_institution_type' => 'coursefield_institutiontype',
        'tm_academic_year' => 'coursefield_academicyear',
        'tm_board' => 'coursefield_board',
        'tm_medium' => 'coursefield_medium',
        'tm_grade' => 'coursefield_grade',
        'tm_stream' => 'coursefield_stream',
        'tm_programme' => 'coursefield_programme',
        'tm_semester' => 'coursefield_semester',
        'tm_specialisation' => 'coursefield_specialisation',
        'tm_subject' => 'coursefield_subject',
        'tm_credit_value' => 'coursefield_creditvalue',
        'tm_source_external_id' => 'coursefield_sourceexternalid',
    ];

    /** @var array<string, string> Master type to native custom-field shortname. */
    private const MASTER_FIELDS = [
        'board' => 'tm_board',
        'medium' => 'tm_medium',
        'grade' => 'tm_grade',
        'stream' => 'tm_stream',
        'programme' => 'tm_programme',
        'semester' => 'tm_semester',
        'specialisation' => 'tm_specialisation',
        'subject' => 'tm_subject',
    ];

    /**
     * Ensure the shared native course custom-field definitions exist.
     *
     * Existing definitions are never renamed, deleted or reconfigured.
     *
     * @return array<string, field_controller>
     */
    public function ensure_definitions(): array {
        $handler = course_handler::create();
        $category = null;
        foreach ($handler->get_categories_with_fields() as $candidate) {
            if ((string)$candidate->get('name') === get_string('coursefieldcategory', 'local_tenantmaster')) {
                $category = $candidate;
                break;
            }
        }
        if (!$category) {
            $categoryid = $handler->create_category(get_string('coursefieldcategory', 'local_tenantmaster'));
            foreach ($handler->get_categories_with_fields() as $candidate) {
                if ((int)$candidate->get('id') === $categoryid) {
                    $category = $candidate;
                    break;
                }
            }
        }
        if (!$category) {
            throw new \coding_exception('Unable to create the Tenant Master course custom-field category.');
        }

        $fields = [];
        foreach ($handler->get_fields() as $field) {
            $shortname = (string)$field->get('shortname');
            if (array_key_exists($shortname, self::FIELDS)) {
                $fields[$shortname] = $field;
            }
        }
        $sortorder = count($category->get_fields());
        foreach (self::FIELDS as $shortname => $stringkey) {
            if (isset($fields[$shortname])) {
                continue;
            }
            $field = field_controller::create(0, (object)['type' => 'text'], $category);
            $handler->save_field_configuration($field, (object)[
                'name' => get_string($stringkey, 'local_tenantmaster'),
                'shortname' => $shortname,
                'description' => get_string('coursefielddescription', 'local_tenantmaster'),
                'descriptionformat' => FORMAT_PLAIN,
                'type' => 'text',
                'sortorder' => $sortorder++,
                'configdata' => [
                    'required' => 0,
                    'uniquevalues' => 0,
                    'locked' => 1,
                    'visibility' => course_handler::VISIBLETOTEACHERS,
                    'defaultvalue' => '',
                    'defaultvalueformat' => FORMAT_PLAIN,
                    'displaysize' => 30,
                    'maxlength' => 255,
                    'ispassword' => 0,
                    'link' => '',
                    'linktarget' => '',
                ],
            ]);
            $fields[$shortname] = $field;
        }
        return $fields;
    }

    /**
     * Save and read back native course custom-field values.
     *
     * @param object $tenant Tenant.
     * @param object $master Course-producing master.
     * @param int $courseid Native course ID.
     * @return array<string, string>
     */
    public function project(object $tenant, object $master, int $courseid): array {
        $fields = $this->ensure_definitions();
        $values = $this->metadata($tenant, $master);
        $contextid = \context_course::instance($courseid)->id;
        $handler = course_handler::create();
        $existing = $handler->get_instance_data($courseid, true);

        foreach ($fields as $shortname => $field) {
            $data = $existing[(int)$field->get('id')] ?? \core_customfield\data_controller::create(
                0,
                (object)['instanceid' => $courseid],
                $field,
            );
            if (!(int)$data->get('id')) {
                $data->set('contextid', $contextid);
            }
            $element = $data->get_form_element_name();
            $data->instance_form_save((object)[$element => $values[$shortname] ?? '']);
        }

        $actual = [];
        foreach ($handler->get_instance_data($courseid, true) as $data) {
            $shortname = (string)$data->get_field()->get('shortname');
            if (array_key_exists($shortname, self::FIELDS)) {
                $actual[$shortname] = (string)$data->get_value();
            }
        }
        foreach ($values as $shortname => $value) {
            if (($actual[$shortname] ?? null) !== $value) {
                throw new \moodle_exception(
                    'projectionreadbackfailed',
                    'local_tenantmaster',
                    '',
                    'core_course/customfield:' . $shortname,
                );
            }
        }
        return $actual;
    }

    /**
     * Build stable native values from the master hierarchy.
     *
     * @return array<string, string>
     */
    private function metadata(object $tenant, object $master): array {
        global $DB;

        $values = array_fill_keys(array_keys(self::FIELDS), '');
        $values['tm_company_code'] = (string)$tenant->trustcode;
        $values['tm_institution_type'] = (string)$tenant->tenanttype;
        $values['tm_source_external_id'] = (string)$master->externalid;

        $yearid = (int)($master->acadyearid ?? 0);
        if ($yearid > 0) {
            $year = $DB->get_record('local_tenantmaster_acadyear', [
                'id' => $yearid,
                'tenantid' => $tenant->id,
            ], '*', MUST_EXIST);
            $values['tm_academic_year'] = (string)$year->code;
        }

        $current = $master;
        $visited = [];
        while ($current && !isset($visited[(int)$current->id])) {
            $visited[(int)$current->id] = true;
            $type = (string)$current->mastertype;
            if (isset(self::MASTER_FIELDS[$type]) && $values[self::MASTER_FIELDS[$type]] === '') {
                $values[self::MASTER_FIELDS[$type]] = $this->master_code($tenant, $current);
            }
            $payload = json::decode_object((string)$current->payloadjson);
            if ($values['tm_credit_value'] === '' && isset($payload['credits'])) {
                $values['tm_credit_value'] = (string)$payload['credits'];
            }
            $parentid = (int)$current->parentid;
            $current = $parentid > 0
                ? $DB->get_record('local_tenantmaster_master', [
                    'id' => $parentid,
                    'tenantid' => $tenant->id,
                ])
                : null;
        }
        return $values;
    }

    /**
     * Prefer the shared source definition code for year-scoped copies.
     */
    private function master_code(object $tenant, object $master): string {
        global $DB;

        $payload = json::decode_object((string)$master->payloadjson);
        $sourceid = (int)($payload['_tenantmaster_source_masterid'] ?? 0);
        if ($sourceid > 0) {
            $source = $DB->get_record('local_tenantmaster_master', [
                'id' => $sourceid,
                'tenantid' => $tenant->id,
            ]);
            if ($source) {
                return (string)$source->code;
            }
        }
        return (string)$master->code;
    }
}
