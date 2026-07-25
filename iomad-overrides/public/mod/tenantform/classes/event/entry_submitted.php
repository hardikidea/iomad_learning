<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_tenantform\event;

/**
 * A tenant form entry was submitted.
 *
 * @package    mod_tenantform
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class entry_submitted extends \core\event\base {
    /**
     * Set event properties.
     */
    protected function init(): void {
        $this->data['objecttable'] = 'tenantform_entry';
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    /**
     * Event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('evententrysubmitted', 'mod_tenantform');
    }

    /**
     * Event description.
     *
     * @return string
     */
    public function get_description(): string {
        return "The user with id '{$this->userid}' submitted tenant form entry "
            . "'{$this->objectid}' in course module '{$this->contextinstanceid}'.";
    }

    /**
     * Relevant URL.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return new \moodle_url('/mod/tenantform/view.php', ['id' => $this->contextinstanceid]);
    }

    /**
     * Object mapping for restore.
     *
     * @return array
     */
    public static function get_objectid_mapping(): array {
        return ['db' => 'tenantform_entry', 'restore' => 'tenantform_entry'];
    }
}
