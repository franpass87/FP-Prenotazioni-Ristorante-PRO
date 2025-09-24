<?php
/**
 * Plugin Name: FP Prenotazioni Ristorante
 * Description: Prenotazioni con calendario Flatpickr IT/EN, gestione capienza per servizio, notifiche email (con CC), Brevo sempre e GA4/Meta (bucket standard), con supporto ai limiti temporali minimi.
 * Version:     1.5
 * Author:      Francesco Passeri
 * Text Domain: rbf
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('RBF_PLUGIN_FILE', __FILE__);
define('RBF_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RBF_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RBF_VERSION', '1.5');
define('RBF_MIN_PHP_VERSION', '7.4');
define('RBF_MIN_WP_VERSION', '6.0');

/**
 * Determine environment requirement errors.
 *
 * @return array List of human-readable error messages.
 */
function rbf_get_environment_requirement_errors() {
    $errors = [];

    if (version_compare(PHP_VERSION, RBF_MIN_PHP_VERSION, '<')) {
        $errors[] = sprintf(
            /* translators: 1: required PHP version, 2: current PHP version */
            esc_html__('Versione PHP minima richiesta: %1$s (versione corrente: %2$s).', 'rbf'),
            RBF_MIN_PHP_VERSION,
            PHP_VERSION
        );
    }

    global $wp_version;
    if (isset($wp_version) && version_compare($wp_version, RBF_MIN_WP_VERSION, '<')) {
        $errors[] = sprintf(
            /* translators: 1: required WordPress version, 2: current WordPress version */
            esc_html__('Versione minima di WordPress richiesta: %1$s (versione corrente: %2$s).', 'rbf'),
            RBF_MIN_WP_VERSION,
            $wp_version
        );
    }

    return $errors;
}

/**
 * Check if the current environment meets the plugin requirements.
 *
 * @return bool
 */
function rbf_environment_meets_requirements() {
    return count(rbf_get_environment_requirement_errors()) === 0;
}

/**
 * Deactivate the plugin when requirements are not satisfied.
 */
function rbf_deactivate_plugin_for_environment() {
    if (!function_exists('deactivate_plugins')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    deactivate_plugins(plugin_basename(RBF_PLUGIN_FILE));
}

/**
 * Render an admin notice describing missing requirements.
 */
function rbf_render_environment_requirement_notice() {
    $errors = rbf_get_environment_requirement_errors();

    if (empty($errors)) {
        return;
    }

    echo '<div class="notice notice-error"><p>';
    echo esc_html__('FP Prenotazioni Ristorante richiede un ambiente aggiornato e verrà disattivato.', 'rbf');
    echo '</p><ul style="margin-left:1.5em;">';

    foreach ($errors as $error) {
        echo '<li>' . esc_html($error) . '</li>';
    }

    echo '</ul></div>';
}

$rbf_environment_ready = rbf_environment_meets_requirements();

if (!$rbf_environment_ready) {
    if (function_exists('is_admin') && is_admin()) {
        add_action('admin_notices', 'rbf_render_environment_requirement_notice');
        add_action('network_admin_notices', 'rbf_render_environment_requirement_notice');
    }

    rbf_deactivate_plugin_for_environment();
    return;
}

// Load utilities early for logging support
require_once RBF_PLUGIN_DIR . 'includes/utils.php';

/**
 * Load plugin translations.
 *
 * Executed early on the `plugins_loaded` hook to ensure translation files are
 * available before other modules register strings.
 */
function rbf_load_textdomain() {
    if (!function_exists('load_plugin_textdomain') || !function_exists('plugin_basename')) {
        return;
    }

    load_plugin_textdomain('rbf', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}
add_action('plugins_loaded', 'rbf_load_textdomain', -10);

/**
 * Clear all transients used by the plugin.
 */
function rbf_clear_transients() {
    global $wpdb;

    if (!isset($wpdb)) {
        return;
    }

    // Clear RBF-specific transients with improved pattern matching
    $transient_patterns = [
        '_transient_rbf_',
        '_transient_timeout_rbf_'
    ];

    foreach ($transient_patterns as $pattern) {
        $pattern_like = $wpdb->esc_like($pattern) . '%';
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $pattern_like
            )
        );
        
        // Log cleanup for debugging
        if ($deleted > 0) {
            rbf_log("RBF Plugin: Cleared {$deleted} transients matching pattern: {$pattern}");
        }
    }

    // Also clear specific availability transients
    $availability_pattern = $wpdb->esc_like('_transient_rbf_avail_') . '%';

    $deleted_avail = $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $availability_pattern
        )
    );
    
    if ($deleted_avail > 0) {
        rbf_log("RBF Plugin: Cleared {$deleted_avail} availability transients");
    }

    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
}

/**
 * Clear transients when plugin version changes.
 */
function rbf_maybe_clear_transients_on_load() {
    $version = get_option('rbf_plugin_version');
    if ($version !== RBF_VERSION) {
        rbf_clear_transients();
        update_option('rbf_plugin_version', RBF_VERSION);
    }
}
add_action('plugins_loaded', 'rbf_maybe_clear_transients_on_load', -1);

/**
 * Load plugin modules
 */
function rbf_load_modules() {
    $modules = [
        'utils.php',
        'optimistic-locking.php',
        'table-management.php',
        'admin.php',
        'frontend.php',
        'booking-handler.php',
        'email-failover.php',
        'integrations.php',
        'ga4-funnel-tracking.php',
        'tracking-validation.php',
        'tracking-enhanced-integration.php',
        'ai-suggestions.php',
        'privacy.php',
        'site-health.php',
        'wp-cli.php'
    ];

    foreach ($modules as $module) {
        $file = RBF_PLUGIN_DIR . 'includes/' . $module;
        if (file_exists($file)) {
            require_once $file;
        }
    }
}

// Load modules immediately after WordPress functions are available
// Use 'plugins_loaded' hook instead of 'init' to load earlier
add_action('plugins_loaded', 'rbf_load_modules', 0);

/**
 * Perform runtime environment checks once WordPress is fully loaded.
 */
function rbf_initialize_runtime_environment() {
    if (function_exists('rbf_verify_database_schema')) {
        rbf_verify_database_schema();
    }
}
add_action('plugins_loaded', 'rbf_initialize_runtime_environment', 1);

if (!function_exists('rbf_should_load_admin_tests')) {
    /**
     * Determine whether developer test harnesses should be loaded.
     *
     * Tests are only loaded in explicitly enabled environments to avoid
     * accidental execution on production sites.
     *
     * @return bool
     */
    function rbf_should_load_admin_tests() {
        $should_load = false;

        if (defined('RBF_ENABLE_ADMIN_TESTS')) {
            $should_load = (bool) RBF_ENABLE_ADMIN_TESTS;
        } elseif (defined('WP_DEBUG') && WP_DEBUG && function_exists('wp_get_environment_type')) {
            $environment = wp_get_environment_type();
            $should_load = in_array($environment, ['local', 'development'], true);
        }

        if (function_exists('apply_filters')) {
            return (bool) apply_filters('rbf_should_load_admin_tests', $should_load);
        }

        return (bool) $should_load;
    }
}

// Load test files in admin context
if (is_admin()) {
    add_action('plugins_loaded', function() {
        if (!rbf_should_load_admin_tests()) {
            return;
        }

        $test_files = [
            'ga4-funnel-tests.php',
            'ai-suggestions-tests.php',
            'hybrid-tracking-tests.php',
            'comprehensive-tracking-verification.php'
        ];
        
        foreach ($test_files as $test_file) {
            $file_path = RBF_PLUGIN_DIR . 'tests/' . $test_file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
    });
}

/**
 * Plugin activation hook
 */
/**
 * Execute activation tasks within the current site context.
 */
function rbf_run_site_activation_tasks() {
    rbf_clear_transients();

    // Load plugin modules so that CPTs and helpers are available.
    rbf_load_modules();

    if (function_exists('rbf_register_default_capabilities')) {
        rbf_register_default_capabilities();
    }

    if (function_exists('rbf_register_post_type')) {
        rbf_register_post_type();
    }

    if (function_exists('rbf_create_table_management_tables')) {
        rbf_create_table_management_tables();
    }

    if (function_exists('rbf_create_slot_version_table')) {
        rbf_create_slot_version_table();
    }

    if (function_exists('rbf_schedule_status_updates')) {
        rbf_schedule_status_updates();
    }

    if (function_exists('rbf_schedule_email_log_cleanup')) {
        rbf_schedule_email_log_cleanup();
    }

    update_option('rbf_plugin_version', RBF_VERSION);

    flush_rewrite_rules();
}

register_activation_hook(__FILE__, 'rbf_activate_plugin');
function rbf_activate_plugin($network_wide) {
    $network_wide = (bool) $network_wide;

    if (!function_exists('is_multisite') || !is_multisite() || !$network_wide) {
        rbf_run_site_activation_tasks();
        return;
    }

    if (!function_exists('get_sites') || !function_exists('switch_to_blog') || !function_exists('restore_current_blog')) {
        rbf_run_site_activation_tasks();
        return;
    }

    $site_ids = get_sites(['fields' => 'ids']);

    foreach ($site_ids as $site_id) {
        switch_to_blog($site_id);
        rbf_run_site_activation_tasks();
        restore_current_blog();
    }
}

/**
 * Plugin deactivation hook
 */
/**
 * Execute deactivation tasks within the current site context.
 */
function rbf_run_site_deactivation_tasks() {
    if (function_exists('rbf_clear_automatic_status_events')) {
        rbf_clear_automatic_status_events();
    } elseif (function_exists('wp_clear_scheduled_hook')) {
        wp_clear_scheduled_hook('rbf_update_booking_statuses');
    }

    if (function_exists('rbf_clear_email_log_cleanup_event')) {
        rbf_clear_email_log_cleanup_event();
    }

    flush_rewrite_rules();
    rbf_clear_transients();
}

register_deactivation_hook(__FILE__, 'rbf_deactivate_plugin');
function rbf_deactivate_plugin() {
    if (function_exists('is_multisite') && is_multisite() && function_exists('get_sites') && function_exists('switch_to_blog') && function_exists('restore_current_blog')) {
        $site_ids = get_sites(['fields' => 'ids']);

        foreach ($site_ids as $site_id) {
            switch_to_blog($site_id);
            rbf_run_site_deactivation_tasks();
            restore_current_blog();
        }

        return;
    }

    rbf_run_site_deactivation_tasks();
}

register_uninstall_hook(__FILE__, 'rbf_uninstall_plugin');

function rbf_uninstall_cleanup_site() {
    if (function_exists('wp_clear_scheduled_hook')) {
        wp_clear_scheduled_hook('rbf_update_booking_statuses');
    }

    if (function_exists('rbf_clear_email_log_cleanup_event')) {
        rbf_clear_email_log_cleanup_event();
    }

    if (!function_exists('rbf_remove_default_capabilities')) {
        $admin_module = RBF_PLUGIN_DIR . 'includes/admin.php';
        if (file_exists($admin_module)) {
            require_once $admin_module;
        }
    }

    if (function_exists('rbf_remove_default_capabilities')) {
        rbf_remove_default_capabilities();
    }

    $options = ['rbf_settings', 'rbf_admin_notices', 'rbf_plugin_version', 'rbf_schema_last_verified'];
    foreach ($options as $option_name) {
        delete_option($option_name);
    }

    if (function_exists('get_posts') && function_exists('wp_delete_post')) {
        $booking_ids = get_posts([
            'post_type'              => 'rbf_booking',
            'post_status'            => 'any',
            'numberposts'            => -1,
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);

        foreach ($booking_ids as $booking_id) {
            wp_delete_post($booking_id, true);
        }
    }

    global $wpdb;
    if (!isset($wpdb)) {
        return;
    }

    $tables = [
        $wpdb->prefix . 'rbf_areas',
        $wpdb->prefix . 'rbf_tables',
        $wpdb->prefix . 'rbf_table_groups',
        $wpdb->prefix . 'rbf_table_group_members',
        $wpdb->prefix . 'rbf_table_assignments',
        $wpdb->prefix . 'rbf_slot_versions',
        $wpdb->prefix . 'rbf_email_notifications',
    ];

    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS `{$table}`");
    }

    $transient_patterns = [
        '_transient_rbf_',
        '_transient_timeout_rbf_',
    ];

    foreach ($transient_patterns as $pattern) {
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like($pattern) . '%'
            )
        );
    }
}

function rbf_uninstall_plugin() {
    if (!function_exists('current_user_can') || !current_user_can('activate_plugins')) {
        return;
    }

    $is_multisite = function_exists('is_multisite') && is_multisite();

    if ($is_multisite && function_exists('get_sites') && function_exists('switch_to_blog') && function_exists('restore_current_blog')) {
        $site_ids = get_sites(['fields' => 'ids']);
        foreach ($site_ids as $site_id) {
            switch_to_blog($site_id);
            rbf_uninstall_cleanup_site();
            restore_current_blog();
        }

        if (function_exists('delete_site_option')) {
            $network_options = ['rbf_settings', 'rbf_admin_notices', 'rbf_plugin_version', 'rbf_schema_last_verified'];
            foreach ($network_options as $option_name) {
                delete_site_option($option_name);
            }
        }
    } else {
        rbf_uninstall_cleanup_site();
    }

    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
}

