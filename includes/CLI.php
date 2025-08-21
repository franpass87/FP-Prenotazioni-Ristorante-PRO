<?php
namespace RBF\Bookings;

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	class CLI {
		public static function register() {
			\WP_CLI::add_command( 'rbf export', array( __CLASS__, 'export' ) );
		}

		public static function export( $args, $assoc_args ) {
			// stub: output header only
			\WP_CLI::line( 'id,date,time,people' );
		}
	}
	CLI::register();
}
