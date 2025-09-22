<?php
/**
 * Booking submission handler for FP Prenotazioni Ristorante
 *
 * @package FP_Prenotazioni_Ristorante_PRO
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Validate booking request and sanitize data.
 *
 * @param array  $post         Raw POST data.
 * @param string $redirect_url URL for redirection on error.
 * @param string $anchor       Anchor for messages.
 * @return array|false Sanitized booking data or false on failure.
 */
function rbf_validate_request($post, $redirect_url, $anchor) {
    if (!isset($post['rbf_nonce']) || !wp_verify_nonce($post['rbf_nonce'], 'rbf_booking')) {
        rbf_handle_error(rbf_translate_string('Errore di sicurezza.'), 'security', $redirect_url . $anchor);
        return false;
    }

    // Anti-bot validation
    $bot_detected = rbf_detect_bot_submission($post);
    if ($bot_detected['is_bot']) {
        rbf_log("RBF Bot Detection: " . $bot_detected['reason'] . " - IP: " . $_SERVER['REMOTE_ADDR']);

        if ($bot_detected['severity'] === 'high') {
            $options = rbf_get_settings();
            $recaptcha_configured = !empty($options['recaptcha_site_key']) && !empty($options['recaptcha_secret_key']);

            if ($recaptcha_configured && !empty($post['g-recaptcha-response'])) {
                $recaptcha_result = rbf_verify_recaptcha($post['g-recaptcha-response']);
                if (!$recaptcha_result['success']) {
                    rbf_log("RBF reCAPTCHA Failed: " . $recaptcha_result['reason'] . " - IP: " . $_SERVER['REMOTE_ADDR']);
                    rbf_handle_error(rbf_translate_string('Verifica di sicurezza fallita. Per favore riprova.'), 'recaptcha_failed', $redirect_url . $anchor);
                    return false;
                }
                rbf_log("RBF Bot detected but reCAPTCHA passed - allowing submission");
            } else {
                rbf_handle_error(rbf_translate_string('Rilevata attività sospetta. Per favore riprova.'), 'bot_detected', $redirect_url . $anchor);
                return false;
            }
        }
    }

    $required = ['rbf_meal','rbf_data','rbf_orario','rbf_persone','rbf_nome','rbf_cognome','rbf_email','rbf_phone_prefix','rbf_tel_number','rbf_privacy'];
    foreach ($required as $f) {
        if (empty($post[$f])) {
            rbf_handle_error(rbf_translate_string('Tutti i campi sono obbligatori, inclusa l\'accettazione della privacy policy.'), 'validation', $redirect_url . $anchor);
            return false;
        }
    }

    // Sanitize form input
    $sanitized_fields = rbf_sanitize_input_fields($post, [
        'rbf_meal'           => 'text',
        'rbf_data'           => 'text',
        'rbf_orario'         => 'text',
        'rbf_persone'        => 'int',
        'rbf_nome'           => 'name',
        'rbf_cognome'        => 'name',
        'rbf_allergie'       => 'textarea',
        'rbf_lang'           => 'text',
        'rbf_phone_prefix'   => 'text',
        'rbf_tel_number'     => 'phone',
        'rbf_utm_source'     => 'text',
        'rbf_utm_medium'     => 'text',
        'rbf_utm_campaign'   => 'text',
        'rbf_gclid'          => 'text',
        'rbf_fbclid'         => 'text',
        'rbf_referrer'       => 'text',
        // Special occasion fields
        'rbf_special_type'   => 'text',
        'rbf_special_label'  => 'text',
        // Anti-bot fields
        'rbf_form_timestamp' => 'int',
        'rbf_website'        => 'text',
        // Consent fields
        'rbf_privacy'        => 'text',
        'rbf_marketing'      => 'text'
    ]);

    $privacy_raw = $sanitized_fields['rbf_privacy'] ?? '';
    if ($privacy_raw !== 'yes') {
        rbf_handle_error(rbf_translate_string('È necessario accettare l\'informativa sulla privacy per proseguire.'), 'privacy_validation', $redirect_url . $anchor);
        return false;
    }
    $privacy = 'yes';

    $meal = $sanitized_fields['rbf_meal'];
    $date = $sanitized_fields['rbf_data'];
    $time_data = $sanitized_fields['rbf_orario'];

    $valid_meal_ids = rbf_get_valid_meal_ids();
    if (!in_array($meal, $valid_meal_ids, true)) {
        rbf_handle_error(rbf_translate_string('Tipo di pasto non valido.'), 'meal_validation', $redirect_url . $anchor);
        return false;
    }

    if (strpos($time_data, '|') === false) {
        rbf_handle_error(rbf_translate_string('Formato orario non valido.'), 'time_format', $redirect_url . $anchor);
        return false;
    }
    list($slot, $time) = explode('|', $time_data, 2);

    $slot = trim($slot);
    $normalized_slot = sanitize_key($slot);
    $slot_config = null;

    if ($normalized_slot !== '') {
        $slot_config = rbf_get_meal_config($normalized_slot);

        if (!$slot_config && $normalized_slot !== $slot) {
            $slot_config = rbf_get_meal_config($slot);
        }

        if (!$slot_config) {
            $active_meals = rbf_get_active_meals();
            foreach ($active_meals as $meal_config_candidate) {
                if (empty($meal_config_candidate['legacy_ids'])) {
                    continue;
                }

                $legacy_ids = array_map('sanitize_key', (array) $meal_config_candidate['legacy_ids']);
                if (in_array($normalized_slot, $legacy_ids, true)) {
                    $slot_config = $meal_config_candidate;
                    break;
                }
            }
        }

        if ($slot_config && !empty($slot_config['id'])) {
            $normalized_slot = $slot_config['id'];
        }
    }

    if ($normalized_slot === '') {
        rbf_handle_error(rbf_translate_string('La fascia oraria selezionata non è valida.'), 'slot_validation', $redirect_url . $anchor);
        return false;
    }

    if (!in_array($normalized_slot, $valid_meal_ids, true)) {
        rbf_handle_error(rbf_translate_string('La fascia oraria selezionata non è valida.'), 'slot_validation', $redirect_url . $anchor);
        return false;
    }

    if ($normalized_slot !== $meal) {
        rbf_handle_error(rbf_translate_string('Il pasto selezionato non corrisponde alla fascia oraria scelta.'), 'slot_mismatch', $redirect_url . $anchor);
        return false;
    }

    $slot = $normalized_slot;
    $people = $sanitized_fields['rbf_persone'];

    $people_max_limit = rbf_get_people_max_limit();
    if ($people < 1 || $people > $people_max_limit) {
        rbf_handle_error(sprintf(rbf_translate_string('Il numero di persone deve essere compreso tra 1 e %d.'), $people_max_limit), 'people_validation', $redirect_url . $anchor);
        return false;
    }

    $first_name = $sanitized_fields['rbf_nome'];
    $last_name = $sanitized_fields['rbf_cognome'];
    $email = rbf_validate_email($post['rbf_email']);
    if (is_array($email) && isset($email['error'])) {
        rbf_handle_error($email['message'], 'email_validation', $redirect_url . $anchor);
        return false;
    }
    $phone_data = rbf_prepare_phone_number($sanitized_fields['rbf_phone_prefix'] ?? '', $sanitized_fields['rbf_tel_number'] ?? '');

    if (empty($phone_data['number'])) {
        rbf_handle_error(rbf_translate_string('Il numero di telefono inserito non è valido.'), 'phone_validation', $redirect_url . $anchor);
        return false;
    }

    $validated_tel = rbf_validate_phone($phone_data['full']);
    if (is_array($validated_tel) && isset($validated_tel['error'])) {
        rbf_handle_error($validated_tel['message'], 'phone_validation', $redirect_url . $anchor);
        return false;
    }
    $tel = is_array($validated_tel) ? $phone_data['full'] : $validated_tel;

    $notes = $sanitized_fields['rbf_allergie'] ?? '';
    $form_lang = $sanitized_fields['rbf_lang'] ?? 'it';
    $normalized_lang = strtolower($form_lang);
    if ($normalized_lang !== 'en') {
        $normalized_lang = 'it';
    }
    $country_code = strtolower($phone_data['country_code'] ?? 'it');
    if ($country_code === '') {
        $country_code = 'it';
    }

    // Determine Brevo list using form language first, then phone prefix rules
    $brevo_lang = ($normalized_lang === 'en') ? 'en' : 'it';
    if ($country_code === 'it') {
        $brevo_lang = 'it';
    }

    $marketing_raw = $sanitized_fields['rbf_marketing'] ?? 'no';
    $marketing = ($marketing_raw === 'yes' || $marketing_raw === 'no') ? $marketing_raw : 'no';

    // Sorgente & UTM dal form
    $utm_source   = $sanitized_fields['rbf_utm_source'] ?? '';
    $utm_medium   = $sanitized_fields['rbf_utm_medium'] ?? '';
    $utm_campaign = $sanitized_fields['rbf_utm_campaign'] ?? '';
    $gclid        = $sanitized_fields['rbf_gclid'] ?? '';
    $fbclid       = $sanitized_fields['rbf_fbclid'] ?? '';
    $referrer     = $sanitized_fields['rbf_referrer'] ?? '';

    $src = rbf_detect_source([
        'utm_source'   => $utm_source,
        'utm_medium'   => $utm_medium,
        'utm_campaign' => $utm_campaign,
        'gclid'        => $gclid,
        'fbclid'       => $fbclid,
        'referrer'     => $referrer
    ]);

    return [
        'sanitized_fields' => $sanitized_fields,
        'meal'             => $meal,
        'date'             => $date,
        'slot'             => $slot,
        'time'             => $time,
        'people'           => $people,
        'first_name'       => $first_name,
        'last_name'        => $last_name,
        'email'            => $email,
        'tel'              => $tel,
        'tel_prefix'       => $phone_data['prefix'],
        'tel_number'       => $phone_data['number'],
        'notes'            => $notes,
        'lang'             => $form_lang,
        'country_code'     => $country_code,
        'brevo_lang'       => $brevo_lang,
        'privacy'          => $privacy,
        'marketing'        => $marketing,
        'utm_source'       => $utm_source,
        'utm_medium'       => $utm_medium,
        'utm_campaign'     => $utm_campaign,
        'gclid'            => $gclid,
        'fbclid'           => $fbclid,
        'referrer'         => $referrer,
        'src'              => $src
    ];
}

/**
 * Check availability and reserve the slot.
 *
 * @param array  $data         Sanitized booking data.
 * @param string $redirect_url URL for redirection on error.
 * @param string $anchor       Anchor for messages.
 * @return array|false Booking data enriched with reservation info or false on failure.
 */
function rbf_check_availability($data, $redirect_url, $anchor) {
    $meal   = $data['meal'];
    $date   = $data['date'];
    $slot   = $data['slot'];
    $time   = $data['time'];
    $people = $data['people'];

    if (!rbf_is_meal_available_on_day($meal, $date)) {
        $meal_config = rbf_get_meal_config($meal);
        $meal_name = $meal_config ? $meal_config['name'] : $meal;
        rbf_handle_error(sprintf(rbf_translate_string('%s non è disponibile in questo giorno.'), $meal_name), 'meal_day_validation', $redirect_url . $anchor);
        return false;
    }

    if (!rbf_is_restaurant_open($date, $meal)) {
        rbf_handle_error(rbf_translate_string('Il ristorante è chiuso nella data selezionata.'), 'restaurant_closed', $redirect_url . $anchor);
        return false;
    }

    $time_validation = rbf_validate_booking_time($date, $time);
    if ($time_validation !== true) {
        rbf_handle_error($time_validation['message'], 'time_validation', $redirect_url . $anchor);
        return false;
    }

    $buffer_validation = rbf_validate_buffer_time($date, $time, $slot, $people);
    if ($buffer_validation !== true) {
        rbf_handle_error($buffer_validation['message'], 'buffer_validation', $redirect_url . $anchor);
        return false;
    }

    $booking_result = rbf_book_slot_optimistic($date, $slot, $people);

    if (!$booking_result['success']) {
        if ($booking_result['error'] === 'insufficient_capacity') {
            $remaining = $booking_result['remaining'] ?? 0;
            $error_msg = sprintf(
                rbf_translate_string('Spiacenti, non ci sono abbastanza posti. Rimasti: %d. Scegli un altro orario.'),
                $remaining
            );
            rbf_handle_error($error_msg, 'capacity_validation', $redirect_url . $anchor);
        } elseif ($booking_result['error'] === 'version_conflict') {
            $error_msg = rbf_translate_string('Questo slot è stato appena prenotato da un altro utente. Ti preghiamo di ricaricare la pagina e riprovare.');
            rbf_handle_error($error_msg, 'concurrent_booking', $redirect_url . $anchor);
        } else {
            $error_msg = rbf_translate_string('Errore durante la prenotazione. Ti preghiamo di riprovare.');
            rbf_handle_error($error_msg, 'booking_system_error', $redirect_url . $anchor);
        }
        return false;
    }

    $data['booking_result'] = $booking_result;
    $data['booking_status'] = 'confirmed';
    return $data;
}

/**
 * Create the booking post and store metadata.
 *
 * @param array  $data         Booking data with reservation info.
 * @param string $redirect_url URL for redirection on error.
 * @param string $anchor       Anchor for messages.
 * @return array|false Context data including post ID and tracking info or false on failure.
 */
function rbf_create_booking_post($data, $redirect_url, $anchor) {
    $sanitized_fields = $data['sanitized_fields'];
    $meal             = $data['meal'];
    $date             = $data['date'];
    $slot             = $data['slot'];
    $time             = $data['time'];
    $people           = $data['people'];
    $first_name       = $data['first_name'];
    $last_name        = $data['last_name'];
    $email            = $data['email'];
    $tel              = $data['tel'];
    $notes            = $data['notes'];
    $lang             = $data['lang'];
    $country_code     = $data['country_code'];
    $brevo_lang       = $data['brevo_lang'];
    $privacy          = $data['privacy'];
    $marketing        = $data['marketing'];
    $src              = $data['src'];
    $gclid            = $data['gclid'];
    $fbclid           = $data['fbclid'];
    $referrer         = $data['referrer'];
    $booking_result   = $data['booking_result'];
    $booking_status   = $data['booking_status'];

    $options = rbf_get_settings();

    // Calcola il prezzo unitario e totale utilizzando le configurazioni disponibili
    $meal_config = rbf_get_meal_config($meal);
    if ($meal_config) {
        $valore_pp = (float) $meal_config['price'];
    } else {
        $meal_for_value = ($meal === 'brunch') ? 'pranzo' : $meal;
        $valore_pp = (float) ($options['valore_' . $meal_for_value] ?? 0);
    }

    $valore_tot = $valore_pp * $people;

    $meta_input = [
        'rbf_data'          => $date,
        'rbf_meal'          => $meal,
        'rbf_orario'        => $time,
        'rbf_time'          => $time,
        'rbf_persone'       => $people,
        'rbf_nome'          => $first_name,
        'rbf_cognome'       => $last_name,
        'rbf_email'         => $email,
        'rbf_tel'           => $tel,
        'rbf_tel_prefix'    => $data['tel_prefix'],
        'rbf_tel_number'    => $data['tel_number'],
        'rbf_allergie'      => $notes,
        'rbf_lang'          => $lang,
        'rbf_country_code'  => $country_code,
        'rbf_brevo_lang'    => $brevo_lang,
        'rbf_privacy'       => $privacy,
        'rbf_marketing'     => $marketing,
        'rbf_special_type'  => $sanitized_fields['rbf_special_type'] ?? '',
        'rbf_special_label' => $sanitized_fields['rbf_special_label'] ?? '',
        'rbf_source_bucket' => $src['bucket'],
        'rbf_source'        => $src['source'],
        'rbf_medium'        => $src['medium'],
        'rbf_campaign'      => $src['campaign'],
        'rbf_gclid'         => $gclid,
        'rbf_fbclid'        => $fbclid,
        'rbf_referrer'      => $referrer,
        'rbf_booking_status'  => $booking_status,
        'rbf_booking_created' => current_time('Y-m-d H:i:s'),
        'rbf_booking_hash'    => wp_generate_password(16, false, false),
    ];

    // Persist calculated financial metadata for reliable fallbacks and reporting
    $meta_input['rbf_valore_pp']  = $valore_pp;
    $meta_input['rbf_valore_tot'] = $valore_tot;

    $post_id = wp_insert_post([
        'post_type'   => 'rbf_booking',
        'post_title'  => ucfirst($meal) . " per {$first_name} {$last_name} - {$date} {$time}",
        'post_status' => 'publish',
        'meta_input'  => $meta_input,
    ]);

    if (is_wp_error($post_id)) {
        rbf_release_slot_capacity($date, $slot, $people);
        rbf_handle_error(rbf_translate_string('Errore nel salvataggio.'), 'database_error', $redirect_url . $anchor);
        return false;
    }

    update_post_meta($post_id, 'rbf_slot_version', $booking_result['version']);
    update_post_meta($post_id, 'rbf_booking_attempt', $booking_result['attempt']);

    // Automatic table assignment
    $table_assignment = rbf_assign_tables_first_fit($people, $date, $time, $meal);
    if ($table_assignment) {
        rbf_save_table_assignment($post_id, $table_assignment);

        update_post_meta($post_id, 'rbf_table_assignment_type', $table_assignment['type']);
        update_post_meta($post_id, 'rbf_assigned_tables', $table_assignment['total_capacity']);

        if ($table_assignment['type'] === 'joined' && isset($table_assignment['group_id'])) {
            update_post_meta($post_id, 'rbf_table_group_id', $table_assignment['group_id']);
        }
    }

    $tracking_token = wp_generate_password(20, false, false);

    $booking_context = array_merge(
        $data,
        [
            'post_id'          => $post_id,
            'table_assignment' => $table_assignment,
            'tracking_token'   => $tracking_token,
        ]
    );

    /**
     * Fires immediately after a booking post has been created.
     *
     * @param int   $post_id         The ID of the booking post.
     * @param array $booking_context Booking context data.
     */
    do_action('rbf_booking_created', $post_id, $booking_context);

    delete_transient('rbf_avail_' . $date . '_' . $slot);

    // I valori economici sono stati calcolati prima della creazione del post
    $event_id   = 'rbf_' . $post_id;

    set_transient('rbf_booking_data_' . $post_id, [
        'id'            => $post_id,
        'value'         => $valore_tot,
        'currency'      => 'EUR',
        'meal'          => $meal,
        'people'        => $people,
        'bucket'        => $src['bucket'],
        'gclid'         => $gclid,
        'fbclid'        => $fbclid,
        'event_id'      => $event_id,
        'unit_price'    => $valore_pp,
        'tracking_token' => $tracking_token,
    ], 60 * 15);

    return [
        'post_id'        => $post_id,
        'valore_tot'     => $valore_tot,
        'valore_pp'      => $valore_pp,
        'event_id'       => $event_id,
        'options'        => $options,
        'tracking_token' => $tracking_token,
    ];
}

/**
 * Send notifications and perform integrations.
 *
 * @param array $data    Booking data.
 * @param array $context Context data from post creation.
 * @return void
 */
function rbf_send_notifications($data, $context) {
    $post_id    = $context['post_id'];
    $valore_tot = $context['valore_tot'];
    $event_id   = $context['event_id'];
    $options    = $context['options'];

    $first_name = $data['first_name'];
    $last_name  = $data['last_name'];
    $email      = $data['email'];
    $date       = $data['date'];
    $time       = $data['time'];
    $people     = $data['people'];
    $notes      = $data['notes'];
    $form_lang  = $data['lang'];
    $tel        = $data['tel'];
    $meal       = $data['meal'];
    $brevo_lang = $data['brevo_lang'];
    $marketing  = $data['marketing'];
    $src        = $data['src'];
    $gclid      = $data['gclid'];
    $fbclid     = $data['fbclid'];
    $sanitized_fields = $data['sanitized_fields'];

    if (function_exists('rbf_send_admin_notification_with_failover')) {
        rbf_send_admin_notification_with_failover(
            $first_name, $last_name, $email, $date, $time, $people, $notes, $tel, $meal,
            $brevo_lang, $form_lang, $post_id,
            $sanitized_fields['rbf_special_type'] ?? '', $sanitized_fields['rbf_special_label'] ?? ''
        );
    }

    if (function_exists('rbf_send_customer_notification_with_failover')) {
        rbf_send_customer_notification_with_failover(
            $first_name, $last_name, $email, $date, $time, $people, $notes, $brevo_lang, $form_lang, $tel, $marketing, $meal, $post_id,
            $sanitized_fields['rbf_special_type'] ?? '', $sanitized_fields['rbf_special_label'] ?? ''
        );
    }

    if (!empty($options['meta_pixel_id']) && !empty($options['meta_access_token'])) {
        $meta_url = "https://graph.facebook.com/v20.0/{$options['meta_pixel_id']}/events?access_token={$options['meta_access_token']}";
        $bucket_std = rbf_normalize_bucket($gclid, $fbclid);

        $meta_payload = [
            'data' => [[
                'action_source' => 'website',
                'event_name'   => 'Purchase',
                'event_time'   => time(),
                'event_id'     => (string) $event_id,
                'user_data'    => [
                    'client_ip_address' => filter_var($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', FILTER_VALIDATE_IP) ?: '127.0.0.1',
                    'client_user_agent' => substr(sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'), 0, 250),
                ],
                'custom_data'  => [
                    'value'    => $valore_tot,
                    'currency' => 'EUR',
                    'bucket'   => $bucket_std,
                    'vertical' => 'restaurant'
                ]
            ]]
        ];

        $response = wp_remote_post($meta_url, [
            'body'    => wp_json_encode($meta_payload),
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 8
        ]);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            rbf_handle_error("Meta CAPI Error - Booking ID: {$post_id}, Error: {$error_message}", 'meta_api');

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

    if (function_exists('rbf_track_booking_completion')) {
        $tracking_completion_data = [
            'id'      => $post_id,
            'value'   => $valore_tot,
            'currency'=> 'EUR',
            'meal'    => $meal,
            'people'  => $people,
            'bucket'  => $src['bucket']
        ];
        rbf_track_booking_completion($post_id, $tracking_completion_data);
    }
}

/**
 * Build normalized tracking data for a booking using stored metadata.
 *
 * @param int        $booking_id Booking post ID.
 * @param array      $base_data  Optional base tracking data to merge.
 * @param array|null $meta       Optional pre-fetched post meta array.
 * @return array Normalized tracking dataset with financial fallbacks.
 */
function rbf_build_booking_tracking_data($booking_id, array $base_data = [], $meta = null) {
    if (!$booking_id) {
        return $base_data;
    }

    if (!is_array($meta)) {
        $meta = get_post_meta($booking_id);
    }

    $tracking = array_merge([
        'id'       => $booking_id,
        'currency' => 'EUR',
        'event_id' => 'rbf_' . $booking_id,
    ], $base_data);

    $meal_meta = $meta['rbf_meal'][0] ?? ($meta['rbf_orario'][0] ?? '');
    if (empty($tracking['meal']) && !empty($meal_meta)) {
        $tracking['meal'] = $meal_meta;
    } elseif (empty($tracking['meal'])) {
        $tracking['meal'] = 'pranzo';
    }

    $people_meta = isset($meta['rbf_persone'][0]) ? (int) $meta['rbf_persone'][0] : 0;
    if (!isset($tracking['people']) || (int) $tracking['people'] <= 0) {
        $tracking['people'] = $people_meta;
    } else {
        $tracking['people'] = (int) $tracking['people'];
    }

    if (empty($tracking['bucket'])) {
        $tracking['bucket'] = $meta['rbf_source_bucket'][0] ?? 'organic';
    }

    if (!isset($tracking['gclid'])) {
        $tracking['gclid'] = $meta['rbf_gclid'][0] ?? '';
    }

    if (!isset($tracking['fbclid'])) {
        $tracking['fbclid'] = $meta['rbf_fbclid'][0] ?? '';
    }

    if (empty($tracking['currency']) && isset($meta['rbf_value_currency'][0])) {
        $tracking['currency'] = $meta['rbf_value_currency'][0];
    }

    $value_meta      = isset($meta['rbf_valore_tot'][0]) ? (float) $meta['rbf_valore_tot'][0] : 0.0;
    $unit_price_meta = isset($meta['rbf_valore_pp'][0]) ? (float) $meta['rbf_valore_pp'][0] : 0.0;

    if (!isset($tracking['value']) || !is_numeric($tracking['value']) || $tracking['value'] <= 0) {
        $tracking['value'] = $value_meta;
    } else {
        $tracking['value'] = (float) $tracking['value'];
    }

    if (!isset($tracking['unit_price']) || !is_numeric($tracking['unit_price']) || $tracking['unit_price'] <= 0) {
        $tracking['unit_price'] = $unit_price_meta;
    } else {
        $tracking['unit_price'] = (float) $tracking['unit_price'];
    }

    $people_for_calc = (int) ($tracking['people'] ?? 0);
    if ($people_for_calc <= 0 && $people_meta > 0) {
        $people_for_calc = $people_meta;
        $tracking['people'] = $people_meta;
    }
    $people_for_calc = max(1, $people_for_calc);

    if ($tracking['unit_price'] <= 0 && $tracking['value'] > 0) {
        $tracking['unit_price'] = $people_for_calc > 0 ? $tracking['value'] / $people_for_calc : 0.0;
    }

    if ($tracking['value'] <= 0 && $tracking['unit_price'] > 0) {
        $tracking['value'] = $tracking['unit_price'] * $people_for_calc;
    }

    if ($tracking['unit_price'] <= 0 || $tracking['value'] <= 0) {
        $meal_key = $tracking['meal'] ?? '';
        if (!empty($meal_key)) {
            $meal_config = rbf_get_meal_config($meal_key);
            if ($meal_config && $tracking['unit_price'] <= 0) {
                $tracking['unit_price'] = (float) $meal_config['price'];
            }

            if ($tracking['unit_price'] <= 0) {
                $options = rbf_get_settings();
                $meal_for_value = ($meal_key === 'brunch') ? 'pranzo' : $meal_key;
                $option_price = (float) ($options['valore_' . $meal_for_value] ?? 0);
                if ($option_price > 0) {
                    $tracking['unit_price'] = $option_price;
                }
            }
        }

        if ($tracking['unit_price'] > 0 && $tracking['value'] <= 0) {
            $tracking['value'] = $tracking['unit_price'] * $people_for_calc;
        }
    }

    $tracking['value'] = max(0, (float) ($tracking['value'] ?? 0));
    $tracking['unit_price'] = max(0, (float) ($tracking['unit_price'] ?? 0));

    return $tracking;
}

/**
 * Handle booking form submission
 */
add_action('admin_post_rbf_submit_booking', 'rbf_handle_booking_submission');
add_action('admin_post_nopriv_rbf_submit_booking', 'rbf_handle_booking_submission');
function rbf_handle_booking_submission() {
    $redirect_url = wp_get_referer() ? strtok(wp_get_referer(), '?') : home_url();
    $anchor = '#rbf-message-anchor';

    $data = rbf_validate_request($_POST, $redirect_url, $anchor);
    if (!$data) {
        return;
    }

    $data = rbf_check_availability($data, $redirect_url, $anchor);
    if (!$data) {
        return;
    }

    $context = rbf_create_booking_post($data, $redirect_url, $anchor);
    if (!$context) {
        return;
    }

    rbf_send_notifications($data, $context);

    $success_args = [
        'rbf_success'   => '1',
        'booking_id'    => $context['post_id'],
        'booking_token' => $context['tracking_token'],
    ];
    rbf_handle_success('Booking created successfully', $success_args, add_query_arg($success_args, $redirect_url . $anchor));
}

