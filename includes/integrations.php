<?php
/**
 * Third-party integrations for Restaurant Booking Plugin
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
        function gtag(){ dataLayer.push(arguments); }
        gtag('js', new Date());
        gtag('config', '<?php echo esc_js($ga4_id); ?>', ['send_page_view' => <?php echo $send_page_view ? 'true' : 'false'; ?>]);
    </script>
    <?php
}

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
        ?>
        <script>
            (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','<?php echo esc_js($gtm_id); ?>');
        </script>
        <?php
    } elseif ($gtm_id) {
        ?>
        <script>window.dataLayer = window.dataLayer || [];</script>
        <script>
            (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','<?php echo esc_js($gtm_id); ?>');
        </script>
        <?php
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

    if (isset($_GET['rbf_success'], $_GET['booking_id']) && is_numeric($_GET['booking_id'])) {
        $booking_id = intval($_GET['booking_id']);
        $tracking_data = get_transient('rbf_booking_data_' . $booking_id);

        // Fallback se manca il transient: ricostruisci dai meta
        if (!$tracking_data || !is_array($tracking_data)) {
            // Get all meta in single call for performance
            $meta = get_post_meta($booking_id);
            $value = $meta['rbf_valore_tot'][0] ?? 0;
            $meal = $meta['rbf_orario'][0] ?? 'pranzo';
            $people = $meta['rbf_persone'][0] ?? 1;
            $bucket = $meta['rbf_source_bucket'][0] ?? 'organic';
            $gclid = $meta['rbf_gclid'][0] ?? '';
            $fbclid = $meta['rbf_fbclid'][0] ?? '';
            $tracking_data = [
                'id' => $booking_id,
                'value' => $value,
                'currency' => 'EUR',
                'meal' => $meal,
                'people' => $people,
                'bucket' => $bucket,
                'gclid' => $gclid,
                'fbclid' => $fbclid,
                'event_id' => 'rbf_' . $booking_id
            ];
        }

        $transaction_id = 'rbf_' . $tracking_data['id'];
        $value = $tracking_data['value'];
        $currency = $tracking_data['currency'];
        $meal = $tracking_data['meal'];
        $people = $tracking_data['people'];
        $bucket = $tracking_data['bucket'];
        $eventId = $tracking_data['event_id'];

        // Get gclid and fbclid for normalized bucket calculation
        $gclid = $tracking_data['gclid'] ?? '';
        $fbclid = $tracking_data['fbclid'] ?? '';
        
        // If not in transient data, fallback to meta
        if (empty($gclid) && empty($fbclid)) {
            $meta = get_post_meta($booking_id);
            $gclid = $meta['rbf_gclid'][0] ?? '';
            $fbclid = $meta['rbf_fbclid'][0] ?? '';
        }
        
        // Use centralized normalization function with priority gclid > fbclid > organic
        $bucketStd = rbf_normalize_bucket($gclid, $fbclid);
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

              function rbfTrackEvent(eventName, params) {
                window.dataLayer = window.dataLayer || [];
                // Ensure Google Ads required params exist in dataLayer
                var data = Object.assign({ event: eventName }, params);
                window.dataLayer.push(data);
                if (typeof gtag === 'function') {
                  gtag('event', eventName, params);
                }
              }

              rbfTrackEvent('purchase', {
                transaction_id: transaction_id,
                value: Number(value || 0),
                currency: currency,
                items: [{
                  item_id: 'booking_' + meal,
                  item_name: 'Prenotazione ' + meal,
                  category: 'booking',
                  quantity: Number(people || 0),
                  price: Number(value || 0) / Number(people || 1)
                }],
                bucket: bucketStd,
                vertical: 'restaurant'
              });

              // Evento custom con dettaglio ristorante
              rbfTrackEvent('restaurant_booking', {
                transaction_id: transaction_id,
                value: Number(value || 0),
                currency: currency,
                bucket: bucketStd,          // standard (gads/fbads/organic)
                traffic_bucket: bucket,     // dettaglio (fborg/direct/other...)
                meal: meal,
                people: Number(people || 0),
                vertical: 'restaurant'
              });

              <?php if ($meta_pixel_id) : ?>
              if (typeof fbq === 'function') {
                // Dedup con CAPI: stesso eventID + bucket standard lato browser
                fbq('track', 'Purchase',
                    { value: Number(value || 0), currency: currency, bucket: bucketStd, vertical: 'restaurant' },
                    { eventID: eventId }
                );
              }
              <?php endif; ?>
            })();
        </script>
        <?php
        delete_transient('rbf_booking_data_' . $booking_id);
    }
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
 * Trigger Brevo automation (simplified - only Brevo, no WordPress emails)
 */
function rbf_trigger_brevo_automation($first_name, $last_name, $email, $date, $time, $people, $notes, $lang, $tel, $marketing, $meal) {
    $options = rbf_get_settings();
    $api_key = $options['brevo_api'] ?? '';
    $list_id = $lang === 'en' ? ($options['brevo_list_en'] ?? '') : ($options['brevo_list_it'] ?? '');

    if (empty($api_key)) { rbf_handle_error('Brevo: API key non configurata.', 'brevo_config'); return; }
    if (empty($list_id)) { rbf_handle_error("Brevo: ID lista non configurato per lingua {$lang}", 'brevo_config'); return; }

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

    $response = wp_remote_post(
        'https://api.brevo.com/v3/contacts',
        array_merge($base_args, ['body' => wp_json_encode($contact_payload)])
    );
    
    if (is_wp_error($response)) {
        rbf_handle_error('Errore Brevo (upsert contatto): ' . $response->get_error_message(), 'brevo_api');
    } else {
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code < 200 || $response_code >= 300) {
            $response_body = wp_remote_retrieve_body($response);
            rbf_handle_error("Errore Brevo (upsert contatto) - HTTP {$response_code}: {$response_body}", 'brevo_api');
        }
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

    $response = wp_remote_post(
        'https://api.brevo.com/v3/events',
        array_merge($base_args, ['body' => wp_json_encode($event_payload)])
    );
    
    if (is_wp_error($response)) {
        rbf_handle_error('Errore Brevo (evento booking_bistrot): ' . $response->get_error_message(), 'brevo_api');
    } else {
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code < 200 || $response_code >= 300) {
            $response_body = wp_remote_retrieve_body($response);
            rbf_handle_error("Errore Brevo (evento booking_bistrot) - HTTP {$response_code}: {$response_body}", 'brevo_api');
        }
    }
}