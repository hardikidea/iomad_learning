<?php
// This file is part of Moodle - http://moodle.org/

namespace local_global_events\local;

/**
 * Moodle messaging gateway.
 *
 * @package local_global_events
 */
final class moodle_gateway implements gateway_interface {
    /**
     * Send through Moodle's message API.
     *
     * @param object $message Queue record.
     * @param array $variables Template variables.
     */
    public function deliver(object $message, array $variables): void {
        global $DB;

        $rendered = (new template_renderer())->render($message->templatekey, $variables);
        $recipient = $DB->get_record('user', [
            'id' => $message->userid,
            'deleted' => 0,
            'suspended' => 0,
        ], '*', MUST_EXIST);
        $notification = new \core\message\message();
        $notification->component = 'local_global_events';
        $notification->name = 'eventnotification';
        $notification->userfrom = \core_user::get_noreply_user();
        $notification->userto = $recipient;
        $notification->subject = $rendered['subject'];
        $notification->fullmessage = $rendered['body'];
        $notification->fullmessageformat = FORMAT_PLAIN;
        $notification->fullmessagehtml = '';
        $notification->smallmessage = $rendered['body'];
        $notification->notification = 1;
        if (!message_send($notification)) {
            throw new \moodle_exception('messagedeliveryfailed', 'local_global_events');
        }
    }
}
