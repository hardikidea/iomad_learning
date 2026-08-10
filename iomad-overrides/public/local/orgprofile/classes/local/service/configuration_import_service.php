<?php
// This file is part of Moodle - https://moodle.org/

namespace local_orgprofile\local\service;

use invalid_parameter_exception;
use stdClass;

/**
 * Validates and idempotently imports the maintained organization-profile CSV.
 *
 * @package local_orgprofile
 */
final class configuration_import_service {

    /** @var string[] Required CSV columns. */
    private const REQUIRED_HEADERS = [
        'Record Type', 'Organization Type', 'Organization Shortname', 'Organization Sort Order',
        'Organization Enabled', 'User Types (name|shortname|sort)',
        'Profile Forms (name|shortname|user type)', 'Category', 'Category Shortname',
        'Category Sort Order', 'Category Collapsed', 'Field', 'Field Shortname', 'Field Description',
        'Field Type', 'Core Field Mapping', 'Field Library Required', 'Form Required Override',
        'Readonly Override', 'Visible Override', 'Uniqueness Scope', 'Visible', 'Read Only',
        'Sensitive', 'Field Enabled', 'Default Value', 'Options JSON', 'Validation JSON',
        'Field Sort Order', 'Applies To User Types', 'Applies To Profile Forms',
    ];

    /** Validate/import one CSV file. */
    public function import(string $filepath, bool $apply = false): array {
        if ($filepath === '-') {
            $handle = fopen('php://stdin', 'rb');
        } else {
            $handle = fopen($filepath, 'rb');
        }
        if (!$handle) {
            throw new invalid_parameter_exception('Unable to open configuration CSV.');
        }
        try {
            $rows = $this->read_rows($handle);
        } finally {
            fclose($handle);
        }
        $model = $this->validate_rows($rows);
        if (!$apply) {
            return $this->summary($model, false);
        }
        return $this->apply($model);
    }

    /** Read associative rows from an open CSV stream. */
    private function read_rows($handle): array {
        $headers = fgetcsv($handle, 0, ',', '"', '\\');
        if ($headers === false) {
            throw new invalid_parameter_exception('The configuration CSV is empty.');
        }
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
        $missing = array_diff(self::REQUIRED_HEADERS, $headers);
        if ($missing) {
            throw new invalid_parameter_exception('Missing CSV columns: ' . implode(', ', $missing));
        }
        $rows = [];
        $line = 1;
        while (($values = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $line++;
            if (count($values) === 1 && trim((string) $values[0]) === '') {
                continue;
            }
            if (count($values) !== count($headers)) {
                throw new invalid_parameter_exception("CSV line {$line} has the wrong column count.");
            }
            $row = array_combine($headers, $values);
            $row['_line'] = $line;
            $rows[] = $row;
        }
        return $rows;
    }

    /** Convert rows into a fully resolved, write-ready model. */
    private function validate_rows(array $rows): array {
        $organizations = [];
        $ownershiprules = 0;
        foreach ($rows as $row) {
            if ($row['Record Type'] === 'Ownership Rule') {
                $ownershiprules++;
                continue;
            }
            if ($row['Record Type'] !== 'Organization Setup') {
                continue;
            }
            $shortname = $this->shortname($row['Organization Shortname'], $row['_line']);
            if (isset($organizations[$shortname])) {
                throw new invalid_parameter_exception("Duplicate organization shortname on line {$row['_line']}.");
            }
            $usertypes = [];
            foreach ($this->parts($row['User Types (name|shortname|sort)']) as $definition) {
                $pieces = array_map('trim', explode('|', $definition));
                if (count($pieces) !== 3 || $pieces[0] === '') {
                    throw new invalid_parameter_exception("Invalid user type definition on line {$row['_line']}.");
                }
                $usershortname = $this->shortname($pieces[1], $row['_line']);
                $usertypes[$pieces[0]] = [
                    'name' => $pieces[0],
                    'shortname' => $usershortname,
                    'sortorder' => (int) $pieces[2],
                ];
            }
            $forms = [];
            foreach ($this->parts($row['Profile Forms (name|shortname|user type)']) as $definition) {
                $pieces = array_map('trim', explode('|', $definition));
                if (count($pieces) !== 3 || !isset($usertypes[$pieces[2]])) {
                    throw new invalid_parameter_exception("Invalid profile form definition on line {$row['_line']}.");
                }
                $forms[$pieces[0]] = [
                    'name' => $pieces[0],
                    'shortname' => $this->shortname($pieces[1], $row['_line']),
                    'usertype' => $pieces[2],
                ];
            }
            $organizations[$shortname] = [
                'name' => trim($row['Organization Type']),
                'shortname' => $shortname,
                'sortorder' => (int) $row['Organization Sort Order'],
                'enabled' => $this->boolean($row['Organization Enabled'], $row['_line']),
                'usertypes' => $usertypes,
                'forms' => $forms,
            ];
        }
        if (!$organizations) {
            throw new invalid_parameter_exception('No Organization Setup rows were found.');
        }

        $allforms = [];
        foreach ($organizations as $orgshortname => $organization) {
            foreach ($organization['forms'] as $formname => $form) {
                if (isset($allforms[$formname])) {
                    throw new invalid_parameter_exception("Duplicate profile form name: {$formname}.");
                }
                $allforms[$formname] = $form + ['orgshortname' => $orgshortname];
            }
        }

        $fields = [];
        foreach ($rows as $row) {
            if ($row['Record Type'] !== 'Field Definition + Placement Map') {
                continue;
            }
            $fieldshortname = $this->shortname($row['Field Shortname'], $row['_line']);
            if (isset($fields[$fieldshortname])) {
                throw new invalid_parameter_exception("Duplicate field shortname on line {$row['_line']}.");
            }
            $field = (object) [
                'name' => trim($row['Field']),
                'shortname' => $fieldshortname,
                'description' => trim($row['Field Description']),
                'datatype' => trim($row['Field Type']),
                'corefield' => trim($row['Core Field Mapping']),
                'defaultvalue' => trim($row['Default Value']),
                'required' => $this->boolean($row['Field Library Required'], $row['_line']),
                'uniquescope' => trim($row['Uniqueness Scope']),
                'readonly' => $this->boolean($row['Read Only'], $row['_line']),
                'visible' => $this->boolean($row['Visible'], $row['_line']),
                'sensitive' => $this->boolean($row['Sensitive'], $row['_line']),
                'optionsjson' => trim($row['Options JSON']),
                'validationjson' => trim($row['Validation JSON']),
                'enabled' => $this->boolean($row['Field Enabled'], $row['_line']),
            ];
            $errors = (new validation_service())->configuration_errors($field);
            if ($errors) {
                throw new invalid_parameter_exception(
                    "Invalid field {$fieldshortname} on line {$row['_line']}: " . reset($errors)
                );
            }
            $targetforms = $this->target_forms($row, $organizations, $allforms);
            if (!$targetforms) {
                throw new invalid_parameter_exception("Field {$fieldshortname} has no target forms.");
            }
            $fields[$fieldshortname] = [
                'record' => $field,
                'category' => [
                    'name' => trim($row['Category']),
                    'shortname' => $this->shortname($row['Category Shortname'], $row['_line']),
                    'sortorder' => (int) $row['Category Sort Order'],
                    'collapsed' => $this->boolean($row['Category Collapsed'], $row['_line']),
                ],
                'placement' => [
                    'sortorder' => (int) $row['Field Sort Order'],
                    'requiredoverride' => $this->override($row['Form Required Override'], $row['_line']),
                    'readonlyoverride' => $this->override($row['Readonly Override'], $row['_line']),
                    'visibleoverride' => $this->override($row['Visible Override'], $row['_line']),
                ],
                'forms' => $targetforms,
            ];
        }
        return ['organizations' => $organizations, 'forms' => $allforms, 'fields' => $fields,
            'ownershiprules' => $ownershiprules];
    }

    /** Resolve special shared scopes and explicit form lists. */
    private function target_forms(array $row, array $organizations, array $allforms): array {
        $orgshortname = trim($row['Organization Shortname']);
        if ($orgshortname === 'shared_field_library') {
            return array_keys($allforms);
        }
        if ($orgshortname === 'shared_employment_library') {
            $types = array_flip($this->parts($row['Applies To User Types']));
            return array_keys(array_filter($allforms, static fn(array $form): bool => isset($types[$form['usertype']])));
        }
        if (!isset($organizations[$orgshortname])) {
            throw new invalid_parameter_exception("Unknown organization scope on line {$row['_line']}.");
        }
        $targets = $this->parts($row['Applies To Profile Forms']);
        foreach ($targets as $formname) {
            if (!isset($allforms[$formname]) || $allforms[$formname]['orgshortname'] !== $orgshortname) {
                throw new invalid_parameter_exception("Unknown profile form '{$formname}' on line {$row['_line']}.");
            }
        }
        return $targets;
    }

    /** Apply the resolved model atomically, updating existing records by stable shortname. */
    private function apply(array $model): array {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        $organizations = new organization_service();
        $forms = new form_service();
        $orgids = [];
        $usertypeids = [];
        $formids = [];
        try {
            foreach ($model['organizations'] as $orgshortname => $organization) {
                $existing = $DB->get_record('local_orgprofile_orgtype', ['shortname' => $orgshortname]);
                $orgids[$orgshortname] = $organizations->save_organization_type((object) [
                    'id' => $existing->id ?? 0,
                    'name' => $organization['name'],
                    'shortname' => $orgshortname,
                    'description' => $organization['name'] . ' organization profile configuration.',
                    'sortorder' => $organization['sortorder'],
                    'enabled' => $organization['enabled'],
                ]);
                foreach ($organization['usertypes'] as $typename => $usertype) {
                    $existing = $DB->get_record('local_orgprofile_usertype', [
                        'orgtypeid' => $orgids[$orgshortname],
                        'shortname' => $usertype['shortname'],
                    ]);
                    $usertypeids[$orgshortname][$typename] = $organizations->save_user_type((object) [
                        'id' => $existing->id ?? 0,
                        'orgtypeid' => $orgids[$orgshortname],
                        'name' => $typename,
                        'shortname' => $usertype['shortname'],
                        'sortorder' => $usertype['sortorder'],
                        'enabled' => 1,
                    ]);
                }
            }
            foreach ($model['forms'] as $formname => $form) {
                $existing = $DB->get_record('local_orgprofile_form', ['shortname' => $form['shortname']]);
                $formids[$formname] = $forms->save_form((object) [
                    'id' => $existing->id ?? 0,
                    'orgtypeid' => $orgids[$form['orgshortname']],
                    'usertypeid' => $usertypeids[$form['orgshortname']][$form['usertype']],
                    'name' => $formname,
                    'shortname' => $form['shortname'],
                    'description' => $formname . '.',
                    'enabled' => 1,
                ]);
            }
            foreach ($model['fields'] as $fieldshortname => $definition) {
                $existing = $DB->get_record('local_orgprofile_field', ['shortname' => $fieldshortname]);
                $fieldrecord = clone $definition['record'];
                $fieldrecord->id = $existing->id ?? 0;
                $fieldid = $forms->save_field($fieldrecord);
                foreach ($definition['forms'] as $formname) {
                    $formid = $formids[$formname];
                    $category = $definition['category'];
                    $existingcategory = $DB->get_record('local_orgprofile_category', [
                        'formid' => $formid,
                        'shortname' => $category['shortname'],
                    ]);
                    $categoryid = $forms->save_category((object) ($category + [
                        'id' => $existingcategory->id ?? 0,
                        'formid' => $formid,
                    ]));
                    $existingplacement = $DB->get_record('local_orgprofile_formfield', [
                        'formid' => $formid,
                        'fieldid' => $fieldid,
                    ]);
                    $forms->save_form_field((object) ($definition['placement'] + [
                        'id' => $existingplacement->id ?? 0,
                        'formid' => $formid,
                        'categoryid' => $categoryid,
                        'fieldid' => $fieldid,
                    ]));
                }
            }
            $transaction->allow_commit();
        } catch (\Throwable $exception) {
            $transaction->rollback($exception);
        }
        return $this->summary($model, true);
    }

    /** Return deterministic operation counts. */
    private function summary(array $model, bool $applied): array {
        $usertypes = 0;
        foreach ($model['organizations'] as $organization) {
            $usertypes += count($organization['usertypes']);
        }
        $placements = 0;
        foreach ($model['fields'] as $field) {
            $placements += count($field['forms']);
        }
        return [
            'mode' => $applied ? 'applied' : 'dry-run',
            'organizations' => count($model['organizations']),
            'usertypes' => $usertypes,
            'forms' => count($model['forms']),
            'fields' => count($model['fields']),
            'placements' => $placements,
            'ownershiprules' => $model['ownershiprules'],
        ];
    }

    /** Split semicolon-separated manifest values. */
    private function parts(string $value): array {
        return array_values(array_filter(array_map('trim', explode(';', $value)), static fn(string $part): bool => $part !== ''));
    }

    /** Validate a configuration shortname. */
    private function shortname(string $value, int $line): string {
        $value = \core_text::strtolower(trim($value));
        if (!preg_match('/^[a-z0-9_]+$/', $value)) {
            throw new invalid_parameter_exception("Invalid shortname on line {$line}.");
        }
        return $value;
    }

    /** Parse manifest Yes/No. */
    private function boolean(string $value, int $line): int {
        return match (\core_text::strtolower(trim($value))) {
            'yes', '1', 'true' => 1,
            'no', '0', 'false', '' => 0,
            default => throw new invalid_parameter_exception("Invalid boolean on line {$line}."),
        };
    }

    /** Parse placement tri-state to service-compatible value. */
    private function override(string $value, int $line): string {
        return match (\core_text::strtolower(trim($value))) {
            'yes', '1', 'true' => '1',
            'no', '0', 'false' => '0',
            'inherit', '' => '',
            default => throw new invalid_parameter_exception("Invalid placement override on line {$line}."),
        };
    }
}
