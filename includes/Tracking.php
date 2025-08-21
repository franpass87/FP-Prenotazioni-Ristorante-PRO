<?php
namespace RBF\Bookings;

class Tracking {
	public static function footer_scripts() {
		$options = get_option( 'rbf_settings', Helpers::get_default_settings() );
		$ga4     = $options['ga4_id'] ?? '';
		$meta    = $options['meta_pixel_id'] ?? '';
		if ( $ga4 ) {
			echo "<script async src='https://www.googletagmanager.com/gtag/js?id=" . esc_attr( $ga4 ) . "'></script>"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','" . esc_js( $ga4 ) . "');</script>"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( $meta ) {
			echo "<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','" . esc_js( $meta ) . "');fbq('track','PageView');</script>"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}
}

add_action( 'wp_footer', array( Tracking::class, 'footer_scripts' ) );
