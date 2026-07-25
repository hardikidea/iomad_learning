<?php
// This file is part of Moodle - http://moodle.org/

namespace local_global_events\local;

/**
 * Company XP level repository.
 *
 * @package local_global_events
 */
final class level_repository {
    /**
     * Upsert a level by stable company and number.
     *
     * @param tenant_scope $scope Scope.
     * @param int $levelnum Level number.
     * @param string $name Name.
     * @param int $minpoints Minimum points.
     * @return object
     */
    public function upsert(tenant_scope $scope, int $levelnum, string $name, int $minpoints): object {
        global $DB;

        $name = trim($name);
        if ($levelnum < 1 || $levelnum > 1000 || $name === '' || $minpoints < 0) {
            throw new \invalid_parameter_exception('Invalid gamification level.');
        }
        $conditions = ['companyid' => $scope->companyid(), 'levelnum' => $levelnum];
        $record = $DB->get_record('local_ge_level', $conditions);
        if ($record) {
            $record->name = mb_substr($name, 0, 100);
            $record->minpoints = $minpoints;
            $DB->update_record('local_ge_level', $record);
            return $record;
        }
        $record = (object)($conditions + [
            'name' => mb_substr($name, 0, 100),
            'minpoints' => $minpoints,
        ]);
        $record->id = $DB->insert_record('local_ge_level', $record);
        return $record;
    }
}
