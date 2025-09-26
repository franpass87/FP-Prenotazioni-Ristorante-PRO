<?php
/**
 * Visual health dashboard that reuses the internal WP-CLI checks.
 *
 * @package FP_Prenotazioni_Ristorante_PRO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'rbf_register_system_health_dashboard', 12 );
function rbf_register_system_health_dashboard() {
	add_submenu_page(
		'rbf_calendar',
		rbf_translate_string( 'Stato sistema' ),
		rbf_translate_string( 'Stato sistema' ),
		rbf_get_settings_capability(),
		'rbf_system_health',
		'rbf_render_system_health_dashboard'
	);
}

/**
 * Gather status information from the same helpers used by WP-CLI commands.
 *
 * @return array<string, array>
 */
function rbf_collect_system_health_checks() {
	$checks = array();

	$errors                = rbf_get_environment_requirement_errors();
	$checks['environment'] = array(
		'label'   => rbf_translate_string( 'Requisiti ambiente' ),
		'status'  => empty( $errors ) ? 'good' : 'critical',
		'details' => empty( $errors ) ? array( rbf_translate_string( 'PHP e WordPress soddisfano i requisiti minimi.' ) ) : $errors,
	);

	$tables_ok      = true;
	$missing_tables = array();
	if ( function_exists( 'rbf_database_table_exists' ) ) {
		global $wpdb;
		if ( isset( $wpdb ) ) {
			$required_tables = array(
				$wpdb->prefix . 'rbf_areas',
				$wpdb->prefix . 'rbf_tables',
				$wpdb->prefix . 'rbf_table_groups',
				$wpdb->prefix . 'rbf_table_group_members',
				$wpdb->prefix . 'rbf_table_assignments',
			);

			foreach ( $required_tables as $table ) {
				if ( ! rbf_database_table_exists( $table ) ) {
					$tables_ok        = false;
					$missing_tables[] = $table;
				}
			}
		}
	}

	$checks['database'] = array(
		'label'   => rbf_translate_string( 'Schema database' ),
		'status'  => $tables_ok ? 'good' : 'critical',
		'details' => $tables_ok
			? array( rbf_translate_string( 'Tutte le tabelle richieste sono presenti.' ) )
			: array_map( 'esc_html', $missing_tables ),
	);

	$next_cron      = function_exists( 'wp_next_scheduled' ) ? wp_next_scheduled( 'rbf_update_booking_statuses' ) : false;
	$checks['cron'] = array(
		'label'   => rbf_translate_string( 'Eventi cron prenotazioni' ),
		'status'  => $next_cron ? 'good' : 'warning',
		'details' => $next_cron
			? array( sprintf( rbf_translate_string( 'Prossima esecuzione aggiorna stati: %s' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_cron ) ) )
			: array( rbf_translate_string( 'Nessun evento pianificato trovato. Riprogramma dal pannello.' ) ),
	);

	$tracking_status  = 'warning';
	$tracking_details = array();
	if ( function_exists( 'rbf_validate_tracking_setup' ) ) {
		$results = rbf_validate_tracking_setup();
		foreach ( $results as $result ) {
			if ( ( $result['status'] ?? '' ) === 'error' ) {
				$tracking_status    = 'critical';
				$tracking_details[] = $result['message'];
			} elseif ( ( $result['status'] ?? '' ) === 'warning' ) {
				$tracking_status    = $tracking_status === 'critical' ? 'critical' : 'warning';
				$tracking_details[] = $result['message'];
			}
		}
		if ( empty( $tracking_details ) ) {
			$tracking_status    = 'good';
			$tracking_details[] = rbf_translate_string( 'Tracking configurato correttamente o disattivato.' );
		}
	}

	$checks['tracking'] = array(
		'label'   => rbf_translate_string( 'Tracking marketing' ),
		'status'  => $tracking_status,
		'details' => $tracking_details,
	);

	$checks['email'] = array(
		'label'   => rbf_translate_string( 'Notifiche email' ),
		'status'  => is_email( rbf_get_settings()['notification_email'] ?? '' ) ? 'good' : 'warning',
		'details' => array(
			sprintf( rbf_translate_string( 'Email notifiche: %s' ), esc_html( rbf_get_settings()['notification_email'] ?? '—' ) ),
		),
	);

	$booking_page_details = array();
	$booking_page_status  = 'warning';
	$booking_page_id      = function_exists( 'rbf_detect_booking_page_id' ) ? rbf_detect_booking_page_id() : 0;

	if ( $booking_page_id > 0 ) {
		$booking_page_status = 'good';
		$title               = function_exists( 'get_the_title' ) ? get_the_title( $booking_page_id ) : '';
		$permalink           = function_exists( 'get_permalink' ) ? get_permalink( $booking_page_id ) : '';
		if ( $title !== '' ) {
			$booking_page_details[] = sprintf( rbf_translate_string( 'Pagina: %s' ), sanitize_text_field( $title ) );
		}
		if ( is_string( $permalink ) && $permalink !== '' ) {
			$booking_page_details[] = $permalink;
		}
		if ( empty( $booking_page_details ) ) {
			$booking_page_details[] = rbf_translate_string( 'Pagina prenotazioni pubblicata e rilevata.' );
		}
	} else {
		$booking_page_details[] = rbf_translate_string( 'Nessuna pagina con il modulo trovata. Usa il setup guidato o aggiungi lo shortcode [ristorante_booking_form].' );
		$settings               = rbf_get_settings();
		$configured_page_id     = absint( $settings['booking_page_id'] ?? 0 );
		if ( $configured_page_id > 0 && function_exists( 'get_post' ) ) {
			$configured_post = get_post( $configured_page_id );
			if ( $configured_post instanceof WP_Post && $configured_post->post_status !== 'publish' ) {
				$booking_page_details[] = rbf_translate_string( 'La pagina configurata esiste ma non è pubblicata.' );
			}
		}
	}

	$checks['booking_page'] = array(
		'label'   => rbf_translate_string( 'Pagina prenotazioni' ),
		'status'  => $booking_page_status,
		'details' => $booking_page_details,
	);

	$has_table_setup       = function_exists( 'rbf_table_setup_exists' ) ? rbf_table_setup_exists() : false;
	$checks['table_setup'] = array(
		'label'   => rbf_translate_string( 'Sale e tavoli' ),
		'status'  => $has_table_setup ? 'good' : 'warning',
		'details' => $has_table_setup
			? array( rbf_translate_string( 'Sono presenti tavoli pronti per le assegnazioni.' ) )
			: array( rbf_translate_string( 'Nessun tavolo configurato: crea i dati di esempio o importali dal tuo gestionale.' ) ),
	);

	return $checks;
}

/**
 * Handle quick actions (clear cache, reschedule cron, send test email).
 */
add_action( 'admin_post_rbf_health_action', 'rbf_handle_system_health_action' );
function rbf_handle_system_health_action() {
	if ( ! current_user_can( rbf_get_settings_capability() ) ) {
		wp_die( __( 'Non hai i permessi per questa operazione.', 'rbf' ) );
	}

	check_admin_referer( 'rbf_health_action' );

	$action   = isset( $_POST['action_id'] ) ? sanitize_key( $_POST['action_id'] ) : '';
	$redirect = wp_get_referer() ?: admin_url( 'admin.php?page=rbf_system_health' );

	switch ( $action ) {
		case 'verify_schema':
			if ( function_exists( 'rbf_verify_database_schema' ) ) {
				rbf_verify_database_schema();
			}
			break;
		case 'clear_cache':
			if ( function_exists( 'rbf_clear_transients' ) ) {
				rbf_clear_transients();
			}
			break;
		case 'reschedule_cron':
			if ( function_exists( 'rbf_schedule_status_updates' ) ) {
				rbf_schedule_status_updates();
			}
			break;
		case 'send_test_email':
			$recipient = rbf_get_settings()['notification_email'] ?? get_option( 'admin_email' );
			if ( $recipient && is_email( $recipient ) ) {
				wp_mail(
					$recipient,
					sprintf( '[%s] %s', get_bloginfo( 'name' ), rbf_translate_string( 'Test notifiche prenotazioni' ) ),
					rbf_translate_string( 'Questo è un messaggio di prova inviato dal pannello “Stato sistema” del plugin prenotazioni.' )
				);
			}
			break;
		case 'create_booking_page':
			if ( function_exists( 'rbf_ensure_booking_page_exists' ) ) {
				$page_result = rbf_ensure_booking_page_exists( array( 'update_settings' => true ) );
				$notice_key  = 'page-error';
				if ( ! empty( $page_result['page_id'] ) ) {
					$notice_key = ! empty( $page_result['created'] ) ? 'page-created' : 'page-updated';
				}
				$redirect = add_query_arg( 'rbf-health-notice', $notice_key, $redirect );
			}
			break;
		case 'seed_tables':
			if ( function_exists( 'rbf_table_setup_exists' ) && function_exists( 'rbf_create_default_table_setup' ) ) {
				$before = rbf_table_setup_exists();
				rbf_create_default_table_setup();
				$after = rbf_table_setup_exists();
				if ( ! $before && $after ) {
					$notice_key = 'tables-seeded';
				} elseif ( $after ) {
					$notice_key = 'tables-exist';
				} else {
					$notice_key = 'tables-error';
				}
				$redirect = add_query_arg( 'rbf-health-notice', $notice_key, $redirect );
			}
			break;
	}

	wp_safe_redirect( $redirect );
	exit;
}

/**
 * Render dashboard UI.
 */
function rbf_render_system_health_dashboard() {
	if ( ! rbf_require_settings_capability() ) {
		return;
	}

	$checks        = rbf_collect_system_health_checks();
	$recent_events = rbf_get_recent_tracking_events();

	$notice_key = isset( $_GET['rbf-health-notice'] ) ? sanitize_key( $_GET['rbf-health-notice'] ) : '';
	$notice_map = array(
		'page-created'  => array(
			'class' => 'notice-success',
			'text'  => rbf_translate_string( 'Pagina prenotazioni creata e collegata alle impostazioni.' ),
		),
		'page-updated'  => array(
			'class' => 'notice-success',
			'text'  => rbf_translate_string( 'Pagina prenotazioni aggiornata con il modulo pronto.' ),
		),
		'page-error'    => array(
			'class' => 'notice-error',
			'text'  => rbf_translate_string( 'Impossibile creare automaticamente la pagina. Verifica i permessi e riprova.' ),
		),
		'tables-seeded' => array(
			'class' => 'notice-success',
			'text'  => rbf_translate_string( 'Sale e tavoli di esempio aggiunti correttamente.' ),
		),
		'tables-exist'  => array(
			'class' => 'notice-warning',
			'text'  => rbf_translate_string( 'Esistono già tavoli configurati: nessuna modifica effettuata.' ),
		),
		'tables-error'  => array(
			'class' => 'notice-error',
			'text'  => rbf_translate_string( 'Non è stato possibile creare i tavoli di esempio. Controlla il database.' ),
		),
	);

	echo '<div class="wrap rbf-health-dashboard">';
	if ( $notice_key && isset( $notice_map[ $notice_key ] ) ) {
		$notice = $notice_map[ $notice_key ];
		echo '<div class="notice ' . esc_attr( $notice['class'] ) . '"><p>' . esc_html( $notice['text'] ) . '</p></div>';
	}
	echo '<h1>' . esc_html( rbf_translate_string( 'Stato sistema & diagnostica' ) ) . '</h1>';
	echo '<p class="description">' . esc_html( rbf_translate_string( 'Controlla requisiti, database, cron e tracking direttamente dal backoffice. I pulsanti rapidi riutilizzano gli stessi fix disponibili via WP-CLI.' ) ) . '</p>';

	echo '<div class="rbf-health-grid">';
	foreach ( $checks as $check ) {
		$status = $check['status'];
		$class  = 'rbf-health-card status-' . esc_attr( $status );
		echo '<div class="' . $class . '">';
		echo '<h2>' . esc_html( $check['label'] ) . '</h2>';
		echo '<ul>';
		foreach ( (array) $check['details'] as $detail ) {
			echo '<li>' . esc_html( $detail ) . '</li>';
		}
		echo '</ul>';
		echo '</div>';
	}
	echo '</div>';

	echo '<h2>' . esc_html( rbf_translate_string( 'Azioni rapide' ) ) . '</h2>';
	echo '<div class="rbf-health-actions">';
	$actions = array(
		array(
			'id'    => 'verify_schema',
			'label' => rbf_translate_string( 'Verifica schema DB' ),
		),
		array(
			'id'    => 'clear_cache',
			'label' => rbf_translate_string( 'Svuota cache plugin' ),
		),
		array(
			'id'    => 'reschedule_cron',
			'label' => rbf_translate_string( 'Riprogramma cron prenotazioni' ),
		),
		array(
			'id'    => 'send_test_email',
			'label' => rbf_translate_string( 'Invia email di test' ),
		),
	);

	if ( isset( $checks['booking_page'] ) && ( $checks['booking_page']['status'] ?? '' ) !== 'good' ) {
		$actions[] = array(
			'id'    => 'create_booking_page',
			'label' => rbf_translate_string( 'Crea pagina prenotazioni' ),
		);
	}

	if ( isset( $checks['table_setup'] ) && ( $checks['table_setup']['status'] ?? '' ) !== 'good' ) {
		$actions[] = array(
			'id'    => 'seed_tables',
			'label' => rbf_translate_string( 'Popola tavoli di esempio' ),
		);
	}

	foreach ( $actions as $action ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="rbf-health-action-form">';
		echo '<input type="hidden" name="action" value="rbf_health_action" />';
		echo '<input type="hidden" name="action_id" value="' . esc_attr( $action['id'] ) . '" />';
		wp_nonce_field( 'rbf_health_action' );
		submit_button( $action['label'], 'secondary', 'submit', false );
		echo '</form>';
	}
	echo '</div>';

	echo '<h2>' . esc_html( rbf_translate_string( 'Ultimi eventi di tracking' ) ) . '</h2>';
	if ( empty( $recent_events ) ) {
		echo '<p>' . esc_html( rbf_translate_string( 'Ancora nessun evento registrato.' ) ) . '</p>';
	} else {
		echo '<table class="widefat striped">';
		echo '<thead><tr><th>' . esc_html( rbf_translate_string( 'Canale' ) ) . '</th><th>' . esc_html( rbf_translate_string( 'Evento' ) ) . '</th><th>' . esc_html( rbf_translate_string( 'Data' ) ) . '</th><th>' . esc_html( rbf_translate_string( 'Dettagli' ) ) . '</th></tr></thead>';
		echo '<tbody>';
		foreach ( $recent_events as $event ) {
			$time = isset( $event['time'] ) ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $event['time'] ) : '—';
			echo '<tr>';
			echo '<td>' . esc_html( $event['channel'] ?? '-' ) . '</td>';
			echo '<td><code>' . esc_html( $event['event'] ?? '-' ) . '</code></td>';
			echo '<td>' . esc_html( $time ) . '</td>';
			$details = '';
			if ( ! empty( $event['context'] ) ) {
				$pairs = array();
				foreach ( $event['context'] as $ctx_key => $ctx_value ) {
					$pairs[] = esc_html( $ctx_key ) . ': ' . esc_html( $ctx_value );
				}
				$details = implode( '<br>', $pairs );
			}
			echo '<td>' . ( $details ? $details : '—' ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	echo '</div>';
}
