<?php
/**
 * AI-powered alternative booking suggestions for FP Prenotazioni Ristorante
 * 
 * @package FP_Prenotazioni_Ristorante_PRO
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get alternative booking suggestions when requested slot is full
 * 
 * @param string $date Original requested date (Y-m-d format)
 * @param string $meal Original requested meal type 
 * @param int $people Number of people
 * @param string $requested_time Original requested time (optional for context)
 * @return array Array of alternative suggestions with dates, meals, and times
 */
function rbf_get_alternative_suggestions($date, $meal, $people, $requested_time = '') {
    $suggestions = [];
    $max_suggestions = 2; // Limit to avoid overwhelming users
    
    // Get meal configurations for intelligent suggestions
    $meals = rbf_get_active_meals();
    $options = rbf_get_settings();
    
    // Strategy 1: Same day, different meal times (if restaurant offers multiple services)
    $same_day_suggestions = rbf_suggest_same_day_alternatives($date, $meal, $people, $meals);
    $suggestions = array_merge($suggestions, array_slice($same_day_suggestions, 0, 2));
    
    // Strategy 2: Same meal, nearby dates (±1-3 days)
    $nearby_date_suggestions = rbf_suggest_nearby_dates($date, $meal, $people, 3);
    $suggestions = array_merge($suggestions, array_slice($nearby_date_suggestions, 0, 2));
    
    // Strategy 3: Same day of week in following weeks (up to 2 weeks ahead)
    $same_weekday_suggestions = rbf_suggest_same_weekday($date, $meal, $people, 2);
    $suggestions = array_merge($suggestions, array_slice($same_weekday_suggestions, 0, 2));
    
    // Remove duplicates and limit total suggestions
    $suggestions = rbf_deduplicate_suggestions($suggestions);
    $suggestions = array_slice($suggestions, 0, $max_suggestions);
    
    // Sort by preference score (date proximity + meal similarity)
    usort($suggestions, 'rbf_compare_suggestion_preference');
    
    return $suggestions;
}

/**
 * Suggest alternative meal times on the same date
 * 
 * @param string $date Date in Y-m-d format
 * @param string $original_meal Original meal type
 * @param int $people Number of people
 * @param array $meals Available meal configurations
 * @return array Alternative suggestions for same day
 */
function rbf_suggest_same_day_alternatives($date, $original_meal, $people, $meals) {
    $suggestions = [];
    
    foreach ($meals as $meal) {
        // Skip the originally requested meal
        if ($meal['id'] === $original_meal) {
            continue;
        }
        
        // Check if this meal is available on the requested date
        if (!rbf_is_meal_available_on_day($meal['id'], $date)) {
            continue;
        }
        
        // Check if meal has availability for the party size
        $availability = rbf_get_availability_status($date, $meal['id']);
        $remaining_spots = $availability['remaining'];
        if ($availability['level'] === 'full' || ($remaining_spots !== null && $remaining_spots < $people)) {
            continue;
        }
        
        // Get available time slots for this meal
        $available_times = rbf_get_available_time_slots($date, $meal['id'], $people);
        
        if (!empty($available_times)) {
            // Take the first available time slot
            $time_slot = reset($available_times);
            
            $suggestions[] = [
                'date' => $date,
                'date_display' => rbf_format_date_display($date),
                'meal' => $meal['id'],
                'meal_name' => rbf_translate_string($meal['name']),
                'time' => $time_slot['time'],
                'time_display' => $time_slot['display'],
                'reason' => rbf_translate_string('Stesso giorno, servizio diverso'),
                'preference_score' => 90, // High score for same day
                'remaining_spots' => $remaining_spots
            ];
        }
    }
    
    return $suggestions;
}

/**
 * Suggest same meal on nearby dates
 * 
 * @param string $original_date Original date in Y-m-d format
 * @param string $meal Meal type
 * @param int $people Number of people
 * @param int $days_range Number of days to check before/after
 * @return array Alternative date suggestions
 */
function rbf_suggest_nearby_dates($original_date, $meal, $people, $days_range = 3) {
    $suggestions = [];
    $tz = rbf_wp_timezone();
    $original_date_obj = DateTimeImmutable::createFromFormat('!Y-m-d', $original_date, $tz);

    if (!$original_date_obj) {
        return $suggestions;
    }

    $today = (new DateTimeImmutable('now', $tz))->setTime(0, 0);

    // Check dates after the original date (prioritize future dates)
    for ($i = 1; $i <= $days_range; $i++) {
        $check_date_obj = $original_date_obj->add(new DateInterval("P{$i}D"));
        $check_date = $check_date_obj->format('Y-m-d');

        $suggestion = rbf_check_date_availability(
            $check_date,
            $meal,
            $people,
            $original_date,
            $check_date_obj,
            $original_date_obj
        );

        if ($suggestion) {
            $suggestion['preference_score'] = 80 - ($i * 5); // Decrease score with distance
            $suggestions[] = $suggestion;
        }
    }

    // Check dates before the original date (only if we need more suggestions)
    if (count($suggestions) < 2) {
        for ($i = 1; $i <= $days_range; $i++) {
            $check_date_obj = $original_date_obj->sub(new DateInterval("P{$i}D"));

            // Don't suggest dates in the past
            if ($check_date_obj < $today) {
                continue;
            }

            $check_date = $check_date_obj->format('Y-m-d');

            $suggestion = rbf_check_date_availability(
                $check_date,
                $meal,
                $people,
                $original_date,
                $check_date_obj,
                $original_date_obj
            );

            if ($suggestion) {
                $suggestion['preference_score'] = 70 - ($i * 5); // Lower score for earlier dates
                $suggestions[] = $suggestion;
            }
        }
    }

    return $suggestions;
}

/**
 * Suggest same day of week in following weeks
 * 
 * @param string $original_date Original date in Y-m-d format  
 * @param string $meal Meal type
 * @param int $people Number of people
 * @param int $weeks_ahead Number of weeks to check ahead
 * @return array Same weekday suggestions
 */
function rbf_suggest_same_weekday($original_date, $meal, $people, $weeks_ahead = 2) {
    $suggestions = [];
    $tz = rbf_wp_timezone();
    $original_date_obj = DateTimeImmutable::createFromFormat('!Y-m-d', $original_date, $tz);

    if (!$original_date_obj) {
        return $suggestions;
    }

    for ($week = 1; $week <= $weeks_ahead; $week++) {
        $check_date_obj = $original_date_obj->add(new DateInterval("P{$week}W"));
        $check_date = $check_date_obj->format('Y-m-d');

        $suggestion = rbf_check_date_availability(
            $check_date,
            $meal,
            $people,
            $original_date,
            $check_date_obj,
            $original_date_obj
        );

        if ($suggestion) {
            $suggestion['reason'] = sprintf(
                rbf_translate_string('Stesso giorno della settimana, %d settimana dopo'),
                $week
            );
            $suggestion['preference_score'] = 60 - ($week * 10); // Decrease score with week distance
            $suggestions[] = $suggestion;
        }
    }

    return $suggestions;
}

/**
 * Check if a specific date has availability and return suggestion
 * 
 * @param string $date Date to check in Y-m-d format
 * @param string $meal Meal type
 * @param int $people Number of people  
 * @param string $original_date Original requested date for context
 * @return array|null Suggestion array or null if not available
 */
function rbf_check_date_availability($date, $meal, $people, $original_date, ?DateTimeImmutable $date_obj = null, ?DateTimeImmutable $original_date_obj = null) {
    $tz = rbf_wp_timezone();
    $date_obj = $date_obj ?? DateTimeImmutable::createFromFormat('!Y-m-d', $date, $tz);
    $original_date_obj = $original_date_obj ?? DateTimeImmutable::createFromFormat('!Y-m-d', $original_date, $tz);

    if (!$date_obj || !$original_date_obj) {
        return null;
    }

    // Check if restaurant is open on this date
    if (!rbf_is_restaurant_open_on_date($date)) {
        return null;
    }
    
    // Check if meal is available on this day of week
    if (!rbf_is_meal_available_on_day($meal, $date)) {
        return null;
    }
    
    // Check capacity
    $availability = rbf_get_availability_status($date, $meal);
    $remaining_spots = $availability['remaining'];
    if ($availability['level'] === 'full' || ($remaining_spots !== null && $remaining_spots < $people)) {
        return null;
    }
    
    // Get available time slots
    $available_times = rbf_get_available_time_slots($date, $meal, $people);
    if (empty($available_times)) {
        return null;
    }
    
    // Take the first available time slot  
    $time_slot = reset($available_times);
    $meal_config = rbf_get_meal_config($meal);
    
    // Calculate days difference for reason text
    $diff_interval = $original_date_obj->diff($date_obj);
    $days_diff = (int) $diff_interval->format('%r%a');
    $reason = '';

    if ($days_diff == 1) {
        $reason = rbf_translate_string('Il giorno successivo');
    } elseif ($days_diff == -1) {
        $reason = rbf_translate_string('Il giorno precedente');
    } elseif ($days_diff > 1) {
        $reason = sprintf(rbf_translate_string('%d giorni dopo'), $days_diff);
    } elseif ($days_diff < -1) {
        $reason = sprintf(rbf_translate_string('%d giorni prima'), abs($days_diff));
    }
    
    return [
        'date' => $date,
        'date_display' => rbf_format_date_display($date),
        'meal' => $meal,
        'meal_name' => rbf_translate_string($meal_config['name'] ?? $meal),
        'time' => $time_slot['time'],
        'time_display' => $time_slot['display'],
        'reason' => $reason,
        'remaining_spots' => $remaining_spots
    ];
}

/**
 * Get available time slots for a date and meal
 * 
 * @param string $date Date in Y-m-d format
 * @param string $meal Meal type
 * @param int $people Number of people
 * @return array Available time slots
 */
function rbf_get_available_time_slots($date, $meal, $people) {
    $available_times = [];
    
    // Get meal configuration
    $meal_config = rbf_get_meal_config($meal);
    if (!$meal_config) {
        return $available_times;
    }
    
    // Normalize time slots to individual times to ensure consistent validation
    $times_csv = $meal_config['time_slots'] ?? '';
    if (empty($times_csv)) {
        return $available_times;
    }

    $slot_duration_minutes = rbf_calculate_slot_duration($meal, $people);
    $normalized_slots = rbf_normalize_time_slots($times_csv, $slot_duration_minutes);

    foreach ($normalized_slots as $time) {
        if (rbf_check_time_slot_capacity($date, $meal, $time, $people)) {
            $available_times[] = [
                'time' => $time,
                'display' => $time
            ];
        }
    }

    return $available_times;
}

/**
 * Check if a specific time slot has capacity for the party size
 * 
 * @param string $date Date in Y-m-d format
 * @param string $meal Meal type
 * @param string $time Time in H:i format
 * @param int $people Number of people
 * @return bool True if capacity available
 */
function rbf_check_time_slot_capacity($date, $meal, $time, $people) {
    // Get effective capacity for this meal
    $total_capacity = rbf_get_effective_capacity($meal);
    if ($total_capacity <= 0) {
        return true; // Unlimited capacity
    }
    
    // For capacity checking, we need to consider all bookings for this meal on this date
    // since the current system doesn't track individual time slots separately
    $current_bookings = rbf_sum_active_bookings($date, $meal);
    $remaining_capacity = $total_capacity - $current_bookings;
    
    return $remaining_capacity >= $people;
}


/**
 * Check if restaurant is open on a specific date
 * 
 * @param string $date Date in Y-m-d format
 * @return bool True if restaurant is open
 */
function rbf_is_restaurant_open_on_date($date) {
    $options = rbf_get_settings();

    // Check day of week using WordPress timezone
    $tz = rbf_wp_timezone();
    $date_obj = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $tz);

    if (!$date_obj) {
        return false;
    }

    $day_of_week = (int) $date_obj->format('w');
    $day_keys = ['sun','mon','tue','wed','thu','fri','sat'];
    $day_key = $day_keys[$day_of_week];

    if (($options["open_{$day_key}"] ?? 'no') !== 'yes') {
        return false;
    }
    
    // Check specific closed dates
    $closed_specific = rbf_get_closed_specific($options);
    
    // Single closed dates
    if (in_array($date, $closed_specific['singles'], true)) {
        return false;
    }
    
    // Date ranges
    foreach ($closed_specific['ranges'] as $range) {
        if ($date >= $range['from'] && $date <= $range['to']) {
            return false;
        }
    }
    
    return true;
}

/**
 * Remove duplicate suggestions
 * 
 * @param array $suggestions Array of suggestions
 * @return array Deduplicated suggestions
 */
function rbf_deduplicate_suggestions($suggestions) {
    $unique = [];
    $seen = [];
    
    foreach ($suggestions as $suggestion) {
        $key = $suggestion['date'] . '|' . $suggestion['meal'] . '|' . $suggestion['time'];
        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $unique[] = $suggestion;
        }
    }
    
    return $unique;
}

/**
 * Compare suggestions for sorting by preference
 * 
 * @param array $a First suggestion
 * @param array $b Second suggestion  
 * @return int Comparison result
 */
function rbf_compare_suggestion_preference($a, $b) {
    // Higher preference score comes first
    return $b['preference_score'] - $a['preference_score'];
}

/**
 * Format date for display
 * 
 * @param string $date Date in Y-m-d format
 * @return string Formatted date
 */
function rbf_format_date_display($date) {
    $tz = rbf_wp_timezone();
    $date_obj = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $tz);

    if (!$date_obj) {
        return $date;
    }

    $locale = rbf_current_lang();

    if ($locale === 'it') {
        // Italian format: "Lunedì 15 Gennaio"
        $day_names = ['Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato'];
        $month_names = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno',
                       'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];

        $day_name = $day_names[(int) $date_obj->format('w')];
        $day = (int) $date_obj->format('j');
        $month_name = $month_names[(int) $date_obj->format('n')];

        return "{$day_name} {$day} {$month_name}";
    } else {
        // English format: "Monday, January 15"
        return $date_obj->format('l, F j');
    }
}

/**
 * AJAX handler for getting alternative suggestions
 */
add_action('wp_ajax_rbf_get_suggestions', 'rbf_ajax_get_suggestions_callback');
add_action('wp_ajax_nopriv_rbf_get_suggestions', 'rbf_ajax_get_suggestions_callback');
function rbf_ajax_get_suggestions_callback() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'rbf_ajax_nonce')) {
        wp_send_json_error(['message' => rbf_translate_string('Errore di sicurezza.')]);
        return;
    }

    // Load settings once to derive validation constraints
    $settings = rbf_get_settings();
    $people_max_limit = rbf_get_people_max_limit($settings);
    $has_people_limit = ($people_max_limit > 0 && $people_max_limit < PHP_INT_MAX);

    // Validate required parameters
    $date = sanitize_text_field($_POST['date'] ?? '');
    $meal = sanitize_text_field($_POST['meal'] ?? '');
    $people = intval($_POST['people'] ?? 1);
    $requested_time = sanitize_text_field($_POST['time'] ?? '');

    $people_exceeds_limit = $has_people_limit && $people > $people_max_limit;

    if (empty($date) || empty($meal) || $people < 1 || $people_exceeds_limit) {
        $error_message = rbf_translate_string('Parametri non validi.');

        if ($people_exceeds_limit) {
            $error_message = sprintf(
                rbf_translate_string('Parametri non validi: è consentito un massimo di %d persone.'),
                $people_max_limit
            );
        }

        wp_send_json_error(['message' => $error_message]);
        return;
    }
    
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        wp_send_json_error(['message' => rbf_translate_string('Formato data non valido.')]);
        return;
    }
    
    // Get suggestions
    $suggestions = rbf_get_alternative_suggestions($date, $meal, $people, $requested_time);
    
    wp_send_json_success([
        'suggestions' => $suggestions,
        'count' => count($suggestions),
        'message' => count($suggestions) > 0 
            ? rbf_translate_string('Abbiamo trovato alcune alternative per te!')
            : rbf_translate_string('Non abbiamo trovato alternative disponibili.')
    ]);
}