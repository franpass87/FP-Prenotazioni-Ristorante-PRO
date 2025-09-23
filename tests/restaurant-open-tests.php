<?php
/**
 * Tests for rbf_is_restaurant_open utility function.
 * Provides manual verification for weekday and closed date logic.
 */

// Define ABSPATH to satisfy direct access checks
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

require_once __DIR__ . '/../includes/utils.php';

$rbf_test_settings = [
    'open_mon' => 'yes',
    'open_tue' => 'no',
    'open_wed' => 'yes',
    'open_thu' => 'yes',
    'open_fri' => 'yes',
    'open_sat' => 'yes',
    'open_sun' => 'yes',
    'closed_dates' => "2024-12-25\n2024-08-15 - 2024-08-20",
    'notification_email' => 'admin@example.com'
];

// --- WordPress function stubs for test environment ---
if (!function_exists('get_option')) {
    function get_option($name, $default = []) {
        global $rbf_test_settings;

        if ($name === 'rbf_settings') {
            return $rbf_test_settings;
        }
        if ($name === 'admin_email') {
            return 'admin@example.com';
        }
        if ($name === 'timezone_string') {
            return 'UTC';
        }
        if ($name === 'gmt_offset') {
            return 0;
        }
        return $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($name, $value) {
        global $rbf_test_settings;

        if ($name === 'rbf_settings' && is_array($value)) {
            $rbf_test_settings = $value;
        }
    }
}

if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = []) {
        return array_merge($defaults, $args);
    }
}

// Minimal version of rbf_get_closed_specific to support tests
if (!function_exists('rbf_get_closed_specific')) {
    function rbf_get_closed_specific($options = null) {
        $closed_dates_str = $options['closed_dates'] ?? '';
        $closed_items = array_filter(array_map('trim', explode("\n", $closed_dates_str)));
        $singles = [];
        $ranges = [];
        foreach ($closed_items as $item) {
            if (strpos($item, ' - ') !== false) {
                list($start, $end) = array_map('trim', explode(' - ', $item, 2));
                $ranges[] = ['from' => $start, 'to' => $end];
            } else {
                $singles[] = $item;
            }
        }
        return ['singles' => $singles, 'ranges' => $ranges, 'exceptions' => []];
    }
}

// --- Test cases ---
echo "Restaurant Open Function Tests\n";
echo "================================\n";

$tests = [
    ['2024-12-23', 'pranzo', true,  'Monday open'],
    ['2024-12-24', 'pranzo', false, 'Tuesday closed via weekday setting'],
    ['2024-12-25', 'pranzo', false, 'Specific closed date'],
    ['2024-08-17', 'pranzo', false, 'Within closed date range']
];

foreach ($tests as $case) {
    list($date, $meal, $expected, $label) = $case;
    $result = rbf_is_restaurant_open($date, $meal);
    $status = ($result === $expected) ? '✅' : '❌';
    echo sprintf("%s %s => %s\n", $status, $label, $result ? 'open' : 'closed');
}

echo "\n";

echo "Truthiness Normalization Checks\n";
echo "-------------------------------\n";

$rbf_test_settings['open_tue'] = '1';
$rbf_test_settings['open_sat'] = 'ON';

$truthy_tests = [
    ['2024-12-24', 'pranzo', true,  'Tuesday open when using "1"'],
    ['2024-12-28', 'pranzo', true,  'Saturday open when using "ON"'],
];

foreach ($truthy_tests as $case) {
    list($date, $meal, $expected, $label) = $case;
    $result = rbf_is_restaurant_open($date, $meal);
    $status = ($result === $expected) ? '✅' : '❌';
    echo sprintf("%s %s => %s\n", $status, $label, $result ? 'open' : 'closed');
}

echo "\n";
