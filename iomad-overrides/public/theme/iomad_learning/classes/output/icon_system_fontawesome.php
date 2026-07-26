<?php
// This file is part of Moodle - http://moodle.org/

namespace theme_iomad_learning\output;

use theme_iomad_learning\local\icon_catalog;

/**
 * Product-wide Font Awesome icon system.
 *
 * @package    theme_iomad_learning
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class icon_system_fontawesome extends \core\output\icon_system_fontawesome {
    /**
     * Add reviewed IOMAD Learning mappings before plugin fallback maps.
     *
     * @return array<string, string>
     */
    public function get_core_icon_map(): array {
        return array_replace(parent::get_core_icon_map(), icon_catalog::fontawesome_map());
    }
}
