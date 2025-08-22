<?php
/**
 * Booking submission handler for Restaurant Booking Plugin
 * 
 * @package FP_Prenotazioni_Ristorante_PRO
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle booking form submission
 */
add_action('admin_post_rbf_submit_booking', 'rbf_handle_booking_submission');
add_action('admin_post_nopriv_rbf_submit_booking', 'rbf_handle_booking_submission');
function rbf_handle_booking_submission() {
    // Start performance monitoring
    if (RBF_DEBUG && class_exists('RBF_Performance_Monitor')) {
        RBF_Performance_Monitor::start_timing('booking_submission');
    }
    
    $redirect_url = wp_get_referer() ? strtok(wp_get_referer(), '?') : home_url();
    $anchor = '#rbf-message-anchor';

    if (!isset($_POST['rbf_nonce']) || !wp_verify_nonce($_POST['rbf_nonce'], 'rbf_booking')) {
        // Log security error
        if (RBF_DEBUG && class_exists('RBF_Debug_Logger')) {
            RBF_Debug_Logger::track_event('booking_security_error', [
                'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'referer' => wp_get_referer() ?? 'none'
            ], 'WARNING');
        }
        wp_safe_redirect(add_query_arg('rbf_error', urlencode(rbf_translate_string('Errore di sicurezza.')), $redirect_url . $anchor)); exit;
    }

    $required = ['rbf_meal','rbf_data','rbf_orario','rbf_persone','rbf_nome','rbf_cognome','rbf_email','rbf_tel','rbf_privacy'];
    foreach ($required as $f) {
        if (empty($_POST[$f])) {
            wp_safe_redirect(add_query_arg('rbf_error', urlencode(rbf_translate_string('Tutti i campi sono obbligatori, inclusa l\'accettazione della privacy policy.')), $redirect_url . $anchor)); exit;
        }
    }

    $meal = sanitize_text_field($_POST['rbf_meal']);
    $date = sanitize_text_field($_POST['rbf_data']);
    $time_data = sanitize_text_field($_POST['rbf_orario']);
    if (strpos($time_data, '|') === false) {
        wp_safe_redirect(add_query_arg('rbf_error', urlencode(rbf_translate_string('Orario non valido.')), $redirect_url . $anchor)); exit;
    }
    list($slot, $time) = explode('|', $time_data);
    $people = intval($_POST['rbf_persone']);
    $first_name = sanitize_text_field($_POST['rbf_nome']);
    $last_name = sanitize_text_field($_POST['rbf_cognome']);
    $email = sanitize_email($_POST['rbf_email']);
    $tel = sanitize_text_field($_POST['rbf_tel']);
    $notes = sanitize_textarea_field($_POST['rbf_allergie'] ?? '');
    $lang = sanitize_text_field($_POST['rbf_lang'] ?? 'it');
    $country_code = strtolower(sanitize_text_field($_POST['rbf_country_code'] ?? ''));
    
    // Fallback: if no country code is provided, default to Italy
    if (empty($country_code)) {
        $country_code = 'it';
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('RBF Booking: No country code provided, defaulting to IT');
        }
    }
    
    // Determine Brevo language based on country selection
    // If Italy is selected, use Italian list, otherwise use English list
    $brevo_lang = ($country_code === 'it') ? 'it' : 'en';
    
    // Debug logging if enabled
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("RBF Booking: Country code={$country_code}, Brevo language={$brevo_lang}");
    }
    
    $privacy = (isset($_POST['rbf_privacy']) && $_POST['rbf_privacy']==='yes') ? 'yes' : 'no';
    $marketing = (isset($_POST['rbf_marketing']) && $_POST['rbf_marketing']==='yes') ? 'yes' : 'no';

    // Sorgente & UTM dal form
    $utm_source   = sanitize_text_field($_POST['rbf_utm_source']   ?? '');
    $utm_medium   = sanitize_text_field($_POST['rbf_utm_medium']   ?? '');
    $utm_campaign = sanitize_text_field($_POST['rbf_utm_campaign'] ?? '');
    $gclid        = sanitize_text_field($_POST['rbf_gclid']        ?? '');
    $fbclid       = sanitize_text_field($_POST['rbf_fbclid']       ?? '');
    $referrer     = sanitize_text_field($_POST['rbf_referrer']     ?? '');

    $src = rbf_detect_source([
      'utm_source' => $utm_source,
      'utm_medium' => $utm_medium,
      'utm_campaign' => $utm_campaign,
      'gclid' => $gclid,
      'fbclid' => $fbclid,
      'referrer' => $referrer
    ]);

    if (!$email) {
        wp_safe_redirect(add_query_arg('rbf_error', urlencode(rbf_translate_string('Indirizzo email non valido.')), $redirect_url . $anchor)); exit;
    }

    // Get settings for advance booking validation
    $options = get_option('rbf_settings', rbf_get_default_settings());
    
    // Check maximum advance booking time
    $max_advance_hours = absint($options['max_advance_hours'] ?? 72);
    $tz = rbf_wp_timezone();
    $now = new DateTime('now', $tz);
    $booking_datetime = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $time, $tz);
    
    if ($booking_datetime) {
        $hours_diff = ($booking_datetime->getTimestamp() - $now->getTimestamp()) / 3600;
        
        // Check if booking time is in the past (negative hours_diff)
        if ($hours_diff < 0) {
            $error_msg = rbf_translate_string('Non è possibile prenotare per orari già passati. Scegli un orario futuro.');
            wp_safe_redirect(add_query_arg('rbf_error', urlencode($error_msg), $redirect_url . $anchor)); 
            exit;
        }
        
        if ($hours_diff > $max_advance_hours) {
            $days_max = ceil($max_advance_hours / 24);
            $error_msg = sprintf(
                rbf_translate_string('Spiacenti, è possibile prenotare al massimo %d ore in anticipo (%d giorni). Scegli una data più vicina.'), 
                $max_advance_hours,
                $days_max
            );
            wp_safe_redirect(add_query_arg('rbf_error', urlencode($error_msg), $redirect_url . $anchor)); 
            exit;
        }
    }

    $remaining_capacity = rbf_get_remaining_capacity($date, $slot);
    
    // Check capacity - if not enough, show error (no waitlist)
    if ($remaining_capacity < $people) {
        $error_msg = sprintf(
            rbf_translate_string('Spiacenti, non ci sono abbastanza posti. Rimasti: %d. Scegli un altro orario.'), 
            $remaining_capacity
        );
        wp_safe_redirect(add_query_arg('rbf_error', urlencode($error_msg), $redirect_url . $anchor)); 
        exit;
    }
    
    // All bookings are automatically confirmed
    $booking_status = 'confirmed';

    $post_id = wp_insert_post([
        'post_type' => 'rbf_booking',
        'post_title' => ucfirst($meal) . " per {$first_name} {$last_name} - {$date} {$time}",
        'post_status' => 'publish',
        'meta_input' => [
            'rbf_data' => $date,
            'rbf_orario' => $slot,
            'rbf_time' => $time,
            'rbf_persone' => $people,
            'rbf_nome' => $first_name,
            'rbf_cognome' => $last_name,
            'rbf_email' => $email,
            'rbf_tel' => $tel,
            'rbf_allergie' => $notes,
            'rbf_lang' => $lang,
            'rbf_country_code' => $country_code,
            'rbf_brevo_lang' => $brevo_lang,
            'rbf_privacy' => $privacy,
            'rbf_marketing' => $marketing,
            // sorgente
            'rbf_source_bucket' => $src['bucket'],
            'rbf_source'        => $src['source'],
            'rbf_medium'        => $src['medium'],
            'rbf_campaign'      => $src['campaign'],
            'rbf_gclid'         => $gclid,
            'rbf_fbclid'        => $fbclid,
            'rbf_referrer'      => $referrer,
            // Enhanced booking status system
            'rbf_booking_status' => $booking_status,
            'rbf_booking_created' => current_time('Y-m-d H:i:s'),
            'rbf_booking_hash' => wp_generate_password(16, false, false),
        ],
    ]);

    if (is_wp_error($post_id)) {
        wp_safe_redirect(add_query_arg('rbf_error', urlencode(rbf_translate_string('Errore nel salvataggio.')), $redirect_url . $anchor)); exit;
    }

    delete_transient('rbf_avail_' . $date . '_' . $slot);
    $options = get_option('rbf_settings', rbf_get_default_settings());
    $valore_pp  = (float) ($options['valore_' . $meal] ?? 0);
    $valore_tot = $valore_pp * $people;
    $event_id   = 'rbf_' . $post_id; // usato per Pixel (browser) e CAPI (server)

    // Transient completo per tracking in footer
    set_transient('rbf_booking_data_' . $post_id, [
        'id'       => $post_id,
        'value'    => $valore_tot,
        'currency' => 'EUR',
        'meal'     => $meal,
        'people'   => $people,
        'bucket'   => $src['bucket'],
        'event_id' => $event_id
    ], 60 * 15);

    // Notifiche e integrazioni
    // Admin notification email (webmaster notification)
    if (function_exists('rbf_send_admin_notification_email')) {
        rbf_send_admin_notification_email($first_name, $last_name, $email, $date, $time, $people, $notes, $tel, $meal);
    }
    
    // Brevo: sempre (lista + evento) - use country-based language for list selection
    if (function_exists('rbf_trigger_brevo_automation')) {
        rbf_trigger_brevo_automation($first_name, $last_name, $email, $date, $time, $people, $notes, $brevo_lang, $tel, $marketing, $meal);
    }

    // Meta CAPI server-side (dedup con event_id) + bucket standard
    if (!empty($options['meta_pixel_id']) && !empty($options['meta_access_token'])) {
        $meta_url = "https://graph.facebook.com/v20.0/{$options['meta_pixel_id']}/events?access_token={$options['meta_access_token']}";
        // standardizza: tutto ciò che NON è gads/fbads => organic
        $bucket_std = ($src['bucket'] === 'gads' || $src['bucket'] === 'fbads') ? $src['bucket'] : 'organic';

        $meta_payload = [
            'data' => [[
                'action_source' => 'website',
                'event_name' => 'Purchase',
                'event_time' => time(),
                'event_id' => (string) $event_id,
                'user_data' => [
                    'client_ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'client_user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                ],
                'custom_data' => [
                    'value'    => $valore_tot,
                    'currency' => 'EUR',
                    'bucket'   => $bucket_std
                ]
            ]]
        ];
        
        // Track Meta CAPI performance
        $meta_start_time = microtime(true);
        $response = wp_remote_post($meta_url, [
            'body' => wp_json_encode($meta_payload),
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 8
        ]);
        
        // Monitor API performance and log results
        if (RBF_DEBUG && class_exists('RBF_Performance_Monitor')) {
            RBF_Performance_Monitor::track_api_call('meta_capi', $meta_url, $meta_start_time, $response);
        }
        
        // Enhanced error handling
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log('RBF Meta CAPI Error - Booking ID: ' . $post_id . ', Error: ' . $error_message);
            
            // Log detailed error information
            if (RBF_DEBUG && class_exists('RBF_Debug_Logger')) {
                RBF_Debug_Logger::track_event('meta_capi_error', [
                    'booking_id' => $post_id,
                    'error_message' => $error_message,
                    'error_code' => $response->get_error_code(),
                    'bucket' => $bucket_std,
                    'value' => $valore_tot
                ], 'ERROR');
            }
            
            // Notify admin of critical API failures
            if ($response->get_error_code() === 'http_request_timeout') {
                wp_mail(
                    get_option('admin_email'),
                    'RBF: Meta CAPI Timeout Warning',
                    "Timeout su chiamata Meta CAPI per prenotazione #{$post_id}. Valore: €{$valore_tot}"
                );
            }
        } else {
            // Log successful API call
            if (RBF_DEBUG && class_exists('RBF_Debug_Logger')) {
                $response_code = wp_remote_retrieve_response_code($response);
                RBF_Debug_Logger::track_event('meta_capi_success', [
                    'booking_id' => $post_id,
                    'response_code' => $response_code,
                    'bucket' => $bucket_std,
                    'value' => $valore_tot
                ], 'INFO');
            }
        }
    }

    // Log successful booking creation
    if (RBF_DEBUG && class_exists('RBF_Debug_Logger')) {
        RBF_Debug_Logger::track_event('booking_created', [
            'booking_id' => $post_id,
            'source_bucket' => $src['bucket'],
            'meal' => $meal,
            'people' => $people,
            'value' => $valore_tot,
            'utm_source' => $utm_source,
            'utm_medium' => $utm_medium,
            'utm_campaign' => $utm_campaign,
            'gclid' => $gclid,
            'fbclid' => $fbclid
        ], 'INFO');
    }

    // End performance monitoring
    if (RBF_DEBUG && class_exists('RBF_Performance_Monitor')) {
        RBF_Performance_Monitor::end_timing('booking_submission', [
            'booking_id' => $post_id,
            'integrations_called' => [
                'brevo' => function_exists('rbf_trigger_brevo_automation'),
                'meta_capi' => !empty($options['meta_pixel_id']) && !empty($options['meta_access_token'])
            ]
        ]);
    }

    $success_args = ['rbf_success' => '1', 'booking_id' => $post_id];
    wp_safe_redirect(add_query_arg($success_args, $redirect_url . $anchor)); exit;
}

/**
 * AJAX handler for availability check - Completely rebuilt for reliability
 */
add_action('wp_ajax_rbf_get_availability', 'rbf_ajax_get_availability_callback');
add_action('wp_ajax_nopriv_rbf_get_availability', 'rbf_ajax_get_availability_callback');
function rbf_ajax_get_availability_callback() {
    // Enhanced debug logging from the start
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("RBF: Starting availability check");
    }
    
    check_ajax_referer('rbf_ajax_nonce');
    
    if (empty($_POST['date']) || empty($_POST['meal'])) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("RBF: Missing required parameters - date or meal");
        }
        wp_send_json_error(['message' => 'Missing required parameters']);
        return;
    }

    $date = sanitize_text_field($_POST['date']);
    $meal = sanitize_text_field($_POST['meal']);
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("RBF: Checking availability for date={$date}, meal={$meal}");
    }

    // Get settings
    $options = get_option('rbf_settings', rbf_get_default_settings());
    
    // Step 1: Check if restaurant is open on this day of the week
    $day_of_week = date('w', strtotime($date));
    $day_keys = ['sun','mon','tue','wed','thu','fri','sat'];
    $day_key = $day_keys[$day_of_week];

    if (($options["open_{$day_key}"] ?? 'no') !== 'yes') {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("RBF: Restaurant closed on {$day_key}");
        }
        wp_send_json_success([]);
        return;
    }

    // Step 2: Check specific closed dates
    $closed_specific = rbf_get_closed_specific($options);
    if (in_array($date, $closed_specific['singles'], true)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("RBF: Date {$date} is specifically closed");
        }
        wp_send_json_success([]);
        return;
    }
    
    foreach ($closed_specific['ranges'] as $range) {
        if ($date >= $range['from'] && $date <= $range['to']) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("RBF: Date {$date} falls within closed range {$range['from']} to {$range['to']}");
            }
            wp_send_json_success([]);
            return;
        }
    }

    // Step 3: Get configured time slots for this meal
    $times_csv = $options['orari_'.$meal] ?? '';
    if (empty($times_csv)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("RBF: No time slots configured for meal {$meal}");
        }
        wp_send_json_success([]);
        return;
    }
    
    $times = array_values(array_filter(array_map('trim', explode(',', $times_csv))));
    if (empty($times)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("RBF: No valid time slots found for meal {$meal}");
        }
        wp_send_json_success([]);
        return;
    }

    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("RBF: Found " . count($times) . " configured time slots: " . implode(', ', $times));
    }

    // Step 4: Check remaining capacity
    $remaining_capacity = rbf_get_remaining_capacity($date, $meal);
    if ($remaining_capacity <= 0) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("RBF: No remaining capacity for {$date} {$meal} (capacity: {$remaining_capacity})");
        }
        wp_send_json_success([]);
        return;
    }

    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("RBF: Remaining capacity: {$remaining_capacity}");
    }

    // Step 5: Filter times based on date and time constraints
    $tz = rbf_wp_timezone();
    $now = new DateTime('now', $tz);
    $today_string = $now->format('Y-m-d');
    $is_today = ($date === $today_string);
    $is_future = ($date > $today_string);

    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("RBF: Date analysis - Today: {$today_string}, Requested: {$date}, IsToday: " . ($is_today ? 'YES' : 'NO') . ", IsFuture: " . ($is_future ? 'YES' : 'NO'));
    }

    // Check maximum advance booking limit
    $max_advance_hours = absint($options['max_advance_hours'] ?? 72);
    $booking_date = DateTime::createFromFormat('Y-m-d', $date, $tz);
    
    if ($booking_date) {
        $hours_diff = ($booking_date->getTimestamp() - $now->getTimestamp()) / 3600;
        if ($hours_diff > $max_advance_hours) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("RBF: Date {$date} is beyond max advance booking limit ({$max_advance_hours}h). Hours diff: {$hours_diff}");
            }
            wp_send_json_success([]);
            return;
        }
    }

    // Filter times based on whether it's today or a future date
    $available_times = [];
    
    foreach ($times as $time) {
        $time = trim($time);
        
        // Normalize time format (ensure HH:MM)
        if (preg_match('/^\d:\d\d$/', $time)) {
            $time = '0' . $time;
        }
        if (preg_match('/^\d\d:\d$/', $time)) {
            $time = $time . '0';
        }
        
        // Validate time format
        if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("RBF: Invalid time format: {$time}");
            }
            continue;
        }

        // For future dates, include all times (no time-based filtering needed)
        if ($is_future) {
            $available_times[] = $time;
            continue;
        }

        // For today, filter based on current time + buffer
        if ($is_today) {
            try {
                $slot_datetime = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $time, $tz);
                if (!$slot_datetime) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("RBF: Could not create datetime for {$date} {$time}");
                    }
                    continue;
                }

                // Use 1 hour minimum advance booking for same day (reasonable buffer)
                $now_plus_buffer = clone $now;
                $now_plus_buffer->modify('+1 hour');
                
                if ($slot_datetime > $now_plus_buffer) {
                    $available_times[] = $time;
                } else {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("RBF: Time slot {$time} is too soon (within 1 hour buffer)");
                    }
                }
            } catch (Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("RBF: Exception processing time {$time}: " . $e->getMessage());
                }
                continue;
            }
        }
    }

    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("RBF: Final available times for {$date}: " . implode(', ', $available_times) . " (Total: " . count($available_times) . ")");
    }

    // Format response
    $response = [];
    foreach ($available_times as $time) {
        $response[] = [
            'slot' => $meal,
            'time' => $time
        ];
    }

    wp_send_json_success($response);
}