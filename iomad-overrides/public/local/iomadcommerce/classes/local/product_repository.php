<?php
// This file is part of Moodle - http://moodle.org/

namespace local_iomadcommerce\local;

/**
 * Tenant course product repository.
 *
 * @package    local_iomadcommerce
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class product_repository {
    /** @var array Product states. */
    public const STATUSES = ['draft', 'free', 'paid', 'closed'];

    /**
     * Create or update one product through stable identifiers.
     *
     * @param tenant_scope $scope Tenant scope.
     * @param object $course Course.
     * @param array $input Product input.
     * @return array Product and action.
     */
    public function upsert(tenant_scope $scope, object $course, array $input): array {
        global $DB;

        if (!$scope->contains_course((int)$course->id)) {
            throw new \invalid_parameter_exception('The product course is outside the company.');
        }
        $externalid = trim((string)($input['externalid'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9_.-]{3,100}$/', $externalid)) {
            throw new \invalid_parameter_exception('Invalid product external ID.');
        }
        $status = (string)($input['status'] ?? 'draft');
        if (!in_array($status, self::STATUSES, true)) {
            throw new \invalid_parameter_exception('Invalid product status.');
        }
        $priceminor = filter_var($input['priceminor'] ?? null, FILTER_VALIDATE_INT);
        if ($priceminor === false || $priceminor < 0) {
            throw new \invalid_parameter_exception('Product price must be a non-negative minor-unit integer.');
        }
        if ($status === 'free' && $priceminor !== 0) {
            throw new \invalid_parameter_exception('A free product cannot have a non-zero price.');
        }
        if ($status === 'paid' && $priceminor === 0) {
            throw new \invalid_parameter_exception('A paid product requires a positive price.');
        }
        $currency = strtoupper(trim((string)($input['currency'] ?? 'USD')));
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new \invalid_parameter_exception('Currency must be an ISO-style three-letter code.');
        }
        $checkouturl = trim((string)($input['checkouturl'] ?? ''));
        if ($checkouturl !== '' && !preg_match('#^https://#i', $checkouturl)) {
            throw new \invalid_parameter_exception('Paid checkout URLs must use HTTPS.');
        }
        $recommendations = array_values(array_unique(array_filter(array_map(
            static fn(mixed $value): string => trim((string)$value),
            (array)($input['recommendations'] ?? []),
        ))));
        foreach ($recommendations as $recommendation) {
            if (!preg_match('/^[A-Za-z0-9_.-]{3,100}$/', $recommendation)) {
                throw new \invalid_parameter_exception('Invalid recommended product external ID.');
            }
        }
        $now = time();
        $record = (object)[
            'companyid' => $scope->companyid(),
            'courseid' => $course->id,
            'externalid' => $externalid,
            'name' => trim((string)($input['name'] ?? '')) ?: format_string($course->fullname),
            'status' => $status,
            'priceminor' => $priceminor,
            'currency' => $currency,
            'accessdays' => max(0, (int)($input['accessdays'] ?? 0)),
            'checkouturl' => $checkouturl,
            'recommendjson' => json_encode($recommendations, JSON_THROW_ON_ERROR),
            'timemodified' => $now,
        ];
        $existing = $DB->get_record('local_iomadcommerce_product', [
            'companyid' => $scope->companyid(),
            'externalid' => $externalid,
        ]);
        if ($existing) {
            $record->id = $existing->id;
            $record->timecreated = $existing->timecreated;
            $changed = false;
            foreach ((array)$record as $field => $value) {
                if ($field !== 'timemodified' && (string)$existing->{$field} !== (string)$value) {
                    $changed = true;
                    break;
                }
            }
            if (!$changed) {
                return [$existing, 'unchanged'];
            }
            $DB->update_record('local_iomadcommerce_product', $record);
            return [$DB->get_record('local_iomadcommerce_product', ['id' => $existing->id], '*', MUST_EXIST), 'updated'];
        }
        $record->timecreated = $now;
        $record->id = $DB->insert_record('local_iomadcommerce_product', $record);
        return [$record, 'created'];
    }

    /**
     * Products visible in one company.
     *
     * @param int $companyid Company.
     * @param bool $published Published only.
     * @return array
     */
    public function list(int $companyid, bool $published = true): array {
        global $DB;

        $params = ['companyid' => $companyid];
        $where = 'companyid = :companyid';
        if ($published) {
            [$insql, $inparams] = $DB->get_in_or_equal(['free', 'paid'], SQL_PARAMS_NAMED, 'status');
            $where .= " AND status {$insql}";
            $params += $inparams;
        }
        return $DB->get_records_select(
            'local_iomadcommerce_product',
            $where,
            $params,
            'name ASC, id ASC',
        );
    }

    /**
     * Resolve one tenant product.
     *
     * @param int $companyid Company.
     * @param string $externalid Stable product ID.
     * @return object
     */
    public function get(int $companyid, string $externalid): object {
        global $DB;

        return $DB->get_record('local_iomadcommerce_product', [
            'companyid' => $companyid,
            'externalid' => $externalid,
        ], '*', MUST_EXIST);
    }

    /**
     * Tenant products recommended from active purchases, with a safe catalogue fallback.
     *
     * @param int $companyid Company.
     * @param array $purchases Active purchase records.
     * @param int $limit Maximum products.
     * @return array
     */
    public function recommendations(int $companyid, array $purchases, int $limit = 4): array {
        $catalogue = $this->list($companyid);
        $cataloguebyexternalid = [];
        foreach ($catalogue as $product) {
            $cataloguebyexternalid[$product->externalid] = $product;
        }
        $purchasedids = array_fill_keys(array_map(
            static fn(object $purchase): string => $purchase->externalid,
            $purchases,
        ), true);
        $recommendedids = [];
        foreach (array_keys($purchasedids) as $externalid) {
            try {
                $product = $this->get($companyid, $externalid);
                $ids = json_decode($product->recommendjson, true, 32, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                $ids = [];
            }
            foreach ((array)$ids as $id) {
                if (isset($cataloguebyexternalid[$id]) && !isset($purchasedids[$id])) {
                    $recommendedids[$id] = true;
                }
            }
        }
        foreach ($cataloguebyexternalid as $externalid => $product) {
            if (count($recommendedids) >= $limit) {
                break;
            }
            if (!isset($purchasedids[$externalid])) {
                $recommendedids[$externalid] = true;
            }
        }
        return array_values(array_intersect_key($cataloguebyexternalid, array_slice(
            $recommendedids,
            0,
            $limit,
            true,
        )));
    }
}
