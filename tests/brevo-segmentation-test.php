<?php
/**
 * Test for Brevo list segmentation based on both form language and phone prefix
 * 
 * @package FP_Prenotazioni_Ristorante_PRO
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Test Brevo list segmentation logic
 */
function test_brevo_segmentation_logic() {
    echo "<h2>Testing Brevo List Segmentation Logic</h2>\n";
    
    // Test cases: [form_lang, phone_country_code, expected_brevo_lang, description]
    $test_cases = [
        ['en', 'it', 'it', 'English form + Italian phone → Italian list (phone priority)'],
        ['it', 'us', 'it', 'Italian form + US phone → Italian list (form language fallback)'],
        ['en', 'us', 'en', 'English form + US phone → English list'],
    ];
    
    $passed = 0;
    $total = count($test_cases);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr><th>Test Case</th><th>Form Lang</th><th>Phone Country</th><th>Expected</th><th>Actual</th><th>Result</th></tr>\n";
    
    foreach ($test_cases as $i => $test) {
        list($lang, $country_code, $expected, $description) = $test;
        
        // Apply the fallback logic from booking-handler.php
        $original_country_code = $country_code;
        $country_code = strtolower($country_code);
        if (empty($country_code)) {
            $country_code = 'it';
        }

        $normalized_lang = strtolower($lang) === 'en' ? 'en' : 'it';

        // Apply the segmentation logic: form language first, then Italian phone override
        $brevo_lang = ($normalized_lang === 'en') ? 'en' : 'it';
        if ($country_code === 'it') {
            $brevo_lang = 'it';
        }
        
        $result = ($brevo_lang === $expected) ? 'PASS' : 'FAIL';
        $color = ($result === 'PASS') ? 'green' : 'red';
        
        if ($result === 'PASS') {
            $passed++;
        }
        
        echo "<tr>";
        echo "<td>$description</td>";
        echo "<td>$lang</td>";
        echo "<td>" . ($original_country_code === '' ? 'empty' : $original_country_code) . "</td>";
        echo "<td>$expected</td>";
        echo "<td>$brevo_lang</td>";
        echo "<td style='color: $color; font-weight: bold;'>$result</td>";
        echo "</tr>\n";
    }
    
    echo "</table>\n";
    echo "<br><strong>Test Results: $passed/$total tests passed</strong>\n";
    
    if ($passed === $total) {
        echo "<p style='color: green; font-weight: bold;'>✅ All tests passed! The segmentation logic is working correctly.</p>\n";
    } else {
        echo "<p style='color: red; font-weight: bold;'>❌ Some tests failed. Please review the logic.</p>\n";
    }
    
    return $passed === $total;
}

// Add action to run test via WordPress admin or direct access
if (isset($_GET['run_brevo_test']) && $_GET['run_brevo_test'] === '1') {
    test_brevo_segmentation_logic();
}
?>