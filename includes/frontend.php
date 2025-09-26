<?php
/**
 * Frontend functionality for FP Prenotazioni Ristorante
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
    if (!is_singular() || !$post) return;

    if (!rbf_post_has_booking_form($post)) return;

    $default_settings = rbf_get_default_settings();
    $options = wp_parse_args(rbf_get_settings(), $default_settings);
    $locale = rbf_current_lang(); // 'it' o 'en'

    // Enhanced resource loading with error handling
    
    // Add client-side resource error monitoring
    if (!is_admin()) {
        add_action('wp_head', function() {
            echo '<script>
            window.rbfResourceErrors = [];
            window.addEventListener("error", function(e) {
                if (e.target.tagName === "SCRIPT" || e.target.tagName === "LINK") {
                    window.rbfResourceErrors.push({
                        src: e.target.src || e.target.href,
                        type: e.target.tagName,
                        error: "Failed to load"
                    });
                    if (window.console && window.console.warn) {
                        console.warn("RBF: Resource failed to load:", e.target.src || e.target.href);
                    }
                }
            }, true);
            </script>';
        }, 1);
    }

    $use_cdn_assets = apply_filters('rbf_use_cdn_assets', false, 'frontend');

    $flatpickr_version = '4.6.13';
    $flatpickr_cdn_base = 'https://cdn.jsdelivr.net/npm/flatpickr@' . $flatpickr_version . '/dist/';
    $flatpickr_local_js = rbf_get_asset_url('vendor/flatpickr/flatpickr.min.js');
    $flatpickr_local_css = rbf_get_asset_url('vendor/flatpickr/flatpickr.min.css');

    $flatpickr_js_src = $use_cdn_assets ? $flatpickr_cdn_base . 'flatpickr.min.js' : $flatpickr_local_js;
    $flatpickr_css_src = $use_cdn_assets ? $flatpickr_cdn_base . 'flatpickr.min.css' : $flatpickr_local_css;

    wp_enqueue_style('rbf-flatpickr-css', $flatpickr_css_src, [], $flatpickr_version);
    wp_enqueue_script('rbf-flatpickr', $flatpickr_js_src, [], $flatpickr_version, true);

    $deps = ['jquery', 'rbf-flatpickr'];

    if ($locale === 'it') {
        $flatpickr_locale_local = rbf_get_asset_url('vendor/flatpickr/l10n/it.js');
        $flatpickr_locale_src = $use_cdn_assets ? $flatpickr_cdn_base . 'l10n/it.js' : $flatpickr_locale_local;

        wp_enqueue_script('rbf-flatpickr-locale-it', $flatpickr_locale_src, ['rbf-flatpickr'], $flatpickr_version, true);
        $deps[] = 'rbf-flatpickr-locale-it';

        if ($use_cdn_assets) {
            $flatpickr_locale_fallback = wp_json_encode($flatpickr_locale_local);
            $locale_fallback_script = sprintf(
                '(function(){var fallback=%1$s;if(!fallback){return;}var ensure=function(){if(window.flatpickr&&window.flatpickr.l10ns&&window.flatpickr.l10ns.it){return;}if(window.rbfEnsureAssetFallback){window.rbfEnsureAssetFallback("script",fallback);}};if(document.readyState==="complete"){setTimeout(ensure,0);}else{window.addEventListener("load",ensure);}})();',
                $flatpickr_locale_fallback
            );
            wp_add_inline_script('rbf-flatpickr-locale-it', $locale_fallback_script, 'after');
        }
    }

    $intl_tel_version = '19.2.16';
    $intl_tel_local_css = rbf_get_asset_url('vendor/intl-tel-input/intlTelInput.css');
    $intl_tel_css_src = $use_cdn_assets
        ? 'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/19.2.16/css/intlTelInput.css'
        : $intl_tel_local_css;

    wp_enqueue_style('rbf-intl-tel-input-css', $intl_tel_css_src, [], $intl_tel_version);

    foreach (rbf_get_brand_font_stylesheets($options, 'frontend') as $handle => $url) {
        wp_enqueue_style($handle, $url, [], null);
    }

    // Frontend styles and brand variables
    $frontend_css_url = rbf_get_versioned_asset_url('css/frontend.css');
    $frontend_css_version = null;

    if ($frontend_css_url === '') {
        $frontend_css_url = rbf_get_asset_url('css/frontend.css');
        $frontend_css_version = rbf_get_asset_version('css/frontend.css');
    }

    wp_enqueue_style(
        'rbf-frontend-css',
        $frontend_css_url,
        ['rbf-flatpickr-css'],
        $frontend_css_version
    );
    rbf_inject_brand_css_vars();

    // Register frontend script before attaching inline fallbacks
    $frontend_js_url = rbf_get_versioned_asset_url('js/frontend.js');
    $frontend_js_version = null;

    if ($frontend_js_url === '') {
        $frontend_js_url = rbf_get_asset_url('js/frontend.js');
        $frontend_js_version = rbf_get_asset_version('js/frontend.js');
    }

    wp_register_script(
        'rbf-frontend-js',
        $frontend_js_url,
        $deps,
        $frontend_js_version,
        true
    );

    if ($use_cdn_assets) {
        $fallback_helper = <<<'JS'
(function(){
    if (window.rbfEnsureAssetFallback) {
        return;
    }
    window.rbfEnsureAssetFallback = function(type, url) {
        if (!url) {
            return;
        }
        if (type === 'script') {
            if (document.querySelector('script[data-rbf-fallback="' + url + '"]')) {
                return;
            }
            var script = document.createElement('script');
            script.src = url;
            script.defer = true;
            script.dataset.rbfFallback = url;
            document.head.appendChild(script);
        } else if (type === 'style') {
            if (document.querySelector('link[data-rbf-fallback="' + url + '"]')) {
                return;
            }
            var link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = url;
            link.dataset.rbfFallback = url;
            document.head.appendChild(link);
        }
    };
})();
JS;
        wp_add_inline_script('rbf-flatpickr', $fallback_helper, 'before');

        $flatpickr_js_fallback = sprintf(
            'if (typeof window.flatpickr === "undefined") { if (window.rbfEnsureAssetFallback) { window.rbfEnsureAssetFallback("script", %s); } }',
            wp_json_encode($flatpickr_local_js)
        );
        wp_add_inline_script('rbf-flatpickr', $flatpickr_js_fallback, 'after');

        $flatpickr_css_check = sprintf(
            '(function(){var fallbackUrl=%1$s;var primaryUrl=%2$s;if(!fallbackUrl||!primaryUrl||!window.rbfEnsureAssetFallback){return;}var link=null;var styles=document.querySelectorAll(\'link[rel="stylesheet"]\');for(var i=0;i<styles.length;i++){var href=styles[i].getAttribute("href")||"";if(!href){continue;}if(href===primaryUrl||href.indexOf("flatpickr.min.css")!==-1){link=styles[i];break;}}var loadFallback=function(){window.rbfEnsureAssetFallback("style",fallbackUrl);};if(!link){loadFallback();return;}var triggered=false;var once=function(){if(triggered){return;}triggered=true;loadFallback();};link.addEventListener("error",once);setTimeout(function(){try{if(!link.sheet||!link.sheet.cssRules||!link.sheet.cssRules.length){once();}}catch(e){var hasSheet=false;var sheets=document.styleSheets||[];for(var j=0;j<sheets.length;j++){var sheetHref=sheets[j].href||"";if(sheetHref&&sheetHref.indexOf("flatpickr")!==-1){hasSheet=true;break;}}if(!hasSheet){once();}}},3000);})();',
            wp_json_encode($flatpickr_local_css),
            wp_json_encode($flatpickr_css_src)
        );
        wp_add_inline_script('rbf-flatpickr', $flatpickr_css_check, 'after');

        $intl_tel_fallback = sprintf(
            '(function(){var fallbackUrl=%1$s;var primaryUrl=%2$s;if(!fallbackUrl||!primaryUrl||!window.rbfEnsureAssetFallback){return;}var link=document.querySelector("link[href=\""+primaryUrl+"\"]");var ensure=function(){var sheets=document.styleSheets||[];var hasSheet=false;for(var i=0;i<sheets.length;i++){var href=sheets[i].href||"";if(href&&href.indexOf("intlTelInput")!==-1){hasSheet=true;break;}}if(!hasSheet){window.rbfEnsureAssetFallback("style",fallbackUrl);}};if(link){link.addEventListener("error",function(){window.rbfEnsureAssetFallback("style",fallbackUrl);});}setTimeout(ensure,3000);})();',
            wp_json_encode($intl_tel_local_css),
            wp_json_encode($intl_tel_css_src)
        );
        wp_add_inline_script('rbf-frontend-js', $intl_tel_fallback, 'before');
    }

    // Frontend script will be enqueued after localization

    // Giorni chiusi
    $closed_days_map = ['sun'=>0,'mon'=>1,'tue'=>2,'wed'=>3,'thu'=>4,'fri'=>5,'sat'=>6];
    $closed_days = [];
    $open_values = ['yes', '1', 'true', 'on'];
    foreach ($closed_days_map as $key => $day_index) {
        $is_open_raw = $options["open_{$key}"] ?? 'yes';
        $is_open = in_array(strtolower((string)$is_open_raw), $open_values, true);
        if (!$is_open) {
            $closed_days[] = $day_index;
        }
    }
    $closed_specific = rbf_get_closed_specific($options);

    // Get meal tooltips and availability for JavaScript with proper translation
    $active_meals = rbf_get_active_meals();
    $meal_tooltips = [];
    $meal_availability = [];
    $valid_day_keys = array_keys($closed_days_map);
    foreach ($active_meals as $meal) {
        if (!empty($meal['tooltip'])) {
            $meal_tooltips[$meal['id']] = rbf_translate_string($meal['tooltip']);
        }
        $available = $meal['available_days'] ?? [];
        $meal_availability[$meal['id']] = array_values(array_intersect($valid_day_keys, $available));
    }

    $people_max = rbf_get_people_max_limit($options);
    $has_people_limit = ($people_max > 0 && $people_max < PHP_INT_MAX);

    wp_localize_script('rbf-frontend-js', 'rbfData', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('rbf_ajax_nonce'),
        'locale' => $locale, // it/en
        'debug' => defined('WP_DEBUG') && WP_DEBUG, // Enable debug mode if WP_DEBUG is on
        'build' => rbf_get_frontend_build_metadata(),

        // RENEWED: Enhanced data validation to ensure arrays are always arrays
        'closedDays' => rbf_ensure_array($closed_days),
        'closedSingles' => rbf_ensure_array($closed_specific['singles'] ?? []),
        'closedRanges' => rbf_ensure_array($closed_specific['ranges'] ?? []),
        'exceptions' => rbf_ensure_array($closed_specific['exceptions'] ?? []),

        'minAdvanceMinutes' => max(0, absint($options['min_advance_minutes'] ?? 0)),
        'maxAdvanceMinutes' => max(0, absint($options['max_advance_minutes'] ?? $default_settings['max_advance_minutes'])),
        'peopleMax' => $people_max,
        'peopleMaxIsUnlimited' => !$has_people_limit,

        // RENEWED: Enhanced meal data validation
        'mealTooltips' => rbf_ensure_array($meal_tooltips),
        'mealAvailability' => rbf_ensure_meal_availability($meal_availability),

        'labels' => [
            'loading' => rbf_translate_string('Caricamento...'),
            'chooseTime' => rbf_translate_string('Scegli un orario...'),
            'noTime' => rbf_translate_string('Nessun orario disponibile'),
            'invalidPhone' => rbf_translate_string('Il numero di telefono inserito non è valido.'),
            'phonePlaceholder' => rbf_translate_string('Inserisci il numero di telefono'),
            'selectPrefix' => rbf_translate_string('Seleziona prefisso internazionale'),
            'sundayBrunchNotice' => rbf_translate_string('Di Domenica il servizio è Brunch con menù alla carta.'),
            'privacyRequired' => rbf_translate_string('Devi accettare la Privacy Policy per procedere.'),
            
            // Inline validation messages
            'mealRequired' => rbf_translate_string('Seleziona un pasto per continuare.'),
            'dateRequired' => rbf_translate_string('Seleziona una data per continuare.'),
            'dateInPast' => rbf_translate_string('La data selezionata non può essere nel passato.'),
            'timeRequired' => rbf_translate_string('Seleziona un orario per continuare.'),
            'peopleMinimum' => rbf_translate_string('Il numero di persone deve essere almeno 1.'),
            'peopleMaximum' => $has_people_limit
                ? sprintf(rbf_translate_string('Il numero di persone non può superare %d.'), $people_max)
                : '',
            'nameRequired' => rbf_translate_string('Il nome deve contenere almeno 2 caratteri.'),
            'nameInvalid' => rbf_translate_string('Il nome può contenere solo lettere, spazi, apostrofi e trattini.'),
            'surnameRequired' => rbf_translate_string('Il cognome deve contenere almeno 2 caratteri.'),
            'surnameInvalid' => rbf_translate_string('Il cognome può contenere solo lettere, spazi, apostrofi e trattini.'),
            'emailRequired' => rbf_translate_string('L\'indirizzo email è obbligatorio.'),
            'emailInvalid' => rbf_translate_string('Inserisci un indirizzo email valido.'),
            'emailTest' => rbf_translate_string('Utilizza un indirizzo email reale per la prenotazione.'),
            'phoneRequired' => rbf_translate_string('Il numero di telefono è obbligatorio.'),
            'phoneMinLength' => rbf_translate_string('Il numero di telefono deve contenere almeno 8 cifre.'),
            'phoneMaxLength' => rbf_translate_string('Il numero di telefono non può superare 15 cifre.'),
            
            // Calendar availability labels
            'available' => rbf_translate_string('Disponibile'),
            'limited' => rbf_translate_string('Quasi al completo'),
            'nearlyFull' => rbf_translate_string('Quasi al completo'),
            'full' => rbf_translate_string('Completo'),
            'spotsRemaining' => rbf_translate_string('Posti rimasti:'),
            'wideAvailability' => rbf_translate_string('Disponibilità ampia'),
            'occupancy' => rbf_translate_string('Occupazione:'),
            
            // AI Suggestions labels
            'alternativesTitle' => rbf_translate_string('Alternative disponibili'),
            'alternativesSubtitle' => rbf_translate_string('Seleziona una delle alternative seguenti:'),
            
            // Enhanced contextual tooltip labels
            'manySpots' => rbf_translate_string('Molti posti disponibili'),
            'someSpots' => rbf_translate_string('Buona disponibilità'),
            'lastSpots' => rbf_translate_string('Ultimi 2 posti rimasti'),
            'fewSpots' => rbf_translate_string('Pochi posti rimasti'),
            'actFast' => rbf_translate_string('Prenota subito!'),
            // Form tooltip labels
            'timeTooltip' => rbf_translate_string('Seleziona il tuo orario preferito'),
            'timeSelected' => rbf_translate_string('Orario selezionato: {time}'),
            'peopleTooltip' => rbf_translate_string('Numero di persone per la prenotazione'),
            'singlePerson' => rbf_translate_string('Prenotazione per 1 persona'),
            'multiplePeople' => rbf_translate_string('Prenotazione per {count} persone'),
            'nearMaxPeople' => rbf_translate_string('Prenotazione per {count} persone (quasi al massimo)'),
            'largePeople' => rbf_translate_string('Prenotazione per {count} persone (gruppo numeroso)'),
            'phoneTooltip' => rbf_translate_string('Inserisci il tuo numero di telefono per confermare la prenotazione'),
            'emailTooltip' => rbf_translate_string('Riceverai una email di conferma della prenotazione'),
            'nameTooltip' => rbf_translate_string('Il nome del titolare della prenotazione'),
            // Confirmation modal labels
            'confirmBookingTitle' => rbf_translate_string('Conferma Prenotazione'),
            'bookingSummary' => rbf_translate_string('Riepilogo Prenotazione'),
            'confirmWarning' => rbf_get_confirmation_warning_message($options),
            'meal' => rbf_translate_string('Servizio'),
            'date' => rbf_translate_string('Data'),
            'time' => rbf_translate_string('Orario'),
            'people' => rbf_translate_string('Persone'),
            'customer' => rbf_translate_string('Cliente'),
            'phone' => rbf_translate_string('Telefono'),
            'email' => rbf_translate_string('Email'),
            'notes' => rbf_translate_string('Note/Allergie'),
            'noNotes' => rbf_translate_string('Nessuna nota inserita'),
            'cancel' => rbf_translate_string('Annulla'),
            'confirmBooking' => rbf_translate_string('Conferma Prenotazione'),
            // Enhanced error messages for better UX
            'errorRefresh' => rbf_translate_string('Errore di connessione. Aggiorna la pagina e riprova.'),
            'errorGeneric' => rbf_translate_string('Si è verificato un errore. Riprova tra qualche istante.'),
            'errorNetwork' => rbf_translate_string('Problema di connessione. Controlla la tua connessione internet.'),
            'loadingCalendar' => rbf_translate_string('Caricamento calendario...'),
            'loadingTimes' => rbf_translate_string('Caricamento orari...'),
            'refreshing' => rbf_translate_string('Aggiornamento...'),
            'submittingBooking' => rbf_translate_string('Invio prenotazione in corso...'),
            
            // Enhanced calendar labels
            'chooseDate' => rbf_translate_string('Seleziona una data'),
        ],
    ]);

    wp_enqueue_script('rbf-frontend-js');
}

/**
 * Inject brand CSS variables globally
 */
function rbf_inject_brand_css_vars($accent_override = '') {
    $css_vars = rbf_generate_brand_css_vars($accent_override);
    
    $css = ":root {\n";
    foreach ($css_vars as $var => $value) {
        $css .= "    $var: $value;\n";
    }
    $css .= "}\n";
    
    wp_add_inline_style('rbf-frontend-css', $css);
}

/**
 * Inject CSS for a specific shortcode instance
 */
function rbf_inject_instance_css($atts) {
    $accent_override = !empty($atts['accent_color']) ? sanitize_hex_color($atts['accent_color']) : '';
    $radius_override = !empty($atts['border_radius']) ? sanitize_text_field($atts['border_radius']) : '';
    
    if (!$accent_override && !$radius_override) {
        return;
    }
    
    $css_vars = rbf_generate_brand_css_vars($accent_override);
    
    // Override radius if provided
    if ($radius_override) {
        $css_vars['--fppr-radius'] = $radius_override;
        $css_vars['--rbf-radius'] = $radius_override;
    }
    
    // Generate unique ID for this instance
    static $instance_counter = 0;
    $instance_counter++;
    $instance_id = 'rbf-instance-' . $instance_counter;
    
    $css = "#{$instance_id} {\n";
    foreach ($css_vars as $var => $value) {
        $css .= "    $var: $value;\n";
    }
    $css .= "}\n";
    
    // Add CSS to the page
    wp_add_inline_style('rbf-frontend-css', $css);
    
    // Add instance ID to the form container via JavaScript
    wp_add_inline_script('rbf-frontend-js', "
        document.addEventListener('DOMContentLoaded', function() {
            var containers = document.querySelectorAll('.rbf-form-container');
            if (containers.length >= {$instance_counter}) {
                containers[{$instance_counter} - 1].id = '{$instance_id}';
            }
        });
    ");
}

/**
 * Customer booking management shortcode
 */
add_shortcode('customer_booking_management', 'rbf_render_customer_booking_management');
function rbf_render_customer_booking_management() {
    ob_start();
    
    // Check if booking hash is provided
    if (!isset($_GET['booking']) || !sanitize_text_field($_GET['booking'])) {
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
    if ($time === '' || $time === null) {
        $time = get_post_meta($booking_id, 'rbf_orario', true);
    }
    if (is_string($time)) {
        $time = trim($time);
    }
    $people = get_post_meta($booking_id, 'rbf_persone', true);
    $meal = get_post_meta($booking_id, 'rbf_meal', true) ?: get_post_meta($booking_id, 'rbf_orario', true);
    $notes = get_post_meta($booking_id, 'rbf_allergie', true);
    $status = get_post_meta($booking_id, 'rbf_booking_status', true) ?: 'pending';
    $created = get_post_meta($booking_id, 'rbf_booking_created', true);

    $time_display = ($time !== '' && $time !== null) ? $time : rbf_translate_string('Orario non disponibile');
    
    $statuses = rbf_get_booking_statuses();
    $status_label = $statuses[$status] ?? $status;
    $status_color = rbf_get_status_color($status);
    
    $formatted_date = date('d/m/Y', strtotime($date));
    $formatted_created = $created ? date('d/m/Y H:i', strtotime($created)) : '';
    
    $meal_config = rbf_get_meal_config($meal);
    if ($meal_config && isset($meal_config['name']) && $meal_config['name'] !== '') {
        $meal_label = $meal_config['name'];
    } else {
        $meal_label = $meal;
    }
    
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
                    <p style="margin: 5px 0;"><strong><?php echo esc_html(rbf_translate_string('Orario')); ?>:</strong> <?php echo esc_html($time_display); ?></p>
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
        $tz = rbf_wp_timezone();
        $booking_datetime = null;
        if (!empty($date)) {
            $time_for_datetime = $time;
            if (!is_string($time_for_datetime)) {
                $time_for_datetime = (string) $time_for_datetime;
            }
            $time_for_datetime = trim($time_for_datetime);
            if ($time_for_datetime === '') {
                $time_for_datetime = '00:00';
            }

            $datetime_string = trim($date . ' ' . $time_for_datetime);
            $booking_datetime = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $datetime_string, $tz);
            if (!$booking_datetime) {
                $booking_datetime = DateTimeImmutable::createFromFormat('Y-m-d H:i', $datetime_string, $tz);
            }
            if (!$booking_datetime) {
                $booking_datetime = DateTimeImmutable::createFromFormat('Y-m-d', $date, $tz);
            }
        }

        $now = new DateTimeImmutable('now', $tz);
        $can_cancel = in_array($status, ['confirmed']) && $booking_datetime instanceof DateTimeImmutable && $booking_datetime > $now;
        
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
        <?php elseif ($booking_datetime instanceof DateTimeImmutable && $booking_datetime <= $now) : ?>
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
function rbf_render_booking_form($atts = []) {
    // Parse shortcode attributes
    $atts = shortcode_atts([
        'accent_color' => '', // Allow accent color override
        'border_radius' => '', // Allow border radius override
        'special_type' => '', // Special occasion type (anniversary, birthday, celebration, etc.)
        'special_label' => '', // Custom label for the special occasion
    ], $atts);
    
    // Inject custom CSS if accent color is provided
    if (!empty($atts['accent_color'])) {
        rbf_inject_instance_css($atts);
    }
    
    // Detect special occasion type from shortcode attributes or URL parameters
    $special_type = '';
    $special_label = '';
    
    // Priority: shortcode attribute > URL parameter
    if (!empty($atts['special_type'])) {
        $special_type = sanitize_key($atts['special_type']);
        $special_label = !empty($atts['special_label']) ? sanitize_text_field($atts['special_label']) : '';
    } elseif (!empty($_GET['special']) || !empty($_GET['booking_type'])) {
        $special_type = sanitize_key($_GET['special'] ?? $_GET['booking_type'] ?? '');
    }
    
    // Get predefined special occasion labels
    $special_labels = rbf_get_special_occasion_labels();
    if (empty($special_label) && !empty($special_type) && isset($special_labels[$special_type])) {
        $special_label = $special_labels[$special_type];
    }

    $options = rbf_get_settings();
    $privacy_policy_url = trim($options['privacy_policy_url'] ?? '');
    if ($privacy_policy_url === '' && function_exists('get_privacy_policy_url')) {
        $privacy_policy_url = get_privacy_policy_url();
    }

    $phone_prefixes = rbf_get_phone_prefixes();
    $default_phone_prefix = rbf_get_default_phone_prefix();

    $active_meals = rbf_get_active_meals();
    $fallback_time_map = [];

    if (!empty($active_meals)) {
        foreach ($active_meals as $meal_config) {
            $meal_id = $meal_config['id'] ?? '';
            $meal_label = $meal_config['name'] ?? $meal_id;
            $time_slots = $meal_config['time_slots'] ?? '';

            if ($meal_id === '' || $time_slots === '') {
                continue;
            }

            $slot_duration = $meal_config['slot_duration_minutes'] ?? null;
            $normalized_slots = rbf_normalize_time_slots($time_slots, $slot_duration);

            if (empty($normalized_slots)) {
                continue;
            }

            $fallback_time_map[$meal_id] = [
                'label' => rbf_translate_string($meal_label),
                'times' => $normalized_slots,
            ];
        }
    }

    $build_signature = rbf_get_plugin_build_signature();
    $build_signature_attr = '';
    if ($build_signature !== '') {
        $build_signature_attr = sprintf(' data-rbf-build="%s"', esc_attr($build_signature));
    }

    ob_start(); ?>
    <?php if ($build_signature !== '') : ?>
        <!-- FP Prenotazioni Ristorante build: <?php echo esc_html($build_signature); ?> -->
    <?php endif; ?>
    <div class="rbf-form-container"<?php echo $build_signature_attr; ?>>
        <div id="rbf-message-anchor"></div>
        <?php if (isset($_GET['rbf_success'])) : ?>
            <div class="rbf-success-message">
                <?php echo esc_html(rbf_translate_string('Grazie! La tua prenotazione è stata confermata con successo.')); ?>
            </div>
        <?php else : ?>
            <?php if (isset($_GET['rbf_error'])) : ?>
                <div class="rbf-error-message" role="alert">
                    <?php echo esc_html(urldecode($_GET['rbf_error'])); ?>
                </div>
            <?php endif; ?>
            
            <form id="rbf-form" class="rbf-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" role="form" aria-label="<?php echo esc_attr(rbf_translate_string('Modulo di prenotazione ristorante')); ?>">
                <input type="hidden" name="action" value="rbf_submit_booking">
                <?php wp_nonce_field('rbf_booking','rbf_nonce'); ?>
                
                <?php if (!empty($special_type)) : ?>
                    <input type="hidden" name="rbf_special_type" value="<?php echo esc_attr($special_type); ?>">
                    <?php if (!empty($special_label)) : ?>
                        <input type="hidden" name="rbf_special_label" value="<?php echo esc_attr($special_label); ?>">
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php if (!empty($special_type) && !empty($special_label)) : ?>
                    <div class="rbf-special-occasion-notice">
                        <div class="rbf-special-icon">🎉</div>
                        <div class="rbf-special-text">
                            <strong><?php echo esc_html(rbf_translate_string('Prenotazione Speciale')); ?>:</strong>
                            <?php echo esc_html($special_label); ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Progress Indicator -->
                <div class="rbf-progress-indicator" role="progressbar" aria-valuenow="1" aria-valuemin="1" aria-valuemax="5" aria-label="<?php echo esc_attr(rbf_translate_string('Progresso prenotazione')); ?>" aria-describedby="progress-description">
                    <div class="rbf-progress-step active" data-step="1" aria-label="<?php echo esc_attr(rbf_translate_string('Scegli il pasto')); ?>" aria-current="step">1</div>
                    <div class="rbf-progress-step" data-step="2" aria-label="<?php echo esc_attr(rbf_translate_string('Data')); ?>" aria-current="false">2</div>
                    <div class="rbf-progress-step" data-step="3" aria-label="<?php echo esc_attr(rbf_translate_string('Orario')); ?>" aria-current="false">3</div>
                    <div class="rbf-progress-step" data-step="4" aria-label="<?php echo esc_attr(rbf_translate_string('Persone')); ?>" aria-current="false">4</div>
                    <div class="rbf-progress-step" data-step="5" aria-label="<?php echo esc_attr(rbf_translate_string('Dati personali')); ?>" aria-current="false">5</div>
                </div>
                <div id="progress-description" class="sr-only"><?php echo esc_html(rbf_translate_string('Indicatore di progresso a 5 passaggi per completare la prenotazione')); ?></div>

                <noscript>
                    <style>
                        .rbf-form-container .rbf-progress-indicator { display: none !important; }
                        .rbf-form-container .rbf-step { display: block !important; opacity: 1 !important; transform: none !important; }
                        .rbf-form-container .rbf-fade-in { display: block !important; opacity: 1 !important; }
                        .rbf-form-container .rbf-skeleton, .rbf-form-container [data-skeleton="true"] .rbf-skeleton { display: none !important; }
                        .rbf-form-container #rbf-submit { display: inline-flex !important; }
                        .rbf-form-container .rbf-people-selector button { display: none !important; }
                        .rbf-form-container .rbf-noscript-notice {
                            margin: 1.5rem 0;
                            padding: 1rem 1.25rem;
                            border-radius: var(--rbf-radius, 8px);
                            background: #fff3cd;
                            border: 1px solid #ffeeba;
                            color: #664d03;
                        }
                    </style>
                    <div class="rbf-noscript-notice">
                        <p><?php echo esc_html(rbf_translate_string('JavaScript è disattivato. Puoi comunque completare la prenotazione compilando tutti i campi e inviando il modulo.')); ?></p>
                        <p><?php echo esc_html(rbf_translate_string('Seleziona il pasto e poi scegli un orario dall\'elenco corrispondente.')); ?></p>
                        <p><?php echo esc_html(rbf_translate_string('Inserisci la data nel formato AAAA-MM-GG se il calendario non è visibile.')); ?></p>
                    </div>
                </noscript>

                <div id="step-meal" class="rbf-step active" role="group" aria-labelledby="meal-label">
                    <div class="rbf-field-wrapper">
                        <label id="meal-label"><?php echo esc_html(rbf_translate_string('Scegli il pasto')); ?><span class="rbf-required-indicator">*</span></label>
                        <div class="rbf-radio-group" role="radiogroup" aria-labelledby="meal-label" aria-required="true">
                            <?php
                            if (empty($active_meals)) {
                                // No meals configured - show helpful message for admin users
                                if (rbf_user_can_manage_settings()) {
                                    ?>
                                    <div class="notice notice-warning inline" style="margin: 0;">
                                        <p><strong><?php echo esc_html(rbf_translate_string('Configurazione richiesta:')); ?></strong> <?php echo esc_html(rbf_translate_string('Nessun pasto è configurato. Per abilitare le prenotazioni, configura almeno un pasto nella sezione')); ?> <a href="<?php echo admin_url('admin.php?page=rbf_settings'); ?>"><?php echo esc_html(rbf_translate_string('Impostazioni Plugin')); ?></a>.</p>
                                    </div>
                                    <?php
                                } else {
                                    ?>
                                    <div class="notice notice-info inline" style="margin: 0;">
                                        <p><?php echo esc_html(rbf_translate_string('Il servizio di prenotazione è temporaneamente non disponibile. Riprova più tardi o contatta il ristorante.')); ?></p>
                                    </div>
                                    <?php
                                }
                            } else {
                                foreach ($active_meals as $meal) {
                                    $meal_id = esc_attr($meal['id']);
                                    $meal_name = esc_html($meal['name']);
                                    ?>
                                    <input type="radio" name="rbf_meal" value="<?php echo $meal_id; ?>" id="rbf_meal_<?php echo $meal_id; ?>" required aria-describedby="rbf-meal-notice">
                                    <label for="rbf_meal_<?php echo $meal_id; ?>"><?php echo $meal_name; ?></label>
                                    <?php
                                }
                            }
                            ?>
                        </div>
                        <div id="rbf-meal-error" class="rbf-field-error"></div>
                        <p id="rbf-meal-notice" style="display:none;" role="status" aria-live="polite"></p>
                    </div>
                </div>

                <div id="step-date" class="rbf-step" style="display:none;" role="group" aria-labelledby="date-label" data-skeleton="true">
                    <div class="rbf-field-wrapper">
                        <label id="date-label" for="rbf-date"><?php echo esc_html(rbf_translate_string('Data')); ?><span class="rbf-required-indicator">*</span></label>
                        
                        <!-- Skeleton for calendar loading -->
                        <div class="rbf-skeleton rbf-skeleton-calendar" aria-hidden="true"></div>
                        
                        <!-- Actual calendar input (initially hidden) -->
                        <div class="rbf-fade-in">
                            <input id="rbf-date" name="rbf_data" required aria-describedby="date-help" role="combobox" aria-expanded="false" aria-haspopup="grid" aria-label="<?php echo esc_attr(rbf_translate_string('Seleziona data prenotazione')); ?>" placeholder="<?php echo esc_attr(rbf_translate_string('es. 2024-12-31')); ?>">
                            <div id="rbf-date-error" class="rbf-field-error"></div>
                            <small id="date-help" class="rbf-help-text"><?php echo esc_html(rbf_translate_string('Seleziona una data dal calendario. Usa i tasti freccia per navigare, Invio per selezionare')); ?></small>
                            <div class="rbf-exception-legend" style="display:none;" role="group" aria-labelledby="legend-label" aria-hidden="true">
                                <div id="legend-label" class="sr-only"><?php echo esc_html(rbf_translate_string('Legenda calendario')); ?></div>
                                <div class="rbf-exception-legend-item rbf-legend-availability-item">
                                    <span class="rbf-legend-availability-swatch rbf-availability-available" role="img" aria-label="<?php echo esc_attr(rbf_translate_string('Indicatore disponibilità: Disponibile')); ?>"></span>
                                    <span><?php echo esc_html(rbf_translate_string('Disponibile')); ?></span>
                                </div>
                                <div class="rbf-exception-legend-item rbf-legend-availability-item">
                                    <span class="rbf-legend-availability-swatch rbf-availability-limited" role="img" aria-label="<?php echo esc_attr(rbf_translate_string('Indicatore disponibilità: Quasi al completo')); ?>"></span>
                                    <span><?php echo esc_html(rbf_translate_string('Quasi al completo')); ?></span>
                                </div>
                                <div class="rbf-exception-legend-item rbf-legend-availability-item">
                                    <span class="rbf-legend-availability-swatch rbf-availability-full" role="img" aria-label="<?php echo esc_attr(rbf_translate_string('Indicatore disponibilità: Completo')); ?>"></span>
                                    <span><?php echo esc_html(rbf_translate_string('Completo')); ?></span>
                                </div>
                                <div class="rbf-exception-legend-item">
                                    <span class="rbf-exception-legend-dot" style="background: #20c997;" role="img" aria-label="<?php echo esc_attr(rbf_translate_string('Indicatore evento speciale')); ?>"></span>
                                    <span><?php echo esc_html(rbf_translate_string('Eventi Speciali')); ?></span>
                                </div>
                                <div class="rbf-exception-legend-item">
                                    <span class="rbf-exception-legend-dot" style="background: #0d6efd;" role="img" aria-label="<?php echo esc_attr(rbf_translate_string('Indicatore orari estesi')); ?>"></span>
                                    <span><?php echo esc_html(rbf_translate_string('Orari Estesi')); ?></span>
                                </div>
                                <div class="rbf-exception-legend-item">
                                    <span class="rbf-exception-legend-dot" style="background: #fd7e14;" role="img" aria-label="<?php echo esc_attr(rbf_translate_string('Indicatore festività')); ?>"></span>
                                    <span><?php echo esc_html(rbf_translate_string('Festività')); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="step-time" class="rbf-step" role="group" aria-labelledby="time-label" data-skeleton="true">
                    <div class="rbf-field-wrapper">
                        <label id="time-label" for="rbf-time"><?php echo esc_html(rbf_translate_string('Orario')); ?><span class="rbf-required-indicator">*</span></label>
                        
                        <!-- Skeleton for time slot loading -->
                        <div class="rbf-skeleton rbf-skeleton-select" aria-hidden="true"></div>
                        
                        <!-- Actual time select (initially hidden) -->
                        <div class="rbf-fade-in">
                            <select id="rbf-time" name="rbf_orario" required aria-describedby="time-help">
                                <option value=""><?php echo esc_html(rbf_translate_string('Seleziona un orario disponibile')); ?></option>
                                <?php if (!empty($fallback_time_map)) : ?>
                                    <?php foreach ($fallback_time_map as $meal_id => $fallback_config) :
                                        $meal_label = $fallback_config['label'] ?? $meal_id;
                                        $time_slots = $fallback_config['times'] ?? [];
                                        if (empty($time_slots)) {
                                            continue;
                                        }
                                        ?>
                                        <optgroup label="<?php echo esc_attr($meal_label); ?>">
                                            <?php foreach ($time_slots as $time_slot) : ?>
                                                <option value="<?php echo esc_attr($meal_id . '|' . $time_slot); ?>"><?php echo esc_html($time_slot); ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <div id="rbf-time-error" class="rbf-field-error"></div>
                            <small id="time-help" class="rbf-help-text"><?php echo esc_html(rbf_translate_string('Seleziona un orario disponibile')); ?></small>
                        </div>
                    </div>
                </div>

                <div id="step-people" class="rbf-step" role="group" aria-labelledby="people-label" data-skeleton="true">
                    <div class="rbf-field-wrapper">
                        <label id="people-label"><?php echo esc_html(rbf_translate_string('Persone')); ?><span class="rbf-required-indicator">*</span></label>
                        
                        <!-- Skeleton for people selector -->
                        <div class="rbf-skeleton-people-selector" aria-hidden="true">
                            <div class="rbf-skeleton rbf-skeleton-button"></div>
                            <div class="rbf-skeleton rbf-skeleton-input" style="width: 4rem; margin: 0 0.5rem;"></div>
                            <div class="rbf-skeleton rbf-skeleton-button"></div>
                        </div>
                        
                        <!-- Actual people selector (initially hidden) -->
                        <div class="rbf-fade-in">
                            <div class="rbf-people-selector" role="group" aria-labelledby="people-label" aria-describedby="people-instructions">
                                <button type="button" id="rbf-people-minus" aria-label="<?php echo esc_attr(rbf_translate_string('Diminuisci numero persone')); ?>" tabindex="0">-</button>
                                <input type="number" id="rbf-people" name="rbf_persone" value="1" min="1" required aria-describedby="people-help" aria-label="<?php echo esc_attr(rbf_translate_string('Numero di persone')); ?>" role="spinbutton" aria-valuemin="1" aria-valuenow="1">
                                <button type="button" id="rbf-people-plus" aria-label="<?php echo esc_attr(rbf_translate_string('Aumenta numero persone')); ?>" tabindex="0">+</button>
                            </div>
                            <div id="rbf-people-error" class="rbf-field-error"></div>
                            <div id="people-instructions" class="sr-only"><?php echo esc_html(rbf_translate_string('Usa i pulsanti + e - oppure digita il numero di persone')); ?></div>
                            <small id="people-help" class="rbf-help-text"><?php echo esc_html(rbf_translate_string('Usa i pulsanti + e - per modificare')); ?></small>
                        </div>
                    </div>
                </div>

                <div id="step-details" class="rbf-step" role="group" aria-labelledby="details-label" data-skeleton="true">
                    <h3 id="details-label" class="rbf-section-title"><?php echo esc_html(rbf_translate_string('I tuoi dati')); ?></h3>
                    
                    <!-- Skeleton for form fields -->
                    <div class="rbf-skeleton-fields" aria-hidden="true">
                        <div class="rbf-skeleton rbf-skeleton-text short"></div>
                        <div class="rbf-skeleton rbf-skeleton-input"></div>
                        <div class="rbf-skeleton rbf-skeleton-text short"></div>
                        <div class="rbf-skeleton rbf-skeleton-input"></div>
                        <div class="rbf-skeleton rbf-skeleton-text short"></div>
                        <div class="rbf-skeleton rbf-skeleton-input"></div>
                        <div class="rbf-skeleton rbf-skeleton-text short"></div>
                        <div class="rbf-skeleton rbf-skeleton-input"></div>
                        <div class="rbf-skeleton rbf-skeleton-text short"></div>
                        <div class="rbf-skeleton rbf-skeleton-textarea"></div>
                        <div class="rbf-skeleton rbf-skeleton-checkbox"></div>
                        <div class="rbf-skeleton rbf-skeleton-checkbox"></div>
                    </div>
                    
                    <!-- Actual form fields (initially hidden) -->
                    <div class="rbf-fade-in">
                        <div class="rbf-field-wrapper">
                            <label for="rbf-name"><?php echo esc_html(rbf_translate_string('Nome')); ?><span class="rbf-required-indicator">*</span></label>
                            <input type="text" id="rbf-name" name="rbf_nome" required aria-required="true">
                            <div id="rbf-name-error" class="rbf-field-error"></div>
                        </div>
                        
                        <div class="rbf-field-wrapper">
                            <label for="rbf-surname"><?php echo esc_html(rbf_translate_string('Cognome')); ?><span class="rbf-required-indicator">*</span></label>
                            <input type="text" id="rbf-surname" name="rbf_cognome" required aria-required="true">
                            <div id="rbf-surname-error" class="rbf-field-error"></div>
                        </div>
                        
                        <div class="rbf-field-wrapper">
                            <label for="rbf-email"><?php echo esc_html(rbf_translate_string('Email')); ?><span class="rbf-required-indicator">*</span></label>
                            <input type="email" id="rbf-email" name="rbf_email" required aria-required="true">
                            <div id="rbf-email-error" class="rbf-field-error"></div>
                        </div>
                        
                        <div class="rbf-field-wrapper">
                            <label for="rbf-tel"><?php echo esc_html(rbf_translate_string('Telefono')); ?><span class="rbf-required-indicator">*</span></label>
                            <div class="rbf-phone-wrapper" data-flag="<?php echo esc_attr($default_phone_prefix['code'] ?? 'it'); ?>">
                                <div class="rbf-phone-prefix">
                                <div
                                    class="rbf-phone-flag iti__flag iti__<?php echo esc_attr($default_phone_prefix['code'] ?? 'it'); ?>"
                                    data-flag="<?php echo esc_attr($default_phone_prefix['code'] ?? 'it'); ?>"
                                    data-flag-label="<?php echo esc_attr($default_phone_prefix['label'] ?? ''); ?>"
                                    aria-hidden="true"
                                ></div>
                                    <span id="rbf-phone-prefix-label" class="sr-only"><?php echo esc_html(rbf_translate_string('Seleziona prefisso internazionale')); ?></span>
                                    <select id="rbf-phone-prefix" name="rbf_phone_prefix" aria-labelledby="rbf-phone-prefix-label">
                                        <?php foreach ($phone_prefixes as $prefix) :
                                            $is_default = !empty($prefix['default']);
                                            $country_code = esc_attr($prefix['code']);
                                            $option_label = esc_html($prefix['label']);
                                            $option_prefix = esc_attr($prefix['prefix']);
                                            $option_example = !empty($prefix['example']) ? esc_attr($prefix['example']) : '';
                                            $selected = ($default_phone_prefix['code'] ?? '') === $prefix['code'];
                                            ?>
                                            <option value="<?php echo $option_prefix; ?>" data-country="<?php echo $country_code; ?>" data-label="<?php echo $option_label; ?>" <?php if ($option_example) : ?>data-example="<?php echo $option_example; ?>"<?php endif; ?> <?php selected($selected || $is_default); ?>>
                                                <?php echo $option_label . ' ' . $option_prefix; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <input type="tel" id="rbf-tel" name="rbf_tel_number" inputmode="tel" autocomplete="tel" required aria-required="true" placeholder="<?php echo esc_attr(rbf_translate_string('Inserisci il numero di telefono')); ?>">
                            </div>
                            <div id="rbf-tel-error" class="rbf-field-error"></div>
                        </div>
                        
                        <div class="rbf-field-wrapper">
                            <label for="rbf-notes"><?php echo esc_html(rbf_translate_string('Allergie/Note')); ?></label>
                            <textarea id="rbf-notes" name="rbf_allergie" placeholder="<?php echo esc_attr(rbf_translate_string('Inserisci eventuali allergie o note particolari...')); ?>"></textarea>
                        </div>

                        <div class="rbf-checkbox-group" role="group" aria-labelledby="consent-label">
                            <h4 id="consent-label" class="rbf-consent-title"><?php echo esc_html(rbf_translate_string('Consensi')); ?></h4>
                            <div class="rbf-field-wrapper">
                                <label>
                                    <input type="checkbox" id="rbf-privacy" name="rbf_privacy" value="yes" required aria-required="true">
                                    <span>
                                        <?php
                                        $privacy_policy_text = '';
                                        if (!empty($privacy_policy_url)) {
                                            $privacy_policy_text = sprintf(
                                                rbf_translate_string('Acconsento al trattamento dei dati secondo l\'<a href="%s" target="_blank" rel="noopener">Informativa sulla Privacy</a>'),
                                                esc_url($privacy_policy_url)
                                            );
                                        } else {
                                            $privacy_policy_text = rbf_translate_string('Acconsento al trattamento dei dati secondo l\'Informativa sulla Privacy');
                                        }
                                        echo wp_kses_post($privacy_policy_text);
                                        ?>
                                        <span class="rbf-required-indicator">*</span>
                                    </span>
                                </label>
                                <div id="rbf-privacy-error" class="rbf-field-error"></div>
                            </div>
                            <label>
                                <input type="checkbox" id="rbf-marketing" name="rbf_marketing" value="yes">
                                <span><?php echo rbf_translate_string('Acconsento a ricevere comunicazioni promozionali via email e/o messaggi riguardanti eventi, offerte o novità.'); ?></span>
                            </label>
                        </div>
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
                
                <!-- Anti-bot protection fields -->
                <input type="hidden" name="rbf_form_timestamp" value="<?php echo esc_attr(time()); ?>">
                <!-- Honeypot field - invisible to users but visible to bots -->
                <div style="position: absolute; left: -9999px; visibility: hidden;" aria-hidden="true">
                    <label for="rbf_website">Website (leave blank):</label>
                    <input type="text" name="rbf_website" id="rbf_website" value="" tabindex="-1" autocomplete="off">
                </div>
                <button id="rbf-submit" type="submit"><?php echo esc_html(rbf_translate_string('Prenota')); ?></button>
            </form>
        <?php endif; ?>
        
        <?php
        // Add reCAPTCHA v3 script if configured
        $site_key = $options['recaptcha_site_key'] ?? '';
        if (!empty($site_key)) : ?>
            <script src="https://www.google.com/recaptcha/api.js?render=<?php echo esc_attr($site_key); ?>" async defer></script>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Initialize reCAPTCHA when ready
                if (typeof grecaptcha !== 'undefined') {
                    grecaptcha.ready(function() {
                        // Add reCAPTCHA token to form submission
                        var form = document.getElementById('rbf-form');
                        if (form) {
                            form.addEventListener('submit', function(e) {
                                e.preventDefault();
                                
                                grecaptcha.execute('<?php echo esc_js($site_key); ?>', {action: 'booking_submit'})
                                .then(function(token) {
                                    // Add token to form
                                    var tokenInput = document.createElement('input');
                                    tokenInput.type = 'hidden';
                                    tokenInput.name = 'g-recaptcha-response';
                                    tokenInput.value = token;
                                    form.appendChild(tokenInput);
                                    
                                    // Submit form
                                    form.submit();
                                })
                                .catch(function(error) {
                                    console.error('reCAPTCHA error:', error);
                                    // Submit anyway to avoid blocking legitimate users
                                    form.submit();
                                });
                            });
                        }
                    });
                }
            });
            </script>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Helper function to detect source channel classification
 */
function rbf_detect_source($data = []) {
    // Validate UTM parameters first for security
    $validated_data = rbf_validate_utm_parameters($data);
    
    $utm_source   = strtolower(trim($validated_data['utm_source'] ?? ''));
    $utm_medium   = strtolower(trim($validated_data['utm_medium'] ?? ''));
    $utm_campaign = trim($validated_data['utm_campaign'] ?? '');
    $gclid        = trim($validated_data['gclid'] ?? '');
    $fbclid       = trim($validated_data['fbclid'] ?? '');
    $referrer     = strtolower(trim($data['referrer'] ?? ''));

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
    if ($cached !== false) {
        return $cached === null ? null : (int) $cached;
    }

    $options = rbf_get_settings();
    
    // Try to get capacity from configurable meals first
    $meal_config = rbf_get_meal_config($slot);
    if ($meal_config) {
        // Use effective capacity with overbooking
        $total = rbf_get_effective_capacity($slot);
    } else {
        // Fallback to legacy capacity settings
        $total = (int) ($options['capienza_'.$slot] ?? 0);
    }

    // Treat zero capacity as unlimited to avoid blocking services like aperitivo
    if ($total <= 0) {
        set_transient($transient_key, null, HOUR_IN_SECONDS);
        return null;
    }

    $spots_taken = rbf_sum_active_bookings($date, $slot);
    $remaining = (int) max(0, $total - (int) $spots_taken);
    set_transient($transient_key, $remaining, HOUR_IN_SECONDS);
    return $remaining;
}

/**
 * Get closed specific dates and calendar exceptions
 */
function rbf_get_closed_specific($options = null) {
    if (is_null($options)) $options = rbf_get_settings();
    $closed_dates_str = $options['closed_dates'] ?? '';
    $closed_items = array_filter(array_map('trim', explode("\n", $closed_dates_str)));
    
    $singles = []; 
    $ranges = [];
    $exceptions = [];
    
    foreach ($closed_items as $item) {
        // Handle new exception format: date|type|hours|description
        if (strpos($item, '|') !== false) {
            $parts = array_map('trim', explode('|', $item));
            $date = $parts[0];
            $type = $parts[1] ?? 'closure';
            $hours = $parts[2] ?? '';
            $description = $parts[3] ?? '';
            
            if (DateTime::createFromFormat('Y-m-d', $date) !== false) {
                $exception = [
                    'date' => $date,
                    'type' => $type,
                    'hours' => $hours,
                    'description' => $description
                ];
                $exceptions[] = $exception;
                
                // For closure and holiday types, also add to singles for backward compatibility
                if (in_array($type, ['closure', 'holiday'])) {
                    $singles[] = $date;
                }
            }
        } 
        // Handle legacy date ranges: 2024-12-24 - 2024-12-26
        else if (strpos($item, ' - ') !== false) {
            list($start, $end) = array_map('trim', explode(' - ', $item, 2));
            $start_ok = DateTime::createFromFormat('Y-m-d', $start) !== false;
            $end_ok = DateTime::createFromFormat('Y-m-d', $end) !== false;
            if ($start_ok && $end_ok) $ranges[] = ['from'=>$start, 'to'=>$end];
        } 
        // Handle legacy single dates: 2024-12-25
        else {
            if (DateTime::createFromFormat('Y-m-d', $item) !== false) {
                $singles[] = $item;
                // Also add as closure exception for consistency
                $exceptions[] = [
                    'date' => $item,
                    'type' => 'closure',
                    'hours' => '',
                    'description' => ''
                ];
            }
        }
    }
    
    return [
        'singles' => $singles, 
        'ranges' => $ranges,
        'exceptions' => $exceptions
    ];
}

/**
 * Get calendar exceptions for a specific date
 */
function rbf_get_date_exceptions($date, $options = null) {
    $closed_data = rbf_get_closed_specific($options);
    $date_exceptions = [];
    
    foreach ($closed_data['exceptions'] as $exception) {
        if ($exception['date'] === $date) {
            $date_exceptions[] = $exception;
        }
    }
    
    return $date_exceptions;
}

/**
 * Check if a date has special hours due to exceptions
 */
function rbf_get_special_hours_for_date($date, $meal = null, $options = null) {
    $exceptions = rbf_get_date_exceptions($date, $options);
    
    foreach ($exceptions as $exception) {
        if (in_array($exception['type'], ['special', 'extended']) && !empty($exception['hours'])) {
            return $exception['hours'];
        }
    }
    
    return null;
}

/**
 * Get predefined special occasion labels
 * 
 * @return array Associative array of special occasion types and their labels
 */
function rbf_get_special_occasion_labels() {
    $labels = [
        'anniversary' => rbf_translate_string('Anniversario'),
        'birthday' => rbf_translate_string('Compleanno'),
        'celebration' => rbf_translate_string('Celebrazione'),
        'romantic' => rbf_translate_string('Cena Romantica'),
        'business' => rbf_translate_string('Cena di Lavoro'),
        'family' => rbf_translate_string('Riunione Famiglia'),
        'graduation' => rbf_translate_string('Laurea'),
        'engagement' => rbf_translate_string('Fidanzamento'),
        'proposal' => rbf_translate_string('Proposta di Matrimonio'),
        'wedding' => rbf_translate_string('Matrimonio'),
        'holiday' => rbf_translate_string('Festività'),
        'special' => rbf_translate_string('Occasione Speciale')
    ];
    
    // Allow filtering for customization
    return apply_filters('rbf_special_occasion_labels', $labels);
}

/**
 * Additional shortcodes for common special occasions
 */

// Anniversary booking form
add_shortcode('anniversary_booking_form', function($atts = []) {
    $atts['special_type'] = 'anniversary';
    if (empty($atts['special_label'])) {
        $atts['special_label'] = rbf_translate_string('Anniversario');
    }
    return rbf_render_booking_form($atts);
});

// Birthday booking form
add_shortcode('birthday_booking_form', function($atts = []) {
    $atts['special_type'] = 'birthday';
    if (empty($atts['special_label'])) {
        $atts['special_label'] = rbf_translate_string('Compleanno');
    }
    return rbf_render_booking_form($atts);
});

// Romantic dinner booking form  
add_shortcode('romantic_booking_form', function($atts = []) {
    $atts['special_type'] = 'romantic';
    if (empty($atts['special_label'])) {
        $atts['special_label'] = rbf_translate_string('Cena Romantica');
    }
    return rbf_render_booking_form($atts);
});

// Celebration booking form
add_shortcode('celebration_booking_form', function($atts = []) {
    $atts['special_type'] = 'celebration';
    if (empty($atts['special_label'])) {
        $atts['special_label'] = rbf_translate_string('Celebrazione');
    }
    return rbf_render_booking_form($atts);
});

// Business dinner booking form
add_shortcode('business_booking_form', function($atts = []) {
    $atts['special_type'] = 'business';
    if (empty($atts['special_label'])) {
        $atts['special_label'] = rbf_translate_string('Cena di Lavoro');
    }
    return rbf_render_booking_form($atts);
});

// Proposal booking form
add_shortcode('proposal_booking_form', function($atts = []) {
    $atts['special_type'] = 'proposal';
    if (empty($atts['special_label'])) {
        $atts['special_label'] = rbf_translate_string('Proposta di Matrimonio');
    }
    return rbf_render_booking_form($atts);
});

// Generic special occasion booking form
add_shortcode('special_booking_form', function($atts = []) {
    $atts['special_type'] = 'special';
    if (empty($atts['special_label'])) {
        $atts['special_label'] = rbf_translate_string('Occasione Speciale');
    }
    return rbf_render_booking_form($atts);
});

/**
 * AJAX handler for calendar availability data
 */
add_action('wp_ajax_rbf_get_calendar_availability', 'rbf_ajax_get_calendar_availability');
add_action('wp_ajax_nopriv_rbf_get_calendar_availability', 'rbf_ajax_get_calendar_availability');

function rbf_normalize_cache_key_part($value) {
    $value = is_scalar($value) ? strtolower((string) $value) : '';
    $value = preg_replace('/[^a-z0-9\-]+/', '-', $value);
    $value = trim($value, '-');

    return $value !== '' ? $value : 'all';
}

function rbf_build_calendar_cache_key($meal, $start_date, $end_date) {
    return sprintf(
        'rbf_cal_avail_%s_%s_%s',
        rbf_normalize_cache_key_part($meal),
        rbf_normalize_cache_key_part($start_date),
        rbf_normalize_cache_key_part($end_date)
    );
}

function rbf_build_times_cache_key($meal, $date, $people) {
    return sprintf(
        'rbf_times_%s_%s_%s',
        rbf_normalize_cache_key_part($meal),
        rbf_normalize_cache_key_part($date),
        rbf_normalize_cache_key_part(absint($people))
    );
}

function rbf_ajax_get_calendar_availability() {
    global $wpdb;

    // Verify nonce
    if (!isset($_POST['_ajax_nonce']) || !wp_verify_nonce($_POST['_ajax_nonce'], 'rbf_ajax_nonce')) {
        wp_send_json_error([
            'message' => rbf_translate_string('Controllo di sicurezza fallito'),
            'code' => 'nonce_failed'
        ]);
        return;
    }
    
    $start_date = sanitize_text_field($_POST['start_date'] ?? '');
    $end_date = sanitize_text_field($_POST['end_date'] ?? '');
    $meal = sanitize_text_field($_POST['meal'] ?? '');
    
    if (empty($start_date) || empty($end_date) || empty($meal)) {
        wp_send_json_error([
            'message' => rbf_translate_string('Parametri mancanti'),
            'code' => 'missing_params'
        ]);
        return;
    }
    
    $timezone = rbf_wp_timezone();
    $start_date_obj = DateTimeImmutable::createFromFormat('!Y-m-d', $start_date, $timezone);
    $end_date_obj = DateTimeImmutable::createFromFormat('!Y-m-d', $end_date, $timezone);
    $current_date = DateTimeImmutable::createFromFormat('!Y-m-d', $start_date, $timezone);

    if (!$start_date_obj || !$end_date_obj || !$current_date) {
        wp_send_json_error([
            'message' => rbf_translate_string('Formato data non valido'),
            'code' => 'invalid_date_format'
        ]);
        return;
    }

    // Check for reasonable date range (max 3 months)
    $date_diff = $start_date_obj->diff($end_date_obj);
    
    if ($date_diff->days > 90) {
        wp_send_json_error([
            'message' => rbf_translate_string('Intervallo di date troppo ampio'),
            'code' => 'date_range_too_wide'
        ]);
        return;
    }
    
    // Check for cache first
    $cache_key = rbf_build_calendar_cache_key($meal, $start_date, $end_date);
    $cached_data = get_transient($cache_key);
    
    if ($cached_data !== false) {
        wp_send_json_success($cached_data);
        return;
    }
    
    $availability_data = [];
    $debug_enabled = (defined('WP_DEBUG') && WP_DEBUG) || (defined('RBF_FORCE_LOG') && RBF_FORCE_LOG);
    $timer_start = $debug_enabled ? microtime(true) : null;
    $query_start = ($debug_enabled && isset($wpdb) && is_object($wpdb) && isset($wpdb->num_queries))
        ? (int) $wpdb->num_queries
        : null;

    try {
        $bulk_bookings = rbf_get_bulk_bookings_for_meal($meal, $start_date_obj, $end_date_obj);
        $total_capacity = rbf_get_effective_capacity($meal);
        $has_finite_capacity = $total_capacity > 0;
        $range_days = $date_diff->days + 1;
        $included_days = 0;

        // Generate availability data for each date in the range
        while ($current_date <= $end_date_obj) {
            $date_str = $current_date->format('Y-m-d');

            // Check if meal is available on this day
            if (rbf_is_meal_available_on_day($meal, $date_str)) {
                $booked_people = isset($bulk_bookings[$date_str]) ? (int) $bulk_bookings[$date_str] : 0;
                $remaining_capacity = null;
                $occupancy = 0.0;

                if ($has_finite_capacity && $total_capacity > 0) {
                    $remaining_capacity = max(0, $total_capacity - $booked_people);
                    $occupancy = $total_capacity > 0
                        ? ($booked_people / $total_capacity) * 100
                        : 0.0;
                }

                $level = 'available';
                if ($has_finite_capacity) {
                    if ($occupancy >= 100) {
                        $level = 'full';
                    } elseif ($occupancy >= 70) {
                        $level = 'limited';
                    }
                }

                $availability_data[$date_str] = [
                    'level' => $level,
                    'occupancy' => round($occupancy, 1),
                    'remaining' => $has_finite_capacity ? (int) $remaining_capacity : null,
                    'total' => $has_finite_capacity ? (int) $total_capacity : null,
                ];

                $remaining_key = 'rbf_avail_' . $date_str . '_' . $meal;
                set_transient($remaining_key, $has_finite_capacity ? (int) $remaining_capacity : null, HOUR_IN_SECONDS);

                $included_days++;
            }

            $current_date = $current_date->modify('+1 day');
        }

        // Cache results for 10 minutes
        set_transient($cache_key, $availability_data, 10 * MINUTE_IN_SECONDS);

        if ($debug_enabled) {
            $elapsed_ms = $timer_start !== null ? (microtime(true) - $timer_start) * 1000 : 0.0;
            $query_delta = ($query_start !== null && isset($wpdb) && is_object($wpdb) && isset($wpdb->num_queries))
                ? ((int) $wpdb->num_queries - $query_start)
                : null;

            rbf_log(sprintf(
                'RBF Calendar availability for meal "%s" (%s-%s): %d/%d days processed in %.2fms (queries: %s)',
                $meal,
                $start_date,
                $end_date,
                $included_days,
                $range_days,
                $elapsed_ms,
                $query_delta === null ? 'n/a' : (string) $query_delta
            ));
        }

        wp_send_json_success($availability_data);

    } catch (Exception $e) {
        rbf_log('Calendar availability error: ' . $e->getMessage());
        wp_send_json_error([
            'message' => rbf_translate_string('Errore nel caricamento della disponibilità'),
            'code' => 'availability_error'
        ]);
    }
}

/**
 * AJAX handler for time slot availability
 */
add_action('wp_ajax_rbf_get_availability', 'rbf_ajax_get_availability');
add_action('wp_ajax_nopriv_rbf_get_availability', 'rbf_ajax_get_availability');

function rbf_ajax_get_availability() {
    // Verify nonce
    if (!isset($_POST['_ajax_nonce']) || !wp_verify_nonce($_POST['_ajax_nonce'], 'rbf_ajax_nonce')) {
        wp_send_json_error([
            'message' => rbf_translate_string('Controllo di sicurezza fallito'),
            'code' => 'nonce_failed'
        ]);
        return;
    }
    
    $date = sanitize_text_field($_POST['date'] ?? '');
    $meal = sanitize_text_field($_POST['meal'] ?? '');
    $people = absint($_POST['people'] ?? 2); // Accept people count for better accuracy
    
    if (empty($date) || empty($meal)) {
        wp_send_json_error([
            'message' => rbf_translate_string('Parametri mancanti'),
            'code' => 'missing_params'
        ]);
        return;
    }
    
    // Check if the requested meal is configured and enabled
    $meal_config = rbf_get_meal_config($meal);
    if (!$meal_config) {
        wp_send_json_error([
            'message' => rbf_translate_string('Il servizio richiesto non è configurato o non è disponibile'),
            'code' => 'meal_not_configured'
        ]);
        return;
    }
    
    $timezone = rbf_wp_timezone();
    // Validate date format with WordPress timezone context
    $date_obj = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $timezone);
    if (!$date_obj) {
        wp_send_json_error([
            'message' => rbf_translate_string('Formato data non valido'),
            'code' => 'invalid_date_format'
        ]);
        return;
    }

    // Check if date is not in the past
    $today = (new DateTimeImmutable('now', $timezone))->setTime(0, 0, 0);

    if ($date_obj < $today) {
        wp_send_json_error([
            'message' => rbf_translate_string('Non è possibile prenotare per date passate'),
            'code' => 'date_in_past'
        ]);
        return;
    }
    
    // Check for cache first
    $cache_key = rbf_build_times_cache_key($meal, $date, $people);
    $cached_data = get_transient($cache_key);

    if ($cached_data !== false) {
        $filtered_cached_data = $cached_data;

        if (is_array($cached_data) && isset($cached_data['available_times']) && is_array($cached_data['available_times'])) {
            $filtered_times = [];

            foreach ($cached_data['available_times'] as $time_entry) {
                if (!is_array($time_entry)) {
                    $filtered_times[] = $time_entry;
                    continue;
                }

                $raw_time = $time_entry['value'] ?? $time_entry['time'] ?? null;

                if ($raw_time && !rbf_is_buffer_time_valid($date, $raw_time, $meal, $people)) {
                    continue;
                }

                $filtered_times[] = $time_entry;
            }

            if (count($filtered_times) !== count($cached_data['available_times'])) {
                $filtered_cached_data['available_times'] = array_values($filtered_times);

                if (empty($filtered_times)) {
                    $filtered_cached_data['message'] = rbf_translate_string('Nessun orario disponibile per questa data');
                }
            }
        }

        if ($filtered_cached_data !== $cached_data) {
            set_transient($cache_key, $filtered_cached_data, 5 * MINUTE_IN_SECONDS);
        }

        wp_send_json_success($filtered_cached_data);
        return;
    }
    
    try {
        // Check if meal is available on this day
        if (!rbf_is_meal_available_on_day($meal, $date)) {
            wp_send_json_error([
                'message' => rbf_translate_string('Servizio non disponibile per questa data'),
                'code' => 'meal_not_available'
            ]);
            return;
        }
        
        // Get available time slots for the date and meal
        $available_times = rbf_get_available_time_slots($date, $meal, $people);
        
        if (empty($available_times)) {
            $response_data = [
                'available_times' => [],
                'message' => rbf_translate_string('Nessun orario disponibile per questa data')
            ];
            
            // Cache negative results for a shorter time (5 minutes)
            set_transient($cache_key, $response_data, 5 * MINUTE_IN_SECONDS);
            
            wp_send_json_success($response_data);
            return;
        }
        
        // Format times for the frontend
        $formatted_times = [];
        $remaining_capacity = rbf_get_remaining_capacity($date, $meal);
        $total_capacity = rbf_get_effective_capacity($meal);
        $has_finite_capacity = $total_capacity > 0;
        
        foreach ($available_times as $time_data) {
            $time = $time_data['time'];
            $display = $time_data['display'] ?? $time;
            
            $formatted_times[] = [
                'time' => $display,
                'slot' => $meal, // The meal/slot identifier
                'remaining' => $has_finite_capacity ? $remaining_capacity : null,
                'value' => $time // Add raw time value for form submission
            ];
        }

        $response_data = [
            'available_times' => $formatted_times,
            'message' => '',
            'total_capacity' => $has_finite_capacity ? $total_capacity : null,
            'requested_people' => $people
        ];
        
        // Cache positive results for 5 minutes
        set_transient($cache_key, $response_data, 5 * MINUTE_IN_SECONDS);
        
        wp_send_json_success($response_data);
        
    } catch (Exception $e) {
        rbf_log('Time availability error: ' . $e->getMessage());
        wp_send_json_error([
            'message' => rbf_translate_string('Errore nel caricamento degli orari disponibili'),
            'code' => 'time_availability_error'
        ]);
    }
}

/**
 * Clear calendar cache when bookings are modified
 */
function rbf_clear_calendar_cache($date = null, $meal = null) {
    $patterns = [];

    if ($date && $meal) {
        $date = sanitize_text_field($date);
        $meal = sanitize_text_field($meal);

        $normalized_meal = rbf_normalize_cache_key_part($meal);
        $normalized_date = rbf_normalize_cache_key_part($date);

        $patterns = [
            '_transient_rbf_cal_avail_' . $normalized_meal . '_',
            '_transient_timeout_rbf_cal_avail_' . $normalized_meal . '_',
            '_transient_rbf_times_' . $normalized_meal . '_' . $normalized_date . '_',
            '_transient_timeout_rbf_times_' . $normalized_meal . '_' . $normalized_date . '_',
            '_transient_rbf_avail_' . $date . '_' . $meal,
            '_transient_timeout_rbf_avail_' . $date . '_' . $meal,
        ];
    } else {
        $patterns = rbf_get_global_availability_transient_patterns();
    }

    rbf_delete_transients_like($patterns);
}

// Hook into booking creation/modification to clear cache
add_action('rbf_booking_created', function($booking_id, $booking_context = []) {
    $date = '';
    $meal = '';

    if (is_array($booking_context)) {
        $date = $booking_context['date'] ?? '';
        $meal = $booking_context['meal'] ?? '';
    }

    if (empty($date) || empty($meal)) {
        $date = get_post_meta($booking_id, 'rbf_data', true);
        $meal = get_post_meta($booking_id, 'rbf_meal', true);
    }

    if ($date && $meal) {
        rbf_clear_calendar_cache($date, $meal);
    }
}, 10, 2);

add_action('rbf_booking_status_changed', function($booking_id, $old_status, $new_status, $note = '') {
    $date = get_post_meta($booking_id, 'rbf_data', true);
    $meal = get_post_meta($booking_id, 'rbf_meal', true) ?: get_post_meta($booking_id, 'rbf_orario', true);

    if ($new_status === 'cancelled' && function_exists('rbf_remove_table_assignment')) {
        rbf_remove_table_assignment($booking_id);
    }

    $release_attempted = false;
    $release_success = false;
    $cache_cleared = false;

    if ($new_status === 'cancelled' && $old_status !== 'cancelled' && $date && $meal) {
        $people = intval(get_post_meta($booking_id, 'rbf_persone', true));

        if ($people > 0) {
            $release_attempted = true;
            $release_success = (bool) rbf_release_slot_capacity($date, $meal, $people);

            if (!$release_success) {
                $sync_attempted = false;

                if (function_exists('rbf_sync_slot_version')) {
                    $sync_attempted = true;
                    rbf_sync_slot_version($date, $meal);
                }

                $release_success = (bool) rbf_release_slot_capacity($date, $meal, $people);

                if (!$release_success) {
                    $log_message = sprintf(
                        'RBF: Failed to release capacity for cancelled booking after recovery attempt. Booking ID: %d, Date: %s, Meal: %s, People: %d.%s',
                        $booking_id,
                        $date,
                        $meal,
                        $people,
                        $sync_attempted ? '' : ' rbf_sync_slot_version() unavailable.'
                    );
                    rbf_log($log_message);

                    $notice_message = sprintf(
                        rbf_translate_string('Verifica manuale: impossibile rilasciare la capienza per la prenotazione cancellata (ID: %d, Data: %s, Servizio: %s).'),
                        $booking_id,
                        $date,
                        $meal
                    );
                    rbf_add_admin_notice($notice_message, 'error');
                }
            }

            if ($release_success) {
                delete_transient('rbf_avail_' . $date . '_' . $meal);
                rbf_clear_calendar_cache($date, $meal);
                $cache_cleared = true;
            }
        }
    }

    if ($date && $meal && !$cache_cleared) {
        if (!$release_attempted || $release_success) {
            rbf_clear_calendar_cache($date, $meal);
        }
    }
}, 10, 4);

add_action('trashed_post', 'rbf_release_booking_resources_on_delete');
add_action('before_delete_post', 'rbf_release_booking_resources_on_delete');
add_action('untrashed_post', 'rbf_reset_booking_release_flag');

function rbf_release_booking_resources_on_delete($post_id) {
    $post_id = absint($post_id);
    if ($post_id <= 0) {
        return;
    }

    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    if (get_post_type($post_id) !== 'rbf_booking') {
        return;
    }

    $release_flag_key = '_rbf_booking_release_processed';

    if (get_post_meta($post_id, $release_flag_key, true)) {
        return;
    }

    if (function_exists('rbf_remove_table_assignment')) {
        rbf_remove_table_assignment($post_id);
    }

    $booking_date_raw = get_post_meta($post_id, 'rbf_data', true);
    $booking_meal_raw = get_post_meta($post_id, 'rbf_meal', true);
    if (empty($booking_meal_raw)) {
        $booking_meal_raw = get_post_meta($post_id, 'rbf_orario', true);
    }

    $booking_date = $booking_date_raw ? sanitize_text_field($booking_date_raw) : '';
    $booking_meal = $booking_meal_raw ? sanitize_text_field($booking_meal_raw) : '';
    $booking_people = absint(get_post_meta($post_id, 'rbf_persone', true));

    $should_mark_processed = false;

    if ($booking_date && $booking_meal && $booking_people > 0) {
        if (function_exists('rbf_release_slot_capacity')) {
            $release_success = rbf_release_slot_capacity($booking_date, $booking_meal, $booking_people);
            if ($release_success) {
                $should_mark_processed = true;
            } else {
                rbf_log(sprintf(
                    'RBF booking deletion: unable to release capacity for booking #%d (%s, %s, %d).',
                    $post_id,
                    $booking_date,
                    $booking_meal,
                    $booking_people
                ));
            }
        } else {
            rbf_log('RBF booking deletion: release function unavailable for booking #' . $post_id);
            $should_mark_processed = true;
        }
    } else {
        $should_mark_processed = true;
        rbf_log(sprintf(
            'RBF booking deletion: incomplete metadata for booking #%d (date: %s, meal: %s, people: %s). Release skipped.',
            $post_id,
            $booking_date ? $booking_date : 'n/a',
            $booking_meal ? $booking_meal : 'n/a',
            $booking_people > 0 ? $booking_people : '0'
        ));
    }

    if ($booking_date && $booking_meal) {
        delete_transient('rbf_avail_' . $booking_date . '_' . $booking_meal);
        rbf_clear_calendar_cache($booking_date, $booking_meal);
    }

    if ($should_mark_processed) {
        update_post_meta($post_id, $release_flag_key, 1);
    }
}

function rbf_reset_booking_release_flag($post_id) {
    $post_id = absint($post_id);
    if ($post_id <= 0) {
        return;
    }

    if (get_post_type($post_id) !== 'rbf_booking') {
        return;
    }

    delete_post_meta($post_id, '_rbf_booking_release_processed');
}

/**
 * AJAX handler to refresh calendar data (for real-time updates)
 */
add_action('wp_ajax_rbf_refresh_calendar', 'rbf_ajax_refresh_calendar');
add_action('wp_ajax_nopriv_rbf_refresh_calendar', 'rbf_ajax_refresh_calendar');

function rbf_ajax_refresh_calendar() {
    // Verify nonce
    if (!isset($_POST['_ajax_nonce']) || !wp_verify_nonce($_POST['_ajax_nonce'], 'rbf_ajax_nonce')) {
        wp_send_json_error([
            'message' => rbf_translate_string('Controllo di sicurezza fallito'),
            'code' => 'nonce_failed'
        ]);
        return;
    }
    
    $date = sanitize_text_field($_POST['date'] ?? '');
    $meal = sanitize_text_field($_POST['meal'] ?? '');
    
    if (empty($date) || empty($meal)) {
        wp_send_json_error([
            'message' => rbf_translate_string('Parametri mancanti'),
            'code' => 'missing_params'
        ]);
        return;
    }
    
    try {
        // Clear cache for this specific date/meal combination
        rbf_clear_calendar_cache($date, $meal);
        
        // Get fresh availability data
        $availability_status = rbf_get_availability_status($date, $meal);
        $remaining_capacity = rbf_get_remaining_capacity($date, $meal);
        $total_capacity = rbf_get_effective_capacity($meal);
        if ($total_capacity <= 0) {
            $remaining_capacity = null;
        }

        wp_send_json_success([
            'availability' => $availability_status,
            'remaining_capacity' => $remaining_capacity,
            'date' => $date,
            'meal' => $meal,
            'refreshed_at' => current_time('c')
        ]);
        
    } catch (Exception $e) {
        rbf_log('Calendar refresh error: ' . $e->getMessage());
        wp_send_json_error([
            'message' => rbf_translate_string('Errore nell\'aggiornamento del calendario'),
            'code' => 'refresh_error'
        ]);
    }
}

/**
 * RENEWED: Enhanced data validation helper functions
 * Ensure calendar receives properly structured data
 */

/**
 * Ensure a value is always an array
 */
function rbf_ensure_array($value) {
    if (is_array($value)) {
        return array_values($value); // Reindex to ensure numeric keys
    }
    if (is_string($value) && !empty($value)) {
        return [$value];
    }
    return [];
}

/**
 * Ensure meal availability data is properly structured
 */
function rbf_ensure_meal_availability($meal_availability) {
    if (!is_array($meal_availability)) {
        return [];
    }
    
    $validated = [];
    $valid_days = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
    
    foreach ($meal_availability as $meal_id => $days) {
        if (is_array($days)) {
            // Filter to only valid day names and ensure array values
            $validated[$meal_id] = array_values(array_intersect($days, $valid_days));
        } else {
            // Fallback: assume all days available if invalid data
            $validated[$meal_id] = $valid_days;
        }
    }
    
    return $validated;
}