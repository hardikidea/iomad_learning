<?php
// This file is part of Moodle - http://moodle.org/

namespace local_global_events;

use local_global_events\communication\chatbot;
use local_global_events\communication\manager;
use local_global_events\local\message_queue;
use local_global_events\local\template_renderer;
use local_global_events\local\tenant_scope;
use local_global_events\local\webhook_verifier;
use local_global_events\local\whatsapp_gateway;
use local_iomad\company;

/**
 * Messaging signature, privacy, and disabled-gateway tests.
 *
 * @package local_global_events
 * @covers \local_global_events\local\message_queue
 * @covers \local_global_events\communication\chatbot
 * @covers \local_global_events\communication\manager
 * @covers \local_global_events\local\certificate_service
 * @covers \local_global_events\local\template_renderer
 * @covers \local_global_events\local\webhook_verifier
 * @covers \local_global_events\local\whatsapp_gateway
 */
final class messaging_security_test extends \advanced_testcase {
    /**
     * Valid signed payloads pass and tampered payloads fail.
     */
    public function test_webhook_signature_verification(): void {
        $secret = str_repeat('s', 32);
        putenv('IOMAD_WHATSAPP_WEBHOOK_SECRET=' . $secret);
        $timestamp = (string)time();
        $body = json_encode([
            'companyid' => 1,
            'eventid' => 'provider-event-001',
            'address' => '+919999999999',
            'command' => 'STATUS',
        ], JSON_THROW_ON_ERROR);
        $signature = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, $secret);

        $payload = (new webhook_verifier())->verify($body, $timestamp, $signature);
        $this->assertSame('STATUS', $payload['command']);

        try {
            (new webhook_verifier())->verify($body . ' ', $timestamp, $signature);
            $this->fail('Tampered body was accepted.');
        } catch (\invalid_parameter_exception) {
            $this->assertTrue(true);
        } finally {
            putenv('IOMAD_WHATSAPP_WEBHOOK_SECRET');
        }
    }

    /**
     * Queue variables reject direct personal data.
     */
    public function test_queue_rejects_personal_variables(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $company = company::create_company((object)[
            'name' => 'Message Company',
            'shortname' => 'message_company',
            'city' => 'Pune',
            'country' => 'IN',
        ]);
        $user = $this->getDataGenerator()->create_user();
        $company->assign_user_to_company($user->id);

        $this->expectException(\invalid_parameter_exception::class);
        (new message_queue())->enqueue(
            tenant_scope::system($company->id),
            $user->id,
            'moodle',
            'achievement',
            ['email' => 1],
            'message-event-001',
        );
    }

    /**
     * External gateway is disabled without secure environment injection.
     */
    public function test_whatsapp_gateway_is_disabled_by_default(): void {
        putenv('IOMAD_WHATSAPP_GATEWAY_URL');
        putenv('IOMAD_WHATSAPP_GATEWAY_TOKEN');
        $this->assertFalse((new whatsapp_gateway())->enabled());
    }

    /**
     * Communication manager rejects arbitrary channel or class selection.
     */
    public function test_communication_manager_uses_channel_allowlist(): void {
        $this->expectException(\invalid_parameter_exception::class);
        (new manager())->gateway('custom-class-name');
    }

    /**
     * Chat help advertises only implemented fixed commands.
     */
    public function test_chat_help_includes_certificate_command(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $company = company::create_company((object)[
            'name' => 'Chat Help Company',
            'shortname' => 'chat_help_company',
            'city' => 'Pune',
            'country' => 'IN',
        ]);
        $plan = (new chatbot())->plan(
            tenant_scope::system($company->id),
            1,
            'HELP',
        );
        $rendered = (new template_renderer())->render($plan['template'], $plan['variables']);

        $this->assertSame('chat_help', $plan['template']);
        $this->assertStringContainsString('MY CODES', $rendered['body']);
    }

    /**
     * Certificate projection returns no data outside official issue records.
     */
    public function test_chat_certificate_count_is_company_scoped(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $company = company::create_company((object)[
            'name' => 'Certificate Company',
            'shortname' => 'certificate_company',
            'city' => 'Pune',
            'country' => 'IN',
        ]);
        $user = $this->getDataGenerator()->create_user();
        $company->assign_user_to_company($user->id);

        $plan = (new chatbot())->plan(
            tenant_scope::system($company->id),
            $user->id,
            'MY CODES',
        );

        $this->assertSame('chat_certificates', $plan['template']);
        $this->assertSame(['count' => 0], $plan['variables']);
    }
}
