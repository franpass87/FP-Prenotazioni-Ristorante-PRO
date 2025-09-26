<?php
/**
 * Immutable booking context holder.
 *
 * @package FP_Prenotazioni_Ristorante_PRO\Backend\Booking
 */

namespace RBF\Backend\Booking;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The booking flow now exchanges data using this small immutable object.
 *
 * It keeps the array based nature of the legacy implementation while offering
 * a predictable API and helper utilities to derive new versions when a stage
 * of the pipeline enriches the dataset.
 */
class BookingContext {
	/**
	 * @var array<string, mixed>
	 */
	private $data = array();

	/**
	 * @param array<string, mixed> $data Initial payload.
	 */
	public function __construct( array $data = array() ) {
		$this->data = $data;
	}

	/**
	 * Retrieve a value from the context.
	 *
	 * @param string $key     Context key.
	 * @param mixed  $default Default fallback.
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		if ( array_key_exists( $key, $this->data ) ) {
			return $this->data[ $key ];
		}

		return $default;
	}

	/**
	 * Export the internal payload as array.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray() {
		return $this->data;
	}

	/**
	 * Create a new context with extra data merged in.
	 *
	 * @param array<string, mixed> $extra Extra key/value pairs.
	 * @return static
	 */
	public function with( array $extra ) {
		return new static( array_merge( $this->data, $extra ) );
	}
}
