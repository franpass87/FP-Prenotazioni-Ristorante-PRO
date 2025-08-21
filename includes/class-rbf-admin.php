<?php
/**
 * Admin functionality for Restaurant Booking Plugin
 *
 * @package RBF
 * @since 9.3.2
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin functionality class
 */
class RBF_Admin {

    /**
     * Constructor
     */
    public function __construct() {
        if (is_admin()) {
            $this->init();
        }
    }

    /**
     * Initialize admin functionality
     */
    private function init() {
        add_action('admin_menu', array($this, 'create_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Create admin menu
     */
    public function create_admin_menu() {
        add_menu_page(
            RBF_Utils::translate_string('Prenotazioni'),
            RBF_Utils::translate_string('Prenotazioni'),
            'manage_options',
            'rbf_bookings_menu',
            null,
            'dashicons-calendar-alt',
            20
        );
        
        add_submenu_page(
            'rbf_bookings_menu',
            RBF_Utils::translate_string('Tutte le Prenotazioni'),
            RBF_Utils::translate_string('Tutte le Prenotazioni'),
            'manage_options',
            'edit.php?post_type=rbf_booking'
        );
        
        add_submenu_page(
            'rbf_bookings_menu',
            RBF_Utils::translate_string('Aggiungi Prenotazione'),
            RBF_Utils::translate_string('Aggiungi Nuova'),
            'manage_options',
            'rbf_add_booking',
            array($this, 'add_booking_page')
        );
        
        add_submenu_page(
            'rbf_bookings_menu',
            RBF_Utils::translate_string('Vista Calendario'),
            RBF_Utils::translate_string('Calendario'),
            'manage_options',
            'rbf_calendar',
            array($this, 'calendar_page')
        );
        
        add_submenu_page(
            'rbf_bookings_menu',
            RBF_Utils::translate_string('Impostazioni'),
            RBF_Utils::translate_string('Impostazioni'),
            'manage_options',
            'rbf_settings',
            array($this, 'settings_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('rbf_opts_group', 'rbf_settings', [
            'sanitize_callback' => array('RBF_Utils', 'sanitize_settings'),
            'default' => RBF_Utils::get_default_settings(),
        ]);
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our plugin pages
        if ($hook !== 'rbf_bookings_menu_page_rbf_settings' &&
            $hook !== 'rbf_bookings_menu_page_rbf_calendar' &&
            $hook !== 'rbf_bookings_menu_page_rbf_add_booking' &&
            strpos($hook, 'edit.php?post_type=rbf_booking') === false) {
            return;
        }

        wp_enqueue_style('rbf-admin-css', RBF_Plugin::get_plugin_url() . 'assets/css/admin.css', [], RBF_Plugin::VERSION);
        
        // Load calendar assets only on calendar page
        if ($hook === 'rbf_bookings_menu_page_rbf_calendar') {
            wp_enqueue_style('fullcalendar-css', 'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css', [], '5.11.3');
            wp_enqueue_script('fullcalendar-js', 'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js', ['jquery'], '5.11.3', true);
            wp_enqueue_script('rbf-admin-js', RBF_Plugin::get_plugin_url() . 'assets/js/admin.js', ['jquery', 'fullcalendar-js'], RBF_Plugin::VERSION, true);
            
            wp_localize_script('rbf-admin-js', 'rbfAdminData', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('rbf_calendar_nonce')
            ]);
        }
    }

    /**
     * Settings page HTML
     */
    public function settings_page() {
        $options = wp_parse_args(get_option('rbf_settings', RBF_Utils::get_default_settings()), RBF_Utils::get_default_settings());
        ?>
        <div class="rbf-admin-wrap">
            <h1><?php echo esc_html(RBF_Utils::translate_string('Impostazioni Prenotazioni Ristorante')); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields('rbf_opts_group'); ?>
                <table class="form-table" role="presentation">
                    <tr><th colspan="2"><h2><?php echo esc_html(RBF_Utils::translate_string('Capienza e Orari')); ?></h2></th></tr>
                    
                    <tr><th><label for="rbf_capienza_pranzo"><?php echo esc_html(RBF_Utils::translate_string('Capienza Pranzo')); ?></label></th>
                        <td><input type="number" id="rbf_capienza_pranzo" name="rbf_settings[capienza_pranzo]" value="<?php echo esc_attr($options['capienza_pranzo']); ?>"></td></tr>
                    <tr><th><label for="rbf_orari_pranzo"><?php echo esc_html(RBF_Utils::translate_string('Orari Pranzo (inclusa Domenica)')); ?></label></th>
                        <td><input type="text" id="rbf_orari_pranzo" name="rbf_settings[orari_pranzo]" value="<?php echo esc_attr($options['orari_pranzo']); ?>" class="regular-text" placeholder="Es: 12:00,12:30,13:00"></td></tr>
                    
                    <tr><th><label for="rbf_capienza_cena"><?php echo esc_html(RBF_Utils::translate_string('Capienza Cena')); ?></label></th>
                        <td><input type="number" id="rbf_capienza_cena" name="rbf_settings[capienza_cena]" value="<?php echo esc_attr($options['capienza_cena']); ?>"></td></tr>
                    <tr><th><label for="rbf_orari_cena"><?php echo esc_html(RBF_Utils::translate_string('Orari Cena')); ?></label></th>
                        <td><input type="text" id="rbf_orari_cena" name="rbf_settings[orari_cena]" value="<?php echo esc_attr($options['orari_cena']); ?>" class="regular-text" placeholder="Es: 19:00,19:30,20:00"></td></tr>
                    
                    <tr><th><label for="rbf_capienza_aperitivo"><?php echo esc_html(RBF_Utils::translate_string('Capienza Aperitivo')); ?></label></th>
                        <td><input type="number" id="rbf_capienza_aperitivo" name="rbf_settings[capienza_aperitivo]" value="<?php echo esc_attr($options['capienza_aperitivo']); ?>"></td></tr>
                    <tr><th><label for="rbf_orari_aperitivo"><?php echo esc_html(RBF_Utils::translate_string('Orari Aperitivo')); ?></label></th>
                        <td><input type="text" id="rbf_orari_aperitivo" name="rbf_settings[orari_aperitivo]" value="<?php echo esc_attr($options['orari_aperitivo']); ?>" class="regular-text" placeholder="Es: 17:00,17:30,18:00"></td></tr>

                    <tr>
                        <th><?php echo esc_html(RBF_Utils::translate_string('Giorni aperti')); ?></th>
                        <td>
                            <?php
                            $days = ['mon'=>'Lunedì','tue'=>'Martedì','wed'=>'Mercoledì','thu'=>'Giovedì','fri'=>'Venerdì','sat'=>'Sabato','sun'=>'Domenica'];
                            foreach ($days as $key=>$label) {
                                $checked = ($options["open_{$key}"] ?? 'yes') === 'yes' ? 'checked' : '';
                                echo "<label><input type='checkbox' name='rbf_settings[open_{$key}]' value='yes' {$checked}> " . esc_html(RBF_Utils::translate_string($label)) . "</label><br>";
                            }
                            ?>
                        </td>
                    </tr>

                    <tr><th colspan="2"><h2><?php echo esc_html(RBF_Utils::translate_string('Chiusure Straordinarie')); ?></h2></th></tr>
                    <tr>
                        <th><label for="rbf_closed_dates"><?php echo esc_html(RBF_Utils::translate_string('Date Chiuse (una per riga, formato Y-m-d o Y-m-d - Y-m-d)')); ?></label></th>
                        <td><textarea id="rbf_closed_dates" name="rbf_settings[closed_dates]" rows="5" class="large-text"><?php echo esc_textarea($options['closed_dates']); ?></textarea></td>
                    </tr>

                    <tr><th colspan="2"><h2><?php echo esc_html(RBF_Utils::translate_string('Valore Economico Pasti (per Tracking)')); ?></h2></th></tr>
                    <tr><th><label for="rbf_valore_pranzo"><?php echo esc_html(RBF_Utils::translate_string('Valore medio Pranzo (€)')); ?></label></th>
                        <td><input type="number" step="0.01" id="rbf_valore_pranzo" name="rbf_settings[valore_pranzo]" value="<?php echo esc_attr($options['valore_pranzo']); ?>"></td></tr>
                    <tr><th><label for="rbf_valore_cena"><?php echo esc_html(RBF_Utils::translate_string('Valore medio Cena (€)')); ?></label></th>
                        <td><input type="number" step="0.01" id="rbf_valore_cena" name="rbf_settings[valore_cena]" value="<?php echo esc_attr($options['valore_cena']); ?>"></td></tr>
                    <tr><th><label for="rbf_valore_aperitivo"><?php echo esc_html(RBF_Utils::translate_string('Valore medio Aperitivo (€)')); ?></label></th>
                        <td><input type="number" step="0.01" id="rbf_valore_aperitivo" name="rbf_settings[valore_aperitivo]" value="<?php echo esc_attr($options['valore_aperitivo']); ?>"></td></tr>

                    <tr><th colspan="2"><h2><?php echo esc_html(RBF_Utils::translate_string('Integrazioni e Marketing')); ?></h2></th></tr>
                    <tr><th><label for="rbf_notification_email"><?php echo esc_html(RBF_Utils::translate_string('Email per Notifiche Ristorante')); ?></label></th>
                        <td><input type="email" id="rbf_notification_email" name="rbf_settings[notification_email]" value="<?php echo esc_attr($options['notification_email']); ?>" class="regular-text" placeholder="es. ristorante@esempio.com"></td></tr>
                    <tr><th><label for="rbf_ga4_id"><?php echo esc_html(RBF_Utils::translate_string('ID misurazione GA4')); ?></label></th>
                        <td><input type="text" id="rbf_ga4_id" name="rbf_settings[ga4_id]" value="<?php echo esc_attr($options['ga4_id']); ?>" class="regular-text" placeholder="G-XXXXXXXXXX"></td></tr>
                    <tr><th><label for="rbf_ga4_api_secret">GA4 API Secret (per invii server-side)</label></th>
                        <td><input type="text" id="rbf_ga4_api_secret" name="rbf_settings[ga4_api_secret]" value="<?php echo esc_attr($options['ga4_api_secret']); ?>" class="regular-text"></td></tr>
                    <tr><th><label for="rbf_meta_pixel_id"><?php echo esc_html(RBF_Utils::translate_string('ID Meta Pixel')); ?></label></th>
                        <td><input type="text" id="rbf_meta_pixel_id" name="rbf_settings[meta_pixel_id]" value="<?php echo esc_attr($options['meta_pixel_id']); ?>" class="regular-text"></td></tr>
                    <tr><th><label for="rbf_meta_access_token">Meta Access Token (per invii server-side)</label></th>
                        <td><input type="password" id="rbf_meta_access_token" name="rbf_settings[meta_access_token]" value="<?php echo esc_attr($options['meta_access_token']); ?>" class="regular-text"></td></tr>

                    <tr><th colspan="2"><h3><?php echo esc_html(RBF_Utils::translate_string('Impostazioni Brevo')); ?></h3></th></tr>
                    <tr><th><label for="rbf_brevo_api"><?php echo esc_html(RBF_Utils::translate_string('API Key Brevo')); ?></label></th>
                        <td><input type="password" id="rbf_brevo_api" name="rbf_settings[brevo_api]" value="<?php echo esc_attr($options['brevo_api']); ?>" class="regular-text"></td></tr>
                    <tr><th><label for="rbf_brevo_list_it"><?php echo esc_html(RBF_Utils::translate_string('ID Lista Brevo (IT)')); ?></label></th>
                        <td><input type="number" id="rbf_brevo_list_it" name="rbf_settings[brevo_list_it]" value="<?php echo esc_attr($options['brevo_list_it']); ?>"></td></tr>
                    <tr><th><label for="rbf_brevo_list_en"><?php echo esc_html(RBF_Utils::translate_string('ID Lista Brevo (EN)')); ?></label></th>
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
    public function calendar_page() {
        ?>
        <div class="rbf-admin-wrap">
            <h1><?php echo esc_html(RBF_Utils::translate_string('Vista Calendario Prenotazioni')); ?></h1>
            <div id="rbf-calendar"></div>
        </div>
        <?php
    }

    /**
     * Add booking page HTML
     */
    public function add_booking_page() {
        $options = get_option('rbf_settings', RBF_Utils::get_default_settings());
        $message = '';

        if (!empty($_POST) && check_admin_referer('rbf_add_backend_booking')) {
            $result = $this->process_manual_booking($_POST);
            $message = $result['message'];
        }

        ?>
        <div class="rbf-admin-wrap">
            <h1><?php echo esc_html(RBF_Utils::translate_string('Aggiungi Nuova Prenotazione')); ?></h1>
            <?php echo $message; ?>
            <form method="post">
                <?php wp_nonce_field('rbf_add_backend_booking'); ?>
                <table class="form-table">
                    <tr><th><label for="rbf_meal"><?php echo esc_html(RBF_Utils::translate_string('Pasto')); ?></label></th>
                        <td><select id="rbf_meal" name="rbf_meal">
                            <option value=""><?php echo esc_html(RBF_Utils::translate_string('Scegli il pasto')); ?></option>
                            <option value="pranzo"><?php echo esc_html(RBF_Utils::translate_string('Pranzo')); ?></option>
                            <option value="aperitivo"><?php echo esc_html(RBF_Utils::translate_string('Aperitivo')); ?></option>
                            <option value="cena"><?php echo esc_html(RBF_Utils::translate_string('Cena')); ?></option>
                        </select></td></tr>
                    <tr><th><label for="rbf_data"><?php echo esc_html(RBF_Utils::translate_string('Data')); ?></label></th>
                        <td><input type="date" id="rbf_data" name="rbf_data"></td></tr>
                    <tr><th><label for="rbf_time"><?php echo esc_html(RBF_Utils::translate_string('Orario')); ?></label></th>
                        <td><input type="time" id="rbf_time" name="rbf_time"></td></tr>
                    <tr><th><label for="rbf_persone"><?php echo esc_html(RBF_Utils::translate_string('Persone')); ?></label></th>
                        <td><input type="number" id="rbf_persone" name="rbf_persone" min="0"></td></tr>
                    <tr><th><label for="rbf_nome"><?php echo esc_html(RBF_Utils::translate_string('Nome')); ?></label></th>
                        <td><input type="text" id="rbf_nome" name="rbf_nome"></td></tr>
                    <tr><th><label for="rbf_cognome"><?php echo esc_html(RBF_Utils::translate_string('Cognome')); ?></label></th>
                        <td><input type="text" id="rbf_cognome" name="rbf_cognome"></td></tr>
                    <tr><th><label for="rbf_email"><?php echo esc_html(RBF_Utils::translate_string('Email')); ?></label></th>
                        <td><input type="email" id="rbf_email" name="rbf_email"></td></tr>
                    <tr><th><label for="rbf_tel"><?php echo esc_html(RBF_Utils::translate_string('Telefono')); ?></label></th>
                        <td><input type="tel" id="rbf_tel" name="rbf_tel"></td></tr>
                    <tr><th><label for="rbf_allergie"><?php echo esc_html(RBF_Utils::translate_string('Allergie/Note')); ?></label></th>
                        <td><textarea id="rbf_allergie" name="rbf_allergie"></textarea></td></tr>
                    <tr><th><label for="rbf_lang"><?php echo esc_html(RBF_Utils::translate_string('Lingua')); ?></label></th>
                        <td><select id="rbf_lang" name="rbf_lang"><option value="it">IT</option><option value="en">EN</option></select></td></tr>
                    <tr><th><?php echo esc_html(RBF_Utils::translate_string('Privacy')); ?></th>
                        <td><label><input type="checkbox" name="rbf_privacy" value="yes"> <?php echo esc_html(RBF_Utils::translate_string('Accettata')); ?></label></td></tr>
                    <tr><th><?php echo esc_html(RBF_Utils::translate_string('Marketing')); ?></th>
                        <td><label><input type="checkbox" name="rbf_marketing" value="yes"> <?php echo esc_html(RBF_Utils::translate_string('Accettato')); ?></label></td></tr>
                </table>
                <?php submit_button(esc_html(RBF_Utils::translate_string('Aggiungi Prenotazione'))); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Process manual booking submission
     * 
     * @param array $data POST data
     * @return array Result with success status and message
     */
    private function process_manual_booking($data) {
        $meal = sanitize_text_field($data['rbf_meal'] ?? '');
        $date = sanitize_text_field($data['rbf_data'] ?? '');
        $time = sanitize_text_field($data['rbf_time'] ?? '');
        $people = intval($data['rbf_persone'] ?? 0);
        $first_name = sanitize_text_field($data['rbf_nome'] ?? '');
        $last_name = sanitize_text_field($data['rbf_cognome'] ?? '');
        $email = sanitize_email($data['rbf_email'] ?? '');
        $tel = sanitize_text_field($data['rbf_tel'] ?? '');
        $notes = sanitize_textarea_field($data['rbf_allergie'] ?? '');
        $lang = sanitize_text_field($data['rbf_lang'] ?? 'it');
        $privacy = isset($data['rbf_privacy']) ? 'yes' : 'no';
        $marketing = isset($data['rbf_marketing']) ? 'yes' : 'no';

        $title = (!empty($first_name) && !empty($last_name)) 
            ? ucfirst($meal) . " per {$first_name} {$last_name} - {$date} {$time}" 
            : "Prenotazione Manuale - {$date} {$time}";

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
            // Get integrations instance and trigger notifications
            $integrations = RBF_Plugin::get_instance()->get_component('integrations');
            if ($integrations) {
                $integrations->send_admin_notification_email($first_name, $last_name, $email, $date, $time, $people, $notes, $tel, $meal);
                $integrations->trigger_brevo_automation($first_name, $last_name, $email, $date, $time, $people, $notes, $lang, $tel, $marketing, $meal);
            }

            return [
                'success' => true,
                'message' => '<div class="notice notice-success"><p>Prenotazione aggiunta con successo! <a href="' . admin_url('post.php?post=' . $post_id . '&action=edit') . '">Modifica</a></p></div>'
            ];
        } else {
            return [
                'success' => false,
                'message' => '<div class="notice notice-error"><p>Errore durante l\'aggiunta della prenotazione.</p></div>'
            ];
        }
    }
}