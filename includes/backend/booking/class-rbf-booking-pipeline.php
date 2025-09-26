<?php
/**
 * Booking pipeline orchestrator.
 *
 * @package FP_Prenotazioni_Ristorante_PRO\Backend\Booking
 */

namespace RBF\Backend\Booking;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coordinates the booking workflow stages.
 */
class BookingPipeline {
	/**
	 * @var BookingRequestValidator
	 */
	private $validator;

	/**
	 * @var AvailabilityService
	 */
	private $availability;

	/**
	 * @var BookingRepository
	 */
	private $repository;

	/**
	 * @var NotificationService
	 */
	private $notifications;

	/**
	 * @param BookingRequestValidator $validator    Validator instance.
	 * @param AvailabilityService     $availability Availability checker.
	 * @param BookingRepository       $repository   Persistence handler.
	 * @param NotificationService     $notifications Notification dispatcher.
	 */
	public function __construct(
		BookingRequestValidator $validator,
		AvailabilityService $availability,
		BookingRepository $repository,
		NotificationService $notifications
	) {
		$this->validator     = $validator;
		$this->availability  = $availability;
		$this->repository    = $repository;
		$this->notifications = $notifications;
	}

	/**
	 * Execute the booking workflow.
	 *
	 * @param array<string,mixed> $post         Raw POST payload.
	 * @param string              $redirect_url Redirect destination on failure.
	 * @param string              $anchor       Anchor used for feedback messages.
	 * @return BookingContext|null
	 */
	public function handle( array $post, $redirect_url, $anchor ) {
		$context = $this->validator->validate( $post, $redirect_url, $anchor );
		if ( ! $context ) {
			return null;
		}

		$context = $this->availability->ensure( $context, $redirect_url, $anchor );
		if ( ! $context ) {
			return null;
		}

		$context = $this->repository->create( $context, $redirect_url, $anchor );
		if ( ! $context ) {
			return null;
		}

		$this->notifications->dispatch( $context );

		return $context;
	}
}
