<?php
/**
 * Comprehensive Tracking System Verification
 * 
 * This script performs a complete verification of the tracking system
 * to ensure all components are functioning correctly.
 * 
 * @package FP_Prenotazioni_Ristorante_PRO
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Comprehensive Tracking Verification Class
 */
class RBF_Comprehensive_Tracking_Verification {
    
    private $test_results = [];
    private $warnings = [];
    private $errors = [];
    
    /**
     * Run comprehensive tracking verification
     */
    public function run_verification() {
        echo "<h2>Comprehensive Tracking System Verification</h2>\n";
        echo "<p>Verifying all tracking system components...</p>\n";
        
        $this->verify_file_existence();
        $this->verify_function_availability();
        $this->verify_configuration_integrity();
        $this->verify_tracking_flow();
        $this->verify_javascript_integration();
        $this->verify_deduplication_mechanisms();
        $this->verify_error_handling();
        $this->verify_security_measures();
        
        $this->display_verification_summary();
    }
    
    /**
     * Verify all tracking files exist
     */
    private function verify_file_existence() {
        $test_name = "File Existence Verification";
        
        try {
            $required_files = [
                'includes/tracking-validation.php',
                'includes/ga4-funnel-tracking.php',
                'includes/integrations.php',
                'assets/js/ga4-funnel-tracking.js',
                'tests/hybrid-tracking-tests.php'
            ];
            
            $missing_files = [];
            foreach ($required_files as $file) {
                $full_path = RBF_PLUGIN_DIR . $file;
                if (!file_exists($full_path)) {
                    $missing_files[] = $file;
                }
            }
            
            if (empty($missing_files)) {
                $this->test_results[$test_name] = ['status' => 'PASS', 'message' => 'All tracking system files exist'];
            } else {
                $this->test_results[$test_name] = ['status' => 'FAIL', 'message' => 'Missing files: ' . implode(', ', $missing_files)];
                $this->errors[] = "Missing tracking files detected";
            }
            
        } catch (Exception $e) {
            $this->test_results[$test_name] = ['status' => 'FAIL', 'message' => $e->getMessage()];
            $this->errors[] = $e->getMessage();
        }
    }
    
    /**
     * Verify all required functions are available
     */
    private function verify_function_availability() {
        $test_name = "Function Availability Verification";
        
        try {
            $required_functions = [
                'rbf_validate_tracking_setup',
                'rbf_generate_tracking_debug_info',
                'rbf_is_gtm_hybrid_mode',
                'rbf_generate_session_id',
                'rbf_generate_event_id',
                'rbf_get_ga4_config',
                'rbf_send_ga4_measurement_protocol',
                'rbf_track_booking_completion',
                'rbf_send_facebook_capi_event',
                'rbf_perform_tracking_test'
            ];
            
            $missing_functions = [];
            foreach ($required_functions as $function_name) {
                if (!function_exists($function_name)) {
                    $missing_functions[] = $function_name;
                }
            }
            
            if (empty($missing_functions)) {
                $this->test_results[$test_name] = ['status' => 'PASS', 'message' => 'All tracking functions are available'];
            } else {
                $this->test_results[$test_name] = ['status' => 'FAIL', 'message' => 'Missing functions: ' . implode(', ', $missing_functions)];
                $this->errors[] = "Missing tracking functions detected";
            }
            
        } catch (Exception $e) {
            $this->test_results[$test_name] = ['status' => 'FAIL', 'message' => $e->getMessage()];
            $this->errors[] = $e->getMessage();
        }
    }
    
    /**
     * Verify configuration integrity
     */
    private function verify_configuration_integrity() {
        $test_name = "Configuration Integrity Verification";
        
        try {
            $options = rbf_get_settings();
            $config_issues = [];
            
            // Check for required configuration keys
            $tracking_keys = ['ga4_id', 'ga4_api_secret', 'gtm_id', 'gtm_hybrid', 'meta_pixel_id', 'meta_access_token'];
            foreach ($tracking_keys as $key) {
                if (!array_key_exists($key, $options)) {
                    $config_issues[] = "Missing configuration key: {$key}";
                }
            }
            
            // Check for hybrid mode consistency
            if (($options['gtm_hybrid'] ?? '') === 'yes') {
                if (empty($options['gtm_id'])) {
                    $config_issues[] = "Hybrid mode enabled but GTM ID is missing";
                }
                if (empty($options['ga4_id'])) {
                    $config_issues[] = "Hybrid mode enabled but GA4 ID is missing";
                }
            }
            
            // Check for Facebook CAPI consistency
            if (!empty($options['meta_pixel_id']) && empty($options['meta_access_token'])) {
                $this->warnings[] = "Facebook Pixel configured but access token missing - CAPI won't work";
            }
            
            // Check for GA4 server-side tracking consistency
            if (!empty($options['ga4_id']) && empty($options['ga4_api_secret'])) {
                $this->warnings[] = "GA4 configured but API secret missing - server-side tracking unavailable";
            }
            
            if (empty($config_issues)) {
                $this->test_results[$test_name] = ['status' => 'PASS', 'message' => 'Configuration integrity verified'];
            } else {
                $this->test_results[$test_name] = ['status' => 'FAIL', 'message' => implode('; ', $config_issues)];
                $this->errors = array_merge($this->errors, $config_issues);
            }
            
        } catch (Exception $e) {
            $this->test_results[$test_name] = ['status' => 'FAIL', 'message' => $e->getMessage()];
            $this->errors[] = $e->getMessage();
        }
    }
    
    /**
     * Verify tracking flow execution
     */
    private function verify_tracking_flow() {
        $test_name = "Tracking Flow Verification";
        
        try {
            // Test validation function execution
            $validation_results = rbf_validate_tracking_setup();
            if (!is_array($validation_results)) {
                throw new Exception("Validation function returns invalid data type");
            }
            
            // Test debug info generation
            $debug_info = rbf_generate_tracking_debug_info();
            if (!is_array($debug_info) || !isset($debug_info['configuration'], $debug_info['tracking_flow'], $debug_info['validation'])) {
                throw new Exception("Debug info generation returns invalid structure");
            }
            
            // Test session ID generation
            $session_id = rbf_generate_session_id();
            if (empty($session_id) || !is_string($session_id)) {
                throw new Exception("Session ID generation failed");
            }
            
            // Test event ID generation
            $event_id = rbf_generate_event_id('test_event', $session_id);
            if (empty($event_id) || !is_string($event_id) || !str_contains($event_id, 'rbf_test_event')) {
                throw new Exception("Event ID generation failed");
            }
            
            // Test GA4 configuration
            $ga4_config = rbf_get_ga4_config();
            if (!is_array($ga4_config) || !isset($ga4_config['measurement_id'], $ga4_config['api_secret'], $ga4_config['enabled'])) {
                throw new Exception("GA4 configuration function returns invalid structure");
            }
            
            $this->test_results[$test_name] = ['status' => 'PASS', 'message' => 'All tracking flow functions execute correctly'];
            
        } catch (Exception $e) {
            $this->test_results[$test_name] = ['status' => 'FAIL', 'message' => $e->getMessage()];
            $this->errors[] = $e->getMessage();
        }
    }
    
    /**
     * Verify JavaScript integration
     */
    private function verify_javascript_integration() {
        $test_name = "JavaScript Integration Verification";
        
        try {
            $js_file = RBF_PLUGIN_DIR . 'assets/js/ga4-funnel-tracking.js';
            $js_content = file_get_contents($js_file);
            
            $required_js_elements = [
                'rbfFunnelTracker',
                'trackEvent',
                'generateEventId',
                'gtmHybrid',
                'dataLayer',
                'rbfGA4Funnel'
            ];
            
            $missing_js_elements = [];
            foreach ($required_js_elements as $element) {
                if (strpos($js_content, $element) === false) {
                    $missing_js_elements[] = $element;
                }
            }
            
            if (empty($missing_js_elements)) {
                $this->test_results[$test_name] = ['status' => 'PASS', 'message' => 'JavaScript integration verified'];
            } else {
                $this->test_results[$test_name] = ['status' => 'FAIL', 'message' => 'Missing JS elements: ' . implode(', ', $missing_js_elements)];
                $this->errors[] = "JavaScript integration incomplete";
            }
            
        } catch (Exception $e) {
            $this->test_results[$test_name] = ['status' => 'FAIL', 'message' => $e->getMessage()];
            $this->errors[] = $e->getMessage();
        }
    }
    
    /**
     * Verify deduplication mechanisms
     */
    private function verify_deduplication_mechanisms() {
        $test_name = "Deduplication Mechanisms Verification";
        
        try {
            $integrations_file = RBF_PLUGIN_DIR . 'includes/integrations.php';
            $integrations_content = file_get_contents($integrations_file);
            
            $deduplication_elements = [
                'event_id',
                'gtm_uniqueEventId',
                'eventID',
                'transaction_id',
                'deduplication_key'
            ];
            
            $missing_dedup_elements = [];
            foreach ($deduplication_elements as $element) {
                if (strpos($integrations_content, $element) === false) {
                    $missing_dedup_elements[] = $element;
                }
            }
            
            if (empty($missing_dedup_elements)) {
                $this->test_results[$test_name] = ['status' => 'PASS', 'message' => 'Deduplication mechanisms verified'];
            } else {
                $this->test_results[$test_name] = ['status' => 'FAIL', 'message' => 'Missing deduplication elements: ' . implode(', ', $missing_dedup_elements)];
                $this->errors[] = "Deduplication mechanisms incomplete";
            }
            
        } catch (Exception $e) {
            $this->test_results[$test_name] = ['status' => 'FAIL', 'message' => $e->getMessage()];
            $this->errors[] = $e->getMessage();
        }
    }
    
    /**
     * Verify error handling
     */
    private function verify_error_handling() {
        $test_name = "Error Handling Verification";
        
        try {
            // Check if error tracking functions exist
            if (!function_exists('rbf_track_booking_error')) {
                throw new Exception("Error tracking function missing");
            }
            
            if (!function_exists('rbf_classify_error_type')) {
                throw new Exception("Error classification function missing");
            }
            
            // Test error classification
            $error_type = rbf_classify_error_type('validation');
            if ($error_type !== 'validation_error') {
                throw new Exception("Error classification not working correctly");
            }
            
            $this->test_results[$test_name] = ['status' => 'PASS', 'message' => 'Error handling verified'];
            
        } catch (Exception $e) {
            $this->test_results[$test_name] = ['status' => 'FAIL', 'message' => $e->getMessage()];
            $this->errors[] = $e->getMessage();
        }
    }
    
    /**
     * Verify security measures
     */
    private function verify_security_measures() {
        $test_name = "Security Measures Verification";
        
        try {
            $integrations_file = RBF_PLUGIN_DIR . 'includes/integrations.php';
            $integrations_content = file_get_contents($integrations_file);
            
            $security_elements = [
                'hash(\'sha256\'',
                'esc_js(',
                'esc_attr(',
                'sanitize_text_field(',
                'wp_verify_nonce('
            ];
            
            $missing_security_elements = [];
            foreach ($security_elements as $element) {
                if (strpos($integrations_content, $element) === false) {
                    $missing_security_elements[] = $element;
                }
            }
            
            if (empty($missing_security_elements)) {
                $this->test_results[$test_name] = ['status' => 'PASS', 'message' => 'Security measures verified'];
            } else {
                $this->test_results[$test_name] = ['status' => 'FAIL', 'message' => 'Missing security elements: ' . implode(', ', $missing_security_elements)];
                $this->errors[] = "Security measures incomplete";
            }
            
        } catch (Exception $e) {
            $this->test_results[$test_name] = ['status' => 'FAIL', 'message' => $e->getMessage()];
            $this->errors[] = $e->getMessage();
        }
    }
    
    /**
     * Display comprehensive verification summary
     */
    private function display_verification_summary() {
        echo "<h3>Comprehensive Verification Results</h3>\n";
        echo "<table style='border-collapse: collapse; width: 100%; margin-bottom: 20px;'>\n";
        echo "<tr style='background: #f0f0f0;'><th style='border: 1px solid #ddd; padding: 8px;'>Verification Test</th><th style='border: 1px solid #ddd; padding: 8px;'>Status</th><th style='border: 1px solid #ddd; padding: 8px;'>Details</th></tr>\n";
        
        $pass_count = 0;
        $total_count = count($this->test_results);
        
        foreach ($this->test_results as $test_name => $result) {
            $status_color = $result['status'] === 'PASS' ? '#4CAF50' : '#f44336';
            $status_text = $result['status'] === 'PASS' ? '✓ PASS' : '✗ FAIL';
            
            if ($result['status'] === 'PASS') {
                $pass_count++;
            }
            
            echo "<tr>\n";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$test_name}</td>\n";
            echo "<td style='border: 1px solid #ddd; padding: 8px; color: {$status_color}; font-weight: bold;'>{$status_text}</td>\n";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . esc_html($result['message']) . "</td>\n";
            echo "</tr>\n";
        }
        
        echo "</table>\n";
        
        $pass_rate = $total_count > 0 ? round(($pass_count / $total_count) * 100, 1) : 0;
        $summary_color = $pass_rate >= 100 ? '#4CAF50' : ($pass_rate >= 80 ? '#FF9800' : '#f44336');
        
        echo "<div style='margin-top: 20px; padding: 15px; background: {$summary_color}; color: white; border-radius: 5px;'>\n";
        echo "<h3>Overall Verification Result: {$pass_count}/{$total_count} tests passed ({$pass_rate}%)</h3>\n";
        echo "</div>\n";
        
        // Display warnings if any
        if (!empty($this->warnings)) {
            echo "<div style='margin-top: 20px; padding: 15px; background: #fff8f0; border: 1px solid #dba617; border-radius: 5px;'>\n";
            echo "<h4 style='color: #dba617; margin-top: 0;'>⚠ Warnings:</h4>\n";
            echo "<ul>\n";
            foreach ($this->warnings as $warning) {
                echo "<li style='color: #666;'>" . esc_html($warning) . "</li>\n";
            }
            echo "</ul>\n";
            echo "</div>\n";
        }
        
        // Display errors if any
        if (!empty($this->errors)) {
            echo "<div style='margin-top: 20px; padding: 15px; background: #fff0f0; border: 1px solid #f44336; border-radius: 5px;'>\n";
            echo "<h4 style='color: #f44336; margin-top: 0;'>✗ Critical Issues:</h4>\n";
            echo "<ul>\n";
            foreach ($this->errors as $error) {
                echo "<li style='color: #666;'>" . esc_html($error) . "</li>\n";
            }
            echo "</ul>\n";
            echo "</div>\n";
        }
        
        // Recommendations
        echo "<div style='margin-top: 30px; padding: 15px; background: #f9f9f9; border-radius: 5px;'>\n";
        echo "<h3>Recommendations</h3>\n";
        
        if ($pass_rate >= 100) {
            echo "<p style='color: #4CAF50;'><strong>✓ Excellent!</strong> The tracking system is fully functional and well-implemented.</p>\n";
            echo "<ul>\n";
            echo "<li>All core tracking components are working correctly</li>\n";
            echo "<li>Deduplication mechanisms are in place</li>\n";
            echo "<li>Security measures are implemented</li>\n";
            echo "<li>Error handling is comprehensive</li>\n";
            echo "</ul>\n";
        } else {
            echo "<p style='color: #f44336;'><strong>Action Required:</strong> Some tracking system components need attention.</p>\n";
            echo "<ul>\n";
            echo "<li>Review and fix the failed verification tests above</li>\n";
            echo "<li>Ensure all required functions are properly loaded</li>\n";
            echo "<li>Verify configuration settings are complete</li>\n";
            echo "<li>Test the tracking system with real booking scenarios</li>\n";
            echo "</ul>\n";
        }
        
        echo "<h4>Additional Manual Testing:</h4>\n";
        echo "<ul>\n";
        echo "<li><strong>GTM Preview Mode:</strong> Test with Google Tag Manager Preview mode to verify event flow</li>\n";
        echo "<li><strong>GA4 DebugView:</strong> Check for events in Google Analytics 4 DebugView</li>\n";
        echo "<li><strong>Facebook Events Manager:</strong> Verify Facebook Pixel and CAPI events</li>\n";
        echo "<li><strong>Browser Developer Tools:</strong> Monitor console for JavaScript errors</li>\n";
        echo "<li><strong>Network Tab:</strong> Verify tracking requests are being sent</li>\n";
        echo "</ul>\n";
        echo "</div>\n";
    }
}

/**
 * Run comprehensive tracking verification if requested
 */
if (isset($_GET['rbf_comprehensive_tracking_verification']) && ((function_exists('rbf_user_can_manage_settings') && rbf_user_can_manage_settings()) || (!function_exists('rbf_user_can_manage_settings') && function_exists('current_user_can') && current_user_can('manage_options')))) {
    add_action('admin_init', function() {
        if (!wp_verify_nonce($_GET['nonce'] ?? '', 'rbf_comprehensive_tracking_verification')) {
            wp_die('Invalid nonce');
        }
        
        echo "<div style='max-width: 1200px; margin: 20px; font-family: Arial, sans-serif;'>\n";
        
        $verification = new RBF_Comprehensive_Tracking_Verification();
        $verification->run_verification();
        
        echo "</div>\n";
        exit;
    });
}