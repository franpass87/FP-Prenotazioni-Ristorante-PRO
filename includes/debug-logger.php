<?php
/**
 * Debug and Performance Logging System for Restaurant Booking Plugin
 * 
 * @package FP_Prenotazioni_Ristorante_PRO
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Advanced debugging and logging system for tracking operations
 */
class RBF_Debug_Logger {
    private static $enabled = false;
    private static $log_level = 'INFO';
    private static $initialized = false;
    
    // Log levels with numeric values for comparison
    const LOG_LEVELS = [
        'DEBUG' => 0,
        'INFO' => 1, 
        'WARNING' => 2,
        'ERROR' => 3
    ];
    
    /**
     * Initialize the logger
     */
    public static function init() {
        if (self::$initialized) return;
        
        // Check if we're in WordPress environment to access database settings
        if (function_exists('get_option')) {
            // Use the new helper functions that check database first
            self::$enabled = rbf_is_debug_enabled();
            self::$log_level = rbf_get_debug_log_level();
        } else {
            // Fallback for non-WordPress environments
            self::$enabled = defined('RBF_DEBUG') && RBF_DEBUG;
            self::$log_level = defined('RBF_LOG_LEVEL') ? RBF_LOG_LEVEL : 'INFO';
        }
        
        self::$initialized = true;
        
        // Only schedule WordPress cron if we're in WordPress environment
        if (function_exists('wp_next_scheduled') && !wp_next_scheduled('rbf_cleanup_debug_logs')) {
            wp_schedule_event(time(), 'daily', 'rbf_cleanup_debug_logs');
        }
        if (function_exists('add_action')) {
            add_action('rbf_cleanup_debug_logs', [self::class, 'cleanup_old_logs']);
        }
    }
    
    /**
     * Track a tracking event with detailed information
     */
    public static function track_event($event_type, $data = [], $level = 'INFO') {
        self::init();
        
        if (!self::should_log($level)) return;
        
        $log_entry = [
            'timestamp' => current_time('mysql'),
            'event_type' => $event_type,
            'level' => $level,
            'booking_id' => $data['booking_id'] ?? null,
            'source_bucket' => $data['bucket'] ?? $data['source_bucket'] ?? null,
            'platforms' => [
                'ga4' => self::get_platform_status('ga4_id'),
                'meta' => self::get_platform_status('meta_pixel_id'),
                'brevo' => self::get_platform_status('brevo_api')
            ],
            'performance' => [
                'memory_usage' => self::format_bytes(memory_get_usage(true)),
                'memory_peak' => self::format_bytes(memory_get_peak_usage(true)),
                'execution_time' => round(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 4) . 's'
            ],
            'data' => $data
        ];
        
        self::save_log_entry($log_entry);
        
        // Also log to WordPress error log if enabled and level is WARNING or higher
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG && self::LOG_LEVELS[$level] >= self::LOG_LEVELS['WARNING']) {
            error_log('RBF_TRACKING [' . $level . ']: ' . json_encode([
                'event' => $event_type,
                'booking_id' => $data['booking_id'] ?? null,
                'message' => $data['message'] ?? 'No message provided'
            ]));
        }
    }
    
    /**
     * Track API call performance
     */
    public static function track_api_call($platform, $endpoint, $duration, $success, $response_data = []) {
        self::track_event('api_call', [
            'platform' => $platform,
            'endpoint' => $endpoint,
            'duration' => round($duration, 4) . 's',
            'success' => $success,
            'response_size' => isset($response_data['body']) ? strlen($response_data['body']) : 0,
            'http_code' => $response_data['response']['code'] ?? null
        ], $success ? 'INFO' : 'WARNING');
    }
    
    /**
     * Track performance metrics
     */
    public static function track_performance($operation, $start_time, $additional_data = []) {
        $duration = microtime(true) - $start_time;
        $level = $duration > 2.0 ? 'WARNING' : 'INFO'; // Slow operations are warnings
        
        self::track_event('performance', array_merge([
            'operation' => $operation,
            'duration' => round($duration, 4) . 's',
            'slow_operation' => $duration > 2.0
        ], $additional_data), $level);
    }
    
    /**
     * Get recent debug logs for admin dashboard
     */
    public static function get_recent_logs($limit = 50) {
        if (!current_user_can('manage_options')) return [];
        
        $logs = get_option('rbf_debug_logs', []);
        return array_slice($logs, -$limit);
    }
    
    /**
     * Get log statistics for dashboard
     */
    public static function get_log_stats() {
        if (!current_user_can('manage_options')) return [];
        
        $logs = get_option('rbf_debug_logs', []);
        $today = date('Y-m-d');
        $stats = [
            'total_logs' => count($logs),
            'today_logs' => 0,
            'error_count' => 0,
            'warning_count' => 0,
            'platforms' => ['ga4' => 0, 'meta' => 0, 'brevo' => 0],
            'api_calls' => 0,
            'avg_performance' => 0
        ];
        
        $performance_times = [];
        
        foreach ($logs as $log) {
            // Count today's logs
            if (strpos($log['timestamp'], $today) === 0) {
                $stats['today_logs']++;
            }
            
            // Count by level
            if ($log['level'] === 'ERROR') $stats['error_count']++;
            if ($log['level'] === 'WARNING') $stats['warning_count']++;
            
            // Count platform usage
            if ($log['event_type'] === 'api_call' && isset($log['data']['platform'])) {
                $platform = $log['data']['platform'];
                if (isset($stats['platforms'][$platform])) {
                    $stats['platforms'][$platform]++;
                }
                $stats['api_calls']++;
            }
            
            // Collect performance data
            if ($log['event_type'] === 'performance' && isset($log['data']['duration'])) {
                $duration = floatval(str_replace('s', '', $log['data']['duration']));
                $performance_times[] = $duration;
            }
        }
        
        // Calculate average performance
        if (!empty($performance_times)) {
            $stats['avg_performance'] = round(array_sum($performance_times) / count($performance_times), 4);
        }
        
        return $stats;
    }
    
    /**
     * Clear all debug logs
     */
    public static function clear_logs() {
        if (!current_user_can('manage_options')) return false;
        
        delete_option('rbf_debug_logs');
        self::track_event('logs_cleared', [], 'INFO');
        return true;
    }
    
    /**
     * Export logs as JSON
     */
    public static function export_logs() {
        if (!current_user_can('manage_options')) return false;
        
        $logs = get_option('rbf_debug_logs', []);
        $export_data = [
            'timestamp' => current_time('mysql'),
            'version' => get_option('rbf_version', RBF_VERSION),
            'logs' => $logs,
            'stats' => self::get_log_stats()
        ];
        
        return json_encode($export_data, JSON_PRETTY_PRINT);
    }
    
    // Private methods
    
    private static function should_log($level) {
        if (!self::$enabled) return false;
        
        return self::LOG_LEVELS[$level] >= self::LOG_LEVELS[self::$log_level];
    }
    
    private static function get_platform_status($setting_key) {
        $options = get_option('rbf_settings', []);
        return !empty($options[$setting_key]) ? 'enabled' : 'disabled';
    }
    
    /**
     * Format bytes for display (public method)
     */
    public static function format_bytes($size) {
        $units = ['B', 'KB', 'MB', 'GB'];
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        return round($size, 2) . ' ' . $units[$i];
    }
    
    private static function save_log_entry($log_entry) {
        if (!function_exists('get_option')) {
            return; // Not in WordPress environment
        }
        
        $logs = get_option('rbf_debug_logs', []);
        $logs[] = $log_entry;
        
        // Keep only last 200 entries to prevent option bloat
        if (count($logs) > 200) {
            $logs = array_slice($logs, -200);
        }
        
        update_option('rbf_debug_logs', $logs, false);
    }
    
    /**
     * Cleanup old logs (called daily via cron)
     */
    public static function cleanup_old_logs() {
        // Check if auto cleanup is enabled
        if (function_exists('get_option')) {
            $settings = get_option('rbf_settings', []);
            $auto_cleanup = $settings['debug_auto_cleanup'] ?? 'yes';
            $cleanup_days = $settings['debug_cleanup_days'] ?? 7;
            
            if ($auto_cleanup !== 'yes') {
                return; // Auto cleanup disabled
            }
        } else {
            // Fallback for non-WordPress environments
            $cleanup_days = 7;
        }
        
        $logs = get_option('rbf_debug_logs', []);
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$cleanup_days} days"));
        
        $filtered_logs = array_filter($logs, function($log) use ($cutoff_date) {
            return $log['timestamp'] >= $cutoff_date;
        });
        
        // If we removed logs, update the option
        if (count($filtered_logs) < count($logs)) {
            update_option('rbf_debug_logs', $filtered_logs, false);
            self::track_event('logs_cleanup', [
                'removed_count' => count($logs) - count($filtered_logs),
                'remaining_count' => count($filtered_logs),
                'cleanup_days' => $cleanup_days
            ], 'INFO');
        }
    }
}

// Note: Logger initialization is handled by rbf_load_modules() in the main plugin file