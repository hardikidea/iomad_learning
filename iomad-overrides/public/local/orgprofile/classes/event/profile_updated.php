<?php
// This file is part of Moodle - https://moodle.org/

namespace local_orgprofile\event;

use core\event\base;
use local_iomad\custom_context\context_company;

/** Profile update event which intentionally contains no submitted values. */
final class profile_updated extends base {
    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    public static function create_for_profile(int $relateduserid, int $companyid, int $formid): self {
        return self::create([
            'relateduserid' => $relateduserid,
            'context' => context_company::instance($companyid),
            'other' => ['companyid' => $companyid, 'formid' => $formid],
        ]);
    }

    public static function get_name(): string {
        return get_string('profileupdatedevent', 'local_orgprofile');
    }

    public function get_description(): string {
        return "The user with id '{$this->userid}' updated organization profile metadata for user id " .
            "'{$this->relateduserid}' in company id '{$this->other['companyid']}'.";
    }
}
