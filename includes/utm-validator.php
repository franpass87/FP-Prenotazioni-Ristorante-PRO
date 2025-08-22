<?php
/**
 * Enhanced UTM Parameter Validation and Processing
 * 
 * @package FP_Prenotazioni_Ristorante_PRO
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}



/**
 * Get UTM analytics for dashboard
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