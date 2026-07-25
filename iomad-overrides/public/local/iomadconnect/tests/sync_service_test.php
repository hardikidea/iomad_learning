<?php
// This file is part of Moodle - http://moodle.org/

namespace local_iomadconnect;

use local_iomad\company;
use local_iomadcommerce\local\tenant_scope;
use local_iomadconnect\local\link_repository;
use local_iomadconnect\local\sync_service;

/**
 * Supported synchronization workflow tests.
 *
 * @package    local_iomadconnect
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_iomadconnect\local\sync_service
 */
final class sync_service_test extends \advanced_testcase {
    /**
     * Categories, courses, federated users, and owned enrolments are idempotent.
     */
    public function test_apply_workflow_and_owned_unenrolment(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $company = $this->company('Connect Workflow', 'connect_workflow');
        $scope = new tenant_scope($company->id);
        $service = new sync_service();
        $events = $this->events('OWNED');

        $results = $service->apply($scope, 'wordpress', $events);
        $replay = $service->apply($scope, 'wordpress', $events);
        $links = new link_repository();
        $userlink = $links->get($company->id, 'wordpress', 'user', 'USER-OWNED');
        $courselink = $links->get($company->id, 'wordpress', 'course', 'COURSE-OWNED');

        $this->assertSame(['created', 'created', 'created', 'enrolled'], array_column($results, 'action'));
        $this->assertSame(['unchanged', 'unchanged', 'unchanged', 'unchanged'], array_column($replay, 'action'));
        $this->assertTrue(is_enrolled(
            \context_course::instance((int)$courselink->localid),
            (int)$userlink->localid,
        ));

        $unenrol = [[
            'eventid' => 'EVENT-OWNED-UNENROL',
            'entitytype' => 'enrolment',
            'entityid' => 'ENROL-OWNED',
            'action' => 'unenrol',
            'payload' => [
                'externalid' => 'ENROL-OWNED',
                'user_externalid' => 'USER-OWNED',
                'course_externalid' => 'COURSE-OWNED',
            ],
        ]];
        $this->assertSame('unenrolled', $service->apply($scope, 'wordpress', $unenrol)[0]['action']);
        $this->assertFalse(is_enrolled(
            \context_course::instance((int)$courselink->localid),
            (int)$userlink->localid,
        ));
    }

    /**
     * A connector never removes an enrolment it did not create.
     */
    public function test_preexisting_enrolment_is_not_owned_or_removed(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $company = $this->company('Connect Preserve', 'connect_preserve');
        $scope = new tenant_scope($company->id);
        $service = new sync_service();
        $events = $this->events('PRESERVE');
        $service->apply($scope, 'wordpress', array_slice($events, 0, 3));
        $links = new link_repository();
        $user = $links->get($company->id, 'wordpress', 'user', 'USER-PRESERVE');
        $course = $links->get($company->id, 'wordpress', 'course', 'COURSE-PRESERVE');
        \local_iomad\company_user::enrol((int)$user->localid, (int)$course->localid, $company->id);

        $this->assertSame('unchanged', $service->apply($scope, 'wordpress', [$events[3]])[0]['action']);
        $unenrol = $events[3];
        $unenrol['eventid'] = 'EVENT-PRESERVE-UNENROL';
        $unenrol['action'] = 'unenrol';
        $this->assertSame('unchanged', $service->apply($scope, 'wordpress', [$unenrol])[0]['action']);
        $this->assertTrue(is_enrolled(
            \context_course::instance((int)$course->localid),
            (int)$user->localid,
        ));
    }

    /**
     * Password fields are rejected recursively.
     */
    public function test_password_sync_is_rejected(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $company = $this->company('Connect Password', 'connect_password');
        $event = $this->events('PASSWORD')[2];
        $event['payload']['profile'] = ['temporaryPassword' => 'never-log-this'];

        $this->expectException(\invalid_parameter_exception::class);
        (new sync_service())->apply(new tenant_scope($company->id), 'wordpress', [$event]);
    }

    /**
     * Build a stable workflow event set.
     *
     * @param string $suffix Stable suffix.
     * @return array
     */
    private function events(string $suffix): array {
        return [
            [
                'eventid' => 'EVENT-' . $suffix . '-CATEGORY',
                'entitytype' => 'category',
                'entityid' => 'CATEGORY-' . $suffix,
                'action' => 'upsert',
                'payload' => [
                    'externalid' => 'CATEGORY-' . $suffix,
                    'name' => 'Connector category ' . $suffix,
                    'visible' => false,
                ],
            ],
            [
                'eventid' => 'EVENT-' . $suffix . '-COURSE',
                'entitytype' => 'course',
                'entityid' => 'COURSE-' . $suffix,
                'action' => 'upsert',
                'payload' => [
                    'externalid' => 'COURSE-' . $suffix,
                    'category_externalid' => 'CATEGORY-' . $suffix,
                    'shortname' => 'COURSE-' . $suffix,
                    'fullname' => 'Connector course ' . $suffix,
                    'visible' => false,
                    'format' => 'topics',
                ],
            ],
            [
                'eventid' => 'EVENT-' . $suffix . '-USER',
                'entitytype' => 'user',
                'entityid' => 'USER-' . $suffix,
                'action' => 'upsert',
                'payload' => [
                    'externalid' => 'USER-' . $suffix,
                    'username' => 'connector_' . strtolower($suffix),
                    'firstname' => 'Connector',
                    'lastname' => $suffix,
                    'email' => 'connector_' . strtolower($suffix) . '@example.test',
                ],
            ],
            [
                'eventid' => 'EVENT-' . $suffix . '-ENROL',
                'entitytype' => 'enrolment',
                'entityid' => 'ENROL-' . $suffix,
                'action' => 'enrol',
                'payload' => [
                    'externalid' => 'ENROL-' . $suffix,
                    'user_externalid' => 'USER-' . $suffix,
                    'course_externalid' => 'COURSE-' . $suffix,
                ],
            ],
        ];
    }

    /**
     * Create a company.
     *
     * @param string $name Name.
     * @param string $shortname Shortname.
     * @return company
     */
    private function company(string $name, string $shortname): company {
        return company::create_company((object)[
            'name' => $name,
            'shortname' => $shortname,
            'city' => 'Pune',
            'country' => 'IN',
        ]);
    }
}
