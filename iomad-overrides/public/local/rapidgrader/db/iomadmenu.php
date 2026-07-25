<?php
// This file is part of IOMAD - http://www.iomad.org/

/**
 * Add RapidGrader to the IOMAD company menu.
 *
 * @return array
 */
function local_rapidgrader_menu(): array {
    return [
        'rapidgrader' => [
            'category' => 'Courses',
            'tab' => 1,
            'name' => get_string('pluginname', 'local_rapidgrader'),
            'url' => '/local/rapidgrader/index.php',
            'cap' => 'local/rapidgrader:viewcompany',
            'icondefault' => 'grades',
            'style' => 'grade',
            'icon' => 'fa-table-list',
            'iconsmall' => 'fa-table-list',
        ],
    ];
}
