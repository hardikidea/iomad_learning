<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_tenantform\local;

/**
 * Maintained form-definition templates.
 *
 * Templates are copied into an activity definition and may then be edited.
 *
 * @package    mod_tenantform
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class template_catalog {
    /**
     * Return localised template names.
     *
     * @return array
     */
    public static function names(): array {
        return [
            'custom' => get_string('templatecustom', 'mod_tenantform'),
            'contact' => get_string('templatecontact', 'mod_tenantform'),
            'survey' => get_string('templatesurvey', 'mod_tenantform'),
            'feedback' => get_string('templatefeedback', 'mod_tenantform'),
            'course_enrolment' => get_string('templatecourseenrolment', 'mod_tenantform'),
            'registration_request' => get_string('templateregistration', 'mod_tenantform'),
            'incident' => get_string('templateincident', 'mod_tenantform'),
            'file_upload' => get_string('templatefileupload', 'mod_tenantform'),
            'application' => get_string('templateapplication', 'mod_tenantform'),
        ];
    }

    /**
     * Return a canonical schema for a template.
     *
     * @param string $key Template key.
     * @return array
     */
    public static function definition(string $key): array {
        $definitions = self::definitions();
        if (!isset($definitions[$key])) {
            throw new \invalid_parameter_exception('Unknown tenant form template.');
        }
        return $definitions[$key];
    }

    /**
     * Return all template schemas.
     *
     * @return array
     */
    private static function definitions(): array {
        $base = [
            'schema_version' => 1,
            'pages' => [],
        ];
        return [
            'custom' => array_replace($base, [
                'pages' => [
                    self::page('details', 'Details', [
                        self::field('subject', 'text', 'Subject', true),
                        self::field('message', 'textarea', 'Message', true),
                    ]),
                ],
            ]),
            'contact' => array_replace($base, [
                'pages' => [
                    self::page('contact', 'Contact details', [
                        self::field('name', 'text', 'Name', true),
                        self::field('email', 'email', 'Email address', true),
                        self::field('topic', 'select', 'Topic', true, [
                            'options' => ['Admissions', 'Course support', 'Technical support', 'Other'],
                        ]),
                        self::field('message', 'textarea', 'Message', true),
                    ]),
                ],
            ]),
            'survey' => array_replace($base, [
                'pages' => [
                    self::page('experience', 'Your experience', [
                        self::field('rating', 'radio', 'Overall rating', true, [
                            'options' => ['Excellent', 'Good', 'Fair', 'Poor'],
                        ]),
                        self::field('recommend', 'radio', 'Would you recommend this course?', true, [
                            'options' => ['Yes', 'No'],
                        ]),
                        self::field('reason', 'textarea', 'What influenced your answer?', false, [
                            'condition' => ['field' => 'recommend', 'operator' => 'equals', 'value' => 'No'],
                        ]),
                        self::field('comments', 'textarea', 'Additional comments'),
                    ]),
                ],
            ]),
            'feedback' => array_replace($base, [
                'pages' => [
                    self::page('feedback', 'Feedback', [
                        self::field('area', 'select', 'Feedback area', true, [
                            'options' => ['Content', 'Teaching', 'Assessment', 'Platform', 'Other'],
                        ]),
                        self::field('helpful', 'radio', 'Was this helpful?', true, [
                            'options' => ['Yes', 'Partly', 'No'],
                        ]),
                        self::field('comments', 'textarea', 'Comments', true),
                        self::field('followup', 'checkbox', 'I would like a follow-up'),
                        self::field('email', 'email', 'Contact email', true, [
                            'condition' => ['field' => 'followup', 'operator' => 'equals', 'value' => '1'],
                        ]),
                    ]),
                ],
            ]),
            'course_enrolment' => array_replace($base, [
                'pages' => [
                    self::page('request', 'Enrolment request', [
                        self::field('motivation', 'textarea', 'Why do you want to join this course?', true),
                        self::field('experience', 'select', 'Current experience', true, [
                            'options' => ['New to the subject', 'Some experience', 'Experienced'],
                        ]),
                        self::field('terms', 'consent', 'I confirm that the information is accurate', true),
                    ]),
                ],
            ]),
            'registration_request' => array_replace($base, [
                'pages' => [
                    self::page('identity', 'Your details', [
                        self::field('firstname', 'text', 'First name', true),
                        self::field('lastname', 'text', 'Last name', true),
                        self::field('email', 'email', 'Email address', true),
                    ]),
                    self::page('organisation', 'Organisation', [
                        self::field('organisation', 'text', 'Organisation name'),
                        self::field('role', 'text', 'Role or position'),
                        self::field('reason', 'textarea', 'Reason for requesting access', true),
                        self::field('privacy', 'consent', 'I agree to the privacy notice', true),
                    ]),
                ],
            ]),
            'incident' => array_replace($base, [
                'pages' => [
                    self::page('incident', 'Incident details', [
                        self::field('category', 'select', 'Category', true, [
                            'options' => ['Safeguarding', 'Security', 'Service', 'Academic', 'Other'],
                        ]),
                        self::field('occurred', 'date', 'Date of incident', true),
                        self::field('summary', 'textarea', 'What happened?', true),
                        self::field('urgent', 'checkbox', 'This requires urgent review'),
                        self::field('evidence', 'file', 'Supporting file'),
                    ]),
                ],
            ]),
            'file_upload' => array_replace($base, [
                'pages' => [
                    self::page('upload', 'Submit a file', [
                        self::field('title', 'text', 'Document title', true),
                        self::field('description', 'textarea', 'Description'),
                        self::field('document', 'file', 'File', true),
                        self::field('declaration', 'consent', 'I am permitted to submit this file', true),
                    ]),
                ],
            ]),
            'application' => array_replace($base, [
                'pages' => [
                    self::page('profile', 'Profile', [
                        self::field('fullname', 'text', 'Full name', true),
                        self::field('email', 'email', 'Email address', true),
                        self::field('phone', 'text', 'Phone number'),
                    ]),
                    self::page('study', 'Study preferences', [
                        self::field('programme', 'text', 'Programme or course', true),
                        self::field('study_mode', 'radio', 'Study mode', true, [
                            'options' => ['On campus', 'Online', 'Blended'],
                        ]),
                        self::field('support', 'checkbox', 'I need accessibility support'),
                        self::field('support_details', 'textarea', 'Accessibility requirements', true, [
                            'condition' => ['field' => 'support', 'operator' => 'equals', 'value' => '1'],
                        ]),
                    ]),
                    self::page('declaration', 'Declaration', [
                        self::field('statement', 'textarea', 'Personal statement', true),
                        self::field('attachment', 'file', 'Supporting document'),
                        self::field('consent', 'consent', 'I confirm this application is complete', true),
                    ]),
                ],
            ]),
        ];
    }

    /**
     * Build a page.
     *
     * @param string $id Stable ID.
     * @param string $title Title.
     * @param array $fields Fields.
     * @return array
     */
    private static function page(string $id, string $title, array $fields): array {
        return ['id' => $id, 'title' => $title, 'fields' => $fields];
    }

    /**
     * Build a field.
     *
     * @param string $id Stable ID.
     * @param string $type Type.
     * @param string $label Label.
     * @param bool $required Required.
     * @param array $extra Extra options.
     * @return array
     */
    private static function field(
        string $id,
        string $type,
        string $label,
        bool $required = false,
        array $extra = []
    ): array {
        return ['id' => $id, 'type' => $type, 'label' => $label, 'required' => $required] + $extra;
    }
}
