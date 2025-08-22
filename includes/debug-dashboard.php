<?php
/**
 * Admin Dashboard with Debug and Performance Analytics
 * 
 * @package FP_Prenotazioni_Ristorante_PRO
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add debug dashboard to admin menu
 */
add_action('admin_menu', 'rbf_add_debug_dashboard');
function rbf_add_debug_dashboard() {
    // Only show debug dashboard if debugging is enabled
    if (!RBF_DEBUG || !current_user_can('manage_options')) {
        return;
    }
    
    add_submenu_page(
        'edit.php?post_type=rbf_booking',
        'Debug & Performance',
        '🔧 Debug',
        'manage_options',
        'rbf-debug-dashboard',
        'rbf_render_debug_dashboard'
    );
}

/**
 * Handle AJAX requests for debug dashboard
 */
add_action('wp_ajax_rbf_clear_debug_logs', 'rbf_ajax_clear_debug_logs');
function rbf_ajax_clear_debug_logs() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    
    check_ajax_referer('rbf_debug_nonce');
    
    if (class_exists('RBF_Debug_Logger')) {
        $success = RBF_Debug_Logger::clear_logs();
        wp_send_json_success(['cleared' => $success]);
    } else {
        wp_send_json_error('Debug Logger not available');
    }
}

add_action('wp_ajax_rbf_export_debug_logs', 'rbf_ajax_export_debug_logs');
function rbf_ajax_export_debug_logs() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    
    check_ajax_referer('rbf_debug_nonce');
    
    if (class_exists('RBF_Debug_Logger')) {
        $export_data = RBF_Debug_Logger::export_logs();
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="rbf-debug-logs-' . date('Y-m-d-H-i-s') . '.json"');
        echo $export_data;
        exit;
    }
    
    wp_send_json_error('Debug Logger not available');
}

/**
 * Render debug dashboard page
 */
function rbf_render_debug_dashboard() {
    $debug_stats = class_exists('RBF_Debug_Logger') ? RBF_Debug_Logger::get_log_stats() : [];
    $recent_logs = class_exists('RBF_Debug_Logger') ? RBF_Debug_Logger::get_recent_logs(20) : [];
    $performance_report = class_exists('RBF_Performance_Monitor') ? RBF_Performance_Monitor::generate_performance_report(7) : [];
    
    ?>
    <div class="wrap">
        <h1>🔧 Debug & Performance Dashboard</h1>
        <p>Sistema di monitoraggio avanzato per le prestazioni del plugin e le integrazioni API.</p>
        
        <?php if (!RBF_DEBUG): ?>
            <div class="notice notice-warning">
                <p><strong>Debug Mode Disabled:</strong> Per attivare il debug, aggiungi <code>define('RBF_DEBUG', true);</code> nel tuo wp-config.php</p>
            </div>
        <?php else: ?>
            
            <!-- Debug Stats Cards -->
            <div class="rbf-dashboard-stats" style="display: flex; gap: 20px; margin: 20px 0;">
                <div class="rbf-stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); flex: 1;">
                    <h3 style="margin: 0 0 10px 0; color: #1d4ed8;">📊 Log Totali</h3>
                    <div style="font-size: 24px; font-weight: bold;"><?php echo $debug_stats['total_logs'] ?? 0; ?></div>
                    <small>Oggi: <?php echo $debug_stats['today_logs'] ?? 0; ?></small>
                </div>
                
                <div class="rbf-stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); flex: 1;">
                    <h3 style="margin: 0 0 10px 0; color: #dc2626;">⚠️ Errori</h3>
                    <div style="font-size: 24px; font-weight: bold; color: #dc2626;"><?php echo $debug_stats['error_count'] ?? 0; ?></div>
                    <small>Warning: <?php echo $debug_stats['warning_count'] ?? 0; ?></small>
                </div>
                
                <div class="rbf-stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); flex: 1;">
                    <h3 style="margin: 0 0 10px 0; color: #059669;">🚀 API Calls</h3>
                    <div style="font-size: 24px; font-weight: bold; color: #059669;"><?php echo $debug_stats['api_calls'] ?? 0; ?></div>
                    <small>Perf. Media: <?php echo $debug_stats['avg_performance'] ?? 0; ?>s</small>
                </div>
                
                <div class="rbf-stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); flex: 1;">
                    <h3 style="margin: 0 0 10px 0; color: #7c3aed;">📡 Platforms</h3>
                    <div style="font-size: 12px;">
                        GA4: <?php echo $debug_stats['platforms']['ga4'] ?? 0; ?><br>
                        Meta: <?php echo $debug_stats['platforms']['meta'] ?? 0; ?><br>
                        Brevo: <?php echo $debug_stats['platforms']['brevo'] ?? 0; ?>
                    </div>
                </div>
            </div>
            
            <!-- Performance Report -->
            <?php if (!empty($performance_report['summary'])): ?>
            <div class="rbf-performance-section" style="background: #fff; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h2>📈 Performance Report (Ultimi 7 giorni)</h2>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0;">
                    <div>
                        <h4>🔧 Operazioni</h4>
                        <div style="background: #f8fafc; padding: 15px; border-radius: 6px;">
                            <p><strong>Totale Operazioni:</strong> <?php echo $performance_report['summary']['total_operations']; ?></p>
                            <p><strong>Tempo Medio:</strong> <?php echo number_format($performance_report['summary']['avg_operation_time'], 4); ?>s</p>
                        </div>
                    </div>
                    
                    <div>
                        <h4>📡 API Calls</h4>
                        <div style="background: #f8fafc; padding: 15px; border-radius: 6px;">
                            <p><strong>Totale API Calls:</strong> <?php echo $performance_report['summary']['total_api_calls']; ?></p>
                            <p><strong>Success Rate:</strong> <?php echo number_format($performance_report['summary']['success_rate'], 2); ?>%</p>
                            <p><strong>Tempo Medio API:</strong> <?php echo number_format($performance_report['summary']['avg_api_time'], 4); ?>s</p>
                        </div>
                    </div>
                </div>
                
                <!-- API Performance Details -->
                <?php if (!empty($performance_report['apis'])): ?>
                <h4>📊 Dettaglio Performance API</h4>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Platform</th>
                            <th>Calls</th>
                            <th>Avg Duration</th>
                            <th>Success Rate</th>
                            <th>Errors</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($performance_report['apis'] as $platform => $data): ?>
                        <tr>
                            <td><strong><?php echo esc_html(strtoupper($platform)); ?></strong></td>
                            <td><?php echo $data['calls']; ?></td>
                            <td><?php echo number_format($data['avg_duration'], 4); ?>s</td>
                            <td>
                                <span style="color: <?php echo $data['success_rate'] > 95 ? '#059669' : ($data['success_rate'] > 90 ? '#d97706' : '#dc2626'); ?>">
                                    <?php echo number_format($data['success_rate'], 2); ?>%
                                </span>
                            </td>
                            <td><?php echo $data['errors']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Recent Logs Section -->
            <div class="rbf-logs-section" style="background: #fff; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2>📝 Log Recenti (Ultimi 20)</h2>
                    <div>
                        <button id="rbf-clear-logs" class="button button-secondary" style="margin-right: 10px;">🗑️ Clear Logs</button>
                        <button id="rbf-export-logs" class="button button-primary">📥 Export JSON</button>
                    </div>
                </div>
                
                <?php if (empty($recent_logs)): ?>
                    <p style="color: #666; font-style: italic;">Nessun log disponibile.</p>
                <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th width="15%">Timestamp</th>
                            <th width="12%">Level</th>
                            <th width="15%">Event Type</th>
                            <th width="10%">Booking ID</th>
                            <th width="12%">Source</th>
                            <th width="36%">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_reverse($recent_logs) as $log): ?>
                        <tr>
                            <td style="font-size: 11px;"><?php echo esc_html($log['timestamp']); ?></td>
                            <td>
                                <span style="
                                    padding: 2px 8px; 
                                    border-radius: 4px; 
                                    font-size: 11px; 
                                    font-weight: bold;
                                    color: white;
                                    background: <?php 
                                        echo $log['level'] === 'ERROR' ? '#dc2626' : 
                                            ($log['level'] === 'WARNING' ? '#d97706' : 
                                            ($log['level'] === 'INFO' ? '#059669' : '#6b7280')); 
                                    ?>
                                ">
                                    <?php echo esc_html($log['level']); ?>
                                </span>
                            </td>
                            <td><code style="font-size: 11px;"><?php echo esc_html($log['event_type']); ?></code></td>
                            <td><?php echo $log['booking_id'] ? '#' . $log['booking_id'] : '-'; ?></td>
                            <td>
                                <?php if ($log['source_bucket']): ?>
                                    <span style="background: #e5e7eb; padding: 2px 6px; border-radius: 3px; font-size: 11px;">
                                        <?php echo esc_html($log['source_bucket']); ?>
                                    </span>
                                <?php else: echo '-'; endif; ?>
                            </td>
                            <td style="font-size: 11px;">
                                <?php if (isset($log['data']['message'])): ?>
                                    <?php echo esc_html($log['data']['message']); ?>
                                <?php elseif (isset($log['data']['error'])): ?>
                                    <span style="color: #dc2626;"><?php echo esc_html($log['data']['error']); ?></span>
                                <?php elseif (isset($log['performance'])): ?>
                                    <span style="color: #7c3aed;">Exec: <?php echo $log['performance']['execution_time']; ?>, Mem: <?php echo $log['performance']['memory_usage']; ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
            
        <?php endif; ?>
    </div>
    
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        const nonce = '<?php echo wp_create_nonce('rbf_debug_nonce'); ?>';
        
        $('#rbf-clear-logs').click(function() {
            if (!confirm('Sei sicuro di voler cancellare tutti i log di debug?')) return;
            
            $.post(ajaxurl, {
                action: 'rbf_clear_debug_logs',
                _ajax_nonce: nonce
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Errore durante la cancellazione dei log');
                }
            });
        });
        
        $('#rbf-export-logs').click(function() {
            window.location.href = ajaxurl + '?action=rbf_export_debug_logs&_ajax_nonce=' + nonce;
        });
    });
    </script>
    
    <style>
    .rbf-dashboard-stats {
        margin: 20px 0;
    }
    
    .rbf-stat-card {
        transition: transform 0.2s ease;
    }
    
    .rbf-stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15) !important;
    }
    
    .wp-list-table th {
        background: #f8fafc;
        font-weight: 600;
    }
    
    .wp-list-table tbody tr:hover {
        background: #f8fafc;
    }
    </style>
    <?php
}