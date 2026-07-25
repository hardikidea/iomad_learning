<?php
// This file is part of Moodle - http://moodle.org/

namespace local_global_events\task;

use local_global_events\communication\manager;
use local_global_events\local\message_queue;

/**
 * Process the tenant notification queue.
 *
 * @package local_global_events
 */
final class process_messages extends \core\task\scheduled_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskmessages', 'local_global_events');
    }

    /**
     * Execute.
     */
    public function execute(): void {
        $queue = new message_queue();
        $communications = new manager();
        foreach ($queue->ready(50) as $message) {
            $span = class_exists('\tool_iomadmonitor\local\operation_span')
                ? new \tool_iomadmonitor\local\operation_span('notification.deliver', [
                    'component' => 'local_global_events',
                    'event' => $message->channel,
                ])
                : null;
            try {
                $variables = json_decode($message->payloadjson, true, 8, JSON_THROW_ON_ERROR);
                $communications->deliver($message, $variables);
                $queue->sent($message);
                $span?->finish('ok');
            } catch (\Throwable $exception) {
                $span?->finish('error');
                $code = $exception instanceof \moodle_exception
                    ? $exception->errorcode
                    : 'delivery_failed';
                $queue->failed($message, $code);
                if (class_exists('\tool_iomadmonitor\local\error_reporter')) {
                    (new \tool_iomadmonitor\local\error_reporter())->report(
                        'global_events.delivery_failed',
                        $exception,
                        ['component' => 'local_global_events'],
                    );
                }
            }
        }
    }
}
