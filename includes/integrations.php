<?php
/**
 * Third-party integrations for Restaurant Booking Plugin
 * (Tracking, Email notifications, Brevo automation)
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
            $meal   = get_post_meta($booking_id, 'rbf_orario', true);
            $people = (int) get_post_meta($booking_id, 'rbf_persone', true);
            $bucket = get_post_meta($booking_id, 'rbf_source_bucket', true) ?: 'direct';
            $val_per = 0.0;
            if ($meal) {
                $val_per = (float) ($options['valore_' . $meal] ?? 0);
            }
            $val_tot  = $val_per * max(0,$people);
            $event_id = 'rbf_' . $booking_id;
            $tracking_data = [
              'id'       => $booking_id,
              'value'    => $val_tot,
              'currency' => 'EUR',
              'meal'     => $meal ?: '',
              'people'   => max(0,$people),
              'bucket'   => $bucket,
              'event_id' => $event_id
            ];
            set_transient('rbf_booking_data_' . $booking_id, $tracking_data, 60 * 15);
        }

        $value = (float) $tracking_data['value'];
        $currency = esc_js($tracking_data['currency']);
        $transaction_id = esc_js($tracking_data['id']);
        $meal_js = esc_js($tracking_data['meal']);
        $people_js = (int) $tracking_data['people'];
        $bucket_js = esc_js($tracking_data['bucket']);
        $event_id_js = esc_js($tracking_data['event_id']); ?>
        <script>
            (function(){
              var value = <?php echo json_encode($value); ?>;
              var currency = <?php echo json_encode($currency); ?>;
              var transaction_id = <?php echo json_encode($transaction_id); ?>;
              var meal = <?php echo json_encode($meal_js); ?>;
              var people = <?php echo json_encode($people_js); ?>;
              var bucket = <?php echo json_encode($bucket_js); ?>; // es: gads, fbads, fborg, direct, other
              var eventId = <?php echo json_encode($event_id_js); ?>;

              // standardizza: tutto ciò che NON è gads/fbads => organic
              var bucketStd = (bucket === 'gads' || bucket === 'fbads') ? bucket : 'organic';

              <?php if ($ga4_id) : ?>
              if (typeof gtag === 'function') {
                // Ecommerce standard + bucket standard (per allineo con HIC)
                gtag('event', 'purchase', {
                  transaction_id: transaction_id,
                  value: Number(value || 0),
                  currency: currency,
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
 * Send admin notification email
 */
function rbf_send_admin_notification_email($first_name, $last_name, $email, $date, $time, $people, $notes, $tel, $meal) {
    $options = get_option('rbf_settings', rbf_get_default_settings());
    $to = $options['notification_email'] ?? 'info@villadianella.it';
    $cc = 'francesco.passeri@gmail.com';
    if (empty($to) || !is_email($to)) return;

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
    $headers[] = 'Cc: ' . $cc;
    wp_mail($to, $subject, $body, $headers);
}

/**
 * Trigger Brevo automation
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
}

/**
 * Handle booking status change notifications
 */
add_action('rbf_booking_status_changed', 'rbf_send_status_change_notification', 10, 4);
function rbf_send_status_change_notification($booking_id, $old_status, $new_status, $note) {
    $booking = get_post($booking_id);
    if (!$booking) return;
    
    $first_name = get_post_meta($booking_id, 'rbf_nome', true);
    $last_name = get_post_meta($booking_id, 'rbf_cognome', true);
    $email = get_post_meta($booking_id, 'rbf_email', true);
    $date = get_post_meta($booking_id, 'rbf_data', true);
    $time = get_post_meta($booking_id, 'rbf_time', true);
    $people = get_post_meta($booking_id, 'rbf_persone', true);
    $meal = get_post_meta($booking_id, 'rbf_orario', true);
    $lang = get_post_meta($booking_id, 'rbf_lang', true) ?: 'it';
    
    // Don't send notifications for pending status (initial creation)
    if ($new_status === 'pending') return;
    
    $statuses = rbf_get_booking_statuses();
    $status_label = $statuses[$new_status] ?? $new_status;
    
    $site_name = get_bloginfo('name');
    $booking_hash = get_post_meta($booking_id, 'rbf_booking_hash', true);
    
    // Prepare email content based on language and status
    if ($lang === 'en') {
        $subject = "Booking Status Update - #{$booking_id}";
        $greeting = "Dear {$first_name}";
        $status_text = "Your booking status has been updated to: <strong>{$status_label}</strong>";
        $booking_details = "Booking Details";
        $customer_label = "Customer";
        $date_label = "Date";
        $time_label = "Time";
        $people_label = "Guests";
        $meal_label = "Service";
        $thank_you = "Thank you for choosing {$site_name}!";
    } else {
        $subject = "Aggiornamento Stato Prenotazione - #{$booking_id}";
        $greeting = "Gentile {$first_name}";
        $status_text = "Lo stato della sua prenotazione è stato aggiornato a: <strong>{$status_label}</strong>";
        $booking_details = "Dettagli Prenotazione";
        $customer_label = "Cliente";
        $date_label = "Data";
        $time_label = "Orario";
        $people_label = "Persone";
        $meal_label = "Servizio";
        $thank_you = "Grazie per aver scelto {$site_name}!";
    }
    
    $formatted_date = date('d/m/Y', strtotime($date));
    $meal_display = ucfirst($meal);
    
    // Status-specific messages
    $status_message = '';
    switch ($new_status) {
        case 'confirmed':
            $status_message = $lang === 'en' 
                ? 'Your booking has been confirmed. We look forward to welcoming you!'
                : 'La sua prenotazione è stata confermata. Non vediamo l\'ora di accoglierla!';
            break;
        case 'completed':
            $status_message = $lang === 'en'
                ? 'Thank you for dining with us! We hope you enjoyed your experience.'
                : 'Grazie per aver cenato da noi! Speriamo che abbiate apprezzato l\'esperienza.';
            break;
        case 'cancelled':
            $status_message = $lang === 'en'
                ? 'Your booking has been cancelled. We hope to see you again soon.'
                : 'La sua prenotazione è stata cancellata. Speriamo di rivederla presto.';
            break;
    }
    
    // Management link text
    $manage_text = $lang === 'en' 
        ? 'You can view and manage your booking details using the link below:'
        : 'Puoi visualizzare e gestire la tua prenotazione utilizzando il link qui sotto:';
    $manage_button = $lang === 'en' ? 'Manage Booking' : 'Gestisci Prenotazione';
    
    // Generate management URL (assuming page with [customer_booking_management] shortcode exists)
    $management_url = home_url('/gestisci-prenotazione/?booking=' . $booking_hash);
    
    $body = <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8">
<style>
    body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; }
    .container { padding: 20px; border: 1px solid #ddd; max-width: 600px; margin: auto; border-radius: 8px; }
    .header { background-color: #f8f9fa; padding: 15px; border-radius: 8px 8px 0 0; margin: -20px -20px 20px -20px; }
    .status-badge { display: inline-block; padding: 8px 16px; border-radius: 20px; color: white; font-weight: bold; }
    .status-confirmed { background-color: #10b981; }
    .status-completed { background-color: #06b6d4; }
    .status-cancelled { background-color: #ef4444; }
    .booking-details { background-color: #f1f5f9; padding: 15px; border-radius: 6px; margin: 15px 0; }
    .detail-row { margin: 8px 0; }
    .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e7eb; font-size: 14px; color: #6b7280; }
    .manage-btn { display: inline-block; background-color: #3b82f6; color: white; text-decoration: none; padding: 12px 24px; border-radius: 6px; font-weight: bold; margin: 15px 0; }
</style>
</head><body>
<div class="container">
    <div class="header">
        <h2 style="margin: 0; color: #1f2937;">{$subject}</h2>
    </div>
    
    <p>{$greeting},</p>
    
    <p>{$status_text}</p>
    <span class="status-badge status-{$new_status}">{$status_label}</span>
    
    {$status_message ? "<p><em>{$status_message}</em></p>" : ""}
    
    <div class="booking-details">
        <h3 style="margin-top: 0; color: #374151;">{$booking_details}</h3>
        <div class="detail-row"><strong>{$customer_label}:</strong> {$first_name} {$last_name}</div>
        <div class="detail-row"><strong>{$date_label}:</strong> {$formatted_date}</div>
        <div class="detail-row"><strong>{$time_label}:</strong> {$time}</div>
        <div class="detail-row"><strong>{$people_label}:</strong> {$people}</div>
        <div class="detail-row"><strong>{$meal_label}:</strong> {$meal_display}</div>
    </div>
    
    <p>{$thank_you}</p>
    
    <p>{$manage_text}</p>
    <a href="{$management_url}" class="manage-btn">{$manage_button}</a>
    
    <div class="footer">
        <p>Booking ID: #{$booking_id} | Booking Code: {$booking_hash}</p>
        <p>{$site_name}</p>
    </div>
</div>
</body></html>
HTML;

    $headers = ['Content-Type: text/html; charset=UTF-8'];
    $from_email = 'noreply@' . preg_replace('/^www\./', '', $_SERVER['SERVER_NAME']);
    $headers[] = 'From: ' . $site_name . ' <' . $from_email . '>';
    
    wp_mail($email, $subject, $body, $headers);
}
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