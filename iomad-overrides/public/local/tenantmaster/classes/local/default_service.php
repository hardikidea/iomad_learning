<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\local;

/**
 * Versioned in-plugin defaults and non-destructive tenant adoption.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class default_service {
    private const VERSION = '2026.1';

    /**
     * Register immutable defaults and selectively copy them to a tenant.
     *
     * Existing tenant values are never changed.
     *
     * @param object $tenant Tenant.
     * @return array{created: int, existing: int}
     */
    public function adopt(object $tenant): array {
        global $DB;

        $academicyear = (new academic_year_service())->ensure_current($tenant);
        $tenant->activeyearid = (int)$academicyear->id;
        $items = array_merge($this->shared_defaults(), $this->type_defaults((string)$tenant->tenanttype));
        $setid = $this->register_set((string)$tenant->tenanttype, $items);
        $result = ['created' => 0, 'existing' => 0];
        $repository = new master_repository();
        $queue = new queue_service();
        $createdids = [];
        $transaction = $DB->start_delegated_transaction();
        foreach ($items as $sortorder => $item) {
            if (
                $DB->record_exists('local_tenantmaster_master', [
                    'tenantid' => $tenant->id,
                    'mastertype' => $item['type'],
                    'externalid' => $item['externalid'],
                ])
            ) {
                $result['existing']++;
                continue;
            }
            $record = $repository->save((object)[
                'tenantid' => $tenant->id,
                'acadyearid' => 0,
                'parentid' => 0,
                'mastertype' => $item['type'],
                'externalid' => $item['externalid'],
                'code' => $item['code'],
                'name' => $item['name'],
                'description' => $item['description'] ?? null,
                'payloadjson' => json::encode($item['payload'] ?? []),
                'active' => 1,
                'sortorder' => $sortorder + 1,
            ]);
            $createdids[$item['externalid']] = (int)$record->id;
            foreach ($this->modules_for_type($item['type']) as $module) {
                $queue->mark_dirty(
                    (int)$tenant->id,
                    $module,
                    'local_tenantmaster_master',
                    (int)$record->id,
                    'default_adopted',
                );
                if (in_array($module, ['assessments', 'attendance', 'certificates'], true)) {
                    $queue->queue_company_courses((int)$tenant->id, $module, 'default_policy_adopted');
                }
            }
            $result['created']++;
        }
        foreach ($items as $item) {
            $parentexternalid = (string)($item['parentexternalid'] ?? '');
            if ($parentexternalid === '' || !isset($createdids[$item['externalid']])) {
                continue;
            }
            $child = $repository->get((int)$tenant->id, $createdids[$item['externalid']]);
            $parentid = (int)$DB->get_field('local_tenantmaster_master', 'id', [
                'tenantid' => $tenant->id,
                'externalid' => $parentexternalid,
            ]);
            if ($parentid <= 0 || (int)$child->parentid === $parentid) {
                continue;
            }
            $child->parentid = $parentid;
            $repository->save($child);
            foreach ($this->modules_for_type((string)$child->mastertype) as $module) {
                $queue->mark_dirty(
                    (int)$tenant->id,
                    $module,
                    'local_tenantmaster_master',
                    (int)$child->id,
                    'default_parent_adopted',
                );
            }
        }
        $tenant->defaultversion = self::VERSION;
        $tenant->timemodified = time();
        $DB->update_record('local_tenantmaster_tenant', $tenant);
        $transaction->allow_commit();
        return $result;
    }

    /**
     * Compare current tenant values to the latest defaults.
     *
     * @param object $tenant Tenant.
     * @return array{available: int, adopted: int, version: string}
     */
    public function compare(object $tenant): array {
        global $DB;

        $items = array_merge($this->shared_defaults(), $this->type_defaults((string)$tenant->tenanttype));
        $adopted = 0;
        foreach ($items as $item) {
            if (
                $DB->record_exists('local_tenantmaster_master', [
                    'tenantid' => $tenant->id,
                    'mastertype' => $item['type'],
                    'externalid' => $item['externalid'],
                ])
            ) {
                $adopted++;
            }
        }
        return ['available' => count($items), 'adopted' => $adopted, 'version' => self::VERSION];
    }

    /**
     * Register immutable set and item definitions.
     *
     * @param string $tenanttype Tenant type.
     * @param array<int, array<string, mixed>> $items Items.
     * @return int
     */
    private function register_set(string $tenanttype, array $items): int {
        global $DB;

        $code = 'tenantmaster_' . $tenanttype;
        $set = $DB->get_record('local_tenantmaster_defset', ['code' => $code, 'version' => self::VERSION]);
        if ($set) {
            return (int)$set->id;
        }
        $checksum = json::hash($items);
        $setid = (int)$DB->insert_record('local_tenantmaster_defset', (object)[
            'code' => $code,
            'version' => self::VERSION,
            'tenanttype' => $tenanttype,
            'name' => ucfirst($tenanttype) . ' defaults ' . self::VERSION,
            'checksum' => $checksum,
            'active' => 1,
            'timecreated' => time(),
        ]);
        foreach ($items as $sortorder => $item) {
            $DB->insert_record('local_tenantmaster_defitem', (object)[
                'defsetid' => $setid,
                'entitytype' => $item['type'],
                'externalid' => $item['externalid'],
                'parentexternalid' => $item['parentexternalid'] ?? null,
                'payloadjson' => json::encode($item),
                'sortorder' => $sortorder + 1,
                'active' => 1,
            ]);
        }
        return $setid;
    }

    /**
     * Shared defaults.
     *
     * @return array<int, array<string, mixed>>
     */
    private function shared_defaults(): array {
        return [
            [
                'type' => 'assessment_policy',
                'externalid' => 'ASSESSMENT_DEFAULT',
                'code' => 'ASSESSMENT_DEFAULT',
                'name' => 'Default assessment policy',
                'payload' => ['aggregation' => 'weighted_mean', 'passpercent' => 40],
            ],
            [
                'type' => 'attendance_policy',
                'externalid' => 'ATTENDANCE_DEFAULT',
                'code' => 'ATTENDANCE_DEFAULT',
                'name' => 'Default attendance policy',
                'payload' => ['minimumpercent' => 75, 'warningpercent' => 80],
            ],
            [
                'type' => 'certificate_rule',
                'externalid' => 'CERTIFICATE_DEFAULT',
                'code' => 'CERTIFICATE_DEFAULT',
                'name' => 'Default completion certificate',
                'payload' => ['requirescompletion' => true, 'savecertificate' => true],
            ],
            [
                'type' => 'progression_rule',
                'externalid' => 'PROGRESSION_DEFAULT',
                'code' => 'PROGRESSION_DEFAULT',
                'name' => 'Default progression rule',
                'payload' => ['minimumgrade' => 40, 'minimumattendance' => 75],
            ],
        ];
    }

    /**
     * Tenant-type defaults.
     *
     * @param string $tenanttype Type.
     * @return array<int, array<string, mixed>>
     */
    private function type_defaults(string $tenanttype): array {
        return match ($tenanttype) {
            'school' => $this->school_defaults(),
            'university', 'college' => $this->university_defaults(),
            default => $this->training_defaults(),
        };
    }

    /**
     * School defaults.
     *
     * @return array<int, array<string, mixed>>
     */
    private function school_defaults(): array {
        $items = [];
        foreach (
            [
            'CBSE' => 'Central Board of Secondary Education',
            'CISCE' => 'Council for the Indian School Certificate Examinations',
            'STATE' => 'State Board',
            'IB' => 'International Baccalaureate',
            ] as $code => $name
        ) {
            $items[] = ['type' => 'board', 'externalid' => 'BOARD_' . $code, 'code' => $code, 'name' => $name];
        }
        foreach (['English', 'Hindi', 'Gujarati'] as $name) {
            $code = strtoupper($name);
            $items[] = ['type' => 'medium', 'externalid' => 'MEDIUM_' . $code, 'code' => $code, 'name' => $name];
        }
        $grades = ['Nursery', 'LKG', 'UKG'];
        for ($grade = 1; $grade <= 12; $grade++) {
            $grades[] = 'Standard ' . $grade;
        }
        foreach ($grades as $order => $name) {
            $code = $order < 3 ? strtoupper($name) : 'STD_' . ($order - 2);
            $items[] = [
                'type' => 'grade',
                'externalid' => 'GRADE_' . $code,
                'code' => $code,
                'name' => $name,
                'payload' => ['order' => $order + 1],
            ];
        }
        foreach (['Science', 'Commerce', 'Humanities'] as $name) {
            $code = strtoupper($name);
            $items[] = ['type' => 'stream', 'externalid' => 'STREAM_' . $code, 'code' => $code, 'name' => $name];
        }
        foreach (
            [
            'English', 'Hindi', 'Gujarati', 'Mathematics', 'Environmental Studies',
            'Science', 'Social Science', 'Physics', 'Chemistry', 'Biology',
            'Computer Science', 'Accountancy', 'Business Studies', 'Economics',
            'Political Science', 'History', 'Geography', 'Physical Education',
            ] as $name
        ) {
            $code = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', $name));
            $items[] = [
                'type' => 'subject',
                'externalid' => 'SUBJECT_' . $code,
                'code' => $code,
                'name' => $name,
                'payload' => ['courseformat' => 'topics'],
            ];
        }
        foreach (range('A', 'D') as $division) {
            $items[] = [
                'type' => 'division',
                'externalid' => 'DIVISION_' . $division,
                'code' => $division,
                'name' => 'Division ' . $division,
            ];
        }
        return $items;
    }

    /**
     * University and college defaults.
     *
     * @return array<int, array<string, mixed>>
     */
    private function university_defaults(): array {
        $items = [];
        foreach (
            [
            'SCIENCE' => 'Faculty of Science',
            'ENGINEERING' => 'Faculty of Engineering and Technology',
            'COMMERCE' => 'Faculty of Commerce',
            'ARTS' => 'Faculty of Arts and Humanities',
            'MANAGEMENT' => 'Faculty of Management',
            ] as $code => $name
        ) {
            $items[] = [
                'type' => 'programme',
                'externalid' => 'FACULTY_' . $code,
                'code' => 'FAC_' . $code,
                'name' => $name,
                'payload' => ['level' => 'faculty'],
            ];
        }
        foreach (
            [
            'BSC_CS' => ['Bachelor of Science in Computer Science', 'FACULTY_SCIENCE'],
            'BTECH_CSE' => ['Bachelor of Technology in Computer Science', 'FACULTY_ENGINEERING'],
            'BCOM' => ['Bachelor of Commerce', 'FACULTY_COMMERCE'],
            'BA' => ['Bachelor of Arts', 'FACULTY_ARTS'],
            'MBA' => ['Master of Business Administration', 'FACULTY_MANAGEMENT'],
            ] as $code => [$name, $parentexternalid]
        ) {
            $items[] = [
                'type' => 'programme',
                'externalid' => 'PROGRAMME_' . $code,
                'code' => $code,
                'name' => $name,
                'parentexternalid' => $parentexternalid,
                'payload' => ['level' => 'programme'],
            ];
        }
        for ($semester = 1; $semester <= 8; $semester++) {
            $items[] = [
                'type' => 'semester',
                'externalid' => 'SEMESTER_' . $semester,
                'code' => 'SEM_' . $semester,
                'name' => 'Semester ' . $semester,
                'payload' => ['order' => $semester],
            ];
        }
        foreach (
            [
            'Programming Fundamentals', 'Data Structures', 'Database Systems',
            'Computer Networks', 'Operating Systems', 'Software Engineering',
            'Business Economics', 'Financial Accounting', 'Business Communication',
            'Research Methodology', 'Environmental Studies', 'Professional Ethics',
            ] as $name
        ) {
            $code = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', $name));
            $items[] = [
                'type' => 'subject',
                'externalid' => 'COURSE_' . $code,
                'code' => $code,
                'name' => $name,
                'payload' => ['credits' => 4, 'courseformat' => 'topics'],
            ];
        }
        foreach ([1, 2, 3, 4, 6] as $credit) {
            $items[] = [
                'type' => 'credit',
                'externalid' => 'CREDIT_' . $credit,
                'code' => 'CR_' . $credit,
                'name' => $credit . ' credit',
                'payload' => ['value' => $credit],
            ];
        }
        return $items;
    }

    /**
     * Training defaults.
     *
     * @return array<int, array<string, mixed>>
     */
    private function training_defaults(): array {
        return [
            [
                'type' => 'programme',
                'externalid' => 'PROGRAMME_COMPLIANCE',
                'code' => 'COMPLIANCE',
                'name' => 'Compliance training',
            ],
            [
                'type' => 'course_template',
                'externalid' => 'TEMPLATE_STANDARD',
                'code' => 'STANDARD_TEMPLATE',
                'name' => 'Standard training template',
            ],
        ];
    }

    /**
     * Projection modules for adopted default.
     *
     * @param string $type Type.
     * @return string[]
     */
    private function modules_for_type(string $type): array {
        return match ($type) {
            'subject', 'course_template' => ['academic', 'courses'],
            'assessment_policy' => ['assessments'],
            'attendance_policy' => ['attendance'],
            'certificate_rule' => ['certificates'],
            'progression_rule' => ['progression'],
            'credit' => ['academic'],
            default => ['academic', 'categories'],
        };
    }
}
