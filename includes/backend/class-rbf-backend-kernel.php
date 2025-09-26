<?php
/**
 * Backend service container for FP Prenotazioni Ristorante.
 *
 * @package FP_Prenotazioni_Ristorante_PRO\Backend
 */

namespace RBF\Backend;

use RBF\Backend\Booking\AvailabilityService;
use RBF\Backend\Booking\BookingPipeline;
use RBF\Backend\Booking\BookingRepository;
use RBF\Backend\Booking\BookingRequestValidator;
use RBF\Backend\Booking\NotificationService;
use RBF\Backend\Booking\TrackingBuilder;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Extremely lightweight service container tailored for the plugin backend.
 *
 * The plugin historically relied on large procedural functions; this container
 * exposes the new object oriented services so that legacy wrappers can defer
 * to them while new code can depend on a clean API.
 */
class Kernel {
	/**
	 * Registered service factories.
	 *
	 * @var array<string, callable>
	 */
	private $factories = array();

	/**
	 * Instantiated services cache.
	 *
	 * @var array<string, object>
	 */
	private $instances = array();

	/**
	 * Build the kernel and register default services.
	 */
	public function __construct() {
		$this->factories = array(
			'booking.validator'        => function () {
				return new BookingRequestValidator();
			},
			'booking.availability'     => function () {
				return new AvailabilityService();
			},
			'booking.repository'       => function () {
				return new BookingRepository();
			},
			'booking.notifications'    => function () {
				return new NotificationService();
			},
			'booking.tracking_builder' => function () {
				return new TrackingBuilder();
			},
		);

		$this->factories['booking.pipeline'] = function () {
			return new BookingPipeline(
				$this->get( 'booking.validator' ),
				$this->get( 'booking.availability' ),
				$this->get( 'booking.repository' ),
				$this->get( 'booking.notifications' )
			);
		};
	}

	/**
	 * Retrieve a registered backend service instance.
	 *
	 * @param string $id Service identifier.
	 * @return object
	 */
	public function get( $id ) {
		if ( isset( $this->instances[ $id ] ) ) {
			return $this->instances[ $id ];
		}

		if ( ! isset( $this->factories[ $id ] ) ) {
			throw new \InvalidArgumentException( 'Unknown backend service: ' . $id );
		}

		$this->instances[ $id ] = call_user_func( $this->factories[ $id ] );
		return $this->instances[ $id ];
	}
}
