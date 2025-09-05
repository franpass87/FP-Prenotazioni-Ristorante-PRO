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
    $redirect_url = wp_get_referer() ? strtok(wp_get_referer(), '?') : home_url();
    $anchor = '#rbf-message-anchor';

    if (!isset($_POST['rbf_nonce']) || !wp_verify_nonce($_POST['rbf_nonce'], 'rbf_booking')) {
        rbf_handle_error(rbf_translate_string('Errore di sicurezza.'), 'security', $redirect_url . $anchor);
        return;
    }

    $required = ['rbf_meal','rbf_data','rbf_orario','rbf_persone','rbf_nome','rbf_cognome','rbf_email','rbf_tel','rbf_privacy'];
    foreach ($required as $f) {
        if (empty($_POST[$f])) {
            rbf_handle_error(rbf_translate_string('Tutti i campi sono obbligatori, inclusa l\'accettazione della privacy policy.'), 'validation', $redirect_url . $anchor);
            return;
        }
    }

    // Sanitize form input using centralized helper
    $sanitized_fields = rbf_sanitize_input_fields($_POST, [
        'rbf_meal' => 'text',
        'rbf_data' => 'text', 
        'rbf_orario' => 'text',
        'rbf_persone' => 'int',
        'rbf_nome' => 'text',
        'rbf_cognome' => 'text',
        'rbf_allergie' => 'textarea',
        'rbf_lang' => 'text',
        'rbf_country_code' => 'text',
        'rbf_utm_source' => 'text',
        'rbf_utm_medium' => 'text', 
        'rbf_utm_campaign' => 'text',
        'rbf_gclid' => 'text',
        'rbf_fbclid' => 'text',
        'rbf_referrer' => 'text'
    ]);
    
    $meal = $sanitized_fields['rbf_meal'];
    $date = $sanitized_fields['rbf_data'];
    $time_data = $sanitized_fields['rbf_orario'];
    
    // Validate meal type
    $valid_meal_ids = rbf_get_valid_meal_ids();
    if (!in_array($meal, $valid_meal_ids, true)) {
        rbf_handle_error(rbf_translate_string('Tipo di pasto non valido.'), 'meal_validation', $redirect_url . $anchor);
        return;
    }

    // Check if meal is available on this day
    if (!rbf_is_meal_available_on_day($meal, $date)) {
        $meal_config = rbf_get_meal_config($meal);
        $meal_name = $meal_config ? $meal_config['name'] : $meal;
        rbf_handle_error(sprintf(rbf_translate_string('%s non è disponibile in questo giorno.'), $meal_name), 'meal_day_validation', $redirect_url . $anchor);
        return;
    }

    // Validate that the date is not in the closed dates list
    $options = rbf_get_settings();
    
    // Check if restaurant is open on this day of the week
    $day_of_week = date('w', strtotime($date));
    $day_keys = ['sun','mon','tue','wed','thu','fri','sat'];
    $day_key = $day_keys[$day_of_week];

    if (($options["open_{$day_key}"] ?? 'no') !== 'yes') {
        rbf_handle_error(rbf_translate_string('Il ristorante è chiuso in questo giorno della settimana.'), 'closed_day_validation', $redirect_url . $anchor);
        return;
    }

    // Check specific closed dates
    $closed_specific = rbf_get_closed_specific($options);
    if (in_array($date, $closed_specific['singles'], true)) {
        rbf_handle_error(rbf_translate_string('Il ristorante è chiuso nella data selezionata.'), 'closed_date_validation', $redirect_url . $anchor);
        return;
    }
    
    foreach ($closed_specific['ranges'] as $range) {
        if ($date >= $range['from'] && $date <= $range['to']) {
            rbf_handle_error(rbf_translate_string('Il ristorante è chiuso nella data selezionata.'), 'closed_date_range_validation', $redirect_url . $anchor);
            return;
        }
    }
    
    // Validate time data format
    if (strpos($time_data, '|') === false) {
        rbf_handle_error(rbf_translate_string('Formato orario non valido.'), 'time_format', $redirect_url . $anchor);
        return;
    }
    list($slot, $time) = explode('|', $time_data, 2);
    $people = $sanitized_fields['rbf_persone'];
    
    // Validate people count
    if ($people < 1 || $people > 20) {
        rbf_handle_error(rbf_translate_string('Il numero di persone deve essere compreso tra 1 e 20.'), 'people_validation', $redirect_url . $anchor);
        return;
    }
    $first_name = $sanitized_fields['rbf_nome'];
    $last_name = $sanitized_fields['rbf_cognome'];
    $email = rbf_validate_email($_POST['rbf_email']);
    if (is_array($email) && isset($email['error'])) {
        rbf_handle_error($email['message'], 'email_validation', $redirect_url . $anchor);
        return;
    }
    $tel = rbf_validate_phone($_POST['rbf_tel']);
    if (is_array($tel) && isset($tel['error'])) {
        rbf_handle_error($tel['message'], 'phone_validation', $redirect_url . $anchor);
        return;
    }
    $notes = $sanitized_fields['rbf_allergie'] ?? '';
    $lang = $sanitized_fields['rbf_lang'] ?? 'it';
    $country_code = strtolower($sanitized_fields['rbf_country_code'] ?? '');
    
    // Fallback: if no country code is provided, default to Italy
    if (empty($country_code)) {
        $country_code = 'it';
    }
    
    // Determine Brevo language based on country selection
    // If Italy is selected, use Italian list, otherwise use English list
    $brevo_lang = ($country_code === 'it') ? 'it' : 'en';
    
    $privacy = (isset($_POST['rbf_privacy']) && $_POST['rbf_privacy']==='yes') ? 'yes' : 'no';
    $marketing = (isset($_POST['rbf_marketing']) && $_POST['rbf_marketing']==='yes') ? 'yes' : 'no';

    // Sorgente & UTM dal form
    $utm_source   = $sanitized_fields['rbf_utm_source'] ?? '';
    $utm_medium   = $sanitized_fields['rbf_utm_medium'] ?? '';
    $utm_campaign = $sanitized_fields['rbf_utm_campaign'] ?? '';
    $gclid        = $sanitized_fields['rbf_gclid'] ?? '';
    $fbclid       = $sanitized_fields['rbf_fbclid'] ?? '';
    $referrer     = $sanitized_fields['rbf_referrer'] ?? '';

    $src = rbf_detect_source([
      'utm_source' => $utm_source,
      'utm_medium' => $utm_medium,
      'utm_campaign' => $utm_campaign,
      'gclid' => $gclid,
      'fbclid' => $fbclid,
      'referrer' => $referrer
    ]);

    // Validate booking time using centralized function
    $time_validation = rbf_validate_booking_time($date, $time);
    if ($time_validation !== true) {
        rbf_handle_error($time_validation['message'], 'time_validation', $redirect_url . $anchor);
        return;
    }

    // Validate buffer time requirements
    $buffer_validation = rbf_validate_buffer_time($date, $time, $slot, $people);
    if ($buffer_validation !== true) {
        rbf_handle_error($buffer_validation['message'], 'buffer_validation', $redirect_url . $anchor);
        return;
    }

    $remaining_capacity = rbf_get_remaining_capacity($date, $slot);
    
    // Check capacity - if not enough, show error (no waitlist)
    if ($remaining_capacity < $people) {
        $error_msg = sprintf(
            rbf_translate_string('Spiacenti, non ci sono abbastanza posti. Rimasti: %d. Scegli un altro orario.'), 
            $remaining_capacity
        );
        rbf_handle_error($error_msg, 'capacity_validation', $redirect_url . $anchor);
        return;
    }
    
    // All bookings are automatically confirmed
    $booking_status = 'confirmed';

    $post_id = wp_insert_post([
        'post_type' => 'rbf_booking',
        'post_title' => ucfirst($meal) . " per {$first_name} {$last_name} - {$date} {$time}",
        'post_status' => 'publish',
        'meta_input' => [
            'rbf_data' => $date,
            'rbf_meal' => $slot,
            'rbf_orario' => $slot, // Keep for backward compatibility
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
        rbf_handle_error(rbf_translate_string('Errore nel salvataggio.'), 'database_error', $redirect_url . $anchor);
        return;
    }

    // Automatic table assignment
    $table_assignment = rbf_assign_tables_first_fit($people, $date, $time, $meal);
    if ($table_assignment) {
        rbf_save_table_assignment($post_id, $table_assignment);
        
        // Store table assignment info as meta for easy access
        update_post_meta($post_id, 'rbf_table_assignment_type', $table_assignment['type']);
        update_post_meta($post_id, 'rbf_assigned_tables', $table_assignment['total_capacity']);
        
        if ($table_assignment['type'] === 'joined' && isset($table_assignment['group_id'])) {
            update_post_meta($post_id, 'rbf_table_group_id', $table_assignment['group_id']);
        }
    }

    delete_transient('rbf_avail_' . $date . '_' . $slot);
    $options = rbf_get_settings();
    
    // Get price from configurable meal or fallback to legacy
    $meal_config = rbf_get_meal_config($meal);
    if ($meal_config) {
        $valore_pp = (float) $meal_config['price'];
    } else {
        // Legacy fallback: For brunch, use lunch value for tracking
        $meal_for_value = ($meal === 'brunch') ? 'pranzo' : $meal;
        $valore_pp = (float) ($options['valore_' . $meal_for_value] ?? 0);
    }
    
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
        'gclid'    => $gclid,
        'fbclid'   => $fbclid,
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
        // Use centralized normalization function with priority gclid > fbclid > organic
        $bucket_std = rbf_normalize_bucket($gclid, $fbclid);

        $meta_payload = [
            'data' => [[
                'action_source' => 'website',
                'event_name' => 'Purchase',
                'event_time' => time(),
                'event_id' => (string) $event_id,
                'user_data' => [
                    'client_ip_address' => filter_var($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', FILTER_VALIDATE_IP) ?: '127.0.0.1',
                    'client_user_agent' => substr(sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'), 0, 250),
                ],
                'custom_data' => [
                    'value'    => $valore_tot,
                    'currency' => 'EUR',
                    'bucket'   => $bucket_std,
                    'vertical' => 'restaurant'
                ]
            ]]
        ];
        
        $meta_start_time = microtime(true);
        $response = wp_remote_post($meta_url, [
            'body' => wp_json_encode($meta_payload),
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 8
        ]);
        
        // Enhanced error handling
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            rbf_handle_error("Meta CAPI Error - Booking ID: {$post_id}, Error: {$error_message}", 'meta_api');
            
            // Notify admin of critical API failures
            if ($response->get_error_code() === 'http_request_timeout') {
                wp_mail(
                    get_option('admin_email'),
                    'RBF: Meta CAPI Timeout Warning',
                    "Timeout su chiamata Meta CAPI per prenotazione #{$post_id}. Valore: €{$valore_tot}"
                );
            }
        } else {
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code < 200 || $response_code >= 300) {
                $response_body = wp_remote_retrieve_body($response);
                rbf_handle_error("Meta CAPI Error - Booking ID: {$post_id}, HTTP {$response_code}: {$response_body}", 'meta_api');
            }
        }
    }

    // Success - redirect with booking confirmation
    $success_args = ['rbf_success' => '1', 'booking_id' => $post_id];
    rbf_handle_success('Booking created successfully', $success_args, add_query_arg($success_args, $redirect_url . $anchor));
}

/**
 * AJAX handler for availability check - Completely rebuilt for reliability
 */
add_action('wp_ajax_rbf_get_availability', 'rbf_ajax_get_availability_callback');
add_action('wp_ajax_nopriv_rbf_get_availability', 'rbf_ajax_get_availability_callback');
function rbf_ajax_get_availability_callback() {
    // Verify nonce for security
    if (!check_ajax_referer('rbf_ajax_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => 'Security check failed']);
        return;
    }

    // Validate required parameters
    if (empty($_POST['date']) || empty($_POST['meal'])) {
        rbf_handle_error('Missing required parameters', 'ajax_validation');
        return;
    }

    // Sanitize and validate inputs
    $sanitized_fields = rbf_sanitize_input_fields($_POST, [
        'date' => 'text',
        'meal' => 'text'
    ]);
    
    $date = $sanitized_fields['date'];
    $meal = $sanitized_fields['meal'];
    
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !DateTime::createFromFormat('Y-m-d', $date)) {
        rbf_handle_error(rbf_translate_string('Data non valida.'), 'date_validation');
        return;
    }
    
    // Validate meal type
    $valid_meal_ids = rbf_get_valid_meal_ids();
    if (!in_array($meal, $valid_meal_ids, true)) {
        rbf_handle_error(rbf_translate_string('Tipo di pasto non valido.'), 'meal_validation');
        return;
    }

    // Check if meal is available on this day
    if (!rbf_is_meal_available_on_day($meal, $date)) {
        $meal_config = rbf_get_meal_config($meal);
        $meal_name = $meal_config ? $meal_config['name'] : $meal;
        rbf_handle_error(sprintf(rbf_translate_string('%s non è disponibile in questo giorno.'), $meal_name), 'meal_day_validation');
        return;
    }

    // Get settings
    $options = rbf_get_settings();
    
    // Step 1: Check if restaurant is open on this day of the week
    $day_of_week = date('w', strtotime($date));
    $day_keys = ['sun','mon','tue','wed','thu','fri','sat'];
    $day_key = $day_keys[$day_of_week];

    if (($options["open_{$day_key}"] ?? 'no') !== 'yes') {
        wp_send_json_success([]);
        return;
    }

    // Step 2: Check specific closed dates
    $closed_specific = rbf_get_closed_specific($options);
    if (in_array($date, $closed_specific['singles'], true)) {
        wp_send_json_success([]);
        return;
    }
    
    foreach ($closed_specific['ranges'] as $range) {
        if ($date >= $range['from'] && $date <= $range['to']) {
            wp_send_json_success([]);
            return;
        }
    }

    // Step 3: Get configured time slots for this meal, considering special exceptions
    $special_hours = rbf_get_special_hours_for_date($date, $meal, $options);
    $times = [];
    
    if (!empty($special_hours)) {
        // Parse special hours format (e.g., "18:00-02:00" or "19:00,20:00,21:00")
        if (strpos($special_hours, '-') !== false) {
            // Range format: generate hourly slots
            list($start_time, $end_time) = array_map('trim', explode('-', $special_hours, 2));
            $start_hour = (int) substr($start_time, 0, 2);
            $end_hour = (int) substr($end_time, 0, 2);
            
            // Handle overnight ranges (e.g., 19:00-02:00)
            if ($end_hour <= $start_hour) {
                $end_hour += 24;
            }
            
            for ($hour = $start_hour; $hour < $end_hour; $hour++) {
                $display_hour = $hour >= 24 ? $hour - 24 : $hour;
                $times[] = sprintf('%02d:00', $display_hour);
                $times[] = sprintf('%02d:30', $display_hour);
            }
        } else if (strpos($special_hours, ',') !== false) {
            // Comma-separated format
            $times = array_map('trim', explode(',', $special_hours));
        } else {
            // Single time
            $times = [$special_hours];
        }
    } else {
        // Use regular meal configuration
        $meal_config = rbf_get_meal_config($meal);
        if (!$meal_config) {
            wp_send_json_success([]);
            return;
        }
        
        $times_csv = $meal_config['time_slots'] ?? '';
        if (empty($times_csv)) {
            wp_send_json_success([]);
            return;
        }
        
        $times = array_values(array_filter(array_map('trim', explode(',', $times_csv))));
    }
    
    if (empty($times)) {
        wp_send_json_success([]);
        return;
    }

    // Step 4: Check remaining capacity
    $remaining_capacity = rbf_get_remaining_capacity($date, $meal);
    if ($remaining_capacity <= 0) {
        wp_send_json_success([]);
        return;
    }


    // Step 5: Filter times based on advance requirement and buffer conflicts
    $tz = rbf_wp_timezone();
    $now = new DateTime('now', $tz);
    $min_datetime = clone $now;
    $min_datetime->modify("+60 minutes");

    $available_times = [];

    foreach ($times as $time) {
        // Use centralized time normalization
        $normalized_time = rbf_normalize_time_format($time);
        if ($normalized_time === false) {
            continue;
        }

        try {
            $slot_datetime = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $normalized_time, $tz);
            if (!$slot_datetime) {
                continue;
            }

            // Simple 1-hour minimum advance check
            if ($slot_datetime < $min_datetime) {
                continue;
            }

            // Check if time slot has potential buffer conflicts
            // For availability check, we use a conservative approach with average party size
            $average_party_size = 2; // Conservative estimate
            $buffer_check = rbf_validate_buffer_time($date, $normalized_time, $meal, $average_party_size);
            if ($buffer_check !== true) {
                continue; // Skip this time if it has buffer conflicts
            }

            $available_times[] = $normalized_time;
        } catch (Exception $e) {
            continue;
        }
    }

    // Format response
    $response = [];
    foreach ($available_times as $time) {
        $response[] = [
            'slot' => $meal, // Use the actual slot for capacity
            'time' => $time
        ];
    }

    wp_send_json_success($response);
}