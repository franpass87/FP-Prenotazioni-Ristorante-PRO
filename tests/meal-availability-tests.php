<?php
/**
 * Targeted tests for rbf_is_meal_available_on_day() edge cases.
 *
 * Ensures custom meals without available_days defined behave predictably and
 * no longer trigger PHP warnings when the value is missing or empty.
 */

// Provide the minimal WordPress stubs required by the utility functions.
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!defined('RBF_PLUGIN_DIR')) {
    define('RBF_PLUGIN_DIR', dirname(__DIR__) . '/');
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        global $rbf_test_options;
        return $rbf_test_options[$option] ?? $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value) {
        global $rbf_test_options;
        $rbf_test_options[$option] = $value;
        return true;
    }
}

if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = []) {
        if (is_object($args)) {
            $args = get_object_vars($args);
        }

        if (!is_array($args)) {
            $args = [$args];
        }

        return array_merge($defaults, $args);
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value) {
        return $value;
    }
}

if (!function_exists('absint')) {
    function absint($maybeint) {
        return abs(intval($maybeint));
    }
}

// Preload deterministic settings for the tests.
$rbf_test_options = [
    'admin_email' => 'admin@example.com',
    'timezone_string' => 'UTC',
    'rbf_settings' => [
        'custom_meals' => [
            [
                'id' => 'standard',
                'name' => 'Standard Meal',
                'enabled' => true,
                'available_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
            ],
            [
                'id' => 'missing-days',
                'name' => 'Missing Days Meal',
                'enabled' => true,
                'available_days' => null,
            ],
            [
                'id' => 'empty-days',
                'name' => 'Empty Days Meal',
                'enabled' => true,
                'available_days' => [],
            ],
        ],
    ],
];

require_once RBF_PLUGIN_DIR . 'includes/utils.php';

echo "Meal Availability Tests\n";
echo "=======================\n\n";

/**
 * Helper assertion to compare boolean values.
 */
function rbf_assert_bool($expected, $actual, $message) {
    $result = ($expected === $actual) ? '✅' : '❌';
    echo sprintf("%s %s (expected %s, got %s)\n", $result, $message, var_export($expected, true), var_export($actual, true));
}

$monday = '2024-04-01'; // Monday
$sunday = '2024-04-07'; // Sunday

// Standard configuration continues to behave as expected.
rbf_assert_bool(true, rbf_is_meal_available_on_day('standard', $monday), 'Standard meal available on Monday');
rbf_assert_bool(false, rbf_is_meal_available_on_day('standard', $sunday), 'Standard meal not available on Sunday');

// Meals without available_days information are treated as unavailable.
rbf_assert_bool(false, rbf_is_meal_available_on_day('missing-days', $monday), 'Meal with null available_days is unavailable');
rbf_assert_bool(false, rbf_is_meal_available_on_day('empty-days', $monday), 'Meal with empty available_days is unavailable');

echo "\nTests complete.\n";
