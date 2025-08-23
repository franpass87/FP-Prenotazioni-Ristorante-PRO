<?php
/**
 * Notification Manager Class for Restaurant Booking Plugin
 * Centralizes all notification and integration logic
 */

if (!defined('ABSPATH')) {
    exit;
}

class RBF_Notification_Manager {
    
    /**
     * Send all booking notifications (admin email + integrations)
     */
    public static function send_booking_notifications($booking_data, $type = 'new') {
        $first_name = $booking_data['first_name'] ?? '';
        $last_name = $booking_data['last_name'] ?? '';
        $email = $booking_data['email'] ?? '';
        $date = $booking_data['date'] ?? '';
        $time = $booking_data['time'] ?? '';
        $people = $booking_data['people'] ?? 1;
        $notes = $booking_data['notes'] ?? '';
        $tel = $booking_data['tel'] ?? '';
        $meal = $booking_data['meal'] ?? '';
        $language = $booking_data['language'] ?? 'it';
        $marketing = $booking_data['marketing'] ?? 'no';
        
        $notifications_sent = [];
        
        // Admin notification email (webmaster notification)
        if (function_exists('rbf_send_admin_notification_email')) {
            try {
                rbf_send_admin_notification_email($first_name, $last_name, $email, $date, $time, $people, $notes, $tel, $meal);
                $notifications_sent['admin_email'] = true;
            } catch (Exception $e) {
                if (WP_DEBUG) {
                    error_log("RBF Admin Email Error: " . $e->getMessage());
                }
                $notifications_sent['admin_email'] = false;
            }
        }
        
        // Brevo integration (always - lista + evento)
        if (function_exists('rbf_trigger_brevo_automation')) {
            try {
                rbf_trigger_brevo_automation($first_name, $last_name, $email, $date, $time, $people, $notes, $language, $tel, $marketing, $meal);
                $notifications_sent['brevo'] = true;
            } catch (Exception $e) {
                if (WP_DEBUG) {
                    error_log("RBF Brevo Integration Error: " . $e->getMessage());
                }
                $notifications_sent['brevo'] = false;
            }
        }
        
        return $notifications_sent;
    }
    
    /**
     * Trigger tracking integrations (Meta CAPI, GA4, etc.)
     */
    public static function trigger_tracking_integrations($booking_data) {
        $options = rbf_get_settings();
        $post_id = $booking_data['booking_id'] ?? 0;
        $valore_tot = $booking_data['value'] ?? 0;
        $event_id = $booking_data['event_id'] ?? 'rbf_' . $post_id;
        $bucket = $booking_data['bucket'] ?? 'direct';
        
        $integrations_triggered = [];
        
        // Meta CAPI server-side (dedup with event_id) + bucket standard
        if (!empty($options['meta_pixel_id']) && !empty($options['meta_access_token'])) {
            try {
                $meta_url = "https://graph.facebook.com/v20.0/{$options['meta_pixel_id']}/events?access_token={$options['meta_access_token']}";
                
                // Standardize bucket: everything that is NOT gads/fbads => organic
                $standardized_bucket = in_array($bucket, ['gads', 'fbads'], true) ? $bucket : 'organic';
                
                $meta_data = [
                    'data' => [[
                        'event_name' => 'Purchase',
                        'event_time' => time(),
                        'event_id' => $event_id,
                        'user_data' => [
                            'em' => [hash('sha256', strtolower(trim($booking_data['email'] ?? '')))]
                        ],
                        'custom_data' => [
                            'currency' => 'EUR',
                            'value' => $valore_tot,
                            'content_name' => 'Restaurant Booking',
                            'content_category' => $booking_data['meal'] ?? 'booking',
                            'num_items' => intval($booking_data['people'] ?? 1),
                            'custom_parameters' => ['traffic_source' => $standardized_bucket]
                        ]
                    ]]
                ];
                
                $response = wp_remote_post($meta_url, [
                    'method' => 'POST',
                    'timeout' => 15,
                    'headers' => ['Content-Type' => 'application/json'],
                    'body' => wp_json_encode($meta_data)
                ]);
                
                if (is_wp_error($response)) {
                    if (WP_DEBUG) {
                        error_log("RBF Meta CAPI Error: " . $response->get_error_message());
                    }
                    
                    // Notify admin of critical API failures
                    if ($response->get_error_code() === 'http_request_timeout') {
                        wp_mail(
                            get_option('admin_email'),
                            'RBF: Meta CAPI Timeout Warning',
                            "Timeout su chiamata Meta CAPI per prenotazione #{$post_id}. Valore: €{$valore_tot}"
                        );
                    }
                    $integrations_triggered['meta_capi'] = false;
                } else {
                    $integrations_triggered['meta_capi'] = true;
                }
            } catch (Exception $e) {
                if (WP_DEBUG) {
                    error_log("RBF Meta CAPI Exception: " . $e->getMessage());
                }
                $integrations_triggered['meta_capi'] = false;
            }
        }
        
        // GA4 server-side tracking could be added here in the future
        
        return $integrations_triggered;
    }
    
    /**
     * Create tracking transient for frontend JavaScript
     */
    public static function create_tracking_transient($booking_data) {
        $post_id = $booking_data['booking_id'] ?? 0;
        if (!$post_id) return false;
        
        $tracking_data = [
            'id' => $post_id,
            'value' => $booking_data['value'] ?? 0,
            'currency' => 'EUR',
            'meal' => $booking_data['meal'] ?? '',
            'people' => $booking_data['people'] ?? 1,
            'bucket' => $booking_data['bucket'] ?? 'direct',
            'event_id' => $booking_data['event_id'] ?? 'rbf_' . $post_id
        ];
        
        // Store transient for 15 minutes (frontend tracking pickup)
        return set_transient('rbf_booking_data_' . $post_id, $tracking_data, 60 * 15);
    }
    
    /**
     * Send notification for booking status change
     */
    public static function send_status_change_notification($booking_id, $old_status, $new_status) {
        // Get booking data
        $booking_data = [
            'first_name' => get_post_meta($booking_id, 'rbf_nome', true),
            'last_name' => get_post_meta($booking_id, 'rbf_cognome', true),
            'email' => get_post_meta($booking_id, 'rbf_email', true),
            'date' => get_post_meta($booking_id, 'rbf_data', true),
            'time' => get_post_meta($booking_id, 'rbf_orario', true),
            'people' => get_post_meta($booking_id, 'rbf_persone', true),
            'notes' => get_post_meta($booking_id, 'rbf_allergie', true),
            'tel' => get_post_meta($booking_id, 'rbf_tel', true),
            'meal' => get_post_meta($booking_id, 'rbf_servizio', true),
            'language' => get_post_meta($booking_id, 'rbf_language', true) ?: 'it',
            'marketing' => get_post_meta($booking_id, 'rbf_marketing', true) ?: 'no'
        ];
        
        // Send notifications with context about status change
        return self::send_booking_notifications($booking_data, 'status_change');
    }
    
    /**
     * Log notification results for debugging
     */
    private static function log_notification_results($results, $context = 'booking') {
        if (WP_DEBUG) {
            $success_count = count(array_filter($results));
            $total_count = count($results);
            error_log("RBF Notifications [{$context}]: {$success_count}/{$total_count} successful");
            
            foreach ($results as $service => $success) {
                if (!$success) {
                    error_log("RBF Notification Failed: {$service}");
                }
            }
        }
    }
}