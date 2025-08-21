<?php
namespace RBF\Bookings;

class Shortcode {
	public static function register() {
		add_shortcode( 'ristorante_booking_form', array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function assets() {
		global $post;
		if ( ! is_singular() || ! has_shortcode( $post->post_content, 'ristorante_booking_form' ) ) {
			return;
		}
		wp_enqueue_style( 'rbf-frontend', plugins_url( 'assets/css/frontend.css', __DIR__ ), array(), '1.0' );
		wp_enqueue_script( 'rbf-frontend', plugins_url( 'assets/js/frontend.js', __DIR__ ), array( 'jquery' ), '1.0', true );
		wp_localize_script(
			'rbf-frontend',
			'rbfData',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'rbf_ajax_nonce' ),
				'locale'        => Helpers::current_lang(),
				'closedDays'    => array(),
				'closedSingles' => array(),
				'closedRanges'  => array(),
				'utilsScript'   => 'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/js/utils.js',
				'labels'        => array(
					'loading'            => Helpers::translate_string( 'Caricamento...' ),
					'chooseTime'         => Helpers::translate_string( 'Scegli un orario...' ),
					'noTime'             => Helpers::translate_string( 'Nessun orario disponibile' ),
					'invalidPhone'       => Helpers::translate_string( 'Il numero di telefono inserito non è valido.' ),
					'sundayBrunchNotice' => Helpers::translate_string( 'Di Domenica il servizio è Brunch con menù alla carta.' ),
					'privacyRequired'    => Helpers::translate_string( 'Devi accettare la Privacy Policy per procedere.' ),
				),
			)
		);
	}

	public static function render() {
		ob_start();
		?>
		<div class="rbf-form-container">
			<div id="rbf-message-anchor"></div>
			<form id="rbf-form" class="rbf-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="rbf_submit_booking" />
				<?php wp_nonce_field( 'rbf_booking', 'rbf_nonce' ); ?>
				<div id="step-meal" class="rbf-step">
					<label><?php echo esc_html( Helpers::translate_string( 'Scegli il pasto' ) ); ?></label>
					<div class="rbf-radio-group">
						<input type="radio" name="rbf_meal" value="pranzo" id="rbf_meal_pranzo" required />
						<label for="rbf_meal_pranzo"><?php echo esc_html( Helpers::translate_string( 'Pranzo' ) ); ?></label>
						<input type="radio" name="rbf_meal" value="aperitivo" id="rbf_meal_aperitivo" required />
						<label for="rbf_meal_aperitivo"><?php echo esc_html( Helpers::translate_string( 'Aperitivo' ) ); ?></label>
						<input type="radio" name="rbf_meal" value="cena" id="rbf_meal_cena" required />
						<label for="rbf_meal_cena"><?php echo esc_html( Helpers::translate_string( 'Cena' ) ); ?></label>
					</div>
					<p id="rbf-meal-notice" style="display:none;"></p>
				</div>
				<div id="step-date" class="rbf-step" style="display:none;">
					<label for="rbf-date"><?php echo esc_html( Helpers::translate_string( 'Data' ) ); ?></label>
					<input id="rbf-date" name="rbf_data" readonly="readonly" required />
				</div>
				<div id="step-time" class="rbf-step" style="display:none;">
					<label for="rbf-time"><?php echo esc_html( Helpers::translate_string( 'Orario' ) ); ?></label>
					<select id="rbf-time" name="rbf_orario" required disabled>
						<option value=""><?php echo esc_html( Helpers::translate_string( 'Prima scegli la data' ) ); ?></option>
					</select>
				</div>
				<div id="step-people" class="rbf-step" style="display:none;">
					<label><?php echo esc_html( Helpers::translate_string( 'Persone' ) ); ?></label>
					<div class="rbf-people-selector">
						<button type="button" id="rbf-people-minus" disabled>-</button>
						<input type="number" id="rbf-people" name="rbf_persone" value="1" min="1" readonly="readonly" required />
						<button type="button" id="rbf-people-plus">+</button>
					</div>
				</div>
				<div id="step-details" class="rbf-step" style="display:none;">
					<label for="rbf-name"><?php echo esc_html( Helpers::translate_string( 'Nome' ) ); ?></label>
					<input type="text" id="rbf-name" name="rbf_nome" required disabled />
					<label for="rbf-surname"><?php echo esc_html( Helpers::translate_string( 'Cognome' ) ); ?></label>
					<input type="text" id="rbf-surname" name="rbf_cognome" required disabled />
					<label for="rbf-email"><?php echo esc_html( Helpers::translate_string( 'Email' ) ); ?></label>
					<input type="email" id="rbf-email" name="rbf_email" required disabled />
					<label for="rbf-tel"><?php echo esc_html( Helpers::translate_string( 'Telefono' ) ); ?></label>
					<input type="tel" id="rbf-tel" name="rbf_tel" required disabled />
					<div class="rbf-checkbox-group">
						<label>
							<input type="checkbox" id="rbf-privacy" name="rbf_privacy" value="yes" required disabled />
							<?php echo esc_html( Helpers::translate_string( 'Acconsento al trattamento dei dati secondo l’Informativa sulla Privacy' ) ); ?>
						</label>
					</div>
				</div>
				<input type="hidden" name="rbf_lang" value="<?php echo esc_attr( Helpers::current_lang() ); ?>" />
				<button id="rbf-submit" type="submit" disabled style="display:none;">
					<?php echo esc_html( Helpers::translate_string( 'Prenota' ) ); ?>
				</button>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}
}

Shortcode::register();
