<?php
/**
 * Utility functions for Restaurant Booking Plugin
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
        'webmaster_email' => '',
        'brevo_api' => '',
        'brevo_list_it' => '',
        'brevo_list_en' => '',
        'closed_dates' => '',
        // Note: Advance booking limits removed - using fixed 1-hour minimum rule
        'min_advance_minutes' => 60, // Fixed at 1 hour for system compatibility
        'max_advance_minutes' => 0, // No maximum limit
        
        // New configurable meals system
        'use_custom_meals' => 'no',
        'custom_meals' => rbf_get_default_custom_meals(),
    ];
}

/**
 * Get default custom meals configuration
 */
function rbf_get_default_custom_meals() {
    return [
        [
            'id' => 'pranzo',
            'name' => 'Pranzo',
            'capacity' => 30,
            'time_slots' => '12:00,12:30,13:00,13:30,14:00',
            'price' => 35.00,
            'enabled' => true,
            'tooltip' => 'Di Domenica il servizio è Brunch con menù alla carta.',
            'available_days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun']
        ],
        [
            'id' => 'aperitivo',
            'name' => 'Aperitivo',
            'capacity' => 25,
            'time_slots' => '17:00,17:30,18:00',
            'price' => 15.00,
            'enabled' => true,
            'tooltip' => '',
            'available_days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat']
        ],
        [
            'id' => 'cena',
            'name' => 'Cena',
            'capacity' => 40,
            'time_slots' => '19:00,19:30,20:00,20:30',
            'price' => 50.00,
            'enabled' => true,
            'tooltip' => '',
            'available_days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat']
        ],
        [
            'id' => 'brunch',
            'name' => 'Brunch',
            'capacity' => 30,
            'time_slots' => '12:00,12:30,13:00,13:30',
            'price' => 35.00,
            'enabled' => true,
            'tooltip' => 'Disponibile solo la domenica con menù speciale.',
            'available_days' => ['sun']
        ]
    ];
}

/**
 * Get active meals configuration
 * Returns either custom meals or legacy meal configuration
 */
function rbf_get_active_meals() {
    $options = wp_parse_args(get_option('rbf_settings', []), rbf_get_default_settings());
    
    // If custom meals are enabled, return them
    if (($options['use_custom_meals'] ?? 'no') === 'yes') {
        $custom_meals = $options['custom_meals'] ?? rbf_get_default_custom_meals();
        // Filter only enabled meals
        return array_filter($custom_meals, function($meal) {
            return $meal['enabled'] ?? false;
        });
    }
    
    // Fallback to legacy meal configuration
    $legacy_meals = [];
    
    // Check each legacy meal type
    $legacy_types = [
        'pranzo' => 'Pranzo',
        'aperitivo' => 'Aperitivo', 
        'cena' => 'Cena'
    ];
    
    foreach ($legacy_types as $id => $name) {
        if (!empty($options["capienza_{$id}"]) && !empty($options["orari_{$id}"])) {
            $legacy_meals[] = [
                'id' => $id,
                'name' => $name,
                'capacity' => (int) $options["capienza_{$id}"],
                'time_slots' => $options["orari_{$id}"],
                'price' => (float) ($options["valore_{$id}"] ?? 0),
                'enabled' => true,
                'available_days' => rbf_get_legacy_available_days($options)
            ];
        }
    }
    
    // Add brunch for Sunday only if we have lunch settings
    if (!empty($options['capienza_pranzo']) && !empty($options['orari_pranzo'])) {
        $legacy_meals[] = [
            'id' => 'brunch',
            'name' => 'Brunch',
            'capacity' => (int) $options['capienza_pranzo'],
            'time_slots' => $options['orari_pranzo'],
            'price' => (float) ($options['valore_pranzo'] ?? 0),
            'enabled' => true,
            'available_days' => ['sun']
        ];
    }
    
    return $legacy_meals;
}

/**
 * Get legacy available days from global settings
 */
function rbf_get_legacy_available_days($options) {
    $days = [];
    $day_mapping = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
    
    foreach ($day_mapping as $day) {
        if (($options["open_{$day}"] ?? 'yes') === 'yes') {
            $days[] = $day;
        }
    }
    
    return $days;
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
            if (WP_DEBUG) {
                error_log('RBF Plugin: Timezone creation failed: ' . $e->getMessage());
            }
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
        'Usa configurazione personalizzata' => 'Use custom configuration',
        'No - Usa impostazioni classiche' => 'No - Use classic settings',
        'Sì - Configura pasti personalizzati' => 'Yes - Configure custom meals',
        'Scegli se utilizzare la configurazione classica o quella personalizzata per i pasti.' => 'Choose whether to use the classic or custom configuration for meals.',
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
        'Rimuovi Pasto' => 'Remove Meal',
        'Aggiungi Pasto' => 'Add Meal',
        'Capienza e Orari (Classico)' => 'Capacity and Timetable (Classic)',
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
        'Marketing' => 'Marketing',
        'Accettato' => 'Accepted',

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
        'Tooltip informativo' => 'Information Tooltip',
        'Testo informativo che apparirà quando questo pasto viene selezionato (opzionale)' => 'Information text that will appear when this meal is selected (optional)',
        'Di Domenica il servizio è Brunch con menù alla carta.' => 'On Sundays the service is Brunch with à la carte menu.',
        'Disponibile solo la domenica con menù speciale.' => 'Available only on Sundays with special menu.',
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
 * Centralized phone number validation
 */
function rbf_validate_phone($phone) {
    $phone = sanitize_text_field($phone);
    // Basic phone validation - at least 8 digits
    if (strlen(preg_replace('/[^0-9]/', '', $phone)) < 8) {
        return ['error' => true, 'message' => rbf_translate_string('Il numero di telefono inserito non è valido.')];
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
    if (WP_DEBUG) {
        error_log("RBF Error [{$context}]: {$message}");
    }
    
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
        wp_safe_redirect(add_query_arg('rbf_success', urlencode($message), $redirect_url));
        exit;
    }
    
    // Fallback: return success array
    return array_merge(['success' => true, 'message' => $message], $data);
}

/**
 * Centralized asset version helper for cache-busting
 * Consolidates the RBF_VERSION . '.' . time() pattern used across files
 */
function rbf_get_asset_version() {
    return RBF_VERSION . '.' . time();
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
function fp_normalize_bucket($gclid = '', $fbclid = '') {
    // Clean and validate input parameters
    $gclid = trim($gclid);
    $fbclid = trim($fbclid);
    
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
                WHEN pm_meal.meta_value = 'pranzo' THEN pm_people.meta_value * 35
                WHEN pm_meal.meta_value = 'cena' THEN pm_people.meta_value * 50
                WHEN pm_meal.meta_value = 'aperitivo' THEN pm_people.meta_value * 15
                ELSE 0
            END) as estimated_revenue
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm_bucket ON (p.ID = pm_bucket.post_id AND pm_bucket.meta_key = 'rbf_source_bucket')
        LEFT JOIN {$wpdb->postmeta} pm_people ON (p.ID = pm_people.post_id AND pm_people.meta_key = 'rbf_persone')
        LEFT JOIN {$wpdb->postmeta} pm_meal ON (p.ID = pm_meal.post_id AND pm_meal.meta_key = 'rbf_orario')
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
 * Centralized input sanitization helper
 * Reduces repetitive sanitize_text_field calls across the codebase
 */
function rbf_sanitize_input_fields(array $input_data, array $field_map) {
    $sanitized = [];
    
    foreach ($field_map as $key => $type) {
        if (!isset($input_data[$key])) {
            continue;
        }
        
        $value = $input_data[$key];
        
        switch ($type) {
            case 'text':
                $sanitized[$key] = sanitize_text_field($value);
                break;
            case 'email':
                $sanitized[$key] = sanitize_email($value);
                break;
            case 'textarea':
                $sanitized[$key] = sanitize_textarea_field($value);
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
            default:
                $sanitized[$key] = sanitize_text_field($value);
        }
    }
    
    return $sanitized;
}

function rbf_update_booking_status($booking_id, $new_status, $note = '') {
    $old_status = get_post_meta($booking_id, 'rbf_booking_status', true);
    
    // Update status
    update_post_meta($booking_id, 'rbf_booking_status', $new_status);
    update_post_meta($booking_id, 'rbf_status_updated', current_time('Y-m-d H:i:s'));
    
    // Add to history
    $history = get_post_meta($booking_id, 'rbf_status_history', true);
    if (!is_array($history)) $history = [];
    
    $history[] = [
        'timestamp' => current_time('Y-m-d H:i:s'),
        'from' => $old_status ?: 'pending',
        'to' => $new_status,
        'note' => $note,
        'user' => get_current_user_id()
    ];
    
    update_post_meta($booking_id, 'rbf_status_history', $history);
    
    // Trigger status change hook for notifications
    do_action('rbf_booking_status_changed', $booking_id, $old_status, $new_status, $note);
    
    return true;
}