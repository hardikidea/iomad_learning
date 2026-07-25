<?php
// This file is part of Moodle - http://moodle.org/

namespace local_rapidgrader\local;

/**
 * Tenant-filtered Moodle gradebook read and update operations.
 *
 * @package    local_rapidgrader
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class gradebook_service {
    /** @var int Maximum grade cells accepted in one request. */
    public const MAX_UPDATE_CELLS = 5000;

    /** @var array<string, array> Learners cached by normalized search. */
    private array $learnercache = [];

    /**
     * Constructor.
     *
     * @param course_scope $scope Tenant scope.
     * @param object $course Course.
     */
    public function __construct(
        private readonly course_scope $scope,
        private readonly object $course,
    ) {
        require_once(__DIR__ . '/../../../../lib/gradelib.php');
    }

    /**
     * Gradebook-role learners in this course and company.
     *
     * @param string $search Optional name search.
     * @param int|null $limit Optional result limit.
     * @param int $offset Result offset.
     * @return array
     */
    public function learners(string $search = '', ?int $limit = null, int $offset = 0): array {
        $search = \core_text::strtolower(trim($search));
        if (!array_key_exists($search, $this->learnercache)) {
            $this->learnercache[$search] = $this->load_learners($search);
        }
        return $limit === null
            ? $this->learnercache[$search]
            : array_slice($this->learnercache[$search], max(0, $offset), max(0, $limit));
    }

    /**
     * Number of gradebook-role learners matching a search.
     *
     * @param string $search Optional name search.
     * @return int
     */
    public function learner_count(string $search = ''): int {
        return count($this->learners($search));
    }

    /**
     * Load gradebook-role learners through Moodle role APIs.
     *
     * @param string $search Normalized search.
     * @return array
     */
    private function load_learners(string $search): array {
        global $CFG;

        $context = \context_course::instance($this->course->id);
        $gradebookroles = array_filter(array_map('intval', explode(',', $CFG->gradebookroles)));
        $namefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false);
        $userfields = 'u.id,' . $namefields->selects . ',u.email,u.idnumber,u.deleted,u.suspended';
        $users = [];
        foreach ($gradebookroles as $roleid) {
            $roleusers = get_role_users(
                $roleid,
                $context,
                false,
                $userfields,
                'u.lastname,u.firstname,u.id',
                false,
            );
            foreach ($roleusers as $user) {
                $fullname = fullname($user);
                if (
                    !$this->scope->contains_user((int)$user->id)
                    || $user->deleted
                    || $user->suspended
                    || ($search !== '' && !str_contains(\core_text::strtolower($fullname), $search))
                ) {
                    continue;
                }
                $users[$user->id] = $user;
            }
        }
        uasort($users, static function (object $a, object $b): int {
            return [\core_text::strtolower($a->lastname), \core_text::strtolower($a->firstname), (int)$a->id]
                <=> [\core_text::strtolower($b->lastname), \core_text::strtolower($b->firstname), (int)$b->id];
        });
        return array_values($users);
    }

    /**
     * Reportable grade items.
     *
     * @return array
     */
    public function items(): array {
        $context = \context_course::instance($this->course->id);
        $canviewhidden = has_capability('moodle/grade:viewhidden', $context);
        $items = \grade_item::fetch_all(['courseid' => $this->course->id]) ?: [];
        foreach ($items as $id => $item) {
            if (
                !in_array($item->itemtype, ['manual', 'mod'], true)
                || !in_array((int)$item->gradetype, [GRADE_TYPE_VALUE, GRADE_TYPE_SCALE], true)
                || ($item->is_hidden() && !$canviewhidden)
            ) {
                unset($items[$id]);
            }
        }
        uasort($items, static fn(\grade_item $a, \grade_item $b): int => $a->sortorder <=> $b->sortorder);
        return $items;
    }

    /**
     * Return one final grade value.
     *
     * @param \grade_item $item Item.
     * @param int $userid User.
     * @return float|null
     */
    public function grade(\grade_item $item, int $userid): ?float {
        $grade = $item->get_final($userid);
        return $grade && $grade->finalgrade !== null ? (float)$grade->finalgrade : null;
    }

    /**
     * User-facing grade value, including scale labels.
     *
     * @param \grade_item $item Item.
     * @param float|null $grade Grade.
     * @return string
     */
    public function display_grade(\grade_item $item, ?float $grade): string {
        if ($grade === null) {
            return get_string('notgraded', 'local_rapidgrader');
        }
        if ((int)$item->gradetype === GRADE_TYPE_SCALE) {
            $scale = $item->load_scale();
            $index = (int)round($grade) - 1;
            return $scale && isset($scale->scale_items[$index])
                ? format_string($scale->scale_items[$index])
                : format_float($grade, 0);
        }
        return format_float($grade, 2);
    }

    /**
     * Select options for a scale grade.
     *
     * @param \grade_item $item Scale item.
     * @return array
     */
    public function scale_options(\grade_item $item): array {
        $options = ['' => get_string('notgraded', 'local_rapidgrader')];
        $scale = $item->load_scale();
        if ($scale) {
            foreach ($scale->scale_items as $index => $label) {
                $options[$index + 1] = format_string($label);
            }
        }
        return $options;
    }

    /**
     * Update manual grade items only.
     *
     * @param array $updates Nested item/user grade values.
     * @param int $actorid Actor.
     * @return int Number changed.
     */
    public function update(array $updates, int $actorid): int {
        $context = \context_course::instance($this->course->id);
        require_capability('local/rapidgrader:grade', $context);
        require_capability('moodle/grade:edit', $context);
        $cells = array_sum(array_map(
            static fn(mixed $grades): int => is_array($grades) ? count($grades) : 1,
            $updates,
        ));
        if ($cells > self::MAX_UPDATE_CELLS) {
            throw new \invalid_parameter_exception('The grade update exceeds the permitted batch size.');
        }
        $items = $this->items();
        $learners = array_column($this->learners(), null, 'id');
        $changed = 0;
        foreach ($updates as $itemid => $usergrades) {
            $itemid = (int)$itemid;
            if (!isset($items[$itemid]) || $items[$itemid]->itemtype !== 'manual' || !is_array($usergrades)) {
                continue;
            }
            $item = $items[$itemid];
            foreach ($usergrades as $userid => $rawgrade) {
                $userid = (int)$userid;
                if (!isset($learners[$userid]) || is_array($rawgrade)) {
                    continue;
                }
                $rawgrade = trim((string)$rawgrade);
                if ($rawgrade === '') {
                    $grade = null;
                } else if (is_numeric($rawgrade)) {
                    $grade = (float)$rawgrade;
                    if ((int)$item->gradetype === GRADE_TYPE_SCALE && floor($grade) !== $grade) {
                        throw new \invalid_parameter_exception('A scale grade must use one available scale value.');
                    }
                    if ($grade < (float)$item->grademin || $grade > (float)$item->grademax) {
                        throw new \invalid_parameter_exception('A grade is outside the item range.');
                    }
                } else {
                    throw new \invalid_parameter_exception('A grade must be numeric or empty.');
                }
                $current = $this->grade($item, $userid);
                if ($current === $grade) {
                    continue;
                }
                if (
                    !$item->update_final_grade(
                        $userid,
                        $grade,
                        'local_rapidgrader',
                        false,
                        FORMAT_MOODLE,
                        $actorid,
                        null,
                        true,
                    )
                ) {
                    throw new \moodle_exception('gradeupdatefailed', 'local_rapidgrader');
                }
                $changed++;
            }
        }
        if ($changed) {
            grade_regrade_final_grades($this->course->id);
        }
        return $changed;
    }

    /**
     * Grade distribution over all non-null values.
     *
     * @param array $items Items.
     * @param array $learners Learners.
     * @return array
     */
    public function distribution(array $items, array $learners): array {
        $bands = [
            'notgraded' => 0,
            'below50' => 0,
            'from50' => 0,
            'from65' => 0,
            'from80' => 0,
        ];
        foreach ($learners as $learner) {
            foreach ($items as $item) {
                $grade = $this->grade($item, (int)$learner->id);
                if ($grade === null) {
                    $bands['notgraded']++;
                    continue;
                }
                $range = (float)$item->grademax - (float)$item->grademin;
                $percentage = $range > 0
                    ? (($grade - (float)$item->grademin) / $range) * 100
                    : 0;
                $key = match (true) {
                    $percentage < 50 => 'below50',
                    $percentage < 65 => 'from50',
                    $percentage < 80 => 'from65',
                    default => 'from80',
                };
                $bands[$key]++;
            }
        }
        return $bands;
    }

    /**
     * Quiz overview links in this course.
     *
     * @return array
     */
    public function quizzes(): array {
        global $CFG, $DB;

        require_once(__DIR__ . '/../../../../mod/quiz/locallib.php');
        $quizzes = [];
        $learners = $this->learners();
        $modinfo = get_fast_modinfo($this->course);
        foreach ($modinfo->get_instances_of('quiz') as $cm) {
            if (!$cm->uservisible) {
                continue;
            }
            $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
            $attempts = 0;
            $participants = 0;
            foreach ($learners as $learner) {
                $userattempts = quiz_get_user_attempts($quiz->id, $learner->id, 'all', true);
                $attempts += count($userattempts);
                if ($userattempts) {
                    $participants++;
                }
            }
            $quizzes[] = [
                'name' => format_string($cm->name),
                'url' => new \moodle_url('/mod/quiz/report.php', ['id' => $cm->id, 'mode' => 'overview']),
                'attempts' => $attempts,
                'participants' => $participants,
            ];
        }
        return $quizzes;
    }
}
