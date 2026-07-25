<?php
// This file is part of Moodle - http://moodle.org/

namespace block_iomaddashboard\local;

use context_course;
use core_completion\progress;
use local_iomad\custom_context\context_company;
use moodle_url;

/**
 * Builds permission-filtered data for every dashboard mode.
 *
 * @package    block_iomaddashboard
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class dashboard_service {
    /** @var \stdClass */
    private \stdClass $course;

    /** @var \context */
    private \context $context;

    /** @var \stdClass */
    private \stdClass $user;

    /** @var int */
    private int $limit;

    /** @var tenant_scope */
    private tenant_scope $scope;

    /**
     * Create a dashboard data service.
     *
     * @param \stdClass $course Current course.
     * @param \context $context Current page context.
     * @param \stdClass $user Current user.
     * @param int $limit Row limit.
     */
    public function __construct(\stdClass $course, \context $context, \stdClass $user, int $limit = 5) {
        $this->course = $course;
        $this->context = $context;
        $this->user = $user;
        $this->limit = min(20, max(3, $limit));
        $this->scope = new tenant_scope();
    }

    /**
     * Build one whitelisted widget.
     *
     * @param string $widget Widget identifier.
     * @return array
     */
    public function build(string $widget): array {
        if (!widget_catalog::exists($widget)) {
            $widget = 'courseprogress';
        }
        $method = 'build_' . $widget;
        $data = $this->{$method}();
        $data['widget'] = $widget;
        $data['title'] = widget_catalog::all()[$widget];
        $data['hasrows'] = !empty($data['rows']);
        $data['empty'] = $data['empty'] ?? get_string('nothingtoshow', 'block_iomaddashboard');
        return $data;
    }

    /**
     * Learner course progress.
     *
     * @return array
     */
    private function build_courseprogress(): array {
        $rows = [];
        foreach ($this->my_courses() as $course) {
            $percentage = progress::get_course_progress_percentage($course, $this->user->id);
            $rows[] = $this->row(
                format_string($course->fullname),
                get_string('progresslabel', 'block_iomaddashboard', $percentage === null ? 0 : round($percentage)),
                new moodle_url('/course/view.php', ['id' => $course->id]),
                $percentage,
            );
            if (count($rows) >= $this->limit) {
                break;
            }
        }
        return ['rows' => $rows];
    }

    /**
     * Tenant-filtered course participants.
     *
     * @return array
     */
    private function build_enrolledusers(): array {
        if (!$this->is_course_page()) {
            return ['rows' => [], 'empty' => get_string('courseonly', 'block_iomaddashboard')];
        }
        $context = context_course::instance($this->course->id);
        if (!has_capability('moodle/course:viewparticipants', $context)) {
            return $this->denied();
        }

        $rows = [];
        $users = get_enrolled_users($context, '', 0, 'u.id,u.firstname,u.lastname,u.lastaccess', 'u.lastaccess DESC');
        foreach ($users as $user) {
            if (!$this->scope->contains_user((int)$user->id)) {
                continue;
            }
            $rows[] = $this->row(
                fullname($user),
                $user->lastaccess ? userdate($user->lastaccess) : get_string('never'),
                new moodle_url('/user/view.php', ['id' => $user->id, 'course' => $this->course->id]),
            );
            if (count($rows) >= $this->limit) {
                break;
            }
        }
        return ['rows' => $rows];
    }

    /**
     * Current user's quiz attempts.
     *
     * @return array
     */
    private function build_quizattempts(): array {
        global $CFG;

        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        $rows = [];
        foreach ($this->my_courses() as $course) {
            foreach (get_fast_modinfo($course, $this->user->id)->get_cms() as $cm) {
                if ($cm->modname !== 'quiz' || !$cm->uservisible) {
                    continue;
                }
                $attempts = quiz_get_user_attempts($cm->instance, $this->user->id, 'finished', true);
                $attempt = $attempts ? end($attempts) : null;
                $rows[] = $this->row(
                    $cm->get_formatted_name(),
                    $attempt ? userdate($attempt->timefinish) : get_string('notattempted', 'block_iomaddashboard'),
                    $cm->url,
                );
                if (count($rows) >= $this->limit) {
                    break 2;
                }
            }
        }
        return ['rows' => $rows];
    }

    /**
     * Tenant-filtered aggregate metrics for a course.
     *
     * @return array
     */
    private function build_courseanalytics(): array {
        if (!$this->is_course_page()) {
            return ['rows' => [], 'empty' => get_string('courseonly', 'block_iomaddashboard')];
        }
        $context = context_course::instance($this->course->id);
        if (!has_capability('moodle/course:viewparticipants', $context)) {
            return $this->denied();
        }

        $users = get_enrolled_users($context, '', 0, 'u.id');
        $count = 0;
        $progresssum = 0.0;
        $progresscount = 0;
        foreach ($users as $user) {
            if (!$this->scope->contains_user((int)$user->id)) {
                continue;
            }
            $count++;
            $percentage = progress::get_course_progress_percentage($this->course, $user->id);
            if ($percentage !== null) {
                $progresssum += $percentage;
                $progresscount++;
            }
        }
        $modinfo = get_fast_modinfo($this->course);
        return ['rows' => [
            $this->row(get_string('enrolledlearners', 'block_iomaddashboard'), (string)$count),
            $this->row(
                get_string('averageprogress', 'block_iomaddashboard'),
                round($progresscount ? $progresssum / $progresscount : 0) . '%',
            ),
            $this->row(get_string('visibleactivities', 'block_iomaddashboard'), (string)count($modinfo->get_cms())),
        ]];
    }

    /**
     * Latest members in the active company boundary.
     *
     * @return array
     */
    private function build_latestmembers(): array {
        global $DB;

        $companyids = $this->scope->get_companyids();
        if (!$companyids) {
            return ['rows' => [], 'empty' => get_string('selectcompany', 'block_iomaddashboard')];
        }
        $companycontext = context_company::instance($this->scope->get_companyid());
        if (
            !is_siteadmin() &&
                !has_capability('block/iomad_company_admin:usermanagement_view', $companycontext)
        ) {
            return $this->denied();
        }
        [$insql, $params] = $DB->get_in_or_equal($companyids, SQL_PARAMS_NAMED, 'company');
        $params['deleted'] = 0;
        $users = $DB->get_records_sql(
            "SELECT DISTINCT u.id, u.firstname, u.lastname, u.timecreated
               FROM {user} u
               JOIN {local_iomad_company_users} cu ON cu.userid = u.id
              WHERE cu.companyid {$insql}
                AND cu.suspended = 0
                AND u.deleted = :deleted
           ORDER BY u.timecreated DESC",
            $params,
            0,
            $this->limit,
        );
        $rows = [];
        foreach ($users as $user) {
            $rows[] = $this->row(fullname($user), userdate($user->timecreated));
        }
        return ['rows' => $rows];
    }

    /**
     * Notes authored by the current user plus the core notes action.
     *
     * @return array
     */
    private function build_addnotes(): array {
        global $CFG;

        if (!$this->is_course_page()) {
            return ['rows' => [], 'empty' => get_string('courseonly', 'block_iomaddashboard')];
        }
        require_once($CFG->dirroot . '/notes/lib.php');
        $context = context_course::instance($this->course->id);
        if (!has_capability('moodle/notes:view', $context)) {
            return $this->denied();
        }
        $notes = note_list($this->course->id, 0, '', $this->user->id, 'lastmodified DESC', 0, $this->limit);
        $rows = [];
        foreach ($notes as $note) {
            $rows[] = $this->row(
                shorten_text(trim(strip_tags($note->content)), 90),
                userdate($note->lastmodified),
            );
        }
        return [
            'rows' => $rows,
            'actions' => [[
                'label' => get_string('opennotes', 'block_iomaddashboard'),
                'url' => (new moodle_url('/notes/index.php', ['course' => $this->course->id]))->out(false),
            ]],
        ];
    }

    /**
     * Grade feedback belonging only to the current user.
     *
     * @return array
     */
    private function build_recentfeedback(): array {
        global $CFG;

        require_once($CFG->libdir . '/gradelib.php');
        $rows = [];
        foreach ($this->my_courses() as $course) {
            $grades = grade_get_grades($course->id, '', '', $this->user->id);
            if (!$grades || empty($grades->items)) {
                continue;
            }
            foreach ($grades->items as $item) {
                $grade = $item->grades[$this->user->id] ?? null;
                if (!$grade || trim(strip_tags((string)$grade->feedback)) === '') {
                    continue;
                }
                $rows[] = $this->row(
                    format_string($item->name),
                    shorten_text(trim(strip_tags($grade->feedback)), 100),
                    new moodle_url('/grade/report/user/index.php', [
                        'id' => $course->id,
                        'userid' => $this->user->id,
                    ]),
                );
                if (count($rows) >= $this->limit) {
                    break 2;
                }
            }
        }
        return ['rows' => $rows];
    }

    /**
     * Accessible forum activities from current enrolments.
     *
     * @return array
     */
    private function build_recentforums(): array {
        $rows = [];
        foreach ($this->my_courses() as $course) {
            foreach (get_fast_modinfo($course, $this->user->id)->get_cms() as $cm) {
                if ($cm->modname !== 'forum' || !$cm->uservisible || !$cm->url) {
                    continue;
                }
                $rows[] = $this->row($cm->get_formatted_name(), format_string($course->fullname), $cm->url);
                if (count($rows) >= $this->limit) {
                    break 2;
                }
            }
        }
        return ['rows' => $rows];
    }

    /**
     * Core course-management commands.
     *
     * @return array
     */
    private function build_managecourse(): array {
        if (!$this->is_course_page()) {
            return ['rows' => [], 'empty' => get_string('courseonly', 'block_iomaddashboard')];
        }
        $context = context_course::instance($this->course->id);
        $actions = [];
        if (has_capability('moodle/course:update', $context)) {
            $actions[] = $this->action('editcoursesettings', '/course/edit.php');
        }
        if (has_capability('moodle/course:viewparticipants', $context)) {
            $actions[] = $this->action('participants', '/user/index.php');
        }
        if (has_capability('moodle/grade:viewall', $context)) {
            $actions[] = $this->action('grades', '/grade/report/grader/index.php');
        }
        if (has_capability('report/completion:view', $context)) {
            $actions[] = [
                'label' => get_string('completionreport', 'block_iomaddashboard'),
                'url' => (new moodle_url(
                    '/report/completion/index.php',
                    ['course' => $this->course->id],
                ))->out(false),
            ];
        }
        return ['rows' => [], 'actions' => $actions, 'empty' => get_string('nocourseactions', 'block_iomaddashboard')];
    }

    /**
     * Current user's private tasks.
     *
     * @return array
     */
    private function build_todo(): array {
        $repository = new todo_repository();
        $rows = [];
        foreach ($repository->list_for_user($this->user->id, $this->limit) as $task) {
            $rows[] = [
                'primary' => format_string($task->tasktext),
                'secondary' => $task->duedate ? userdate($task->duedate) : '',
                'completed' => !empty($task->completed),
                'todoid' => $task->id,
            ];
        }
        return ['rows' => $rows, 'todoform' => true];
    }

    /**
     * Return enrolled courses visible to this user.
     *
     * @return array
     */
    private function my_courses(): array {
        return array_values(enrol_get_my_courses(
            ['id', 'fullname', 'shortname', 'enablecompletion'],
            'sortorder ASC',
            0,
            [],
            true,
        ));
    }

    /**
     * Check for a real course context.
     *
     * @return bool
     */
    private function is_course_page(): bool {
        return $this->course->id != SITEID && $this->context->contextlevel >= CONTEXT_COURSE;
    }

    /**
     * Build a generic row.
     *
     * @param string $primary Primary text.
     * @param string $secondary Secondary text.
     * @param moodle_url|null $url Optional URL.
     * @param float|null $percentage Optional progress.
     * @return array
     */
    private function row(
        string $primary,
        string $secondary = '',
        ?moodle_url $url = null,
        ?float $percentage = null,
    ): array {
        return [
            'primary' => $primary,
            'secondary' => $secondary,
            'url' => $url?->out(false),
            'hasprogress' => $percentage !== null,
            'percentage' => $percentage === null ? 0 : round($percentage),
        ];
    }

    /**
     * Build a course action.
     *
     * @param string $stringid Core or plugin string.
     * @param string $path URL path.
     * @return array
     */
    private function action(string $stringid, string $path): array {
        $component = $stringid === 'completionreport' ? 'block_iomaddashboard' : 'moodle';
        return [
            'label' => get_string($stringid, $component),
            'url' => (new moodle_url($path, ['id' => $this->course->id]))->out(false),
        ];
    }

    /**
     * Return a consistent permission denial.
     *
     * @return array
     */
    private function denied(): array {
        return ['rows' => [], 'empty' => get_string('nopermission', 'block_iomaddashboard')];
    }
}
