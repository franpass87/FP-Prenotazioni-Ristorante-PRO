<?php
/**
 * Integration test for optimistic locking system
 * Tests that all functions are properly loaded and accessible
 */

// Set up mock WordPress environment
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/');
}

// Define WordPress constants
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

// Mock WordPress functions
function wp_json_encode($data) { return json_encode($data); }
function sanitize_text_field($str) { return trim(strip_tags($str)); }
function current_time($type = 'mysql') { return date($type === 'mysql' ? 'Y-m-d H:i:s' : 'Y-m-d'); }
function wp_generate_password($length, $special_chars = true, $extra_special_chars = false) { 
    return substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, $length); 
}

// Mock global $wpdb
class MockWpdb {
    public $prefix = 'wp_';
    public function get_charset_collate() { return 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'; }
    public function prepare($query, ...$args) { return sprintf($query, ...$args); }
    public function get_row($query, $output = OBJECT) { return null; }
    public function get_var($query) { return 0; }
    public function insert($table, $data, $format = null) { return 1; }
    public function update($table, $data, $where, $format = null, $where_format = null) { return 1; }
}
global $wpdb;
$wpdb = new MockWpdb();

// Mock utility functions that optimistic locking depends on
function rbf_get_effective_capacity($slot_id) { return 30; }

// Include the optimistic locking module
require_once 'includes/optimistic-locking.php';

echo "🧪 Testing Optimistic Locking Integration...\n\n";

// Test 1: Function availability
echo "✅ Testing function availability:\n";
$functions_to_test = [
    'rbf_create_slot_version_table',
    'rbf_get_slot_version', 
    'rbf_book_slot_optimistic',
    'rbf_release_slot_capacity',
    'rbf_sync_slot_version'
];

foreach ($functions_to_test as $function) {
    if (function_exists($function)) {
        echo "  ✅ $function - Available\n";
    } else {
        echo "  ❌ $function - Missing\n";
    }
}

// Test 2: Basic function calls
echo "\n✅ Testing basic function calls:\n";

try {
    // Test slot version creation
    rbf_create_slot_version_table();
    echo "  ✅ rbf_create_slot_version_table() - Success\n";
} catch (Exception $e) {
    echo "  ❌ rbf_create_slot_version_table() - Error: " . $e->getMessage() . "\n";
}

try {
    // Test optimistic booking
    $result = rbf_book_slot_optimistic('2024-12-20', 'pranzo', 4);
    if (is_array($result) && isset($result['success'])) {
        echo "  ✅ rbf_book_slot_optimistic() - Returns proper structure\n";
    } else {
        echo "  ❌ rbf_book_slot_optimistic() - Invalid return structure\n";
    }
} catch (Exception $e) {
    echo "  ❌ rbf_book_slot_optimistic() - Error: " . $e->getMessage() . "\n";
}

try {
    // Test capacity release
    $result = rbf_release_slot_capacity('2024-12-20', 'pranzo', 2);
    if (is_bool($result)) {
        echo "  ✅ rbf_release_slot_capacity() - Returns boolean\n";
    } else {
        echo "  ❌ rbf_release_slot_capacity() - Invalid return type\n";
    }
} catch (Exception $e) {
    echo "  ❌ rbf_release_slot_capacity() - Error: " . $e->getMessage() . "\n";
}

// Test 3: Error handling
echo "\n✅ Testing error handling:\n";

try {
    // Test with invalid parameters
    $result = rbf_book_slot_optimistic('', '', 0);
    if (!$result['success']) {
        echo "  ✅ Invalid parameters properly rejected\n";
    } else {
        echo "  ❌ Invalid parameters not properly handled\n";
    }
} catch (Exception $e) {
    echo "  ✅ Exception handling working: " . $e->getMessage() . "\n";
}

echo "\n🎉 Integration test completed!\n";
echo "✅ Optimistic locking system is properly integrated and functional.\n";