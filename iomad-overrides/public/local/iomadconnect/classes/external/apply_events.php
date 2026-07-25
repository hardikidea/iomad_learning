<?php
// This file is part of Moodle - http://moodle.org/

namespace local_iomadconnect\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_iomad\custom_context\context_company;
use local_iomad\iomad;
use local_iomadcommerce\local\tenant_scope;
use local_iomadconnect\local\sync_service;

/**
 * Apply an ordered tenant synchronization batch.
 *
 * @package    local_iomadconnect
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class apply_events extends external_api {
    /** Maximum JSON request size. */
    private const MAX_JSON_BYTES = 1048576;

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'companyid' => new external_value(PARAM_INT, 'IOMAD company ID'),
            'systemkey' => new external_value(PARAM_ALPHANUMEXT, 'Stable source-system key'),
            'eventsjson' => new external_value(PARAM_RAW, 'JSON event array, maximum 500 events'),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $companyid Company.
     * @param string $systemkey System key.
     * @param string $eventsjson Events JSON.
     * @return array
     */
    public static function execute(int $companyid, string $systemkey, string $eventsjson): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'companyid' => $companyid,
            'systemkey' => $systemkey,
            'eventsjson' => $eventsjson,
        ]);
        if (strlen($params['eventsjson']) > self::MAX_JSON_BYTES) {
            throw new \invalid_parameter_exception('The synchronization request is too large.');
        }
        try {
            $events = json_decode($params['eventsjson'], true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \invalid_parameter_exception('The synchronization request is not valid JSON.');
        }
        if (!is_array($events) || !array_is_list($events)) {
            throw new \invalid_parameter_exception('The synchronization request must be a JSON array.');
        }
        $scope = tenant_scope::resolve($params['companyid']);
        if ($scope->companyid() !== $params['companyid']) {
            throw new \invalid_parameter_exception('The requested company is outside the authenticated scope.');
        }
        $context = context_company::instance($scope->companyid());
        self::validate_context($context);
        if (!is_siteadmin()) {
            iomad::require_capability('local/iomadconnect:write', $context, $scope->companyid());
        }
        return ['results' => (new sync_service())->apply($scope, $params['systemkey'], $events)];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'results' => new external_multiple_structure(new external_single_structure([
                'eventid' => new external_value(PARAM_RAW_TRIMMED, 'Event ID'),
                'action' => new external_value(PARAM_ALPHA, 'Applied action'),
            ])),
        ]);
    }
}
