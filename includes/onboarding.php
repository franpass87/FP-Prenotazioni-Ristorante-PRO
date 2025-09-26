<?php
/**
 * Guided onboarding wizard to configure the plugin in a few steps.
 *
 * @package FP_Prenotazioni_Ristorante_PRO
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Retrieve the admin URL for the setup wizard, ensuring a valid string is returned.
 *
 * @return string
 */
function rbf_get_setup_wizard_admin_url() {
    $wizard_url = '';

    if (function_exists('menu_page_url')) {
        $wizard_url = menu_page_url('rbf_setup_wizard', false);
        if (!is_string($wizard_url)) {
            $wizard_url = '';
        }
    }

    $fallback_url = '';

    if (function_exists('is_network_admin') && is_network_admin() && function_exists('network_admin_url')) {
        $fallback_url = network_admin_url('admin.php?page=rbf_setup_wizard');
    } elseif (function_exists('admin_url')) {
        $fallback_url = admin_url('admin.php?page=rbf_setup_wizard');
    } elseif (function_exists('site_url')) {
        $site_base = site_url();
        if (function_exists('trailingslashit')) {
            $site_base = trailingslashit($site_base);
        } else {
            $site_base = rtrim($site_base, '/\\') . '/';
        }
        $fallback_url = $site_base . 'wp-admin/admin.php?page=rbf_setup_wizard';
    }

    if ($wizard_url === '' && $fallback_url !== '') {
        $wizard_url = $fallback_url;
    }

    if (is_string($wizard_url) && $wizard_url !== '') {
        $parsed_url = function_exists('wp_parse_url') ? wp_parse_url($wizard_url) : parse_url($wizard_url);

        $has_host = is_array($parsed_url) && !empty($parsed_url['host']);
        $path = is_array($parsed_url) && isset($parsed_url['path']) ? (string) $parsed_url['path'] : '';
        $has_wp_admin_path = $path !== '' && strpos($path, 'wp-admin') !== false;
        $is_relative_admin = !$has_host && strpos($wizard_url, 'admin.php') === 0;

        if (!$is_relative_admin && !$has_wp_admin_path && $fallback_url !== '') {
            $wizard_url = $fallback_url;
        }
    }

    /**
     * Filter the admin URL used for the setup wizard entry point.
     *
     * @param string $wizard_url Default URL for the setup wizard.
     */
    $wizard_url = apply_filters('rbf_setup_wizard_admin_url', $wizard_url);

    if (!is_string($wizard_url) || $wizard_url === '') {
        return $fallback_url;
    }

    return $wizard_url;
}

/**
 * Register submenu and notices for the onboarding wizard.
 */
add_action('admin_menu', 'rbf_register_setup_wizard_menu', 8);
function rbf_register_setup_wizard_menu() {
    add_submenu_page(
        'rbf_calendar',
        rbf_translate_string('Setup Guidato'),
        rbf_translate_string('Setup Guidato'),
        rbf_get_settings_capability(),
        'rbf_setup_wizard',
        'rbf_render_setup_wizard_page',
        1
    );
}

add_action('admin_notices', 'rbf_setup_wizard_admin_notice');
function rbf_setup_wizard_admin_notice() {
    if (!current_user_can(rbf_get_settings_capability())) {
        return;
    }

    if (get_option('rbf_setup_wizard_dismissed')) {
        return;
    }

    $settings = function_exists('rbf_get_settings') ? rbf_get_settings() : [];

    if (!empty($settings['setup_completed_at']) || get_option('rbf_setup_wizard_completed')) {
        return;
    }

    $seeded_defaults = (bool) get_option('rbf_bootstrap_defaults_seeded');

    if (!$seeded_defaults && rbf_has_configured_meals($settings)) {
        return;
    }

    $wizard_url = rbf_get_setup_wizard_admin_url();
    $dismiss_url = wp_nonce_url(add_query_arg('rbf-dismiss-setup', '1'), 'rbf-dismiss-setup');

    echo '<div class="notice notice-warning is-dismissible rbf-setup-notice">';
    echo '<p><strong>' . esc_html(rbf_translate_string('Configura il modulo di prenotazione in pochi minuti.')) . '</strong></p>';
    echo '<p>' . esc_html(rbf_translate_string('Il modulo frontend è inattivo finché non crei almeno un servizio. Usa il setup guidato per creare pranzo/cena, gli orari e le notifiche.')) . '</p>';
    echo '<p>';
    echo '<a class="button button-primary" href="' . esc_url($wizard_url) . '">' . esc_html(rbf_translate_string('Avvia setup guidato')) . '</a> ';
    echo '<a class="button-link" href="' . esc_url($dismiss_url) . '">' . esc_html(rbf_translate_string('Non mostrare più')) . '</a>';
    echo '</p>';
    echo '</div>';
}

add_action('admin_init', function() {
    if (!current_user_can(rbf_get_settings_capability())) {
        return;
    }

    if (!empty($_GET['rbf-dismiss-setup']) && check_admin_referer('rbf-dismiss-setup')) {
        update_option('rbf_setup_wizard_dismissed', 1);
        wp_safe_redirect(remove_query_arg(['rbf-dismiss-setup', '_wpnonce']));
        exit;
    }
});

/**
 * Helper: retrieve persisted wizard state.
 *
 * @return array
 */
function rbf_get_setup_wizard_state() {
    $state = get_option('rbf_setup_wizard_state', []);

    return is_array($state) ? $state : [];
}

/**
 * Persist wizard state between steps.
 *
 * @param array $state State to save.
 * @return void
 */
function rbf_update_setup_wizard_state(array $state) {
    update_option('rbf_setup_wizard_state', $state, false);
}

/**
 * Reset wizard state after completion or cancellation.
 *
 * @return void
 */
function rbf_reset_setup_wizard_state() {
    delete_option('rbf_setup_wizard_state');
}

/**
 * Generate a normalized ID for services created in the wizard.
 *
 * @param string $name Raw service name.
 * @return string
 */
function rbf_setup_generate_service_id($name) {
    $slug = sanitize_title($name);
    return $slug !== '' ? $slug : uniqid('servizio_', true);
}

/**
 * Build time slots string from range + interval.
 *
 * @param string $start Start time (HH:MM).
 * @param string $end   End time (HH:MM).
 * @param int    $interval Minutes between slots.
 * @return string
 */
function rbf_setup_generate_time_slots($start, $end, $interval) {
    if (function_exists('rbf_generate_time_slots_range')) {
        return rbf_generate_time_slots_range($start, $end, $interval);
    }

    $start_dt = DateTime::createFromFormat('H:i', $start);
    $end_dt = DateTime::createFromFormat('H:i', $end);
    $interval = max(5, (int) $interval);

    if (!$start_dt || !$end_dt) {
        return '';
    }

    if ($end_dt <= $start_dt) {
        $end_dt->modify('+1 hour');
    }

    $slots = [];
    $current = clone $start_dt;

    while ($current <= $end_dt) {
        $slots[] = $current->format('H:i');
        $current->modify('+' . $interval . ' minutes');
    }

    return implode(',', $slots);
}

/**
 * Apply wizard data to plugin settings.
 *
 * @param array $state Wizard data.
 * @return void
 */
function rbf_apply_setup_wizard_state(array $state) {
    $settings = rbf_get_settings();
    $services = $state['services'] ?? [];
    $integrations = $state['integrations'] ?? [];
    $should_create_page = !empty($state['create_booking_page']);
    $should_seed_tables = !empty($state['seed_default_tables']);

    $existing_booking_page_id = function_exists('rbf_detect_booking_page_id') ? rbf_detect_booking_page_id() : 0;
    $existing_tables = function_exists('rbf_table_setup_exists') ? rbf_table_setup_exists() : false;

    $wizard_result = [
        'booking_page_id' => 0,
        'booking_page_url' => '',
        'created_booking_page' => false,
        'updated_booking_page' => false,
        'had_booking_page' => $existing_booking_page_id > 0,
        'seeded_tables' => false,
        'tables_were_present' => $existing_tables,
        'tables_available' => $existing_tables,
    ];

    $meals = [];
    foreach ($services as $service_id => $service) {
        if (empty($service['enabled'])) {
            continue;
        }

        $name = $service['name'] ?? ucfirst($service_id);
        $start = $service['start'] ?? '12:00';
        $end = $service['end'] ?? '14:00';
        $interval = max(10, (int) ($service['interval'] ?? 30));
        $capacity = max(10, (int) ($service['capacity'] ?? 30));
        $buffer = max(5, (int) ($service['buffer'] ?? 15));
        $buffer_pp = max(0, (int) ($service['buffer_per_person'] ?? 5));
        $overbooking = max(0, min(100, (int) ($service['overbooking'] ?? 10)));
        $available_days = array_values(array_intersect(
            ['mon','tue','wed','thu','fri','sat','sun'],
            array_map('sanitize_text_field', (array) ($service['days'] ?? []))
        ));

        if (empty($available_days)) {
            $available_days = ['mon','tue','wed','thu','fri','sat'];
        }

        $time_slots = !empty($service['time_slots'])
            ? sanitize_text_field($service['time_slots'])
            : rbf_setup_generate_time_slots($start, $end, $interval);

        $meals[] = [
            'id' => $service_id,
            'name' => $name,
            'enabled' => true,
            'capacity' => $capacity,
            'time_slots' => $time_slots,
            'available_days' => $available_days,
            'buffer_time_minutes' => $buffer,
            'buffer_time_per_person' => $buffer_pp,
            'overbooking_limit' => $overbooking,
            'tooltip' => sanitize_text_field($service['tooltip'] ?? ''),
        ];
    }

    if (!empty($meals)) {
        $settings['custom_meals'] = $meals;
        $settings['use_custom_meals'] = 'yes';
    }

    if (!empty($integrations['notification_email']) && is_email($integrations['notification_email'])) {
        $settings['notification_email'] = sanitize_email($integrations['notification_email']);
    }

    if (!empty($integrations['ga4_id'])) {
        $settings['ga4_id'] = sanitize_text_field($integrations['ga4_id']);
    }

    if (!empty($integrations['ga4_api_secret'])) {
        $settings['ga4_api_secret'] = sanitize_text_field($integrations['ga4_api_secret']);
    }

    if (!empty($integrations['meta_pixel_id']) && ctype_digit($integrations['meta_pixel_id'])) {
        $settings['meta_pixel_id'] = sanitize_text_field($integrations['meta_pixel_id']);
    }

    if (!empty($integrations['meta_access_token'])) {
        $settings['meta_access_token'] = sanitize_text_field($integrations['meta_access_token']);
    }

    if ($should_create_page && function_exists('rbf_ensure_booking_page_exists')) {
        $page_result = rbf_ensure_booking_page_exists([
            'update_settings' => false,
        ]);

        if (!empty($page_result['page_id'])) {
            $settings['booking_page_id'] = (int) $page_result['page_id'];
            $wizard_result['booking_page_id'] = (int) $page_result['page_id'];
            $wizard_result['created_booking_page'] = !empty($page_result['created']);
            $wizard_result['updated_booking_page'] = !empty($page_result['updated']);
            if (!empty($page_result['page_url'])) {
                $wizard_result['booking_page_url'] = $page_result['page_url'];
            }
        }
    } elseif ($existing_booking_page_id > 0) {
        if (empty($settings['booking_page_id'])) {
            $settings['booking_page_id'] = $existing_booking_page_id;
        }
        $wizard_result['booking_page_id'] = $existing_booking_page_id;
    }

    if ($should_seed_tables && function_exists('rbf_create_default_table_setup')) {
        rbf_create_default_table_setup();
        $has_tables_after = function_exists('rbf_table_setup_exists') ? rbf_table_setup_exists() : $existing_tables;
        $wizard_result['seeded_tables'] = !$existing_tables && $has_tables_after;
        $wizard_result['tables_available'] = $has_tables_after;
    }

    if ($wizard_result['booking_page_url'] === '' && $wizard_result['booking_page_id'] > 0 && function_exists('get_permalink')) {
        $permalink = get_permalink($wizard_result['booking_page_id']);
        if (is_string($permalink)) {
            $wizard_result['booking_page_url'] = $permalink;
        }
    }

    if (function_exists('rbf_detect_booking_page_id')) {
        $detected_id = rbf_detect_booking_page_id(true);
        if ($wizard_result['booking_page_id'] === 0 && $detected_id > 0) {
            $wizard_result['booking_page_id'] = $detected_id;
            if ($wizard_result['booking_page_url'] === '' && function_exists('get_permalink')) {
                $wizard_result['booking_page_url'] = get_permalink($detected_id);
            }
        }
    }

    if (!$wizard_result['tables_available'] && function_exists('rbf_table_setup_exists')) {
        $wizard_result['tables_available'] = rbf_table_setup_exists();
    }

    $settings['setup_completed_at'] = current_time('mysql');

    update_option('rbf_settings', $settings);
    rbf_invalidate_settings_cache();

    if (function_exists('rbf_set_tracking_package_enabled')) {
        $ga4_enabled = !empty($settings['ga4_id']);
        $meta_enabled = !empty($settings['meta_pixel_id']) && !empty($settings['meta_access_token']);

        rbf_set_tracking_package_enabled('ga4_basic', $ga4_enabled);
        rbf_set_tracking_package_enabled('meta_standard', $meta_enabled);
    }

    update_option('rbf_setup_wizard_completed', 1, false);
    update_option('rbf_setup_wizard_dismissed', 1, false);
    update_option('rbf_setup_wizard_result', $wizard_result, false);
    rbf_reset_setup_wizard_state();
}

/**
 * Render wizard steps and handle submissions.
 */
function rbf_render_setup_wizard_page() {
    if (!rbf_require_settings_capability()) {
        return;
    }

    $state = rbf_get_setup_wizard_state();
    $step = isset($_GET['step']) ? sanitize_key($_GET['step']) : 'welcome';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rbf_setup_step'])) {
        check_admin_referer('rbf_setup_wizard_step');

        $posted_step = sanitize_key($_POST['rbf_setup_step']);

        if ($posted_step === 'services') {
            $services = [];
            $raw_services = $_POST['services'] ?? [];

            foreach ($raw_services as $key => $service) {
                $service_id = rbf_setup_generate_service_id($service['name'] ?? $key);

                $services[$service_id] = [
                    'name' => sanitize_text_field($service['name'] ?? ''),
                    'start' => sanitize_text_field($service['start'] ?? '12:00'),
                    'end' => sanitize_text_field($service['end'] ?? '14:00'),
                    'interval' => max(10, (int) ($service['interval'] ?? 30)),
                    'capacity' => max(1, (int) ($service['capacity'] ?? 30)),
                    'buffer' => max(0, (int) ($service['buffer'] ?? 15)),
                    'buffer_per_person' => max(0, (int) ($service['buffer_per_person'] ?? 5)),
                    'overbooking' => max(0, min(100, (int) ($service['overbooking'] ?? 10))),
                    'days' => array_map('sanitize_text_field', (array) ($service['days'] ?? [])),
                    'tooltip' => sanitize_text_field($service['tooltip'] ?? ''),
                    'enabled' => !empty($service['enabled']),
                ];
            }

            $state['services'] = $services;
            rbf_update_setup_wizard_state($state);
            $step = 'integrations';
        } elseif ($posted_step === 'integrations') {
            $state['integrations'] = [
                'notification_email' => sanitize_email($_POST['notification_email'] ?? ''),
                'ga4_id' => sanitize_text_field($_POST['ga4_id'] ?? ''),
                'ga4_api_secret' => sanitize_text_field($_POST['ga4_api_secret'] ?? ''),
                'meta_pixel_id' => sanitize_text_field($_POST['meta_pixel_id'] ?? ''),
                'meta_access_token' => sanitize_text_field($_POST['meta_access_token'] ?? ''),
            ];
            rbf_update_setup_wizard_state($state);
            $step = 'summary';
        } elseif ($posted_step === 'summary') {
            $state['create_booking_page'] = !empty($_POST['create_booking_page']);
            $state['seed_default_tables'] = !empty($_POST['seed_default_tables']);
            rbf_update_setup_wizard_state($state);
            rbf_apply_setup_wizard_state($state);
            $step = 'completed';
        }
    }

    $default_days = ['mon','tue','wed','thu','fri','sat','sun'];
    $day_labels = [
        'mon' => rbf_translate_string('Lunedì'),
        'tue' => rbf_translate_string('Martedì'),
        'wed' => rbf_translate_string('Mercoledì'),
        'thu' => rbf_translate_string('Giovedì'),
        'fri' => rbf_translate_string('Venerdì'),
        'sat' => rbf_translate_string('Sabato'),
        'sun' => rbf_translate_string('Domenica'),
    ];

    $services = $state['services'] ?? [];
    if (empty($services)) {
        $services = [
            'pranzo' => [
                'name' => 'Pranzo',
                'start' => '12:00',
                'end' => '14:30',
                'interval' => 30,
                'capacity' => 30,
                'buffer' => 15,
                'buffer_per_person' => 5,
                'overbooking' => 10,
                'days' => $default_days,
                'enabled' => true,
                'tooltip' => '',
            ],
            'cena' => [
                'name' => 'Cena',
                'start' => '19:00',
                'end' => '22:00',
                'interval' => 30,
                'capacity' => 40,
                'buffer' => 20,
                'buffer_per_person' => 5,
                'overbooking' => 5,
                'days' => ['tue','wed','thu','fri','sat'],
                'enabled' => true,
                'tooltip' => '',
            ],
        ];
    }

    $integrations = $state['integrations'] ?? [];
    $existing_booking_page_id = function_exists('rbf_detect_booking_page_id') ? rbf_detect_booking_page_id() : 0;
    $existing_booking_page_url = ($existing_booking_page_id && function_exists('get_permalink'))
        ? get_permalink($existing_booking_page_id)
        : '';
    $existing_booking_page_title = ($existing_booking_page_id && function_exists('get_the_title'))
        ? get_the_title($existing_booking_page_id)
        : '';
    $has_table_setup = function_exists('rbf_table_setup_exists') ? rbf_table_setup_exists() : false;

    $default_create_page = array_key_exists('create_booking_page', $state)
        ? !empty($state['create_booking_page'])
        : ($existing_booking_page_id === 0);
    $default_seed_tables = array_key_exists('seed_default_tables', $state)
        ? !empty($state['seed_default_tables'])
        : !$has_table_setup;

    echo '<div class="wrap rbf-setup-wizard">';
    echo '<h1>' . esc_html(rbf_translate_string('Setup guidato prenotazioni')) . '</h1>';
    echo '<p class="description">' . esc_html(rbf_translate_string('Completa i passaggi per attivare il modulo in frontend con pasti, orari e tracking base.')) . '</p>';

    echo '<ol class="rbf-setup-steps">';
    $steps = [
        'welcome' => rbf_translate_string('Introduzione'),
        'services' => rbf_translate_string('Servizi & Orari'),
        'integrations' => rbf_translate_string('Notifiche & Tracking'),
        'summary' => rbf_translate_string('Riepilogo'),
        'completed' => rbf_translate_string('Fatto'),
    ];

    foreach ($steps as $step_key => $label) {
        $class = 'rbf-step-item';
        if ($step === $step_key) {
            $class .= ' is-active';
        }
        echo '<li class="' . esc_attr($class) . '">' . esc_html($label) . '</li>';
    }
    echo '</ol>';

    if ($step === 'welcome') {
        echo '<div class="rbf-setup-card">';
        echo '<h2>' . esc_html(rbf_translate_string('Benvenuto!')) . '</h2>';
        echo '<p>' . esc_html(rbf_translate_string('Il setup guidato crea automaticamente pranzo e cena con orari consigliati, imposta le email di notifica e abilita gli eventi GA4/Meta. Puoi modificare tutto in seguito.')) . '</p>';
        $services_step_url = add_query_arg('step', 'services', rbf_get_setup_wizard_admin_url());

        echo '<a class="button button-primary button-hero" href="' . esc_url($services_step_url) . '">' . esc_html(rbf_translate_string('Iniziamo')) . '</a>';
        echo '</div>';
        echo '</div>';
        return;
    }

    if ($step === 'completed') {
        $result = get_option('rbf_setup_wizard_result', []);
        delete_option('rbf_setup_wizard_result');

        $messages = [];
        $booking_page_title = (!empty($result['booking_page_id']) && function_exists('get_the_title'))
            ? get_the_title((int) $result['booking_page_id'])
            : '';

        if (!empty($result['created_booking_page']) && !empty($result['booking_page_url'])) {
            $label = $booking_page_title !== '' ? $booking_page_title : $result['booking_page_url'];
            $messages[] = sprintf(
                rbf_translate_string('Pagina prenotazioni pubblicata: %s'),
                $label
            );
        } elseif (!empty($result['booking_page_id'])) {
            $messages[] = rbf_translate_string('Pagina prenotazioni già pronta: prova subito il form in frontend.');
        } else {
            $messages[] = rbf_translate_string('Aggiungi lo shortcode [ristorante_booking_form] a una pagina per completare il flusso pubblico.');
        }

        if (!empty($result['seeded_tables'])) {
            $messages[] = rbf_translate_string('Sale e tavoli di esempio creati: personalizzali dalla schermata “Gestione Tavoli”.');
        } elseif (!empty($result['tables_available'])) {
            $messages[] = rbf_translate_string('Sono stati trovati tavoli già configurati: puoi procedere direttamente con le prenotazioni.');
        } else {
            $messages[] = rbf_translate_string('Nessun tavolo configurato: aggiungili dal pannello “Gestione Tavoli” per attivare l’assegnazione posti.');
        }

        echo '<div class="rbf-setup-card">';
        echo '<h2>' . esc_html(rbf_translate_string('Setup completato!')) . '</h2>';
        echo '<p>' . esc_html(rbf_translate_string('Il modulo è pronto: trovi tutte le impostazioni avanzate nella pagina “Impostazioni” e puoi già provare il form in frontend.')) . '</p>';

        if (!empty($messages)) {
            echo '<ul class="rbf-setup-summary-results">';
            foreach ($messages as $message) {
                echo '<li>' . esc_html($message) . '</li>';
            }
            echo '</ul>';
        }

        echo '<div class="rbf-setup-complete-actions">';
        echo '<a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=rbf_calendar')) . '">' . esc_html(rbf_translate_string('Vai al calendario')) . '</a>';
        echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=rbf_settings')) . '">' . esc_html(rbf_translate_string('Apri impostazioni complete')) . '</a>';
        echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=rbf_tables')) . '">' . esc_html(rbf_translate_string('Gestisci tavoli')) . '</a>';
        if (!empty($result['booking_page_url'])) {
            echo '<a class="button" target="_blank" rel="noopener noreferrer" href="' . esc_url($result['booking_page_url']) . '">' . esc_html(rbf_translate_string('Visualizza pagina prenotazioni')) . '</a>';
        }
        echo '</div>';

        echo '</div>';
        echo '</div>';
        return;
    }

    echo '<form method="post" class="rbf-setup-form">';
    wp_nonce_field('rbf_setup_wizard_step');

    if ($step === 'services') {
        echo '<input type="hidden" name="rbf_setup_step" value="services" />';
        echo '<div class="rbf-setup-card">';
        echo '<h2>' . esc_html(rbf_translate_string('Configura i servizi base')) . '</h2>';
        echo '<p>' . esc_html(rbf_translate_string('Definisci pasti, orari e capienza. Puoi aggiungere altri servizi in seguito.')) . '</p>';

        foreach ($services as $service_id => $service) {
            echo '<fieldset class="rbf-service-block">';
            echo '<legend>' . esc_html($service['name'] ?? ucfirst($service_id)) . '</legend>';
            echo '<label><input type="checkbox" name="services[' . esc_attr($service_id) . '][enabled]" value="1" ' . checked(!empty($service['enabled']), true, false) . '> ' . esc_html(rbf_translate_string('Attiva servizio')) . '</label>';

            echo '<div class="rbf-service-grid">';
            printf('<label>%s <input type="text" name="services[%s][name]" value="%s" class="regular-text"></label>',
                esc_html(rbf_translate_string('Nome servizio')),
                esc_attr($service_id),
                esc_attr($service['name']));

            printf('<label>%s <input type="time" name="services[%s][start]" value="%s"></label>',
                esc_html(rbf_translate_string('Inizio')),
                esc_attr($service_id),
                esc_attr($service['start']));

            printf('<label>%s <input type="time" name="services[%s][end]" value="%s"></label>',
                esc_html(rbf_translate_string('Fine')),
                esc_attr($service_id),
                esc_attr($service['end']));

            printf('<label>%s <input type="number" min="10" step="5" name="services[%s][interval]" value="%d"></label>',
                esc_html(rbf_translate_string('Intervallo (min)')),
                esc_attr($service_id),
                (int) $service['interval']);

            printf('<label>%s <input type="number" min="1" step="1" name="services[%s][capacity]" value="%d"></label>',
                esc_html(rbf_translate_string('Capienza base')),
                esc_attr($service_id),
                (int) $service['capacity']);

            printf('<label>%s <input type="number" min="0" step="1" name="services[%s][overbooking]" value="%d"></label>',
                esc_html(rbf_translate_string('Overbooking %')),
                esc_attr($service_id),
                (int) $service['overbooking']);

            printf('<label>%s <input type="number" min="0" step="1" name="services[%s][buffer]" value="%d"></label>',
                esc_html(rbf_translate_string('Buffer base (min)')),
                esc_attr($service_id),
                (int) $service['buffer']);

            printf('<label>%s <input type="number" min="0" step="1" name="services[%s][buffer_per_person]" value="%d"></label>',
                esc_html(rbf_translate_string('Buffer per persona (min)')),
                esc_attr($service_id),
                (int) $service['buffer_per_person']);

            printf('<label>%s <input type="text" name="services[%s][tooltip]" value="%s" class="regular-text"></label>',
                esc_html(rbf_translate_string('Tooltip/Note opzionali')),
                esc_attr($service_id),
                esc_attr($service['tooltip'] ?? ''));

            echo '<div class="rbf-day-picker"><span>' . esc_html(rbf_translate_string('Giorni attivi')) . '</span>';
            foreach ($default_days as $day_key) {
                $checked = in_array($day_key, (array) $service['days'], true);
                echo '<label><input type="checkbox" name="services[' . esc_attr($service_id) . '][days][]" value="' . esc_attr($day_key) . '" ' . checked($checked, true, false) . '> ' . esc_html($day_labels[$day_key]) . '</label>';
            }
            echo '</div>';
            echo '</div>';
            echo '</fieldset>';
        }

        submit_button(rbf_translate_string('Continua con le integrazioni'));
        echo '</div>';
    } elseif ($step === 'integrations') {
        echo '<input type="hidden" name="rbf_setup_step" value="integrations" />';
        echo '<div class="rbf-setup-card">';
        echo '<h2>' . esc_html(rbf_translate_string('Notifiche & Tracking')) . '</h2>';
        echo '<p>' . esc_html(rbf_translate_string('Imposta l’email di destinazione e abilita GA4/Meta inserendo solo gli ID fondamentali.')) . '</p>';

        printf('<label>%s <input type="email" name="notification_email" value="%s" class="regular-text" placeholder="prenotazioni@example.com"></label>',
            esc_html(rbf_translate_string('Email notifiche prenotazioni')),
            esc_attr($integrations['notification_email'] ?? get_option('admin_email'))
        );

        echo '<hr />';
        echo '<h3>' . esc_html(rbf_translate_string('Google Analytics 4')) . '</h3>';
        printf('<label>%s <input type="text" name="ga4_id" value="%s" class="regular-text" placeholder="G-XXXXXXXXXX"></label>',
            esc_html(rbf_translate_string('Measurement ID')),
            esc_attr($integrations['ga4_id'] ?? '')
        );
        printf('<label>%s <input type="text" name="ga4_api_secret" value="%s" class="regular-text"></label>',
            esc_html(rbf_translate_string('API Secret (per eventi server-side)')),
            esc_attr($integrations['ga4_api_secret'] ?? '')
        );

        echo '<h3>' . esc_html(rbf_translate_string('Meta Pixel / Conversion API')) . '</h3>';
        printf('<label>%s <input type="text" name="meta_pixel_id" value="%s" class="regular-text" placeholder="1234567890"></label>',
            esc_html(rbf_translate_string('Pixel ID')),
            esc_attr($integrations['meta_pixel_id'] ?? '')
        );
        printf('<label>%s <input type="password" name="meta_access_token" value="%s" class="regular-text"></label>',
            esc_html(rbf_translate_string('Access Token CAPI')),
            esc_attr($integrations['meta_access_token'] ?? '')
        );

        submit_button(rbf_translate_string('Mostra riepilogo'));
        echo '</div>';
    } elseif ($step === 'summary') {
        echo '<input type="hidden" name="rbf_setup_step" value="summary" />';
        echo '<div class="rbf-setup-card">';
        echo '<h2>' . esc_html(rbf_translate_string('Riepilogo configurazione')) . '</h2>';
        echo '<p>' . esc_html(rbf_translate_string('Verifica i dati prima di confermare. Potrai comunque modificarli dalle impostazioni complete.')) . '</p>';

        echo '<h3>' . esc_html(rbf_translate_string('Servizi creati')) . '</h3>';
        echo '<ul class="rbf-summary-list">';
        foreach ($services as $service_id => $service) {
            if (empty($service['enabled'])) {
                continue;
            }
            echo '<li><strong>' . esc_html($service['name']) . '</strong>: ';
            echo esc_html($service['start'] . ' → ' . $service['end']);
            echo ' · ' . esc_html(sprintf(rbf_translate_string('%d posti (+%d%% overbooking)'), (int) $service['capacity'], (int) $service['overbooking']));
            echo '</li>';
        }
        echo '</ul>';

        echo '<h3>' . esc_html(rbf_translate_string('Notifiche & Tracking')) . '</h3>';
        echo '<ul class="rbf-summary-list">';
        if (!empty($integrations['notification_email'])) {
            echo '<li>📧 ' . esc_html($integrations['notification_email']) . '</li>';
        }
        if (!empty($integrations['ga4_id'])) {
            echo '<li>📊 GA4: ' . esc_html($integrations['ga4_id']) . '</li>';
        }
        if (!empty($integrations['meta_pixel_id'])) {
            echo '<li>📘 Meta Pixel: ' . esc_html($integrations['meta_pixel_id']) . '</li>';
        }
        echo '</ul>';

        echo '<h3>' . esc_html(rbf_translate_string('Attivazione rapida')) . '</h3>';
        echo '<div class="rbf-summary-options">';
        echo '<label class="rbf-summary-toggle">';
        echo '<input type="checkbox" name="create_booking_page" value="1" ' . checked($default_create_page, true, false) . '>';
        echo '<div>';
        echo '<strong>' . esc_html(rbf_translate_string('Crea pagina “Prenotazioni” pronta all’uso')) . '</strong>';
        echo '<span class="description">' . esc_html(rbf_translate_string('Pubblica una pagina con il modulo già inserito e collegato al riepilogo.')) . '</span>';
        if ($existing_booking_page_id > 0) {
            $page_info = $existing_booking_page_title !== ''
                ? sprintf(rbf_translate_string('Pagina attuale: %s'), $existing_booking_page_title)
                : rbf_translate_string('È già presente una pagina con il modulo.');
            echo '<span class="description">' . esc_html($page_info);
            if ($existing_booking_page_url) {
                echo ' · ' . esc_html($existing_booking_page_url);
            }
            echo '</span>';
        } else {
            echo '<span class="description">' . esc_html(rbf_translate_string('Perfetto per iniziare subito i test senza creare manualmente una pagina.')) . '</span>';
        }
        echo '</div>';
        echo '</label>';

        echo '<label class="rbf-summary-toggle">';
        echo '<input type="checkbox" name="seed_default_tables" value="1" ' . checked($default_seed_tables, true, false) . '>';
        echo '<div>';
        echo '<strong>' . esc_html(rbf_translate_string('Popola sale e tavoli di esempio')) . '</strong>';
        echo '<span class="description">' . esc_html(rbf_translate_string('Crea “Sala Principale” e “Dehors” con tavoli da 2 a 8 posti per verificare la gestione tavoli.')) . '</span>';
        if ($has_table_setup) {
            echo '<span class="description">' . esc_html(rbf_translate_string('Sono già presenti tavoli configurati: disattiva l’opzione se non vuoi modificarli.')) . '</span>';
        }
        echo '</div>';
        echo '</label>';
        echo '</div>';

        submit_button(rbf_translate_string('Conferma e attiva'), 'primary', 'submit', true);
        echo '</div>';
    }

    echo '</form>';
    echo '</div>';
}

