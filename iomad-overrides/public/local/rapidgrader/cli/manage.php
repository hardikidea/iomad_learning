<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Validate, seed, report, and update tenant grades from the CLI.
 *
 * @package    local_rapidgrader
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/gradelib.php');

$help = <<<'HELP'
Tenant RapidGrader operations.

Options:
  --mode=doctor|create-item|report|set
  --company=SHORTNAME          IOMAD company shortname.
  --course=SHORTNAME           Course shortname.
  --item-idnumber=IDNUMBER     Stable manual grade item idnumber.
  --item-name=NAME             Name used when creating an item.
  --user-idnumber=IDNUMBER     Stable learner external ID.
  --grade=VALUE                Numeric grade, or empty to remove a grade.
  --minimum=VALUE              Minimum grade for a new item (default: 0).
  --maximum=VALUE              Maximum grade for a new item (default: 100).
  --help                       Show this help.

The command never prints learner names, email addresses, or grade values.
HELP;

[$options, $unrecognised] = cli_get_params([
    'mode' => 'doctor',
    'company' => '',
    'course' => '',
    'item-idnumber' => '',
    'item-name' => '',
    'user-idnumber' => '',
    'grade' => '',
    'minimum' => '0',
    'maximum' => '100',
    'help' => false,
], [
    'h' => 'help',
]);

if ($unrecognised) {
    cli_error('Unknown options: ' . implode(', ', $unrecognised));
}
if ($options['help']) {
    cli_writeln($help);
    exit(0);
}

/**
 * Write deterministic JSON.
 *
 * @param array $result Result.
 */
function rapidgrader_cli_json(array $result): void {
    cli_writeln(json_encode(
        $result,
        JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
    ));
}

/**
 * Resolve and verify one company/course pair.
 *
 * @param string $companyshortname Company shortname.
 * @param string $courseshortname Course shortname.
 * @return array Company and course records.
 */
function rapidgrader_cli_scope(string $companyshortname, string $courseshortname): array {
    global $DB;

    if ($companyshortname === '' || $courseshortname === '') {
        cli_error('--company and --course are required.');
    }
    $company = $DB->get_record(
        'local_iomad_companies',
        ['shortname' => $companyshortname],
        '*',
        MUST_EXIST,
    );
    $course = $DB->get_record('course', ['shortname' => $courseshortname], '*', MUST_EXIST);
    $scope = new \local_rapidgrader\local\course_scope((int)$company->id);
    if (!array_key_exists((int)$course->id, $scope->courses())) {
        cli_error('The course is outside the requested company or is not gradable by the CLI actor.');
    }
    return [$company, $course, $scope];
}

/**
 * Validate a stable grade-item ID.
 *
 * @param string $idnumber Item ID.
 * @return string
 */
function rapidgrader_cli_item_id(string $idnumber): string {
    $idnumber = trim($idnumber);
    if ($idnumber === '' || !preg_match('/^[A-Za-z0-9_.-]{3,100}$/', $idnumber)) {
        cli_error('--item-idnumber must use letters, numbers, dot, dash, or underscore.');
    }
    return $idnumber;
}

\core\session\manager::set_user(get_admin());
$mode = (string)$options['mode'];

if ($mode === 'doctor') {
    $formats = array_keys(\core_component::get_plugin_list('dataformat'));
    $requiredformats = \local_rapidgrader\local\exporter::FORMATS;
    rapidgrader_cli_json([
        'ok' => (int)get_config('local_rapidgrader', 'version') >= 2026072500
            && !array_diff($requiredformats, $formats),
        'version' => (int)get_config('local_rapidgrader', 'version'),
        'formats' => $requiredformats,
        'maximum_update_cells' => \local_rapidgrader\local\gradebook_service::MAX_UPDATE_CELLS,
    ]);
    exit(0);
}

[$company, $course, $scope] = rapidgrader_cli_scope(
    trim((string)$options['company']),
    trim((string)$options['course']),
);
$itemidnumber = rapidgrader_cli_item_id((string)$options['item-idnumber']);

if ($mode === 'create-item') {
    $existing = \grade_item::fetch([
        'courseid' => $course->id,
        'idnumber' => $itemidnumber,
    ]);
    if ($existing) {
        if ($existing->itemtype !== 'manual') {
            cli_error('The stable item ID belongs to a non-manual grade item.');
        }
        rapidgrader_cli_json([
            'ok' => true,
            'action' => 'unchanged',
            'company' => $company->shortname,
            'course' => $course->shortname,
            'item_idnumber' => $itemidnumber,
        ]);
        exit(0);
    }
    if (!is_numeric($options['minimum']) || !is_numeric($options['maximum'])) {
        cli_error('--minimum and --maximum must be numeric.');
    }
    $minimum = (float)$options['minimum'];
    $maximum = (float)$options['maximum'];
    if ($maximum <= $minimum) {
        cli_error('--maximum must be greater than --minimum.');
    }
    $itemname = trim((string)$options['item-name']);
    if ($itemname === '') {
        cli_error('--item-name is required when creating a grade item.');
    }
    $category = \grade_category::fetch_course_category((int)$course->id);
    $item = new \grade_item();
    $item->courseid = $course->id;
    $item->categoryid = $category->id;
    $item->itemtype = 'manual';
    $item->itemname = $itemname;
    $item->idnumber = $itemidnumber;
    $item->gradetype = GRADE_TYPE_VALUE;
    $item->grademin = $minimum;
    $item->grademax = $maximum;
    $item->insert('local_rapidgrader');
    grade_regrade_final_grades((int)$course->id);
    rapidgrader_cli_json([
        'ok' => true,
        'action' => 'created',
        'company' => $company->shortname,
        'course' => $course->shortname,
        'item_idnumber' => $itemidnumber,
    ]);
    exit(0);
}

$item = \grade_item::fetch([
    'courseid' => $course->id,
    'idnumber' => $itemidnumber,
]);
if (!$item) {
    cli_error('The requested grade item does not exist in this course.');
}
$service = new \local_rapidgrader\local\gradebook_service($scope, $course);

if ($mode === 'report') {
    rapidgrader_cli_json([
        'ok' => true,
        'company' => $company->shortname,
        'course' => $course->shortname,
        'item_idnumber' => $itemidnumber,
        'learners' => $service->learner_count(),
        'graded' => count(array_filter(
            $service->learners(),
            static fn(object $user): bool => $service->grade($item, (int)$user->id) !== null,
        )),
    ]);
    exit(0);
}

if ($mode === 'set') {
    $useridnumber = trim((string)$options['user-idnumber']);
    if ($useridnumber === '') {
        cli_error('--user-idnumber is required.');
    }
    $user = $DB->get_record('user', [
        'idnumber' => $useridnumber,
        'deleted' => 0,
        'suspended' => 0,
    ], 'id,idnumber', MUST_EXIST);
    if (!$scope->contains_user((int)$user->id)) {
        cli_error('The learner is outside the requested company.');
    }
    $grade = trim((string)$options['grade']);
    if ($grade !== '' && !is_numeric($grade)) {
        cli_error('--grade must be numeric or empty.');
    }
    $changed = $service->update([
        $item->id => [
            $user->id => $grade,
        ],
    ], get_admin()->id);
    rapidgrader_cli_json([
        'ok' => true,
        'action' => $changed ? 'updated' : 'unchanged',
        'company' => $company->shortname,
        'course' => $course->shortname,
        'item_idnumber' => $itemidnumber,
    ]);
    exit(0);
}

cli_error('Unsupported --mode. Use doctor, create-item, report, or set.');
