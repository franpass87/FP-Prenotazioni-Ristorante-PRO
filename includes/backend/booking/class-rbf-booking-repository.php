<?php
/**
 * Booking persistence layer.
 *
 * @package FP_Prenotazioni_Ristorante_PRO\Backend\Booking
 */

namespace RBF\Backend\Booking;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles booking post creation and metadata persistence.
 */
class BookingRepository {
    /**
     * Persist the booking and return an enriched context.
     *
     * @param BookingContext $context      Booking context with reservation data.
     * @param string         $redirect_url Redirect destination on failure.
     * @param string         $anchor       Anchor used for feedback messages.
     * @return BookingContext|null
     */
    public function create(BookingContext $context, $redirect_url, $anchor) {
        $data             = $context->toArray();
        $sanitized_fields = $data['sanitized_fields'];
        $meal             = $data['meal'];
        $date             = $data['date'];
        $slot             = $data['slot'];
        $time             = $data['time'];
        $people           = $data['people'];
        $first_name       = $data['first_name'];
        $last_name        = $data['last_name'];
        $email            = $data['email'];
        $tel              = $data['tel'];
        $notes            = $data['notes'];
        $lang             = $data['lang'];
        $country_code     = $data['country_code'];
        $brevo_lang       = $data['brevo_lang'];
        $privacy          = $data['privacy'];
        $marketing        = $data['marketing'];
        $src              = $data['src'];
        $gclid            = $data['gclid'];
        $fbclid           = $data['fbclid'];
        $referrer         = $data['referrer'];
        $booking_result   = $data['booking_result'];
        $booking_status   = $data['booking_status'];

        $options = rbf_get_settings();

        $tracking_token = wp_generate_password(20, false, false);
        $tracking_token_hash = rbf_hash_tracking_token($tracking_token);

        $meal_config = rbf_get_meal_config($meal);
        if ($meal_config) {
            $valore_pp = (float) $meal_config['price'];
        } else {
            $meal_for_value = ($meal === 'brunch') ? 'pranzo' : $meal;
            $valore_pp = (float) ($options['valore_' . $meal_for_value] ?? 0);
        }

        $valore_tot = $valore_pp * $people;

        $meta_input = [
            'rbf_data'          => $date,
            'rbf_meal'          => $meal,
            'rbf_orario'        => $time,
            'rbf_time'          => $time,
            'rbf_persone'       => $people,
            'rbf_nome'          => $first_name,
            'rbf_cognome'       => $last_name,
            'rbf_email'         => $email,
            'rbf_tel'           => $tel,
            'rbf_tel_prefix'    => $data['tel_prefix'],
            'rbf_tel_number'    => $data['tel_number'],
            'rbf_allergie'      => $notes,
            'rbf_lang'          => $lang,
            'rbf_country_code'  => $country_code,
            'rbf_brevo_lang'    => $brevo_lang,
            'rbf_privacy'       => $privacy,
            'rbf_marketing'     => $marketing,
            'rbf_special_type'  => $sanitized_fields['rbf_special_type'] ?? '',
            'rbf_special_label' => $sanitized_fields['rbf_special_label'] ?? '',
            'rbf_source_bucket' => $src['bucket'],
            'rbf_source'        => $src['source'],
            'rbf_medium'        => $src['medium'],
            'rbf_campaign'      => $src['campaign'],
            'rbf_gclid'         => $gclid,
            'rbf_fbclid'        => $fbclid,
            'rbf_referrer'      => $referrer,
            'rbf_booking_status'  => $booking_status,
            'rbf_booking_created' => current_time('Y-m-d H:i:s'),
            'rbf_booking_hash'    => wp_generate_password(16, false, false),
            'rbf_tracking_token'  => $tracking_token_hash,
            'rbf_valore_pp'       => $valore_pp,
            'rbf_valore_tot'      => $valore_tot,
        ];

        $post_id = wp_insert_post([
            'post_type'   => 'rbf_booking',
            'post_title'  => ucfirst($meal) . " per {$first_name} {$last_name} - {$date} {$time}",
            'post_status' => 'publish',
            'meta_input'  => $meta_input,
        ]);

        if (is_wp_error($post_id)) {
            rbf_release_slot_capacity($date, $slot, $people);
            rbf_handle_error(rbf_translate_string('Errore nel salvataggio.'), 'database_error', $redirect_url . $anchor);
            return null;
        }

        update_post_meta($post_id, 'rbf_slot_version', $booking_result['version']);
        update_post_meta($post_id, 'rbf_booking_attempt', $booking_result['attempt']);

        $table_assignment = rbf_assign_tables_first_fit($people, $date, $time, $meal);
        if ($table_assignment) {
            rbf_save_table_assignment($post_id, $table_assignment);

            update_post_meta($post_id, 'rbf_table_assignment_type', $table_assignment['type']);
            update_post_meta($post_id, 'rbf_assigned_tables', $table_assignment['total_capacity']);

            if ($table_assignment['type'] === 'joined' && isset($table_assignment['group_id'])) {
                update_post_meta($post_id, 'rbf_table_group_id', $table_assignment['group_id']);
            }
        }

        rbf_store_booking_tracking_token($post_id, $tracking_token);

        $booking_context = array_merge(
            $data,
            [
                'post_id'          => $post_id,
                'table_assignment' => $table_assignment,
                'tracking_token'   => $tracking_token,
            ]
        );

        /**
         * Fires immediately after a booking post has been created.
         *
         * @param int   $post_id         The ID of the booking post.
         * @param array $booking_context Booking context data.
         */
        do_action('rbf_booking_created', $post_id, $booking_context);

        delete_transient('rbf_avail_' . $date . '_' . $slot);

        $event_id = 'rbf_' . $post_id;
        set_transient(
            'rbf_booking_data_' . $post_id,
            [
                'id'            => $post_id,
                'value'         => $valore_tot,
                'currency'      => 'EUR',
                'meal'          => $meal,
                'people'        => $people,
                'bucket'        => $src['bucket'],
                'gclid'         => $gclid,
                'fbclid'        => $fbclid,
                'event_id'      => $event_id,
                'unit_price'    => $valore_pp,
                'tracking_token'=> $tracking_token,
            ],
            60 * 15
        );

        return $context->with([
            'post_id'          => $post_id,
            'table_assignment' => $table_assignment,
            'tracking_token'   => $tracking_token,
            'valore_pp'        => $valore_pp,
            'valore_tot'       => $valore_tot,
            'event_id'         => $event_id,
            'options'          => $options,
        ]);
    }
}
