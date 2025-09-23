<?php
/**
 * Third-party integrations for FP Prenotazioni Ristorante
 * (Tracking, Brevo automation only - no WordPress emails)
 * 
 * @package FP_Prenotazioni_Ristorante_PRO
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Print the GA4 gtag snippet only once.
 */
function rbf_print_gtag($ga4_id, $send_page_view = true) {
    static $printed = false;
    if ($printed || empty($ga4_id)) {
        return;
    }
    $printed = true;
    ?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr($ga4_id); ?>"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({event:'consent', 'analytics_storage':'denied'});
        function gtag(){ dataLayer.push(arguments); }
        gtag('js', new Date());
        gtag('config', '<?php echo esc_js($ga4_id); ?>', { 'send_page_view': <?php echo $send_page_view ? 'true' : 'false'; ?> });
    </script>
    <?php
}

/**
 * Print the GTM snippet only once.
 */
function rbf_print_gtm($gtm_id, $init_datalayer = true) {
    static $printed = false;
    if ($printed || empty($gtm_id)) {
        return;
    }
    $printed = true;
    if ($init_datalayer) {
        ?>
        <script>
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push({event:'consent', 'analytics_storage':'denied'});
        </script>
        <?php
    }
    ?>
    <script>
        (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?"&l="+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','<?php echo esc_js($gtm_id); ?>');
    </script>
    <?php
}

/**
 * Update analytics consent state in dataLayer.
 *
 * @param bool $granted Whether analytics storage is granted.
 */
function rbf_update_consent($granted) {
    $status = $granted ? 'granted' : 'denied';
    echo "<script>window.dataLayer.push({event:'consent', 'analytics_storage':'{$status}'});</script>";
}
add_action('rbf_update_consent', 'rbf_update_consent');

/**
 * Output tracking scripts in head
 */
add_action('wp_head','rbf_add_tracking_scripts_to_head');
function rbf_add_tracking_scripts_to_head() {
    $options = rbf_get_settings();
    $ga4_id = $options['ga4_id'] ?? '';
    $gtm_id = $options['gtm_id'] ?? '';
    $gtm_hybrid = ($options['gtm_hybrid'] ?? '') === 'yes';
    $meta_pixel_id = $options['meta_pixel_id'] ?? '';

    $send_page_view = !$gtm_hybrid;

    if ($gtm_hybrid && $gtm_id) {
        rbf_print_gtag($ga4_id, $send_page_view);
        rbf_print_gtm($gtm_id, false);
    } elseif ($gtm_id) {
        rbf_print_gtm($gtm_id);
    } elseif ($ga4_id) {
        rbf_print_gtag($ga4_id, $send_page_view);
    }

    if ($meta_pixel_id) { ?>
        <script>
            !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
            n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
            n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
            t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script',
            'https://connect.facebook.net/en_US/fbevents.js');
            fbq('init','<?php echo esc_js($meta_pixel_id); ?>');
            fbq('track','PageView');
        </script>
    <?php }
}

/**
 * Output noscript tracking tag after body opening
 */
add_action('wp_body_open','rbf_add_tracking_noscript');
function rbf_add_tracking_noscript() {
    $options = rbf_get_settings();
    $gtm_id = $options['gtm_id'] ?? '';
    if ($gtm_id) {
        ?>
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo esc_attr($gtm_id); ?>" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
        <?php
    }
}

/**
 * Add purchase tracking script in footer when booking succeeds
 */
add_action('wp_footer','rbf_add_booking_tracking_script');
function rbf_add_booking_tracking_script() {
    $options = rbf_get_settings();
    $meta_pixel_id = $options['meta_pixel_id'] ?? '';

    if (!isset($_GET['rbf_success'], $_GET['booking_id'], $_GET['booking_token'])) {
        return;
    }

    $booking_id = absint($_GET['booking_id']);
    if (!$booking_id) {
        return;
    }

    $raw_token = wp_unslash($_GET['booking_token']);
    $booking_token = is_string($raw_token) ? sanitize_text_field($raw_token) : '';
    if ($booking_token === '') {
        return;
    }

    $transient_key = 'rbf_booking_data_' . $booking_id;
    $transient_data = get_transient($transient_key);

    $stored_hash = (string) get_post_meta($booking_id, 'rbf_tracking_token', true);
    $incoming_hash = rbf_hash_tracking_token($booking_token);
    $hash_matches = ($stored_hash !== '' && $incoming_hash !== '' && hash_equals($stored_hash, $incoming_hash));

    $meta = null;
    $tracking_data = null;

    if (is_array($transient_data)) {
        $expected_token = isset($transient_data['tracking_token']) ? (string) $transient_data['tracking_token'] : '';
        if ($expected_token !== '' && hash_equals($expected_token, $booking_token)) {
            if ($stored_hash !== '' && !$hash_matches) {
                return;
            }

            unset($transient_data['tracking_token']);
            $meta = get_post_meta($booking_id);
            $tracking_data = rbf_build_booking_tracking_data($booking_id, $transient_data, $meta);
        }
    }

    if ($tracking_data === null) {
        if (!$hash_matches) {
            return;
        }

        if ($meta === null) {
            $meta = get_post_meta($booking_id);
        }

        $tracking_data = rbf_build_booking_tracking_data($booking_id, [], $meta);
    }

    delete_transient($transient_key);
    rbf_clear_booking_tracking_token($booking_id);

    if (!is_array($tracking_data) || empty($tracking_data)) {
        return;
    }

    if ($meta === null) {
        $meta = get_post_meta($booking_id);
    }

    $transaction_id = $tracking_data['transaction_id'] ?? ('rbf_' . $tracking_data['id']);
    $value = $tracking_data['value'] ?? 0;
    $currency = $tracking_data['currency'] ?? 'EUR';
    $meal = $tracking_data['meal'] ?? 'pranzo';
    $people = $tracking_data['people'] ?? 0;
    $bucket = $tracking_data['bucket'] ?? 'organic';
    $eventId = $tracking_data['event_id'] ?? $transaction_id;
    $unit_price_value = $tracking_data['unit_price'] ?? 0;
    $unitPrice = $unit_price_value > 0 ? $unit_price_value : null;

    $gclid = $tracking_data['gclid'] ?? '';
    $fbclid = $tracking_data['fbclid'] ?? '';

    // Use centralized normalization function with priority gclid > fbclid > organic
    $bucketStd = rbf_normalize_bucket($gclid, $fbclid);

    $email_meta = $meta['rbf_email'][0] ?? '';
    $phone_meta = $meta['rbf_tel'][0] ?? '';
    $first_name_meta = $meta['rbf_nome'][0] ?? '';
    $last_name_meta = $meta['rbf_cognome'][0] ?? '';
    $booking_date_meta = $meta['rbf_data'][0] ?? '';
    $booking_time_meta = $meta['rbf_orario'][0] ?? '';

    $normalized_email = strtolower(trim((string) $email_meta));
    $customer_email_hash = $normalized_email !== '' ? hash('sha256', $normalized_email) : '';

    $customer_phone_clean = preg_replace('/[^\\d+]/', '', (string) $phone_meta);
    $customer_phone_hash = $customer_phone_clean !== '' ? hash('sha256', $customer_phone_clean) : '';

    $normalized_first_name = strtolower(trim((string) $first_name_meta));
    $customer_first_name_hash = $normalized_first_name !== '' ? hash('sha256', $normalized_first_name) : '';

    $normalized_last_name = strtolower(trim((string) $last_name_meta));
    $customer_last_name_hash = $normalized_last_name !== '' ? hash('sha256', $normalized_last_name) : '';

    $customer_hashes = array_filter([
        'customer_email' => $customer_email_hash,
        'customer_phone' => $customer_phone_hash,
        'customer_first_name' => $customer_first_name_hash,
        'customer_last_name' => $customer_last_name_hash,
    ]);

    $customer_conversion_hashes = array_filter([
        'email_address' => $customer_email_hash,
        'phone_number' => $customer_phone_hash,
        'first_name' => $customer_first_name_hash,
        'last_name' => $customer_last_name_hash,
    ]);
    ?>
    <script>
        (function() {
              var value = <?php echo json_encode($value); ?>;
              var currency = <?php echo json_encode($currency); ?>;
              var transaction_id = <?php echo json_encode($transaction_id); ?>;
              var meal = <?php echo json_encode($meal); ?>;
              var people = <?php echo json_encode($people); ?>;
              var bucket = <?php echo json_encode($bucket); ?>;
              var bucketStd = <?php echo json_encode($bucketStd); ?>;
              var eventId = <?php echo json_encode($eventId); ?>;
              var unitPrice = <?php echo json_encode($unitPrice); ?>;
              var isGtmHybrid = <?php echo json_encode($options['gtm_hybrid'] === 'yes'); ?>;
              var gtmId = <?php echo json_encode($options['gtm_id'] ?? ''); ?>;
              var customerData = <?php echo wp_json_encode((object) $customer_hashes); ?>;
              var customerConversionData = <?php echo wp_json_encode((object) $customer_conversion_hashes); ?>;

              function rbfTrackEvent(eventName, params, eventId, options) {
                options = options || {};
                window.dataLayer = window.dataLayer || [];
                
                // Enhanced params with deduplication data
                var enhancedParams = Object.assign({}, params, { 
                  event_id: eventId,
                  transaction_id: transaction_id,
                  deduplication_key: eventId
                });
                
                // Always push to dataLayer for GTM and other integrations
                var dataLayerEvent = Object.assign({ event: eventName }, enhancedParams, {
                  gtm_uniqueEventId: eventId // GTM-specific deduplication
                });
                window.dataLayer.push(dataLayerEvent);
                
                // Only send direct gtag events if not in hybrid mode or explicitly requested
                if ((!isGtmHybrid || options.forceGtag) && typeof gtag === 'function') {
                  gtag('event', eventName, enhancedParams);
                }
              }

              // Standard GA4 purchase event with enhanced conversions data
              var purchaseParams = {
                transaction_id: transaction_id,
                value: Number(value || 0),
                currency: currency,
                items: [{
                  item_id: 'booking_' + meal,
                  item_name: 'Prenotazione ' + meal,
                  category: 'booking',
                  quantity: Number(people || 0),
                  price: Number(unitPrice !== null ? unitPrice : (Number(value || 0) / Number(people || 1)))
                }],
                bucket: bucketStd,
                vertical: 'restaurant',
                unit_price: Number(unitPrice !== null ? unitPrice : (Number(value || 0) / Number(people || 1)))
              };

              // Enhanced conversion data for Google Ads (only when available)
              if (Object.keys(customerData).length > 0) {
                Object.assign(purchaseParams, customerData);
              }

              rbfTrackEvent('purchase', purchaseParams, eventId);

              // Custom restaurant booking event with detailed attribution data
              rbfTrackEvent('restaurant_booking', {
                transaction_id: transaction_id,
                value: Number(value || 0),
                currency: currency,
                bucket: bucketStd,          // standard (gads/fbads/organic)
                traffic_bucket: bucket,     // detailed (fborg/direct/other...)
                meal: meal,
                people: Number(people || 0),
                unit_price: Number(unitPrice !== null ? unitPrice : (Number(value || 0) / Number(people || 1))),
                vertical: 'restaurant',
                booking_date: '<?php echo esc_js($booking_date_meta); ?>',
                booking_time: '<?php echo esc_js($booking_time_meta); ?>'
              }, eventId);

              // Google Ads specific conversion tracking with enhanced data (only if not in GTM hybrid mode)
              if (!isGtmHybrid && bucketStd === 'gads' && typeof gtag === 'function') {
                var conversionParams = {
                  send_to: 'AW-CONVERSION_ID/CONVERSION_LABEL', // Replace with actual conversion ID
                  transaction_id: transaction_id,
                  value: Number(value || 0),
                  currency: currency,
                  event_id: eventId
                };

                if (Object.keys(customerConversionData).length > 0) {
                  conversionParams.customer_data = customerConversionData;
                }

                gtag('event', 'conversion', conversionParams);
              }

              <?php if ($meta_pixel_id) : ?>
              // Facebook Pixel tracking with deduplication
              if (typeof fbq === 'function') {
                // Browser-side tracking with deduplication
                fbq('track', 'Purchase', {
                  value: Number(value || 0), 
                  currency: currency, 
                  bucket: bucketStd, 
                  vertical: 'restaurant',
                  content_type: 'product',
                  content_name: 'Restaurant Booking',
                  content_category: 'booking'
                }, { 
                  eventID: eventId  // Deduplication with server-side
                });
              }
              
              // Send server-side Facebook Conversion API event
              <?php 
              // Add server-side Facebook CAPI call
              $meta_access_token = $options['meta_access_token'] ?? '';
              if (!empty($meta_access_token)) {
                  rbf_send_facebook_capi_event($booking_id, $meta_pixel_id, $meta_access_token, $eventId, [
                      'value' => $value,
                      'currency' => 'EUR',
                      'bucket' => $bucketStd,
                      'vertical' => 'restaurant'
                  ]);
              }
              ?>
              <?php endif; ?>
            })();
        </script>
        <?php
}

/**
 * Send admin notification email (to both restaurant and webmaster)
 */
function rbf_send_admin_notification_email($first_name, $last_name, $email, $date, $time, $people, $notes, $tel, $meal) {
    $options = rbf_get_settings();
    $restaurant_email = $options['notification_email'];
    $webmaster_email = $options['webmaster_email'];
    
    // Collect valid email addresses
    $recipients = [];
    if (!empty($restaurant_email) && is_email($restaurant_email)) {
        $recipients[] = $restaurant_email;
    }
    if (!empty($webmaster_email) && is_email($webmaster_email) && $webmaster_email !== $restaurant_email) {
        $recipients[] = $webmaster_email;
    }
    
    if (empty($recipients)) return;

    $site_name = wp_strip_all_tags(get_bloginfo('name'), true);
    $site_name_for_body = esc_html($site_name);
    
    // Escape all user input for email subject (prevent header injection)
    $safe_first_name = rbf_escape_for_email($first_name, 'subject');
    $safe_last_name = rbf_escape_for_email($last_name, 'subject');
    $subject = "Nuova Prenotazione dal Sito Web - {$safe_first_name} {$safe_last_name}";
    
    $date_obj = date_create($date);
    $formatted_date = date_format($date_obj, 'd/m/Y');
    $notes_display = empty($notes) ? 'Nessuna' : nl2br(rbf_escape_for_email($notes, 'html'));

    // Escape all user data for HTML context
    $safe_first_name_html = rbf_escape_for_email($first_name, 'html');
    $safe_last_name_html = rbf_escape_for_email($last_name, 'html');
    $safe_email_html = rbf_escape_for_email($email, 'html');
    $safe_tel_html = rbf_escape_for_email($tel, 'html');
    $safe_time_html = rbf_escape_for_email($time, 'html');
    $safe_meal_html = rbf_escape_for_email($meal, 'html');
    $safe_people_html = rbf_escape_for_email($people, 'html');

    $body = <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8">
<style>body{font-family:Arial,sans-serif;color:#333}.container{padding:20px;border:1px solid #ddd;max-width:600px;margin:auto}h2{color:#000}strong{color:#555}</style>
</head><body><div class="container">
<h2>Nuova Prenotazione da {$site_name_for_body}</h2>
<ul>
  <li><strong>Cliente:</strong> {$safe_first_name_html} {$safe_last_name_html}</li>
  <li><strong>Email:</strong> {$safe_email_html}</li>
  <li><strong>Telefono:</strong> {$safe_tel_html}</li>
  <li><strong>Data:</strong> {$formatted_date}</li>
  <li><strong>Orario:</strong> {$safe_time_html}</li>
  <li><strong>Pasto:</strong> {$safe_meal_html}</li>
  <li><strong>Persone:</strong> {$safe_people_html}</li>
  <li><strong>Note/Allergie:</strong> {$notes_display}</li>
</ul>
</div></body></html>
HTML;

    $headers = ['Content-Type: text/html; charset=UTF-8'];
    $from_domain = wp_parse_url(home_url(), PHP_URL_HOST);
    $from_domain = preg_replace('/^www\./', '', (string) $from_domain);
    $from_email = sanitize_email('noreply@' . $from_domain);
    $headers[] = 'From: ' . $site_name . ' <' . $from_email . '>';
    
    // Send to all recipients
    foreach ($recipients as $recipient) {
        wp_mail($recipient, $subject, $body, $headers);
    }
}

/**
 * Generate and offer ICS calendar file for booking
 */
function rbf_generate_booking_ics($first_name, $last_name, $email, $date, $time, $people, $notes, $meal) {
    $options = rbf_get_settings();
    $restaurant_name = get_bloginfo('name');
    
    // Prepare booking data for ICS generation
    $booking_data = [
        'date' => $date,
        'time' => $time,
        'summary' => "Prenotazione Ristorante - {$meal}",
        'description' => "Prenotazione presso {$restaurant_name}\\nNome: {$first_name} {$last_name}\\nPersone: {$people}\\nNote: {$notes}",
        'location' => $restaurant_name
    ];
    
    return rbf_generate_ics_content($booking_data);
}

/**
 * Trigger Brevo automation with enhanced list segmentation
 * 
 * List segmentation is determined by both form language and phone prefix:
 * - If phone prefix is Italian (+39) → Italian list (regardless of form language)
 * - If phone prefix is NOT Italian but form is in Italian → Italian list  
 * - If phone prefix is NOT Italian and form is in English → English list
 * 
 * @param string $lang The determined language for Brevo segmentation (already processed by booking handler)
 */
function rbf_trigger_brevo_automation($first_name, $last_name, $email, $date, $time, $people, $notes, $lang, $tel, $marketing, $meal) {
    $options = rbf_get_settings();
    $api_key = $options['brevo_api'] ?? '';
    // Note: $lang parameter already contains the segmentation result based on form language + phone prefix
    $list_id = $lang === 'en' ? ($options['brevo_list_en'] ?? '') : ($options['brevo_list_it'] ?? '');

    if (empty($api_key)) {
        $message = 'Brevo: API key non configurata.';
        rbf_handle_error($message, 'brevo_config');
        return [
            'success' => false,
            'error' => $message,
            'code' => 'missing_api_key',
        ];
    }

    if (empty($list_id)) {
        $message = "Brevo: ID lista non configurato per lingua {$lang}";
        rbf_handle_error($message, 'brevo_config');
        return [
            'success' => false,
            'error' => $message,
            'code' => 'missing_list_id',
        ];
    }

    $base_args = [
        'headers' => ['api-key' => $api_key, 'Content-Type' => 'application/json'],
        'timeout' => 10,
        'blocking' => true,
    ];

    // 1) Contact upsert: sempre in lista
    $contact_payload = [
        'email' => $email,
        'attributes' => [
            'FIRSTNAME' => $first_name,
            'LASTNAME' => $last_name,
            'WHATSAPP' => $tel,
            'PRENOTAZIONE_DATA' => $date,
            'PRENOTAZIONE_ORARIO' => $time,
            'PERSONE' => $people,
            'NOTE' => empty($notes) ? 'Nessuna' : $notes,
            'LINGUA' => $lang,
            'MARKETING_CONSENT' => ($marketing === 'yes')
        ],
        'listIds' => [intval($list_id)],
        'updateEnabled' => true,
    ];

    $contact_response = wp_remote_post(
        'https://api.brevo.com/v3/contacts',
        array_merge($base_args, ['body' => wp_json_encode($contact_payload)])
    );

    if (is_wp_error($contact_response)) {
        $message = 'Errore Brevo (upsert contatto): ' . $contact_response->get_error_message();
        rbf_handle_error($message, 'brevo_api');
        return [
            'success' => false,
            'error' => $message,
            'code' => 'contact_request_error',
            'step' => 'contact_upsert',
        ];
    }

    $contact_status = wp_remote_retrieve_response_code($contact_response);
    if ($contact_status < 200 || $contact_status >= 300) {
        $response_body = wp_remote_retrieve_body($contact_response);
        $message = "Errore Brevo (upsert contatto) - HTTP {$contact_status}: {$response_body}";
        rbf_handle_error($message, 'brevo_api');
        return [
            'success' => false,
            'error' => $message,
            'code' => 'contact_http_error',
            'step' => 'contact_upsert',
            'status_code' => $contact_status,
            'response_body' => $response_body,
        ];
    }

    // 2) Custom Event via /v3/events: sempre
    $event_payload = [
        'event_name' => 'booking_bistrot',
        'event_date' => gmdate('Y-m-d\TH:i:s\Z'),
        'identifiers' => ['email_id' => $email],
        'event_properties' => [
            'meal' => $meal, 'time' => $time, 'people' => $people, 'notes' => $notes,
            'language' => $lang, 'marketing_consent' => ($marketing === 'yes')
        ],
    ];

    $event_response = wp_remote_post(
        'https://api.brevo.com/v3/events',
        array_merge($base_args, ['body' => wp_json_encode($event_payload)])
    );

    if (is_wp_error($event_response)) {
        $message = 'Errore Brevo (evento booking_bistrot): ' . $event_response->get_error_message();
        rbf_handle_error($message, 'brevo_api');
        return [
            'success' => false,
            'error' => $message,
            'code' => 'event_request_error',
            'step' => 'event_trigger',
        ];
    }

    $event_status = wp_remote_retrieve_response_code($event_response);
    if ($event_status < 200 || $event_status >= 300) {
        $response_body = wp_remote_retrieve_body($event_response);
        $message = "Errore Brevo (evento booking_bistrot) - HTTP {$event_status}: {$response_body}";
        rbf_handle_error($message, 'brevo_api');
        return [
            'success' => false,
            'error' => $message,
            'code' => 'event_http_error',
            'step' => 'event_trigger',
            'status_code' => $event_status,
            'response_body' => $response_body,
        ];
    }

    return [
        'success' => true,
        'code' => 'brevo_automation_triggered',
        'contact_status' => $contact_status,
        'event_status' => $event_status,
    ];
}

/**
 * Send Facebook Conversion API event server-side for deduplication
 */
function rbf_send_facebook_capi_event($booking_id, $pixel_id, $access_token, $event_id, $event_data) {
    if (empty($pixel_id) || empty($access_token)) {
        return;
    }
    
    // Get booking data
    $email = get_post_meta($booking_id, 'rbf_email', true);
    $phone = get_post_meta($booking_id, 'rbf_tel', true);
    $first_name = get_post_meta($booking_id, 'rbf_nome', true);
    $last_name = get_post_meta($booking_id, 'rbf_cognome', true);
    
    // Prepare user data with hashing
    $user_data = [];
    if (!empty($email)) {
        $user_data['em'] = hash('sha256', strtolower(trim($email)));
    }
    if (!empty($phone)) {
        $user_data['ph'] = hash('sha256', preg_replace('/[^\d+]/', '', $phone));
    }
    if (!empty($first_name)) {
        $user_data['fn'] = hash('sha256', strtolower(trim($first_name)));
    }
    if (!empty($last_name)) {
        $user_data['ln'] = hash('sha256', strtolower(trim($last_name)));
    }
    
    // Add IP and user agent if available
    if (!empty($_SERVER['REMOTE_ADDR'])) {
        $user_data['client_ip_address'] = $_SERVER['REMOTE_ADDR'];
    }
    if (!empty($_SERVER['HTTP_USER_AGENT'])) {
        $user_data['client_user_agent'] = $_SERVER['HTTP_USER_AGENT'];
    }
    
    // Prepare event payload
    $event_payload = [
        'data' => [
            [
                'event_name' => 'Purchase',
                'event_time' => time(),
                'event_id' => $event_id,
                'action_source' => 'website',
                'user_data' => $user_data,
                'custom_data' => [
                    'value' => floatval($event_data['value']),
                    'currency' => $event_data['currency'],
                    'content_type' => 'product',
                    'content_name' => 'Restaurant Booking',
                    'content_category' => 'booking',
                    'custom_properties' => [
                        'bucket' => $event_data['bucket'],
                        'vertical' => $event_data['vertical']
                    ]
                ]
            ]
        ]
    ];
    
    // Send to Facebook Conversion API
    $response = wp_remote_post(
        "https://graph.facebook.com/v18.0/{$pixel_id}/events",
        [
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $access_token
            ],
            'body' => wp_json_encode($event_payload)
        ]
    );
    
    if (is_wp_error($response)) {
        rbf_handle_error('Facebook CAPI error: ' . $response->get_error_message(), 'facebook_capi');
    } else {
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code < 200 || $response_code >= 300) {
            $response_body = wp_remote_retrieve_body($response);
            rbf_handle_error("Facebook CAPI HTTP {$response_code}: {$response_body}", 'facebook_capi');
        }
    }
}