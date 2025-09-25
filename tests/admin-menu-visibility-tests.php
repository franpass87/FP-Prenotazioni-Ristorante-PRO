<?php
/**
 * Basic regression test to ensure the admin menu exposes all key pages.
 */
declare(strict_types=1);

error_reporting(E_ALL);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!function_exists('__')) {
    function __($text, $domain = null)
    {
        return $text;
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value)
    {
        return $value;
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1)
    {
        $GLOBALS['rbf_registered_hooks'][] = [$hook, $callback, $priority, $accepted_args];
        return true;
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1)
    {
        $GLOBALS['rbf_registered_filters'][] = [$hook, $callback, $priority, $accepted_args];
        return true;
    }
}

if (!function_exists('get_locale')) {
    function get_locale()
    {
        return 'it_IT';
    }
}

$GLOBALS['rbf_menu_pages'] = [];
if (!function_exists('add_menu_page')) {
    function add_menu_page($page_title, $menu_title, $capability, $menu_slug, $callback = '', $icon_url = '', $position = null)
    {
        $entry = [
            'page_title' => $page_title,
            'menu_title' => $menu_title,
            'capability' => $capability,
            'menu_slug'  => $menu_slug,
            'callback'   => $callback,
            'icon_url'   => $icon_url,
            'position'   => $position,
        ];
        $GLOBALS['rbf_menu_pages'][] = $entry;

        return 'toplevel_page_' . $menu_slug;
    }
}

$GLOBALS['rbf_submenu_pages'] = [];
if (!function_exists('add_submenu_page')) {
    function add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback = '')
    {
        $entry = [
            'parent_slug' => $parent_slug,
            'page_title'  => $page_title,
            'menu_title'  => $menu_title,
            'capability'  => $capability,
            'menu_slug'   => $menu_slug,
            'callback'    => $callback,
        ];
        $GLOBALS['rbf_submenu_pages'][] = $entry;

        return $menu_slug;
    }
}

require_once __DIR__ . '/../includes/utils.php';
require_once __DIR__ . '/../includes/admin.php';

rbf_create_bookings_menu();

$top_level = $GLOBALS['rbf_menu_pages'][0] ?? null;
if ($top_level === null) {
    throw new RuntimeException('Top-level menu was not registered.');
}

$expected_top_level = [
    'page_title' => 'FP Prenotazioni Ristorante',
    'menu_title' => 'FP Prenotazioni Ristorante',
    'capability' => 'rbf_manage_bookings',
    'menu_slug'  => 'rbf_calendar',
];

foreach ($expected_top_level as $key => $expected_value) {
    $actual = $top_level[$key] ?? null;
    if ($actual !== $expected_value) {
        throw new RuntimeException(sprintf(
            'Expected top-level menu %s to be "%s" but found "%s".',
            $key,
            $expected_value,
            $actual ?? '(missing)'
        ));
    }
}

$expected_submenus = [
    [
        'parent_slug' => 'rbf_calendar',
        'page_title'  => 'Calendario',
        'menu_title'  => 'Calendario',
        'capability'  => 'rbf_manage_bookings',
        'menu_slug'   => 'rbf_calendar',
    ],
    [
        'parent_slug' => 'rbf_calendar',
        'page_title'  => 'Agenda Settimanale',
        'menu_title'  => 'Agenda',
        'capability'  => 'rbf_manage_bookings',
        'menu_slug'   => 'rbf_weekly_staff',
    ],
    [
        'parent_slug' => 'rbf_calendar',
        'page_title'  => 'Nuova Prenotazione Manuale',
        'menu_title'  => 'Nuova Prenotazione Manuale',
        'capability'  => 'rbf_manage_bookings',
        'menu_slug'   => 'rbf_add_booking',
    ],
    [
        'parent_slug' => 'rbf_calendar',
        'page_title'  => 'Gestione Tavoli',
        'menu_title'  => 'Gestione Tavoli',
        'capability'  => 'rbf_manage_bookings',
        'menu_slug'   => 'rbf_tables',
    ],
    [
        'parent_slug' => 'rbf_calendar',
        'page_title'  => 'Report & Analytics',
        'menu_title'  => 'Report & Analytics',
        'capability'  => 'rbf_manage_bookings',
        'menu_slug'   => 'rbf_reports',
    ],
    [
        'parent_slug' => 'rbf_calendar',
        'page_title'  => 'Notifiche Email',
        'menu_title'  => 'Notifiche Email',
        'capability'  => 'manage_options',
        'menu_slug'   => 'rbf_email_notifications',
    ],
    [
        'parent_slug' => 'rbf_calendar',
        'page_title'  => 'Esporta Dati',
        'menu_title'  => 'Esporta Dati',
        'capability'  => 'rbf_manage_bookings',
        'menu_slug'   => 'rbf_export',
    ],
    [
        'parent_slug' => 'rbf_calendar',
        'page_title'  => 'Impostazioni',
        'menu_title'  => 'Impostazioni',
        'capability'  => 'manage_options',
        'menu_slug'   => 'rbf_settings',
    ],
    [
        'parent_slug' => 'rbf_calendar',
        'page_title'  => 'Validazione Tracking',
        'menu_title'  => 'Validazione Tracking',
        'capability'  => 'manage_options',
        'menu_slug'   => 'rbf_tracking_validation',
    ],
];

foreach ($expected_submenus as $expected) {
    $found = false;
    foreach ($GLOBALS['rbf_submenu_pages'] as $submenu) {
        $matches = true;
        foreach ($expected as $key => $value) {
            if (($submenu[$key] ?? null) !== $value) {
                $matches = false;
                break;
            }
        }
        if ($matches) {
            $found = true;
            break;
        }
    }

    if (!$found) {
        throw new RuntimeException(sprintf(
            'Expected submenu "%s" was not registered.',
            $expected['page_title']
        ));
    }
}

echo "Admin menu visibility test passed.\n";

