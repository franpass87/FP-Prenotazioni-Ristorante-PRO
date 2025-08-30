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
 * Add tracking scripts to footer
 */
add_action('wp_footer','rbf_add_tracking_scripts_to_footer');
function rbf_add_tracking_scripts_to_footer() {
    $options = rbf_get_settings();
    $ga4_id = $options['ga4_id'] ?? '';
    $meta_pixel_id = $options['meta_pixel_id'] ?? '';

    if ($ga4_id) { ?>
        <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr($ga4_id); ?>"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){ dataLayer.push(arguments); }
            gtag('js', new Date());
            gtag('config', '<?php echo esc_js($ga4_id); ?>', { 'send_page_view': true });
        </script>
    <?php }

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

              <?php if ($ga4_id) : ?>
              if (typeof gtag === 'function') {
                gtag('event', 'purchase', {
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
                gtag('event', 'restaurant_booking', {
                  transaction_id: transaction_id,
                  value: Number(value || 0),
                  currency: currency,
                  bucket: bucketStd,          // standard (gads/fbads/organic)
                  traffic_bucket: bucket,     // dettaglio (fborg/direct/other...)
                  meal: meal,
                  people: Number(people || 0),
                  vertical: 'restaurant'
                });
              }
              <?php endif; ?>

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

    $site_name = get_bloginfo('name');
    $subject = "Nuova Prenotazione dal Sito Web - {$first_name} {$last_name}";
    $date_obj = date_create($date);
    $formatted_date = date_format($date_obj, 'd/m/Y');
    $notes_display = empty($notes) ? 'Nessuna' : nl2br(esc_html($notes));

    $body = <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8">
<style>body{font-family:Arial,sans-serif;color:#333}.container{padding:20px;border:1px solid #ddd;max-width:600px;margin:auto}h2{color:#000}strong{color:#555}</style>
</head><body><div class="container">
<h2>Nuova Prenotazione da {$site_name}</h2>
<ul>
  <li><strong>Cliente:</strong> {$first_name} {$last_name}</li>
  <li><strong>Email:</strong> {$email}</li>
  <li><strong>Telefono:</strong> {$tel}</li>
  <li><strong>Data:</strong> {$formatted_date}</li>
  <li><strong>Orario:</strong> {$time}</li>
  <li><strong>Pasto:</strong> {$meal}</li>
  <li><strong>Persone:</strong> {$people}</li>
  <li><strong>Note/Allergie:</strong> {$notes_display}</li>
</ul>
</div></body></html>
HTML;

    $headers = ['Content-Type: text/html; charset=UTF-8'];
    $from_email = 'noreply@' . preg_replace('/^www\./', '', $_SERVER['SERVER_NAME']);
    $headers[] = 'From: ' . $site_name . ' <' . $from_email . '>';
    
    // Send to all recipients
    foreach ($recipients as $recipient) {
        wp_mail($recipient, $subject, $body, $headers);
    }
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