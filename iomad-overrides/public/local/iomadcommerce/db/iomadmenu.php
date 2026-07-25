<?php
// This file is part of IOMAD - http://www.iomad.org/

/**
 * Add tenant commerce management to the company menu.
 *
 * @return array
 */
function local_iomadcommerce_menu(): array {
    return [
        'tenantcommerce' => [
            'category' => 'Courses',
            'tab' => 1,
            'name' => get_string('pluginname', 'local_iomadcommerce'),
            'url' => '/local/iomadcommerce/manage.php',
            'cap' => 'local/iomadcommerce:manage',
            'icondefault' => 'payment',
            'style' => 'commerce',
            'icon' => 'fa-cart-shopping',
            'iconsmall' => 'fa-cart-shopping',
        ],
    ];
}
