<?php
/**
 * Frontend functionality for Restaurant Booking Plugin
 * 
 * @package FP_Prenotazioni_Ristorante_PRO
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enqueue frontend assets
 */
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
    $deps[] = 'rbf-intl-tel-input';

    // Frontend styles
    wp_enqueue_style('rbf-frontend-css', plugin_dir_url(dirname(__FILE__)) . 'assets/css/frontend.css', ['rbf-flatpickr-css'], '9.3.2');

    // Frontend script (must be enqueued before wp_localize_script)
    wp_enqueue_script('rbf-frontend-js', plugin_dir_url(dirname(__FILE__)) . 'assets/js/frontend.js', $deps, '9.3.2', true);

    // Giorni chiusi
    $closed_days_map = ['sun'=>0,'mon'=>1,'tue'=>2,'wed'=>3,'thu'=>4,'fri'=>5,'sat'=>6];
    $closed_days = [];
    foreach ($closed_days_map as $key=>$day_index) {
        if (($options["open_{$key}"] ?? 'yes') !== 'yes') $closed_days[] = $day_index;
    }
    $closed_specific = rbf_get_closed_specific($options);

    wp_localize_script('rbf-frontend-js', 'rbfData', [
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
}

/**
 * Customer booking management shortcode
 */
add_shortcode('customer_booking_management', 'rbf_render_customer_booking_management');
function rbf_render_customer_booking_management() {
    ob_start();
    
    // Check if booking hash is provided
    if (!isset($_GET['booking']) || !$_GET['booking']) {
        ?>
        <div class="rbf-customer-management">
            <h3><?php echo esc_html(rbf_translate_string('Gestisci la tua Prenotazione')); ?></h3>
            <p><?php echo esc_html(rbf_translate_string('Inserisci il codice della tua prenotazione per visualizzare i dettagli e gestirla.')); ?></p>
            <form method="get">
                <div style="display: flex; gap: 10px; align-items: center; max-width: 400px;">
                    <input type="text" name="booking" placeholder="<?php echo esc_attr(rbf_translate_string('Codice Prenotazione')); ?>" required style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    <button type="submit" class="button button-primary"><?php echo esc_html(rbf_translate_string('Cerca')); ?></button>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
    
    $booking_hash = sanitize_text_field($_GET['booking']);
    
    // Find booking by hash
    global $wpdb;
    $booking_id = $wpdb->get_var($wpdb->prepare(
        "SELECT p.ID FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
         WHERE p.post_type = 'rbf_booking' AND p.post_status = 'publish'
         AND pm.meta_key = 'rbf_booking_hash' AND pm.meta_value = %s",
        $booking_hash
    ));
    
    if (!$booking_id) {
        ?>
        <div class="rbf-customer-management">
            <div class="rbf-error-message">
                <?php echo esc_html(rbf_translate_string('Prenotazione non trovata. Verifica il codice inserito.')); ?>
            </div>
            <a href="?">←<?php echo esc_html(rbf_translate_string('Torna indietro')); ?></a>
        </div>
        <?php
        return ob_get_clean();
    }
    
    // Get booking details
    $booking = get_post($booking_id);
    $first_name = get_post_meta($booking_id, 'rbf_nome', true);
    $last_name = get_post_meta($booking_id, 'rbf_cognome', true);
    $email = get_post_meta($booking_id, 'rbf_email', true);
    $tel = get_post_meta($booking_id, 'rbf_tel', true);
    $date = get_post_meta($booking_id, 'rbf_data', true);
    $time = get_post_meta($booking_id, 'rbf_time', true);
    $people = get_post_meta($booking_id, 'rbf_persone', true);
    $meal = get_post_meta($booking_id, 'rbf_orario', true);
    $notes = get_post_meta($booking_id, 'rbf_allergie', true);
    $status = get_post_meta($booking_id, 'rbf_booking_status', true) ?: 'pending';
    $created = get_post_meta($booking_id, 'rbf_booking_created', true);
    
    $statuses = rbf_get_booking_statuses();
    $status_label = $statuses[$status] ?? $status;
    $status_color = rbf_get_status_color($status);
    
    $formatted_date = date('d/m/Y', strtotime($date));
    $formatted_created = $created ? date('d/m/Y H:i', strtotime($created)) : '';
    
    $meals = [
        'pranzo' => rbf_translate_string('Pranzo'),
        'cena' => rbf_translate_string('Cena'),
        'aperitivo' => rbf_translate_string('Aperitivo')
    ];
    $meal_label = $meals[$meal] ?? ucfirst($meal);
    
    // Handle cancellation request
    if (isset($_POST['cancel_booking']) && wp_verify_nonce($_POST['_wpnonce'], 'cancel_booking_' . $booking_id)) {
        if (in_array($status, ['confirmed'])) {
            rbf_update_booking_status($booking_id, 'cancelled', 'Cancelled by customer');
            $status = 'cancelled';
            $status_label = rbf_translate_string('Annullata');
            $status_color = rbf_get_status_color('cancelled');
            ?>
            <div class="rbf-success-message" style="margin-bottom: 20px;">
                <?php echo esc_html(rbf_translate_string('La tua prenotazione è stata cancellata con successo.')); ?>
            </div>
            <?php
        }
    }
    ?>
    
    <div class="rbf-customer-management">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3><?php echo esc_html(rbf_translate_string('Dettagli Prenotazione')); ?> #<?php echo esc_html($booking_id); ?></h3>
            <a href="?" style="color: #666; text-decoration: none;">← <?php echo esc_html(rbf_translate_string('Nuova ricerca')); ?></a>
        </div>
        
        <div class="rbf-booking-details" style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                <div>
                    <h4 style="margin: 0 0 15px 0; color: #374151;"><?php echo esc_html(rbf_translate_string('Informazioni Cliente')); ?></h4>
                    <p style="margin: 5px 0;"><strong><?php echo esc_html(rbf_translate_string('Nome')); ?>:</strong> <?php echo esc_html($first_name . ' ' . $last_name); ?></p>
                    <p style="margin: 5px 0;"><strong><?php echo esc_html(rbf_translate_string('Email')); ?>:</strong> <?php echo esc_html($email); ?></p>
                    <p style="margin: 5px 0;"><strong><?php echo esc_html(rbf_translate_string('Telefono')); ?>:</strong> <?php echo esc_html($tel); ?></p>
                </div>
                
                <div>
                    <h4 style="margin: 0 0 15px 0; color: #374151;"><?php echo esc_html(rbf_translate_string('Dettagli Prenotazione')); ?></h4>
                    <p style="margin: 5px 0;"><strong><?php echo esc_html(rbf_translate_string('Data')); ?>:</strong> <?php echo esc_html($formatted_date); ?></p>
                    <p style="margin: 5px 0;"><strong><?php echo esc_html(rbf_translate_string('Orario')); ?>:</strong> <?php echo esc_html($time); ?></p>
                    <p style="margin: 5px 0;"><strong><?php echo esc_html(rbf_translate_string('Servizio')); ?>:</strong> <?php echo esc_html($meal_label); ?></p>
                    <p style="margin: 5px 0;"><strong><?php echo esc_html(rbf_translate_string('Persone')); ?>:</strong> <?php echo esc_html($people); ?></p>
                </div>
            </div>
            
            <?php if ($notes) : ?>
                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e5e7eb;">
                    <p style="margin: 5px 0;"><strong><?php echo esc_html(rbf_translate_string('Note/Allergie')); ?>:</strong></p>
                    <p style="margin: 5px 0; color: #666;"><?php echo esc_html($notes); ?></p>
                </div>
            <?php endif; ?>
            
            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <strong><?php echo esc_html(rbf_translate_string('Stato')); ?>:</strong>
                    <span style="display: inline-block; margin-left: 10px; padding: 4px 12px; border-radius: 20px; background: <?php echo esc_attr($status_color); ?>; color: white; font-size: 14px; font-weight: bold;">
                        <?php echo esc_html($status_label); ?>
                    </span>
                </div>
                <?php if ($formatted_created) : ?>
                    <small style="color: #6b7280;">
                        <?php echo esc_html(rbf_translate_string('Creata il')); ?>: <?php echo esc_html($formatted_created); ?>
                    </small>
                <?php endif; ?>
            </div>
        </div>
        
        <?php
        // Show appropriate actions based on status and date
        $booking_date = DateTime::createFromFormat('Y-m-d', $date);
        $today = new DateTime();
        $can_cancel = in_array($status, ['confirmed']) && $booking_date > $today;
        
        if ($can_cancel) : ?>
            <div class="rbf-booking-actions" style="background: #fff3cd; padding: 15px; border-radius: 8px; border: 1px solid #ffeaa7;">
                <h4 style="margin: 0 0 10px 0; color: #856404;"><?php echo esc_html(rbf_translate_string('Azioni Disponibili')); ?></h4>
                <p style="margin: 0 0 15px 0; color: #856404;">
                    <?php echo esc_html(rbf_translate_string('Puoi cancellare questa prenotazione se necessario. La cancellazione è definitiva.')); ?>
                </p>
                <form method="post" style="display: inline-block;">
                    <?php wp_nonce_field('cancel_booking_' . $booking_id); ?>
                    <input type="hidden" name="cancel_booking" value="1">
                    <button type="submit" 
                            onclick="return confirm('<?php echo esc_js(rbf_translate_string('Sei sicuro di voler cancellare questa prenotazione? L\'operazione non può essere annullata.')); ?>')"
                            style="background: #dc3545; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-weight: bold;">
                        <?php echo esc_html(rbf_translate_string('Cancella Prenotazione')); ?>
                    </button>
                </form>
            </div>
        <?php elseif ($status === 'cancelled') : ?>
            <div class="rbf-booking-info" style="background: #fee2e2; padding: 15px; border-radius: 8px; border: 1px solid #fecaca; color: #991b1b;">
                <p style="margin: 0;">
                    <strong><?php echo esc_html(rbf_translate_string('Prenotazione Cancellata')); ?></strong><br>
                    <?php echo esc_html(rbf_translate_string('Questa prenotazione è stata cancellata e non è più attiva.')); ?>
                </p>
            </div>
        <?php elseif ($status === 'completed') : ?>
            <div class="rbf-booking-info" style="background: #d1fae5; padding: 15px; border-radius: 8px; border: 1px solid #a7f3d0; color: #065f46;">
                <p style="margin: 0;">
                    <strong><?php echo esc_html(rbf_translate_string('Prenotazione Completata')); ?></strong><br>
                    <?php echo esc_html(rbf_translate_string('Grazie per aver scelto il nostro ristorante! Speriamo di rivederti presto.')); ?>
                </p>
            </div>
        <?php elseif ($booking_date <= $today) : ?>
            <div class="rbf-booking-info" style="background: #f3f4f6; padding: 15px; border-radius: 8px; border: 1px solid #d1d5db; color: #374151;">
                <p style="margin: 0;">
                    <strong><?php echo esc_html(rbf_translate_string('Prenotazione Passata')); ?></strong><br>
                    <?php echo esc_html(rbf_translate_string('Questa prenotazione si riferisce a una data passata.')); ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
    
    <?php
    return ob_get_clean();
}

/**
 * Booking form shortcode
 */
add_shortcode('ristorante_booking_form', 'rbf_render_booking_form');
function rbf_render_booking_form() {
    ob_start(); ?>
    <div class="rbf-form-container">
        <div id="rbf-message-anchor"></div>
        <?php if (isset($_GET['rbf_success'])) : ?>
            <div class="rbf-success-message">
                <?php echo esc_html(rbf_translate_string('Grazie! La tua prenotazione è stata confermata con successo.')); ?>
            </div>
        <?php else : ?>
            <?php if (isset($_GET['rbf_error'])) : ?>
                <div class="rbf-error-message">
                    <?php echo esc_html(urldecode($_GET['rbf_error'])); ?>
                </div>
            <?php endif; ?>
            <form id="rbf-form" class="rbf-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="rbf_submit_booking">
                <?php wp_nonce_field('rbf_booking','rbf_nonce'); ?>
                
                <!-- Progress Indicator -->
                <div class="rbf-progress-indicator" role="progressbar" aria-valuenow="1" aria-valuemin="1" aria-valuemax="5" aria-label="<?php echo esc_attr(rbf_translate_string('Progresso prenotazione')); ?>">
                    <div class="rbf-progress-step active" data-step="1" aria-label="<?php echo esc_attr(rbf_translate_string('Scegli il pasto')); ?>">1</div>
                    <div class="rbf-progress-step" data-step="2" aria-label="<?php echo esc_attr(rbf_translate_string('Data')); ?>">2</div>
                    <div class="rbf-progress-step" data-step="3" aria-label="<?php echo esc_attr(rbf_translate_string('Orario')); ?>">3</div>
                    <div class="rbf-progress-step" data-step="4" aria-label="<?php echo esc_attr(rbf_translate_string('Persone')); ?>">4</div>
                    <div class="rbf-progress-step" data-step="5" aria-label="<?php echo esc_attr(rbf_translate_string('Dati personali')); ?>">5</div>
                </div>

                <div id="step-meal" class="rbf-step active" role="group" aria-labelledby="meal-label">
                    <label id="meal-label"><?php echo esc_html(rbf_translate_string('Scegli il pasto')); ?></label>
                    <div class="rbf-radio-group" role="radiogroup" aria-labelledby="meal-label">
                        <input type="radio" name="rbf_meal" value="pranzo" id="rbf_meal_pranzo" required aria-describedby="rbf-meal-notice">
                        <label for="rbf_meal_pranzo"><?php echo esc_html(rbf_translate_string('Pranzo')); ?></label>
                        <input type="radio" name="rbf_meal" value="aperitivo" id="rbf_meal_aperitivo" required aria-describedby="rbf-meal-notice">
                        <label for="rbf_meal_aperitivo"><?php echo esc_html(rbf_translate_string('Aperitivo')); ?></label>
                        <input type="radio" name="rbf_meal" value="cena" id="rbf_meal_cena" required aria-describedby="rbf-meal-notice">
                        <label for="rbf_meal_cena"><?php echo esc_html(rbf_translate_string('Cena')); ?></label>
                    </div>
                    <p id="rbf-meal-notice" style="display:none;" role="status" aria-live="polite"></p>
                </div>

                <div id="step-date" class="rbf-step" style="display:none;" role="group" aria-labelledby="date-label">
                    <label id="date-label" for="rbf-date"><?php echo esc_html(rbf_translate_string('Data')); ?></label>
                    <input id="rbf-date" name="rbf_data" readonly="readonly" required aria-describedby="date-help">
                    <small id="date-help" class="rbf-help-text"><?php echo esc_html(rbf_translate_string('Seleziona una data dal calendario')); ?></small>
                </div>

                <div id="step-time" class="rbf-step" style="display:none;" role="group" aria-labelledby="time-label">
                    <label id="time-label" for="rbf-time"><?php echo esc_html(rbf_translate_string('Orario')); ?></label>
                    <select id="rbf-time" name="rbf_orario" required disabled aria-describedby="time-help">
                        <option value=""><?php echo esc_html(rbf_translate_string('Prima scegli la data')); ?></option>
                    </select>
                    <small id="time-help" class="rbf-help-text"><?php echo esc_html(rbf_translate_string('Seleziona un orario disponibile')); ?></small>
                </div>

                <div id="step-people" class="rbf-step" style="display:none;" role="group" aria-labelledby="people-label">
                    <label id="people-label"><?php echo esc_html(rbf_translate_string('Persone')); ?></label>
                    <div class="rbf-people-selector" role="group" aria-labelledby="people-label">
                        <button type="button" id="rbf-people-minus" disabled aria-label="<?php echo esc_attr(rbf_translate_string('Diminuisci numero persone')); ?>">-</button>
                        <input type="number" id="rbf-people" name="rbf_persone" value="1" min="1" readonly="readonly" required aria-describedby="people-help">
                        <button type="button" id="rbf-people-plus" aria-label="<?php echo esc_attr(rbf_translate_string('Aumenta numero persone')); ?>">+</button>
                    </div>
                    <small id="people-help" class="rbf-help-text"><?php echo esc_html(rbf_translate_string('Usa i pulsanti + e - per modificare')); ?></small>
                </div>

                <div id="step-details" class="rbf-step" style="display:none;" role="group" aria-labelledby="details-label">
                    <h3 id="details-label" class="rbf-section-title"><?php echo esc_html(rbf_translate_string('I tuoi dati')); ?></h3>
                    
                    <label for="rbf-name"><?php echo esc_html(rbf_translate_string('Nome')); ?> *</label>
                    <input type="text" id="rbf-name" name="rbf_nome" required disabled aria-required="true">
                    
                    <label for="rbf-surname"><?php echo esc_html(rbf_translate_string('Cognome')); ?> *</label>
                    <input type="text" id="rbf-surname" name="rbf_cognome" required disabled aria-required="true">
                    
                    <label for="rbf-email"><?php echo esc_html(rbf_translate_string('Email')); ?> *</label>
                    <input type="email" id="rbf-email" name="rbf_email" required disabled aria-required="true">
                    
                    <label for="rbf-tel"><?php echo esc_html(rbf_translate_string('Telefono')); ?> *</label>
                    <input type="tel" id="rbf-tel" name="rbf_tel" required disabled aria-required="true">
                    
                    <label for="rbf-notes"><?php echo esc_html(rbf_translate_string('Allergie/Note')); ?></label>
                    <textarea id="rbf-notes" name="rbf_allergie" disabled placeholder="<?php echo esc_attr(rbf_translate_string('Inserisci eventuali allergie o note particolari...')); ?>"></textarea>

                    <div class="rbf-checkbox-group" role="group" aria-labelledby="consent-label">
                        <h4 id="consent-label" class="rbf-consent-title"><?php echo esc_html(rbf_translate_string('Consensi')); ?></h4>
                        <label>
                            <input type="checkbox" id="rbf-privacy" name="rbf_privacy" value="yes" required disabled aria-required="true">
                            <?php echo sprintf(
                                rbf_translate_string('Acconsento al trattamento dei dati secondo l\'<a href="%s" target="_blank" rel="noopener">Informativa sulla Privacy</a> *'),
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

/**
 * Helper function to detect source channel classification
 */
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

/**
 * Get remaining capacity for a date and slot
 */
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

/**
 * Get closed specific dates
 */
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