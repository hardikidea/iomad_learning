<?php
// This file is part of Moodle - http://moodle.org/

namespace local_global_events\communication;

/**
 * Notification delivery contract.
 *
 * @package local_global_events
 */
interface gateway_interface {
    /**
     * Deliver a validated queued message.
     *
     * @param object $message Queue record.
     * @param array $variables Validated integer variables.
     */
    public function deliver(object $message, array $variables): void;
}
