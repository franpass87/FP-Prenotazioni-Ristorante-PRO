<?php
/**
 * Accessibility checker integrated in the admin area.
 *
 * @package FP_Prenotazioni_Ristorante_PRO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'rbf_register_accessibility_checker', 13 );
function rbf_register_accessibility_checker() {
	add_submenu_page(
		'rbf_calendar',
		rbf_translate_string( 'Accessibility checker' ),
		rbf_translate_string( 'Accessibilità' ),
		rbf_get_settings_capability(),
		'rbf_accessibility_checker',
		'rbf_render_accessibility_checker_page'
	);
}

/**
 * Calculate relative luminance of a hex color.
 *
 * @param string $hex Hex color (#rrggbb).
 * @return float
 */
function rbf_calc_luminance( $hex ) {
	$hex = ltrim( $hex, '#' );
	if ( strlen( $hex ) === 3 ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	$r = hexdec( substr( $hex, 0, 2 ) ) / 255;
	$g = hexdec( substr( $hex, 2, 2 ) ) / 255;
	$b = hexdec( substr( $hex, 4, 2 ) ) / 255;

	$values = array( $r, $g, $b );
	foreach ( $values as &$value ) {
		$value = ( $value <= 0.03928 ) ? $value / 12.92 : pow( ( $value + 0.055 ) / 1.055, 2.4 );
	}

	return 0.2126 * $values[0] + 0.7152 * $values[1] + 0.0722 * $values[2];
}

/**
 * Calculate contrast ratio between two colors.
 *
 * @param string $color_a Hex color.
 * @param string $color_b Hex color.
 * @return float
 */
function rbf_calc_contrast_ratio( $color_a, $color_b ) {
	$lum_a = rbf_calc_luminance( $color_a );
	$lum_b = rbf_calc_luminance( $color_b );

	$lighter = max( $lum_a, $lum_b );
	$darker  = min( $lum_a, $lum_b );

	return ( $lighter + 0.05 ) / ( $darker + 0.05 );
}

function rbf_render_accessibility_checker_page() {
	if ( ! rbf_require_settings_capability() ) {
		return;
	}

	$settings = rbf_get_settings();
	$brand    = rbf_get_brand_config();

	$accent                      = $brand['accent_color'] ?? '#000000';
	$secondary                   = $brand['secondary_color'] ?? '#f8b500';
	$contrast_accent_on_white    = rbf_calc_contrast_ratio( $accent, '#ffffff' );
	$contrast_accent_on_dark     = rbf_calc_contrast_ratio( $accent, '#1f2937' );
	$contrast_secondary_on_white = rbf_calc_contrast_ratio( $secondary, '#ffffff' );

	$checks   = array();
	$checks[] = array(
		'label'  => rbf_translate_string( 'Contrasto colore primario su fondo bianco' ),
		'score'  => $contrast_accent_on_white,
		'passed' => $contrast_accent_on_white >= 4.5,
		'detail' => sprintf( rbf_translate_string( 'Rapporto %.2f:1 (obiettivo WCAG AA testi normali ≥ 4.5:1).' ), $contrast_accent_on_white ),
	);
	$checks[] = array(
		'label'  => rbf_translate_string( 'Contrasto colore primario su fondo scuro' ),
		'score'  => $contrast_accent_on_dark,
		'passed' => $contrast_accent_on_dark >= 3,
		'detail' => sprintf( rbf_translate_string( 'Rapporto %.2f:1 (bottoni su background scuro).' ), $contrast_accent_on_dark ),
	);
	$checks[] = array(
		'label'  => rbf_translate_string( 'Contrasto colore secondario' ),
		'score'  => $contrast_secondary_on_white,
		'passed' => $contrast_secondary_on_white >= 3,
		'detail' => sprintf( rbf_translate_string( 'Rapporto %.2f:1 per badge/alert.' ), $contrast_secondary_on_white ),
	);

	$checks[] = array(
		'label'  => rbf_translate_string( 'Nome brand impostato' ),
		'score'  => ! empty( $settings['brand_name'] ),
		'passed' => ! empty( $settings['brand_name'] ),
		'detail' => ! empty( $settings['brand_name'] ) ? esc_html( $settings['brand_name'] ) : rbf_translate_string( 'Imposta il nome del brand per facilitarne la lettura dagli screen reader.' ),
	);

	$checks[] = array(
		'label'  => rbf_translate_string( 'Logo caricato' ),
		'score'  => ! empty( $settings['brand_logo_url'] ),
		'passed' => ! empty( $settings['brand_logo_url'] ),
		'detail' => ! empty( $settings['brand_logo_url'] ) ? esc_url( $settings['brand_logo_url'] ) : rbf_translate_string( 'Aggiungi un logo per l\'anteprima live e per i canali di conferma.' ),
	);

	$checks[] = array(
		'label'  => rbf_translate_string( 'Font heading differenziato' ),
		'score'  => $settings['brand_font_heading'] ?? 'system',
		'passed' => ( $settings['brand_font_heading'] ?? 'system' ) !== 'system',
		'detail' => rbf_translate_string( 'Usa un font differenziato per titoli per migliorare la gerarchia visiva (opzionale).' ),
	);

	echo '<div class="wrap rbf-accessibility-checker">';
	echo '<h1>' . esc_html( rbf_translate_string( 'Accessibility checker' ) ) . '</h1>';
	echo '<p class="description">' . esc_html( rbf_translate_string( 'Verifica contrasto, branding e checklist contenutistica direttamente dal backoffice.' ) ) . '</p>';

	echo '<div class="rbf-accessibility-grid">';
	foreach ( $checks as $check ) {
		$passed = ! empty( $check['passed'] );
		$class  = $passed ? 'passed' : 'warning';
		echo '<div class="rbf-accessibility-card ' . esc_attr( $class ) . '">';
		echo '<h2>' . esc_html( $check['label'] ) . '</h2>';
		echo '<p>' . esc_html( $check['detail'] ) . '</p>';
		echo '</div>';
	}
	echo '</div>';

	echo '<h2>' . esc_html( rbf_translate_string( 'Checklist contenuti' ) ) . '</h2>';
	echo '<ul class="rbf-accessibility-list">';
	echo '<li>' . esc_html( rbf_translate_string( 'Verifica che ogni tooltip e testo di aiuto sia comprensibile senza riferimenti visivi.' ) ) . '</li>';
	echo '<li>' . esc_html( rbf_translate_string( 'Assicurati che le etichette dei campi descrivano il contenuto (es. “Numero di persone”).' ) ) . '</li>';
	echo '<li>' . esc_html( rbf_translate_string( 'Controlla che la CTA finale indichi l\'azione (es. “Completa prenotazione”).' ) ) . '</li>';
	echo '<li>' . esc_html( rbf_translate_string( 'Testa la navigazione da tastiera nel frontend usando il tasto TAB.' ) ) . '</li>';
	echo '</ul>';

	echo '<h2>' . esc_html( rbf_translate_string( 'Come testare in frontend' ) ) . '</h2>';
	echo '<ol class="rbf-accessibility-list">';
	echo '<li>' . esc_html( rbf_translate_string( 'Apri il form di prenotazione e attiva la modalità lettore schermo (VoiceOver/NVDA).' ) ) . '</li>';
	echo '<li>' . esc_html( rbf_translate_string( 'Verifica che il focus visivo sia sempre evidente quando ci si sposta con TAB.' ) ) . '</li>';
	echo '<li>' . esc_html( rbf_translate_string( 'Riduci la finestra o usa un simulatore mobile per verificare lo zoom del browser.' ) ) . '</li>';
	echo '<li>' . esc_html( rbf_translate_string( 'Utilizza uno strumento come Lighthouse per verifiche aggiuntive.' ) ) . '</li>';
	echo '</ol>';

	echo '</div>';
}
