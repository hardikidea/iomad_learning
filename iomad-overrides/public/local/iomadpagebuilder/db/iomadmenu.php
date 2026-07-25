<?php
// This file is part of IOMAD - http://www.iomad.org/

/**
 * Add page builder to the IOMAD company administration menu.
 */
function local_iomadpagebuilder_menu(): array {
    return [
        'iomadpagebuilder' => [
            'category' => 'CompanyAdmin',
            'tab' => 1,
            'name' => get_string('pluginname', 'local_iomadpagebuilder'),
            'url' => '/local/iomadpagebuilder/index.php',
            'cap' => 'local/iomadpagebuilder:manage',
            'icondefault' => 'report',
            'style' => 'report',
            'icon' => 'fa-object-group',
            'iconsmall' => 'fa-object-group',
        ],
    ];
}
