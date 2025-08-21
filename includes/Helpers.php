<?php
namespace RBF\Bookings;

class Helpers {
	public static function wp_timezone() {
		if ( function_exists( 'wp_timezone' ) ) {
			return wp_timezone();
		}
		$tz_string = get_option( 'timezone_string' );
		if ( $tz_string ) {
			return new \DateTimeZone( $tz_string );
		}
		$offset  = (float) get_option( 'gmt_offset', 0 );
		$hours   = (int) $offset;
		$minutes = abs( $offset - $hours ) * 60;
		$sign    = $offset < 0 ? '-' : '+';
		return new \DateTimeZone( sprintf( '%s%02d:%02d', $sign, abs( $hours ), $minutes ) );
	}

	public static function current_lang() {
		if ( function_exists( 'pll_current_language' ) ) {
			$slug = pll_current_language( 'slug' );
			return in_array( $slug, array( 'it', 'en' ), true ) ? $slug : 'en';
		}
		if ( defined( 'ICL_LANGUAGE_CODE' ) ) {
			$slug = ICL_LANGUAGE_CODE;
			return in_array( $slug, array( 'it', 'en' ), true ) ? $slug : 'en';
		}
		$slug = substr( get_locale(), 0, 2 );
		return in_array( $slug, array( 'it', 'en' ), true ) ? $slug : 'en';
	}

	public static function translate_string( $text ) {
		$locale = self::current_lang();
		if ( 'en' !== $locale ) {
			return $text;
		}
		$translations = array(
			'Prenotazioni'    => 'Bookings',
			'Prenotazione'    => 'Booking',
			'Impostazioni'    => 'Settings',
			'Scegli il pasto' => 'Choose your meal',
			'Data'            => 'Date',
			'Orario'          => 'Time',
			'Persone'         => 'Guests',
			'Nome'            => 'Name',
			'Cognome'         => 'Surname',
			'Email'           => 'Email',
			'Telefono'        => 'Phone',
			'Prenota'         => 'Book Now',
		);
		return $translations[ $text ] ?? $text;
	}

	public static function get_default_settings() {
		return array(
			'capienza_pranzo'    => 30,
			'capienza_cena'      => 40,
			'capienza_aperitivo' => 25,
			'orari_pranzo'       => '12:00,12:30,13:00,13:30,14:00',
			'orari_cena'         => '19:00,19:30,20:00,20:30',
			'orari_aperitivo'    => '17:00,17:30,18:00',
			'notification_email' => 'info@example.com',
			'closed_dates'       => '',
		);
	}

	public static function detect_source( $data = array() ) {
		$utm_source = strtolower( trim( $data['utm_source'] ?? '' ) );
		$utm_medium = strtolower( trim( $data['utm_medium'] ?? '' ) );
		if ( 'google' === $utm_source && in_array( $utm_medium, array( 'cpc', 'paid' ), true ) ) {
			return array(
				'bucket' => 'gads',
				'source' => 'google',
				'medium' => $utm_medium,
			);
		}
		if ( in_array( $utm_source, array( 'facebook', 'instagram', 'meta' ), true ) && in_array( $utm_medium, array( 'cpc', 'paid', 'ads' ), true ) ) {
			return array(
				'bucket' => 'fbads',
				'source' => $utm_source,
				'medium' => $utm_medium,
			);
		}
		return array(
			'bucket' => 'direct',
			'source' => 'direct',
			'medium' => 'none',
		);
	}

	public static function get_closed_specific( $options ) {
		$closed_dates_str = $options['closed_dates'] ?? '';
		$closed_items     = array_filter( array_map( 'trim', preg_split( '/\r?\n/', $closed_dates_str ) ) );
		$singles          = array();
		$ranges           = array();
		foreach ( $closed_items as $line ) {
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}\s*-\s*\d{4}-\d{2}-\d{2}$/', $line ) ) {
				list( $start, $end ) = array_map( 'trim', preg_split( '/\s*-\s*/', $line ) );
				$ranges[]            = array(
					'from' => $start,
					'to'   => $end,
				);
			} elseif ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $line ) ) {
				$singles[] = $line;
			}
		}
		return array(
			'singles' => $singles,
			'ranges'  => $ranges,
		);
	}
}

// Global wrappers for backward compatibility.
function rbf_wp_timezone() {
	return Helpers::wp_timezone(); }
function rbf_current_lang() {
	return Helpers::current_lang(); }
function rbf_translate_string( $text ) {
	return Helpers::translate_string( $text ); }
function rbf_get_default_settings() {
	return Helpers::get_default_settings(); }
function rbf_detect_source( $data = array() ) {
	return Helpers::detect_source( $data ); }
function rbf_get_closed_specific( $options = array() ) {
	return Helpers::get_closed_specific( $options ); }
