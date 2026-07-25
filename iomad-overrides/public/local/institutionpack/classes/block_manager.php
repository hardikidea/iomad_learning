<?php
// This file is part of IOMAD - http://www.iomad.org/

namespace local_institutionpack;

defined('MOODLE_INTERNAL') || die();

/**
 * Constrained block placement through Moodle's block manager.
 */
final class block_manager {
    /** @var string[] Blocks reviewed for front-page placement. */
    private const SUPPORTED_BLOCKS = [
        'dash',
        'iomad_html',
        'iomaddashboard',
        'iomadpagebuilder',
        'gamification_telemetry',
    ];

    /**
     * List managed front-page block placements.
     *
     * @return array
     */
    public function listing(): array {
        global $DB;

        $records = $DB->get_records_select(
            'block_instances',
            'parentcontextid = :contextid AND pagetypepattern = :pagetype',
            [
                'contextid' => \context_system::instance()->id,
                'pagetype' => 'site-index',
            ],
            'defaultregion ASC, defaultweight ASC',
            'id,blockname,defaultregion,defaultweight,showinsubcontexts'
        );
        $blocks = [];
        foreach ($records as $record) {
            $blocks[] = [
                'blockname' => $record->blockname,
                'region' => $record->defaultregion,
                'weight' => (int)$record->defaultweight,
                'show_in_subcontexts' => (bool)$record->showinsubcontexts,
            ];
        }

        return [
            'ok' => true,
            'mode' => 'read',
            'page' => 'site-index',
            'blocks' => $blocks,
        ];
    }

    /**
     * Build a placement plan.
     *
     * @param array $input Raw command input.
     * @return array
     */
    public function plan(array $input): array {
        global $DB;

        $placement = $this->normalise($input);
        $exists = $DB->record_exists('block_instances', [
            'blockname' => $placement['blockname'],
            'parentcontextid' => \context_system::instance()->id,
            'pagetypepattern' => 'site-index',
            'defaultregion' => $placement['region'],
        ]);

        return [
            'ok' => true,
            'mode' => 'plan',
            'action' => $exists ? 'unchanged' : 'inject',
            'placement' => $placement,
            'message' => $exists ? 'A matching managed block placement already exists.' : '',
        ];
    }

    /**
     * Add a reviewed block through Moodle's block manager.
     *
     * @param array $input Raw command input.
     * @return array
     */
    public function apply(array $input): array {
        global $DB;

        $plan = $this->plan($input);
        if ($plan['action'] === 'unchanged') {
            $plan['mode'] = 'apply';
            return $plan;
        }

        $placement = $this->normalise($input);
        $page = new \moodle_page();
        $page->set_context(\context_system::instance());
        $page->set_pagetype('site-index');
        $page->set_url('/');
        $page->blocks->add_region($placement['region']);

        $transaction = $DB->start_delegated_transaction();
        $page->blocks->add_block(
            $placement['blockname'],
            $placement['region'],
            $placement['weight'],
            false,
            'site-index'
        );
        $transaction->allow_commit();

        $result = [
            'ok' => true,
            'mode' => 'apply',
            'action' => 'injected',
            'placement' => $placement,
        ];
        $result['audit_report'] = audit_log::write('block-inject', $result);
        return $result;
    }

    /**
     * Validate a constrained placement.
     *
     * @param array $input Raw input.
     * @return array
     */
    private function normalise(array $input): array {
        $blockname = trim((string)($input['blockname'] ?? ''));
        $region = trim((string)($input['region'] ?? 'content'));
        $weight = filter_var($input['weight'] ?? 0, FILTER_VALIDATE_INT);
        $page = trim((string)($input['page'] ?? 'site-index'));

        if (!in_array($blockname, self::SUPPORTED_BLOCKS, true)) {
            throw new \InvalidArgumentException(
                'Block is not in the reviewed front-page allowlist: ' . implode(', ', self::SUPPORTED_BLOCKS)
            );
        }
        if (\core_component::get_plugin_directory('block', $blockname) === null) {
            throw new \InvalidArgumentException('The requested block is not installed.');
        }
        if ($page !== 'site-index') {
            throw new \InvalidArgumentException('Only the stable site-index page target is supported.');
        }
        if ($region !== 'content') {
            throw new \InvalidArgumentException('Only the front-page content region is supported.');
        }
        if ($weight === false || $weight < -100 || $weight > 100) {
            throw new \InvalidArgumentException('Block weight must be between -100 and 100.');
        }

        return [
            'blockname' => $blockname,
            'page' => $page,
            'region' => $region,
            'weight' => $weight,
        ];
    }
}
