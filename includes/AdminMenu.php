<?php
namespace RBF\Bookings;

class AdminMenu {
	public static function register() {
		add_menu_page(
			Helpers::translate_string( 'Prenotazioni' ),
			Helpers::translate_string( 'Prenotazioni' ),
			'manage_options',
			'rbf_bookings_menu',
			null,
			'dashicons-calendar-alt'
		);
		add_submenu_page( 'rbf_bookings_menu', Helpers::translate_string( 'Impostazioni' ), Helpers::translate_string( 'Impostazioni' ), 'manage_options', 'rbf_settings', array( __CLASS__, 'settings_page' ) );
	}

	public static function enqueue_admin_assets( $hook ) {
		if ( false !== strpos( $hook, 'rbf_bookings_menu' ) ) {
			wp_enqueue_style( 'rbf-admin', plugins_url( 'assets/css/admin.css', __DIR__ ) );
			wp_enqueue_script( 'rbf-admin', plugins_url( 'assets/js/admin.js', __DIR__ ), array(), '1.0', true );
		}
	}

	public static function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$options = get_option( 'rbf_settings', Helpers::get_default_settings() );
		?>
		<div class="rbf-admin-wrap">
			<h1><?php echo esc_html( Helpers::translate_string( 'Impostazioni' ) ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'rbf_opts_group' ); ?>
				<table class="form-table">
					<tr>
						<th><label for="rbf_notification_email"><?php esc_html_e( 'Email per Notifiche Ristorante', 'rbf' ); ?></label></th>
						<td><input type="email" id="rbf_notification_email" name="rbf_settings[notification_email]" value="<?php echo esc_attr( $options['notification_email'] ); ?>" /></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}

add_action( 'admin_menu', array( AdminMenu::class, 'register' ) );
add_action( 'admin_enqueue_scripts', array( AdminMenu::class, 'enqueue_admin_assets' ) );
