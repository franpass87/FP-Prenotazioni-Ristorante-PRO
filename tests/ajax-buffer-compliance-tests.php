<?php
/**
 * Tests to ensure buffer-protected slots are excluded from the AJAX availability response.
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!defined('RBF_PLUGIN_DIR')) {
    define('RBF_PLUGIN_DIR', dirname(__DIR__) . '/');
}

if (!defined('RBF_PLUGIN_URL')) {
    define('RBF_PLUGIN_URL', 'http://example.com/wp-content/plugins/fp-prenotazioni-ristorante/');
}

if (!defined('RBF_PLUGIN_FILE')) {
    define('RBF_PLUGIN_FILE', RBF_PLUGIN_DIR . 'fp-prenotazioni-ristorante-pro.php');
}

if (!defined('RBF_VERSION')) {
    define('RBF_VERSION', 'test');
}

if (!defined('RBF_FORCE_LOG')) {
    define('RBF_FORCE_LOG', true);
}

if (function_exists('ini_set')) {
    ini_set('log_errors', 'On');
    ini_set('error_log', 'php://stdout');
}

if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}

if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

if (!class_exists('WP_Post')) {
    class WP_Post {
        public $post_content = '';
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        // Hooks are no-ops in tests.
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
        // Filters not required for tests.
    }
}

if (!function_exists('add_shortcode')) {
    function add_shortcode($tag, $callback) {
        // Shortcodes are not executed in tests.
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($hook, $value) {
        return $value;
    }
}

if (!function_exists('do_action')) {
    function do_action($hook, ...$args) {
        // No-op.
    }
}

if (!class_exists('Rbf_WP_Ajax_Response')) {
    class Rbf_WP_Ajax_Response extends Error {
        public $success;
        public $data;
        public $status_code;

        public function __construct($success, $data = null, $status_code = 200) {
            parent::__construct('', 0);
            $this->success = $success;
            $this->data = $data;
            $this->status_code = $status_code;
        }
    }
}

if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null, $status_code = null) {
        throw new Rbf_WP_Ajax_Response(true, $data, $status_code ?? 200);
    }
}

if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null, $status_code = null) {
        throw new Rbf_WP_Ajax_Response(false, $data, $status_code ?? 200);
    }
}

if (!function_exists('wp_send_json')) {
    function wp_send_json($response, $status_code = null) {
        throw new Rbf_WP_Ajax_Response(true, $response, $status_code ?? 200);
    }
}

if (!function_exists('wp_die')) {
    function wp_die($message = '') {
        throw new RuntimeException($message);
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action) {
        return true;
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($value) {
        if (!is_scalar($value)) {
            return '';
        }

        $value = preg_replace('/[\r\n\t\0\x0B]+/', '', (string) $value);
        return trim($value);
    }
}

if (!function_exists('absint')) {
    function absint($maybeint) {
        return abs((int) $maybeint);
    }
}

if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = []) {
        if (is_object($args)) {
            $args = get_object_vars($args);
        }

        if (!is_array($args)) {
            $args = [];
        }

        return array_merge($defaults, $args);
    }
}

if (!function_exists('get_option')) {
    function get_option($name, $default = false) {
        global $rbf_test_options;
        return array_key_exists($name, $rbf_test_options) ? $rbf_test_options[$name] : $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($name, $value) {
        global $rbf_test_options;
        $rbf_test_options[$name] = $value;
        return true;
    }
}

if (!function_exists('set_transient')) {
    function set_transient($key, $value, $expiration) {
        global $rbf_transients;
        $rbf_transients[$key] = $value;
        return true;
    }
}

if (!function_exists('get_transient')) {
    function get_transient($key) {
        global $rbf_transients;
        return array_key_exists($key, $rbf_transients) ? $rbf_transients[$key] : false;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($key) {
        global $rbf_transients;
        unset($rbf_transients[$key]);
        return true;
    }
}

if (!function_exists('is_admin')) {
    function is_admin() {
        return false;
    }
}

if (!function_exists('is_singular')) {
    function is_singular() {
        return false;
    }
}

if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file) {
        return RBF_PLUGIN_URL;
    }
}

if (!function_exists('has_shortcode')) {
    function has_shortcode($content, $tag) {
        return false;
    }
}

if (!function_exists('get_post')) {
    function get_post($post = null) {
        return new WP_Post();
    }
}

if (!function_exists('get_posts')) {
    function get_posts($args = []) {
        return [];
    }
}

if (!function_exists('get_permalink')) {
    function get_permalink($post = null) {
        return '';
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg($args, $url = '') {
        if (!is_array($args)) {
            return $url;
        }

        $query = http_build_query($args);
        if ($query === '') {
            return $url;
        }

        $separator = strpos($url, '?') === false ? '?' : '&';
        return $url . $separator . $query;
    }
}

if (!function_exists('sanitize_hex_color')) {
    function sanitize_hex_color($color) {
        if (!is_string($color)) {
            return '';
        }

        $color = trim($color);
        if ($color === '') {
            return '';
        }

        if ($color[0] !== '#') {
            $color = '#' . $color;
        }

        return $color;
    }
}

if (!function_exists('get_theme_mod')) {
    function get_theme_mod($name, $default = '') {
        return $default;
    }
}

if (!function_exists('wp_enqueue_style')) {
    function wp_enqueue_style() {
        // No-op for tests.
    }
}

if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script() {
        // No-op for tests.
    }
}

if (!function_exists('wp_localize_script')) {
    function wp_localize_script() {
        // No-op for tests.
    }
}

if (!function_exists('wp_add_inline_style')) {
    function wp_add_inline_style() {
        // No-op for tests.
    }
}

if (!function_exists('__')) {
    function __($text, $domain = null) {
        return $text;
    }
}

if (!function_exists('_e')) {
    function _e($text, $domain = null) {
        echo $text;
    }
}

if (!function_exists('get_locale')) {
    function get_locale() {
        return 'it_IT';
    }
}

if (!function_exists('get_privacy_policy_url')) {
    function get_privacy_policy_url() {
        return '';
    }
}

global $rbf_test_options, $rbf_transients, $rbf_logs, $rbf_test_existing_bookings, $rbf_test_current_query;
$rbf_test_options = [];
$rbf_transients = [];
$rbf_logs = [];
$rbf_test_existing_bookings = [];
$rbf_test_current_query = null;

function rbf_buffer_test_future_date() {
    static $future_date = null;

    if ($future_date === null) {
        $future_date = date('Y-m-d', strtotime('+30 days'));
    }

    return $future_date;
}

class Rbf_Test_WPDB {
    public $prefix = 'wp_';
    public $posts = 'wp_posts';
    public $postmeta = 'wp_postmeta';
    public $options = 'wp_options';

    public function prepare($query, ...$args) {
        if (count($args) === 1 && is_array($args[0])) {
            $args = $args[0];
        }

        if (stripos($query, 'show tables like') === false && count($args) >= 2) {
            global $rbf_test_current_query;
            $rbf_test_current_query = [
                'date' => (string) $args[0],
                'meal' => (string) $args[1],
            ];
        }

        if (strpos($query, '%') === false) {
            return $query;
        }

        $escaped = array_map(function($arg) {
            if (is_int($arg) || is_float($arg)) {
                return $arg;
            }
            return (string) $arg;
        }, $args);

        return vsprintf($query, $escaped);
    }

    public function get_var($query) {
        if (stripos($query, 'show tables like') !== false) {
            return $this->prefix . 'rbf_booking_status';
        }

        if (stripos($query, 'select coalesce(sum') !== false) {
            return $this->sum_people_for_current_query();
        }

        return null;
    }

    public function get_results($query) {
        if (stripos($query, 'from ' . $this->posts) !== false) {
            return $this->get_bookings_for_current_query();
        }

        return [];
    }

    private function sum_people_for_current_query() {
        global $rbf_test_existing_bookings, $rbf_test_current_query;
        if (!$rbf_test_current_query) {
            return 0;
        }

        $total = 0;
        foreach ($rbf_test_existing_bookings as $booking) {
            if ($booking['date'] === $rbf_test_current_query['date'] && $booking['meal'] === $rbf_test_current_query['meal']) {
                $total += (int) $booking['people'];
            }
        }

        return $total;
    }

    private function get_bookings_for_current_query() {
        global $rbf_test_existing_bookings, $rbf_test_current_query;
        $results = [];

        if (!$rbf_test_current_query) {
            return $results;
        }

        foreach ($rbf_test_existing_bookings as $booking) {
            if ($booking['date'] !== $rbf_test_current_query['date'] || $booking['meal'] !== $rbf_test_current_query['meal']) {
                continue;
            }

            $results[] = (object) [
                'booking_time' => $booking['time'],
                'people' => $booking['people'],
            ];
        }

        return $results;
    }
}

global $wpdb;
$wpdb = new Rbf_Test_WPDB();

require_once RBF_PLUGIN_DIR . 'includes/utils.php';
require_once RBF_PLUGIN_DIR . 'includes/ai-suggestions.php';
require_once RBF_PLUGIN_DIR . 'includes/frontend.php';

echo "AJAX Buffer Compliance Tests\n";
echo "=============================\n\n";

function rbf_buffer_test_reset_state() {
    global $rbf_transients, $rbf_test_existing_bookings, $rbf_test_current_query, $rbf_test_options;

    $rbf_transients = [];
    $rbf_test_existing_bookings = [];
    $rbf_test_current_query = null;

    $rbf_test_options['timezone_string'] = 'UTC';
    $rbf_test_options['gmt_offset'] = 0;
    $rbf_test_options['admin_email'] = 'admin@example.com';
    $rbf_test_options['rbf_settings'] = [
        'custom_meals' => [
            [
                'id' => 'cena',
                'name' => 'Cena',
                'enabled' => true,
                'available_days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'],
                'time_slots' => '19:00,19:15,19:30,20:00',
                'buffer_time_minutes' => 30,
                'buffer_time_per_person' => 0,
                'slot_duration_minutes' => 30,
                'capacity' => 10,
                'overbooking_limit' => 0,
                'tooltip' => '',
            ],
        ],
        'open_mon' => 'yes',
        'open_tue' => 'yes',
        'open_wed' => 'yes',
        'open_thu' => 'yes',
        'open_fri' => 'yes',
        'open_sat' => 'yes',
        'open_sun' => 'yes',
    ];
}

function rbf_buffer_test_assert_true($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function rbf_buffer_test_run_ajax_request($date, $meal, $people) {
    $_POST = [
        '_ajax_nonce' => 'nonce',
        'date' => $date,
        'meal' => $meal,
        'people' => $people,
    ];

    try {
        rbf_ajax_get_availability();
    } catch (Throwable $response) {
        $_POST = [];
        if ($response instanceof Rbf_WP_Ajax_Response) {
            return $response;
        }

        throw $response;
    }

    $_POST = [];
    throw new RuntimeException('AJAX handler did not send a response.');
}

function rbf_buffer_test_collect_values(array $available_times) {
    $values = [];
    foreach ($available_times as $entry) {
        if (is_array($entry) && isset($entry['value'])) {
            $values[] = $entry['value'];
        } elseif (is_array($entry) && isset($entry['time'])) {
            $values[] = $entry['time'];
        }
    }
    return $values;
}

function rbf_buffer_test_case_fresh_request_filters_conflicts() {
    global $rbf_test_existing_bookings;

    rbf_buffer_test_reset_state();
    $date = rbf_buffer_test_future_date();
    $rbf_test_existing_bookings = [
        ['date' => $date, 'meal' => 'cena', 'time' => '19:00', 'people' => 2],
    ];

    $debug_slots = rbf_get_available_time_slots($date, 'cena', 2);
    try {
        $debug_remaining = rbf_get_remaining_capacity($date, 'cena');
    } catch (Throwable $debug_e) {
        throw new RuntimeException('Remaining capacity failed: ' . $debug_e->getMessage());
    }
    $debug_total = rbf_get_effective_capacity('cena');
    $response = rbf_buffer_test_run_ajax_request($date, 'cena', 2);
    if (!$response->success) {
        $message = '';
        if (is_array($response->data) && isset($response->data['message'])) {
            $message = (string) $response->data['message'];
        }
        $slot_count = is_array($debug_slots) ? count($debug_slots) : 0;
        $remaining_str = isset($debug_remaining) ? (string) $debug_remaining : 'null';
        $total_str = isset($debug_total) ? (string) $debug_total : 'null';
        throw new RuntimeException(
            'Expected AJAX success response. Received error: ' . $message .
            ' (slots generated: ' . $slot_count . ', remaining: ' . $remaining_str . ', total: ' . $total_str . ')'
        );
    }

    $times = rbf_buffer_test_collect_values($response->data['available_times'] ?? []);

    rbf_buffer_test_assert_true(!in_array('19:00', $times, true), '19:00 slot should be blocked by buffer.');
    rbf_buffer_test_assert_true(!in_array('19:15', $times, true), '19:15 slot should be blocked by buffer.');
    rbf_buffer_test_assert_true(in_array('19:30', $times, true), '19:30 slot should remain available.');
}

function rbf_buffer_test_case_cached_response_filters_conflicts() {
    global $rbf_test_existing_bookings, $rbf_transients;

    rbf_buffer_test_reset_state();
    $date = rbf_buffer_test_future_date();
    $rbf_test_existing_bookings = [
        ['date' => $date, 'meal' => 'cena', 'time' => '19:00', 'people' => 2],
    ];

    $cache_key = rbf_build_times_cache_key('cena', $date, 2);
    $rbf_transients[$cache_key] = [
        'available_times' => [
            [
                'time' => '19:15',
                'slot' => 'cena',
                'remaining' => 8,
                'value' => '19:15',
            ],
            [
                'time' => '19:30',
                'slot' => 'cena',
                'remaining' => 8,
                'value' => '19:30',
            ],
        ],
        'message' => '',
        'total_capacity' => 10,
        'requested_people' => 2,
    ];

    $response = rbf_buffer_test_run_ajax_request($date, 'cena', 2);
    if (!$response->success) {
        $message = '';
        if (is_array($response->data) && isset($response->data['message'])) {
            $message = (string) $response->data['message'];
        }
        throw new RuntimeException('Expected successful response for cached data. Received error: ' . $message);
    }

    $times = rbf_buffer_test_collect_values($response->data['available_times'] ?? []);
    rbf_buffer_test_assert_true(!in_array('19:15', $times, true), 'Cached results should drop buffer-conflicting slot.');
    rbf_buffer_test_assert_true(in_array('19:30', $times, true), 'Cached results should keep valid slots.');
    rbf_buffer_test_assert_true(count($times) === 1, 'Only one buffer-compliant slot should remain after filtering.');
}

$tests = [
    'Fresh request filters buffer conflicts' => 'rbf_buffer_test_case_fresh_request_filters_conflicts',
    'Cached response filters buffer conflicts' => 'rbf_buffer_test_case_cached_response_filters_conflicts',
];

foreach ($tests as $label => $callable) {
    echo "🧪 {$label}... ";
    try {
        call_user_func($callable);
        echo "✅ Passed\n";
    } catch (Throwable $e) {
        echo "❌ Failed: " . $e->getMessage() . "\n";
    }
}

echo "\nTests completed.\n";
