<?php
// This file is part of Moodle - http://moodle.org/

namespace local_iomadcommerce;

use local_iomadcommerce\local\webhook_verifier;

/**
 * Signed callback validation tests.
 *
 * @package    local_iomadcommerce
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_iomadcommerce\local\webhook_verifier
 */
final class webhook_verifier_test extends \basic_testcase {
    /**
     * Valid signatures pass and altered signatures fail.
     */
    public function test_signature_is_bound_to_timestamp_nonce_and_body(): void {
        $body = '{"event_id":"evt-100","status":"paid"}';
        $timestamp = 1784963000;
        $nonce = 'nonce-1234567890';
        $secret = str_repeat('a', 32);
        $signature = hash_hmac('sha256', $timestamp . "\n" . $nonce . "\n" . $body, $secret);
        $verifier = new webhook_verifier();

        $verifier->verify($body, $timestamp, $nonce, $signature, $secret, $timestamp + 10);
        $this->addToAssertionCount(1);

        $this->expectException(\invalid_parameter_exception::class);
        $verifier->verify($body . ' ', $timestamp, $nonce, $signature, $secret, $timestamp + 10);
    }

    /**
     * Expired callbacks fail before state mutation.
     */
    public function test_expired_signature_is_rejected(): void {
        $timestamp = 1784963000;
        $secret = str_repeat('b', 32);
        $body = '{}';
        $nonce = 'nonce-0987654321';
        $signature = hash_hmac('sha256', $timestamp . "\n" . $nonce . "\n" . $body, $secret);

        $this->expectException(\invalid_parameter_exception::class);
        (new webhook_verifier())->verify(
            $body,
            $timestamp,
            $nonce,
            $signature,
            $secret,
            $timestamp + webhook_verifier::MAX_AGE + 1,
        );
    }
}
