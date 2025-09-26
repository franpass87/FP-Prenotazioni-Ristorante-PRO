<?php
/**
 * Booking submission handler for FP Prenotazioni Ristorante.
 *
 * @package FP_Prenotazioni_Ristorante_PRO
 */

use RBF\Backend\Booking\BookingContext;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/backend/bootstrap.php';

/**
 * Validate booking request and sanitize data.
 *
 * Legacy helper preserved for backward compatibility. The heavy lifting is
 * now delegated to the dedicated service layer.
 *
 * @param array  $post         Raw POST data.
 * @param string $redirect_url URL for redirection on error.
 * @param string $anchor       Anchor for messages.
 * @return array|false Sanitized booking data or false on failure.
 */
function rbf_validate_request( $post, $redirect_url, $anchor ) {
	$context = rbf_backend( 'booking.validator' )->validate( $post, $redirect_url, $anchor );
	return $context ? $context->toArray() : false;
}

/**
 * Check availability and reserve the slot.
 *
 * @param array  $data         Sanitized booking data.
 * @param string $redirect_url URL for redirection on error.
 * @param string $anchor       Anchor for messages.
 * @return array|false Booking data enriched with reservation info or false on failure.
 */
function rbf_check_availability( $data, $redirect_url, $anchor ) {
	$context = new BookingContext( $data );
	$context = rbf_backend( 'booking.availability' )->ensure( $context, $redirect_url, $anchor );
	return $context ? $context->toArray() : false;
}

/**
 * Create the booking post and store metadata.
 *
 * @param array  $data         Booking data with reservation info.
 * @param string $redirect_url URL for redirection on error.
 * @param string $anchor       Anchor for messages.
 * @return array|false Context data including post ID and tracking info or false on failure.
 */
function rbf_create_booking_post( $data, $redirect_url, $anchor ) {
	$context = new BookingContext( $data );
	$context = rbf_backend( 'booking.repository' )->create( $context, $redirect_url, $anchor );
	return $context ? array_intersect_key(
		$context->toArray(),
		array(
			'post_id'        => true,
			'valore_tot'     => true,
			'valore_pp'      => true,
			'event_id'       => true,
			'options'        => true,
			'tracking_token' => true,
		)
	) : false;
}

/**
 * Send notifications and perform integrations.
 *
 * @param array $data    Booking data.
 * @param array $context Context data from post creation.
 * @return void
 */
function rbf_send_notifications( $data, $context ) {
	$full_context = new BookingContext( array_merge( $data, $context ) );
	rbf_backend( 'booking.notifications' )->dispatch( $full_context );
}

/**
 * Build normalized tracking data for a booking using stored metadata.
 *
 * @param int        $booking_id Booking post ID.
 * @param array      $base_data  Optional base tracking data to merge.
 * @param array|null $meta       Optional pre-fetched post meta array.
 * @return array Normalized tracking dataset with financial fallbacks.
 */
function rbf_build_booking_tracking_data( $booking_id, array $base_data = array(), $meta = null ) {
	return rbf_backend( 'booking.tracking_builder' )->build( $booking_id, $base_data, $meta );
}

/**
 * Handle booking form submission.
 */
add_action( 'admin_post_rbf_submit_booking', 'rbf_handle_booking_submission' );
add_action( 'admin_post_nopriv_rbf_submit_booking', 'rbf_handle_booking_submission' );
function rbf_handle_booking_submission() {
	$redirect_url = wp_get_referer() ? strtok( wp_get_referer(), '?' ) : home_url();
	$anchor       = '#rbf-message-anchor';

	$context = rbf_backend( 'booking.pipeline' )->handle( $_POST, $redirect_url, $anchor );
	if ( ! $context ) {
		return;
	}

	$post_id        = $context->get( 'post_id' );
	$tracking_token = $context->get( 'tracking_token' );

	$success_args = array(
		'rbf_success'   => '1',
		'booking_id'    => $post_id,
		'booking_token' => $tracking_token,
	);

	rbf_handle_success(
		'Booking created successfully',
		$success_args,
		add_query_arg( $success_args, $redirect_url . $anchor )
	);
}
