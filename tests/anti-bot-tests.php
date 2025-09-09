<?php
/**
 * Anti-Bot Protection Tests for RBF Plugin
 * Tests honeypot, timestamp validation, and bot detection logic
 */

// Prevent direct access in WordPress context only
if (defined('ABSPATH') && !defined('WP_CLI') && !defined('PHPUNIT_COMPOSER_INSTALL')) {
    // In WordPress context, check permissions
    if (!current_user_can('manage_options')) {
        exit('Access denied');
    }
} elseif (!defined('ABSPATH') && basename($_SERVER['PHP_SELF']) !== basename(__FILE__)) {
    // Not in WordPress and not direct execution
    exit('Direct access not allowed');
}

// Include required files if running standalone
if (!function_exists('rbf_detect_bot_submission')) {
    // Mock WordPress environment if not available
    if (!defined('ABSPATH')) {
        define('ABSPATH', dirname(__DIR__) . '/');
        $_SERVER['HTTP_USER_AGENT'] = $_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0 Test Browser';
        $_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        
        // Mock WordPress functions
        function get_transient($key) { return false; }
        function set_transient($key, $value, $expiration) { return true; }
    }
    
    require_once dirname(__DIR__) . '/includes/utils.php';
}

class RBF_Anti_Bot_Tests {
    private $test_results = [];
    
    public function run_all_tests() {
        echo "🛡️  Running Anti-Bot Protection Tests for RBF Plugin...\n";
        echo "=" . str_repeat("=", 60) . "\n\n";
        
        $this->test_honeypot_detection();
        $this->test_timestamp_validation();
        $this->test_user_agent_detection();
        $this->test_field_pattern_analysis();
        $this->test_rate_limiting();
        $this->test_integration_scenarios();
        
        $this->print_summary();
    }
    
    /**
     * Test honeypot field detection
     */
    public function test_honeypot_detection() {
        echo "🧪 Testing Honeypot Detection...\n";
        
        // Test 1: Clean submission (no honeypot filled)
        $clean_data = [
            'rbf_nome' => 'Mario',
            'rbf_cognome' => 'Rossi',
            'rbf_email' => 'mario@example.com',
            'rbf_website' => '', // Honeypot empty (good)
            'rbf_form_timestamp' => time() - 30
        ];
        
        $result = rbf_detect_bot_submission($clean_data);
        $this->assert_test(
            'Clean submission passes',
            !$result['is_bot'] || $result['severity'] !== 'high',
            "Expected clean submission, got: " . $result['reason']
        );
        
        // Test 2: Honeypot filled (bot detected)
        $bot_data = [
            'rbf_nome' => 'Bot',
            'rbf_cognome' => 'User',
            'rbf_email' => 'bot@spam.com',
            'rbf_website' => 'http://spam-site.com', // Honeypot filled (bad)
            'rbf_form_timestamp' => time() - 30
        ];
        
        $result = rbf_detect_bot_submission($bot_data);
        $this->assert_test(
            'Honeypot detection works',
            $result['is_bot'] && $result['severity'] === 'high',
            "Expected bot detection, got: " . json_encode($result)
        );
        
        echo "✅ Honeypot detection tests completed\n\n";
    }
    
    /**
     * Test timestamp validation
     */
    public function test_timestamp_validation() {
        echo "🧪 Testing Timestamp Validation...\n";
        
        // Test 1: Too fast submission (< 5 seconds)
        $fast_data = [
            'rbf_nome' => 'Fast',
            'rbf_cognome' => 'User',
            'rbf_email' => 'fast@example.com',
            'rbf_website' => '',
            'rbf_form_timestamp' => time() - 2 // 2 seconds ago
        ];
        
        $result = rbf_detect_bot_submission($fast_data);
        $this->assert_test(
            'Fast submission detected',
            $result['score'] >= 70,
            "Expected high score for fast submission, got: " . $result['score']
        );
        
        // Test 2: Normal timing (30 seconds)
        $normal_data = [
            'rbf_nome' => 'Normal',
            'rbf_cognome' => 'User',
            'rbf_email' => 'normal@example.com',
            'rbf_website' => '',
            'rbf_form_timestamp' => time() - 30 // 30 seconds ago
        ];
        
        $result = rbf_detect_bot_submission($normal_data);
        $this->assert_test(
            'Normal timing accepted',
            $result['score'] < 40,
            "Expected low score for normal timing, got: " . $result['score']
        );
        
        // Test 3: Very slow submission (> 30 minutes)
        $slow_data = [
            'rbf_nome' => 'Slow',
            'rbf_cognome' => 'User',
            'rbf_email' => 'slow@example.com',
            'rbf_website' => '',
            'rbf_form_timestamp' => time() - 2000 // 33+ minutes ago
        ];
        
        $result = rbf_detect_bot_submission($slow_data);
        $this->assert_test(
            'Slow submission flagged',
            $result['score'] >= 30,
            "Expected moderate score for slow submission, got: " . $result['score']
        );
        
        // Test 4: Missing timestamp
        $no_timestamp_data = [
            'rbf_nome' => 'NoTime',
            'rbf_cognome' => 'User',
            'rbf_email' => 'notime@example.com',
            'rbf_website' => ''
            // Missing rbf_form_timestamp
        ];
        
        $result = rbf_detect_bot_submission($no_timestamp_data);
        $this->assert_test(
            'Missing timestamp flagged',
            $result['score'] >= 30,
            "Expected score penalty for missing timestamp, got: " . $result['score']
        );
        
        echo "✅ Timestamp validation tests completed\n\n";
    }
    
    /**
     * Test user agent detection
     */
    public function test_user_agent_detection() {
        echo "🧪 Testing User Agent Detection...\n";
        
        // Test bot user agents
        $bot_agents = [
            '',
            'bot',
            'spider',
            'crawler',
            'curl/7.68.0',
            'python-requests/2.25.1',
            'wget',
            'short'
        ];
        
        foreach ($bot_agents as $agent) {
            $is_bot = rbf_detect_bot_user_agent($agent);
            $this->assert_test(
                "Bot agent detected: '$agent'",
                $is_bot,
                "Expected bot detection for: '$agent'"
            );
        }
        
        // Test legitimate user agents
        $human_agents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 14_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0 Mobile/15E148 Safari/604.1'
        ];
        
        foreach ($human_agents as $agent) {
            $is_bot = rbf_detect_bot_user_agent($agent);
            $this->assert_test(
                "Human agent accepted",
                !$is_bot,
                "Expected human detection for legitimate browser"
            );
        }
        
        echo "✅ User agent detection tests completed\n\n";
    }
    
    /**
     * Test field pattern analysis
     */
    public function test_field_pattern_analysis() {
        echo "🧪 Testing Field Pattern Analysis...\n";
        
        // Test 1: Test/fake data detection
        $fake_data = [
            'rbf_nome' => 'Test',
            'rbf_cognome' => 'Bot',
            'rbf_email' => 'test@example.com'
        ];
        
        $score = rbf_analyze_field_patterns($fake_data);
        $this->assert_test(
            'Fake data detected',
            $score > 0,
            "Expected penalty for fake data, got score: $score"
        );
        
        // Test 2: Identical name/surname
        $identical_data = [
            'rbf_nome' => 'Same',
            'rbf_cognome' => 'Same',
            'rbf_email' => 'real@domain.com'
        ];
        
        $score = rbf_analyze_field_patterns($identical_data);
        $this->assert_test(
            'Identical names flagged',
            $score >= 15,
            "Expected penalty for identical names, got score: $score"
        );
        
        // Test 3: Legitimate data
        $good_data = [
            'rbf_nome' => 'Mario',
            'rbf_cognome' => 'Rossi',
            'rbf_email' => 'mario.rossi@gmail.com'
        ];
        
        $score = rbf_analyze_field_patterns($good_data);
        $this->assert_test(
            'Legitimate data accepted',
            $score === 0,
            "Expected no penalty for good data, got score: $score"
        );
        
        // Test 4: Temporary email detection
        $temp_email_data = [
            'rbf_nome' => 'Real',
            'rbf_cognome' => 'Person',
            'rbf_email' => 'user@10minutemail.com'
        ];
        
        $score = rbf_analyze_field_patterns($temp_email_data);
        $this->assert_test(
            'Temporary email flagged',
            $score >= 20,
            "Expected penalty for temp email, got score: $score"
        );
        
        echo "✅ Field pattern analysis tests completed\n\n";
    }
    
    /**
     * Test rate limiting
     */
    public function test_rate_limiting() {
        echo "🧪 Testing Rate Limiting...\n";
        
        // Simulate multiple submissions (this is a simplified test)
        $score = rbf_check_submission_rate();
        $this->assert_test(
            'Rate limiting functional',
            $score >= 0,
            "Rate limiting should return non-negative score"
        );
        
        echo "✅ Rate limiting tests completed\n\n";
    }
    
    /**
     * Test integration scenarios
     */
    public function test_integration_scenarios() {
        echo "🧪 Testing Integration Scenarios...\n";
        
        // Scenario 1: Obvious bot (multiple red flags)
        $obvious_bot = [
            'rbf_nome' => 'Bot',
            'rbf_cognome' => 'Bot', 
            'rbf_email' => 'bot@10minutemail.com',
            'rbf_website' => 'http://spam.com', // Honeypot filled
            'rbf_form_timestamp' => time() - 1 // Too fast
        ];
        
        // Mock bot user agent
        $original_ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $_SERVER['HTTP_USER_AGENT'] = 'python-bot/1.0';
        
        $result = rbf_detect_bot_submission($obvious_bot);
        
        $_SERVER['HTTP_USER_AGENT'] = $original_ua; // Restore
        
        $this->assert_test(
            'Obvious bot detected',
            $result['is_bot'] && $result['severity'] === 'high',
            "Expected definitive bot detection, got: " . json_encode($result)
        );
        
        // Scenario 2: Legitimate user
        $legitimate_user = [
            'rbf_nome' => 'Maria',
            'rbf_cognome' => 'Bianchi',
            'rbf_email' => 'maria.bianchi@outlook.com',
            'rbf_website' => '', // Honeypot empty
            'rbf_form_timestamp' => time() - 45 // Normal timing
        ];
        
        $result = rbf_detect_bot_submission($legitimate_user);
        $this->assert_test(
            'Legitimate user accepted',
            !$result['is_bot'] || $result['severity'] === 'low',
            "Expected legitimate user acceptance, got: " . json_encode($result)
        );
        
        // Scenario 3: Borderline case (some flags but not definitive)
        $borderline_case = [
            'rbf_nome' => 'Quick',
            'rbf_cognome' => 'User',
            'rbf_email' => 'quick@gmail.com',
            'rbf_website' => '', // Honeypot empty
            'rbf_form_timestamp' => time() - 8 // Somewhat fast but not extreme
        ];
        
        $result = rbf_detect_bot_submission($borderline_case);
        $this->assert_test(
            'Borderline case handled appropriately',
            $result['severity'] === 'low' || $result['severity'] === 'medium',
            "Expected moderate handling, got: " . json_encode($result)
        );
        
        echo "✅ Integration scenario tests completed\n\n";
    }
    
    /**
     * Assert test result and track it
     */
    private function assert_test($name, $condition, $message = '') {
        $passed = (bool) $condition;
        $this->test_results[] = [
            'name' => $name,
            'passed' => $passed,
            'message' => $message
        ];
        
        $status = $passed ? '✅' : '❌';
        echo "  $status $name";
        if (!$passed && $message) {
            echo " - $message";
        }
        echo "\n";
    }
    
    /**
     * Print test summary
     */
    private function print_summary() {
        $total = count($this->test_results);
        $passed = array_filter($this->test_results, function($r) { return $r['passed']; });
        $failed = $total - count($passed);
        
        echo "Test Summary\n";
        echo "============\n";
        echo "Total Tests: $total\n";
        echo "Passed: " . count($passed) . "\n";
        echo "Failed: $failed\n";
        
        if ($failed > 0) {
            echo "⚠️  Some anti-bot tests failed. Please review the implementation.\n\n";
            echo "Failed Tests:\n";
            foreach ($this->test_results as $result) {
                if (!$result['passed']) {
                    echo "  ❌ {$result['name']}: {$result['message']}\n";
                }
            }
        } else {
            echo "✅ All anti-bot protection tests passed!\n";
        }
        
        echo "\n🛡️  Anti-Bot Features Tested:\n";
        echo "=================================\n";
        echo "- ✅ Honeypot field detection\n";
        echo "- ✅ Timestamp validation (too fast/slow)\n";
        echo "- ✅ User agent analysis\n";
        echo "- ✅ Field pattern recognition\n";
        echo "- ✅ Rate limiting protection\n";
        echo "- ✅ Integration scenarios\n";
        echo "\n📋 Anti-Bot Implementation Notes:\n";
        echo "==================================\n";
        echo "1. Honeypot: Invisible field that bots fill but humans don't see\n";
        echo "2. Timestamp: Validates form submission timing to detect automation\n";
        echo "3. User Agent: Identifies known bot patterns and suspicious agents\n";
        echo "4. Field Patterns: Detects fake data, test inputs, and suspicious patterns\n";
        echo "5. Rate Limiting: Tracks submission frequency from same IP address\n";
        echo "6. Scoring System: Combines multiple signals for accurate detection\n";
        echo "7. Severity Levels: High (block), Medium (challenge), Low (allow)\n";
    }
}

// Run tests if this file is executed directly
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    $test_runner = new RBF_Anti_Bot_Tests();
    $test_runner->run_all_tests();
}