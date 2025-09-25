<?php
/**
 * Site Health integration for FP Prenotazioni Ristorante.
 *
 * Registers custom Site Health tests to highlight potential
 * misconfigurations before going live in production.
 *
 * @package FP_Prenotazioni_Ristorante_PRO
 */

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('rbf_site_health_register_tests')) {
    /**
     * Register custom Site Health tests.
     *
     * @param array $tests Existing Site Health tests.
     * @return array
     */
    function rbf_site_health_register_tests($tests) {
        if (!is_array($tests)) {
            $tests = [];
        }

        if (!isset($tests['direct'])) {
            $tests['direct'] = [];
        }

        $tests['direct']['rbf_database_schema'] = [
            'label' => rbf_translate_string('Database Prenotazioni'),
            'test'  => 'rbf_site_health_database_schema_test',
        ];

        $tests['direct']['rbf_cron_events'] = [
            'label' => rbf_translate_string('Eventi Pianificati Prenotazioni'),
            'test'  => 'rbf_site_health_cron_events_test',
        ];

        $tests['direct']['rbf_email_configuration'] = [
            'label' => rbf_translate_string('Configurazione Notifiche Email'),
            'test'  => 'rbf_site_health_email_configuration_test',
        ];

        $tests['direct']['rbf_tracking_configuration'] = [
            'label' => rbf_translate_string('Configurazione Tracking Marketing'),
            'test'  => 'rbf_site_health_tracking_configuration_test',
        ];

        $tests['direct']['rbf_booking_page'] = [
            'label' => rbf_translate_string('Pagina di Prenotazione'),
            'test'  => 'rbf_site_health_booking_page_test',
        ];

        return $tests;
    }

    add_filter('site_status_tests', 'rbf_site_health_register_tests');
}

if (!function_exists('rbf_site_health_build_result')) {
    /**
     * Helper to build a Site Health response using plugin defaults.
     *
     * @param string $status      Result status (good|recommended|critical).
     * @param string $label       Short label describing the test.
     * @param string $description HTML description of the result.
     * @param string $actions     Optional HTML with action links.
     * @param string $test        The executing test callback name.
     * @return array
     */
    function rbf_site_health_build_result($status, $label, $description, $actions = '', $test = '') {
        if (!in_array($status, ['good', 'recommended', 'critical'], true)) {
            $status = 'recommended';
        }

        $result = [
            'label' => $label,
            'status' => $status,
            'badge' => [
                'label' => rbf_translate_string('Prenotazioni Ristorante'),
                'color' => 'blue',
            ],
            'description' => function_exists('wp_kses_post') ? wp_kses_post($description) : $description,
        ];

        if ($actions !== '') {
            $result['actions'] = function_exists('wp_kses_post') ? wp_kses_post($actions) : $actions;
        }

        if ($test !== '') {
            $result['test'] = $test;
        }

        return $result;
    }
}

if (!function_exists('rbf_site_health_tracking_configuration_test')) {
    /**
     * Validate that at least one marketing tracking integration is fully configured.
     *
     * Highlights misconfigurations for GA4, GTM Hybrid, Facebook Pixel/Conversion API,
     * and Google Ads conversion tracking so the marketing funnel works in production.
     *
     * @return array
     */
    function rbf_site_health_tracking_configuration_test() {
        $settings = rbf_get_settings();

        $ga4_id            = trim((string) ($settings['ga4_id'] ?? ''));
        $ga4_api_secret    = trim((string) ($settings['ga4_api_secret'] ?? ''));
        $gtm_id            = trim((string) ($settings['gtm_id'] ?? ''));
        $gtm_hybrid        = ($settings['gtm_hybrid'] ?? '') === 'yes';
        $meta_pixel_id     = trim((string) ($settings['meta_pixel_id'] ?? ''));
        $meta_token        = trim((string) ($settings['meta_access_token'] ?? ''));
        $google_ads_id     = trim((string) ($settings['google_ads_conversion_id'] ?? ''));
        $google_ads_label  = trim((string) ($settings['google_ads_conversion_label'] ?? ''));

        $has_any_tracking = ($ga4_id !== '') || ($gtm_id !== '') || ($meta_pixel_id !== '') || ($google_ads_id !== '' && $google_ads_label !== '');

        $issues = [];

        if (!$has_any_tracking) {
            $description = '<p>' . rbf_translate_string('Nessuna integrazione di tracking è configurata. Senza un sistema di analytics non sarà possibile misurare le conversioni delle prenotazioni.') . '</p>';

            $actions = '';
            if (function_exists('admin_url')) {
                $actions = sprintf(
                    '<p><a href="%s" class="button button-primary">%s</a></p>',
                    esc_url(admin_url('admin.php?page=rbf_settings#tracking')),
                    esc_html(rbf_translate_string('Configura Tracking'))
                );
            }

            return rbf_site_health_build_result(
                'critical',
                rbf_translate_string('Configurazione Tracking Marketing'),
                $description,
                $actions,
                __FUNCTION__
            );
        }

        if ($gtm_hybrid && ($gtm_id === '' || $ga4_id === '')) {
            $issues[] = rbf_translate_string('La modalità ibrida GTM è attiva ma manca l\'ID GTM o l\'ID GA4. Disattiva la modalità ibrida oppure completa entrambe le configurazioni.');
        }

        if ($ga4_id !== '' && $ga4_api_secret === '') {
            $issues[] = rbf_translate_string('GA4 è configurato ma manca l\'API Secret per il tracciamento server-side.');
        }

        if ($ga4_id === '' && $ga4_api_secret !== '') {
            $issues[] = rbf_translate_string('È stata inserita un\'API Secret GA4 senza Measurement ID. Aggiungi l\'ID GA4 per abilitare il tracciamento.');
        }

        if ($meta_pixel_id !== '' && $meta_token === '') {
            $issues[] = rbf_translate_string('Il Pixel Meta è configurato ma manca l\'Access Token per la Conversion API.');
        }

        if (($google_ads_id !== '' && $google_ads_label === '') || ($google_ads_id === '' && $google_ads_label !== '')) {
            $issues[] = rbf_translate_string('Il tracking delle conversioni Google Ads è incompleto. Specifica sia Conversion ID che Conversion Label.');
        }

        if (!empty($issues)) {
            $description = '<p>' . rbf_translate_string('Sono richiesti alcuni interventi sulla configurazione del tracking:') . '</p><ul>';

            foreach ($issues as $issue) {
                $description .= '<li>' . esc_html($issue) . '</li>';
            }

            $description .= '</ul>';

            $actions = '';
            if (function_exists('admin_url')) {
                $actions = sprintf(
                    '<p><a href="%s" class="button">%s</a></p>',
                    esc_url(admin_url('admin.php?page=rbf_settings#tracking')),
                    esc_html(rbf_translate_string('Apri impostazioni tracking'))
                );
            }

            return rbf_site_health_build_result(
                'recommended',
                rbf_translate_string('Configurazione Tracking Marketing'),
                $description,
                $actions,
                __FUNCTION__
            );
        }

        $description = '<p>' . rbf_translate_string('Almeno un sistema di tracking è configurato correttamente e pronto per l\'ambiente di produzione.') . '</p>';

        if ($ga4_id !== '' && $ga4_api_secret !== '') {
            $description .= '<p>' . rbf_translate_string('GA4 invierà eventi sia lato client che lato server.') . '</p>';
        }

        if ($meta_pixel_id !== '' && $meta_token !== '') {
            $description .= '<p>' . rbf_translate_string('Il Pixel Meta utilizza anche la Conversion API come failover.') . '</p>';
        }

        if ($google_ads_id !== '' && $google_ads_label !== '') {
            $description .= '<p>' . rbf_translate_string('Le conversioni Google Ads sono pronte per il monitoraggio delle campagne.') . '</p>';
        }

        return rbf_site_health_build_result(
            'good',
            rbf_translate_string('Configurazione Tracking Marketing'),
            $description,
            '',
            __FUNCTION__
        );
    }
}

if (!function_exists('rbf_site_health_database_schema_test')) {
    /**
     * Ensure the booking database schema exists.
     *
     * @return array
     */
    function rbf_site_health_database_schema_test() {
        global $wpdb;

        if (!isset($wpdb)) {
            return rbf_site_health_build_result(
                'recommended',
                rbf_translate_string('Database Prenotazioni'),
                '<p>' . rbf_translate_string('Impossibile verificare il database durante l\'installazione.') . '</p>',
                '',
                __FUNCTION__
            );
        }

        $tables = [
            $wpdb->prefix . 'rbf_areas',
            $wpdb->prefix . 'rbf_tables',
            $wpdb->prefix . 'rbf_table_groups',
            $wpdb->prefix . 'rbf_table_group_members',
            $wpdb->prefix . 'rbf_table_assignments',
        ];

        if (function_exists('apply_filters')) {
            $tables = (array) apply_filters('rbf_site_health_required_tables', $tables);
        }

        $missing = array_filter($tables, function($table) {
            return !rbf_database_table_exists($table);
        });

        if (empty($missing)) {
            return rbf_site_health_build_result(
                'good',
                rbf_translate_string('Database Prenotazioni'),
                '<p>' . rbf_translate_string('Tutte le tabelle richieste dal sistema prenotazioni sono presenti.') . '</p>',
                '',
                __FUNCTION__
            );
        }

        if (function_exists('rbf_log')) {
            rbf_log('RBF Site Health: Missing tables detected - ' . implode(', ', $missing));
        }

        $description = sprintf(
            '<p>%s</p>',
            rbf_translate_string('Una o più tabelle del database prenotazioni non sono presenti. Il sistema potrebbe non registrare correttamente le prenotazioni.')
        );

        $actions = '';
        if (function_exists('admin_url')) {
            $actions = sprintf(
                '<p><a href="%s" class="button button-primary">%s</a></p>',
                esc_url(admin_url('admin.php?page=rbf_tables')),
                esc_html(rbf_translate_string('Apri Gestione Tavoli'))
            );
        }

        return rbf_site_health_build_result(
            'critical',
            rbf_translate_string('Database Prenotazioni'),
            $description,
            $actions,
            __FUNCTION__
        );
    }
}

if (!function_exists('rbf_site_health_cron_events_test')) {
    /**
     * Validate that the daily status cron event is scheduled.
     *
     * @return array
     */
    function rbf_site_health_cron_events_test() {
        $timestamp = function_exists('wp_next_scheduled') ? wp_next_scheduled('rbf_update_booking_statuses') : false;

        if (!$timestamp && function_exists('rbf_schedule_status_updates')) {
            rbf_schedule_status_updates();
            $timestamp = wp_next_scheduled('rbf_update_booking_statuses');
        }

        if (!$timestamp) {
            $description = '<p>' . rbf_translate_string('L\'evento pianificato per aggiornare automaticamente lo stato delle prenotazioni non è attivo.') . '</p>';
            $actions = '';

            if (function_exists('admin_url')) {
                $actions = sprintf(
                    '<p><a href="%s" class="button">%s</a></p>',
                    esc_url(admin_url('admin.php?page=rbf_settings')),
                    esc_html(rbf_translate_string('Verifica impostazioni cron'))
                );
            }

            return rbf_site_health_build_result(
                'recommended',
                rbf_translate_string('Eventi Pianificati Prenotazioni'),
                $description,
                $actions,
                __FUNCTION__
            );
        }

        $formatted_time = function_exists('get_date_from_gmt')
            ? get_date_from_gmt(gmdate('Y-m-d H:i:s', $timestamp), get_option('date_format') . ' ' . get_option('time_format'))
            : date('Y-m-d H:i', $timestamp);

        $description = sprintf(
            '<p>%s <strong>%s</strong>.</p>',
            rbf_translate_string('Il prossimo controllo automatico delle prenotazioni è pianificato per'),
            esc_html($formatted_time)
        );

        return rbf_site_health_build_result(
            'good',
            rbf_translate_string('Eventi Pianificati Prenotazioni'),
            $description,
            '',
            __FUNCTION__
        );
    }
}

if (!function_exists('rbf_site_health_email_configuration_test')) {
    /**
     * Validate the notification email configuration.
     *
     * @return array
     */
    function rbf_site_health_email_configuration_test() {
        $settings = rbf_get_settings();

        $brevo_configured = !empty($settings['brevo_api']);
        $admin_email = $settings['notification_email'] ?? '';
        $fallback_email = $settings['webmaster_email'] ?? '';
        $has_admin_email = !empty($admin_email) || !empty($fallback_email);

        $log_table_ready = true;
        if (function_exists('rbf_ensure_email_log_table')) {
            $log_table_status = rbf_ensure_email_log_table();
            $log_table_ready = $log_table_status !== 'failed';
        } elseif (function_exists('rbf_database_table_exists')) {
            global $wpdb;
            $table_name = isset($wpdb) ? $wpdb->prefix . 'rbf_email_notifications' : 'rbf_email_notifications';
            $log_table_ready = isset($wpdb) ? rbf_database_table_exists($table_name) : true;
        }

        $retention_days = function_exists('rbf_get_email_log_retention_days')
            ? rbf_get_email_log_retention_days()
            : (defined('RBF_EMAIL_LOG_DEFAULT_RETENTION_DAYS') ? (int) RBF_EMAIL_LOG_DEFAULT_RETENTION_DAYS : 90);

        $cleanup_scheduled = function_exists('wp_next_scheduled')
            ? (bool) wp_next_scheduled('rbf_cleanup_email_notifications_event')
            : false;

        if (!$cleanup_scheduled && function_exists('rbf_schedule_email_log_cleanup')) {
            rbf_schedule_email_log_cleanup();
            $cleanup_scheduled = function_exists('wp_next_scheduled')
                ? (bool) wp_next_scheduled('rbf_cleanup_email_notifications_event')
                : false;
        }

        if ($brevo_configured && $has_admin_email) {
            if (!$log_table_ready) {
                $actions = '';
                if (function_exists('admin_url')) {
                    $actions = sprintf(
                        '<p><a href="%s" class="button button-primary">%s</a></p>',
                        esc_url(admin_url('admin.php?page=rbf_email_notifications')),
                        esc_html(rbf_translate_string('Apri log notifiche email'))
                    );
                }

                $description = '<p>' . rbf_translate_string('La tabella di log delle notifiche email non è disponibile. Il sistema di failover non potrà registrare gli invii.') . '</p>';

                return rbf_site_health_build_result(
                    'critical',
                    rbf_translate_string('Configurazione Notifiche Email'),
                    $description,
                    $actions,
                    __FUNCTION__
                );
            }

            $issues = [];

            if (!$cleanup_scheduled) {
                $issues[] = sprintf(
                    rbf_translate_string('La pulizia automatica dei log non è attiva. I log più vecchi di %d giorni potrebbero accumularsi.'),
                    $retention_days
                );
            }

            if (!empty($issues)) {
                $description = '<p>' . rbf_translate_string('Le notifiche email funzionano ma sono richiesti alcuni interventi:') . '</p><ul>';
                foreach ($issues as $issue) {
                    $description .= '<li>' . esc_html($issue) . '</li>';
                }
                $description .= '</ul>';

                $actions = '';
                if (function_exists('admin_url')) {
                    $actions = sprintf(
                        '<p><a href="%s" class="button">%s</a></p>',
                        esc_url(admin_url('admin.php?page=rbf_settings#email')),
                        esc_html(rbf_translate_string('Configura notifiche email'))
                    );
                }

                return rbf_site_health_build_result(
                    'recommended',
                    rbf_translate_string('Configurazione Notifiche Email'),
                    $description,
                    $actions,
                    __FUNCTION__
                );
            }

            $description = '<p>' . rbf_translate_string('Le notifiche email sono configurate correttamente con provider primario, fallback e registrazione log.') . '</p>';
            $description .= sprintf(
                '<p>%s</p>',
                esc_html(
                    sprintf(
                        rbf_translate_string('I log delle notifiche vengono puliti automaticamente ogni giorno (retention: %d giorni).'),
                        $retention_days
                    )
                )
            );

            return rbf_site_health_build_result(
                'good',
                rbf_translate_string('Configurazione Notifiche Email'),
                $description,
                '',
                __FUNCTION__
            );
        }

        $actions = '';
        if (function_exists('admin_url')) {
            $actions = sprintf(
                '<p><a href="%s" class="button">%s</a></p>',
                esc_url(admin_url('admin.php?page=rbf_settings#email')),
                esc_html(rbf_translate_string('Configura notifiche email'))
            );
        }

        if (!$has_admin_email) {
            $description = '<p>' . rbf_translate_string('Nessun indirizzo email amministrativo è configurato per ricevere le prenotazioni. Nessuna notifica verrà inviata.') . '</p>';

            return rbf_site_health_build_result(
                'critical',
                rbf_translate_string('Configurazione Notifiche Email'),
                $description,
                $actions,
                __FUNCTION__
            );
        }

        $description = '<p>' . rbf_translate_string('Le notifiche email verranno inviate solo tramite WordPress. Configura Brevo per garantire recapito affidabile.') . '</p>';

        if (!$cleanup_scheduled) {
            $description .= sprintf(
                '<p>%s</p>',
                esc_html(
                    sprintf(
                        rbf_translate_string('Attiva la pulizia automatica dei log per mantenere il database leggero (retention attuale: %d giorni).'),
                        $retention_days
                    )
                )
            );
        }

        return rbf_site_health_build_result(
            'recommended',
            rbf_translate_string('Configurazione Notifiche Email'),
            $description,
            $actions,
            __FUNCTION__
        );
    }
}

if (!function_exists('rbf_site_health_booking_page_test')) {
    /**
     * Ensure a public booking page is published and accessible.
     *
     * @return array
     */
    function rbf_site_health_booking_page_test() {
        if (!function_exists('rbf_get_booking_confirmation_base_url')) {
            return rbf_site_health_build_result(
                'recommended',
                rbf_translate_string('Pagina di Prenotazione'),
                '<p>' . rbf_translate_string('Impossibile verificare la pagina di prenotazione durante il caricamento di WordPress.') . '</p>',
                '',
                __FUNCTION__
            );
        }

        $permalink = rbf_get_booking_confirmation_base_url(true);

        if (is_string($permalink) && $permalink !== '') {
            $description = sprintf(
                '<p>%s <a href="%s" target="_blank" rel="noopener">%s</a>.</p>',
                rbf_translate_string('La pagina di prenotazione pubblica è configurata correttamente:'),
                esc_url($permalink),
                esc_html($permalink)
            );

            return rbf_site_health_build_result(
                'good',
                rbf_translate_string('Pagina di Prenotazione'),
                $description,
                '',
                __FUNCTION__
            );
        }

        $actions = '';
        if (function_exists('admin_url')) {
            $actions = sprintf(
                '<p><a href="%s" class="button button-primary">%s</a></p>',
                esc_url(admin_url('edit.php?post_type=page')),
                esc_html(rbf_translate_string('Crea o modifica pagina di prenotazione'))
            );
        }

        $description = '<p>' . rbf_translate_string('Nessuna pagina pubblica con il form di prenotazione è stata trovata. I clienti non potranno completare le prenotazioni online finché non verrà pubblicata una pagina con lo shortcode del form.') . '</p>';

        return rbf_site_health_build_result(
            'critical',
            rbf_translate_string('Pagina di Prenotazione'),
            $description,
            $actions,
            __FUNCTION__
        );
    }
}

