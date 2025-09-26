<?php
/**
 * Booking tracking data builder.
 *
 * @package FP_Prenotazioni_Ristorante_PRO\Backend\Booking
 */

namespace RBF\Backend\Booking;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Produces normalized tracking payloads reused by integrations and analytics.
 */
class TrackingBuilder {
	/**
	 * Build a normalized tracking dataset.
	 *
	 * @param int                 $booking_id Booking post ID.
	 * @param array<string,mixed> $base_data  Base payload to merge with.
	 * @param array|null          $meta       Optional pre-fetched metadata.
	 * @return array<string,mixed>
	 */
	public function build( $booking_id, array $base_data = array(), $meta = null ) {
		if ( ! $booking_id ) {
			return $base_data;
		}

		if ( ! is_array( $meta ) ) {
			$meta = get_post_meta( $booking_id );
		}

		$tracking = array_merge(
			array(
				'id'       => $booking_id,
				'currency' => 'EUR',
				'event_id' => 'rbf_' . $booking_id,
			),
			$base_data
		);

		$meal_meta = $meta['rbf_meal'][0] ?? ( $meta['rbf_orario'][0] ?? '' );
		if ( empty( $tracking['meal'] ) && ! empty( $meal_meta ) ) {
			$tracking['meal'] = $meal_meta;
		} elseif ( empty( $tracking['meal'] ) ) {
			$tracking['meal'] = 'pranzo';
		}

		$people_meta = isset( $meta['rbf_persone'][0] ) ? (int) $meta['rbf_persone'][0] : 0;
		if ( ! isset( $tracking['people'] ) || (int) $tracking['people'] <= 0 ) {
			$tracking['people'] = $people_meta;
		} else {
			$tracking['people'] = (int) $tracking['people'];
		}

		if ( empty( $tracking['bucket'] ) ) {
			$tracking['bucket'] = $meta['rbf_source_bucket'][0] ?? 'organic';
		}

		if ( ! isset( $tracking['gclid'] ) ) {
			$tracking['gclid'] = $meta['rbf_gclid'][0] ?? '';
		}

		if ( ! isset( $tracking['fbclid'] ) ) {
			$tracking['fbclid'] = $meta['rbf_fbclid'][0] ?? '';
		}

		if ( empty( $tracking['currency'] ) && isset( $meta['rbf_value_currency'][0] ) ) {
			$tracking['currency'] = $meta['rbf_value_currency'][0];
		}

		$value_meta      = isset( $meta['rbf_valore_tot'][0] ) ? (float) $meta['rbf_valore_tot'][0] : 0.0;
		$unit_price_meta = isset( $meta['rbf_valore_pp'][0] ) ? (float) $meta['rbf_valore_pp'][0] : 0.0;

		if ( ! isset( $tracking['value'] ) || ! is_numeric( $tracking['value'] ) || $tracking['value'] <= 0 ) {
			$tracking['value'] = $value_meta;
		} else {
			$tracking['value'] = (float) $tracking['value'];
		}

		if ( ! isset( $tracking['unit_price'] ) || ! is_numeric( $tracking['unit_price'] ) || $tracking['unit_price'] <= 0 ) {
			$tracking['unit_price'] = $unit_price_meta;
		} else {
			$tracking['unit_price'] = (float) $tracking['unit_price'];
		}

		$people_for_calc = (int) ( $tracking['people'] ?? 0 );
		if ( $people_for_calc <= 0 && $people_meta > 0 ) {
			$people_for_calc    = $people_meta;
			$tracking['people'] = $people_meta;
		}
		$people_for_calc = max( 1, $people_for_calc );

		if ( $tracking['unit_price'] <= 0 && $tracking['value'] > 0 ) {
			$tracking['unit_price'] = $people_for_calc > 0 ? $tracking['value'] / $people_for_calc : 0.0;
		}

		if ( $tracking['value'] <= 0 && $tracking['unit_price'] > 0 ) {
			$tracking['value'] = $tracking['unit_price'] * $people_for_calc;
		}

		if ( $tracking['unit_price'] <= 0 || $tracking['value'] <= 0 ) {
			$meal_key = $tracking['meal'] ?? '';
			if ( ! empty( $meal_key ) ) {
				$meal_config = rbf_get_meal_config( $meal_key );
				if ( $meal_config && $tracking['unit_price'] <= 0 ) {
					$tracking['unit_price'] = (float) $meal_config['price'];
				}

				if ( $tracking['unit_price'] <= 0 ) {
					$options        = rbf_get_settings();
					$meal_for_value = ( $meal_key === 'brunch' ) ? 'pranzo' : $meal_key;
					$option_price   = (float) ( $options[ 'valore_' . $meal_for_value ] ?? 0 );
					if ( $option_price > 0 ) {
						$tracking['unit_price'] = $option_price;
					}
				}
			}

			if ( $tracking['unit_price'] > 0 && $tracking['value'] <= 0 ) {
				$tracking['value'] = $tracking['unit_price'] * $people_for_calc;
			}
		}

		$tracking['value']      = max( 0, (float) ( $tracking['value'] ?? 0 ) );
		$tracking['unit_price'] = max( 0, (float) ( $tracking['unit_price'] ?? 0 ) );

		return $tracking;
	}
}
