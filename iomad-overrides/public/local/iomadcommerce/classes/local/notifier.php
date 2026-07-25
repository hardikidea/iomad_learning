<?php
// This file is part of Moodle - http://moodle.org/

namespace local_iomadcommerce\local;

/**
 * Privacy-minimised commerce notifications.
 *
 * @package    local_iomadcommerce
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class notifier {
    /**
     * Notify a learner about a seat state.
     *
     * @param int $userid Recipient.
     * @param string $provider Message provider.
     */
    public function send(int $userid, string $provider): void {
        global $DB;

        if (!in_array($provider, ['seatassigned', 'refundprocessed'], true)) {
            throw new \coding_exception('Unsupported commerce notification.');
        }
        $user = $DB->get_record('user', [
            'id' => $userid,
            'deleted' => 0,
            'suspended' => 0,
        ]);
        if (!$user) {
            return;
        }
        $message = new \core\message\message();
        $message->component = 'local_iomadcommerce';
        $message->name = $provider;
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $user;
        $message->subject = get_string($provider . 'subject', 'local_iomadcommerce');
        $message->fullmessage = get_string($provider . 'text', 'local_iomadcommerce');
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = '';
        $message->smallmessage = $message->subject;
        $message->notification = 1;
        $message->contexturl = (new \moodle_url('/local/iomadcommerce/purchases.php'))->out(false);
        $message->contexturlname = get_string('mycourses', 'local_iomadcommerce');
        message_send($message);
    }
}
