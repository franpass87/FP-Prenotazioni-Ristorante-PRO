<?php
namespace RBF\Bookings;

class Ajax {
	public static function register() {
		add_action( 'wp_ajax_rbf_get_availability', array( __CLASS__, 'availability' ) );
		add_action( 'wp_ajax_nopriv_rbf_get_availability', array( __CLASS__, 'availability' ) );
	}

	public static function availability() {
		check_ajax_referer( 'rbf_ajax_nonce' );
		$date = sanitize_text_field( $_POST['date'] ?? '' );
		$meal = sanitize_text_field( $_POST['meal'] ?? '' );
		if ( empty( $date ) || empty( $meal ) ) {
			wp_send_json_error();
		}
		$options   = get_option( 'rbf_settings', Helpers::get_default_settings() );
		$times_csv = $options[ 'orari_' . $meal ] ?? '';
		$times     = array_filter( array_map( 'trim', explode( ',', $times_csv ) ) );
		$available = array();
		foreach ( $times as $time ) {
			$available[] = array(
				'slot' => $meal,
				'time' => $time,
			);
		}
		wp_send_json_success( $available );
	}
}

Ajax::register();
