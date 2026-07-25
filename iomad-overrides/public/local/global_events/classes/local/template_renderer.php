<?php
// This file is part of Moodle - http://moodle.org/

namespace local_global_events\local;

/**
 * Fixed notification templates.
 *
 * @package local_global_events
 */
final class template_renderer {
    /**
     * Render a known template.
     *
     * @param string $key Template key.
     * @param array $variables Integer variables.
     * @return array Subject and plain text.
     */
    public function render(string $key, array $variables): array {
        return match ($key) {
            'achievement' => [
                'subject' => get_string('messageachievement', 'local_global_events'),
                'body' => 'Your learning points total is ' . (int)($variables['points'] ?? 0) . '.',
            ],
            'event_reminder' => [
                'subject' => get_string('messageevent', 'local_global_events'),
                'body' => 'Event ' . (int)($variables['eventid'] ?? 0) . ' has an upcoming activity.',
            ],
            'chat_status' => [
                'subject' => get_string('messageachievement', 'local_global_events'),
                'body' => 'Your learning points total is ' . (int)($variables['points'] ?? 0)
                    . ' and your level is ' . (int)($variables['level'] ?? 0) . '.',
            ],
            'chat_badges' => [
                'subject' => get_string('messageachievement', 'local_global_events'),
                'body' => 'You have ' . (int)($variables['count'] ?? 0)
                    . ' earned badges. Open your secure IOMAD dashboard to view them.',
            ],
            'chat_certificates' => [
                'subject' => get_string('messageachievement', 'local_global_events'),
                'body' => 'You have ' . (int)($variables['count'] ?? 0)
                    . ' certificates. Open your secure IOMAD dashboard to view certificate codes.',
            ],
            'chat_help' => [
                'subject' => get_string('messageevent', 'local_global_events'),
                'body' => 'Available commands: STATUS, MY BADGES, MY CODES, HELP.',
            ],
            default => throw new \invalid_parameter_exception('Unknown notification template.'),
        };
    }
}
