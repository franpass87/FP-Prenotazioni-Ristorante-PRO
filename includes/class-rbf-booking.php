<?php
/**
 * Booking processing functionality for Restaurant Booking Plugin
 *
 * @package RBF
 * @since 9.3.2
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Booking processing class
 */
class RBF_Booking {

    /**
     * Constructor
     */
    public function __construct() {
        $this->init();
    }

    /**
     * Initialize booking functionality
     */
    private function init() {
        add_action('admin_post_rbf_submit_booking', array($this, 'handle_booking_submission'));
        add_action('admin_post_nopriv_rbf_submit_booking', array($this, 'handle_booking_submission'));
    }

    /**
     * Handle booking form submission
     */
    public function handle_booking_submission() {
        $redirect_url = wp_get_referer() ? strtok(wp_get_referer(), '?') : home_url();
        $anchor = '#rbf-message-anchor';

        // Verify nonce
        if (!isset($_POST['rbf_nonce']) || !wp_verify_nonce($_POST['rbf_nonce'], 'rbf_booking')) {
            wp_safe_redirect(add_query_arg('rbf_error', urlencode(RBF_Utils::translate_string('Errore di sicurezza.')), $redirect_url . $anchor)); 
            exit;
        }

        // Validate required fields
        $required = ['rbf_meal','rbf_data','rbf_orario','rbf_persone','rbf_nome','rbf_cognome','rbf_email','rbf_tel','rbf_privacy'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                wp_safe_redirect(add_query_arg('rbf_error', urlencode(RBF_Utils::translate_string('Tutti i campi sono obbligatori, inclusa l\'accettazione della privacy policy.')), $redirect_url . $anchor)); 
                exit;
            }
        }

        // Sanitize input data
        $booking_data = $this->sanitize_booking_data($_POST);

        // Validate time format
        if (strpos($booking_data['time_data'], '|') === false) {
            wp_safe_redirect(add_query_arg('rbf_error', urlencode(RBF_Utils::translate_string('Orario non valido.')), $redirect_url . $anchor)); 
            exit;
        }

        // Parse time data
        list($slot, $time) = explode('|', $booking_data['time_data']);

        // Validate email
        if (!$booking_data['email']) {
            wp_safe_redirect(add_query_arg('rbf_error', urlencode(RBF_Utils::translate_string('Indirizzo email non valido.')), $redirect_url . $anchor)); 
            exit;
        }

        // Check capacity
        $frontend = RBF_Plugin::get_instance()->get_component('frontend');
        $remaining_capacity = $frontend ? $frontend->get_remaining_capacity($booking_data['date'], $slot) : 0;
        
        if ($remaining_capacity < $booking_data['people']) {
            $error_msg = sprintf(RBF_Utils::translate_string('Spiacenti, non ci sono abbastanza posti. Rimasti: %d'), $remaining_capacity);
            wp_safe_redirect(add_query_arg('rbf_error', urlencode($error_msg), $redirect_url . $anchor)); 
            exit;
        }

        // Detect traffic source
        $source_data = $this->detect_source($booking_data);

        // Create booking post
        $post_id = $this->create_booking_post($booking_data, $slot, $time, $source_data);

        if (is_wp_error($post_id)) {
            wp_safe_redirect(add_query_arg('rbf_error', urlencode(RBF_Utils::translate_string('Errore nel salvataggio.')), $redirect_url . $anchor)); 
            exit;
        }

        // Clear capacity cache
        delete_transient('rbf_avail_' . $booking_data['date'] . '_' . $slot);

        // Set up tracking data
        $this->setup_tracking_data($post_id, $booking_data, $source_data);

        // Send notifications
        $this->send_notifications($booking_data, $slot, $time);

        // Process integrations (Meta CAPI)
        $this->process_integrations($post_id, $booking_data, $source_data);

        // Redirect to success page
        $success_args = ['rbf_success' => '1', 'booking_id' => $post_id];
        wp_safe_redirect(add_query_arg($success_args, $redirect_url . $anchor)); 
        exit;
    }

    /**
     * Sanitize booking form data
     * 
     * @param array $data Raw POST data
     * @return array Sanitized data
     */
    private function sanitize_booking_data($data) {
        return [
            'meal' => sanitize_text_field($data['rbf_meal']),
            'date' => sanitize_text_field($data['rbf_data']),
            'time_data' => sanitize_text_field($data['rbf_orario']),
            'people' => intval($data['rbf_persone']),
            'first_name' => sanitize_text_field($data['rbf_nome']),
            'last_name' => sanitize_text_field($data['rbf_cognome']),
            'email' => sanitize_email($data['rbf_email']),
            'tel' => sanitize_text_field($data['rbf_tel']),
            'notes' => sanitize_textarea_field($data['rbf_allergie'] ?? ''),
            'lang' => sanitize_text_field($data['rbf_lang'] ?? 'it'),
            'privacy' => (isset($data['rbf_privacy']) && $data['rbf_privacy'] === 'yes') ? 'yes' : 'no',
            'marketing' => (isset($data['rbf_marketing']) && $data['rbf_marketing'] === 'yes') ? 'yes' : 'no',
            // UTM and tracking data
            'utm_source' => sanitize_text_field($data['rbf_utm_source'] ?? ''),
            'utm_medium' => sanitize_text_field($data['rbf_utm_medium'] ?? ''),
            'utm_campaign' => sanitize_text_field($data['rbf_utm_campaign'] ?? ''),
            'gclid' => sanitize_text_field($data['rbf_gclid'] ?? ''),
            'fbclid' => sanitize_text_field($data['rbf_fbclid'] ?? ''),
            'referrer' => sanitize_text_field($data['rbf_referrer'] ?? ''),
        ];
    }

    /**
     * Detect traffic source from booking data
     * 
     * @param array $data Booking data
     * @return array Source classification
     */
    private function detect_source($data) {
        $utm_source   = strtolower(trim($data['utm_source']));
        $utm_medium   = strtolower(trim($data['utm_medium']));
        $utm_campaign = trim($data['utm_campaign']);
        $gclid        = trim($data['gclid']);
        $fbclid       = trim($data['fbclid']);
        $referrer     = strtolower(trim($data['referrer']));

        // Google Ads (paid)
        if ($gclid || ($utm_source === 'google' && in_array($utm_medium, ['cpc','paid','ppc','sem'], true))) {
            return ['bucket'=>'gads','source'=>'google','medium'=>$utm_medium ?: 'cpc','campaign'=>$utm_campaign];
        }

        // Meta Ads (paid)
        if ($fbclid || (in_array($utm_source, ['facebook','meta','instagram'], true) && in_array($utm_medium, ['cpc','paid','ppc','ads'], true))) {
            return ['bucket'=>'fbads','source'=>$utm_source ?: 'facebook','medium'=>$utm_medium ?: 'paid','campaign'=>$utm_campaign];
        }

        // Facebook/Instagram organic
        if ((strpos($referrer, 'facebook.') !== false || strpos($referrer, 'instagram.') !== false) ||
            (in_array($utm_source, ['facebook','meta','instagram'], true) && ($utm_medium === '' || in_array($utm_medium, ['social','organic'], true)))) {
            return ['bucket'=>'fborg','source'=>$utm_source ?: 'facebook','medium'=>$utm_medium ?: 'social','campaign'=>$utm_campaign];
        }

        // Direct
        if ($referrer === '' && $utm_source === '' && $utm_medium === '' && $utm_campaign === '' && !$gclid && !$fbclid) {
            return ['bucket'=>'direct','source'=>'direct','medium'=>'none','campaign'=>''];
        }

        // Other sources (referral/organic)
        if ($utm_source || $utm_medium) {
            return ['bucket'=>'other','source'=>$utm_source ?: 'unknown','medium'=>$utm_medium ?: 'organic','campaign'=>$utm_campaign];
        }
        if ($referrer) {
            $host = parse_url($referrer, PHP_URL_HOST);
            return ['bucket'=>'other','source'=>$host ?: 'referral','medium'=>'referral','campaign'=>''];
        }

        return ['bucket'=>'direct','source'=>'direct','medium'=>'none','campaign'=>''];
    }

    /**
     * Create booking post in database
     * 
     * @param array $data Booking data
     * @param string $slot Meal slot
     * @param string $time Time
     * @param array $source_data Source classification
     * @return int|WP_Error Post ID or error
     */
    private function create_booking_post($data, $slot, $time, $source_data) {
        $title = ucfirst($data['meal']) . " per {$data['first_name']} {$data['last_name']} - {$data['date']} {$time}";

        return wp_insert_post([
            'post_type' => 'rbf_booking',
            'post_title' => $title,
            'post_status' => 'publish',
            'meta_input' => [
                'rbf_data' => $data['date'],
                'rbf_orario' => $slot,
                'rbf_time' => $time,
                'rbf_persone' => $data['people'],
                'rbf_nome' => $data['first_name'],
                'rbf_cognome' => $data['last_name'],
                'rbf_email' => $data['email'],
                'rbf_tel' => $data['tel'],
                'rbf_allergie' => $data['notes'],
                'rbf_lang' => $data['lang'],
                'rbf_privacy' => $data['privacy'],
                'rbf_marketing' => $data['marketing'],
                // Source data
                'rbf_source_bucket' => $source_data['bucket'],
                'rbf_source'        => $source_data['source'],
                'rbf_medium'        => $source_data['medium'],
                'rbf_campaign'      => $source_data['campaign'],
                'rbf_gclid'         => $data['gclid'],
                'rbf_fbclid'        => $data['fbclid'],
                'rbf_referrer'      => $data['referrer'],
            ],
        ]);
    }

    /**
     * Set up tracking data for analytics
     * 
     * @param int $post_id Booking post ID
     * @param array $data Booking data
     * @param array $source_data Source classification
     */
    private function setup_tracking_data($post_id, $data, $source_data) {
        $options = get_option('rbf_settings', RBF_Utils::get_default_settings());
        $value_per_person = (float) ($options['valore_' . $data['meal']] ?? 0);
        $total_value = $value_per_person * $data['people'];
        $event_id = 'rbf_' . $post_id;

        // Store tracking data in transient for footer tracking script
        set_transient('rbf_booking_data_' . $post_id, [
            'id'       => $post_id,
            'value'    => $total_value,
            'currency' => 'EUR',
            'meal'     => $data['meal'],
            'people'   => $data['people'],
            'bucket'   => $source_data['bucket'],
            'event_id' => $event_id
        ], 60 * 15); // 15 minutes
    }

    /**
     * Send email notifications
     * 
     * @param array $data Booking data
     * @param string $slot Meal slot
     * @param string $time Time
     */
    private function send_notifications($data, $slot, $time) {
        $integrations = RBF_Plugin::get_instance()->get_component('integrations');
        if ($integrations) {
            $integrations->send_admin_notification_email(
                $data['first_name'], 
                $data['last_name'], 
                $data['email'], 
                $data['date'], 
                $time, 
                $data['people'], 
                $data['notes'], 
                $data['tel'], 
                $data['meal']
            );

            $integrations->trigger_brevo_automation(
                $data['first_name'], 
                $data['last_name'], 
                $data['email'], 
                $data['date'], 
                $time, 
                $data['people'], 
                $data['notes'], 
                $data['lang'], 
                $data['tel'], 
                $data['marketing'], 
                $data['meal']
            );
        }
    }

    /**
     * Process external integrations (Meta CAPI)
     * 
     * @param int $post_id Booking post ID
     * @param array $data Booking data
     * @param array $source_data Source classification
     */
    private function process_integrations($post_id, $data, $source_data) {
        $options = get_option('rbf_settings', RBF_Utils::get_default_settings());
        
        // Meta CAPI server-side tracking
        if (!empty($options['meta_pixel_id']) && !empty($options['meta_access_token'])) {
            $this->send_meta_capi_event($post_id, $data, $source_data, $options);
        }
    }

    /**
     * Send Meta Conversion API event
     * 
     * @param int $post_id Booking post ID
     * @param array $data Booking data
     * @param array $source_data Source classification
     * @param array $options Plugin options
     */
    private function send_meta_capi_event($post_id, $data, $source_data, $options) {
        $value_per_person = (float) ($options['valore_' . $data['meal']] ?? 0);
        $total_value = $value_per_person * $data['people'];
        $event_id = 'rbf_' . $post_id;
        
        // Standardize bucket (everything except gads/fbads becomes organic)
        $bucket_std = ($source_data['bucket'] === 'gads' || $source_data['bucket'] === 'fbads') ? $source_data['bucket'] : 'organic';

        $meta_url = "https://graph.facebook.com/v20.0/{$options['meta_pixel_id']}/events?access_token={$options['meta_access_token']}";
        
        $payload = [
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
                    'value'    => $total_value,
                    'currency' => 'EUR',
                    'bucket'   => $bucket_std
                ]
            ]]
        ];

        wp_remote_post($meta_url, [
            'body' => wp_json_encode($payload),
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 8
        ]);
    }
}