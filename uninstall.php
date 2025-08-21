<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'rbf_settings' );

global $wpdb;
$transients = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_rbf_%' OR option_name LIKE '_transient_timeout_rbf_%'" );
foreach ( $transients as $transient ) {
	$key = str_replace( array( '_transient_', '_transient_timeout_' ), '', $transient );
	delete_transient( $key );
}
