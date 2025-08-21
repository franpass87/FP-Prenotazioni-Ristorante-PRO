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
    $options = get_option('rbf_settings', rbf_get_default_settings());
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

    if (isset($_GET['rbf_success'], $_GET['booking_id'])) {
        $booking_id = intval($_GET['booking_id']);
        $tracking_data = get_transient('rbf_booking_data_' . $booking_id);

        // Fallback se manca il transient: ricostruisci dai meta
        if (!$tracking_data || !is_array($tracking_data)) {
            $value = get_post_meta($booking_id, 'rbf_valore_tot', true) ?: 0;
            $meal = get_post_meta($booking_id, 'rbf_orario', true) ?: 'pranzo';
            $people = get_post_meta($booking_id, 'rbf_persone', true) ?: 1;
            $bucket = get_post_meta($booking_id, 'rbf_source_bucket', true) ?: 'organic';
            $tracking_data = [
                'id' => $booking_id,
                'value' => $value,
                'currency' => 'EUR',
                'meal' => $meal,
                'people' => $people,
                'bucket' => $bucket,
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

        // Bucket standard per dedup
        $bucketStd = ($bucket === 'gads' || $bucket === 'fbads') ? $bucket : 'organic';
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
                  bucket: bucketStd
                });
                // Evento custom con dettaglio ristorante
                gtag('event', 'restaurant_booking', {
                  transaction_id: transaction_id,
                  value: Number(value || 0),
                  currency: currency,
                  bucket: bucketStd,          // standard (gads/fbads/organic)
                  traffic_bucket: bucket,     // dettaglio (fborg/direct/other...)
                  meal: meal,
                  people: Number(people || 0)
                });
              }
              <?php endif; ?>

              <?php if ($meta_pixel_id) : ?>
              if (typeof fbq === 'function') {
                // Dedup con CAPI: stesso eventID + bucket standard lato browser
                fbq('track', 'Purchase',
                    { value: Number(value || 0), currency: currency, bucket: bucketStd },
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
 * Trigger Brevo automation (simplified - only Brevo, no WordPress emails)
 */
function rbf_trigger_brevo_automation($first_name, $last_name, $email, $date, $time, $people, $notes, $lang, $tel, $marketing, $meal) {
    $options = get_option('rbf_settings', rbf_get_default_settings());
    $api_key = $options['brevo_api'] ?? '';
    $list_id = $lang === 'en' ? ($options['brevo_list_en'] ?? '') : ($options['brevo_list_it'] ?? '');

    if (empty($api_key)) { error_log('Brevo: API key non configurata.'); return; }
    if (empty($list_id)) { error_log('Brevo: ID lista non configurato per lingua ' . $lang); return; }

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
    if (is_wp_error($response)) error_log('Errore Brevo (upsert contatto): '.$response->get_error_message());

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
    if (is_wp_error($response)) error_log('Errore Brevo (evento booking_bistrot): '.$response->get_error_message());
}