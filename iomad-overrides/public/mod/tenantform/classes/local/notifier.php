<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_tenantform\local;

/**
 * Notify same-company reviewers without exposing submission data in messages.
 *
 * @package    mod_tenantform
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class notifier {
    /**
     * Send submission notifications.
     *
     * @param object $form Form.
     * @param object $entry Entry.
     * @param \context_module $context Module context.
     * @param object $course Course.
     */
    public function submitted(
        object $form,
        object $entry,
        \context_module $context,
        object $course
    ): void {
        if (empty($form->notify)) {
            return;
        }
        $reviewers = get_users_by_capability(
            $context,
            'mod/tenantform:manageentries',
            'u.id,u.firstname,u.lastname,u.email,u.deleted,u.suspended',
        );
        $url = new \moodle_url('/mod/tenantform/entry.php', [
            'id' => $context->instanceid,
            'entryid' => $entry->id,
        ]);
        foreach ($reviewers as $reviewer) {
            if ($reviewer->deleted || $reviewer->suspended) {
                continue;
            }
            if (!tenant_access::user_in_company((int)$reviewer->id, (int)$form->companyid)) {
                continue;
            }
            $message = new \core\message\message();
            $message->component = 'mod_tenantform';
            $message->name = 'entrysubmitted';
            $message->userfrom = \core_user::get_noreply_user();
            $message->userto = $reviewer;
            $message->subject = get_string('notificationsubject', 'mod_tenantform', format_string($form->name));
            $message->fullmessage = get_string('notificationbody', 'mod_tenantform', format_string($form->name));
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = '';
            $message->smallmessage = $message->subject;
            $message->notification = 1;
            $message->courseid = $course->id;
            $message->contexturl = $url->out(false);
            $message->contexturlname = format_string($form->name);
            message_send($message);
        }
    }
}
