<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\local;

/**
 * Privacy-aware Tenant Master audit writer.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class audit_service {
    /** @var string[] */
    private const SENSITIVE_KEYS = [
        'password',
        'token',
        'secret',
        'privatekey',
        'accesskey',
        'email',
        'firstname',
        'lastname',
        'phone',
        'address',
    ];

    /**
     * Append an immutable audit record.
     *
     * @param int $tenantid Tenant.
     * @param string $action Action.
     * @param string $result Result.
     * @param array<string, mixed> $detail Non-sensitive detail.
     * @param array<string, int|string> $entity Entity references.
     * @return int
     */
    public function record(
        int $tenantid,
        string $action,
        string $result,
        array $detail = [],
        array $entity = [],
    ): int {
        global $DB, $USER;

        return $DB->insert_record('local_tenantmaster_audit', (object)[
            'tenantid' => $tenantid,
            'jobid' => (int)($entity['jobid'] ?? 0),
            'actorid' => (int)($USER->id ?? 0),
            'action' => $action,
            'entitytable' => $entity['entitytable'] ?? null,
            'entityid' => (int)($entity['entityid'] ?? 0),
            'targetcomponent' => $entity['targetcomponent'] ?? null,
            'targetid' => (int)($entity['targetid'] ?? 0),
            'result' => $result,
            'detailjson' => json::encode($this->redact($detail)),
            'ipaddress' => getremoteaddr() ?: null,
            'timecreated' => time(),
        ]);
    }

    /**
     * Redact sensitive keys recursively.
     *
     * @param mixed $value Value.
     * @return mixed
     */
    private function redact(mixed $value): mixed {
        if (!is_array($value)) {
            return $value;
        }
        foreach ($value as $key => $item) {
            $normalised = strtolower((string)$key);
            $sensitivevalue = false;
            foreach (self::SENSITIVE_KEYS as $sensitive) {
                if (str_contains($normalised, $sensitive)) {
                    $sensitivevalue = true;
                    break;
                }
            }
            if ($sensitivevalue) {
                $value[$key] = '[redacted]';
            } else {
                $value[$key] = $this->redact($item);
            }
        }
        return $value;
    }
}
