<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Tenant commerce operator CLI.
 *
 * @package    local_iomadcommerce
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

$help = <<<'HELP'
Tenant commerce operations.

Options:
  --mode=doctor|product|order|pay|assign|assign-file|refund|report
  --company=SHORTNAME
  --course=SHORTNAME
  --product=EXTERNAL_ID
  --product-name=NAME
  --product-status=draft|free|paid|closed
  --price-minor=INTEGER
  --currency=CODE
  --access-days=INTEGER
  --checkout-url=HTTPS_URL
  --recommend=ID,ID
  --order=EXTERNAL_ID
  --user-idnumber=EXTERNAL_ID
  --users-file=/ABSOLUTE/users.csv
  --quantity=INTEGER
  --provider=KEY
  --event=IDEMPOTENCY_KEY
  --help

The command never prints learner names, email addresses, prices, or secrets.
HELP;

[$options, $unrecognised] = cli_get_params([
    'mode' => 'doctor',
    'company' => '',
    'course' => '',
    'product' => '',
    'product-name' => '',
    'product-status' => 'draft',
    'price-minor' => 0,
    'currency' => 'USD',
    'access-days' => 0,
    'checkout-url' => '',
    'recommend' => '',
    'order' => '',
    'user-idnumber' => '',
    'users-file' => '',
    'quantity' => 1,
    'provider' => 'local',
    'event' => '',
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
function iomadcommerce_cli_json(array $result): void {
    cli_writeln(json_encode(
        $result,
        JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
    ));
}

/**
 * Resolve company scope.
 *
 * @param string $shortname Company shortname.
 * @return array Company and scope.
 */
function iomadcommerce_cli_scope(string $shortname): array {
    global $DB;

    if ($shortname === '') {
        cli_error('--company is required.');
    }
    $company = $DB->get_record('local_iomad_companies', ['shortname' => $shortname], '*', MUST_EXIST);
    return [$company, new \local_iomadcommerce\local\tenant_scope((int)$company->id)];
}

/**
 * Resolve a company learner without printing personal data.
 *
 * @param \local_iomadcommerce\local\tenant_scope $scope Scope.
 * @param string $idnumber Stable user ID.
 * @return object
 */
function iomadcommerce_cli_user(
    \local_iomadcommerce\local\tenant_scope $scope,
    string $idnumber,
): object {
    global $DB;

    if ($idnumber === '') {
        cli_error('--user-idnumber is required.');
    }
    $user = $DB->get_record('user', [
        'idnumber' => $idnumber,
        'deleted' => 0,
        'suspended' => 0,
    ], 'id,idnumber', MUST_EXIST);
    if (!$scope->contains_user((int)$user->id)) {
        cli_error('The user is outside the requested company.');
    }
    return $user;
}

\core\session\manager::set_user(get_admin());
$mode = (string)$options['mode'];
if ($mode === 'doctor') {
    $manager = $DB->get_manager();
    $tables = [
        'local_iomadcommerce_product',
        'local_iomadcommerce_order',
        'local_iomadcommerce_item',
        'local_iomadcommerce_seat',
        'local_iomadcommerce_event',
    ];
    iomadcommerce_cli_json([
        'ok' => !array_filter($tables, static fn(string $table): bool => !$manager->table_exists($table)),
        'version' => (int)get_config('local_iomadcommerce', 'version'),
        'tables' => count($tables),
        'provider_credentials_configured' => getenv('IOMAD_COMMERCE_WEBHOOK_KEYS_JSON') !== false,
    ]);
    exit(0);
}

[$company, $scope] = iomadcommerce_cli_scope(trim((string)$options['company']));
$products = new \local_iomadcommerce\local\product_repository();
$orders = new \local_iomadcommerce\local\order_service();
$productexternalid = trim((string)$options['product']);

if ($mode === 'product') {
    $course = $DB->get_record('course', ['shortname' => $options['course']], '*', MUST_EXIST);
    [$product, $action] = $products->upsert($scope, $course, [
        'externalid' => $productexternalid,
        'name' => $options['product-name'],
        'status' => $options['product-status'],
        'priceminor' => $options['price-minor'],
        'currency' => $options['currency'],
        'accessdays' => $options['access-days'],
        'checkouturl' => $options['checkout-url'],
        'recommendations' => array_filter(array_map('trim', explode(',', $options['recommend']))),
    ]);
    iomadcommerce_cli_json([
        'ok' => true,
        'action' => $action,
        'company' => $company->shortname,
        'course' => $course->shortname,
        'product' => $product->externalid,
        'status' => $product->status,
    ]);
    exit(0);
}

if ($mode === 'report') {
    iomadcommerce_cli_json([
        'ok' => true,
        'company' => $company->shortname,
        'products' => $DB->count_records('local_iomadcommerce_product', ['companyid' => $company->id]),
        'orders' => $DB->count_records('local_iomadcommerce_order', ['companyid' => $company->id]),
        'paid_orders' => $DB->count_records('local_iomadcommerce_order', [
            'companyid' => $company->id,
            'status' => 'paid',
        ]),
        'active_seats' => $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {local_iomadcommerce_seat} s
               JOIN {local_iomadcommerce_item} i ON i.id = s.orderitemid
               JOIN {local_iomadcommerce_order} o ON o.id = i.orderid
              WHERE o.companyid = :companyid AND s.status = :status",
            ['companyid' => $company->id, 'status' => 'active'],
        ),
    ]);
    exit(0);
}

$orderexternalid = trim((string)$options['order']);
if ($orderexternalid === '') {
    cli_error('--order is required.');
}
if ($mode === 'order') {
    $product = $products->get($scope->companyid(), $productexternalid);
    $user = iomadcommerce_cli_user($scope, trim((string)$options['user-idnumber']));
    [, $action] = $orders->create(
        $scope,
        $product,
        (int)$user->id,
        $orderexternalid,
        (int)$options['quantity'],
        trim((string)$options['provider']),
    );
    iomadcommerce_cli_json([
        'ok' => true,
        'action' => $action,
        'company' => $company->shortname,
        'order' => $orderexternalid,
        'product' => $productexternalid,
    ]);
    exit(0);
}

if ($mode === 'assign') {
    $user = iomadcommerce_cli_user($scope, trim((string)$options['user-idnumber']));
    [, $action] = $orders->assign($scope, $orderexternalid, (int)$user->id, get_admin()->id);
    iomadcommerce_cli_json([
        'ok' => true,
        'action' => $action,
        'company' => $company->shortname,
        'order' => $orderexternalid,
    ]);
    exit(0);
}

if ($mode === 'assign-file') {
    $path = realpath((string)$options['users-file']);
    if ($path === false || !is_file($path) || !is_readable($path)) {
        cli_error('--users-file must identify a readable CSV file.');
    }
    $handle = fopen($path, 'rb');
    if ($handle === false || fgetcsv($handle) !== ['user_idnumber']) {
        cli_error('The assignment CSV header must be exactly user_idnumber.');
    }
    $row = 1;
    $assigned = 0;
    $unchanged = 0;
    $errors = [];
    $seen = [];
    while (($values = fgetcsv($handle)) !== false) {
        $row++;
        if ($row > 10001) {
            fclose($handle);
            cli_error('The assignment CSV may contain at most 10000 learners.');
        }
        $idnumber = trim((string)($values[0] ?? ''));
        if (count($values) !== 1 || $idnumber === '' || isset($seen[$idnumber])) {
            $errors[] = $row;
            continue;
        }
        $seen[$idnumber] = true;
        try {
            $user = $DB->get_record('user', [
                'idnumber' => $idnumber,
                'deleted' => 0,
                'suspended' => 0,
            ], 'id', MUST_EXIST);
            if (!$scope->contains_user((int)$user->id)) {
                throw new invalid_parameter_exception('User is outside the company.');
            }
            [, $action] = $orders->assign($scope, $orderexternalid, (int)$user->id, get_admin()->id);
            if ($action === 'assigned') {
                $assigned++;
            } else {
                $unchanged++;
            }
        } catch (Throwable) {
            $errors[] = $row;
        }
    }
    fclose($handle);
    iomadcommerce_cli_json([
        'ok' => !$errors,
        'company' => $company->shortname,
        'order' => $orderexternalid,
        'assigned' => $assigned,
        'unchanged' => $unchanged,
        'error_rows' => $errors,
    ]);
    exit($errors ? 1 : 0);
}

if (in_array($mode, ['pay', 'refund'], true)) {
    $status = $mode === 'pay' ? 'paid' : 'refunded';
    $eventkey = trim((string)$options['event']);
    if ($eventkey === '') {
        cli_error('--event is required for payment and refund transitions.');
    }
    [, $action] = $orders->transition(
        $scope,
        $orderexternalid,
        $status,
        $eventkey,
        hash('sha256', $status . ':' . $orderexternalid),
        get_admin()->id,
    );
    iomadcommerce_cli_json([
        'ok' => true,
        'action' => $action,
        'company' => $company->shortname,
        'order' => $orderexternalid,
        'status' => $status,
    ]);
    exit(0);
}

cli_error('Unsupported --mode.');
