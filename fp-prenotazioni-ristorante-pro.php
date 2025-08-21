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

if (!defined('ABSPATH')) {
    exit;
}

// Load the main plugin class
require_once plugin_dir_path(__FILE__) . 'includes/class-rbf-plugin.php';

// Initialize the plugin
function rbf_init_plugin() {
    RBF_Plugin::get_instance();
}
add_action('plugins_loaded', 'rbf_init_plugin');

// Legacy function wrappers for backward compatibility
// These ensure that if any external code is calling the old functions, they still work

if (!function_exists('rbf_wp_timezone')) {
    function rbf_wp_timezone() {
        return RBF_Utils::wp_timezone();
    }
}

if (!function_exists('rbf_current_lang')) {
    function rbf_current_lang() {
        return RBF_Utils::current_lang();
    }
}

if (!function_exists('rbf_translate_string')) {
    function rbf_translate_string($text) {
        return RBF_Utils::translate_string($text);
    }
}

if (!function_exists('rbf_get_default_settings')) {
    function rbf_get_default_settings() {
        return RBF_Utils::get_default_settings();
    }
}

// Activation and deactivation hooks
register_activation_hook(__FILE__, function() {
    $plugin = RBF_Plugin::get_instance();
    $plugin->activate();
});

register_deactivation_hook(__FILE__, function() {
    $plugin = RBF_Plugin::get_instance();
    $plugin->deactivate();
});