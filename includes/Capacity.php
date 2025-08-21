<?php
namespace RBF\Bookings;

class Capacity {
	public static function remaining_capacity( $date, $slot ) {
		$options = get_option( 'rbf_settings', Helpers::get_default_settings() );
		$total   = (int) ( $options[ 'capienza_' . $slot ] ?? 0 );
		$total   = apply_filters( 'rbf/capacity/max_people', $total, $slot );
		if ( 0 === $total ) {
			return 0;
		}
		global $wpdb;
		$taken     = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(meta_value) FROM {$wpdb->postmeta} WHERE meta_key='rbf_persone' AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type='rbf_booking' AND post_status='publish') AND post_id IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='rbf_data' AND meta_value=%s) AND post_id IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='rbf_orario' AND meta_value=%s)", $date, $slot ) );
		$remaining = max( 0, $total - (int) $taken );
		return $remaining;
	}
}

function rbf_get_remaining_capacity( $date, $slot ) {
	return Capacity::remaining_capacity( $date, $slot );
}
