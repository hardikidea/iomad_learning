<?php
// This file is part of Moodle - https://moodle.org/

namespace local_orgprofile\event;

use core\event\base;
use local_iomad\custom_context\context_company;

/** Company profile mapping change event. */
final class company_mapping_changed extends base {
    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_orgprofile_company';
    }

    public static function create_for_mapping(int $objectid, int $companyid): self {
        return self::create([
            'objectid' => $objectid,
            'context' => context_company::instance($companyid),
            'other' => ['companyid' => $companyid],
        ]);
    }

    public static function get_name(): string {
        return get_string('companymappingchangedevent', 'local_orgprofile');
    }

    public function get_description(): string {
        return "The user with id '{$this->userid}' changed the organization profile mapping for company id " .
            "'{$this->other['companyid']}'.";
    }
}
