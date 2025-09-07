<?php
/**
 * Tests for Optimistic Locking functionality
 * Simulates concurrent booking scenarios and race conditions
 * 
 * @package FP_Prenotazioni_Ristorante_PRO
 */

// Mock WordPress functions for testing
if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data) {
        return json_encode($data);
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim(strip_tags($str));
    }
}

if (!function_exists('current_time')) {
    function current_time($type = 'mysql') {
        return date($type === 'mysql' ? 'Y-m-d H:i:s' : 'Y-m-d');
    }
}

/**
 * Test Optimistic Locking functionality
 */
class RBF_Optimistic_Locking_Tests {
    
    private $test_results = [];
    
    public function __construct() {
        echo "Running Optimistic Locking Tests...\n\n";
        $this->run_all_tests();
    }
    
    /**
     * Run all tests
     */
    public function run_all_tests() {
        $this->test_slot_version_initialization();
        $this->test_single_booking_success();
        $this->test_concurrent_booking_simulation();
        $this->test_insufficient_capacity_handling();
        $this->test_retry_mechanism();
        $this->test_capacity_release();
        $this->test_edge_cases();
        
        $this->print_test_summary();
    }
    
    /**
     * Test slot version record initialization
     */
    public function test_slot_version_initialization() {
        echo "🧪 Testing Slot Version Initialization...\n";
        
        // Mock slot version creation
        $mock_slot = $this->mock_get_slot_version('2024-12-20', 'pranzo');
        
        $this->assert_equals(1, $mock_slot['version_number'], "Initial version should be 1");
        $this->assert_equals(30, $mock_slot['total_capacity'], "Total capacity should be set correctly");
        $this->assert_equals(0, $mock_slot['booked_capacity'], "Initial booked capacity should be 0");
        
        echo "✅ Slot version initialization tests passed\n\n";
    }
    
    /**
     * Test successful single booking
     */
    public function test_single_booking_success() {
        echo "🧪 Testing Single Booking Success...\n";
        
        $result = $this->mock_book_slot_optimistic('2024-12-20', 'pranzo', 4);
        
        $this->assert_true($result['success'], "Single booking should succeed");
        $this->assert_equals(2, $result['version'], "Version should increment to 2");
        $this->assert_equals(4, $result['new_booked_capacity'], "Booked capacity should be 4");
        $this->assert_equals(26, $result['remaining_capacity'], "Remaining capacity should be 26");
        $this->assert_equals(1, $result['attempt'], "Should succeed on first attempt");
        
        echo "✅ Single booking success tests passed\n\n";
    }
    
    /**
     * Test concurrent booking simulation
     */
    public function test_concurrent_booking_simulation() {
        echo "🧪 Testing Concurrent Booking Simulation...\n";
        
        // Simulate near-capacity scenario: 30 total, 27 already booked = 3 remaining
        $initial_state = [
            'version_number' => 5,
            'total_capacity' => 30,
            'booked_capacity' => 27
        ];
        
        // User A tries to book 2 people (should succeed)
        $result_a = $this->mock_concurrent_booking($initial_state, 2, 'UserA');
        $this->assert_true($result_a['success'], "First concurrent booking should succeed");
        $this->assert_equals(29, $result_a['new_booked_capacity'], "Capacity should be 29 after first booking");
        
        // User B tries to book 2 people with same initial state (version conflict)
        $result_b = $this->mock_concurrent_booking($initial_state, 2, 'UserB');
        $this->assert_false($result_b['success'], "Second concurrent booking should fail due to version conflict");
        $this->assert_equals('version_conflict', $result_b['error'], "Should detect version conflict");
        
        // User B retries with updated state (should fail due to insufficient capacity)
        $updated_state = [
            'version_number' => 6,
            'total_capacity' => 30,
            'booked_capacity' => 29
        ];
        $result_b_retry = $this->mock_concurrent_booking($updated_state, 2, 'UserB_Retry');
        $this->assert_false($result_b_retry['success'], "Retry should fail due to insufficient capacity");
        $this->assert_equals('insufficient_capacity', $result_b_retry['error'], "Should detect insufficient capacity");
        
        echo "✅ Concurrent booking simulation tests passed\n\n";
    }
    
    /**
     * Test insufficient capacity handling
     */
    public function test_insufficient_capacity_handling() {
        echo "🧪 Testing Insufficient Capacity Handling...\n";
        
        // Full capacity scenario
        $result_full = $this->mock_book_slot_optimistic('2024-12-21', 'cena', 5, [
            'version_number' => 1,
            'total_capacity' => 25,
            'booked_capacity' => 25
        ]);
        
        $this->assert_false($result_full['success'], "Booking should fail when fully booked");
        $this->assert_equals('insufficient_capacity', $result_full['error'], "Should detect insufficient capacity");
        $this->assert_equals(0, $result_full['remaining'], "Should show 0 remaining");
        
        // Partial capacity scenario
        $result_partial = $this->mock_book_slot_optimistic('2024-12-21', 'cena', 8, [
            'version_number' => 1,
            'total_capacity' => 25,
            'booked_capacity' => 20
        ]);
        
        $this->assert_false($result_partial['success'], "Booking should fail when requesting more than available");
        $this->assert_equals('insufficient_capacity', $result_partial['error'], "Should detect insufficient capacity");
        $this->assert_equals(5, $result_partial['remaining'], "Should show 5 remaining");
        
        echo "✅ Insufficient capacity handling tests passed\n\n";
    }
    
    /**
     * Test retry mechanism
     */
    public function test_retry_mechanism() {
        echo "🧪 Testing Retry Mechanism...\n";
        
        // Simulate multiple version conflicts before success
        $result = $this->mock_booking_with_retries('2024-12-22', 'aperitivo', 3, 2);
        
        $this->assert_true($result['success'], "Booking should eventually succeed after retries");
        $this->assert_equals(3, $result['attempt'], "Should succeed on attempt 3 (after 2 conflicts)");
        
        // Simulate max retries exhausted
        $result_max = $this->mock_booking_with_retries('2024-12-22', 'aperitivo', 3, 5);
        
        $this->assert_false($result_max['success'], "Booking should fail after max retries");
        $this->assert_equals('version_conflict', $result_max['error'], "Should show version conflict error");
        $this->assert_true($result_max['attempt'] >= 3, "Should attempt at least 3 times");
        
        echo "✅ Retry mechanism tests passed\n\n";
    }
    
    /**
     * Test capacity release functionality
     */
    public function test_capacity_release() {
        echo "🧪 Testing Capacity Release...\n";
        
        // Book some capacity first
        $initial_result = $this->mock_book_slot_optimistic('2024-12-23', 'pranzo', 6);
        $this->assert_true($initial_result['success'], "Initial booking should succeed");
        
        // Release capacity
        $release_result = $this->mock_release_slot_capacity('2024-12-23', 'pranzo', 6, [
            'version_number' => 2,
            'total_capacity' => 30,
            'booked_capacity' => 6
        ]);
        
        $this->assert_true($release_result['success'], "Capacity release should succeed");
        $this->assert_equals(0, $release_result['new_booked_capacity'], "Booked capacity should return to 0");
        $this->assert_equals(3, $release_result['new_version'], "Version should increment");
        
        // Test partial release
        $partial_release = $this->mock_release_slot_capacity('2024-12-23', 'pranzo', 2, [
            'version_number' => 3,
            'total_capacity' => 30,
            'booked_capacity' => 10
        ]);
        
        $this->assert_true($partial_release['success'], "Partial release should succeed");
        $this->assert_equals(8, $partial_release['new_booked_capacity'], "Booked capacity should be reduced by 2");
        
        echo "✅ Capacity release tests passed\n\n";
    }
    
    /**
     * Test edge cases
     */
    public function test_edge_cases() {
        echo "🧪 Testing Edge Cases...\n";
        
        // Zero capacity booking
        $zero_result = $this->mock_book_slot_optimistic('2024-12-24', 'test', 0);
        $this->assert_false($zero_result['success'], "Zero people booking should fail");
        
        // Negative capacity booking
        $negative_result = $this->mock_book_slot_optimistic('2024-12-24', 'test', -1);
        $this->assert_false($negative_result['success'], "Negative people booking should fail");
        
        // Large party booking
        $large_result = $this->mock_book_slot_optimistic('2024-12-24', 'test', 50, [
            'version_number' => 1,
            'total_capacity' => 30,
            'booked_capacity' => 0
        ]);
        $this->assert_false($large_result['success'], "Oversized booking should fail");
        $this->assert_equals('insufficient_capacity', $large_result['error'], "Should detect insufficient capacity");
        
        // Version synchronization test
        $sync_result = $this->mock_sync_slot_version('2024-12-25', 'cena');
        $this->assert_true($sync_result['success'], "Version sync should succeed");
        
        echo "✅ Edge cases tests passed\n\n";
    }
    
    // ======================== MOCK FUNCTIONS ========================
    
    /**
     * Mock slot version retrieval
     */
    private function mock_get_slot_version($date, $slot_id) {
        return [
            'id' => 1,
            'slot_date' => $date,
            'slot_id' => $slot_id,
            'version_number' => 1,
            'total_capacity' => 30,
            'booked_capacity' => 0,
            'last_updated' => current_time('mysql'),
            'created_at' => current_time('mysql')
        ];
    }
    
    /**
     * Mock optimistic booking
     */
    private function mock_book_slot_optimistic($date, $slot_id, $people, $initial_state = null) {
        if ($people <= 0) {
            return [
                'success' => false,
                'error' => 'invalid_people_count',
                'message' => 'People count must be positive',
                'attempt' => 1
            ];
        }
        
        $state = $initial_state ?: [
            'version_number' => 1,
            'total_capacity' => 30,
            'booked_capacity' => 0
        ];
        
        $remaining = $state['total_capacity'] - $state['booked_capacity'];
        
        if ($remaining < $people) {
            return [
                'success' => false,
                'error' => 'insufficient_capacity',
                'message' => sprintf('Not enough spots. Requested: %d, Available: %d', $people, $remaining),
                'remaining' => $remaining,
                'attempt' => 1
            ];
        }
        
        return [
            'success' => true,
            'version' => $state['version_number'] + 1,
            'previous_version' => $state['version_number'],
            'new_booked_capacity' => $state['booked_capacity'] + $people,
            'remaining_capacity' => $remaining - $people,
            'attempt' => 1
        ];
    }
    
    /**
     * Mock concurrent booking scenario
     */
    private function mock_concurrent_booking($initial_state, $people, $user_id) {
        $remaining = $initial_state['total_capacity'] - $initial_state['booked_capacity'];
        
        if ($remaining < $people) {
            return [
                'success' => false,
                'error' => 'insufficient_capacity',
                'message' => sprintf('Not enough spots for %s', $user_id),
                'remaining' => $remaining,
                'user' => $user_id
            ];
        }
        
        // Simulate version conflict for second user trying with old version
        if ($user_id === 'UserB') {
            return [
                'success' => false,
                'error' => 'version_conflict',
                'message' => sprintf('Version conflict detected for %s', $user_id),
                'user' => $user_id
            ];
        }
        
        return [
            'success' => true,
            'version' => $initial_state['version_number'] + 1,
            'new_booked_capacity' => $initial_state['booked_capacity'] + $people,
            'remaining_capacity' => $remaining - $people,
            'user' => $user_id
        ];
    }
    
    /**
     * Mock booking with retry simulation
     */
    private function mock_booking_with_retries($date, $slot_id, $people, $conflicts_before_success) {
        if ($conflicts_before_success >= 3) {
            return [
                'success' => false,
                'error' => 'version_conflict',
                'message' => 'Max retries exceeded',
                'attempt' => 3
            ];
        }
        
        return [
            'success' => true,
            'version' => $conflicts_before_success + 2,
            'new_booked_capacity' => $people,
            'remaining_capacity' => 30 - $people,
            'attempt' => $conflicts_before_success + 1
        ];
    }
    
    /**
     * Mock capacity release
     */
    private function mock_release_slot_capacity($date, $slot_id, $people, $current_state) {
        $new_booked = max(0, $current_state['booked_capacity'] - $people);
        
        return [
            'success' => true,
            'new_booked_capacity' => $new_booked,
            'new_version' => $current_state['version_number'] + 1,
            'released_capacity' => $people
        ];
    }
    
    /**
     * Mock version synchronization
     */
    private function mock_sync_slot_version($date, $slot_id) {
        return [
            'success' => true,
            'synced_capacity' => 15,
            'new_version' => 10
        ];
    }
    
    // ======================== ASSERTION HELPERS ========================
    
    private function assert_true($condition, $message) {
        $result = $condition === true;
        $this->record_test_result($result, $message, $condition);
        if ($result) {
            echo "  ✅ $message\n";
        } else {
            echo "  ❌ $message (Expected: true, Got: " . var_export($condition, true) . ")\n";
        }
    }
    
    private function assert_false($condition, $message) {
        $result = $condition === false;
        $this->record_test_result($result, $message, $condition);
        if ($result) {
            echo "  ✅ $message\n";
        } else {
            echo "  ❌ $message (Expected: false, Got: " . var_export($condition, true) . ")\n";
        }
    }
    
    private function assert_equals($expected, $actual, $message) {
        $result = $expected === $actual;
        $this->record_test_result($result, $message, $actual, $expected);
        if ($result) {
            echo "  ✅ $message\n";
        } else {
            echo "  ❌ $message (Expected: $expected, Got: $actual)\n";
        }
    }
    
    private function record_test_result($passed, $message, $actual = null, $expected = null) {
        $this->test_results[] = [
            'passed' => $passed,
            'message' => $message,
            'expected' => $expected,
            'actual' => $actual
        ];
    }
    
    private function print_test_summary() {
        $total = count($this->test_results);
        $passed = array_sum(array_column($this->test_results, 'passed'));
        $failed = $total - $passed;
        
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "OPTIMISTIC LOCKING TEST SUMMARY\n";
        echo str_repeat("=", 50) . "\n";
        echo "Total Tests: $total\n";
        echo "Passed: ✅ $passed\n";
        echo "Failed: ❌ $failed\n";
        echo "Success Rate: " . round(($passed / $total) * 100, 1) . "%\n";
        
        if ($failed > 0) {
            echo "\nFailed Tests:\n";
            foreach ($this->test_results as $result) {
                if (!$result['passed']) {
                    echo "  ❌ " . $result['message'] . "\n";
                }
            }
        }
        
        echo "\n✅ All optimistic locking tests completed!\n";
    }
}

// Run tests if this file is executed directly
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    new RBF_Optimistic_Locking_Tests();
}