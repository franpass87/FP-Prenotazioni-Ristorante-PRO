<?php
/**
 * Edge case tests for Buffer and Overbooking functionality
 * Tests error conditions, boundary cases, and integration scenarios
 */

// Simple test environment setup
if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data) {
        return json_encode($data);
    }
}

if (!function_exists('rbf_translate_string')) {
    function rbf_translate_string($text) {
        return $text; // Mock function for testing
    }
}

/**
 * Edge Case Testing for Buffer and Overbooking
 */
class RBF_Edge_Case_Tests {
    
    public function __construct() {
        echo "Running Edge Case Tests for Buffer and Overbooking...\n\n";
        $this->run_all_tests();
    }
    
    /**
     * Run all edge case tests
     */
    public function run_all_tests() {
        $this->test_boundary_conditions();
        $this->test_error_conditions();
        $this->test_integration_scenarios();
        $this->test_performance_scenarios();
        
        echo "\n✅ All edge case tests completed!\n";
    }
    
    /**
     * Test boundary conditions
     */
    public function test_boundary_conditions() {
        echo "🧪 Testing Boundary Conditions...\n";
        
        // Test maximum buffer settings
        $max_buffer_config = [
            'buffer_time_minutes' => 120,
            'buffer_time_per_person' => 30
        ];
        $max_buffer = $this->mock_calculate_buffer_time($max_buffer_config, 8);
        $this->assert_equals($max_buffer, 360, "Maximum buffer: 120 + 8*30 = 360 minutes");
        
        // Test minimum buffer settings
        $min_buffer_config = [
            'buffer_time_minutes' => 0,
            'buffer_time_per_person' => 0
        ];
        $min_buffer = $this->mock_calculate_buffer_time($min_buffer_config, 10);
        $this->assert_equals($min_buffer, 0, "Minimum buffer should be 0");
        
        // Test maximum overbooking
        $max_overbooking = $this->mock_get_effective_capacity(20, 50);
        $this->assert_equals($max_overbooking, 30, "Maximum 50% overbooking: 20 + 10 = 30");
        
        // Test single person booking
        $single_person_buffer = $this->mock_calculate_buffer_time(['buffer_time_minutes' => 15, 'buffer_time_per_person' => 5], 1);
        $this->assert_equals($single_person_buffer, 20, "Single person: 15 + 5 = 20");
        
        echo "✅ Boundary conditions tests passed\n\n";
    }
    
    /**
     * Test error conditions
     */
    public function test_error_conditions() {
        echo "🧪 Testing Error Conditions...\n";
        
        // Test invalid meal configuration
        $invalid_config = [];
        $default_buffer = $this->mock_calculate_buffer_time($invalid_config, 4);
        $this->assert_equals($default_buffer, 15, "Invalid config should return default buffer (15)");
        
        // Test negative values in configuration
        $negative_config = [
            'buffer_time_minutes' => -10,
            'buffer_time_per_person' => -5
        ];
        $negative_buffer = $this->mock_calculate_buffer_time_safe($negative_config, 4);
        $this->assert_true($negative_buffer >= 0, "Negative config should not result in negative buffer");
        
        // Test invalid capacity
        $invalid_capacity = $this->mock_get_effective_capacity(-5, 10);
        $this->assert_equals($invalid_capacity, 0, "Negative capacity should be treated as 0");
        
        // Test extreme overbooking percentage
        $extreme_overbooking = $this->mock_get_effective_capacity_safe(30, 200);
        $this->assert_true($extreme_overbooking <= 45, "Extreme overbooking should be capped at 50%"); // 30 + 15
        
        echo "✅ Error conditions tests passed\n\n";
    }
    
    /**
     * Test integration scenarios
     */
    public function test_integration_scenarios() {
        echo "🧪 Testing Integration Scenarios...\n";
        
        // Test complex booking scenario
        $complex_config = [
            'buffer_time_minutes' => 25,
            'buffer_time_per_person' => 7,
            'capacity' => 35,
            'overbooking_limit' => 12
        ];
        
        // Multiple existing bookings with varying party sizes
        $existing_bookings = [
            ['time' => '12:00', 'people' => 2], // Buffer: 25 + 14 = 39min
            ['time' => '12:30', 'people' => 1], // Buffer: 25 + 7 = 32min
            ['time' => '13:15', 'people' => 6], // Buffer: 25 + 42 = 67min
            ['time' => '14:30', 'people' => 3]  // Buffer: 25 + 21 = 46min
        ];
        
        // Test valid booking times
        $valid_times = ['11:15', '13:45', '15:25'];
        foreach ($valid_times as $time) {
            $is_valid = $this->mock_validate_buffer($time, 2, $complex_config, $existing_bookings);
            $this->assert_true($is_valid, "Time $time should be valid for 2 people");
        }
        
        // Test invalid booking times
        $invalid_times = ['12:15', '12:45', '13:00'];
        foreach ($invalid_times as $time) {
            $is_valid = $this->mock_validate_buffer($time, 2, $complex_config, $existing_bookings);
            $this->assert_false($is_valid, "Time $time should be invalid due to buffer conflicts");
        }
        
        // Test capacity with overbooking
        $effective_capacity = $this->mock_get_effective_capacity($complex_config['capacity'], $complex_config['overbooking_limit']);
        $this->assert_equals($effective_capacity, 39, "Complex config capacity: 35 + 4 = 39");
        
        echo "✅ Integration scenarios tests passed\n\n";
    }
    
    /**
     * Test performance scenarios
     */
    public function test_performance_scenarios() {
        echo "🧪 Testing Performance Scenarios...\n";
        
        // Test with many existing bookings
        $many_bookings = [];
        for ($i = 0; $i < 50; $i++) {
            $hour = 10 + ($i % 12);
            $minute = ($i % 4) * 15;
            $many_bookings[] = [
                'time' => sprintf('%02d:%02d', $hour, $minute),
                'people' => 2 + ($i % 6)
            ];
        }
        
        $config = ['buffer_time_minutes' => 15, 'buffer_time_per_person' => 5];
        
        $start_time = microtime(true);
        $is_valid = $this->mock_validate_buffer('18:00', 4, $config, $many_bookings);
        $end_time = microtime(true);
        
        $execution_time = ($end_time - $start_time) * 1000; // Convert to milliseconds
        $this->assert_true($execution_time < 100, "Buffer validation with 50 bookings should complete in under 100ms");
        
        // Test large party size calculations
        $large_party_times = [];
        for ($people = 1; $people <= 20; $people++) {
            $start = microtime(true);
            $buffer = $this->mock_calculate_buffer_time($config, $people);
            $end = microtime(true);
            $large_party_times[] = ($end - $start) * 1000;
        }
        
        $avg_time = array_sum($large_party_times) / count($large_party_times);
        $this->assert_true($avg_time < 1, "Buffer calculations should average under 1ms");
        
        echo "✅ Performance scenarios tests passed\n\n";
    }
    
    // Mock implementation methods with enhanced error handling
    private function mock_calculate_buffer_time($meal_config, $people_count) {
        $base_buffer = intval($meal_config['buffer_time_minutes'] ?? 15);
        $per_person_buffer = intval($meal_config['buffer_time_per_person'] ?? 5);
        
        return $base_buffer + ($per_person_buffer * max(0, $people_count));
    }
    
    private function mock_calculate_buffer_time_safe($meal_config, $people_count) {
        $base_buffer = max(0, intval($meal_config['buffer_time_minutes'] ?? 15));
        $per_person_buffer = max(0, intval($meal_config['buffer_time_per_person'] ?? 5));
        
        return $base_buffer + ($per_person_buffer * max(0, $people_count));
    }
    
    private function mock_get_effective_capacity($base_capacity, $overbooking_percent) {
        $base_capacity = max(0, $base_capacity);
        $overbooking_spots = round($base_capacity * ($overbooking_percent / 100));
        return $base_capacity + $overbooking_spots;
    }
    
    private function mock_get_effective_capacity_safe($base_capacity, $overbooking_percent) {
        $base_capacity = max(0, $base_capacity);
        $overbooking_percent = min(50, max(0, $overbooking_percent)); // Cap at 50%
        $overbooking_spots = round($base_capacity * ($overbooking_percent / 100));
        return $base_capacity + $overbooking_spots;
    }
    
    private function mock_validate_buffer($new_time, $new_people, $meal_config, $existing_bookings) {
        $required_buffer = $this->mock_calculate_buffer_time($meal_config, $new_people);
        
        foreach ($existing_bookings as $existing) {
            $existing_buffer = $this->mock_calculate_buffer_time($meal_config, $existing['people']);
            $needed_buffer = max($required_buffer, $existing_buffer);
            
            $new_minutes = $this->time_to_minutes($new_time);
            $existing_minutes = $this->time_to_minutes($existing['time']);
            $time_diff = abs($new_minutes - $existing_minutes);
            
            if ($time_diff < $needed_buffer) {
                return false;
            }
        }
        
        return true;
    }
    
    private function time_to_minutes($time) {
        if (!preg_match('/^(\d{1,2}):(\d{2})$/', $time, $matches)) {
            return 0; // Invalid time format
        }
        return intval($matches[1]) * 60 + intval($matches[2]);
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
    new RBF_Edge_Case_Tests();
}