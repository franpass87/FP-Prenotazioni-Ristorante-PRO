<?php
/**
 * Main Plugin Class for Restaurant Booking Plugin
 *
 * @package RBF
 * @since 9.3.2
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main plugin class
 */
class RBF_Plugin {

    /**
     * Plugin version
     */
    const VERSION = '9.3.2';

    /**
     * Plugin instance
     * 
     * @var RBF_Plugin
     */
    private static $instance = null;

    /**
     * Plugin components
     */
    private $admin;
    private $frontend;
    private $booking;
    private $integrations;
    private $ajax;

    /**
     * Get plugin instance (Singleton pattern)
     * 
     * @return RBF_Plugin
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - private to enforce singleton
     */
    private function __construct() {
        $this->init();
    }

    /**
     * Initialize the plugin
     */
    private function init() {
        // Load dependencies
        $this->load_dependencies();

        // Initialize components
        $this->init_components();

        // Setup hooks
        $this->setup_hooks();
    }

    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        $includes_path = plugin_dir_path(__FILE__);
        
        // Load utility class first
        require_once $includes_path . 'class-rbf-utils.php';
        
        // Load other classes
        require_once $includes_path . 'class-rbf-admin.php';
        require_once $includes_path . 'class-rbf-frontend.php';
        require_once $includes_path . 'class-rbf-booking.php';
        require_once $includes_path . 'class-rbf-integrations.php';
        require_once $includes_path . 'class-rbf-ajax.php';
    }

    /**
     * Initialize plugin components
     */
    private function init_components() {
        $this->admin = new RBF_Admin();
        $this->frontend = new RBF_Frontend();
        $this->booking = new RBF_Booking();
        $this->integrations = new RBF_Integrations();
        $this->ajax = new RBF_Ajax();
    }

    /**
     * Setup WordPress hooks
     */
    private function setup_hooks() {
        // Plugin activation/deactivation
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Initialize on WordPress init
        add_action('init', array($this, 'wp_init'));
    }

    /**
     * WordPress init hook
     */
    public function wp_init() {
        // Register custom post type
        $this->register_post_type();
        
        // Load text domain
        load_plugin_textdomain('rbf', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    /**
     * Register custom post type for bookings
     */
    private function register_post_type() {
        register_post_type('rbf_booking', [
            'labels' => [
                'name' => RBF_Utils::translate_string('Prenotazioni'),
                'singular_name' => RBF_Utils::translate_string('Prenotazione'),
                'add_new' => RBF_Utils::translate_string('Aggiungi Nuova'),
                'add_new_item' => RBF_Utils::translate_string('Aggiungi Nuova Prenotazione'),
                'edit_item' => RBF_Utils::translate_string('Modifica Prenotazione'),
                'new_item' => RBF_Utils::translate_string('Nuova Prenotazione'),
                'view_item' => RBF_Utils::translate_string('Visualizza Prenotazione'),
                'search_items' => RBF_Utils::translate_string('Cerca Prenotazioni'),
                'not_found' => RBF_Utils::translate_string('Nessuna Prenotazione trovata'),
                'not_found_in_trash' => RBF_Utils::translate_string('Nessuna Prenotazione trovata nel cestino'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'rbf_bookings_menu',
            'menu_icon' => 'dashicons-calendar-alt',
            'supports' => ['title', 'custom-fields'],
            'menu_position' => 20,
        ]);
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables if needed
        $this->create_tables();
        
        // Set default options
        if (!get_option('rbf_settings')) {
            add_option('rbf_settings', RBF_Utils::get_default_settings());
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Create necessary database tables
     */
    private function create_tables() {
        // For now we use WordPress posts/postmeta
        // Future enhancement: custom tables for better performance
    }

    /**
     * Get plugin URL
     * 
     * @return string Plugin URL
     */
    public static function get_plugin_url() {
        return plugin_dir_url(dirname(__FILE__));
    }

    /**
     * Get plugin path
     * 
     * @return string Plugin path
     */
    public static function get_plugin_path() {
        return plugin_dir_path(dirname(__FILE__));
    }

    /**
     * Get component instance
     * 
     * @param string $component Component name
     * @return object|null Component instance
     */
    public function get_component($component) {
        switch ($component) {
            case 'admin':
                return $this->admin;
            case 'frontend':
                return $this->frontend;
            case 'booking':
                return $this->booking;
            case 'integrations':
                return $this->integrations;
            case 'ajax':
                return $this->ajax;
            default:
                return null;
        }
    }
}