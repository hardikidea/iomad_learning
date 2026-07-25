<?php
// This file is part of Moodle - http://moodle.org/

namespace local_global_events\communication;

use local_global_events\local\moodle_gateway;
use local_global_events\local\whatsapp_gateway;

/**
 * Resolve allowlisted communication channels.
 *
 * @package local_global_events
 */
final class manager {
    /**
     * Deliver through an explicitly supported channel.
     *
     * @param object $message Queue record.
     * @param array $variables Validated variables.
     */
    public function deliver(object $message, array $variables): void {
        $this->gateway((string)$message->channel)->deliver($message, $variables);
    }

    /**
     * Resolve a gateway without dynamic class names.
     *
     * @param string $channel Channel.
     * @return gateway_interface
     */
    public function gateway(string $channel): gateway_interface {
        return match ($channel) {
            'moodle' => new moodle_gateway(),
            'whatsapp' => new whatsapp_gateway(),
            default => throw new \invalid_parameter_exception('Unsupported communication channel.'),
        };
    }
}
