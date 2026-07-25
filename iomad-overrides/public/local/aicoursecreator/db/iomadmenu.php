<?php
// This file is part of IOMAD - http://www.iomad.org/

/**
 * Add AI course creator to the IOMAD company administration menu.
 */
function local_aicoursecreator_menu(): array {
    return [
        'aicoursecreator' => [
            'category' => 'CompanyAdmin',
            'tab' => 1,
            'name' => get_string('pluginname', 'local_aicoursecreator'),
            'url' => '/local/aicoursecreator/index.php',
            'cap' => 'local/aicoursecreator:manage',
            'icondefault' => 'courses',
            'style' => 'courses',
            'icon' => 'fa-wand-magic-sparkles',
            'iconsmall' => 'fa-wand-magic-sparkles',
        ],
    ];
}
