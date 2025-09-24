<?php
/**
 * Tests for GA4 Funnel Tracking
 *
 * @package FP_Prenotazioni_Ristorante_PRO
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * GA4 Funnel Tracking Test Suite
 */
class RBF_GA4_Funnel_Tests {
    
    private $test_results = [];
    private $session_id;
    
    public function __construct() {
        $this->session_id = 'test_session_' . time();
    }
    
    /**
     * Run all funnel tracking tests
     */
    public function run_all_tests() {
        echo "<h2>GA4 Funnel Tracking Tests</h2>\n";
        
        $this->test_session_id_generation();
        $this->test_event_id_generation();
        $this->test_ga4_config();
        $this->test_event_tracking();
        $this->test_error_classification();
        $this->test_booking_completion_tracking();
        $this->test_booking_completion_refresh_fallback();
        $this->test_measurement_protocol();
        $this->test_javascript_integration();
        
        $this->display_test_summary();
    }
    
    /**
     * Test session ID generation
     */
    private function test_session_id_generation() {
        $test_name = "Session ID Generation";
        
        try {
            // Test session ID generation
            $session_id1 = rbf_generate_session_id();
            $session_id2 = rbf_generate_session_id();
            
            // Should return same ID for same session
            $this->assert_true(
                $session_id1 === $session_id2,
                "Session ID should be consistent within same session"
            );
            
            // Should have correct format
            $this->assert_true(
                preg_match('/^rbf_[a-f0-9]{16}$/', $session_id1),
                "Session ID should have format 'rbf_' + 16 hex chars"
            );
            
            $this->test_results[$test_name] = ['status' => 'PASS', 'message' => 'Session ID generation working correctly'];
            
        } catch (Exception $e) {
            $this->test_results[$test_name] = ['status' => 'FAIL', 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Test event ID generation
     */
    private function test_event_id_generation() {
        $test_name = "Event ID Generation";
        
        try {
            $event_id1 = rbf_generate_event_id('test_event', $this->session_id);
            $event_id2 = rbf_generate_event_id('test_event', $this->session_id);
            
            // Should be unique even for same event type
            $this->assert_true(
                $event_id1 !== $event_id2,
                "Event IDs should be unique"
            );
            
            // Should have correct format
            $this->assert_true(
                preg_match('/^rbf_test_event_' . preg_quote($this->session_id) . '_\d+$/', $event_id1),
                "Event ID should have correct format"
            );
            
            $this->test_results[$test_name] = ['status' => 'PASS', 'message' => 'Event ID generation working correctly'];
            
        } catch (Exception $e) {
            $this->test_results[$test_name] = ['status' => 'FAIL', 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Test GA4 configuration
     */
    private function test_ga4_config() {
        $test_name = "GA4 Configuration";
        
        try {
            $config = rbf_get_ga4_config();
            
            $this->assert_true(
                is_array($config),
                "GA4 config should return array"
            );
            
            $this->assert_true(
                isset($config['measurement_id']) && isset($config['api_secret']) && isset($config['enabled']),
                "GA4 config should have required keys"
            );
            
            $this->test_results[$test_name] = ['status' => 'PASS', 'message' => 'GA4 configuration working correctly'];
            
        } catch (Exception $e) {
            $this->test_results[$test_name] = ['status' => 'FAIL', 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Test event tracking functions
     */
    private function test_event_tracking() {
        $test_name = "Event Tracking Functions";
        
        try {
            // Test booking completion tracking
            $booking_data = [
                'value' => 50.0,
                'currency' => 'EUR',
                'meal' => 'cena',
                'people' => 2,
                'bucket' => 'organic',
                'tracking_token' => 'testtoken123'
            ];

            // This should not throw errors
            rbf_track_booking_completion(123, $booking_data);
            
            // Test error tracking
            rbf_track_booking_error('Test error message', 'validation', $this->session_id);
            
            $this->test_results[$test_name] = ['status' => 'PASS', 'message' => 'Event tracking functions working correctly'];
            
        } catch (Exception $e) {
            $this->test_results[$test_name] = ['status' => 'FAIL', 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Test error type classification
     */
    private function test_error_classification() {
        $test_name = "Error Classification";
        
        try {
            $test_cases = [
                'validation' => 'validation_error',
                'email_validation' => 'validation_error',
                'capacity_validation' => 'availability_error',
                'security' => 'security_error',
                'database_error' => 'system_error',
                'meta_api' => 'integration_error',
                'unknown_context' => 'unknown_error'
            ];
            
            foreach ($test_cases as $context => $expected) {
                $actual = rbf_classify_error_type($context);
                $this->assert_true(
                    $actual === $expected,
                    "Error classification for '{$context}' should be '{$expected}', got '{$actual}'"
                );
            }
            
            $this->test_results[$test_name] = ['status' => 'PASS', 'message' => 'Error classification working correctly'];
            
        } catch (Exception $e) {
            $this->test_results[$test_name] = ['status' => 'FAIL', 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Test booking completion tracking data
     */
    private function test_booking_completion_tracking() {
        $test_name = "Booking Completion Tracking";

        try {
            $booking_id = 456;
            $booking_data = [
                'value' => 75.50,
                'currency' => 'EUR',
                'meal' => 'pranzo',
                'people' => 3,
                'bucket' => 'gads',
                'tracking_token' => 'token456'
            ];

            rbf_track_booking_completion($booking_id, $booking_data);

            // Check if transient was set
            $transient_data = get_transient('rbf_ga4_completion_' . $booking_id);
            
            $this->assert_true(
                $transient_data !== false,
                "Completion tracking should set transient data"
            );
            
            $this->assert_true(
                isset($transient_data['event_params']) &&
                $transient_data['event_params']['value'] == 75.50,
                "Transient should contain correct event parameters"
            );

            $this->assert_true(
                isset($transient_data['tracking_token']) && $transient_data['tracking_token'] === 'token456',
                'Transient should persist the tracking token for verification'
            );

            $this->assert_true(
                !empty($transient_data['client_id']),
                "Completion tracking should persist GA client ID"
            );

            $this->assert_true(
                !empty($transient_data['event_id']),
                "Completion tracking should store event ID for deduplication"
            );

            $this->assert_true(
                ($transient_data['event_name'] ?? '') === 'booking_confirmed',
                "Completion tracking should expose booking_confirmed event name"
            );

            $this->test_results[$test_name] = ['status' => 'PASS', 'message' => 'Booking completion tracking working correctly'];

        } catch (Exception $e) {
            $this->test_results[$test_name] = ['status' => 'FAIL', 'message' => $e->getMessage()];
        }
    }

    /**
     * Ensure the completion endpoint returns real booking data after transient expiry.
     */
    private function test_booking_completion_refresh_fallback() {
        $test_name = "Booking Completion Fallback After Expiry";
        $booking_id = 0;

        try {
            $booking_post = wp_insert_post([
                'post_type' => 'rbf_booking',
                'post_title' => 'GA4 Fallback Test Booking',
                'post_status' => 'publish',
            ], true);

            if (is_wp_error($booking_post)) {
                throw new Exception('Failed to create test booking for fallback validation');
            }

            $booking_id = (int) $booking_post;
            $value = 120.5;
            $people = 4;
            $meal = 'cena';
            $bucket = 'gads';
            $currency = 'EUR';
            $tracking_token = 'fallbacktoken_' . wp_rand(1000, 9999);

            update_post_meta($booking_id, 'rbf_meal', $meal);
            update_post_meta($booking_id, 'rbf_persone', $people);
            update_post_meta($booking_id, 'rbf_source_bucket', $bucket);
            update_post_meta($booking_id, 'rbf_valore_tot', $value);
            update_post_meta($booking_id, 'rbf_valore_pp', $value / $people);

            rbf_store_booking_tracking_token($booking_id, $tracking_token);

            $booking_data = [
                'value' => $value,
                'currency' => $currency,
                'meal' => $meal,
                'people' => $people,
                'bucket' => $bucket,
                'tracking_token' => $tracking_token,
            ];

            rbf_track_booking_completion($booking_id, $booking_data);

            $completion_key = 'rbf_ga4_completion_' . $booking_id;
            $initial_payload = get_transient($completion_key);

            $this->assert_true(
                is_array($initial_payload) && !empty($initial_payload['event_params']),
                'Completion payload should be stored before transient expiry'
            );

            $initial_event_params = $initial_payload['event_params'];

            // Simulate the transient expiring between page loads.
            delete_transient($completion_key);

            $response = rbf_prepare_booking_completion_response($booking_id, $tracking_token);
            $this->assert_true(
                !is_wp_error($response),
                'Fallback should succeed when the stored token hash matches the provided token'
            );

            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }

            $event_params = $response['event_params'] ?? [];
            $this->assert_true(!empty($event_params), 'Fallback response should include event parameters');

            $this->assert_true(
                abs((float) ($event_params['value'] ?? 0) - (float) ($initial_event_params['value'] ?? 0)) < 0.0001,
                'Fallback response should preserve booking value'
            );

            $this->assert_true(
                ($event_params['currency'] ?? '') === ($initial_event_params['currency'] ?? ''),
                'Fallback response should preserve booking currency'
            );

            $this->assert_true(
                ($event_params['meal_type'] ?? '') === ($initial_event_params['meal_type'] ?? ''),
                'Fallback response should preserve meal type'
            );

            $this->assert_true(
                (int) ($event_params['people_count'] ?? 0) === (int) ($initial_event_params['people_count'] ?? 0),
                'Fallback response should preserve people count'
            );

            $this->test_results[$test_name] = [
                'status' => 'PASS',
                'message' => 'Fallback reconstruction returns real booking data after transient expiry'
            ];
        } catch (Exception $e) {
            $this->test_results[$test_name] = ['status' => 'FAIL', 'message' => $e->getMessage()];
        } finally {
            if ($booking_id) {
                delete_transient('rbf_ga4_completion_' . $booking_id);
                rbf_clear_booking_tracking_token($booking_id);
                wp_delete_post($booking_id, true);
            }
        }
    }
    
    /**
     * Test measurement protocol payload structure
     */
    private function test_measurement_protocol() {
        $test_name = "Measurement Protocol Structure";
        
        try {
            // Mock the measurement protocol function to test payload structure
            $event_name = 'test_event';
            $params = ['test_param' => 'test_value'];
            $session_id = $this->session_id;
            $event_id = rbf_generate_event_id($event_name, $session_id);
            
            // Test that function exists and accepts correct parameters
            $this->assert_true(
                function_exists('rbf_send_ga4_measurement_protocol'),
                "Measurement protocol function should exist"
            );
            
            // Test with no config (should return error)
            $result = rbf_send_ga4_measurement_protocol($event_name, $params, $session_id, $event_id);
            
            $this->assert_true(
                isset($result['success']) && $result['success'] === false,
                "Should return error when GA4 not configured"
            );
            
            $this->test_results[$test_name] = ['status' => 'PASS', 'message' => 'Measurement protocol structure correct'];
            
        } catch (Exception $e) {
            $this->test_results[$test_name] = ['status' => 'FAIL', 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Test JavaScript integration points
     */
    private function test_javascript_integration() {
        $test_name = "JavaScript Integration";
        
        try {
            // Test that JavaScript file exists
            $js_file = RBF_PLUGIN_DIR . 'assets/js/ga4-funnel-tracking.js';
            $this->assert_true(
                file_exists($js_file),
                "GA4 funnel tracking JavaScript file should exist"
            );
            
            // Test that enqueue function exists
            $this->assert_true(
                function_exists('rbf_enqueue_ga4_funnel_tracking'),
                "GA4 funnel tracking enqueue function should exist"
            );
            
            // Test that AJAX handler exists  
            $this->assert_true(
                function_exists('rbf_ajax_track_ga4_event'),
                "GA4 event tracking AJAX handler should exist"
            );
            
            $this->assert_true(
                function_exists('rbf_ajax_get_booking_completion_data'),
                "Booking completion data AJAX handler should exist"
            );
            
            $this->test_results[$test_name] = ['status' => 'PASS', 'message' => 'JavaScript integration points working correctly'];
            
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
            echo "<p style='color: #f44336;'><strong>Note:</strong> Some tests failed. Please review the implementation and fix any issues before proceeding.</p>\n";
        } else {
            echo "<p style='color: #4CAF50;'><strong>All tests passed!</strong> GA4 funnel tracking is working correctly.</p>\n";
        }
    }
}

/**
 * Run GA4 funnel tracking tests if requested
 */
if (isset($_GET['rbf_test_ga4_funnel']) && ((function_exists('rbf_user_can_manage_settings') && rbf_user_can_manage_settings()) || (!function_exists('rbf_user_can_manage_settings') && function_exists('current_user_can') && current_user_can('manage_options')))) {
    add_action('admin_init', function() {
        if (!wp_verify_nonce($_GET['nonce'] ?? '', 'rbf_test_ga4_funnel')) {
            wp_die('Invalid nonce');
        }
        
        echo "<div style='max-width: 1200px; margin: 20px; font-family: Arial, sans-serif;'>\n";
        
        $tests = new RBF_GA4_Funnel_Tests();
        $tests->run_all_tests();
        
        echo "<div style='margin-top: 30px; padding: 15px; background: #f9f9f9; border-radius: 5px;'>\n";
        echo "<h3>Manual Testing Instructions</h3>\n";
        echo "<ol>\n";
        echo "<li><strong>Frontend Testing:</strong> Visit a page with the booking form and open browser developer tools.</li>\n";
        echo "<li><strong>Check Console:</strong> Look for 'RBF GA4 Funnel:' messages in the console.</li>\n";
        echo "<li><strong>Test Form Interaction:</strong> Go through the booking process and verify events are tracked.</li>\n";
        echo "<li><strong>Verify GA4:</strong> Check Google Analytics 4 DebugView (if measurement ID is configured).</li>\n";
        echo "<li><strong>Test Error Tracking:</strong> Submit invalid form data to verify error events.</li>\n";
        echo "</ol>\n";
        echo "</div>\n";
        
        echo "</div>\n";
        exit;
    });
}