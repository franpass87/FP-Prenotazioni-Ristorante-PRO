<?php
namespace RBF\Bookings;

class Mailer {
	public static function send_admin_notification( $data ) {
		$options = get_option( 'rbf_settings', Helpers::get_default_settings() );
		$to      = $options['notification_email'];
		$cc      = apply_filters( 'rbf/notification_cc_email', 'francesco.passeri@gmail.com', $data );
		$subject = 'Nuova Prenotazione';
		$body    = "Prenotazione per {$data['rbf_nome']} {$data['rbf_cognome']}";
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		if ( is_array( $cc ) ) {
			foreach ( $cc as $email ) {
				$headers[] = 'Cc: ' . $email;
			}
		} else {
			$headers[] = 'Cc: ' . $cc;
		}
		wp_mail( $to, $subject, $body, $headers );
	}
}
