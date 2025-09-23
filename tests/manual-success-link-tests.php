<?php
/**
 * Tests for manual booking success URL detection.
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!defined('RBF_PLUGIN_DIR')) {
    define('RBF_PLUGIN_DIR', dirname(__DIR__) . '/');
}

if (!defined('RBF_VERSION')) {
    define('RBF_VERSION', '1.0-test');
}

$GLOBALS['rbf_test_options'] = [];
$GLOBALS['rbf_test_posts'] = [];
$GLOBALS['rbf_test_permalinks'] = [];

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

if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value) {
        return $value;
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($value) {
        if (is_scalar($value)) {
            $value = preg_replace('/[\r\n\t\0\x0B]/', '', (string) $value);
            return trim($value);
        }

        return '';
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($email) {
        return trim((string) $email);
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($value) {
        if (!is_string($value)) {
            return '';
        }

        $value = str_replace(["\r\n", "\r"], "\n", $value);
        return trim($value);
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        $key = strtolower((string) $key);
        return preg_replace('/[^a-z0-9_]/', '', $key);
    }
}

if (!function_exists('get_privacy_policy_url')) {
    function get_privacy_policy_url() {
        return '';
    }
}

if (!function_exists('has_shortcode')) {
    function has_shortcode($content, $tag) {
        return is_string($content) && strpos($content, '[' . $tag) !== false;
    }
}

if (!class_exists('WP_Post')) {
    class WP_Post {
        public $ID;
        public $post_content;
        public $post_status;
        public $post_type;

        public function __construct($data = []) {
            foreach ($data as $key => $value) {
                $this->$key = $value;
            }
        }
    }
}

if (!function_exists('get_post')) {
    function get_post($post_id) {
        $post_id = is_object($post_id) && isset($post_id->ID) ? $post_id->ID : $post_id;
        return $GLOBALS['rbf_test_posts'][$post_id] ?? null;
    }
}

if (!function_exists('get_posts')) {
    function get_posts($args = []) {
        $defaults = [
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1,
        ];

        $args = wp_parse_args($args, $defaults);

        $results = [];
        foreach ($GLOBALS['rbf_test_posts'] as $post) {
            if ($args['post_type'] !== 'any' && $post->post_type !== $args['post_type']) {
                continue;
            }

            if ($post->post_status !== $args['post_status']) {
                continue;
            }

            $results[] = $post;
        }

        usort($results, function($a, $b) {
            return $a->ID <=> $b->ID;
        });

        if ($args['posts_per_page'] > -1) {
            $results = array_slice($results, 0, $args['posts_per_page']);
        }

        return $results;
    }
}

if (!function_exists('get_permalink')) {
    function get_permalink($post) {
        $post_id = $post instanceof WP_Post ? $post->ID : intval($post);
        return $GLOBALS['rbf_test_permalinks'][$post_id] ?? '';
    }
}

if (!function_exists('home_url')) {
    function home_url($path = '') {
        $path = ltrim((string) $path, '/');
        return $path === '' ? 'https://example.com/' : 'https://example.com/' . $path;
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg($args, $url) {
        $parsed = parse_url($url);
        $existing = [];

        if (!empty($parsed['query'])) {
            parse_str($parsed['query'], $existing);
        }

        if (is_array($args)) {
            foreach ($args as $key => $value) {
                $existing[$key] = $value;
            }
        } elseif ($args !== null) {
            $existing[$args] = $url;
        }

        $parsed['query'] = http_build_query($existing);

        $scheme = isset($parsed['scheme']) ? $parsed['scheme'] . '://' : '';
        $host = $parsed['host'] ?? '';
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $path = $parsed['path'] ?? '';
        $query = $parsed['query'] ? '?' . $parsed['query'] : '';
        $fragment = isset($parsed['fragment']) ? '#' . $parsed['fragment'] : '';

        return $scheme . $host . $port . $path . $query . $fragment;
    }
}

require_once RBF_PLUGIN_DIR . 'includes/utils.php';

function rbf_manual_success_reset_environment() {
    $GLOBALS['rbf_test_posts'] = [];
    $GLOBALS['rbf_test_permalinks'] = [];
    update_option('rbf_settings', []);
    rbf_get_booking_confirmation_base_url(true);
}

function rbf_manual_success_add_page($id, $content, $permalink) {
    $GLOBALS['rbf_test_posts'][$id] = new WP_Post([
        'ID' => $id,
        'post_content' => $content,
        'post_status' => 'publish',
        'post_type' => 'page',
    ]);

    $GLOBALS['rbf_test_permalinks'][$id] = $permalink;
}

function assert_true($condition, $message) {
    if ($condition) {
        echo "✅ {$message}\n";
        return true;
    }

    echo "❌ {$message}\n";
    return false;
}

echo "Manual booking success URL tests\n";
echo "================================\n\n";

// Test 1: Automatically detected booking page
rbf_manual_success_reset_environment();
rbf_manual_success_add_page(10, 'Welcome page', 'https://example.com/welcome/');
rbf_manual_success_add_page(20, 'Book now [ristorante_booking_form]', 'https://example.com/book/');

$detected_url = rbf_get_booking_confirmation_base_url(true);
assert_true($detected_url === 'https://example.com/book/', 'Automatically detected booking page permalink');

$success_url = rbf_get_manual_booking_success_url(42, 'token-123');
$parsed_success = parse_url($success_url);
parse_str($parsed_success['query'] ?? '', $query_vars);
assert_true(strpos($success_url, 'https://example.com/book/') === 0, 'Success URL uses detected booking page');
assert_true(($query_vars['rbf_success'] ?? '') === '1', 'Success URL contains success flag');
assert_true(($query_vars['booking_id'] ?? '') === '42', 'Success URL contains booking ID');
assert_true(rbf_post_has_booking_form(20), 'Detected page contains booking shortcode');

echo "\n";

// Test 2: Fallback to configured booking page ID
rbf_manual_success_reset_environment();
rbf_manual_success_add_page(30, 'Contact us', 'https://example.com/contact/');
rbf_manual_success_add_page(40, 'Custom page', 'https://example.com/custom-booking/');
update_option('rbf_settings', ['booking_page_id' => 40]);

$fallback_url = rbf_get_booking_confirmation_base_url(true);
assert_true($fallback_url === 'https://example.com/custom-booking/', 'Fallback booking page uses configured option');

$fallback_success = rbf_get_manual_booking_success_url(77, 'token-xyz');
assert_true(strpos($fallback_success, 'https://example.com/custom-booking/') === 0, 'Success URL uses configured fallback page');

echo "\n";

// Test 3: Fallback to home_url when nothing is available
rbf_manual_success_reset_environment();
$home_base = rbf_get_booking_confirmation_base_url(true);
assert_true($home_base === '', 'No booking page detected when none exist');

$home_success = rbf_get_manual_booking_success_url(99, 'token-home');
assert_true(strpos($home_success, 'https://example.com/') === 0, 'Success URL falls back to site home');

echo "\nTests completed.\n";
