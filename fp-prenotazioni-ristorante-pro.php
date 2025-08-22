<?php
/**
 * Plugin Name: Prenotazioni Ristorante Completo (Flatpickr, lingua dinamica)
 * Description: Prenotazioni con calendario Flatpickr IT/EN, last-minute, capienza per servizio, notifiche email (con CC), Brevo sempre e GA4/Meta (bucket standard).
 * Version:     10.0.1
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
define('RBF_VERSION', '10.0.1');

/**
 * Clear all transients used by the plugin.
 */
function rbf_clear_transients() {
    global $wpdb;

    if (!isset($wpdb)) {
        return;
    }

    $prefix  = $wpdb->esc_like('_transient_rbf_') . '%';
    $timeout = $wpdb->esc_like('_transient_timeout_rbf_') . '%';

    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $prefix,
            $timeout
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

// Debug configuration (will be set during WordPress initialization)
// These constants will be defined in rbf_load_modules() to ensure WordPress functions are available

/**
 * Load plugin modules
 */
function rbf_load_modules() {
    // Define debug constants now that WordPress is initialized
    if (!defined('RBF_DEBUG')) {
        // Check database settings first, then fall back to WP_DEBUG
        $settings = get_option('rbf_settings', []);
        $debug_enabled = isset($settings['debug_enabled']) ? ($settings['debug_enabled'] === 'yes') : (defined('WP_DEBUG') ? WP_DEBUG : false);
        define('RBF_DEBUG', $debug_enabled);
    }
    if (!defined('RBF_LOG_LEVEL')) {
        // Check database settings first, then fall back to default
        $settings = get_option('rbf_settings', []);
        $log_level = isset($settings['debug_log_level']) ? $settings['debug_log_level'] : 'INFO';
        define('RBF_LOG_LEVEL', $log_level); // DEBUG, INFO, WARNING, ERROR
    }

    $modules = [
        'debug-logger.php',      // Load debug system first
        'performance-monitor.php', // Load performance monitor
        'utm-validator.php',     // Load UTM validation
        'debug-dashboard.php',   // Load debug dashboard
        'utils.php',
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
    
    // Initialize debug and performance monitoring
    if (RBF_DEBUG) {
        if (class_exists('RBF_Debug_Logger')) {
            RBF_Debug_Logger::init();
        }
        if (class_exists('RBF_Performance_Monitor')) {
            RBF_Performance_Monitor::init();
        }
        
        // Log plugin initialization
        if (class_exists('RBF_Debug_Logger')) {
            RBF_Debug_Logger::track_event('plugin_initialized', [
                'version' => RBF_VERSION,
                'debug_enabled' => true,
                'log_level' => RBF_LOG_LEVEL
            ], 'INFO');
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
    if (!function_exists('rbf_register_post_type')) {
        rbf_load_modules();
    }
    
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

