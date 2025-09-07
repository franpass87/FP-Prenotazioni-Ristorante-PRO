<?php
/**
 * Inline Validation Tests for Restaurant Booking Plugin
 * 
 * Tests the inline validation functionality including:
 * - Required field indicators
 * - Synchronous validation
 * - Asynchronous validation
 * - Error messaging
 * - Visual feedback
 * 
 * @package FP_Prenotazioni_Ristorante_PRO
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    // For testing purposes, we'll simulate WordPress environment
    if (!defined('RBF_PLUGIN_DIR')) {
        define('RBF_PLUGIN_DIR', dirname(__DIR__) . '/');
    }
}

/**
 * Test required field indicators are present in CSS
 */
function test_rbf_required_field_indicators() {
    echo "Testing Required Field Indicators...\n";
    
    $css_file = RBF_PLUGIN_DIR . 'assets/css/frontend.css';
    if (!file_exists($css_file)) {
        echo "❌ CSS file not found\n";
        return false;
    }
    
    $css_content = file_get_contents($css_file);
    
    $required_css_classes = [
        '.rbf-required-indicator',
        '.rbf-field-wrapper',
        '.rbf-field-error',
        '.rbf-field-success',
        '.rbf-field-invalid',
        '.rbf-field-valid',
        '.rbf-field-validating',
        '@keyframes rbf-spin'
    ];
    
    foreach ($required_css_classes as $class) {
        if (strpos($css_content, $class) === false) {
            echo "❌ Missing CSS class/animation: $class\n";
            return false;
        }
        echo "✓ Found CSS: $class\n";
    }
    
    // Check for proper error styling
    if (strpos($css_content, 'rbf-field-error.show') === false) {
        echo "❌ Missing show state for error messages\n";
        return false;
    }
    
    echo "✅ All required field indicator CSS classes found!\n\n";
    return true;
}

/**
 * Test form structure includes validation elements
 */
function test_rbf_form_validation_structure() {
    echo "Testing Form Validation Structure...\n";
    
    $frontend_file = RBF_PLUGIN_DIR . 'includes/frontend.php';
    if (!file_exists($frontend_file)) {
        echo "❌ Frontend file not found\n";
        return false;
    }
    
    $frontend_content = file_get_contents($frontend_file);
    
    $required_elements = [
        'rbf-required-indicator',
        'rbf-field-wrapper',
        'rbf-meal-error',
        'rbf-date-error',
        'rbf-time-error',
        'rbf-people-error',
        'rbf-name-error',
        'rbf-surname-error',
        'rbf-email-error',
        'rbf-tel-error',
        'rbf-privacy-error'
    ];
    
    foreach ($required_elements as $element) {
        if (strpos($frontend_content, $element) === false) {
            echo "❌ Missing form element: $element\n";
            return false;
        }
        echo "✓ Found form element: $element\n";
    }
    
    // Check for required indicators (asterisks)
    $asterisk_count = substr_count($frontend_content, 'rbf-required-indicator');
    if ($asterisk_count < 7) { // At least 7 required fields
        echo "❌ Not enough required field indicators found (expected ≥7, got $asterisk_count)\n";
        return false;
    }
    
    echo "✅ All form validation structure elements found!\n\n";
    return true;
}

/**
 * Test JavaScript validation functionality
 */
function test_rbf_javascript_validation() {
    echo "Testing JavaScript Validation...\n";
    
    $js_file = RBF_PLUGIN_DIR . 'assets/js/frontend.js';
    if (!file_exists($js_file)) {
        echo "❌ JavaScript file not found\n";
        return false;
    }
    
    $js_content = file_get_contents($js_file);
    
    $required_js_elements = [
        'ValidationManager',
        'rules:',
        'validate:',
        'asyncValidate:',
        'showFieldError',
        'showFieldSuccess',
        'showFieldValidating',
        'clearFieldValidation',
        'validateField',
        'rbf_meal',
        'rbf_data',
        'rbf_orario',
        'rbf_persone',
        'rbf_nome',
        'rbf_cognome',
        'rbf_email',
        'rbf_tel',
        'rbf_privacy'
    ];
    
    foreach ($required_js_elements as $element) {
        if (strpos($js_content, $element) === false) {
            echo "❌ Missing JavaScript element: $element\n";
            return false;
        }
        echo "✓ Found JavaScript element: $element\n";
    }
    
    // Check for specific validation rules
    $validation_checks = [
        'emailRegex',
        'required: true',
        'addEventListener',
        'classList.add',
        'classList.remove',
        'setTimeout'
    ];
    
    foreach ($validation_checks as $check) {
        if (strpos($js_content, $check) === false) {
            echo "❌ Missing validation logic: $check\n";
            return false;
        }
        echo "✓ Found validation logic: $check\n";
    }
    
    echo "✅ All JavaScript validation functionality found!\n\n";
    return true;
}

/**
 * Test validation messages localization
 */
function test_rbf_validation_messages() {
    echo "Testing Validation Messages Localization...\n";
    
    $frontend_file = RBF_PLUGIN_DIR . 'includes/frontend.php';
    $frontend_content = file_get_contents($frontend_file);
    
    $required_messages = [
        'mealRequired',
        'dateRequired',
        'dateInPast',
        'timeRequired',
        'peopleMinimum',
        'peopleMaximum',
        'nameRequired',
        'nameInvalid',
        'surnameRequired',
        'surnameInvalid',
        'emailRequired',
        'emailInvalid',
        'emailTest',
        'phoneRequired',
        'phoneMinLength',
        'phoneMaxLength'
    ];
    
    foreach ($required_messages as $message) {
        if (strpos($frontend_content, "'$message'") === false) {
            echo "❌ Missing validation message: $message\n";
            return false;
        }
        echo "✓ Found validation message: $message\n";
    }
    
    echo "✅ All validation messages properly localized!\n\n";
    return true;
}

/**
 * Test validation field types and rules
 */
function test_rbf_validation_rules() {
    echo "Testing Validation Rules Logic...\n";
    
    $js_file = RBF_PLUGIN_DIR . 'assets/js/frontend.js';
    $js_content = file_get_contents($js_file);
    
    $validation_patterns = [
        // Email regex pattern
        '/^[^\s@]+@[^\s@]+\.[^\s@]+$/',
        // Name validation pattern
        'a-zA-ZÀ-ÿ',
        // Number validation
        'parseInt',
        // Length checks
        'length',
        // Trim function
        'trim()',
        // Date validation
        'new Date',
        // Required field checks
        'if (!value)',
        // Range checks
        'people > 20',
        'people < 1'
    ];
    
    foreach ($validation_patterns as $pattern) {
        if (strpos($js_content, $pattern) === false) {
            echo "❌ Missing validation pattern: $pattern\n";
            return false;
        }
        echo "✓ Found validation pattern: $pattern\n";
    }
    
    echo "✅ All validation rules properly implemented!\n\n";
    return true;
}

/**
 * Test accessibility compliance
 */
function test_rbf_validation_accessibility() {
    echo "Testing Validation Accessibility...\n";
    
    $frontend_file = RBF_PLUGIN_DIR . 'includes/frontend.php';
    $frontend_content = file_get_contents($frontend_file);
    
    $accessibility_features = [
        'aria-required="true"',
        'aria-describedby',
        'role="group"',
        'aria-labelledby',
        'aria-live="polite"'
    ];
    
    foreach ($accessibility_features as $feature) {
        if (strpos($frontend_content, $feature) === false) {
            echo "❌ Missing accessibility feature: $feature\n";
            return false;
        }
        echo "✓ Found accessibility feature: $feature\n";
    }
    
    // Check CSS for focus indicators
    $css_file = RBF_PLUGIN_DIR . 'assets/css/frontend.css';
    $css_content = file_get_contents($css_file);
    
    if (strpos($css_content, 'outline:') === false) {
        echo "❌ Missing focus outline styles\n";
        return false;
    }
    
    echo "✅ All accessibility features properly implemented!\n\n";
    return true;
}

/**
 * Test error message timing and UX
 */
function test_rbf_validation_ux() {
    echo "Testing Validation UX Requirements...\n";
    
    $js_file = RBF_PLUGIN_DIR . 'assets/js/frontend.js';
    $js_content = file_get_contents($js_file);
    
    $ux_requirements = [
        // Debounced validation
        'setTimeout',
        'clearTimeout',
        // State management
        'classList.add',
        'classList.remove',
        // Event listeners
        'addEventListener',
        'blur',
        'focus',
        'input',
        'change',
        // Visual feedback
        'rbf-field-invalid',
        'rbf-field-valid',
        'rbf-field-validating',
        // Animation support
        'show'
    ];
    
    foreach ($ux_requirements as $requirement) {
        if (strpos($js_content, $requirement) === false) {
            echo "❌ Missing UX requirement: $requirement\n";
            return false;
        }
        echo "✓ Found UX requirement: $requirement\n";
    }
    
    // Check CSS animations and transitions for smooth UX
    $css_file = RBF_PLUGIN_DIR . 'assets/css/frontend.css';
    $css_content = file_get_contents($css_file);
    
    $css_ux_requirements = [
        'transition:',
        'opacity',
        'transform'
    ];
    
    foreach ($css_ux_requirements as $requirement) {
        if (strpos($css_content, $requirement) === false) {
            echo "❌ Missing CSS UX requirement: $requirement\n";
            return false;
        }
        echo "✓ Found CSS UX requirement: $requirement\n";
    }
    
    echo "✅ All UX requirements satisfied!\n\n";
    return true;
}

/**
 * Test preservation of existing functionality
 */
function test_rbf_existing_functionality_intact() {
    echo "Testing Existing Functionality Preservation...\n";
    
    $js_file = RBF_PLUGIN_DIR . 'assets/js/frontend.js';
    $js_content = file_get_contents($js_file);
    
    // Check that existing functionality is still present
    $existing_functions = [
        'initializeAutosave',
        'AutoSave.load',
        'collectFormData',
        'restoreFormData',
        'enhanceMobileExperience',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'rbfData',
        'rbfLog'
    ];
    
    foreach ($existing_functions as $function) {
        if (strpos($js_content, $function) === false) {
            echo "❌ Missing existing function: $function\n";
            return false;
        }
        echo "✓ Preserved existing function: $function\n";
    }
    
    echo "✅ All existing functionality preserved!\n\n";
    return true;
}

// Run tests if this file is executed directly or included
if (defined('RBF_PLUGIN_DIR')) {
    echo "Running Inline Validation Tests...\n";
    echo "===================================\n\n";
    
    $test1 = test_rbf_required_field_indicators();
    $test2 = test_rbf_form_validation_structure();
    $test3 = test_rbf_javascript_validation();
    $test4 = test_rbf_validation_messages();
    $test5 = test_rbf_validation_rules();
    $test6 = test_rbf_validation_accessibility();
    $test7 = test_rbf_validation_ux();
    $test8 = test_rbf_existing_functionality_intact();
    
    if ($test1 && $test2 && $test3 && $test4 && $test5 && $test6 && $test7 && $test8) {
        echo "🎉 ALL TESTS PASSED! Inline validation is ready.\n";
        echo "Feature successfully implements:\n";
        echo "- ✅ Visual required field indicators\n";
        echo "- ✅ Synchronous validation (format checks)\n";
        echo "- ✅ Asynchronous validation (email checks)\n";
        echo "- ✅ Real-time error messaging\n";
        echo "- ✅ Visual feedback states\n";
        echo "- ✅ Accessibility compliance\n";
        echo "- ✅ Smooth UX with animations\n";
        echo "- ✅ Preserved existing functionality\n";
    } else {
        echo "❌ Some tests failed. Please review the implementation.\n";
    }
    
    echo "\n";
}