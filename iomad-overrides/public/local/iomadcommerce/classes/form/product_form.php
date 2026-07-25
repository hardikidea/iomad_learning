<?php
// This file is part of Moodle - http://moodle.org/

namespace local_iomadcommerce\form;

/**
 * Tenant product editor.
 *
 * @package    local_iomadcommerce
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class product_form extends \moodleform {
    /**
     * Build the form.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $courses = $this->_customdata['courses'];
        $companyid = $this->_customdata['companyid'];
        $mform->addElement('hidden', 'companyid', $companyid);
        $mform->setType('companyid', PARAM_INT);
        $mform->addElement('text', 'externalid', get_string('externalid', 'local_iomadcommerce'));
        $mform->setType('externalid', PARAM_ALPHANUMEXT);
        $mform->addRule('externalid', null, 'required');
        $mform->addElement('select', 'courseid', get_string('course'), $courses);
        $mform->addRule('courseid', null, 'required');
        $mform->addElement('text', 'name', get_string('product', 'local_iomadcommerce'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addElement('select', 'status', get_string('status'), [
            'draft' => get_string('draft', 'local_iomadcommerce'),
            'free' => get_string('free', 'local_iomadcommerce'),
            'paid' => get_string('paid', 'local_iomadcommerce'),
            'closed' => get_string('closed', 'local_iomadcommerce'),
        ]);
        $mform->addElement('text', 'priceminor', get_string('priceminor', 'local_iomadcommerce'));
        $mform->setType('priceminor', PARAM_INT);
        $mform->setDefault('priceminor', 0);
        $mform->addElement('text', 'currency', get_string('currency', 'local_iomadcommerce'));
        $mform->setType('currency', PARAM_ALPHANUMEXT);
        $mform->setDefault('currency', 'USD');
        $mform->addElement('text', 'accessdays', get_string('accessdays', 'local_iomadcommerce'));
        $mform->setType('accessdays', PARAM_INT);
        $mform->setDefault('accessdays', 0);
        $mform->addElement('url', 'checkouturl', get_string('checkouturl', 'local_iomadcommerce'));
        $mform->setType('checkouturl', PARAM_URL);
        $mform->addElement('text', 'recommendations', get_string('recommendations', 'local_iomadcommerce'));
        $mform->setType('recommendations', PARAM_TEXT);
        $this->add_action_buttons(false, get_string('savechanges'));
    }
}
