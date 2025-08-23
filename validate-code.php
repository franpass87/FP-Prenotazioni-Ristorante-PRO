#!/usr/bin/env php
<?php
/**
 * Simple code validation script
 */

echo "=== RBF Code Validation ===\n\n";

// Test basic syntax for all PHP files
$phpFiles = [
    'fp-prenotazioni-ristorante-pro.php',
    'includes/admin.php',
    'includes/booking-handler.php', 
    'includes/frontend.php',
    'includes/integrations.php',
    'includes/utils.php'
];

$syntaxErrors = 0;

echo "1. PHP Syntax Check:\n";
foreach ($phpFiles as $file) {
    if (file_exists($file)) {
        $output = [];
        $returnCode = 0;
        exec("php -l \"$file\" 2>&1", $output, $returnCode);
        if ($returnCode === 0) {
            echo "✅ $file - OK\n";
        } else {
            echo "❌ $file - ERROR: " . implode(' ', $output) . "\n";
            $syntaxErrors++;
        }
    } else {
        echo "⚠️  $file - Not found\n";
    }
}

echo "\n2. Function Completeness Check:\n";

// Check if critical functions exist
$criticalFunctions = [
    'rbf_get_settings',
    'rbf_current_lang', 
    'rbf_wp_timezone',
    'rbf_translate_string',
    'rbf_handle_error',
    'rbf_handle_success'
];

$missingFunctions = 0;
include_once 'includes/utils.php';

foreach ($criticalFunctions as $func) {
    if (function_exists($func)) {
        echo "✅ $func - Defined\n";
    } else {
        echo "❌ $func - Missing\n";
        $missingFunctions++;
    }
}

echo "\n3. Security Pattern Check:\n";

// Check for potential security issues
$securityPatterns = [
    '$_GET[' => 'Direct superglobal access',
    '$_POST[' => 'Direct superglobal access', 
    '$_REQUEST[' => 'Direct superglobal access',
    'mysql_' => 'Deprecated MySQL functions',
    'eval(' => 'Dangerous eval() usage',
    'exec(' => 'Command execution'
];

$securityIssues = 0;
foreach ($phpFiles as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        foreach ($securityPatterns as $pattern => $description) {
            if (strpos($content, $pattern) !== false) {
                // Check if it's properly sanitized
                if ($pattern === '$_GET[' || $pattern === '$_POST[' || $pattern === '$_REQUEST[') {
                    $lines = explode("\n", $content);
                    $lineNumber = 0;
                    foreach ($lines as $i => $line) {
                        if (strpos($line, $pattern) !== false) {
                            $lineNumber = $i + 1;
                            // Check if line contains sanitization
                            if (strpos($line, 'sanitize_') !== false || strpos($line, 'wp_verify_nonce') !== false) {
                                echo "✅ $file:$lineNumber - $pattern (sanitized)\n";
                            } else {
                                echo "⚠️  $file:$lineNumber - $pattern (check sanitization)\n";
                                $securityIssues++;
                            }
                            break;
                        }
                    }
                } else {
                    echo "⚠️  $file - $description\n";
                    $securityIssues++;
                }
            }
        }
    }
}

echo "\n4. Performance Check:\n";
// Check for common performance issues
$performancePatterns = [
    'get_option(' => 'Database calls - should be cached if repeated'
];

$performanceWarnings = 0;
foreach ($phpFiles as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $getOptionCount = substr_count($content, 'get_option(');
        if ($getOptionCount > 5) {
            echo "⚠️  $file - $getOptionCount get_option() calls (consider caching)\n";
            $performanceWarnings++;
        } else {
            echo "✅ $file - $getOptionCount get_option() calls (acceptable)\n";
        }
    }
}

// Summary
echo "\n=== VALIDATION SUMMARY ===\n";
echo "Syntax Errors: $syntaxErrors\n";
echo "Missing Functions: $missingFunctions\n";
echo "Security Issues: $securityIssues\n";
echo "Performance Warnings: $performanceWarnings\n";

$totalIssues = $syntaxErrors + $missingFunctions + $securityIssues;
if ($totalIssues === 0) {
    echo "\n🎉 All checks passed! Code is ready for production.\n";
    exit(0);
} else {
    echo "\n⚠️  $totalIssues issues found. Review before deployment.\n";
    exit(1);
}