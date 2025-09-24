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
 * Stores the ID in a cookie for consistency across requests
 */
function rbf_generate_session_id() {
    // Return existing cookie value if available
    if (!empty($_COOKIE['rbf_session_id'])) {
        return sanitize_key($_COOKIE['rbf_session_id']);
    }

    // Generate new session ID using random bytes
    try {
        $session_id = 'rbf_' . bin2hex(random_bytes(8));
    } catch (Exception $e) {
        // Fallback in case random_bytes isn't available
        $session_id = 'rbf_' . substr(md5(uniqid('', true)), 0, 16);
    }

    // Store in cookie for 30 minutes
    if (!headers_sent()) {
        setcookie(
            'rbf_session_id',
            $session_id,
            time() + 1800,
            COOKIEPATH,
            COOKIE_DOMAIN,
            is_ssl(),
            true
        );
    }

    // Make the cookie available immediately in this request
    $_COOKIE['rbf_session_id'] = $session_id;

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
 * Sanitize GA4 client ID value
 */
function rbf_clean_ga_client_id($client_id) {
    $client_id = preg_replace('/[^A-Za-z0-9\.\-_]/', '', (string) $client_id);
    return $client_id ?: '';
}

/**
 * Sanitize GA4 session ID value
 */
function rbf_clean_ga_session_id($session_id) {
    $session_id = preg_replace('/[^0-9]/', '', (string) $session_id);
    return $session_id ?: '';
}

/**
 * Extract GA4 client ID from GA cookies
 */
function rbf_extract_ga_client_id_from_cookie_value($value) {
    $parts = array_values(array_filter(explode('.', (string) $value), 'strlen'));
    $count = count($parts);

    if ($count >= 2) {
        $client_id = $parts[$count - 2] . '.' . $parts[$count - 1];
        return rbf_clean_ga_client_id($client_id);
    }

    return '';
}

/**
 * Extract GA4 session ID from GA measurement cookie values
 */
function rbf_extract_ga_session_id_from_cookie_value($value) {
    $parts = array_values(array_filter(explode('.', (string) $value), 'strlen'));

    if (count($parts) >= 3 && stripos($parts[0], 'GS') === 0) {
        $session_id = rbf_clean_ga_session_id($parts[2]);
        if ($session_id) {
            return $session_id;
        }
    }

    return '';
}

/**
 * Attempt to determine GA4 client ID from available cookies
 */
function rbf_get_ga_client_id_from_cookies() {
    if (empty($_COOKIE) || !is_array($_COOKIE)) {
        return '';
    }

    $preferred_names = [];
    $config = rbf_get_ga4_config();

    if (!empty($config['measurement_id'])) {
        $suffix = preg_replace('/[^A-Z0-9_]/i', '', str_replace('G-', '', $config['measurement_id']));
        if (!empty($suffix)) {
            $preferred_names[] = '_ga_' . $suffix;
        }
    }

    foreach ($preferred_names as $cookie_name) {
        if (!empty($_COOKIE[$cookie_name])) {
            $client_id = rbf_extract_ga_client_id_from_cookie_value($_COOKIE[$cookie_name]);
            if ($client_id) {
                return $client_id;
            }
        }
    }

    foreach ($_COOKIE as $name => $value) {
        if (strpos($name, '_ga') !== 0) {
            continue;
        }

        $client_id = rbf_extract_ga_client_id_from_cookie_value($value);
        if ($client_id) {
            return $client_id;
        }
    }

    if (!empty($_COOKIE['rbf_ga_client_id'])) {
        $client_id = rbf_clean_ga_client_id($_COOKIE['rbf_ga_client_id']);
        if ($client_id) {
            return $client_id;
        }
    }

    return '';
}

/**
 * Attempt to determine GA4 session ID from available cookies
 */
function rbf_get_ga_session_id_from_cookies() {
    if (empty($_COOKIE) || !is_array($_COOKIE)) {
        return '';
    }

    $config = rbf_get_ga4_config();
    $preferred_names = [];

    if (!empty($config['measurement_id'])) {
        $suffix = preg_replace('/[^A-Z0-9_]/i', '', str_replace('G-', '', $config['measurement_id']));
        if (!empty($suffix)) {
            $preferred_names[] = '_ga_' . $suffix;
        }
    }

    foreach ($preferred_names as $cookie_name) {
        if (!empty($_COOKIE[$cookie_name])) {
            $session_id = rbf_extract_ga_session_id_from_cookie_value($_COOKIE[$cookie_name]);
            if ($session_id) {
                return $session_id;
            }
        }
    }

    foreach ($_COOKIE as $name => $value) {
        if (strpos($name, '_ga_') !== 0) {
            continue;
        }

        $session_id = rbf_extract_ga_session_id_from_cookie_value($value);
        if ($session_id) {
            return $session_id;
        }
    }

    if (!empty($_COOKIE['rbf_ga_session_id'])) {
        $session_id = rbf_clean_ga_session_id($_COOKIE['rbf_ga_session_id']);
        if ($session_id) {
            return $session_id;
        }
    }

    return '';
}

/**
 * Persist GA4 identifiers for a session
 */
function rbf_store_ga_client_id_for_session($session_id, $client_id, $ga_session_id = '') {
    $session_id = sanitize_key($session_id);
    $client_id = rbf_clean_ga_client_id($client_id);
    $ga_session_id = rbf_clean_ga_session_id($ga_session_id);

    if (empty($session_id)) {
        return;
    }

    $existing = get_transient('rbf_ga4_client_' . $session_id);
    $identifiers = [];

    if (is_array($existing)) {
        $identifiers['client_id'] = rbf_clean_ga_client_id($existing['client_id'] ?? '');
        $identifiers['ga_session_id'] = rbf_clean_ga_session_id($existing['ga_session_id'] ?? '');
    } elseif (!empty($existing)) {
        $identifiers['client_id'] = rbf_clean_ga_client_id($existing);
    }

    if ($client_id) {
        $identifiers['client_id'] = $client_id;
    }

    if ($ga_session_id) {
        $identifiers['ga_session_id'] = $ga_session_id;
    }

    if (empty($identifiers['client_id']) && empty($identifiers['ga_session_id'])) {
        return;
    }

    $expiration = defined('MINUTE_IN_SECONDS') ? 30 * MINUTE_IN_SECONDS : 1800;
    set_transient('rbf_ga4_client_' . $session_id, $identifiers, $expiration);
}

/**
 * Resolve GA4 client ID using stored values or fallbacks
 */
function rbf_resolve_ga_client_id($session_id, $provided_client_id = '') {
    $session_id = sanitize_key($session_id);
    $candidate = rbf_clean_ga_client_id($provided_client_id);

    if ($candidate) {
        if ($session_id) {
            rbf_store_ga_client_id_for_session($session_id, $candidate);
        }
        return $candidate;
    }

    if ($session_id) {
        $stored = get_transient('rbf_ga4_client_' . $session_id);
        if (is_array($stored)) {
            $stored_client = rbf_clean_ga_client_id($stored['client_id'] ?? '');
        } else {
            $stored_client = rbf_clean_ga_client_id($stored);
        }

        if (!empty($stored_client)) {
            return $stored_client;
        }
    }

    $cookie_client_id = rbf_get_ga_client_id_from_cookies();
    if ($cookie_client_id) {
        if ($session_id) {
            rbf_store_ga_client_id_for_session($session_id, $cookie_client_id);
        }
        return $cookie_client_id;
    }

    return $session_id ? rbf_clean_ga_client_id($session_id) : '';
}

/**
 * Resolve GA4 session ID using stored values or fallbacks
 */
function rbf_resolve_ga_session_id($session_id, $provided_session_id = '') {
    $session_id = sanitize_key($session_id);
    $candidate = rbf_clean_ga_session_id($provided_session_id);

    if ($candidate) {
        if ($session_id) {
            rbf_store_ga_client_id_for_session($session_id, '', $candidate);
        }
        return $candidate;
    }

    if ($session_id) {
        $stored = get_transient('rbf_ga4_client_' . $session_id);
        if (is_array($stored)) {
            $stored_session = rbf_clean_ga_session_id($stored['ga_session_id'] ?? '');
            if ($stored_session) {
                return $stored_session;
            }
        }
    }

    $cookie_session_id = rbf_get_ga_session_id_from_cookies();
    if ($cookie_session_id) {
        if ($session_id) {
            rbf_store_ga_client_id_for_session($session_id, '', $cookie_session_id);
        }
        return $cookie_session_id;
    }

    return '';
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
    if (!function_exists('is_singular') || !is_singular()) {
        return false;
    }

    global $post;

    if (!$post) {
        return false;
    }

    return rbf_post_has_booking_form($post);
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
    $event_name = sanitize_text_field(wp_unslash($_POST['event_name'] ?? ''));

    $event_params_raw = $_POST['event_params'] ?? [];
    if (is_string($event_params_raw)) {
        $decoded_params = json_decode(wp_unslash($event_params_raw), true);
        $event_params = is_array($decoded_params) ? $decoded_params : [];
    } else {
        $event_params = wp_unslash($event_params_raw);
    }

    if (!is_array($event_params)) {
        $event_params = [];
    }

    $session_id = sanitize_text_field(wp_unslash($_POST['session_id'] ?? ''));
    $event_id = sanitize_text_field(wp_unslash($_POST['event_id'] ?? ''));
    $client_id_input = isset($_POST['client_id']) ? wp_unslash($_POST['client_id']) : '';
    $client_id = rbf_clean_ga_client_id($client_id_input);
    $ga_session_input = isset($_POST['ga_session_id']) ? wp_unslash($_POST['ga_session_id']) : '';
    $ga_session_id = rbf_clean_ga_session_id($ga_session_input);

    if (!$client_id) {
        $client_id = rbf_get_ga_client_id_from_cookies();
    }

    if (!$ga_session_id) {
        $ga_session_id = rbf_get_ga_session_id_from_cookies();
    }

    if (empty($event_name) || empty($session_id)) {
        wp_send_json_error(['message' => 'Missing required parameters']);
        return;
    }

    // Sanitize event parameters recursively preserving numeric types
    $sanitized_params = rbf_recursive_sanitize($event_params);

    // Derive GA session ID from event parameters when not explicitly provided
    if (!$ga_session_id) {
        if (isset($sanitized_params['ga_session_id'])) {
            $candidate = $sanitized_params['ga_session_id'];
        } elseif (isset($sanitized_params['session_id'])) {
            $candidate = $sanitized_params['session_id'];
        } else {
            $candidate = '';
        }

        if (!is_array($candidate)) {
            $ga_session_id = rbf_clean_ga_session_id($candidate);
        }
    }

    // Ensure outgoing parameters never contain invalid GA session identifiers
    if (isset($sanitized_params['ga_session_id'])) {
        $clean_param_session = rbf_clean_ga_session_id($sanitized_params['ga_session_id']);
        if ($clean_param_session) {
            $sanitized_params['ga_session_id'] = $clean_param_session;
        } else {
            unset($sanitized_params['ga_session_id']);
        }
    }

    if (isset($sanitized_params['session_id'])) {
        $clean_param_session = rbf_clean_ga_session_id($sanitized_params['session_id']);
        if ($clean_param_session) {
            $sanitized_params['session_id'] = $clean_param_session;
        } else {
            unset($sanitized_params['session_id']);
        }
    }

    if (!empty($session_id) && (!empty($client_id) || !empty($ga_session_id))) {
        rbf_store_ga_client_id_for_session($session_id, $client_id, $ga_session_id);
    }

    // Send to GA4 Measurement Protocol if API secret is configured
    $config = rbf_get_ga4_config();
    if (!empty($config['api_secret'])) {
        $result = rbf_send_ga4_measurement_protocol($event_name, $sanitized_params, $session_id, $event_id, $client_id, $ga_session_id);

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
function rbf_send_ga4_measurement_protocol($event_name, $params, $session_id, $event_id, $client_id = '', $ga_session_id = '') {
    $config = rbf_get_ga4_config();

    if (empty($config['measurement_id']) || empty($config['api_secret'])) {
        return ['success' => false, 'error' => 'GA4 not configured'];
    }

    $resolved_client_id = rbf_resolve_ga_client_id($session_id, $client_id);
    $resolved_ga_session_id = rbf_resolve_ga_session_id($session_id, $ga_session_id);

    $event_params = is_array($params) ? $params : [];

    if ($resolved_ga_session_id) {
        $event_params['session_id'] = $resolved_ga_session_id;
        $event_params['ga_session_id'] = $resolved_ga_session_id;
    } else {
        if (isset($event_params['session_id']) && !preg_match('/^\d+$/', (string) $event_params['session_id'])) {
            unset($event_params['session_id']);
        }
        if (isset($event_params['ga_session_id']) && !preg_match('/^\d+$/', (string) $event_params['ga_session_id'])) {
            unset($event_params['ga_session_id']);
        }
    }

    if (!isset($event_params['rbf_session_id']) && $session_id) {
        $event_params['rbf_session_id'] = $session_id;
    }

    $event_params['event_id'] = $event_id;
    if (!isset($event_params['engagement_time_msec'])) {
        $event_params['engagement_time_msec'] = 100;
    }

    $url = sprintf(
        'https://www.google-analytics.com/mp/collect?measurement_id=%s&api_secret=%s',
        $config['measurement_id'],
        $config['api_secret']
    );

    $payload_client_id = $resolved_client_id ?: ($session_id ? rbf_clean_ga_client_id($session_id) : '');

    $payload = [
        'client_id' => $payload_client_id,
        'events' => [
            [
                'name' => $event_name,
                'params' => $event_params
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
    $event_id = rbf_generate_event_id('booking_confirmed', $session_id);
    $client_id = rbf_resolve_ga_client_id($session_id);
    $ga_session_id = rbf_resolve_ga_session_id($session_id);

    $tracking_token = '';
    if (!empty($booking_data['tracking_token']) && is_string($booking_data['tracking_token'])) {
        $tracking_token = sanitize_text_field($booking_data['tracking_token']);
    } else {
        $booking_transient = get_transient('rbf_booking_data_' . $booking_id);
        if (is_array($booking_transient) && !empty($booking_transient['tracking_token'])) {
            $tracking_token = sanitize_text_field((string) $booking_transient['tracking_token']);
        }
    }

    $event_params = [
        'booking_id' => $booking_id,
        'value' => $booking_data['value'] ?? 0,
        'currency' => $booking_data['currency'] ?? 'EUR',
        'meal_type' => $booking_data['meal'] ?? '',
        'people_count' => $booking_data['people'] ?? 1,
        'traffic_source' => $booking_data['bucket'] ?? 'organic',
        'vertical' => 'restaurant'
    ];

    if ($ga_session_id) {
        $event_params['session_id'] = $ga_session_id;
        $event_params['ga_session_id'] = $ga_session_id;
    }

    $event_params['rbf_session_id'] = $session_id;

    if (!empty($session_id) && ($client_id || $ga_session_id)) {
        rbf_store_ga_client_id_for_session($session_id, $client_id, $ga_session_id);
    }

    // Send via Measurement Protocol for server-side tracking
    $config = rbf_get_ga4_config();
    if (!empty($config['api_secret'])) {
        rbf_send_ga4_measurement_protocol('booking_confirmed', $event_params, $session_id, $event_id, $client_id, $ga_session_id);
    }

    // Store data for client-side tracking on success page
    $completion_payload = [
        'event_params' => $event_params,
        'session_id' => $session_id,
        'event_id' => $event_id,
        'client_id' => $client_id,
        'ga_session_id' => $ga_session_id,
        'event_name' => 'booking_confirmed'
    ];

    if ($tracking_token !== '') {
        $completion_payload['tracking_token'] = $tracking_token;
    }

    set_transient('rbf_ga4_completion_' . $booking_id, $completion_payload, 300); // 5 minutes
}

/**
 * Track booking error with error type classification
 */
function rbf_track_booking_error($error_message, $error_context, $session_id = null) {
    if (!$session_id) {
        $session_id = rbf_generate_session_id();
    }

    $event_id = rbf_generate_event_id('booking_error', $session_id);
    $ga_session_id = rbf_resolve_ga_session_id($session_id);

    // Classify error type
    $error_type = rbf_classify_error_type($error_context);

    $event_params = [
        'error_type' => $error_type,
        'error_context' => $error_context,
        'error_message' => substr($error_message, 0, 100), // Limit length
        'vertical' => 'restaurant'
    ];

    if ($ga_session_id) {
        $event_params['session_id'] = $ga_session_id;
        $event_params['ga_session_id'] = $ga_session_id;
    }

    $event_params['rbf_session_id'] = $session_id;

    // Send via Measurement Protocol for server-side tracking
    $config = rbf_get_ga4_config();
    if (!empty($config['api_secret'])) {
        rbf_send_ga4_measurement_protocol('booking_error', $event_params, $session_id, $event_id, '', $ga_session_id);
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
 * Build the GA4 completion payload for a booking confirmation request.
 *
 * @param int    $booking_id    Booking post ID.
 * @param string $booking_token Raw booking token supplied by the client.
 * @return array|WP_Error Response payload or WP_Error on validation failure.
 */
function rbf_prepare_booking_completion_response($booking_id, $booking_token) {
    $booking_id = intval($booking_id);
    if (!$booking_id) {
        return new WP_Error('invalid_booking', 'Invalid booking ID');
    }

    $booking_token = is_string($booking_token) ? trim($booking_token) : '';
    if ($booking_token === '') {
        return new WP_Error('missing_token', 'Missing booking token');
    }

    $completion_key = 'rbf_ga4_completion_' . $booking_id;
    $completion_data = get_transient($completion_key);

    if (is_array($completion_data) && !empty($completion_data['event_params'])) {
        $original_completion = $completion_data;
        $expected_token = isset($original_completion['tracking_token']) ? (string) $original_completion['tracking_token'] : '';

        unset($completion_data['tracking_token']);
        if (!empty($completion_data)) {
            set_transient($completion_key, $completion_data, 300);
        } else {
            delete_transient($completion_key);
        }

        $event_params = is_array($original_completion['event_params']) ? $original_completion['event_params'] : [];
        $event_name = sanitize_text_field($original_completion['event_name'] ?? 'booking_confirmed');

        $session_id = '';
        if (!empty($original_completion['session_id'])) {
            $session_id = sanitize_key($original_completion['session_id']);
        }
        if (!$session_id) {
            $session_id = rbf_generate_session_id();
        }

        $event_id = '';
        if (!empty($original_completion['event_id'])) {
            $event_id = sanitize_text_field($original_completion['event_id']);
        }
        if (!$event_id) {
            $event_id = rbf_generate_event_id($event_name, $session_id);
        }

        $client_id = '';
        if (!empty($original_completion['client_id'])) {
            $client_id = rbf_clean_ga_client_id($original_completion['client_id']);
        }
        if (!$client_id) {
            $client_id = rbf_resolve_ga_client_id($session_id);
        }

        $ga_session_id = '';
        if (!empty($original_completion['ga_session_id'])) {
            $ga_session_id = rbf_clean_ga_session_id($original_completion['ga_session_id']);
        }
        if (!$ga_session_id) {
            $ga_session_id = rbf_resolve_ga_session_id($session_id);
        }

        if ($ga_session_id) {
            $event_params['session_id'] = $ga_session_id;
            $event_params['ga_session_id'] = $ga_session_id;
        }

        if (!isset($event_params['rbf_session_id'])) {
            $event_params['rbf_session_id'] = $session_id;
        }

        $response_data = [
            'event_params' => $event_params,
            'session_id' => $session_id,
            'event_id' => $event_id,
            'client_id' => $client_id,
            'event_name' => $event_name,
            'ga_session_id' => $ga_session_id,
        ];

        $token_is_valid = false;
        if ($expected_token !== '') {
            $token_is_valid = hash_equals($expected_token, $booking_token);
        } else {
            $stored_hash = (string) get_post_meta($booking_id, 'rbf_tracking_token', true);
            $incoming_hash = rbf_hash_tracking_token($booking_token);
            if ($stored_hash !== '' && $incoming_hash !== '' && hash_equals($stored_hash, $incoming_hash)) {
                $token_is_valid = true;
            }
        }

        if (!$token_is_valid) {
            return new WP_Error('invalid_token', 'Invalid or expired booking token');
        }

        return $response_data;
    }

    // Fallback path: rebuild payload when transient data is missing or expired.
    $session_id = rbf_generate_session_id();
    $client_id = rbf_resolve_ga_client_id($session_id);
    $ga_session_id = rbf_resolve_ga_session_id($session_id);

    $tracking_data = rbf_build_booking_tracking_data($booking_id, []);
    if (!is_array($tracking_data)) {
        $tracking_data = [];
    }

    $fallback_value = isset($tracking_data['value']) ? (float) $tracking_data['value'] : 0.0;
    $fallback_currency = !empty($tracking_data['currency']) ? sanitize_text_field($tracking_data['currency']) : 'EUR';
    $fallback_meal = !empty($tracking_data['meal']) ? sanitize_text_field($tracking_data['meal']) : '';
    $fallback_people = isset($tracking_data['people']) ? (int) $tracking_data['people'] : 0;
    if ($fallback_people <= 0) {
        $fallback_people = 1;
    }

    $fallback_bucket = !empty($tracking_data['bucket']) ? sanitize_text_field($tracking_data['bucket']) : 'organic';
    $fallback_unit_price = isset($tracking_data['unit_price']) ? (float) $tracking_data['unit_price'] : 0.0;
    $fallback_gclid = isset($tracking_data['gclid']) ? sanitize_text_field($tracking_data['gclid']) : '';
    $fallback_fbclid = isset($tracking_data['fbclid']) ? sanitize_text_field($tracking_data['fbclid']) : '';
    $fallback_event_id = !empty($tracking_data['event_id']) ? sanitize_text_field($tracking_data['event_id']) : '';
    if (!$fallback_event_id) {
        $fallback_event_id = 'rbf_' . $booking_id;
    }

    $fallback_params = [
        'booking_id' => $booking_id,
        'value' => $fallback_value,
        'currency' => $fallback_currency,
        'meal_type' => $fallback_meal,
        'meal' => $fallback_meal,
        'people_count' => $fallback_people,
        'people' => $fallback_people,
        'traffic_source' => $fallback_bucket,
        'bucket' => $fallback_bucket,
        'unit_price' => $fallback_unit_price,
        'funnel_step' => 7,
        'step_name' => 'booking_confirmation',
        'vertical' => 'restaurant',
        'event_id' => $fallback_event_id,
    ];

    if ($fallback_gclid !== '') {
        $fallback_params['gclid'] = $fallback_gclid;
    }

    if ($fallback_fbclid !== '') {
        $fallback_params['fbclid'] = $fallback_fbclid;
    }

    if ($ga_session_id) {
        $fallback_params['session_id'] = $ga_session_id;
        $fallback_params['ga_session_id'] = $ga_session_id;
    }

    $fallback_params['rbf_session_id'] = $session_id;

    $fallback_data = [
        'event_params' => $fallback_params,
        'session_id' => $session_id,
        'event_id' => $fallback_event_id,
        'client_id' => $client_id,
        'ga_session_id' => $ga_session_id,
        'event_name' => 'booking_confirmed',
    ];

    $stored_hash = (string) get_post_meta($booking_id, 'rbf_tracking_token', true);
    $incoming_hash = rbf_hash_tracking_token($booking_token);
    if ($stored_hash === '' || $incoming_hash === '' || !hash_equals($stored_hash, $incoming_hash)) {
        return new WP_Error('invalid_token', 'Invalid or expired booking token');
    }

    return $fallback_data;
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

    $booking_token_raw = $_POST['booking_token'] ?? '';
    $booking_token = '';
    if (is_string($booking_token_raw)) {
        $booking_token = sanitize_text_field(wp_unslash($booking_token_raw));
    }

    if ($booking_token === '') {
        wp_send_json_error(['message' => 'Missing booking token']);
        return;
    }

    $response_data = rbf_prepare_booking_completion_response($booking_id, $booking_token);

    if (is_wp_error($response_data)) {
        wp_send_json_error(['message' => $response_data->get_error_message()]);
        return;
    }

    wp_send_json_success($response_data);
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