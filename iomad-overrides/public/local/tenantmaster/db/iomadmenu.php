<?php
// This file is part of IOMAD - http://www.iomad.org/

/**
 * Add Tenant Master control surfaces to the IOMAD company menu.
 *
 * @return array
 */
function local_tenantmaster_menu(): array {
    return [
        'tenantmaster' => [
            'category' => 'CompanyAdmin',
            'tab' => 1,
            'name' => get_string('pluginname', 'local_tenantmaster'),
            'url' => '/local/tenantmaster/index.php',
            'cap' => 'local/tenantmaster:view',
            'icondefault' => 'editcompany',
            'style' => 'tenantmaster project-tool',
            'icon' => 'fa-building-columns',
            'iconsmall' => 'fa-diagram-project',
        ],
        'tenantmastercatalogue' => [
            'category' => 'CompanyAdmin',
            'tab' => 1,
            'name' => get_string('mastercatalogue', 'local_tenantmaster'),
            'url' => '/local/tenantmaster/index.php?section=catalogue',
            'cap' => 'local/tenantmaster:managecatalogue',
            'icondefault' => 'managecoursesettings',
            'style' => 'catalogue project-tool',
            'icon' => 'fa-layer-group',
            'iconsmall' => 'fa-pen-to-square',
        ],
        'tenantmastercourseeditor' => [
            'category' => 'CourseAdmin',
            'tab' => 3,
            'name' => get_string('tenantcourseeditor', 'local_tenantmaster'),
            'url' => '/local/tenantmaster/index.php?section=courses',
            'cap' => 'local/tenantmaster:view',
            'icondefault' => 'managecoursesettings',
            'style' => 'course project-tool',
            'icon' => 'fa-graduation-cap',
            'iconsmall' => 'fa-pen-to-square',
        ],
    ];
}
