<?php
/**
 * Security and Sanitization Tests for RBF Plugin
 * Tests input validation, XSS prevention, email template security, and ICS generation
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

// Mock WordPress functions for standalone testing
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) { return strip_tags(trim($str)); }
    function sanitize_email($email) { return filter_var($email, FILTER_SANITIZE_EMAIL); }
    function sanitize_textarea_field($str) { return trim($str); }
    function esc_html($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
    function esc_attr($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
    function esc_url($url) { return filter_var($url, FILTER_SANITIZE_URL); }
    function wp_strip_all_tags($string) { return strip_tags($string); }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value) {
        if (is_array($value)) {
            return array_map('wp_unslash', $value);
        }

        return stripslashes($value);
    }
}

if (!function_exists('home_url')) {
    function home_url($path = '', $scheme = null) {
        $home = 'https://example.test';

        if ($path) {
            $home .= '/' . ltrim($path, '/');
        }

        return $home;
    }
}

if (!function_exists('wp_parse_url')) {
    function wp_parse_url($url, $component = -1) {
        return parse_url($url, $component);
    }
}

if (!function_exists('get_bloginfo')) {
    function get_bloginfo($show = '', $filter = 'raw') {
        if ($show === 'name') {
            return 'RBF Test Site';
        }

        return '';
    }
}

class RBF_Security_Sanitization_Tests {
    private $test_results = [];
    
    public function run_all_tests() {
        echo "🔒 Running Security and Sanitization Tests for RBF Plugin...\n";
        echo "=" . str_repeat("=", 65) . "\n\n";
        
        $this->test_input_sanitization();
        $this->test_xss_prevention();
        $this->test_injection_attacks();
        $this->test_email_template_security();
        $this->test_ics_generation_security();
        $this->test_edge_cases();
        $this->test_malicious_payloads();
        
        $this->print_summary();
    }
    
    /**
     * Test enhanced input sanitization functions
     */
    public function test_input_sanitization() {
        echo "🧪 Testing Enhanced Input Sanitization...\n";
        
        // Test strict text sanitization
        $malicious_input = "<script>alert('xss')</script>Hello";
        $sanitized = rbf_sanitize_text_strict($malicious_input);
        $this->assert_test(
            'XSS script removal', 
            !strpos($sanitized, '<script>') && strpos($sanitized, 'Hello') !== false,
            "Expected: 'Hello', Got: '$sanitized'"
        );
        
        // Test name field sanitization
        $malicious_name = "John<script>alert(1)</script>Doe123!@#";
        $sanitized_name = rbf_sanitize_name_field($malicious_name);
        $this->assert_test(
            'Name field sanitization',
            preg_match('/^[a-zA-ZÀ-ÿ\s\-\'\.]+$/', $sanitized_name) && !strpos($sanitized_name, 'script'),
            "Expected: letters only, Got: '$sanitized_name'"
        );
        
        // Test phone field sanitization
        $malicious_phone = "+39<script>123</script>456-789";
        $sanitized_phone = rbf_sanitize_phone_field($malicious_phone);
        $this->assert_test(
            'Phone field sanitization',
            preg_match('/^[\d\s\-\(\)\+]+$/', $sanitized_phone) && !strpos($sanitized_phone, 'script'),
            "Expected: phone format, Got: '$sanitized_phone'"
        );
        
        // Test textarea sanitization
        $malicious_textarea = "Notes<script>alert('xss')</script>\n<iframe src='evil.com'></iframe>OK";
        $sanitized_textarea = rbf_sanitize_textarea_strict($malicious_textarea);
        $this->assert_test(
            'Textarea sanitization',
            !strpos($sanitized_textarea, '<script>') && !strpos($sanitized_textarea, '<iframe>') && strpos($sanitized_textarea, 'OK') !== false,
            "Expected: safe text, Got: '$sanitized_textarea'"
        );
        
        echo "✅ Input sanitization tests completed\n\n";
    }
    
    /**
     * Test XSS prevention measures
     */
    public function test_xss_prevention() {
        echo "🧪 Testing XSS Prevention...\n";
        
        $xss_payloads = [
            "<script>alert('XSS')</script>",
            "javascript:alert('XSS')",
            "<img src=x onerror=alert('XSS')>",
            "<svg onload=alert('XSS')>",
            "onmouseover=alert('XSS')",
            "<iframe src='javascript:alert(1)'></iframe>",
            "<object data='data:text/html,<script>alert(1)</script>'></object>",
            "<embed src='data:text/html,<script>alert(1)</script>'>",
            "<meta http-equiv='refresh' content='0;url=javascript:alert(1)'>",
            "<link rel='stylesheet' href='javascript:alert(1)'>",
            "<style>@import 'javascript:alert(1)';</style>",
            "<form><button formaction=javascript:alert(1)>XSS</button></form>",
            "<input type=image src=x onerror=alert(1)>",
            "<video><source onerror=alert(1)>",
            "<audio src=x onerror=alert(1)>",
            "<details open ontoggle=alert(1)>",
            "<marquee onstart=alert(1)>XSS</marquee>",
            "vbscript:alert('XSS')",
            "data:text/html,<script>alert('XSS')</script>"
        ];
        
        foreach ($xss_payloads as $index => $payload) {
            $sanitized = rbf_sanitize_text_strict($payload);
            $this->assert_test(
                "XSS payload #{$index}",
                !preg_match('/<[^>]*script[^>]*>/i', $sanitized) && 
                !preg_match('/javascript:/i', $sanitized) && 
                !preg_match('/vbscript:/i', $sanitized) &&
                !preg_match('/on\w+\s*=/i', $sanitized),
                "Payload: '$payload' -> '$sanitized'"
            );
        }
        
        echo "✅ XSS prevention tests completed\n\n";
    }
    
    /**
     * Test injection attack prevention
     */
    public function test_injection_attacks() {
        echo "🧪 Testing Injection Attack Prevention...\n";
        
        // Test header injection prevention in email subjects
        $header_injection = "Subject\r\nBcc: attacker@evil.com\r\nMalicious content";
        $safe_subject = rbf_escape_for_email($header_injection, 'subject');
        $this->assert_test(
            'Email header injection prevention',
            strpos($safe_subject, "\r") === false && strpos($safe_subject, "\n") === false,
            "Expected: no CRLF, Got: '$safe_subject'"
        );
        
        // Test null byte injection
        $null_byte_attack = "normal_text\0<script>alert('xss')</script>";
        $sanitized = rbf_sanitize_text_strict($null_byte_attack);
        $this->assert_test(
            'Null byte injection prevention',
            strpos($sanitized, chr(0)) === false && !strpos($sanitized, '<script>'),
            "Expected: no null bytes or scripts, Got: '$sanitized'"
        );
        
        // Test control character removal
        $control_chars = "test\x01\x02\x03\x04\x05normal";
        $sanitized = rbf_sanitize_text_strict($control_chars);
        $this->assert_test(
            'Control character removal',
            !preg_match('/[\x00-\x1F\x7F]/', $sanitized),
            "Expected: no control chars, Got: '" . addslashes($sanitized) . "'"
        );
        
        echo "✅ Injection attack prevention tests completed\n\n";
    }
    
    /**
     * Test email template security
     */
    public function test_email_template_security() {
        echo "🧪 Testing Email Template Security...\n";
        
        // Test HTML escaping in email context
        $malicious_name = "<script>alert('xss')</script>Mario";
        $escaped = rbf_escape_for_email($malicious_name, 'html');
        $this->assert_test(
            'HTML escaping in email',
            $escaped === '&lt;script&gt;alert(&#039;xss&#039;)&lt;/script&gt;Mario',
            "Expected: escaped HTML, Got: '$escaped'"
        );
        
        // Test attribute escaping
        $malicious_attr = "value\" onload=\"alert('xss')";
        $escaped_attr = rbf_escape_for_email($malicious_attr, 'attr');
        $this->assert_test(
            'Attribute escaping',
            strpos($escaped_attr, '"') === false || strpos($escaped_attr, '&quot;') !== false,
            "Expected: escaped quotes, Got: '$escaped_attr'"
        );
        
        // Test URL escaping
        $malicious_url = "javascript:alert('xss')";
        $escaped_url = rbf_escape_for_email($malicious_url, 'url');
        $this->assert_test(
            'URL escaping',
            !preg_match('/javascript:/i', $escaped_url),
            "Expected: safe URL, Got: '$escaped_url'"
        );
        
        echo "✅ Email template security tests completed\n\n";
    }
    
    /**
     * Test ICS generation security
     */
    public function test_ics_generation_security() {
        echo "🧪 Testing ICS Generation Security...\n";
        
        // Test ICS escaping
        $malicious_summary = "Meeting; DROP TABLE bookings; --";
        $escaped_ics = rbf_escape_for_ics($malicious_summary);
        $this->assert_test(
            'ICS semicolon escaping',
            strpos($escaped_ics, '\\;') !== false && strpos($escaped_ics, 'DROP') === false,
            "Expected: escaped semicolons, Got: '$escaped_ics'"
        );
        
        // Test ICS line break handling
        $text_with_breaks = "Line1\nLine2\rLine3\r\nLine4";
        $escaped_ics = rbf_escape_for_ics($text_with_breaks);
        $this->assert_test(
            'ICS line break handling',
            strpos($escaped_ics, '\\n') !== false && strpos($escaped_ics, "\r") === false,
            "Expected: escaped line breaks, Got: '$escaped_ics'"
        );
        
        // Test ICS comma escaping
        $text_with_commas = "Location, City, Country";
        $escaped_ics = rbf_escape_for_ics($text_with_commas);
        $this->assert_test(
            'ICS comma escaping',
            strpos($escaped_ics, '\\,') !== false,
            "Expected: escaped commas, Got: '$escaped_ics'"
        );
        
        // Test complete ICS generation
        $booking_data = [
            'date' => '2024-12-25',
            'time' => '19:30',
            'summary' => 'Test<script>alert(1)</script> Booking',
            'description' => 'Dangerous; content\nwith breaks',
            'location' => 'Restaurant, City'
        ];
        
        $ics_content = rbf_generate_ics_content($booking_data);
        $this->assert_test(
            'ICS generation safety',
            $ics_content !== false &&
            strpos($ics_content, '<script>') === false &&
            strpos($ics_content, 'BEGIN:VCALENDAR') !== false &&
            strpos($ics_content, 'END:VCALENDAR') !== false,
            "Expected: safe ICS content, Got: " . substr($ics_content, 0, 100) . "..."
        );

        // Test UID sanitization against malicious host headers
        $original_host = $_SERVER['HTTP_HOST'] ?? null;
        $_SERVER['HTTP_HOST'] = "malicious.example\r\nX-Injected: attack";

        $uid_booking_data = [
            'date' => '2024-12-25',
            'time' => '20:00',
            'summary' => 'UID Sanitization Check',
            'description' => 'Ensure host is sanitized',
            'location' => 'Secure Location'
        ];

        $ics_with_malicious_host = rbf_generate_ics_content($uid_booking_data);
        $uid_host = $this->extract_uid_host($ics_with_malicious_host);

        $this->assert_test(
            'ICS UID host sanitization',
            $ics_with_malicious_host !== false && $uid_host !== '' && preg_match('/^[A-Za-z0-9.-]+$/', $uid_host) === 1,
            "Expected sanitized host, Got: '$uid_host'"
        );

        if ($original_host !== null) {
            $_SERVER['HTTP_HOST'] = $original_host;
        } else {
            unset($_SERVER['HTTP_HOST']);
        }

        echo "✅ ICS generation security tests completed\n\n";
    }
    
    /**
     * Test edge cases and boundary conditions
     */
    public function test_edge_cases() {
        echo "🧪 Testing Edge Cases...\n";
        
        // Test empty inputs
        $empty_sanitized = rbf_sanitize_text_strict('');
        $this->assert_test(
            'Empty input handling',
            $empty_sanitized === '',
            "Expected: empty string, Got: '$empty_sanitized'"
        );
        
        // Test very long inputs
        $long_input = str_repeat('A', 1000) . '<script>alert(1)</script>';
        $sanitized_long = rbf_sanitize_name_field($long_input);
        $this->assert_test(
            'Long input truncation',
            strlen($sanitized_long) <= 100 && !strpos($sanitized_long, '<script>'),
            "Expected: truncated safe string, Got length: " . strlen($sanitized_long)
        );
        
        // Test unicode and special characters
        $unicode_input = "Café André São José François";
        $sanitized_unicode = rbf_sanitize_name_field($unicode_input);
        $this->assert_test(
            'Unicode name handling',
            $sanitized_unicode === $unicode_input,
            "Expected: preserved unicode, Got: '$sanitized_unicode'"
        );
        
        // Test phone number edge cases
        $suspicious_phone = "1111111111111111";
        $phone_result = rbf_validate_phone($suspicious_phone);
        $this->assert_test(
            'Suspicious phone pattern detection',
            is_array($phone_result) && isset($phone_result['error']),
            "Expected: validation error, Got: " . (is_array($phone_result) ? 'error detected' : 'accepted')
        );
        
        echo "✅ Edge case tests completed\n\n";
    }
    
    /**
     * Test with comprehensive malicious payloads
     */
    public function test_malicious_payloads() {
        echo "🧪 Testing Malicious Payloads...\n";
        
        $malicious_payloads = [
            // XSS variants
            'Basic XSS' => "<script>alert('XSS')</script>",
            'Encoded XSS' => "%3Cscript%3Ealert('XSS')%3C/script%3E",
            'Event handler' => "<img src=x onerror=alert('XSS')>",
            'Data URI' => "data:text/html,<script>alert('XSS')</script>",
            
            // Injection attempts  
            'Header injection' => "normal\r\nBcc: evil@example.com",
            'SQL-like' => "'; DROP TABLE users; --",
            'Path traversal' => "../../../etc/passwd",
            'Null byte' => "normal\0<script>alert(1)</script>",
            
            // Protocol handlers
            'JavaScript protocol' => "javascript:alert('XSS')",
            'VBScript protocol' => "vbscript:msgbox('XSS')",
            'Data protocol' => "data:text/html;base64,PHNjcmlwdD5hbGVydCgnWFNTJyk8L3NjcmlwdD4=",
            
            // HTML5 vectors
            'SVG XSS' => "<svg onload=alert('XSS')></svg>",
            'Math XSS' => "<math><mi//xlink:href=\"data:x,<script>alert('XSS')</script>\">",
            'Details XSS' => "<details open ontoggle=alert('XSS')>",
            
            // Unicode/encoding bypasses
            'Unicode bypass' => "\u003cscript\u003ealert('XSS')\u003c/script\u003e",
            'Double encoding' => "%253Cscript%253Ealert('XSS')%253C/script%253E",
            'Mixed case' => "<ScRiPt>alert('XSS')</ScRiPt>",
        ];
        
        foreach ($malicious_payloads as $name => $payload) {
            // Test in text field
            $sanitized_text = rbf_sanitize_text_strict($payload);
            $this->assert_test(
                "Text field vs {$name}",
                $this->is_safe_output($sanitized_text),
                "Payload: '$payload' -> '$sanitized_text'"
            );
            
            // Test in name field
            $sanitized_name = rbf_sanitize_name_field($payload);
            $this->assert_test(
                "Name field vs {$name}",
                $this->is_safe_output($sanitized_name),
                "Payload: '$payload' -> '$sanitized_name'"
            );
            
            // Test in email template escaping
            $escaped_email = rbf_escape_for_email($payload, 'html');
            $this->assert_test(
                "Email template vs {$name}",
                $this->is_safe_output($escaped_email),
                "Payload: '$payload' -> '$escaped_email'"
            );
        }
        
        echo "✅ Malicious payload tests completed\n\n";
    }
    
    /**
     * Check if output is considered safe
     */
    private function is_safe_output($output) {
        $dangerous_patterns = [
            '/<script[^>]*>/i',
            '/<iframe[^>]*>/i',
            '/<object[^>]*>/i',
            '/<embed[^>]*>/i',
            '/javascript:/i',
            '/vbscript:/i',
            '/data:\s*text\/html/i',
            '/on\w+\s*=/i',
            '/[\r\n]/',
        ];

        foreach ($dangerous_patterns as $pattern) {
            if (preg_match($pattern, $output)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Extract the host portion from an ICS UID line
     */
    private function extract_uid_host($ics_content) {
        if (!is_string($ics_content) || $ics_content === '') {
            return '';
        }

        $lines = explode("\r\n", $ics_content);
        foreach ($lines as $line) {
            if (strpos($line, 'UID:') === 0) {
                $uid_value = substr($line, 4);
                $parts = explode('@', $uid_value);
                return $parts[1] ?? '';
            }
        }

        return '';
    }

    /**
     * Assert test result
     */
    private function assert_test($test_name, $condition, $details = '') {
        $status = $condition ? 'passed' : 'failed';
        $icon = $condition ? '✅' : '❌';
        
        $this->test_results[] = [
            'test' => $test_name,
            'status' => $status,
            'details' => $details
        ];
        
        echo "  {$icon} {$test_name}";
        if (!$condition && $details) {
            echo " - {$details}";
        }
        echo "\n";
    }
    
    /**
     * Print test summary
     */
    private function print_summary() {
        echo "Test Summary\n";
        echo "============\n";
        
        $total_tests = count($this->test_results);
        $passed_tests = count(array_filter($this->test_results, function($test) {
            return $test['status'] === 'passed';
        }));
        
        echo "Total Tests: {$total_tests}\n";
        echo "Passed: {$passed_tests}\n";
        echo "Failed: " . ($total_tests - $passed_tests) . "\n";
        
        if ($passed_tests === $total_tests) {
            echo "🎉 All security tests passed! Input sanitization is working correctly.\n";
        } else {
            echo "⚠️  Some security tests failed. Please review the sanitization implementation.\n";
            echo "\nFailed Tests:\n";
            foreach ($this->test_results as $result) {
                if ($result['status'] === 'failed') {
                    echo "  ❌ {$result['test']}: {$result['details']}\n";
                }
            }
        }
        
        echo "\n🔒 Security Features Tested:\n";
        echo "- ✅ XSS prevention in text fields\n";
        echo "- ✅ HTML injection prevention\n";
        echo "- ✅ Email header injection prevention\n";
        echo "- ✅ Script tag removal\n";
        echo "- ✅ Event handler sanitization\n";
        echo "- ✅ Protocol handler blocking\n";
        echo "- ✅ Control character removal\n";
        echo "- ✅ Input length validation\n";
        echo "- ✅ ICS format security\n";
        echo "- ✅ Email template escaping\n";
        
        echo "\n";
    }
}

// Load the enhanced functions if in WordPress context
if (function_exists('rbf_sanitize_input_fields')) {
    // WordPress context - functions already loaded
} else {
    // Standalone testing - define the functions inline for testing
    
    /**
     * Enhanced centralized input sanitization helper with security improvements
     */
    function rbf_sanitize_input_fields(array $input_data, array $field_map) {
        $sanitized = [];
        
        foreach ($field_map as $key => $type) {
            if (!isset($input_data[$key])) {
                continue;
            }
            
            $value = $input_data[$key];
            
            // First level: remove potential null bytes and control characters
            $value = str_replace(chr(0), '', $value);
            
            switch ($type) {
                case 'text':
                    $sanitized[$key] = rbf_sanitize_text_strict($value);
                    break;
                case 'email':
                    $sanitized[$key] = sanitize_email($value);
                    break;
                case 'textarea':
                    $sanitized[$key] = rbf_sanitize_textarea_strict($value);
                    break;
                case 'int':
                    $sanitized[$key] = intval($value);
                    break;
                case 'float':
                    $sanitized[$key] = floatval($value);
                    break;
                case 'url':
                    $sanitized[$key] = esc_url_raw($value);
                    break;
                case 'name':
                    $sanitized[$key] = rbf_sanitize_name_field($value);
                    break;
                case 'phone':
                    $sanitized[$key] = rbf_sanitize_phone_field($value);
                    break;
                default:
                    $sanitized[$key] = rbf_sanitize_text_strict($value);
            }
        }
        
        return $sanitized;
    }

    /**
     * Strict text field sanitization with enhanced security
     */
    function rbf_sanitize_text_strict($value) {
        // Remove potential script tags and dangerous characters
        $value = strip_tags($value);
        $value = sanitize_text_field($value);
        
        // Additional security: remove potentially dangerous sequences
        $dangerous_patterns = [
            '/javascript:/i',
            '/data:/i',
            '/vbscript:/i',
            '/on\w+\s*=/i', // onload, onclick, etc.
            '/<script/i',
            '/<iframe/i',
            '/<object/i',
            '/<embed/i'
        ];
        
        foreach ($dangerous_patterns as $pattern) {
            $value = preg_replace($pattern, '', $value);
        }
        
        return trim($value);
    }

    /**
     * Strict textarea sanitization while preserving basic formatting
     */
    function rbf_sanitize_textarea_strict($value) {
        // Allow only safe HTML tags for formatting
        $allowed_tags = '<br><p>';
        $value = strip_tags($value, $allowed_tags);
        $value = sanitize_textarea_field($value);
        
        // Remove dangerous sequences
        $dangerous_patterns = [
            '/javascript:/i',
            '/data:/i',
            '/vbscript:/i',
            '/on\w+\s*=/i',
            '/<script/i',
            '/<iframe/i',
            '/<object/i',
            '/<embed/i'
        ];
        
        foreach ($dangerous_patterns as $pattern) {
            $value = preg_replace($pattern, '', $value);
        }
        
        return trim($value);
    }

    /**
     * Sanitize name fields with extra validation
     */
    function rbf_sanitize_name_field($value) {
        $value = rbf_sanitize_text_strict($value);
        
        // Names should only contain letters, spaces, hyphens, apostrophes, and accented characters
        $value = preg_replace('/[^\p{L}\s\-\'\.]/u', '', $value);
        
        // Limit length to prevent buffer overflow attempts
        $value = substr($value, 0, 100);
        
        return trim($value);
    }

    /**
     * Sanitize phone fields with validation
     */
    function rbf_sanitize_phone_field($value) {
        $value = sanitize_text_field($value);
        
        // Phone should only contain numbers, spaces, hyphens, parentheses, and plus sign
        $value = preg_replace('/[^\d\s\-\(\)\+]/', '', $value);
        
        // Limit length
        $value = substr($value, 0, 20);
        
        return trim($value);
    }

    /**
     * Escape data for safe use in email templates (HTML context)
     */
    function rbf_escape_for_email($value, $context = 'html') {
        switch ($context) {
            case 'html':
                return esc_html($value);
            case 'attr':
                return esc_attr($value);
            case 'url':
                return esc_url($value);
            case 'subject':
                // For email subjects, ensure no header injection
                $value = str_replace(["\r", "\n", "\r\n"], '', $value);
                return sanitize_text_field($value);
            default:
                return esc_html($value);
        }
    }

    /**
     * Generate secure ICS calendar file content
     */
    function rbf_generate_ics_content($booking_data) {
        // Sanitize all booking data for ICS format
        $sanitized_data = [];
        foreach ($booking_data as $key => $value) {
            // ICS format requires specific escaping
            $sanitized_data[$key] = rbf_escape_for_ics($value);
        }

        // Generate unique UID
        $raw_host = isset($_SERVER['HTTP_HOST']) ? wp_unslash($_SERVER['HTTP_HOST']) : '';
        $host = sanitize_text_field($raw_host);
        $host = preg_replace('/[^A-Za-z0-9\.-]/', '', $host);

        if ($host === '') {
            $fallback_host = wp_parse_url(home_url(), PHP_URL_HOST);
            if (!empty($fallback_host)) {
                $fallback_host = sanitize_text_field($fallback_host);
                $host = preg_replace('/[^A-Za-z0-9\.-]/', '', $fallback_host);
            }
        }

        if ($host === '') {
            $fallback_name = sanitize_text_field(get_bloginfo('name'));
            $fallback_name = preg_replace('/[^A-Za-z0-9\.-]/', '', strtolower($fallback_name));
            $fallback_name = substr($fallback_name, 0, 64);
            if ($fallback_name === '') {
                $fallback_name = 'rbf-booking';
            }

            $host = $fallback_name;
        }

        $uid = uniqid('rbf_booking_', true) . '@' . $host;
        
        // Format datetime for ICS
        $booking_datetime = DateTime::createFromFormat('Y-m-d H:i', $sanitized_data['date'] . ' ' . $sanitized_data['time']);
        if (!$booking_datetime) {
            return false;
        }
        
        $start_time = $booking_datetime->format('Ymd\THis\Z');
        $end_time = $booking_datetime->add(new DateInterval('PT2H'))->format('Ymd\THis\Z'); // 2 hour duration
        $created_time = gmdate('Ymd\THis\Z');
        
        $ics_content = "BEGIN:VCALENDAR\r\n";
        $ics_content .= "VERSION:2.0\r\n";
        $ics_content .= "PRODID:-//RBF Restaurant Booking//EN\r\n";
        $ics_content .= "CALSCALE:GREGORIAN\r\n";
        $ics_content .= "BEGIN:VEVENT\r\n";
        $ics_content .= "UID:" . $uid . "\r\n";
        $ics_content .= "DTSTAMP:" . $created_time . "\r\n";
        $ics_content .= "DTSTART:" . $start_time . "\r\n";
        $ics_content .= "DTEND:" . $end_time . "\r\n";
        $ics_content .= "SUMMARY:" . $sanitized_data['summary'] . "\r\n";
        $ics_content .= "DESCRIPTION:" . $sanitized_data['description'] . "\r\n";
        if (!empty($sanitized_data['location'])) {
            $ics_content .= "LOCATION:" . $sanitized_data['location'] . "\r\n";
        }
        $ics_content .= "STATUS:CONFIRMED\r\n";
        $ics_content .= "END:VEVENT\r\n";
        $ics_content .= "END:VCALENDAR\r\n";
        
        return $ics_content;
    }

    /**
     * Escape text for ICS format
     */
    function rbf_escape_for_ics($text) {
        // ICS format escaping rules
        $text = str_replace(['\\', ';', ',', "\n", "\r"], ['\\\\', '\\;', '\\,', '\\n', ''], $text);
        
        // Remove any remaining control characters
        $text = preg_replace('/[\x00-\x1F\x7F]/', '', $text);
        
        // Limit length to prevent issues
        return substr($text, 0, 250);
    }

    /**
     * Enhanced centralized phone number validation with security improvements
     */
    function rbf_validate_phone($phone) {
        $phone = rbf_sanitize_phone_field($phone);
        
        // Enhanced phone validation - at least 8 digits, max 20 characters
        $digits_only = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($digits_only) < 8) {
            return ['error' => true, 'message' => 'Il numero di telefono inserito non è valido.'];
        }
        
        // Check for suspicious patterns (all same digits, etc.)
        if (preg_match('/^(\d)\1+$/', $digits_only)) {
            return ['error' => true, 'message' => 'Il numero di telefono inserito non sembra valido.'];
        }
        
        return $phone;
    }
}

// Run tests if this file is executed directly
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    $tests = new RBF_Security_Sanitization_Tests();
    $tests->run_all_tests();
    
    echo "📋 Security Implementation Notes:\n";
    echo "=================================\n";
    echo "1. Input validation: All user inputs are sanitized using enhanced functions\n";
    echo "2. XSS prevention: HTML escaping applied in all output contexts\n";
    echo "3. Email security: Header injection prevention and content escaping\n";
    echo "4. ICS security: Proper escaping for calendar file generation\n";
    echo "5. Phone validation: Enhanced patterns including suspicious number detection\n";
    echo "6. Name validation: Character restriction to prevent injection\n";
    echo "7. Length limits: Buffer overflow prevention through input truncation\n";
    echo "8. Control character removal: Binary safety improvements\n\n";
}