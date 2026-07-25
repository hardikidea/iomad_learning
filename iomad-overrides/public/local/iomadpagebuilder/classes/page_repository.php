<?php
// This file is part of IOMAD - http://www.iomad.org/

namespace local_iomadpagebuilder;

defined('MOODLE_INTERNAL') || die();

/**
 * Transactional repository for tenant page definitions.
 */
final class page_repository {
    private const TARGETS = ['custompage', 'frontpage', 'dashboard', 'course'];

    public function __construct(
        private readonly definition_validator $validator = new definition_validator(),
    ) {
    }

    /**
     * List pages visible in a company scope.
     *
     * Shared pages are readable templates; tenant records override matching slugs.
     */
    public function list_for_company(int $companyid): array {
        global $DB;

        if ($companyid < 0) {
            throw new \invalid_parameter_exception('Invalid company id.');
        }
        return array_values($DB->get_records_select(
            'local_iomadpagebuilder_page',
            'companyid = :companyid OR companyid = 0',
            ['companyid' => $companyid],
            'companyid DESC, name ASC'
        ));
    }

    /**
     * Resolve a published page without crossing tenant scope.
     */
    public function get_published(int $companyid, string $target, int $targetid = 0, string $slug = ''): ?\stdClass {
        global $DB;

        $target = $this->target($target);
        $slug = clean_param($slug, PARAM_ALPHANUMEXT);
        $params = [
            'companyid' => $companyid,
            'target' => $target,
            'targetid' => $targetid,
            'status' => 'published',
        ];
        $slugsql = '';
        if ($slug !== '') {
            $slugsql = ' AND slug = :slug';
            $params['slug'] = $slug;
        }
        $sql = "SELECT *
                  FROM {local_iomadpagebuilder_page}
                 WHERE companyid = :companyid
                   AND target = :target
                   AND targetid = :targetid
                   AND status = :status
                       {$slugsql}
              ORDER BY timemodified DESC";
        $page = $DB->get_record_sql($sql, $params, IGNORE_MULTIPLE);
        if (!$page && $companyid !== 0) {
            $params['companyid'] = 0;
            $page = $DB->get_record_sql($sql, $params, IGNORE_MULTIPLE);
        }
        return $page ?: null;
    }

    /**
     * Get one page while enforcing its owner.
     */
    public function get(int $id, int $companyid, bool $allowshared = false): \stdClass {
        global $DB;

        $page = $DB->get_record('local_iomadpagebuilder_page', ['id' => $id], '*', MUST_EXIST);
        if ((int)$page->companyid !== $companyid && !($allowshared && (int)$page->companyid === 0)) {
            throw new \required_capability_exception(
                \context_system::instance(),
                'local/iomadpagebuilder:view',
                'nopermissions',
                ''
            );
        }
        return $page;
    }

    /**
     * Create or update a page and append an immutable revision.
     */
    public function save(array $input, int $companyid, int $userid): \stdClass {
        global $DB;

        if ($companyid < 0) {
            throw new \invalid_parameter_exception('Invalid company id.');
        }
        $definition = is_string($input['definition'] ?? null)
            ? $this->validator->from_json($input['definition'])
            : $this->validator->validate((array)($input['definition'] ?? []));
        $definitionjson = json_encode(
            $definition,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
        $checksum = hash('sha256', $definitionjson);
        $now = time();
        $id = (int)($input['id'] ?? 0);
        $slug = clean_param((string)($input['slug'] ?? ''), PARAM_ALPHANUMEXT);
        if ($slug === '') {
            throw new \invalid_parameter_exception('Page slug is required.');
        }
        if ($id === 0) {
            $id = (int)$DB->get_field('local_iomadpagebuilder_page', 'id', [
                'companyid' => $companyid,
                'slug' => $slug,
            ]);
        }

        $transaction = $DB->start_delegated_transaction();
        if ($id > 0) {
            $page = $this->get($id, $companyid);
            if (
                hash_equals($page->checksum, $checksum)
                    && $page->name === (string)$input['name']
                    && $page->slug === $slug
                    && $page->target === (string)$input['target']
                    && (int)$page->targetid === (int)($input['targetid'] ?? 0)
            ) {
                $transaction->allow_commit();
                return $page;
            }
            $page->revision = (int)$page->revision + 1;
            $page->modifiedby = $userid;
            $page->timemodified = $now;
        } else {
            $page = (object)[
                'uuid' => \core\uuid::generate(),
                'companyid' => $companyid,
                'status' => 'draft',
                'revision' => 1,
                'createdby' => $userid,
                'modifiedby' => $userid,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
        }

        $page->name = clean_param((string)($input['name'] ?? ''), PARAM_TEXT);
        if ($page->name === '') {
            throw new \invalid_parameter_exception('Page name is required.');
        }
        $page->slug = $slug;
        $page->description = clean_param((string)($input['description'] ?? ''), PARAM_TEXT);
        $page->target = $this->target((string)($input['target'] ?? 'custompage'));
        $page->targetid = max(0, (int)($input['targetid'] ?? 0));
        $page->definition = $definitionjson;
        $page->checksum = $checksum;

        if ($id > 0) {
            $DB->update_record('local_iomadpagebuilder_page', $page);
        } else {
            $page->id = $DB->insert_record('local_iomadpagebuilder_page', $page);
        }
        $this->append_revision($page, $userid, $now);
        $transaction->allow_commit();
        return $DB->get_record('local_iomadpagebuilder_page', ['id' => $page->id], '*', MUST_EXIST);
    }

    /**
     * Publish the current immutable revision.
     */
    public function publish(int $id, int $companyid, int $userid): \stdClass {
        global $DB;

        $page = $this->get($id, $companyid);
        if ($page->status === 'published') {
            return $page;
        }
        $page->status = 'published';
        $page->modifiedby = $userid;
        $page->timemodified = time();
        $page->timepublished = $page->timemodified;
        $DB->update_record('local_iomadpagebuilder_page', $page);
        return $page;
    }

    /**
     * Return a validated decoded definition.
     */
    public function definition(\stdClass $page): array {
        return $this->validator->from_json($page->definition);
    }

    private function append_revision(\stdClass $page, int $userid, int $now): void {
        global $DB;

        $DB->insert_record('local_iomadpagebuilder_rev', (object)[
            'pageid' => $page->id,
            'revision' => $page->revision,
            'definition' => $page->definition,
            'checksum' => $page->checksum,
            'createdby' => $userid,
            'timecreated' => $now,
        ]);
    }

    private function target(string $target): string {
        $target = clean_param($target, PARAM_ALPHA);
        if (!in_array($target, self::TARGETS, true)) {
            throw new \invalid_parameter_exception('Unsupported page target.');
        }
        return $target;
    }
}
