<?php
/**
 * Enhanced Tracking System Integration Test
 * 
 * This file adds any missing integration points and ensures
 * the tracking validation interface is properly accessible.
 * 
 * @package FP_Prenotazioni_Ristorante_PRO
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add comprehensive tracking validation interface to admin menu
 */
add_action('admin_menu', 'rbf_add_comprehensive_tracking_validation_submenu', 15);
function rbf_add_comprehensive_tracking_validation_submenu() {
    // Add submenu under the main RBF menu
    add_submenu_page(
        'rbf_calendar',
        rbf_translate_string('Test Sistema Tracking'),
        rbf_translate_string('Test Sistema Tracking'),
        'manage_options',
        'rbf_comprehensive_tracking_test',
        'rbf_comprehensive_tracking_test_page'
    );
}

/**
 * Comprehensive tracking test page
 */
function rbf_comprehensive_tracking_test_page() {
    // Load required files
    if (!function_exists('rbf_validate_tracking_setup')) {
        require_once RBF_PLUGIN_DIR . 'includes/tracking-validation.php';
    }
    
    if (file_exists(RBF_PLUGIN_DIR . 'tests/comprehensive-tracking-verification.php')) {
        require_once RBF_PLUGIN_DIR . 'tests/comprehensive-tracking-verification.php';
    }
    
    // Handle form submissions
    $test_result = null;
    $validation_test = null;
    $comprehensive_test = null;
    
    if (isset($_POST['run_basic_test']) && wp_verify_nonce($_POST['_wpnonce'], 'rbf_tracking_test')) {
        $test_result = rbf_perform_tracking_test();
    }
    
    if (isset($_POST['run_validation_test']) && wp_verify_nonce($_POST['_wpnonce'], 'rbf_tracking_test')) {
        $validation_test = rbf_validate_tracking_setup();
    }
    
    if (isset($_POST['run_comprehensive_test']) && wp_verify_nonce($_POST['_wpnonce'], 'rbf_tracking_test')) {
        if (class_exists('RBF_Comprehensive_Tracking_Verification')) {
            ob_start();
            $verification = new RBF_Comprehensive_Tracking_Verification();
            $verification->run_verification();
            $comprehensive_test = ob_get_clean();
        } else {
            $comprehensive_test = '<p style="color: #f44336;">Comprehensive verification class not available.</p>';
        }
    }
    
    ?>
    <div class="rbf-admin-wrap">
        <h1><?php echo esc_html(rbf_translate_string('Test Sistema Tracking Completo')); ?></h1>
        
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 30px;">
            <h2><?php echo esc_html(rbf_translate_string('Panoramica Sistema')); ?></h2>
            <p><?php echo esc_html(rbf_translate_string('Questa pagina permette di testare completamente il sistema di tracking per verificare che tutti i componenti funzionino correttamente.')); ?></p>
            
            <?php
            $options = rbf_get_settings();
            $config_summary = [];
            
            if (!empty($options['ga4_id'])) {
                $config_summary[] = '✓ Google Analytics 4 configurato';
            }
            if (!empty($options['gtm_id'])) {
                $config_summary[] = '✓ Google Tag Manager configurato';
            }
            if (($options['gtm_hybrid'] ?? '') === 'yes') {
                $config_summary[] = '✓ Modalità ibrida GTM+GA4 attiva';
            }
            if (!empty($options['meta_pixel_id'])) {
                $config_summary[] = '✓ Facebook Pixel configurato';
            }
            if (!empty($options['meta_access_token'])) {
                $config_summary[] = '✓ Facebook Conversion API configurato';
            }
            
            if (!empty($config_summary)) {
                echo '<div style="background: #f0f9ff; padding: 15px; border-radius: 6px; margin-top: 15px;">';
                echo '<h4 style="margin-top: 0;">Configurazione Rilevata:</h4>';
                echo '<ul style="margin-bottom: 0;">';
                foreach ($config_summary as $item) {
                    echo '<li>' . esc_html($item) . '</li>';
                }
                echo '</ul>';
                echo '</div>';
            }
            ?>
        </div>
        
        <!-- Test Controls -->
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 30px;">
            <h2><?php echo esc_html(rbf_translate_string('Test Sistema Tracking')); ?></h2>
            
            <form method="post" style="margin-bottom: 20px;">
                <?php wp_nonce_field('rbf_tracking_test'); ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                    
                    <div style="border: 1px solid #ddd; padding: 15px; border-radius: 6px;">
                        <h3 style="margin-top: 0;"><?php echo esc_html(rbf_translate_string('Test Base')); ?></h3>
                        <p><?php echo esc_html(rbf_translate_string('Verifica la configurazione base del sistema di tracking.')); ?></p>
                        <input type="submit" name="run_basic_test" class="button button-primary" value="<?php echo esc_attr(rbf_translate_string('Esegui Test Base')); ?>">
                    </div>
                    
                    <div style="border: 1px solid #ddd; padding: 15px; border-radius: 6px;">
                        <h3 style="margin-top: 0;"><?php echo esc_html(rbf_translate_string('Test Validazione')); ?></h3>
                        <p><?php echo esc_html(rbf_translate_string('Esegue la validazione completa del setup di tracking.')); ?></p>
                        <input type="submit" name="run_validation_test" class="button button-primary" value="<?php echo esc_attr(rbf_translate_string('Esegui Validazione')); ?>">
                    </div>
                    
                    <div style="border: 1px solid #ddd; padding: 15px; border-radius: 6px;">
                        <h3 style="margin-top: 0;"><?php echo esc_html(rbf_translate_string('Test Completo')); ?></h3>
                        <p><?php echo esc_html(rbf_translate_string('Verifica approfondita di tutti i componenti del sistema.')); ?></p>
                        <input type="submit" name="run_comprehensive_test" class="button button-primary" value="<?php echo esc_attr(rbf_translate_string('Esegui Test Completo')); ?>">
                    </div>
                    
                </div>
            </form>
        </div>
        
        <!-- Test Results -->
        <?php if ($test_result): ?>
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 30px;">
            <h2><?php echo esc_html(rbf_translate_string('Risultati Test Base')); ?></h2>
            <div style="padding: 15px; border-radius: 6px; background: <?php echo $test_result['success'] ? '#f0f9ff' : '#fff0f0'; ?>; border: 1px solid <?php echo $test_result['success'] ? '#00a32a' : '#d63638'; ?>;">
                <strong><?php echo esc_html($test_result['success'] ? 'Test Completato con Successo' : 'Test Fallito'); ?></strong>
                <div style="margin-top: 10px;">
                    <?php echo wp_kses_post($test_result['message']); ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($validation_test): ?>
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 30px;">
            <h2><?php echo esc_html(rbf_translate_string('Risultati Validazione')); ?></h2>
            <?php foreach ($validation_test as $check_name => $result): ?>
                <div style="display: flex; align-items: center; padding: 15px; margin-bottom: 10px; border-radius: 6px; background: <?php 
                    echo $result['status'] === 'ok' ? '#f0f9ff' : 
                        ($result['status'] === 'warning' ? '#fff8f0' : '#f8f9fa'); 
                ?>;">
                    <span style="font-size: 20px; margin-right: 15px; color: <?php 
                        echo $result['status'] === 'ok' ? '#00a32a' : 
                            ($result['status'] === 'warning' ? '#dba617' : '#666'); 
                    ?>;">
                        <?php echo $result['status'] === 'ok' ? '✓' : ($result['status'] === 'warning' ? '⚠' : 'ℹ'); ?>
                    </span>
                    <div style="flex: 1;">
                        <strong style="display: block; margin-bottom: 5px;">
                            <?php echo esc_html(ucfirst(str_replace('_', ' ', $check_name))); ?>
                        </strong>
                        <span style="color: #666;">
                            <?php echo esc_html($result['message']); ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($comprehensive_test): ?>
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 30px;">
            <h2><?php echo esc_html(rbf_translate_string('Risultati Test Completo')); ?></h2>
            <div style="font-family: monospace; background: #f8f9fa; padding: 15px; border-radius: 6px; max-height: 600px; overflow-y: auto;">
                <?php echo $comprehensive_test; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Manual Testing Guide -->
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 30px;">
            <h2><?php echo esc_html(rbf_translate_string('Guida Test Manuali')); ?></h2>
            <p><?php echo esc_html(rbf_translate_string('Dopo aver completato i test automatici, esegui questi test manuali per una verifica completa:')); ?></p>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; margin-top: 20px;">
                <div>
                    <h3><?php echo esc_html(rbf_translate_string('Test Google Analytics')); ?></h3>
                    <ul>
                        <li>Apri GA4 DebugView</li>
                        <li>Effettua una prenotazione di test</li>
                        <li>Verifica che gli eventi vengano tracciati</li>
                        <li>Controlla i parametri evento</li>
                    </ul>
                </div>
                
                <div>
                    <h3><?php echo esc_html(rbf_translate_string('Test Google Tag Manager')); ?></h3>
                    <ul>
                        <li>Attiva GTM Preview Mode</li>
                        <li>Naviga sul sito con il form prenotazioni</li>
                        <li>Verifica gli eventi nel dataLayer</li>
                        <li>Controlla l'attivazione dei tag</li>
                    </ul>
                </div>
                
                <div>
                    <h3><?php echo esc_html(rbf_translate_string('Test Facebook Pixel')); ?></h3>
                    <ul>
                        <li>Apri Facebook Events Manager</li>
                        <li>Usa Facebook Pixel Helper extension</li>
                        <li>Effettua una prenotazione di test</li>
                        <li>Verifica eventi Pixel e CAPI</li>
                    </ul>
                </div>
                
                <div>
                    <h3><?php echo esc_html(rbf_translate_string('Test Browser')); ?></h3>
                    <ul>
                        <li>Apri DevTools (F12)</li>
                        <li>Monitora tab Network</li>
                        <li>Controlla console per errori</li>
                        <li>Verifica richieste tracking</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Quick Links -->
        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
            <h3><?php echo esc_html(rbf_translate_string('Link Utili')); ?></h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <a href="<?php echo admin_url('admin.php?page=rbf_tracking_validation'); ?>" class="button">
                    <?php echo esc_html(rbf_translate_string('Validazione Tracking')); ?>
                </a>
                <a href="https://analytics.google.com/analytics/web/#/debugview/" target="_blank" class="button">
                    GA4 DebugView
                </a>
                <a href="https://tagassistant.google.com/" target="_blank" class="button">
                    Google Tag Assistant
                </a>
                <a href="https://business.facebook.com/events_manager/" target="_blank" class="button">
                    Facebook Events Manager
                </a>
            </div>
        </div>
    </div>
    
    <style>
    .rbf-admin-wrap {
        max-width: 1200px;
        margin: 20px 0;
    }
    .rbf-admin-wrap h1 {
        color: #23282d;
        margin-bottom: 20px;
    }
    .rbf-admin-wrap h2 {
        color: #23282d;
        margin-top: 0;
        margin-bottom: 15px;
    }
    .rbf-admin-wrap h3 {
        color: #32373c;
        margin-top: 0;
        margin-bottom: 10px;
    }
    .rbf-admin-wrap ul {
        margin-left: 20px;
    }
    .rbf-admin-wrap ul li {
        margin-bottom: 5px;
    }
    </style>
    <?php
}

/**
 * Add tracking system status dashboard widget
 */
add_action('wp_dashboard_setup', 'rbf_add_tracking_dashboard_widget');
function rbf_add_tracking_dashboard_widget() {
    if (current_user_can('manage_options')) {
        wp_add_dashboard_widget(
            'rbf_tracking_status',
            rbf_translate_string('Stato Sistema Tracking'),
            'rbf_tracking_status_dashboard_widget'
        );
    }
}

/**
 * Tracking status dashboard widget content
 */
function rbf_tracking_status_dashboard_widget() {
    $options = rbf_get_settings();
    
    $status_items = [];
    $overall_status = 'good';
    
    // Check GA4
    if (!empty($options['ga4_id'])) {
        $status_items[] = ['status' => 'good', 'text' => 'GA4 configurato'];
        if (empty($options['ga4_api_secret'])) {
            $status_items[] = ['status' => 'warning', 'text' => 'GA4 API Secret mancante'];
            $overall_status = 'warning';
        }
    } else {
        $status_items[] = ['status' => 'warning', 'text' => 'GA4 non configurato'];
        $overall_status = 'warning';
    }
    
    // Check GTM
    if (!empty($options['gtm_id'])) {
        $status_items[] = ['status' => 'good', 'text' => 'GTM configurato'];
        if (($options['gtm_hybrid'] ?? '') === 'yes') {
            $status_items[] = ['status' => 'good', 'text' => 'Modalità ibrida attiva'];
        }
    }
    
    // Check Facebook
    if (!empty($options['meta_pixel_id'])) {
        $status_items[] = ['status' => 'good', 'text' => 'Facebook Pixel configurato'];
        if (empty($options['meta_access_token'])) {
            $status_items[] = ['status' => 'warning', 'text' => 'Facebook CAPI non configurato'];
            if ($overall_status !== 'error') $overall_status = 'warning';
        }
    }
    
    echo '<div style="margin-bottom: 15px;">';
    foreach ($status_items as $item) {
        $color = $item['status'] === 'good' ? '#00a32a' : ($item['status'] === 'warning' ? '#dba617' : '#d63638');
        $icon = $item['status'] === 'good' ? '✓' : ($item['status'] === 'warning' ? '⚠' : '✗');
        echo '<div style="margin-bottom: 8px; color: ' . $color . ';"><span style="margin-right: 8px;">' . $icon . '</span>' . esc_html($item['text']) . '</div>';
    }
    echo '</div>';
    
    echo '<p><a href="' . admin_url('admin.php?page=rbf_comprehensive_tracking_test') . '" class="button button-primary">';
    echo esc_html(rbf_translate_string('Test Sistema Tracking'));
    echo '</a></p>';
}