<?php
// This file is part of Moodle - https://moodle.org/

/**
 * Plan or provision the reviewed organization structure for an IOMAD company.
 *
 * The host-side wrapper streams this file into the IOMAD container. All writes
 * use the Moodle course-category and IOMAD department APIs with stable
 * business identifiers.
 *
 * @package local_iomad
 */

declare(strict_types=1);

define('CLI_SCRIPT', true);

require('/var/www/iomad/config.php');

\core\session\manager::set_user(get_admin());

/**
 * Read a required environment variable.
 *
 * @param string $name Variable name.
 * @return string Trimmed value.
 */
function category_setup_env(string $name): string {
    $value = getenv($name);
    if ($value === false || trim($value) === '') {
        throw new invalid_parameter_exception("Missing required environment option: {$name}");
    }
    return trim($value);
}

/**
 * Return whether a category is below a company category root.
 *
 * @param core_course_category $category Category being checked.
 * @param core_course_category $root Company category root.
 * @return bool
 */
function category_setup_is_descendant(
    core_course_category $category,
    core_course_category $root
): bool {
    return str_starts_with($category->path . '/', $root->path . '/');
}

/**
 * Normalize text before deciding whether a managed value changed.
 *
 * @param string $value Text to normalize.
 * @return string
 */
function category_setup_normalize_text(string $value): string {
    return trim(str_replace(["\r\n", "\r"], "\n", $value));
}

/**
 * Build a stable IOMAD department shortname from a CSV category short code.
 *
 * @param string $sourceidnumber CSV category short code.
 * @return string
 */
function category_setup_department_shortname(string $sourceidnumber): string {
    return 'ORGDEP_' . str_replace('-', '_', $sourceidnumber);
}

/**
 * Resolve a CSV parent by display name and stable short-code ancestry.
 *
 * Parent names are not globally unique in the supplied format. A short-code
 * prefix wins when exactly one candidate matches; otherwise the parent name
 * must identify exactly one previously declared row.
 *
 * @param array $definition Current row definition.
 * @param array $candidates Previously declared rows with the requested name.
 * @param int $linenumber CSV line number.
 * @return array Parent definition.
 */
function category_setup_resolve_parent(
    array $definition,
    array $candidates,
    int $linenumber
): array {
    if (!$candidates) {
        throw new invalid_parameter_exception(
            "Unknown or forward parent '{$definition['parentname']}' on CSV line {$linenumber}."
        );
    }

    $prefixmatches = array_values(array_filter(
        $candidates,
        static fn(array $candidate): bool => str_starts_with(
            $definition['sourceidnumber'],
            $candidate['sourceidnumber'] . '-'
        )
    ));

    if (count($prefixmatches) === 1) {
        return $prefixmatches[0];
    }
    if (count($candidates) === 1) {
        return $candidates[0];
    }

    throw new invalid_parameter_exception(
        "Ambiguous parent '{$definition['parentname']}' on CSV line {$linenumber}; " .
        'use short codes that identify a single parent branch.'
    );
}

$companyshortname = category_setup_env('CATEGORY_SETUP_COMPANY');
$organizationoption = category_setup_env('CATEGORY_SETUP_ORGANIZATION');
$applyoption = category_setup_env('CATEGORY_SETUP_APPLY');

if (!preg_match('/^[A-Za-z0-9_-]+$/', $companyshortname)) {
    throw new invalid_parameter_exception('Invalid IOMAD company shortname.');
}
if (!in_array($applyoption, ['0', '1'], true)) {
    throw new invalid_parameter_exception('CATEGORY_SETUP_APPLY must be 0 or 1.');
}
$visible = 1;
$apply = $applyoption === '1';

// IOMAD 5.1 has no public lookup-by-shortname method. This is a read-only DML
// lookup; company and category changes continue to use their owning APIs.
$companyrecord = $DB->get_record(
    'local_iomad_companies',
    ['shortname' => $companyshortname],
    'id,name,shortname,coursecategoryid',
    IGNORE_MISSING
);
if ($companyrecord === false) {
    throw new RuntimeException(
        "No IOMAD company exists with shortname '{$companyshortname}'. " .
        'Use the exact company shortname configured in IOMAD; do not use a documentation example or display name.'
    );
}
if (empty($companyrecord->coursecategoryid)) {
    throw new RuntimeException('The IOMAD company does not have a course-category root.');
}
if (!$DB->record_exists('course_categories', ['id' => $companyrecord->coursecategoryid])) {
    $candidatecategories = $DB->get_records(
        'course_categories',
        ['idnumber' => $companyrecord->shortname, 'parent' => 0],
        'id',
        'id,name,idnumber',
        0,
        2
    );
    $candidatehint = '';
    if (count($candidatecategories) === 1) {
        $candidate = reset($candidatecategories);
        $candidatehint = " Top-level category ID {$candidate->id} ({$candidate->name}) has the matching " .
            "idnumber '{$candidate->idnumber}', but it is not linked to the company. This command will not " .
            'relink it automatically.';
    }
    throw new RuntimeException(
        "The IOMAD company {$companyrecord->shortname} references missing course-category root ID " .
        "{$companyrecord->coursecategoryid}.{$candidatehint} " .
        'Repair or recreate the company before setting up categories.'
    );
}
$companyroot = core_course_category::get(
    (int) $companyrecord->coursecategoryid,
    MUST_EXIST,
    true
);

$csvpath = '/var/www/institution-packs/categories/moodle_iomad_category_grab_format.csv';
$handle = fopen($csvpath, 'rb');
if ($handle === false) {
    throw new RuntimeException("Unable to read category CSV: {$csvpath}");
}

$expectedheaders = [
    'TOP PARENT',
    'PARENT-CATEGORY',
    'CATEGORY-NAME',
    'CATEGORY-ID-NUMBER (SHORT-CODE)',
    'DESCRIPTION',
];
$headers = fgetcsv($handle, escape: '');
if (isset($headers[0])) {
    $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
}
if ($headers !== $expectedheaders) {
    fclose($handle);
    throw new invalid_parameter_exception(
        'The category CSV headers do not exactly match the reviewed five-column schema.'
    );
}

$companyprefix = strtoupper($companyrecord->shortname);
$definitions = [];
$seenbyname = [];
$rootcount = 0;
$linenumber = 1;

while (($row = fgetcsv($handle, escape: '')) !== false) {
    $linenumber++;
    if (count($row) !== count($expectedheaders)) {
        fclose($handle);
        throw new invalid_parameter_exception("Invalid column count on CSV line {$linenumber}.");
    }

    $data = array_combine(
        $expectedheaders,
        array_map(static fn($value): string => trim((string) $value), $row)
    );
    foreach ($expectedheaders as $header) {
        if ($header !== 'PARENT-CATEGORY' && $data[$header] === '') {
            fclose($handle);
            throw new invalid_parameter_exception("Missing {$header} on CSV line {$linenumber}.");
        }
    }

    $sourceidnumber = $data['CATEGORY-ID-NUMBER (SHORT-CODE)'];
    if (!preg_match('/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/', $sourceidnumber)) {
        fclose($handle);
        throw new invalid_parameter_exception("Invalid category short code on CSV line {$linenumber}.");
    }
    if (isset($definitions[$sourceidnumber])) {
        fclose($handle);
        throw new invalid_parameter_exception("Duplicate category short code on CSV line {$linenumber}.");
    }

    $name = $data['CATEGORY-NAME'];
    $topparent = $data['TOP PARENT'];
    if (core_text::strlen($name) > 255 || core_text::strlen($topparent) > 255) {
        fclose($handle);
        throw new invalid_parameter_exception("Category name exceeds 255 characters on CSV line {$linenumber}.");
    }

    $managedidnumber = "{$companyprefix}-{$sourceidnumber}";
    if (core_text::strlen($managedidnumber) > 100) {
        fclose($handle);
        throw new invalid_parameter_exception(
            "Company-prefixed category idnumber exceeds 100 characters on CSV line {$linenumber}."
        );
    }

    $definition = [
        'sourceidnumber' => $sourceidnumber,
        'idnumber' => $managedidnumber,
        'name' => $name,
        'topparent' => $topparent,
        'parentname' => $data['PARENT-CATEGORY'],
        'parentidnumber' => null,
        'description' => clean_text($data['DESCRIPTION'], FORMAT_HTML),
        'descriptionformat' => FORMAT_HTML,
        'visible' => $visible,
        'linenumber' => $linenumber,
    ];

    if ($definition['parentname'] === '') {
        $rootcount++;
    } else {
        $parent = category_setup_resolve_parent(
            $definition,
            $seenbyname[$definition['parentname']] ?? [],
            $linenumber
        );
        $definition['parentidnumber'] = $parent['idnumber'];
    }

    $definitions[$sourceidnumber] = $definition;
    $seenbyname[$name][] = $definition;
}
fclose($handle);

if ($rootcount !== 1) {
    throw new invalid_parameter_exception("The category CSV must contain exactly one root; found {$rootcount}.");
}
if (count($definitions) !== 598) {
    throw new invalid_parameter_exception(
        'The reviewed category CSV must contain exactly 598 category rows; found ' . count($definitions) . '.'
    );
}
$cataloguerowcount = count($definitions);

// TOP PARENT is a validation/grouping column. Confirm it names the current row
// or one of its resolved ancestors without using it as a database identifier.
$definitionsbyidnumber = [];
foreach ($definitions as $definition) {
    $definitionsbyidnumber[$definition['idnumber']] = $definition;
}
foreach ($definitions as $definition) {
    $cursor = $definition;
    $topparentfound = false;
    while ($cursor) {
        if ($cursor['name'] === $definition['topparent']) {
            $topparentfound = true;
            break;
        }
        $cursor = $cursor['parentidnumber'] === null
            ? null
            : ($definitionsbyidnumber[$cursor['parentidnumber']] ?? null);
    }
    if (!$topparentfound) {
        throw new invalid_parameter_exception(
            "TOP PARENT '{$definition['topparent']}' is not in the ancestor path on CSV line " .
            "{$definition['linenumber']}."
        );
    }
}

$availableorganizations = [];
$organizationanchors = [];
foreach ($definitions as $definition) {
    $availableorganizations[$definition['topparent']] = $definition['topparent'];
    if ($definition['name'] === $definition['topparent']) {
        $organizationanchors[$definition['topparent']][] = $definition;
    }
}
if (count($organizationanchors) !== count($availableorganizations)) {
    throw new invalid_parameter_exception('Each TOP PARENT must identify exactly one organization anchor row.');
}
$organizationanchorbyname = [];
foreach ($organizationanchors as $organizationname => $anchors) {
    if (count($anchors) !== 1) {
        throw new invalid_parameter_exception(
            "Organization '{$organizationname}' must identify exactly one anchor row."
        );
    }
    $organizationanchorbyname[$organizationname] = reset($anchors);
}
$organizationselection = 'ALL';
if (core_text::strtoupper($organizationoption) !== 'ALL') {
    $organizationselection = '';
    foreach ($availableorganizations as $availableorganization) {
        if (core_text::strtolower($availableorganization) === core_text::strtolower($organizationoption)) {
            $organizationselection = $availableorganization;
            break;
        }
    }
    if ($organizationselection === '') {
        throw new RuntimeException(
            "Unknown organization '{$organizationoption}'. Use an exact TOP PARENT value from the CSV or ALL."
        );
    }

    // Include every row in the requested organization plus the ancestors
    // required to attach that branch below the IOMAD company category root.
    $selectedidnumbers = [];
    foreach ($definitions as $definition) {
        if ($definition['topparent'] !== $organizationselection) {
            continue;
        }
        $cursor = $definition;
        while ($cursor !== null) {
            $selectedidnumbers[$cursor['idnumber']] = true;
            $cursor = $cursor['parentidnumber'] === null
                ? null
                : ($definitionsbyidnumber[$cursor['parentidnumber']] ?? null);
        }
    }
    $definitions = array_filter(
        $definitions,
        static fn(array $definition): bool => isset($selectedidnumbers[$definition['idnumber']])
    );
}

// Mirror only the selected high-level organization anchors into IOMAD
// departments. The CSV root Organization maps to IOMAD's existing company
// department root; classes, streams, and subjects remain course categories.
$selectedorganizations = [];
foreach ($definitions as $definition) {
    $selectedorganizations[$definition['topparent']] = true;
}
$departmentshortnamebyanchorid = [];
$organizationrootanchor = $organizationanchorbyname['Organization'] ?? null;
if ($organizationrootanchor === null) {
    throw new invalid_parameter_exception("The CSV must define the 'Organization' anchor.");
}
$departmentshortnamebyanchorid[$organizationrootanchor['idnumber']] = null;
$departmentdefinitions = [];
foreach ($definitions as $definition) {
    if ($definition['name'] !== $definition['topparent'] ||
            !isset($selectedorganizations[$definition['topparent']]) ||
            $definition['topparent'] === 'Organization') {
        continue;
    }

    $parentanchor = $definition['parentidnumber'] === null
        ? null
        : ($definitionsbyidnumber[$definition['parentidnumber']] ?? null);
    while ($parentanchor !== null && $parentanchor['name'] !== $parentanchor['topparent']) {
        $parentanchor = $parentanchor['parentidnumber'] === null
            ? null
            : ($definitionsbyidnumber[$parentanchor['parentidnumber']] ?? null);
    }
    if ($parentanchor === null || !array_key_exists($parentanchor['idnumber'], $departmentshortnamebyanchorid)) {
        throw new invalid_parameter_exception(
            "Unable to resolve the department parent for '{$definition['topparent']}'."
        );
    }

    $departmentshortname = category_setup_department_shortname($definition['sourceidnumber']);
    if (core_text::strlen($departmentshortname) > 32) {
        throw new invalid_parameter_exception(
            "Generated department shortname exceeds 32 characters: {$departmentshortname}."
        );
    }
    $departmentdefinitions[$departmentshortname] = [
        'name' => $definition['topparent'] . ' (Department)',
        'shortname' => $departmentshortname,
        'parentshortname' => $departmentshortnamebyanchorid[$parentanchor['idnumber']],
        'sourceidnumber' => $definition['sourceidnumber'],
    ];
    $departmentshortnamebyanchorid[$definition['idnumber']] = $departmentshortname;
}

$existingbyidnumber = [];
foreach (core_course_category::get_all(['returnhidden' => true]) as $category) {
    if ($category->idnumber !== null && $category->idnumber !== '') {
        $existingbyidnumber[$category->idnumber] = $category;
    }
}

$actions = [];
$counts = ['create' => 0, 'update' => 0, 'unchanged' => 0, 'conflict' => 0];
foreach ($definitions as $definition) {
    $idnumber = $definition['idnumber'];
    $existing = $existingbyidnumber[$idnumber] ?? null;
    $expectedparent = $definition['parentidnumber'] === null
        ? $companyroot
        : ($existingbyidnumber[$definition['parentidnumber']] ?? null);

    if ($existing === null) {
        $actions[$idnumber] = 'create';
        $counts['create']++;
        continue;
    }
    if (!category_setup_is_descendant($existing, $companyroot) ||
            $expectedparent === null ||
            (int) $existing->parent !== (int) $expectedparent->id) {
        $actions[$idnumber] = 'conflict';
        $counts['conflict']++;
        continue;
    }

    $effectivevisible = $definition['visible'] && (int) $expectedparent->visible ? 1 : 0;
    $changed = $existing->name !== $definition['name'] ||
        category_setup_normalize_text((string) $existing->description) !==
            category_setup_normalize_text($definition['description']) ||
        (int) $existing->descriptionformat !== (int) $definition['descriptionformat'] ||
        (int) $existing->visible !== $effectivevisible;
    $actions[$idnumber] = $changed ? 'update' : 'unchanged';
    $counts[$actions[$idnumber]]++;
}

$existingdepartments = $DB->get_records(
    'local_iomad_company_departments',
    ['companyid' => $companyrecord->id],
    'id',
    'id,companyid,parentid,name,shortname'
);
$departmentroots = array_filter(
    $existingdepartments,
    static fn(stdClass $department): bool => empty($department->parentid)
);
if (count($departmentroots) !== 1) {
    throw new RuntimeException(
        "IOMAD company {$companyrecord->shortname} must have exactly one root department; found " .
        count($departmentroots) . '.'
    );
}
$companydepartmentroot = reset($departmentroots);

$existingdepartmentsbyshortname = [];
foreach ($existingdepartments as $department) {
    if ((int) $department->id === (int) $companydepartmentroot->id) {
        continue;
    }
    $existingdepartmentsbyshortname[$department->shortname][] = $department;
}

$departmentactions = [];
$departmentcounts = ['create' => 0, 'update' => 0, 'unchanged' => 0, 'conflict' => 0];
foreach ($departmentdefinitions as $shortname => $definition) {
    $matches = $existingdepartmentsbyshortname[$shortname] ?? [];
    if (count($matches) > 1) {
        $departmentactions[$shortname] = 'conflict';
        $departmentcounts['conflict']++;
        continue;
    }
    $existing = $matches ? reset($matches) : null;
    $expectedparent = $definition['parentshortname'] === null
        ? $companydepartmentroot
        : (($existingdepartmentsbyshortname[$definition['parentshortname']][0] ?? null));
    if ($existing === null) {
        $departmentactions[$shortname] = 'create';
        $departmentcounts['create']++;
        continue;
    }
    if ($expectedparent === null ||
            (int) $existing->companyid !== (int) $companyrecord->id ||
            (int) $existing->parentid !== (int) $expectedparent->id) {
        $departmentactions[$shortname] = 'conflict';
        $departmentcounts['conflict']++;
        continue;
    }
    $departmentactions[$shortname] = $existing->name === $definition['name'] ? 'unchanged' : 'update';
    $departmentcounts[$departmentactions[$shortname]]++;
}

$hasconflicts = $counts['conflict'] > 0 || $departmentcounts['conflict'] > 0;
$departmentplan = [];
foreach ($departmentdefinitions as $shortname => $definition) {
    $departmentplan[] = [
        'name' => $definition['name'],
        'shortname' => $shortname,
        'parent' => $definition['parentshortname'] ?? 'IOMAD company department root',
        'action' => $departmentactions[$shortname],
    ];
}

$result = [
    'status' => $hasconflicts ? 'conflict' : ($apply ? 'applied' : 'planned'),
    'mode' => $apply ? 'apply' : 'plan',
    'scope' => 'moodle_course_categories_and_iomad_departments',
    'company_shortname' => $companyrecord->shortname,
    'company_category_id' => (int) $companyroot->id,
    'organization' => $organizationselection,
    'source_file' => 'institution-packs/categories/moodle_iomad_category_grab_format.csv',
    'source_rows_total' => $cataloguerowcount,
    'source_rows' => count($definitions),
    'idnumber_prefix' => $companyprefix . '-',
    'category_counts' => $counts,
    'department_counts' => $departmentcounts,
    'department_plan' => $departmentplan,
    'validation' => [
        'expected_categories' => count($definitions),
        'existing_categories' => count($definitions) - $counts['create'],
        'missing_categories' => $counts['create'],
        'valid_unchanged_categories' => $counts['unchanged'],
        'drifted_categories' => $counts['update'],
        'conflicting_categories' => $counts['conflict'],
        'expected_departments' => count($departmentdefinitions),
        'existing_departments' => count($departmentdefinitions) - $departmentcounts['create'],
        'missing_departments' => $departmentcounts['create'],
        'valid_unchanged_departments' => $departmentcounts['unchanged'],
        'drifted_departments' => $departmentcounts['update'],
        'conflicting_departments' => $departmentcounts['conflict'],
    ],
];

if ($hasconflicts) {
    $result['conflicts'] = [
        'categories' => array_keys(array_filter(
            $actions,
            static fn(string $action): bool => $action === 'conflict'
        )),
        'departments' => array_keys(array_filter(
            $departmentactions,
            static fn(string $action): bool => $action === 'conflict'
        )),
    ];
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(2);
}

if ($apply) {
    $transaction = $DB->start_delegated_transaction();
    try {
        foreach ($definitions as $definition) {
            $idnumber = $definition['idnumber'];
            $action = $actions[$idnumber];
            if ($action === 'unchanged') {
                continue;
            }

            $parent = $definition['parentidnumber'] === null
                ? $companyroot
                : ($existingbyidnumber[$definition['parentidnumber']] ?? null);
            if ($parent === null) {
                throw new RuntimeException("Missing managed parent category: {$definition['parentidnumber']}");
            }

            $record = [
                'name' => $definition['name'],
                'idnumber' => $definition['idnumber'],
                'parent' => $parent->id,
                'description' => $definition['description'],
                'descriptionformat' => $definition['descriptionformat'],
                'visible' => $definition['visible'],
            ];
            if ($action === 'create') {
                $category = core_course_category::create($record);
                $existingbyidnumber[$idnumber] = $category;
            } else {
                $existingbyidnumber[$idnumber]->update($record);
                $existingbyidnumber[$idnumber] = core_course_category::get(
                    $existingbyidnumber[$idnumber]->id,
                    MUST_EXIST,
                    true
                );
            }
        }

        foreach ($departmentdefinitions as $shortname => $definition) {
            $action = $departmentactions[$shortname];
            if ($action === 'unchanged') {
                continue;
            }
            $parent = $definition['parentshortname'] === null
                ? $companydepartmentroot
                : ($existingdepartmentsbyshortname[$definition['parentshortname']][0] ?? null);
            if ($parent === null) {
                throw new RuntimeException(
                    "Missing managed parent department: {$definition['parentshortname']}"
                );
            }
            $departmentid = 0;
            if ($action === 'update') {
                $existingmatches = $existingdepartmentsbyshortname[$shortname] ?? [];
                if (count($existingmatches) !== 1) {
                    throw new RuntimeException("Unable to resolve existing department: {$shortname}");
                }
                $existingdepartment = reset($existingmatches);
                $departmentid = (int) $existingdepartment->id;
            }
            \local_iomad\company::create_department(
                $departmentid,
                (int) $companyrecord->id,
                $definition['name'],
                $definition['shortname'],
                (int) $parent->id
            );
            $freshmatches = $DB->get_records(
                'local_iomad_company_departments',
                ['companyid' => $companyrecord->id, 'shortname' => $shortname],
                'id',
                'id,companyid,parentid,name,shortname',
                0,
                2
            );
            if (count($freshmatches) !== 1) {
                throw new RuntimeException("Department API validation failed for {$shortname}.");
            }
            $existingdepartmentsbyshortname[$shortname] = [reset($freshmatches)];
        }

        // Verify the complete managed trees before committing. This makes a
        // repeat apply a validation-only pass when every managed record is intact.
        $validatedcount = 0;
        foreach ($definitions as $definition) {
            $categoryreference = $existingbyidnumber[$definition['idnumber']] ?? null;
            $parentreference = $definition['parentidnumber'] === null
                ? $companyroot
                : ($existingbyidnumber[$definition['parentidnumber']] ?? null);
            if ($categoryreference === null || $parentreference === null) {
                throw new RuntimeException(
                    "Post-apply category lookup failed for {$definition['idnumber']}."
                );
            }
            $category = core_course_category::get($categoryreference->id, MUST_EXIST, true);
            $parent = core_course_category::get($parentreference->id, MUST_EXIST, true);
            if (!category_setup_is_descendant($category, $companyroot) ||
                    (int) $category->parent !== (int) $parent->id) {
                throw new RuntimeException(
                    "Post-apply hierarchy validation failed for {$definition['idnumber']}."
                );
            }

            $effectivevisible = $definition['visible'] && (int) $parent->visible ? 1 : 0;
            if ($category->name !== $definition['name'] ||
                    category_setup_normalize_text((string) $category->description) !==
                        category_setup_normalize_text($definition['description']) ||
                    (int) $category->descriptionformat !== (int) $definition['descriptionformat'] ||
                    (int) $category->visible !== $effectivevisible) {
                throw new RuntimeException(
                    "Post-apply field validation failed for {$definition['idnumber']}."
                );
            }
            $validatedcount++;
        }

        $validateddepartmentcount = 0;
        foreach ($departmentdefinitions as $shortname => $definition) {
            $matches = $DB->get_records(
                'local_iomad_company_departments',
                ['companyid' => $companyrecord->id, 'shortname' => $shortname],
                'id',
                'id,companyid,parentid,name,shortname',
                0,
                2
            );
            $parent = $definition['parentshortname'] === null
                ? $companydepartmentroot
                : ($existingdepartmentsbyshortname[$definition['parentshortname']][0] ?? null);
            if (count($matches) !== 1 || $parent === null) {
                throw new RuntimeException("Post-apply department lookup failed for {$shortname}.");
            }
            $department = reset($matches);
            if ((int) $department->companyid !== (int) $companyrecord->id ||
                    (int) $department->parentid !== (int) $parent->id ||
                    $department->name !== $definition['name']) {
                throw new RuntimeException("Post-apply department validation failed for {$shortname}.");
            }
            $validateddepartmentcount++;
        }
        $result['post_apply_validation'] = [
            'validated_categories' => $validatedcount,
            'validated_departments' => $validateddepartmentcount,
            'missing_records' => 0,
            'conflicting_records' => 0,
        ];
        $transaction->allow_commit();
    } catch (Throwable $exception) {
        $transaction->rollback($exception);
    }
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
