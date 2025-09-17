<?php
/**
 * Email Failover System for FP Prenotazioni Ristorante
 * Provides reliable email delivery with Brevo fallback to wp_mail
 * 
 * @package FP_Prenotazioni_Ristorante_PRO
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Email Failover Service Class
 * Handles email delivery with primary/fallback provider logic
 */
class RBF_Email_Failover_Service {
    
    private $notification_queue = [];
    private $log_table = 'rbf_email_notifications';
    
    public function __construct() {
        $this->maybe_create_log_table();
    }
    
    /**
     * Create email notification log table if it doesn't exist
     */
    private function maybe_create_log_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . $this->log_table;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            booking_id bigint(20) DEFAULT NULL,
            notification_type varchar(50) NOT NULL,
            recipient_email varchar(255) NOT NULL,
            subject varchar(500) NOT NULL,
            provider_used varchar(50) NOT NULL,
            attempt_number tinyint(3) DEFAULT 1,
            status enum('pending','success','failed','fallback_success') NOT NULL DEFAULT 'pending',
            error_message text,
            attempted_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            metadata longtext,
            PRIMARY KEY (id),
            KEY booking_id (booking_id),
            KEY status (status),
            KEY attempted_at (attempted_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Send notification with failover logic
     * 
     * @param array $notification_data Notification details
     * @return array Result with success status and details
     */
    public function send_notification($notification_data) {
        $log_id = $this->log_notification_attempt($notification_data);
        
        // Try primary provider (Brevo) first
        $brevo_result = $this->try_brevo_notification($notification_data);
        
        if (!empty($brevo_result['success'])) {
            $this->update_notification_log($log_id, 'success', 'brevo', null);
            $brevo_result['log_id'] = $log_id;
            return $brevo_result;
        }

        // Log Brevo failure and try fallback
        $brevo_error = $brevo_result['error'] ?? 'Brevo notification failed for an unknown reason';
        $brevo_details = $brevo_result['details'] ?? null;

        $this->update_notification_log($log_id, 'failed', 'brevo', $brevo_error);

        // Try fallback provider (wp_mail)
        $fallback_result = $this->try_fallback_notification($notification_data);

        if (!empty($fallback_result['success'])) {
            $this->update_notification_log($log_id, 'fallback_success', 'wp_mail', 'Brevo failure: ' . $brevo_error, 2);
            $fallback_result['brevo_error'] = $brevo_error;
            if ($brevo_details !== null) {
                $fallback_result['brevo_details'] = $brevo_details;
            }
            $fallback_result['log_id'] = $log_id;
            return $fallback_result;
        }

        // Both providers failed
        $fallback_error = $fallback_result['error'] ?? 'Fallback provider failed for an unknown reason';
        $this->update_notification_log($log_id, 'failed', 'wp_mail', $fallback_error, 2);

        return [
            'success' => false,
            'error' => sprintf('Brevo failed: %s; fallback failed: %s', $brevo_error, $fallback_error),
            'brevo_error' => $brevo_error,
            'brevo_details' => $brevo_details,
            'fallback_error' => $fallback_error,
            'log_id' => $log_id
        ];
    }
    
    /**
     * Try sending notification via Brevo
     */
    private function try_brevo_notification($data) {
        $options = rbf_get_settings();
        $api_key = $options['brevo_api'] ?? '';
        
        if (empty($api_key)) {
            return [
                'success' => false,
                'error' => 'Brevo API key not configured',
                'provider' => 'brevo'
            ];
        }
        
        // For customer notifications, use existing Brevo automation
        if ($data['type'] === 'customer_notification') {
            return $this->send_brevo_customer_automation($data);
        }
        
        // For admin notifications, send via Brevo transactional email
        if ($data['type'] === 'admin_notification') {
            return $this->send_brevo_transactional_email($data);
        }
        
        return [
            'success' => false,
            'error' => 'Unknown notification type for Brevo',
            'provider' => 'brevo'
        ];
    }
    
    /**
     * Send customer notification via Brevo automation
     */
    private function send_brevo_customer_automation($data) {
        ob_start();

        try {
            $result = rbf_trigger_brevo_automation(
                $data['first_name'],
                $data['last_name'],
                $data['email'],
                $data['date'],
                $data['time'],
                $data['people'],
                $data['notes'],
                $data['lang'],
                $data['tel'],
                $data['marketing'],
                $data['meal']
            );
        } catch (Throwable $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'provider' => 'brevo'
            ];
        }

        if (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (is_array($result) && !empty($result['success'])) {
            return [
                'success' => true,
                'provider' => 'brevo',
                'method' => 'automation',
                'details' => $result
            ];
        }

        $error_message = 'Brevo automation returned an unexpected response.';
        $details = null;

        if (is_array($result)) {
            if (!empty($result['error'])) {
                $error_message = $result['error'];
            }
            $details = $result;
        } elseif (is_wp_error($result)) {
            $error_message = $result->get_error_message();
            $details = [
                'code' => $result->get_error_code(),
                'data' => $result->get_error_data(),
            ];
        }

        return [
            'success' => false,
            'error' => $error_message,
            'provider' => 'brevo',
            'details' => $details
        ];
    }
    
    /**
     * Send admin notification via Brevo transactional email
     */
    private function send_brevo_transactional_email($data) {
        $options = rbf_get_settings();
        $api_key = $options['brevo_api'] ?? '';
        
        if (empty($api_key)) {
            return [
                'success' => false,
                'error' => 'Brevo API key not configured',
                'provider' => 'brevo'
            ];
        }
        
        $recipients = [];
        if (!empty($data['restaurant_email']) && is_email($data['restaurant_email'])) {
            $recipients[] = ['email' => $data['restaurant_email']];
        }
        if (!empty($data['webmaster_email']) && is_email($data['webmaster_email']) && 
            $data['webmaster_email'] !== $data['restaurant_email']) {
            $recipients[] = ['email' => $data['webmaster_email']];
        }
        
        if (empty($recipients)) {
            return [
                'success' => false,
                'error' => 'No valid recipient emails',
                'provider' => 'brevo'
            ];
        }
        
        $site_name = wp_strip_all_tags(get_bloginfo('name'), true);
        
        $email_data = [
            'sender' => [
                'name' => $site_name,
                'email' => 'noreply@' . wp_parse_url(home_url(), PHP_URL_HOST)
            ],
            'to' => $recipients,
            'subject' => $data['subject'],
            'htmlContent' => $data['html_body']
        ];
        
        $response = wp_remote_post(
            'https://api.brevo.com/v3/smtp/email',
            [
                'headers' => [
                    'api-key' => $api_key,
                    'Content-Type' => 'application/json'
                ],
                'body' => wp_json_encode($email_data),
                'timeout' => 15,
                'blocking' => true
            ]
        );
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message(),
                'provider' => 'brevo'
            ];
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code < 200 || $response_code >= 300) {
            $response_body = wp_remote_retrieve_body($response);
            return [
                'success' => false,
                'error' => "HTTP {$response_code}: {$response_body}",
                'provider' => 'brevo'
            ];
        }
        
        return [
            'success' => true,
            'provider' => 'brevo',
            'method' => 'transactional'
        ];
    }
    
    /**
     * Try sending notification via fallback provider (wp_mail)
     */
    private function try_fallback_notification($data) {
        try {
            if ($data['type'] === 'customer_notification') {
                // For customer notifications, we can only log that Brevo failed
                // wp_mail doesn't have automation capabilities
                return [
                    'success' => false,
                    'error' => 'Customer automation not available via wp_mail',
                    'provider' => 'wp_mail'
                ];
            }
            
            if ($data['type'] === 'admin_notification') {
                return $this->send_wpmail_admin_notification($data);
            }
            
            return [
                'success' => false,
                'error' => 'Unknown notification type for wp_mail',
                'provider' => 'wp_mail'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'provider' => 'wp_mail'
            ];
        }
    }
    
    /**
     * Send admin notification via wp_mail
     */
    private function send_wpmail_admin_notification($data) {
        $recipients = [];
        if (!empty($data['restaurant_email']) && is_email($data['restaurant_email'])) {
            $recipients[] = $data['restaurant_email'];
        }
        if (!empty($data['webmaster_email']) && is_email($data['webmaster_email']) && 
            $data['webmaster_email'] !== $data['restaurant_email']) {
            $recipients[] = $data['webmaster_email'];
        }
        
        if (empty($recipients)) {
            return [
                'success' => false,
                'error' => 'No valid recipient emails',
                'provider' => 'wp_mail'
            ];
        }
        
        $site_name = wp_strip_all_tags(get_bloginfo('name'), true);
        $from_domain = wp_parse_url(home_url(), PHP_URL_HOST);
        $from_domain = preg_replace('/^www\./', '', (string) $from_domain);
        $from_email = sanitize_email('noreply@' . $from_domain);
        
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $site_name . ' <' . $from_email . '>'
        ];
        
        $success_count = 0;
        $errors = [];
        
        foreach ($recipients as $recipient) {
            $result = wp_mail($recipient, $data['subject'], $data['html_body'], $headers);
            if ($result) {
                $success_count++;
            } else {
                $errors[] = "Failed to send to {$recipient}";
            }
        }
        
        if ($success_count > 0) {
            return [
                'success' => true,
                'provider' => 'wp_mail',
                'sent_count' => $success_count,
                'total_recipients' => count($recipients)
            ];
        }
        
        return [
            'success' => false,
            'error' => implode('; ', $errors),
            'provider' => 'wp_mail'
        ];
    }
    
    /**
     * Log notification attempt
     */
    private function log_notification_attempt($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . $this->log_table;
        
        $result = $wpdb->insert(
            $table_name,
            [
                'booking_id' => $data['booking_id'] ?? null,
                'notification_type' => $data['type'],
                'recipient_email' => $data['email'] ?? $data['restaurant_email'] ?? '',
                'subject' => $data['subject'] ?? 'Restaurant booking notification',
                'provider_used' => 'brevo',
                'attempt_number' => 1,
                'status' => 'pending',
                'metadata' => wp_json_encode($data)
            ],
            ['%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s']
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update notification log
     */
    private function update_notification_log($log_id, $status, $provider, $error = null, $attempt = 1) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . $this->log_table;
        
        $update_data = [
            'status' => $status,
            'provider_used' => $provider,
            'attempt_number' => $attempt,
            'completed_at' => current_time('mysql')
        ];
        
        if ($error) {
            $update_data['error_message'] = $error;
        }
        
        $wpdb->update(
            $table_name,
            $update_data,
            ['id' => $log_id],
            ['%s', '%s', '%d', '%s', '%s'],
            ['%d']
        );
    }
    
    /**
     * Get notification logs for a booking
     */
    public function get_notification_logs($booking_id = null, $limit = 50) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . $this->log_table;
        
        $where = '';
        if ($booking_id) {
            $where = $wpdb->prepare(' WHERE booking_id = %d', $booking_id);
        }
        
        return $wpdb->get_results(
            "SELECT * FROM {$table_name}{$where} ORDER BY attempted_at DESC LIMIT {$limit}"
        );
    }
    
    /**
     * Get notification statistics
     */
    public function get_notification_stats($days = 7) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . $this->log_table;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                provider_used,
                status,
                COUNT(*) as count,
                DATE(attempted_at) as date
            FROM {$table_name} 
            WHERE attempted_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            GROUP BY provider_used, status, DATE(attempted_at)
            ORDER BY date DESC, provider_used, status",
            $days
        ));
    }
}

/**
 * Get the global email failover service instance
 */
function rbf_get_email_failover_service() {
    static $service = null;
    if ($service === null) {
        $service = new RBF_Email_Failover_Service();
    }
    return $service;
}

/**
 * Send customer notification with failover
 */
function rbf_send_customer_notification_with_failover($first_name, $last_name, $email, $date, $time, $people, $notes, $lang, $tel, $marketing, $meal, $booking_id = null, $special_type = '', $special_label = '') {
    $service = rbf_get_email_failover_service();
    
    $notification_data = [
        'type' => 'customer_notification',
        'booking_id' => $booking_id,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'email' => $email,
        'date' => $date,
        'time' => $time,
        'people' => $people,
        'notes' => $notes,
        'lang' => $lang,
        'tel' => $tel,
        'marketing' => $marketing,
        'meal' => $meal,
        'special_type' => $special_type,
        'special_label' => $special_label
    ];
    
    return $service->send_notification($notification_data);
}

/**
 * Send admin notification with failover
 */
function rbf_send_admin_notification_with_failover($first_name, $last_name, $email, $date, $time, $people, $notes, $tel, $meal, $booking_id = null, $special_type = '', $special_label = '') {
    $options = rbf_get_settings();
    $restaurant_email = $options['notification_email'];
    $webmaster_email = $options['webmaster_email'];
    
    $site_name = wp_strip_all_tags(get_bloginfo('name'), true);
    $site_name_for_body = esc_html($site_name);
    
    // Escape all user input for email subject (prevent header injection)
    $safe_first_name = rbf_escape_for_email($first_name, 'subject');
    $safe_last_name = rbf_escape_for_email($last_name, 'subject');
    
    // Modify subject for special occasions
    $subject = "Nuova Prenotazione dal Sito Web - {$safe_first_name} {$safe_last_name}";
    if (!empty($special_label)) {
        $safe_special_label = rbf_escape_for_email($special_label, 'subject');
        $subject = "🎉 Prenotazione Speciale ({$safe_special_label}) - {$safe_first_name} {$safe_last_name}";
    }
    
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
    
    // Prepare special occasion section
    $special_section = '';
    if (!empty($special_label)) {
        $safe_special_label_html = rbf_escape_for_email($special_label, 'html');
        $special_section = "<div style='background:#fff3cd;border:2px solid #ffeaa7;padding:15px;margin:15px 0;border-radius:8px;'>" .
                          "<strong style='color:#856404;font-size:16px;'>🎉 PRENOTAZIONE SPECIALE:</strong> " .
                          "<span style='color:#856404;font-weight:bold;'>{$safe_special_label_html}</span></div>";
    }

    $email_title = !empty($special_label) ? "Prenotazione Speciale da {$site_name_for_body}" : "Nuova Prenotazione da {$site_name_for_body}";

    $html_body = <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8">
<style>body{font-family:Arial,sans-serif;color:#333}.container{padding:20px;border:1px solid #ddd;max-width:600px;margin:auto}h2{color:#000}strong{color:#555}</style>
</head><body><div class="container">
<h2>{$email_title}</h2>
{$special_section}
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
    
    $service = rbf_get_email_failover_service();
    
    $notification_data = [
        'type' => 'admin_notification',
        'booking_id' => $booking_id,
        'restaurant_email' => $restaurant_email,
        'webmaster_email' => $webmaster_email,
        'subject' => $subject,
        'html_body' => $html_body,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'email' => $email,
        'date' => $date,
        'time' => $time,
        'people' => $people,
        'notes' => $notes,
        'tel' => $tel,
        'meal' => $meal,
        'special_type' => $special_type,
        'special_label' => $special_label
    ];
    
    return $service->send_notification($notification_data);
}