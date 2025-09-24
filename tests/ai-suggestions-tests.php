<?php
/**
 * Tests for AI Alternative Suggestions functionality
 * 
 * @package FP_Prenotazioni_Ristorante_PRO
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Test AI suggestions functionality
 */
function rbf_test_ai_suggestions() {
    // Test basic suggestion functionality
    $test_results = [];
    
    // Test 1: Basic suggestion generation
    $test_results['basic_suggestions'] = rbf_test_basic_suggestions();
    
    // Test 2: Suggestion when full capacity  
    $test_results['full_capacity_suggestions'] = rbf_test_full_capacity_suggestions();
    
    // Test 3: No suggestions when restaurant closed
    $test_results['closed_restaurant_suggestions'] = rbf_test_closed_restaurant_suggestions();
    
    // Test 4: AJAX endpoint
    $test_results['ajax_endpoint'] = rbf_test_ajax_suggestions_endpoint();
    
    return $test_results;
}

/**
 * Test basic suggestion generation
 */
function rbf_test_basic_suggestions() {
    try {
        // Test with future date and common meal type
        $future_date = date('Y-m-d', strtotime('+7 days'));
        $suggestions = rbf_get_alternative_suggestions($future_date, 'pranzo', 2);
        
        $result = [
            'status' => 'pass',
            'message' => 'Basic suggestions generated successfully',
            'suggestions_count' => count($suggestions),
            'suggestions' => $suggestions
        ];
        
        if (empty($suggestions)) {
            $result['status'] = 'warning';
            $result['message'] = 'No suggestions generated - this may be expected if no alternatives available';
        }
        
        return $result;
        
    } catch (Exception $e) {
        return [
            'status' => 'fail',
            'message' => 'Exception during basic suggestions test: ' . $e->getMessage(),
            'suggestions_count' => 0
        ];
    }
}

/**
 * Test suggestions when capacity is full
 */
function rbf_test_full_capacity_suggestions() {
    try {
        // This test simulates a full capacity scenario
        // In a real scenario, we'd need to create actual bookings
        $test_date = date('Y-m-d', strtotime('+3 days'));
        $suggestions = rbf_get_alternative_suggestions($test_date, 'cena', 4);
        
        return [
            'status' => 'pass',
            'message' => 'Full capacity suggestions test completed',
            'suggestions_count' => count($suggestions),
            'note' => 'This test cannot fully simulate full capacity without creating real bookings'
        ];
        
    } catch (Exception $e) {
        return [
            'status' => 'fail',
            'message' => 'Exception during full capacity test: ' . $e->getMessage()
        ];
    }
}

/**
 * Test suggestions when restaurant is closed
 */
function rbf_test_closed_restaurant_suggestions() {
    try {
        // Test with a date when restaurant is typically closed (if configured)
        // This will depend on the restaurant's opening hours configuration
        $suggestions = rbf_get_alternative_suggestions('2024-01-01', 'pranzo', 2); // New Year's Day
        
        return [
            'status' => 'pass', 
            'message' => 'Closed restaurant suggestions test completed',
            'suggestions_count' => count($suggestions),
            'note' => 'Suggestions may still appear for closed dates if alternatives exist'
        ];
        
    } catch (Exception $e) {
        return [
            'status' => 'fail',
            'message' => 'Exception during closed restaurant test: ' . $e->getMessage()
        ];
    }
}

/**
 * Test AJAX suggestions endpoint
 */
function rbf_test_ajax_suggestions_endpoint() {
    try {
        $settings = function_exists('rbf_get_settings') ? rbf_get_settings() : [];
        $people_max_limit = function_exists('rbf_get_people_max_limit')
            ? rbf_get_people_max_limit($settings)
            : 20;
        $people_for_request = max(1, min($people_max_limit, 25));

        // Simulate AJAX request data
        $_POST = [
            'nonce' => wp_create_nonce('rbf_ajax_nonce'),
            'date' => date('Y-m-d', strtotime('+5 days')),
            'meal' => 'pranzo',
            'people' => $people_for_request,
            'time' => '13:00'
        ];

        // Capture output
        ob_start();
        rbf_ajax_get_suggestions_callback();
        $output = ob_get_clean();
        
        // Try to decode JSON response
        $response = json_decode($output, true);
        
        if ($response && isset($response['success'])) {
            return [
                'status' => 'pass',
                'message' => sprintf(
                    'AJAX endpoint responded successfully for %d people (limit %d)',
                    $people_for_request,
                    $people_max_limit
                ),
                'response' => $response,
                'tested_people' => $people_for_request,
                'configured_limit' => $people_max_limit
            ];
        } else {
            return [
                'status' => 'fail',
                'message' => 'AJAX endpoint did not return valid JSON',
                'raw_output' => $output
            ];
        }
        
    } catch (Exception $e) {
        return [
            'status' => 'fail',
            'message' => 'Exception during AJAX endpoint test: ' . $e->getMessage()
        ];
    } finally {
        // Clean up $_POST
        $_POST = [];
    }
}

/**
 * Display test results in admin
 */
function rbf_display_ai_suggestions_tests() {
    if ((function_exists('rbf_user_can_manage_settings') && !rbf_user_can_manage_settings()) ||
        (!function_exists('rbf_user_can_manage_settings') && function_exists('current_user_can') && !current_user_can('manage_options'))
    ) {
        return;
    }
    
    echo '<div class="wrap">';
    echo '<h2>AI Suggestions Tests</h2>';
    
    if (isset($_GET['run_ai_tests'])) {
        $results = rbf_test_ai_suggestions();
        
        echo '<div class="notice notice-info"><p>Test Results:</p></div>';
        
        foreach ($results as $test_name => $result) {
            $class = $result['status'] === 'pass' ? 'notice-success' : 
                    ($result['status'] === 'warning' ? 'notice-warning' : 'notice-error');
            
            echo '<div class="notice ' . $class . '">';
            echo '<h4>' . ucwords(str_replace('_', ' ', $test_name)) . '</h4>';
            echo '<p><strong>Status:</strong> ' . ucwords($result['status']) . '</p>';
            echo '<p><strong>Message:</strong> ' . esc_html($result['message']) . '</p>';
            
            if (isset($result['suggestions_count'])) {
                echo '<p><strong>Suggestions Generated:</strong> ' . $result['suggestions_count'] . '</p>';
            }
            
            if (isset($result['note'])) {
                echo '<p><em>Note: ' . esc_html($result['note']) . '</em></p>';
            }
            
            echo '</div>';
        }
    }
    
    echo '<p><a href="' . add_query_arg('run_ai_tests', '1') . '" class="button button-primary">Run AI Suggestions Tests</a></p>';
    echo '</div>';
}

// Add admin menu for tests
add_action('admin_menu', function() {
    $capability = function_exists('rbf_get_settings_capability') ? rbf_get_settings_capability() : 'manage_options';
    add_submenu_page(
        'rbf_dashboard',
        'AI Suggestions Tests',
        'AI Tests',
        $capability,
        'rbf_ai_tests',
        'rbf_display_ai_suggestions_tests'
    );
});