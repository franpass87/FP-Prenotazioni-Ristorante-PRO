<?php
/**
 * Database Helper Class for Restaurant Booking Plugin
 * Centralizes database operations to reduce code duplication
 */

if (!defined('ABSPATH')) {
    exit;
}

class RBF_Database_Helper {
    
    /**
     * Get booking by hash
     */
    public static function get_booking_by_hash($hash) {
        global $wpdb;
        
        $booking_id = $wpdb->get_var($wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p 
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
             WHERE p.post_type = 'rbf_booking' AND p.post_status = 'publish'
             AND pm.meta_key = 'rbf_booking_hash' AND pm.meta_value = %s",
            $hash
        ));
        
        return $booking_id ? intval($booking_id) : null;
    }
    
    /**
     * Get booking capacity usage for a specific date and slot
     */
    public static function get_slot_capacity_used($date, $slot) {
        global $wpdb;
        
        $spots_taken = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(pm_people.meta_value)
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_people ON p.ID = pm_people.post_id AND pm_people.meta_key = 'rbf_persone'
             INNER JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'rbf_data'
             INNER JOIN {$wpdb->postmeta} pm_slot ON p.ID = pm_slot.post_id AND pm_slot.meta_key = 'rbf_orario'
             WHERE p.post_type = 'rbf_booking' AND p.post_status = 'publish'
             AND pm_date.meta_value = %s AND pm_slot.meta_value = %s",
            $date, $slot
        ));
        
        return intval($spots_taken);
    }
    
    /**
     * Get bookings in date range with optional status filter
     */
    public static function get_bookings_in_date_range($start_date, $end_date, $status_filter = null) {
        global $wpdb;
        
        $where_status = '';
        if ($status_filter) {
            $where_status = $wpdb->prepare(" AND pm_status.meta_value = %s", $status_filter);
        }
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID, p.post_title, p.post_date,
                    pm_date.meta_value as booking_date,
                    pm_time.meta_value as booking_time,
                    pm_people.meta_value as people,
                    COALESCE(pm_meal.meta_value, pm_meal_legacy.meta_value) as meal,
                    pm_status.meta_value as status,
                    pm_first_name.meta_value as first_name,
                    pm_last_name.meta_value as last_name,
                    pm_email.meta_value as email,
                    pm_tel.meta_value as tel,
                    pm_notes.meta_value as notes,
                    pm_lang.meta_value as language,
                    pm_privacy.meta_value as privacy,
                    pm_marketing.meta_value as marketing,
                    pm_source.meta_value as source,
                    pm_medium.meta_value as medium,
                    pm_campaign.meta_value as campaign,
                    pm_bucket.meta_value as bucket,
                    pm_gclid.meta_value as gclid,
                    pm_fbclid.meta_value as fbclid,
                    pm_created.meta_value as created_date
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'rbf_data'
             LEFT JOIN {$wpdb->postmeta} pm_time ON p.ID = pm_time.post_id AND pm_time.meta_key = 'rbf_orario'
             LEFT JOIN {$wpdb->postmeta} pm_people ON p.ID = pm_people.post_id AND pm_people.meta_key = 'rbf_persone'
             LEFT JOIN {$wpdb->postmeta} pm_meal ON p.ID = pm_meal.post_id AND pm_meal.meta_key = 'rbf_servizio'
             LEFT JOIN {$wpdb->postmeta} pm_meal_legacy ON p.ID = pm_meal_legacy.post_id AND pm_meal_legacy.meta_key = 'rbf_meal'
             LEFT JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = 'rbf_booking_status'
             LEFT JOIN {$wpdb->postmeta} pm_first_name ON p.ID = pm_first_name.post_id AND pm_first_name.meta_key = 'rbf_nome'
             LEFT JOIN {$wpdb->postmeta} pm_last_name ON p.ID = pm_last_name.post_id AND pm_last_name.meta_key = 'rbf_cognome'
             LEFT JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = 'rbf_email'
             LEFT JOIN {$wpdb->postmeta} pm_tel ON p.ID = pm_tel.post_id AND pm_tel.meta_key = 'rbf_tel'
             LEFT JOIN {$wpdb->postmeta} pm_notes ON p.ID = pm_notes.post_id AND pm_notes.meta_key = 'rbf_allergie'
             LEFT JOIN {$wpdb->postmeta} pm_lang ON p.ID = pm_lang.post_id AND pm_lang.meta_key = 'rbf_language'
             LEFT JOIN {$wpdb->postmeta} pm_privacy ON p.ID = pm_privacy.post_id AND pm_privacy.meta_key = 'rbf_privacy'
             LEFT JOIN {$wpdb->postmeta} pm_marketing ON p.ID = pm_marketing.post_id AND pm_marketing.meta_key = 'rbf_marketing'
             LEFT JOIN {$wpdb->postmeta} pm_source ON p.ID = pm_source.post_id AND pm_source.meta_key = 'rbf_utm_source'
             LEFT JOIN {$wpdb->postmeta} pm_medium ON p.ID = pm_medium.post_id AND pm_medium.meta_key = 'rbf_utm_medium'
             LEFT JOIN {$wpdb->postmeta} pm_campaign ON p.ID = pm_campaign.post_id AND pm_campaign.meta_key = 'rbf_utm_campaign'
             LEFT JOIN {$wpdb->postmeta} pm_bucket ON p.ID = pm_bucket.post_id AND pm_bucket.meta_key = 'rbf_source_bucket'
             LEFT JOIN {$wpdb->postmeta} pm_gclid ON p.ID = pm_gclid.post_id AND pm_gclid.meta_key = 'rbf_gclid'
             LEFT JOIN {$wpdb->postmeta} pm_fbclid ON p.ID = pm_fbclid.post_id AND pm_fbclid.meta_key = 'rbf_fbclid'
             LEFT JOIN {$wpdb->postmeta} pm_created ON p.ID = pm_created.post_id AND pm_created.meta_key = 'rbf_booking_created'
             WHERE p.post_type = 'rbf_booking' AND p.post_status = 'publish'
             AND pm_date.meta_value >= %s AND pm_date.meta_value <= %s
             {$where_status}
             ORDER BY pm_date.meta_value DESC, pm_time.meta_value DESC",
            $start_date, $end_date
        ));
    }
    
    /**
     * Get traffic source statistics
     */
    public static function get_traffic_source_stats($since_date) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
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
             INNER JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'rbf_data'
             LEFT JOIN {$wpdb->postmeta} pm_people ON p.ID = pm_people.post_id AND pm_people.meta_key = 'rbf_persone'
             LEFT JOIN {$wpdb->postmeta} pm_meal ON p.ID = pm_meal.post_id AND pm_meal.meta_key = 'rbf_servizio'
             LEFT JOIN {$wpdb->postmeta} pm_bucket ON p.ID = pm_bucket.post_id AND pm_bucket.meta_key = 'rbf_source_bucket'
             WHERE p.post_type = 'rbf_booking' 
             AND p.post_status = 'publish'
             AND p.post_date >= %s
             GROUP BY pm_bucket.meta_value
             ORDER BY count DESC",
            $since_date
        ));
    }
    
    /**
     * Get campaign statistics  
     */
    public static function get_campaign_stats($since_date) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                COALESCE(pm_campaign.meta_value, 'No Campaign') as campaign,
                pm_source.meta_value as utm_source,
                pm_medium.meta_value as utm_medium,
                COUNT(*) as bookings,
                SUM(pm_people.meta_value) as total_people
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm_campaign ON p.ID = pm_campaign.post_id AND pm_campaign.meta_key = 'rbf_utm_campaign'
             LEFT JOIN {$wpdb->postmeta} pm_source ON p.ID = pm_source.post_id AND pm_source.meta_key = 'rbf_utm_source'
             LEFT JOIN {$wpdb->postmeta} pm_medium ON p.ID = pm_medium.post_id AND pm_medium.meta_key = 'rbf_utm_medium'
             LEFT JOIN {$wpdb->postmeta} pm_people ON p.ID = pm_people.post_id AND pm_people.meta_key = 'rbf_persone'
             WHERE p.post_type = 'rbf_booking'
             AND p.post_status = 'publish'
             AND p.post_date >= %s
             AND pm_source.meta_value IS NOT NULL
             GROUP BY pm_campaign.meta_value, pm_source.meta_value, pm_medium.meta_value
             ORDER BY bookings DESC
             LIMIT 10",
            $since_date
        ));
    }
    
    /**
     * Get calendar events for FullCalendar
     */
    public static function get_calendar_events($start_date, $end_date) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID, pm_date.meta_value as booking_date, pm_people.meta_value as people, 
                    COALESCE(pm_meal.meta_value, pm_meal_legacy.meta_value) as meal, pm_status.meta_value as status,
                    pm_source.meta_value as source, pm_bucket.meta_value as bucket
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'rbf_data'
             LEFT JOIN {$wpdb->postmeta} pm_people ON p.ID = pm_people.post_id AND pm_people.meta_key = 'rbf_persone'
             LEFT JOIN {$wpdb->postmeta} pm_meal ON p.ID = pm_meal.post_id AND pm_meal.meta_key = 'rbf_servizio'
             LEFT JOIN {$wpdb->postmeta} pm_meal_legacy ON p.ID = pm_meal_legacy.post_id AND pm_meal_legacy.meta_key = 'rbf_meal'
             LEFT JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = 'rbf_booking_status'
             LEFT JOIN {$wpdb->postmeta} pm_source ON p.ID = pm_source.post_id AND pm_source.meta_key = 'rbf_utm_source'
             LEFT JOIN {$wpdb->postmeta} pm_bucket ON p.ID = pm_bucket.post_id AND pm_bucket.meta_key = 'rbf_source_bucket'
             WHERE p.post_type = 'rbf_booking' AND p.post_status = 'publish'
             AND pm_date.meta_value >= %s AND pm_date.meta_value <= %s
             ORDER BY pm_date.meta_value ASC",
            $start_date, $end_date
        ));
    }
}