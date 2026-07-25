<?php
// This file is part of IOMAD - http://www.iomad.org/

use local_iomadpagebuilder\output\page as page_output;
use local_iomadpagebuilder\page_repository;
use local_iomadpagebuilder\tenant_context;

defined('MOODLE_INTERNAL') || die();

/**
 * Renders a published tenant page definition.
 */
final class block_iomadpagebuilder extends block_base {
    public function init(): void {
        $this->title = get_string('pluginname', 'block_iomadpagebuilder');
    }

    public function applicable_formats(): array {
        return [
            'site-index' => true,
            'my' => true,
            'course-view' => true,
            'local-iomadcustompage-view' => true,
            'all' => false,
        ];
    }

    public function instance_allow_multiple(): bool {
        return true;
    }

    public function has_config(): bool {
        return false;
    }

    public function get_content(): ?stdClass {
        if ($this->content !== null) {
            return $this->content;
        }

        $companyid = tenant_context::companyid(false);
        $target = $this->config->pagetarget ?? $this->target_from_page();
        $slug = clean_param((string)($this->config->pageslug ?? ''), PARAM_ALPHANUMEXT);
        $targetid = $target === 'course' ? (int)($this->page->course->id ?? 0) : 0;
        $record = (new page_repository())->get_published($companyid, $target, $targetid, $slug);

        $this->content = new stdClass();
        $this->content->footer = '';
        if (!$record) {
            $this->content->text = has_capability('block/iomadpagebuilder:addinstance', $this->context)
                ? get_string('nothingpublished', 'block_iomadpagebuilder') : '';
            return $this->content;
        }

        $renderer = $this->page->get_renderer('core');
        $this->content->text = $renderer->render_from_template(
            'local_iomadpagebuilder/page',
            page_output::from_record($record)->export_for_template($renderer)
        );
        return $this->content;
    }

    private function target_from_page(): string {
        if ($this->page->pagetype === 'site-index') {
            return 'frontpage';
        }
        if (str_starts_with($this->page->pagetype, 'course-view')) {
            return 'course';
        }
        if ($this->page->pagetype === 'my-index') {
            return 'dashboard';
        }
        return 'custompage';
    }
}
