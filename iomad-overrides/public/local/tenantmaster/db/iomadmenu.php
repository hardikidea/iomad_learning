<?php
// This file is part of IOMAD - http://www.iomad.org/

/**
 * Add Tenant Master control surfaces to the IOMAD company menu.
 *
 * @return array
 */
function local_tenantmaster_menu(): array {
    global $DB, $SESSION;

    $companyid = (int)($SESSION->currenteditingcompany ?? 0);
    $tenant = $companyid > 0
        ? $DB->get_record('local_tenantmaster_tenant', ['companyid' => $companyid])
        : false;
    $tenanttype = $tenant ? (string)$tenant->tenanttype : '';
    $tab = 9;
    $category = 'TenantAdmin';
    $menu = [];

    $add = static function(
        string $key,
        string $namekey,
        string $section,
        string $capability,
        string $icon,
        array $params = [],
    ) use (&$menu, $companyid, $tab, $category): void {
        $params = ['section' => $section] + $params;
        if ($companyid > 0) {
            $params['companyid'] = $companyid;
        }
        $menu[$key] = [
            'category' => $category,
            'tab' => $tab,
            'tabcustom' => true,
            'tablabel' => get_string('tenants', 'local_tenantmaster'),
            'tabicon' => 'fa-building-columns',
            'name' => get_string($namekey, 'local_tenantmaster'),
            'url' => '/local/tenantmaster/index.php?' . http_build_query(
                $params,
                '',
                '&',
                PHP_QUERY_RFC3986,
            ),
            'cap' => $capability,
            'icondefault' => 'managecoursesettings',
            'preferfonticon' => true,
            'style' => 'tenantmaster project-tool',
            'icon' => $icon,
            'iconsmall' => '',
        ];
    };

    $add('tenantmaster', 'tenantworkspace', 'dashboard', 'local/tenantmaster:view', 'fa-gauge-high');
    $add(
        'tenantmasterinstitutions',
        'managedinstitutions',
        'tenants',
        'local/tenantmaster:view',
        'fa-building-columns',
    );

    // An uninitialised company needs onboarding before tenant-owned CRUD is available.
    if (!$tenant) {
        return $menu;
    }

    $add(
        'tenantmasterprofile',
        'institutionmasterdata',
        'profile',
        'local/tenantmaster:view',
        'fa-building',
    );
    $add(
        'tenantmasterorganisation',
        'organisation',
        'organisation',
        'local/tenantmaster:view',
        'fa-diagram-project',
    );
    $add(
        'tenantmasteracademicyears',
        'academicyears',
        'academic',
        'local/tenantmaster:manageacademic',
        'fa-calendar',
        ['academicview' => 'years'],
    );

    $schooltypes = [
        'board' => 'fa-building-columns',
        'medium' => 'fa-language',
        'grade' => 'fa-graduation-cap',
        'stream' => 'fa-diagram-project',
        'division' => 'fa-people-group',
        'subject' => 'fa-book',
    ];
    $universitytypes = [
        'programme' => 'fa-graduation-cap',
        'semester' => 'fa-calendar',
        'specialisation' => 'fa-diagram-project',
        'credit' => 'fa-award',
        'subject' => 'fa-book',
    ];
    $trainingtypes = [
        'programme' => 'fa-graduation-cap',
        'subject' => 'fa-book',
        'credit' => 'fa-award',
    ];
    $mastertypes = match ($tenanttype) {
        'school' => $schooltypes,
        'university', 'college' => $universitytypes,
        default => $trainingtypes,
    };
    foreach ($mastertypes as $mastertype => $icon) {
        $add(
            'tenantmaster_' . $mastertype,
            \local_tenantmaster\local\catalog::MASTER_TYPES[$mastertype],
            'academic',
            'local/tenantmaster:manageacademic',
            $icon,
            ['type' => $mastertype],
        );
    }

    $operations = [
        ['tenantmastercourses', 'academiccourseprojections', 'courses', 'fa-graduation-cap'],
        ['tenantmasterpeople', 'usersandroles', 'people', 'fa-users'],
        ['tenantmasterclasses', 'classmanagement', 'classes', 'fa-people-group'],
        ['tenantmasteraccess', 'cohortsandenrolments', 'access', 'fa-link'],
        ['tenantmasterassessments', 'assessments', 'assessments', 'fa-list-check'],
        ['tenantmastercertificates', 'certificates', 'certificates', 'fa-certificate'],
        ['tenantmasterprogression', 'progression', 'progression', 'fa-chart-line'],
        ['tenantmasterimports', 'imports', 'imports', 'fa-file-import'],
        ['tenantmastersync', 'synchronization', 'sync', 'fa-arrows-rotate'],
        ['tenantmastervalidation', 'validation', 'validation', 'fa-shield'],
        ['tenantmasteraudit', 'audit', 'audit', 'fa-clipboard'],
    ];
    foreach ($operations as [$key, $namekey, $section, $icon]) {
        if ($section === 'classes' && $tenanttype !== 'school') {
            continue;
        }
        $capability = match ($section) {
            'people' => 'local/tenantmaster:managepeople',
            'imports' => 'local/tenantmaster:import',
            'sync' => 'local/tenantmaster:sync',
            'validation', 'audit' => 'local/tenantmaster:viewaudit',
            default => 'local/tenantmaster:view',
        };
        $add($key, $namekey, $section, $capability, $icon);
    }

    return $menu;
}
