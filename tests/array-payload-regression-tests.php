<?php
/**
 * Regression coverage for array payload handling across sanitization entry points.
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($value) {
        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                $value = (string) $value;
            } else {
                return '';
            }
        } elseif (!is_scalar($value)) {
            return '';
        }

        $value = (string) $value;
        $value = preg_replace('/[\r\n\t\0\x0B]/', '', $value);

        return trim($value);
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($email) {
        if (!is_scalar($email)) {
            return '';
        }

        return filter_var((string) $email, FILTER_SANITIZE_EMAIL) ?: '';
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($value) {
        if (!is_scalar($value) && !(is_object($value) && method_exists($value, '__toString'))) {
            return '';
        }

        $value = (string) $value;
        return trim(preg_replace('/[\0\x0B]/', '', $value));
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url) {
        if (!is_scalar($url)) {
            return '';
        }

        return filter_var((string) $url, FILTER_SANITIZE_URL) ?: '';
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    function esc_url($url) {
        return esc_url_raw($url);
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value) {
        if (is_array($value)) {
            return array_map('wp_unslash', $value);
        }

        if (is_string($value)) {
            return stripslashes($value);
        }

        return $value;
    }
}

if (!function_exists('sanitize_title')) {
    function sanitize_title($title) {
        if (!is_scalar($title)) {
            return '';
        }

        $title = strtolower((string) $title);
        $title = preg_replace('/[^a-z0-9\-]+/', '-', $title);
        return trim($title, '-');
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value) {
        return $value;
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        return true;
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
        return true;
    }
}

if (!function_exists('home_url')) {
    function home_url($path = '') {
        return 'https://example.test' . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('wp_parse_url')) {
    function wp_parse_url($url, $component = -1) {
        return parse_url($url, $component);
    }
}

if (!function_exists('get_bloginfo')) {
    function get_bloginfo($show = '') {
        if ($show === 'name') {
            return 'Test Site';
        }

        return '';
    }
}

require_once dirname(__DIR__) . '/includes/utils.php';

class RBF_Array_Payload_Regression_Tests {
    private $results = [];

    public function run() {
        echo "🔁 Running Array Payload Regression Tests...\n";
        echo str_repeat('=', 60) . "\n\n";

        $this->test_booking_submission_arrays();
        $this->test_admin_booking_edit_arrays();
        $this->test_ajax_array_payloads();
        $this->test_helper_short_circuits();

        $this->print_summary();
    }

    private function assert_result($label, $condition, $details = '') {
        $this->results[] = [$label, $condition, $details];
        if ($condition) {
            echo "✅ {$label}\n";
        } else {
            echo "❌ {$label}\n";
            if ($details !== '') {
                echo "   Details: {$details}\n";
            }
        }
    }

    private function values_are_scalar(array $data) {
        foreach ($data as $value) {
            if (!is_scalar($value) && $value !== null) {
                return false;
            }
        }

        return true;
    }

    private function test_booking_submission_arrays() {
        $field_map = [
            'rbf_meal'           => 'text',
            'rbf_data'           => 'text',
            'rbf_orario'         => 'text',
            'rbf_persone'        => 'int',
            'rbf_nome'           => 'name',
            'rbf_cognome'        => 'name',
            'rbf_allergie'       => 'textarea',
            'rbf_lang'           => 'text',
            'rbf_phone_prefix'   => 'text',
            'rbf_tel_number'     => 'phone',
            'rbf_utm_source'     => 'text',
            'rbf_utm_medium'     => 'text',
            'rbf_utm_campaign'   => 'text',
            'rbf_gclid'          => 'text',
            'rbf_fbclid'         => 'text',
            'rbf_referrer'       => 'text',
            'rbf_special_type'   => 'text',
            'rbf_special_label'  => 'text',
            'rbf_form_timestamp' => 'int',
            'rbf_website'        => 'text',
            'rbf_privacy'        => 'text',
            'rbf_marketing'      => 'text',
        ];

        $payload = [];
        foreach ($field_map as $key => $type) {
            $payload[$key] = ['malicious'];
        }

        $sanitized = rbf_sanitize_input_fields($payload, $field_map);

        $condition = $this->values_are_scalar($sanitized)
            && isset($sanitized['rbf_nome'], $sanitized['rbf_persone'], $sanitized['rbf_privacy'])
            && $sanitized['rbf_nome'] === ''
            && $sanitized['rbf_persone'] === 0
            && $sanitized['rbf_privacy'] === '';

        $this->assert_result(
            'Booking submission rejects array payloads gracefully',
            $condition,
            json_encode($sanitized)
        );
    }

    private function test_admin_booking_edit_arrays() {
        $field_map = [
            'customer_name'  => 'text',
            'customer_email' => 'email',
            'customer_phone' => 'text',
            'people'         => 'int',
            'notes'          => 'textarea',
            'status'         => 'text',
        ];

        $payload = [];
        foreach ($field_map as $key => $type) {
            $payload[$key] = ['nested'];
        }

        $sanitized = rbf_sanitize_input_fields($payload, $field_map);

        $condition = $this->values_are_scalar($sanitized)
            && $sanitized['customer_name'] === ''
            && $sanitized['customer_email'] === ''
            && $sanitized['people'] === 0;

        $this->assert_result(
            'Admin booking edits ignore array payloads',
            $condition,
            json_encode($sanitized)
        );
    }

    private function test_ajax_array_payloads() {
        $status_payload = [
            'booking_id' => ['123'],
            'status'     => ['confirmed'],
        ];

        $status_sanitized = rbf_sanitize_input_fields($status_payload, [
            'booking_id' => 'int',
            'status'     => 'text',
        ]);

        $calendar_payload = [
            'start' => [['2024-01-01']],
            'end'   => [['2024-01-31']],
        ];

        $calendar_sanitized = rbf_sanitize_input_fields($calendar_payload, [
            'start' => 'text',
            'end'   => 'text',
        ]);

        $condition = $this->values_are_scalar($status_sanitized)
            && $this->values_are_scalar($calendar_sanitized)
            && $status_sanitized['booking_id'] === 0
            && $status_sanitized['status'] === ''
            && $calendar_sanitized['start'] === ''
            && $calendar_sanitized['end'] === '';

        $details = json_encode([
            'status'   => $status_sanitized,
            'calendar' => $calendar_sanitized,
        ]);

        $this->assert_result('AJAX endpoints sanitize array payloads safely', $condition, $details);
    }

    private function test_helper_short_circuits() {
        $text = rbf_sanitize_text_strict(['bad']);
        $textarea = rbf_sanitize_textarea_strict(['bad']);
        $name = rbf_sanitize_name_field(['bad']);
        $phone = rbf_sanitize_phone_field(['bad']);
        $email_context = rbf_escape_for_email(['bad']);
        $ics = rbf_escape_for_ics(['bad']);

        $object_without_string = new stdClass();
        $ics_object = rbf_escape_for_ics($object_without_string);

        $condition = ($text === '')
            && ($textarea === '')
            && ($name === '')
            && ($phone === '')
            && ($email_context === '')
            && ($ics === '')
            && ($ics_object === '');

        $details = json_encode([
            'text'    => $text,
            'textarea'=> $textarea,
            'name'    => $name,
            'phone'   => $phone,
            'email'   => $email_context,
            'ics'     => $ics,
            'ics_obj' => $ics_object,
        ]);

        $this->assert_result('Helper sanitizers short-circuit non-string data', $condition, $details);
    }

    private function print_summary() {
        $passed = array_filter($this->results, static function ($result) {
            return $result[1];
        });

        $failed = count($this->results) - count($passed);

        echo "\nSummary: " . count($passed) . ' passed, ' . $failed . " failed." . "\n";
    }
}

$tests = new RBF_Array_Payload_Regression_Tests();
$tests->run();
