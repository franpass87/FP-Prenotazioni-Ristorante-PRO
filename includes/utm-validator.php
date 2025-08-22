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
 * Enhanced UTM parameter validation with security improvements
 */
function rbf_validate_utm_parameters($utm_data) {
    $validated = [];
    
    // Source validation - alphanumeric, dots, hyphens, underscores only
    if (!empty($utm_data['utm_source'])) {
        $source = strtolower(trim($utm_data['utm_source']));
        $validated['utm_source'] = preg_replace('/[^a-zA-Z0-9._-]/', '', $source);
        
        // Limit length to prevent data bloat
        $validated['utm_source'] = substr($validated['utm_source'], 0, 100);
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
        if (in_array($medium, $valid_mediums, true)) {
            $validated['utm_medium'] = $medium;
        } else {
            // Fallback for unrecognized mediums
            $validated['utm_medium'] = 'other';
            
            // Log unrecognized medium for analysis removed
        }
    }
    
    // Campaign validation
    if (!empty($utm_data['utm_campaign'])) {
        $campaign = sanitize_text_field($utm_data['utm_campaign']);
        // Remove potentially dangerous characters and limit length
        $validated['utm_campaign'] = substr(
            preg_replace('/[<>"\'\\/\\\\]/', '', $campaign), 
            0, 
            150
        );
    }
    
    // UTM Term validation (for search keywords)
    if (!empty($utm_data['utm_term'])) {
        $term = sanitize_text_field($utm_data['utm_term']);
        $validated['utm_term'] = substr(
            preg_replace('/[<>"\'\\/\\\\]/', '', $term), 
            0, 
            100
        );
    }
    
    // UTM Content validation (for A/B testing)
    if (!empty($utm_data['utm_content'])) {
        $content = sanitize_text_field($utm_data['utm_content']);
        $validated['utm_content'] = substr(
            preg_replace('/[<>"\'\\/\\\\]/', '', $content), 
            0, 
            100
        );
    }
    
    // Google Ads Click ID validation
    if (!empty($utm_data['gclid'])) {
        $gclid = trim($utm_data['gclid']);
        // GCLID should be alphanumeric with some allowed special chars
        if (preg_match('/^[a-zA-Z0-9._-]+$/', $gclid) && strlen($gclid) <= 200) {
            $validated['gclid'] = $gclid;
        } else {
            // Suspicious gclid handling
        }
    }
    
    // Facebook Click ID validation
    if (!empty($utm_data['fbclid'])) {
        $fbclid = trim($utm_data['fbclid']);
        // FBCLID should be alphanumeric with some allowed special chars
        if (preg_match('/^[a-zA-Z0-9._-]+$/', $fbclid) && strlen($fbclid) <= 200) {
            $validated['fbclid'] = $fbclid;
        } else {
            // Suspicious fbclid handling
        }
    }
    
    // Successful validation logging removed
    
    return $validated;
}

/**
 * Enhanced source detection with improved validation
 */
function rbf_detect_source_enhanced($data = []) {
    // Validate UTM parameters first
    $validated_data = rbf_validate_utm_parameters($data);
    
    // Use the original detection function with validated data
    if (function_exists('rbf_detect_source')) {
        $result = rbf_detect_source($validated_data);
        
        // Source detection analytics removed
        
        return $result;
    }
    
    // Fallback if original function doesn't exist
    return ['bucket' => 'unknown', 'source' => null, 'medium' => null, 'campaign' => null];
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