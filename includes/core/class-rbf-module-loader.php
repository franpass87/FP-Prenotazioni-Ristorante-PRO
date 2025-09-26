<?php
/**
 * Context-aware module loader for FP Prenotazioni Ristorante.
 *
 * @package FP_Prenotazioni_Ristorante_PRO
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Lightweight loader that conditionally requires plugin modules.
 */
class RBF_Module_Loader {
        /**
         * Base directory used to resolve relative module paths.
         *
         * @var string
         */
        private $base_dir = '';

        /**
         * Registered modules grouped by execution context.
         *
         * @var array<string, array<int, string>>
         */
        private $modules = array(
                'shared'   => array(),
                'admin'    => array(),
                'frontend' => array(),
                'cli'      => array(),
        );

        /**
         * Track which module groups have already been loaded.
         *
         * @var array<string, bool>
         */
        private $loaded_groups = array(
                'shared'   => false,
                'admin'    => false,
                'frontend' => false,
                'cli'      => false,
        );

        /**
         * Constructor.
         *
         * @param string $base_dir Directory used as root for relative modules.
         */
        public function __construct( $base_dir ) {
                if ( is_string( $base_dir ) ) {
                        $this->base_dir = rtrim( $base_dir, '/\\' ) . DIRECTORY_SEPARATOR;
                }
        }

        /**
         * Register multiple groups of modules at once.
         *
         * @param array<string, array<int, string>> $module_map Group => modules map.
         * @return $this
         */
        public function register_modules( array $module_map ) {
                foreach ( $module_map as $group => $modules ) {
                        $this->register_group( $group, (array) $modules );
                }

                return $this;
        }

        /**
         * Register a list of modules under a specific group.
         *
         * @param string              $group   Module group name.
         * @param array<int, string>  $modules Module paths relative to $base_dir or absolute.
         * @return void
         */
        public function register_group( $group, array $modules ) {
                if ( ! is_string( $group ) || $group === '' ) {
                        return;
                }

                if ( ! isset( $this->modules[ $group ] ) ) {
                        $this->modules[ $group ]      = array();
                        $this->loaded_groups[ $group ] = false;
                }

                foreach ( $modules as $module ) {
                        $path = $this->normalize_module_path( $module );

                        if ( $path === '' ) {
                                continue;
                        }

                        if ( ! in_array( $path, $this->modules[ $group ], true ) ) {
                                $this->modules[ $group ][] = $path;
                        }
                }
        }

        /**
         * Load all registered modules appropriate for the current context.
         *
         * @return void
         */
        public function load_registered_modules() {
                $this->load_group( 'shared' );

                if ( $this->should_load_admin_modules() ) {
                        $this->load_group( 'admin' );
                }

                if ( $this->should_load_frontend_modules() ) {
                        $this->load_group( 'frontend' );
                }

                if ( $this->should_load_cli_modules() ) {
                        $this->load_group( 'cli' );
                }
        }

        /**
         * Force loading for a given group regardless of context detection.
         *
         * @param string $group Group name.
         * @return void
         */
        public function load_group( $group ) {
                if ( empty( $this->modules[ $group ] ) ) {
                        $this->loaded_groups[ $group ] = true;
                        return;
                }

                if ( isset( $this->loaded_groups[ $group ] ) && true === $this->loaded_groups[ $group ] ) {
                        return;
                }

                foreach ( $this->modules[ $group ] as $module ) {
                        if ( $module === '' ) {
                                continue;
                        }

                        if ( file_exists( $module ) ) {
                                require_once $module;
                        }
                }

                $this->loaded_groups[ $group ] = true;
        }

        /**
         * Determine whether admin modules should load in the current request.
         *
         * @return bool
         */
        protected function should_load_admin_modules() {
                return function_exists( 'is_admin' ) && is_admin();
        }

        /**
         * Determine whether frontend modules should load in the current request.
         *
         * @return bool
         */
        protected function should_load_frontend_modules() {
                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                        return false;
                }

                if ( function_exists( 'is_admin' ) && is_admin() ) {
                        if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
                                return true;
                        }

                        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
                                return true;
                        }

                        if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
                                return true;
                        }

                        return false;
                }

                return true;
        }

        /**
         * Determine whether CLI modules should load in the current request.
         *
         * @return bool
         */
        protected function should_load_cli_modules() {
                return defined( 'WP_CLI' ) && WP_CLI;
        }

        /**
         * Convert a module path into an absolute path.
         *
         * @param string $module Module path.
         * @return string
         */
        protected function normalize_module_path( $module ) {
                if ( ! is_string( $module ) ) {
                        return '';
                }

                $module = trim( $module );

                if ( $module === '' ) {
                        return '';
                }

                // Absolute path.
                if ( $module[0] === '/' || preg_match( '#^[A-Za-z]:[\\/]#', $module ) ) {
                        return $module;
                }

                $module = str_replace( array( '/', '\\' ), DIRECTORY_SEPARATOR, $module );

                return $this->base_dir . ltrim( $module, DIRECTORY_SEPARATOR );
        }
}
