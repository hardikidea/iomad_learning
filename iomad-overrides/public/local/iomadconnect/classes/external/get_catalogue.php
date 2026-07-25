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
use local_iomadconnect\local\catalogue_exporter;

/**
 * Return one tenant catalogue page.
 *
 * @package    local_iomadconnect
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class get_catalogue extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'companyid' => new external_value(PARAM_INT, 'IOMAD company ID'),
            'cursor' => new external_value(PARAM_RAW_TRIMMED, 'Opaque cursor', VALUE_DEFAULT, ''),
            'limit' => new external_value(PARAM_INT, 'Page size, maximum 500', VALUE_DEFAULT, 100),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $companyid Company.
     * @param string $cursor Cursor.
     * @param int $limit Limit.
     * @return array
     */
    public static function execute(int $companyid, string $cursor = '', int $limit = 100): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'companyid' => $companyid,
            'cursor' => $cursor,
            'limit' => $limit,
        ]);
        $scope = tenant_scope::resolve($params['companyid']);
        if ($scope->companyid() !== $params['companyid']) {
            throw new \invalid_parameter_exception('The requested company is outside the authenticated scope.');
        }
        $context = context_company::instance($scope->companyid());
        self::validate_context($context);
        if (!is_siteadmin()) {
            iomad::require_capability('local/iomadconnect:read', $context, $scope->companyid());
        }
        return (new catalogue_exporter())->export(
            $scope->companyid(),
            $params['cursor'],
            $params['limit'],
        );
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'events' => new external_multiple_structure(new external_single_structure([
                'entitytype' => new external_value(PARAM_ALPHA, 'Entity type'),
                'entityid' => new external_value(PARAM_RAW_TRIMMED, 'Stable external ID'),
                'action' => new external_value(PARAM_ALPHA, 'Change action'),
                'modified' => new external_value(PARAM_INT, 'Modified timestamp'),
                'payload' => new external_value(PARAM_RAW, 'JSON payload'),
            ])),
            'cursor' => new external_value(PARAM_RAW_TRIMMED, 'Next opaque cursor'),
            'hasmore' => new external_value(PARAM_BOOL, 'More records are available'),
        ]);
    }
}
