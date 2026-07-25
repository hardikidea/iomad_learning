<?php
// This file is part of Moodle - http://moodle.org/

declare(strict_types=1);

/**
 * Tenant analytics post-install configuration.
 *
 * @package    local_tenantanalytics
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Create a per-install key for non-reversible learner pseudonyms.
 */
function xmldb_local_tenantanalytics_install(): void {
    set_config('pseudonymkey', bin2hex(random_bytes(32)), 'local_tenantanalytics');
}
