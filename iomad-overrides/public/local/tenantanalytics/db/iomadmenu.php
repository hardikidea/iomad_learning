<?php
// This file is part of IOMAD - http://www.iomad.org/

/**
 * Add tenant analytics to the IOMAD company menu.
 *
 * @return array
 */
function local_tenantanalytics_menu(): array {
    return [
        'tenantanalytics' => [
            'category' => 'Reports',
            'tab' => 1,
            'name' => get_string('pluginname', 'local_tenantanalytics'),
            'url' => '/local/tenantanalytics/index.php',
            'cap' => 'local/tenantanalytics:viewcompany',
            'icondefault' => 'report',
            'style' => 'report',
            'icon' => 'fa-chart-line',
            'iconsmall' => 'fa-chart-line',
        ],
    ];
}
