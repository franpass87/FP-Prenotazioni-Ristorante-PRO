<?php
/**
 * Utility functions for FP Prenotazioni Ristorante
 * 
 * @package FP_Prenotazioni_Ristorante_PRO
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Retrieve all booking form shortcodes handled by the plugin.
 *
 * Having a centralized list keeps script enqueues and integrations
 * synchronized with the shortcodes registered on the frontend.
 *
 * @return array List of shortcode tags.
 */
function rbf_get_booking_form_shortcodes() {
    $shortcodes = [
        'ristorante_booking_form',
        'anniversary_booking_form',
        'birthday_booking_form',
        'romantic_booking_form',
        'celebration_booking_form',
        'business_booking_form',
        'proposal_booking_form',
        'special_booking_form',
    ];

    // Maintain backward compatibility with legacy shortcode names.
    $legacy_shortcodes = [
        'rbf_form',
        'restaurant_booking_form',
    ];

    $shortcodes = array_merge($shortcodes, $legacy_shortcodes);

    /**
     * Filter the list of booking form shortcodes.
     *
     * @param array $shortcodes Default list of booking form shortcodes.
     */
    return apply_filters('rbf_booking_form_shortcodes', array_values(array_unique($shortcodes)));
}

/**
 * Determine if the supplied post content includes a booking form shortcode.
 *
 * @param WP_Post|int|null $post Optional post object or ID to inspect.
 * @return bool True if a booking shortcode is present, false otherwise.
 */
function rbf_post_has_booking_form($post = null) {
    if (!function_exists('has_shortcode')) {
        return false;
    }

    if (!($post instanceof WP_Post)) {
        $post = get_post($post);
    }

    if (!$post || empty($post->post_content)) {
        return false;
    }

    foreach (rbf_get_booking_form_shortcodes() as $shortcode) {
        if (has_shortcode($post->post_content, $shortcode)) {
            return true;
        }
    }

    return false;
}

/**
 * Conditional debug logger for the plugin.
 * Logs messages only when WP_DEBUG or RBF_FORCE_LOG is enabled.
 *
 * @param string $message Message to log.
 */
function rbf_log($message) {
    if ((defined('WP_DEBUG') && WP_DEBUG) || (defined('RBF_FORCE_LOG') && RBF_FORCE_LOG)) {
        error_log($message);
    }
}

/**
 * Get default plugin settings
 */
function rbf_get_default_settings() {
    return [
        'open_mon' => 'yes','open_tue' => 'yes','open_wed' => 'yes','open_thu' => 'yes','open_fri' => 'yes','open_sat' => 'yes','open_sun' => 'yes',
        'ga4_id' => '',
        'ga4_api_secret' => '',
        'gtm_id' => '',
        'gtm_hybrid' => 'no',
        'meta_pixel_id' => '',
        'meta_access_token' => '',
        'notification_email' => get_option('admin_email'),
        'webmaster_email' => '',
        'brevo_api' => '',
        'brevo_list_it' => '',
        'brevo_list_en' => '',
        'closed_dates' => '',
        // Note: Advance booking limits removed - using fixed 1-hour minimum rule
        'min_advance_minutes' => 60, // Fixed at 1 hour for system compatibility
        'max_advance_minutes' => 0, // No maximum limit
        
        // Custom meals system (always enabled)
        'use_custom_meals' => 'yes',
        'custom_meals' => rbf_get_default_custom_meals(),
        
        // Anti-bot protection
        'recaptcha_site_key' => '',
        'recaptcha_secret_key' => '',
        'recaptcha_threshold' => '0.5',
    ];
}

/**
 * Get default custom meals configuration
 */
function rbf_get_default_custom_meals() {
    // No restaurant-specific meals are preloaded by default. Site owners must configure their own services.
    return [];
}

/**
 * Get active meals configuration
 * Returns custom meals configuration only
 */
function rbf_get_active_meals() {
    $options = wp_parse_args(get_option('rbf_settings', []), rbf_get_default_settings());
    
    $custom_meals = $options['custom_meals'] ?? rbf_get_default_custom_meals();
    // Filter only enabled meals
    return array_filter($custom_meals, function($meal) {
        return $meal['enabled'] ?? false;
    });
}

/**
 * Get meal configuration by ID
 */
function rbf_get_meal_config($meal_id) {
    $active_meals = rbf_get_active_meals();
    
    foreach ($active_meals as $meal) {
        if ($meal['id'] === $meal_id) {
            return $meal;
        }
    }
    
    return null;
}

/**
 * Validate if a meal is available on a specific day
 */
function rbf_is_meal_available_on_day($meal_id, $date) {
    $meal_config = rbf_get_meal_config($meal_id);
    if (!$meal_config) {
        return false;
    }
    
    $day_of_week = (int) date('w', strtotime($date));
    $day_mapping = [
        0 => 'sun', 1 => 'mon', 2 => 'tue', 3 => 'wed',
        4 => 'thu', 5 => 'fri', 6 => 'sat'
    ];
    
    $day_key = $day_mapping[$day_of_week];
    return in_array($day_key, $meal_config['available_days']);
}

/**
 * Get valid meal IDs for validation
 */
function rbf_get_valid_meal_ids() {
    $active_meals = rbf_get_active_meals();
    return array_column($active_meals, 'id');
}

/**
 * Determine if restaurant is open for a given date and meal.
 * Encapsulates weekday and closed-date/range checks.
 *
 * @param string $date Date in Y-m-d format
 * @param string $meal Meal identifier (currently unused but reserved for future logic)
 * @return bool True if restaurant is open, false if closed
 */
function rbf_is_restaurant_open($date, $meal) {
    $options = rbf_get_settings();

    // Check day of week availability
    $day_of_week = date('w', strtotime($date));
    $day_keys = ['sun','mon','tue','wed','thu','fri','sat'];
    $day_key = $day_keys[$day_of_week];

    if (($options["open_{$day_key}"] ?? 'no') !== 'yes') {
        return false;
    }

    // Check specific closed dates and ranges
    $closed_specific = rbf_get_closed_specific($options);

    if (in_array($date, $closed_specific['singles'], true)) {
        return false;
    }

    foreach ($closed_specific['ranges'] as $range) {
        if ($date >= $range['from'] && $date <= $range['to']) {
            return false;
        }
    }

    return true;
}

/**
 * WordPress timezone compatibility function
 */
if (!function_exists('rbf_wp_timezone')) {
    function rbf_wp_timezone() {
        if (function_exists('wp_timezone')) return wp_timezone();
        
        // Only access WordPress options if WordPress is fully loaded
        if (!function_exists('get_option')) {
            // Fallback to UTC if WordPress is not loaded
            return new DateTimeZone('UTC');
        }
        
        try {
            $tz_string = get_option('timezone_string');
            if ($tz_string) return new DateTimeZone($tz_string);
            
            $offset = (float) get_option('gmt_offset', 0);
            $hours = (int) $offset;
            $minutes = abs($offset - $hours) * 60;
            $sign = $offset < 0 ? '-' : '+';
            return new DateTimeZone(sprintf('%s%02d:%02d', $sign, abs($hours), $minutes));
        } catch (Exception $e) {
            // Log the error if debugging is enabled
            rbf_log('RBF Plugin: Timezone creation failed: ' . $e->getMessage());
            // Fallback to UTC on any error
            return new DateTimeZone('UTC');
        }
    }
}

/**
 * Get current language (limited to it/en with Polylang/WPML support; fallback en)
 */
function rbf_current_lang() {
    if (function_exists('pll_current_language')) {
        $slug = pll_current_language('slug');
        return in_array($slug, ['it','en'], true) ? $slug : 'en';
    }
    if (defined('ICL_LANGUAGE_CODE')) {
        $slug = ICL_LANGUAGE_CODE;
        return in_array($slug, ['it','en'], true) ? $slug : 'en';
    }
    
    // Only use get_locale if WordPress is fully loaded
    if (function_exists('get_locale')) {
        $slug = substr(get_locale(), 0, 2);
        return in_array($slug, ['it','en'], true) ? $slug : 'it'; // Default to Italian
    }
    
    // Default to Italian for Italian restaurant context
    return 'it';
}

/**
 * Retrieve plugin settings merged with defaults.
 *
 * Ensures new options have sensible default values even if the settings
 * were saved before the option was introduced.
 *
 * @return array
 */
function rbf_get_settings() {
    $saved = get_option('rbf_settings', []);
    $defaults = rbf_get_default_settings();
    $settings = wp_parse_args($saved, $defaults);

    // Migration: Convert old hour-based settings to minute-based settings
    if (isset($settings['min_advance_hours']) && !isset($saved['min_advance_minutes'])) {
        $settings['min_advance_minutes'] = $settings['min_advance_hours'] * 60;
        // Remove old setting
        unset($settings['min_advance_hours']);
        // Update the saved options
        update_option('rbf_settings', $settings);
    }

    return $settings;
}

/**
 * Get the maximum number of people allowed for a booking.
 *
 * @param array|null $settings Optional settings array to read the limit from.
 * @return int Normalized maximum number of people.
 */
function rbf_get_people_max_limit($settings = null) {
    if (!is_array($settings)) {
        $settings = rbf_get_settings();
    }

    $people_max = absint($settings['max_people'] ?? 0);

    if ($people_max <= 0) {
        $people_max = 20;
    }

    return $people_max;
}

/**
 * Translate strings to English
 */
function rbf_translate_string($text) {
    $locale = rbf_current_lang();
    if ($locale !== 'en') return $text;

    static $translations = [
        // Backend UI
        'Prenotazioni' => 'Bookings',
        'Tutte le Prenotazioni' => 'All Bookings',
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
        
        // New configurable meals system
        'Configurazione Pasti' => 'Meal Configuration',
        'Pasti Personalizzati' => 'Custom Meals',
        'Pasto %d' => 'Meal %d',
        'Attivo' => 'Active',
        'ID' => 'ID',
        'ID univoco del pasto (senza spazi, solo lettere e numeri)' => 'Unique meal ID (no spaces, letters and numbers only)',
        'Nome' => 'Name',
        'Capienza' => 'Capacity',
        'Orari' => 'Time Slots',
        'Orari separati da virgola' => 'Time slots separated by comma',
        'Prezzo (€)' => 'Price (€)',
        'Giorni disponibili' => 'Available Days',
        'Buffer Base (minuti)' => 'Base Buffer (minutes)',
        'Tempo minimo di buffer tra prenotazioni (minuti)' => 'Minimum buffer time between bookings (minutes)',
        'Buffer per Persona (minuti)' => 'Buffer per Person (minutes)',
        'Tempo aggiuntivo di buffer per ogni persona (minuti)' => 'Additional buffer time for each person (minutes)',
        'Limite Overbooking (%)' => 'Overbooking Limit (%)',
        'Percentuale di overbooking consentita oltre la capienza normale' => 'Percentage of overbooking allowed beyond normal capacity',
        'Durata Slot (minuti)' => 'Slot Duration (minutes)',
        'Durata di occupazione del tavolo per questo servizio (minuti)' => 'Table occupation duration for this service (minutes)',
        'Tooltip informativo' => 'Informative Tooltip',
        'Questo orario non rispetta il buffer di %d minuti richiesto. Scegli un altro orario.' => 'This time slot does not respect the required %d minute buffer. Choose another time.',
        'Rimuovi Pasto' => 'Remove Meal',
        'Aggiungi Pasto' => 'Add Meal',
        'Tipo di pasto non valido.' => 'Invalid meal type.',
        '%s non è disponibile in questo giorno.' => '%s is not available on this day.',
        
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
        'Limiti Temporali Prenotazioni' => 'Booking Time Limits',
        'Minuti minimi in anticipo per prenotare' => 'Minimum minutes in advance to book',
        'Numero minimo di minuti richiesti in anticipo per le prenotazioni. Valore minimo 0, massimo 525600 (1 anno). Esempi: 60 = 1 ora, 1440 = 1 giorno. Nota: le prenotazioni per il pranzo dello stesso giorno sono consentite se effettuate prima delle 6:00.' => 'Minimum number of minutes required in advance for bookings. Minimum value 0, maximum 525600 (1 year). Examples: 60 = 1 hour, 1440 = 1 day. Note: same-day lunch bookings are allowed if made before 6:00 AM.',
        'Minuti massimi in anticipo per prenotare' => 'Maximum minutes in advance to book',
        'Numero massimo di minuti entro cui è possibile prenotare. Valore minimo 0, massimo 525600 (1 anno). Esempi: 10080 = 7 giorni, 43200 = 30 giorni.' => 'Maximum number of minutes within which it is possible to book. Minimum value 0, maximum 525600 (1 year). Examples: 10080 = 7 days, 43200 = 30 days.',
        'Integrazioni e Marketing' => 'Integrations & Marketing',
        'Email per Notifiche Ristorante' => 'Restaurant Notification Email',
        'ID misurazione GA4' => 'GA4 Measurement ID',
        'ID GTM' => 'GTM ID',
        'Modalità ibrida GTM + GA4' => 'GTM + GA4 Hybrid Mode',
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
        'Marketing' => 'Marketing',
        'Accettato' => 'Accepted',
        'Accettata' => 'Accepted',
        'Aggiungi' => 'Add',
        'Aggiungi Nuova Eccezione' => 'Add New Exception',
        'Chiusura' => 'Closure',
        'Descrizione' => 'Description',
        'Domenica' => 'Sunday',
        'Eccezioni Attive' => 'Active Exceptions',
        'Eccezioni Calendario' => 'Calendar Exceptions',
        'Elimina' => 'Delete',
        'Email per Notifiche Webmaster' => 'Webmaster Notification Email',
        'Eventi Speciali' => 'Special Events',
        'Evento Speciale' => 'Special Event',
        'Festività' => 'Holiday',
        'Formato manuale: Data|Tipo|Orari|Descrizione (es. 2024-12-25|closure||Natale) oppure formato semplice (es. 2024-12-25)' => 'Manual format: Date|Type|Hours|Description (e.g. 2024-12-25|closure||Christmas) or simple format (e.g. 2024-12-25)',
        'Formato orari non valido. Usa: HH:MM-HH:MM o HH:MM,HH:MM,HH:MM' => 'Invalid time format. Use: HH:MM-HH:MM or HH:MM,HH:MM,HH:MM',
        'Gestione Eccezioni' => 'Exception Management',
        'Gestisci chiusure straordinarie, festività, eventi speciali e orari estesi.' => 'Manage extraordinary closures, holidays, special events and extended hours.',
        'Giovedì' => 'Thursday',
        'Grazie! La tua prenotazione è stata confermata con successo.' => 'Thank you! Your booking has been confirmed successfully.',
        
        // Tracking validation translations
        'Validazione Tracking' => 'Tracking Validation',
        'Validazione Sistema Tracking' => 'Tracking System Validation',
        'Panoramica Configurazione' => 'Configuration Overview',
        'Google Analytics 4' => 'Google Analytics 4',
        'Google Tag Manager' => 'Google Tag Manager',
        'Meta Pixel' => 'Meta Pixel',
        'ID Misurazione' => 'Measurement ID',
        'API Secret' => 'API Secret',
        'Container ID' => 'Container ID',
        'Modalità Ibrida' => 'Hybrid Mode',
        'Pixel ID' => 'Pixel ID',
        'Access Token (CAPI)' => 'Access Token (CAPI)',
        'Non configurato' => 'Not configured',
        'Configurato' => 'Configured',
        'Attiva' => 'Active',
        'Disattiva' => 'Inactive',
        'Risultati Validazione' => 'Validation Results',
        'Test Sistema Tracking' => 'Tracking System Test',
        'Esegui Test Tracking' => 'Run Tracking Test',
        'Test Completato' => 'Test Completed',
        'Test Fallito' => 'Test Failed',
        'Informazioni Debug' => 'Debug Information',
        'Flusso Tracking Implementato' => 'Implemented Tracking Flow',
        'Modalità Ibrida GTM + GA4 attiva' => 'GTM + GA4 Hybrid Mode active',
        'Eventi inviati solo a dataLayer per elaborazione GTM' => 'Events sent only to dataLayer for GTM processing',
        'Chiamate gtag() dirette disabilitate automaticamente' => 'Direct gtag() calls automatically disabled',
        'ID evento unico utilizzato per deduplicazione' => 'Unique event ID used for deduplication',
        'Modalità Standard GA4 attiva' => 'Standard GA4 Mode active',
        'Eventi inviati direttamente via gtag()' => 'Events sent directly via gtag()',
        'Tracking server-side disponibile se API secret configurato' => 'Server-side tracking available if API secret configured',
        'Enhanced Conversions con dati cliente hashati' => 'Enhanced Conversions with hashed customer data',
        'Facebook CAPI per backup eventi Pixel' => 'Facebook CAPI for Pixel event backup',
        'Sistema attribution bucket automatico' => 'Automatic attribution bucket system',
        'Note Importanti' => 'Important Notes',
        'In modalità ibrida, assicurati che GTM non abbia tag GA4 che si attivano su eventi purchase' => 'In hybrid mode, ensure GTM doesn\'t have GA4 tags that trigger on purchase events',
        'I dati cliente sono sempre hashati con SHA256 prima dell\'invio' => 'Customer data is always SHA256 hashed before sending',
        'Usa GA4 DebugView per verificare gli eventi in tempo reale' => 'Use GA4 DebugView to verify events in real time',
        'Facebook Events Manager mostra gli eventi CAPI con badge "Server"' => 'Facebook Events Manager shows CAPI events with "Server" badge',
        'Esegui un test del sistema di tracking per verificare che tutti i componenti funzionino correttamente.' => 'Run a tracking system test to verify that all components work correctly.',
        'Documentazione e Risorse' => 'Documentation and Resources',
        'Guide Implementazione' => 'Implementation Guides',
        'Strumenti Debug' => 'Debug Tools',
        'Test e Validazione' => 'Testing and Validation',
        'Configurazione Base' => 'Basic Configuration',
        'Documentazione Ibrida' => 'Hybrid Documentation',
        'Tracking GA4' => 'GA4 Tracking',
        'GA4 DebugView' => 'GA4 DebugView',
        'Facebook Events Manager' => 'Facebook Events Manager',
        'Debug Browser' => 'Browser Debug',
        'Console JavaScript' => 'JavaScript Console',
        'ID GA4 non valido. Deve essere nel formato G-XXXXXXXXXX.' => 'Invalid GA4 ID. Must be in format G-XXXXXXXXXX.',
        'ID GTM non valido. Deve essere nel formato GTM-XXXXXXX.' => 'Invalid GTM ID. Must be in format GTM-XXXXXXX.',
        'Il numero di persone deve essere compreso tra 1 e %d.' => 'The number of people must be between 1 and %d.',
        'Il numero di persone deve essere compreso tra 1 e 20.' => 'The number of people must be between 1 and 20.',
        'Il numero di persone non può superare %d.' => 'The number of people cannot exceed %d.',
        'Errore durante l\'aggiornamento della capacità della prenotazione.' => 'Error while updating the booking capacity.',
        'Spiacenti, non ci sono abbastanza posti. Rimasti: %d. Scegli un altro orario.' => 'Sorry, there are not enough seats available. Remaining: %d. Please choose another time.',

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
        'Controllo di sicurezza fallito.' => 'Security check failed.',
        'Parametri obbligatori mancanti.' => 'Missing required parameters.',
        'Data non valida.' => 'Invalid date.',
        'Indirizzo email non valido.' => 'Invalid email address.',
        'Orario non valido.' => 'Invalid time.',
        'Formato orario non valido.' => 'Invalid time format.',
        'Spiacenti, non ci sono abbastanza posti. Rimasti: %d' => 'Sorry, there are not enough seats available. Remaining: %d',
        'Errore nel salvataggio.' => 'Error while saving.',
        'Caricamento...' => 'Loading...',
        'Scegli un orario...' => 'Choose a time...',
        'Nessun orario disponibile' => 'No time available',
        'Il numero di telefono inserito non è valido.' => 'The phone number entered is not valid.',
        'Di Domenica il servizio è Brunch con menù alla carta.' => 'On Sundays, we serve our à la carte Brunch menu.',
        'Acconsento al trattamento dei dati secondo l\'<a href="%s" target="_blank">Informativa sulla Privacy</a>' => 'I consent to the processing of my data in accordance with the <a href="%s" target="_blank">Privacy Policy</a>',
        'Acconsento a ricevere comunicazioni promozionali via email e/o messaggi riguardanti eventi, offerte o novità.' => 'I agree to receive promotional emails and/or messages about events, offers, or news.',
        'Devi accettare la Privacy Policy per procedere.' => 'You must accept the Privacy Policy to proceed.',
        'Le prenotazioni devono essere effettuate con almeno %s di anticipo.' => 'Bookings must be made at least %s in advance.',
        'Le prenotazioni possono essere effettuate al massimo %s in anticipo.' => 'Bookings can be made at most %s in advance.',
        'Pranzo' => 'Lunch',
        'Aperitivo' => 'Aperitif',
        'Cena' => 'Dinner',
        'Brunch' => 'Brunch',
        'Il brunch è disponibile solo la domenica.' => 'Brunch is only available on Sundays.',
        
        // New accessibility and UX strings
        'Progresso prenotazione' => 'Booking progress',
        'Dati personali' => 'Personal details',
        'I tuoi dati' => 'Your details',
        'Consensi' => 'Consents',
        'Seleziona una data dal calendario' => 'Select a date from the calendar',
        'Seleziona un orario disponibile' => 'Select an available time',
        'Usa i pulsanti + e - per modificare' => 'Use + and - buttons to change',
        'Diminuisci numero persone' => 'Decrease number of people',
        'Aumenta numero persone' => 'Increase number of people',
        'Inserisci eventuali allergie o note particolari...' => 'Enter any allergies or special notes...',
        
        // Brand configuration strings
        'Configurazione Brand e Colori' => 'Brand and Color Configuration',
        'Colore Primario' => 'Primary Color',
        'Colore Secondario' => 'Secondary Color',
        'Raggio Angoli' => 'Border Radius',
        'Anteprima' => 'Preview',
        'Pulsante Principale' => 'Primary Button',
        'Pulsante Secondario' => 'Secondary Button',
        'Campo di esempio' => 'Example field',
        'Questa anteprima mostra come appariranno i colori selezionati' => 'This preview shows how the selected colors will appear',
        'Colore principale utilizzato per pulsanti, evidenziazioni e elementi attivi' => 'Primary color used for buttons, highlights, and active elements',
        'Colore secondario per accenti e elementi complementari' => 'Secondary color for accents and complementary elements',
        'Determina quanto arrotondati appaiono gli angoli di pulsanti e campi' => 'Determines how rounded buttons and field corners appear',
        'Squadrato (0px)' => 'Square (0px)',
        'Leggermente arrotondato (4px)' => 'Slightly rounded (4px)',
        'Arrotondato (8px)' => 'Rounded (8px)',
        'Molto arrotondato (12px)' => 'Very rounded (12px)',
        'Estremamente arrotondato (16px)' => 'Extremely rounded (16px)',
        
        // Enhanced booking status system
        'Stato Prenotazione' => 'Booking Status',
        'In Attesa' => 'Pending',
        'Confermata' => 'Confirmed', 
        'Completata' => 'Completed',
        'Annullata' => 'Cancelled',
        'In Lista d\'Attesa' => 'On Waitlist',
        'Azioni Prenotazione' => 'Booking Actions',
        'Conferma Prenotazione' => 'Confirm Booking',
        'Segna come Completata' => 'Mark as Completed',
        'Annulla Prenotazione' => 'Cancel Booking',
        'Cronologia Status' => 'Status History',
        'Hash Prenotazione' => 'Booking Hash',
        'Gestisci Prenotazione' => 'Manage Booking',
        'Modifica/Annulla' => 'Modify/Cancel',
        'La tua prenotazione #%s è stata aggiornata' => 'Your booking #%s has been updated',
        'Tutti gli stati' => 'All statuses',
        'Cliente' => 'Customer',
        'Valore' => 'Value',
        'Azioni' => 'Actions',
        'Conferma' => 'Confirm',
        'Completa' => 'Complete',
        
        // Reports and Analytics
        'Report & Analytics' => 'Reports & Analytics',
        'Da:' => 'From:',
        'A:' => 'To:',
        'Aggiorna Report' => 'Update Report',
        'Prenotazioni Totali' => 'Total Bookings',
        'Dal %s al %s' => 'From %s to %s',
        'Persone Totali' => 'Total Guests',
        'Media: %.1f per prenotazione' => 'Average: %.1f per booking',
        'Valore Stimato' => 'Estimated Value',
        'Media: €%.2f per prenotazione' => 'Average: €%.2f per booking',
        'Tasso Completamento' => 'Completion Rate',
        '%d completate su %d confermate' => '%d completed out of %d confirmed',
        'Prenotazioni per Stato' => 'Bookings by Status',
        'Prenotazioni per Servizio' => 'Bookings by Service',
        'Andamento Prenotazioni Giornaliere' => 'Daily Bookings Trend',
        'Analisi Sorgenti di Traffico' => 'Traffic Sources Analysis',
        'Prenotazioni' => 'Bookings',
        
        // Customer booking management
        'Gestisci la tua Prenotazione' => 'Manage Your Booking',
        'Inserisci il codice della tua prenotazione per visualizzare i dettagli e gestirla.' => 'Enter your booking code to view details and manage it.',
        'Codice Prenotazione' => 'Booking Code',
        'Cerca' => 'Search',
        'Prenotazione non trovata. Verifica il codice inserito.' => 'Booking not found. Please verify the entered code.',
        'Torna indietro' => 'Go back',
        'Dettagli Prenotazione' => 'Booking Details',
        'Nuova ricerca' => 'New search',
        'Informazioni Cliente' => 'Customer Information',
        'Servizio' => 'Service',
        'Creata il' => 'Created on',
        'Azioni Disponibili' => 'Available Actions',
        'Puoi cancellare questa prenotazione se necessario. La cancellazione è definitiva.' => 'You can cancel this booking if necessary. Cancellation is final.',
        'Cancella Prenotazione' => 'Cancel Booking',
        'Sei sicuro di voler cancellare questa prenotazione? L\'operazione non può essere annullata.' => 'Are you sure you want to cancel this booking? This action cannot be undone.',
        'La tua prenotazione è stata cancellata con successo.' => 'Your booking has been cancelled successfully.',
        'Prenotazione Cancellata' => 'Booking Cancelled',
        'Questa prenotazione è stata cancellata e non è più attiva.' => 'This booking has been cancelled and is no longer active.',
        'Prenotazione Completata' => 'Booking Completed',
        'Grazie per aver scelto il nostro ristorante! Speriamo di rivederti presto.' => 'Thank you for choosing our restaurant! We hope to see you again soon.',
        'Prenotazione Passata' => 'Past Booking',
        'Questa prenotazione si riferisce a una data passata.' => 'This booking refers to a past date.',
        
        // Export functionality
        'Esporta Dati' => 'Export Data',
        'Esporta Dati Prenotazioni' => 'Export Booking Data',
        'Data Inizio' => 'Start Date',
        'Data Fine' => 'End Date', 
        'Filtra per Stato' => 'Filter by Status',
        'Formato Export' => 'Export Format',
        'Esporta Prenotazioni' => 'Export Bookings',
        'Informazioni Export' => 'Export Information',
        'L\'export includerà tutti i dati delle prenotazioni nel periodo selezionato:' => 'The export will include all booking data for the selected period:',
        'Informazioni cliente (nome, email, telefono)' => 'Customer information (name, email, phone)',
        'Dettagli prenotazione (data, orario, servizio, persone)' => 'Booking details (date, time, service, guests)',
        'Stato prenotazione e cronologia' => 'Booking status and history',
        'Sorgenti di traffico e parametri UTM' => 'Traffic sources and UTM parameters',
        'Note e preferenze alimentari' => 'Notes and dietary preferences',
        'Consensi privacy e marketing' => 'Privacy and marketing consents',
        'Gestione Automatica' => 'Automatic Management',
        'Elimina definitivamente questa prenotazione?' => 'Permanently delete this booking?',
        'Tooltip informativo' => 'Information Tooltip',
        'Testo informativo che apparirà quando questo pasto viene selezionato (opzionale)' => 'Information text that will appear when this meal is selected (optional)',
        'Di Domenica il servizio è Brunch con menù alla carta.' => 'On Sundays the service is Brunch with à la carte menu.',
        'Disponibile solo la domenica con menù speciale.' => 'Available only on Sundays with special menu.',
        
        // Calendar availability status
        'Disponibile' => 'Available',
        'Limitato' => 'Limited',
        'Quasi pieno' => 'Nearly full',
        'Posti rimasti:' => 'Spots remaining:',
        'Occupazione:' => 'Occupancy:',
        
        // AI Suggestions
        'Alternative disponibili' => 'Available alternatives',
        'Seleziona una delle alternative seguenti:' => 'Select one of the following alternatives:',
        'Stesso giorno, servizio diverso' => 'Same day, different service',
        'Il giorno successivo' => 'The next day',
        'Il giorno precedente' => 'The previous day',
        '%d giorni dopo' => '%d days later',
        '%d giorni prima' => '%d days earlier',
        'Stesso giorno della settimana, %d settimana dopo' => 'Same day of the week, %d week later',
        'Abbiamo trovato alcune alternative per te!' => 'We found some alternatives for you!',
        'Non abbiamo trovato alternative disponibili.' => 'We found no available alternatives.',
        'Questo orario è completo, ma abbiamo trovato delle alternative per te!' => 'This time is full, but we found alternatives for you!',
        'Questo orario è completamente prenotato.' => 'This time is completely booked.',
        'Non ci sono orari disponibili per questa data, ma abbiamo trovato delle alternative!' => 'No times available for this date, but we found alternatives!',
        'Non ci sono orari disponibili per questa data.' => 'No times available for this date.',
    ];
    return $translations[$text] ?? $text;
}

/**
 * Get available booking statuses (simplified - no waitlist or pending)
 */
function rbf_get_booking_statuses() {
    return [
        'confirmed' => rbf_translate_string('Confermata'),
        'completed' => rbf_translate_string('Completata'),
        'cancelled' => rbf_translate_string('Annullata'),
    ];
}

/**
 * Get booking status color (simplified)
 */
function rbf_get_status_color($status) {
    $colors = [
        'confirmed' => '#10b981',  // emerald
        'completed' => '#06b6d4',  // cyan
        'cancelled' => '#ef4444',  // red
    ];
    return $colors[$status] ?? '#6b7280'; // gray fallback
}

/**
 * Normalize time format to HH:MM
 */
function rbf_normalize_time_format($time) {
    $time = trim($time);
    
    // Normalize time format (ensure HH:MM)
    if (preg_match('/^\d:\d\d$/', $time)) {
        $time = '0' . $time;
    }
    if (preg_match('/^\d\d:\d$/', $time)) {
        $time = $time . '0';
    }
    
    // Validate time format
    if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
        return false;
    }
    
    return $time;
}

/**
 * Validate booking time against minimum advance requirement (1 hour)
 * 
 * @param string $date Date in Y-m-d format
 * @param string $time Time in H:i format
 * @return array|true Returns array with error info if invalid, true if valid
 */
function rbf_validate_booking_time($date, $time) {
    $tz = rbf_wp_timezone();
    $now = new DateTime('now', $tz);
    $booking_datetime = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $time, $tz);
    
    if (!$booking_datetime) {
        return [
            'error' => true,
            'message' => rbf_translate_string('Orario non valido.')
        ];
    }
    
    $minutes_diff = ($booking_datetime->getTimestamp() - $now->getTimestamp()) / 60;
    
    // Check if booking time is in the past
    if ($minutes_diff < 0) {
        return [
            'error' => true,
            'message' => rbf_translate_string('Non è possibile prenotare per orari già passati. Scegli un orario futuro.')
        ];
    }
    
    // Check minimum 1-hour advance booking requirement
    if ($minutes_diff < 60) {
        return [
            'error' => true,
            'message' => rbf_translate_string('Le prenotazioni devono essere effettuate con almeno 1 ora di anticipo.')
        ];
    }
    
    return true;
}

/**
 * Centralized email validation
 */
function rbf_validate_email($email) {
    $email = sanitize_email($email);
    if (!is_email($email)) {
        return ['error' => true, 'message' => rbf_translate_string('Indirizzo email non valido.')];
    }
    return $email;
}

/**
 * Enhanced centralized phone number validation with security improvements
 */
function rbf_validate_phone($phone) {
    $phone = rbf_sanitize_phone_field($phone);
    
    // Enhanced phone validation - at least 8 digits, max 20 characters
    $digits_only = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($digits_only) < 8) {
        return ['error' => true, 'message' => rbf_translate_string('Il numero di telefono inserito non è valido.')];
    }
    
    // Check for suspicious patterns (all same digits, etc.)
    if (preg_match('/^(\d)\1+$/', $digits_only)) {
        return ['error' => true, 'message' => rbf_translate_string('Il numero di telefono inserito non sembra valido.')];
    }
    
    return $phone;
}

/**
 * Centralized date validation
 */
function rbf_validate_date($date) {
    $date = sanitize_text_field($date);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !DateTime::createFromFormat('Y-m-d', $date)) {
        return ['error' => true, 'message' => rbf_translate_string('Data non valida.')];
    }
    return $date;
}

/**
 * Standardized error response handler
 */
function rbf_handle_error($message, $context = 'general', $redirect_url = null) {
    // Log error for debugging
    rbf_log("RBF Error [{$context}]: {$message}");

    // Fire action for error tracking
    do_action('rbf_error_logged', $message, $context);
    
    // If AJAX request, send JSON response
    if (wp_doing_ajax()) {
        wp_send_json_error(['message' => $message, 'context' => $context]);
        return;
    }
    
    // If redirect URL provided, redirect with error message
    if ($redirect_url) {
        wp_safe_redirect(add_query_arg('rbf_error', urlencode($message), $redirect_url));
        exit;
    }
    
    // Fallback: return error array
    return ['error' => true, 'message' => $message, 'context' => $context];
}

/**
 * Standardized success response handler  
 */
function rbf_handle_success($message, $data = [], $redirect_url = null) {
    // If AJAX request, send JSON response
    if (wp_doing_ajax()) {
        wp_send_json_success(array_merge(['message' => $message], $data));
        return;
    }

    // If redirect URL provided, redirect with success message
    if ($redirect_url) {
        // Preserve existing query arguments from the redirect URL
        $fragment = '';
        $redirect_parts = explode('#', $redirect_url, 2);
        if (count($redirect_parts) === 2) {
            $fragment = $redirect_parts[1];
        }

        $base_parts = explode('?', $redirect_parts[0], 2);
        $base_url = $base_parts[0];

        $existing_args = [];
        if (!empty($base_parts[1])) {
            wp_parse_str($base_parts[1], $existing_args);
        }

        // Merge existing query arguments with caller-provided data
        $query_args = array_merge($existing_args, $data);

        // Inject default success flag only when not provided by caller or URL
        if (!array_key_exists('rbf_success', $query_args)) {
            $query_args['rbf_success'] = urlencode($message);
        }

        $final_url = add_query_arg($query_args, $base_url);

        if (!empty($fragment)) {
            $final_url .= '#' . $fragment;
        }

        wp_safe_redirect($final_url);
        exit;
    }

    // Fallback: return success array
    return array_merge(['success' => true, 'message' => $message], $data);
}

/**
 * Centralized asset version helper for cache-busting
 * Returns base version with optional timestamp when debugging
 */
function rbf_get_asset_version() {
    if (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) {
        return RBF_VERSION . '.' . time();
    }
    return RBF_VERSION;
}

/**
 * Centralized UTM parameter sanitization
 * Consolidates sanitization logic used across multiple files
 */
function rbf_sanitize_utm_param($value, $max_length = 100) {
    $sanitized = sanitize_text_field($value);
    return substr(preg_replace('/[<>"\'\\/\\\\]/', '', $sanitized), 0, $max_length);
}

/**
 * Enhanced UTM parameter validation with security improvements
 * Moved from frontend.php to consolidate validation logic
 */
function rbf_validate_utm_parameters($utm_data) {
    $validated = [];
    
    // Source validation - alphanumeric, dots, hyphens, underscores only
    if (!empty($utm_data['utm_source'])) {
        $source = strtolower(trim($utm_data['utm_source']));
        $validated['utm_source'] = substr(preg_replace('/[^a-zA-Z0-9._-]/', '', $source), 0, 100);
    }
    
    // Medium validation with predefined valid values
    if (!empty($utm_data['utm_medium'])) {
        $medium = strtolower(trim($utm_data['utm_medium']));
        $valid_mediums = [
            'cpc', 'banner', 'email', 'social', 'organic', 
            'referral', 'direct', 'paid', 'ppc', 'sem', 
            'display', 'affiliate', 'newsletter', 'sms'
        ];
        
        // Check if it's a recognized medium
        $validated['utm_medium'] = in_array($medium, $valid_mediums, true) ? $medium : 'other';
    }
    
    // Campaign validation using helper function
    if (!empty($utm_data['utm_campaign'])) {
        $validated['utm_campaign'] = rbf_sanitize_utm_param($utm_data['utm_campaign'], 150);
    }
    
    // UTM Term validation using helper function
    if (!empty($utm_data['utm_term'])) {
        $validated['utm_term'] = rbf_sanitize_utm_param($utm_data['utm_term'], 100);
    }
    
    // UTM Content validation using helper function
    if (!empty($utm_data['utm_content'])) {
        $validated['utm_content'] = rbf_sanitize_utm_param($utm_data['utm_content'], 100);
    }
    
    // Google Ads Click ID validation
    if (!empty($utm_data['gclid'])) {
        $gclid = trim($utm_data['gclid']);
        // GCLID should be alphanumeric with some allowed special chars
        if (preg_match('/^[a-zA-Z0-9._-]+$/', $gclid) && strlen($gclid) <= 200) {
            $validated['gclid'] = $gclid;
        }
    }
    
    // Facebook Click ID validation
    if (!empty($utm_data['fbclid'])) {
        $fbclid = trim($utm_data['fbclid']);
        // FBCLID should be alphanumeric with some allowed special chars
        if (preg_match('/^[a-zA-Z0-9._-]+$/', $fbclid) && strlen($fbclid) <= 200) {
            $validated['fbclid'] = $fbclid;
        }
    }
    
    return $validated;
}

/**
 * Normalize bucket attribution for unified cross-platform tracking
 * 
 * This function implements the priority-based bucket classification:
 * Priority: gclid > fbclid > organic
 * 
 * @param string $gclid Google Click ID parameter
 * @param string $fbclid Facebook Click ID parameter
 * @return string Normalized bucket value: 'gads', 'fbads', or 'organic'
 */
function rbf_normalize_bucket($gclid = '', $fbclid = '') {
    // Clean and validate input parameters
    $gclid = sanitize_text_field(trim($gclid));
    $fbclid = sanitize_text_field(trim($fbclid));
    
    // Priority 1: Google Ads - if gclid is present
    if (!empty($gclid) && preg_match('/^[a-zA-Z0-9._-]+$/', $gclid)) {
        return 'gads';
    }
    
    // Priority 2: Facebook/Meta Ads - if fbclid is present
    if (!empty($fbclid) && preg_match('/^[a-zA-Z0-9._-]+$/', $fbclid)) {
        return 'fbads';
    }
    
    // Priority 3: Everything else becomes organic
    return 'organic';
}

/**
 * Get UTM analytics for dashboard
 * Moved from utm-validator.php to consolidate analytics functionality
 */
function rbf_get_utm_analytics($days = 30) {
    if (!current_user_can('manage_options')) {
        return [];
    }
    
    global $wpdb;
    
    $since_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
    
    // Get source bucket distribution
    $bucket_stats = $wpdb->get_results($wpdb->prepare("
        SELECT
            pm_bucket.meta_value as bucket,
            COUNT(*) as count,
            AVG(pm_people.meta_value) as avg_people,
            SUM(CASE
                WHEN COALESCE(pm_meal.meta_value, pm_meal_legacy.meta_value) = 'pranzo' THEN pm_people.meta_value * 35
                WHEN COALESCE(pm_meal.meta_value, pm_meal_legacy.meta_value) = 'cena' THEN pm_people.meta_value * 50
                WHEN COALESCE(pm_meal.meta_value, pm_meal_legacy.meta_value) = 'aperitivo' THEN pm_people.meta_value * 15
                ELSE 0
            END) as estimated_revenue
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm_bucket ON (p.ID = pm_bucket.post_id AND pm_bucket.meta_key = 'rbf_source_bucket')
        LEFT JOIN {$wpdb->postmeta} pm_people ON (p.ID = pm_people.post_id AND pm_people.meta_key = 'rbf_persone')
        LEFT JOIN {$wpdb->postmeta} pm_meal ON (p.ID = pm_meal.post_id AND pm_meal.meta_key = 'rbf_meal')
        LEFT JOIN {$wpdb->postmeta} pm_meal_legacy ON (p.ID = pm_meal_legacy.post_id AND pm_meal_legacy.meta_key = 'rbf_orario')
        WHERE p.post_type = 'rbf_booking'
        AND p.post_status = 'publish'
        AND p.post_date >= %s
        GROUP BY pm_bucket.meta_value
        ORDER BY count DESC
    ", $since_date));
    
    // Get campaign performance
    $campaign_stats = $wpdb->get_results($wpdb->prepare("
        SELECT 
            COALESCE(pm_campaign.meta_value, 'No Campaign') as campaign,
            pm_source.meta_value as utm_source,
            pm_medium.meta_value as utm_medium,
            COUNT(*) as bookings,
            SUM(pm_people.meta_value) as total_people
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm_campaign ON (p.ID = pm_campaign.post_id AND pm_campaign.meta_key = 'rbf_utm_campaign')
        LEFT JOIN {$wpdb->postmeta} pm_source ON (p.ID = pm_source.post_id AND pm_source.meta_key = 'rbf_utm_source')
        LEFT JOIN {$wpdb->postmeta} pm_medium ON (p.ID = pm_medium.post_id AND pm_medium.meta_key = 'rbf_utm_medium')
        LEFT JOIN {$wpdb->postmeta} pm_people ON (p.ID = pm_people.post_id AND pm_people.meta_key = 'rbf_persone')
        WHERE p.post_type = 'rbf_booking' 
        AND p.post_status = 'publish'
        AND p.post_date >= %s
        AND pm_source.meta_value IS NOT NULL
        GROUP BY pm_campaign.meta_value, pm_source.meta_value, pm_medium.meta_value
        ORDER BY bookings DESC
        LIMIT 10
    ", $since_date));
    
    return [
        'bucket_distribution' => $bucket_stats,
        'campaign_performance' => $campaign_stats,
        'period_days' => $days
    ];
}

/**
 * Recursively sanitize data structures using sanitize_text_field for strings.
 * Numeric values are preserved as proper int or float types.
 *
 * @param mixed $data Data to sanitize.
 * @return mixed Sanitized data with preserved numeric types.
 */
function rbf_recursive_sanitize($data) {
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = rbf_recursive_sanitize($value);
        }
        return $data;
    }

    if (is_string($data)) {
        $sanitized = sanitize_text_field($data);
        if (is_numeric($sanitized)) {
            return strpos($sanitized, '.') !== false ? (float) $sanitized : (int) $sanitized;
        }
        return $sanitized;
    }

    if (is_numeric($data)) {
        return $data + 0; // Cast to int or float as needed
    }

    return $data;
}

/**
 * Enhanced centralized input sanitization helper with security improvements
 * Reduces repetitive sanitize_text_field calls across the codebase and prevents injection attacks
 */
function rbf_sanitize_input_fields(array $input_data, array $field_map) {
    $sanitized = [];
    
    foreach ($field_map as $key => $type) {
        if (!isset($input_data[$key])) {
            continue;
        }
        
        $value = $input_data[$key];
        
        // First level: remove potential null bytes and control characters
        $value = str_replace(chr(0), '', $value);
        
        switch ($type) {
            case 'text':
                $sanitized[$key] = rbf_sanitize_text_strict($value);
                break;
            case 'email':
                $sanitized[$key] = sanitize_email($value);
                break;
            case 'textarea':
                $sanitized[$key] = rbf_sanitize_textarea_strict($value);
                break;
            case 'int':
                $sanitized[$key] = intval($value);
                break;
            case 'float':
                $sanitized[$key] = floatval($value);
                break;
            case 'url':
                $sanitized[$key] = esc_url_raw($value);
                break;
            case 'name':
                $sanitized[$key] = rbf_sanitize_name_field($value);
                break;
            case 'phone':
                $sanitized[$key] = rbf_sanitize_phone_field($value);
                break;
            default:
                $sanitized[$key] = rbf_sanitize_text_strict($value);
        }
    }
    
    return $sanitized;
}

/**
 * Strict text field sanitization with enhanced security
 */
function rbf_sanitize_text_strict($value) {
    // Remove potential script tags and dangerous characters
    $value = strip_tags($value);
    $value = sanitize_text_field($value);
    
    // Additional security: remove potentially dangerous sequences
    $dangerous_patterns = [
        '/javascript:/i',
        '/data:/i',
        '/vbscript:/i',
        '/on\w+\s*=/i', // onload, onclick, etc.
        '/<script/i',
        '/<iframe/i',
        '/<object/i',
        '/<embed/i'
    ];
    
    foreach ($dangerous_patterns as $pattern) {
        $value = preg_replace($pattern, '', $value);
    }
    
    return trim($value);
}

/**
 * Strict textarea sanitization while preserving basic formatting
 */
function rbf_sanitize_textarea_strict($value) {
    // Allow only safe HTML tags for formatting
    $allowed_tags = '<br><p>';
    $value = strip_tags($value, $allowed_tags);
    $value = sanitize_textarea_field($value);
    
    // Remove dangerous sequences
    $dangerous_patterns = [
        '/javascript:/i',
        '/data:/i',
        '/vbscript:/i',
        '/on\w+\s*=/i',
        '/<script/i',
        '/<iframe/i',
        '/<object/i',
        '/<embed/i'
    ];
    
    foreach ($dangerous_patterns as $pattern) {
        $value = preg_replace($pattern, '', $value);
    }
    
    return trim($value);
}

/**
 * Sanitize name fields with extra validation
 */
function rbf_sanitize_name_field($value) {
    $value = rbf_sanitize_text_strict($value);
    
    // Names should only contain letters, spaces, hyphens, apostrophes, and accented characters
    $value = preg_replace('/[^\p{L}\s\-\'\.]/u', '', $value);
    
    // Limit length to prevent buffer overflow attempts
    $value = substr($value, 0, 100);
    
    return trim($value);
}

/**
 * Sanitize phone fields with validation
 */
function rbf_sanitize_phone_field($value) {
    $value = sanitize_text_field($value);
    
    // Phone should only contain numbers, spaces, hyphens, parentheses, and plus sign
    $value = preg_replace('/[^\d\s\-\(\)\+]/', '', $value);
    
    // Limit length
    $value = substr($value, 0, 20);
    
    return trim($value);
}

/**
 * Escape data for safe use in email templates (HTML context)
 */
function rbf_escape_for_email($value, $context = 'html') {
    switch ($context) {
        case 'html':
            return esc_html($value);
        case 'attr':
            return esc_attr($value);
        case 'url':
            return esc_url($value);
        case 'subject':
            // For email subjects, ensure no header injection
            $value = str_replace(["\r", "\n", "\r\n"], '', $value);
            return sanitize_text_field($value);
        default:
            return esc_html($value);
    }
}

/**
 * Generate secure ICS calendar file content
 */
function rbf_generate_ics_content($booking_data) {
    // Sanitize all booking data for ICS format
    $sanitized_data = [];
    foreach ($booking_data as $key => $value) {
        // ICS format requires specific escaping
        $sanitized_data[$key] = rbf_escape_for_ics($value);
    }
    
    // Generate unique UID
    $uid = uniqid('rbf_booking_', true) . '@' . $_SERVER['HTTP_HOST'];
    
    // Format datetime for ICS
    $booking_datetime = DateTime::createFromFormat('Y-m-d H:i', $sanitized_data['date'] . ' ' . $sanitized_data['time']);
    if (!$booking_datetime) {
        return false;
    }
    
    $start_time = $booking_datetime->format('Ymd\THis\Z');
    $end_time = $booking_datetime->add(new DateInterval('PT2H'))->format('Ymd\THis\Z'); // 2 hour duration
    $created_time = gmdate('Ymd\THis\Z');
    
    $ics_content = "BEGIN:VCALENDAR\r\n";
    $ics_content .= "VERSION:2.0\r\n";
    $ics_content .= "PRODID:-//RBF Restaurant Booking//EN\r\n";
    $ics_content .= "CALSCALE:GREGORIAN\r\n";
    $ics_content .= "BEGIN:VEVENT\r\n";
    $ics_content .= "UID:" . $uid . "\r\n";
    $ics_content .= "DTSTAMP:" . $created_time . "\r\n";
    $ics_content .= "DTSTART:" . $start_time . "\r\n";
    $ics_content .= "DTEND:" . $end_time . "\r\n";
    $ics_content .= "SUMMARY:" . $sanitized_data['summary'] . "\r\n";
    $ics_content .= "DESCRIPTION:" . $sanitized_data['description'] . "\r\n";
    if (!empty($sanitized_data['location'])) {
        $ics_content .= "LOCATION:" . $sanitized_data['location'] . "\r\n";
    }
    $ics_content .= "STATUS:CONFIRMED\r\n";
    $ics_content .= "END:VEVENT\r\n";
    $ics_content .= "END:VCALENDAR\r\n";
    
    return $ics_content;
}

/**
 * Escape text for ICS format
 */
function rbf_escape_for_ics($text) {
    // ICS format escaping rules
    $text = str_replace(['\\', ';', ',', "\n", "\r"], ['\\\\', '\\;', '\\,', '\\n', ''], $text);
    
    // Remove any remaining control characters
    $text = preg_replace('/[\x00-\x1F\x7F]/', '', $text);
    
    // Limit length to prevent issues
    return substr($text, 0, 250);
}

function rbf_update_booking_status($booking_id, $new_status, $note = '') {
    $valid_statuses = array_keys(rbf_get_booking_statuses());
    $valid_statuses[] = 'pending';

    if (!in_array($new_status, $valid_statuses, true)) {
        return false;
    }

    $booking = get_post($booking_id);
    if (!$booking || $booking->post_type !== 'rbf_booking') {
        return false;
    }

    $old_status_raw = get_post_meta($booking_id, 'rbf_booking_status', true);
    $old_status = $old_status_raw ?: 'pending';

    if ($old_status_raw !== $new_status) {
        $updated = update_post_meta($booking_id, 'rbf_booking_status', $new_status);

        if ($updated === false) {
            return false;
        }
    }

    $timestamp = current_time('Y-m-d H:i:s');
    update_post_meta($booking_id, 'rbf_status_updated', $timestamp);

    $history = get_post_meta($booking_id, 'rbf_status_history', true);
    if (!is_array($history)) {
        $history = [];
    }

    $history[] = [
        'timestamp' => $timestamp,
        'from' => $old_status,
        'to' => $new_status,
        'note' => $note,
        'user' => get_current_user_id()
    ];

    update_post_meta($booking_id, 'rbf_status_history', $history);

    do_action('rbf_booking_status_changed', $booking_id, $old_status, $new_status, $note);

    return true;
}

/**
 * Brand Configuration System
 * Provides flexible accent color and brand parameter management
 */

/**
 * Get brand configuration with priority: Admin Settings > JSON file > PHP constant > filter > default
 */
function rbf_get_brand_config() {
    // Start with default configuration
    $default_config = [
        'accent_color' => '#000000',
        'accent_color_light' => '#333333', 
        'accent_color_dark' => '#000000',
        'secondary_color' => '#f8b500',
        'border_radius' => '8px',
        // Future extensibility
        'logo_url' => '',
        'brand_name' => ''
    ];
    
    // 1. Check admin settings first (highest priority for user interface)
    $admin_settings = get_option('rbf_settings', []);
    if (!empty($admin_settings['accent_color']) || !empty($admin_settings['secondary_color']) || !empty($admin_settings['border_radius'])) {
        $config = $default_config;
        
        if (!empty($admin_settings['accent_color'])) {
            $config['accent_color'] = sanitize_hex_color($admin_settings['accent_color']);
            // Auto-generate light/dark variants
            $config['accent_color_light'] = rbf_lighten_color($config['accent_color'], 20);
            $config['accent_color_dark'] = rbf_darken_color($config['accent_color'], 10);
        }
        
        if (!empty($admin_settings['secondary_color'])) {
            $config['secondary_color'] = sanitize_hex_color($admin_settings['secondary_color']);
        }
        
        if (!empty($admin_settings['border_radius'])) {
            $config['border_radius'] = sanitize_text_field($admin_settings['border_radius']);
        }
    } else {
        // 2. Try to load from JSON file
        $json_config = rbf_load_brand_json();
        if ($json_config) {
            $config = array_merge($default_config, $json_config);
        } else {
            $config = $default_config;
        }
    }
    
    // 3. Check for PHP constant override (still allows override even with admin settings)
    if (defined('FPPR_ACCENT_COLOR')) {
        $config['accent_color'] = FPPR_ACCENT_COLOR;
        // Auto-generate variants when overridden by constant
        $config['accent_color_light'] = rbf_lighten_color($config['accent_color'], 20);
        $config['accent_color_dark'] = rbf_darken_color($config['accent_color'], 10);
    }
    if (defined('FPPR_ACCENT_COLOR_LIGHT')) {
        $config['accent_color_light'] = FPPR_ACCENT_COLOR_LIGHT;
    }
    if (defined('FPPR_ACCENT_COLOR_DARK')) {
        $config['accent_color_dark'] = FPPR_ACCENT_COLOR_DARK;
    }
    if (defined('FPPR_BORDER_RADIUS')) {
        $config['border_radius'] = FPPR_BORDER_RADIUS;
    }
    
    // 4. Apply filter for programmatic override (highest priority)
    $config = apply_filters('fppr_brand_config', $config);
    
    return $config;
}

/**
 * Load brand configuration from JSON file
 */
function rbf_load_brand_json() {
    // Look for fppr-brand.json in plugin directory first
    $plugin_json = RBF_PLUGIN_DIR . 'fppr-brand.json';
    
    // Then check wp-content directory for global overrides
    $global_json = WP_CONTENT_DIR . '/fppr-brand.json';
    
    $json_file = file_exists($global_json) ? $global_json : $plugin_json;
    
    if (!file_exists($json_file)) {
        return false;
    }
    
    $json_content = file_get_contents($json_file);
    if ($json_content === false) {
        return false;
    }
    
    $config = json_decode($json_content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        rbf_log('FPPR Brand Config: Invalid JSON in ' . $json_file);
        return false;
    }
    
    return $config;
}

/**
 * Get accent color for current context (with shortcode override support)
 */
function rbf_get_accent_color($override_color = '') {
    if (!empty($override_color)) {
        return sanitize_hex_color($override_color);
    }
    
    $config = rbf_get_brand_config();
    return $config['accent_color'];
}

/**
 * Generate CSS variables for brand configuration
 */
function rbf_generate_brand_css_vars($accent_override = '') {
    $config = rbf_get_brand_config();
    
    // Allow single-instance override
    if (!empty($accent_override)) {
        $config['accent_color'] = sanitize_hex_color($accent_override);
        // Auto-generate light/dark variants if only accent is overridden
        $config['accent_color_light'] = rbf_lighten_color($config['accent_color'], 20);
        $config['accent_color_dark'] = rbf_darken_color($config['accent_color'], 10);
    }
    
    $css_vars = [
        '--fppr-accent' => $config['accent_color'],
        '--fppr-accent-light' => $config['accent_color_light'],
        '--fppr-accent-dark' => $config['accent_color_dark'],
        '--fppr-secondary' => $config['secondary_color'],
        '--fppr-radius' => $config['border_radius'],
        // Maintain backward compatibility
        '--rbf-primary' => $config['accent_color'],
        '--rbf-primary-light' => $config['accent_color_light'],
        '--rbf-primary-dark' => $config['accent_color_dark'],
    ];
    
    return $css_vars;
}

/**
 * Lighten a hex color by percentage
 */
function rbf_lighten_color($hex, $percent) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) == 3) {
        $hex = str_repeat($hex[0], 2) . str_repeat($hex[1], 2) . str_repeat($hex[2], 2);
    }
    
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    
    // Lighten by moving towards white (255)
    $r = min(255, $r + ((255 - $r) * $percent / 100));
    $g = min(255, $g + ((255 - $g) * $percent / 100));
    $b = min(255, $b + ((255 - $b) * $percent / 100));
    
    return sprintf('#%02x%02x%02x', round($r), round($g), round($b));
}

/**
 * Darken a hex color by percentage
 */
function rbf_darken_color($hex, $percent) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) == 3) {
        $hex = str_repeat($hex[0], 2) . str_repeat($hex[1], 2) . str_repeat($hex[2], 2);
    }
    
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    
    $r = max(0, $r - ($r * $percent / 100));
    $g = max(0, $g - ($g * $percent / 100));
    $b = max(0, $b - ($b * $percent / 100));
    
    return sprintf('#%02x%02x%02x', $r, $g, $b);
}

/**
 * Calculate required buffer time for a booking
 * 
 * @param string $meal_id Meal ID
 * @param int $people_count Number of people
 * @return int Buffer time in minutes
 */
function rbf_calculate_buffer_time($meal_id, $people_count) {
    $meal_config = rbf_get_meal_config($meal_id);
    if (!$meal_config) {
        return 15; // Default buffer if meal not found
    }
    
    $base_buffer = intval($meal_config['buffer_time_minutes'] ?? 15);
    $per_person_buffer = intval($meal_config['buffer_time_per_person'] ?? 5);
    
    return $base_buffer + ($per_person_buffer * $people_count);
}

/**
 * Calculate dynamic slot duration based on meal type and party size
 * 
 * @param string $meal_id Meal ID  
 * @param int $people_count Number of people
 * @return int Slot duration in minutes
 */
function rbf_calculate_slot_duration($meal_id, $people_count) {
    $meal_config = rbf_get_meal_config($meal_id);
    if (!$meal_config) {
        return 90; // Default duration if meal not found
    }
    
    // Get base duration from meal configuration
    $base_duration = intval($meal_config['slot_duration_minutes'] ?? 90);
    
    // Apply group rule: groups >6 people get 120 minutes
    if ($people_count > 6) {
        return 120;
    }
    
    return $base_duration;
}

/**
 * Check if a time slot conflicts with buffer requirements
 * 
 * @param string $date Date in Y-m-d format
 * @param string $time Time in H:i format
 * @param string $meal_id Meal ID
 * @param int $people_count Number of people
 * @return array|true Returns array with error info if conflict, true if valid
 */
function rbf_validate_buffer_time($date, $time, $meal_id, $people_count) {
    global $wpdb;
    
    $required_buffer = rbf_calculate_buffer_time($meal_id, $people_count);
    $tz = rbf_wp_timezone();
    $booking_datetime = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $time, $tz);
    
    if (!$booking_datetime) {
        return [
            'error' => true,
            'message' => rbf_translate_string('Orario non valido.')
        ];
    }
    
    // Get existing bookings for the same date and meal
    $existing_bookings = $wpdb->get_results($wpdb->prepare(
        "SELECT pm_time.meta_value as time, pm_people.meta_value as people
         FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'rbf_data'
         INNER JOIN {$wpdb->postmeta} pm_meal ON p.ID = pm_meal.post_id AND pm_meal.meta_key = 'rbf_meal'
         INNER JOIN {$wpdb->postmeta} pm_time ON p.ID = pm_time.post_id AND pm_time.meta_key = 'rbf_time'
         INNER JOIN {$wpdb->postmeta} pm_people ON p.ID = pm_people.post_id AND pm_people.meta_key = 'rbf_persone'
         WHERE p.post_type = 'rbf_booking' AND p.post_status = 'publish'
         AND pm_date.meta_value = %s AND pm_meal.meta_value = %s",
        $date, $meal_id
    ));
    
    foreach ($existing_bookings as $existing) {
        $existing_datetime = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $existing->time, $tz);
        if (!$existing_datetime) continue;
        
        $existing_people = intval($existing->people);
        $existing_buffer = rbf_calculate_buffer_time($meal_id, $existing_people);
        
        $time_diff_minutes = abs(($booking_datetime->getTimestamp() - $existing_datetime->getTimestamp()) / 60);
        $needed_buffer = max($required_buffer, $existing_buffer);
        
        if ($time_diff_minutes < $needed_buffer) {
            return [
                'error' => true,
                'message' => sprintf(
                    rbf_translate_string('Questo orario non rispetta il buffer di %d minuti richiesto. Scegli un altro orario.'),
                    $needed_buffer
                )
            ];
        }
    }
    
    return true;
}

/**
 * Get effective capacity with overbooking limit
 * 
 * @param string $meal_id Meal ID
 * @return int Effective capacity including overbooking
 */
function rbf_get_effective_capacity($meal_id) {
    $meal_config = rbf_get_meal_config($meal_id);
    if (!$meal_config) {
        return 0;
    }
    
    $base_capacity = intval($meal_config['capacity'] ?? 30);
    $overbooking_limit = intval($meal_config['overbooking_limit'] ?? 10);
    
    // Calculate overbooking allowance
    $overbooking_spots = round($base_capacity * ($overbooking_limit / 100));
    
    return $base_capacity + $overbooking_spots;
}

/**
 * Calculate occupancy percentage for a date and meal
 * 
 * @param string $date Date in Y-m-d format
 * @param string $meal_id Meal ID
 * @return float Occupancy percentage (0-100)
 */
function rbf_calculate_occupancy_percentage($date, $meal_id) {
    $total_capacity = rbf_get_effective_capacity($meal_id);
    if ($total_capacity <= 0) {
        return 0; // No capacity configured
    }
    
    global $wpdb;
    $spots_taken = $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(pm_people.meta_value)
         FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm_people ON p.ID = pm_people.post_id AND pm_people.meta_key = 'rbf_persone'
         INNER JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'rbf_data'
         INNER JOIN {$wpdb->postmeta} pm_slot ON p.ID = pm_slot.post_id AND pm_slot.meta_key = 'rbf_meal'
         WHERE p.post_type = 'rbf_booking' AND p.post_status = 'publish'
         AND pm_date.meta_value = %s AND pm_slot.meta_value = %s",
        $date, $meal_id
    ));
    
    $spots_taken = intval($spots_taken);
    return ($spots_taken / $total_capacity) * 100;
}

/**
 * Get availability status for a date and meal
 * 
 * @param string $date Date in Y-m-d format
 * @param string $meal_id Meal ID
 * @return array Status information with level, percentage, remaining spots
 */
function rbf_get_availability_status($date, $meal_id) {
    $occupancy = rbf_calculate_occupancy_percentage($date, $meal_id);
    $total_capacity = rbf_get_effective_capacity($meal_id);
    $remaining = rbf_get_remaining_capacity($date, $meal_id);
    
    // Define thresholds
    $level = 'available';  // green
    if ($occupancy >= 100) {
        $level = 'full';     // red
    } elseif ($occupancy >= 70) {
        $level = 'limited';  // yellow
    }
    
    return [
        'level' => $level,
        'occupancy' => round($occupancy, 1),
        'remaining' => $remaining,
        'total' => $total_capacity
    ];
}

/**
 * Anti-bot detection system
 * Detects suspicious submission patterns that indicate automated behavior
 * 
 * @param array $form_data POST data from form submission
 * @return array Detection result with is_bot, severity, and reason
 */
function rbf_detect_bot_submission($form_data) {
    $suspicion_score = 0;
    $reasons = [];
    
    // 1. Honeypot field check (highest priority)
    if (!empty($form_data['rbf_website'])) {
        return [
            'is_bot' => true,
            'severity' => 'high',
            'reason' => 'Honeypot field filled',
            'score' => 100
        ];
    }
    
    // 2. Timestamp validation (form submission timing)
    if (isset($form_data['rbf_form_timestamp'])) {
        $form_timestamp = intval($form_data['rbf_form_timestamp']);
        $current_time = time();
        $submission_time = $current_time - $form_timestamp;
        
        // Too fast (less than 5 seconds) - likely bot
        if ($submission_time < 5) {
            $suspicion_score += 80;
            $reasons[] = "Too fast submission: {$submission_time}s";
        }
        // Reasonable time range for humans (5s to 30 minutes)
        elseif ($submission_time <= 1800) {
            // Normal submission time, no penalty
        }
        // Too slow (over 30 minutes) - might be bot or abandoned session
        else {
            $suspicion_score += 30;
            $reasons[] = "Very slow submission: " . floor($submission_time / 60) . "m";
        }
    } else {
        // Missing timestamp is suspicious
        $suspicion_score += 40;
        $reasons[] = "Missing form timestamp";
    }
    
    // 3. User agent analysis
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (rbf_detect_bot_user_agent($user_agent)) {
        $suspicion_score += 60;
        $reasons[] = "Bot user agent detected";
    }
    
    // 4. Field completion pattern analysis
    $pattern_score = rbf_analyze_field_patterns($form_data);
    $suspicion_score += $pattern_score;
    if ($pattern_score > 0) {
        $reasons[] = "Suspicious field patterns";
    }
    
    // 5. Rate limiting check (multiple submissions from same IP)
    $rate_limit_score = rbf_check_submission_rate();
    $suspicion_score += $rate_limit_score;
    if ($rate_limit_score > 0) {
        $reasons[] = "High submission rate from IP";
    }
    
    // Determine result based on score
    $is_bot = $suspicion_score >= 70;
    $severity = $suspicion_score >= 90 ? 'high' : ($suspicion_score >= 40 ? 'medium' : 'low');
    
    return [
        'is_bot' => $is_bot,
        'severity' => $severity,
        'reason' => implode(', ', $reasons),
        'score' => $suspicion_score
    ];
}

/**
 * Detect bot user agents
 * 
 * @param string $user_agent User agent string
 * @return bool True if bot detected
 */
function rbf_detect_bot_user_agent($user_agent) {
    if (empty($user_agent)) {
        return true; // Missing user agent is suspicious
    }
    
    $bot_patterns = [
        'bot', 'crawler', 'spider', 'scraper', 'curl', 'wget', 'python',
        'http', 'php', 'ruby', 'perl', 'java', 'automated', 'headless'
    ];
    
    $user_agent_lower = strtolower($user_agent);
    
    foreach ($bot_patterns as $pattern) {
        if (strpos($user_agent_lower, $pattern) !== false) {
            return true;
        }
    }
    
    // Check for very short or suspicious user agent strings
    if (strlen($user_agent) < 20) {
        return true;
    }
    
    return false;
}

/**
 * Analyze field completion patterns for bot-like behavior
 * 
 * @param array $form_data Form submission data
 * @return int Suspicion score (0-50)
 */
function rbf_analyze_field_patterns($form_data) {
    $score = 0;
    
    // Check for obviously fake or test data
    $test_patterns = [
        'test', 'bot', 'automated', 'fake', 'example', 'asdf', 'qwerty',
        '123456', 'aaaa', 'bbbb', 'cccc', 'dddd'
    ];
    
    $name = strtolower(($form_data['rbf_nome'] ?? '') . ' ' . ($form_data['rbf_cognome'] ?? ''));
    $email = strtolower($form_data['rbf_email'] ?? '');
    
    foreach ($test_patterns as $pattern) {
        if (strpos($name, $pattern) !== false || strpos($email, $pattern) !== false) {
            $score += 25;
            break;
        }
    }
    
    // Check for identical name/surname (unlikely for real users)
    if (!empty($form_data['rbf_nome']) && !empty($form_data['rbf_cognome'])) {
        if (strtolower($form_data['rbf_nome']) === strtolower($form_data['rbf_cognome'])) {
            $score += 15;
        }
    }
    
    // Check for very generic email domains commonly used by bots
    $suspicious_domains = ['10minutemail.com', 'guerrillamail.com', 'tempmail.org'];
    foreach ($suspicious_domains as $domain) {
        if (strpos($email, $domain) !== false) {
            $score += 20;
            break;
        }
    }
    
    return min($score, 50); // Cap at 50
}

/**
 * Check submission rate from current IP
 * 
 * @return int Suspicion score (0-30)
 */
function rbf_check_submission_rate() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (empty($ip)) {
        return 0;
    }
    
    // Check transient for recent submissions from this IP
    $transient_key = 'rbf_ip_submissions_' . md5($ip);
    $recent_submissions = get_transient($transient_key);
    
    if (!is_array($recent_submissions)) {
        $recent_submissions = [];
    }
    
    // Clean old submissions (older than 1 hour)
    $one_hour_ago = time() - 3600;
    $recent_submissions = array_filter($recent_submissions, function($timestamp) use ($one_hour_ago) {
        return $timestamp > $one_hour_ago;
    });
    
    // Add current submission
    $recent_submissions[] = time();
    
    // Store back in transient for 1 hour
    set_transient($transient_key, $recent_submissions, 3600);
    
    // Calculate score based on submission frequency
    $submission_count = count($recent_submissions);
    
    if ($submission_count > 10) {
        return 30; // Very high rate
    } elseif ($submission_count > 5) {
        return 20; // High rate
    } elseif ($submission_count > 3) {
        return 10; // Moderate rate
    }
    
    return 0; // Normal rate
}

/**
 * Verify reCAPTCHA v3 token
 * 
 * @param string $token reCAPTCHA token from frontend
 * @param string $action Expected action name
 * @return array Result with success, score, and details
 */
function rbf_verify_recaptcha($token, $action = 'booking_submit') {
    $options = rbf_get_settings();
    $secret_key = $options['recaptcha_secret_key'] ?? '';
    $threshold = floatval($options['recaptcha_threshold'] ?? 0.5);
    
    if (empty($secret_key) || empty($token)) {
        return [
            'success' => true, // Allow if reCAPTCHA not configured
            'score' => 1.0,
            'reason' => 'reCAPTCHA not configured'
        ];
    }
    
    // Verify token with Google
    $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
        'body' => [
            'secret' => $secret_key,
            'response' => $token,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
        ],
        'timeout' => 10
    ]);
    
    if (is_wp_error($response)) {
        rbf_log('reCAPTCHA verification failed: ' . $response->get_error_message());
        return [
            'success' => true, // Allow on API failure to avoid blocking legitimate users
            'score' => 0.5,
            'reason' => 'API error: ' . $response->get_error_message()
        ];
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if (!$data || !isset($data['success'])) {
        return [
            'success' => true, // Allow on invalid response
            'score' => 0.5,
            'reason' => 'Invalid API response'
        ];
    }
    
    if (!$data['success']) {
        $errors = $data['error-codes'] ?? ['unknown-error'];
        return [
            'success' => false,
            'score' => 0.0,
            'reason' => 'reCAPTCHA verification failed: ' . implode(', ', $errors)
        ];
    }
    
    $score = floatval($data['score'] ?? 0);
    $api_action = $data['action'] ?? '';
    
    // Verify action matches (if provided)
    if (!empty($action) && $api_action !== $action) {
        return [
            'success' => false,
            'score' => $score,
            'reason' => "Action mismatch: expected '$action', got '$api_action'"
        ];
    }
    
    // Check if score meets threshold
    $success = $score >= $threshold;
    
    return [
        'success' => $success,
        'score' => $score,
        'reason' => $success ? 'Passed threshold' : "Score $score below threshold $threshold"
    ];
}

/**
 * Check slot availability for booking movement
 * Used by drag & drop functionality to validate if a slot can accommodate a booking
 * 
 * @param string $date Date in YYYY-MM-DD format
 * @param string $meal Meal type (pranzo, cena, etc.)
 * @param string $time Time in HH:MM format
 * @param int $people Number of people
 * @return bool True if slot is available, false otherwise
 */
function rbf_check_slot_availability($date, $meal, $time, $people) {
    // Basic input validation
    if (empty($date) || empty($meal) || empty($time) || $people <= 0) {
        return false;
    }
    
    // Check if date is in the past
    if (strtotime($date) < strtotime('today')) {
        return false;
    }
    
    // Get meal configuration
    $meals = rbf_get_active_meals();
    $meal_config = null;
    foreach ($meals as $m) {
        if ($m['id'] === $meal) {
            $meal_config = $m;
            break;
        }
    }
    
    if (!$meal_config) {
        return false;
    }
    
    // Check if meal is available on this day
    if (!rbf_is_meal_available_on_day($meal, $date)) {
        return false;
    }
    
    // Check if time is within meal time slots
    $time_slots = explode(',', $meal_config['time_slots']);
    if (!in_array($time, $time_slots)) {
        return false;
    }
    
    // Get current capacity usage (excluding the booking being moved)
    $current_bookings = rbf_calculate_current_bookings($date, $meal);
    $meal_capacity = intval($meal_config['capacity']);
    
    // Calculate overbooking allowance
    $overbooking_limit = intval($meal_config['overbooking_limit'] ?? 0);
    $overbooking_spots = round($meal_capacity * ($overbooking_limit / 100));
    $effective_capacity = $meal_capacity + $overbooking_spots;
    
    // Check if there's enough capacity
    $remaining_capacity = $effective_capacity - $current_bookings;
    
    return $remaining_capacity >= $people;
}

/**
 * Reserve slot capacity for booking movement
 * Wrapper function for optimistic locking system
 * 
 * @param string $date Date in YYYY-MM-DD format
 * @param string $meal Meal type (pranzo, cena, etc.)
 * @param int $people Number of people
 * @return bool True if successfully reserved, false otherwise
 */
function rbf_reserve_slot_capacity($date, $meal, $people) {
    // Use the optimistic locking system to reserve capacity
    $result = rbf_book_slot_optimistic($date, $meal, $people);
    return $result['success'];
}