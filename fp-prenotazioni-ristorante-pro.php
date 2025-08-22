<?php
/**
 * Plugin Name: Prenotazioni Ristorante Completo (Flatpickr, lingua dinamica)
 * Description: Prenotazioni con calendario Flatpickr IT/EN, last-minute, capienza per servizio, notifiche email (con CC), Brevo sempre e GA4/Meta (bucket standard).
 * Version:     9.3.2
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
define('RBF_VERSION', '9.3.2');

// Debug configuration (can be overridden in wp-config.php)
if (!defined('RBF_DEBUG')) {
    define('RBF_DEBUG', WP_DEBUG);
}
if (!defined('RBF_LOG_LEVEL')) {
    define('RBF_LOG_LEVEL', 'INFO'); // DEBUG, INFO, WARNING, ERROR
}

/**
 * Load plugin modules
 */
function rbf_load_modules() {
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
        RBF_Debug_Logger::init();
        RBF_Performance_Monitor::init();
        
        // Log plugin initialization
        RBF_Debug_Logger::track_event('plugin_initialized', [
            'version' => RBF_VERSION,
            'debug_enabled' => true,
            'log_level' => RBF_LOG_LEVEL
        ], 'INFO');
    }
}

// Load modules after WordPress is initialized
add_action('init', 'rbf_load_modules', 1);

/**
 * Plugin activation hook
 */
register_activation_hook(__FILE__, 'rbf_activate_plugin');
function rbf_activate_plugin() {
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