<?php
/**
 * Booking availability check service.
 *
 * @package FP_Prenotazioni_Ristorante_PRO\Backend\Booking
 */

namespace RBF\Backend\Booking;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Encapsulates availability checks and optimistic capacity reservations.
 */
class AvailabilityService {
	/**
	 * Ensure the requested slot can be reserved.
	 *
	 * @param BookingContext $context      Validated booking context.
	 * @param string         $redirect_url Redirect destination on failure.
	 * @param string         $anchor       Anchor used for feedback messages.
	 * @return BookingContext|null
	 */
	public function ensure( BookingContext $context, $redirect_url, $anchor ) {
		$meal   = $context->get( 'meal' );
		$date   = $context->get( 'date' );
		$slot   = $context->get( 'slot' );
		$time   = $context->get( 'time' );
		$people = $context->get( 'people' );

		if ( ! rbf_is_meal_available_on_day( $meal, $date ) ) {
			$meal_config = rbf_get_meal_config( $meal );
			$meal_name   = $meal_config ? $meal_config['name'] : $meal;
			rbf_handle_error(
				sprintf( rbf_translate_string( '%s non è disponibile in questo giorno.' ), $meal_name ),
				'meal_day_validation',
				$redirect_url . $anchor
			);
			return null;
		}

		if ( ! rbf_is_restaurant_open( $date, $meal ) ) {
			rbf_handle_error(
				rbf_translate_string( 'Il ristorante è chiuso nella data selezionata.' ),
				'restaurant_closed',
				$redirect_url . $anchor
			);
			return null;
		}

		$time_validation = rbf_validate_booking_time( $date, $time );
		if ( $time_validation !== true ) {
			rbf_handle_error( $time_validation['message'], 'time_validation', $redirect_url . $anchor );
			return null;
		}

		$buffer_validation = rbf_validate_buffer_time( $date, $time, $slot, $people );
		if ( $buffer_validation !== true ) {
			rbf_handle_error( $buffer_validation['message'], 'buffer_validation', $redirect_url . $anchor );
			return null;
		}

		$booking_result = rbf_book_slot_optimistic( $date, $slot, $people );
		if ( ! $booking_result['success'] ) {
			if ( $booking_result['error'] === 'insufficient_capacity' ) {
				$remaining = $booking_result['remaining'] ?? 0;
				$error_msg = sprintf(
					rbf_translate_string( 'Spiacenti, non ci sono abbastanza posti. Rimasti: %d. Scegli un altro orario.' ),
					$remaining
				);
				rbf_handle_error( $error_msg, 'capacity_validation', $redirect_url . $anchor );
			} elseif ( $booking_result['error'] === 'version_conflict' ) {
				$error_msg = rbf_translate_string( 'Questo slot è stato appena prenotato da un altro utente. Ti preghiamo di ricaricare la pagina e riprovare.' );
				rbf_handle_error( $error_msg, 'concurrent_booking', $redirect_url . $anchor );
			} else {
				$error_msg = rbf_translate_string( 'Errore durante la prenotazione. Ti preghiamo di riprovare.' );
				rbf_handle_error( $error_msg, 'booking_system_error', $redirect_url . $anchor );
			}
			return null;
		}

		return $context->with(
			array(
				'booking_result' => $booking_result,
				'booking_status' => 'confirmed',
			)
		);
	}
}
