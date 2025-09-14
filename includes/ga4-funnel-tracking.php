<?php
/**
 * GA4 Funnel Tracking for FP Prenotazioni Ristorante
 * 
 * Implements comprehensive funnel tracking with custom GA4 events,
 * session/event ID generation for deduplication, and error tracking.
 * 
 * @package FP_Prenotazioni_Ristorante_PRO
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Generate unique session ID for funnel tracking
 * Uses browser fingerprinting and server session for consistency
 */
function rbf_generate_session_id() {
    // Check if we already have a session ID in the current session
    if (isset($_SESSION['rbf_session_id'])) {
        return $_SESSION['rbf_session_id'];
    }
    
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Generate new session ID based on various factors
    $factors = [
        session_id(),
        $_SERVER['HTTP_USER_AGENT'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? '',
        time()
    ];
    
    $session_id = 'rbf_' . substr(md5(implode('|', $factors)), 0, 16);
    $_SESSION['rbf_session_id'] = $session_id;
    
    return $session_id;
}

/**
 * Generate unique event ID for deduplication
 * Format: rbf_eventtype_sessionid_timestamp_microseconds
 */
function rbf_generate_event_id($event_type, $session_id = null) {
    if (!$session_id) {
        $session_id = rbf_generate_session_id();
    }
    
    $microtime = microtime(true);
    $timestamp = floor($microtime);
    $microseconds = str_pad(floor(($microtime - $timestamp) * 1000000), 6, '0', STR_PAD_LEFT);
    
    return sprintf('rbf_%s_%s_%d_%s', 
        sanitize_key($event_type), 
        $session_id, 
        $timestamp,
        $microseconds
    );
}

/**
 * Get GA4 configuration for the current site
 */
function rbf_get_ga4_config() {
    $options = rbf_get_settings();
    return [
        'measurement_id' => $options['ga4_id'] ?? '',
        'api_secret' => $options['ga4_api_secret'] ?? '',
        'enabled' => !empty($options['ga4_id'])
    ];
}

/**
 * Enqueue GA4 funnel tracking script to frontend
 */
add_action('wp_enqueue_scripts', 'rbf_enqueue_ga4_funnel_tracking');
function rbf_enqueue_ga4_funnel_tracking() {
    if (!rbf_is_booking_page()) {
        return;
    }
    
    $config = rbf_get_ga4_config();
    if (!$config['enabled']) {
        return;
    }
    
    // Enqueue our funnel tracking script
    wp_enqueue_script(
        'rbf-ga4-funnel',
        RBF_PLUGIN_URL . 'assets/js/ga4-funnel-tracking.js',
        ['jquery'],
        RBF_VERSION,
        true
    );
    
    // Localize script with necessary data
    wp_localize_script('rbf-ga4-funnel', 'rbfGA4Funnel', [
        'sessionId' => rbf_generate_session_id(),
        'measurementId' => $config['measurement_id'],
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('rbf_ga4_funnel_nonce'),
        'debug' => defined('WP_DEBUG') && WP_DEBUG,
        'gtmHybrid' => rbf_is_gtm_hybrid_mode()
    ]);
}

/**
 * Check if GTM hybrid mode is enabled
 */
function rbf_is_gtm_hybrid_mode() {
    $options = rbf_get_settings();
    return ($options['gtm_hybrid'] ?? '') === 'yes';
}

/**
 * Check if current page has booking form
 */
function rbf_is_booking_page() {
    // Check if we're on a page/post that contains the booking shortcode
    global $post;
    
    if (!$post) {
        return false;
    }
    
    // Check if post content contains the booking shortcode
    return has_shortcode($post->post_content, 'rbf_form') || 
           has_shortcode($post->post_content, 'restaurant_booking_form');
}

/**
 * AJAX handler for server-side GA4 event tracking
 */
add_action('wp_ajax_rbf_track_ga4_event', 'rbf_ajax_track_ga4_event');
add_action('wp_ajax_nopriv_rbf_track_ga4_event', 'rbf_ajax_track_ga4_event');
function rbf_ajax_track_ga4_event() {
    // Verify nonce
    if (!check_ajax_referer('rbf_ga4_funnel_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => 'Security check failed']);
        return;
    }
    
    // Get and validate input
    $event_name = sanitize_text_field($_POST['event_name'] ?? '');
    $event_params = $_POST['event_params'] ?? [];
    $session_id = sanitize_text_field($_POST['session_id'] ?? '');
    $event_id = sanitize_text_field($_POST['event_id'] ?? '');
    
    if (empty($event_name) || empty($session_id)) {
        wp_send_json_error(['message' => 'Missing required parameters']);
        return;
    }
    
    // Sanitize event parameters
    $sanitized_params = array_map('sanitize_text_field', $event_params);
    
    // Send to GA4 Measurement Protocol if API secret is configured
    $config = rbf_get_ga4_config();
    if (!empty($config['api_secret'])) {
        $result = rbf_send_ga4_measurement_protocol($event_name, $sanitized_params, $session_id, $event_id);
        
        if ($result['success']) {
            wp_send_json_success(['message' => 'Event tracked successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to send to GA4: ' . $result['error']]);
        }
    } else {
        // Just acknowledge the client-side tracking
        wp_send_json_success(['message' => 'Event logged for client-side tracking']);
    }
}

/**
 * Send event to GA4 via Measurement Protocol
 */
function rbf_send_ga4_measurement_protocol($event_name, $params, $session_id, $event_id) {
    $config = rbf_get_ga4_config();
    
    if (empty($config['measurement_id']) || empty($config['api_secret'])) {
        return ['success' => false, 'error' => 'GA4 not configured'];
    }
    
    $url = sprintf(
        'https://www.google-analytics.com/mp/collect?measurement_id=%s&api_secret=%s',
        $config['measurement_id'],
        $config['api_secret']
    );
    
    $payload = [
        'client_id' => $session_id,
        'events' => [
            [
                'name' => $event_name,
                'params' => array_merge($params, [
                    'session_id' => $session_id,
                    'event_id' => $event_id,
                    'engagement_time_msec' => 100
                ])
            ]
        ]
    ];
    
    $response = wp_remote_post($url, [
        'body' => wp_json_encode($payload),
        'headers' => ['Content-Type' => 'application/json'],
        'timeout' => 10
    ]);
    
    if (is_wp_error($response)) {
        rbf_handle_error(
            "GA4 Measurement Protocol Error: " . $response->get_error_message(), 
            'ga4_measurement_protocol'
        );
        return ['success' => false, 'error' => $response->get_error_message()];
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code >= 200 && $response_code < 300) {
        return ['success' => true];
    } else {
        $error = sprintf('HTTP %d: %s', $response_code, wp_remote_retrieve_body($response));
        rbf_handle_error("GA4 Measurement Protocol Error: " . $error, 'ga4_measurement_protocol');
        return ['success' => false, 'error' => $error];
    }
}

/**
 * Track booking completion event with enhanced data
 */
function rbf_track_booking_completion($booking_id, $booking_data) {
    $session_id = rbf_generate_session_id();
    $event_id = rbf_generate_event_id('booking_complete', $session_id);
    
    $event_params = [
        'booking_id' => $booking_id,
        'value' => $booking_data['value'] ?? 0,
        'currency' => $booking_data['currency'] ?? 'EUR',
        'meal_type' => $booking_data['meal'] ?? '',
        'people_count' => $booking_data['people'] ?? 1,
        'traffic_source' => $booking_data['bucket'] ?? 'organic',
        'vertical' => 'restaurant'
    ];
    
    // Send via Measurement Protocol for server-side tracking
    $config = rbf_get_ga4_config();
    if (!empty($config['api_secret'])) {
        rbf_send_ga4_measurement_protocol('booking_complete', $event_params, $session_id, $event_id);
    }
    
    // Store data for client-side tracking on success page
    set_transient('rbf_ga4_completion_' . $booking_id, [
        'event_params' => $event_params,
        'session_id' => $session_id,
        'event_id' => $event_id
    ], 300); // 5 minutes
}

/**
 * Track booking error with error type classification
 */
function rbf_track_booking_error($error_message, $error_context, $session_id = null) {
    if (!$session_id) {
        $session_id = rbf_generate_session_id();
    }
    
    $event_id = rbf_generate_event_id('booking_error', $session_id);
    
    // Classify error type
    $error_type = rbf_classify_error_type($error_context);
    
    $event_params = [
        'error_type' => $error_type,
        'error_context' => $error_context,
        'error_message' => substr($error_message, 0, 100), // Limit length
        'vertical' => 'restaurant'
    ];
    
    // Send via Measurement Protocol for server-side tracking
    $config = rbf_get_ga4_config();
    if (!empty($config['api_secret'])) {
        rbf_send_ga4_measurement_protocol('booking_error', $event_params, $session_id, $event_id);
    }
}

/**
 * Classify error type for better analytics
 */
function rbf_classify_error_type($context) {
    $error_types = [
        'validation' => 'validation_error',
        'email_validation' => 'validation_error',
        'phone_validation' => 'validation_error',
        'meal_validation' => 'validation_error',
        'date_validation' => 'validation_error',
        'time_validation' => 'validation_error',
        'people_validation' => 'validation_error',
        'capacity_validation' => 'availability_error',
        'closed_day_validation' => 'availability_error',
        'closed_date_validation' => 'availability_error',
        'buffer_validation' => 'availability_error',
        'security' => 'security_error',
        'database_error' => 'system_error',
        'meta_api' => 'integration_error',
        'brevo_api' => 'integration_error',
        'ga4_measurement_protocol' => 'analytics_error',
        'ajax_validation' => 'technical_error'
    ];
    
    return $error_types[$context] ?? 'unknown_error';
}

/**
 * AJAX handler for getting booking completion data for success page tracking
 */
add_action('wp_ajax_rbf_get_booking_completion_data', 'rbf_ajax_get_booking_completion_data');
add_action('wp_ajax_nopriv_rbf_get_booking_completion_data', 'rbf_ajax_get_booking_completion_data');
function rbf_ajax_get_booking_completion_data() {
    // Verify nonce
    if (!check_ajax_referer('rbf_ga4_funnel_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => 'Security check failed']);
        return;
    }
    
    $booking_id = intval($_POST['booking_id'] ?? 0);
    if (!$booking_id) {
        wp_send_json_error(['message' => 'Invalid booking ID']);
        return;
    }
    
    // Get completion data from transient
    $completion_data = get_transient('rbf_ga4_completion_' . $booking_id);
    
    if ($completion_data && isset($completion_data['event_params'])) {
        wp_send_json_success($completion_data['event_params']);
    } else {
        // Fallback: reconstruct from booking data
        $fallback_data = [
            'booking_id' => $booking_id,
            'value' => 0,
            'currency' => 'EUR',
            'meal_type' => '',
            'people_count' => 1,
            'traffic_source' => 'organic'
        ];
        wp_send_json_success($fallback_data);
    }
}

/**
 * Update error handler to support tracking action
 */
function rbf_handle_error_with_tracking($message, $context = 'general', $redirect_url = null) {
    // Call original error handler
    rbf_handle_error($message, $context, $redirect_url);
    
    // Fire action for tracking
    do_action('rbf_error_logged', $message, $context);
}

/**
 * Hook into existing error handler to track errors
 */
add_action('rbf_error_logged', 'rbf_track_error_event', 10, 2);
function rbf_track_error_event($message, $context) {
    // Only track booking-related errors during form interaction
    if (wp_doing_ajax() || isset($_POST['rbf_meal'])) {
        rbf_track_booking_error($message, $context);
    }
}