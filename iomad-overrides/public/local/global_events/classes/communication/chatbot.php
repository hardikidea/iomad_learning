<?php
// This file is part of Moodle - http://moodle.org/

namespace local_global_events\communication;

use local_global_events\local\badge_service;
use local_global_events\local\certificate_service;
use local_global_events\local\gamification_service;
use local_global_events\local\tenant_scope;

/**
 * Fixed-command conversational learning projection.
 *
 * @package local_global_events
 */
final class chatbot {
    /**
     * Resolve a command to a fixed template and integer-only variables.
     *
     * @param tenant_scope $scope Company scope.
     * @param int $userid Learner.
     * @param string $command User command.
     * @return array
     */
    public function plan(tenant_scope $scope, int $userid, string $command): array {
        $command = strtoupper(trim($command));
        return match ($command) {
            'STATUS' => $this->status($scope, $userid),
            'MY BADGES' => [
                'template' => 'chat_badges',
                'variables' => ['count' => count((new badge_service())->earned($scope, $userid, 100))],
            ],
            'MY CODES' => [
                'template' => 'chat_certificates',
                'variables' => ['count' => (new certificate_service())->count_earned($scope, $userid)],
            ],
            default => ['template' => 'chat_help', 'variables' => []],
        };
    }

    /**
     * Own progress response.
     *
     * @param tenant_scope $scope Scope.
     * @param int $userid User.
     * @return array
     */
    private function status(tenant_scope $scope, int $userid): array {
        $progress = (new gamification_service())->progress($scope, $userid);
        return [
            'template' => 'chat_status',
            'variables' => ['points' => $progress['points'], 'level' => $progress['level']],
        ];
    }
}
