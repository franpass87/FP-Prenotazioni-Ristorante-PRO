<?php
/**
 * Tracking presets management: GA4, Meta and consent mode helpers.
 *
 * @package FP_Prenotazioni_Ristorante_PRO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return catalog definition for available packages.
 *
 * @return array<string, array>
 */
function rbf_get_tracking_package_catalog() {
	return array(
		'ga4_basic'      => array(
			'label'            => __( 'GA4 funnel standard', 'rbf' ),
			'description'      => __( 'Abilita gli eventi funnel client+server già pronti. Richiede il Measurement ID e (opzionale) l\'API secret.', 'rbf' ),
			'required_options' => array( 'ga4_id' ),
		),
		'meta_standard'  => array(
			'label'            => __( 'Meta Pixel + Conversion API', 'rbf' ),
			'description'      => __( 'Preconfigura deduplicazione Pixel/CAPI e invia gli eventi di prenotazione. Richiede Pixel ID e Access Token.', 'rbf' ),
			'required_options' => array( 'meta_pixel_id', 'meta_access_token' ),
		),
		'consent_helper' => array(
			'label'            => __( 'Snippet CMP / Consent Mode', 'rbf' ),
			'description'      => __( 'Mostra snippet pronti per collegare i principali CMP all\'hook rbf_update_consent.', 'rbf' ),
			'required_options' => array(),
		),
	);
}

/**
 * Map option keys to human readable labels for notices.
 *
 * @param string $key Option key.
 * @return string
 */
function rbf_get_tracking_option_label( $key ) {
	$labels = array(
		'ga4_id'            => rbf_translate_string( 'ID misurazione GA4' ),
		'ga4_api_secret'    => 'GA4 API Secret',
		'meta_pixel_id'     => rbf_translate_string( 'ID Meta Pixel' ),
		'meta_access_token' => 'Meta Access Token',
	);

	return $labels[ $key ] ?? $key;
}

/**
 * Default storage structure for tracking packages state.
 *
 * @return array
 */
function rbf_get_default_tracking_packages() {
	return array(
		'ga4_basic'      => array(
			'enabled'      => false,
			'last_enabled' => 0,
		),
		'meta_standard'  => array(
			'enabled'      => false,
			'last_enabled' => 0,
		),
		'consent_helper' => array(
			'enabled'      => false,
			'last_enabled' => 0,
		),
	);
}

/**
 * Retrieve persisted package state merged with defaults.
 *
 * @return array
 */
function rbf_get_tracking_packages() {
	$saved    = get_option( 'rbf_tracking_packages', array() );
	$defaults = rbf_get_default_tracking_packages();

	return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
}

/**
 * Update storage for tracking packages.
 *
 * @param array $packages Packages data.
 * @return void
 */
function rbf_update_tracking_packages( array $packages ) {
	update_option( 'rbf_tracking_packages', $packages, false );
}

/**
 * Determine if a package is enabled.
 *
 * @param string $package_id Package key.
 * @return bool
 */
function rbf_is_tracking_package_enabled( $package_id ) {
	$packages = rbf_get_tracking_packages();
	return ! empty( $packages[ $package_id ]['enabled'] );
}

/**
 * Toggle a package on/off.
 *
 * @param string $package_id Package key.
 * @param bool   $enabled    Desired status.
 * @return void
 */
function rbf_set_tracking_package_enabled( $package_id, $enabled ) {
	$packages = rbf_get_tracking_packages();
	if ( ! isset( $packages[ $package_id ] ) ) {
		return;
	}

	$packages[ $package_id ]['enabled'] = (bool) $enabled;
	if ( $enabled ) {
		$packages[ $package_id ]['last_enabled'] = time();
	}

	rbf_update_tracking_packages( $packages );
}

/**
 * Store an admin notice for the next plugin page load.
 *
 * @param string $message Notice text.
 * @param string $type    Type (success|error|warning|info).
 * @return void
 */
function rbf_add_tracking_package_notice( $message, $type = 'info' ) {
	if ( ! is_string( $message ) || trim( $message ) === '' ) {
		return;
	}

	$allowed_types = array( 'success', 'error', 'warning', 'info' );
	if ( ! in_array( $type, $allowed_types, true ) ) {
		$type = 'info';
	}

	set_transient(
		'rbf_tracking_package_notice',
		array(
			'message' => trim( $message ),
			'type'    => $type,
		),
		60
	);
}

add_action( 'admin_notices', 'rbf_render_tracking_package_notice' );
/**
 * Output stored admin notice for tracking package operations.
 */
function rbf_render_tracking_package_notice() {
	$notice = get_transient( 'rbf_tracking_package_notice' );
	if ( ! $notice || empty( $notice['message'] ) ) {
		return;
	}

	$screen    = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	$screen_id = $screen && isset( $screen->id ) ? $screen->id : '';

	$is_plugin_screen = true;
	if ( function_exists( 'rbf_is_plugin_admin_identifier' ) ) {
		$is_plugin_screen = rbf_is_plugin_admin_identifier( $screen_id );
	} elseif ( ! empty( $_GET['page'] ) ) {
		$is_plugin_screen = strpos( (string) $_GET['page'], 'rbf_' ) === 0;
	}

	if ( ! $is_plugin_screen ) {
		return;
	}

	delete_transient( 'rbf_tracking_package_notice' );

	$type    = $notice['type'];
	$classes = 'notice';
	switch ( $type ) {
		case 'success':
			$classes .= ' notice-success';
			break;
		case 'error':
			$classes .= ' notice-error';
			break;
		case 'warning':
			$classes .= ' notice-warning';
			break;
		default:
			$classes .= ' notice-info';
			break;
	}

	printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $classes ), esc_html( $notice['message'] ) );
}

/**
 * Log tracking events for diagnostics.
 *
 * @param string $channel Channel identifier (ga4|meta|gads|other).
 * @param string $event_name Event key.
 * @param array  $context Additional context data.
 * @return void
 */
function rbf_record_tracking_event( $channel, $event_name, array $context = array() ) {
	$events = get_option( 'rbf_recent_tracking_events', array() );
	if ( ! is_array( $events ) ) {
		$events = array();
	}

	$sanitized_context = array();
	foreach ( array_slice( $context, 0, 12, true ) as $ctx_key => $ctx_value ) {
		$key = sanitize_key( (string) $ctx_key );
		if ( $key === '' ) {
			continue;
		}

		if ( is_scalar( $ctx_value ) || $ctx_value === null ) {
			$sanitized_context[ $key ] = rbf_sanitize_text_strict( (string) $ctx_value );
		} else {
			$sanitized_context[ $key ] = rbf_sanitize_text_strict( wp_json_encode( $ctx_value ) );
		}
	}

	$events[] = array(
		'channel' => sanitize_key( $channel ),
		'event'   => sanitize_text_field( $event_name ),
		'time'    => current_time( 'timestamp' ),
		'context' => $sanitized_context,
	);

	if ( count( $events ) > 20 ) {
		$events = array_slice( $events, -20 );
	}

	update_option( 'rbf_recent_tracking_events', $events, false );
}

/**
 * Retrieve recent tracking events optionally filtered by channel.
 *
 * @param string|null $channel Channel filter.
 * @return array
 */
function rbf_get_recent_tracking_events( $channel = null ) {
	$events = get_option( 'rbf_recent_tracking_events', array() );
	if ( ! is_array( $events ) ) {
		return array();
	}

	if ( $channel === null ) {
		return array_reverse( $events );
	}

	$channel  = sanitize_key( $channel );
	$filtered = array_filter(
		$events,
		static function ( $event ) use ( $channel ) {
			return isset( $event['channel'] ) && $event['channel'] === $channel;
		}
	);

	return array_reverse( array_values( $filtered ) );
}

/**
 * Handle enable/disable requests from admin UI.
 */
add_action( 'admin_post_rbf_toggle_tracking_package', 'rbf_handle_toggle_tracking_package' );
function rbf_handle_toggle_tracking_package() {
	if ( ! current_user_can( rbf_get_settings_capability() ) ) {
		wp_die( __( 'Non hai i permessi per modificare queste impostazioni.', 'rbf' ) );
	}

	check_admin_referer( 'rbf_toggle_tracking_package' );

	$package = isset( $_POST['package'] ) ? sanitize_key( $_POST['package'] ) : '';
	$enabled = ! empty( $_POST['enabled'] );

	$catalog = rbf_get_tracking_package_catalog();
	if ( ! isset( $catalog[ $package ] ) ) {
		wp_die( __( 'Pacchetto tracking non riconosciuto.', 'rbf' ) );
	}

	$redirect_url = wp_get_referer() ?: admin_url( 'admin.php?page=rbf_settings#tracking' );

	$required_fields  = $catalog[ $package ]['required_options'] ?? array();
	$current_settings = rbf_get_settings();
	$missing_fields   = array();
	foreach ( $required_fields as $field_key ) {
		if ( empty( $current_settings[ $field_key ] ) ) {
			$missing_fields[] = rbf_get_tracking_option_label( $field_key );
		}
	}

	if ( $enabled && ! empty( $missing_fields ) ) {
		$message = sprintf(
			rbf_translate_string( 'Completa prima i campi richiesti: %s.' ),
			implode( ', ', $missing_fields )
		);
		rbf_add_tracking_package_notice( $message, 'error' );
		wp_safe_redirect( $redirect_url );
		exit;
	}

	rbf_set_tracking_package_enabled( $package, $enabled );

	$label = isset( $catalog[ $package ]['label'] ) ? wp_strip_all_tags( $catalog[ $package ]['label'] ) : $package;
	if ( $enabled ) {
		$message = sprintf( rbf_translate_string( 'Preset "%s" attivato.' ), $label );
		rbf_add_tracking_package_notice( $message, 'success' );
	} else {
		$message = sprintf( rbf_translate_string( 'Preset "%s" disattivato.' ), $label );
		rbf_add_tracking_package_notice( $message, 'success' );
	}

	wp_safe_redirect( $redirect_url );
	exit;
}

/**
 * Provide ready-to-use CMP snippets for the consent helper package.
 *
 * @return array<int, array{label:string, code:string}>
 */
function rbf_get_consent_helper_snippets() {
	return array(
		array(
			'label' => 'Iubenda',
			'code'  => "<script>document.addEventListener('iubenda_consent_given',function(e){if(window.rbfUpdateConsent){rbfUpdateConsent(e.detail);}});</script>",
		),
		array(
			'label' => 'Cookiebot',
			'code'  => '<script>function CookiebotCallback_OnAccept(){if(window.rbfUpdateConsent){rbfUpdateConsent(window.Cookiebot.consent);}}</script>',
		),
		array(
			'label' => 'Complianz',
			'code'  => "<script>document.addEventListener('cmplz_status_change',function(e){if(window.rbfUpdateConsent){rbfUpdateConsent(e.detail);}});</script>",
		),
	);
}
