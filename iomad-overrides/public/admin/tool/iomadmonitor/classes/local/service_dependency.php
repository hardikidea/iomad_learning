<?php
// This file is part of Moodle - http://moodle.org/

namespace tool_iomadmonitor\local;

/**
 * Validated service dependency edge.
 *
 * @package tool_iomadmonitor
 */
final class service_dependency {
    /**
     * Constructor.
     *
     * @param string $serviceid Consumer service ID.
     * @param string $dependencyid Required service ID.
     */
    public function __construct(
        public readonly string $serviceid,
        public readonly string $dependencyid,
    ) {
        foreach ([$serviceid, $dependencyid] as $id) {
            if (!preg_match('/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/', $id)) {
                throw new \InvalidArgumentException('Invalid service dependency ID.');
            }
        }
        if ($serviceid === $dependencyid) {
            throw new \InvalidArgumentException('A service cannot depend on itself.');
        }
    }
}
