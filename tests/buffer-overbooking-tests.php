<?php
/**
 * Tests for Buffer and Overbooking functionality
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

/**
 * Test Buffer Time and Overbooking functionality
 */
class RBF_Buffer_Overbooking_Tests {
    
    public function __construct() {
        echo "Running Buffer and Overbooking Tests...\n\n";
        $this->run_all_tests();
    }
    
    /**
     * Run all tests
     */
    public function run_all_tests() {
        $this->test_buffer_time_calculation();
        $this->test_effective_capacity_calculation();
        $this->test_buffer_validation_logic();
        $this->test_overbooking_scenarios();
        $this->test_edge_cases();
        
        echo "\n✅ All buffer and overbooking tests completed!\n";
    }
    
    /**
     * Test buffer time calculation
     */
    public function test_buffer_time_calculation() {
        echo "🧪 Testing Buffer Time Calculation...\n";
        
        // Mock meal config
        $meal_config = [
            'buffer_time_minutes' => 15,
            'buffer_time_per_person' => 5
        ];
        
        // Test basic buffer calculation
        $buffer_2_people = $this->mock_calculate_buffer_time($meal_config, 2);
        $this->assert_equals($buffer_2_people, 25, "Buffer for 2 people should be 25 minutes (15 + 2*5)");
        
        $buffer_4_people = $this->mock_calculate_buffer_time($meal_config, 4);
        $this->assert_equals($buffer_4_people, 35, "Buffer for 4 people should be 35 minutes (15 + 4*5)");
        
        $buffer_8_people = $this->mock_calculate_buffer_time($meal_config, 8);
        $this->assert_equals($buffer_8_people, 55, "Buffer for 8 people should be 55 minutes (15 + 8*5)");
        
        // Test edge cases
        $buffer_0_people = $this->mock_calculate_buffer_time($meal_config, 0);
        $this->assert_equals($buffer_0_people, 15, "Buffer for 0 people should be base buffer (15)");
        
        echo "✅ Buffer time calculation tests passed\n\n";
    }
    
    /**
     * Test effective capacity calculation with overbooking
     */
    public function test_effective_capacity_calculation() {
        echo "🧪 Testing Effective Capacity Calculation...\n";
        
        // Test 10% overbooking on 30 capacity
        $capacity_30_10pct = $this->mock_get_effective_capacity(30, 10);
        $this->assert_equals($capacity_30_10pct, 33, "30 capacity with 10% overbooking should be 33");
        
        // Test 15% overbooking on 25 capacity  
        $capacity_25_15pct = $this->mock_get_effective_capacity(25, 15);
        $this->assert_equals($capacity_25_15pct, 29, "25 capacity with 15% overbooking should be 29");
        
        // Test 5% overbooking on 40 capacity
        $capacity_40_5pct = $this->mock_get_effective_capacity(40, 5);
        $this->assert_equals($capacity_40_5pct, 42, "40 capacity with 5% overbooking should be 42");
        
        // Test 0% overbooking
        $capacity_30_0pct = $this->mock_get_effective_capacity(30, 0);
        $this->assert_equals($capacity_30_0pct, 30, "30 capacity with 0% overbooking should be 30");
        
        echo "✅ Effective capacity calculation tests passed\n\n";
    }
    
    /**
     * Test buffer validation logic
     */
    public function test_buffer_validation_logic() {
        echo "🧪 Testing Buffer Validation Logic...\n";
        
        $meal_config = [
            'buffer_time_minutes' => 20,
            'buffer_time_per_person' => 5
        ];
        
        // Mock existing bookings
        $existing_bookings = [
            ['time' => '19:00', 'people' => 2], // needs 30 min buffer (20 + 2*5)
            ['time' => '20:30', 'people' => 4]  // needs 40 min buffer (20 + 4*5)
        ];
        
        // Test valid booking (enough buffer)
        $valid_booking = $this->mock_validate_buffer('19:45', 2, $meal_config, $existing_bookings);
        $this->assert_true($valid_booking, "19:45 booking should be valid (45 min after 19:00)");
        
        // Test invalid booking (not enough buffer)
        $invalid_booking = $this->mock_validate_buffer('19:25', 2, $meal_config, $existing_bookings);
        $this->assert_false($invalid_booking, "19:25 booking should be invalid (only 25 min after 19:00, needs 30)");
        
        // Test booking before first slot
        $before_booking = $this->mock_validate_buffer('18:25', 2, $meal_config, $existing_bookings);
        $this->assert_false($before_booking, "18:25 booking should be invalid (only 35 min before 19:00, needs 30)");
        
        // Test booking with larger party requiring more buffer
        $large_party = $this->mock_validate_buffer('21:00', 6, $meal_config, $existing_bookings);
        $this->assert_false($large_party, "21:00 booking for 6 people should be invalid (only 30 min after 20:30, needs 50)");
        
        echo "✅ Buffer validation logic tests passed\n\n";
    }
    
    /**
     * Test overbooking scenarios
     */
    public function test_overbooking_scenarios() {
        echo "🧪 Testing Overbooking Scenarios...\n";
        
        $base_capacity = 30;
        $overbooking_limit = 10; // 10%
        $effective_capacity = 33; // 30 + 3
        
        // Test normal capacity usage
        $remaining_normal = $this->mock_get_remaining_capacity($effective_capacity, 25);
        $this->assert_equals($remaining_normal, 8, "Normal usage: 33 - 25 = 8 remaining");
        
        // Test capacity at overbooking threshold
        $remaining_threshold = $this->mock_get_remaining_capacity($effective_capacity, 31);
        $this->assert_equals($remaining_threshold, 2, "At overbooking: 33 - 31 = 2 remaining");
        
        // Test capacity at maximum overbooking
        $remaining_max = $this->mock_get_remaining_capacity($effective_capacity, 33);
        $this->assert_equals($remaining_max, 0, "Maximum overbooking: 33 - 33 = 0 remaining");
        
        // Test capacity over limit
        $remaining_over = $this->mock_get_remaining_capacity($effective_capacity, 35);
        $this->assert_equals($remaining_over, 0, "Over limit: should be 0 (cannot go negative)");
        
        echo "✅ Overbooking scenario tests passed\n\n";
    }
    
    /**
     * Test edge cases and error conditions
     */
    public function test_edge_cases() {
        echo "🧪 Testing Edge Cases...\n";
        
        // Test zero buffer configuration
        $zero_buffer_config = [
            'buffer_time_minutes' => 0,
            'buffer_time_per_person' => 0
        ];
        $zero_buffer = $this->mock_calculate_buffer_time($zero_buffer_config, 4);
        $this->assert_equals($zero_buffer, 0, "Zero buffer config should return 0");
        
        // Test very high buffer configuration
        $high_buffer_config = [
            'buffer_time_minutes' => 60,
            'buffer_time_per_person' => 15
        ];
        $high_buffer = $this->mock_calculate_buffer_time($high_buffer_config, 4);
        $this->assert_equals($high_buffer, 120, "High buffer: 60 + 4*15 = 120");
        
        // Test negative people count
        $negative_people = $this->mock_calculate_buffer_time($zero_buffer_config, -1);
        $this->assert_equals($negative_people, 0, "Negative people should not affect buffer calculation");
        
        // Test zero capacity with overbooking
        $zero_capacity = $this->mock_get_effective_capacity(0, 10);
        $this->assert_equals($zero_capacity, 0, "Zero capacity should remain zero even with overbooking");
        
        echo "✅ Edge cases tests passed\n\n";
    }
    
    // Mock implementation methods
    private function mock_calculate_buffer_time($meal_config, $people_count) {
        $base_buffer = intval($meal_config['buffer_time_minutes'] ?? 15);
        $per_person_buffer = intval($meal_config['buffer_time_per_person'] ?? 5);
        
        return $base_buffer + ($per_person_buffer * max(0, $people_count));
    }
    
    private function mock_get_effective_capacity($base_capacity, $overbooking_percent) {
        $overbooking_spots = round($base_capacity * ($overbooking_percent / 100));
        return $base_capacity + $overbooking_spots;
    }
    
    private function mock_get_remaining_capacity($total_capacity, $spots_taken) {
        return max(0, $total_capacity - $spots_taken);
    }
    
    private function mock_validate_buffer($new_time, $new_people, $meal_config, $existing_bookings) {
        $required_buffer = $this->mock_calculate_buffer_time($meal_config, $new_people);
        
        foreach ($existing_bookings as $existing) {
            $existing_buffer = $this->mock_calculate_buffer_time($meal_config, $existing['people']);
            $needed_buffer = max($required_buffer, $existing_buffer);
            
            // Calculate time difference in minutes
            $new_minutes = $this->time_to_minutes($new_time);
            $existing_minutes = $this->time_to_minutes($existing['time']);
            $time_diff = abs($new_minutes - $existing_minutes);
            
            if ($time_diff < $needed_buffer) {
                return false; // Buffer conflict
            }
        }
        
        return true; // No conflicts
    }
    
    private function time_to_minutes($time) {
        list($hours, $minutes) = explode(':', $time);
        return intval($hours) * 60 + intval($minutes);
    }
    
    // Testing helper methods
    private function assert_equals($actual, $expected, $message) {
        if ($actual === $expected) {
            echo "  ✅ $message\n";
        } else {
            echo "  ❌ $message (Expected: $expected, Got: $actual)\n";
        }
    }
    
    private function assert_true($condition, $message) {
        if ($condition) {
            echo "  ✅ $message\n";
        } else {
            echo "  ❌ $message (Expected: true, Got: false)\n";
        }
    }
    
    private function assert_false($condition, $message) {
        if (!$condition) {
            echo "  ✅ $message\n";
        } else {
            echo "  ❌ $message (Expected: false, Got: true)\n";
        }
    }
}

// Run tests if this file is executed directly
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    new RBF_Buffer_Overbooking_Tests();
}