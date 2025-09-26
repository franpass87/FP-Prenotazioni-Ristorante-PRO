<?php
/**
 * Runtime logging helpers for capturing notices, warnings and errors during QA.
 *
 * The logger mirrors `WP_DEBUG_LOG` but is self-contained so QA engineers can
 * bundle the generated log within the plugin repository without touching the
 * host environment configuration.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'rbf_runtime_logger_bootstrap' ) ) {
	/**
	 * Initialize runtime logging by registering error, exception and shutdown handlers.
	 */
	function rbf_runtime_logger_bootstrap() {
		static $bootstrapped = false;

		if ( $bootstrapped || ! rbf_runtime_logger_should_capture() ) {
			return;
		}

		$bootstrapped = true;

		$log_path = rbf_runtime_logger_get_path();
		if ( ! $log_path ) {
			return;
		}

		rbf_runtime_logger_append( '=== Runtime logging session started at ' . gmdate( 'c' ) . ' ===' );

		$previous_error_handler = set_error_handler( 'rbf_runtime_logger_error_handler' );
		if ( $previous_error_handler ) {
			$GLOBALS['rbf_runtime_logger_previous_error_handler'] = $previous_error_handler;
		}

		$previous_exception_handler = set_exception_handler( 'rbf_runtime_logger_exception_handler' );
		if ( $previous_exception_handler ) {
			$GLOBALS['rbf_runtime_logger_previous_exception_handler'] = $previous_exception_handler;
		}

		register_shutdown_function( 'rbf_runtime_logger_shutdown_handler' );

		if ( function_exists( 'add_action' ) ) {
			add_action( 'doing_it_wrong_run', 'rbf_runtime_logger_handle_doing_it_wrong', 10, 3 );
			add_action( 'deprecated_function_run', 'rbf_runtime_logger_handle_deprecated', 10, 3 );
			add_action( 'deprecated_argument_run', 'rbf_runtime_logger_handle_deprecated_argument', 10, 3 );
			add_action( 'deprecated_hook_run', 'rbf_runtime_logger_handle_deprecated_hook', 10, 4 );
			add_action( 'deprecated_file_included', 'rbf_runtime_logger_handle_deprecated_file', 10, 4 );
		}
	}
}

if ( ! function_exists( 'rbf_runtime_logger_should_capture' ) ) {
	/**
	 * Determine if runtime logging should capture events.
	 *
	 * Logging is disabled for WP-CLI commands unless explicitly forced because the
	 * WordPress bootstrap frequently triggers deprecation notices in that context.
	 *
	 * @return bool
	 */
	function rbf_runtime_logger_should_capture() {
		if ( defined( 'RBF_ENABLE_RUNTIME_LOGGING' ) ) {
			return (bool) RBF_ENABLE_RUNTIME_LOGGING;
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return ( defined( 'RBF_FORCE_RUNTIME_LOG' ) && RBF_FORCE_RUNTIME_LOG );
		}

		if ( defined( 'RBF_FORCE_RUNTIME_LOG' ) ) {
			return (bool) RBF_FORCE_RUNTIME_LOG;
		}

		return ( defined( 'WP_DEBUG' ) && WP_DEBUG );
	}
}

if ( ! function_exists( 'rbf_runtime_logger_get_path' ) ) {
	/**
	 * Retrieve the absolute path to the runtime log file, ensuring directories exist.
	 *
	 * @return string|null
	 */
	function rbf_runtime_logger_get_path() {
                $base_path = rtrim( RBF_PLUGIN_DIR, '/\\' );

		if ( function_exists( 'trailingslashit' ) ) {
			$base_path = trailingslashit( $base_path );
		} else {
			$base_path .= DIRECTORY_SEPARATOR;
		}

		$log_path  = $base_path . 'docs/audit/runtime-issues.log';
		$directory = dirname( $log_path );

		if ( ! is_dir( $directory ) ) {
			if ( function_exists( 'wp_mkdir_p' ) ) {
				wp_mkdir_p( $directory );
			} else {
				mkdir( $directory, 0777, true );
			}
		}

		if ( ! is_dir( $directory ) || ( file_exists( $log_path ) && ! is_writable( $log_path ) ) ) {
			return null;
		}

		if ( ! file_exists( $log_path ) ) {
			file_put_contents( $log_path, '' );
		}

		return $log_path;
	}
}

if ( ! function_exists( 'rbf_runtime_logger_append' ) ) {
	/**
	 * Append a message to the runtime log with optional contextual data.
	 *
	 * @param string $message Log line.
	 * @param array  $context Additional context values.
	 */
	function rbf_runtime_logger_append( $message, $context = array() ) {
		if ( ! rbf_runtime_logger_should_capture() ) {
			return;
		}

		$log_path = rbf_runtime_logger_get_path();
		if ( ! $log_path ) {
			return;
		}

		$timestamp = gmdate( 'Y-m-d H:i:s' );
		$line      = '[' . $timestamp . '] ' . $message;

		if ( ! empty( $context ) ) {
			$encoded = function_exists( 'wp_json_encode' )
				? wp_json_encode( $context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
				: json_encode( $context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

			if ( $encoded ) {
				$line .= ' ' . $encoded;
			}
		}

		file_put_contents( $log_path, $line . PHP_EOL, FILE_APPEND | LOCK_EX );
	}
}

if ( ! function_exists( 'rbf_runtime_logger_error_handler' ) ) {
	/**
	 * Error handler that records PHP warnings and notices without preventing default handling.
	 */
	function rbf_runtime_logger_error_handler( $severity, $message, $file, $line ) {
		$levels = array(
			E_WARNING        => 'WARNING',
			E_USER_WARNING   => 'WARNING',
			E_NOTICE         => 'NOTICE',
			E_USER_NOTICE    => 'NOTICE',
			E_STRICT         => 'STRICT',
			E_DEPRECATED     => 'DEPRECATED',
			E_USER_DEPRECATED => 'DEPRECATED',
		);

		$label = isset( $levels[ $severity ] ) ? $levels[ $severity ] : 'ERROR';

		rbf_runtime_logger_append(
			sprintf( '%s: %s in %s on line %d', $label, $message, $file, $line ),
			array( 'severity' => $severity )
		);

		if ( isset( $GLOBALS['rbf_runtime_logger_previous_error_handler'] ) && is_callable( $GLOBALS['rbf_runtime_logger_previous_error_handler'] ) ) {
			return call_user_func( $GLOBALS['rbf_runtime_logger_previous_error_handler'], $severity, $message, $file, $line );
		}

		return false;
	}
}

if ( ! function_exists( 'rbf_runtime_logger_exception_handler' ) ) {
	/**
	 * Exception handler that forwards to the previous handler after logging.
	 */
	function rbf_runtime_logger_exception_handler( $exception ) {
		rbf_runtime_logger_append(
			sprintf( 'UNCAUGHT EXCEPTION: %s in %s on line %d', $exception->getMessage(), $exception->getFile(), $exception->getLine() ),
			array( 'type' => get_class( $exception ) )
		);

		if ( isset( $GLOBALS['rbf_runtime_logger_previous_exception_handler'] ) && is_callable( $GLOBALS['rbf_runtime_logger_previous_exception_handler'] ) ) {
			call_user_func( $GLOBALS['rbf_runtime_logger_previous_exception_handler'], $exception );
			return;
		}

		throw $exception;
	}
}

if ( ! function_exists( 'rbf_runtime_logger_shutdown_handler' ) ) {
	/**
	 * Shutdown handler to capture fatal errors.
	 */
	function rbf_runtime_logger_shutdown_handler() {
		$error = error_get_last();

		if ( ! $error ) {
			return;
		}

		$fatal_types = array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR );
		if ( ! in_array( $error['type'], $fatal_types, true ) ) {
			return;
		}

		rbf_runtime_logger_append(
			sprintf( 'FATAL: %s in %s on line %d', $error['message'], $error['file'], $error['line'] ),
			array( 'severity' => $error['type'] )
		);
	}
}

if ( ! function_exists( 'rbf_runtime_logger_handle_doing_it_wrong' ) ) {
	function rbf_runtime_logger_handle_doing_it_wrong( $function, $message, $version ) {
		rbf_runtime_logger_append(
			sprintf( 'DOING_IT_WRONG: %s %s (since %s)', $function, $message, $version )
		);
	}
}

if ( ! function_exists( 'rbf_runtime_logger_handle_deprecated' ) ) {
	function rbf_runtime_logger_handle_deprecated( $function, $replacement, $version ) {
		rbf_runtime_logger_append(
			sprintf( 'DEPRECATED_FUNCTION: %s (replacement: %s, since %s)', $function, $replacement, $version )
		);
	}
}

if ( ! function_exists( 'rbf_runtime_logger_handle_deprecated_argument' ) ) {
	function rbf_runtime_logger_handle_deprecated_argument( $function, $message, $version ) {
		rbf_runtime_logger_append(
			sprintf( 'DEPRECATED_ARGUMENT: %s %s (since %s)', $function, $message, $version )
		);
	}
}

if ( ! function_exists( 'rbf_runtime_logger_handle_deprecated_hook' ) ) {
	function rbf_runtime_logger_handle_deprecated_hook( $hook, $replacement, $version, $message ) {
		rbf_runtime_logger_append(
			sprintf( 'DEPRECATED_HOOK: %s (replacement: %s, since %s) %s', $hook, $replacement, $version, $message )
		);
	}
}

if ( ! function_exists( 'rbf_runtime_logger_handle_deprecated_file' ) ) {
	function rbf_runtime_logger_handle_deprecated_file( $file, $replacement, $version, $message ) {
		rbf_runtime_logger_append(
			sprintf( 'DEPRECATED_FILE: %s (replacement: %s, since %s) %s', $file, $replacement, $version, $message )
		);
	}
}
