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
        'webmaster_email' => get_option('admin_email', ''),
        'brevo_api' => '',
        'brevo_list_it' => '',
        'brevo_list_en' => '',
        'closed_dates' => '',
        // Note: Advance booking limits removed - using fixed 1-hour minimum rule
        'min_advance_minutes' => 60, // Fixed at 1 hour for system compatibility
        'max_advance_minutes' => 0, // No maximum limit
    ];
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
        
        $tz_string = get_option('timezone_string');
        if ($tz_string) return new DateTimeZone($tz_string);
        $offset = (float) get_option('gmt_offset', 0);
        $hours = (int) $offset;
        $minutes = abs($offset - $hours) * 60;
        $sign = $offset < 0 ? '-' : '+';
        return new DateTimeZone(sprintf('%s%02d:%02d', $sign, abs($hours), $minutes));
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
 * Update booking status with history tracking
 */
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