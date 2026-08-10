<?php
// This file is part of Moodle - https://moodle.org/

namespace local_orgprofile\event;

use core\event\base;
use local_iomad\custom_context\context_company;

/** User type assignment event. */
final class user_type_assigned extends base {
    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_orgprofile_user';
    }

    public static function create_for_assignment(int $objectid, int $relateduserid, int $companyid): self {
        return self::create([
            'objectid' => $objectid,
            'relateduserid' => $relateduserid,
            'context' => context_company::instance($companyid),
            'other' => ['companyid' => $companyid],
        ]);
    }

    public static function get_name(): string {
        return get_string('usertypeassignedevent', 'local_orgprofile');
    }

    public function get_description(): string {
        return "The user with id '{$this->userid}' assigned an organization profile user type to user id " .
            "'{$this->relateduserid}' in company id '{$this->other['companyid']}'.";
    }
}
