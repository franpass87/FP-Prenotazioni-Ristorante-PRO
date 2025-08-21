<?php
/**
 * Utility functions for Restaurant Booking Plugin
 *
 * @package RBF
 * @since 9.3.2
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Utility functions class
 */
class RBF_Utils {

    /**
     * Get WordPress timezone
     * 
     * @return DateTimeZone WordPress timezone object
     */
    public static function wp_timezone() {
        if (function_exists('wp_timezone')) {
            return wp_timezone();
        }
        
        $tz_string = get_option('timezone_string');
        if ($tz_string) {
            return new DateTimeZone($tz_string);
        }
        
        $offset = (float) get_option('gmt_offset', 0);
        $hours = (int) $offset;
        $minutes = abs($offset - $hours) * 60;
        $sign = $offset < 0 ? '-' : '+';
        
        return new DateTimeZone(sprintf('%s%02d:%02d', $sign, abs($hours), $minutes));
    }

    /**
     * Get current language (limited to it/en)
     * Supports Polylang/WPML; fallback to 'en'
     * 
     * @return string Language code ('it' or 'en')
     */
    public static function current_lang() {
        // Polylang support
        if (function_exists('pll_current_language')) {
            $slug = pll_current_language('slug');
            return in_array($slug, ['it', 'en'], true) ? $slug : 'en';
        }
        
        // WPML support
        if (defined('ICL_LANGUAGE_CODE')) {
            $slug = ICL_LANGUAGE_CODE;
            return in_array($slug, ['it', 'en'], true) ? $slug : 'en';
        }
        
        // WordPress locale fallback
        $slug = substr(get_locale(), 0, 2);
        return in_array($slug, ['it', 'en'], true) ? $slug : 'en';
    }

    /**
     * Translate string to English if current language is English
     * 
     * @param string $text Text to translate
     * @return string Translated text or original text
     */
    public static function translate_string($text) {
        $locale = self::current_lang();
        if ($locale !== 'en') {
            return $text;
        }

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
            'Acconsento al trattamento dei dati secondo l\'<a href="%s" target="_blank">Informativa sulla Privacy</a>' => 'I consent to the processing of my data in accordance with the <a href="%s" target="_blank">Privacy Policy</a>',
            'Acconsento a ricevere comunicazioni promozionali via email e/o messaggi riguardanti eventi, offerte o novità.' => 'I agree to receive promotional emails and/or messages about events, offers, or news.',
            'Devi accettare la Privacy Policy per procedere.' => 'You must accept the Privacy Policy to proceed.',
            'Pranzo' => 'Lunch',
            'Aperitivo' => 'Aperitif',
            'Cena' => 'Dinner',
        ];

        return $translations[$text] ?? $text;
    }

    /**
     * Get default plugin settings
     * 
     * @return array Default settings array
     */
    public static function get_default_settings() {
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
     * Sanitize plugin settings
     * 
     * @param array $input Raw input from settings form
     * @return array Sanitized settings
     */
    public static function sanitize_settings($input) {
        $defaults = self::get_default_settings();
        $output = [];
        $input = (array) $input;

        // Integer fields
        $int_keys = ['capienza_pranzo','capienza_cena','capienza_aperitivo','brevo_list_it','brevo_list_en'];
        foreach ($int_keys as $key) {
            $output[$key] = isset($input[$key]) ? absint($input[$key]) : ($defaults[$key] ?? 0);
        }

        // Text fields  
        $text_keys = ['orari_pranzo','orari_cena','orari_aperitivo','brevo_api','ga4_api_secret','meta_access_token'];
        foreach ($text_keys as $key) {
            $output[$key] = isset($input[$key]) ? sanitize_text_field(trim($input[$key])) : ($defaults[$key] ?? '');
        }

        // GA4 ID validation
        if (isset($input['ga4_id']) && !empty($input['ga4_id']) && !preg_match('/^G-[A-Z0-9]+$/', $input['ga4_id'])) {
            $output['ga4_id'] = '';
            add_settings_error('rbf_settings', 'invalid_ga4_id', self::translate_string('ID GA4 non valido. Deve essere nel formato G-XXXXXXXXXX.'));
        } else {
            $output['ga4_id'] = isset($input['ga4_id']) ? sanitize_text_field(trim($input['ga4_id'])) : ($defaults['ga4_id'] ?? '');
        }

        // Meta Pixel ID validation
        if (isset($input['meta_pixel_id']) && !empty($input['meta_pixel_id']) && !ctype_digit($input['meta_pixel_id'])) {
            $output['meta_pixel_id'] = '';
            add_settings_error('rbf_settings', 'invalid_meta_pixel_id', self::translate_string('ID Meta Pixel non valido. Deve essere un numero.'));
        } else {
            $output['meta_pixel_id'] = isset($input['meta_pixel_id']) ? sanitize_text_field(trim($input['meta_pixel_id'])) : ($defaults['meta_pixel_id'] ?? '');
        }

        // Email validation
        if (isset($input['notification_email'])) {
            $output['notification_email'] = sanitize_email($input['notification_email']);
        }

        // Float fields
        $float_keys = ['valore_pranzo','valore_cena','valore_aperitivo'];
        foreach ($float_keys as $key) {
            $output[$key] = isset($input[$key]) ? floatval($input[$key]) : ($defaults[$key] ?? 0);
        }

        // Day checkboxes
        $days = ['mon','tue','wed','thu','fri','sat','sun'];
        foreach ($days as $day) {
            $output["open_{$day}"] = (isset($input["open_{$day}"]) && $input["open_{$day}"] === 'yes') ? 'yes' : 'no';
        }

        // Closed dates
        if (isset($input['closed_dates'])) {
            $output['closed_dates'] = sanitize_textarea_field($input['closed_dates']);
        }

        return $output;
    }

    /**
     * Parse closed dates from settings
     * 
     * @param array|null $options Plugin options (null to get from DB)
     * @return array Array with 'singles' and 'ranges' keys
     */
    public static function get_closed_specific($options = null) {
        if (is_null($options)) {
            $options = get_option('rbf_settings', self::get_default_settings());
        }
        
        $closed_dates_str = $options['closed_dates'] ?? '';
        $closed_items = array_filter(array_map('trim', explode("\n", $closed_dates_str)));
        
        $singles = [];
        $ranges = [];
        
        foreach ($closed_items as $item) {
            if (strpos($item, '-') !== false) {
                list($start, $end) = array_map('trim', explode('-', $item, 2));
                $start_ok = DateTime::createFromFormat('Y-m-d', $start) !== false;
                $end_ok = DateTime::createFromFormat('Y-m-d', $end) !== false;
                if ($start_ok && $end_ok) {
                    $ranges[] = ['from' => $start, 'to' => $end];
                }
            } else {
                if (DateTime::createFromFormat('Y-m-d', $item) !== false) {
                    $singles[] = $item;
                }
            }
        }
        
        return ['singles' => $singles, 'ranges' => $ranges];
    }
}