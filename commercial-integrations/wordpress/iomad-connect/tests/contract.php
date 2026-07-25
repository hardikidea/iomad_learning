<?php
// Static package contract test; WordPress integration tests run in the companion pipeline.

$plugin = dirname(__DIR__) . '/iomad-connect.php';
$source = file_get_contents($plugin);
if ($source === false) {
    fwrite(STDERR, "Plugin source is missing.\n");
    exit(1);
}
$required = [
    'local_iomadconnect_get_catalogue',
    'local_iomadconnect_apply_events',
    'IOMAD_CONNECT_TOKEN',
    'IOMAD_COMMERCE_WEBHOOK_SECRET',
    'woocommerce_order_status_completed',
    'woocommerce_order_refunded',
    'woocommerce_subscription_status_expired',
    'iomad_my_courses',
    '_iomad_category_externalid',
    'hash_hmac',
];
foreach ($required as $needle) {
    if (!str_contains($source, $needle)) {
        fwrite(STDERR, "Missing contract: {$needle}\n");
        exit(1);
    }
}
foreach (['password' . 'sync', 'wp_set_password'] as $forbidden) {
    if (str_contains(strtolower($source), strtolower($forbidden))) {
        fwrite(STDERR, "Forbidden password-sharing contract found.\n");
        exit(1);
    }
}
echo "IOMAD Connect WordPress package contract passed.\n";
