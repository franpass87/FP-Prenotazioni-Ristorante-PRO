<?php
/**
 * Privacy tools integration for FP Prenotazioni Ristorante.
 *
 * Registers personal data exporters and erasers so site owners can comply
 * with GDPR/CCPA requests directly from WordPress.
 *
 * @package FP_Prenotazioni_Ristorante_PRO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register personal data exporter for restaurant bookings.
 *
 * @param array $exporters Registered exporters.
 * @return array
 */
function rbf_register_personal_data_exporter( $exporters ) {
	$exporters['rbf-bookings'] = array(
		'exporter_friendly_name' => rbf_translate_string( 'Prenotazioni Ristorante' ),
		'callback'               => 'rbf_personal_data_exporter',
	);

	return $exporters;
}
add_filter( 'wp_privacy_personal_data_exporters', 'rbf_register_personal_data_exporter' );

/**
 * Export personal data stored inside booking posts for a specific email address.
 *
 * @param string $email_address Email address to search for.
 * @param int    $page          Page number for batched exports.
 * @return array {
 *     @type array $data Export items.
 *     @type bool  $done Whether all items have been exported.
 * }
 */
function rbf_personal_data_exporter( $email_address, $page = 1 ) {
	$email_address = sanitize_email( $email_address );
	$page          = max( 1, (int) $page );

	if ( empty( $email_address ) ) {
		return array(
			'data' => array(),
			'done' => true,
		);
	}

	$items  = array();
	$number = 25;
	$offset = ( $page - 1 ) * $number;

	$query_args = array(
		'post_type'     => 'rbf_booking',
		'post_status'   => 'any',
		'numberposts'   => $number,
		'offset'        => $offset,
		'orderby'       => 'ID',
		'order'         => 'ASC',
		'fields'        => 'ids',
		'no_found_rows' => true,
		'meta_query'    => array(
			array(
				'key'     => 'rbf_email',
				'value'   => $email_address,
				'compare' => '=',
			),
		),
	);

	$booking_ids = get_posts( $query_args );

	if ( empty( $booking_ids ) ) {
		return array(
			'data' => array(),
			'done' => true,
		);
	}

	$statuses = rbf_get_booking_statuses();

	foreach ( $booking_ids as $booking_id ) {
		$meta = get_post_meta( $booking_id );

		$booking_date  = $meta['rbf_data'][0] ?? '';
		$booking_time  = $meta['rbf_time'][0] ?? ( $meta['rbf_orario'][0] ?? '' );
		$people        = $meta['rbf_persone'][0] ?? '';
		$meal          = $meta['rbf_meal'][0] ?? '';
		$status_key    = $meta['rbf_booking_status'][0] ?? 'confirmed';
		$status_label  = $statuses[ $status_key ] ?? $status_key;
		$first_name    = $meta['rbf_nome'][0] ?? '';
		$last_name     = $meta['rbf_cognome'][0] ?? '';
		$phone         = $meta['rbf_tel'][0] ?? '';
		$notes         = $meta['rbf_allergie'][0] ?? '';
		$privacy       = $meta['rbf_privacy'][0] ?? '';
		$marketing     = $meta['rbf_marketing'][0] ?? '';
		$language      = $meta['rbf_lang'][0] ?? '';
		$country       = $meta['rbf_country_code'][0] ?? '';
		$special_type  = $meta['rbf_special_type'][0] ?? '';
		$special_label = $meta['rbf_special_label'][0] ?? '';
		$source_bucket = $meta['rbf_source_bucket'][0] ?? '';
		$source        = $meta['rbf_source'][0] ?? '';
		$medium        = $meta['rbf_medium'][0] ?? '';
		$campaign      = $meta['rbf_campaign'][0] ?? '';
		$gclid         = $meta['rbf_gclid'][0] ?? '';
		$fbclid        = $meta['rbf_fbclid'][0] ?? '';
		$referrer      = $meta['rbf_referrer'][0] ?? '';
		$created_at    = $meta['rbf_booking_created'][0] ?? '';

		$item_data = array(
			array(
				'name'  => rbf_translate_string( 'ID prenotazione' ),
				'value' => $booking_id,
			),
			array(
				'name'  => rbf_translate_string( 'Stato prenotazione' ),
				'value' => $status_label,
			),
			array(
				'name'  => rbf_translate_string( 'Data' ),
				'value' => $booking_date,
			),
			array(
				'name'  => rbf_translate_string( 'Orario' ),
				'value' => $booking_time,
			),
			array(
				'name'  => rbf_translate_string( 'Persone' ),
				'value' => $people,
			),
			array(
				'name'  => rbf_translate_string( 'Servizio' ),
				'value' => $meal,
			),
			array(
				'name'  => rbf_translate_string( 'Nome' ),
				'value' => $first_name,
			),
			array(
				'name'  => rbf_translate_string( 'Cognome' ),
				'value' => $last_name,
			),
			array(
				'name'  => rbf_translate_string( 'Email' ),
				'value' => $email_address,
			),
			array(
				'name'  => rbf_translate_string( 'Telefono' ),
				'value' => $phone,
			),
		);

		if ( $notes !== '' ) {
			$item_data[] = array(
				'name'  => rbf_translate_string( 'Note' ),
				'value' => $notes,
			);
		}

		if ( $privacy !== '' ) {
			$item_data[] = array(
				'name'  => rbf_translate_string( 'Consenso privacy' ),
				'value' => $privacy,
			);
		}

		if ( $marketing !== '' ) {
			$item_data[] = array(
				'name'  => rbf_translate_string( 'Consenso marketing' ),
				'value' => $marketing,
			);
		}

		if ( $language !== '' ) {
			$item_data[] = array(
				'name'  => rbf_translate_string( 'Lingua modulo' ),
				'value' => $language,
			);
		}

		if ( $country !== '' ) {
			$item_data[] = array(
				'name'  => rbf_translate_string( 'Paese' ),
				'value' => $country,
			);
		}

		if ( $special_type !== '' || $special_label !== '' ) {
			$item_data[] = array(
				'name'  => rbf_translate_string( 'Occasione speciale' ),
				'value' => trim( $special_type . ' ' . $special_label ),
			);
		}

		if ( $source_bucket !== '' || $source !== '' || $medium !== '' || $campaign !== '' ) {
			$item_data[] = array(
				'name'  => rbf_translate_string( 'Sorgente prenotazione' ),
				'value' => trim( $source_bucket . ' ' . $source . ' ' . $medium . ' ' . $campaign ),
			);
		}

		if ( $gclid !== '' ) {
			$item_data[] = array(
				'name'  => 'gclid',
				'value' => $gclid,
			);
		}

		if ( $fbclid !== '' ) {
			$item_data[] = array(
				'name'  => 'fbclid',
				'value' => $fbclid,
			);
		}

		if ( $referrer !== '' ) {
			$item_data[] = array(
				'name'  => rbf_translate_string( 'Referrer' ),
				'value' => $referrer,
			);
		}

		if ( $created_at !== '' ) {
			$item_data[] = array(
				'name'  => rbf_translate_string( 'Data creazione' ),
				'value' => $created_at,
			);
		}

		$items[] = array(
			'group_id'    => 'rbf_bookings',
			'group_label' => rbf_translate_string( 'Prenotazioni Ristorante' ),
			'item_id'     => 'rbf-booking-' . $booking_id,
			'data'        => $item_data,
		);
	}

	$done = count( $booking_ids ) < $number;

	return array(
		'data' => $items,
		'done' => $done,
	);
}

/**
 * Register personal data eraser for restaurant bookings.
 *
 * @param array $erasers Registered erasers.
 * @return array
 */
function rbf_register_personal_data_eraser( $erasers ) {
	$erasers['rbf-bookings'] = array(
		'eraser_friendly_name' => rbf_translate_string( 'Prenotazioni Ristorante' ),
		'callback'             => 'rbf_personal_data_eraser',
	);

	return $erasers;
}
add_filter( 'wp_privacy_personal_data_erasers', 'rbf_register_personal_data_eraser' );

/**
 * Erase or anonymize personal data stored inside booking posts.
 *
 * @param string $email_address Email address to search for.
 * @param int    $page          Page number for batched erasure.
 * @return array
 */
function rbf_personal_data_eraser( $email_address, $page = 1 ) {
	$email_address = sanitize_email( $email_address );
	$page          = max( 1, (int) $page );

	if ( empty( $email_address ) ) {
		return array(
			'items_removed'  => false,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	$number = 25;
	$offset = ( $page - 1 ) * $number;

	$query_args = array(
		'post_type'   => 'rbf_booking',
		'post_status' => 'any',
		'numberposts' => $number,
		'offset'      => $offset,
		'fields'      => 'ids',
		'orderby'     => 'ID',
		'order'       => 'ASC',
		'meta_query'  => array(
			array(
				'key'     => 'rbf_email',
				'value'   => $email_address,
				'compare' => '=',
			),
		),
	);

	$booking_ids = get_posts( $query_args );

	if ( empty( $booking_ids ) ) {
		return array(
			'items_removed'  => false,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	$items_removed = false;

	foreach ( $booking_ids as $booking_id ) {
		$anonymised_title = sprintf( rbf_translate_string( 'Prenotazione #%d (dati rimossi)' ), $booking_id );
		wp_update_post(
			array(
				'ID'         => $booking_id,
				'post_title' => $anonymised_title,
			)
		);

		$meta_updates = array(
			'rbf_nome'           => '',
			'rbf_cognome'        => '',
			'rbf_email'          => '',
			'rbf_tel'            => '',
			'rbf_tel_prefix'     => '',
			'rbf_tel_number'     => '',
			'rbf_allergie'       => '',
			'rbf_privacy'        => 'erased',
			'rbf_marketing'      => '',
			'rbf_lang'           => '',
			'rbf_country_code'   => '',
			'rbf_brevo_lang'     => '',
			'rbf_special_type'   => '',
			'rbf_special_label'  => '',
			'rbf_source_bucket'  => '',
			'rbf_source'         => '',
			'rbf_medium'         => '',
			'rbf_campaign'       => '',
			'rbf_gclid'          => '',
			'rbf_fbclid'         => '',
			'rbf_referrer'       => '',
			'rbf_tracking_token' => '',
		);

		foreach ( $meta_updates as $meta_key => $meta_value ) {
			update_post_meta( $booking_id, $meta_key, $meta_value );
		}

		$items_removed = true;
	}

	$done = count( $booking_ids ) < $number;

	$messages = array();
	if ( $items_removed ) {
		$messages[] = rbf_translate_string( 'I dati personali delle prenotazioni sono stati anonimizzati.' );
	}

	return array(
		'items_removed'  => $items_removed,
		'items_retained' => false,
		'messages'       => $messages,
		'done'           => $done,
	);
}
