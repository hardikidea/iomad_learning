<?php
// This file is part of Moodle - https://moodle.org/

defined('MOODLE_INTERNAL') || die();

/**
 * Add guided organization-profile workflows to the IOMAD dashboard.
 *
 * @return array
 */
function local_orgprofile_menu(): array {
    return [
        'orgprofile_create_company' => [
            'category' => 'CompanyAdmin',
            'tab' => 1,
            'name' => get_string('createcompanyprofiled', 'local_orgprofile'),
            'url' => '/local/orgprofile/company_create.php',
            'cap' => 'block/iomad_company_admin:company_add',
            'style' => 'company',
            'icon' => 'fa-building-circle-check',
            'iconsmall' => 'fa-plus-square',
        ],
        'orgprofile_create_user' => [
            'category' => 'UserAdmin',
            'tab' => 2,
            'name' => get_string('createprofileduser', 'local_orgprofile'),
            'url' => '/local/orgprofile/company_user_create.php',
            'cap' => 'block/iomad_company_admin:user_create',
            'style' => 'user',
            'icon' => 'fa-user-plus',
            'iconsmall' => 'fa-plus-square',
        ],
    ];
}
