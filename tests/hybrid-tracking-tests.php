<?php
/**
 * Test cases for Hybrid Tracking System
 * 
 * Tests the GTM + GA4 hybrid tracking implementation
 * to ensure proper conversion tracking without duplication.
 * 
 * @package FP_Prenotazioni_Ristorante_PRO
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Hybrid Tracking Test Suite
 */
class RBF_Hybrid_Tracking_Tests {
    
    private $test_results = [];
    
    /**
     * Run all hybrid tracking tests
     */
    public function run_all_tests() {
        echo "<h2>Hybrid Tracking System Tests</h2>\n";
        
        $this->test_gtm_hybrid_configuration();
        $this->test_event_deduplication();
        $this->test_enhanced_conversions_data();
        $this->test_facebook_capi_integration();
        $this->test_tracking_validation();
        $this->test_conversion_flow();
        
        $this->display_test_summary();
    }
    
    /**
     * Test GTM hybrid configuration
     */
    private function test_gtm_hybrid_configuration() {
        $test_name = "GTM Hybrid Configuration";
        
        try {
            // Test hybrid mode detection
            $is_hybrid = rbf_is_gtm_hybrid_mode();
            
            $this->assert_true(
                function_exists('rbf_is_gtm_hybrid_mode'),
                "Hybrid mode detection function should exist"
            );
            
            // Test settings integration
            $options = rbf_get_settings();
            $this->assert_true(
                isset($options['gtm_hybrid']),
                "GTM hybrid setting should exist in options"
            );
            
            $this->test_results[$test_name] = ['status' => 'PASS', 'message' => 'GTM hybrid configuration working correctly'];
            
        } catch (Exception $e) {
            $this->test_results[$test_name] = ['status' => 'FAIL', 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Test event deduplication mechanisms
     */
    private function test_event_deduplication() {
        $test_name = "Event Deduplication";
        
        try {
            // Test that the JavaScript tracking includes deduplication keys
            $js_file = RBF_PLUGIN_DIR . 'assets/js/ga4-funnel-tracking.js';
            $js_content = file_get_contents($js_file);
            
            $this->assert_true(
                strpos($js_content, 'gtmHybrid') !== false,
                "JavaScript should check for GTM hybrid mode"
            );
            
            $this->assert_true(
                strpos($js_content, 'forceGtag') !== false,
                "JavaScript should support forcing gtag calls"
            );
            
            // Test server-side tracking configuration
            $integrations_file = RBF_PLUGIN_DIR . 'includes/integrations.php';
            $integrations_content = file_get_contents($integrations_file);
            
            $this->assert_true(
                strpos($integrations_content, 'gtm_uniqueEventId') !== false,
                "Server-side tracking should include GTM-specific deduplication"
            );
            
            $this->test_results[$test_name] = ['status' => 'PASS', 'message' => 'Event deduplication mechanisms implemented correctly'];
            
        } catch (Exception $e) {
            $this->test_results[$test_name] = ['status' => 'FAIL', 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Test enhanced conversions data
     */
    private function test_enhanced_conversions_data() {
        $test_name = "Enhanced Conversions Data";
        
        try {
            $integrations_file = RBF_PLUGIN_DIR . 'includes/integrations.php';
            $integrations_content = file_get_contents($integrations_file);
            
            // Check for customer data hashing
            $this->assert_true(
                strpos($integrations_content, 'customer_email') !== false,
                "Enhanced conversions should include customer email"
            );
            
            $this->assert_true(
                strpos($integrations_content, 'customer_phone') !== false,
                "Enhanced conversions should include customer phone"
            );
            
            $this->assert_true(
                strpos($integrations_content, 'hash(\'sha256\'') !== false,
                "Customer data should be hashed for privacy"
            );
            
            $this->test_results[$test_name] = ['status' => 'PASS', 'message' => 'Enhanced conversions data implemented correctly'];
            
        } catch (Exception $e) {
            $this->test_results[$test_name] = ['status' => 'FAIL', 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Test Facebook CAPI integration
     */
    private function test_facebook_capi_integration() {
        $test_name = "Facebook CAPI Integration";
        
        try {
            // Test CAPI function exists
            $this->assert_true(
                function_exists('rbf_send_facebook_capi_event'),
                "Facebook CAPI function should exist"
            );
            
            // Test CAPI implementation
            $integrations_file = RBF_PLUGIN_DIR . 'includes/integrations.php';
            $integrations_content = file_get_contents($integrations_file);
            
            $this->assert_true(
                strpos($integrations_content, 'graph.facebook.com') !== false,
                "CAPI should use Facebook Graph API"
            );
            
            $this->assert_true(
                strpos($integrations_content, 'eventID') !== false,
                "CAPI should use event ID for deduplication"
            );
            
            $this->test_results[$test_name] = ['status' => 'PASS', 'message' => 'Facebook CAPI integration implemented correctly'];
            
        } catch (Exception $e) {
            $this->test_results[$test_name] = ['status' => 'FAIL', 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Test tracking validation system
     */
    private function test_tracking_validation() {
        $test_name = "Tracking Validation System";
        
        try {
            // Test validation functions exist
            $this->assert_true(
                function_exists('rbf_validate_tracking_setup'),
                "Tracking validation function should exist"
            );
            
            $this->assert_true(
                function_exists('rbf_generate_tracking_debug_info'),
                "Debug info generation function should exist"
            );
            
            // Test validation execution
            $validation_results = rbf_validate_tracking_setup();
            
            $this->assert_true(
                is_array($validation_results),
                "Validation should return array results"
            );
            
            $this->assert_true(
                isset($validation_results['hybrid_config']),
                "Validation should check hybrid configuration"
            );
            
            $this->test_results[$test_name] = ['status' => 'PASS', 'message' => 'Tracking validation system working correctly'];
            
        } catch (Exception $e) {
            $this->test_results[$test_name] = ['status' => 'FAIL', 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Test conversion tracking flow
     */
    private function test_conversion_flow() {
        $test_name = "Conversion Tracking Flow";
        
        try {
            $integrations_file = RBF_PLUGIN_DIR . 'includes/integrations.php';
            $integrations_content = file_get_contents($integrations_file);
            
            // Test standard GA4 purchase event
            $this->assert_true(
                strpos($integrations_content, "rbfTrackEvent('purchase'") !== false,
                "Should track standard GA4 purchase event"
            );
            
            // Test custom restaurant booking event
            $this->assert_true(
                strpos($integrations_content, "rbfTrackEvent('restaurant_booking'") !== false,
                "Should track custom restaurant booking event"
            );
            
            // Test Google Ads conversion tracking
            $this->assert_true(
                strpos($integrations_content, "gtag('event', 'conversion'") !== false,
                "Should track Google Ads conversions"
            );
            
            // Test bucket attribution
            $this->assert_true(
                strpos($integrations_content, 'bucketStd') !== false,
                "Should include normalized bucket attribution"
            );
            
            $this->test_results[$test_name] = ['status' => 'PASS', 'message' => 'Conversion tracking flow implemented correctly'];
            
        } catch (Exception $e) {
            $this->test_results[$test_name] = ['status' => 'FAIL', 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Helper assertion method
     */
    private function assert_true($condition, $message) {
        if (!$condition) {
            throw new Exception($message);
        }
    }
    
    /**
     * Display test summary
     */
    private function display_test_summary() {
        echo "<h3>Test Results Summary</h3>\n";
        echo "<table style='border-collapse: collapse; width: 100%;'>\n";
        echo "<tr style='background: #f0f0f0;'><th style='border: 1px solid #ddd; padding: 8px;'>Test</th><th style='border: 1px solid #ddd; padding: 8px;'>Status</th><th style='border: 1px solid #ddd; padding: 8px;'>Message</th></tr>\n";
        
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
        
        echo "<p style='margin-top: 20px; padding: 15px; background: {$summary_color}; color: white; border-radius: 5px;'>\n";
        echo "<strong>Overall Result: {$pass_count}/{$total_count} tests passed ({$pass_rate}%)</strong>\n";
        echo "</p>\n";
        
        if ($pass_rate < 100) {
            echo "<p style='color: #f44336;'><strong>Note:</strong> Some tests failed. Please review the implementation and fix any issues before deploying.</p>\n";
        } else {
            echo "<p style='color: #4CAF50;'><strong>All tests passed!</strong> Hybrid tracking system is working correctly.</p>\n";
        }
    }
}

/**
 * Run hybrid tracking tests if requested
 */
if (isset($_GET['rbf_test_hybrid_tracking']) && ((function_exists('rbf_user_can_manage_settings') && rbf_user_can_manage_settings()) || (!function_exists('rbf_user_can_manage_settings') && function_exists('current_user_can') && current_user_can('manage_options')))) {
    add_action('admin_init', function() {
        if (!wp_verify_nonce($_GET['nonce'] ?? '', 'rbf_test_hybrid_tracking')) {
            wp_die('Invalid nonce');
        }
        
        echo "<div style='max-width: 1200px; margin: 20px; font-family: Arial, sans-serif;'>\n";
        
        $tests = new RBF_Hybrid_Tracking_Tests();
        $tests->run_all_tests();
        
        echo "<div style='margin-top: 30px; padding: 15px; background: #f9f9f9; border-radius: 5px;'>\n";
        echo "<h3>Manual Testing Recommendations</h3>\n";
        echo "<ol>\n";
        echo "<li><strong>GTM Configuration:</strong> Ensure GTM container does not have GA4 tag triggering on purchase events in hybrid mode</li>\n";
        echo "<li><strong>Google Analytics DebugView:</strong> Check for duplicate events in GA4 DebugView</li>\n";
        echo "<li><strong>Facebook Events Manager:</strong> Verify Facebook Pixel and CAPI events are deduplicated</li>\n";
        echo "<li><strong>Google Ads:</strong> Test enhanced conversions are working with proper customer data</li>\n";
        echo "<li><strong>Browser Testing:</strong> Test booking flow with different traffic sources (gclid, fbclid, organic)</li>\n";
        echo "</ol>\n";
        echo "</div>\n";
        
        echo "</div>\n";
        exit;
    });
}