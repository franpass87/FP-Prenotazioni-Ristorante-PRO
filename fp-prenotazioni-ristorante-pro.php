<?php
/**
 * Plugin Name: Prenotazioni Ristorante Completo (Flatpickr, lingua dinamica)
 * Description: Prenotazioni con calendario Flatpickr IT/EN.
 * Version:     9.4.0
 * Author:      Francesco Passeri
 * Text Domain: rbf
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Autoload if available.
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require __DIR__ . '/vendor/autoload.php';
}

// Includes.
require_once __DIR__ . '/includes/Helpers.php';
require_once __DIR__ . '/includes/Settings.php';
require_once __DIR__ . '/includes/PostType.php';
require_once __DIR__ . '/includes/AdminMenu.php';
require_once __DIR__ . '/includes/Shortcode.php';
require_once __DIR__ . '/includes/Ajax.php';
require_once __DIR__ . '/includes/Submission.php';
require_once __DIR__ . '/includes/Capacity.php';
require_once __DIR__ . '/includes/Tracking.php';
require_once __DIR__ . '/includes/Mailer.php';
require_once __DIR__ . '/includes/Brevo.php';
require_once __DIR__ . '/includes/Rest.php';
require_once __DIR__ . '/includes/CLI.php';
