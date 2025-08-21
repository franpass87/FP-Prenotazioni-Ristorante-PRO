/**
 * Plugin Name: Prenotazioni Ristorante Completo (Flatpickr, lingua dinamica)
 * Description: Prenotazioni con calendario Flatpickr IT/EN, last-minute, capienza per servizio, notifiche email (con CC), Brevo sempre e GA4/Meta (bucket standard).
 * Version:     9.3.2
 * Author:      Francesco Passeri
 * Text Domain: rbf
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) exit;

/* -------------------------------------------------------------------------
   0) Utils & Traduzioni
------------------------------------------------------------------------- */

// Timezone WP compat
if (!function_exists('rbf_wp_timezone')) {
    function rbf_wp_timezone() {
        if (function_exists('wp_timezone')) return wp_timezone();
        $tz_string = get_option('timezone_string');
        if ($tz_string) return new DateTimeZone($tz_string);
        $offset = (float) get_option('gmt_offset', 0);
        $hours = (int) $offset;
        $minutes = abs($offset - $hours) * 60;
        $sign = $offset < 0 ? '-' : '+';
        return new DateTimeZone(sprintf('%s%02d:%02d', $sign, abs($hours), $minutes));
    }
}

// Lingua corrente limitata a it/en (supporto Polylang/WPML; fallback en)
function rbf_current_lang() {
    if (function_exists('pll_current_language')) {
        $slug = pll_current_language('slug');
        return in_array($slug, ['it','en'], true) ? $slug : 'en';
    }
    if (defined('ICL_LANGUAGE_CODE')) {
        $slug = ICL_LANGUAGE_CODE;
        return in_array($slug, ['it','en'], true) ? $slug : 'en';
    }
    $slug = substr(get_locale(), 0, 2);
    return in_array($slug, ['it','en'], true) ? $slug : 'en';
}

function rbf_translate_string($text) {
    $locale = rbf_current_lang();
    if ($locale !== 'en') return $text;

    static $translations = [
        // Backend UI
        'Prenotazioni' => 'Bookings',
        'Prenotazione' => 'Booking',
        'Aggiungi Nuova' => 'Add New',
        'Aggiungi Nuova Prenotazione' => 'Add New Booking',
        'Modifica Prenotazione' => 'Edit Booking',
        'Nuova Prenotazione' => 'New Booking',
        'Visualizza Prenotazione' => 'View Booking',
        'Cerca Prenotazioni' => 'Search Bookings',
        'Nessuna Prenotazione trovata' => 'No bookings found',
        'Nessuna Prenotazione trovata nel cestino' => 'No bookings found in Trash',
        'Impostazioni' => 'Settings',
        'Impostazioni Prenotazioni Ristorante' => 'Restaurant Booking Settings',
        'Capienza e Orari' => 'Capacity and Timetable',
        'Capienza Pranzo' => 'Lunch Capacity',
        'Orari Pranzo (inclusa Domenica)' => 'Lunch Hours (including Sunday)',
        'Capienza Cena' => 'Dinner Capacity',
        'Orari Cena' => 'Dinner Hours',
        'Capienza Aperitivo' => 'Aperitif Capacity',
        'Orari Aperitivo' => 'Aperitif Hours',
        'Giorni aperti' => 'Opening Days',
        'Lunedì'=>'Monday','Martedì'=>'Tuesday','Mercoledì'=>'Wednesday','Giovedì'=>'Thursday','Venerdì'=>'Friday','Sabato'=>'Saturday','Domenica'=>'Sunday',
        'Chiusure Straordinarie' => 'Extraordinary Closures',
        'Date Chiuse (una per riga, formato Y-m-d o Y-m-d - Y-m-d)' => 'Closed Dates (one per line, format Y-m-d or Y-m-d - Y-m-d)',
        'Valore Economico Pasti (per Tracking)' => 'Meal Economic Value (for Tracking)',
        'Valore medio Pranzo (€)' => 'Average Lunch Value (€)',
        'Valore medio Cena (€)' => 'Average Dinner Value (€)',
        'Valore medio Aperitivo (€)' => 'Average Aperitif Value (€)',
        'Integrazioni e Marketing' => 'Integrations & Marketing',
        'Email per Notifiche Ristorante' => 'Restaurant Notification Email',
        'ID misurazione GA4' => 'GA4 Measurement ID',
        'ID Meta Pixel' => 'Meta Pixel ID',
        'Impostazioni Brevo' => 'Brevo Settings',
        'API Key Brevo' => 'Brevo API Key',
        'ID Lista Brevo (IT)' => 'Brevo List ID (IT)',
        'ID Lista Brevo (EN)' => 'Brevo List ID (EN)',
        'Vista Calendario Prenotazioni' => 'Bookings Calendar View',
        'Calendario' => 'Calendar',
        'Aggiungi Prenotazione' => 'Add Booking',
        'Pasto' => 'Meal',
        'Lingua' => 'Language',
        'Privacy' => 'Privacy',
        'Accettata' => 'Accepted',
        'Marketing' => 'Marketing',
        'Accettato' => 'Accepted',
        'Tutte le Prenotazioni' => 'All Bookings',

        // Frontend
        'Scegli il pasto' => 'Choose your meal',
        'Data' => 'Date',
        'Orario' => 'Time',
        'Persone' => 'Guests',
        'Nome' => 'Name',
        'Cognome' => 'Surname',
        'Email' => 'Email',
        'Telefono' => 'Phone',
        'Allergie/Note' => 'Allergies/Notes',
        'Prenota' => 'Book Now',
        'Prima scegli la data' => 'Please select a date first',
        'Grazie! La tua prenotazione è stata inviata con successo.' => 'Thank you! Your booking has been sent successfully.',
        'Tutti i campi sono obbligatori.' => 'All fields are required.',
        'Errore di sicurezza.' => 'Security error.',
        'Indirizzo email non valido.' => 'Invalid email address.',
        'Orario non valido.' => 'Invalid time.',
        'Spiacenti, non ci sono abbastanza posti. Rimasti: %d' => 'Sorry, there are not enough seats available. Remaining: %d',
        'Errore nel salvataggio.' => 'Error while saving.',
        'Caricamento...' => 'Loading...',
        'Scegli un orario...' => 'Choose a time...',
        'Nessun orario disponibile' => 'No time available',
        'Il numero di telefono inserito non è valido.' => 'The phone number entered is not valid.',
        'Di Domenica il servizio è Brunch con menù alla carta.' => 'On Sundays, we serve our à la carte Brunch menu.',
        'Acconsento al trattamento dei dati secondo l’<a href="%s" target="_blank">Informativa sulla Privacy</a>' => 'I consent to the processing of my data in accordance with the <a href="%s" target="_blank">Privacy Policy</a>',
        'Acconsento a ricevere comunicazioni promozionali via email e/o messaggi riguardanti eventi, offerte o novità.' => 'I agree to receive promotional emails and/or messages about events, offers, or news.',
        'Devi accettare la Privacy Policy per procedere.' => 'You must accept the Privacy Policy to proceed.',
        'Pranzo' => 'Lunch',
        'Aperitivo' => 'Aperitif',
        'Cena' => 'Dinner',
    ];
    return $translations[$text] ?? $text;
}

/* -------------------------------------------------------------------------
   1) Setup CPT, Menu e Impostazioni
------------------------------------------------------------------------- */

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

add_action('admin_menu', 'rbf_create_bookings_menu');
function rbf_create_bookings_menu() {
    add_menu_page(rbf_translate_string('Prenotazioni'), rbf_translate_string('Prenotazioni'), 'manage_options', 'rbf_bookings_menu', null, 'dashicons-calendar-alt', 20);
    add_submenu_page('rbf_bookings_menu', rbf_translate_string('Tutte le Prenotazioni'), rbf_translate_string('Tutte le Prenotazioni'), 'manage_options', 'edit.php?post_type=rbf_booking');
    add_submenu_page('rbf_bookings_menu', rbf_translate_string('Aggiungi Prenotazione'), rbf_translate_string('Aggiungi Nuova'), 'manage_options', 'rbf_add_booking', 'rbf_add_booking_page_html');
    add_submenu_page('rbf_bookings_menu', rbf_translate_string('Vista Calendario'), rbf_translate_string('Calendario'), 'manage_options', 'rbf_calendar', 'rbf_calendar_page_html');
    add_submenu_page('rbf_bookings_menu', rbf_translate_string('Impostazioni'), rbf_translate_string('Impostazioni'), 'manage_options', 'rbf_settings', 'rbf_settings_page_html');
}

add_action('admin_init', 'rbf_register_settings');
function rbf_register_settings() {
    register_setting('rbf_opts_group', 'rbf_settings', [
        'sanitize_callback' => 'rbf_sanitize_settings_callback',
        'default' => rbf_get_default_settings(),
    ]);
}

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

/* -------------------------------------------------------------------------
   1.b) Admin styles (ritocco estetico)
------------------------------------------------------------------------- */
add_action('admin_enqueue_scripts','rbf_enqueue_admin_styles');
function rbf_enqueue_admin_styles($hook) {
    if ($hook !== 'rbf_bookings_menu_page_rbf_settings' &&
        $hook !== 'rbf_bookings_menu_page_rbf_calendar' &&
        $hook !== 'rbf_bookings_menu_page_rbf_add_booking' &&
        strpos($hook,'edit.php?post_type=rbf_booking') === false) return;

    $css = <<<CSS
.rbf-admin-wrap{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f7f7f9;padding:24px;border-radius:10px;box-shadow:0 1px 2px rgba(0,0,0,.06);max-width:980px;margin:24px auto}
.rbf-admin-wrap h1{color:#1d2327;font-size:22px;margin:0 0 18px;border-bottom:1px solid #e6e7eb;padding-bottom:10px}
.rbf-admin-wrap .form-table{background:#fff;border-radius:10px;padding:18px 20px;box-shadow:0 1px 2px rgba(0,0,0,.05)}
.rbf-admin-wrap .form-table th{font-weight:600;color:#1d2327;padding:14px 10px;width:260px}
.rbf-admin-wrap .form-table td{padding:14px 10px}
.rbf-admin-wrap .form-table input[type="text"],
.rbf-admin-wrap .form-table input[type="email"],
.rbf-admin-wrap .form-table input[type="number"],
.rbf-admin-wrap .form-table input[type="password"],
.rbf-admin-wrap .form-table textarea,
.rbf-admin-wrap .form-table select{width:100%;max-width:420px;padding:8px 10px;border:1px solid #dcdcde;border-radius:6px;font-size:14px;background:#fcfcfd}
.rbf-admin-wrap .form-table textarea{min-height:100px}
.rbf-admin-wrap .submit{text-align:right;margin-top:18px}
.rbf-admin-wrap .button-primary{background:#111827;border-color:#111827;color:#fff;padding:10px 18px;font-size:14px;border-radius:8px}
.rbf-admin-wrap .button-primary:hover{background:#0b1220}
.notice{border-radius:8px}
#rbf-calendar{background:#fff;padding:16px;border-radius:10px;box-shadow:0 1px 2px rgba(0,0,0,.05);max-width:980px;margin:16px auto}
CSS;
    wp_add_inline_style('admin-bar', $css);
}

/* -------------------------------------------------------------------------
   1.c) Settings page
------------------------------------------------------------------------- */
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

/* -------------------------------------------------------------------------
   2) Frontend: Assets, Shortcode, JS, AJAX (Flatpickr)
------------------------------------------------------------------------- */
add_action('wp_enqueue_scripts', 'rbf_enqueue_frontend_assets');
function rbf_enqueue_frontend_assets() {
    global $post;
    if (!is_singular() || !$post || !has_shortcode($post->post_content, 'ristorante_booking_form')) return;

    $plugin_version = '9.3.2';
    $options = get_option('rbf_settings', rbf_get_default_settings());
    $locale = rbf_current_lang(); // 'it' o 'en'

    // Flatpickr
    wp_enqueue_style('rbf-flatpickr-css', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', [], '4.6.9');
    wp_enqueue_script('rbf-flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr', [], '4.6.9', true);
    $deps = ['jquery','rbf-flatpickr'];

    // Carica SOLO la locale italiana (EN è default)
    if ($locale === 'it') {
        wp_enqueue_script('rbf-flatpickr-locale-it', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/it.js', ['rbf-flatpickr'], '4.6.9', true);
        $deps[] = 'rbf-flatpickr-locale-it';
    }

    // intl-tel-input
    wp_enqueue_style('rbf-intl-tel-input-css','https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/css/intlTelInput.css',[], '17.0.13');
    wp_enqueue_script('rbf-intl-tel-input','https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/js/intlTelInput.min.js',[], '17.0.13', true);

    wp_register_script('rbf-main-script','', $deps, $plugin_version, true);
    wp_enqueue_script('rbf-main-script');

    // Giorni chiusi
    $closed_days_map = ['sun'=>0,'mon'=>1,'tue'=>2,'wed'=>3,'thu'=>4,'fri'=>5,'sat'=>6];
    $closed_days = [];
    foreach ($closed_days_map as $key=>$day_index) {
        if (($options["open_{$key}"] ?? 'yes') !== 'yes') $closed_days[] = $day_index;
    }
    $closed_specific = rbf_get_closed_specific($options);

    wp_localize_script('rbf-main-script', 'rbfData', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('rbf_ajax_nonce'),
        'locale' => $locale, // it/en
        'closedDays' => $closed_days,
        'closedSingles' => $closed_specific['singles'],
        'closedRanges' => $closed_specific['ranges'],
        'utilsScript' => 'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/js/utils.js',
        'labels' => [
            'loading' => rbf_translate_string('Caricamento...'),
            'chooseTime' => rbf_translate_string('Scegli un orario...'),
            'noTime' => rbf_translate_string('Nessun orario disponibile'),
            'invalidPhone' => rbf_translate_string('Il numero di telefono inserito non è valido.'),
            'sundayBrunchNotice' => rbf_translate_string('Di Domenica il servizio è Brunch con menù alla carta.'),
            'privacyRequired' => rbf_translate_string('Devi accettare la Privacy Policy per procedere.'),
        ],
    ]);

    // Stili frontend (sintetici)
    $css = <<<CSS
.rbf-form-container{position:relative}
#rbf-message-anchor{position:absolute;top:-20px}
.rbf-form{max-width:520px;margin:2em auto;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,'Helvetica Neue',sans-serif}
.rbf-step{margin-bottom:1.5em}
.rbf-step>label{display:block;font-weight:600;margin-bottom:.8em}
#rbf-meal-notice{font-size:.9em;font-style:italic;color:#555;margin-top:12px;padding:8px;background:#f8f9fa;border-left:3px solid #6c757d}
.rbf-form input[type='text'],.rbf-form input[type='email'],.rbf-form input[type='tel'],.rbf-form select,.rbf-form textarea{box-sizing:border-box;width:100%;padding:10px;border:1px solid #ccc;border-radius:6px;font-size:16px;background:#fff}
.rbf-radio-group{display:flex;flex-wrap:wrap;gap:10px}
.rbf-radio-group input[type='radio']{opacity:0;position:fixed;width:0}
.rbf-radio-group label{display:inline-block;padding:10px 18px;border:1px solid #ccc;border-radius:10px;font-weight:500;text-align:center;cursor:pointer;transition:.2s;background:#f8f9fa;color:#333}
.rbf-radio-group label:hover{background:#eef1f4;border-color:#bfc7cf}
.rbf-radio-group input[type='radio']:checked+label{background:#000;color:#fff;border-color:#000;box-shadow:0 2px 5px rgba(0,0,0,.1)}
.rbf-people-selector{display:flex;align-items:center}
.rbf-people-selector button{width:44px;height:44px;font-size:24px;font-weight:bold;border:1px solid #ccc;background:#f0f0f0;cursor:pointer;line-height:1;border-radius:6px}
.rbf-people-selector button:disabled{background:#f8f8f8;cursor:not-allowed;color:#ccc}
.rbf-people-selector input{height:44px;width:60px;text-align:center;font-weight:700;border:1px solid #ccc;border-left:none;border-right:none;font-size:18px}
#rbf-submit{width:100%;padding:15px;margin-top:18px;font-size:18px;font-weight:700;color:#fff;background:#000;border:none;border-radius:8px;cursor:pointer}
#rbf-submit:disabled{background:#bdc3c7;cursor:not-allowed}
.rbf-success-message,.rbf-error-message{padding:14px;margin-bottom:18px;border-radius:6px;border:2px solid}
.rbf-success-message{color:#155724;background:#d4edda;border-color:#c3e6cb}
.rbf-error-message{color:#721c24;background:#f8d7da;border-color:#f5c6cb}
.iti{width:100%}
.rbf-checkbox-group{margin-top:12px}
.rbf-checkbox-group label{display:block;margin-bottom:10px;font-size:14px}
.rbf-checkbox-group a{color:#000;text-decoration:underline}
CSS;
    wp_add_inline_style('rbf-flatpickr-css', $css);

    // Script principale (Flatpickr + UTM capture)
    $js = <<<'JS'
jQuery(function($){
  'use strict';
  if (typeof rbfData === 'undefined' || typeof flatpickr === 'undefined' || typeof intlTelInput === 'undefined') return;

  const form = $('#rbf-form');
  if (!form.length) return;

  const el = {
    mealRadios: form.find('input[name="rbf_meal"]'),
    mealNotice: form.find('#rbf-meal-notice'),
    dateStep: form.find('#step-date'),
    dateInput: form.find('#rbf-date'),
    timeStep: form.find('#step-time'),
    timeSelect: form.find('#rbf-time'),
    peopleStep: form.find('#step-people'),
    peopleInput: form.find('#rbf-people'),
    peopleMinus: form.find('#rbf-people-minus'),
    peoplePlus: form.find('#rbf-people-plus'),
    detailsStep: form.find('#step-details'),
    detailsInputs: form.find('#step-details input:not([type=checkbox]), #step-details textarea'),
    telInput: form.find('#rbf-tel'),
    privacyCheckbox: form.find('#rbf-privacy'),
    marketingCheckbox: form.find('#rbf-marketing'),
    submitButton: form.find('#rbf-submit')
  };

  let fp = null;
  let iti = null;

  function initializeTelInput(){
    if (el.telInput.is(':visible') && !iti) {
      iti = intlTelInput(el.telInput[0], {
        utilsScript: rbfData.utilsScript,
        initialCountry: 'it',
        preferredCountries: ['it','gb','us','de','fr','es'],
        separateDialCode: true,
        nationalMode: false
      });
    }
  }

  function resetSteps(fromStep){
    if (fromStep <= 1) {
      el.dateStep.hide();
      if (fp) { fp.clear(); fp.destroy(); fp = null; }
    }
    if (fromStep <= 2) el.timeStep.hide();
    if (fromStep <= 3) el.peopleStep.hide();
    if (fromStep <= 4) {
      el.detailsStep.hide();
      el.detailsInputs.prop('disabled', true);
      el.privacyCheckbox.prop('disabled', true);
      el.marketingCheckbox.prop('disabled', true);
      el.submitButton.hide().prop('disabled', true);
    }
  }

  el.mealRadios.on('change', function(){
    resetSteps(1);
    el.mealNotice.hide();
    el.dateStep.show();

    fp = flatpickr(el.dateInput[0], {
      altInput: true,
      altFormat: 'd-m-Y',
      dateFormat: 'Y-m-d',
      minDate: 'today',
      locale: (rbfData.locale === 'it') ? 'it' : 'default',
      disable: [function(date){
        const day = date.getDay();
        if (rbfData.closedDays.includes(day)) return true;
        const dateStr = date.toISOString().split('T')[0];
        if (rbfData.closedSingles.includes(dateStr)) return true;
        for (let range of rbfData.closedRanges) {
          if (dateStr >= range.from && dateStr <= range.to) return true;
        }
        return false;
      }],
      onChange: onDateChange
    });
  });

  function onDateChange(selectedDates){
    if (!selectedDates.length) { el.mealNotice.hide(); return; }
    resetSteps(2);
    const date = selectedDates[0];
    const dow = date.getDay();
    const selectedMeal = el.mealRadios.filter(':checked').val();
    if (selectedMeal === 'pranzo' && dow === 0) {
      el.mealNotice.text(rbfData.labels.sundayBrunchNotice).show();
    } else {
      el.mealNotice.hide();
    }
    const dateString = date.toISOString().split('T')[0];
    el.timeStep.show();
    el.timeSelect.html(`<option value="">${rbfData.labels.loading}</option>`).prop('disabled', true);

    $.post(rbfData.ajaxUrl, {
      action: 'rbf_get_availability',
      _ajax_nonce: rbfData.nonce,
      date: dateString,
      meal: selectedMeal
    }, function(response){
      el.timeSelect.html('');
      if (response.success && response.data.length > 0) {
        el.timeSelect.append(new Option(rbfData.labels.chooseTime,''));
        response.data.forEach(item=>{
          const opt = new Option(item.time, `${item.slot}|${item.time}`);
          el.timeSelect.append(opt);
        });
        el.timeSelect.prop('disabled', false);
      } else {
        el.timeSelect.append(new Option(rbfData.labels.noTime,''));
      }
    });
  }

  el.timeSelect.on('change', function(){
    resetSteps(3);
    if (this.value) {
      el.peopleStep.show();
      const maxPeople = 30; // cap generico
      el.peopleInput.val(1).attr('max', maxPeople).trigger('input');
    }
  });

  function updatePeopleButtons(){
    const val = parseInt(el.peopleInput.val());
    const max = parseInt(el.peopleInput.attr('max'));
    el.peopleMinus.prop('disabled', val <= 1);
    el.peoplePlus.prop('disabled', val >= max);
  }

  el.peoplePlus.on('click', function(){
    let val = parseInt(el.peopleInput.val());
    let max = parseInt(el.peopleInput.attr('max'));
    if (val < max) el.peopleInput.val(val+1).trigger('input');
  });
  el.peopleMinus.on('click', function(){
    let val = parseInt(el.peopleInput.val());
    if (val > 1) el.peopleInput.val(val-1).trigger('input');
  });
  el.peopleInput.on('input', function(){
    updatePeopleButtons();
    resetSteps(4);
    if (parseInt($(this).val()) > 0) {
      el.detailsStep.show();
      el.detailsInputs.prop('disabled', false);
      el.privacyCheckbox.prop('disabled', false);
      el.marketingCheckbox.prop('disabled', false);
      el.submitButton.show().prop('disabled', true);
      initializeTelInput();
    }
  });

  el.privacyCheckbox.on('change', function(){
    el.submitButton.prop('disabled', !this.checked);
  });

  form.on('submit', function(e){
    el.submitButton.prop('disabled', true);
    if (!el.privacyCheckbox.is(':checked')) {
      e.preventDefault();
      alert(rbfData.labels.privacyRequired);
      el.submitButton.prop('disabled', false);
      return;
    }
    if (iti && !iti.isValidNumber()) {
      e.preventDefault();
      alert(rbfData.labels.invalidPhone);
      el.submitButton.prop('disabled', false);
      return;
    }
    if (iti) el.telInput.val(iti.getNumber());
  });

  // --- UTM & clid capture ---
  (function(){
    const qs = new URLSearchParams(window.location.search);
    const get = k => qs.get(k) || '';
    const setVal = (id,val)=>{ var el=document.getElementById(id); if(el) el.value = val; };

    setVal('rbf_utm_source',   get('utm_source'));
    setVal('rbf_utm_medium',   get('utm_medium'));
    setVal('rbf_utm_campaign', get('utm_campaign'));

    setVal('rbf_gclid',  get('gclid'));
    setVal('rbf_fbclid', get('fbclid'));

    if (document.getElementById('rbf_referrer') && !document.getElementById('rbf_referrer').value) {
      document.getElementById('rbf_referrer').value = document.referrer || '';
    }
  })();

});
JS;
    wp_add_inline_script('rbf-main-script', $js);
}

add_shortcode('ristorante_booking_form', 'rbf_render_booking_form');
function rbf_render_booking_form() {
    ob_start(); ?>
    <div class="rbf-form-container">
        <div id="rbf-message-anchor"></div>
        <?php if (isset($_GET['rbf_success'])) : ?>
            <div class="rbf-success-message"><?php echo esc_html(rbf_translate_string('Grazie! La tua prenotazione è stata inviata con successo.')); ?></div>
        <?php else : ?>
            <?php if (isset($_GET['rbf_error'])) : ?>
                <div class="rbf-error-message"><?php echo esc_html(urldecode($_GET['rbf_error'])); ?></div>
            <?php endif; ?>
            <form id="rbf-form" class="rbf-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="rbf_submit_booking">
                <?php wp_nonce_field('rbf_booking','rbf_nonce'); ?>
                <div id="step-meal" class="rbf-step">
                    <label><?php echo esc_html(rbf_translate_string('Scegli il pasto')); ?></label>
                    <div class="rbf-radio-group">
                        <input type="radio" name="rbf_meal" value="pranzo" id="rbf_meal_pranzo" required>
                        <label for="rbf_meal_pranzo"><?php echo esc_html(rbf_translate_string('Pranzo')); ?></label>
                        <input type="radio" name="rbf_meal" value="aperitivo" id="rbf_meal_aperitivo" required>
                        <label for="rbf_meal_aperitivo"><?php echo esc_html(rbf_translate_string('Aperitivo')); ?></label>
                        <input type="radio" name="rbf_meal" value="cena" id="rbf_meal_cena" required>
                        <label for="rbf_meal_cena"><?php echo esc_html(rbf_translate_string('Cena')); ?></label>
                    </div>
                    <p id="rbf-meal-notice" style="display:none;"></p>
                </div>

                <div id="step-date" class="rbf-step" style="display:none;">
                    <label for="rbf-date"><?php echo esc_html(rbf_translate_string('Data')); ?></label>
                    <input id="rbf-date" name="rbf_data" readonly="readonly" required>
                </div>

                <div id="step-time" class="rbf-step" style="display:none;">
                    <label for="rbf-time"><?php echo esc_html(rbf_translate_string('Orario')); ?></label>
                    <select id="rbf-time" name="rbf_orario" required disabled>
                        <option value=""><?php echo esc_html(rbf_translate_string('Prima scegli la data')); ?></option>
                    </select>
                </div>

                <div id="step-people" class="rbf-step" style="display:none;">
                    <label><?php echo esc_html(rbf_translate_string('Persone')); ?></label>
                    <div class="rbf-people-selector">
                        <button type="button" id="rbf-people-minus" disabled>-</button>
                        <input type="number" id="rbf-people" name="rbf_persone" value="1" min="1" readonly="readonly" required>
                        <button type="button" id="rbf-people-plus">+</button>
                    </div>
                </div>

                <div id="step-details" class="rbf-step" style="display:none;">
                    <label for="rbf-name"><?php echo esc_html(rbf_translate_string('Nome')); ?></label>
                    <input type="text" id="rbf-name" name="rbf_nome" required disabled>
                    <label for="rbf-surname"><?php echo esc_html(rbf_translate_string('Cognome')); ?></label>
                    <input type="text" id="rbf-surname" name="rbf_cognome" required disabled>
                    <label for="rbf-email"><?php echo esc_html(rbf_translate_string('Email')); ?></label>
                    <input type="email" id="rbf-email" name="rbf_email" required disabled>
                    <label for="rbf-tel"><?php echo esc_html(rbf_translate_string('Telefono')); ?></label>
                    <input type="tel" id="rbf-tel" name="rbf_tel" required disabled>
                    <label for="rbf-notes"><?php echo esc_html(rbf_translate_string('Allergie/Note')); ?></label>
                    <textarea id="rbf-notes" name="rbf_allergie" disabled></textarea>

                    <div class="rbf-checkbox-group">
                        <label>
                            <input type="checkbox" id="rbf-privacy" name="rbf_privacy" value="yes" required disabled>
                            <?php echo sprintf(
                                rbf_translate_string('Acconsento al trattamento dei dati secondo l’<a href="%s" target="_blank">Informativa sulla Privacy</a>'),
                                'https://www.villadianella.it/privacy-statement-eu'
                            ); ?>
                        </label>
                        <label>
                            <input type="checkbox" id="rbf-marketing" name="rbf_marketing" value="yes" disabled>
                            <?php echo rbf_translate_string('Acconsento a ricevere comunicazioni promozionali via email e/o messaggi riguardanti eventi, offerte o novità.'); ?>
                        </label>
                    </div>
                </div>

                <!-- Tracciamento sorgente -->
                <input type="hidden" name="rbf_utm_source" id="rbf_utm_source" value="">
                <input type="hidden" name="rbf_utm_medium" id="rbf_utm_medium" value="">
                <input type="hidden" name="rbf_utm_campaign" id="rbf_utm_campaign" value="">
                <input type="hidden" name="rbf_gclid" id="rbf_gclid" value="">
                <input type="hidden" name="rbf_fbclid" id="rbf_fbclid" value="">
                <input type="hidden" name="rbf_referrer" id="rbf_referrer" value="">

                <input type="hidden" name="rbf_lang" value="<?php echo esc_attr(rbf_current_lang()); ?>">
                <button id="rbf-submit" type="submit" disabled style="display:none;"><?php echo esc_html(rbf_translate_string('Prenota')); ?></button>
            </form>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/* -------------------------------------------------------------------------
   2.b) Helper: classificazione sorgente canale
------------------------------------------------------------------------- */
function rbf_detect_source($data = []) {
    $utm_source   = strtolower(trim($data['utm_source']   ?? ''));
    $utm_medium   = strtolower(trim($data['utm_medium']   ?? ''));
    $utm_campaign = trim($data['utm_campaign'] ?? '');
    $gclid        = trim($data['gclid']        ?? '');
    $fbclid       = trim($data['fbclid']       ?? '');
    $referrer     = strtolower(trim($data['referrer']     ?? ''));

    // Google Ads (paid)
    if ($gclid || ($utm_source === 'google' && in_array($utm_medium, ['cpc','paid','ppc','sem'], true))) {
        return ['bucket'=>'gads','source'=>'google','medium'=>$utm_medium ?: 'cpc','campaign'=>$utm_campaign];
    }

    // Meta Ads (paid)
    if ($fbclid || (in_array($utm_source, ['facebook','meta','instagram'], true) && in_array($utm_medium, ['cpc','paid','ppc','ads'], true))) {
        return ['bucket'=>'fbads','source'=>$utm_source ?: 'facebook','medium'=>$utm_medium ?: 'paid','campaign'=>$utm_campaign];
    }

    // Facebook/Instagram organico
    if ((strpos($referrer, 'facebook.') !== false || strpos($referrer, 'instagram.') !== false) ||
        (in_array($utm_source, ['facebook','meta','instagram'], true) && ($utm_medium === '' || in_array($utm_medium, ['social','organic'], true)))) {
        return ['bucket'=>'fborg','source'=>$utm_source ?: 'facebook','medium'=>$utm_medium ?: 'social','campaign'=>$utm_campaign];
    }

    // Direct
    if ($referrer === '' && $utm_source === '' && $utm_medium === '' && $utm_campaign === '' && !$gclid && !$fbclid) {
        return ['bucket'=>'direct','source'=>'direct','medium'=>'none','campaign'=>''];
    }

    // Altre sorgenti (referral/organic)
    if ($utm_source || $utm_medium) {
        return ['bucket'=>'other','source'=>$utm_source ?: 'unknown','medium'=>$utm_medium ?: 'organic','campaign'=>$utm_campaign];
    }
    if ($referrer) {
        $host = parse_url($referrer, PHP_URL_HOST);
        return ['bucket'=>'other','source'=>$host ?: 'referral','medium'=>'referral','campaign'=>''];
    }

    return ['bucket'=>'direct','source'=>'direct','medium'=>'none','campaign'=>''];
}

/* -------------------------------------------------------------------------
   2.c) Submit booking + GA4/Meta/Brevo
------------------------------------------------------------------------- */
add_action('admin_post_rbf_submit_booking', 'rbf_handle_booking_submission');
add_action('admin_post_nopriv_rbf_submit_booking', 'rbf_handle_booking_submission');
function rbf_handle_booking_submission() {
    $redirect_url = wp_get_referer() ? strtok(wp_get_referer(), '?') : home_url();
    $anchor = '#rbf-message-anchor';

    if (!isset($_POST['rbf_nonce']) || !wp_verify_nonce($_POST['rbf_nonce'], 'rbf_booking')) {
        wp_safe_redirect(add_query_arg('rbf_error', urlencode(rbf_translate_string('Errore di sicurezza.')), $redirect_url . $anchor)); exit;
    }

    $required = ['rbf_meal','rbf_data','rbf_orario','rbf_persone','rbf_nome','rbf_cognome','rbf_email','rbf_tel','rbf_privacy'];
    foreach ($required as $f) {
        if (empty($_POST[$f])) {
            wp_safe_redirect(add_query_arg('rbf_error', urlencode(rbf_translate_string('Tutti i campi sono obbligatori, inclusa l\'accettazione della privacy policy.')), $redirect_url . $anchor)); exit;
        }
    }

    $meal = sanitize_text_field($_POST['rbf_meal']);
    $date = sanitize_text_field($_POST['rbf_data']);
    $time_data = sanitize_text_field($_POST['rbf_orario']);
    if (strpos($time_data, '|') === false) {
        wp_safe_redirect(add_query_arg('rbf_error', urlencode(rbf_translate_string('Orario non valido.')), $redirect_url . $anchor)); exit;
    }
    list($slot, $time) = explode('|', $time_data);
    $people = intval($_POST['rbf_persone']);
    $first_name = sanitize_text_field($_POST['rbf_nome']);
    $last_name = sanitize_text_field($_POST['rbf_cognome']);
    $email = sanitize_email($_POST['rbf_email']);
    $tel = sanitize_text_field($_POST['rbf_tel']);
    $notes = sanitize_textarea_field($_POST['rbf_allergie'] ?? '');
    $lang = sanitize_text_field($_POST['rbf_lang'] ?? 'it');
    $privacy = (isset($_POST['rbf_privacy']) && $_POST['rbf_privacy']==='yes') ? 'yes' : 'no';
    $marketing = (isset($_POST['rbf_marketing']) && $_POST['rbf_marketing']==='yes') ? 'yes' : 'no';

    // --- Sorgente & UTM dal form
    $utm_source   = sanitize_text_field($_POST['rbf_utm_source']   ?? '');
    $utm_medium   = sanitize_text_field($_POST['rbf_utm_medium']   ?? '');
    $utm_campaign = sanitize_text_field($_POST['rbf_utm_campaign'] ?? '');
    $gclid        = sanitize_text_field($_POST['rbf_gclid']        ?? '');
    $fbclid       = sanitize_text_field($_POST['rbf_fbclid']       ?? '');
    $referrer     = sanitize_text_field($_POST['rbf_referrer']     ?? '');

    $src = rbf_detect_source([
      'utm_source' => $utm_source,
      'utm_medium' => $utm_medium,
      'utm_campaign' => $utm_campaign,
      'gclid' => $gclid,
      'fbclid' => $fbclid,
      'referrer' => $referrer
    ]);

    if (!$email) {
        wp_safe_redirect(add_query_arg('rbf_error', urlencode(rbf_translate_string('Indirizzo email non valido.')), $redirect_url . $anchor)); exit;
    }

    $remaining_capacity = rbf_get_remaining_capacity($date, $slot);
    if ($remaining_capacity < $people) {
        $error_msg = sprintf(rbf_translate_string('Spiacenti, non ci sono abbastanza posti. Rimasti: %d'), $remaining_capacity);
        wp_safe_redirect(add_query_arg('rbf_error', urlencode($error_msg), $redirect_url . $anchor)); exit;
    }

    $post_id = wp_insert_post([
        'post_type' => 'rbf_booking',
        'post_title' => ucfirst($meal) . " per {$first_name} {$last_name} - {$date} {$time}",
        'post_status' => 'publish',
        'meta_input' => [
            'rbf_data' => $date,
            'rbf_orario' => $slot,
            'rbf_time' => $time,
            'rbf_persone' => $people,
            'rbf_nome' => $first_name,
            'rbf_cognome' => $last_name,
            'rbf_email' => $email,
            'rbf_tel' => $tel,
            'rbf_allergie' => $notes,
            'rbf_lang' => $lang,
            'rbf_privacy' => $privacy,
            'rbf_marketing' => $marketing,
            // sorgente
            'rbf_source_bucket' => $src['bucket'],
            'rbf_source'        => $src['source'],
            'rbf_medium'        => $src['medium'],
            'rbf_campaign'      => $src['campaign'],
            'rbf_gclid'         => $gclid,
            'rbf_fbclid'        => $fbclid,
            'rbf_referrer'      => $referrer,
        ],
    ]);

    if (is_wp_error($post_id)) {
        wp_safe_redirect(add_query_arg('rbf_error', urlencode(rbf_translate_string('Errore nel salvataggio.')), $redirect_url . $anchor)); exit;
    }

    delete_transient('rbf_avail_' . $date . '_' . $slot);
    $options = get_option('rbf_settings', rbf_get_default_settings());
    $valore_pp  = (float) ($options['valore_' . $meal] ?? 0);
    $valore_tot = $valore_pp * $people;
    $event_id   = 'rbf_' . $post_id; // usato per Pixel (browser) e CAPI (server)

    // Transient completo per tracking in footer
    set_transient('rbf_booking_data_' . $post_id, [
        'id'       => $post_id,
        'value'    => $valore_tot,
        'currency' => 'EUR',
        'meal'     => $meal,
        'people'   => $people,
        'bucket'   => $src['bucket'],
        'event_id' => $event_id
    ], 60 * 15);

    // Notifiche email (con CC fisso)
    rbf_send_admin_notification_email($first_name, $last_name, $email, $date, $time, $people, $notes, $tel, $meal);

    // Brevo: sempre (lista + evento)
    rbf_trigger_brevo_automation($first_name, $last_name, $email, $date, $time, $people, $notes, $lang, $tel, $marketing, $meal);

    // --- NO GA4 Measurement Protocol (evita doppi/attribuzione "direct")

    // Meta CAPI server-side (dedup con event_id) + bucket standard
    if (!empty($options['meta_pixel_id']) && !empty($options['meta_access_token'])) {
        $meta_url = "https://graph.facebook.com/v20.0/{$options['meta_pixel_id']}/events?access_token={$options['meta_access_token']}";
        // standardizza: tutto ciò che NON è gads/fbads => organic
        $bucket_std = ($src['bucket'] === 'gads' || $src['bucket'] === 'fbads') ? $src['bucket'] : 'organic';

        $meta_payload = [
            'data' => [[
                'action_source' => 'website',
                'event_name' => 'Purchase',
                'event_time' => time(),
                'event_id' => (string) $event_id,
                'user_data' => [
                    'client_ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'client_user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                ],
                'custom_data' => [
                    'value'    => $valore_tot,
                    'currency' => 'EUR',
                    'bucket'   => $bucket_std
                ]
            ]]
        ];
        wp_remote_post($meta_url, [
            'body' => wp_json_encode($meta_payload),
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 8
        ]);
    }

    $success_args = ['rbf_success' => '1', 'booking_id' => $post_id];
    wp_safe_redirect(add_query_arg($success_args, $redirect_url . $anchor)); exit;
}

/* -------------------------------------------------------------------------
   2.d) Disponibilità AJAX (last-minute oggi)
------------------------------------------------------------------------- */
add_action('wp_ajax_rbf_get_availability', 'rbf_ajax_get_availability_callback');
add_action('wp_ajax_nopriv_rbf_get_availability', 'rbf_ajax_get_availability_callback');
function rbf_ajax_get_availability_callback() {
    check_ajax_referer('rbf_ajax_nonce');
    if (empty($_POST['date']) || empty($_POST['meal'])) wp_send_json_error();

    $date = sanitize_text_field($_POST['date']);
    $meal = sanitize_text_field($_POST['meal']);
    $day_of_week = date('w', strtotime($date));
    $options = get_option('rbf_settings', rbf_get_default_settings());
    $day_keys = ['sun','mon','tue','wed','thu','fri','sat'];
    $day_key = $day_keys[$day_of_week];

    if (($options["open_{$day_key}"] ?? 'no') !== 'yes') { wp_send_json_success([]); return; }

    $closed_specific = rbf_get_closed_specific($options);
    if (in_array($date, $closed_specific['singles'], true)) { wp_send_json_success([]); return; }
    foreach ($closed_specific['ranges'] as $range) {
        if ($date >= $range['from'] && $date <= $range['to']) { wp_send_json_success([]); return; }
    }

    $times_csv = $options['orari_'.$meal] ?? '';
    if (empty($times_csv)) { wp_send_json_success([]); return; }
    $times = array_values(array_filter(array_map('trim', explode(',', $times_csv))));
    if (empty($times)) { wp_send_json_success([]); return; }

    $remaining_capacity = rbf_get_remaining_capacity($date, $meal);
    if ($remaining_capacity <= 0) { wp_send_json_success([]); return; }

    // LAST-MINUTE: se oggi, mostra solo orari futuri (margine 15')
    $tz = rbf_wp_timezone();
    $now = new DateTime('now', $tz);
    $today_str = $now->format('Y-m-d');
    if ($date === $today_str) {
        $now_plus = clone $now;
        $now_plus->modify('+15 minutes');
        $cut = $now_plus->format('H:i');
        $times = array_values(array_filter($times, function($t) use ($cut) { return $t > $cut; }));
    }

    $available = [];
    foreach ($times as $time) $available[] = ['slot'=>$meal, 'time'=>$time];
    wp_send_json_success($available);
}

/* -------------------------------------------------------------------------
   2.e) Capienza e chiusure
------------------------------------------------------------------------- */
function rbf_get_remaining_capacity($date, $slot) {
    $transient_key = 'rbf_avail_' . $date . '_' . $slot;
    $cached = get_transient($transient_key);
    if ($cached !== false) return (int) $cached;

    $options = get_option('rbf_settings', rbf_get_default_settings());
    $total = (int) ($options['capienza_'.$slot] ?? 0);
    if ($total === 0) return 0;

    global $wpdb;
    $spots_taken = $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(pm_people.meta_value)
         FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm_people ON p.ID = pm_people.post_id AND pm_people.meta_key = 'rbf_persone'
         INNER JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'rbf_data'
         INNER JOIN {$wpdb->postmeta} pm_slot ON p.ID = pm_slot.post_id AND pm_slot.meta_key = 'rbf_orario'
         WHERE p.post_type = 'rbf_booking' AND p.post_status = 'publish'
         AND pm_date.meta_value = %s AND pm_slot.meta_value = %s",
        $date, $slot
    ));
    $remaining = max(0, $total - (int) $spots_taken);
    set_transient($transient_key, $remaining, HOUR_IN_SECONDS);
    return $remaining;
}

function rbf_get_closed_specific($options = null) {
    if (is_null($options)) $options = get_option('rbf_settings', rbf_get_default_settings());
    $closed_dates_str = $options['closed_dates'] ?? '';
    $closed_items = array_filter(array_map('trim', explode("\n", $closed_dates_str)));
    $singles = []; $ranges = [];
    foreach ($closed_items as $item) {
        if (strpos($item, '-') !== false) {
            list($start, $end) = array_map('trim', explode('-', $item, 2));
            $start_ok = DateTime::createFromFormat('Y-m-d', $start) !== false;
            $end_ok = DateTime::createFromFormat('Y-m-d', $end) !== false;
            if ($start_ok && $end_ok) $ranges[] = ['from'=>$start, 'to'=>$end];
        } else {
            if (DateTime::createFromFormat('Y-m-d', $item) !== false) $singles[] = $item;
        }
    }
    return ['singles'=>$singles, 'ranges'=>$ranges];
}

/* -------------------------------------------------------------------------
   3) Tracking footer (GA4 + Meta client)
------------------------------------------------------------------------- */
add_action('wp_footer','rbf_add_tracking_scripts_to_footer');
function rbf_add_tracking_scripts_to_footer() {
    $options = get_option('rbf_settings', rbf_get_default_settings());
    $ga4_id = $options['ga4_id'] ?? '';
    $meta_pixel_id = $options['meta_pixel_id'] ?? '';

    if ($ga4_id) { ?>
        <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr($ga4_id); ?>"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){ dataLayer.push(arguments); }
            gtag('js', new Date());
            gtag('config', '<?php echo esc_js($ga4_id); ?>', { 'send_page_view': true });
        </script>
    <?php }

    if ($meta_pixel_id) { ?>
        <script>
            !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
            n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
            n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
            t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script',
            'https://connect.facebook.net/en_US/fbevents.js');
            fbq('init','<?php echo esc_js($meta_pixel_id); ?>');
            fbq('track','PageView');
        </script>
    <?php }

    if (isset($_GET['rbf_success'], $_GET['booking_id'])) {
        $booking_id = intval($_GET['booking_id']);
        $tracking_data = get_transient('rbf_booking_data_' . $booking_id);

        // Fallback se manca il transient: ricostruisci dai meta
        if (!$tracking_data || !is_array($tracking_data)) {
            $meal   = get_post_meta($booking_id, 'rbf_orario', true);
            $people = (int) get_post_meta($booking_id, 'rbf_persone', true);
            $bucket = get_post_meta($booking_id, 'rbf_source_bucket', true) ?: 'direct';
            $val_per = 0.0;
            if ($meal) {
                $val_per = (float) ($options['valore_' . $meal] ?? 0);
            }
            $val_tot  = $val_per * max(0,$people);
            $event_id = 'rbf_' . $booking_id;
            $tracking_data = [
              'id'       => $booking_id,
              'value'    => $val_tot,
              'currency' => 'EUR',
              'meal'     => $meal ?: '',
              'people'   => max(0,$people),
              'bucket'   => $bucket,
              'event_id' => $event_id
            ];
            set_transient('rbf_booking_data_' . $booking_id, $tracking_data, 60 * 15);
        }

        $value = (float) $tracking_data['value'];
        $currency = esc_js($tracking_data['currency']);
        $transaction_id = esc_js($tracking_data['id']);
        $meal_js = esc_js($tracking_data['meal']);
        $people_js = (int) $tracking_data['people'];
        $bucket_js = esc_js($tracking_data['bucket']);
        $event_id_js = esc_js($tracking_data['event_id']); ?>
        <script>
            (function(){
              var value = <?php echo json_encode($value); ?>;
              var currency = <?php echo json_encode($currency); ?>;
              var transaction_id = <?php echo json_encode($transaction_id); ?>;
              var meal = <?php echo json_encode($meal_js); ?>;
              var people = <?php echo json_encode($people_js); ?>;
              var bucket = <?php echo json_encode($bucket_js); ?>; // es: gads, fbads, fborg, direct, other
              var eventId = <?php echo json_encode($event_id_js); ?>;

              // standardizza: tutto ciò che NON è gads/fbads => organic
              var bucketStd = (bucket === 'gads' || bucket === 'fbads') ? bucket : 'organic';

              <?php if ($ga4_id) : ?>
              if (typeof gtag === 'function') {
                // Ecommerce standard + bucket standard (per allineo con HIC)
                gtag('event', 'purchase', {
                  transaction_id: transaction_id,
                  value: Number(value || 0),
                  currency: currency,
                  bucket: bucketStd
                });
                // Evento custom con dettaglio ristorante
                gtag('event', 'restaurant_booking', {
                  transaction_id: transaction_id,
                  value: Number(value || 0),
                  currency: currency,
                  bucket: bucketStd,          // standard (gads/fbads/organic)
                  traffic_bucket: bucket,     // dettaglio (fborg/direct/other...)
                  meal: meal,
                  people: Number(people || 0)
                });
              }
              <?php endif; ?>

              <?php if ($meta_pixel_id) : ?>
              if (typeof fbq === 'function') {
                // Dedup con CAPI: stesso eventID + bucket standard lato browser
                fbq('track', 'Purchase',
                    { value: Number(value || 0), currency: currency, bucket: bucketStd },
                    { eventID: eventId }
                );
              }
              <?php endif; ?>
            })();
        </script>
        <?php
        delete_transient('rbf_booking_data_' . $booking_id);
    }
}

/* -------------------------------------------------------------------------
   3.b) Email notifiche (con CC fisso)
------------------------------------------------------------------------- */
function rbf_send_admin_notification_email($first_name, $last_name, $email, $date, $time, $people, $notes, $tel, $meal) {
    $options = get_option('rbf_settings', rbf_get_default_settings());
    $to = $options['notification_email'] ?? 'info@villadianella.it';
    $cc = 'francesco.passeri@gmail.com';
    if (empty($to) || !is_email($to)) return;

    $site_name = get_bloginfo('name');
    $subject = "Nuova Prenotazione dal Sito Web - {$first_name} {$last_name}";
    $date_obj = date_create($date);
    $formatted_date = date_format($date_obj, 'd/m/Y');
    $notes_display = empty($notes) ? 'Nessuna' : nl2br(esc_html($notes));

    $body = <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8">
<style>body{font-family:Arial,sans-serif;color:#333}.container{padding:20px;border:1px solid #ddd;max-width:600px;margin:auto}h2{color:#000}strong{color:#555}</style>
</head><body><div class="container">
<h2>Nuova Prenotazione da {$site_name}</h2>
<ul>
  <li><strong>Cliente:</strong> {$first_name} {$last_name}</li>
  <li><strong>Email:</strong> {$email}</li>
  <li><strong>Telefono:</strong> {$tel}</li>
  <li><strong>Data:</strong> {$formatted_date}</li>
  <li><strong>Orario:</strong> {$time}</li>
  <li><strong>Pasto:</strong> {$meal}</li>
  <li><strong>Persone:</strong> {$people}</li>
  <li><strong>Note/Allergie:</strong> {$notes_display}</li>
</ul>
</div></body></html>
HTML;

    $headers = ['Content-Type: text/html; charset=UTF-8'];
    $from_email = 'noreply@' . preg_replace('/^www\./', '', $_SERVER['SERVER_NAME']);
    $headers[] = 'From: ' . $site_name . ' <' . $from_email . '>';
    $headers[] = 'Cc: ' . $cc;
    wp_mail($to, $subject, $body, $headers);
}

/* -------------------------------------------------------------------------
   3.c) Brevo (sempre, anche senza consenso marketing)
------------------------------------------------------------------------- */
function rbf_trigger_brevo_automation($first_name, $last_name, $email, $date, $time, $people, $notes, $lang, $tel, $marketing, $meal) {
    $options = get_option('rbf_settings', rbf_get_default_settings());
    $api_key = $options['brevo_api'] ?? '';
    $list_id = $lang === 'en' ? ($options['brevo_list_en'] ?? '') : ($options['brevo_list_it'] ?? '');

    if (empty($api_key)) { error_log('Brevo: API key non configurata.'); return; }
    if (empty($list_id)) { error_log('Brevo: ID lista non configurato per lingua ' . $lang); return; }

    $base_args = [
        'headers' => ['api-key' => $api_key, 'Content-Type' => 'application/json'],
        'timeout' => 10,
        'blocking' => true,
    ];

    // 1) Contact upsert: sempre in lista
    $contact_payload = [
        'email' => $email,
        'attributes' => [
            'FIRSTNAME' => $first_name,
            'LASTNAME' => $last_name,
            'WHATSAPP' => $tel,
            'PRENOTAZIONE_DATA' => $date,
            'PRENOTAZIONE_ORARIO' => $time,
            'PERSONE' => $people,
            'NOTE' => empty($notes) ? 'Nessuna' : $notes,
            'LINGUA' => $lang,
            'MARKETING_CONSENT' => ($marketing === 'yes')
        ],
        'listIds' => [intval($list_id)],
        'updateEnabled' => true,
    ];

    $response = wp_remote_post(
        'https://api.brevo.com/v3/contacts',
        array_merge($base_args, ['body' => wp_json_encode($contact_payload)])
    );
    if (is_wp_error($response)) error_log('Errore Brevo (upsert contatto): '.$response->get_error_message());

    // 2) Custom Event via /v3/events: sempre
    $event_payload = [
        'event_name' => 'booking_bistrot',
        'event_date' => gmdate('Y-m-d\TH:i:s\Z'),
        'identifiers' => ['email_id' => $email],
        'event_properties' => [
            'meal' => $meal, 'time' => $time, 'people' => $people, 'notes' => $notes,
            'language' => $lang, 'marketing_consent' => ($marketing === 'yes')
        ],
    ];

    $response = wp_remote_post(
        'https://api.brevo.com/v3/events',
        array_merge($base_args, ['body' => wp_json_encode($event_payload)])
    );
    if (is_wp_error($response)) error_log('Errore Brevo (evento booking_bistrot): '.$response->get_error_message());
}

/* -------------------------------------------------------------------------
   4) Admin: Calendario e Inserimento Manuale
------------------------------------------------------------------------- */
function rbf_calendar_page_html() {
    wp_enqueue_style('fullcalendar-css', 'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css', [], '5.11.3');
    wp_enqueue_script('fullcalendar-js', 'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js', ['jquery'], '5.11.3', true);
    wp_localize_script('fullcalendar-js', 'rbfAdminData', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('rbf_calendar_nonce')
    ]);
    $js = <<<'JS'
jQuery(function($){
  var el = document.getElementById('rbf-calendar'); if(!el) return;
  var calendar = new FullCalendar.Calendar(el,{
    initialView:'dayGridMonth',
    firstDay:1,
    events:function(fetchInfo,success,failure){
      $.ajax({
        url: rbfAdminData.ajaxUrl, type: 'POST',
        data: { action:'rbf_get_bookings_for_calendar', start:fetchInfo.startStr, end:fetchInfo.endStr, _ajax_nonce: rbfAdminData.nonce },
        success: function(r){ if(r.success) success(r.data); else failure(); },
        error: failure
      });
    }
  });
  calendar.render();
});
JS;
    wp_add_inline_script('fullcalendar-js', $js);
    ?>
    <div class="rbf-admin-wrap">
        <h1><?php echo esc_html(rbf_translate_string('Vista Calendario Prenotazioni')); ?></h1>
        <div id="rbf-calendar"></div>
    </div>
    <?php
}

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
            ],
        ]);

        if (!is_wp_error($post_id)) {
            $valore_pp = (float) ($options['valore_' . $meal] ?? 0);
            $valore_tot = $valore_pp * $people;
            $event_id   = 'rbf_' . $post_id;

            // Email + Brevo
            rbf_send_admin_notification_email($first_name, $last_name, $email, $date, $time, $people, $notes, $tel, $meal);
            rbf_trigger_brevo_automation($first_name, $last_name, $email, $date, $time, $people, $notes, $lang, $tel, $marketing, $meal);

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
            <?php submit_button(esc_html(rbf_translate_string('Aggiungi Prenotazione'))); ?>
        </form>
    </div>
    <?php
}
