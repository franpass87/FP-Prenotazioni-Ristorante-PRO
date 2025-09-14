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
        if ($availability['level'] === 'full' || $availability['remaining'] < $people) {
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
                'remaining_spots' => $availability['remaining']
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
    $original_timestamp = strtotime($original_date);
    
    // Check dates after the original date (prioritize future dates)
    for ($i = 1; $i <= $days_range; $i++) {
        $check_date = date('Y-m-d', $original_timestamp + ($i * DAY_IN_SECONDS));
        
        $suggestion = rbf_check_date_availability($check_date, $meal, $people, $original_date);
        if ($suggestion) {
            $suggestion['preference_score'] = 80 - ($i * 5); // Decrease score with distance
            $suggestions[] = $suggestion;
        }
    }
    
    // Check dates before the original date (only if we need more suggestions)
    if (count($suggestions) < 2) {
        for ($i = 1; $i <= $days_range; $i++) {
            $check_date = date('Y-m-d', $original_timestamp - ($i * DAY_IN_SECONDS));
            
            // Don't suggest dates in the past
            if (strtotime($check_date) < strtotime(date('Y-m-d'))) {
                continue;
            }
            
            $suggestion = rbf_check_date_availability($check_date, $meal, $people, $original_date);
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
    $original_timestamp = strtotime($original_date);
    
    for ($week = 1; $week <= $weeks_ahead; $week++) {
        $check_date = date('Y-m-d', $original_timestamp + ($week * WEEK_IN_SECONDS));
        
        $suggestion = rbf_check_date_availability($check_date, $meal, $people, $original_date);
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
function rbf_check_date_availability($date, $meal, $people, $original_date) {
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
    if ($availability['level'] === 'full' || $availability['remaining'] < $people) {
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
    $days_diff = (strtotime($date) - strtotime($original_date)) / DAY_IN_SECONDS;
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
        'remaining_spots' => $availability['remaining']
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
    
    // Parse time slots (similar to existing logic in booking-handler.php)
    $times_csv = $meal_config['time_slots'] ?? '';
    if (empty($times_csv)) {
        return $available_times;
    }
    
    $times_array = array_map('trim', explode(',', $times_csv));
    
    foreach ($times_array as $time_str) {
        $time_str = trim($time_str);
        if (empty($time_str)) continue;
        
        // Handle both single times and ranges
        if (strpos($time_str, '-') !== false) {
            // Range format: generate slots
            list($start, $end) = explode('-', $time_str, 2);
            $start_time = trim($start);
            $end_time = trim($end);
            
            $current = strtotime($start_time);
            $end_timestamp = strtotime($end_time);
            
            while ($current <= $end_timestamp) {
                $time = date('H:i', $current);
                
                // Check if this specific time slot has capacity
                if (rbf_check_time_slot_capacity($date, $meal, $time, $people)) {
                    $available_times[] = [
                        'time' => $time,
                        'display' => $time
                    ];
                }
                
                $current += 3600; // Add 1 hour
            }
        } else {
            // Single time
            $time = $time_str;
            if (rbf_check_time_slot_capacity($date, $meal, $time, $people)) {
                $available_times[] = [
                    'time' => $time,
                    'display' => $time
                ];
            }
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
    global $wpdb;
    
    // Get effective capacity for this meal
    $total_capacity = rbf_get_effective_capacity($meal);
    if ($total_capacity <= 0) {
        return true; // Unlimited capacity
    }
    
    // For capacity checking, we need to consider all bookings for this meal on this date
    // since the current system doesn't track individual time slots separately
    $current_bookings = $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(pm_people.meta_value)
         FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm_people ON p.ID = pm_people.post_id AND pm_people.meta_key = 'rbf_persone'
         INNER JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'rbf_data'
         INNER JOIN {$wpdb->postmeta} pm_meal ON p.ID = pm_meal.post_id AND pm_meal.meta_key = 'rbf_orario'
         WHERE p.post_type = 'rbf_booking' AND p.post_status = 'publish'
         AND pm_date.meta_value = %s AND pm_meal.meta_value = %s",
        $date, $meal
    ));
    
    $current_bookings = intval($current_bookings);
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
    
    // Check day of week
    $day_of_week = date('w', strtotime($date));
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
    $timestamp = strtotime($date);
    $locale = rbf_current_lang();
    
    if ($locale === 'it') {
        // Italian format: "Lunedì 15 Gennaio"
        $day_names = ['Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato'];
        $month_names = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno',
                       'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
        
        $day_name = $day_names[date('w', $timestamp)];
        $day = date('j', $timestamp);
        $month_name = $month_names[date('n', $timestamp)];
        
        return "{$day_name} {$day} {$month_name}";
    } else {
        // English format: "Monday, January 15"
        return date('l, F j', $timestamp);
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
    
    // Validate required parameters
    $date = sanitize_text_field($_POST['date'] ?? '');
    $meal = sanitize_text_field($_POST['meal'] ?? '');
    $people = intval($_POST['people'] ?? 1);
    $requested_time = sanitize_text_field($_POST['time'] ?? '');
    
    if (empty($date) || empty($meal) || $people < 1 || $people > 20) {
        wp_send_json_error(['message' => rbf_translate_string('Parametri non validi.')]);
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