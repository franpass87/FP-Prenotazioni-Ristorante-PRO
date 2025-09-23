<?php
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}

if (!defined('RBF_PLUGIN_DIR')) {
    define('RBF_PLUGIN_DIR', __DIR__ . '/../');
}

if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}

$rbf_test_options = [
    'rbf_settings' => [
        'use_custom_meals' => 'yes',
        'custom_meals' => [
            [
                'id' => 'unlimited',
                'name' => 'Unlimited Meal',
                'enabled' => true,
                'capacity' => 0,
                'overbooking_limit' => 0,
                'time_slots' => '19:00,19:30',
                'available_days' => ['mon','tue','wed','thu','fri','sat','sun'],
                'slot_duration_minutes' => 60,
                'buffer_time_minutes' => 0,
                'buffer_time_per_person' => 0,
            ],
            [
                'id' => 'limited',
                'name' => 'Limited Meal',
                'enabled' => true,
                'capacity' => 10,
                'overbooking_limit' => 0,
                'time_slots' => '19:00,19:30',
                'available_days' => ['mon','tue','wed','thu','fri','sat','sun'],
                'slot_duration_minutes' => 60,
                'buffer_time_minutes' => 0,
                'buffer_time_per_person' => 0,
            ],
        ],
    ],
    'timezone_string' => 'UTC',
    'gmt_offset' => 0,
    'admin_email' => 'admin@example.com',
];

$rbf_test_transients = [];

function get_option($name, $default = null) {
    global $rbf_test_options;

    if ($name === 'admin_email' && !isset($rbf_test_options[$name])) {
        return 'admin@example.com';
    }

    return $rbf_test_options[$name] ?? $default;
}

function update_option($name, $value) {
    global $rbf_test_options;
    $rbf_test_options[$name] = $value;
    return true;
}

function get_transient($key) {
    global $rbf_test_transients;
    return $rbf_test_transients[$key] ?? false;
}

function set_transient($key, $value, $expiration = 0) {
    global $rbf_test_transients;
    $rbf_test_transients[$key] = $value;
    return true;
}

function delete_transient($key) {
    global $rbf_test_transients;
    unset($rbf_test_transients[$key]);
    return true;
}

function wp_parse_args($args, $defaults = []) {
    if (is_array($args)) {
        return array_merge($defaults, $args);
    }

    if (is_object($args)) {
        return array_merge($defaults, get_object_vars($args));
    }

    return $defaults;
}

function apply_filters($tag, $value) {
    return $value;
}

function sanitize_text_field($value) {
    return is_scalar($value) ? trim($value) : '';
}

function sanitize_textarea_field($value) {
    return is_scalar($value) ? trim($value) : '';
}

function sanitize_email($value) {
    return filter_var($value, FILTER_SANITIZE_EMAIL);
}

function is_email($value) {
    return (bool) filter_var($value, FILTER_VALIDATE_EMAIL);
}

function esc_url_raw($url) {
    return filter_var($url, FILTER_SANITIZE_URL);
}

function sanitize_key($key) {
    return is_string($key) ? preg_replace('/[^a-z0-9_]/', '', strtolower($key)) : '';
}

function absint($maybeint) {
    return abs((int) $maybeint);
}

function get_locale() {
    return 'it_IT';
}

function current_time($type = 'mysql') {
    return date($type === 'mysql' ? 'Y-m-d H:i:s' : 'Y-m-d');
}

function wp_json_encode($data) {
    return json_encode($data);
}

function rbf_get_remaining_capacity($date, $slot) {
    $total_capacity = rbf_get_effective_capacity($slot);

    if ($total_capacity <= 0) {
        return null;
    }

    $spots_taken = rbf_sum_active_bookings($date, $slot);

    return max(0, $total_capacity - (int) $spots_taken);
}

class RBF_Unlimited_Test_WPDB {
    public $prefix = 'wp_';
    public $posts = 'wp_posts';
    public $postmeta = 'wp_postmeta';

    private $slot_versions = [];
    private $booking_totals = [];

    public function prepare($query, ...$args) {
        return ['query' => $query, 'args' => $args];
    }

    public function get_row($prepared, $output = OBJECT) {
        if (!is_array($prepared)) {
            return null;
        }

        $query = $prepared['query'];
        $args = $prepared['args'];

        if (strpos($query, 'FROM ' . $this->prefix . 'rbf_slot_versions') !== false) {
            $date = $args[0] ?? '';
            $slot = $args[1] ?? '';
            $key = $date . '|' . $slot;

            if (!isset($this->slot_versions[$key])) {
                return null;
            }

            $row = $this->slot_versions[$key];
            return $output === ARRAY_A ? $row : (object) $row;
        }

        return null;
    }

    public function insert($table, $data, $format = null) {
        $key = ($data['slot_date'] ?? '') . '|' . ($data['slot_id'] ?? '');

        $this->slot_versions[$key] = [
            'id' => count($this->slot_versions) + 1,
            'slot_date' => $data['slot_date'],
            'slot_id' => $data['slot_id'],
            'version_number' => (int) $data['version_number'],
            'total_capacity' => (int) $data['total_capacity'],
            'booked_capacity' => (int) $data['booked_capacity'],
            'last_updated' => current_time('mysql'),
            'created_at' => current_time('mysql'),
        ];

        return 1;
    }

    public function update($table, $data, $where, $format = null, $where_format = null) {
        $key = ($where['slot_date'] ?? '') . '|' . ($where['slot_id'] ?? '');

        if (!isset($this->slot_versions[$key])) {
            return 0;
        }

        $current = $this->slot_versions[$key];

        if (isset($where['version_number']) && $current['version_number'] !== (int) $where['version_number']) {
            return 0;
        }

        foreach ($data as $field => $value) {
            if (in_array($field, ['booked_capacity', 'version_number', 'total_capacity'], true)) {
                $current[$field] = (int) $value;
            }
        }

        $current['last_updated'] = current_time('mysql');
        $this->slot_versions[$key] = $current;

        return 1;
    }

    public function get_var($prepared) {
        if (!is_array($prepared)) {
            return 0;
        }

        $query = $prepared['query'];
        $args = $prepared['args'];

        if (strpos($query, 'SHOW TABLES LIKE') !== false) {
            return 0;
        }

        if (strpos($query, 'COALESCE(SUM(pm_people.meta_value)') !== false) {
            $date = $args[0] ?? '';
            $meal = $args[1] ?? '';
            $key = $date . '|' . $meal;
            return $this->booking_totals[$key] ?? 0;
        }

        return 0;
    }

    public function get_results($prepared) {
        return [];
    }

    public function get_charset_collate() {
        return 'utf8mb4_general_ci';
    }

    public function set_booking_total($date, $meal, $people) {
        $key = $date . '|' . $meal;
        $this->booking_totals[$key] = (int) $people;
    }

    public function reset() {
        $this->slot_versions = [];
        $this->booking_totals = [];
    }
}

global $wpdb;
$wpdb = new RBF_Unlimited_Test_WPDB();

require_once __DIR__ . '/../includes/utils.php';
require_once __DIR__ . '/../includes/optimistic-locking.php';

echo "Unlimited Capacity Regression Tests\n";
echo "===================================\n\n";

function rbf_unlimited_assert_true($condition, $message) {
    echo ($condition ? '✅' : '❌') . ' ' . $message . "\n";
}

function rbf_unlimited_assert_equals($expected, $actual, $message) {
    if ($expected === $actual) {
        echo "✅ {$message}\n";
    } else {
        echo "❌ {$message} (expected " . var_export($expected, true) . ", got " . var_export($actual, true) . ")\n";
    }
}

$date = date('Y-m-d', strtotime('+1 day'));
$time = '19:00';

$wpdb->reset();
$wpdb->set_booking_total($date, 'unlimited', 20);

$cache_key = 'rbf_avail_' . $date . '_unlimited';
delete_transient($cache_key);

$initial_status = rbf_get_availability_status($date, 'unlimited');
rbf_unlimited_assert_true($initial_status['remaining'] === null, 'Unlimited meal reports unlimited remaining slots.');
$initial_level = $initial_status['level'];

$booking_result = rbf_book_slot_optimistic($date, 'unlimited', 4);
rbf_unlimited_assert_true($booking_result['success'] === true, 'Unlimited capacity booking succeeds.');
rbf_unlimited_assert_true($booking_result['remaining_capacity'] === null, 'Unlimited booking returns null remaining capacity.');

$drag_drop_allowed = rbf_check_slot_availability($date, 'unlimited', $time, 6);
rbf_unlimited_assert_true($drag_drop_allowed === true, 'Drag & drop move allowed for unlimited capacity meal.');

delete_transient($cache_key);
$after_status = rbf_get_availability_status($date, 'unlimited');
rbf_unlimited_assert_true($after_status['remaining'] === null, 'Availability reporting stays unlimited after booking.');
rbf_unlimited_assert_equals($initial_level, $after_status['level'], 'Availability level remains unchanged for unlimited meal.');

$limited_booking = rbf_book_slot_optimistic($date, 'limited', 11);
rbf_unlimited_assert_true($limited_booking['success'] === false && $limited_booking['error'] === 'insufficient_capacity', 'Finite capacity guard still blocks oversized booking.');

$wpdb->set_booking_total($date, 'limited', 10);
$limited_available = rbf_check_slot_availability($date, 'limited', $time, 2);
rbf_unlimited_assert_true($limited_available === false, 'Finite capacity meal still enforces remaining capacity.');

echo "\n";
