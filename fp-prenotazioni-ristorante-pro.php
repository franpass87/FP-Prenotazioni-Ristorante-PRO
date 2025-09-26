<?php
/**
 * Plugin Name: FP Prenotazioni Ristorante
 * Description: Prenotazioni con calendario Flatpickr IT/EN, gestione capienza per servizio, notifiche email (con CC), Brevo sempre e GA4/Meta (bucket standard), con supporto ai limiti temporali minimi.
 * Version:     1.7.0
 * Requires at least: 6.0
 * Tested up to: 6.5
 * Requires PHP: 7.4
 * Author:      Francesco Passeri
 * Author URI:  https://francescopasseri.com
 * Plugin URI:  https://francescopasseri.com/progetti/fp-prenotazioni-ristorante-pro
 * Update URI:  https://francescopasseri.com/progetti/fp-prenotazioni-ristorante-pro
 * Text Domain: rbf
 * Domain Path: /languages
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'RBF_PLUGIN_FILE', __FILE__ );
define( 'RBF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RBF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'RBF_VERSION', '1.7.0' );
define( 'RBF_MIN_PHP_VERSION', '7.4' );
define( 'RBF_MIN_WP_VERSION', '6.0' );

/**
 * Generate a signature representing the current plugin build.
 *
 * The signature combines the plugin version with metadata from the most
 * critical source directories so that caches depending on a specific build
 * can be safely invalidated when files change without bumping the version.
 *
 * @return string Unique signature for the active build.
 */
function rbf_get_plugin_build_signature() {
	static $signature = null;

	if ( $signature !== null ) {
		return apply_filters( 'rbf_plugin_build_signature', $signature );
	}

	$signature = RBF_VERSION;

	$paths = array(
		RBF_PLUGIN_FILE,
		RBF_PLUGIN_DIR . 'includes',
		RBF_PLUGIN_DIR . 'assets/css',
		RBF_PLUGIN_DIR . 'assets/js',
	);

	$components = array();

	foreach ( $paths as $path ) {
		if ( ! is_string( $path ) || $path === '' ) {
			continue;
		}

		if ( is_file( $path ) && is_readable( $path ) ) {
			$components[] = $path . '|' . filesize( $path ) . '|' . filemtime( $path );
			continue;
		}

		if ( ! is_dir( $path ) ) {
			continue;
		}

		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator(
					$path,
					FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO
				)
			);
		} catch ( UnexpectedValueException $e ) {
			continue;
		}

		foreach ( $iterator as $file_info ) {
			if ( ! $file_info instanceof SplFileInfo || ! $file_info->isFile() ) {
				continue;
			}

			$extension           = strtolower( $file_info->getExtension() );
			$relevant_extensions = array( 'php', 'js', 'css', 'json', 'html', 'htm' );

			if ( ! in_array( $extension, $relevant_extensions, true ) ) {
				continue;
			}

			$components[] = $file_info->getPathname() . '|' . $file_info->getSize() . '|' . $file_info->getMTime();
		}
	}

	if ( ! empty( $components ) ) {
		sort( $components );
		$signature = RBF_VERSION . ':' . md5( implode( '|', $components ) );
	}

	return apply_filters( 'rbf_plugin_build_signature', $signature );
}

// Polyfills for PHP versions prior to 8.0 used in development utilities.
if ( ! function_exists( 'str_contains' ) ) {
	/**
	 * Polyfill for str_contains to maintain compatibility with PHP 7.4.
	 *
	 * Mirrors native behaviour by accepting stringable values and throwing
	 * a TypeError when invalid arguments are supplied.
	 *
	 * @param string $haystack The string to search in.
	 * @param string $needle   The substring to search for.
	 * @return bool True when $needle is found in $haystack.
	 */
	function str_contains( $haystack, $needle ) {
		if ( is_object( $haystack ) ) {
			if ( method_exists( $haystack, '__toString' ) ) {
				$haystack = (string) $haystack;
			} else {
				throw new TypeError(
					'str_contains(): Argument #1 ($haystack) must be of type string, ' . gettype( $haystack ) . ' given'
				);
			}
		} elseif ( ! is_string( $haystack ) ) {
			throw new TypeError(
				'str_contains(): Argument #1 ($haystack) must be of type string, ' . gettype( $haystack ) . ' given'
			);
		}

		if ( is_object( $needle ) ) {
			if ( method_exists( $needle, '__toString' ) ) {
				$needle = (string) $needle;
			} else {
				throw new TypeError(
					'str_contains(): Argument #2 ($needle) must be of type string, ' . gettype( $needle ) . ' given'
				);
			}
		} elseif ( ! is_string( $needle ) ) {
			throw new TypeError(
				'str_contains(): Argument #2 ($needle) must be of type string, ' . gettype( $needle ) . ' given'
			);
		}

		if ( $needle === '' ) {
			return true;
		}

		return strpos( $haystack, $needle ) !== false;
	}
}

/**
 * Determine environment requirement errors.
 *
 * @return array List of human-readable error messages.
 */
function rbf_get_environment_requirement_errors() {
	$errors = array();

	if ( version_compare( PHP_VERSION, RBF_MIN_PHP_VERSION, '<' ) ) {
		$errors[] = sprintf(
			/* translators: 1: required PHP version, 2: current PHP version */
			esc_html__( 'Versione PHP minima richiesta: %1$s (versione corrente: %2$s).', 'rbf' ),
			RBF_MIN_PHP_VERSION,
			PHP_VERSION
		);
	}

	global $wp_version;
	if ( isset( $wp_version ) && version_compare( $wp_version, RBF_MIN_WP_VERSION, '<' ) ) {
		$errors[] = sprintf(
			/* translators: 1: required WordPress version, 2: current WordPress version */
			esc_html__( 'Versione minima di WordPress richiesta: %1$s (versione corrente: %2$s).', 'rbf' ),
			RBF_MIN_WP_VERSION,
			$wp_version
		);
	}

	return $errors;
}

/**
 * Check if the current environment meets the plugin requirements.
 *
 * @return bool
 */
function rbf_environment_meets_requirements() {
	return count( rbf_get_environment_requirement_errors() ) === 0;
}

/**
 * Determine if the plugin is network-activated on a multisite installation.
 *
 * @return bool
 */
function rbf_is_plugin_network_active() {
	if ( ! function_exists( 'is_multisite' ) || ! is_multisite() ) {
		return false;
	}

	if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
		return false;
	}

	return is_plugin_active_for_network( plugin_basename( RBF_PLUGIN_FILE ) );
}

/**
 * Deactivate the plugin when requirements are not satisfied.
 */
function rbf_deactivate_plugin_for_environment() {
	if ( ! function_exists( 'deactivate_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	deactivate_plugins( plugin_basename( RBF_PLUGIN_FILE ) );
}

/**
 * Render an admin notice describing missing requirements.
 */
function rbf_render_environment_requirement_notice() {
	$errors = rbf_get_environment_requirement_errors();

	if ( empty( $errors ) ) {
		return;
	}

	echo '<div class="notice notice-error"><p>';
	echo esc_html__( 'FP Prenotazioni Ristorante richiede un ambiente aggiornato e verrà disattivato.', 'rbf' );
	echo '</p><ul style="margin-left:1.5em;">';

	foreach ( $errors as $error ) {
		echo '<li>' . esc_html( $error ) . '</li>';
	}

	echo '</ul></div>';
}

$rbf_environment_ready = rbf_environment_meets_requirements();

if ( ! $rbf_environment_ready ) {
        if ( function_exists( 'is_admin' ) && is_admin() ) {
                add_action( 'admin_notices', 'rbf_render_environment_requirement_notice' );
                add_action( 'network_admin_notices', 'rbf_render_environment_requirement_notice' );
        }

        rbf_deactivate_plugin_for_environment();
        return;
}

/**
 * Determine if network-level options should be used.
 *
 * @param bool|null $override Optional override to explicitly enable/disable network usage.
 * @return bool
 */
function rbf_should_use_network_options( $override = null ) {
        if ( $override !== null ) {
                return (bool) $override;
        }

        return rbf_is_plugin_network_active();
}

/**
 * Retrieve an option supporting multisite network overrides.
 *
 * @param string     $option      Option name.
 * @param mixed      $default     Default value when the option is missing.
 * @param bool|null  $use_network Optional override for network lookups.
 * @return mixed
 */
function rbf_get_network_aware_option( $option, $default = false, $use_network = null ) {
        if ( ! is_string( $option ) || $option === '' ) {
                return $default;
        }

        $sentinel = new stdClass();

        if ( rbf_should_use_network_options( $use_network ) && function_exists( 'get_site_option' ) ) {
                $network_value = get_site_option( $option, $sentinel );

                if ( $network_value !== $sentinel ) {
                        return $network_value;
                }
        }

        if ( function_exists( 'get_option' ) ) {
                return get_option( $option, $default );
        }

        return $default;
}

/**
 * Persist an option while mirroring to the network when relevant.
 *
 * @param string     $option      Option name.
 * @param mixed      $value       Value to store.
 * @param bool|null  $autoload    Optional autoload flag for the site option.
 * @param bool|null  $use_network Optional override for network writes.
 * @return bool True on success, false otherwise.
 */
function rbf_update_network_aware_option( $option, $value, $autoload = null, $use_network = null ) {
        if ( ! is_string( $option ) || $option === '' || ! function_exists( 'update_option' ) ) {
                return false;
        }

        if ( rbf_should_use_network_options( $use_network ) && function_exists( 'update_site_option' ) ) {
                update_site_option( $option, $value );
        }

        if ( $autoload === null ) {
                return update_option( $option, $value );
        }

        return update_option( $option, $value, $autoload );
}

/**
 * Delete an option while clearing potential network copies.
 *
 * @param string     $option      Option name.
 * @param bool|null  $use_network Optional override for network deletions.
 * @return bool True when any value was deleted.
 */
function rbf_delete_network_aware_option( $option, $use_network = null ) {
        if ( ! is_string( $option ) || $option === '' ) {
                return false;
        }

        $deleted = false;

        if ( rbf_should_use_network_options( $use_network ) && function_exists( 'delete_site_option' ) ) {
                $deleted = delete_site_option( $option ) || $deleted;
        }

        if ( function_exists( 'delete_option' ) ) {
                $deleted = delete_option( $option ) || $deleted;
        }

        return $deleted;
}

// Load utilities early for logging support
require_once RBF_PLUGIN_DIR . 'includes/utils.php';

// Initialize the runtime logging harness when debugging is enabled
require_once RBF_PLUGIN_DIR . 'includes/runtime-logger.php';
add_action( 'plugins_loaded', 'rbf_runtime_logger_bootstrap', -25 );

// Load the contextual module loader used to orchestrate plugin components.
require_once RBF_PLUGIN_DIR . 'includes/core/class-rbf-module-loader.php';
require_once RBF_PLUGIN_DIR . 'includes/core/upgrade-manager.php';

RBF_Upgrade_Manager::bootstrap();

/**
 * Load plugin translations.
 *
 * Executed early on the `plugins_loaded` hook to ensure translation files are
 * available before other modules register strings.
 */
function rbf_load_textdomain() {
	if ( ! function_exists( 'load_plugin_textdomain' ) || ! function_exists( 'plugin_basename' ) ) {
		return;
	}

	load_plugin_textdomain( 'rbf', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}
add_action( 'plugins_loaded', 'rbf_load_textdomain', -10 );

/**
 * Invalidate PHP OPcache entries for plugin files when supported.
 *
 * @return int Number of files invalidated.
 */
function rbf_flush_plugin_opcache() {
        static $flushed = false;

        if ( $flushed ) {
                return 0;
        }

        if ( ! function_exists( 'wp_opcache_invalidate' ) ) {
                return 0;
        }

        $flushed = true;

        $targets = array(
                RBF_PLUGIN_FILE,
                RBF_PLUGIN_DIR . 'includes',
        );

        $invalidated = 0;

        foreach ( $targets as $target ) {
                if ( is_file( $target ) ) {
                        if ( wp_opcache_invalidate( $target, true ) ) {
                                ++$invalidated;
                        }

                        continue;
                }

                if ( ! is_dir( $target ) ) {
                        continue;
                }

                try {
                        $iterator = new RecursiveIteratorIterator(
                                new RecursiveDirectoryIterator(
                                        $target,
                                        FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO
                                )
                        );
                } catch ( UnexpectedValueException $exception ) {
                        continue;
                }

                foreach ( $iterator as $file_info ) {
                        if ( ! $file_info instanceof SplFileInfo || ! $file_info->isFile() ) {
                                continue;
                        }

                        if ( strtolower( $file_info->getExtension() ) !== 'php' ) {
                                continue;
                        }

                        if ( wp_opcache_invalidate( $file_info->getPathname(), true ) ) {
                                ++$invalidated;
                        }
                }
        }

        return $invalidated;
}

/**
 * Clear all transients used by the plugin.
 */
function rbf_clear_transients() {
	global $wpdb;

	if ( ! isset( $wpdb ) ) {
		return;
	}

	// Clear RBF-specific transients with improved pattern matching
	$transient_patterns = array(
		'_transient_rbf_',
		'_transient_timeout_rbf_',
	);

        foreach ( $transient_patterns as $pattern ) {
                $pattern_like = $wpdb->esc_like( $pattern ) . '%';
                $deleted      = $wpdb->query(
                        $wpdb->prepare(
                                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                                $pattern_like
			)
		);

		// Log cleanup for debugging
		if ( $deleted > 0 ) {
			rbf_log( "RBF Plugin: Cleared {$deleted} transients matching pattern: {$pattern}" );
		}
	}

	// Also clear specific availability transients
	$availability_pattern = $wpdb->esc_like( '_transient_rbf_avail_' ) . '%';

	$deleted_avail = $wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$availability_pattern
                )
        );

        if ( $deleted_avail > 0 ) {
                rbf_log( "RBF Plugin: Cleared {$deleted_avail} availability transients" );
        }

        if ( rbf_should_use_network_options( null ) && ! empty( $wpdb->sitemeta ) ) {
                foreach ( $transient_patterns as $pattern ) {
                        $network_pattern = str_replace( '_transient_', '_site_transient_', $pattern );
                        $pattern_like    = $wpdb->esc_like( $network_pattern ) . '%';

                        $deleted_network = $wpdb->query(
                                $wpdb->prepare(
                                        "DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s",
                                        $pattern_like
                                )
                        );

                        if ( $deleted_network > 0 ) {
                                rbf_log( "RBF Plugin: Cleared {$deleted_network} network transients matching pattern: {$network_pattern}" );
                        }
                }
        }

        if ( function_exists( 'wp_cache_flush' ) ) {
                wp_cache_flush();
        }
}

/**
 * Clear transients when plugin version changes.
 */
function rbf_maybe_clear_transients_on_load() {
        $version           = rbf_get_network_aware_option( 'rbf_plugin_version' );
        $stored_signature  = rbf_get_network_aware_option( 'rbf_plugin_build_signature' );
        $current_signature = rbf_get_plugin_build_signature();

        $version_changed = ( $version !== RBF_VERSION );
        $build_changed   = ( $stored_signature !== $current_signature );

        if ( $version_changed || $build_changed ) {
                rbf_clear_transients();
                rbf_update_network_aware_option( 'rbf_plugin_build_signature', $current_signature );
        }
}
add_action( 'plugins_loaded', 'rbf_maybe_clear_transients_on_load', -1 );

/**
 * Retrieve the shared module loader instance.
 *
 * @return RBF_Module_Loader
 */
function rbf_get_module_loader() {
        static $loader = null;

        if ( $loader instanceof RBF_Module_Loader ) {
                return $loader;
        }

        $loader = new RBF_Module_Loader( RBF_PLUGIN_DIR . 'includes/' );

        $default_config = array(
                'shared'   => array(
                        'optimistic-locking.php',
                        'booking-handler.php',
                        'email-failover.php',
                        'integrations.php',
                        'ga4-funnel-tracking.php',
                        'tracking-validation.php',
                        'tracking-enhanced-integration.php',
                        'tracking-presets.php',
                        'ai-suggestions.php',
                        'privacy.php',
                ),
                'admin'    => array(
                        'admin.php',
                        'booking-dashboard.php',
                        'onboarding.php',
                        'branding-profiles.php',
                        'system-health-dashboard.php',
                        'table-management.php',
                        'accessibility-checker.php',
                        'site-health.php',
                ),
                'frontend' => array(
                        'frontend.php',
                ),
                'cli'      => array(
                        'wp-cli.php',
                ),
        );

        /**
         * Filter the default module configuration before modules are registered.
         *
         * @param array              $default_config Default module configuration.
         * @param RBF_Module_Loader  $loader         Loader instance.
         */
        $default_config = apply_filters( 'rbf_module_loader_default_config', $default_config, $loader );

        if ( is_array( $default_config ) ) {
                $loader->register_modules( $default_config );
        }

        /**
         * Allow extensions to interact with the loader after initialization.
         *
         * @param RBF_Module_Loader $loader Loader instance.
         */
        do_action( 'rbf_module_loader_initialized', $loader );

        return $loader;
}

/**
 * Load plugin modules using the contextual loader.
 */
function rbf_bootstrap_modules() {
        $loader = rbf_get_module_loader();

        $dynamic_modules = apply_filters( 'rbf_module_loader_dynamic_modules', array(), $loader );
        if ( is_array( $dynamic_modules ) && ! empty( $dynamic_modules ) ) {
                $loader->register_modules( $dynamic_modules );
        }

        $loader->load_registered_modules();
}

// Load modules immediately after WordPress functions are available.
add_action( 'plugins_loaded', 'rbf_bootstrap_modules', 0 );

/**
 * Perform runtime environment checks once WordPress is fully loaded.
 */
function rbf_initialize_runtime_environment() {
	if ( function_exists( 'rbf_verify_database_schema' ) ) {
		rbf_verify_database_schema();
	}
}
add_action( 'plugins_loaded', 'rbf_initialize_runtime_environment', 1 );

if ( ! function_exists( 'rbf_should_load_admin_tests' ) ) {
	/**
	 * Determine whether developer test harnesses should be loaded.
	 *
	 * Tests are only loaded in explicitly enabled environments to avoid
	 * accidental execution on production sites.
	 *
	 * @return bool
	 */
	function rbf_should_load_admin_tests() {
		$should_load = false;

		if ( defined( 'RBF_ENABLE_ADMIN_TESTS' ) ) {
			$should_load = (bool) RBF_ENABLE_ADMIN_TESTS;
		} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'wp_get_environment_type' ) ) {
			$environment = wp_get_environment_type();
			$should_load = in_array( $environment, array( 'local', 'development' ), true );
		}

		if ( function_exists( 'apply_filters' ) ) {
			return (bool) apply_filters( 'rbf_should_load_admin_tests', $should_load );
		}

		return (bool) $should_load;
	}
}

// Load test files in admin context
if ( is_admin() ) {
	add_action(
		'plugins_loaded',
		function () {
			if ( ! rbf_should_load_admin_tests() ) {
				return;
			}

			$test_files = array(
				'ga4-funnel-tests.php',
				'ai-suggestions-tests.php',
				'hybrid-tracking-tests.php',
				'comprehensive-tracking-verification.php',
			);

			foreach ( $test_files as $test_file ) {
				$file_path = RBF_PLUGIN_DIR . 'tests/' . $test_file;
				if ( file_exists( $file_path ) ) {
					require_once $file_path;
				}
			}
		}
	);
}

/**
 * Plugin activation hook
 */
/**
 * Execute activation tasks within the current site context.
 *
 * @param bool $flush_rewrite Optional. Whether to flush rewrite rules. Default true.
 */
function rbf_run_site_activation_tasks( $flush_rewrite = true ) {
	rbf_clear_transients();

        // Load plugin modules so that CPTs and helpers are available.
        rbf_bootstrap_modules();

	if ( function_exists( 'rbf_register_default_capabilities' ) ) {
		rbf_register_default_capabilities();
	}

	if ( function_exists( 'rbf_register_post_type' ) ) {
		rbf_register_post_type();
	}

	if ( function_exists( 'rbf_create_table_management_tables' ) ) {
		rbf_create_table_management_tables();
	}

	if ( function_exists( 'rbf_create_slot_version_table' ) ) {
		rbf_create_slot_version_table();
	}

	if ( function_exists( 'rbf_schedule_status_updates' ) ) {
		rbf_schedule_status_updates();
	}

	if ( function_exists( 'rbf_schedule_email_log_cleanup' ) ) {
		rbf_schedule_email_log_cleanup();
	}

	$provisioning_summary = array();

	if ( function_exists( 'rbf_seed_default_meals_if_missing' ) ) {
		$seeded_meals = (int) rbf_seed_default_meals_if_missing();
		if ( $seeded_meals > 0 ) {
			$provisioning_summary[] = rbf_translate_string( 'Servizi predefiniti attivati' );
		}
	}

	if ( function_exists( 'rbf_ensure_booking_page_exists' ) ) {
		$page_result = rbf_ensure_booking_page_exists(
			array(
				'update_settings' => true,
			)
		);

		if ( ! empty( $page_result['created'] ) ) {
			$provisioning_summary[] = rbf_translate_string( 'Pagina di prenotazione pubblicata automaticamente' );
		}
	}

	if ( ! empty( $provisioning_summary ) && function_exists( 'rbf_add_admin_notice' ) ) {
		$notice = sprintf(
			rbf_translate_string( 'Setup iniziale completato: %s.' ),
			implode( ' · ', $provisioning_summary )
		);

		rbf_add_admin_notice( $notice, 'success' );
	}

        rbf_update_network_aware_option( 'rbf_plugin_version', RBF_VERSION );
        rbf_update_network_aware_option( 'rbf_plugin_build_signature', rbf_get_plugin_build_signature() );

	if ( $flush_rewrite && function_exists( 'flush_rewrite_rules' ) ) {
		flush_rewrite_rules();
	}
}

register_activation_hook( __FILE__, 'rbf_activate_plugin' );
function rbf_activate_plugin( $network_wide ) {
	$network_wide = (bool) $network_wide;

	if ( ! function_exists( 'is_multisite' ) || ! is_multisite() || ! $network_wide ) {
		rbf_run_site_activation_tasks();
		return;
	}

	if ( ! function_exists( 'get_sites' ) || ! function_exists( 'switch_to_blog' ) || ! function_exists( 'restore_current_blog' ) ) {
		rbf_run_site_activation_tasks();
		return;
	}

	$site_ids = get_sites( array( 'fields' => 'ids' ) );

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( $site_id );
		rbf_run_site_activation_tasks();
		restore_current_blog();
	}
}

/**
 * Plugin deactivation hook
 */
/**
 * Execute deactivation tasks within the current site context.
 */
function rbf_run_site_deactivation_tasks() {
	if ( function_exists( 'rbf_clear_automatic_status_events' ) ) {
		rbf_clear_automatic_status_events();
	} elseif ( function_exists( 'wp_clear_scheduled_hook' ) ) {
		wp_clear_scheduled_hook( 'rbf_update_booking_statuses' );
	}

	if ( function_exists( 'rbf_clear_email_log_cleanup_event' ) ) {
		rbf_clear_email_log_cleanup_event();
	}

	flush_rewrite_rules();
	rbf_clear_transients();
}

register_deactivation_hook( __FILE__, 'rbf_deactivate_plugin' );
function rbf_deactivate_plugin() {
	if ( function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'get_sites' ) && function_exists( 'switch_to_blog' ) && function_exists( 'restore_current_blog' ) ) {
		$site_ids = get_sites( array( 'fields' => 'ids' ) );

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( $site_id );
			rbf_run_site_deactivation_tasks();
			restore_current_blog();
		}

		return;
	}

	rbf_run_site_deactivation_tasks();
}

register_uninstall_hook( __FILE__, 'rbf_uninstall_plugin' );

function rbf_uninstall_cleanup_site() {
	if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
		wp_clear_scheduled_hook( 'rbf_update_booking_statuses' );
	}

	if ( function_exists( 'rbf_clear_email_log_cleanup_event' ) ) {
		rbf_clear_email_log_cleanup_event();
	}

	if ( ! function_exists( 'rbf_remove_default_capabilities' ) ) {
		$admin_module = RBF_PLUGIN_DIR . 'includes/admin.php';
		if ( file_exists( $admin_module ) ) {
			require_once $admin_module;
		}
	}

	if ( function_exists( 'rbf_remove_default_capabilities' ) ) {
		rbf_remove_default_capabilities();
	}

        $options = array( 'rbf_settings', 'rbf_admin_notices', 'rbf_plugin_version', 'rbf_schema_last_verified', 'rbf_bootstrap_defaults_seeded' );
        foreach ( $options as $option_name ) {
                rbf_delete_network_aware_option( $option_name );
        }

	if ( function_exists( 'get_posts' ) && function_exists( 'wp_delete_post' ) ) {
		$booking_ids = get_posts(
			array(
				'post_type'              => 'rbf_booking',
				'post_status'            => 'any',
				'numberposts'            => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		foreach ( $booking_ids as $booking_id ) {
			wp_delete_post( $booking_id, true );
		}
	}

	global $wpdb;
	if ( ! isset( $wpdb ) ) {
		return;
	}

	$tables = array(
		$wpdb->prefix . 'rbf_areas',
		$wpdb->prefix . 'rbf_tables',
		$wpdb->prefix . 'rbf_table_groups',
		$wpdb->prefix . 'rbf_table_group_members',
		$wpdb->prefix . 'rbf_table_assignments',
		$wpdb->prefix . 'rbf_slot_versions',
		$wpdb->prefix . 'rbf_email_notifications',
	);

	foreach ( $tables as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
	}

	$transient_patterns = array(
		'_transient_rbf_',
		'_transient_timeout_rbf_',
	);

	foreach ( $transient_patterns as $pattern ) {
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( $pattern ) . '%'
			)
		);
	}
}

function rbf_uninstall_plugin() {
	if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	$is_multisite = function_exists( 'is_multisite' ) && is_multisite();

	if ( $is_multisite && function_exists( 'get_sites' ) && function_exists( 'switch_to_blog' ) && function_exists( 'restore_current_blog' ) ) {
		$site_ids = get_sites( array( 'fields' => 'ids' ) );
		foreach ( $site_ids as $site_id ) {
			switch_to_blog( $site_id );
			rbf_uninstall_cleanup_site();
			restore_current_blog();
		}

                $network_options = array( 'rbf_settings', 'rbf_admin_notices', 'rbf_plugin_version', 'rbf_schema_last_verified', 'rbf_bootstrap_defaults_seeded' );
                foreach ( $network_options as $option_name ) {
                        rbf_delete_network_aware_option( $option_name, true );
                }
	} else {
		rbf_uninstall_cleanup_site();
	}

	if ( function_exists( 'wp_cache_flush' ) ) {
		wp_cache_flush();
	}
}

/**
 * Bootstrap plugin requirements on newly created multisite blogs.
 *
 * @param int $site_id Site/blog ID.
 */
function rbf_initialize_new_site( $site_id ) {
	$site_id = (int) $site_id;

	if ( $site_id <= 0 || ! rbf_is_plugin_network_active() ) {
		return;
	}

	if ( ! function_exists( 'switch_to_blog' ) || ! function_exists( 'restore_current_blog' ) ) {
		return;
	}

	static $processed = array();

	if ( isset( $processed[ $site_id ] ) ) {
		return;
	}

	$switched = switch_to_blog( $site_id );

	if ( ! $switched ) {
		return;
	}

	$processed[ $site_id ] = true;

	try {
		rbf_run_site_activation_tasks( false );
	} finally {
		restore_current_blog();
	}
}

if ( function_exists( 'add_action' ) && function_exists( 'is_multisite' ) && is_multisite() ) {
	if ( ! function_exists( 'rbf_handle_wp_initialize_site' ) ) {
		/**
		 * Initialize plugin data when a new site is created (WordPress 5.1+).
		 *
		 * @param WP_Site $new_site Site object.
		 */
		function rbf_handle_wp_initialize_site( $new_site ) {
			$site_id = ( $new_site instanceof WP_Site ) ? (int) $new_site->blog_id : (int) $new_site;
			rbf_initialize_new_site( $site_id );
		}
	}

	if ( ! function_exists( 'rbf_handle_wpmu_new_blog' ) ) {
		/**
		 * Initialize plugin data for legacy multisite creation hook.
		 *
		 * @param int $blog_id Blog ID.
		 */
		function rbf_handle_wpmu_new_blog( $blog_id ) {
			rbf_initialize_new_site( $blog_id );
		}
	}

	add_action( 'wp_initialize_site', 'rbf_handle_wp_initialize_site', 20, 1 );
	add_action( 'wpmu_new_blog', 'rbf_handle_wpmu_new_blog', 20, 1 );
}

/**
 * Add quick access links within the plugins screen.
 *
 * Provides shortcuts to the main settings page and documentation so
 * administrators can configure the plugin immediately after activation.
 *
 * @param array $links Existing action links for the plugin.
 * @return array
 */
function rbf_plugin_action_links( $links ) {
	if ( ! is_array( $links ) ) {
		$links = array();
	}

	if ( ! function_exists( 'admin_url' ) ) {
		return $links;
	}

	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		esc_url( admin_url( 'admin.php?page=rbf_settings' ) ),
		esc_html__( 'Impostazioni', 'rbf' )
	);

	array_unshift( $links, $settings_link );

	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'rbf_plugin_action_links' );

/**
 * Append helpful resources to the plugin row meta links.
 *
 * @param array  $links Current plugin row meta links.
 * @param string $file  Plugin basename.
 * @return array
 */
function rbf_plugin_row_meta( $links, $file ) {
	if ( ! is_array( $links ) ) {
		$links = array();
	}

	if ( $file !== plugin_basename( __FILE__ ) ) {
		return $links;
	}

	$resources = array();

	$resources[] = sprintf(
		'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
		esc_url( 'https://github.com/franpass87/FP-Prenotazioni-Ristorante-PRO#readme' ),
		esc_html__( 'Documentazione', 'rbf' )
	);

	$resources[] = sprintf(
		'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
		esc_url( 'https://github.com/franpass87/FP-Prenotazioni-Ristorante-PRO/issues' ),
		esc_html__( 'Supporto', 'rbf' )
	);

	return array_merge( $links, $resources );
}
add_filter( 'plugin_row_meta', 'rbf_plugin_row_meta', 10, 2 );
