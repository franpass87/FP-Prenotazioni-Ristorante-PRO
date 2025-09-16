<?php
/**
 * Database Cleanup for Calendar Issue
 * 
 * This file contains functions to diagnose and fix the WordPress database
 * configuration that causes all calendar dates to appear disabled.
 * 
 * Use this ONLY if the JavaScript fix doesn't solve the issue permanently.
 */

/**
 * Diagnose calendar configuration issues
 * 
 * @return array Array of diagnostic information
 */
function rbf_diagnose_calendar_config() {
    $options = rbf_get_settings();
    $diagnostics = [
        'issues' => [],
        'warnings' => [],
        'recommendations' => []
    ];
    
    // Check opening hours configuration
    $closed_days_map = ['sun'=>0,'mon'=>1,'tue'=>2,'wed'=>3,'thu'=>4,'fri'=>5,'sat'=>6];
    $closed_days = [];
    $open_values = ['yes', '1', 'true', 'on'];
    
    foreach ($closed_days_map as $key => $day_index) {
        $is_open_raw = $options["open_{$key}"] ?? 'yes';
        $is_open = in_array(strtolower((string)$is_open_raw), $open_values, true);
        if (!$is_open) {
            $closed_days[] = $day_index;
        }
    }
    
    // CRITICAL: All days closed
    if (count($closed_days) >= 7) {
        $diagnostics['issues'][] = [
            'type' => 'critical',
            'message' => 'All 7 days of the week are marked as closed',
            'details' => 'This causes ALL calendar dates to be disabled',
            'affected_options' => array_keys($closed_days_map),
            'current_values' => $closed_days
        ];
    }
    
    // WARNING: Most days closed
    if (count($closed_days) >= 5) {
        $diagnostics['warnings'][] = [
            'type' => 'warning', 
            'message' => 'Most days of the week are marked as closed',
            'details' => 'This severely limits available booking dates',
            'closed_count' => count($closed_days)
        ];
    }
    
    // Check closed specific dates
    $closed_specific = rbf_get_closed_specific($options);
    
    // WARNING: Too many single closed dates
    if (count($closed_specific['singles']) > 100) {
        $diagnostics['warnings'][] = [
            'type' => 'performance',
            'message' => 'Too many individual closed dates',
            'details' => 'Consider using date ranges instead',
            'count' => count($closed_specific['singles'])
        ];
    }
    
    // Check for extremely long date ranges
    if (!empty($closed_specific['ranges'])) {
        foreach ($closed_specific['ranges'] as $range) {
            if ($range['from'] && $range['to']) {
                $from_date = new DateTime($range['from']);
                $to_date = new DateTime($range['to']);
                $diff_days = $from_date->diff($to_date)->days;
                
                if ($diff_days > 730) { // More than 2 years
                    $diagnostics['issues'][] = [
                        'type' => 'critical',
                        'message' => 'Extremely long closed date range detected',
                        'details' => "Range from {$range['from']} to {$range['to']} spans {$diff_days} days",
                        'recommendation' => 'Review and remove if this is unintentional'
                    ];
                }
            }
        }
    }
    
    // Generate recommendations
    if (!empty($diagnostics['issues'])) {
        $diagnostics['recommendations'][] = 'Run rbf_fix_calendar_config() to apply automatic fixes';
    }
    
    if (count($closed_days) >= 7) {
        $diagnostics['recommendations'][] = 'Set at least some days as "open" in WordPress admin settings';
    }
    
    return $diagnostics;
}

/**
 * Fix critical calendar configuration issues automatically
 * 
 * @return array Results of the fix operation
 */
function rbf_fix_calendar_config() {
    $options = rbf_get_settings();
    $fixes_applied = [];
    $backup_created = false;
    
    // Create backup of current settings
    $backup_key = 'rbf_settings_backup_' . date('Y_m_d_H_i_s');
    update_option($backup_key, $options);
    $backup_created = true;
    
    // Check and fix opening hours
    $closed_days_map = ['sun'=>0,'mon'=>1,'tue'=>2,'wed'=>3,'thu'=>4,'fri'=>5,'sat'=>6];
    $closed_days = [];
    $open_values = ['yes', '1', 'true', 'on'];
    
    foreach ($closed_days_map as $key => $day_index) {
        $is_open_raw = $options["open_{$key}"] ?? 'yes';
        $is_open = in_array(strtolower((string)$is_open_raw), $open_values, true);
        if (!$is_open) {
            $closed_days[] = $day_index;
        }
    }
    
    // FIX 1: If all days are closed, apply safe default (only Monday closed)
    if (count($closed_days) >= 7) {
        // Set safe defaults: open Tuesday through Sunday, closed Monday
        $safe_defaults = [
            'open_sun' => 'yes',
            'open_mon' => 'no',   // Common restaurant closure day
            'open_tue' => 'yes',
            'open_wed' => 'yes', 
            'open_thu' => 'yes',
            'open_fri' => 'yes',
            'open_sat' => 'yes'
        ];
        
        foreach ($safe_defaults as $key => $value) {
            $options[$key] = $value;
        }
        
        $fixes_applied[] = [
            'fix' => 'opening_hours_reset',
            'description' => 'Reset opening hours to safe defaults (Monday closed, all other days open)',
            'old_closed_days' => $closed_days,
            'new_closed_days' => [1] // Only Monday
        ];
    }
    
    // FIX 2: Remove extremely long date ranges
    $closed_specific = rbf_get_closed_specific($options);
    $ranges_to_remove = [];
    
    if (!empty($closed_specific['ranges'])) {
        foreach ($closed_specific['ranges'] as $index => $range) {
            if ($range['from'] && $range['to']) {
                $from_date = new DateTime($range['from']);
                $to_date = new DateTime($range['to']);
                $diff_days = $from_date->diff($to_date)->days;
                
                if ($diff_days > 730) {
                    $ranges_to_remove[] = $index;
                }
            }
        }
    }
    
    if (!empty($ranges_to_remove)) {
        // Remove problematic ranges
        foreach (array_reverse($ranges_to_remove) as $index) {
            unset($closed_specific['ranges'][$index]);
        }
        
        // Rebuild the closed_dates string
        $new_closed_dates = [];
        foreach ($closed_specific['singles'] as $single) {
            $new_closed_dates[] = $single;
        }
        foreach ($closed_specific['ranges'] as $range) {
            $new_closed_dates[] = $range['from'] . ' - ' . $range['to'];
        }
        foreach ($closed_specific['exceptions'] as $exception) {
            $new_closed_dates[] = $exception['date'] . ' [' . $exception['type'] . ']';
        }
        
        $options['closed_dates'] = implode("\n", $new_closed_dates);
        
        $fixes_applied[] = [
            'fix' => 'long_ranges_removed',
            'description' => 'Removed extremely long closed date ranges',
            'removed_count' => count($ranges_to_remove)
        ];
    }
    
    // Save the fixed options
    if (!empty($fixes_applied)) {
        rbf_update_settings($options);
    }
    
    return [
        'success' => true,
        'backup_created' => $backup_created,
        'backup_key' => $backup_created ? $backup_key : null,
        'fixes_applied' => $fixes_applied,
        'message' => count($fixes_applied) > 0 
            ? 'Configuration fixes applied successfully'
            : 'No fixes were needed'
    ];
}

/**
 * Restore settings from a backup
 * 
 * @param string $backup_key The backup option key
 * @return bool Success status
 */
function rbf_restore_calendar_config($backup_key) {
    $backup_data = get_option($backup_key);
    if (!$backup_data) {
        return false;
    }
    
    rbf_update_settings($backup_data);
    return true;
}

/**
 * Admin function to display calendar configuration status
 * This can be called from WordPress admin to show current status
 */
function rbf_display_calendar_diagnostic() {
    $diagnostics = rbf_diagnose_calendar_config();
    
    echo '<div class="rbf-calendar-diagnostic">';
    echo '<h3>🔍 Calendar Configuration Diagnostic</h3>';
    
    if (!empty($diagnostics['issues'])) {
        echo '<div class="notice notice-error"><h4>❌ Critical Issues:</h4>';
        foreach ($diagnostics['issues'] as $issue) {
            echo '<p><strong>' . $issue['message'] . '</strong><br>';
            echo $issue['details'] . '</p>';
        }
        echo '</div>';
    }
    
    if (!empty($diagnostics['warnings'])) {
        echo '<div class="notice notice-warning"><h4>⚠️ Warnings:</h4>';
        foreach ($diagnostics['warnings'] as $warning) {
            echo '<p><strong>' . $warning['message'] . '</strong><br>';
            echo $warning['details'] . '</p>';
        }
        echo '</div>';
    }
    
    if (empty($diagnostics['issues']) && empty($diagnostics['warnings'])) {
        echo '<div class="notice notice-success">';
        echo '<p>✅ <strong>Calendar configuration looks good!</strong></p>';
        echo '</div>';
    }
    
    if (!empty($diagnostics['recommendations'])) {
        echo '<div class="notice notice-info"><h4>💡 Recommendations:</h4>';
        foreach ($diagnostics['recommendations'] as $rec) {
            echo '<p>' . $rec . '</p>';
        }
        echo '</div>';
    }
    
    echo '</div>';
}

/**
 * AJAX handler for fixing calendar configuration
 * This can be called via WordPress AJAX to apply fixes
 */
function rbf_ajax_fix_calendar_config() {
    // Security check
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    check_ajax_referer('rbf_admin_nonce', 'nonce');
    
    $result = rbf_fix_calendar_config();
    wp_send_json($result);
}
add_action('wp_ajax_rbf_fix_calendar_config', 'rbf_ajax_fix_calendar_config');
?>