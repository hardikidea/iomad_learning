<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Tenant Master post-install hook.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Declare native Moodle course custom fields used by academic projections.
 */
function xmldb_local_tenantmaster_install(): void {
    (new \local_tenantmaster\local\course_metadata_service())->ensure_definitions();
    (new \local_tenantmaster\local\catalogue_service())->ensure_seeded();
}
