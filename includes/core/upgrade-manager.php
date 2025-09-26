<?php
/**
 * Plugin upgrade manager for FP Prenotazioni Ristorante.
 *
 * @package FP_Prenotazioni_Ristorante_PRO
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Coordinate schema and data migrations across plugin releases.
 */
class RBF_Upgrade_Manager {
        /**
         * Singleton instance.
         *
         * @var RBF_Upgrade_Manager|null
         */
        private static $instance = null;

        /**
         * Track whether the upgrade routines executed for the current request.
         *
         * @var bool
         */
        private $did_run = false;

        /**
         * Map of target versions to migration callbacks.
         *
         * @var array<string, callable>
         */
        private $migrations = array();

        /**
         * Retrieve the shared manager instance and register hooks.
         *
         * @return RBF_Upgrade_Manager
         */
        public static function bootstrap() {
                $manager = self::get_instance();
                $manager->register_hooks();

                return $manager;
        }

        /**
         * Retrieve the shared manager instance.
         *
         * @return RBF_Upgrade_Manager
         */
        public static function get_instance() {
                if ( null === self::$instance ) {
                        self::$instance = new self();
                }

                return self::$instance;
        }

        /**
         * Constructor.
         */
        private function __construct() {
                $this->migrations = array(
                        '1.7.0' => array( $this, 'upgrade_to_170' ),
                );

                if ( ! empty( $this->migrations ) ) {
                        uksort(
                                $this->migrations,
                                static function ( $a, $b ) {
                                        return version_compare( (string) $a, (string) $b );
                                }
                        );
                }
        }

        /**
         * Register WordPress hooks.
         */
        private function register_hooks() {
                add_action( 'init', array( $this, 'maybe_run_upgrades' ), 5 );
        }

        /**
         * Execute outstanding upgrade routines when a new plugin version is detected.
         *
         * @return void
         */
        public function maybe_run_upgrades() {
                if ( $this->did_run ) {
                        return;
                }

                $this->did_run = true;

                if ( defined( 'WP_INSTALLING' ) && WP_INSTALLING ) {
                        return;
                }

                $stored_version = rbf_get_network_aware_option( 'rbf_plugin_version', '0.0.0' );
                if ( ! is_string( $stored_version ) || '' === $stored_version ) {
                        $stored_version = '0.0.0';
                }

                $current_version = RBF_VERSION;

                if ( version_compare( $stored_version, $current_version, '>=' ) ) {
                        $this->maybe_refresh_build_signature();
                        return;
                }

                /**
                 * Fires before plugin upgrade routines are executed.
                 *
                 * @param string $stored_version  Previously installed version.
                 * @param string $current_version Target plugin version.
                 */
                do_action( 'rbf_before_plugin_upgrade', $stored_version, $current_version );

                foreach ( $this->migrations as $target_version => $callback ) {
                        if ( ! is_callable( $callback ) ) {
                                continue;
                        }

                        if ( version_compare( $stored_version, $target_version, '<' ) &&
                                version_compare( $current_version, $target_version, '>=' )
                        ) {
                                call_user_func( $callback, $stored_version );
                        }
                }

                $this->finalize_upgrade();

                /**
                 * Fires after plugin upgrade routines are executed.
                 *
                 * @param string $stored_version  Previously installed version.
                 * @param string $current_version Target plugin version.
                 */
                do_action( 'rbf_after_plugin_upgrade', $stored_version, $current_version );
        }

        /**
         * Ensure the build signature mirrors the active plugin files.
         *
         * @return void
         */
        private function maybe_refresh_build_signature() {
                $stored_signature  = rbf_get_network_aware_option( 'rbf_plugin_build_signature', '' );
                $current_signature = rbf_get_plugin_build_signature();

                if ( $stored_signature !== $current_signature ) {
                        rbf_update_network_aware_option( 'rbf_plugin_build_signature', $current_signature );
                }
        }

        /**
         * Perform final cache invalidation and state updates after migrations run.
         *
         * @return void
         */
        private function finalize_upgrade() {
                rbf_clear_transients();

                if ( function_exists( 'rbf_invalidate_settings_cache' ) ) {
                        rbf_invalidate_settings_cache();
                }

                if ( function_exists( 'wp_cache_flush' ) ) {
                        wp_cache_flush();
                }

                rbf_flush_plugin_opcache();

                rbf_update_network_aware_option( 'rbf_plugin_version', RBF_VERSION );
                rbf_update_network_aware_option( 'rbf_plugin_build_signature', rbf_get_plugin_build_signature() );
                rbf_update_network_aware_option( 'rbf_last_upgrade_completed', current_time( 'mysql', true ) );
        }

        /**
         * Upgrade routine for version 1.7.0.
         *
         * @param string $from_version Previously installed version string.
         * @return void
         */
        private function upgrade_to_170( $from_version ) {
                $table_ready = $this->ensure_booking_status_table();

                if ( $table_ready ) {
                        $this->backfill_booking_status_table();
                        rbf_booking_status_table_exists( true );
                        rbf_get_booking_status_sql_source( true );
                }

                if ( function_exists( 'rbf_verify_database_schema' ) ) {
                        rbf_verify_database_schema();
                }

                $this->ensure_status_cron_is_scheduled();

                if ( function_exists( 'rbf_log' ) ) {
                        rbf_log( sprintf( 'RBF Upgrade: migrated from %s to %s.', $from_version, RBF_VERSION ) );
                }
        }

        /**
         * Ensure the dedicated booking status table exists.
         *
         * @return bool True when the table exists after the check.
         */
        private function ensure_booking_status_table() {
                if ( rbf_booking_status_table_exists() ) {
                        return true;
                }

                global $wpdb;

                if ( ! isset( $wpdb ) ) {
                        return false;
                }

                $table_name      = rbf_get_booking_status_table_name();
                $charset_collate = $wpdb->get_charset_collate();

                if ( '' === $table_name ) {
                        return false;
                }

                require_once ABSPATH . 'wp-admin/includes/upgrade.php';

                $sql = "CREATE TABLE $table_name (
        booking_id BIGINT(20) UNSIGNED NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'confirmed',
        note text NULL,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_by BIGINT(20) UNSIGNED DEFAULT 0,
        PRIMARY KEY  (booking_id),
        KEY status (status),
        KEY updated_at (updated_at)
    ) $charset_collate;";

                dbDelta( $sql );

                return rbf_booking_status_table_exists( true );
        }

        /**
         * Populate the booking status table from legacy post meta when empty.
         *
         * @return void
         */
        private function backfill_booking_status_table() {
                global $wpdb;

                if ( ! isset( $wpdb ) ) {
                        return;
                }

                $table_name = rbf_get_booking_status_table_name();

                if ( '' === $table_name || ! rbf_booking_status_table_exists() ) {
                        return;
                }

                $existing_rows = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );
                if ( $existing_rows > 0 ) {
                        return;
                }

                $allowed_statuses = array_keys( rbf_get_booking_statuses() );
                $allowed_statuses[] = 'pending';
                $allowed_statuses   = array_unique( array_filter( $allowed_statuses ) );

                $batch_size = 200;
                $offset     = 0;

                $utc_now = current_time( 'mysql', true );

                while ( true ) {
                        $rows = $wpdb->get_results(
                                $wpdb->prepare(
                                        "SELECT p.ID AS booking_id, COALESCE(pm_status.meta_value, 'confirmed') AS status
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = 'rbf_booking_status'
        WHERE p.post_type = 'rbf_booking'
        ORDER BY p.ID ASC
        LIMIT %d OFFSET %d",
                                        $batch_size,
                                        $offset
                                )
                        );

                        if ( empty( $rows ) ) {
                                break;
                        }

                        $offset      += count( $rows );
                        $placeholders = array();
                        $values       = array();

                        foreach ( $rows as $row ) {
                                $booking_id = absint( $row->booking_id );

                                if ( $booking_id <= 0 ) {
                                        continue;
                                }

                                $normalized = rbf_normalize_booking_status( sanitize_key( $row->status ) );

                                if ( is_wp_error( $normalized ) || '' === $normalized ) {
                                        $normalized = 'confirmed';
                                }

                                if ( ! in_array( $normalized, $allowed_statuses, true ) ) {
                                        $normalized = 'confirmed';
                                }

                                $placeholders[] = '(%d, %s, %s, %d, %s)';
                                $values[]       = $booking_id;
                                $values[]       = $normalized;
                                $values[]       = $utc_now;
                                $values[]       = 0;
                                $values[]       = '';
                        }

                        if ( empty( $placeholders ) ) {
                                continue;
                        }

                        $sql = "INSERT INTO $table_name (booking_id, status, updated_at, updated_by, note)
        VALUES " . implode( ', ', $placeholders ) . '
        ON DUPLICATE KEY UPDATE status = VALUES(status), updated_at = VALUES(updated_at), updated_by = VALUES(updated_by), note = VALUES(note)';

                        $wpdb->query( $wpdb->prepare( $sql, $values ) );
                }
        }

        /**
         * Ensure the daily booking status cron event remains scheduled.
         *
         * @return void
         */
        private function ensure_status_cron_is_scheduled() {
                if ( ! function_exists( 'wp_next_scheduled' ) || ! function_exists( 'wp_schedule_event' ) ) {
                        return;
                }

                if ( wp_next_scheduled( 'rbf_update_booking_statuses' ) ) {
                        return;
                }

                $timestamp = null;

                if ( function_exists( 'rbf_get_next_daily_event_timestamp' ) ) {
                        $timestamp = rbf_get_next_daily_event_timestamp( 6, 0 );
                }

                if ( null === $timestamp ) {
                        $timestamp = time() + DAY_IN_SECONDS;
                }

                wp_schedule_event( $timestamp, 'daily', 'rbf_update_booking_statuses' );
        }
}
