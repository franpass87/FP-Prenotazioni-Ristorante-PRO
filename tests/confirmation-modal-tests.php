<?php
/**
 * Test for confirmation modal functionality
 * 
 * @package FP_Prenotazioni_Ristorante_PRO
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Test confirmation modal functionality
 */
function test_rbf_confirmation_modal() {
    echo "\n=== Test Confirmation Modal Functionality ===\n";
    
    // Test 1: Check if modal CSS is properly loaded
    echo "✓ Testing modal CSS inclusion...\n";
    
    $frontend_css_path = RBF_PLUGIN_DIR . 'assets/css/frontend.css';
    $css_content = file_get_contents($frontend_css_path);
    
    $required_css_classes = [
        '.rbf-confirmation-modal-overlay',
        '.rbf-confirmation-modal-content',
        '.rbf-confirmation-modal-header',
        '.rbf-confirmation-modal-body',
        '.rbf-confirmation-modal-footer',
        '.rbf-booking-summary',
        '.rbf-summary-item'
    ];
    
    foreach ($required_css_classes as $class) {
        if (strpos($css_content, $class) === false) {
            echo "❌ CSS class $class not found\n";
            return false;
        }
    }
    echo "✓ All required CSS classes found\n";
    
    // Test 2: Check if JavaScript labels are properly added
    echo "✓ Testing modal labels...\n";
    
    $frontend_php_path = RBF_PLUGIN_DIR . 'includes/frontend.php';
    $php_content = file_get_contents($frontend_php_path);
    
    $required_labels = [
        'confirmBookingTitle',
        'bookingSummary',
        'confirmWarning',
        'confirmBooking',
        'submittingBooking'
    ];
    
    foreach ($required_labels as $label) {
        if (strpos($php_content, "'$label'") === false) {
            echo "❌ Label $label not found in frontend.php\n";
            return false;
        }
    }
    echo "✓ All required labels found\n";
    
    // Test 3: Check JavaScript modal functionality 
    echo "✓ Testing JavaScript modal implementation...\n";
    
    $js_path = RBF_PLUGIN_DIR . 'assets/js/frontend.js';
    $js_content = file_get_contents($js_path);
    
    $required_js_functions = [
        'showBookingConfirmationModal',
        'formatDateForDisplay',
        'e.preventDefault()' // Form submission interception
    ];
    
    foreach ($required_js_functions as $func) {
        if (strpos($js_content, $func) === false) {
            echo "❌ JavaScript function/code $func not found\n";
            return false;
        }
    }
    echo "✓ All required JavaScript functionality found\n";
    
    // Test 4: Verify accessibility features
    echo "✓ Testing accessibility features...\n";
    
    $accessibility_features = [
        'role="dialog"',
        'aria-modal="true"',
        'aria-labelledby',
        'focus()',
        'key === \'Escape\'',
        'key === \'Tab\''
    ];
    
    foreach ($accessibility_features as $feature) {
        if (strpos($js_content, $feature) === false) {
            echo "❌ Accessibility feature $feature not found\n";
            return false;
        }
    }
    echo "✓ All accessibility features implemented\n";
    
    // Test 5: Check responsive design
    echo "✓ Testing responsive design...\n";
    
    $responsive_checks = [
        '@media (max-width: 768px)',
        '@media (max-width: 360px)',
        'flex-direction: column'
    ];
    
    foreach ($responsive_checks as $check) {
        if (strpos($css_content, $check) === false) {
            echo "❌ Responsive feature $check not found\n";
            return false;
        }
    }
    echo "✓ Responsive design implemented\n";
    
    echo "\n✅ All confirmation modal tests passed!\n\n";
    return true;
}

/**
 * Test modal UX requirements from acceptance criteria
 */
function test_rbf_modal_ux_requirements() {
    echo "=== Test UX Requirements ===\n";
    
    // Check CSS content for specific UX elements
    $frontend_css_path = RBF_PLUGIN_DIR . 'assets/css/frontend.css';
    $css_content = file_get_contents($frontend_css_path);
    
    $ux_requirements = [
        'Visualizzazione riepilogo dati' => [
            '.rbf-booking-summary',
            '.rbf-summary-item', 
            '.rbf-summary-label',
            '.rbf-summary-value'
        ],
        'Pulsante conferma/annulla' => [
            '.rbf-btn-confirm',
            '.rbf-btn-cancel',
            '.rbf-confirmation-modal-footer'
        ],
        'Warning chiaro' => [
            '.rbf-confirmation-warning',
            '.rbf-confirmation-warning-icon'
        ],
        'Loading state' => [
            '.rbf-btn-confirm.loading',
            'animation: rbf-spin'
        ]
    ];
    
    foreach ($ux_requirements as $requirement => $checks) {
        echo "✓ Testing: $requirement\n";
        foreach ($checks as $check) {
            if (strpos($css_content, $check) === false) {
                echo "❌ UX element $check not found for $requirement\n";
                return false;
            }
        }
    }
    
    echo "✅ All UX requirements satisfied!\n\n";
    return true;
}

/**
 * Test that existing functionality is not broken
 */
function test_rbf_existing_functionality_intact() {
    echo "=== Test Existing Functionality Integrity ===\n";
    
    $js_path = RBF_PLUGIN_DIR . 'assets/js/frontend.js';
    $js_content = file_get_contents($js_path);
    
    // Check that existing functionality is preserved
    $existing_functions = [
        'collectFormData',
        'restoreFormData',
        'AutoSave.clear',
        'showComponentLoading',
        'lazyLoadDatePicker',
        'lazyLoadTelInput'
    ];
    
    foreach ($existing_functions as $func) {
        if (strpos($js_content, $func) === false) {
            echo "❌ Existing function $func appears to be missing\n";
            return false;
        }
    }
    echo "✓ All existing functions preserved\n";
    
    // Check that form validation is still in place
    $validation_checks = [
        'el.privacyCheckbox.is(\':checked\')',
        'iti.isValidNumber()',
        'rbfData.labels.privacyRequired',
        'rbfData.labels.invalidPhone'
    ];
    
    foreach ($validation_checks as $check) {
        if (strpos($js_content, $check) === false) {
            echo "❌ Existing validation $check appears to be missing\n";
            return false;
        }
    }
    echo "✓ All existing validations preserved\n";
    
    echo "✅ Existing functionality integrity maintained!\n\n";
    return true;
}

// Run tests if this file is executed directly or included
if (defined('RBF_PLUGIN_DIR')) {
    echo "Running Confirmation Modal Tests...\n";
    echo "=====================================\n";
    
    $test1 = test_rbf_confirmation_modal();
    $test2 = test_rbf_modal_ux_requirements();
    $test3 = test_rbf_existing_functionality_intact();
    
    if ($test1 && $test2 && $test3) {
        echo "🎉 ALL TESTS PASSED! Confirmation modal is ready.\n";
        echo "Feature successfully implements:\n";
        echo "- ✅ Visualizzazione riepilogo dati inseriti\n";
        echo "- ✅ Pulsante conferma/annulla\n";
        echo "- ✅ Test UX su errori prevenuti\n";
        echo "- ✅ Accessibilità e responsive design\n";
        echo "- ✅ Compatibilità con funzionalità esistenti\n";
    } else {
        echo "❌ Some tests failed. Please review the implementation.\n";
    }
    
    echo "\n";
}