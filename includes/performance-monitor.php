<?php
/**
 * Performance Monitoring System for Restaurant Booking Plugin
 * 
 * @package FP_Prenotazioni_Ristorante_PRO
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Performance monitoring and API metrics tracking
 */
class RBF_Performance_Monitor {
    private static $timing_operations = [];
    private static $initialized = false;
    
    /**
     * Initialize the performance monitor
     */
    public static function init() {
        if (self::$initialized) return;
        
        // Only schedule WordPress cron if we're in WordPress environment
        if (function_exists('wp_next_scheduled') && !wp_next_scheduled('rbf_aggregate_metrics')) {
            wp_schedule_event(time(), 'daily', 'rbf_aggregate_metrics');
        }
        if (function_exists('add_action')) {
            add_action('rbf_aggregate_metrics', [self::class, 'aggregate_daily_metrics']);
        }
        
        self::$initialized = true;
    }
    
    /**
     * Start timing an operation
     */
    public static function start_timing($operation) {
        self::$timing_operations[$operation] = [
            'start' => microtime(true),
            'memory_start' => memory_get_usage(true)
        ];
        
        RBF_Debug_Logger::track_event('timing_start', [
            'operation' => $operation,
            'memory_at_start' => RBF_Debug_Logger::format_bytes(memory_get_usage(true))
        ], 'DEBUG');
    }
    
    /**
     * End timing an operation and log results
     */
    public static function end_timing($operation, $additional_data = []) {
        if (!isset(self::$timing_operations[$operation])) {
            RBF_Debug_Logger::track_event('timing_error', [
                'operation' => $operation,
                'message' => 'Timing started but not found'
            ], 'WARNING');
            return null;
        }
        
        $timing = self::$timing_operations[$operation];
        $duration = microtime(true) - $timing['start'];
        $memory_used = memory_get_usage(true) - $timing['memory_start'];
        
        unset(self::$timing_operations[$operation]);
        
        // Log performance data
        RBF_Debug_Logger::track_performance($operation, $timing['start'], array_merge([
            'memory_used' => RBF_Debug_Logger::format_bytes($memory_used),
            'memory_peak_during' => RBF_Debug_Logger::format_bytes(memory_get_peak_usage(true))
        ], $additional_data));
        
        // Store performance metrics
        self::store_performance_metric($operation, $duration, $memory_used);
        
        return $duration;
    }
    
    /**
     * Track API call performance with detailed metrics
     */
    public static function track_api_call($platform, $endpoint, $start_time, $response, $success = null) {
        $duration = microtime(true) - $start_time;
        
        // Determine success from response if not provided
        if ($success === null) {
            $success = !is_wp_error($response) && 
                      isset($response['response']['code']) && 
                      $response['response']['code'] >= 200 && 
                      $response['response']['code'] < 300;
        }
        
        $response_data = [
            'platform' => $platform,
            'endpoint' => $endpoint,
            'duration' => $duration,
            'success' => $success,
            'http_code' => null,
            'response_size' => 0,
            'error_message' => null
        ];
        
        // Extract response details
        if (is_wp_error($response)) {
            $response_data['error_message'] = $response->get_error_message();
            $response_data['error_code'] = $response->get_error_code();
        } elseif (is_array($response)) {
            $response_data['http_code'] = $response['response']['code'] ?? null;
            $response_data['response_size'] = isset($response['body']) ? strlen($response['body']) : 0;
        }
        
        // Log to debug logger
        RBF_Debug_Logger::track_api_call(
            $platform, 
            $endpoint, 
            $duration, 
            $success, 
            $response_data
        );
        
        // Store API metrics
        self::store_api_metrics($platform, $duration, $success, $response_data);
        
        return $response_data;
    }
    
    /**
     * Get performance metrics for dashboard
     */
    public static function get_performance_metrics($days = 7) {
        if (!current_user_can('manage_options')) return [];
        
        $metrics = get_option('rbf_performance_metrics', []);
        $cutoff_date = date('Y-m-d', strtotime("-{$days} days"));
        
        // Filter metrics by date range
        $filtered_metrics = array_filter($metrics, function($date) use ($cutoff_date) {
            return $date >= $cutoff_date;
        }, ARRAY_FILTER_USE_KEY);
        
        return $filtered_metrics;
    }
    
    /**
     * Get API performance metrics
     */
    public static function get_api_metrics($days = 7) {
        if (!current_user_can('manage_options')) return [];
        
        $metrics = get_option('rbf_api_metrics', []);
        $cutoff_date = date('Y-m-d', strtotime("-{$days} days"));
        
        $filtered_metrics = array_filter($metrics, function($date) use ($cutoff_date) {
            return $date >= $cutoff_date;
        }, ARRAY_FILTER_USE_KEY);
        
        return $filtered_metrics;
    }
    
    /**
     * Generate performance report
     */
    public static function generate_performance_report($days = 7) {
        if (!current_user_can('manage_options')) return [];
        
        $perf_metrics = self::get_performance_metrics($days);
        $api_metrics = self::get_api_metrics($days);
        
        $report = [
            'period' => [
                'days' => $days,
                'start_date' => date('Y-m-d', strtotime("-{$days} days")),
                'end_date' => date('Y-m-d')
            ],
            'operations' => [],
            'apis' => [],
            'summary' => [
                'total_operations' => 0,
                'total_api_calls' => 0,
                'avg_operation_time' => 0,
                'avg_api_time' => 0,
                'success_rate' => 0
            ]
        ];
        
        // Aggregate operation metrics
        $all_operations = [];
        foreach ($perf_metrics as $date => $daily_metrics) {
            foreach ($daily_metrics as $operation => $data) {
                if (!isset($all_operations[$operation])) {
                    $all_operations[$operation] = [
                        'calls' => 0,
                        'total_duration' => 0,
                        'total_memory' => 0,
                        'avg_duration' => 0,
                        'avg_memory' => 0
                    ];
                }
                
                $all_operations[$operation]['calls'] += $data['calls'];
                $all_operations[$operation]['total_duration'] += $data['total_duration'];
                $all_operations[$operation]['total_memory'] += $data['total_memory'];
            }
        }
        
        // Calculate averages
        foreach ($all_operations as $operation => $data) {
            if ($data['calls'] > 0) {
                $all_operations[$operation]['avg_duration'] = $data['total_duration'] / $data['calls'];
                $all_operations[$operation]['avg_memory'] = $data['total_memory'] / $data['calls'];
            }
        }
        
        $report['operations'] = $all_operations;
        
        // Aggregate API metrics
        $all_apis = [];
        $total_api_calls = 0;
        $total_api_success = 0;
        
        foreach ($api_metrics as $date => $daily_apis) {
            foreach ($daily_apis as $platform => $data) {
                if (!isset($all_apis[$platform])) {
                    $all_apis[$platform] = [
                        'calls' => 0,
                        'total_duration' => 0,
                        'errors' => 0,
                        'avg_duration' => 0,
                        'success_rate' => 0
                    ];
                }
                
                $all_apis[$platform]['calls'] += $data['calls'];
                $all_apis[$platform]['total_duration'] += $data['total_duration'];
                $all_apis[$platform]['errors'] += $data['errors'];
                
                $total_api_calls += $data['calls'];
                $total_api_success += ($data['calls'] - $data['errors']);
            }
        }
        
        // Calculate API averages
        foreach ($all_apis as $platform => $data) {
            if ($data['calls'] > 0) {
                $all_apis[$platform]['avg_duration'] = $data['total_duration'] / $data['calls'];
                $all_apis[$platform]['success_rate'] = (($data['calls'] - $data['errors']) / $data['calls']) * 100;
            }
        }
        
        $report['apis'] = $all_apis;
        
        // Calculate summary
        $total_operations = array_sum(array_column($all_operations, 'calls'));
        $total_operation_time = array_sum(array_column($all_operations, 'total_duration'));
        $total_api_time = array_sum(array_column($all_apis, 'total_duration'));
        
        $report['summary'] = [
            'total_operations' => $total_operations,
            'total_api_calls' => $total_api_calls,
            'avg_operation_time' => $total_operations > 0 ? $total_operation_time / $total_operations : 0,
            'avg_api_time' => $total_api_calls > 0 ? $total_api_time / $total_api_calls : 0,
            'success_rate' => $total_api_calls > 0 ? ($total_api_success / $total_api_calls) * 100 : 0
        ];
        
        return $report;
    }
    
    // Private methods
    
    private static function store_performance_metric($operation, $duration, $memory_used) {
        self::init();
        
        if (!function_exists('get_option')) {
            return; // Not in WordPress environment
        }
        
        $today = date('Y-m-d');
        $metrics = get_option('rbf_performance_metrics', []);
        
        if (!isset($metrics[$today])) {
            $metrics[$today] = [];
        }
        
        if (!isset($metrics[$today][$operation])) {
            $metrics[$today][$operation] = [
                'calls' => 0,
                'total_duration' => 0,
                'total_memory' => 0,
                'avg_duration' => 0,
                'avg_memory' => 0
            ];
        }
        
        $metrics[$today][$operation]['calls']++;
        $metrics[$today][$operation]['total_duration'] += $duration;
        $metrics[$today][$operation]['total_memory'] += $memory_used;
        
        // Calculate averages
        $calls = $metrics[$today][$operation]['calls'];
        $metrics[$today][$operation]['avg_duration'] = $metrics[$today][$operation]['total_duration'] / $calls;
        $metrics[$today][$operation]['avg_memory'] = $metrics[$today][$operation]['total_memory'] / $calls;
        
        // Keep only last 30 days
        if (count($metrics) > 30) {
            $metrics = array_slice($metrics, -30, 30, true);
        }
        
        update_option('rbf_performance_metrics', $metrics, false);
    }
    
    private static function store_api_metrics($platform, $duration, $success, $response_data) {
        self::init();
        
        if (!function_exists('get_option')) {
            return; // Not in WordPress environment
        }
        
        $today = date('Y-m-d');
        $metrics = get_option('rbf_api_metrics', []);
        
        if (!isset($metrics[$today])) {
            $metrics[$today] = [];
        }
        
        if (!isset($metrics[$today][$platform])) {
            $metrics[$today][$platform] = [
                'calls' => 0,
                'total_duration' => 0,
                'errors' => 0,
                'avg_duration' => 0,
                'success_rate' => 0
            ];
        }
        
        $metrics[$today][$platform]['calls']++;
        $metrics[$today][$platform]['total_duration'] += $duration;
        if (!$success) $metrics[$today][$platform]['errors']++;
        
        // Calculate derived metrics
        $calls = $metrics[$today][$platform]['calls'];
        $errors = $metrics[$today][$platform]['errors'];
        
        $metrics[$today][$platform]['avg_duration'] = $metrics[$today][$platform]['total_duration'] / $calls;
        $metrics[$today][$platform]['success_rate'] = (($calls - $errors) / $calls) * 100;
        
        // Keep only last 30 days
        if (count($metrics) > 30) {
            $metrics = array_slice($metrics, -30, 30, true);
        }
        
        update_option('rbf_api_metrics', $metrics, false);
    }
    
    /**
     * Daily aggregation of metrics (called via cron)
     */
    public static function aggregate_daily_metrics() {
        // This function could be used for more complex aggregations
        // For now, it just logs that aggregation ran
        RBF_Debug_Logger::track_event('metrics_aggregation', [
            'performance_metrics_days' => count(get_option('rbf_performance_metrics', [])),
            'api_metrics_days' => count(get_option('rbf_api_metrics', []))
        ], 'INFO');
    }
}

// Initialize if debugging is enabled
if (defined('RBF_DEBUG') && RBF_DEBUG) {
    RBF_Performance_Monitor::init();
}