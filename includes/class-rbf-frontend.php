<?php
/**
 * Frontend functionality for Restaurant Booking Plugin
 *
 * @package RBF
 * @since 9.3.2
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Frontend functionality class
 */
class RBF_Frontend {

    /**
     * Constructor
     */
    public function __construct() {
        if (!is_admin()) {
            $this->init();
        }
    }

    /**
     * Initialize frontend functionality
     */
    private function init() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_shortcode('ristorante_booking_form', array($this, 'render_booking_form'));
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        global $post;
        if (!is_singular() || !$post || !has_shortcode($post->post_content, 'ristorante_booking_form')) {
            return;
        }

        $options = get_option('rbf_settings', RBF_Utils::get_default_settings());
        $locale = RBF_Utils::current_lang(); // 'it' o 'en'

        // Flatpickr
        wp_enqueue_style('rbf-flatpickr-css', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', [], '4.6.9');
        wp_enqueue_script('rbf-flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr', [], '4.6.9', true);
        $deps = ['jquery', 'rbf-flatpickr'];

        // Load Italian locale only (EN is default)
        if ($locale === 'it') {
            wp_enqueue_script('rbf-flatpickr-locale-it', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/it.js', ['rbf-flatpickr'], '4.6.9', true);
            $deps[] = 'rbf-flatpickr-locale-it';
        }

        // International telephone input
        wp_enqueue_style('rbf-intl-tel-input-css','https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/css/intlTelInput.css',[], '17.0.13');
        wp_enqueue_script('rbf-intl-tel-input','https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/js/intlTelInput.min.js',[], '17.0.13', true);

        // Frontend styles and script
        wp_enqueue_style('rbf-frontend-css', RBF_Plugin::get_plugin_url() . 'assets/css/frontend.css', ['rbf-flatpickr-css'], RBF_Plugin::VERSION);
        wp_enqueue_script('rbf-frontend-js', RBF_Plugin::get_plugin_url() . 'assets/js/frontend.js', $deps, RBF_Plugin::VERSION, true);

        // Prepare closed days data
        $closed_days_map = ['sun'=>0,'mon'=>1,'tue'=>2,'wed'=>3,'thu'=>4,'fri'=>5,'sat'=>6];
        $closed_days = [];
        foreach ($closed_days_map as $key => $day_index) {
            if (($options["open_{$key}"] ?? 'yes') !== 'yes') {
                $closed_days[] = $day_index;
            }
        }
        $closed_specific = RBF_Utils::get_closed_specific($options);

        // Localize script data
        wp_localize_script('rbf-frontend-js', 'rbfData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rbf_ajax_nonce'),
            'locale' => $locale,
            'closedDays' => $closed_days,
            'closedSingles' => $closed_specific['singles'],
            'closedRanges' => $closed_specific['ranges'],
            'utilsScript' => 'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/js/utils.js',
            'labels' => [
                'loading' => RBF_Utils::translate_string('Caricamento...'),
                'chooseTime' => RBF_Utils::translate_string('Scegli un orario...'),
                'noTime' => RBF_Utils::translate_string('Nessun orario disponibile'),
                'invalidPhone' => RBF_Utils::translate_string('Il numero di telefono inserito non è valido.'),
                'sundayBrunchNotice' => RBF_Utils::translate_string('Di Domenica il servizio è Brunch con menù alla carta.'),
                'privacyRequired' => RBF_Utils::translate_string('Devi accettare la Privacy Policy per procedere.'),
            ],
        ]);
    }

    /**
     * Render booking form shortcode
     * 
     * @return string Form HTML
     */
    public function render_booking_form() {
        ob_start(); ?>
        <div class="rbf-form-container">
            <div id="rbf-message-anchor"></div>
            <?php if (isset($_GET['rbf_success'])) : ?>
                <div class="rbf-success-message"><?php echo esc_html(RBF_Utils::translate_string('Grazie! La tua prenotazione è stata inviata con successo.')); ?></div>
            <?php else : ?>
                <?php if (isset($_GET['rbf_error'])) : ?>
                    <div class="rbf-error-message"><?php echo esc_html(urldecode($_GET['rbf_error'])); ?></div>
                <?php endif; ?>
                <form id="rbf-form" class="rbf-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="rbf_submit_booking">
                    <?php wp_nonce_field('rbf_booking','rbf_nonce'); ?>
                    
                    <!-- Meal selection -->
                    <div id="step-meal" class="rbf-step">
                        <label><?php echo esc_html(RBF_Utils::translate_string('Scegli il pasto')); ?></label>
                        <div class="rbf-radio-group">
                            <input type="radio" name="rbf_meal" value="pranzo" id="rbf_meal_pranzo" required>
                            <label for="rbf_meal_pranzo"><?php echo esc_html(RBF_Utils::translate_string('Pranzo')); ?></label>
                            <input type="radio" name="rbf_meal" value="aperitivo" id="rbf_meal_aperitivo" required>
                            <label for="rbf_meal_aperitivo"><?php echo esc_html(RBF_Utils::translate_string('Aperitivo')); ?></label>
                            <input type="radio" name="rbf_meal" value="cena" id="rbf_meal_cena" required>
                            <label for="rbf_meal_cena"><?php echo esc_html(RBF_Utils::translate_string('Cena')); ?></label>
                        </div>
                        <p id="rbf-meal-notice" style="display:none;"></p>
                    </div>

                    <!-- Date selection -->
                    <div id="step-date" class="rbf-step" style="display:none;">
                        <label for="rbf-date"><?php echo esc_html(RBF_Utils::translate_string('Data')); ?></label>
                        <input id="rbf-date" name="rbf_data" readonly="readonly" required>
                    </div>

                    <!-- Time selection -->
                    <div id="step-time" class="rbf-step" style="display:none;">
                        <label for="rbf-time"><?php echo esc_html(RBF_Utils::translate_string('Orario')); ?></label>
                        <select id="rbf-time" name="rbf_orario" required disabled>
                            <option value=""><?php echo esc_html(RBF_Utils::translate_string('Prima scegli la data')); ?></option>
                        </select>
                    </div>

                    <!-- People selection -->
                    <div id="step-people" class="rbf-step" style="display:none;">
                        <label><?php echo esc_html(RBF_Utils::translate_string('Persone')); ?></label>
                        <div class="rbf-people-selector">
                            <button type="button" id="rbf-people-minus" disabled>-</button>
                            <input type="number" id="rbf-people" name="rbf_persone" value="1" min="1" readonly="readonly" required>
                            <button type="button" id="rbf-people-plus">+</button>
                        </div>
                    </div>

                    <!-- Customer details -->
                    <div id="step-details" class="rbf-step" style="display:none;">
                        <label for="rbf-name"><?php echo esc_html(RBF_Utils::translate_string('Nome')); ?></label>
                        <input type="text" id="rbf-name" name="rbf_nome" required disabled>
                        
                        <label for="rbf-surname"><?php echo esc_html(RBF_Utils::translate_string('Cognome')); ?></label>
                        <input type="text" id="rbf-surname" name="rbf_cognome" required disabled>
                        
                        <label for="rbf-email"><?php echo esc_html(RBF_Utils::translate_string('Email')); ?></label>
                        <input type="email" id="rbf-email" name="rbf_email" required disabled>
                        
                        <label for="rbf-tel"><?php echo esc_html(RBF_Utils::translate_string('Telefono')); ?></label>
                        <input type="tel" id="rbf-tel" name="rbf_tel" required disabled>
                        
                        <label for="rbf-notes"><?php echo esc_html(RBF_Utils::translate_string('Allergie/Note')); ?></label>
                        <textarea id="rbf-notes" name="rbf_allergie" disabled></textarea>

                        <!-- Privacy and marketing checkboxes -->
                        <div class="rbf-checkbox-group">
                            <label>
                                <input type="checkbox" id="rbf-privacy" name="rbf_privacy" value="yes" required disabled>
                                <?php echo sprintf(
                                    RBF_Utils::translate_string('Acconsento al trattamento dei dati secondo l\'<a href="%s" target="_blank">Informativa sulla Privacy</a>'),
                                    'https://www.villadianella.it/privacy-statement-eu'
                                ); ?>
                            </label>
                            <label>
                                <input type="checkbox" id="rbf-marketing" name="rbf_marketing" value="yes" disabled>
                                <?php echo RBF_Utils::translate_string('Acconsento a ricevere comunicazioni promozionali via email e/o messaggi riguardanti eventi, offerte o novità.'); ?>
                            </label>
                        </div>
                    </div>

                    <!-- UTM tracking hidden fields -->
                    <input type="hidden" name="rbf_utm_source" id="rbf_utm_source" value="">
                    <input type="hidden" name="rbf_utm_medium" id="rbf_utm_medium" value="">
                    <input type="hidden" name="rbf_utm_campaign" id="rbf_utm_campaign" value="">
                    <input type="hidden" name="rbf_gclid" id="rbf_gclid" value="">
                    <input type="hidden" name="rbf_fbclid" id="rbf_fbclid" value="">
                    <input type="hidden" name="rbf_referrer" id="rbf_referrer" value="">

                    <!-- Language -->
                    <input type="hidden" name="rbf_lang" value="<?php echo esc_attr(RBF_Utils::current_lang()); ?>">
                    
                    <!-- Submit button -->
                    <button id="rbf-submit" type="submit" disabled style="display:none;"><?php echo esc_html(RBF_Utils::translate_string('Prenota')); ?></button>
                </form>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get remaining capacity for a date/slot
     * 
     * @param string $date Date in Y-m-d format
     * @param string $slot Meal slot (pranzo, cena, aperitivo)
     * @return int Remaining capacity
     */
    public function get_remaining_capacity($date, $slot) {
        $transient_key = 'rbf_avail_' . $date . '_' . $slot;
        $cached = get_transient($transient_key);
        if ($cached !== false) {
            return (int) $cached;
        }

        $options = get_option('rbf_settings', RBF_Utils::get_default_settings());
        $total = (int) ($options['capienza_'.$slot] ?? 0);
        if ($total === 0) {
            return 0;
        }

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
}