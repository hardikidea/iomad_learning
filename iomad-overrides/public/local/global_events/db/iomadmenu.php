<?php
// This file is part of IOMAD - http://www.iomad.org/

/**
 * Add tenant event management to IOMAD Admin Tools.
 *
 * @return array
 */
function local_global_events_menu(): array {
    return [
        'globaleventmanagement' => [
            'category' => 'CompanyAdmin',
            'tab' => 1,
            'name' => get_string('eventmanagement', 'local_global_events'),
            'url' => '/local/global_events/manage.php',
            'cap' => 'local/global_events:manage',
            'icondefault' => 'report',
            'style' => 'event project-tool',
            'icon' => 'fa-calendar',
            'iconsmall' => 'fa-gear',
        ],
    ];
}
