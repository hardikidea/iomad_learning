<?php
// This file is part of Moodle - http://moodle.org/

namespace tool_iomadmonitor\local;

/**
 * Extension contract for project service catalogues.
 *
 * @package tool_iomadmonitor
 */
interface service_provider_interface {
    /**
     * Return owned service descriptors.
     *
     * @return service_descriptor[]
     */
    public function services(): array;
}
