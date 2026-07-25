<?php
// This file is part of Moodle - http://moodle.org/

namespace local_iomadcommerce\local;

use local_iomad\company;
use local_iomad\iomad;

/**
 * Resolve one authenticated IOMAD company boundary.
 *
 * @package    local_iomadcommerce
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tenant_scope {
    /**
     * Constructor.
     *
     * @param int $companyid Company.
     */
    public function __construct(private readonly int $companyid) {
    }

    /**
     * Resolve current company, with an explicit site administrator override.
     *
     * @param int $requested Requested company.
     * @return self
     */
    public static function resolve(int $requested = 0): self {
        global $DB;

        if (is_siteadmin() && $requested > 0) {
            if (!$DB->record_exists('local_iomad_companies', ['id' => $requested])) {
                throw new \invalid_parameter_exception('The requested company does not exist.');
            }
            return new self($requested);
        }
        $companyid = iomad::get_my_companyid(\context_system::instance(), false);
        if ($companyid <= 0) {
            throw new \required_capability_exception(
                \context_system::instance(),
                'local/iomadcommerce:viewcatalogue',
                'nopermissions',
                '',
            );
        }
        return new self($companyid);
    }

    /**
     * Company ID.
     *
     * @return int
     */
    public function companyid(): int {
        return $this->companyid;
    }

    /**
     * Course belongs to or is shared with this company.
     *
     * @param int $courseid Course.
     * @return bool
     */
    public function contains_course(int $courseid): bool {
        $courses = (new company($this->companyid))->get_menu_courses(
            shared: true,
            default: false,
            includehidden: true,
        );
        return array_key_exists($courseid, $courses);
    }

    /**
     * User belongs to this exact company.
     *
     * @param int $userid User.
     * @return bool
     */
    public function contains_user(int $userid): bool {
        global $DB;

        return $DB->record_exists('local_iomad_company_users', [
            'companyid' => $this->companyid,
            'userid' => $userid,
        ]);
    }
}
