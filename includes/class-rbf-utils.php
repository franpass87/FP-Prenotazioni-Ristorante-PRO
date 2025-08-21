<?php

/**
 * Utility class for RBF Restaurant Booking Plugin
 * Provides common helper functions for timezone, language, and translations
 */
class RBF_Utils {
    
    /**
     * Get WordPress timezone compatible with older WP versions
     */
    public static function get_timezone() {
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
     * Get current language (it/en only) with Polylang/WPML support
     */
    public static function get_current_lang() {
        // Polylang support
        if (function_exists('pll_current_language')) {
            $slug = pll_current_language('slug');
            return in_array($slug, ['it','en'], true) ? $slug : 'en';
        }
        
        // WPML support
        if (defined('ICL_LANGUAGE_CODE')) {
            $slug = ICL_LANGUAGE_CODE;
            return in_array($slug, ['it','en'], true) ? $slug : 'en';
        }
        
        // Fallback to WordPress locale
        $slug = substr(get_locale(), 0, 2);
        return in_array($slug, ['it','en'], true) ? $slug : 'en';
    }
    
    /**
     * Get translations array for strings
     */
    public static function get_translations() {
        return [
            // Backend UI
            'Impostazioni Prenotazioni Ristorante' => 'Restaurant Booking Settings',
            'Capienza e Orari' => 'Capacity & Hours',
            'Capienza Pranzo' => 'Lunch Capacity',
            'Orari Pranzo (inclusa Domenica)' => 'Lunch Hours (including Sunday)',
            'Capienza Cena' => 'Dinner Capacity', 
            'Orari Cena' => 'Dinner Hours',
            'Capienza Aperitivo' => 'Aperitif Capacity',
            'Orari Aperitivo' => 'Aperitif Hours',
            'Giorni aperti' => 'Open Days',
            'Lunedì' => 'Monday',
            'Martedì' => 'Tuesday', 
            'Mercoledì' => 'Wednesday',
            'Giovedì' => 'Thursday',
            'Venerdì' => 'Friday',
            'Sabato' => 'Saturday',
            'Domenica' => 'Sunday',
            'Chiusure Straordinarie' => 'Special Closures',
            'Date Chiuse (una per riga, formato Y-m-d o Y-m-d - Y-m-d)' => 'Closed Dates (one per line, Y-m-d or Y-m-d - Y-m-d format)',
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
            'Aggiungi Prenotazione' => 'Add Booking',
            'Prenotazioni' => 'Bookings',
            'Impostazioni' => 'Settings',
            'Calendario' => 'Calendar',
            'Aggiungi Manualmente' => 'Add Manually',
            
            // Frontend UI
            'Prenota un tavolo' => 'Book a table',
            'Seleziona il tipo di servizio' => 'Select service type',
            'Pranzo' => 'Lunch',
            'Cena' => 'Dinner', 
            'Aperitivo' => 'Aperitif',
            'Seleziona la data' => 'Select date',
            'Seleziona l\'orario' => 'Select time',
            'Numero di persone' => 'Number of people',
            'I tuoi dati' => 'Your details',
            'Nome' => 'Name',
            'Cognome' => 'Surname',
            'Email' => 'Email',
            'Telefono' => 'Phone',
            'Allergie/Note' => 'Allergies/Notes',
            'Prenota' => 'Book',
            'Grazie! La tua prenotazione è stata inviata con successo.' => 'Thank you! Your booking has been sent successfully.',
            'Caricamento...' => 'Loading...',
            'Non ci sono posti disponibili per questa data/orario.' => 'No places available for this date/time.',
            'Errore nel caricamento degli orari disponibili.' => 'Error loading available times.',
            'Seleziona prima il tipo di servizio.' => 'Please select service type first.',
            'Acconsento al trattamento dei dati secondo l\'<a href="%s" target="_blank">Informativa sulla Privacy</a>' => 'I consent to data processing according to the <a href="%s" target="_blank">Privacy Policy</a>',
            'Acconsento a ricevere comunicazioni promozionali via email e/o messaggi riguardanti eventi, offerte o novità.' => 'I consent to receive promotional communications via email and/or messages about events, offers or news.',
            'Devi accettare l\'informativa sulla privacy per procedere.' => 'You must accept the privacy policy to proceed.',
            'Il numero di telefono inserito non è valido.' => 'The phone number entered is not valid.',
        ];
    }
    
    /**
     * Translate a string based on current language
     */
    public static function translate_string($text) {
        $locale = self::get_current_lang();
        if ($locale !== 'en') {
            return $text; // Return original Italian text
        }
        
        $translations = self::get_translations();
        return $translations[$text] ?? $text;
    }
}