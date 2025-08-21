<?php
namespace RBF\Bookings;

class Submission {
	public static function register() {
		add_action( 'admin_post_rbf_submit_booking', array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_nopriv_rbf_submit_booking', array( __CLASS__, 'handle' ) );
	}

	public static function handle() {
		$redirect = wp_get_referer() ? wp_get_referer() : home_url();
		if ( ! isset( $_POST['rbf_nonce'] ) || ! wp_verify_nonce( $_POST['rbf_nonce'], 'rbf_booking' ) ) {
			wp_safe_redirect( add_query_arg( 'rbf_error', urlencode( Helpers::translate_string( 'Errore di sicurezza.' ) ), $redirect ) );
			exit;
		}
		$data    = array_map( 'sanitize_text_field', $_POST );
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'rbf_booking',
				'post_status' => 'publish',
				'post_title'  => $data['rbf_nome'] . ' ' . $data['rbf_cognome'],
				'meta_input'  => $data,
			)
		);
		if ( ! is_wp_error( $post_id ) ) {
			Mailer::send_admin_notification( $data );
			do_action( 'rbf/booking/created', $post_id, $data );
			wp_safe_redirect( add_query_arg( 'rbf_success', '1', $redirect ) );
		} else {
			wp_safe_redirect( add_query_arg( 'rbf_error', urlencode( Helpers::translate_string( 'Errore nel salvataggio.' ) ), $redirect ) );
		}
		exit;
	}
}

Submission::register();
