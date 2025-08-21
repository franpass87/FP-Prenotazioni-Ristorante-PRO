<?php
/**
 * External integrations for Restaurant Booking Plugin
 *
 * @package RBF
 * @since 9.3.2
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * External integrations class (GA4, Meta Pixel, Brevo, Email)
 */
class RBF_Integrations {

    /**
     * Constructor
     */
    public function __construct() {
        $this->init();
    }

    /**
     * Initialize integrations
     */
    private function init() {
        add_action('wp_footer', array($this, 'add_tracking_scripts_to_footer'));
    }

    /**
     * Add tracking scripts to footer
     */
    public function add_tracking_scripts_to_footer() {
        $options = get_option('rbf_settings', RBF_Utils::get_default_settings());
        $ga4_id = $options['ga4_id'] ?? '';
        $meta_pixel_id = $options['meta_pixel_id'] ?? '';

        // Add GA4 tracking
        if ($ga4_id) {
            $this->render_ga4_tracking($ga4_id);
        }

        // Add Meta Pixel tracking
        if ($meta_pixel_id) {
            $this->render_meta_pixel_tracking($meta_pixel_id);
        }

        // Add booking success tracking if present
        if (isset($_GET['rbf_success'], $_GET['booking_id'])) {
            $this->render_booking_success_tracking(intval($_GET['booking_id']), $ga4_id, $meta_pixel_id);
        }
    }

    /**
     * Render GA4 tracking script
     * 
     * @param string $ga4_id GA4 measurement ID
     */
    private function render_ga4_tracking($ga4_id) {
        ?>
        <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr($ga4_id); ?>"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){ dataLayer.push(arguments); }
            gtag('js', new Date());
            gtag('config', '<?php echo esc_js($ga4_id); ?>', { 'send_page_view': true });
        </script>
        <?php
    }

    /**
     * Render Meta Pixel tracking script
     * 
     * @param string $meta_pixel_id Meta Pixel ID
     */
    private function render_meta_pixel_tracking($meta_pixel_id) {
        ?>
        <script>
            !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
            n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
            n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
            t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script',
            'https://connect.facebook.net/en_US/fbevents.js');
            fbq('init','<?php echo esc_js($meta_pixel_id); ?>');
            fbq('track','PageView');
        </script>
        <?php
    }

    /**
     * Render booking success tracking script
     * 
     * @param int $booking_id Booking ID
     * @param string $ga4_id GA4 measurement ID
     * @param string $meta_pixel_id Meta Pixel ID
     */
    private function render_booking_success_tracking($booking_id, $ga4_id, $meta_pixel_id) {
        $tracking_data = get_transient('rbf_booking_data_' . $booking_id);

        // Fallback: reconstruct tracking data from post meta if transient expired
        if (!$tracking_data || !is_array($tracking_data)) {
            $tracking_data = $this->reconstruct_tracking_data($booking_id);
        }

        $value = (float) $tracking_data['value'];
        $currency = esc_js($tracking_data['currency']);
        $transaction_id = esc_js($tracking_data['id']);
        $meal = esc_js($tracking_data['meal']);
        $people = (int) $tracking_data['people'];
        $bucket = esc_js($tracking_data['bucket']);
        $event_id = esc_js($tracking_data['event_id']);
        ?>
        <script>
            (function(){
              var value = <?php echo json_encode($value); ?>;
              var currency = <?php echo json_encode($currency); ?>;
              var transaction_id = <?php echo json_encode($transaction_id); ?>;
              var meal = <?php echo json_encode($meal); ?>;
              var people = <?php echo json_encode($people); ?>;
              var bucket = <?php echo json_encode($bucket); ?>; // e.g.: gads, fbads, fborg, direct, other
              var eventId = <?php echo json_encode($event_id); ?>;

              // Standardize bucket: everything except gads/fbads becomes organic
              var bucketStd = (bucket === 'gads' || bucket === 'fbads') ? bucket : 'organic';

              <?php if ($ga4_id) : ?>
              if (typeof gtag === 'function') {
                // Standard ecommerce event
                gtag('event', 'purchase', {
                  transaction_id: transaction_id,
                  value: Number(value || 0),
                  currency: currency,
                  bucket: bucketStd
                });
                // Custom restaurant booking event with details
                gtag('event', 'restaurant_booking', {
                  transaction_id: transaction_id,
                  value: Number(value || 0),
                  currency: currency,
                  bucket: bucketStd,          // standard (gads/fbads/organic)
                  traffic_bucket: bucket,     // detailed (fborg/direct/other...)
                  meal: meal,
                  people: Number(people || 0)
                });
              }
              <?php endif; ?>

              <?php if ($meta_pixel_id) : ?>
              if (typeof fbq === 'function') {
                // Browser-side tracking with event deduplication
                fbq('track', 'Purchase',
                    { value: Number(value || 0), currency: currency, bucket: bucketStd },
                    { eventID: eventId }
                );
              }
              <?php endif; ?>
            })();
        </script>
        <?php
        
        // Clean up transient after use
        delete_transient('rbf_booking_data_' . $booking_id);
    }

    /**
     * Reconstruct tracking data from post meta (fallback)
     * 
     * @param int $booking_id Booking ID
     * @return array Tracking data
     */
    private function reconstruct_tracking_data($booking_id) {
        $options = get_option('rbf_settings', RBF_Utils::get_default_settings());
        $meal = get_post_meta($booking_id, 'rbf_orario', true);
        $people = (int) get_post_meta($booking_id, 'rbf_persone', true);
        $bucket = get_post_meta($booking_id, 'rbf_source_bucket', true) ?: 'direct';
        
        $value_per_person = 0.0;
        if ($meal) {
            $value_per_person = (float) ($options['valore_' . $meal] ?? 0);
        }
        
        $total_value = $value_per_person * max(0, $people);
        $event_id = 'rbf_' . $booking_id;
        
        $tracking_data = [
            'id'       => $booking_id,
            'value'    => $total_value,
            'currency' => 'EUR',
            'meal'     => $meal ?: '',
            'people'   => max(0, $people),
            'bucket'   => $bucket,
            'event_id' => $event_id
        ];
        
        // Re-store in transient
        set_transient('rbf_booking_data_' . $booking_id, $tracking_data, 60 * 15);
        
        return $tracking_data;
    }

    /**
     * Send admin notification email
     * 
     * @param string $first_name Customer first name
     * @param string $last_name Customer last name
     * @param string $email Customer email
     * @param string $date Booking date
     * @param string $time Booking time
     * @param int $people Number of people
     * @param string $notes Customer notes
     * @param string $tel Customer phone
     * @param string $meal Meal type
     */
    public function send_admin_notification_email($first_name, $last_name, $email, $date, $time, $people, $notes, $tel, $meal) {
        $options = get_option('rbf_settings', RBF_Utils::get_default_settings());
        $to = $options['notification_email'] ?? 'info@villadianella.it';
        $cc = 'francesco.passeri@gmail.com';
        
        if (empty($to) || !is_email($to)) {
            return;
        }

        $site_name = get_bloginfo('name');
        $subject = "Nuova Prenotazione dal Sito Web - {$first_name} {$last_name}";
        
        $date_obj = date_create($date);
        $formatted_date = date_format($date_obj, 'd/m/Y');
        $notes_display = empty($notes) ? 'Nessuna' : nl2br(esc_html($notes));

        $body = $this->get_email_template($site_name, $first_name, $last_name, $email, $tel, $formatted_date, $time, $meal, $people, $notes_display);

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $from_email = 'noreply@' . preg_replace('/^www\./', '', $_SERVER['SERVER_NAME']);
        $headers[] = 'From: ' . $site_name . ' <' . $from_email . '>';
        $headers[] = 'Cc: ' . $cc;
        
        wp_mail($to, $subject, $body, $headers);
    }

    /**
     * Get email template
     * 
     * @param string $site_name Site name
     * @param string $first_name Customer first name
     * @param string $last_name Customer last name
     * @param string $email Customer email
     * @param string $tel Customer phone
     * @param string $formatted_date Formatted date
     * @param string $time Time
     * @param string $meal Meal type
     * @param int $people Number of people
     * @param string $notes_display Formatted notes
     * @return string HTML email template
     */
    private function get_email_template($site_name, $first_name, $last_name, $email, $tel, $formatted_date, $time, $meal, $people, $notes_display) {
        return <<<HTML
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
    }

    /**
     * Trigger Brevo automation
     * 
     * @param string $first_name Customer first name
     * @param string $last_name Customer last name
     * @param string $email Customer email
     * @param string $date Booking date
     * @param string $time Booking time
     * @param int $people Number of people
     * @param string $notes Customer notes
     * @param string $lang Language
     * @param string $tel Customer phone
     * @param string $marketing Marketing consent
     * @param string $meal Meal type
     */
    public function trigger_brevo_automation($first_name, $last_name, $email, $date, $time, $people, $notes, $lang, $tel, $marketing, $meal) {
        $options = get_option('rbf_settings', RBF_Utils::get_default_settings());
        $api_key = $options['brevo_api'] ?? '';
        $list_id = $lang === 'en' ? ($options['brevo_list_en'] ?? '') : ($options['brevo_list_it'] ?? '');

        if (empty($api_key)) {
            error_log('Brevo: API key non configurata.');
            return;
        }
        
        if (empty($list_id)) {
            error_log('Brevo: ID lista non configurato per lingua ' . $lang);
            return;
        }

        $base_args = [
            'headers' => ['api-key' => $api_key, 'Content-Type' => 'application/json'],
            'timeout' => 10,
            'blocking' => true,
        ];

        // 1) Upsert contact to list
        $this->upsert_brevo_contact($base_args, $email, $first_name, $last_name, $tel, $date, $time, $people, $notes, $lang, $marketing, $list_id);

        // 2) Send custom event
        $this->send_brevo_custom_event($base_args, $email, $meal, $time, $people, $notes, $lang, $marketing);
    }

    /**
     * Upsert contact in Brevo
     * 
     * @param array $base_args Base request arguments
     * @param string $email Email
     * @param string $first_name First name
     * @param string $last_name Last name
     * @param string $tel Phone
     * @param string $date Date
     * @param string $time Time
     * @param int $people People
     * @param string $notes Notes
     * @param string $lang Language
     * @param string $marketing Marketing consent
     * @param int $list_id List ID
     */
    private function upsert_brevo_contact($base_args, $email, $first_name, $last_name, $tel, $date, $time, $people, $notes, $lang, $marketing, $list_id) {
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
            error_log('Errore Brevo (upsert contatto): ' . $response->get_error_message());
        }
    }

    /**
     * Send custom event to Brevo
     * 
     * @param array $base_args Base request arguments
     * @param string $email Email
     * @param string $meal Meal
     * @param string $time Time
     * @param int $people People
     * @param string $notes Notes
     * @param string $lang Language
     * @param string $marketing Marketing consent
     */
    private function send_brevo_custom_event($base_args, $email, $meal, $time, $people, $notes, $lang, $marketing) {
        $event_payload = [
            'event_name' => 'booking_bistrot',
            'event_date' => gmdate('Y-m-d\TH:i:s\Z'),
            'identifiers' => ['email_id' => $email],
            'event_properties' => [
                'meal' => $meal, 
                'time' => $time, 
                'people' => $people, 
                'notes' => $notes,
                'language' => $lang, 
                'marketing_consent' => ($marketing === 'yes')
            ],
        ];

        $response = wp_remote_post(
            'https://api.brevo.com/v3/events',
            array_merge($base_args, ['body' => wp_json_encode($event_payload)])
        );
        
        if (is_wp_error($response)) {
            error_log('Errore Brevo (evento booking_bistrot): ' . $response->get_error_message());
        }
    }
}