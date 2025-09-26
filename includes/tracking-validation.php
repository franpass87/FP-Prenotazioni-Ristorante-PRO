<?php
/**
 * Tracking Validation and Debug Functions
 * 
 * Provides validation and debugging tools for the hybrid tracking system
 * to ensure Google Tag Manager, Google Analytics 4, and Facebook tracking
 * work correctly without duplication.
 * 
 * @package FP_Prenotazioni_Ristorante_PRO
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Validate hybrid tracking configuration
 */
function rbf_validate_tracking_setup() {
    $options = rbf_get_settings();
    $validation_results = [];
    
    // Check GTM + GA4 hybrid configuration
    $gtm_id = $options['gtm_id'] ?? '';
    $ga4_id = $options['ga4_id'] ?? '';
    $gtm_hybrid = ($options['gtm_hybrid'] ?? '') === 'yes';
    
    if ($gtm_hybrid && !empty($gtm_id) && !empty($ga4_id)) {
        $validation_results['hybrid_config'] = [
            'status' => 'ok',
            'message' => 'GTM + GA4 hybrid mode configured correctly'
        ];
    } elseif ($gtm_hybrid && (empty($gtm_id) || empty($ga4_id))) {
        $validation_results['hybrid_config'] = [
            'status' => 'warning',
            'message' => 'Hybrid mode enabled but missing GTM ID or GA4 ID'
        ];
    } else {
        $validation_results['hybrid_config'] = [
            'status' => 'info',
            'message' => 'Standard tracking mode (non-hybrid)'
        ];
    }
    
    // Check Facebook Pixel configuration
    $meta_pixel_id = $options['meta_pixel_id'] ?? '';
    $meta_access_token = $options['meta_access_token'] ?? '';
    
    if (!empty($meta_pixel_id)) {
        if (!empty($meta_access_token)) {
            $validation_results['facebook_tracking'] = [
                'status' => 'ok',
                'message' => 'Facebook Pixel + Conversion API configured'
            ];
        } else {
            $validation_results['facebook_tracking'] = [
                'status' => 'warning',
                'message' => 'Facebook Pixel configured but missing access token for CAPI'
            ];
        }
    } else {
        $validation_results['facebook_tracking'] = [
            'status' => 'info',
            'message' => 'Facebook tracking not configured'
        ];
    }
    
    // Check GA4 API Secret for server-side tracking
    $ga4_api_secret = $options['ga4_api_secret'] ?? '';
    if (!empty($ga4_id)) {
        if (!empty($ga4_api_secret)) {
            $validation_results['ga4_server_side'] = [
                'status' => 'ok',
                'message' => 'GA4 server-side tracking configured'
            ];
        } else {
            $validation_results['ga4_server_side'] = [
                'status' => 'warning',
                'message' => 'GA4 configured but missing API secret for server-side tracking'
            ];
        }
    }

    $google_ads_conversion_id = $options['google_ads_conversion_id'] ?? '';
    $google_ads_conversion_label = $options['google_ads_conversion_label'] ?? '';

    if (!empty($google_ads_conversion_id) && !empty($google_ads_conversion_label)) {
        $validation_results['google_ads_conversion'] = [
            'status' => 'ok',
            'message' => 'Google Ads conversion IDs configured'
        ];
    } elseif (!empty($google_ads_conversion_id) || !empty($google_ads_conversion_label)) {
        $validation_results['google_ads_conversion'] = [
            'status' => 'warning',
            'message' => 'Google Ads conversion tracking partially configured - specify both conversion ID and label'
        ];
    } else {
        $validation_results['google_ads_conversion'] = [
            'status' => 'info',
            'message' => 'Google Ads conversion tracking not configured'
        ];
    }

    // Check for potential duplication risks
    if ($gtm_hybrid && !empty($gtm_id) && !empty($ga4_id)) {
        $validation_results['duplication_risk'] = [
            'status' => 'warning',
            'message' => 'In hybrid mode, ensure GTM container does not have GA4 tag that triggers on purchase events to avoid duplication'
        ];
    } else {
        $validation_results['duplication_risk'] = [
            'status' => 'ok',
            'message' => 'Low risk of event duplication'
        ];
    }
    
    return $validation_results;
}

/**
 * Generate tracking debug information
 */
function rbf_generate_tracking_debug_info() {
    $options = rbf_get_settings();
    $debug_info = [
        'configuration' => [
            'gtm_id' => !empty($options['gtm_id']) ? 'Configured' : 'Not configured',
            'ga4_id' => !empty($options['ga4_id']) ? 'Configured' : 'Not configured',
            'gtm_hybrid' => ($options['gtm_hybrid'] ?? '') === 'yes' ? 'Enabled' : 'Disabled',
            'meta_pixel_id' => !empty($options['meta_pixel_id']) ? 'Configured' : 'Not configured',
            'meta_access_token' => !empty($options['meta_access_token']) ? 'Configured' : 'Not configured',
            'ga4_api_secret' => !empty($options['ga4_api_secret']) ? 'Configured' : 'Not configured',
            'google_ads_conversion' => (!empty($options['google_ads_conversion_id']) && !empty($options['google_ads_conversion_label'])) ? 'Configured' : 'Not configured'
        ],
        'tracking_flow' => rbf_get_tracking_flow_description($options),
        'validation' => rbf_validate_tracking_setup()
    ];
    
    return $debug_info;
}

/**
 * Get description of current tracking flow
 */
function rbf_get_tracking_flow_description($options) {
    $gtm_id = $options['gtm_id'] ?? '';
    $ga4_id = $options['ga4_id'] ?? '';
    $gtm_hybrid = ($options['gtm_hybrid'] ?? '') === 'yes';
    $meta_pixel_id = $options['meta_pixel_id'] ?? '';
    $google_ads_conversion_id = $options['google_ads_conversion_id'] ?? '';
    $google_ads_conversion_label = $options['google_ads_conversion_label'] ?? '';

    $flow = [];

    if ($gtm_hybrid && !empty($gtm_id) && !empty($ga4_id)) {
        $flow[] = "1. GTM container loads ({$gtm_id})";
        $flow[] = "2. GA4 gtag script loads ({$ga4_id})";
        $flow[] = "3. Events sent to dataLayer for GTM processing";
        $flow[] = "4. Direct gtag calls DISABLED to prevent duplication";
    } elseif (!empty($gtm_id)) {
        $flow[] = "1. GTM container loads ({$gtm_id})";
        $flow[] = "2. Events sent to dataLayer for GTM processing";
        $flow[] = "3. GTM handles all tracking tags";
    } elseif (!empty($ga4_id)) {
        $flow[] = "1. GA4 gtag script loads ({$ga4_id})";
        $flow[] = "2. Events sent via direct gtag calls";
    }

    if (!empty($meta_pixel_id)) {
        $flow[] = "Facebook Pixel tracking active";
        if (!empty($options['meta_access_token'])) {
            $flow[] = "Facebook Conversion API server-side backup enabled";
        }
    }

    if (!empty($google_ads_conversion_id) && !empty($google_ads_conversion_label)) {
        if ($gtm_hybrid) {
            $flow[] = "Google Ads conversion tracking configured (activate via GTM workspace)";
        } else {
            $flow[] = "Google Ads conversion tracking active for paid traffic";
        }
    }

    return $flow;
}

/**
 * Add tracking validation to admin page
 */
add_action('admin_init', 'rbf_add_tracking_validation_page');
function rbf_add_tracking_validation_page() {
    if (isset($_GET['rbf_validate_tracking']) && rbf_user_can_manage_settings()) {
        if (!wp_verify_nonce($_GET['nonce'] ?? '', 'rbf_validate_tracking')) {
            wp_die('Invalid nonce');
        }
        
        echo "<div style='max-width: 1200px; margin: 20px; font-family: Arial, sans-serif;'>";
        echo "<h1>Tracking System Validation</h1>";
        
        $debug_info = rbf_generate_tracking_debug_info();
        
        // Configuration status
        echo "<h2>Configuration Status</h2>";
        echo "<table style='border-collapse: collapse; width: 100%; margin-bottom: 20px;'>";
        echo "<tr style='background: #f0f0f0;'><th style='border: 1px solid #ddd; padding: 8px; text-align: left;'>Component</th><th style='border: 1px solid #ddd; padding: 8px; text-align: left;'>Status</th></tr>";
        
        foreach ($debug_info['configuration'] as $component => $status) {
            $color = $status === 'Configured' || $status === 'Enabled' ? '#4CAF50' : '#888';
            echo "<tr>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . ucfirst(str_replace('_', ' ', $component)) . "</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px; color: {$color}; font-weight: bold;'>{$status}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Tracking flow
        echo "<h2>Current Tracking Flow</h2>";
        echo "<ol>";
        foreach ($debug_info['tracking_flow'] as $step) {
            echo "<li style='margin-bottom: 5px;'>{$step}</li>";
        }
        echo "</ol>";
        
        // Validation results
        echo "<h2>Validation Results</h2>";
        echo "<table style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #f0f0f0;'><th style='border: 1px solid #ddd; padding: 8px; text-align: left;'>Check</th><th style='border: 1px solid #ddd; padding: 8px; text-align: left;'>Status</th><th style='border: 1px solid #ddd; padding: 8px; text-align: left;'>Message</th></tr>";
        
        foreach ($debug_info['validation'] as $check => $result) {
            $status_colors = [
                'ok' => '#4CAF50',
                'warning' => '#FF9800',
                'error' => '#f44336',
                'info' => '#2196F3'
            ];
            $color = $status_colors[$result['status']] ?? '#888';
            $status_text = strtoupper($result['status']);
            
            echo "<tr>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . ucfirst(str_replace('_', ' ', $check)) . "</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px; color: {$color}; font-weight: bold;'>{$status_text}</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . esc_html($result['message']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Recommendations
        echo "<h2>Recommendations</h2>";
        echo "<div style='background: #f9f9f9; padding: 15px; border-radius: 5px; margin-top: 20px;'>";
        echo "<h3>For Optimal Tracking Setup:</h3>";
        echo "<ul>";
        echo "<li><strong>GTM + GA4 Hybrid:</strong> Use when you need both GTM flexibility and direct GA4 control</li>";
        echo "<li><strong>Deduplication:</strong> In hybrid mode, disable GA4 configuration tag in GTM or use trigger conditions</li>";
        echo "<li><strong>Enhanced Conversions:</strong> Configure Google Ads conversion IDs in the tracking code</li>";
        echo "<li><strong>Server-side Backup:</strong> Configure API secrets for Facebook CAPI and GA4 Measurement Protocol</li>";
        echo "<li><strong>Testing:</strong> Use Google Analytics DebugView and Facebook Events Manager to verify events</li>";
        echo "</ul>";
        echo "</div>";
        
        echo "</div>";
        exit;
    }
}

// Note: Admin menu and page implementation is now in includes/admin.php
// This file contains only the validation logic functions