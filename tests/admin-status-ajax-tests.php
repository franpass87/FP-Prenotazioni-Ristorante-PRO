<?php
/**
 * Tests for admin booking status AJAX callbacks and reactivation flow.
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

if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', sys_get_temp_dir());
}

if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        // No-op for tests; hooks are not executed.
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
        // No-op for tests.
    }
}

if (!function_exists('register_activation_hook')) {
    function register_activation_hook($file, $callback) {
        // No-op for tests.
    }
}

if (!function_exists('register_deactivation_hook')) {
    function register_deactivation_hook($file, $callback) {
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

if (!function_exists('apply_filters')) {
    function apply_filters($hook, $value) {
        return $value;
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

if (!function_exists('sanitize_email')) {
    function sanitize_email($value) {
        return is_scalar($value) ? filter_var($value, FILTER_SANITIZE_EMAIL) : '';
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($value) {
        if (!is_scalar($value)) {
            return '';
        }

        $value = preg_replace('/[\t\0\x0B]+/', '', (string) $value);
        return trim($value);
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url) {
        return is_scalar($url) ? filter_var($url, FILTER_SANITIZE_URL) : '';
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return $text;
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return $text;
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

if (!function_exists('absint')) {
    function absint($maybeint) {
        return abs(intval($maybeint));
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        return $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value) {
        return true;
    }
}

if (!function_exists('get_locale')) {
    function get_locale() {
        return 'it_IT';
    }
}

if (!function_exists('get_post')) {
    function get_post($post_id) {
        global $rbf_test_posts;
        return $rbf_test_posts[$post_id] ?? null;
    }
}

if (!function_exists('get_post_meta')) {
    function get_post_meta($post_id, $key, $single = false) {
        global $rbf_test_meta;

        if (!isset($rbf_test_meta[$post_id]) || !array_key_exists($key, $rbf_test_meta[$post_id])) {
            return $single ? '' : [];
        }

        $value = $rbf_test_meta[$post_id][$key];
        return $single ? $value : [$value];
    }
}

if (!function_exists('update_post_meta')) {
    function update_post_meta($post_id, $key, $value) {
        global $rbf_test_meta;

        if (!isset($rbf_test_meta[$post_id])) {
            $rbf_test_meta[$post_id] = [];
        }

        $rbf_test_meta[$post_id][$key] = $value;
        return true;
    }
}

if (!function_exists('current_time')) {
    function current_time($type = 'mysql') {
        return $type === 'mysql' ? '2024-01-01 12:00:00' : '2024-01-01';
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id() {
        return 1;
    }
}

if (!function_exists('do_action')) {
    function do_action($hook, ...$args) {
        global $rbf_triggered_actions;
        $rbf_triggered_actions[] = [$hook, $args];
    }
}

if (!function_exists('check_ajax_referer')) {
    function check_ajax_referer($action = -1, $query_arg = false, $die = true) {
        // Always allow in tests.
        return true;
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability) {
        return true;
    }
}

if (!class_exists('Rbf_WP_Ajax_Response')) {
    class Rbf_WP_Ajax_Response extends Exception {
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

if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null, $status_code = null) {
        throw new Rbf_WP_Ajax_Response(false, $data, $status_code ?? 200);
    }
}

if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null, $status_code = null) {
        throw new Rbf_WP_Ajax_Response(true, $data, $status_code ?? 200);
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

if (!function_exists('rbf_release_slot_capacity')) {
    function rbf_release_slot_capacity($date, $slot_id, $people) {
        global $rbf_released_slots;
        $rbf_released_slots[] = [$date, $slot_id, $people];
        return true;
    }
}

if (!function_exists('rbf_book_slot_optimistic')) {
    function rbf_book_slot_optimistic($date, $slot_id, $people) {
        global $rbf_slot_behavior;
        $key = $date . '|' . $slot_id . '|' . $people;
        return $rbf_slot_behavior[$key] ?? ['success' => true];
    }
}

$rbf_test_posts = [];
$rbf_test_meta = [];
$rbf_slot_behavior = [];
$rbf_triggered_actions = [];
$rbf_released_slots = [];
$rbf_test_logs = [];

require_once RBF_PLUGIN_DIR . 'includes/utils.php';
require_once RBF_PLUGIN_DIR . 'includes/admin.php';

echo "Admin Status AJAX Tests\n";
echo "========================\n\n";

function rbf_reset_test_state() {
    global $rbf_test_posts, $rbf_test_meta, $rbf_slot_behavior, $rbf_triggered_actions, $rbf_released_slots, $rbf_test_logs;
    $rbf_test_posts = [];
    $rbf_test_meta = [];
    $rbf_slot_behavior = [];
    $rbf_triggered_actions = [];
    $rbf_released_slots = [];
    $rbf_test_logs = [];
}

function rbf_setup_cancelled_booking($booking_id) {
    global $rbf_test_posts, $rbf_test_meta;

    $rbf_test_posts[$booking_id] = (object) [
        'ID' => $booking_id,
        'post_type' => 'rbf_booking',
    ];

    $rbf_test_meta[$booking_id] = [
        'rbf_booking_status' => 'cancelled',
        'rbf_data' => '2024-12-24',
        'rbf_meal' => 'cena',
        'rbf_persone' => 4,
    ];
}

// Test 1: Direct function returns false when reactivation fails
rbf_reset_test_state();
$booking_id = 101;
rbf_setup_cancelled_booking($booking_id);
$rbf_slot_behavior['2024-12-24|cena|4'] = [
    'success' => false,
    'error' => 'insufficient_capacity',
];

$result = rbf_update_booking_status($booking_id, 'confirmed');

if ($result === false) {
    echo "✅ Reactivation failure prevents status update\n";
} else {
    echo "❌ Reactivation failure allowed status update\n";
}

$stored_status = get_post_meta($booking_id, 'rbf_booking_status', true);
if ($stored_status === 'cancelled') {
    echo "✅ Booking status remained cancelled after failure\n";
} else {
    echo "❌ Booking status changed unexpectedly to {$stored_status}\n";
}

if (isset($GLOBALS['rbf_test_meta'][$booking_id]['rbf_status_history'])) {
    echo "❌ Status history was recorded despite failure\n";
} else {
    echo "✅ No status history recorded when update blocked\n";
}

echo "\n";

// Test 2: AJAX callback propagates failure message
rbf_reset_test_state();
rbf_setup_cancelled_booking($booking_id);
$rbf_slot_behavior['2024-12-24|cena|4'] = [
    'success' => false,
    'error' => 'insufficient_capacity',
];

$_POST = [
    'booking_id' => (string) $booking_id,
    'status' => 'confirmed',
    '_ajax_nonce' => 'test',
];

try {
    rbf_update_booking_status_callback();
    echo "❌ AJAX callback did not throw expected error response\n";
} catch (Rbf_WP_Ajax_Response $response) {
    if ($response->success) {
        echo "❌ AJAX callback reported success despite failed reactivation\n";
    } elseif ($response->data === "Errore durante l'aggiornamento") {
        echo "✅ AJAX callback returned error message for failed reactivation\n";
    } else {
        $message = is_scalar($response->data) ? $response->data : json_encode($response->data);
        echo "❌ AJAX callback returned unexpected error: {$message}\n";
    }
}

$stored_status = get_post_meta($booking_id, 'rbf_booking_status', true);
if ($stored_status === 'cancelled') {
    echo "✅ Booking status unchanged via AJAX failure\n";
} else {
    echo "❌ Booking status changed via AJAX to {$stored_status}\n";
}

if (isset($GLOBALS['rbf_test_meta'][$booking_id]['rbf_status_history'])) {
    echo "❌ AJAX failure still created history entry\n";
} else {
    echo "✅ AJAX failure avoided history entry\n";
}

echo "\nTests completed.\n";
