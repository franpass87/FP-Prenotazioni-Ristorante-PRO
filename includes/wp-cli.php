<?php
/**
 * WP-CLI commands for FP Prenotazioni Ristorante.
 *
 * @package FP_Prenotazioni_Ristorante_PRO
 */

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

if (!defined('WP_CLI') || !WP_CLI) {
    return;
}

if (!class_exists('WP_CLI')) {
    return;
}

/**
 * Manage FP Prenotazioni Ristorante from the command line.
 */
class RBF_WPCLI_Command extends WP_CLI_Command {
    /**
     * Check if the current environment satisfies the plugin requirements.
     *
     * ## EXAMPLES
     *
     *     wp rbf check-environment
     *
     * @return void
     */
    public function check_environment() {
        if (!function_exists('rbf_get_environment_requirement_errors')) {
            WP_CLI::error('Impossibile verificare i requisiti: funzione mancante.');
        }

        $errors = (array) rbf_get_environment_requirement_errors();

        if (empty($errors)) {
            WP_CLI::success('L\'ambiente soddisfa tutti i requisiti del plugin.');
            return;
        }

        foreach ($errors as $error) {
            WP_CLI::warning($error);
        }

        WP_CLI::error('L\'ambiente non soddisfa i requisiti minimi richiesti.');
    }

    /**
     * Verify and repair the booking database schema if needed.
     *
     * ## EXAMPLES
     *
     *     wp rbf verify-schema
     *
     * @return void
     */
    public function verify_schema() {
        if (!function_exists('rbf_verify_database_schema')) {
            WP_CLI::error('La verifica dello schema non è disponibile: funzione mancante.');
        }

        if (!function_exists('rbf_database_table_exists')) {
            WP_CLI::error('Funzione di verifica delle tabelle mancante.');
        }

        rbf_verify_database_schema();

        global $wpdb;
        if (!isset($wpdb)) {
            WP_CLI::error('Impossibile accedere al database di WordPress.');
        }

        $required_tables = [
            $wpdb->prefix . 'rbf_areas',
            $wpdb->prefix . 'rbf_tables',
            $wpdb->prefix . 'rbf_table_groups',
            $wpdb->prefix . 'rbf_table_group_members',
            $wpdb->prefix . 'rbf_table_assignments',
            $wpdb->prefix . 'rbf_slot_versions',
            $wpdb->prefix . 'rbf_email_notifications',
        ];

        $missing = array_filter($required_tables, static function ($table) {
            return !rbf_database_table_exists($table);
        });

        if (!empty($missing)) {
            foreach ($missing as $table) {
                WP_CLI::warning(sprintf('Tabella mancante: %s', $table));
            }

            WP_CLI::error('Lo schema del database non è completo.');
        }

        WP_CLI::success('Lo schema del database prenotazioni è stato verificato.');
    }

    /**
     * Svuota le cache e i transient utilizzati dal plugin.
     *
     * ## EXAMPLES
     *
     *     wp rbf clear-cache
     *
     * @return void
     */
    public function clear_cache() {
        if (!function_exists('rbf_clear_transients')) {
            WP_CLI::error('Non è possibile svuotare le cache: funzione mancante.');
        }

        rbf_clear_transients();

        WP_CLI::success('Tutti i transient del plugin sono stati eliminati.');
    }

    /**
     * Programma nuovamente il cron per aggiornare gli stati delle prenotazioni.
     *
     * ## EXAMPLES
     *
     *     wp rbf reschedule-cron
     *
     * @return void
     */
    public function reschedule_cron() {
        if (!function_exists('rbf_schedule_status_updates')) {
            WP_CLI::error('La funzione per programmare il cron non è disponibile.');
        }

        rbf_schedule_status_updates();

        if (function_exists('wp_next_scheduled')) {
            $timestamp = wp_next_scheduled('rbf_update_booking_statuses');
            if ($timestamp) {
                $date = function_exists('get_date_from_gmt')
                    ? get_date_from_gmt(gmdate('Y-m-d H:i:s', $timestamp), get_option('date_format') . ' ' . get_option('time_format'))
                    : gmdate('Y-m-d H:i', $timestamp);
                WP_CLI::success(sprintf('Evento programmato per: %s', $date));
                return;
            }
        }

        WP_CLI::success('Evento riprogrammato, ma impossibile determinare l\'orario della prossima esecuzione.');
    }

    /**
     * Elimina i log email più vecchi del periodo di retention configurato.
     *
     * ## OPTIONS
     *
     * [--days=<days>]
     * : Specifica un numero di giorni personalizzato per questa esecuzione.
     *
     * ## EXAMPLES
     *
     *     wp rbf purge-email-logs
     *     wp rbf purge-email-logs --days=30
     *
     * @param array $args       Positional arguments (unused).
     * @param array $assoc_args Assoc arguments.
     * @return void
     */
    public function purge_email_logs($args, $assoc_args) {
        if (!function_exists('rbf_cleanup_email_notifications')) {
            WP_CLI::error('La funzione di pulizia dei log email non è disponibile.');
        }

        $days = null;
        if (isset($assoc_args['days'])) {
            $days = absint($assoc_args['days']);
            if ($days <= 0) {
                WP_CLI::error('Il parametro --days deve essere un intero positivo.');
            }
        }

        $result = rbf_cleanup_email_notifications($days ?: null);

        if (empty($result['table_exists'])) {
            WP_CLI::warning('La tabella dei log email non è stata trovata. Nessun record eliminato.');
            return;
        }

        $retention = (int) ($result['retention_days'] ?? 0);
        if ($retention <= 0) {
            if ($days) {
                $retention = $days;
            } elseif (function_exists('rbf_get_email_log_retention_days')) {
                $retention = (int) rbf_get_email_log_retention_days();
            } else {
                $retention = (int) RBF_EMAIL_LOG_DEFAULT_RETENTION_DAYS;
            }
        }

        $deleted = (int) ($result['deleted'] ?? 0);

        WP_CLI::success(sprintf(
            'Pulizia completata. Log rimossi: %d (retention %d giorni).',
            $deleted,
            $retention
        ));

        if ($deleted === 0) {
            WP_CLI::log('Non sono stati trovati log più vecchi del limite specificato.');
        }
    }
}

WP_CLI::add_command('rbf', 'RBF_WPCLI_Command');
