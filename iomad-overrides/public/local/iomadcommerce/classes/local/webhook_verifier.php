<?php
// This file is part of Moodle - http://moodle.org/

namespace local_iomadcommerce\local;

/**
 * Verify timestamped HMAC commerce callbacks.
 *
 * @package    local_iomadcommerce
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class webhook_verifier {
    /** @var int Maximum callback clock skew. */
    public const MAX_AGE = 300;

    /**
     * Verify a callback without exposing the secret or payload.
     *
     * @param string $rawbody Raw body.
     * @param int $timestamp Unix timestamp.
     * @param string $nonce Unique nonce.
     * @param string $signature Hex HMAC.
     * @param string $secret Secret.
     * @param int|null $now Testable current time.
     */
    public function verify(
        string $rawbody,
        int $timestamp,
        string $nonce,
        string $signature,
        string $secret,
        ?int $now = null,
    ): void {
        $now ??= time();
        if (abs($now - $timestamp) > self::MAX_AGE) {
            throw new \invalid_parameter_exception('Webhook timestamp is outside the accepted window.');
        }
        if (!preg_match('/^[A-Za-z0-9_.-]{16,100}$/', $nonce)) {
            throw new \invalid_parameter_exception('Webhook nonce is invalid.');
        }
        if (strlen($secret) < 32) {
            throw new \coding_exception('Commerce webhook secrets must contain at least 32 bytes.');
        }
        $expected = hash_hmac('sha256', $timestamp . "\n" . $nonce . "\n" . $rawbody, $secret);
        if (!preg_match('/^[a-f0-9]{64}$/i', $signature) || !hash_equals($expected, strtolower($signature))) {
            throw new \invalid_parameter_exception('Webhook signature is invalid.');
        }
    }

    /**
     * Resolve a company secret from environment-backed configuration.
     *
     * @param string $companyshortname Company.
     * @return string
     */
    public function secret_for(string $companyshortname): string {
        $raw = (string)getenv('IOMAD_COMMERCE_WEBHOOK_KEYS_JSON');
        try {
            $keys = $raw === '' ? [] : json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \coding_exception('Invalid commerce webhook key configuration.', '', $exception);
        }
        $secret = is_array($keys) ? (string)($keys[$companyshortname] ?? '') : '';
        if (strlen($secret) < 32) {
            throw new \moodle_exception('notconfigured', 'local_iomadcommerce');
        }
        return $secret;
    }
}
