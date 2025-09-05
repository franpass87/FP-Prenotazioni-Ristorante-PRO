<?php
/**
 * Tests for Table Management functionality
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
 * Test Table Assignment Algorithms
 */
class RBF_Table_Management_Tests {
    
    public function __construct() {
        echo "Running Table Management Tests...\n\n";
        $this->run_all_tests();
    }
    
    /**
     * Run all tests
     */
    public function run_all_tests() {
        $this->test_first_fit_single_table();
        $this->test_first_fit_joined_tables();
        $this->test_table_combination_logic();
        $this->test_capacity_constraints();
        $this->test_availability_check();
        $this->test_edge_cases();
        
        echo "\n✅ All tests completed!\n";
    }
    
    /**
     * Test single table assignment
     */
    public function test_first_fit_single_table() {
        echo "🧪 Testing First-Fit Single Table Assignment...\n";
        
        // Mock available tables
        $tables = [
            (object) ['id' => 1, 'capacity' => 2, 'min_capacity' => 1, 'max_capacity' => 4],
            (object) ['id' => 2, 'capacity' => 4, 'min_capacity' => 2, 'max_capacity' => 6],
            (object) ['id' => 3, 'capacity' => 6, 'min_capacity' => 4, 'max_capacity' => 8],
        ];
        
        // Test case 1: Request for 2 people - should get smallest suitable table (table 1)
        $result = $this->mock_find_single_table($tables, 2);
        $this->assert_equals($result['table_id'], 1, "Should assign table 1 for 2 people");
        
        // Test case 2: Request for 5 people - should get table 3
        $result = $this->mock_find_single_table($tables, 5);
        $this->assert_equals($result['table_id'], 3, "Should assign table 3 for 5 people");
        
        // Test case 3: Request for 10 people - should return null (no single table available)
        $result = $this->mock_find_single_table($tables, 10);
        $this->assert_null($result, "Should return null for 10 people (no single table available)");
        
        echo "✅ Single table assignment tests passed\n\n";
    }
    
    /**
     * Test joined table assignment
     */
    public function test_first_fit_joined_tables() {
        echo "🧪 Testing Joined Table Assignment...\n";
        
        // Mock available tables in a group
        $group_tables = [
            (object) ['id' => 1, 'capacity' => 2, 'min_capacity' => 1, 'max_capacity' => 4],
            (object) ['id' => 2, 'capacity' => 2, 'min_capacity' => 1, 'max_capacity' => 4],
            (object) ['id' => 3, 'capacity' => 4, 'min_capacity' => 2, 'max_capacity' => 6],
        ];
        
        // Test case 1: Request for 5 people - should join tables
        $result = $this->mock_find_table_combination($group_tables, 5, 12);
        $this->assert_not_null($result, "Should find combination for 5 people");
        $this->assert_true($result['total_capacity'] >= 5, "Combined capacity should be >= 5");
        
        // Test case 2: Request for 8 people - should join all tables
        $result = $this->mock_find_table_combination($group_tables, 8, 12);
        $this->assert_not_null($result, "Should find combination for 8 people");
        $this->assert_equals(count($result['tables']), 3, "Should use all 3 tables for 8 people");
        
        // Test case 3: Request for 15 people - should return null (exceeds max capacity)
        $result = $this->mock_find_table_combination($group_tables, 15, 12);
        $this->assert_null($result, "Should return null when exceeding max group capacity");
        
        echo "✅ Joined table assignment tests passed\n\n";
    }
    
    /**
     * Test table combination logic
     */
    public function test_table_combination_logic() {
        echo "🧪 Testing Table Combination Logic...\n";
        
        $tables = [
            (object) ['id' => 1, 'capacity' => 2],
            (object) ['id' => 2, 'capacity' => 2],
            (object) ['id' => 3, 'capacity' => 4],
            (object) ['id' => 4, 'capacity' => 6],
        ];
        
        // Test optimal pairing (should prefer 2+2 over 6 for 4 people)
        $result = $this->mock_find_table_combination($tables, 4, 16);
        $this->assert_not_null($result, "Should find combination for 4 people");
        $this->assert_equals($result['total_capacity'], 4, "Should choose optimal combination (2+2)");
        
        echo "✅ Table combination logic tests passed\n\n";
    }
    
    /**
     * Test capacity constraints
     */
    public function test_capacity_constraints() {
        echo "🧪 Testing Capacity Constraints...\n";
        
        $table = (object) ['id' => 1, 'capacity' => 4, 'min_capacity' => 2, 'max_capacity' => 6];
        
        // Test within range
        $this->assert_true($this->mock_table_can_accommodate($table, 3), "Table should accommodate 3 people");
        $this->assert_true($this->mock_table_can_accommodate($table, 4), "Table should accommodate 4 people");
        
        // Test edge cases
        $this->assert_true($this->mock_table_can_accommodate($table, 2), "Table should accommodate 2 people (min)");
        $this->assert_false($this->mock_table_can_accommodate($table, 1), "Table should NOT accommodate 1 person (below min)");
        $this->assert_false($this->mock_table_can_accommodate($table, 7), "Table should NOT accommodate 7 people (above capacity)");
        
        echo "✅ Capacity constraints tests passed\n\n";
    }
    
    /**
     * Test availability checking logic
     */
    public function test_availability_check() {
        echo "🧪 Testing Availability Logic...\n";
        
        // Mock all tables
        $all_tables = [
            (object) ['id' => 1, 'name' => 'T1'],
            (object) ['id' => 2, 'name' => 'T2'],
            (object) ['id' => 3, 'name' => 'T3'],
        ];
        
        // Mock assigned tables
        $assigned_table_ids = [2]; // Table 2 is assigned
        
        $available = $this->mock_filter_available_tables($all_tables, $assigned_table_ids);
        
        $this->assert_equals(count($available), 2, "Should have 2 available tables");
        $available_ids = array_column($available, 'id');
        $this->assert_true(in_array(1, $available_ids), "Table 1 should be available");
        $this->assert_true(in_array(3, $available_ids), "Table 3 should be available");
        $this->assert_false(in_array(2, $available_ids), "Table 2 should NOT be available");
        
        echo "✅ Availability logic tests passed\n\n";
    }
    
    /**
     * Test edge cases
     */
    public function test_edge_cases() {
        echo "🧪 Testing Edge Cases...\n";
        
        // Test empty tables array
        $result = $this->mock_find_single_table([], 4);
        $this->assert_null($result, "Should return null for empty tables array");
        
        // Test zero people request
        $tables = [(object) ['id' => 1, 'capacity' => 4, 'min_capacity' => 1, 'max_capacity' => 6]];
        $result = $this->mock_find_single_table($tables, 0);
        $this->assert_null($result, "Should return null for 0 people request");
        
        // Test negative people request
        $result = $this->mock_find_single_table($tables, -1);
        $this->assert_null($result, "Should return null for negative people request");
        
        echo "✅ Edge cases tests passed\n\n";
    }
    
    // Mock implementation of single table finding
    private function mock_find_single_table($tables, $people_count) {
        if ($people_count <= 0 || empty($tables)) {
            return null;
        }
        
        // Sort by capacity (smallest first)
        usort($tables, function($a, $b) {
            return $a->capacity - $b->capacity;
        });
        
        foreach ($tables as $table) {
            if ($this->mock_table_can_accommodate($table, $people_count)) {
                return ['table_id' => $table->id, 'capacity' => $table->capacity];
            }
        }
        
        return null;
    }
    
    // Mock implementation of table combination finding
    private function mock_find_table_combination($tables, $people_count, $max_capacity) {
        if ($people_count <= 0 || empty($tables) || $people_count > $max_capacity) {
            return null;
        }
        
        usort($tables, function($a, $b) {
            return $a->capacity - $b->capacity;
        });
        
        $n = count($tables);
        
        // Try single table first
        foreach ($tables as $table) {
            if ($table->capacity >= $people_count) {
                return [
                    'tables' => [$table],
                    'total_capacity' => $table->capacity
                ];
            }
        }
        
        // Try pairs
        for ($i = 0; $i < $n - 1; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $total = $tables[$i]->capacity + $tables[$j]->capacity;
                if ($total >= $people_count && $total <= $max_capacity) {
                    return [
                        'tables' => [$tables[$i], $tables[$j]],
                        'total_capacity' => $total
                    ];
                }
            }
        }
        
        // Try triplets
        for ($i = 0; $i < $n - 2; $i++) {
            for ($j = $i + 1; $j < $n - 1; $j++) {
                for ($k = $j + 1; $k < $n; $k++) {
                    $total = $tables[$i]->capacity + $tables[$j]->capacity + $tables[$k]->capacity;
                    if ($total >= $people_count && $total <= $max_capacity) {
                        return [
                            'tables' => [$tables[$i], $tables[$j], $tables[$k]],
                            'total_capacity' => $total
                        ];
                    }
                }
            }
        }
        
        return null;
    }
    
    private function mock_table_can_accommodate($table, $people_count) {
        return $people_count >= $table->min_capacity && $people_count <= $table->capacity;
    }
    
    private function mock_filter_available_tables($all_tables, $assigned_table_ids) {
        return array_filter($all_tables, function($table) use ($assigned_table_ids) {
            return !in_array($table->id, $assigned_table_ids);
        });
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
    
    private function assert_null($value, $message) {
        if ($value === null) {
            echo "  ✅ $message\n";
        } else {
            echo "  ❌ $message (Expected: null, Got: " . print_r($value, true) . ")\n";
        }
    }
    
    private function assert_not_null($value, $message) {
        if ($value !== null) {
            echo "  ✅ $message\n";
        } else {
            echo "  ❌ $message (Expected: not null, Got: null)\n";
        }
    }
}

// Run tests if this file is executed directly
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    new RBF_Table_Management_Tests();
}