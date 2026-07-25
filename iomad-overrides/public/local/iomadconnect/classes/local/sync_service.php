<?php
// This file is part of Moodle - http://moodle.org/

namespace local_iomadconnect\local;

use local_iomad\company;
use local_iomad\company_user;
use local_iomadcommerce\local\tenant_scope;

/**
 * Apply supported category, course, user, and enrolment events.
 *
 * @package    local_iomadconnect
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class sync_service {
    /**
     * Constructor.
     *
     * @param event_repository $events Event audit.
     * @param link_repository $links Entity links.
     */
    public function __construct(
        private readonly event_repository $events = new event_repository(),
        private readonly link_repository $links = new link_repository(),
    ) {
    }

    /**
     * Apply an ordered event batch, stopping at the first failure.
     *
     * @param tenant_scope $scope Tenant scope.
     * @param string $systemkey Source system.
     * @param array $events Events.
     * @return array Per-event non-personal statuses.
     */
    public function apply(tenant_scope $scope, string $systemkey, array $events): array {
        if (!preg_match('/^[A-Za-z0-9_.-]{2,40}$/', $systemkey)) {
            throw new \invalid_parameter_exception('Invalid source system key.');
        }
        if (!$events || count($events) > 500) {
            throw new \invalid_parameter_exception('An event batch must contain between 1 and 500 events.');
        }
        $results = [];
        foreach ($events as $event) {
            if (!is_array($event) || !is_array($event['payload'] ?? null)) {
                throw new \invalid_parameter_exception('Each event requires an object payload.');
            }
            $this->reject_password_fields($event['payload']);
            $hash = hash('sha256', json_encode(
                $this->canonicalize($event),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
            ));
            $eventid = (string)($event['eventid'] ?? '');
            try {
                if (!$this->events->claim($scope->companyid(), $event, $hash)) {
                    $results[] = ['eventid' => $eventid, 'action' => 'unchanged'];
                    continue;
                }
                $action = match ((string)$event['entitytype']) {
                    'category' => $this->apply_category($scope, $systemkey, $event),
                    'course' => $this->apply_course($scope, $systemkey, $event),
                    'user' => $this->apply_user($scope, $systemkey, $event),
                    'enrolment' => $this->apply_enrolment($scope, $systemkey, $event),
                    default => throw new \invalid_parameter_exception('Unsupported entity type.'),
                };
                $this->events->complete($scope->companyid(), $eventid);
                $results[] = ['eventid' => $eventid, 'action' => $action];
            } catch (\Throwable $exception) {
                $this->events->fail(
                    $scope->companyid(),
                    $eventid,
                    $exception instanceof \moodle_exception ? $exception->errorcode : 'apply_failed',
                );
                throw $exception;
            }
        }
        return $results;
    }

    /**
     * Apply a course-category event.
     *
     * @param tenant_scope $scope Scope.
     * @param string $systemkey System.
     * @param array $event Event.
     * @return string
     */
    private function apply_category(tenant_scope $scope, string $systemkey, array $event): string {
        global $DB;

        $payload = $event['payload'];
        $externalid = $this->require_matching_id($event, $payload);
        $parentid = 0;
        $parentexternalid = trim((string)($payload['parent_externalid'] ?? ''));
        if ($parentexternalid !== '') {
            $parent = $this->links->get($scope->companyid(), $systemkey, 'category', $parentexternalid);
            if (!$parent) {
                throw new \invalid_parameter_exception('The parent category has not been synchronized.');
            }
            $parentid = (int)$parent->localid;
        }
        $link = $this->links->get($scope->companyid(), $systemkey, 'category', $externalid);
        $category = $link && $DB->record_exists('course_categories', ['id' => $link->localid])
            ? \core_course_category::get((int)$link->localid)
            : null;
        if (!$category) {
            $category = \core_course_category::create([
                'name' => trim((string)($payload['name'] ?? '')),
                'idnumber' => $externalid,
                'parent' => $parentid,
                'visible' => (int)(bool)($payload['visible'] ?? true),
            ]);
            $action = 'created';
        } else {
            $category->update([
                'name' => trim((string)($payload['name'] ?? $category->name)),
                'idnumber' => $externalid,
                'parent' => $parentid,
                'visible' => (int)(bool)($payload['visible'] ?? true),
            ]);
            $action = 'updated';
        }
        $this->links->upsert(
            $scope->companyid(),
            $systemkey,
            'category',
            $externalid,
            (int)$category->id,
        );
        return $action;
    }

    /**
     * Apply a course event.
     *
     * @param tenant_scope $scope Scope.
     * @param string $systemkey System.
     * @param array $event Event.
     * @return string
     */
    private function apply_course(tenant_scope $scope, string $systemkey, array $event): string {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');
        $payload = $event['payload'];
        $externalid = $this->require_matching_id($event, $payload);
        $categoryexternalid = trim((string)($payload['category_externalid'] ?? ''));
        $category = $this->links->get(
            $scope->companyid(),
            $systemkey,
            'category',
            $categoryexternalid,
        );
        if (!$category) {
            throw new \invalid_parameter_exception('The course category has not been synchronized.');
        }
        $link = $this->links->get($scope->companyid(), $systemkey, 'course', $externalid);
        $course = $link ? $DB->get_record('course', ['id' => $link->localid], '*', MUST_EXIST) : null;
        $data = (object)[
            'shortname' => trim((string)($payload['shortname'] ?? '')),
            'fullname' => trim((string)($payload['fullname'] ?? '')),
            'idnumber' => $externalid,
            'category' => (int)$category->localid,
            'visible' => (int)(bool)($payload['visible'] ?? false),
            'format' => clean_param($payload['format'] ?? 'topics', PARAM_PLUGIN),
        ];
        if ($data->shortname === '' || $data->fullname === '') {
            throw new \invalid_parameter_exception('Course shortname and fullname are required.');
        }
        if ($course) {
            if (!$scope->contains_course((int)$course->id)) {
                throw new \invalid_parameter_exception('The linked course is outside the company.');
            }
            $data->id = $course->id;
            update_course($data);
            $courseid = (int)$course->id;
            $action = 'updated';
        } else {
            if ($DB->record_exists('course', ['shortname' => $data->shortname])) {
                throw new \invalid_parameter_exception('Course shortname is already used outside this connector link.');
            }
            $created = create_course($data);
            (new company($scope->companyid()))->add_course($created);
            $courseid = (int)$created->id;
            $action = 'created';
        }
        $this->links->upsert(
            $scope->companyid(),
            $systemkey,
            'course',
            $externalid,
            $courseid,
        );
        return $action;
    }

    /**
     * Apply a federated user event.
     *
     * @param tenant_scope $scope Scope.
     * @param string $systemkey System.
     * @param array $event Event.
     * @return string
     */
    private function apply_user(tenant_scope $scope, string $systemkey, array $event): string {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/user/lib.php');
        $payload = $event['payload'];
        $externalid = $this->require_matching_id($event, $payload);
        $link = $this->links->get($scope->companyid(), $systemkey, 'user', $externalid);
        $user = $link ? $DB->get_record('user', ['id' => $link->localid], '*', MUST_EXIST) : null;
        if ($event['action'] === 'disable') {
            if (!$user || !$scope->contains_user((int)$user->id)) {
                throw new \invalid_parameter_exception('The linked user is outside the company.');
            }
            company_user::suspend((int)$user->id, $scope->companyid());
            return 'disabled';
        }
        $data = (object)[
            'username' => \core_text::strtolower(trim((string)($payload['username'] ?? ''))),
            'firstname' => trim((string)($payload['firstname'] ?? '')),
            'lastname' => trim((string)($payload['lastname'] ?? '')),
            'email' => \core_text::strtolower(trim((string)($payload['email'] ?? ''))),
            'idnumber' => $externalid,
            'auth' => get_config('local_iomadconnect', 'authmethod') ?: 'iomadoidc',
            'confirmed' => 1,
            'mnethostid' => $CFG->mnet_localhost_id,
        ];
        if (
            $data->username === '' || $data->firstname === '' || $data->lastname === ''
            || !validate_email($data->email)
        ) {
            throw new \invalid_parameter_exception('Valid username, name, and email fields are required.');
        }
        if ($user) {
            if (!$scope->contains_user((int)$user->id)) {
                throw new \invalid_parameter_exception('The linked user is outside the company.');
            }
            $data->id = $user->id;
            user_update_user($data, false);
            $userid = (int)$user->id;
            $action = 'updated';
        } else {
            if (
                $DB->record_exists('user', ['username' => $data->username, 'mnethostid' => $CFG->mnet_localhost_id])
                || $DB->record_exists('user', ['idnumber' => $externalid])
            ) {
                throw new \invalid_parameter_exception('The user stable ID or username is already in use.');
            }
            $userid = user_create_user($data, false);
            (new company($scope->companyid()))->assign_user_to_company($userid);
            $action = 'created';
        }
        $this->links->upsert(
            $scope->companyid(),
            $systemkey,
            'user',
            $externalid,
            $userid,
        );
        return $action;
    }

    /**
     * Apply an enrolment event using connector-owned enrolment IDs.
     *
     * @param tenant_scope $scope Scope.
     * @param string $systemkey System.
     * @param array $event Event.
     * @return string
     */
    private function apply_enrolment(tenant_scope $scope, string $systemkey, array $event): string {
        global $DB;

        $payload = $event['payload'];
        $externalid = $this->require_matching_id($event, $payload);
        $userlink = $this->links->get(
            $scope->companyid(),
            $systemkey,
            'user',
            trim((string)($payload['user_externalid'] ?? '')),
        );
        $courselink = $this->links->get(
            $scope->companyid(),
            $systemkey,
            'course',
            trim((string)($payload['course_externalid'] ?? '')),
        );
        if (
            !$userlink || !$courselink
            || !$scope->contains_user((int)$userlink->localid)
            || !$scope->contains_course((int)$courselink->localid)
        ) {
            throw new \invalid_parameter_exception('The enrolment user or course is outside the company.');
        }
        $existing = $this->links->get($scope->companyid(), $systemkey, 'enrolment', $externalid);
        if ($event['action'] === 'unenrol') {
            if ($existing && $existing->ownedid) {
                $this->remove_owned_enrolment((int)$existing->ownedid, (int)$userlink->localid);
                $this->links->upsert(
                    $scope->companyid(),
                    $systemkey,
                    'enrolment',
                    $externalid,
                    (int)$userlink->localid,
                    0,
                );
                return 'unenrolled';
            }
            return 'unchanged';
        }
        $before = $this->user_enrolment_ids((int)$userlink->localid, (int)$courselink->localid);
        company_user::enrol(
            (int)$userlink->localid,
            (int)$courselink->localid,
            $scope->companyid(),
        );
        $after = $this->user_enrolment_ids((int)$userlink->localid, (int)$courselink->localid);
        if (!$after) {
            throw new \moodle_exception('The connector could not create an enrolment.');
        }
        $newids = array_values(array_diff($after, $before));
        $ownedid = count($newids) === 1 ? (int)$newids[0] : (int)($existing->ownedid ?? 0);
        $this->links->upsert(
            $scope->companyid(),
            $systemkey,
            'enrolment',
            $externalid,
            (int)$userlink->localid,
            $ownedid,
        );
        return $before ? 'unchanged' : 'enrolled';
    }

    /**
     * Require payload ID to match envelope ID.
     *
     * @param array $event Event.
     * @param array $payload Payload.
     * @return string
     */
    private function require_matching_id(array $event, array $payload): string {
        $entityid = trim((string)($event['entityid'] ?? ''));
        if ($entityid === '' || $entityid !== trim((string)($payload['externalid'] ?? ''))) {
            throw new \invalid_parameter_exception('Event and payload external IDs must match.');
        }
        return $entityid;
    }

    /**
     * Reject all password-shaped fields recursively.
     *
     * @param array $payload Payload.
     */
    private function reject_password_fields(array $payload): void {
        foreach ($payload as $key => $value) {
            if (str_contains(\core_text::strtolower((string)$key), 'password')) {
                throw new \invalid_parameter_exception(
                    get_string('sharedpasswordrejected', 'local_iomadconnect'),
                );
            }
            if (is_array($value)) {
                $this->reject_password_fields($value);
            }
        }
    }

    /**
     * Sort associative data recursively for stable hashes.
     *
     * @param mixed $value Value.
     * @return mixed
     */
    private function canonicalize(mixed $value): mixed {
        if (!is_array($value)) {
            return $value;
        }
        if (!array_is_list($value)) {
            ksort($value, SORT_STRING);
        }
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }
        return $value;
    }

    /**
     * User enrolment IDs.
     *
     * @param int $userid User.
     * @param int $courseid Course.
     * @return array
     */
    private function user_enrolment_ids(int $userid, int $courseid): array {
        global $DB;

        return array_map('intval', $DB->get_fieldset_sql(
            "SELECT ue.id
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE ue.userid = :userid AND e.courseid = :courseid",
            ['userid' => $userid, 'courseid' => $courseid],
        ));
    }

    /**
     * Remove one connector-owned user enrolment.
     *
     * @param int $userenrolmentid User enrolment.
     * @param int $userid User.
     */
    private function remove_owned_enrolment(int $userenrolmentid, int $userid): void {
        global $DB;

        $enrol = $DB->get_record_sql(
            "SELECT e.*
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE ue.id = :id AND ue.userid = :userid",
            ['id' => $userenrolmentid, 'userid' => $userid],
        );
        if ($enrol && ($plugin = enrol_get_plugin($enrol->enrol))) {
            $plugin->unenrol_user($enrol, $userid);
        }
    }
}
