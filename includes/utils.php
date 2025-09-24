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
 * Locate the booking page permalink used for confirmation links.
 *
 * Attempts to detect the first published page containing one of the booking
 * shortcodes. Falls back to the manually configured option when no page is
 * detected automatically.
 *
 * @param bool $force_refresh Optional. Whether to bypass the cached result.
 * @return string Permalink of the booking page or empty string when unavailable.
 */
function rbf_get_booking_confirmation_base_url($force_refresh = false) {
    static $cached_url = null;

    if (!$force_refresh && $cached_url !== null) {
        return $cached_url;
    }

    $cached_url = '';

    if (!function_exists('get_posts') || !function_exists('get_permalink')) {
        return $cached_url;
    }

    $shortcodes = rbf_get_booking_form_shortcodes();

    if (!empty($shortcodes)) {
        $query_args = [
            'post_type'              => 'page',
            'post_status'            => 'publish',
            'posts_per_page'         => 50,
            'orderby'                => 'menu_order',
            'order'                  => 'ASC',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'suppress_filters'       => false,
        ];

        $pages = get_posts($query_args);

        foreach ($pages as $page) {
            if (!rbf_post_has_booking_form($page)) {
                continue;
            }

            $permalink = get_permalink($page);

            if (is_string($permalink) && $permalink !== '') {
                $cached_url = $permalink;
                return $cached_url;
            }
        }
    }

    $options = rbf_get_settings();
    $configured_page_id = absint($options['booking_page_id'] ?? 0);

    if ($configured_page_id > 0) {
        $permalink = get_permalink($configured_page_id);

        if (is_string($permalink) && $permalink !== '') {
            $cached_url = $permalink;
            return $cached_url;
        }
    }

    return $cached_url;
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
        'google_ads_conversion_id' => '',
        'google_ads_conversion_label' => '',
        'meta_pixel_id' => '',
        'meta_access_token' => '',
        'notification_email' => get_option('admin_email'),
        'webmaster_email' => '',
        'privacy_policy_url' => '',
        'booking_page_id' => 0,
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
 *
 * @param array|null $settings Optional settings array to read from instead of loading options.
 * @return array
 */
function rbf_get_active_meals($settings = null) {
    if (is_array($settings)) {
        $options = wp_parse_args($settings, rbf_get_default_settings());
    } else {
        $options = wp_parse_args(get_option('rbf_settings', []), rbf_get_default_settings());
    }

    $custom_meals = $options['custom_meals'] ?? rbf_get_default_custom_meals();
    if (!is_array($custom_meals)) {
        $custom_meals = [];
    }

    // Filter only enabled meals
    return array_values(array_filter($custom_meals, function($meal) {
        return !empty($meal['enabled']);
    }));
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
 * Validate if a meal is available on a specific day.
 *
 * When a meal has no configured available days the function logs the situation
 * (when debugging is enabled) and treats the meal as unavailable to avoid
 * exposing times that cannot be booked safely.
 */
function rbf_is_meal_available_on_day($meal_id, $date) {
    $meal_config = rbf_get_meal_config($meal_id);
    if (!$meal_config) {
        return false;
    }

    $timezone = rbf_wp_timezone();
    $date_object = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $timezone);

    if (!$date_object) {
        return false;
    }

    $errors = DateTimeImmutable::getLastErrors();
    if ($errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
        return false;
    }

    $day_of_week = (int) $date_object->format('w');
    $day_mapping = [
        0 => 'sun', 1 => 'mon', 2 => 'tue', 3 => 'wed',
        4 => 'thu', 5 => 'fri', 6 => 'sat'
    ];

    $available_days = $meal_config['available_days'] ?? [];

    if (!is_array($available_days)) {
        $available_days = (array) $available_days;
    }

    $valid_days = array_values($day_mapping);
    $available_days = array_values(array_intersect($available_days, $valid_days));

    if (empty($available_days)) {
        rbf_log(sprintf(
            'RBF Plugin: Meal "%s" has no available days configured. Marking %s as unavailable.',
            $meal_id,
            $date
        ));
        return false;
    }

    $day_key = $day_mapping[$day_of_week];

    return in_array($day_key, $available_days, true);
}

/**
 * Get valid meal IDs for validation
 */
function rbf_get_valid_meal_ids() {
    $active_meals = rbf_get_active_meals();
    return array_column($active_meals, 'id');
}

/**
 * Normalize a comma-separated list of time slots into individual times.
 * Supports both single times (e.g. "19:00") and ranges (e.g. "19:00-21:00").
 *
 * @param string     $time_slots_csv       Raw time slot definition from settings.
 * @param int|float|null $slot_duration_minutes Optional slot duration in minutes used to expand ranges.
 * @return array List of normalized H:i time strings.
 */
function rbf_normalize_time_slots($time_slots_csv, $slot_duration_minutes = null) {
    if (!is_string($time_slots_csv) || $time_slots_csv === '') {
        return [];
    }

    $normalized = [];
    $seen = [];
    $entries = array_map('trim', explode(',', $time_slots_csv));
    $minute_in_seconds = defined('MINUTE_IN_SECONDS') ? MINUTE_IN_SECONDS : 60;
    $default_duration = 30;

    if (is_numeric($slot_duration_minutes)) {
        $duration_minutes = (float) $slot_duration_minutes;
    } else {
        $duration_minutes = null;
    }

    if ($duration_minutes === null || $duration_minutes <= 0) {
        $duration_minutes = $default_duration;
    }

    $increment_seconds = (int) round($duration_minutes * $minute_in_seconds);

    if ($increment_seconds <= 0) {
        $increment_seconds = $default_duration * $minute_in_seconds;
    }

    foreach ($entries as $entry) {
        if ($entry === '') {
            continue;
        }

        if (strpos($entry, '-') !== false) {
            list($start, $end) = array_map('trim', explode('-', $entry, 2));

            if ($start === '' || $end === '') {
                continue;
            }

            $start_timestamp = strtotime($start);
            $end_timestamp = strtotime($end);

            if ($start_timestamp === false || $end_timestamp === false || $end_timestamp < $start_timestamp) {
                continue;
            }

            for ($current = $start_timestamp; $current <= $end_timestamp; $current += $increment_seconds) {
                $time = date('H:i', $current);
                if (!isset($seen[$time])) {
                    $normalized[] = $time;
                    $seen[$time] = true;
                }
            }

            $end_time = date('H:i', $end_timestamp);
            if (!isset($seen[$end_time])) {
                $normalized[] = $end_time;
                $seen[$end_time] = true;
            }
        } else {
            $time = trim($entry);
            if ($time === '') {
                continue;
            }

            if (!isset($seen[$time])) {
                $normalized[] = $time;
                $seen[$time] = true;
            }
        }
    }

    return $normalized;
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
    $timezone = rbf_wp_timezone();
    $date_object = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $timezone);

    if (!$date_object) {
        return false;
    }

    $errors = DateTimeImmutable::getLastErrors();
    if ($errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
        return false;
    }

    $day_keys = ['sun','mon','tue','wed','thu','fri','sat'];
    $day_of_week = (int) $date_object->format('w');
    $day_key = $day_keys[$day_of_week] ?? null;

    if ($day_key === null) {
        return false;
    }

    $open_status = strtolower((string) ($options["open_{$day_key}"] ?? 'no'));
    // Accept the same truthy values used by the frontend toggles: yes, 1, true, on.
    $truthy_open_values = ['yes', '1', 'true', 'on'];

    if (!in_array($open_status, $truthy_open_values, true)) {
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

    if (empty($settings['privacy_policy_url']) && function_exists('get_privacy_policy_url')) {
        $privacy_policy_page = get_privacy_policy_url();
        if (!empty($privacy_policy_page)) {
            $settings['privacy_policy_url'] = $privacy_policy_page;
        }
    }

    return $settings;
}

/**
 * Retrieve the availability transient patterns used across the plugin.
 *
 * @return array
 */
function rbf_get_global_availability_transient_patterns() {
    return [
        '_transient_rbf_cal_avail_',
        '_transient_timeout_rbf_cal_avail_',
        '_transient_rbf_times_',
        '_transient_timeout_rbf_times_',
        '_transient_rbf_avail_',
        '_transient_timeout_rbf_avail_',
    ];
}

/**
 * Delete transients whose option names match the provided patterns.
 *
 * @param array $patterns List of option name prefixes to delete.
 * @return void
 */
function rbf_delete_transients_like(array $patterns) {
    global $wpdb;

    if (!isset($wpdb) || empty($wpdb->options)) {
        return;
    }

    foreach ($patterns as $pattern) {
        if (!is_string($pattern) || $pattern === '') {
            continue;
        }

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like($pattern) . '%'
            )
        );
    }
}

/**
 * Get the maximum number of people allowed for a booking.
 *
 * Returns the highest configured meal capacity when no explicit legacy override is present.
 * A value of PHP_INT_MAX is returned when every active meal is configured with unlimited capacity.
 *
 * @param array|null $settings Optional settings array to read the limit from.
 * @return int Normalized maximum number of people. PHP_INT_MAX indicates an unlimited capacity.
 */
function rbf_get_people_max_limit($settings = null) {
    if (!is_array($settings)) {
        $settings = rbf_get_settings();
    } else {
        $settings = wp_parse_args($settings, rbf_get_default_settings());
    }

    $legacy_max_people = absint($settings['max_people'] ?? 0);
    if ($legacy_max_people > 0) {
        return $legacy_max_people;
    }

    $active_meals = rbf_get_active_meals($settings);
    $max_capacity = 0;
    $has_unlimited_capacity = false;

    foreach ($active_meals as $meal) {
        if (!is_array($meal)) {
            continue;
        }

        $capacity = isset($meal['capacity']) ? (int) $meal['capacity'] : 0;

        if ($capacity <= 0) {
            $has_unlimited_capacity = true;
            continue;
        }

        if ($capacity > $max_capacity) {
            $max_capacity = $capacity;
        }
    }

    if ($max_capacity > 0) {
        return $max_capacity;
    }

    if ($has_unlimited_capacity) {
        return PHP_INT_MAX;
    }

    return 20;
}

/**
 * Translate strings to English
 */
function rbf_translate_string($text) {
    $locale = rbf_current_lang();
    if ($locale !== 'en') return $text;

    static $translations = [
        // Backend UI
        'Apri pagina di conferma' => 'Open confirmation page',
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
        'Pagina di Conferma Prenotazione' => 'Booking Confirmation Page',
        'Pagina del modulo di prenotazione' => 'Booking form page',
        'Seleziona una pagina' => 'Select a page',
        'Utilizzata per i link di conferma generati dal backend. Se vuota, il plugin tenta di individuarla automaticamente.' => 'Used for backend confirmation links. If left empty the plugin will try to detect it automatically.',

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
        'Italia' => 'Italy',
        'Regno Unito' => 'United Kingdom',
        'Stati Uniti' => 'United States',
        'Francia' => 'France',
        'Germania' => 'Germany',
        'Spagna' => 'Spain',
        'Svizzera' => 'Switzerland',
        'Austria' => 'Austria',
        'Paesi Bassi' => 'Netherlands',
        'Belgio' => 'Belgium',
        'Lussemburgo' => 'Luxembourg',
        'Portogallo' => 'Portugal',
        'Irlanda' => 'Ireland',
        'Danimarca' => 'Denmark',
        'Svezia' => 'Sweden',
        'Norvegia' => 'Norway',
        'Finlandia' => 'Finland',
        'Grecia' => 'Greece',
        'Monaco' => 'Monaco',
        'San Marino' => 'San Marino',
        'Città del Vaticano' => 'Vatican City',
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
        'ID Conversione Google Ads' => 'Google Ads Conversion ID',
        'Etichetta Conversione Google Ads' => 'Google Ads Conversion Label',
        'Google Ads' => 'Google Ads',
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
        'Parametri non validi: è consentito un massimo di %d persone.' => 'Invalid parameters: maximum allowed is %d guests.',
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
        'Orario non disponibile' => 'Time unavailable',
        'Formato orario non valido.' => 'Invalid time format.',
        'Spiacenti, non ci sono abbastanza posti. Rimasti: %d' => 'Sorry, there are not enough seats available. Remaining: %d',
        'Errore nel salvataggio.' => 'Error while saving.',
        'Caricamento...' => 'Loading...',
        'Scegli un orario...' => 'Choose a time...',
        'Nessun orario disponibile' => 'No time available',
        'Il numero di telefono inserito non è valido.' => 'The phone number entered is not valid.',
        'Di Domenica il servizio è Brunch con menù alla carta.' => 'On Sundays, we serve our à la carte Brunch menu.',
        'Acconsento al trattamento dei dati secondo l\'<a href="%s" target="_blank" rel="noopener">Informativa sulla Privacy</a>' => 'I consent to the processing of my data in accordance with the <a href="%s" target="_blank" rel="noopener">Privacy Policy</a>',
        'Acconsento al trattamento dei dati secondo l\'Informativa sulla Privacy' => 'I consent to the processing of my data according to the Privacy Policy',
        'Acconsento a ricevere comunicazioni promozionali via email e/o messaggi riguardanti eventi, offerte o novità.' => 'I agree to receive promotional emails and/or messages about events, offers, or news.',
        'Devi accettare la Privacy Policy per procedere.' => 'You must accept the Privacy Policy to proceed.',
        'Le prenotazioni devono essere effettuate con almeno %s di anticipo.' => 'Bookings must be made at least %s in advance.',
        'Le prenotazioni possono essere effettuate al massimo %s in anticipo.' => 'Bookings can be made at most %s in advance.',
        'Pranzo' => 'Lunch',
        'Aperitivo' => 'Aperitif',
        'Cena' => 'Dinner',
        'Brunch' => 'Brunch',
        'Il brunch è disponibile solo la domenica.' => 'Brunch is only available on Sundays.',
        'Prenotazione Ristorante - %s' => 'Reservation - %s',
        'Prenotazione presso %s\nNome: %s %s\nPersone: %d\nPasto: %s\nNote: %s' => 'Reservation at %s\nName: %s %s\nGuests: %d\nMeal: %s\nNotes: %s',

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
 * Normalize an international phone prefix ensuring it always starts with + and contains only digits.
 */
function rbf_normalize_phone_prefix($prefix) {
    if (!is_string($prefix)) {
        $prefix = '';
    }

    $prefix = trim($prefix);
    $prefix = preg_replace('/[^0-9\+]/', '', $prefix);

    if ($prefix === '') {
        return '';
    }

    $prefix = ltrim($prefix, '+');

    return '+' . $prefix;
}

/**
 * Sanitize the local part of a phone number keeping only digits.
 */
function rbf_sanitize_phone_number_part($number) {
    if (!is_string($number)) {
        $number = '';
    }

    $number = trim($number);
    $number = preg_replace('/[^0-9]/', '', $number);

    // Limit to a reasonable length to avoid abuse
    $number = substr($number, 0, 20);

    return $number;
}

/**
 * Format a sequence of digits with spaces every 3 characters for readability.
 */
function rbf_format_phone_digits($digits) {
    $digits = preg_replace('/[^0-9]/', '', (string) $digits);

    if ($digits === '') {
        return '';
    }

    return trim(chunk_split($digits, 3, ' '));
}

/**
 * Retrieve the curated list of phone prefixes supported by the booking form.
 */
function rbf_get_phone_prefixes() {
    $prefixes = [
        ['code' => 'it', 'prefix' => '+39',  'label' => rbf_translate_string('Italia'),          'example' => '347 123 4567', 'default' => true],
        ['code' => 'gb', 'prefix' => '+44',  'label' => rbf_translate_string('Regno Unito'),    'example' => '7700 900123'],
        ['code' => 'us', 'prefix' => '+1',   'label' => rbf_translate_string('Stati Uniti'),    'example' => '415 555 1234'],
        ['code' => 'fr', 'prefix' => '+33',  'label' => rbf_translate_string('Francia'),        'example' => '06 12 34 56 78'],
        ['code' => 'de', 'prefix' => '+49',  'label' => rbf_translate_string('Germania'),       'example' => '1512 3456789'],
        ['code' => 'es', 'prefix' => '+34',  'label' => rbf_translate_string('Spagna'),         'example' => '612 345 678'],
        ['code' => 'ch', 'prefix' => '+41',  'label' => rbf_translate_string('Svizzera'),       'example' => '079 123 45 67'],
        ['code' => 'at', 'prefix' => '+43',  'label' => rbf_translate_string('Austria'),        'example' => '0664 1234567'],
        ['code' => 'nl', 'prefix' => '+31',  'label' => rbf_translate_string('Paesi Bassi'),    'example' => '06 12345678'],
        ['code' => 'be', 'prefix' => '+32',  'label' => rbf_translate_string('Belgio'),         'example' => '0471 12 34 56'],
        ['code' => 'lu', 'prefix' => '+352', 'label' => rbf_translate_string('Lussemburgo'),    'example' => '621 123 456'],
        ['code' => 'pt', 'prefix' => '+351', 'label' => rbf_translate_string('Portogallo'),     'example' => '912 345 678'],
        ['code' => 'ie', 'prefix' => '+353', 'label' => rbf_translate_string('Irlanda'),        'example' => '085 123 4567'],
        ['code' => 'dk', 'prefix' => '+45',  'label' => rbf_translate_string('Danimarca'),      'example' => '20 12 34 56'],
        ['code' => 'se', 'prefix' => '+46',  'label' => rbf_translate_string('Svezia'),         'example' => '070 123 45 67'],
        ['code' => 'no', 'prefix' => '+47',  'label' => rbf_translate_string('Norvegia'),       'example' => '412 34 567'],
        ['code' => 'fi', 'prefix' => '+358', 'label' => rbf_translate_string('Finlandia'),      'example' => '040 1234567'],
        ['code' => 'gr', 'prefix' => '+30',  'label' => rbf_translate_string('Grecia'),         'example' => '691 234 5678'],
        ['code' => 'mc', 'prefix' => '+377', 'label' => rbf_translate_string('Monaco'),         'example' => '06 12 34 56'],
        ['code' => 'sm', 'prefix' => '+378', 'label' => rbf_translate_string('San Marino'),     'example' => '66 12 34 56'],
        ['code' => 'va', 'prefix' => '+379', 'label' => rbf_translate_string('Città del Vaticano'), 'example' => '06 6982 1234'],
    ];

    foreach ($prefixes as &$prefix) {
        $prefix['prefix'] = rbf_normalize_phone_prefix($prefix['prefix']);
    }
    unset($prefix);

    return apply_filters('rbf_phone_prefixes', $prefixes);
}

/**
 * Get the configured default phone prefix entry.
 */
function rbf_get_default_phone_prefix() {
    $prefixes = rbf_get_phone_prefixes();

    foreach ($prefixes as $prefix) {
        if (!empty($prefix['default'])) {
            return $prefix;
        }
    }

    return $prefixes[0] ?? ['code' => 'it', 'prefix' => '+39', 'label' => rbf_translate_string('Italia'), 'default' => true];
}

/**
 * Validate and normalize the provided prefix returning the known configuration entry.
 */
function rbf_validate_phone_prefix_value($prefix_value) {
    $normalized = rbf_normalize_phone_prefix($prefix_value);
    $prefixes = rbf_get_phone_prefixes();

    foreach ($prefixes as $prefix) {
        if ($normalized !== '' && $normalized === $prefix['prefix']) {
            return $prefix;
        }
    }

    return rbf_get_default_phone_prefix();
}

/**
 * Prepare phone data (prefix, number and formatted representation).
 */
function rbf_prepare_phone_number($prefix_value, $number_value) {
    $prefix = rbf_validate_phone_prefix_value($prefix_value);
    $number_digits = rbf_sanitize_phone_number_part($number_value);

    $formatted_digits = rbf_format_phone_digits($number_digits);
    $full_number = '';

    if ($formatted_digits !== '') {
        $full_number = trim($prefix['prefix'] . ' ' . $formatted_digits);
    }

    return [
        'full' => $full_number,
        'prefix' => $prefix['prefix'],
        'number' => $number_digits,
        'country_code' => $prefix['code'],
        'label' => $prefix['label'],
    ];
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
 * Hash a booking tracking token before persisting it to the database.
 *
 * @param string $token Raw tracking token generated for the booking.
 * @return string Sanitized hash or empty string when token is invalid.
 */
function rbf_hash_tracking_token($token) {
    if (!is_string($token)) {
        return '';
    }

    $token = trim($token);
    if ($token === '') {
        return '';
    }

    return hash('sha256', $token);
}

/**
 * Persist a booking tracking token hash.
 *
 * @param int    $booking_id    Booking post ID.
 * @param string $tracking_token Raw tracking token to hash and store.
 * @return void
 */
function rbf_store_booking_tracking_token($booking_id, $tracking_token) {
    $booking_id = absint($booking_id);
    if (!$booking_id) {
        return;
    }

    $hash = rbf_hash_tracking_token($tracking_token);
    if ($hash === '') {
        delete_post_meta($booking_id, 'rbf_tracking_token');
        return;
    }

    update_post_meta($booking_id, 'rbf_tracking_token', $hash);
}

/**
 * Remove the stored tracking token hash for a booking.
 *
 * @param int $booking_id Booking post ID.
 * @return void
 */
function rbf_clear_booking_tracking_token($booking_id) {
    $booking_id = absint($booking_id);
    if (!$booking_id) {
        return;
    }

    delete_post_meta($booking_id, 'rbf_tracking_token');
}

/**
 * Build a shareable success URL for a manually created booking.
 *
 * @param int         $booking_id     Booking post ID.
 * @param string      $tracking_token Raw tracking token.
 * @param string|null $base_url       Optional base URL for the success page.
 * @return string Success URL or empty string when data is insufficient.
 */
function rbf_get_manual_booking_success_url($booking_id, $tracking_token, $base_url = null) {
    $booking_id = absint($booking_id);
    $tracking_token = is_string($tracking_token) ? $tracking_token : '';

    if (!$booking_id || $tracking_token === '') {
        return '';
    }

    $default_base_url = '';

    if (!is_string($base_url) || $base_url === '') {
        $default_base_url = rbf_get_booking_confirmation_base_url();
        $base_url = $default_base_url;
    } else {
        $default_base_url = $base_url;
    }

    /**
     * Allow customization of the manual booking success base URL.
     *
     * @param string $base_url   Default base URL.
     * @param int    $booking_id Booking post ID.
     */
    $base_url = apply_filters('rbf_manual_booking_success_base_url', $base_url, $booking_id);

    if (!is_string($base_url)) {
        $base_url = '';
    }

    if ($base_url === '') {
        if ($default_base_url !== '') {
            $base_url = $default_base_url;
        } elseif (function_exists('home_url')) {
            $base_url = home_url('/');
        } else {
            return '';
        }
    }

    $success_args = [
        'rbf_success'   => '1',
        'booking_id'    => $booking_id,
        'booking_token' => $tracking_token,
    ];

    return add_query_arg($success_args, $base_url);
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
    
    // Generate unique UID with sanitized host fallback handling
    $raw_host = isset($_SERVER['HTTP_HOST']) ? wp_unslash($_SERVER['HTTP_HOST']) : '';
    $host = sanitize_text_field($raw_host);
    $host = preg_replace('/[^A-Za-z0-9\.-]/', '', $host);

    if ($host === '') {
        $fallback_host = wp_parse_url(home_url(), PHP_URL_HOST);
        if (!empty($fallback_host)) {
            $fallback_host = sanitize_text_field($fallback_host);
            $host = preg_replace('/[^A-Za-z0-9\.-]/', '', $fallback_host);
        }
    }

    if ($host === '') {
        $fallback_name = sanitize_text_field(get_bloginfo('name'));
        $fallback_name = preg_replace('/[^A-Za-z0-9\.-]/', '', strtolower($fallback_name));
        $fallback_name = substr($fallback_name, 0, 64);
        if ($fallback_name === '') {
            $fallback_name = 'rbf-booking';
        }

        $host = $fallback_name;
    }

    $uid = uniqid('rbf_booking_', true) . '@' . $host;

    // Format datetime for ICS using the site's configured timezone
    $raw_date = isset($booking_data['date']) ? (string) $booking_data['date'] : '';
    $raw_time = isset($booking_data['time']) ? (string) $booking_data['time'] : '';

    if ($raw_date === '' || $raw_time === '') {
        return false;
    }

    $timezone = rbf_wp_timezone();
    if (!($timezone instanceof DateTimeZone)) {
        $timezone = new DateTimeZone('UTC');
    }

    $booking_datetime = DateTime::createFromFormat('Y-m-d H:i', $raw_date . ' ' . $raw_time, $timezone);
    if (!$booking_datetime) {
        return false;
    }

    $errors = DateTime::getLastErrors();
    if ($errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
        return false;
    }

    $start_datetime = clone $booking_datetime;

    // Determine the event duration from the meal configuration when available
    $duration_minutes = null;

    if (isset($booking_data['slot_duration_minutes']) && is_numeric($booking_data['slot_duration_minutes'])) {
        $duration_minutes = (int) $booking_data['slot_duration_minutes'];
    }

    if ($duration_minutes === null || $duration_minutes <= 0) {
        $meal_candidates = [];

        foreach (['meal_id', 'meal', 'slot'] as $meal_key) {
            if (!empty($booking_data[$meal_key])) {
                $meal_candidates[] = $booking_data[$meal_key];
            }
        }

        $seen_meals = [];
        foreach ($meal_candidates as $candidate) {
            $meal_id = is_string($candidate) ? trim($candidate) : '';

            if ($meal_id === '' || isset($seen_meals[$meal_id])) {
                continue;
            }

            $seen_meals[$meal_id] = true;

            if (isset($booking_data['people']) && is_numeric($booking_data['people']) && function_exists('rbf_calculate_slot_duration')) {
                $maybe_duration = (int) rbf_calculate_slot_duration($meal_id, (int) $booking_data['people']);
                if ($maybe_duration > 0) {
                    $duration_minutes = $maybe_duration;
                    break;
                }
            }

            if (function_exists('rbf_get_meal_config')) {
                $meal_config = rbf_get_meal_config($meal_id);
                if (is_array($meal_config) && isset($meal_config['slot_duration_minutes'])) {
                    $maybe_duration = (int) $meal_config['slot_duration_minutes'];
                    if ($maybe_duration > 0) {
                        $duration_minutes = $maybe_duration;
                        break;
                    }
                }
            }
        }
    }

    if (!is_int($duration_minutes) || $duration_minutes <= 0) {
        $duration_minutes = 90; // Sensible default duration when configuration is unavailable
    }

    $end_datetime = clone $booking_datetime;
    $end_datetime->add(new DateInterval('PT' . $duration_minutes . 'M'));

    $utc_timezone = new DateTimeZone('UTC');
    $start_datetime->setTimezone($utc_timezone);
    $end_datetime->setTimezone($utc_timezone);

    $start_time = $start_datetime->format('Ymd\THis\Z');
    $end_time = $end_datetime->format('Ymd\THis\Z');
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

    $active_statuses = apply_filters('rbf_booking_active_statuses', ['confirmed', 'pending', 'completed']);
    $requires_reactivation = ($old_status === 'cancelled' && in_array($new_status, $active_statuses, true));
    $reservation_context = null;

    if ($requires_reactivation) {
        $date = get_post_meta($booking_id, 'rbf_data', true);
        $meal = get_post_meta($booking_id, 'rbf_meal', true);
        if ($meal === '' || $meal === null) {
            $meal = get_post_meta($booking_id, 'rbf_orario', true);
        }

        $time = get_post_meta($booking_id, 'rbf_time', true);
        if ($time === '' || $time === null) {
            $time = get_post_meta($booking_id, 'rbf_orario', true);
        }

        $people = intval(get_post_meta($booking_id, 'rbf_persone', true));

        if (empty($date) || empty($meal) || empty($time) || $people <= 0) {
            if (function_exists('rbf_log')) {
                rbf_log('RBF Update Booking Status: missing data to re-activate booking ' . $booking_id . '.');
            }
            return false;
        }

        $reservation_success = rbf_reserve_slot_capacity($date, $meal, $people);
        if (!$reservation_success) {
            if (function_exists('rbf_log')) {
                rbf_log(sprintf(
                    'RBF Update Booking Status: failed to reserve capacity for booking %d on %s (%s).',
                    $booking_id,
                    $date,
                    $meal
                ));
            }
            return false;
        }

        $assignment = rbf_assign_tables_first_fit($people, $date, $time, $meal);
        if (!$assignment) {
            if (function_exists('rbf_release_slot_capacity')) {
                rbf_release_slot_capacity($date, $meal, $people);
            }

            if (function_exists('rbf_log')) {
                rbf_log(sprintf(
                    'RBF Update Booking Status: no tables available to re-activate booking %d on %s at %s (%s).',
                    $booking_id,
                    $date,
                    $time,
                    $meal
                ));
            }

            return false;
        }

        $reservation_context = [
            'date' => $date,
            'meal' => $meal,
            'people' => $people,
            'time' => $time,
            'assignment' => $assignment,
        ];
    }

    if ($old_status_raw !== $new_status) {
        $updated = update_post_meta($booking_id, 'rbf_booking_status', $new_status);

        if ($updated === false) {
            if ($reservation_context && function_exists('rbf_release_slot_capacity')) {
                rbf_release_slot_capacity(
                    $reservation_context['date'],
                    $reservation_context['meal'],
                    $reservation_context['people']
                );
            }
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

    if ($reservation_context && isset($reservation_context['assignment'])) {
        rbf_save_table_assignment($booking_id, $reservation_context['assignment']);

        update_post_meta($booking_id, 'rbf_table_assignment_type', $reservation_context['assignment']['type']);
        update_post_meta($booking_id, 'rbf_assigned_tables', $reservation_context['assignment']['total_capacity']);

        if ($reservation_context['assignment']['type'] === 'joined' && isset($reservation_context['assignment']['group_id'])) {
            update_post_meta($booking_id, 'rbf_table_group_id', $reservation_context['assignment']['group_id']);
        } else {
            delete_post_meta($booking_id, 'rbf_table_group_id');
        }

        delete_transient('rbf_avail_' . $reservation_context['date'] . '_' . $reservation_context['meal']);

        if (function_exists('rbf_clear_calendar_cache')) {
            rbf_clear_calendar_cache($reservation_context['date'], $reservation_context['meal']);
        }
    }

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

    if ($base_duration <= 0) {
        $base_duration = 90;
    }

    if ($people_count > 6) {
        $large_party_duration = null;

        if (isset($meal_config['large_party_duration_minutes'])) {
            $large_party_duration = intval($meal_config['large_party_duration_minutes']);
        } elseif (isset($meal_config['group_slot_duration_minutes'])) {
            // Backward compatibility for legacy configuration naming used in some test fixtures.
            $large_party_duration = intval($meal_config['group_slot_duration_minutes']);
        }

        if ($large_party_duration !== null && $large_party_duration > 0) {
            return $large_party_duration;
        }
    }

    return $base_duration;
}

/**
 * Check if a time slot conflicts with buffer requirements
 * 
 * @param string   $date               Date in Y-m-d format
 * @param string   $time               Time in H:i format
 * @param string   $meal_id            Meal ID
 * @param int      $people_count       Number of people
 * @param int|null $ignore_booking_id  Optional booking ID to ignore during validation
 * @return array|true Returns array with error info if conflict, true if valid
 */
function rbf_validate_buffer_time($date, $time, $meal_id, $people_count, $ignore_booking_id = null) {
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
    
    $status_table = $wpdb->prefix . 'rbf_booking_status';

    static $status_source = null;
    if ($status_source === null) {
        $found_table = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $status_table));

        if ($found_table === $status_table) {
            $status_source = [
                'join' => "LEFT JOIN $status_table bs ON p.ID = bs.booking_id",
                'column' => 'bs.status'
            ];
        } else {
            $status_source = [
                'join' => "LEFT JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = 'rbf_booking_status'",
                'column' => 'pm_status.meta_value'
            ];
        }
    }

    $status_join = $status_source['join'];
    $status_column = $status_source['column'];

    // Get existing bookings for the same date and meal
    $query = "
        SELECT COALESCE(pm_time.meta_value, pm_time_legacy.meta_value) AS booking_time,
               pm_people.meta_value AS people
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'rbf_data'
        LEFT JOIN {$wpdb->postmeta} pm_meal ON p.ID = pm_meal.post_id AND pm_meal.meta_key = 'rbf_meal'
        LEFT JOIN {$wpdb->postmeta} pm_meal_legacy ON p.ID = pm_meal_legacy.post_id AND pm_meal_legacy.meta_key = 'rbf_orario'
        LEFT JOIN {$wpdb->postmeta} pm_time ON p.ID = pm_time.post_id AND pm_time.meta_key = 'rbf_time'
        LEFT JOIN {$wpdb->postmeta} pm_time_legacy ON p.ID = pm_time_legacy.post_id AND pm_time_legacy.meta_key = 'rbf_orario'
        INNER JOIN {$wpdb->postmeta} pm_people ON p.ID = pm_people.post_id AND pm_people.meta_key = 'rbf_persone'
        {$status_join}
        WHERE p.post_type = 'rbf_booking' AND p.post_status = 'publish'
          AND pm_date.meta_value = %s
          AND COALESCE(pm_meal.meta_value, pm_meal_legacy.meta_value) = %s
          AND COALESCE({$status_column}, 'confirmed') <> 'cancelled'
    ";

    $prepare_args = [$date, $meal_id];

    if ($ignore_booking_id !== null) {
        $query .= "\n          AND p.ID <> %d";
        $prepare_args[] = intval($ignore_booking_id);
    }

    $prepared_query = call_user_func_array([
        $wpdb,
        'prepare'
    ], array_merge([$query], $prepare_args));

    $existing_bookings = $wpdb->get_results($prepared_query);

    foreach ($existing_bookings as $existing) {
        $existing_datetime = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $existing->booking_time, $tz);
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
 * Check if a specific time slot satisfies buffer requirements.
 *
 * @param string   $date              Date in Y-m-d format.
 * @param string   $time              Time in H:i format.
 * @param string   $meal_id           Meal identifier.
 * @param int      $people_count      Number of guests for the request.
 * @param int|null $ignore_booking_id Optional booking ID to ignore.
 * @return bool True when the slot is buffer-compliant.
 */
function rbf_is_buffer_time_valid($date, $time, $meal_id, $people_count, $ignore_booking_id = null) {
    return rbf_validate_buffer_time($date, $time, $meal_id, $people_count, $ignore_booking_id) === true;
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
 * Sum the number of people for active (non-cancelled) bookings on a date/meal.
 *
 * @param string $date Date in Y-m-d format
 * @param string $meal_id Meal identifier
 * @return int Total guests counted for capacity calculations
 */
function rbf_sum_active_bookings($date, $meal_id) {
    global $wpdb;

    $query = "
        SELECT COALESCE(SUM(pm_people.meta_value), 0)
         FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm_people ON p.ID = pm_people.post_id AND pm_people.meta_key = 'rbf_persone'
         INNER JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'rbf_data'
         LEFT JOIN {$wpdb->postmeta} pm_meal ON p.ID = pm_meal.post_id AND pm_meal.meta_key = 'rbf_meal'
         LEFT JOIN {$wpdb->postmeta} pm_meal_legacy ON p.ID = pm_meal_legacy.post_id AND pm_meal_legacy.meta_key = 'rbf_orario'
         LEFT JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = 'rbf_booking_status'
         WHERE p.post_type = 'rbf_booking' AND p.post_status = 'publish'
         AND pm_date.meta_value = %s AND COALESCE(pm_meal.meta_value, pm_meal_legacy.meta_value) = %s
         AND COALESCE(pm_status.meta_value, 'confirmed') <> 'cancelled'
    ";

    $total_people = $wpdb->get_var($wpdb->prepare(
        $query,
        $date,
        $meal_id
    ));

    return (int) $total_people;
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
    
    $spots_taken = rbf_sum_active_bookings($date, $meal_id);
    
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
    $has_finite_capacity = $total_capacity > 0;
    
    // Define thresholds
    $level = 'available';  // green
    if ($occupancy >= 100) {
        $level = 'full';     // red
    } elseif ($occupancy >= 70) {
        $level = 'limited';  // yellow
    }
    
    $remaining_for_output = $has_finite_capacity ? (int) $remaining : null;
    $total_for_output = $has_finite_capacity ? (int) $total_capacity : null;

    return [
        'level' => $level,
        'occupancy' => round($occupancy, 1),
        'remaining' => $remaining_for_output,
        'total' => $total_for_output
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
 * @param int|null $booking_id Optional booking ID to exclude from the availability calculation.
 * @return bool True if slot is available, false otherwise
 */
function rbf_check_slot_availability($date, $meal, $time, $people, $booking_id = null) {
    // Basic input validation
    if (empty($date) || empty($meal) || empty($time) || $people <= 0) {
        return false;
    }

    // Check if date is in the past using WordPress timezone awareness
    $tz = rbf_wp_timezone();

    try {
        $requested_day = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $tz);
    } catch (Exception $e) {
        $requested_day = false;
    }

    if (!$requested_day) {
        return false;
    }

    $today = (new DateTimeImmutable('now', $tz))->setTime(0, 0, 0);

    if ($requested_day < $today) {
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
    $slot_duration_minutes = rbf_calculate_slot_duration($meal, $people);
    $time_slots = rbf_normalize_time_slots($meal_config['time_slots'] ?? '', $slot_duration_minutes);
    if (empty($time_slots) || !in_array($time, $time_slots, true)) {
        return false;
    }

    $buffer_validation = rbf_validate_buffer_time($date, $time, $meal, $people, $booking_id);
    if ($buffer_validation !== true) {
        return false;
    }

    // Get current capacity usage
    $current_bookings = rbf_calculate_current_bookings($date, $meal);

    // Optionally subtract the booking being moved if it belongs to this slot
    if (!empty($booking_id) && function_exists('get_post') && function_exists('get_post_meta')) {
        $booking = get_post($booking_id);
        if ($booking && $booking->post_type === 'rbf_booking') {
            $booking_date = get_post_meta($booking_id, 'rbf_data', true);
            $booking_meal = get_post_meta($booking_id, 'rbf_meal', true);
            if ($booking_meal === '') {
                $booking_meal = get_post_meta($booking_id, 'rbf_orario', true);
            }

            if ($booking_date === $date && $booking_meal === $meal) {
                $booking_people = intval(get_post_meta($booking_id, 'rbf_persone', true));
                if ($booking_people > 0) {
                    $current_bookings = max(0, $current_bookings - $booking_people);
                }
            }
        }
    }

    $meal_capacity = intval($meal_config['capacity']);

    // Calculate overbooking allowance
    $overbooking_limit = intval($meal_config['overbooking_limit'] ?? 0);
    $overbooking_spots = round($meal_capacity * ($overbooking_limit / 100));
    $effective_capacity = (int) ($meal_capacity + $overbooking_spots);

    if ($effective_capacity <= 0) {
        // Unlimited or undefined capacity should always be considered available
        return true;
    }

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