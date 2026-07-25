<?php
/**
 * Plugin Name: IOMAD Connect
 * Description: Tenant-scoped IOMAD catalogue, federated user, and WooCommerce purchase synchronization.
 * Version: 1.0.0
 * Requires at least: 6.5
 * Requires PHP: 8.2
 * License: GPL-3.0-or-later
 */

defined('ABSPATH') || exit;

final class IOMAD_Connect {
    private const OPTION = 'iomad_connect_settings';
    private const CURSOR = 'iomad_connect_catalogue_cursor';
    private const CRON = 'iomad_connect_sync_catalogue';
    private const MAX_PAGES = 5;

    /**
     * Register hooks.
     */
    public static function bootstrap(): void {
        add_action('init', [self::class, 'register_course_type']);
        add_action('admin_menu', [self::class, 'admin_menu']);
        add_action('admin_init', [self::class, 'register_settings']);
        add_action('admin_post_iomad_connect_test', [self::class, 'test_connection']);
        add_action('admin_post_iomad_connect_sync', [self::class, 'manual_sync']);
        add_filter('cron_schedules', [self::class, 'cron_schedules']);
        add_action(self::CRON, [self::class, 'sync_catalogue']);
        add_action('woocommerce_order_status_completed', [self::class, 'order_completed']);
        add_action('woocommerce_order_status_cancelled', [self::class, 'order_cancelled']);
        add_action('woocommerce_order_refunded', [self::class, 'order_refunded'], 10, 2);
        add_action('woocommerce_subscription_status_cancelled', [self::class, 'subscription_ended']);
        add_action('woocommerce_subscription_status_expired', [self::class, 'subscription_ended']);
        add_action('iomad_connect_process_order', [self::class, 'process_order'], 10, 4);
        add_shortcode('iomad_my_courses', [self::class, 'render_my_courses']);
    }

    /**
     * Activation.
     */
    public static function activate(): void {
        if (!wp_next_scheduled(self::CRON)) {
            wp_schedule_event(time() + 60, 'iomad_connect_five_minutes', self::CRON);
        }
    }

    /**
     * Deactivation.
     */
    public static function deactivate(): void {
        wp_clear_scheduled_hook(self::CRON);
    }

    /**
     * Add the selective-sync interval.
     *
     * @param array $schedules Existing schedules.
     * @return array
     */
    public static function cron_schedules(array $schedules): array {
        $schedules['iomad_connect_five_minutes'] = [
            'interval' => 300,
            'display' => __('Every five minutes', 'iomad-connect'),
        ];
        return $schedules;
    }

    /**
     * Register a non-public course mirror when WooCommerce is unavailable.
     */
    public static function register_course_type(): void {
        register_post_type('iomad_course', [
            'labels' => ['name' => __('IOMAD courses', 'iomad-connect')],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'supports' => ['title', 'editor', 'custom-fields'],
        ]);
        register_taxonomy('iomad_course_category', ['iomad_course'], [
            'labels' => ['name' => __('IOMAD course categories', 'iomad-connect')],
            'public' => false,
            'show_ui' => true,
            'hierarchical' => true,
        ]);
    }

    /**
     * Add settings.
     */
    public static function admin_menu(): void {
        add_options_page(
            __('IOMAD Connect', 'iomad-connect'),
            __('IOMAD Connect', 'iomad-connect'),
            'manage_options',
            'iomad-connect',
            [self::class, 'settings_page'],
        );
    }

    /**
     * Register non-secret settings.
     */
    public static function register_settings(): void {
        register_setting('iomad_connect', self::OPTION, [
            'type' => 'array',
            'sanitize_callback' => [self::class, 'sanitize_settings'],
            'default' => [],
        ]);
    }

    /**
     * Sanitize settings.
     *
     * @param mixed $input Input.
     * @return array
     */
    public static function sanitize_settings($input): array {
        $input = is_array($input) ? $input : [];
        $url = esc_url_raw((string)($input['baseurl'] ?? ''));
        if ($url !== '' && strtolower((string)wp_parse_url($url, PHP_URL_SCHEME)) !== 'https'
            && !self::is_local_url($url)) {
            add_settings_error(self::OPTION, 'https', __('Production IOMAD URLs must use HTTPS.', 'iomad-connect'));
            $url = '';
        }
        return [
            'baseurl' => untrailingslashit($url),
            'companyid' => absint($input['companyid'] ?? 0),
            'companyshortname' => sanitize_key($input['companyshortname'] ?? ''),
            'enabled' => empty($input['enabled']) ? 0 : 1,
        ];
    }

    /**
     * Render settings without exposing credentials.
     */
    public static function settings_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        $settings = self::settings();
        $tokenconfigured = defined('IOMAD_CONNECT_TOKEN') && strlen((string)IOMAD_CONNECT_TOKEN) >= 16;
        $secretconfigured = defined('IOMAD_COMMERCE_WEBHOOK_SECRET')
            && strlen((string)IOMAD_COMMERCE_WEBHOOK_SECRET) >= 32;
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('IOMAD Connect', 'iomad-connect'); ?></h1>
            <?php settings_errors(self::OPTION); ?>
            <form method="post" action="options.php">
                <?php settings_fields('iomad_connect'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="iomad-connect-baseurl"><?php echo esc_html__('IOMAD URL', 'iomad-connect'); ?></label></th>
                        <td><input class="regular-text" id="iomad-connect-baseurl" type="url"
                            name="<?php echo esc_attr(self::OPTION); ?>[baseurl]"
                            value="<?php echo esc_attr($settings['baseurl']); ?>" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="iomad-connect-companyid"><?php echo esc_html__('Company ID', 'iomad-connect'); ?></label></th>
                        <td><input id="iomad-connect-companyid" type="number" min="1"
                            name="<?php echo esc_attr(self::OPTION); ?>[companyid]"
                            value="<?php echo esc_attr((string)$settings['companyid']); ?>" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="iomad-connect-shortname"><?php echo esc_html__('Company shortname', 'iomad-connect'); ?></label></th>
                        <td><input id="iomad-connect-shortname" type="text"
                            name="<?php echo esc_attr(self::OPTION); ?>[companyshortname]"
                            value="<?php echo esc_attr($settings['companyshortname']); ?>" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Selective sync', 'iomad-connect'); ?></th>
                        <td><label><input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[enabled]"
                            value="1" <?php checked($settings['enabled'], 1); ?>>
                            <?php echo esc_html__('Import catalogue changes every five minutes', 'iomad-connect'); ?></label></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Credentials', 'iomad-connect'); ?></th>
                        <td>
                            <p><?php echo esc_html($tokenconfigured ? __('API token configured', 'iomad-connect') : __('Define IOMAD_CONNECT_TOKEN in wp-config.php', 'iomad-connect')); ?></p>
                            <p><?php echo esc_html($secretconfigured ? __('Webhook secret configured', 'iomad-connect') : __('Define IOMAD_COMMERCE_WEBHOOK_SECRET in wp-config.php', 'iomad-connect')); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <p>
                <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=iomad_connect_test'), 'iomad_connect_test')); ?>">
                    <?php echo esc_html__('Test connection', 'iomad-connect'); ?>
                </a>
                <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=iomad_connect_sync'), 'iomad_connect_sync')); ?>">
                    <?php echo esc_html__('Synchronize now', 'iomad-connect'); ?>
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Test one tenant-scoped request.
     */
    public static function test_connection(): void {
        self::authorize_admin_action('iomad_connect_test');
        try {
            $result = self::api('local_iomadconnect_get_catalogue', ['limit' => 1]);
            self::redirect_notice(isset($result['events']) ? 'connected' : 'failed');
        } catch (Throwable $exception) {
            self::redirect_notice('failed');
        }
    }

    /**
     * Run a requested synchronization.
     */
    public static function manual_sync(): void {
        self::authorize_admin_action('iomad_connect_sync');
        self::sync_catalogue();
        self::redirect_notice('synchronized');
    }

    /**
     * Import changed categories and courses as drafts.
     */
    public static function sync_catalogue(): void {
        $settings = self::settings();
        if (!$settings['enabled'] && !current_user_can('manage_options')) {
            return;
        }
        $cursor = (string)get_option(self::CURSOR, '');
        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            try {
                $result = self::api('local_iomadconnect_get_catalogue', [
                    'cursor' => $cursor,
                    'limit' => 100,
                ]);
            } catch (Throwable $exception) {
                return;
            }
            foreach ((array)($result['events'] ?? []) as $event) {
                $payload = json_decode((string)($event['payload'] ?? ''), true);
                if (!is_array($payload)) {
                    continue;
                }
                if (($event['entitytype'] ?? '') === 'category') {
                    self::upsert_category($payload);
                } else if (($event['entitytype'] ?? '') === 'course') {
                    self::upsert_course($payload);
                }
            }
            $cursor = sanitize_text_field((string)($result['cursor'] ?? $cursor));
            update_option(self::CURSOR, $cursor, false);
            if (empty($result['hasmore'])) {
                break;
            }
        }
    }

    /**
     * Send paid callbacks for all mapped order items.
     *
     * @param int $orderid WooCommerce order ID.
     */
    public static function order_completed(int $orderid): void {
        self::queue_order_state($orderid, 'paid', 'complete-' . $orderid);
    }

    /**
     * Send cancellation callbacks.
     *
     * @param int $orderid WooCommerce order ID.
     */
    public static function order_cancelled(int $orderid): void {
        self::queue_order_state($orderid, 'cancelled', 'cancel-' . $orderid);
    }

    /**
     * Send callbacks only after a full refund.
     *
     * @param int $orderid Order.
     * @param int $refundid Refund.
     */
    public static function order_refunded(int $orderid, int $refundid): void {
        $order = function_exists('wc_get_order') ? wc_get_order($orderid) : null;
        if ($order && (float)$order->get_remaining_refund_amount() <= 0.0) {
            self::queue_order_state($orderid, 'refunded', 'refund-' . $refundid);
        }
    }

    /**
     * Revoke the parent purchase when a subscription has actually ended.
     *
     * Renewal orders use the normal completed-order hook.
     *
     * @param mixed $subscription Subscription object or ID.
     */
    public static function subscription_ended($subscription): void {
        if (is_numeric($subscription) && function_exists('wcs_get_subscription')) {
            $subscription = wcs_get_subscription((int)$subscription);
        }
        if (!is_object($subscription)
            || !method_exists($subscription, 'get_parent_id')
            || !method_exists($subscription, 'get_id')) {
            return;
        }
        $orderid = (int)$subscription->get_parent_id();
        if ($orderid > 0) {
            $status = method_exists($subscription, 'get_status')
                ? sanitize_key((string)$subscription->get_status())
                : 'ended';
            self::queue_order_state(
                $orderid,
                'cancelled',
                'subscription-' . (int)$subscription->get_id() . '-' . $status,
            );
        }
    }

    /**
     * Render a translatable purchased-course shortcode.
     *
     * @return string
     */
    public static function render_my_courses(): string {
        if (!is_user_logged_in() || !function_exists('wc_get_orders')) {
            return '';
        }
        $courses = [];
        $orders = wc_get_orders([
            'customer_id' => get_current_user_id(),
            'status' => ['wc-processing', 'wc-completed'],
            'limit' => 50,
        ]);
        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                $productid = (int)$item->get_product_id();
                $externalid = sanitize_text_field(
                    (string)get_post_meta($productid, '_iomad_course_externalid', true),
                );
                if ($externalid !== '') {
                    $courses[$externalid] = sanitize_text_field((string)$item->get_name());
                }
            }
        }
        if (!$courses) {
            return '<p>' . esc_html__('No purchased courses are available.', 'iomad-connect') . '</p>';
        }
        $baseurl = self::settings()['baseurl'];
        $output = '<ul class="iomad-connect-course-library">';
        foreach ($courses as $externalid => $name) {
            $url = add_query_arg('search', $externalid, $baseurl . '/course/search.php');
            $output .= '<li><a href="' . esc_url($url) . '">' . esc_html($name) . '</a></li>';
        }
        return $output . '</ul>';
    }

    /**
     * Process a queued order transition with bounded retries.
     *
     * @param int $orderid Order.
     * @param string $status Status.
     * @param string $event Event.
     * @param int $attempt Attempt.
     */
    public static function process_order(int $orderid, string $status, string $event, int $attempt = 1): void {
        try {
            self::send_order_state($orderid, $status, $event);
        } catch (Throwable $exception) {
            if ($attempt < 3) {
                self::schedule_action(
                    time() + 60 * (2 ** ($attempt - 1)),
                    [$orderid, $status, $event, $attempt + 1],
                );
            }
        }
    }

    /**
     * Mirror one course into a draft WooCommerce product or private course post.
     *
     * @param array $course Course payload.
     */
    private static function upsert_course(array $course): void {
        $externalid = sanitize_text_field((string)($course['externalid'] ?? ''));
        if ($externalid === '') {
            return;
        }
        $existing = get_posts([
            'post_type' => class_exists('WooCommerce') ? 'product' : 'iomad_course',
            'post_status' => ['draft', 'publish', 'private'],
            'meta_key' => '_iomad_course_externalid',
            'meta_value' => $externalid,
            'numberposts' => 1,
            'fields' => 'ids',
        ]);
        $post = [
            'ID' => $existing ? (int)$existing[0] : 0,
            'post_type' => class_exists('WooCommerce') ? 'product' : 'iomad_course',
            'post_status' => $existing ? get_post_status((int)$existing[0]) : 'draft',
            'post_title' => sanitize_text_field((string)($course['fullname'] ?? $externalid)),
            'post_content' => '',
        ];
        $postid = wp_insert_post(wp_slash($post), true);
        if (!is_wp_error($postid)) {
            update_post_meta($postid, '_iomad_course_externalid', $externalid);
            update_post_meta($postid, '_virtual', 'yes');
            update_post_meta($postid, '_sold_individually', 'no');
            $categoryid = self::ensure_category(
                sanitize_text_field((string)($course['category_externalid'] ?? '')),
            );
            if ($categoryid > 0) {
                wp_set_object_terms($postid, [$categoryid], self::course_taxonomy(), false);
            }
        }
    }

    /**
     * Create or update one synchronized course category.
     *
     * @param array $category Category payload.
     */
    private static function upsert_category(array $category): void {
        $externalid = sanitize_text_field((string)($category['externalid'] ?? ''));
        $name = sanitize_text_field((string)($category['name'] ?? $externalid));
        if ($externalid === '' || $name === '') {
            return;
        }
        $termid = self::ensure_category($externalid);
        if ($termid <= 0) {
            return;
        }
        $parentid = self::find_category(
            sanitize_text_field((string)($category['parent_externalid'] ?? '')),
        );
        wp_update_term($termid, self::course_taxonomy(), [
            'name' => $name,
            'parent' => $parentid,
        ]);
    }

    /**
     * Resolve or create a category by stable external ID.
     *
     * @param string $externalid External ID.
     * @return int
     */
    private static function ensure_category(string $externalid): int {
        if ($externalid === '') {
            return 0;
        }
        $existing = self::find_category($externalid);
        if ($existing > 0) {
            return $existing;
        }
        $term = wp_insert_term($externalid, self::course_taxonomy(), [
            'slug' => 'iomad-' . sanitize_title($externalid) . '-' . substr(hash('sha256', $externalid), 0, 8),
        ]);
        if (is_wp_error($term)) {
            return 0;
        }
        $termid = (int)$term['term_id'];
        update_term_meta($termid, '_iomad_category_externalid', $externalid);
        return $termid;
    }

    /**
     * Find a synchronized category.
     *
     * @param string $externalid External ID.
     * @return int
     */
    private static function find_category(string $externalid): int {
        if ($externalid === '') {
            return 0;
        }
        $terms = get_terms([
            'taxonomy' => self::course_taxonomy(),
            'hide_empty' => false,
            'meta_key' => '_iomad_category_externalid',
            'meta_value' => $externalid,
            'fields' => 'ids',
            'number' => 1,
        ]);
        return is_wp_error($terms) || !$terms ? 0 : (int)$terms[0];
    }

    /**
     * Return the active course taxonomy.
     *
     * @return string
     */
    private static function course_taxonomy(): string {
        return class_exists('WooCommerce') ? 'product_cat' : 'iomad_course_category';
    }

    /**
     * Apply a Woo order transition through a signed commerce callback.
     *
     * @param int $orderid Order.
     * @param string $status Status.
     * @param string $event Event suffix.
     */
    private static function send_order_state(int $orderid, string $status, string $event): void {
        if (!function_exists('wc_get_order')) {
            return;
        }
        $order = wc_get_order($orderid);
        if (!$order) {
            return;
        }
        $user = get_user_by('id', (int)$order->get_user_id());
        if (!$user) {
            return;
        }
        $userid = self::user_externalid($user);
        if ($status === 'paid') {
            self::provision_federated_user($user, $userid, $orderid);
        }
        foreach ($order->get_items() as $itemid => $item) {
            $productid = (int)$item->get_product_id();
            $product = sanitize_text_field((string)get_post_meta($productid, '_iomad_course_externalid', true));
            if ($product === '') {
                continue;
            }
            self::commerce_webhook([
                'company' => self::settings()['companyshortname'],
                'event_id' => 'WC-' . get_current_blog_id() . '-' . $event . '-' . $itemid,
                'order' => 'WC-' . get_current_blog_id() . '-' . $orderid . '-' . $itemid,
                'product' => $product,
                'user_idnumber' => $userid,
                'quantity' => max(1, (int)$item->get_quantity()),
                'status' => $status,
            ]);
        }
    }

    /**
     * Queue an order callback outside the checkout request.
     *
     * @param int $orderid Order.
     * @param string $status Status.
     * @param string $event Event.
     */
    private static function queue_order_state(int $orderid, string $status, string $event): void {
        self::schedule_action(time() + 5, [$orderid, $status, $event, 1]);
    }

    /**
     * Use Action Scheduler when available, otherwise WordPress cron.
     *
     * @param int $timestamp Timestamp.
     * @param array $arguments Hook arguments.
     */
    private static function schedule_action(int $timestamp, array $arguments): void {
        if (function_exists('as_schedule_single_action')) {
            as_schedule_single_action(
                $timestamp,
                'iomad_connect_process_order',
                $arguments,
                'iomad-connect',
                true,
            );
            return;
        }
        wp_schedule_single_event($timestamp, 'iomad_connect_process_order', $arguments);
    }

    /**
     * Create or update a user through federated identity only.
     *
     * @param WP_User $user WordPress user.
     * @param string $externalid External ID.
     * @param int $orderid Order for idempotency.
     */
    private static function provision_federated_user(WP_User $user, string $externalid, int $orderid): void {
        $identityhash = substr(hash('sha256', implode('|', [
            $externalid,
            (string)$user->user_email,
            (string)$user->first_name,
            (string)$user->last_name,
        ])), 0, 12);
        $event = [[
            'eventid' => 'WC-USER-' . get_current_blog_id() . '-' . $orderid . '-' . $identityhash,
            'entitytype' => 'user',
            'entityid' => $externalid,
            'action' => 'upsert',
            'payload' => [
                'externalid' => $externalid,
                'username' => 'wp_' . get_current_blog_id() . '_' . $user->ID,
                'firstname' => (string)($user->first_name ?: 'Learner'),
                'lastname' => (string)($user->last_name ?: 'Account'),
                'email' => (string)$user->user_email,
            ],
        ]];
        self::api('local_iomadconnect_apply_events', [
            'systemkey' => 'wordpress',
            'eventsjson' => wp_json_encode($event, JSON_UNESCAPED_SLASHES),
        ]);
    }

    /**
     * Call the restricted IOMAD REST service.
     *
     * @param string $function Function.
     * @param array $params Parameters.
     * @return array
     */
    private static function api(string $function, array $params): array {
        $settings = self::settings();
        $token = defined('IOMAD_CONNECT_TOKEN') ? (string)IOMAD_CONNECT_TOKEN : '';
        if ($settings['baseurl'] === '' || $settings['companyid'] <= 0 || strlen($token) < 16) {
            throw new RuntimeException('IOMAD Connect is not configured.');
        }
        $response = wp_remote_post($settings['baseurl'] . '/webservice/rest/server.php', [
            'timeout' => 20,
            'redirection' => 0,
            'body' => array_merge($params, [
                'wstoken' => $token,
                'wsfunction' => $function,
                'moodlewsrestformat' => 'json',
                'companyid' => $settings['companyid'],
            ]),
        ]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            throw new RuntimeException('IOMAD request failed.');
        }
        $body = json_decode((string)wp_remote_retrieve_body($response), true);
        if (!is_array($body) || isset($body['exception'])) {
            throw new RuntimeException('IOMAD rejected the request.');
        }
        return $body;
    }

    /**
     * Send one HMAC-authenticated commerce callback.
     *
     * @param array $payload Callback.
     */
    private static function commerce_webhook(array $payload): void {
        $settings = self::settings();
        $secret = defined('IOMAD_COMMERCE_WEBHOOK_SECRET') ? (string)IOMAD_COMMERCE_WEBHOOK_SECRET : '';
        if ($settings['baseurl'] === '' || strlen($secret) < 32) {
            throw new RuntimeException('IOMAD commerce callback is not configured.');
        }
        $body = (string)wp_json_encode($payload, JSON_UNESCAPED_SLASHES);
        $timestamp = time();
        $nonce = wp_generate_password(32, false, false);
        $signature = hash_hmac('sha256', $timestamp . "\n" . $nonce . "\n" . $body, $secret);
        $response = wp_remote_post($settings['baseurl'] . '/local/iomadcommerce/webhook.php', [
            'timeout' => 20,
            'redirection' => 0,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-IOMAD-Timestamp' => (string)$timestamp,
                'X-IOMAD-Nonce' => $nonce,
                'X-IOMAD-Signature' => $signature,
            ],
            'body' => $body,
        ]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            throw new RuntimeException('IOMAD commerce callback failed.');
        }
    }

    /**
     * Stable non-secret user identity.
     *
     * @param WP_User $user User.
     * @return string
     */
    private static function user_externalid(WP_User $user): string {
        return 'WP-' . get_current_blog_id() . '-' . $user->ID;
    }

    /**
     * Read settings.
     *
     * @return array
     */
    private static function settings(): array {
        return wp_parse_args((array)get_option(self::OPTION, []), [
            'baseurl' => '',
            'companyid' => 0,
            'companyshortname' => '',
            'enabled' => 0,
        ]);
    }

    /**
     * Authorize an admin-post action.
     *
     * @param string $nonceaction Nonce action.
     */
    private static function authorize_admin_action(string $nonceaction): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Access denied.', 'iomad-connect'), 403);
        }
        check_admin_referer($nonceaction);
    }

    /**
     * Redirect with a non-sensitive status code.
     *
     * @param string $status Status.
     */
    private static function redirect_notice(string $status): void {
        wp_safe_redirect(add_query_arg([
            'page' => 'iomad-connect',
            'iomad_connect_status' => sanitize_key($status),
        ], admin_url('options-general.php')));
        exit;
    }

    /**
     * Permit HTTP only for loopback development.
     *
     * @param string $url URL.
     * @return bool
     */
    private static function is_local_url(string $url): bool {
        return in_array((string)wp_parse_url($url, PHP_URL_HOST), ['localhost', '127.0.0.1', '::1'], true);
    }
}

IOMAD_Connect::bootstrap();
register_activation_hook(__FILE__, [IOMAD_Connect::class, 'activate']);
register_deactivation_hook(__FILE__, [IOMAD_Connect::class, 'deactivate']);
