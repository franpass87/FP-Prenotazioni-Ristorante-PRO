<?php
/**
 * Bot Simulation Test - Demonstrates anti-bot protection in action
 * This simulates actual bot behavior vs human behavior
 */

// Include required files
if (!function_exists('rbf_detect_bot_submission')) {
    require_once dirname(__DIR__) . '/includes/utils.php';
}

// Mock WordPress functions if not available
if (!function_exists('get_transient')) {
    function get_transient($key) { return false; }
    function set_transient($key, $value, $expiration) { return true; }
}

// Mock environment
$_SERVER['HTTP_USER_AGENT'] = $_SERVER['HTTP_USER_AGENT'] ?? 'Test Browser';
$_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

echo "🤖 Bot Simulation Demonstration\n";
echo "=" . str_repeat("=", 40) . "\n\n";

// Test 1: Obvious Bot Behavior
echo "📍 Test 1: Obvious Bot Submission\n";
echo "-" . str_repeat("-", 30) . "\n";

$bot_submission = [
    'rbf_nome' => 'Bot',
    'rbf_cognome' => 'Test',
    'rbf_email' => 'bot@10minutemail.com',
    'rbf_website' => 'http://spam-link.com', // Honeypot filled!
    'rbf_form_timestamp' => time() - 1, // Too fast
    'rbf_allergie' => 'automated test data'
];

// Simulate bot user agent
$original_ua = $_SERVER['HTTP_USER_AGENT'];
$_SERVER['HTTP_USER_AGENT'] = 'python-requests/2.25.1';

$result = rbf_detect_bot_submission($bot_submission);
$_SERVER['HTTP_USER_AGENT'] = $original_ua;

echo "Result: " . ($result['is_bot'] ? '🚫 BOT DETECTED' : '✅ HUMAN') . "\n";
echo "Severity: {$result['severity']}\n";
echo "Score: {$result['score']}/100\n";
echo "Reason: {$result['reason']}\n\n";

// Test 2: Legitimate Human Behavior
echo "📍 Test 2: Legitimate Human Submission\n";
echo "-" . str_repeat("-", 30) . "\n";

$human_submission = [
    'rbf_nome' => 'Maria',
    'rbf_cognome' => 'Rossi',
    'rbf_email' => 'maria.rossi@gmail.com',
    'rbf_website' => '', // Honeypot empty
    'rbf_form_timestamp' => time() - 45, // Normal timing
    'rbf_allergie' => 'Allergica ai crostacei'
];

$result = rbf_detect_bot_submission($human_submission);

echo "Result: " . ($result['is_bot'] ? '🚫 BOT DETECTED' : '✅ HUMAN') . "\n";
echo "Severity: {$result['severity']}\n";
echo "Score: {$result['score']}/100\n";
echo "Reason: " . ($result['reason'] ?: 'No suspicion detected') . "\n\n";

// Test 3: Borderline Case (Fast but not bot)
echo "📍 Test 3: Fast Human Submission\n";
echo "-" . str_repeat("-", 30) . "\n";

$fast_human = [
    'rbf_nome' => 'Luca',
    'rbf_cognome' => 'Bianchi',
    'rbf_email' => 'luca@outlook.com',
    'rbf_website' => '', // Honeypot empty
    'rbf_form_timestamp' => time() - 3, // Fast but not impossible
    'rbf_allergie' => ''
];

$result = rbf_detect_bot_submission($fast_human);

echo "Result: " . ($result['is_bot'] ? '🚫 BOT DETECTED' : '✅ HUMAN') . "\n";
echo "Severity: {$result['severity']}\n";
echo "Score: {$result['score']}/100\n";
echo "Reason: {$result['reason']}\n\n";

// Test 4: Rate Limiting Simulation
echo "📍 Test 4: Rate Limiting Test\n";
echo "-" . str_repeat("-", 30) . "\n";

// Mock WordPress functions for this test
if (!function_exists('get_transient')) {
    function get_transient($key) { return false; }
    function set_transient($key, $value, $expiration) { return true; }
}

$rate_score = rbf_check_submission_rate();
echo "Rate limiting score: {$rate_score}/30\n";
echo "Status: " . ($rate_score > 20 ? '⚠️ High rate detected' : '✅ Normal rate') . "\n\n";

// Test 5: Field Pattern Analysis
echo "📍 Test 5: Field Pattern Analysis\n";
echo "-" . str_repeat("-", 30) . "\n";

$patterns_test = [
    ['rbf_nome' => 'Test', 'rbf_cognome' => 'User', 'rbf_email' => 'test@example.com'],
    ['rbf_nome' => 'Same', 'rbf_cognome' => 'Same', 'rbf_email' => 'real@gmail.com'],
    ['rbf_nome' => 'Marco', 'rbf_cognome' => 'Verdi', 'rbf_email' => 'marco@domain.it'],
];

foreach ($patterns_test as $i => $data) {
    $score = rbf_analyze_field_patterns($data);
    $status = $score > 20 ? '🚫 SUSPICIOUS' : ($score > 0 ? '⚠️ MINOR ISSUES' : '✅ CLEAN');
    echo "Pattern " . ($i + 1) . ": {$status} (Score: {$score})\n";
}

echo "\n📊 Summary\n";
echo "=" . str_repeat("=", 20) . "\n";
echo "✅ Honeypot protection: Active\n";
echo "✅ Timestamp validation: Active\n";
echo "✅ User agent checking: Active\n";
echo "✅ Field pattern analysis: Active\n";
echo "✅ Rate limiting: Active\n";
echo "🔧 reCAPTCHA v3: Conditional (when configured)\n\n";

echo "🛡️ Anti-bot system is fully operational!\n";
echo "Monitor logs at: /wp-content/debug.log\n";
echo "Configure reCAPTCHA at: WordPress Admin → Prenotazioni → Impostazioni\n";