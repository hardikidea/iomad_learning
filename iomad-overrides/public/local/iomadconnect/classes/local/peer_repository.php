<?php
// This file is part of Moodle - http://moodle.org/

namespace local_iomadconnect\local;

/**
 * Store integration peers without credentials.
 *
 * @package    local_iomadconnect
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class peer_repository {
    /**
     * Create or update a tenant peer.
     *
     * @param int $companyid Company.
     * @param string $externalid Peer key.
     * @param string $baseurl HTTPS base URL.
     * @param string $keyid Environment secret key ID.
     * @param string $status Status.
     * @return object
     */
    public function upsert(
        int $companyid,
        string $externalid,
        string $baseurl,
        string $keyid,
        string $status = 'disabled',
    ): object {
        global $DB;

        $externalid = trim($externalid);
        $baseurl = rtrim(trim($baseurl), '/');
        $keyid = trim($keyid);
        if (
            !$DB->record_exists('local_iomad_companies', ['id' => $companyid])
            || !preg_match('/^[A-Za-z0-9_.-]{3,100}$/', $externalid)
            || !preg_match('/^[A-Za-z0-9_.:-]{3,100}$/', $keyid)
            || !in_array($status, ['enabled', 'disabled'], true)
            || !$this->valid_baseurl($baseurl)
        ) {
            throw new \invalid_parameter_exception('Invalid synchronization peer configuration.');
        }
        $conditions = ['companyid' => $companyid, 'externalid' => $externalid];
        $record = $DB->get_record('local_iomadconnect_peer', $conditions);
        $now = time();
        if ($record) {
            $record->baseurl = $baseurl;
            $record->keyid = $keyid;
            $record->status = $status;
            $record->timemodified = $now;
            $DB->update_record('local_iomadconnect_peer', $record);
            return $record;
        }
        $record = (object)($conditions + [
            'baseurl' => $baseurl,
            'keyid' => $keyid,
            'status' => $status,
            'cursorvalue' => '',
            'lastsuccess' => 0,
            'lastfailure' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $record->id = $DB->insert_record('local_iomadconnect_peer', $record);
        return $record;
    }

    /**
     * Get a peer.
     *
     * @param int $companyid Company.
     * @param string $externalid Peer.
     * @return object
     */
    public function get(int $companyid, string $externalid): object {
        global $DB;

        return $DB->get_record('local_iomadconnect_peer', [
            'companyid' => $companyid,
            'externalid' => $externalid,
        ], '*', MUST_EXIST);
    }

    /**
     * Record a connectivity result.
     *
     * @param int $peerid Peer.
     * @param bool $success Result.
     * @param string|null $cursor New cursor.
     */
    public function mark_result(int $peerid, bool $success, ?string $cursor = null): void {
        global $DB;

        $record = $DB->get_record('local_iomadconnect_peer', ['id' => $peerid], '*', MUST_EXIST);
        $field = $success ? 'lastsuccess' : 'lastfailure';
        $record->{$field} = time();
        $record->timemodified = time();
        if ($success && $cursor !== null) {
            $record->cursorvalue = clean_param($cursor, PARAM_RAW_TRIMMED);
        }
        $DB->update_record('local_iomadconnect_peer', $record);
    }

    /**
     * Resolve one token from an injected JSON map.
     *
     * @param string $keyid Key ID.
     * @return string
     */
    public function token_for(string $keyid): string {
        $raw = getenv('IOMAD_CONNECT_TOKENS_JSON') ?: '';
        try {
            $tokens = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \moodle_exception('connecttokensinvalid', 'local_iomadconnect');
        }
        $token = is_array($tokens) ? (string)($tokens[$keyid] ?? '') : '';
        if (!preg_match('/^[A-Za-z0-9]{16,128}$/', $token)) {
            throw new \moodle_exception('connecttokenmissing', 'local_iomadconnect');
        }
        return $token;
    }

    /**
     * Check a base URL.
     *
     * @param string $baseurl URL.
     * @return bool
     */
    private function valid_baseurl(string $baseurl): bool {
        if (!filter_var($baseurl, FILTER_VALIDATE_URL)) {
            return false;
        }
        $parts = parse_url($baseurl);
        if (($parts['scheme'] ?? '') === 'https') {
            return true;
        }
        $localhost = in_array($parts['host'] ?? '', ['localhost', '127.0.0.1', '::1'], true);
        return $localhost && (bool)get_config('local_iomadconnect', 'allowinsecurelocal');
    }
}
