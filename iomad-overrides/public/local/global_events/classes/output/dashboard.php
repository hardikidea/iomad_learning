<?php
// This file is part of Moodle - http://moodle.org/

namespace local_global_events\output;

use local_global_events\local\dashboard_service;
use local_global_events\local\tenant_scope;

/**
 * Learner dashboard view model.
 *
 * @package local_global_events
 */
final class dashboard implements \renderable, \templatable {
    /**
     * Constructor.
     *
     * @param tenant_scope $scope Company scope.
     * @param int $userid Learner.
     */
    public function __construct(
        private readonly tenant_scope $scope,
        private readonly int $userid,
    ) {
    }

    /**
     * Export escaped-by-Mustache presentation data.
     *
     * @param \renderer_base $output Renderer.
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        $companycontext = \local_iomad\custom_context\context_company::instance($this->scope->companyid());
        $canreport = is_siteadmin() || \local_iomad\iomad::has_capability(
            'local/global_events:viewreports',
            $companycontext,
            $this->scope->companyid(),
        );
        if ($canreport) {
            $data = (new dashboard_service())->manager($this->scope, true);
            $data['ismanager'] = true;
            $data['islearner'] = false;
            $data['hascompanies'] = $data['companies'] !== [];
            $data['isparentmanager'] = $data['profile'] === 'parent-manager'
                && count($data['companies']) > 1;
            return $data;
        }

        $data = (new dashboard_service())->learner($this->scope, $this->userid);
        foreach ($data['events'] as &$event) {
            $event['startlabel'] = userdate($event['timestart'], get_string('strftimedatetime', 'langconfig'));
            $event['courseurl'] = $event['courseid'] > 0
                ? (new \moodle_url('/course/view.php', ['id' => $event['courseid']]))->out(false)
                : '';
            $event['hascourse'] = $event['courseid'] > 0;
        }
        unset($event);
        foreach ($data['badges'] as &$badge) {
            $badge['issuedlabel'] = userdate($badge['dateissued'], get_string('strftimedate', 'langconfig'));
        }
        unset($badge);
        $data['haslevel'] = $data['progress']['levelname'] !== '';
        $data['hasnextlevel'] = $data['progress']['nextlevelname'] !== '';
        $data['hasevents'] = $data['events'] !== [];
        $data['hasbadges'] = $data['badges'] !== [];
        $data['ismanager'] = false;
        $data['islearner'] = true;
        $data['certificateurl'] = (new \moodle_url('/my/courses.php'))->out(false);
        return $data;
    }
}
