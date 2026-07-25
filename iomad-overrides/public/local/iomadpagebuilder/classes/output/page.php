<?php
// This file is part of IOMAD - http://www.iomad.org/

namespace local_iomadpagebuilder\output;

use local_iomadpagebuilder\page_repository;
use renderable;
use renderer_base;
use templatable;

defined('MOODLE_INTERNAL') || die();

/**
 * Safe template data for one page definition.
 */
final class page implements renderable, templatable {
    public function __construct(
        private readonly \stdClass $record,
        private readonly array $definition,
    ) {
    }

    public static function from_record(\stdClass $record): self {
        $repository = new page_repository();
        return new self($record, $repository->definition($record));
    }

    public function export_for_template(renderer_base $output): array {
        $sections = [];
        foreach ($this->definition['sections'] as $section) {
            $section['body'] = format_text($section['body'], FORMAT_HTML, [
                'noclean' => false,
                'para' => false,
            ]);
            $section['hasbody'] = $section['body'] !== '';
            $section['hasmedia'] = $section['mediaurl'] !== '';
            $section['hasprimary'] = $section['primarylabel'] !== '' && $section['primaryurl'] !== '';
            $section['hassecondary'] = $section['secondarylabel'] !== '' && $section['secondaryurl'] !== '';
            $section['hasitems'] = !empty($section['items']);
            $section['classes'] = 'iopb-component iopb-' . $section['type']
                . ' iopb-variant-' . $section['variant'];
            $sections[] = $section;
        }
        return [
            'name' => format_string($this->record->name),
            'uuid' => $this->record->uuid,
            'revision' => (int)$this->record->revision,
            'width' => $this->definition['settings']['width'],
            'density' => $this->definition['settings']['density'],
            'sections' => $sections,
        ];
    }
}
