<?php
// This file is part of IOMAD - http://www.iomad.org/

namespace local_aicoursecreator;

defined('MOODLE_INTERNAL') || die();

/**
 * Provider-neutral AI generation contract.
 */
interface ai_gateway {
    /**
     * Generate and validate a course definition.
     *
     * @return array{definition: array, provider: ?string, model: ?string}
     */
    public function generate(\stdClass $draft, int $contextid, int $userid): array;
}
