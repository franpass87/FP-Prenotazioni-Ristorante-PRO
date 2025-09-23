<?php
/**
 * People capacity tests for booking validation and configuration.
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!defined('RBF_PLUGIN_DIR')) {
    define('RBF_PLUGIN_DIR', dirname(__DIR__) . '/');
}

if (!defined('RBF_VERSION')) {
    define('RBF_VERSION', 'test');
}

$GLOBALS['rbf_test_options'] = [];
$GLOBALS['rbf_test_transients'] = [];

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action) {
        return true;
    }
}

if (!function_exists('do_action')) {
    function do_action($hook, ...$args) {
        // No-op for tests.
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        // No-op for tests.
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
        // No-op for tests.
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($hook, $value) {
        return $value;
    }
}

if (!function_exists('wp_doing_ajax')) {
    function wp_doing_ajax() {
        return false;
    }
}

if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data) {
        throw new RuntimeException('wp_send_json_error called during tests: ' . json_encode($data));
    }
}

if (!function_exists('wp_safe_redirect')) {
    function wp_safe_redirect($location) {
        return $location;
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg($args, $url) {
        $query = http_build_query($args);
        if (strpos($url, '?') === false) {
            return rtrim($url, '?') . '?' . $query;
        }

        $separator = substr($url, -1) === '&' ? '' : '&';
        return $url . $separator . $query;
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($value) {
        if (!is_scalar($value)) {
            return '';
        }

        $value = (string) $value;
        $value = strip_tags($value);
        $value = preg_replace('/[\r\n\t\0\x0B]+/', '', $value);
        return trim($value);
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($value) {
        if (!is_scalar($value)) {
            return '';
        }

        $value = (string) $value;
        $value = strip_tags($value, '<br><p>');
        $value = preg_replace('/[\r\0\x0B]+/', '', $value);
        return trim($value);
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($value) {
        return is_scalar($value) ? filter_var($value, FILTER_SANITIZE_EMAIL) : '';
    }
}

if (!function_exists('is_email')) {
    function is_email($email) {
        return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url) {
        return is_scalar($url) ? filter_var($url, FILTER_SANITIZE_URL) : '';
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        if (!is_string($key)) {
            return '';
        }

        $key = strtolower($key);
        return preg_replace('/[^a-z0-9_]/', '', $key);
    }
}

if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = []) {
        if (is_object($args)) {
            $args = get_object_vars($args);
        } elseif (!is_array($args)) {
            parse_str((string) $args, $args);
        }

        if (!is_array($args)) {
            $args = [];
        }

        return array_merge($defaults, $args);
    }
}

if (!function_exists('absint')) {
    function absint($maybeint) {
        return abs((int) $maybeint);
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        return $GLOBALS['rbf_test_options'][$option] ?? $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value) {
        $GLOBALS['rbf_test_options'][$option] = $value;
        return true;
    }
}

if (!function_exists('get_transient')) {
    function get_transient($key) {
        return $GLOBALS['rbf_test_transients'][$key] ?? false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient($key, $value, $expiration = 0) {
        $GLOBALS['rbf_test_transients'][$key] = $value;
        return true;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($key) {
        unset($GLOBALS['rbf_test_transients'][$key]);
        return true;
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

if (!function_exists('rbf_detect_source')) {
    function rbf_detect_source($data = []) {
        return [
            'bucket' => 'direct',
            'source' => 'direct',
            'medium' => 'none',
            'campaign' => '',
        ];
    }
}

require_once RBF_PLUGIN_DIR . 'includes/utils.php';
require_once RBF_PLUGIN_DIR . 'includes/booking-handler.php';

class RBF_People_Capacity_Tests {
    private $failures = 0;

    public function __construct() {
        echo "Running People Capacity Tests...\n\n";
        $this->run_all_tests();

        if ($this->failures === 0) {
            echo "\n✅ All people capacity tests passed!\n";
        } else {
            echo "\n❌ People capacity tests finished with {$this->failures} failure(s).\n";
        }
    }

    private function run_all_tests() {
        $this->test_people_limit_from_meals();
        $this->test_large_party_booking_validation();
    }

    private function reset_environment() {
        $GLOBALS['rbf_test_options'] = [];
        $GLOBALS['rbf_test_transients'] = [];
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36';
    }

    private function test_people_limit_from_meals() {
        echo "🧪 Testing people limit from active meals...\n";

        $this->reset_environment();

        $settings = [
            'use_custom_meals' => 'yes',
            'max_people' => 0,
            'custom_meals' => [
                [
                    'id' => 'pranzo',
                    'name' => 'Pranzo',
                    'enabled' => true,
                    'capacity' => 40,
                ],
                [
                    'id' => 'banquet',
                    'name' => 'Banquet',
                    'enabled' => true,
                    'capacity' => 120,
                ],
            ],
        ];

        $limit = rbf_get_people_max_limit($settings);
        $this->assert_equals(120, $limit, 'Highest configured meal capacity should define the people limit.');

        $unlimited_settings = [
            'use_custom_meals' => 'yes',
            'custom_meals' => [
                [
                    'id' => 'event',
                    'name' => 'Evento',
                    'enabled' => true,
                    'capacity' => 0,
                ],
                [
                    'id' => 'special',
                    'name' => 'Speciale',
                    'enabled' => true,
                    'capacity' => '0',
                ],
            ],
        ];

        $unlimited_limit = rbf_get_people_max_limit($unlimited_settings);
        $this->assert_equals(PHP_INT_MAX, $unlimited_limit, 'All unlimited meals should surface PHP_INT_MAX.');

        echo "✅ People limit configuration tests completed\n\n";
    }

    private function test_large_party_booking_validation() {
        echo "🧪 Testing large party booking validation...\n";

        $this->reset_environment();

        $meal_config = [
            'id' => 'banquet',
            'name' => 'Banquet',
            'enabled' => true,
            'capacity' => 120,
            'available_days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'],
            'time_slots' => '20:00,20:30',
        ];

        update_option('rbf_settings', [
            'use_custom_meals' => 'yes',
            'max_people' => 0,
            'custom_meals' => [$meal_config],
        ]);

        $request = [
            'rbf_nonce' => 'test',
            'rbf_meal' => 'banquet',
            'rbf_data' => date('Y-m-d', strtotime('+7 days')),
            'rbf_orario' => 'banquet|20:00',
            'rbf_persone' => '120',
            'rbf_nome' => 'Mario',
            'rbf_cognome' => 'Rossi',
            'rbf_email' => 'mario.rossi@example.com',
            'rbf_phone_prefix' => '+39',
            'rbf_tel_number' => '3331234567',
            'rbf_privacy' => 'yes',
            'rbf_lang' => 'it',
            'rbf_marketing' => 'no',
            'rbf_form_timestamp' => time() - 60,
            'rbf_website' => '',
        ];

        $result = rbf_validate_request($request, '', '');

        $this->assert_not_false($result, 'Large party within meal capacity should pass validation.');
        if ($result !== false) {
            $this->assert_equals(120, $result['people'], 'Validated booking should preserve the requested party size.');
        }

        echo "✅ Large party validation test completed\n\n";
    }

    private function assert_equals($expected, $actual, $message) {
        if ($expected === $actual) {
            echo "   ✅ {$message}\n";
        } else {
            $this->failures++;
            echo "   ❌ {$message} (expected " . var_export($expected, true) . ", got " . var_export($actual, true) . ")\n";
        }
    }

    private function assert_not_false($value, $message) {
        if ($value !== false) {
            echo "   ✅ {$message}\n";
        } else {
            $this->failures++;
            echo "   ❌ {$message}\n";
        }
    }
}

new RBF_People_Capacity_Tests();
