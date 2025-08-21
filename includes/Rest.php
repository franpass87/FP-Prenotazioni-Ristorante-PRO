<?php
namespace RBF\Bookings;

class Rest {
	public static function register() {
		register_rest_route(
			'rbf/v1',
			'/availability',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'availability' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public static function availability( $request ) {
		$date    = sanitize_text_field( $request['date'] );
		$meal    = sanitize_text_field( $request['meal'] );
		$options = get_option( 'rbf_settings', Helpers::get_default_settings() );
		$times   = array_filter( array_map( 'trim', explode( ',', $options[ 'orari_' . $meal ] ?? '' ) ) );
		return rest_ensure_response( $times );
	}
}

add_action( 'rest_api_init', array( Rest::class, 'register' ) );
