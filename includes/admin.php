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
        'brevo_api' => '',
        'brevo_list_it' => '',
        'brevo_list_en' => '',
        'closed_dates' => '',
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
        'show_in_menu' => 'rbf_bookings_menu',
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
    add_menu_page(rbf_translate_string('Prenotazioni'), rbf_translate_string('Prenotazioni'), 'manage_options', 'rbf_bookings_menu', null, 'dashicons-calendar-alt', 20);
    add_submenu_page('rbf_bookings_menu', rbf_translate_string('Tutte le Prenotazioni'), rbf_translate_string('Tutte le Prenotazioni'), 'manage_options', 'edit.php?post_type=rbf_booking');
    add_submenu_page('rbf_bookings_menu', rbf_translate_string('Aggiungi Prenotazione'), rbf_translate_string('Aggiungi Nuova'), 'manage_options', 'rbf_add_booking', 'rbf_add_booking_page_html');
    add_submenu_page('rbf_bookings_menu', rbf_translate_string('Vista Calendario'), rbf_translate_string('Calendario'), 'manage_options', 'rbf_calendar', 'rbf_calendar_page_html');
    add_submenu_page('rbf_bookings_menu', rbf_translate_string('Report & Analytics'), rbf_translate_string('Report & Analytics'), 'manage_options', 'rbf_reports', 'rbf_reports_page_html');
    add_submenu_page('rbf_bookings_menu', rbf_translate_string('Impostazioni'), rbf_translate_string('Impostazioni'), 'manage_options', 'rbf_settings', 'rbf_settings_page_html');
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
            $status = get_post_meta($post_id, 'rbf_booking_status', true) ?: 'pending';
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
 * Render booking action buttons
 */
function rbf_render_booking_actions($post_id) {
    $status = get_post_meta($post_id, 'rbf_booking_status', true) ?: 'pending';
    $nonce = wp_create_nonce('rbf_status_' . $post_id);
    
    $actions = [];
    
    if ($status === 'pending') {
        $actions['confirm'] = [
            'label' => rbf_translate_string('Conferma'),
            'status' => 'confirmed',
            'color' => '#10b981'
        ];
    }
    
    if (in_array($status, ['pending', 'confirmed'])) {
        $actions['complete'] = [
            'label' => rbf_translate_string('Completa'),
            'status' => 'completed', 
            'color' => '#06b6d4'
        ];
        $actions['cancel'] = [
            'label' => rbf_translate_string('Annulla'),
            'status' => 'cancelled',
            'color' => '#ef4444'
        ];
    }
    
    foreach ($actions as $action => $config) {
        echo '<a href="' . esc_url(admin_url('admin-post.php?action=rbf_update_booking_status&booking_id=' . $post_id . '&status=' . $config['status'] . '&_wpnonce=' . $nonce)) . '" ';
        echo 'style="color: ' . esc_attr($config['color']) . '; font-weight: bold; text-decoration: none; margin-right: 10px;" ';
        echo 'onclick="return confirm(\'' . esc_js(sprintf('Confermi di voler %s questa prenotazione?', strtolower($config['label']))) . '\')">';
        echo esc_html($config['label']);
        echo '</a>';
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

    $int_keys = ['capienza_pranzo','capienza_cena','capienza_aperitivo','brevo_list_it','brevo_list_en'];
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

    $float_keys = ['valore_pranzo','valore_cena','valore_aperitivo'];
    foreach ($float_keys as $key) $output[$key] = isset($input[$key]) ? floatval($input[$key]) : ($defaults[$key] ?? 0);

    $days = ['mon','tue','wed','thu','fri','sat','sun'];
    foreach ($days as $day) $output["open_{$day}"] = (isset($input["open_{$day}"]) && $input["open_{$day}"]==='yes') ? 'yes' : 'no';

    if (isset($input['closed_dates'])) $output['closed_dates'] = sanitize_textarea_field($input['closed_dates']);

    return $output;
}

/**
 * Enqueue admin styles
 */
add_action('admin_enqueue_scripts','rbf_enqueue_admin_styles');
function rbf_enqueue_admin_styles($hook) {
    if ($hook !== 'rbf_bookings_menu_page_rbf_settings' &&
        $hook !== 'rbf_bookings_menu_page_rbf_calendar' &&
        $hook !== 'rbf_bookings_menu_page_rbf_add_booking' &&
        $hook !== 'rbf_bookings_menu_page_rbf_reports' &&
        strpos($hook,'edit.php?post_type=rbf_booking') === false) return;

    wp_enqueue_style('rbf-admin-css', plugin_dir_url(dirname(__FILE__)) . 'assets/css/admin.css', [], '9.3.2');
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
    wp_enqueue_script('rbf-admin-js', plugin_dir_url(dirname(__FILE__)) . 'assets/js/admin.js', ['jquery', 'fullcalendar-js'], '9.3.2', true);
    
    wp_localize_script('rbf-admin-js', 'rbfAdminData', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('rbf_calendar_nonce')
    ]);
    ?>
    <div class="rbf-admin-wrap">
        <h1><?php echo esc_html(rbf_translate_string('Vista Calendario Prenotazioni')); ?></h1>
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
        $title = $booking->post_title . ' (' . $people . ' persone)';
        $events[] = [
            'title' => $title,
            'start' => $date . 'T' . $time,
            'url' => admin_url('post.php?post=' . $booking->ID . '&action=edit')
        ];
    }

    wp_send_json_success($events);
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
}

/**
 * Handle booking status updates
 */
add_action('admin_post_rbf_update_booking_status', 'rbf_handle_status_update');
function rbf_handle_status_update() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    
    $booking_id = intval($_GET['booking_id'] ?? 0);
    $new_status = sanitize_text_field($_GET['status'] ?? '');
    $nonce = $_GET['_wpnonce'] ?? '';
    
    if (!$booking_id || !$new_status || !wp_verify_nonce($nonce, 'rbf_status_' . $booking_id)) {
        wp_die(__('Invalid request'));
    }
    
    $statuses = array_keys(rbf_get_booking_statuses());
    if (!in_array($new_status, $statuses)) {
        wp_die(__('Invalid status'));
    }
    
    // Update status with history tracking
    rbf_update_booking_status($booking_id, $new_status, 'Status updated via admin panel');
    
    // Redirect back to bookings list
    wp_safe_redirect(admin_url('edit.php?post_type=rbf_booking&status_updated=1'));
    exit;
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
            <?php submit_button(esc_html(rbf_translate_string('Aggiungi Prenotazione'))); ?>
        </form>
    </div>
    <?php

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
        $status = $booking->status ?: 'pending';
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
}