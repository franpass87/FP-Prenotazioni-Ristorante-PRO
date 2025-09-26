<?php
/**
 * Booking request validation service.
 *
 * @package FP_Prenotazioni_Ristorante_PRO\Backend\Booking
 */

namespace RBF\Backend\Booking;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Perform all sanitisation and validation for booking submissions.
 */
class BookingRequestValidator {
	/**
	 * Validate the incoming POST request and build the base booking context.
	 *
	 * @param array<string, mixed> $post         Raw POST payload.
	 * @param string               $redirect_url Redirect destination on failure.
	 * @param string               $anchor       Anchor used for feedback messages.
	 * @return BookingContext|null
	 */
	public function validate( array $post, $redirect_url, $anchor ) {
		if ( ! isset( $post['rbf_nonce'] ) || ! wp_verify_nonce( $post['rbf_nonce'], 'rbf_booking' ) ) {
			rbf_handle_error( rbf_translate_string( 'Errore di sicurezza.' ), 'security', $redirect_url . $anchor );
			return null;
		}

		$bot_detected = rbf_detect_bot_submission( $post );
		if ( $bot_detected['is_bot'] ) {
			rbf_log( 'RBF Bot Detection: ' . $bot_detected['reason'] . ' - IP: ' . ( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ) );

			if ( $bot_detected['severity'] === 'high' ) {
				$options              = rbf_get_settings();
				$recaptcha_configured = ! empty( $options['recaptcha_site_key'] ) && ! empty( $options['recaptcha_secret_key'] );

				if ( $recaptcha_configured && ! empty( $post['g-recaptcha-response'] ) ) {
					$recaptcha_result = rbf_verify_recaptcha( $post['g-recaptcha-response'] );
					if ( ! $recaptcha_result['success'] ) {
						rbf_log( 'RBF reCAPTCHA Failed: ' . $recaptcha_result['reason'] . ' - IP: ' . ( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ) );
						rbf_handle_error(
							rbf_translate_string( 'Verifica di sicurezza fallita. Per favore riprova.' ),
							'recaptcha_failed',
							$redirect_url . $anchor
						);
						return null;
					}
					rbf_log( 'RBF Bot detected but reCAPTCHA passed - allowing submission' );
				} else {
					rbf_handle_error(
						rbf_translate_string( 'Rilevata attività sospetta. Per favore riprova.' ),
						'bot_detected',
						$redirect_url . $anchor
					);
					return null;
				}
			}
		}

		$required = array(
			'rbf_meal',
			'rbf_data',
			'rbf_orario',
			'rbf_persone',
			'rbf_nome',
			'rbf_cognome',
			'rbf_email',
			'rbf_phone_prefix',
			'rbf_tel_number',
			'rbf_privacy',
		);

		foreach ( $required as $field ) {
			if ( empty( $post[ $field ] ) ) {
				rbf_handle_error(
					rbf_translate_string( "Tutti i campi sono obbligatori, inclusa l'accettazione della privacy policy." ),
					'validation',
					$redirect_url . $anchor
				);
				return null;
			}
		}

		$sanitized_fields = rbf_sanitize_input_fields(
			$post,
			array(
				'rbf_meal'           => 'text',
				'rbf_data'           => 'text',
				'rbf_orario'         => 'text',
				'rbf_persone'        => 'int',
				'rbf_nome'           => 'name',
				'rbf_cognome'        => 'name',
				'rbf_allergie'       => 'textarea',
				'rbf_lang'           => 'text',
				'rbf_phone_prefix'   => 'text',
				'rbf_tel_number'     => 'phone',
				'rbf_utm_source'     => 'text',
				'rbf_utm_medium'     => 'text',
				'rbf_utm_campaign'   => 'text',
				'rbf_gclid'          => 'text',
				'rbf_fbclid'         => 'text',
				'rbf_referrer'       => 'text',
				'rbf_special_type'   => 'text',
				'rbf_special_label'  => 'text',
				'rbf_form_timestamp' => 'int',
				'rbf_website'        => 'text',
				'rbf_privacy'        => 'text',
				'rbf_marketing'      => 'text',
			)
		);

		$privacy_raw = $sanitized_fields['rbf_privacy'] ?? '';
		if ( $privacy_raw !== 'yes' ) {
			rbf_handle_error(
				rbf_translate_string( "È necessario accettare l'informativa sulla privacy per proseguire." ),
				'privacy_validation',
				$redirect_url . $anchor
			);
			return null;
		}

		$meal      = $sanitized_fields['rbf_meal'];
		$date      = $sanitized_fields['rbf_data'];
		$time_data = $sanitized_fields['rbf_orario'];

		$valid_meal_ids = rbf_get_valid_meal_ids();
		if ( ! in_array( $meal, $valid_meal_ids, true ) ) {
			rbf_handle_error( rbf_translate_string( 'Tipo di pasto non valido.' ), 'meal_validation', $redirect_url . $anchor );
			return null;
		}

		if ( strpos( $time_data, '|' ) === false ) {
			rbf_handle_error( rbf_translate_string( 'Formato orario non valido.' ), 'time_format', $redirect_url . $anchor );
			return null;
		}

		list($slot, $time) = explode( '|', $time_data, 2 );
		$slot              = trim( $slot );
		$normalized_slot   = sanitize_key( $slot );
		$slot_config       = null;

		if ( $normalized_slot !== '' ) {
			$slot_config = rbf_get_meal_config( $normalized_slot );

			if ( ! $slot_config && $normalized_slot !== $slot ) {
				$slot_config = rbf_get_meal_config( $slot );
			}

			if ( ! $slot_config ) {
				$active_meals = rbf_get_active_meals();
				foreach ( $active_meals as $candidate ) {
					if ( empty( $candidate['legacy_ids'] ) ) {
						continue;
					}

					$legacy_ids = array_map( 'sanitize_key', (array) $candidate['legacy_ids'] );
					if ( in_array( $normalized_slot, $legacy_ids, true ) ) {
						$slot_config = $candidate;
						break;
					}
				}
			}

			if ( $slot_config && ! empty( $slot_config['id'] ) ) {
				$normalized_slot = $slot_config['id'];
			}
		}

		if ( $normalized_slot === '' ) {
			rbf_handle_error(
				rbf_translate_string( 'La fascia oraria selezionata non è valida.' ),
				'slot_validation',
				$redirect_url . $anchor
			);
			return null;
		}

		if ( ! in_array( $normalized_slot, $valid_meal_ids, true ) ) {
			rbf_handle_error(
				rbf_translate_string( 'La fascia oraria selezionata non è valida.' ),
				'slot_validation',
				$redirect_url . $anchor
			);
			return null;
		}

		if ( $normalized_slot !== $meal ) {
			rbf_handle_error(
				rbf_translate_string( 'Il pasto selezionato non corrisponde alla fascia oraria scelta.' ),
				'slot_mismatch',
				$redirect_url . $anchor
			);
			return null;
		}

		$slot             = $normalized_slot;
		$people           = (int) $sanitized_fields['rbf_persone'];
		$people_max_limit = rbf_get_people_max_limit();
		if ( $people < 1 || $people > $people_max_limit ) {
			rbf_handle_error(
				sprintf(
					rbf_translate_string( 'Il numero di persone deve essere compreso tra 1 e %d.' ),
					$people_max_limit
				),
				'people_validation',
				$redirect_url . $anchor
			);
			return null;
		}

		$first_name = $sanitized_fields['rbf_nome'];
		$last_name  = $sanitized_fields['rbf_cognome'];
		$email      = rbf_validate_email( $post['rbf_email'] );
		if ( is_array( $email ) && isset( $email['error'] ) ) {
			rbf_handle_error( $email['message'], 'email_validation', $redirect_url . $anchor );
			return null;
		}

		$phone_data = rbf_prepare_phone_number(
			$sanitized_fields['rbf_phone_prefix'] ?? '',
			$sanitized_fields['rbf_tel_number'] ?? ''
		);

		if ( empty( $phone_data['number'] ) ) {
			rbf_handle_error(
				rbf_translate_string( 'Il numero di telefono inserito non è valido.' ),
				'phone_validation',
				$redirect_url . $anchor
			);
			return null;
		}

		$validated_tel = rbf_validate_phone( $phone_data['full'] );
		if ( is_array( $validated_tel ) && isset( $validated_tel['error'] ) ) {
			rbf_handle_error( $validated_tel['message'], 'phone_validation', $redirect_url . $anchor );
			return null;
		}
		$tel = is_array( $validated_tel ) ? $phone_data['full'] : $validated_tel;

		$notes           = $sanitized_fields['rbf_allergie'] ?? '';
		$form_lang       = strtolower( $sanitized_fields['rbf_lang'] ?? 'it' );
		$normalized_lang = $form_lang === 'en' ? 'en' : 'it';
		$country_code    = strtolower( $phone_data['country_code'] ?? 'it' );
		if ( $country_code === '' ) {
			$country_code = 'it';
		}

		$brevo_lang = ( $normalized_lang === 'en' ) ? 'en' : 'it';
		if ( $country_code === 'it' ) {
			$brevo_lang = 'it';
		}

		$marketing_raw = $sanitized_fields['rbf_marketing'] ?? 'no';
		$marketing     = ( $marketing_raw === 'yes' || $marketing_raw === 'no' ) ? $marketing_raw : 'no';

		$utm_source   = $sanitized_fields['rbf_utm_source'] ?? '';
		$utm_medium   = $sanitized_fields['rbf_utm_medium'] ?? '';
		$utm_campaign = $sanitized_fields['rbf_utm_campaign'] ?? '';
		$gclid        = $sanitized_fields['rbf_gclid'] ?? '';
		$fbclid       = $sanitized_fields['rbf_fbclid'] ?? '';
		$referrer     = $sanitized_fields['rbf_referrer'] ?? '';

		$src = rbf_detect_source(
			array(
				'utm_source'   => $utm_source,
				'utm_medium'   => $utm_medium,
				'utm_campaign' => $utm_campaign,
				'gclid'        => $gclid,
				'fbclid'       => $fbclid,
				'referrer'     => $referrer,
			)
		);

		return new BookingContext(
			array(
				'sanitized_fields' => $sanitized_fields,
				'meal'             => $meal,
				'date'             => $date,
				'slot'             => $slot,
				'time'             => $time,
				'people'           => $people,
				'first_name'       => $first_name,
				'last_name'        => $last_name,
				'email'            => $email,
				'tel'              => $tel,
				'tel_prefix'       => $phone_data['prefix'],
				'tel_number'       => $phone_data['number'],
				'notes'            => $notes,
				'lang'             => $form_lang,
				'country_code'     => $country_code,
				'brevo_lang'       => $brevo_lang,
				'privacy'          => 'yes',
				'marketing'        => $marketing,
				'utm_source'       => $utm_source,
				'utm_medium'       => $utm_medium,
				'utm_campaign'     => $utm_campaign,
				'gclid'            => $gclid,
				'fbclid'           => $fbclid,
				'referrer'         => $referrer,
				'src'              => $src,
			)
		);
	}
}
