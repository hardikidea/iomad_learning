<?php
// This file is part of IOMAD - http://www.iomad.org/

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('appearance', new admin_externalpage(
        'local_iomadpagebuilder',
        get_string('managepages', 'local_iomadpagebuilder'),
        new moodle_url('/local/iomadpagebuilder/index.php')
    ));
}
