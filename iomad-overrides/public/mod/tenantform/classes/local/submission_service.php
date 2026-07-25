<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_tenantform\local;

use mod_tenantform\event\entry_submitted;

/**
 * Validate and atomically persist tenant form submissions.
 *
 * @package    mod_tenantform
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class submission_service {
    /** Maximum size accepted for one uploaded file. */
    private const MAX_FILE_BYTES = 10 * 1024 * 1024;

    /**
     * Constructor.
     *
     * @param entry_repository|null $repository Repository.
     * @param workflow_service|null $workflow Workflow.
     * @param notifier|null $notifier Notification service.
     */
    public function __construct(
        private readonly ?entry_repository $repository = null,
        private readonly ?workflow_service $workflow = null,
        private readonly ?notifier $notifier = null,
    ) {
    }

    /**
     * Submit validated data.
     *
     * @param object $form Form instance.
     * @param object $course Course.
     * @param object $cm Course module.
     * @param \context_module $context Context.
     * @param object $user User.
     * @param array $post Raw post data.
     * @param array $files Raw files data.
     * @return submission_result
     */
    public function submit(
        object $form,
        object $course,
        object $cm,
        \context_module $context,
        object $user,
        array $post,
        array $files
    ): submission_result {
        global $DB;

        $token = (string)($post['submissiontoken'] ?? '');
        if (!preg_match('/^[A-Za-z0-9]{32,64}$/', $token)) {
            throw new submission_validation_exception([
                '_form' => get_string('invalidsubmissiontoken', 'mod_tenantform'),
            ]);
        }
        $repository = $this->repository ?? new entry_repository();
        $existing = $repository->find_by_token((int)$form->id, $token);
        if ($existing) {
            return new submission_result($existing, false);
        }

        $definition = (new definition_validator())->from_json($form->definitionjson);
        [$values, $uploadfields, $errors] = $this->normalise($definition, $post, $files);
        if ($errors) {
            throw new submission_validation_exception($errors);
        }
        $json = json_encode(
            $values,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
        $entry = (object)[
            'tenantformid' => (int)$form->id,
            'companyid' => (int)$form->companyid,
            'userid' => isguestuser($user) ? 0 : (int)$user->id,
            'submissiontoken' => $token,
            'status' => 'submitted',
            'datajson' => $json,
            'checksum' => hash('sha256', $json),
            'filecount' => 0,
            'timecreated' => time(),
        ];

        $transaction = $DB->start_delegated_transaction();
        try {
            $entry = $repository->insert($entry);
            $entry->filecount = $this->store_files($entry, $uploadfields, $context);
            if ($entry->filecount > 0) {
                $DB->set_field('tenantform_entry', 'filecount', $entry->filecount, ['id' => $entry->id]);
            }
            ($this->workflow ?? new workflow_service())->apply($form, $entry);
            $event = entry_submitted::create([
                'objectid' => $entry->id,
                'context' => $context,
                'courseid' => $course->id,
                'userid' => $user->id,
            ]);
            $event->add_record_snapshot('tenantform_entry', $entry);
            $event->trigger();
            $transaction->allow_commit();
        } catch (\dml_write_exception $exception) {
            $transaction->rollback($exception);
        } catch (\Throwable $exception) {
            $transaction->rollback($exception);
        }

        try {
            ($this->notifier ?? new notifier())->submitted($form, $entry, $context, $course);
        } catch (\Throwable $exception) {
            debugging('Tenant form notification failed: ' . $exception->getMessage(), DEBUG_DEVELOPER);
        }
        return new submission_result($entry, true);
    }

    /**
     * Validate and normalise field values.
     *
     * @param array $definition Definition.
     * @param array $post Post.
     * @param array $files Files.
     * @return array Values, upload fields and errors.
     */
    public function normalise(array $definition, array $post, array $files): array {
        $values = [];
        $uploadfields = [];
        $errors = [];
        foreach ($definition['pages'] as $page) {
            foreach ($page['fields'] as $field) {
                if ($field['type'] === 'heading') {
                    continue;
                }
                if (!condition_evaluator::is_visible($field, $values)) {
                    continue;
                }
                $id = $field['id'];
                $name = 'field_' . $id;
                if ($field['type'] === 'file') {
                    $upload = $files[$name] ?? null;
                    $hasfile = is_array($upload)
                        && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
                    if ($field['required'] && !$hasfile) {
                        $errors[$id] = get_string('fieldrequired', 'mod_tenantform', $field['label']);
                    } else if ($hasfile) {
                        $fileerror = $this->validate_upload($upload);
                        if ($fileerror !== null) {
                            $errors[$id] = $fileerror;
                        } else {
                            $filename = clean_filename((string)$upload['name']);
                            $values[$id] = $filename;
                            $uploadfields[$id] = $upload;
                        }
                    }
                    continue;
                }

                $raw = $post[$name] ?? '';
                $value = $this->normalise_value($field, $raw);
                $missing = $value === '' || (
                    in_array($field['type'], ['checkbox', 'consent'], true) && $value !== '1'
                );
                if ($field['required'] && $missing) {
                    $errors[$id] = get_string('fieldrequired', 'mod_tenantform', $field['label']);
                    continue;
                }
                if ($value !== '') {
                    $valueerror = $this->validate_value($field, $value);
                    if ($valueerror !== null) {
                        $errors[$id] = $valueerror;
                        continue;
                    }
                }
                $values[$id] = $value;
            }
        }
        return [$values, $uploadfields, $errors];
    }

    /**
     * Normalise one scalar field.
     *
     * @param array $field Field.
     * @param mixed $raw Raw value.
     * @return string
     */
    private function normalise_value(array $field, mixed $raw): string {
        if (is_array($raw) || is_object($raw)) {
            return '';
        }
        return match ($field['type']) {
            'checkbox', 'consent' => empty($raw) ? '0' : '1',
            'textarea' => trim(clean_text((string)$raw, FORMAT_PLAIN)),
            'email' => trim(clean_param((string)$raw, PARAM_EMAIL)),
            'number' => trim(clean_param((string)$raw, PARAM_RAW_TRIMMED)),
            'date' => trim(clean_param((string)$raw, PARAM_ALPHANUMEXT)),
            default => trim(clean_param((string)$raw, PARAM_TEXT)),
        };
    }

    /**
     * Validate a normalised scalar value.
     *
     * @param array $field Field.
     * @param string $value Value.
     * @return string|null
     */
    private function validate_value(array $field, string $value): ?string {
        $label = $field['label'];
        if ($field['type'] === 'email' && !validate_email($value)) {
            return get_string('invalidemailfield', 'mod_tenantform', $label);
        }
        if ($field['type'] === 'date') {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            if (!$date || $date->format('Y-m-d') !== $value) {
                return get_string('invaliddatefield', 'mod_tenantform', $label);
            }
        }
        if ($field['type'] === 'number') {
            if (!is_numeric($value)) {
                return get_string('invalidnumberfield', 'mod_tenantform', $label);
            }
            $number = (float)$value;
            $belowminimum = isset($field['min']) && $number < $field['min'];
            $abovemaximum = isset($field['max']) && $number > $field['max'];
            if ($belowminimum || $abovemaximum) {
                return get_string('numberoutofrange', 'mod_tenantform', $label);
            }
        }
        $isoption = in_array($field['type'], ['select', 'radio'], true);
        if ($isoption && !in_array($value, $field['options'], true)) {
            return get_string('invalidoptionfield', 'mod_tenantform', $label);
        }
        $maxlength = $field['type'] === 'textarea' ? 10000 : 1000;
        if (\core_text::strlen($value) > $maxlength) {
            return get_string('fieldtoolong', 'mod_tenantform', $label);
        }
        return null;
    }

    /**
     * Validate an uploaded file envelope.
     *
     * @param array $upload Upload.
     * @return string|null
     */
    private function validate_upload(array $upload): ?string {
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return get_string('uploadfailed', 'mod_tenantform');
        }
        if (empty($upload['tmp_name']) || !is_readable($upload['tmp_name'])) {
            return get_string('uploadfailed', 'mod_tenantform');
        }
        if ((int)($upload['size'] ?? 0) <= 0 || (int)$upload['size'] > self::MAX_FILE_BYTES) {
            return get_string('uploadsize', 'mod_tenantform', display_size(self::MAX_FILE_BYTES));
        }
        if (clean_filename((string)($upload['name'] ?? '')) === '') {
            return get_string('uploadfailed', 'mod_tenantform');
        }
        return null;
    }

    /**
     * Move validated uploads into Moodle file storage.
     *
     * @param object $entry Entry.
     * @param array $uploads Upload fields.
     * @param \context_module $context Context.
     * @return int
     */
    private function store_files(object $entry, array $uploads, \context_module $context): int {
        $count = 0;
        $storage = get_file_storage();
        foreach ($uploads as $fieldid => $upload) {
            $filename = clean_filename((string)$upload['name']);
            $storage->create_file_from_pathname([
                'contextid' => $context->id,
                'component' => 'mod_tenantform',
                'filearea' => 'entry',
                'itemid' => $entry->id,
                'filepath' => '/' . $fieldid . '/',
                'filename' => $filename,
            ], $upload['tmp_name']);
            $count++;
        }
        return $count;
    }
}
