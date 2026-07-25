<?php
// This file is part of IOMAD - http://www.iomad.org/

namespace local_institutionpack;

defined('MOODLE_INTERNAL') || die();

/**
 * Idempotent, reference-addressed IOMAD license allocations.
 */
final class license_manager {
    /**
     * Build an immutable license allocation plan.
     *
     * @param array $input Raw command input.
     * @return array
     */
    public function plan(array $input): array {
        global $DB;

        $license = $this->normalise($input);
        $existing = $DB->get_record(
            'local_iomad_company_licenses',
            [
                'companyid' => $license['companyid'],
                'reference' => $license['reference'],
            ],
            '*',
            IGNORE_MISSING
        );

        if ($existing) {
            $courses = array_map(
                'intval',
                $DB->get_fieldset_select(
                    'local_iomad_company_license_courses',
                    'courseid',
                    'licenseid = :licenseid',
                    ['licenseid' => $existing->id]
                )
            );
            sort($courses);
            $expectedcourses = array_column($license['courses'], 'courseid');
            sort($expectedcourses);
            $matches = (int)$existing->allocation === $license['allocation']
                && (int)$existing->validlength === $license['validlength']
                && (int)$existing->startdate === $license['startdate']
                && (int)$existing->expirydate === $license['expirydate']
                && (int)$existing->type === $license['type']
                && $courses === $expectedcourses;

            return [
                'ok' => $matches,
                'mode' => 'plan',
                'action' => $matches ? 'unchanged' : 'conflict',
                'license' => $this->public_data($license),
                'message' => $matches
                    ? 'The referenced allocation already exists and matches.'
                    : 'The reference already exists with a different immutable allocation.',
            ];
        }

        return [
            'ok' => true,
            'mode' => 'plan',
            'action' => 'allocate',
            'license' => $this->public_data($license),
        ];
    }

    /**
     * Create an additive license allocation through the IOMAD external API.
     *
     * @param array $input Raw command input.
     * @return array
     */
    public function apply(array $input): array {
        global $CFG, $DB;

        $plan = $this->plan($input);
        if (!$plan['ok'] || $plan['action'] === 'unchanged') {
            $plan['mode'] = 'apply';
            return $plan;
        }

        $license = $this->normalise($input);
        require_once($CFG->dirroot . '/blocks/iomad_company_admin/externallib.php');
        $apilicense = $license;
        unset(
            $apilicense['_company_shortname'],
            $apilicense['_course_key_type'],
            $apilicense['_course_key']
        );

        $transaction = $DB->start_delegated_transaction();
        \block_iomad_company_admin_external::create_licenses([$apilicense]);
        $transaction->allow_commit();

        $result = [
            'ok' => true,
            'mode' => 'apply',
            'action' => 'allocated',
            'license' => $this->public_data($license),
        ];
        $result['audit_report'] = audit_log::write('license-allocate', $result);
        return $result;
    }

    /**
     * Validate and resolve stable identifiers.
     *
     * @param array $input Raw input.
     * @return array
     */
    private function normalise(array $input): array {
        global $DB;

        $companyshortname = trim((string)($input['company'] ?? ''));
        $courseidnumber = trim((string)($input['courseidnumber'] ?? ''));
        $courseshortname = trim((string)($input['courseshortname'] ?? ''));
        $reference = trim((string)($input['reference'] ?? ''));
        $name = trim((string)($input['name'] ?? ''));
        $allocation = filter_var($input['allocation'] ?? null, FILTER_VALIDATE_INT);
        $validlength = filter_var($input['validlength'] ?? 0, FILTER_VALIDATE_INT);
        $type = filter_var($input['type'] ?? 0, FILTER_VALIDATE_INT);

        if (!preg_match('/^[A-Za-z0-9_]{1,25}$/', $companyshortname)) {
            throw new \InvalidArgumentException('Company must be an IOMAD company shortname.');
        }
        if (($courseidnumber === '') === ($courseshortname === '')) {
            throw new \InvalidArgumentException(
                'Pass exactly one stable course idnumber or course shortname.'
            );
        }
        if (!preg_match('/^[A-Za-z0-9._:-]{1,100}$/', $reference)) {
            throw new \InvalidArgumentException('Reference must contain 1-100 stable identifier characters.');
        }
        if ($allocation === false || $allocation < 1) {
            throw new \InvalidArgumentException('Seat allocation must be a positive integer.');
        }
        if ($validlength === false || $validlength < 0) {
            throw new \InvalidArgumentException('Valid length must be zero or a positive number of days.');
        }
        if ($type === false || !in_array($type, [0, 1, 2, 3, 4], true)) {
            throw new \InvalidArgumentException('License type must be an IOMAD type from 0 through 4.');
        }

        $company = $DB->get_record(
            'local_iomad_companies',
            ['shortname' => $companyshortname],
            'id,shortname',
            MUST_EXIST
        );
        $coursefield = $courseidnumber !== '' ? 'idnumber' : 'shortname';
        $coursevalue = $courseidnumber !== '' ? $courseidnumber : $courseshortname;
        $course = $DB->get_record('course', [$coursefield => $coursevalue], 'id,idnumber,shortname', MUST_EXIST);
        if (
            !$DB->record_exists('local_iomad_company_courses', [
            'companyid' => $company->id,
            'courseid' => $course->id,
            ])
        ) {
            throw new \InvalidArgumentException(
                'The course is not assigned to this company; assign it through an institution pack first.'
            );
        }
        if (
            !$DB->record_exists('local_iomad_courses', [
            'courseid' => $course->id,
            'licensed' => 1,
            ])
        ) {
            throw new \InvalidArgumentException(
                'The course is not configured for IOMAD license enrolment.'
            );
        }

        $startdate = $this->date((string)($input['startdate'] ?? ''), 'start date');
        $expirydate = $this->date((string)($input['expirydate'] ?? ''), 'expiry date');
        if ($expirydate <= $startdate) {
            throw new \InvalidArgumentException('Expiry date must be later than the start date.');
        }

        return [
            'name' => $name === '' ? 'Seat allocation ' . $reference : clean_param($name, PARAM_TEXT),
            'allocation' => $allocation,
            'validlength' => $validlength,
            'startdate' => $startdate,
            'expirydate' => $expirydate,
            'used' => 0,
            'companyid' => (int)$company->id,
            'parentid' => 0,
            'type' => $type,
            'program' => 0,
            'reference' => $reference,
            'instant' => !empty($input['instant']) ? 1 : 0,
            'clearonexpire' => !empty($input['clearonexpire']) ? 1 : 0,
            'cutoffdate' => 0,
            'courses' => [
                ['courseid' => (int)$course->id],
            ],
            '_company_shortname' => $company->shortname,
            '_course_key_type' => $coursefield,
            '_course_key' => $coursevalue,
        ];
    }

    /**
     * Parse a strict UTC date.
     *
     * @param string $value YYYY-MM-DD value.
     * @param string $label Error label.
     * @return int
     */
    private function date(string $value, string $label): int {
        $date = \DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            trim($value),
            new \DateTimeZone('UTC')
        );
        $errors = \DateTimeImmutable::getLastErrors();
        if (
            !$date
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== trim($value)
        ) {
            throw new \InvalidArgumentException("License {$label} must use YYYY-MM-DD.");
        }
        return $date->getTimestamp();
    }

    /**
     * Return stable, non-personal fields for output and audit.
     *
     * @param array $license Canonical license.
     * @return array
     */
    private function public_data(array $license): array {
        return [
            'company' => $license['_company_shortname'],
            'course_key_type' => $license['_course_key_type'],
            'course_key' => $license['_course_key'],
            'reference' => $license['reference'],
            'allocation' => $license['allocation'],
            'valid_length_days' => $license['validlength'],
            'start_date' => gmdate('Y-m-d', $license['startdate']),
            'expiry_date' => gmdate('Y-m-d', $license['expirydate']),
            'type' => $license['type'],
            'instant' => (bool)$license['instant'],
            'clear_on_expire' => (bool)$license['clearonexpire'],
        ];
    }
}
