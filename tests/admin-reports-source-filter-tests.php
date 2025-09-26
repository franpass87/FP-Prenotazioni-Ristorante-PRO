<?php
/**
 * Integration test ensuring nested source filters are safely normalized on the reports page.
 */
declare(strict_types=1);

error_reporting(E_ALL);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!defined('RBF_PLUGIN_DIR')) {
    define('RBF_PLUGIN_DIR', dirname(__DIR__) . '/');
}

if (!defined('RBF_PLUGIN_URL')) {
    define('RBF_PLUGIN_URL', 'http://example.com/wp-content/plugins/fp-prenotazioni-ristorante/');
}

if (!function_exists('__')) {
    function __($text, $domain = null)
    {
        return $text;
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1)
    {
        // Hooks are not executed during the test harness.
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1)
    {
        // Filters are not executed during the test harness.
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($hook, $value)
    {
        return $value;
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability)
    {
        return true;
    }
}

if (!function_exists('rbf_translate_string')) {
    function rbf_translate_string($text)
    {
        return $text;
    }
}

if (!function_exists('rbf_get_booking_capability')) {
    function rbf_get_booking_capability()
    {
        return 'rbf_manage_bookings';
    }
}

if (!function_exists('rbf_get_settings_capability')) {
    function rbf_get_settings_capability()
    {
        return 'manage_options';
    }
}

if (!function_exists('rbf_require_booking_capability')) {
    function rbf_require_booking_capability()
    {
        return true;
    }
}

if (!function_exists('rbf_get_booking_statuses')) {
    function rbf_get_booking_statuses()
    {
        return [
            'confirmed' => 'Confermata',
        ];
    }
}

if (!function_exists('rbf_get_meal_config')) {
    function rbf_get_meal_config($meal_id)
    {
        return [
            'price' => 40,
        ];
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($value)
    {
        if (!is_scalar($value)) {
            return '';
        }

        $value = preg_replace('/[\r\n\t\0\x0B]+/', '', (string) $value);
        return trim($value);
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key)
    {
        if (!is_scalar($key)) {
            return '';
        }

        $key = strtolower((string) $key);
        return preg_replace('/[^a-z0-9_\-]/', '', $key);
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value)
    {
        if (is_array($value)) {
            return array_map('wp_unslash', $value);
        }

        return is_string($value) ? stripslashes($value) : $value;
    }
}

if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script(...$args)
    {
        $GLOBALS['rbf_enqueued_scripts'][] = $args;
    }
}

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($string)
    {
        return strip_tags((string) $string);
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, $options = 0, $depth = 512)
    {
        return json_encode($data, $options, $depth);
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg($args, $url = '')
    {
        if (!is_array($args)) {
            $args = [];
        }

        $query = http_build_query($args);
        if ($url === '') {
            return $query === '' ? '' : '?' . $query;
        }

        $separator = strpos($url, '?') === false ? '?' : '&';
        return $url . ($query === '' ? '' : $separator . $query);
    }
}

if (!function_exists('admin_url')) {
    function admin_url($path = '')
    {
        return 'http://example.com/wp-admin/' . ltrim((string) $path, '/');
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text)
    {
        return (string) $text;
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text)
    {
        return (string) $text;
    }
}

if (!function_exists('esc_url')) {
    function esc_url($url)
    {
        return (string) $url;
    }
}

if (!function_exists('esc_js')) {
    function esc_js($text)
    {
        return (string) $text;
    }
}

if (!function_exists('checked')) {
    function checked($checked, $current = true, $echo = true)
    {
        $result = ($checked == $current) ? ' checked="checked"' : '';
        if ($echo) {
            echo $result;
        }

        return $result;
    }
}

if (!function_exists('selected')) {
    function selected($selected, $current = true, $echo = true)
    {
        $result = ($selected == $current) ? ' selected="selected"' : '';
        if ($echo) {
            echo $result;
        }

        return $result;
    }
}

if (!function_exists('number_format_i18n')) {
    function number_format_i18n($number, $decimals = 0)
    {
        return number_format((float) $number, $decimals, ',', '.');
    }
}

class RBF_WPDB_Stub
{
    public $posts = 'wp_posts';
    public $postmeta = 'wp_postmeta';

    public function prepare($query, ...$args)
    {
        $replacements = func_get_args();
        array_shift($replacements);

        if (empty($replacements)) {
            return $query;
        }

        $safe_query = preg_replace('/%[df]/', '%s', $query);
        return vsprintf($safe_query, $replacements);
    }

    public function get_results($query)
    {
        return [];
    }

    public function esc_like($text)
    {
        return (string) $text;
    }

    public function get_var($query)
    {
        return null;
    }
}

$GLOBALS['wpdb'] = new RBF_WPDB_Stub();

require_once RBF_PLUGIN_DIR . 'includes/admin.php';

$_GET = [
    'page' => 'rbf_reports',
    'start_date' => '2024-01-01',
    'end_date' => '2024-01-31',
    'source_filter' => [
        'primary' => [
            ' google Ads ',
            ['facebook_ads', ['backend\\manual']],
        ],
        'malicious' => [
            new stdClass(),
            ['payload' => ['"/><script>alert(1)</script>']],
        ],
        'numeric' => 123,
        'blank' => '',
    ],
];

ob_start();
rbf_reports_page_html();
$output = ob_get_clean();

if ($output === '') {
    throw new RuntimeException('Expected the reports page to produce HTML output.');
}

if (strpos($output, 'Report & Analytics') === false) {
    throw new RuntimeException('The reports page heading was not rendered.');
}

if (strpos($output, 'value="gads"') === false) {
    throw new RuntimeException('Expected the Google Ads channel checkbox to be present.');
}

if (strpos($output, 'value="facebook_ads"') === false) {
    throw new RuntimeException('Expected the Facebook Ads channel checkbox to be present.');
}

if (strpos($output, 'value="backend"') === false) {
    throw new RuntimeException('Expected the Backend channel checkbox to be present.');
}

if (strpos($output, 'checked="checked"') === false) {
    throw new RuntimeException('Expected at least one checkbox to remain checked.');
}

if (strpos($output, 'alert(1)') !== false || strpos($output, '"/><script') !== false) {
    throw new RuntimeException('Potentially malicious script payload detected in the output.');
}

echo "Admin reports source filter test passed.\n";

