<?php
/**
 * Admin functionality for Restaurant Booking Plugin
 * 
 * @package FP_Prenotazioni_Ristorante_PRO
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if debug mode is enabled (database settings take priority over constants)
 * 
 * @return bool
 */
function rbf_is_debug_enabled() {
    $settings = get_option('rbf_settings', []);
    
    // Check database setting first
    if (isset($settings['debug_enabled'])) {
        return $settings['debug_enabled'] === 'yes';
    }
    
    // Fallback to constant if database setting not available
    return defined('RBF_DEBUG') ? RBF_DEBUG : false;
}

/**
 * Get debug log level (database settings take priority over constants)
 * 
 * @return string
 */
function rbf_get_debug_log_level() {
    $settings = get_option('rbf_settings', []);
    
    // Check database setting first
    if (isset($settings['debug_log_level'])) {
        return $settings['debug_log_level'];
    }
    
    // Fallback to constant if database setting not available
    return defined('RBF_LOG_LEVEL') ? RBF_LOG_LEVEL : 'INFO';
}

/**
 * Get default plugin settings
 */
function rbf_get_default_settings() {
    return [
        'capienza_pranzo' => 30,
        'capienza_cena' => 40,
        'capienza_aperitivo' => 25,
        'orari_pranzo' => '12:00,12:30,13:00,13:30,14:00',
        'orari_cena' => '19:00,19:30,20:00,20:30',
        'orari_aperitivo' => '17:00,17:30,18:00',
        'valore_pranzo' => 35.00,
        'valore_cena' => 50.00,
        'valore_aperitivo' => 15.00,
        'open_mon' => 'yes','open_tue' => 'yes','open_wed' => 'yes','open_thu' => 'yes','open_fri' => 'yes','open_sat' => 'yes','open_sun' => 'yes',
        'ga4_id' => '',
        'ga4_api_secret' => '',
        'meta_pixel_id' => '',
        'meta_access_token' => '',
        'notification_email' => 'info@villadianella.it',
        'webmaster_email' => get_option('admin_email', ''),
        'brevo_api' => '',
        'brevo_list_it' => '',
        'brevo_list_en' => '',
        'closed_dates' => '',
        'max_advance_hours' => 72, // Maximum hours in advance bookings are allowed
        // Debug & Performance Settings
        'debug_enabled' => 'no',
        'debug_log_level' => 'INFO',
        'debug_auto_cleanup' => 'yes',
        'debug_cleanup_days' => 7,
    ];
}

/**
 * Register booking custom post type
 */
add_action('init', 'rbf_register_post_type');
function rbf_register_post_type() {
    register_post_type('rbf_booking', [
        'labels' => [
            'name' => rbf_translate_string('Prenotazioni'),
            'singular_name' => rbf_translate_string('Prenotazione'),
            'add_new' => rbf_translate_string('Aggiungi Nuova'),
            'add_new_item' => rbf_translate_string('Aggiungi Nuova Prenotazione'),
            'edit_item' => rbf_translate_string('Modifica Prenotazione'),
            'new_item' => rbf_translate_string('Nuova Prenotazione'),
            'view_item' => rbf_translate_string('Visualizza Prenotazione'),
            'search_items' => rbf_translate_string('Cerca Prenotazioni'),
            'not_found' => rbf_translate_string('Nessuna Prenotazione trovata'),
            'not_found_in_trash' => rbf_translate_string('Nessuna Prenotazione trovata nel cestino'),
        ],
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => false,
        'menu_icon' => 'dashicons-calendar-alt',
        'supports' => ['title', 'custom-fields'],
        'menu_position' => 20,
    ]);
}

/**
 * Create admin menu
 */
add_action('admin_menu', 'rbf_create_bookings_menu');
function rbf_create_bookings_menu() {
    add_menu_page(rbf_translate_string('Prenotazioni'), rbf_translate_string('Prenotazioni'), 'manage_options', 'rbf_calendar', 'rbf_calendar_page_html', 'dashicons-calendar-alt', 20);
    add_submenu_page('rbf_calendar', rbf_translate_string('Prenotazioni'), rbf_translate_string('Tutte le Prenotazioni'), 'manage_options', 'rbf_calendar', 'rbf_calendar_page_html');
    add_submenu_page('rbf_calendar', rbf_translate_string('Aggiungi Prenotazione'), rbf_translate_string('Aggiungi Nuova'), 'manage_options', 'rbf_add_booking', 'rbf_add_booking_page_html');
    add_submenu_page('rbf_calendar', rbf_translate_string('Report & Analytics'), rbf_translate_string('Report & Analytics'), 'manage_options', 'rbf_reports', 'rbf_reports_page_html');
    add_submenu_page('rbf_calendar', rbf_translate_string('Esporta Dati'), rbf_translate_string('Esporta Dati'), 'manage_options', 'rbf_export', 'rbf_export_page_html');
    add_submenu_page('rbf_calendar', rbf_translate_string('Impostazioni'), rbf_translate_string('Impostazioni'), 'manage_options', 'rbf_settings', 'rbf_settings_page_html');
}

/**
 * Customize booking list columns
 */
add_filter('manage_rbf_booking_posts_columns', 'rbf_set_custom_columns');
function rbf_set_custom_columns($columns) {
    unset($columns['date']); // Remove default date column
    
    $new_columns = [];
    $new_columns['cb'] = $columns['cb'];
    $new_columns['title'] = $columns['title'];
    $new_columns['rbf_status'] = rbf_translate_string('Stato Prenotazione');
    $new_columns['rbf_customer'] = rbf_translate_string('Cliente');
    $new_columns['rbf_booking_date'] = rbf_translate_string('Data');
    $new_columns['rbf_time'] = rbf_translate_string('Orario');
    $new_columns['rbf_meal'] = rbf_translate_string('Pasto');
    $new_columns['rbf_people'] = rbf_translate_string('Persone');
    $new_columns['rbf_value'] = rbf_translate_string('Valore');
    $new_columns['rbf_actions'] = rbf_translate_string('Azioni');
    
    return $new_columns;
}

/**
 * Fill custom columns with data
 */
add_action('manage_rbf_booking_posts_custom_column', 'rbf_custom_column_data', 10, 2);
function rbf_custom_column_data($column, $post_id) {
    switch ($column) {
        case 'rbf_status':
            $status = get_post_meta($post_id, 'rbf_booking_status', true) ?: 'confirmed';
            $statuses = rbf_get_booking_statuses();
            $color = rbf_get_status_color($status);
            echo '<span style="display: inline-block; padding: 4px 8px; border-radius: 4px; background: ' . esc_attr($color) . '; color: white; font-size: 12px; font-weight: bold;">';
            echo esc_html($statuses[$status] ?? $status);
            echo '</span>';
            break;
            
        case 'rbf_customer':
            $first_name = get_post_meta($post_id, 'rbf_nome', true);
            $last_name = get_post_meta($post_id, 'rbf_cognome', true);
            $email = get_post_meta($post_id, 'rbf_email', true);
            $tel = get_post_meta($post_id, 'rbf_tel', true);
            echo '<strong>' . esc_html($first_name . ' ' . $last_name) . '</strong><br>';
            echo '<small><a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a></small><br>';
            echo '<small><a href="tel:' . esc_attr($tel) . '">' . esc_html($tel) . '</a></small>';
            break;
            
        case 'rbf_booking_date':
            $date = get_post_meta($post_id, 'rbf_data', true);
            if ($date) {
                $datetime = DateTime::createFromFormat('Y-m-d', $date);
                if ($datetime) {
                    echo esc_html($datetime->format('d/m/Y'));
                    echo '<br><small>' . esc_html($datetime->format('l')) . '</small>';
                }
            }
            break;
            
        case 'rbf_time':
            echo esc_html(get_post_meta($post_id, 'rbf_time', true));
            break;
            
        case 'rbf_meal':
            $meal = get_post_meta($post_id, 'rbf_meal', true);
            $meals = [
                'pranzo' => rbf_translate_string('Pranzo'),
                'cena' => rbf_translate_string('Cena'), 
                'aperitivo' => rbf_translate_string('Aperitivo')
            ];
            echo esc_html($meals[$meal] ?? $meal);
            break;
            
        case 'rbf_people':
            echo '<strong>' . esc_html(get_post_meta($post_id, 'rbf_persone', true)) . '</strong>';
            break;
            
        case 'rbf_value':
            $people = intval(get_post_meta($post_id, 'rbf_persone', true));
            $meal = get_post_meta($post_id, 'rbf_meal', true);
            $options = get_option('rbf_settings', rbf_get_default_settings());
            $valore_pp = (float) ($options['valore_' . $meal] ?? 0);
            $valore_tot = $valore_pp * $people;
            if ($valore_tot > 0) {
                echo '<strong>€' . number_format($valore_tot, 2) . '</strong>';
                echo '<br><small>€' . number_format($valore_pp, 2) . ' x ' . $people . '</small>';
            }
            break;
            
        case 'rbf_actions':
            echo '<div class="row-actions" style="position: static;">';
            rbf_render_booking_actions($post_id);
            echo '</div>';
            break;
    }
}

/**
 * Render booking action buttons (simplified - no manual status changes)
 */
function rbf_render_booking_actions($post_id) {
    $status = get_post_meta($post_id, 'rbf_booking_status', true) ?: 'confirmed';
    
    // Only show delete action for cancelled bookings
    if ($status === 'cancelled') {
        $delete_url = admin_url('post.php?post=' . $post_id . '&action=delete');
        $delete_nonce = wp_create_nonce('delete-post_' . $post_id);
        echo '<a href="' . esc_url($delete_url . '&_wpnonce=' . $delete_nonce) . '" ';
        echo 'style="color: #ef4444; font-weight: bold; text-decoration: none;" ';
        echo 'onclick="return confirm(\'' . esc_js('Elimina definitivamente questa prenotazione?') . '\')">';
        echo esc_html(rbf_translate_string('Elimina'));
        echo '</a>';
    } else {
        echo '<span style="color: #6b7280; font-style: italic;">' . esc_html(rbf_translate_string('Gestione Automatica')) . '</span>';
    }
}

/**
 * Register settings
 */
add_action('admin_init', 'rbf_register_settings');
function rbf_register_settings() {
    register_setting('rbf_opts_group', 'rbf_settings', [
        'sanitize_callback' => 'rbf_sanitize_settings_callback',
        'default' => rbf_get_default_settings(),
    ]);
}

/**
 * Sanitize settings callback
 */
function rbf_sanitize_settings_callback($input) {
    $defaults = rbf_get_default_settings();
    $output = [];
    $input = (array) $input;

    $int_keys = ['capienza_pranzo','capienza_cena','capienza_aperitivo','brevo_list_it','brevo_list_en','debug_cleanup_days'];
    foreach ($int_keys as $key) $output[$key] = isset($input[$key]) ? absint($input[$key]) : ($defaults[$key] ?? '');

    $text_keys = ['orari_pranzo','orari_cena','orari_aperitivo','brevo_api','ga4_api_secret','meta_access_token'];
    foreach ($text_keys as $key) $output[$key] = isset($input[$key]) ? sanitize_text_field(trim($input[$key])) : ($defaults[$key] ?? '');

    if (isset($input['ga4_id']) && !empty($input['ga4_id']) && !preg_match('/^G-[A-Z0-9]+$/', $input['ga4_id'])) {
        $output['ga4_id'] = '';
        add_settings_error('rbf_settings', 'invalid_ga4_id', rbf_translate_string('ID GA4 non valido. Deve essere nel formato G-XXXXXXXXXX.'));
    } else {
        $output['ga4_id'] = isset($input['ga4_id']) ? sanitize_text_field(trim($input['ga4_id'])) : ($defaults['ga4_id'] ?? '');
    }

    if (isset($input['meta_pixel_id']) && !empty($input['meta_pixel_id']) && !ctype_digit($input['meta_pixel_id'])) {
        $output['meta_pixel_id'] = '';
        add_settings_error('rbf_settings', 'invalid_meta_pixel_id', rbf_translate_string('ID Meta Pixel non valido. Deve essere un numero.'));
    } else {
        $output['meta_pixel_id'] = isset($input['meta_pixel_id']) ? sanitize_text_field(trim($input['meta_pixel_id'])) : ($defaults['meta_pixel_id'] ?? '');
    }

    if (isset($input['notification_email'])) $output['notification_email'] = sanitize_email($input['notification_email']);
    if (isset($input['webmaster_email'])) $output['webmaster_email'] = sanitize_email($input['webmaster_email']);

    $float_keys = ['valore_pranzo','valore_cena','valore_aperitivo'];
    foreach ($float_keys as $key) $output[$key] = isset($input[$key]) ? floatval($input[$key]) : ($defaults[$key] ?? 0);

    $days = ['mon','tue','wed','thu','fri','sat','sun'];
    foreach ($days as $day) $output["open_{$day}"] = (isset($input["open_{$day}"]) && $input["open_{$day}"]==='yes') ? 'yes' : 'no';

    if (isset($input['closed_dates'])) $output['closed_dates'] = sanitize_textarea_field($input['closed_dates']);
    
    // Debug settings sanitization
    $output['debug_enabled'] = (isset($input['debug_enabled']) && $input['debug_enabled'] === 'yes') ? 'yes' : 'no';
    $output['debug_auto_cleanup'] = (isset($input['debug_auto_cleanup']) && $input['debug_auto_cleanup'] === 'yes') ? 'yes' : 'no';
    
    // Validate debug log level
    $valid_levels = ['DEBUG', 'INFO', 'WARNING', 'ERROR'];
    if (isset($input['debug_log_level']) && in_array($input['debug_log_level'], $valid_levels)) {
        $output['debug_log_level'] = $input['debug_log_level'];
    } else {
        $output['debug_log_level'] = $defaults['debug_log_level'] ?? 'INFO';
    }
    
    // Validate debug cleanup days (minimum 1 day, maximum 30 days)
    if (isset($input['debug_cleanup_days'])) {
        $cleanup_days = absint($input['debug_cleanup_days']);
        $output['debug_cleanup_days'] = max(1, min(30, $cleanup_days));
    } else {
        $output['debug_cleanup_days'] = $defaults['debug_cleanup_days'] ?? 7;
    }
    
    // Validate max advance hours (minimum 1 hour, maximum 8760 hours = 1 year)
    if (isset($input['max_advance_hours'])) {
        $max_hours = absint($input['max_advance_hours']);
        $output['max_advance_hours'] = max(1, min(8760, $max_hours));
    } else {
        $output['max_advance_hours'] = $defaults['max_advance_hours'] ?? 72;
    }

    return $output;
}

/**
 * Enqueue admin styles
 */
add_action('admin_enqueue_scripts','rbf_enqueue_admin_styles');
function rbf_enqueue_admin_styles($hook) {
    if ($hook !== 'prenotazioni_page_rbf_settings' &&
        $hook !== 'toplevel_page_rbf_calendar' &&
        $hook !== 'prenotazioni_page_rbf_add_booking' &&
        $hook !== 'prenotazioni_page_rbf_reports' &&
        $hook !== 'prenotazioni_page_rbf_export' &&
        strpos($hook,'edit.php?post_type=rbf_booking') === false) return;

    wp_enqueue_style('rbf-admin-css', plugin_dir_url(dirname(__FILE__)) . 'assets/css/admin.css', [], '10.0.0');
}

/**
 * Settings page HTML
 */
function rbf_settings_page_html() {
    $options = wp_parse_args(get_option('rbf_settings', rbf_get_default_settings()), rbf_get_default_settings());
    ?>
    <div class="rbf-admin-wrap">
        <h1><?php echo esc_html(rbf_translate_string('Impostazioni Prenotazioni Ristorante')); ?></h1>
        <form method="post" action="options.php">
            <?php settings_fields('rbf_opts_group'); ?>
            <table class="form-table" role="presentation">
                <tr><th colspan="2"><h2><?php echo esc_html(rbf_translate_string('Capienza e Orari')); ?></h2></th></tr>
                <tr><th><label for="rbf_capienza_pranzo"><?php echo esc_html(rbf_translate_string('Capienza Pranzo')); ?></label></th>
                    <td><input type="number" id="rbf_capienza_pranzo" name="rbf_settings[capienza_pranzo]" value="<?php echo esc_attr($options['capienza_pranzo']); ?>"></td></tr>
                <tr><th><label for="rbf_orari_pranzo"><?php echo esc_html(rbf_translate_string('Orari Pranzo (inclusa Domenica)')); ?></label></th>
                    <td><input type="text" id="rbf_orari_pranzo" name="rbf_settings[orari_pranzo]" value="<?php echo esc_attr($options['orari_pranzo']); ?>" class="regular-text" placeholder="Es: 12:00,12:30,13:00"></td></tr>
                <tr><th><label for="rbf_capienza_cena"><?php echo esc_html(rbf_translate_string('Capienza Cena')); ?></label></th>
                    <td><input type="number" id="rbf_capienza_cena" name="rbf_settings[capienza_cena]" value="<?php echo esc_attr($options['capienza_cena']); ?>"></td></tr>
                <tr><th><label for="rbf_orari_cena"><?php echo esc_html(rbf_translate_string('Orari Cena')); ?></label></th>
                    <td><input type="text" id="rbf_orari_cena" name="rbf_settings[orari_cena]" value="<?php echo esc_attr($options['orari_cena']); ?>" class="regular-text" placeholder="Es: 19:00,19:30,20:00"></td></tr>
                <tr><th><label for="rbf_capienza_aperitivo"><?php echo esc_html(rbf_translate_string('Capienza Aperitivo')); ?></label></th>
                    <td><input type="number" id="rbf_capienza_aperitivo" name="rbf_settings[capienza_aperitivo]" value="<?php echo esc_attr($options['capienza_aperitivo']); ?>"></td></tr>
                <tr><th><label for="rbf_orari_aperitivo"><?php echo esc_html(rbf_translate_string('Orari Aperitivo')); ?></label></th>
                    <td><input type="text" id="rbf_orari_aperitivo" name="rbf_settings[orari_aperitivo]" value="<?php echo esc_attr($options['orari_aperitivo']); ?>" class="regular-text" placeholder="Es: 17:00,17:30,18:00"></td></tr>

                <tr>
                    <th><?php echo esc_html(rbf_translate_string('Giorni aperti')); ?></th>
                    <td>
                        <?php
                        $days = ['mon'=>'Lunedì','tue'=>'Martedì','wed'=>'Mercoledì','thu'=>'Giovedì','fri'=>'Venerdì','sat'=>'Sabato','sun'=>'Domenica'];
                        foreach ($days as $key=>$label) {
                            $checked = ($options["open_{$key}"] ?? 'yes') === 'yes' ? 'checked' : '';
                            echo "<label><input type='checkbox' name='rbf_settings[open_{$key}]' value='yes' {$checked}> " . esc_html(rbf_translate_string($label)) . "</label><br>";
                        }
                        ?>
                    </td>
                </tr>

                <tr><th colspan="2"><h2><?php echo esc_html(rbf_translate_string('Chiusure Straordinarie')); ?></h2></th></tr>
                <tr>
                    <th><label for="rbf_closed_dates"><?php echo esc_html(rbf_translate_string('Date Chiuse (una per riga, formato Y-m-d o Y-m-d - Y-m-d)')); ?></label></th>
                    <td><textarea id="rbf_closed_dates" name="rbf_settings[closed_dates]" rows="5" class="large-text"><?php echo esc_textarea($options['closed_dates']); ?></textarea></td>
                </tr>

                <tr><th colspan="2"><h2><?php echo esc_html(rbf_translate_string('Limiti Temporali Prenotazioni')); ?></h2></th></tr>
                <tr>
                    <th><label for="rbf_max_advance_hours"><?php echo esc_html(rbf_translate_string('Ore massime in anticipo per prenotare')); ?></label></th>
                    <td>
                        <input type="number" id="rbf_max_advance_hours" name="rbf_settings[max_advance_hours]" value="<?php echo esc_attr($options['max_advance_hours']); ?>" min="1" max="8760">
                        <p class="description"><?php echo esc_html(rbf_translate_string('Numero massimo di ore in anticipo consentite per le prenotazioni (es. 72 = 3 giorni). Valore minimo 1, massimo 8760 (1 anno).')); ?></p>
                    </td>
                </tr>

                <tr><th colspan="2"><h2><?php echo esc_html(rbf_translate_string('Valore Economico Pasti (per Tracking)')); ?></h2></th></tr>
                <tr><th><label for="rbf_valore_pranzo"><?php echo esc_html(rbf_translate_string('Valore medio Pranzo (€)')); ?></label></th>
                    <td><input type="number" step="0.01" id="rbf_valore_pranzo" name="rbf_settings[valore_pranzo]" value="<?php echo esc_attr($options['valore_pranzo']); ?>"></td></tr>
                <tr><th><label for="rbf_valore_cena"><?php echo esc_html(rbf_translate_string('Valore medio Cena (€)')); ?></label></th>
                    <td><input type="number" step="0.01" id="rbf_valore_cena" name="rbf_settings[valore_cena]" value="<?php echo esc_attr($options['valore_cena']); ?>"></td></tr>
                <tr><th><label for="rbf_valore_aperitivo"><?php echo esc_html(rbf_translate_string('Valore medio Aperitivo (€)')); ?></label></th>
                    <td><input type="number" step="0.01" id="rbf_valore_aperitivo" name="rbf_settings[valore_aperitivo]" value="<?php echo esc_attr($options['valore_aperitivo']); ?>"></td></tr>

                <tr><th colspan="2"><h2><?php echo esc_html(rbf_translate_string('Integrazioni e Marketing')); ?></h2></th></tr>
                <tr><th><label for="rbf_notification_email"><?php echo esc_html(rbf_translate_string('Email per Notifiche Ristorante')); ?></label></th>
                    <td><input type="email" id="rbf_notification_email" name="rbf_settings[notification_email]" value="<?php echo esc_attr($options['notification_email']); ?>" class="regular-text" placeholder="es. ristorante@esempio.com"></td></tr>
                <tr><th><label for="rbf_webmaster_email"><?php echo esc_html(rbf_translate_string('Email per Notifiche Webmaster')); ?></label></th>
                    <td><input type="email" id="rbf_webmaster_email" name="rbf_settings[webmaster_email]" value="<?php echo esc_attr($options['webmaster_email']); ?>" class="regular-text" placeholder="es. webmaster@esempio.com"></td></tr>
                <tr><th><label for="rbf_ga4_id"><?php echo esc_html(rbf_translate_string('ID misurazione GA4')); ?></label></th>
                    <td><input type="text" id="rbf_ga4_id" name="rbf_settings[ga4_id]" value="<?php echo esc_attr($options['ga4_id']); ?>" class="regular-text" placeholder="G-XXXXXXXXXX"></td></tr>
                <tr><th><label for="rbf_ga4_api_secret">GA4 API Secret (per invii server-side)</label></th>
                    <td><input type="text" id="rbf_ga4_api_secret" name="rbf_settings[ga4_api_secret]" value="<?php echo esc_attr($options['ga4_api_secret']); ?>" class="regular-text"></td></tr>
                <tr><th><label for="rbf_meta_pixel_id"><?php echo esc_html(rbf_translate_string('ID Meta Pixel')); ?></label></th>
                    <td><input type="text" id="rbf_meta_pixel_id" name="rbf_settings[meta_pixel_id]" value="<?php echo esc_attr($options['meta_pixel_id']); ?>" class="regular-text"></td></tr>
                <tr><th><label for="rbf_meta_access_token">Meta Access Token (per invii server-side)</label></th>
                    <td><input type="password" id="rbf_meta_access_token" name="rbf_settings[meta_access_token]" value="<?php echo esc_attr($options['meta_access_token']); ?>" class="regular-text"></td></tr>

                <tr><th colspan="2"><h3><?php echo esc_html(rbf_translate_string('Impostazioni Brevo')); ?></h3></th></tr>
                <tr><th><label for="rbf_brevo_api"><?php echo esc_html(rbf_translate_string('API Key Brevo')); ?></label></th>
                    <td><input type="password" id="rbf_brevo_api" name="rbf_settings[brevo_api]" value="<?php echo esc_attr($options['brevo_api']); ?>" class="regular-text"></td></tr>
                <tr><th><label for="rbf_brevo_list_it"><?php echo esc_html(rbf_translate_string('ID Lista Brevo (IT)')); ?></label></th>
                    <td><input type="number" id="rbf_brevo_list_it" name="rbf_settings[brevo_list_it]" value="<?php echo esc_attr($options['brevo_list_it']); ?>"></td></tr>
                <tr><th><label for="rbf_brevo_list_en"><?php echo esc_html(rbf_translate_string('ID Lista Brevo (EN)')); ?></label></th>
                    <td><input type="number" id="rbf_brevo_list_en" name="rbf_settings[brevo_list_en]" value="<?php echo esc_attr($options['brevo_list_en']); ?>"></td></tr>

                <tr><th colspan="2"><h2 style="color: #1d4ed8;">🔧 <?php echo esc_html(rbf_translate_string('Debug e Monitoraggio Performance')); ?></h2></th></tr>
                <tr>
                    <th><label for="rbf_debug_enabled"><?php echo esc_html(rbf_translate_string('Abilita Sistema Debug')); ?></label></th>
                    <td>
                        <label>
                            <input type="checkbox" id="rbf_debug_enabled" name="rbf_settings[debug_enabled]" value="yes" <?php checked($options['debug_enabled'], 'yes'); ?>>
                            <?php echo esc_html(rbf_translate_string('Attiva debug avanzato, logging e monitoraggio performance')); ?>
                        </label>
                        <p class="description">
                            <?php echo esc_html(rbf_translate_string('Quando attivo, il sistema registra eventi dettagliati, monitora le performance delle API e fornisce analytics avanzate. Impatto minimo sulle prestazioni quando disattivato.')); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th><label for="rbf_debug_log_level"><?php echo esc_html(rbf_translate_string('Livello di Logging')); ?></label></th>
                    <td>
                        <select id="rbf_debug_log_level" name="rbf_settings[debug_log_level]">
                            <option value="DEBUG" <?php selected($options['debug_log_level'], 'DEBUG'); ?>>DEBUG - Tutto (molto dettagliato)</option>
                            <option value="INFO" <?php selected($options['debug_log_level'], 'INFO'); ?>>INFO - Eventi importanti</option>
                            <option value="WARNING" <?php selected($options['debug_log_level'], 'WARNING'); ?>>WARNING - Solo avvisi</option>
                            <option value="ERROR" <?php selected($options['debug_log_level'], 'ERROR'); ?>>ERROR - Solo errori</option>
                        </select>
                        <p class="description">
                            <?php echo esc_html(rbf_translate_string('DEBUG registra tutto (utile per sviluppo), INFO registra eventi chiave (raccomandato), WARNING e ERROR registrano solo problemi.')); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th><label for="rbf_debug_auto_cleanup"><?php echo esc_html(rbf_translate_string('Pulizia Automatica Log')); ?></label></th>
                    <td>
                        <label>
                            <input type="checkbox" id="rbf_debug_auto_cleanup" name="rbf_settings[debug_auto_cleanup]" value="yes" <?php checked($options['debug_auto_cleanup'], 'yes'); ?>>
                            <?php echo esc_html(rbf_translate_string('Elimina automaticamente i log più vecchi (raccomandato per GDPR)')); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><label for="rbf_debug_cleanup_days"><?php echo esc_html(rbf_translate_string('Giorni di Conservazione Log')); ?></label></th>
                    <td>
                        <input type="number" id="rbf_debug_cleanup_days" name="rbf_settings[debug_cleanup_days]" value="<?php echo esc_attr($options['debug_cleanup_days']); ?>" min="1" max="30">
                        <p class="description">
                            <?php echo esc_html(rbf_translate_string('Numero di giorni per cui mantenere i log (minimo 1, massimo 30). I log più vecchi vengono eliminati automaticamente se la pulizia automatica è abilitata.')); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th></th>
                    <td>
                        <div style="background: #f0f8ff; border: 1px solid #1d4ed8; border-radius: 4px; padding: 15px; margin: 10px 0;">
                            <h4 style="margin: 0 0 10px 0; color: #1d4ed8;">ℹ️ <?php echo esc_html(rbf_translate_string('Funzionalità Debug Avanzato:')); ?></h4>
                            <ul style="margin: 0; list-style-type: disc; padding-left: 20px;">
                                <li><?php echo esc_html(rbf_translate_string('Dashboard analytics con statistiche in tempo reale')); ?></li>
                                <li><?php echo esc_html(rbf_translate_string('Monitoraggio performance API (Meta CAPI, Brevo, GA4)')); ?></li>
                                <li><?php echo esc_html(rbf_translate_string('Validazione avanzata parametri UTM con rilevamento minacce')); ?></li>
                                <li><?php echo esc_html(rbf_translate_string('Log strutturati con export JSON per analisi esterne')); ?></li>
                                <li><?php echo esc_html(rbf_translate_string('Tracciamento prestazioni operazioni critiche')); ?></li>
                            </ul>
                            <p style="margin: 10px 0 0 0; font-style: italic; color: #666;">
                                <?php echo esc_html(rbf_translate_string('Accedi al pannello debug da: Admin → Prenotazioni → 🔧 Debug (visibile solo quando abilitato)')); ?>
                            </p>
                        </div>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

/**
 * Calendar page HTML
 */
function rbf_calendar_page_html() {
    wp_enqueue_style('fullcalendar-css', 'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css', [], '5.11.3');
    wp_enqueue_script('fullcalendar-js', 'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js', ['jquery'], '5.11.3', true);
    wp_enqueue_script('rbf-admin-js', plugin_dir_url(dirname(__FILE__)) . 'assets/js/admin.js', ['jquery', 'fullcalendar-js'], '10.0.0', true);
    
    wp_localize_script('rbf-admin-js', 'rbfAdminData', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('rbf_calendar_nonce'),
        'editUrl' => admin_url('post.php?post=BOOKING_ID&action=edit')
    ]);
    ?>
    <div class="rbf-admin-wrap">
        <h1><?php echo esc_html(rbf_translate_string('Prenotazioni')); ?></h1>
        
        <div id="rbf-calendar"></div>
    </div>
    <?php
}

/**
 * AJAX handler for calendar bookings
 */
add_action('wp_ajax_rbf_get_bookings_for_calendar', 'rbf_get_bookings_for_calendar_callback');
function rbf_get_bookings_for_calendar_callback() {
    check_ajax_referer('rbf_calendar_nonce', '_ajax_nonce');
    $start = sanitize_text_field($_POST['start']);
    $end = sanitize_text_field($_POST['end']);

    $args = [
        'post_type' => 'rbf_booking',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'meta_query' => [[
            'key' => 'rbf_data',
            'value' => [$start, $end],
            'compare' => 'BETWEEN',
            'type' => 'DATE'
        ]]
    ];

    $bookings = get_posts($args);
    $events = [];
    foreach ($bookings as $booking) {
        $date = get_post_meta($booking->ID, 'rbf_data', true);
        $time = get_post_meta($booking->ID, 'rbf_time', true);
        $people = get_post_meta($booking->ID, 'rbf_persone', true);
        $first_name = get_post_meta($booking->ID, 'rbf_nome', true);
        $last_name = get_post_meta($booking->ID, 'rbf_cognome', true);
        $email = get_post_meta($booking->ID, 'rbf_email', true);
        $phone = get_post_meta($booking->ID, 'rbf_tel', true);
        $notes = get_post_meta($booking->ID, 'rbf_allergie', true);
        $status = get_post_meta($booking->ID, 'rbf_booking_status', true) ?: 'confirmed';
        $meal = get_post_meta($booking->ID, 'rbf_orario', true);
        
        $title = $booking->post_title . ' (' . $people . ' persone)';
        
        // Color coding based on status
        $color = '#28a745'; // confirmed - green
        if ($status === 'cancelled') $color = '#dc3545'; // red
        if ($status === 'completed') $color = '#6c757d'; // gray
        
        $events[] = [
            'title' => $title,
            'start' => $date . 'T' . $time,
            'backgroundColor' => $color,
            'borderColor' => $color,
            'className' => 'fc-status-' . $status,
            'extendedProps' => [
                'booking_id' => $booking->ID,
                'customer_name' => trim($first_name . ' ' . $last_name),
                'customer_email' => $email,
                'customer_phone' => $phone,
                'booking_date' => date('d/m/Y', strtotime($date)),
                'booking_time' => $time,
                'people' => $people,
                'notes' => $notes,
                'status' => $status,
                'meal' => $meal
            ]
        ];
    }

    wp_send_json_success($events);
}

/**
 * AJAX handler for updating booking status
 */
add_action('wp_ajax_rbf_update_booking_status', 'rbf_update_booking_status_callback');
function rbf_update_booking_status_callback() {
    check_ajax_referer('rbf_calendar_nonce', '_ajax_nonce');
    
    $booking_id = intval($_POST['booking_id']);
    $status = sanitize_text_field($_POST['status']);
    
    if (!$booking_id || !in_array($status, ['confirmed', 'cancelled', 'completed'])) {
        wp_send_json_error('Parametri non validi');
    }
    
    $booking = get_post($booking_id);
    if (!$booking || $booking->post_type !== 'rbf_booking') {
        wp_send_json_error('Prenotazione non trovata');
    }
    
    $updated = update_post_meta($booking_id, 'rbf_booking_status', $status);
    
    if ($updated !== false) {
        wp_send_json_success('Stato aggiornato con successo');
    } else {
        wp_send_json_error('Errore durante l\'aggiornamento');
    }
}

/**
 * AJAX handler for updating complete booking data
 */
add_action('wp_ajax_rbf_update_booking_data', 'rbf_update_booking_data_callback');
function rbf_update_booking_data_callback() {
    check_ajax_referer('rbf_calendar_nonce', '_ajax_nonce');
    
    $booking_id = intval($_POST['booking_id']);
    $booking_data = $_POST['booking_data'];
    
    if (!$booking_id || !$booking_data) {
        wp_send_json_error('Parametri non validi');
    }
    
    $booking = get_post($booking_id);
    if (!$booking || $booking->post_type !== 'rbf_booking') {
        wp_send_json_error('Prenotazione non trovata');
    }
    
    // Update customer name (split into first and last name)
    if (isset($booking_data['customer_name'])) {
        $name_parts = explode(' ', sanitize_text_field($booking_data['customer_name']), 2);
        update_post_meta($booking_id, 'rbf_nome', $name_parts[0]);
        update_post_meta($booking_id, 'rbf_cognome', isset($name_parts[1]) ? $name_parts[1] : '');
        
        // Update post title
        wp_update_post([
            'ID' => $booking_id,
            'post_title' => sanitize_text_field($booking_data['customer_name'])
        ]);
    }
    
    // Update email
    if (isset($booking_data['customer_email'])) {
        update_post_meta($booking_id, 'rbf_email', sanitize_email($booking_data['customer_email']));
    }
    
    // Update phone
    if (isset($booking_data['customer_phone'])) {
        update_post_meta($booking_id, 'rbf_tel', sanitize_text_field($booking_data['customer_phone']));
    }
    
    // Update people count
    if (isset($booking_data['people'])) {
        $people = intval($booking_data['people']);
        if ($people > 0 && $people <= 30) {
            update_post_meta($booking_id, 'rbf_persone', $people);
        }
    }
    
    // Update notes
    if (isset($booking_data['notes'])) {
        update_post_meta($booking_id, 'rbf_allergie', sanitize_textarea_field($booking_data['notes']));
    }
    
    // Update status
    if (isset($booking_data['status']) && in_array($booking_data['status'], ['confirmed', 'cancelled', 'completed'])) {
        update_post_meta($booking_id, 'rbf_booking_status', $booking_data['status']);
    }
    
    wp_send_json_success('Prenotazione aggiornata con successo');
}

/**
 * Add booking page HTML
 */
function rbf_add_booking_page_html() {
    $options = get_option('rbf_settings', rbf_get_default_settings());
    $message = '';

    if (!empty($_POST) && check_admin_referer('rbf_add_backend_booking')) {
        $meal = sanitize_text_field($_POST['rbf_meal'] ?? '');
        $date = sanitize_text_field($_POST['rbf_data'] ?? '');
        $time = sanitize_text_field($_POST['rbf_time'] ?? '');
        $people = intval($_POST['rbf_persone'] ?? 0);
        $first_name = sanitize_text_field($_POST['rbf_nome'] ?? '');
        $last_name = sanitize_text_field($_POST['rbf_cognome'] ?? '');
        $email = sanitize_email($_POST['rbf_email'] ?? '');
        $tel = sanitize_text_field($_POST['rbf_tel'] ?? '');
        $notes = sanitize_textarea_field($_POST['rbf_allergie'] ?? '');
        $lang = sanitize_text_field($_POST['rbf_lang'] ?? 'it');
        $privacy = isset($_POST['rbf_privacy']) ? 'yes' : 'no';
        $marketing = isset($_POST['rbf_marketing']) ? 'yes' : 'no';

        $title = (!empty($first_name) && !empty($last_name)) ? ucfirst($meal) . " per {$first_name} {$last_name} - {$date} {$time}" : "Prenotazione Manuale - {$date} {$time}";

        $post_id = wp_insert_post([
            'post_type' => 'rbf_booking',
            'post_title' => $title,
            'post_status' => 'publish',
            'meta_input' => [
                'rbf_data' => $date,
                'rbf_orario' => $meal,
                'rbf_time' => $time,
                'rbf_persone' => $people,
                'rbf_nome' => $first_name,
                'rbf_cognome' => $last_name,
                'rbf_email' => $email,
                'rbf_tel' => $tel,
                'rbf_allergie' => $notes,
                'rbf_lang' => $lang,
                'rbf_source_bucket' => 'backend',
                'rbf_source' => 'backend',
                'rbf_medium' => 'backend',
                'rbf_campaign' => '',
                'rbf_privacy' => $privacy,
                'rbf_marketing' => $marketing,
                // Enhanced booking system
                'rbf_booking_status' => 'confirmed', // Backend bookings start confirmed
                'rbf_booking_created' => current_time('Y-m-d H:i:s'),
                'rbf_booking_hash' => wp_generate_password(16, false, false),
            ],
        ]);

        if (!is_wp_error($post_id)) {
            $valore_pp = (float) ($options['valore_' . $meal] ?? 0);
            $valore_tot = $valore_pp * $people;
            $event_id   = 'rbf_' . $post_id;

            // Email + Brevo (functions will be loaded from integrations module)
            if (function_exists('rbf_send_admin_notification_email')) {
                rbf_send_admin_notification_email($first_name, $last_name, $email, $date, $time, $people, $notes, $tel, $meal);
            }
            if (function_exists('rbf_trigger_brevo_automation')) {
                rbf_trigger_brevo_automation($first_name, $last_name, $email, $date, $time, $people, $notes, $lang, $tel, $marketing, $meal);
            }

            // Transient per tracking (anche per inserimenti manuali)
            set_transient('rbf_booking_data_' . $post_id, [
                'id'       => $post_id,
                'value'    => $valore_tot,
                'currency' => 'EUR',
                'meal'     => $meal,
                'people'   => $people,
                'bucket'   => 'backend',
                'event_id' => $event_id
            ], 60 * 15);

            $message = '<div class="notice notice-success"><p>Prenotazione aggiunta con successo! <a href="' . admin_url('post.php?post=' . $post_id . '&action=edit') . '">Modifica</a></p></div>';
        } else {
            $message = '<div class="notice notice-error"><p>Errore durante l\'aggiunta della prenotazione.</p></div>';
        }
    }

    ?>
    <div class="rbf-admin-wrap">
        <h1><?php echo esc_html(rbf_translate_string('Aggiungi Nuova Prenotazione')); ?></h1>
        <?php echo $message; ?>
        <form method="post">
            <?php wp_nonce_field('rbf_add_backend_booking'); ?>
            <table class="form-table">
                <tr><th><label for="rbf_meal"><?php echo esc_html(rbf_translate_string('Pasto')); ?></label></th>
                    <td><select id="rbf_meal" name="rbf_meal">
                        <option value=""><?php echo esc_html(rbf_translate_string('Scegli il pasto')); ?></option>
                        <option value="pranzo"><?php echo esc_html(rbf_translate_string('Pranzo')); ?></option>
                        <option value="aperitivo"><?php echo esc_html(rbf_translate_string('Aperitivo')); ?></option>
                        <option value="cena"><?php echo esc_html(rbf_translate_string('Cena')); ?></option>
                    </select></td></tr>
                <tr><th><label for="rbf_data"><?php echo esc_html(rbf_translate_string('Data')); ?></label></th>
                    <td><input type="date" id="rbf_data" name="rbf_data"></td></tr>
                <tr><th><label for="rbf_time"><?php echo esc_html(rbf_translate_string('Orario')); ?></label></th>
                    <td><input type="time" id="rbf_time" name="rbf_time"></td></tr>
                <tr><th><label for="rbf_persone"><?php echo esc_html(rbf_translate_string('Persone')); ?></label></th>
                    <td><input type="number" id="rbf_persone" name="rbf_persone" min="0"></td></tr>
                <tr><th><label for="rbf_nome"><?php echo esc_html(rbf_translate_string('Nome')); ?></label></th>
                    <td><input type="text" id="rbf_nome" name="rbf_nome"></td></tr>
                <tr><th><label for="rbf_cognome"><?php echo esc_html(rbf_translate_string('Cognome')); ?></label></th>
                    <td><input type="text" id="rbf_cognome" name="rbf_cognome"></td></tr>
                <tr><th><label for="rbf_email"><?php echo esc_html(rbf_translate_string('Email')); ?></label></th>
                    <td><input type="email" id="rbf_email" name="rbf_email"></td></tr>
                <tr><th><label for="rbf_tel"><?php echo esc_html(rbf_translate_string('Telefono')); ?></label></th>
                    <td><input type="tel" id="rbf_tel" name="rbf_tel"></td></tr>
                <tr><th><label for="rbf_allergie"><?php echo esc_html(rbf_translate_string('Allergie/Note')); ?></label></th>
                    <td><textarea id="rbf_allergie" name="rbf_allergie"></textarea></td></tr>
                <tr><th><label for="rbf_lang"><?php echo esc_html(rbf_translate_string('Lingua')); ?></label></th>
                    <td><select id="rbf_lang" name="rbf_lang"><option value="it">IT</option><option value="en">EN</option></select></td></tr>
                <tr><th><?php echo esc_html(rbf_translate_string('Privacy')); ?></th>
                    <td><label><input type="checkbox" name="rbf_privacy" value="yes"> <?php echo esc_html(rbf_translate_string('Accettata')); ?></label></td></tr>
                <tr><th><?php echo esc_html(rbf_translate_string('Marketing')); ?></th>
                    <td><label><input type="checkbox" name="rbf_marketing" value="yes"> <?php echo esc_html(rbf_translate_string('Accettato')); ?></label></td></tr>
            </table>
            <?php submit_button(rbf_translate_string('Aggiungi Prenotazione')); ?>
        </form>
    </div>
    <?php
}



/**
 * Make status column sortable
 */
add_filter('manage_edit-rbf_booking_sortable_columns', 'rbf_set_sortable_columns');
function rbf_set_sortable_columns($columns) {
    $columns['rbf_status'] = 'rbf_booking_status';
    $columns['rbf_booking_date'] = 'rbf_data';
    $columns['rbf_people'] = 'rbf_persone';
    return $columns;
}

/**
 * Handle sorting for custom columns
 */
add_action('pre_get_posts', 'rbf_handle_custom_sorting');
function rbf_handle_custom_sorting($query) {
    if (!is_admin() || !$query->is_main_query()) return;
    if ($query->get('post_type') !== 'rbf_booking') return;
    
    $orderby = $query->get('orderby');
    
    switch ($orderby) {
        case 'rbf_booking_status':
            $query->set('meta_key', 'rbf_booking_status');
            $query->set('orderby', 'meta_value');
            break;
        case 'rbf_data':
            $query->set('meta_key', 'rbf_data');
            $query->set('orderby', 'meta_value');
            break;
        case 'rbf_persone':
            $query->set('meta_key', 'rbf_persone');
            $query->set('orderby', 'meta_value_num');
            break;
    }
}

/**
 * Add status filter dropdown
 */
add_action('restrict_manage_posts', 'rbf_add_status_filter');
function rbf_add_status_filter() {
    global $typenow;
    
    if ($typenow === 'rbf_booking') {
        $selected_status = $_GET['rbf_status'] ?? '';
        $statuses = rbf_get_booking_statuses();
        
        echo '<select name="rbf_status">';
        echo '<option value="">' . rbf_translate_string('Tutti gli stati') . '</option>';
        foreach ($statuses as $key => $label) {
            echo '<option value="' . esc_attr($key) . '"' . selected($selected_status, $key, false) . '>';
            echo esc_html($label);
            echo '</option>';
        }
        echo '</select>';
    }
}

/**
 * Reports and Analytics page HTML
 */
function rbf_reports_page_html() {
    // Enqueue Chart.js for analytics
    wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js', [], '3.9.1', true);
    
    // Get date range for reports (default: last 30 days)
    $end_date = $_GET['end_date'] ?? date('Y-m-d');
    $start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    
    // Validate dates
    if (!DateTime::createFromFormat('Y-m-d', $start_date)) $start_date = date('Y-m-d', strtotime('-30 days'));
    if (!DateTime::createFromFormat('Y-m-d', $end_date)) $end_date = date('Y-m-d');
    
    $analytics = rbf_get_booking_analytics($start_date, $end_date);
    ?>
    <div class="rbf-admin-wrap">
        <h1><?php echo esc_html(rbf_translate_string('Report & Analytics')); ?></h1>
        
        <!-- Date Range Filter -->
        <div class="rbf-date-filter" style="background: #f9f9f9; padding: 15px; margin-bottom: 20px; border-radius: 8px;">
            <form method="get" style="display: flex; align-items: center; gap: 15px;">
                <input type="hidden" name="page" value="rbf_reports">
                <label for="start_date"><?php echo esc_html(rbf_translate_string('Da:')); ?></label>
                <input type="date" id="start_date" name="start_date" value="<?php echo esc_attr($start_date); ?>">
                <label for="end_date"><?php echo esc_html(rbf_translate_string('A:')); ?></label>
                <input type="date" id="end_date" name="end_date" value="<?php echo esc_attr($end_date); ?>">
                <button type="submit" class="button button-primary"><?php echo esc_html(rbf_translate_string('Aggiorna Report')); ?></button>
            </form>
        </div>
        
        <!-- Key Metrics Cards -->
        <div class="rbf-metrics-cards" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div class="rbf-metric-card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3 style="margin: 0 0 10px 0; color: #1f2937; font-size: 16px;"><?php echo esc_html(rbf_translate_string('Prenotazioni Totali')); ?></h3>
                <div style="font-size: 32px; font-weight: bold; color: #10b981;"><?php echo esc_html($analytics['total_bookings']); ?></div>
                <small style="color: #6b7280;"><?php echo esc_html(sprintf(rbf_translate_string('Dal %s al %s'), date('d/m/Y', strtotime($start_date)), date('d/m/Y', strtotime($end_date)))); ?></small>
            </div>
            
            <div class="rbf-metric-card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3 style="margin: 0 0 10px 0; color: #1f2937; font-size: 16px;"><?php echo esc_html(rbf_translate_string('Persone Totali')); ?></h3>
                <div style="font-size: 32px; font-weight: bold; color: #3b82f6;"><?php echo esc_html($analytics['total_people']); ?></div>
                <small style="color: #6b7280;"><?php echo esc_html(sprintf(rbf_translate_string('Media: %.1f per prenotazione'), $analytics['avg_people_per_booking'])); ?></small>
            </div>
            
            <div class="rbf-metric-card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3 style="margin: 0 0 10px 0; color: #1f2937; font-size: 16px;"><?php echo esc_html(rbf_translate_string('Valore Stimato')); ?></h3>
                <div style="font-size: 32px; font-weight: bold; color: #f59e0b;">€<?php echo esc_html(number_format($analytics['total_revenue'], 2)); ?></div>
                <small style="color: #6b7280;"><?php echo esc_html(sprintf(rbf_translate_string('Media: €%.2f per prenotazione'), $analytics['avg_revenue_per_booking'])); ?></small>
            </div>
            
            <div class="rbf-metric-card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3 style="margin: 0 0 10px 0; color: #1f2937; font-size: 16px;"><?php echo esc_html(rbf_translate_string('Tasso Completamento')); ?></h3>
                <div style="font-size: 32px; font-weight: bold; color: #8b5cf6;"><?php echo esc_html(number_format($analytics['completion_rate'], 1)); ?>%</div>
                <small style="color: #6b7280;"><?php echo esc_html(sprintf(rbf_translate_string('%d completate su %d confermate'), $analytics['completed_bookings'], $analytics['confirmed_bookings'])); ?></small>
            </div>
        </div>
        
        <!-- Charts Row -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
            <!-- Bookings by Status Chart -->
            <div class="rbf-chart-container" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3 style="margin: 0 0 20px 0;"><?php echo esc_html(rbf_translate_string('Prenotazioni per Stato')); ?></h3>
                <canvas id="statusChart" width="400" height="200"></canvas>
            </div>
            
            <!-- Bookings by Meal Type Chart -->
            <div class="rbf-chart-container" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3 style="margin: 0 0 20px 0;"><?php echo esc_html(rbf_translate_string('Prenotazioni per Servizio')); ?></h3>
                <canvas id="mealChart" width="400" height="200"></canvas>
            </div>
        </div>
        
        <!-- Daily Bookings Chart -->
        <div class="rbf-chart-container" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 30px;">
            <h3 style="margin: 0 0 20px 0;"><?php echo esc_html(rbf_translate_string('Andamento Prenotazioni Giornaliere')); ?></h3>
            <canvas id="dailyChart" width="800" height="300"></canvas>
        </div>
        
        <!-- Source Attribution Analysis -->
        <div class="rbf-chart-container" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h3 style="margin: 0 0 20px 0;"><?php echo esc_html(rbf_translate_string('Analisi Sorgenti di Traffico')); ?></h3>
            <canvas id="sourceChart" width="800" height="300"></canvas>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const statusData = <?php echo wp_json_encode($analytics['by_status']); ?>;
        const mealData = <?php echo wp_json_encode($analytics['by_meal']); ?>;
        const dailyData = <?php echo wp_json_encode($analytics['daily_bookings']); ?>;
        const sourceData = <?php echo wp_json_encode($analytics['by_source']); ?>;
        
        // Status Chart
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: Object.keys(statusData),
                datasets: [{
                    data: Object.values(statusData),
                    backgroundColor: ['#f59e0b', '#10b981', '#06b6d4', '#ef4444', '#8b5cf6'],
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
        
        // Meal Chart
        new Chart(document.getElementById('mealChart'), {
            type: 'bar',
            data: {
                labels: Object.keys(mealData),
                datasets: [{
                    data: Object.values(mealData),
                    backgroundColor: ['#3b82f6', '#f59e0b', '#10b981'],
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
        
        // Daily Chart
        new Chart(document.getElementById('dailyChart'), {
            type: 'line',
            data: {
                labels: Object.keys(dailyData),
                datasets: [{
                    label: '<?php echo esc_js(rbf_translate_string('Prenotazioni')); ?>',
                    data: Object.values(dailyData),
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    fill: true,
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
        
        // Source Chart
        new Chart(document.getElementById('sourceChart'), {
            type: 'bar',
            data: {
                labels: Object.keys(sourceData),
                datasets: [{
                    label: '<?php echo esc_js(rbf_translate_string('Prenotazioni')); ?>',
                    data: Object.values(sourceData),
                    backgroundColor: ['#3b82f6', '#f59e0b', '#10b981', '#ef4444', '#8b5cf6', '#6b7280'],
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    });
    </script>
    <?php
}

/**
 * Filter bookings by status
 */
add_action('pre_get_posts', 'rbf_filter_by_status');
function rbf_filter_by_status($query) {
    if (!is_admin() || !$query->is_main_query()) return;
    if ($query->get('post_type') !== 'rbf_booking') return;
    
    $status = $_GET['rbf_status'] ?? '';
    if ($status) {
        $query->set('meta_query', [
            [
                'key' => 'rbf_booking_status',
                'value' => $status,
                'compare' => '='
            ]
        ]);
    }
}

/**
 * Get booking analytics for reports
 */
function rbf_get_booking_analytics($start_date, $end_date) {
    global $wpdb;
    
    // Get all bookings in date range
    $bookings = $wpdb->get_results($wpdb->prepare(
        "SELECT p.ID, pm_date.meta_value as booking_date, pm_people.meta_value as people, 
                pm_meal.meta_value as meal, pm_status.meta_value as status,
                pm_source.meta_value as source, pm_bucket.meta_value as bucket
         FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'rbf_data'
         LEFT JOIN {$wpdb->postmeta} pm_people ON p.ID = pm_people.post_id AND pm_people.meta_key = 'rbf_persone'
         LEFT JOIN {$wpdb->postmeta} pm_meal ON p.ID = pm_meal.post_id AND pm_meal.meta_key = 'rbf_orario'
         LEFT JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = 'rbf_booking_status'
         LEFT JOIN {$wpdb->postmeta} pm_source ON p.ID = pm_source.post_id AND pm_source.meta_key = 'rbf_source'
         LEFT JOIN {$wpdb->postmeta} pm_bucket ON p.ID = pm_bucket.post_id AND pm_bucket.meta_key = 'rbf_source_bucket'
         WHERE p.post_type = 'rbf_booking' AND p.post_status = 'publish'
         AND pm_date.meta_value >= %s AND pm_date.meta_value <= %s
         ORDER BY pm_date.meta_value ASC",
        $start_date, $end_date
    ));
    
    $options = get_option('rbf_settings', rbf_get_default_settings());
    $statuses = rbf_get_booking_statuses();
    $meals = [
        'pranzo' => rbf_translate_string('Pranzo'),
        'cena' => rbf_translate_string('Cena'),
        'aperitivo' => rbf_translate_string('Aperitivo')
    ];
    
    // Initialize analytics data
    $analytics = [
        'total_bookings' => 0,
        'total_people' => 0,
        'total_revenue' => 0,
        'avg_people_per_booking' => 0,
        'avg_revenue_per_booking' => 0,
        'completion_rate' => 0,
        'confirmed_bookings' => 0,
        'completed_bookings' => 0,
        'by_status' => [],
        'by_meal' => [],
        'by_source' => [],
        'daily_bookings' => []
    ];
    
    // Initialize status counts
    foreach ($statuses as $key => $label) {
        $analytics['by_status'][$label] = 0;
    }
    
    // Initialize meal counts
    foreach ($meals as $key => $label) {
        $analytics['by_meal'][$label] = 0;
    }
    
    // Initialize daily data for the range
    $current_date = new DateTime($start_date);
    $end_date_obj = new DateTime($end_date);
    while ($current_date <= $end_date_obj) {
        $analytics['daily_bookings'][$current_date->format('d/m')] = 0;
        $current_date->modify('+1 day');
    }
    
    // Process each booking
    foreach ($bookings as $booking) {
        $people = intval($booking->people ?: 0);
        $meal = $booking->meal ?: 'pranzo';
        $status = $booking->status ?: 'confirmed';
        $source = $booking->source ?: 'direct';
        $bucket = $booking->bucket ?: 'direct';
        
        // Basic totals
        $analytics['total_bookings']++;
        $analytics['total_people'] += $people;
        
        // Revenue calculation
        $meal_value = (float) ($options['valore_' . $meal] ?? 0);
        $booking_revenue = $meal_value * $people;
        $analytics['total_revenue'] += $booking_revenue;
        
        // Status tracking
        $status_label = $statuses[$status] ?? $status;
        $analytics['by_status'][$status_label]++;
        
        if ($status === 'confirmed' || $status === 'completed') {
            $analytics['confirmed_bookings']++;
        }
        if ($status === 'completed') {
            $analytics['completed_bookings']++;
        }
        
        // Meal tracking
        $meal_label = $meals[$meal] ?? ucfirst($meal);
        $analytics['by_meal'][$meal_label]++;
        
        // Source tracking
        $source_label = ucfirst($bucket ?: $source);
        if (!isset($analytics['by_source'][$source_label])) {
            $analytics['by_source'][$source_label] = 0;
        }
        $analytics['by_source'][$source_label]++;
        
        // Daily tracking
        $booking_date = DateTime::createFromFormat('Y-m-d', $booking->booking_date);
        if ($booking_date) {
            $day_key = $booking_date->format('d/m');
            if (isset($analytics['daily_bookings'][$day_key])) {
                $analytics['daily_bookings'][$day_key]++;
            }
        }
    }
    
    // Calculate averages and rates
    if ($analytics['total_bookings'] > 0) {
        $analytics['avg_people_per_booking'] = $analytics['total_people'] / $analytics['total_bookings'];
        $analytics['avg_revenue_per_booking'] = $analytics['total_revenue'] / $analytics['total_bookings'];
    }
    
    if ($analytics['confirmed_bookings'] > 0) {
        $analytics['completion_rate'] = ($analytics['completed_bookings'] / $analytics['confirmed_bookings']) * 100;
    }
    
    // Sort sources by count
    arsort($analytics['by_source']);
    
    return $analytics;
}

/**
 * Export page HTML
 */
function rbf_export_page_html() {
    // Handle export request
    if (isset($_POST['export_bookings']) && wp_verify_nonce($_POST['_wpnonce'], 'rbf_export')) {
        $start_date = sanitize_text_field($_POST['start_date']);
        $end_date = sanitize_text_field($_POST['end_date']);
        $format = sanitize_text_field($_POST['format']);
        $status_filter = sanitize_text_field($_POST['status_filter']);
        
        rbf_handle_export_request($start_date, $end_date, $format, $status_filter);
        return; // Exit after sending file
    }
    
    $default_start = date('Y-m-d', strtotime('-30 days'));
    $default_end = date('Y-m-d');
    ?>
    <div class="rbf-admin-wrap">
        <h1><?php echo esc_html(rbf_translate_string('Esporta Dati Prenotazioni')); ?></h1>
        
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <form method="post">
                <?php wp_nonce_field('rbf_export'); ?>
                
                <table class="form-table">
                    <tr>
                        <th><label for="start_date"><?php echo esc_html(rbf_translate_string('Data Inizio')); ?></label></th>
                        <td><input type="date" id="start_date" name="start_date" value="<?php echo esc_attr($default_start); ?>" required></td>
                    </tr>
                    <tr>
                        <th><label for="end_date"><?php echo esc_html(rbf_translate_string('Data Fine')); ?></label></th>
                        <td><input type="date" id="end_date" name="end_date" value="<?php echo esc_attr($default_end); ?>" required></td>
                    </tr>
                    <tr>
                        <th><label for="status_filter"><?php echo esc_html(rbf_translate_string('Filtra per Stato')); ?></label></th>
                        <td>
                            <select id="status_filter" name="status_filter">
                                <option value=""><?php echo esc_html(rbf_translate_string('Tutti gli stati')); ?></option>
                                <?php
                                $statuses = rbf_get_booking_statuses();
                                foreach ($statuses as $key => $label) {
                                    echo '<option value="' . esc_attr($key) . '">' . esc_html($label) . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="format"><?php echo esc_html(rbf_translate_string('Formato Export')); ?></label></th>
                        <td>
                            <select id="format" name="format">
                                <option value="csv">CSV (Excel)</option>
                                <option value="json">JSON</option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="export_bookings" class="button button-primary" value="<?php echo esc_attr(rbf_translate_string('Esporta Prenotazioni')); ?>">
                </p>
            </form>
        </div>
        
        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 20px;">
            <h3><?php echo esc_html(rbf_translate_string('Informazioni Export')); ?></h3>
            <p><?php echo esc_html(rbf_translate_string('L\'export includerà tutti i dati delle prenotazioni nel periodo selezionato:')); ?></p>
            <ul>
                <li><?php echo esc_html(rbf_translate_string('Informazioni cliente (nome, email, telefono)')); ?></li>
                <li><?php echo esc_html(rbf_translate_string('Dettagli prenotazione (data, orario, servizio, persone)')); ?></li>
                <li><?php echo esc_html(rbf_translate_string('Stato prenotazione e cronologia')); ?></li>
                <li><?php echo esc_html(rbf_translate_string('Sorgenti di traffico e parametri UTM')); ?></li>
                <li><?php echo esc_html(rbf_translate_string('Note e preferenze alimentari')); ?></li>
                <li><?php echo esc_html(rbf_translate_string('Consensi privacy e marketing')); ?></li>
            </ul>
        </div>
    </div>
    <?php
}

/**
 * Handle export request
 */
function rbf_handle_export_request($start_date, $end_date, $format, $status_filter) {
    global $wpdb;
    
    // Build query
    $where_status = '';
    if ($status_filter) {
        $where_status = $wpdb->prepare(" AND pm_status.meta_value = %s", $status_filter);
    }
    
    $bookings = $wpdb->get_results($wpdb->prepare(
        "SELECT p.ID, p.post_title, p.post_date,
                pm_date.meta_value as booking_date,
                pm_time.meta_value as booking_time,
                pm_people.meta_value as people,
                pm_meal.meta_value as meal,
                pm_status.meta_value as status,
                pm_first_name.meta_value as first_name,
                pm_last_name.meta_value as last_name,
                pm_email.meta_value as email,
                pm_tel.meta_value as tel,
                pm_notes.meta_value as notes,
                pm_lang.meta_value as language,
                pm_privacy.meta_value as privacy,
                pm_marketing.meta_value as marketing,
                pm_source.meta_value as source,
                pm_medium.meta_value as medium,
                pm_campaign.meta_value as campaign,
                pm_bucket.meta_value as bucket,
                pm_gclid.meta_value as gclid,
                pm_fbclid.meta_value as fbclid,
                pm_created.meta_value as created_date
         FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'rbf_data'
         LEFT JOIN {$wpdb->postmeta} pm_time ON p.ID = pm_time.post_id AND pm_time.meta_key = 'rbf_time'
         LEFT JOIN {$wpdb->postmeta} pm_people ON p.ID = pm_people.post_id AND pm_people.meta_key = 'rbf_persone'
         LEFT JOIN {$wpdb->postmeta} pm_meal ON p.ID = pm_meal.post_id AND pm_meal.meta_key = 'rbf_orario'
         LEFT JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = 'rbf_booking_status'
         LEFT JOIN {$wpdb->postmeta} pm_first_name ON p.ID = pm_first_name.post_id AND pm_first_name.meta_key = 'rbf_nome'
         LEFT JOIN {$wpdb->postmeta} pm_last_name ON p.ID = pm_last_name.post_id AND pm_last_name.meta_key = 'rbf_cognome'
         LEFT JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = 'rbf_email'
         LEFT JOIN {$wpdb->postmeta} pm_tel ON p.ID = pm_tel.post_id AND pm_tel.meta_key = 'rbf_tel'
         LEFT JOIN {$wpdb->postmeta} pm_notes ON p.ID = pm_notes.post_id AND pm_notes.meta_key = 'rbf_allergie'
         LEFT JOIN {$wpdb->postmeta} pm_lang ON p.ID = pm_lang.post_id AND pm_lang.meta_key = 'rbf_lang'
         LEFT JOIN {$wpdb->postmeta} pm_privacy ON p.ID = pm_privacy.post_id AND pm_privacy.meta_key = 'rbf_privacy'
         LEFT JOIN {$wpdb->postmeta} pm_marketing ON p.ID = pm_marketing.post_id AND pm_marketing.meta_key = 'rbf_marketing'
         LEFT JOIN {$wpdb->postmeta} pm_source ON p.ID = pm_source.post_id AND pm_source.meta_key = 'rbf_source'
         LEFT JOIN {$wpdb->postmeta} pm_medium ON p.ID = pm_medium.post_id AND pm_medium.meta_key = 'rbf_medium'
         LEFT JOIN {$wpdb->postmeta} pm_campaign ON p.ID = pm_campaign.post_id AND pm_campaign.meta_key = 'rbf_campaign'
         LEFT JOIN {$wpdb->postmeta} pm_bucket ON p.ID = pm_bucket.post_id AND pm_bucket.meta_key = 'rbf_source_bucket'
         LEFT JOIN {$wpdb->postmeta} pm_gclid ON p.ID = pm_gclid.post_id AND pm_gclid.meta_key = 'rbf_gclid'
         LEFT JOIN {$wpdb->postmeta} pm_fbclid ON p.ID = pm_fbclid.post_id AND pm_fbclid.meta_key = 'rbf_fbclid'
         LEFT JOIN {$wpdb->postmeta} pm_created ON p.ID = pm_created.post_id AND pm_created.meta_key = 'rbf_booking_created'
         WHERE p.post_type = 'rbf_booking' AND p.post_status = 'publish'
         AND pm_date.meta_value >= %s AND pm_date.meta_value <= %s
         {$where_status}
         ORDER BY pm_date.meta_value DESC, pm_time.meta_value DESC",
        $start_date, $end_date
    ));
    
    if ($format === 'csv') {
        rbf_export_csv($bookings, $start_date, $end_date);
    } else {
        rbf_export_json($bookings, $start_date, $end_date);
    }
}

/**
 * Export bookings as CSV
 */
function rbf_export_csv($bookings, $start_date, $end_date) {
    $filename = 'bookings_' . $start_date . '_to_' . $end_date . '_' . date('Ymd_His') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for Excel UTF-8 support
    fwrite($output, "\xEF\xBB\xBF");
    
    // CSV Headers
    $headers = [
        'ID', 'Data Prenotazione', 'Orario', 'Nome', 'Cognome', 'Email', 'Telefono',
        'Persone', 'Servizio', 'Stato', 'Note/Allergie', 'Lingua', 'Privacy', 'Marketing',
        'Sorgente', 'Medium', 'Campagna', 'Bucket', 'Google Click ID', 'Facebook Click ID',
        'Data Creazione', 'Data Invio'
    ];
    
    fputcsv($output, $headers);
    
    // Data rows
    foreach ($bookings as $booking) {
        $statuses = rbf_get_booking_statuses();
        $status_label = $statuses[$booking->status ?? 'confirmed'] ?? ($booking->status ?? 'confirmed');
        
        $row = [
            $booking->ID,
            $booking->booking_date,
            $booking->booking_time,
            $booking->first_name,
            $booking->last_name,
            $booking->email,
            $booking->tel,
            $booking->people,
            $booking->meal,
            $status_label,
            $booking->notes,
            $booking->language ?: 'it',
            $booking->privacy === 'yes' ? 'Sì' : 'No',
            $booking->marketing === 'yes' ? 'Sì' : 'No',
            $booking->source,
            $booking->medium,
            $booking->campaign,
            $booking->bucket,
            $booking->gclid,
            $booking->fbclid,
            $booking->created_date,
            $booking->post_date
        ];
        
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

/**
 * Export bookings as JSON
 */
function rbf_export_json($bookings, $start_date, $end_date) {
    $filename = 'bookings_' . $start_date . '_to_' . $end_date . '_' . date('Ymd_His') . '.json';
    
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $export_data = [
        'export_info' => [
            'generated' => current_time('Y-m-d H:i:s'),
            'date_range' => ['start' => $start_date, 'end' => $end_date],
            'total_bookings' => count($bookings),
            'plugin_version' => '10.0.0'
        ],
        'bookings' => []
    ];
    
    $statuses = rbf_get_booking_statuses();
    
    foreach ($bookings as $booking) {
        $export_data['bookings'][] = [
            'id' => intval($booking->ID),
            'title' => $booking->post_title,
            'booking_date' => $booking->booking_date,
            'booking_time' => $booking->booking_time,
            'customer' => [
                'first_name' => $booking->first_name,
                'last_name' => $booking->last_name,
                'email' => $booking->email,
                'phone' => $booking->tel,
                'language' => $booking->language ?: 'it'
            ],
            'booking_details' => [
                'people' => intval($booking->people ?: 0),
                'meal' => $booking->meal,
                'status' => $booking->status ?: 'confirmed',
                'status_label' => $statuses[$booking->status ?? 'confirmed'] ?? ($booking->status ?? 'confirmed'),
                'notes' => $booking->notes
            ],
            'consent' => [
                'privacy' => $booking->privacy === 'yes',
                'marketing' => $booking->marketing === 'yes'
            ],
            'attribution' => [
                'source' => $booking->source,
                'medium' => $booking->medium,
                'campaign' => $booking->campaign,
                'bucket' => $booking->bucket,
                'google_click_id' => $booking->gclid,
                'facebook_click_id' => $booking->fbclid
            ],
            'timestamps' => [
                'created' => $booking->created_date,
                'submitted' => $booking->post_date
            ]
        ];
    }
    
    echo wp_json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Automatic status management - run daily to update completed bookings
 */
add_action('init', 'rbf_schedule_status_updates');
function rbf_schedule_status_updates() {
    if (!wp_next_scheduled('rbf_update_booking_statuses')) {
        // Schedule daily at 6:00 AM to update past bookings to completed
        wp_schedule_event(strtotime('today 06:00'), 'daily', 'rbf_update_booking_statuses');
    }
}

add_action('rbf_update_booking_statuses', 'rbf_auto_complete_past_bookings');
function rbf_auto_complete_past_bookings() {
    global $wpdb;
    
    // Get all confirmed bookings from yesterday or earlier
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    
    $bookings = $wpdb->get_results($wpdb->prepare(
        "SELECT p.ID FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'rbf_data'
         INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = 'rbf_booking_status'
         WHERE p.post_type = 'rbf_booking' AND p.post_status = 'publish'
         AND pm_date.meta_value <= %s 
         AND pm_status.meta_value = 'confirmed'",
        $yesterday
    ));
    
    foreach ($bookings as $booking) {
        rbf_update_booking_status($booking->ID, 'completed', 'Auto-completed after booking date');
    }
}

/**
 * Clear scheduled events on plugin deactivation
 */
register_deactivation_hook(RBF_PLUGIN_FILE, 'rbf_clear_automatic_status_events');
function rbf_clear_automatic_status_events() {
    wp_clear_scheduled_hook('rbf_update_booking_statuses');
}
