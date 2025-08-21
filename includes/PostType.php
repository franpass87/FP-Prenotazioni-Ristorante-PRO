<?php
namespace RBF\Bookings;

class PostType {
	public static function register() {
		register_post_type(
			'rbf_booking',
			array(
				'labels'       => array(
					'name'          => Helpers::translate_string( 'Prenotazioni' ),
					'singular_name' => Helpers::translate_string( 'Prenotazione' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'menu_icon'    => 'dashicons-calendar-alt',
				'supports'     => array( 'title', 'custom-fields' ),
				'show_in_menu' => 'rbf_bookings_menu',
			)
		);
	}

	public static function add_columns( $columns ) {
		$columns['rbf_source_bucket'] = __( 'Fonte (bucket)', 'rbf' );
		return $columns;
	}

	public static function render_columns( $column, $post_id ) {
		if ( 'rbf_source_bucket' === $column ) {
			echo esc_html( get_post_meta( $post_id, 'rbf_source_bucket', true ) );
		}
	}
}

add_action( 'init', array( PostType::class, 'register' ) );
add_filter( 'manage_rbf_booking_posts_columns', array( PostType::class, 'add_columns' ) );
add_action( 'manage_rbf_booking_posts_custom_column', array( PostType::class, 'render_columns' ), 10, 2 );
