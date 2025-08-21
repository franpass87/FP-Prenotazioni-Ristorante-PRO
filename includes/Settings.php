<?php
namespace RBF\Bookings;

class Settings {
	public static function register() {
		register_setting(
			'rbf_opts_group',
			'rbf_settings',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => Helpers::get_default_settings(),
			)
		);
	}

	public static function sanitize( $input ) {
		$defaults                     = Helpers::get_default_settings();
		$output                       = array();
		$output['notification_email'] = isset( $input['notification_email'] ) ? sanitize_email( $input['notification_email'] ) : $defaults['notification_email'];
		$output['closed_dates']       = isset( $input['closed_dates'] ) ? sanitize_textarea_field( $input['closed_dates'] ) : '';
		$int_keys                     = array( 'capienza_pranzo', 'capienza_cena', 'capienza_aperitivo' );
		foreach ( $int_keys as $key ) {
			$output[ $key ] = isset( $input[ $key ] ) ? absint( $input[ $key ] ) : $defaults[ $key ];
		}
		$text_keys = array( 'orari_pranzo', 'orari_cena', 'orari_aperitivo' );
		foreach ( $text_keys as $key ) {
			$output[ $key ] = isset( $input[ $key ] ) ? sanitize_text_field( $input[ $key ] ) : $defaults[ $key ];
		}
		return $output;
	}

	public static function plugin_action_links( $links ) {
		$url     = admin_url( 'admin.php?page=rbf_settings' );
		$links[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'rbf' ) . '</a>';
		return $links;
	}
}

add_action( 'admin_init', array( Settings::class, 'register' ) );
add_filter( 'plugin_action_links_fp-prenotazioni-ristorante-pro/fp-prenotazioni-ristorante-pro.php', array( Settings::class, 'plugin_action_links' ) );
