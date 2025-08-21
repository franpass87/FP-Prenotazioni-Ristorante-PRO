<?php
/**
 * AJAX functionality for Restaurant Booking Plugin
 *
 * @package RBF
 * @since 9.3.2
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX functionality class
 */
class RBF_Ajax {

    /**
     * Constructor
     */
    public function __construct() {
        $this->init();
    }

    /**
     * Initialize AJAX functionality
     */
    private function init() {
        // Frontend availability check
        add_action('wp_ajax_rbf_get_availability', array($this, 'get_availability_callback'));
        add_action('wp_ajax_nopriv_rbf_get_availability', array($this, 'get_availability_callback'));
        
        // Admin calendar events
        add_action('wp_ajax_rbf_get_bookings_for_calendar', array($this, 'get_bookings_for_calendar_callback'));
    }

    /**
     * Handle availability AJAX request
     */
    public function get_availability_callback() {
        check_ajax_referer('rbf_ajax_nonce');
        
        if (empty($_POST['date']) || empty($_POST['meal'])) {
            wp_send_json_error('Missing required parameters');
        }

        $date = sanitize_text_field($_POST['date']);
        $meal = sanitize_text_field($_POST['meal']);

        $available_times = $this->get_available_times($date, $meal);
        wp_send_json_success($available_times);
    }

    /**
     * Get available times for a date and meal
     * 
     * @param string $date Date in Y-m-d format
     * @param string $meal Meal type (pranzo, cena, aperitivo)
     * @return array Available time slots
     */
    private function get_available_times($date, $meal) {
        $day_of_week = date('w', strtotime($date));
        $options = get_option('rbf_settings', RBF_Utils::get_default_settings());
        
        // Check if restaurant is open on this day
        $day_keys = ['sun','mon','tue','wed','thu','fri','sat'];
        $day_key = $day_keys[$day_of_week];

        if (($options["open_{$day_key}"] ?? 'no') !== 'yes') {
            return [];
        }

        // Check for specific closed dates
        $closed_specific = RBF_Utils::get_closed_specific($options);
        if (in_array($date, $closed_specific['singles'], true)) {
            return [];
        }
        
        // Check for closed date ranges
        foreach ($closed_specific['ranges'] as $range) {
            if ($date >= $range['from'] && $date <= $range['to']) {
                return [];
            }
        }

        // Get configured times for this meal
        $times_csv = $options['orari_'.$meal] ?? '';
        if (empty($times_csv)) {
            return [];
        }
        
        $times = array_values(array_filter(array_map('trim', explode(',', $times_csv))));
        if (empty($times)) {
            return [];
        }

        // Check capacity
        $frontend = RBF_Plugin::get_instance()->get_component('frontend');
        $remaining_capacity = $frontend ? $frontend->get_remaining_capacity($date, $meal) : 0;
        
        if ($remaining_capacity <= 0) {
            return [];
        }

        // Filter times for today (last-minute booking: only show times 15+ minutes from now)
        $tz = RBF_Utils::wp_timezone();
        $now = new DateTime('now', $tz);
        $today_str = $now->format('Y-m-d');
        
        if ($date === $today_str) {
            $now_plus = clone $now;
            $now_plus->modify('+15 minutes');
            $cutoff_time = $now_plus->format('H:i');
            
            $times = array_values(array_filter($times, function($time) use ($cutoff_time) {
                return $time > $cutoff_time;
            }));
        }

        // Format response
        $available = [];
        foreach ($times as $time) {
            $available[] = [
                'slot' => $meal,
                'time' => $time
            ];
        }

        return $available;
    }

    /**
     * Handle calendar bookings AJAX request
     */
    public function get_bookings_for_calendar_callback() {
        check_ajax_referer('rbf_calendar_nonce', '_ajax_nonce');
        
        if (empty($_POST['start']) || empty($_POST['end'])) {
            wp_send_json_error('Missing date range parameters');
        }

        $start = sanitize_text_field($_POST['start']);
        $end = sanitize_text_field($_POST['end']);

        $events = $this->get_calendar_events($start, $end);
        wp_send_json_success($events);
    }

    /**
     * Get calendar events for date range
     * 
     * @param string $start Start date
     * @param string $end End date
     * @return array Calendar events
     */
    private function get_calendar_events($start, $end) {
        $args = [
            'post_type' => 'rbf_booking',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => [[
                'key' => 'rbf_data',
                'value' => [$start, $end],
                'compare' => 'BETWEEN',
                'type' => 'DATE'
            ]]
        ];

        $bookings = get_posts($args);
        $events = [];

        foreach ($bookings as $booking) {
            $date = get_post_meta($booking->ID, 'rbf_data', true);
            $time = get_post_meta($booking->ID, 'rbf_time', true);
            $people = get_post_meta($booking->ID, 'rbf_persone', true);
            
            $title = $booking->post_title . ' (' . $people . ' persone)';
            
            $events[] = [
                'title' => $title,
                'start' => $date . 'T' . $time,
                'url' => admin_url('post.php?post=' . $booking->ID . '&action=edit')
            ];
        }

        return $events;
    }
}