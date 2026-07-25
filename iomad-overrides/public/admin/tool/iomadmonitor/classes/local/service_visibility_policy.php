<?php
// This file is part of Moodle - http://moodle.org/

namespace tool_iomadmonitor\local;

/**
 * Authorisation policy for service catalogue records.
 *
 * @package tool_iomadmonitor
 */
final class service_visibility_policy {
    /**
     * Decide whether a caller may receive one service record.
     *
     * @param service_descriptor $service Service.
     * @param bool $authenticated Authenticated caller.
     * @param string[] $capabilities Granted capabilities.
     * @param bool $hascompanycontext Whether an active company context exists.
     * @return bool
     */
    public function can_view(
        service_descriptor $service,
        bool $authenticated,
        array $capabilities,
        bool $hascompanycontext,
    ): bool {
        $visibility = (string)$service->metadata('visibility');
        if ($visibility === 'public') {
            return true;
        }
        if (!$authenticated || $visibility === 'internal') {
            return false;
        }
        $capability = (string)$service->metadata('capability');
        if ($visibility === 'operator' && !in_array($capability, $capabilities, true)) {
            return false;
        }
        return $service->metadata('companyscope') !== 'current' || $hascompanycontext;
    }
}
