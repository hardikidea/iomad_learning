<?php
// This file is part of Moodle - http://moodle.org/

namespace local_iomadcommerce\local;

use local_iomad\company_user;

/**
 * Idempotent tenant order, refund, and seat workflow.
 *
 * @package    local_iomadcommerce
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class order_service {
    /** @var array Allowed order transitions. */
    private const TRANSITIONS = [
        'pending' => ['paid', 'cancelled'],
        'paid' => ['refunded'],
        'cancelled' => [],
        'refunded' => [],
    ];

    /**
     * Constructor.
     *
     * @param notifier $notifier Notification adapter.
     */
    public function __construct(private readonly notifier $notifier = new notifier()) {
    }

    /**
     * Create an order and automatically complete a free single-seat order.
     *
     * @param tenant_scope $scope Tenant scope.
     * @param object $product Product.
     * @param int $userid Buyer.
     * @param string $externalid Stable order ID.
     * @param int $quantity Seat quantity.
     * @param string $provider Provider key.
     * @return array Order and action.
     */
    public function create(
        tenant_scope $scope,
        object $product,
        int $userid,
        string $externalid,
        int $quantity = 1,
        string $provider = 'local',
    ): array {
        global $DB;

        if ((int)$product->companyid !== $scope->companyid() || !$scope->contains_user($userid)) {
            throw new \invalid_parameter_exception('The order buyer or product is outside the company.');
        }
        if (!in_array($product->status, ['free', 'paid'], true)) {
            throw new \invalid_parameter_exception('The product is not available for purchase.');
        }
        if (!preg_match('/^[A-Za-z0-9_.:-]{6,100}$/', $externalid)) {
            throw new \invalid_parameter_exception('Invalid order external ID.');
        }
        if ($quantity < 1 || $quantity > 10000) {
            throw new \invalid_parameter_exception('Order quantity must be between 1 and 10000.');
        }
        if (!preg_match('/^[a-z0-9_-]{2,30}$/', $provider)) {
            throw new \invalid_parameter_exception('Invalid provider key.');
        }
        $existing = $DB->get_record('local_iomadcommerce_order', [
            'companyid' => $scope->companyid(),
            'externalid' => $externalid,
        ]);
        if ($existing) {
            $item = $DB->get_record('local_iomadcommerce_item', ['orderid' => $existing->id], '*', MUST_EXIST);
            if (
                (int)$existing->userid !== $userid
                || (int)$item->productid !== (int)$product->id
                || (int)$item->quantity !== $quantity
                || $existing->provider !== $provider
            ) {
                throw new \invalid_parameter_exception('The order idempotency key was reused with different data.');
            }
            return [$existing, 'unchanged'];
        }
        $transaction = $DB->start_delegated_transaction();
        $now = time();
        $order = (object)[
            'companyid' => $scope->companyid(),
            'externalid' => $externalid,
            'userid' => $userid,
            'status' => 'pending',
            'provider' => $provider,
            'totalminor' => (int)$product->priceminor * $quantity,
            'currency' => $product->currency,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $order->id = $DB->insert_record('local_iomadcommerce_order', $order);
        $item = (object)[
            'orderid' => $order->id,
            'productid' => $product->id,
            'quantity' => $quantity,
            'unitminor' => $product->priceminor,
        ];
        $item->id = $DB->insert_record('local_iomadcommerce_item', $item);
        $this->record_event(
            $scope->companyid(),
            $order->id,
            'created:' . $externalid,
            'created',
            $userid,
            hash('sha256', $externalid . ':' . $product->externalid . ':' . $quantity),
        );
        $transaction->allow_commit();
        if ($product->status === 'free') {
            [$order] = $this->transition(
                $scope,
                $externalid,
                'paid',
                'free:' . $externalid,
                hash('sha256', 'free:' . $externalid),
                $userid,
            );
            if ($quantity === 1) {
                $this->assign($scope, $externalid, $userid, $userid);
            }
        }
        return [$order, 'created'];
    }

    /**
     * Apply one idempotent order transition.
     *
     * @param tenant_scope $scope Tenant.
     * @param string $externalid Order ID.
     * @param string $status New status.
     * @param string $eventkey Unique event key.
     * @param string $payloadhash Hash of canonical event payload.
     * @param int $actorid Actor, or zero for external provider.
     * @return array Order and action.
     */
    public function transition(
        tenant_scope $scope,
        string $externalid,
        string $status,
        string $eventkey,
        string $payloadhash,
        int $actorid = 0,
    ): array {
        global $DB;

        $existingevent = $DB->get_record('local_iomadcommerce_event', [
            'companyid' => $scope->companyid(),
            'eventkey' => $eventkey,
        ]);
        if ($existingevent) {
            if ($existingevent->action !== $status || !hash_equals($existingevent->payloadhash, $payloadhash)) {
                throw new \invalid_parameter_exception('The commerce event key was replayed with different data.');
            }
            return [
                $DB->get_record('local_iomadcommerce_order', ['id' => $existingevent->orderid], '*', MUST_EXIST),
                'unchanged',
            ];
        }
        $transaction = $DB->start_delegated_transaction();
        $order = $DB->get_record_sql(
            "SELECT *
               FROM {local_iomadcommerce_order}
              WHERE companyid = :companyid AND externalid = :externalid
              FOR UPDATE",
            ['companyid' => $scope->companyid(), 'externalid' => $externalid],
            MUST_EXIST,
        );
        $allowed = self::TRANSITIONS[$order->status] ?? [];
        if (!in_array($status, $allowed, true)) {
            throw new \invalid_parameter_exception('The requested order transition is not permitted.');
        }
        $order->status = $status;
        $order->timemodified = time();
        $DB->update_record('local_iomadcommerce_order', $order);
        $this->record_event(
            $scope->companyid(),
            $order->id,
            $eventkey,
            $status,
            $actorid,
            $payloadhash,
        );
        $transaction->allow_commit();
        if ($status === 'refunded') {
            $this->revoke_order_seats($scope, (int)$order->id);
        }
        return [$order, 'updated'];
    }

    /**
     * Assign one purchased seat and own only a newly created enrolment.
     *
     * @param tenant_scope $scope Tenant.
     * @param string $orderexternalid Order.
     * @param int $userid Learner.
     * @param int $actorid Actor.
     * @return array Seat and action.
     */
    public function assign(
        tenant_scope $scope,
        string $orderexternalid,
        int $userid,
        int $actorid,
    ): array {
        global $DB;

        if (!$scope->contains_user($userid)) {
            throw new \invalid_parameter_exception('The learner is outside the company.');
        }
        $transaction = $DB->start_delegated_transaction();
        $sql = "SELECT i.*, o.companyid, o.status AS orderstatus, p.courseid, p.accessdays
                  FROM {local_iomadcommerce_item} i
                  JOIN {local_iomadcommerce_order} o ON o.id = i.orderid
                  JOIN {local_iomadcommerce_product} p ON p.id = i.productid
                 WHERE o.companyid = :companyid AND o.externalid = :externalid
                 FOR UPDATE";
        $item = $DB->get_record_sql($sql, [
            'companyid' => $scope->companyid(),
            'externalid' => $orderexternalid,
        ], MUST_EXIST);
        if ($item->orderstatus !== 'paid') {
            throw new \invalid_parameter_exception('Seats can be assigned only from a paid order.');
        }
        $existing = $DB->get_record('local_iomadcommerce_seat', [
            'orderitemid' => $item->id,
            'userid' => $userid,
        ]);
        if ($existing && $existing->status === 'active') {
            $transaction->allow_commit();
            return [$existing, 'unchanged'];
        }
        $activecount = $DB->count_records('local_iomadcommerce_seat', [
            'orderitemid' => $item->id,
            'status' => 'active',
        ]);
        if ($activecount >= (int)$item->quantity) {
            throw new \invalid_parameter_exception('No purchased seats remain available.');
        }
        $before = $this->user_enrolment_ids($userid, (int)$item->courseid);
        company_user::enrol($userid, (int)$item->courseid, $scope->companyid());
        $after = $this->user_enrolment_ids($userid, (int)$item->courseid);
        $owned = array_values(array_diff($after, $before));
        $ownedid = count($owned) === 1 ? (int)$owned[0] : 0;
        if (!$after) {
            throw new \moodle_exception('An enrolment could not be created for the purchased seat.');
        }
        if ($ownedid && (int)$item->accessdays > 0) {
            $this->set_owned_enrolment_end($ownedid, time() + ((int)$item->accessdays * DAYSECS));
        }
        $seat = (object)[
            'orderitemid' => $item->id,
            'userid' => $userid,
            'assignedby' => $actorid,
            'status' => 'active',
            'userenrolmentid' => $ownedid,
            'timeassigned' => time(),
            'timerevoked' => 0,
        ];
        if ($existing) {
            $seat->id = $existing->id;
            $DB->update_record('local_iomadcommerce_seat', $seat);
        } else {
            $seat->id = $DB->insert_record('local_iomadcommerce_seat', $seat);
        }
        $this->record_event(
            $scope->companyid(),
            $item->orderid,
            'seat:' . $item->id . ':' . $userid . ':' . $seat->timeassigned,
            'seat_assigned',
            $actorid,
            hash('sha256', $item->id . ':' . $userid),
        );
        $transaction->allow_commit();
        $this->notifier->send($userid, 'seatassigned');
        return [$seat, 'assigned'];
    }

    /**
     * User purchases visible in their company.
     *
     * @param tenant_scope $scope Tenant.
     * @param int $userid User.
     * @return array
     */
    public function purchases(tenant_scope $scope, int $userid): array {
        global $DB;

        if (!$scope->contains_user($userid)) {
            return [];
        }
        $sql = "SELECT s.id, s.status, s.timeassigned, p.externalid, p.name, p.courseid,
                       o.externalid AS orderexternalid
                  FROM {local_iomadcommerce_seat} s
                  JOIN {local_iomadcommerce_item} i ON i.id = s.orderitemid
                  JOIN {local_iomadcommerce_order} o ON o.id = i.orderid
                  JOIN {local_iomadcommerce_product} p ON p.id = i.productid
                 WHERE o.companyid = :companyid
                       AND s.userid = :userid
                       AND s.status = :status
              ORDER BY s.timeassigned DESC, s.id DESC";
        return $DB->get_records_sql($sql, [
            'companyid' => $scope->companyid(),
            'userid' => $userid,
            'status' => 'active',
        ]);
    }

    /**
     * Record an immutable event.
     *
     * @param int $companyid Company.
     * @param int $orderid Order.
     * @param string $eventkey Event key.
     * @param string $action Action.
     * @param int $actorid Actor.
     * @param string $payloadhash Hash.
     */
    private function record_event(
        int $companyid,
        int $orderid,
        string $eventkey,
        string $action,
        int $actorid,
        string $payloadhash,
    ): void {
        global $DB;

        if (
            !preg_match('/^[A-Za-z0-9_.:-]{6,100}$/', $eventkey)
            || !preg_match('/^[a-f0-9]{64}$/', $payloadhash)
        ) {
            throw new \invalid_parameter_exception('Invalid commerce audit identity.');
        }
        $DB->insert_record('local_iomadcommerce_event', (object)[
            'companyid' => $companyid,
            'orderid' => $orderid,
            'eventkey' => $eventkey,
            'action' => $action,
            'actorid' => $actorid,
            'payloadhash' => $payloadhash,
            'timecreated' => time(),
        ]);
    }

    /**
     * Revoke seats and only enrolments this plugin created.
     *
     * @param tenant_scope $scope Tenant.
     * @param int $orderid Order.
     */
    private function revoke_order_seats(tenant_scope $scope, int $orderid): void {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        $recipients = [];
        $sql = "SELECT s.*
                  FROM {local_iomadcommerce_seat} s
                  JOIN {local_iomadcommerce_item} i ON i.id = s.orderitemid
                 WHERE i.orderid = :orderid AND s.status = :status";
        foreach ($DB->get_records_sql($sql, ['orderid' => $orderid, 'status' => 'active']) as $seat) {
            if ($seat->userenrolmentid) {
                $this->remove_owned_enrolment((int)$seat->userenrolmentid, (int)$seat->userid);
            }
            $seat->status = 'revoked';
            $seat->timerevoked = time();
            $DB->update_record('local_iomadcommerce_seat', $seat);
            if ($scope->contains_user((int)$seat->userid)) {
                $recipients[] = (int)$seat->userid;
            }
        }
        $transaction->allow_commit();
        foreach (array_unique($recipients) as $userid) {
            $this->notifier->send($userid, 'refundprocessed');
        }
    }

    /**
     * Enrolment IDs for one user/course.
     *
     * @param int $userid User.
     * @param int $courseid Course.
     * @return array
     */
    private function user_enrolment_ids(int $userid, int $courseid): array {
        global $DB;

        return array_map('intval', $DB->get_fieldset_sql(
            "SELECT ue.id
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE ue.userid = :userid AND e.courseid = :courseid",
            ['userid' => $userid, 'courseid' => $courseid],
        ));
    }

    /**
     * Apply access expiry to a plugin-owned enrolment.
     *
     * @param int $userenrolmentid User enrolment.
     * @param int $timeend End.
     */
    private function set_owned_enrolment_end(int $userenrolmentid, int $timeend): void {
        global $DB;

        $record = $DB->get_record_sql(
            "SELECT ue.userid, ue.timestart, e.*
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE ue.id = :id",
            ['id' => $userenrolmentid],
            MUST_EXIST,
        );
        $plugin = enrol_get_plugin($record->enrol);
        if ($plugin) {
            $plugin->update_user_enrol(
                $record,
                $record->userid,
                ENROL_USER_ACTIVE,
                $record->timestart,
                $timeend,
            );
        }
    }

    /**
     * Remove only a specific plugin-created enrolment.
     *
     * @param int $userenrolmentid User enrolment.
     * @param int $userid User.
     */
    private function remove_owned_enrolment(int $userenrolmentid, int $userid): void {
        global $DB;

        $record = $DB->get_record_sql(
            "SELECT e.*
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE ue.id = :id AND ue.userid = :userid",
            ['id' => $userenrolmentid, 'userid' => $userid],
        );
        if ($record && ($plugin = enrol_get_plugin($record->enrol))) {
            $plugin->unenrol_user($record, $userid);
        }
    }
}
