<?php
// This file is part of Moodle - http://moodle.org/

namespace local_iomadcommerce;

use local_iomad\company;
use local_iomadcommerce\local\order_service;
use local_iomadcommerce\local\product_repository;
use local_iomadcommerce\local\tenant_scope;

/**
 * Order, bulk seat, enrolment, refund, and replay tests.
 *
 * @package    local_iomadcommerce
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_iomadcommerce\local\order_service
 */
final class order_service_test extends \advanced_testcase {
    /**
     * Paid seats remain in company and refunds remove only owned enrolments.
     */
    public function test_paid_bulk_order_assignment_and_refund(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        [$scope, $product, $usera, $userb, $other, $course] = $this->fixture('paid', 2500);
        $messagesink = $this->redirectMessages();
        $service = new order_service();

        [$order, $createaction] = $service->create(
            $scope,
            $product,
            $usera->id,
            'ORDER-PAID-001',
            2,
            'test',
        );
        [$paid, $payaction] = $service->transition(
            $scope,
            'ORDER-PAID-001',
            'paid',
            'EVENT-PAY-001',
            hash('sha256', 'pay'),
            get_admin()->id,
        );
        [, $replayaction] = $service->transition(
            $scope,
            'ORDER-PAID-001',
            'paid',
            'EVENT-PAY-001',
            hash('sha256', 'pay'),
            get_admin()->id,
        );
        [, $assigna] = $service->assign($scope, 'ORDER-PAID-001', $usera->id, get_admin()->id);
        [, $assignb] = $service->assign($scope, 'ORDER-PAID-001', $userb->id, get_admin()->id);

        $this->assertSame('created', $createaction);
        $this->assertSame('pending', $order->status);
        $this->assertSame('updated', $payaction);
        $this->assertSame('paid', $paid->status);
        $this->assertSame('unchanged', $replayaction);
        $this->assertSame('assigned', $assigna);
        $this->assertSame('assigned', $assignb);
        $this->assertTrue(is_enrolled(\context_course::instance($course->id), $usera));
        $this->assertTrue(is_enrolled(\context_course::instance($course->id), $userb));
        $this->assertCount(2, $service->purchases($scope, $usera->id) + $service->purchases($scope, $userb->id));

        try {
            $service->assign($scope, 'ORDER-PAID-001', $other->id, get_admin()->id);
            $this->fail('A cross-company learner received a seat.');
        } catch (\invalid_parameter_exception $exception) {
            $this->assertStringContainsString('outside the company', $exception->getMessage());
        }

        [$refunded, $refundaction] = $service->transition(
            $scope,
            'ORDER-PAID-001',
            'refunded',
            'EVENT-REFUND-001',
            hash('sha256', 'refund'),
            get_admin()->id,
        );

        $this->assertSame('updated', $refundaction);
        $this->assertSame('refunded', $refunded->status);
        $this->assertFalse(is_enrolled(\context_course::instance($course->id), $usera));
        $this->assertFalse(is_enrolled(\context_course::instance($course->id), $userb));
        $this->assertSame(2, $DB->count_records('local_iomadcommerce_seat', ['status' => 'revoked']));
        $this->assertCount(4, $messagesink->get_messages());
        $messagesink->close();
    }

    /**
     * Free checkout is idempotent and grants one seat.
     */
    public function test_free_order_is_idempotent_and_enrols_buyer(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        [$scope, $product, $usera, , , $course] = $this->fixture('free', 0);
        $messagesink = $this->redirectMessages();
        $service = new order_service();

        [$created, $createdaction] = $service->create(
            $scope,
            $product,
            $usera->id,
            'ORDER-FREE-001',
        );
        [$unchanged, $unchangedaction] = $service->create(
            $scope,
            $product,
            $usera->id,
            'ORDER-FREE-001',
        );

        $this->assertSame('created', $createdaction);
        $this->assertSame('paid', $created->status);
        $this->assertSame('unchanged', $unchangedaction);
        $this->assertSame($created->id, $unchanged->id);
        $this->assertTrue(is_enrolled(\context_course::instance($course->id), $usera));
        $this->assertCount(1, $service->purchases($scope, $usera->id));
        $this->assertCount(1, $messagesink->get_messages());
        $messagesink->close();
    }

    /**
     * Create tenant product and users.
     *
     * @param string $status Product status.
     * @param int $price Price.
     * @return array
     */
    private function fixture(string $status, int $price): array {
        $companya = $this->company('Order Company A', 'order_a_' . $status);
        $companyb = $this->company('Order Company B', 'order_b_' . $status);
        $course = $this->getDataGenerator()->create_course();
        $usera = $this->getDataGenerator()->create_user();
        $userb = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $companya->add_course($course);
        $companya->assign_user_to_company($usera->id);
        $companya->assign_user_to_company($userb->id);
        $companyb->assign_user_to_company($other->id);
        $scope = new tenant_scope($companya->id);
        [$product] = (new product_repository())->upsert($scope, $course, [
            'externalid' => 'PRODUCT-' . strtoupper($status),
            'name' => 'Commerce product',
            'status' => $status,
            'priceminor' => $price,
            'currency' => 'INR',
            'accessdays' => 30,
        ]);
        return [$scope, $product, $usera, $userb, $other, $course];
    }

    /**
     * Create a company.
     *
     * @param string $name Name.
     * @param string $shortname Shortname.
     * @return company
     */
    private function company(string $name, string $shortname): company {
        return company::create_company((object)[
            'name' => $name,
            'shortname' => $shortname,
            'city' => 'Pune',
            'country' => 'IN',
        ]);
    }
}
