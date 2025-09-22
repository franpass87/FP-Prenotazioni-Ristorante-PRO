<?php
/**
 * Weekly staff view timezone regression tests
 *
 * Ensures rbf_check_slot_availability honours the WordPress timezone
 * when validating same-day drag & drop moves.
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}

$original_timezone = date_default_timezone_get();
date_default_timezone_set('Pacific/Kiritimati');

$rbf_test_options = [
    'timezone_string' => 'America/Adak',
    'gmt_offset' => -10,
    'rbf_settings' => [
        'custom_meals' => [
            [
                'id' => 'cena',
                'name' => 'Cena',
                'enabled' => true,
                'capacity' => 20,
                'time_slots' => '19:00,19:30,20:00',
                'overbooking_limit' => 0,
                'available_days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'],
            ],
        ],
    ],
];

if (!function_exists('get_option')) {
    function get_option($name, $default = null) {
        global $rbf_test_options;

        switch ($name) {
            case 'rbf_settings':
                return $rbf_test_options['rbf_settings'] ?? $default;
            case 'timezone_string':
                return $rbf_test_options['timezone_string'] ?? $default;
            case 'gmt_offset':
                return $rbf_test_options['gmt_offset'] ?? $default;
            case 'admin_email':
                return 'admin@example.com';
            default:
                return $default;
        }
    }
}

if (!function_exists('update_option')) {
    function update_option($name, $value) {
        // no-op for tests
    }
}

if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = []) {
        return array_merge($defaults, is_array($args) ? $args : []);
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value) {
        return $value;
    }
}

if (!isset($wpdb)) {
    class RBF_Timezone_Test_WPDB {
        public $prefix = 'wp_';

        public function prepare($query, ...$args) {
            return $query;
        }

        public function get_var($query) {
            return 0;
        }
    }

    $wpdb = new RBF_Timezone_Test_WPDB();
}

$rbf_test_current_bookings = [];

if (!function_exists('rbf_calculate_current_bookings')) {
    function rbf_calculate_current_bookings($date, $meal) {
        global $rbf_test_current_bookings;
        return $rbf_test_current_bookings[$date][$meal] ?? 0;
    }
}

require_once __DIR__ . '/../includes/utils.php';

echo "Weekly Staff Timezone Tests\n";
echo "============================\n\n";

$wordpress_timezone = rbf_wp_timezone();
$server_now = new DateTimeImmutable('now');
$local_now = new DateTimeImmutable('now', $wordpress_timezone);
$local_date = $local_now->format('Y-m-d');
$previous_date = $local_now->modify('-1 day')->format('Y-m-d');

echo 'Server timezone: ' . date_default_timezone_get() . "\n";
echo 'WordPress timezone: ' . $wordpress_timezone->getName() . "\n";
echo 'Server now: ' . $server_now->format('Y-m-d H:i:s P') . "\n";
echo 'WordPress now: ' . $local_now->format('Y-m-d H:i:s P') . "\n\n";

$available_today = rbf_check_slot_availability($local_date, 'cena', '19:00', 2);
$available_previous = rbf_check_slot_availability($previous_date, 'cena', '19:00', 2);

function rbf_timezone_assert($description, $condition) {
    echo ($condition ? '✅' : '❌') . ' ' . $description . "\n";
}

rbf_timezone_assert(
    'Same-day slot remains available when server day is already advanced',
    $available_today === true
);
rbf_timezone_assert(
    'Previous-day slot is still rejected as a past date',
    $available_previous === false
);

date_default_timezone_set($original_timezone);

echo "\n";
