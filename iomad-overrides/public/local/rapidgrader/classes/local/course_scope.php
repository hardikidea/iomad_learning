<?php
// This file is part of Moodle - http://moodle.org/

namespace local_rapidgrader\local;

use local_iomad\company;
use local_iomad\iomad;

/**
 * Resolve courses and learners within one IOMAD company.
 *
 * @package    local_rapidgrader
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_scope {
    /**
     * Constructor.
     *
     * @param int $companyid Company.
     */
    public function __construct(private readonly int $companyid) {
    }

    /**
     * Resolve current company, with an explicit site-admin override.
     *
     * @param int $requestedcompanyid Requested company.
     * @return self
     */
    public static function resolve(int $requestedcompanyid = 0): self {
        global $DB;

        if (is_siteadmin() && $requestedcompanyid > 0) {
            if (!$DB->record_exists('local_iomad_companies', ['id' => $requestedcompanyid])) {
                throw new \invalid_parameter_exception('The requested company does not exist.');
            }
            return new self($requestedcompanyid);
        }
        $companyid = iomad::get_my_companyid(\context_system::instance(), false);
        if ($companyid <= 0 && !is_siteadmin()) {
            throw new \required_capability_exception(
                \context_system::instance(),
                'local/rapidgrader:viewcompany',
                'nopermissions',
                '',
            );
        }
        return new self(max(0, $companyid));
    }

    /**
     * Company ID.
     *
     * @return int
     */
    public function companyid(): int {
        return $this->companyid;
    }

    /**
     * Courses visible and gradable by the current user.
     *
     * @return array Course ID to formatted name.
     */
    public function courses(): array {
        if ($this->companyid > 0) {
            $company = new company($this->companyid);
            $courses = $company->get_menu_courses(
                shared: true,
                default: false,
                includehidden: true,
            );
        } else {
            $courses = [];
            foreach (get_courses() as $course) {
                if ((int)$course->id !== SITEID) {
                    $courses[$course->id] = format_string($course->fullname);
                }
            }
        }
        foreach ($courses as $courseid => $name) {
            $context = \context_course::instance((int)$courseid);
            if (
                !has_capability('local/rapidgrader:view', $context)
                && !has_capability('moodle/grade:viewall', $context)
            ) {
                unset($courses[$courseid]);
            }
        }
        asort($courses, SORT_NATURAL | SORT_FLAG_CASE);
        return $courses;
    }

    /**
     * Require a selected course to remain in scope.
     *
     * @param int $courseid Course.
     * @return object
     */
    public function require_course(int $courseid): object {
        global $DB;

        if (!array_key_exists($courseid, $this->courses())) {
            $context = $courseid > 0 && $DB->record_exists('course', ['id' => $courseid])
                ? \context_course::instance($courseid)
                : \context_system::instance();
            throw new \required_capability_exception(
                $context,
                'local/rapidgrader:view',
                'nopermissions',
                '',
            );
        }
        return get_course($courseid);
    }

    /**
     * Verify exact company membership for a learner.
     *
     * @param int $userid User.
     * @return bool
     */
    public function contains_user(int $userid): bool {
        global $DB;

        if ($this->companyid === 0) {
            return true;
        }
        return $DB->record_exists('local_iomad_company_users', [
            'companyid' => $this->companyid,
            'userid' => $userid,
        ]);
    }
}
