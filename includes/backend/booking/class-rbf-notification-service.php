<?php
/**
 * Booking notification dispatcher.
 *
 * @package FP_Prenotazioni_Ristorante_PRO\Backend\Booking
 */

namespace RBF\Backend\Booking;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles email notifications and marketing integrations after a booking is stored.
 */
class NotificationService {
    /**
     * Trigger notifications and integrations.
     *
     * @param BookingContext $context Enriched booking context.
     * @return void
     */
    public function dispatch(BookingContext $context) {
        $data = $context->toArray();

        $post_id    = $data['post_id'];
        $valore_tot = $data['valore_tot'];
        $event_id   = $data['event_id'];
        $options    = $data['options'];

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
                $first_name,
                $last_name,
                $email,
                $date,
                $time,
                $people,
                $notes,
                $tel,
                $meal,
                $brevo_lang,
                $form_lang,
                $post_id,
                $sanitized_fields['rbf_special_type'] ?? '',
                $sanitized_fields['rbf_special_label'] ?? ''
            );
        }

        if (function_exists('rbf_send_customer_notification_with_failover')) {
            rbf_send_customer_notification_with_failover(
                $first_name,
                $last_name,
                $email,
                $date,
                $time,
                $people,
                $notes,
                $brevo_lang,
                $form_lang,
                $tel,
                $marketing,
                $meal,
                $post_id,
                $sanitized_fields['rbf_special_type'] ?? '',
                $sanitized_fields['rbf_special_label'] ?? ''
            );
        }

        if (!empty($options['meta_pixel_id']) && !empty($options['meta_access_token'])) {
            $meta_url = 'https://graph.facebook.com/v20.0/' . $options['meta_pixel_id'] . '/events?access_token=' . $options['meta_access_token'];
            $bucket_std = rbf_normalize_bucket($gclid, $fbclid);

            $meta_payload = [
                'data' => [[
                    'action_source' => 'website',
                    'event_name'    => 'Purchase',
                    'event_time'    => time(),
                    'event_id'      => (string) $event_id,
                    'user_data'     => [
                        'client_ip_address' => filter_var($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', FILTER_VALIDATE_IP) ?: '127.0.0.1',
                        'client_user_agent' => substr(sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'), 0, 250),
                    ],
                    'custom_data'   => [
                        'value'    => $valore_tot,
                        'currency' => 'EUR',
                        'bucket'   => $bucket_std,
                        'vertical' => 'restaurant',
                    ],
                ]],
            ];

            $response = wp_remote_post(
                $meta_url,
                [
                    'body'    => wp_json_encode($meta_payload),
                    'headers' => ['Content-Type' => 'application/json'],
                    'timeout' => 8,
                ]
            );

            if (is_wp_error($response)) {
                $error_code    = $response->get_error_code();
                $error_message = $response->get_error_message();
                rbf_handle_error('Meta CAPI Error - Booking ID: ' . $post_id . ', Error: ' . $error_message, 'meta_api');

                if (function_exists('rbf_record_tracking_event')) {
                    rbf_record_tracking_event('meta', 'Purchase', [
                        'status'    => 'error',
                        'transport' => 'server',
                        'code'      => (string) $error_code,
                        'message'   => substr($error_message, 0, 120),
                    ]);
                }

                if ($error_code === 'http_request_timeout') {
                    wp_mail(
                        get_option('admin_email'),
                        'RBF: Meta CAPI Timeout Warning',
                        'Timeout su chiamata Meta CAPI per prenotazione #' . $post_id . '. Valore: €' . $valore_tot
                    );
                }
            } else {
                $response_code = wp_remote_retrieve_response_code($response);
                if ($response_code < 200 || $response_code >= 300) {
                    $response_body = wp_remote_retrieve_body($response);
                    rbf_handle_error(
                        'Meta CAPI Error - Booking ID: ' . $post_id . ', HTTP ' . $response_code . ': ' . $response_body,
                        'meta_api'
                    );

                    if (function_exists('rbf_record_tracking_event')) {
                        rbf_record_tracking_event('meta', 'Purchase', [
                            'status'    => 'error',
                            'transport' => 'server',
                            'code'      => (string) $response_code,
                            'message'   => substr($response_body, 0, 120),
                        ]);
                    }
                } else {
                    if (function_exists('rbf_record_tracking_event')) {
                        rbf_record_tracking_event('meta', 'Purchase', [
                            'status'    => 'success',
                            'transport' => 'server',
                            'event_id'  => (string) $event_id,
                            'value'     => (string) $valore_tot,
                            'bucket'    => $bucket_std,
                        ]);
                    }
                }
            }
        }

        if (function_exists('rbf_track_booking_completion')) {
            $tracking_completion_data = [
                'id'             => $post_id,
                'value'          => $valore_tot,
                'currency'       => 'EUR',
                'meal'           => $meal,
                'people'         => $people,
                'bucket'         => $src['bucket'],
                'tracking_token' => $context->get('tracking_token', ''),
            ];
            rbf_track_booking_completion($post_id, $tracking_completion_data);
        }
    }
}
