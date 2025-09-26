<?php
/**
 * Stress test to ensure the calendar availability AJAX endpoint keeps the
 * number of database queries bounded even for large ranges.
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}

if (!defined('RBF_PLUGIN_DIR')) {
    define('RBF_PLUGIN_DIR', __DIR__ . '/../');
}

if (!defined('RBF_PLUGIN_FILE')) {
    define('RBF_PLUGIN_FILE', RBF_PLUGIN_DIR . 'fp-prenotazioni-ristorante-pro.php');
}

if (!defined('RBF_PLUGIN_URL')) {
    define('RBF_PLUGIN_URL', 'http://example.com/wp-content/plugins/fp-prenotazioni-ristorante/');
}

if (!defined('RBF_VERSION')) {
    define('RBF_VERSION', 'test');
}

if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
}

if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}

if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}

if (function_exists('ini_set')) {
    ini_set('log_errors', 'On');
    ini_set('error_log', 'php://stdout');
}

if (!class_exists('WP_Post')) {
    class WP_Post {
        public $post_content = '';
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        // Hooks are no-ops in the testing environment.
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
        // Filters are unused in this harness.
    }
}

if (!function_exists('add_shortcode')) {
    function add_shortcode($tag, $callback) {
        // Shortcodes are not executed in these tests.
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($hook, $value) {
        return $value;
    }
}

if (!function_exists('do_action')) {
    function do_action($hook, ...$args) {
        // Silence actions in the harness.
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
    function set_transient($key, $value, $expiration = 0) {
        global $rbf_test_transients;
        $rbf_test_transients[$key] = $value;
        return true;
    }
}

if (!function_exists('get_transient')) {
    function get_transient($key) {
        global $rbf_test_transients;
        return array_key_exists($key, $rbf_test_transients) ? $rbf_test_transients[$key] : false;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($key) {
        global $rbf_test_transients;
        unset($rbf_test_transients[$key]);
        return true;
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action) {
        return true;
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($value) {
        return is_scalar($value) ? trim($value) : '';
    }
}

if (!function_exists('wp_timezone')) {
    function wp_timezone() {
        return new DateTimeZone('UTC');
    }
}

if (!function_exists('wp_send_json_success')) {
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

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return (string) $text;
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return (string) $text;
    }
}

if (!function_exists('esc_url')) {
    function esc_url($url) {
        return (string) $url;
    }
}

if (!function_exists('wp_kses_post')) {
    function wp_kses_post($content) {
        return $content;
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1) {
        return 'nonce';
    }
}

if (!function_exists('get_permalink')) {
    function get_permalink($post_id) {
        return 'http://example.com/booking';
    }
}

if (!function_exists('get_bloginfo')) {
    function get_bloginfo($show = '', $filter = 'raw') {
        if ($show === 'name') {
            return 'Test Ristorante';
        }

        if ($show === 'admin_email') {
            return 'admin@example.com';
        }

        return 'test-value';
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($value) {
        return filter_var($value, FILTER_SANITIZE_EMAIL);
    }
}

$rbf_test_options = [
    'rbf_settings' => [
        'use_custom_meals' => 'yes',
        'custom_meals' => [
            [
                'id' => 'cena',
                'name' => 'Cena',
                'enabled' => true,
                'capacity' => 40,
                'overbooking_limit' => 0,
                'time_slots' => '19:00,20:00',
                'available_days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'],
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

$rbf_test_bookings = [];
$base_date = new DateTimeImmutable('2024-07-01');

for ($i = 0; $i < 90; $i++) {
    $current = $base_date->modify('+' . $i . ' days');
    $rbf_test_bookings[] = [
        'date' => $current->format('Y-m-d'),
        'meal' => 'cena',
        'time' => '19:00',
        'people' => ($i % 6) + 2,
    ];
}

class Rbf_Performance_WPDB {
    public $prefix = 'wp_';
    public $posts = 'wp_posts';
    public $postmeta = 'wp_postmeta';
    public $options = 'wp_options';
    public $num_queries = 0;

    private $bookings = [];
    private $current_query = null;
    private $last_bulk_query = null;

    public function __construct(array $bookings) {
        $this->bookings = $bookings;
    }

    public function prepare($query, ...$args) {
        if (count($args) === 1 && is_array($args[0])) {
            $args = $args[0];
        }

        if (stripos($query, 'between') !== false && count($args) >= 3) {
            $this->last_bulk_query = [
                'meal' => (string) $args[0],
                'start' => (string) $args[1],
                'end' => (string) $args[2],
            ];
        } elseif (stripos($query, 'show tables like') === false && count($args) >= 2) {
            $this->current_query = [
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

            return addslashes((string) $arg);
        }, $args);

        return vsprintf($query, $escaped);
    }

    public function get_var($query) {
        $this->num_queries++;

        if (stripos($query, 'show tables like') !== false) {
            return $this->prefix . 'rbf_booking_status';
        }

        if (stripos($query, 'select coalesce(sum') !== false) {
            return $this->sum_for_current_query();
        }

        return null;
    }

    public function get_results($query, $output = OBJECT) {
        $this->num_queries++;

        if (stripos($query, 'group by pm_date.meta_value') !== false) {
            return $this->format_rows($this->bulk_results(), $output);
        }

        if (stripos($query, 'from ' . $this->posts) !== false) {
            return $this->format_rows($this->detailed_results(), $output);
        }

        return $this->format_rows([], $output);
    }

    private function sum_for_current_query() {
        if (!$this->current_query) {
            return 0;
        }

        $total = 0;

        foreach ($this->bookings as $booking) {
            if ($booking['date'] === $this->current_query['date'] && $booking['meal'] === $this->current_query['meal']) {
                $total += (int) $booking['people'];
            }
        }

        return $total;
    }

    private function detailed_results() {
        if (!$this->current_query) {
            return [];
        }

        $results = [];

        foreach ($this->bookings as $booking) {
            if ($booking['date'] !== $this->current_query['date'] || $booking['meal'] !== $this->current_query['meal']) {
                continue;
            }

            $results[] = [
                'booking_time' => $booking['time'],
                'people' => $booking['people'],
            ];
        }

        return $results;
    }

    private function bulk_results() {
        if (!$this->last_bulk_query) {
            return [];
        }

        $totals = [];

        foreach ($this->bookings as $booking) {
            if ($booking['meal'] !== $this->last_bulk_query['meal']) {
                continue;
            }

            if ($booking['date'] < $this->last_bulk_query['start'] || $booking['date'] > $this->last_bulk_query['end']) {
                continue;
            }

            if (!isset($totals[$booking['date']])) {
                $totals[$booking['date']] = 0;
            }

            $totals[$booking['date']] += (int) $booking['people'];
        }

        ksort($totals);

        $results = [];

        foreach ($totals as $date => $people) {
            $results[] = [
                'booking_date' => $date,
                'total_people' => $people,
            ];
        }

        return $results;
    }

    private function format_rows(array $rows, $output) {
        $normalized = is_string($output) ? strtoupper($output) : $output;

        if ($normalized === 'ARRAY_A') {
            return $rows;
        }

        return array_map(function($row) {
            return (object) $row;
        }, $rows);
    }
}

$wpdb = new Rbf_Performance_WPDB($rbf_test_bookings);

global $wpdb;

require_once RBF_PLUGIN_DIR . 'includes/utils.php';
require_once RBF_PLUGIN_DIR . 'includes/frontend.php';

echo "Calendar Availability Performance Test\n";
echo "===================================\n\n";

function rbf_perf_run_request($start, $end) {
    global $wpdb;

    $_POST = [
        '_ajax_nonce' => 'nonce',
        'start_date' => $start,
        'end_date' => $end,
        'meal' => 'cena',
    ];

    $before_queries = $wpdb->num_queries;

    try {
        rbf_ajax_get_calendar_availability();
    } catch (Rbf_WP_Ajax_Response $response) {
        $_POST = [];
        $after_queries = $wpdb->num_queries;
        return [
            'response' => $response,
            'queries' => $after_queries - $before_queries,
        ];
    }

    $_POST = [];
    throw new RuntimeException('AJAX handler did not send a response.');
}

function rbf_perf_expected_people($date) {
    global $rbf_test_bookings;
    $total = 0;

    foreach ($rbf_test_bookings as $booking) {
        if ($booking['date'] === $date && $booking['meal'] === 'cena') {
            $total += (int) $booking['people'];
        }
    }

    return $total;
}

function rbf_perf_assert($condition, $message) {
    echo ($condition ? '✅' : '❌') . ' ' . $message . "\n";
}

$short_range = rbf_perf_run_request('2024-07-01', '2024-07-31');
$short_response = $short_range['response'];
$short_queries = $short_range['queries'];

rbf_perf_assert(
    count($short_response->data) === 31,
    '31-day range returns 31 availability entries'
);

rbf_perf_assert(
    $short_queries <= 3,
    '31-day range uses at most 3 database queries'
);

$sample_date = '2024-07-10';
$expected_people = rbf_perf_expected_people($sample_date);
$expected_remaining = 40 - $expected_people;
$availability_snapshot = $short_response->data[$sample_date] ?? [];

rbf_perf_assert(
    isset($availability_snapshot['remaining']) && $availability_snapshot['remaining'] === $expected_remaining,
    'Remaining capacity matches aggregated booking totals for ' . $sample_date
);

$long_range = rbf_perf_run_request('2024-07-01', '2024-08-29');
$long_queries = $long_range['queries'];
$long_response = $long_range['response'];

rbf_perf_assert(
    count($long_response->data) === 60,
    '60-day range returns availability for every day'
);

rbf_perf_assert(
    $long_queries <= 3,
    '60-day range still uses at most 3 database queries'
);

echo "\n";
