<?php
// This file is part of Moodle - http://moodle.org/

namespace local_global_events\local;

/**
 * Enrol into a visible event course through Moodle's self-enrol API.
 *
 * @package local_global_events
 */
final class event_enrolment_service {
    /**
     * Self-enrol the authenticated learner.
     *
     * @param tenant_scope $scope Scope.
     * @param int $eventid Event.
     */
    public function enrol(tenant_scope $scope, int $eventid): void {
        global $USER;

        $event = (new event_repository())->get_visible($scope, $eventid);
        if ((int)$event->courseid <= 0 || !$scope->contains_user((int)$USER->id)) {
            throw new \invalid_parameter_exception('The event is not enrolable.');
        }
        $plugin = enrol_get_plugin('self');
        if (!$plugin) {
            throw new \moodle_exception('selfenrolnotavailable', 'enrol_self');
        }
        foreach (enrol_get_instances((int)$event->courseid, true) as $instance) {
            if (
                $instance->enrol === 'self'
                && empty($instance->password)
                && $plugin->can_self_enrol($instance) === true
            ) {
                $plugin->enrol_self($instance);
                return;
            }
        }
        throw new \moodle_exception('canntenrol', 'enrol_self');
    }
}
