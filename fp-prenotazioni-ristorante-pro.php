<?php
/**
 * Plugin Name: Prenotazioni Ristorante Completo (Flatpickr, lingua dinamica)
 * Description: Prenotazioni con calendario Flatpickr IT/EN, gestione capienza per servizio, notifiche email (con CC), Brevo sempre e GA4/Meta (bucket standard), con supporto ai limiti temporali minimi.
 * Version:     11.0.0
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
define('RBF_VERSION', '11.0.0');

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
        '_transient_rbf_%',
        '_transient_timeout_rbf_%'
    ];
    
    foreach ($transient_patterns as $pattern) {
        $escaped_pattern = $wpdb->esc_like($pattern);
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $escaped_pattern
            )
        );
    }

    // Also clear specific availability transients
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_rbf_avail_%'
        )
    );

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
        'database.php',
        'utils.php',
        'notifications.php',
        'admin.php',
        'frontend.php',
        'booking-handler.php',
        'integrations.php'
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
 * Plugin activation hook
 */
register_activation_hook(__FILE__, 'rbf_activate_plugin');
function rbf_activate_plugin() {
    rbf_clear_transients();

    // Load modules to ensure custom post types are registered before flushing rules
    rbf_load_modules();
    
    // Register custom post type before flushing rewrite rules
    if (function_exists('rbf_register_post_type')) {
        rbf_register_post_type();
    }
    
    // Flush rewrite rules to ensure custom post types work
    flush_rewrite_rules();
}

/**
 * Plugin deactivation hook
 */
register_deactivation_hook(__FILE__, 'rbf_deactivate_plugin');
function rbf_deactivate_plugin() {
    // Clean up rewrite rules
    flush_rewrite_rules();
}

