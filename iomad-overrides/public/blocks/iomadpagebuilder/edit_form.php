<?php
// This file is part of IOMAD - http://www.iomad.org/

defined('MOODLE_INTERNAL') || die();

final class block_iomadpagebuilder_edit_form extends block_edit_form {
    protected function specific_definition($mform): void {
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));
        $mform->addElement('select', 'config_pagetarget', get_string('pagetarget', 'block_iomadpagebuilder'), [
            'frontpage' => get_string('home'),
            'dashboard' => get_string('myhome'),
            'custompage' => get_string('pluginname', 'local_iomadcustompage'),
            'course' => get_string('course'),
        ]);
        $mform->setDefault('config_pagetarget', 'custompage');
        $mform->addHelpButton('config_pagetarget', 'pagetarget', 'block_iomadpagebuilder');
        $mform->addElement('text', 'config_pageslug', get_string('pageslug', 'block_iomadpagebuilder'));
        $mform->setType('config_pageslug', PARAM_ALPHANUMEXT);
        $mform->addHelpButton('config_pageslug', 'pageslug', 'block_iomadpagebuilder');
    }
}
