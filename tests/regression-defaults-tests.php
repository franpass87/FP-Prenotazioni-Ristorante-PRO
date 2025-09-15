<?php
/**
 * Regression tests for default booking window alignment
 *
 * Ensures that a fresh installation exposes the same
 * max advance booking window on the PHP and JS sides.
 */

// Provide minimal WordPress stubs for standalone execution
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!defined('RBF_PLUGIN_DIR')) {
    define('RBF_PLUGIN_DIR', dirname(__DIR__) . '/');
}

if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', sys_get_temp_dir());
}

if (!defined('RBF_VERSION')) {
    define('RBF_VERSION', '1.0.0-test');
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

if (!function_exists('sanitize_hex_color')) {
    function sanitize_hex_color($color) {
        if (!is_string($color)) {
            return '';
        }

        $color = trim($color);
        if (!preg_match('/^#([A-Fa-f0-9]{3}){1,2}$/', $color)) {
            return '';
        }

        return strtolower($color);
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

if (!function_exists('get_locale')) {
    function get_locale() {
        return 'it_IT';
    }
}

require_once RBF_PLUGIN_DIR . 'includes/utils.php';

/**
 * Ensure the default max advance minutes matches the JS localization fallback.
 */
function test_rbf_default_max_advance_alignment() {
    echo "Testing default max advance alignment...\n";

    $defaults = rbf_get_default_settings();
    $php_default = $defaults['max_advance_minutes'];

    $frontend_path = RBF_PLUGIN_DIR . 'includes/frontend.php';
    if (!file_exists($frontend_path)) {
        echo "❌ Frontend file not found\n";
        return false;
    }

    $frontend_content = file_get_contents($frontend_path);
    $fallback_expr = null;

    foreach (explode("\n", $frontend_content) as $line) {
        if (strpos($line, "'maxAdvanceMinutes'") !== false) {
            $parts = explode('??', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $candidate = trim($parts[1]);
            $candidate = preg_replace('/[),\s]+$/', '', $candidate);

            if ($candidate !== '') {
                $fallback_expr = $candidate;
                break;
            }
        }
    }

    if ($fallback_expr === null) {
        echo "❌ Could not locate JS localization fallback for maxAdvanceMinutes\n";
        return false;
    }

    $normalized_expr = preg_replace('/\s+/', '', $fallback_expr);

    if (!preg_match('/^[A-Za-z0-9_\[\]\'"\$()]+$/', $normalized_expr) ||
        strpos($normalized_expr, 'max_advance_minutes') === false) {
        echo "❌ Unexpected fallback expression: {$fallback_expr}\n";
        return false;
    }

    // Provide common variable aliases for evaluation
    $default_settings = $defaults;

    try {
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.eval_eval
        $evaluated = eval('return ' . $fallback_expr . ';');
    } catch (Throwable $e) {
        echo "❌ Failed to evaluate fallback expression: " . $e->getMessage() . "\n";
        return false;
    }

    if ($evaluated !== $php_default) {
        echo "❌ Mismatch detected: PHP default {$php_default} vs JS fallback {$evaluated}\n";
        return false;
    }

    echo "✅ Defaults aligned: {$php_default} minutes\n\n";
    return true;
}

/**
 * Ensure the frontend handles missing or invalid maxAdvanceMinutes values gracefully.
 */
function test_rbf_max_advance_resilience() {
    echo "Testing max advance resilience logic...\n";

    $js_file = RBF_PLUGIN_DIR . 'assets/js/frontend.js';
    if (!file_exists($js_file)) {
        echo "❌ Frontend JS file not found\n";
        return false;
    }

    $js_content = file_get_contents($js_file);

    $checks = [
        "const DEFAULT_MAX_ADVANCE_MINUTES" => 'Default fallback constant defined',
        "Number(rbfData.maxAdvanceMinutes)" => 'Parsing maxAdvanceMinutes via Number()',
        "Number.isFinite(parsedMaxAdvanceMinutes)" => 'Finite validation check present',
        "const maxAdvanceMinutes =" => 'Sanitized maxAdvanceMinutes constant defined',
        "rbfLog.warn('maxAdvanceMinutes not provided" => 'Warning logged when maxAdvanceMinutes missing',
        "rbfLog.warn('Invalid maxAdvanceMinutes value" => 'Warning logged when maxAdvanceMinutes invalid',
        "new Date(today.getTime() + maxAdvanceMinutes * 60 * 1000)" => 'Fallback applied to HTML date input',
        "flatpickrConfig.maxDate = new Date(new Date().getTime() + maxAdvanceMinutes * 60 * 1000)" => 'Fallback applied to Flatpickr config'
    ];

    foreach ($checks as $needle => $description) {
        if (strpos($js_content, $needle) === false) {
            echo "❌ Missing resilience check: {$description}\n";
            return false;
        }
        echo "✓ {$description}\n";
    }

    echo "✅ Frontend handles missing/malformed maxAdvanceMinutes!\n\n";
    return true;
}

if (defined('RBF_PLUGIN_DIR')) {
    echo "Running Default Settings Regression Tests...\n";
    echo "==========================================\n\n";

    $alignment = test_rbf_default_max_advance_alignment();
    $resilience = test_rbf_max_advance_resilience();

    if ($alignment && $resilience) {
        echo "🎉 All regression checks passed!\n";
    } else {
        echo "❌ Regression detected in default settings alignment or resilience handling.\n";
    }
}

