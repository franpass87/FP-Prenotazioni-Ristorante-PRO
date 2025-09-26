<?php
/**
 * PHPUnit bootstrap for FP Prenotazioni Ristorante PRO plugin.
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    define('ABSPATH', sys_get_temp_dir() . DIRECTORY_SEPARATOR);
}

// Ensure globals used by fixtures start from a clean slate.
$GLOBALS['rbf_dummy_includes'] = [];
