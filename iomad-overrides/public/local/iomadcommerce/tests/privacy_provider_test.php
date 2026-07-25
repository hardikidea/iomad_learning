<?php
// This file is part of Moodle - http://moodle.org/

namespace local_iomadcommerce;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\writer;
use local_iomadcommerce\privacy\provider;

/**
 * Commerce privacy lifecycle tests.
 *
 * @package    local_iomadcommerce
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_iomadcommerce\privacy\provider
 */
final class privacy_provider_test extends \advanced_testcase {
    /**
     * Commerce exports user records and detaches identifiers on erasure.
     */
    public function test_export_and_erasure_detach_personal_identifiers(): void {
        global $DB;

        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $context = \context_user::instance($user->id);
        $now = time();

        $productid = $DB->insert_record('local_iomadcommerce_product', (object)[
            'companyid' => 1,
            'courseid' => SITEID,
            'externalid' => 'privacy-product',
            'name' => 'Privacy product',
            'status' => 'draft',
            'priceminor' => 0,
            'currency' => 'INR',
            'accessdays' => 0,
            'checkouturl' => null,
            'recommendjson' => '[]',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $orderid = $DB->insert_record('local_iomadcommerce_order', (object)[
            'companyid' => 1,
            'externalid' => 'privacy-order',
            'userid' => $user->id,
            'status' => 'paid',
            'provider' => 'test',
            'totalminor' => 0,
            'currency' => 'INR',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $itemid = $DB->insert_record('local_iomadcommerce_item', (object)[
            'orderid' => $orderid,
            'productid' => $productid,
            'quantity' => 1,
            'unitminor' => 0,
        ]);
        $DB->insert_record('local_iomadcommerce_seat', (object)[
            'orderitemid' => $itemid,
            'userid' => $user->id,
            'assignedby' => $other->id,
            'status' => 'active',
            'userenrolmentid' => 0,
            'timeassigned' => $now,
            'timerevoked' => 0,
        ]);
        $DB->insert_record('local_iomadcommerce_event', (object)[
            'companyid' => 1,
            'orderid' => $orderid,
            'eventkey' => 'privacy-event',
            'action' => 'paid',
            'actorid' => $user->id,
            'payloadhash' => hash('sha256', 'privacy'),
            'timecreated' => $now,
        ]);

        $contexts = provider::get_contexts_for_userid($user->id);
        $this->assertSame([$context->id], array_map('intval', $contexts->get_contextids()));

        provider::export_user_data(new approved_contextlist(
            $user,
            'local_iomadcommerce',
            [$context->id],
        ));
        $export = writer::with_context($context)->get_data([
            get_string('pluginname', 'local_iomadcommerce'),
        ]);
        $this->assertCount(1, $export->orders);
        $this->assertCount(1, $export->seats);

        provider::delete_data_for_user(new approved_contextlist(
            $user,
            'local_iomadcommerce',
            [$context->id],
        ));
        $this->assertSame(
            0,
            (int)$DB->get_field('local_iomadcommerce_order', 'userid', ['id' => $orderid]),
        );
        $this->assertSame(
            0,
            (int)$DB->get_field('local_iomadcommerce_seat', 'userid', ['orderitemid' => $itemid]),
        );
        $this->assertSame(
            (int)$other->id,
            (int)$DB->get_field('local_iomadcommerce_seat', 'assignedby', ['orderitemid' => $itemid]),
        );
        $this->assertSame(
            0,
            (int)$DB->get_field('local_iomadcommerce_event', 'actorid', ['orderid' => $orderid]),
        );
    }
}
