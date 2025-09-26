<?php
/**
 * Admin functionality for FP Prenotazioni Ristorante
 *
 * @package FP_Prenotazioni_Ristorante_PRO
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Retrieve the default mapping of WordPress roles to plugin capabilities.
 *
 * @param string|null $booking_capability  Optional booking management capability override.
 * @param string|null $settings_capability Optional settings management capability override.
 * @return array<string, array<int, string>> Normalized role-to-capability map.
 */
function rbf_get_default_capabilities_role_map($booking_capability = null, $settings_capability = null) {
    if (!is_string($booking_capability) || $booking_capability === '') {
        $booking_capability = rbf_get_booking_capability();
    }

    if (!is_string($settings_capability) || $settings_capability === '') {
        $settings_capability = rbf_get_settings_capability();
    }

    $default_role_map = [
        'administrator' => array_filter([
            $booking_capability,
            $settings_capability !== 'manage_options' ? $settings_capability : null,
        ]),
        'editor'       => [$booking_capability],
        'shop_manager' => [$booking_capability],
    ];

    if (function_exists('apply_filters')) {
        $default_role_map = apply_filters('rbf_default_capabilities_map', $default_role_map, $booking_capability, $settings_capability);
    }

    $normalized_map = [];

    foreach ($default_role_map as $role_name => $capabilities) {
        if (!is_string($role_name) || $role_name === '') {
            continue;
        }

        $capabilities = array_filter(
            array_unique(array_map('strval', (array) $capabilities)),
            static function ($capability) {
                return $capability !== '';
            }
        );

        if (!empty($capabilities)) {
            $normalized_map[$role_name] = array_values($capabilities);
        }
    }

    return $normalized_map;
}

add_action('init', 'rbf_register_default_capabilities', 5);
/**
 * Ensure default WordPress roles receive the capabilities required by the plugin.
 */
function rbf_register_default_capabilities() {
    if (!function_exists('get_role')) {
        return;
    }

    foreach (rbf_get_default_capabilities_role_map() as $role_name => $capabilities) {
        $role = get_role($role_name);

        if (!$role) {
            continue;
        }

        foreach ($capabilities as $capability) {
            $role->add_cap($capability);
        }
    }
}

/**
 * Remove plugin capabilities from the default roles.
 *
 * Invoked during uninstall to ensure no orphaned capabilities remain after cleanup.
 */
function rbf_remove_default_capabilities() {
    if (!function_exists('get_role')) {
        return;
    }

    foreach (rbf_get_default_capabilities_role_map() as $role_name => $capabilities) {
        $role = get_role($role_name);

        if (!$role) {
            continue;
        }

        foreach ($capabilities as $capability) {
            $role->remove_cap($capability);
        }
    }
}

/**
 * Register booking custom post type
 */
add_action('init', 'rbf_register_post_type');
function rbf_register_post_type() {
    $booking_capability = rbf_get_booking_capability();

    register_post_type('rbf_booking', [
        'labels' => [
            'name' => rbf_translate_string('Prenotazioni'),
            'singular_name' => rbf_translate_string('Prenotazione'),
            'add_new' => rbf_translate_string('Aggiungi Nuova'),
            'add_new_item' => rbf_translate_string('Aggiungi Nuova Prenotazione'),
            'edit_item' => rbf_translate_string('Modifica Prenotazione'),
            'new_item' => rbf_translate_string('Nuova Prenotazione'),
            'view_item' => rbf_translate_string('Visualizza Prenotazione'),
            'search_items' => rbf_translate_string('Cerca Prenotazioni'),
            'not_found' => rbf_translate_string('Nessuna Prenotazione trovata'),
            'not_found_in_trash' => rbf_translate_string('Nessuna Prenotazione trovata nel cestino'),
        ],
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => false,
        'menu_icon' => 'dashicons-calendar-alt',
        'supports' => ['title', 'custom-fields'],
        'menu_position' => 20,
        'capability_type' => 'rbf_booking',
        'map_meta_cap'    => true,
        'capabilities'    => [
            'edit_post'              => $booking_capability,
            'read_post'              => $booking_capability,
            'delete_post'            => $booking_capability,
            'edit_posts'             => $booking_capability,
            'edit_others_posts'      => $booking_capability,
            'publish_posts'          => $booking_capability,
            'read_private_posts'     => $booking_capability,
            'delete_posts'           => $booking_capability,
            'delete_private_posts'   => $booking_capability,
            'delete_published_posts' => $booking_capability,
            'delete_others_posts'    => $booking_capability,
            'create_posts'           => $booking_capability,
        ],
    ]);
}

/**
 * Create admin menu
 */
add_action('admin_menu', 'rbf_create_bookings_menu');
function rbf_create_bookings_menu() {
    $booking_capability  = rbf_get_booking_capability();
    $settings_capability = rbf_get_settings_capability();
    $booking_menu_capability = $booking_capability;

    if (function_exists('current_user_can')) {
        if (!current_user_can($booking_capability) && current_user_can('manage_options')) {
            $booking_menu_capability = 'manage_options';
        }
    }

    add_menu_page(rbf_translate_string('FP Prenotazioni Ristorante'), rbf_translate_string('FP Prenotazioni Ristorante'), $booking_menu_capability, 'rbf_calendar', 'rbf_calendar_page_html', 'dashicons-calendar-alt', 20);
    add_submenu_page('rbf_calendar', rbf_translate_string('Calendario'), rbf_translate_string('Calendario'), $booking_menu_capability, 'rbf_calendar', 'rbf_calendar_page_html');
    add_submenu_page('rbf_calendar', rbf_translate_string('Agenda Settimanale'), rbf_translate_string('Agenda'), $booking_menu_capability, 'rbf_weekly_staff', 'rbf_weekly_staff_page_html');
    add_submenu_page('rbf_calendar', rbf_translate_string('Nuova Prenotazione Manuale'), rbf_translate_string('Nuova Prenotazione Manuale'), $booking_menu_capability, 'rbf_add_booking', 'rbf_add_booking_page_html');
    add_submenu_page('rbf_calendar', rbf_translate_string('Gestione Tavoli'), rbf_translate_string('Gestione Tavoli'), $booking_menu_capability, 'rbf_tables', 'rbf_tables_page_html');
    add_submenu_page('rbf_calendar', rbf_translate_string('Report & Analytics'), rbf_translate_string('Report & Analytics'), $booking_menu_capability, 'rbf_reports', 'rbf_reports_page_html');
    add_submenu_page('rbf_calendar', rbf_translate_string('Notifiche Email'), rbf_translate_string('Notifiche Email'), $settings_capability, 'rbf_email_notifications', 'rbf_email_notifications_page_html');
    add_submenu_page('rbf_calendar', rbf_translate_string('Esporta Dati'), rbf_translate_string('Esporta Dati'), $booking_menu_capability, 'rbf_export', 'rbf_export_page_html');
    add_submenu_page('rbf_calendar', rbf_translate_string('Impostazioni'), rbf_translate_string('Impostazioni'), $settings_capability, 'rbf_settings', 'rbf_settings_page_html');
    add_submenu_page('rbf_calendar', rbf_translate_string('Validazione Tracking'), rbf_translate_string('Validazione Tracking'), $settings_capability, 'rbf_tracking_validation', 'rbf_tracking_validation_page_html');
}

/**
 * Register and enqueue FullCalendar assets with local fallbacks.
 */
function rbf_enqueue_fullcalendar_assets() {
    $version    = '5.11.3';
    $local_js   = rbf_get_asset_url('vendor/fullcalendar/main.min.js');
    $local_css  = rbf_get_asset_url('vendor/fullcalendar/main.min.css');
    $use_cdn    = apply_filters('rbf_use_cdn_assets', false, 'admin');
    $mode       = $use_cdn ? 'cdn' : 'local';

    static $registered_mode = null;

    if ($registered_mode !== $mode) {
        if (function_exists('wp_deregister_script')) {
            wp_deregister_script('fullcalendar-js');
        }
        if (function_exists('wp_deregister_style')) {
            wp_deregister_style('fullcalendar-css');
        }

        if ($use_cdn) {
            $cdn_base = 'https://cdn.jsdelivr.net/npm/fullcalendar@' . $version . '/main.min';

            wp_register_style('fullcalendar-css', $cdn_base . '.css', [], $version);
            wp_register_script('fullcalendar-js', $cdn_base . '.js', ['jquery'], $version, true);

            $fallback_helper = <<<'JS'
(function(){
    if (window.rbfEnsureAssetFallback) {
        return;
    }
    window.rbfEnsureAssetFallback = function(type, url) {
        if (!url) {
            return;
        }
        if (type === 'script') {
            if (document.querySelector('script[data-rbf-fallback="' + url + '"]')) {
                return;
            }
            var script = document.createElement('script');
            script.src = url;
            script.defer = true;
            script.dataset.rbfFallback = url;
            document.head.appendChild(script);
        } else if (type === 'style') {
            if (document.querySelector('link[data-rbf-fallback="' + url + '"]')) {
                return;
            }
            var link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = url;
            link.dataset.rbfFallback = url;
            document.head.appendChild(link);
        }
    };
})();
JS;
            wp_add_inline_script('fullcalendar-js', $fallback_helper, 'before');

            $css_fallback = sprintf(
                '(function(){var fallbackUrl=%1$s;var primaryUrl=%2$s;if(!fallbackUrl||!primaryUrl||!window.rbfEnsureAssetFallback){return;}var link=null;var styles=document.querySelectorAll(\'link[rel="stylesheet"]\');for(var i=0;i<styles.length;i++){var href=styles[i].getAttribute("href")||"";if(!href){continue;}if(href===primaryUrl||href.indexOf("fullcalendar")!==-1){link=styles[i];break;}}var loadFallback=function(){window.rbfEnsureAssetFallback("style",fallbackUrl);};if(!link){loadFallback();return;}var triggered=false;var once=function(){if(triggered){return;}triggered=true;loadFallback();};link.addEventListener("error",once);setTimeout(function(){try{if(!link.sheet||!link.sheet.cssRules||!link.sheet.cssRules.length){once();}}catch(e){var hasSheet=false;var sheets=document.styleSheets||[];for(var j=0;j<sheets.length;j++){var sheetHref=sheets[j].href||"";if(sheetHref&&sheetHref.indexOf("fullcalendar")!==-1){hasSheet=true;break;}}if(!hasSheet){once();}}},3000);})();',
                wp_json_encode($local_css),
                wp_json_encode($cdn_base . '.css')
            );
            wp_add_inline_script('fullcalendar-js', $css_fallback, 'after');

            $js_fallback = sprintf(
                'if (typeof window.FullCalendar === "undefined" || typeof window.FullCalendar.Calendar === "undefined") { if (window.rbfEnsureAssetFallback) { window.rbfEnsureAssetFallback("script", %s); } }',
                wp_json_encode($local_js)
            );
            wp_add_inline_script('fullcalendar-js', $js_fallback, 'after');
        } else {
            wp_register_style('fullcalendar-css', $local_css, [], $version);
            wp_register_script('fullcalendar-js', $local_js, ['jquery'], $version, true);
        }

        $registered_mode = $mode;
    }

    wp_enqueue_style('fullcalendar-css');
    wp_enqueue_script('fullcalendar-js');
}

/**
 * Customize booking list columns
 */
add_filter('manage_rbf_booking_posts_columns', 'rbf_set_custom_columns');
function rbf_set_custom_columns($columns) {
    unset($columns['date']); // Remove default date column
    
    $new_columns = [];
    $new_columns['cb'] = $columns['cb'];
    $new_columns['title'] = $columns['title'];
    $new_columns['rbf_status'] = rbf_translate_string('Stato Prenotazione');
    $new_columns['rbf_customer'] = rbf_translate_string('Cliente');
    $new_columns['rbf_booking_date'] = rbf_translate_string('Data');
    $new_columns['rbf_time'] = rbf_translate_string('Orario');
    $new_columns['rbf_meal'] = rbf_translate_string('Pasto');
    $new_columns['rbf_people'] = rbf_translate_string('Persone');
    $new_columns['rbf_tables'] = rbf_translate_string('Tavoli');
    $new_columns['rbf_value'] = rbf_translate_string('Valore');
    $new_columns['rbf_actions'] = rbf_translate_string('Azioni');
    
    return $new_columns;
}

/**
 * Fill custom columns with data
 */
add_action('manage_rbf_booking_posts_custom_column', 'rbf_custom_column_data', 10, 2);
function rbf_custom_column_data($column, $post_id) {
    switch ($column) {
        case 'rbf_status':
            $status = get_post_meta($post_id, 'rbf_booking_status', true) ?: 'confirmed';
            $statuses = rbf_get_booking_statuses();
            $color = rbf_get_status_color($status);
            echo '<span class="rbf-color-badge" style="--rbf-color: ' . esc_attr($color) . ';">';
            echo esc_html($statuses[$status] ?? $status);
            echo '</span>';
            break;
            
        case 'rbf_customer':
            $first_name = get_post_meta($post_id, 'rbf_nome', true);
            $last_name = get_post_meta($post_id, 'rbf_cognome', true);
            $email = get_post_meta($post_id, 'rbf_email', true);
            $tel = get_post_meta($post_id, 'rbf_tel', true);
            echo '<strong>' . esc_html($first_name . ' ' . $last_name) . '</strong><br>';
            echo '<small><a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a></small><br>';
            echo '<small><a href="tel:' . esc_attr($tel) . '">' . esc_html($tel) . '</a></small>';
            break;
            
        case 'rbf_booking_date':
            $date = get_post_meta($post_id, 'rbf_data', true);
            if ($date) {
                $datetime = DateTime::createFromFormat('Y-m-d', $date);
                if ($datetime) {
                    echo esc_html($datetime->format('d/m/Y'));
                    echo '<br><small>' . esc_html($datetime->format('l')) . '</small>';
                }
            }
            break;
            
        case 'rbf_time':
            echo esc_html(get_post_meta($post_id, 'rbf_time', true));
            break;
            
        case 'rbf_meal':
            $meal = get_post_meta($post_id, 'rbf_meal', true) ?: get_post_meta($post_id, 'rbf_orario', true); // Fallback for backward compatibility
            $display_name = $meal;

            if (!empty($meal)) {
                $normalizer = function($value) {
                    if (!is_scalar($value)) {
                        return '';
                    }

                    $value = (string) $value;

                    if (function_exists('sanitize_key')) {
                        return sanitize_key($value);
                    }

                    $value = strtolower($value);
                    return preg_replace('/[^a-z0-9_-]/', '', $value);
                };

                $normalized_meal = $normalizer($meal);
                $meal_config = rbf_get_meal_config($meal);

                if (!$meal_config && $normalized_meal !== $meal) {
                    $meal_config = rbf_get_meal_config($normalized_meal);
                }

                if (!$meal_config) {
                    $active_meals = rbf_get_active_meals();
                    foreach ($active_meals as $candidate) {
                        if (empty($candidate['legacy_ids'])) {
                            continue;
                        }

                        $legacy_ids = (array) $candidate['legacy_ids'];
                        $normalized_legacy = array_map($normalizer, $legacy_ids);

                        if (in_array($meal, $legacy_ids, true) || in_array($normalized_meal, $normalized_legacy, true)) {
                            $meal_config = $candidate;
                            break;
                        }
                    }
                }

                if ($meal_config && !empty($meal_config['name'])) {
                    $display_name = $meal_config['name'];
                }
            }

            if (!empty($display_name)) {
                echo esc_html($display_name);
            }
            break;
            
        case 'rbf_people':
            echo '<strong>' . esc_html(get_post_meta($post_id, 'rbf_persone', true)) . '</strong>';
            break;
            
        case 'rbf_tables':
            $assignment = rbf_get_booking_table_assignment($post_id);
            if ($assignment && !empty($assignment['tables'])) {
                $table_names = array_map(function($table) {
                    return $table->table_name . ' (' . $table->area_name . ')';
                }, $assignment['tables']);
                
                $type_label = ($assignment['type'] === 'joined') ? 'Uniti' : 'Singolo';
                echo '<strong>' . implode(', ', $table_names) . '</strong>';
                echo '<br><small class="rbf-text-muted">' . esc_html(sprintf('%s: %s', rbf_translate_string('Tipo'), $type_label)) . '</small>';
                echo '<br><small class="rbf-text-muted">' . esc_html(sprintf('%s: %d', rbf_translate_string('Capacità'), intval($assignment['total_capacity']))) . '</small>';
            } else {
                echo '<em class="rbf-text-muted">' . esc_html(rbf_translate_string('Non assegnato')) . '</em>';
            }
            break;
            
        case 'rbf_value':
            $value_data = rbf_build_booking_tracking_data($post_id);
            $valore_tot = isset($value_data['value']) ? (float) $value_data['value'] : 0.0;
            $valore_pp  = isset($value_data['unit_price']) ? (float) $value_data['unit_price'] : 0.0;
            $people     = isset($value_data['people']) ? (int) $value_data['people'] : 0;

            if ($valore_tot > 0) {
                if ($valore_pp <= 0 && $people > 0) {
                    $valore_pp = $valore_tot / max(1, $people);
                }

                echo '<strong>€' . number_format($valore_tot, 2) . '</strong>';
                echo '<br><small>€' . number_format($valore_pp, 2) . ' x ' . $people . '</small>';
            }
            break;
            
        case 'rbf_actions':
            echo '<div class="row-actions rbf-row-actions-static">';
            rbf_render_booking_actions($post_id);
            echo '</div>';
            break;
    }
}

/**
 * Render booking action buttons (simplified - no manual status changes)
 */
function rbf_render_booking_actions($post_id) {
    $status = get_post_meta($post_id, 'rbf_booking_status', true) ?: 'confirmed';
    
    // Only show delete action for cancelled bookings
    if ($status === 'cancelled') {
        $delete_url = admin_url('post.php?post=' . $post_id . '&action=delete');
        $delete_nonce = wp_create_nonce('delete-post_' . $post_id);
        echo '<a href="' . esc_url($delete_url . '&_wpnonce=' . $delete_nonce) . '" ';
        echo 'class="rbf-link-danger" ';
        echo 'onclick="return confirm(\'' . esc_js(rbf_translate_string('Elimina definitivamente questa prenotazione?')) . '\')">';
        echo esc_html(rbf_translate_string('Elimina'));
        echo '</a>';
    } else {
        echo '<span class="rbf-text-muted rbf-text-italic">' . esc_html(rbf_translate_string('Gestione Automatica')) . '</span>';
    }
}

/**
 * Register settings
 */
add_action('admin_init', 'rbf_register_settings');
function rbf_register_settings() {
    register_setting('rbf_opts_group', 'rbf_settings', [
        'sanitize_callback' => 'rbf_sanitize_settings_callback',
        'default' => rbf_get_default_settings(),
    ]);
}

/**
 * Sanitize settings callback
 */
function rbf_sanitize_settings_callback($input) {
    $defaults = rbf_get_default_settings();
    $output = [];
    $input = (array) $input;
    $current_settings = rbf_get_settings();

    // Define field types for bulk sanitization
    $field_types = [
        // Integer fields
        'capienza_pranzo' => 'int', 'capienza_cena' => 'int', 'capienza_aperitivo' => 'int',
        'brevo_list_it' => 'int', 'brevo_list_en' => 'int',
        'booking_page_id' => 'int',
        'brand_logo_id' => 'int',

        // Text fields
        'orari_pranzo' => 'text', 'orari_cena' => 'text', 'orari_aperitivo' => 'text',
        'brevo_api' => 'text', 'ga4_api_secret' => 'text', 'meta_access_token' => 'text',
        'border_radius' => 'text', 'google_ads_conversion_id' => 'text', 'google_ads_conversion_label' => 'text',
        'brand_name' => 'text', 'brand_font_body' => 'text', 'brand_font_heading' => 'text', 'brand_profile_active' => 'text',

        // Email fields
        'notification_email' => 'email', 'webmaster_email' => 'email',
        'booking_change_email' => 'email', 'booking_change_phone' => 'phone'
    ];

    // URL fields that must retain https schemes
    $url_fields = [
        'brand_logo_url',
    ];

    foreach ($url_fields as $field_key) {
        if (isset($input[$field_key])) {
            $field_types[$field_key] = 'url';
        }
    }

    // Bulk sanitize using helper
    $sanitized = rbf_sanitize_input_fields($input, $field_types);

    // Apply sanitized values with defaults
    foreach ($field_types as $key => $type) {
        if ($type === 'int') {
            $output[$key] = isset($sanitized[$key]) ? max(0, (int) $sanitized[$key]) : (int) ($defaults[$key] ?? 0);
            continue;
        }

        if ($type === 'url') {
            $output[$key] = isset($sanitized[$key]) ? esc_url_raw($sanitized[$key]) : ($defaults[$key] ?? '');
            continue;
        }

        $output[$key] = $sanitized[$key] ?? ($defaults[$key] ?? ($type === 'float' ? 0 : ''));
    }

    // Special validation for GA4 ID
    if (isset($input['ga4_id']) && !empty($input['ga4_id'])) {
        if (preg_match('/^G-[A-Z0-9]+$/', $input['ga4_id'])) {
            $output['ga4_id'] = sanitize_text_field(trim($input['ga4_id']));
        } else {
            $output['ga4_id'] = '';
            add_settings_error('rbf_settings', 'invalid_ga4_id', rbf_translate_string('ID GA4 non valido. Deve essere nel formato G-XXXXXXXXXX.'));
        }
    } else {
        $output['ga4_id'] = $defaults['ga4_id'] ?? '';
    }

    // Special validation for GTM ID
    if (isset($input['gtm_id']) && !empty($input['gtm_id'])) {
        if (preg_match('/^GTM-[A-Z0-9]+$/', $input['gtm_id'])) {
            $output['gtm_id'] = sanitize_text_field(trim($input['gtm_id']));
        } else {
            $output['gtm_id'] = '';
            add_settings_error('rbf_settings', 'invalid_gtm_id', rbf_translate_string('ID GTM non valido. Deve essere nel formato GTM-XXXXXXX.'));
        }
    } else {
        $output['gtm_id'] = $defaults['gtm_id'] ?? '';
    }

    // GTM Hybrid flag
    $output['gtm_hybrid'] = (isset($input['gtm_hybrid']) && $input['gtm_hybrid'] === 'yes') ? 'yes' : 'no';

    // Ensure brand name retains fallback
    if (isset($output['brand_name']) && $output['brand_name'] === '') {
        $output['brand_name'] = $defaults['brand_name'];
    }

    // Validate font selections
    $fonts = rbf_get_supported_brand_fonts();

    $selected_body_font = isset($input['brand_font_body'])
        ? sanitize_key($input['brand_font_body'])
        : ($current_settings['brand_font_body'] ?? $defaults['brand_font_body'] ?? 'system');
    if (!isset($fonts[$selected_body_font])) {
        $selected_body_font = $defaults['brand_font_body'] ?? 'system';
    }
    $output['brand_font_body'] = $selected_body_font;

    $selected_heading_font = isset($input['brand_font_heading'])
        ? sanitize_key($input['brand_font_heading'])
        : ($current_settings['brand_font_heading'] ?? $defaults['brand_font_heading'] ?? $selected_body_font);
    if (!isset($fonts[$selected_heading_font])) {
        $selected_heading_font = $selected_body_font;
    }
    $output['brand_font_heading'] = $selected_heading_font;

    // Preserve logo URL for empty strings to avoid overriding JSON fallback
    if (empty($output['brand_logo_url'])) {
        $output['brand_logo_url'] = '';
    }

    // Ensure active brand profile references exist
    $profiles = function_exists('rbf_get_brand_profiles') ? rbf_get_brand_profiles() : [];
    $active_profile = isset($output['brand_profile_active']) ? sanitize_key($output['brand_profile_active']) : '';
    if ($active_profile && !isset($profiles[$active_profile])) {
        $active_profile = '';
    }
    $output['brand_profile_active'] = $active_profile;

    // Preserve setup completion timestamp
    $output['setup_completed_at'] = $current_settings['setup_completed_at'] ?? ($defaults['setup_completed_at'] ?? '');

    // Special validation for Meta Pixel ID
    if (isset($input['meta_pixel_id']) && !empty($input['meta_pixel_id'])) {
        if (ctype_digit($input['meta_pixel_id'])) {
            $output['meta_pixel_id'] = sanitize_text_field(trim($input['meta_pixel_id']));
        } else {
            $output['meta_pixel_id'] = '';
            add_settings_error('rbf_settings', 'invalid_meta_pixel_id', rbf_translate_string('ID Meta Pixel non valido. Deve essere un numero.'));
        }
    } else {
        $output['meta_pixel_id'] = $defaults['meta_pixel_id'] ?? '';
    }

    // Special validation for brand colors
    if (isset($input['accent_color']) && !empty($input['accent_color'])) {
        $color = sanitize_hex_color($input['accent_color']);
        $output['accent_color'] = $color ? $color : '#000000';
    } else {
        $output['accent_color'] = '';
    }
    
    if (isset($input['secondary_color']) && !empty($input['secondary_color'])) {
        $color = sanitize_hex_color($input['secondary_color']);
        $output['secondary_color'] = $color ? $color : '#f8b500';
    } else {
        $output['secondary_color'] = '';
    }

    $current_settings = wp_parse_args(get_option('rbf_settings', []), $defaults);
    $days = ['mon','tue','wed','thu','fri','sat','sun'];
    foreach ($days as $day) {
        $day_key = "open_{$day}";
        if (array_key_exists($day_key, $input)) {
            $output[$day_key] = ($input[$day_key] === 'yes') ? 'yes' : 'no';
        } else {
            $output[$day_key] = $current_settings[$day_key] ?? ($defaults[$day_key] ?? 'no');
        }
    }

    if (isset($input['closed_dates'])) $output['closed_dates'] = sanitize_textarea_field($input['closed_dates']);

    // Fixed advance booking settings (no longer configurable - using 1-hour minimum rule)
    $output['min_advance_minutes'] = 60; // Fixed at 1 hour
    $output['max_advance_minutes'] = 0;  // No maximum limit

    // Ensure custom meals are always enabled
    $output['use_custom_meals'] = 'yes';

    // Handle custom_meals array
    if (isset($input['custom_meals']) && is_array($input['custom_meals'])) {
        $output['custom_meals'] = [];
        foreach ($input['custom_meals'] as $index => $meal) {
            if (is_array($meal)) {
                $slot_duration = isset($meal['slot_duration_minutes']) ? intval($meal['slot_duration_minutes']) : 90;
                if ($slot_duration < 30) {
                    $slot_duration = 30;
                } elseif ($slot_duration > 240) {
                    $slot_duration = 240;
                }

                $sanitized_meal = [
                    'id' => sanitize_key($meal['id'] ?? ''),
                    'name' => sanitize_text_field($meal['name'] ?? ''),
                    'capacity' => max(1, intval($meal['capacity'] ?? 30)),
                    'time_slots' => rbf_sanitize_time_slot_definition($meal['time_slots'] ?? '', $slot_duration),
                    'price' => max(0, floatval($meal['price'] ?? 0)),
                    'enabled' => isset($meal['enabled']) && $meal['enabled'] == '1',
                    'tooltip' => sanitize_textarea_field($meal['tooltip'] ?? ''),
                    'buffer_time_minutes' => max(0, min(120, intval($meal['buffer_time_minutes'] ?? 15))),
                    'buffer_time_per_person' => max(0, min(30, intval($meal['buffer_time_per_person'] ?? 5))),
                    'overbooking_limit' => max(0, min(50, intval($meal['overbooking_limit'] ?? 10))),
                    'slot_duration_minutes' => $slot_duration,
                    'available_days' => []
                ];

                if (isset($meal['large_party_duration_minutes'])) {
                    $large_party_duration = intval($meal['large_party_duration_minutes']);
                    if ($large_party_duration > 0) {
                        $sanitized_meal['large_party_duration_minutes'] = min(360, $large_party_duration);
                    }
                } elseif (isset($meal['group_slot_duration_minutes'])) {
                    // Backward compatibility for legacy configuration naming.
                    $group_duration = intval($meal['group_slot_duration_minutes']);
                    if ($group_duration > 0) {
                        $sanitized_meal['group_slot_duration_minutes'] = min(360, $group_duration);
                    }
                }

                // Sanitize available days
                if (isset($meal['available_days']) && is_array($meal['available_days'])) {
                    $valid_days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
                    $unique_days = [];
                    foreach ($meal['available_days'] as $day) {
                        if (in_array($day, $valid_days, true)) {
                            $unique_days[$day] = $day;
                        }
                    }
                    if (!empty($unique_days)) {
                        $sanitized_meal['available_days'] = array_values($unique_days);
                    }
                }

                // Only add meal if it has required fields
                if (!empty($sanitized_meal['id']) && !empty($sanitized_meal['name'])) {
                    $output['custom_meals'][] = $sanitized_meal;
                }
            }
        }
    } else {
        $output['custom_meals'] = $defaults['custom_meals'] ?? rbf_get_default_custom_meals();
    }

    // Sanitize reCAPTCHA settings
    if (isset($input['recaptcha_site_key'])) {
        $site_key = sanitize_text_field($input['recaptcha_site_key']);
        if (empty($site_key) || preg_match('/^6L[a-zA-Z0-9_-]+$/', $site_key)) {
            $output['recaptcha_site_key'] = $site_key;
        } else {
            $output['recaptcha_site_key'] = '';
            add_settings_error('rbf_settings', 'invalid_recaptcha_site', rbf_translate_string('reCAPTCHA Site Key non valida.'));
        }
    } else {
        $output['recaptcha_site_key'] = '';
    }

    if (isset($input['recaptcha_secret_key'])) {
        $secret_key = sanitize_text_field($input['recaptcha_secret_key']);
        if (empty($secret_key) || preg_match('/^6L[a-zA-Z0-9_-]+$/', $secret_key)) {
            $output['recaptcha_secret_key'] = $secret_key;
        } else {
            $output['recaptcha_secret_key'] = '';
            add_settings_error('rbf_settings', 'invalid_recaptcha_secret', rbf_translate_string('reCAPTCHA Secret Key non valida.'));
        }
    } else {
        $output['recaptcha_secret_key'] = '';
    }

    if (isset($input['recaptcha_threshold'])) {
        $threshold = floatval($input['recaptcha_threshold']);
        $output['recaptcha_threshold'] = max(0, min(1, $threshold));
    } else {
        $output['recaptcha_threshold'] = '0.5';
    }

    return $output;
}

/**
 * Clear availability caches whenever the main settings option is updated.
 */
add_action('update_option_rbf_settings', 'rbf_clear_availability_caches_on_settings_update', 10, 2);
function rbf_clear_availability_caches_on_settings_update($old_value, $value) {
    if ($old_value === $value) {
        return;
    }

    rbf_delete_transients_like(rbf_get_global_availability_transient_patterns());
}

/**
 * Enqueue admin styles
 */
add_action('admin_enqueue_scripts', 'rbf_enqueue_admin_styles');

/**
 * Normalize a WordPress admin hook/screen identifier for reliable comparisons.
 *
 * Some identifiers use hyphens while others use underscores. By converting
 * hyphens into underscores we can compare the values in a consistent manner
 * regardless of how WordPress generated them.
 *
 * @param mixed $identifier Potential hook or screen identifier.
 * @return string Normalized identifier string.
 */
function rbf_normalize_admin_identifier($identifier) {
    if (!is_string($identifier) || $identifier === '') {
        return '';
    }

    return str_replace('-', '_', $identifier);
}

/**
 * Retrieve the list of plugin admin screen identifiers.
 *
 * @return array<int, string> Normalized identifiers.
 */
function rbf_get_plugin_admin_screen_ids() {
    static $screen_ids = null;

    if (is_array($screen_ids)) {
        return $screen_ids;
    }

    $slugs = [
        'rbf_calendar',
        'rbf_weekly_staff',
        'rbf_add_booking',
        'rbf_tables',
        'rbf_reports',
        'rbf_email_notifications',
        'rbf_export',
        'rbf_settings',
        'rbf_tracking_validation',
        'rbf_booking_dashboard',
    ];

    $raw_ids = [
        'toplevel_page_rbf_calendar',
        'edit-rbf_booking',
        'rbf_booking',
    ];

    foreach ($slugs as $slug) {
        $raw_ids[] = 'rbf_calendar_page_' . $slug;
        $raw_ids[] = 'prenotazioni_page_' . $slug;
    }

    $screen_ids = array_values(array_unique(array_map('rbf_normalize_admin_identifier', $raw_ids)));

    return $screen_ids;
}

/**
 * Determine if the provided hook or screen identifier matches a plugin page.
 *
 * @param string $identifier Hook or screen identifier.
 * @return bool True when the identifier belongs to one of the plugin pages.
 */
function rbf_is_plugin_admin_identifier($identifier) {
    $normalized = rbf_normalize_admin_identifier($identifier);

    if ($normalized === '') {
        return false;
    }

    return in_array($normalized, rbf_get_plugin_admin_screen_ids(), true);
}

/**
 * Enqueue admin styles.
 *
 * @param string $hook Current admin page hook suffix.
 */
function rbf_enqueue_admin_styles($hook) {
    $screen    = function_exists('get_current_screen') ? get_current_screen() : null;
    $screen_id = $screen && isset($screen->id) ? $screen->id : '';

    $is_plugin_screen = rbf_is_plugin_admin_identifier($screen_id) || rbf_is_plugin_admin_identifier($hook);

    if (!$is_plugin_screen) {
        return;
    }

    wp_enqueue_style(
        'rbf-admin-css',
        plugin_dir_url(dirname(__FILE__)) . 'assets/css/admin.css',
        [],
        rbf_get_asset_version('css/admin.css')
    );

    wp_register_script(
        'rbf-admin-js',
        plugin_dir_url(dirname(__FILE__)) . 'assets/js/admin.js',
        ['jquery'],
        rbf_get_asset_version('js/admin.js'),
        true
    );

    wp_enqueue_script('rbf-admin-js');

    $settings_hooks = [
        'rbf_calendar_page_rbf_settings',
        'prenotazioni_page_rbf_settings',
    ];
    $normalized_settings_hooks = array_map('rbf_normalize_admin_identifier', $settings_hooks);

    $is_settings_screen = in_array(
        rbf_normalize_admin_identifier($screen_id),
        $normalized_settings_hooks,
        true
    );

    if (!$is_settings_screen && is_string($hook)) {
        $is_settings_screen = in_array(
            rbf_normalize_admin_identifier($hook),
            $normalized_settings_hooks,
            true
        );
    }

    if ($is_settings_screen) {
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        if (function_exists('wp_enqueue_media')) {
            wp_enqueue_media();
        }

        $current_settings = rbf_get_settings();
        foreach (rbf_get_brand_font_stylesheets($current_settings, 'admin') as $handle => $url) {
            wp_enqueue_style($handle, $url, [], null);
        }

        $fonts_catalog_admin = rbf_get_supported_brand_fonts();

        wp_enqueue_script(
            'rbf-brand-admin',
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/admin-branding.js',
            ['jquery', 'wp-color-picker'],
            rbf_get_asset_version('js/admin-branding.js'),
            true
        );

        $font_payload = [];
        foreach ($fonts_catalog_admin as $key => $font) {
            $font_payload[$key] = [
                'label' => $font['label'],
                'stack' => $font['stack'],
            ];
            if (!empty($font['google'])) {
                $font_payload[$key]['google'] = $font['google'];
            }
        }

        wp_localize_script('rbf-brand-admin', 'rbfBrandingSettings', [
            'fonts' => $font_payload,
            'logoPlaceholder' => rbf_translate_string('Nessun logo'),
            'brandPlaceholder' => rbf_translate_string('Il tuo brand'),
            'mediaTitle' => rbf_translate_string('Scegli un logo'),
            'mediaButton' => rbf_translate_string('Usa questo logo'),
        ]);
    }

    // Inject brand CSS variables for admin
    rbf_inject_brand_css_vars_admin();
}

add_filter('admin_body_class', 'rbf_add_plugin_admin_body_class');
/**
 * Append a distinctive body class on plugin admin screens so CSS can style them uniformly.
 *
 * @param string $classes Existing admin body class string.
 * @return string Adjusted class list.
 */
function rbf_add_plugin_admin_body_class($classes) {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    $screen_id = $screen && isset($screen->id) ? $screen->id : '';

    if (!rbf_is_plugin_admin_identifier($screen_id)) {
        return $classes;
    }

    $existing_classes = preg_split('/\s+/', (string) $classes, -1, PREG_SPLIT_NO_EMPTY);
    $existing_classes[] = 'rbf-admin-screen';

    if ($screen && isset($screen->base) && is_string($screen->base) && $screen->base !== '') {
        $existing_classes[] = 'rbf-admin-screen--' . sanitize_html_class($screen->base, 'rbf-admin');
    }

    return implode(' ', array_unique(array_filter($existing_classes)));
}

/**
 * Inject brand CSS variables for admin interface
 */
function rbf_inject_brand_css_vars_admin() {
    $css_vars = rbf_generate_brand_css_vars();
    
    $css = ":root {\n";
    foreach ($css_vars as $var => $value) {
        $css .= "    $var: $value;\n";
    }
    $css .= "}\n";
    
    wp_add_inline_style('rbf-admin-css', $css);
}

/**
 * Render a single custom meal configuration block.
 *
 * @param int|string $index      Numeric index or placeholder string for template usage.
 * @param array      $meal       Meal configuration values.
 * @param array      $day_labels Mapping of weekday keys to translated labels.
 * @param bool       $is_template Whether the block is used as a JavaScript template.
 *
 * @return string Rendered HTML for the meal configuration block.
 */
function rbf_render_custom_meal_item($index, $meal, array $day_labels, $is_template = false) {
    $defaults = [
        'enabled'               => true,
        'id'                    => '',
        'name'                  => '',
        'capacity'              => 30,
        'time_slots'            => '',
        'price'                 => 0,
        'available_days'        => [],
        'tooltip'               => '',
        'buffer_time_minutes'   => 15,
        'buffer_time_per_person'=> 5,
        'overbooking_limit'     => 10,
        'slot_duration_minutes' => 90,
    ];

    if (!is_array($meal)) {
        $meal = [];
    }

    $meal = array_merge($defaults, $meal);

    $index_attr  = $is_template ? '__INDEX__' : (string) $index;
    $meal_number = $is_template ? '__NUMBER__' : (string) ((int) $index + 1);

    ob_start();
    $field_prefix = sprintf('rbf-custom-meal-%s', $index_attr);
    ?>
    <div class="custom-meal-item rbf-meal-card" data-meal-index="<?php echo esc_attr($index_attr); ?>">
        <div class="rbf-meal-card__header">
            <h4 class="rbf-meal-card__title"><?php echo esc_html(rbf_translate_string('Pasto')); ?> <span class="rbf-meal-number"><?php echo esc_html($meal_number); ?></span></h4>
            <div class="rbf-meal-card__actions">
                <button type="button" class="button-link-delete rbf-meal-card__remove remove-meal"><?php echo esc_html(rbf_translate_string('Rimuovi Pasto')); ?></button>
            </div>
        </div>

        <div class="rbf-meal-card__grid">
            <div class="rbf-field rbf-field--inline">
                <label class="rbf-field__label" for="<?php echo esc_attr($field_prefix . '-enabled'); ?>"><?php echo esc_html(rbf_translate_string('Attivo')); ?></label>
                <div class="rbf-field__control">
                    <input type="checkbox" id="<?php echo esc_attr($field_prefix . '-enabled'); ?>" name="rbf_settings[custom_meals][<?php echo esc_attr($index_attr); ?>][enabled]" value="1" <?php checked(!empty($meal['enabled'])); ?>>
                </div>
            </div>

            <div class="rbf-field">
                <label class="rbf-field__label" for="<?php echo esc_attr($field_prefix . '-id'); ?>"><?php echo esc_html(rbf_translate_string('ID')); ?></label>
                <div class="rbf-field__control">
                    <input type="text" id="<?php echo esc_attr($field_prefix . '-id'); ?>" name="rbf_settings[custom_meals][<?php echo esc_attr($index_attr); ?>][id]" value="<?php echo esc_attr($meal['id']); ?>" class="regular-text" placeholder="<?php echo esc_attr(rbf_translate_string('es: pranzo')); ?>">
                    <p class="description rbf-field__description"><?php echo esc_html(rbf_translate_string('ID univoco del pasto (senza spazi, solo lettere e numeri)')); ?></p>
                </div>
            </div>

            <div class="rbf-field">
                <label class="rbf-field__label" for="<?php echo esc_attr($field_prefix . '-name'); ?>"><?php echo esc_html(rbf_translate_string('Nome')); ?></label>
                <div class="rbf-field__control">
                    <input type="text" id="<?php echo esc_attr($field_prefix . '-name'); ?>" name="rbf_settings[custom_meals][<?php echo esc_attr($index_attr); ?>][name]" value="<?php echo esc_attr($meal['name']); ?>" class="regular-text" placeholder="<?php echo esc_attr(rbf_translate_string('es: Pranzo')); ?>">
                </div>
            </div>

            <div class="rbf-field">
                <label class="rbf-field__label" for="<?php echo esc_attr($field_prefix . '-capacity'); ?>"><?php echo esc_html(rbf_translate_string('Capienza')); ?></label>
                <div class="rbf-field__control">
                    <input type="number" id="<?php echo esc_attr($field_prefix . '-capacity'); ?>" name="rbf_settings[custom_meals][<?php echo esc_attr($index_attr); ?>][capacity]" value="<?php echo esc_attr($meal['capacity']); ?>" min="1">
                </div>
            </div>

            <div class="rbf-field rbf-field--full">
                <label class="rbf-field__label" for="<?php echo esc_attr($field_prefix . '-time-slots'); ?>"><?php echo esc_html(rbf_translate_string('Orari')); ?></label>
                <div class="rbf-field__control">
                    <input type="text" id="<?php echo esc_attr($field_prefix . '-time-slots'); ?>" name="rbf_settings[custom_meals][<?php echo esc_attr($index_attr); ?>][time_slots]" value="<?php echo esc_attr($meal['time_slots']); ?>" class="regular-text" placeholder="<?php echo esc_attr(rbf_translate_string('es: 12:00,12:30,13:00')); ?>">
                    <p class="description rbf-field__description"><?php echo esc_html(rbf_translate_string('Orari separati da virgola')); ?></p>
                </div>
            </div>

            <div class="rbf-field">
                <label class="rbf-field__label" for="<?php echo esc_attr($field_prefix . '-price'); ?>"><?php echo esc_html(rbf_translate_string('Prezzo (€)')); ?></label>
                <div class="rbf-field__control">
                    <input type="number" step="0.01" id="<?php echo esc_attr($field_prefix . '-price'); ?>" name="rbf_settings[custom_meals][<?php echo esc_attr($index_attr); ?>][price]" value="<?php echo esc_attr($meal['price']); ?>" min="0">
                </div>
            </div>

            <div class="rbf-field rbf-field--full">
                <span class="rbf-field__label"><?php echo esc_html(rbf_translate_string('Giorni disponibili')); ?></span>
                <div class="rbf-field__control">
                    <div class="rbf-field__checkbox-group">
                        <?php foreach ($day_labels as $day_key => $day_label) { ?>
                            <label class="rbf-checkbox-pill">
                                <input type="checkbox" name="rbf_settings[custom_meals][<?php echo esc_attr($index_attr); ?>][available_days][]" value="<?php echo esc_attr($day_key); ?>" <?php checked(in_array($day_key, (array) $meal['available_days'], true)); ?>>
                                <span><?php echo esc_html($day_label); ?></span>
                            </label>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <div class="rbf-field rbf-field--full">
                <label class="rbf-field__label" for="<?php echo esc_attr($field_prefix . '-tooltip'); ?>"><?php echo esc_html(rbf_translate_string('Tooltip informativo')); ?></label>
                <div class="rbf-field__control">
                    <textarea id="<?php echo esc_attr($field_prefix . '-tooltip'); ?>" name="rbf_settings[custom_meals][<?php echo esc_attr($index_attr); ?>][tooltip]" class="regular-text" rows="2" placeholder="<?php echo esc_attr(rbf_translate_string('es: Di Domenica il servizio è Brunch con menù alla carta.')); ?>"><?php echo esc_textarea($meal['tooltip']); ?></textarea>
                    <p class="description rbf-field__description"><?php echo esc_html(rbf_translate_string('Testo informativo che apparirà quando questo pasto viene selezionato (opzionale)')); ?></p>
                </div>
            </div>

            <div class="rbf-field">
                <label class="rbf-field__label" for="<?php echo esc_attr($field_prefix . '-buffer'); ?>"><?php echo esc_html(rbf_translate_string('Buffer Base (minuti)')); ?></label>
                <div class="rbf-field__control">
                    <input type="number" id="<?php echo esc_attr($field_prefix . '-buffer'); ?>" name="rbf_settings[custom_meals][<?php echo esc_attr($index_attr); ?>][buffer_time_minutes]" value="<?php echo esc_attr($meal['buffer_time_minutes']); ?>" min="0" max="120">
                    <p class="description rbf-field__description"><?php echo esc_html(rbf_translate_string('Tempo minimo di buffer tra prenotazioni (minuti)')); ?></p>
                </div>
            </div>

            <div class="rbf-field">
                <label class="rbf-field__label" for="<?php echo esc_attr($field_prefix . '-buffer-per-person'); ?>"><?php echo esc_html(rbf_translate_string('Buffer per Persona (minuti)')); ?></label>
                <div class="rbf-field__control">
                    <input type="number" id="<?php echo esc_attr($field_prefix . '-buffer-per-person'); ?>" name="rbf_settings[custom_meals][<?php echo esc_attr($index_attr); ?>][buffer_time_per_person]" value="<?php echo esc_attr($meal['buffer_time_per_person']); ?>" min="0" max="30">
                    <p class="description rbf-field__description"><?php echo esc_html(rbf_translate_string('Tempo aggiuntivo di buffer per ogni persona (minuti)')); ?></p>
                </div>
            </div>

            <div class="rbf-field">
                <label class="rbf-field__label" for="<?php echo esc_attr($field_prefix . '-overbooking'); ?>"><?php echo esc_html(rbf_translate_string('Limite Overbooking (%)')); ?></label>
                <div class="rbf-field__control">
                    <input type="number" id="<?php echo esc_attr($field_prefix . '-overbooking'); ?>" name="rbf_settings[custom_meals][<?php echo esc_attr($index_attr); ?>][overbooking_limit]" value="<?php echo esc_attr($meal['overbooking_limit']); ?>" min="0" max="50">
                    <p class="description rbf-field__description"><?php echo esc_html(rbf_translate_string('Percentuale di overbooking consentita oltre la capienza normale')); ?></p>
                </div>
            </div>

            <div class="rbf-field">
                <label class="rbf-field__label" for="<?php echo esc_attr($field_prefix . '-slot-duration'); ?>"><?php echo esc_html(rbf_translate_string('Durata Slot (minuti)')); ?></label>
                <div class="rbf-field__control">
                    <input type="number" id="<?php echo esc_attr($field_prefix . '-slot-duration'); ?>" name="rbf_settings[custom_meals][<?php echo esc_attr($index_attr); ?>][slot_duration_minutes]" value="<?php echo esc_attr($meal['slot_duration_minutes']); ?>" min="30" max="240">
                    <p class="description rbf-field__description"><?php echo esc_html(rbf_translate_string('Durata di occupazione del tavolo per questo servizio (minuti)')); ?></p>
                </div>
            </div>
        </div>
    </div>
    <?php

    return (string) ob_get_clean();
}

/**
 * Settings page HTML
 */

function rbf_settings_page_html() {
    if (!rbf_require_settings_capability()) {
        return;
    }

    $options = wp_parse_args(get_option('rbf_settings', rbf_get_default_settings()), rbf_get_default_settings());
    $day_labels = [
        'mon' => rbf_translate_string('Lunedì'),
        'tue' => rbf_translate_string('Martedì'),
        'wed' => rbf_translate_string('Mercoledì'),
        'thu' => rbf_translate_string('Giovedì'),
        'fri' => rbf_translate_string('Venerdì'),
        'sat' => rbf_translate_string('Sabato'),
        'sun' => rbf_translate_string('Domenica')
    ];
    $fonts_catalog = rbf_get_supported_brand_fonts();
    $brand_profiles = rbf_get_brand_profiles();
    $brand_profiles_export = rbf_export_brand_profiles();
    $tracking_catalog = rbf_get_tracking_package_catalog();
    $tracking_packages = rbf_get_tracking_packages();
    $recent_tracking_events = array_slice(rbf_get_recent_tracking_events(), 0, 5);

    $custom_meals_config = $options['custom_meals'] ?? [];
    $custom_meals_count = 0;
    $active_meals_count = 0;

    if (is_array($custom_meals_config)) {
        $custom_meals_count = count($custom_meals_config);

        foreach ($custom_meals_config as $meal_config) {
            if (!empty($meal_config['enabled'])) {
                $active_meals_count++;
            }
        }
    }

    $open_days_count = 0;
    foreach ($day_labels as $day_key => $day_label) {
        $option_key = "open_{$day_key}";
        if (($options[$option_key] ?? 'yes') === 'yes') {
            $open_days_count++;
        }
    }

    $closed_dates_raw = trim((string) ($options['closed_dates'] ?? ''));
    $exceptions_count = 0;
    if ($closed_dates_raw !== '') {
        $exceptions_rows = preg_split('/\r?\n/', $closed_dates_raw);
        $exceptions_count = count(array_filter($exceptions_rows, static function ($row) {
            return trim((string) $row) !== '';
        }));
    }

    $enabled_tracking_packages = array_filter($tracking_packages, static function ($package) {
        return !empty($package['enabled']);
    });
    $tracking_enabled_count = count($enabled_tracking_packages);

    $restaurant_name = trim((string) ($options['restaurant_name'] ?? ''));
    $restaurant_email = trim((string) ($options['restaurant_email'] ?? ''));
    $restaurant_logo = trim((string) ($options['restaurant_logo'] ?? ''));
    $branding_ready = ($restaurant_name !== '' && $restaurant_email !== '');
    $branding_description = $branding_ready
        ? ($restaurant_logo !== ''
            ? rbf_translate_string('Logo e contatti sono pronti per essere mostrati nel form e nelle notifiche email.')
            : rbf_translate_string('Contatti aggiornati e pronti per il front-end. Aggiungi il logo per completare il profilo.'))
        : rbf_translate_string('Imposta nome, contatti e logo per allineare il modulo pubblico all\'identità del ristorante.');

    $meals_ready = $active_meals_count > 0;
    if ($meals_ready) {
        $meals_description = $active_meals_count === 1
            ? rbf_translate_string('Hai 1 pasto attivo pronto per essere prenotato.')
            : sprintf(
                rbf_translate_string('Hai %d pasti attivi pronti per essere prenotati.'),
                $active_meals_count
            );
    } else {
        $meals_description = rbf_translate_string('Aggiungi almeno un pasto personalizzato e attivalo per rendere operativo il modulo di prenotazione.');
    }

    $availability_ready = $open_days_count > 0;
    $availability_description = $availability_ready
        ? rbf_translate_string('Giorni e fasce orarie sono sincronizzati con il modulo di prenotazione.')
        : rbf_translate_string('Definisci giorni e orari di apertura per rendere disponibili gli slot ai clienti.');

    $tracking_ready = $tracking_enabled_count > 0;
    if ($tracking_ready) {
        $tracking_description = $tracking_enabled_count === 1
            ? rbf_translate_string('Stai monitorando 1 pacchetto marketing attivo.')
            : sprintf(
                rbf_translate_string('Stai monitorando %d pacchetti marketing attivi.'),
                $tracking_enabled_count
            );
    } else {
        $tracking_description = rbf_translate_string('Collega Google Analytics, Meta o altri strumenti per seguire le conversioni delle prenotazioni.');
    }

    $settings_status_labels = [
        'complete' => rbf_translate_string('Completato'),
        'pending'  => rbf_translate_string('Da completare'),
        'optional' => rbf_translate_string('Opzionale'),
    ];

    $settings_checklist_items = [
        [
            'target'      => 'branding',
            'icon'        => 'dashicons-admin-site-alt3',
            'title'       => rbf_translate_string('Profilo del ristorante'),
            'description' => $branding_description,
            'action'      => $branding_ready
                ? rbf_translate_string('Rivedi impostazioni brand')
                : rbf_translate_string('Configura brand'),
            'status'      => $branding_ready ? 'complete' : 'pending',
        ],
        [
            'target'      => 'branding',
            'icon'        => 'dashicons-products',
            'title'       => rbf_translate_string('Pasti e servizi'),
            'description' => $meals_description,
            'action'      => $meals_ready
                ? rbf_translate_string('Gestisci pasti personalizzati')
                : rbf_translate_string('Aggiungi un pasto'),
            'status'      => $meals_ready ? 'complete' : 'pending',
        ],
        [
            'target'      => 'availability',
            'icon'        => 'dashicons-schedule',
            'title'       => rbf_translate_string('Disponibilità settimanale'),
            'description' => $availability_description,
            'action'      => $availability_ready
                ? rbf_translate_string('Rivedi disponibilità')
                : rbf_translate_string('Imposta disponibilità'),
            'status'      => $availability_ready ? 'complete' : 'pending',
        ],
        [
            'target'      => 'integrations',
            'icon'        => 'dashicons-chart-area',
            'title'       => rbf_translate_string('Tracking e integrazioni'),
            'description' => $tracking_description,
            'action'      => $tracking_ready
                ? rbf_translate_string('Rivedi integrazioni')
                : rbf_translate_string('Configura tracking'),
            'status'      => $tracking_ready ? 'complete' : 'optional',
        ],
    ];
    ?>
    <div class="rbf-admin-wrap rbf-admin-wrap--wide">
        <h1><?php echo esc_html(rbf_translate_string('Impostazioni Prenotazioni Ristorante')); ?></h1>
        <p class="rbf-admin-intro">
            <?php echo esc_html(rbf_translate_string('Configura brand, servizi e integrazioni con un layout vicino alle schermate native di WordPress. Ogni sezione include indicazioni rapide per guidarti nella configurazione.')); ?>
        </p>
        <div class="rbf-admin-hero" role="region" aria-label="<?php echo esc_attr(rbf_translate_string('Stato configurazione ristorante')); ?>">
            <div class="rbf-admin-hero__intro">
                <p class="rbf-admin-hero__lead"><?php echo esc_html(rbf_translate_string('Rivedi rapidamente lo stato della configurazione e raggiungi le schermate operative più usate.')); ?></p>
                <div class="rbf-admin-hero__actions">
                    <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=rbf_calendar')); ?>"><?php echo esc_html(rbf_translate_string('Vai al Calendario')); ?></a>
                    <a class="button button-secondary" href="<?php echo esc_url(admin_url('admin.php?page=rbf_weekly_staff')); ?>"><?php echo esc_html(rbf_translate_string('Agenda Settimanale')); ?></a>
                </div>
            </div>
            <div class="rbf-admin-hero__stats" role="list">
                <div class="rbf-admin-stat" role="listitem">
                    <span class="rbf-admin-stat__label"><?php echo esc_html(rbf_translate_string('Pasti attivi')); ?></span>
                    <span class="rbf-admin-stat__value"><?php echo esc_html($active_meals_count); ?></span>
                    <span class="rbf-admin-stat__meta"><?php echo esc_html(sprintf(rbf_translate_string('su %d configurati'), $custom_meals_count)); ?></span>
                </div>
                <div class="rbf-admin-stat" role="listitem">
                    <span class="rbf-admin-stat__label"><?php echo esc_html(rbf_translate_string('Giorni di apertura')); ?></span>
                    <span class="rbf-admin-stat__value"><?php echo esc_html($open_days_count); ?></span>
                    <span class="rbf-admin-stat__meta"><?php echo esc_html(sprintf(rbf_translate_string('su %d totali'), count($day_labels))); ?></span>
                </div>
                <div class="rbf-admin-stat" role="listitem">
                    <span class="rbf-admin-stat__label"><?php echo esc_html(rbf_translate_string('Eccezioni calendario')); ?></span>
                    <span class="rbf-admin-stat__value"><?php echo esc_html($exceptions_count); ?></span>
                    <span class="rbf-admin-stat__meta"><?php echo esc_html(rbf_translate_string('date personalizzate')); ?></span>
                </div>
                <div class="rbf-admin-stat" role="listitem">
                    <span class="rbf-admin-stat__label"><?php echo esc_html(rbf_translate_string('Pacchetti marketing')); ?></span>
                    <span class="rbf-admin-stat__value"><?php echo esc_html($tracking_enabled_count); ?></span>
                    <span class="rbf-admin-stat__meta"><?php echo esc_html(rbf_translate_string('attivi su questa installazione')); ?></span>
                </div>
            </div>
        </div>
        <?php if (!empty($settings_checklist_items)) : ?>
            <div class="rbf-admin-checklist" role="list" aria-label="<?php echo esc_attr(rbf_translate_string('Prossimi passi consigliati')); ?>">
                <?php foreach ($settings_checklist_items as $item) :
                    $status = isset($item['status']) ? (string) $item['status'] : 'pending';
                    $status_label = $settings_status_labels[$status] ?? '';
                    $card_classes = ['rbf-checklist-card'];
                    if ($status !== '') {
                        $card_classes[] = 'rbf-checklist-card--' . sanitize_html_class($status, 'pending');
                    }
                    ?>
                    <article class="<?php echo esc_attr(implode(' ', $card_classes)); ?>" role="listitem">
                        <div class="rbf-checklist-card__icon" aria-hidden="true">
                            <span class="dashicons <?php echo esc_attr($item['icon']); ?>"></span>
                        </div>
                        <div class="rbf-checklist-card__content">
                            <?php if ($status_label !== '') : ?>
                                <span class="rbf-checklist-card__status"><?php echo esc_html($status_label); ?></span>
                            <?php endif; ?>
                            <h3 class="rbf-checklist-card__title"><?php echo esc_html($item['title']); ?></h3>
                            <p class="rbf-checklist-card__description"><?php echo esc_html($item['description']); ?></p>
                            <a class="rbf-checklist-card__action" href="#rbf-tab-<?php echo esc_attr($item['target']); ?>" data-tab-target="<?php echo esc_attr($item['target']); ?>">
                                <?php echo esc_html($item['action']); ?>
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <nav class="rbf-admin-shortcuts" aria-label="<?php echo esc_attr(rbf_translate_string('Collegamenti rapidi')); ?>">
            <a class="rbf-admin-shortcut" href="<?php echo esc_url(admin_url('admin.php?page=rbf_calendar')); ?>">
                <span class="rbf-admin-shortcut__icon dashicons dashicons-calendar-alt" aria-hidden="true"></span>
                <span class="rbf-admin-shortcut__label"><?php echo esc_html(rbf_translate_string('Calendario live')); ?></span>
                <span class="rbf-admin-shortcut__description"><?php echo esc_html(rbf_translate_string('Controlla le prenotazioni del giorno e gestisci i turni in corso.')); ?></span>
            </a>
            <a class="rbf-admin-shortcut" href="<?php echo esc_url(admin_url('admin.php?page=rbf_weekly_staff')); ?>">
                <span class="rbf-admin-shortcut__icon dashicons dashicons-calendar" aria-hidden="true"></span>
                <span class="rbf-admin-shortcut__label"><?php echo esc_html(rbf_translate_string('Agenda settimanale')); ?></span>
                <span class="rbf-admin-shortcut__description"><?php echo esc_html(rbf_translate_string('Visualizza il carico dei prossimi giorni e assegna lo staff.')); ?></span>
            </a>
            <a class="rbf-admin-shortcut" href="<?php echo esc_url(admin_url('admin.php?page=rbf_tables')); ?>">
                <span class="rbf-admin-shortcut__icon dashicons dashicons-screenoptions" aria-hidden="true"></span>
                <span class="rbf-admin-shortcut__label"><?php echo esc_html(rbf_translate_string('Gestione tavoli')); ?></span>
                <span class="rbf-admin-shortcut__description"><?php echo esc_html(rbf_translate_string('Configura sale, tavoli e capacità per sincronizzare il front-end.')); ?></span>
            </a>
            <a class="rbf-admin-shortcut" href="<?php echo esc_url(admin_url('admin.php?page=rbf_reports')); ?>">
                <span class="rbf-admin-shortcut__icon dashicons dashicons-chart-bar" aria-hidden="true"></span>
                <span class="rbf-admin-shortcut__label"><?php echo esc_html(rbf_translate_string('Report & trend')); ?></span>
                <span class="rbf-admin-shortcut__description"><?php echo esc_html(rbf_translate_string('Analizza volumi, servizi più richiesti e performance marketing.')); ?></span>
            </a>
        </nav>

        <form method="post" action="options.php" class="rbf-settings-form">
            <?php settings_fields('rbf_opts_group'); ?>
            <div class="rbf-settings-tabs-wrapper">
                <h2 class="nav-tab-wrapper rbf-admin-tabs" role="tablist">
                    <a href="#rbf-tab-branding" class="nav-tab nav-tab-active rbf-tab-link" data-tab-target="branding" role="tab" aria-selected="true" aria-controls="rbf-tab-branding" id="rbf-tab-link-branding"><?php echo esc_html(rbf_translate_string('Brand & Conferma')); ?></a>
                    <a href="#rbf-tab-availability" class="nav-tab rbf-tab-link" data-tab-target="availability" role="tab" aria-selected="false" aria-controls="rbf-tab-availability" id="rbf-tab-link-availability"><?php echo esc_html(rbf_translate_string('Disponibilità & Pasti')); ?></a>
                    <a href="#rbf-tab-calendar" class="nav-tab rbf-tab-link" data-tab-target="calendar" role="tab" aria-selected="false" aria-controls="rbf-tab-calendar" id="rbf-tab-link-calendar"><?php echo esc_html(rbf_translate_string('Calendario & Eccezioni')); ?></a>
                    <a href="#rbf-tab-integrations" class="nav-tab rbf-tab-link" data-tab-target="integrations" role="tab" aria-selected="false" aria-controls="rbf-tab-integrations" id="rbf-tab-link-integrations"><?php echo esc_html(rbf_translate_string('Integrazioni & Sicurezza')); ?></a>
                </h2>
            </div>

            <div class="rbf-settings-tab-panel rbf-tab-panel is-active" id="rbf-tab-branding" data-tab-panel="branding" role="tabpanel" aria-labelledby="rbf-tab-link-branding">
                <div class="rbf-tab-intro" role="note">
                    <div class="rbf-tab-intro__icon" aria-hidden="true">
                        <span class="dashicons dashicons-admin-appearance"></span>
                    </div>
                    <div class="rbf-tab-intro__content">
                        <h3 class="rbf-tab-intro__title"><?php echo esc_html(rbf_translate_string('Immagine coordinata e messaggi')); ?></h3>
                        <p class="rbf-tab-intro__description"><?php echo esc_html(rbf_translate_string('Gestisci loghi, palette, font e i testi automatici che i clienti vedono nelle email di conferma.')); ?></p>
                        <p class="rbf-tab-intro__description"><?php echo esc_html(rbf_translate_string('Usa i profili brand per salvare combinazioni riutilizzabili tra più siti o ambienti di test.')); ?></p>
                    </div>
                </div>
                <table class="form-table rbf-form-section" role="presentation">
                    <tr class="rbf-form-section__header">
                        <th colspan="2">
                            <div class="rbf-section__header">
                                <h2 class="rbf-section__title"><?php echo esc_html(rbf_translate_string('Configurazione Brand e Colori')); ?></h2>
                                <p class="rbf-section__description"><?php echo esc_html(rbf_translate_string('Personalizza logo, palette e tipografia per mantenere continuità visiva con il sito.')); ?></p>
                            </div>
                        </th>
                    </tr>
                    <tr>
                        <th><label for="rbf_brand_name"><?php echo esc_html(rbf_translate_string('Nome Brand')); ?></label></th>
                        <td>
                            <input type="text" id="rbf_brand_name" name="rbf_settings[brand_name]" value="<?php echo esc_attr($options['brand_name'] ?? ''); ?>" class="regular-text">
                            <p class="description"><?php echo esc_html(rbf_translate_string('Comparirà nell\'anteprima e nelle email di conferma.')); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="rbf_brand_logo_url"><?php echo esc_html(rbf_translate_string('Logo')); ?></label></th>
                        <td>
                            <div class="rbf-brand-logo-control">
                                <div class="rbf-brand-logo-preview">
                                    <?php if (!empty($options['brand_logo_url'])) : ?>
                                        <img src="<?php echo esc_url($options['brand_logo_url']); ?>" alt="" id="rbf-brand-logo-preview-img" />
                                    <?php else : ?>
                                        <span id="rbf-brand-logo-preview-placeholder" class="rbf-brand-logo-placeholder">
                                            <?php echo esc_html(rbf_translate_string('Nessun logo')); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="rbf-brand-logo-control__details">
                                    <input type="hidden" id="rbf_brand_logo_id" name="rbf_settings[brand_logo_id]" value="<?php echo esc_attr($options['brand_logo_id'] ?? 0); ?>">
                                    <input type="url" id="rbf_brand_logo_url" name="rbf_settings[brand_logo_url]" value="<?php echo esc_attr($options['brand_logo_url'] ?? ''); ?>" class="regular-text" placeholder="https://...">
                                    <div class="rbf-action-group rbf-spacing-top-sm">
                                        <button type="button" class="button" id="rbf-brand-logo-select"><?php echo esc_html(rbf_translate_string('Scegli da Libreria')); ?></button>
                                        <button type="button" class="button-link" id="rbf-brand-logo-reset"><?php echo esc_html(rbf_translate_string('Rimuovi logo')); ?></button>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="rbf_accent_color"><?php echo esc_html(rbf_translate_string('Colore Primario')); ?></label></th>
                        <td>
                            <input type="color" id="rbf_accent_color" name="rbf_settings[accent_color]" value="<?php echo esc_attr($options['accent_color'] ?? '#000000'); ?>" class="rbf-color-picker">
                            <p class="description"><?php echo esc_html(rbf_translate_string('Colore principale utilizzato per pulsanti, stati attivi e focus.')); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="rbf_secondary_color"><?php echo esc_html(rbf_translate_string('Colore Secondario')); ?></label></th>
                        <td>
                            <input type="color" id="rbf_secondary_color" name="rbf_settings[secondary_color]" value="<?php echo esc_attr($options['secondary_color'] ?? '#f8b500'); ?>" class="rbf-color-picker">
                            <p class="description"><?php echo esc_html(rbf_translate_string('Suggerito per badge, messaggi informativi e stati secondari.')); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="rbf_border_radius"><?php echo esc_html(rbf_translate_string('Raggio Angoli')); ?></label></th>
                        <td>
                            <select id="rbf_border_radius" name="rbf_settings[border_radius]">
                                <option value="0px" <?php selected($options['border_radius'] ?? '8px', '0px'); ?>><?php echo esc_html(rbf_translate_string('Squadrato (0px)')); ?></option>
                                <option value="4px" <?php selected($options['border_radius'] ?? '8px', '4px'); ?>><?php echo esc_html(rbf_translate_string('Leggermente arrotondato (4px)')); ?></option>
                                <option value="8px" <?php selected($options['border_radius'] ?? '8px', '8px'); ?>><?php echo esc_html(rbf_translate_string('Arrotondato (8px)')); ?></option>
                                <option value="12px" <?php selected($options['border_radius'] ?? '8px', '12px'); ?>><?php echo esc_html(rbf_translate_string('Molto arrotondato (12px)')); ?></option>
                                <option value="16px" <?php selected($options['border_radius'] ?? '8px', '16px'); ?>><?php echo esc_html(rbf_translate_string('Estremamente arrotondato (16px)')); ?></option>
                            </select>
                            <p class="description"><?php echo esc_html(rbf_translate_string('Determina quanto arrotondati appaiono gli angoli di pulsanti e campi')); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="rbf_brand_font_body"><?php echo esc_html(rbf_translate_string('Font corpo testo')); ?></label></th>
                        <td>
                            <select id="rbf_brand_font_body" name="rbf_settings[brand_font_body]">
                                <?php foreach ($fonts_catalog as $font_key => $font_data) : ?>
                                    <option value="<?php echo esc_attr($font_key); ?>" <?php selected($options['brand_font_body'] ?? 'system', $font_key); ?>><?php echo esc_html($font_data['label']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php echo esc_html(rbf_translate_string('Scegli il font principale per testi e descrizioni.')); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="rbf_brand_font_heading"><?php echo esc_html(rbf_translate_string('Font titoli')); ?></label></th>
                        <td>
                            <select id="rbf_brand_font_heading" name="rbf_settings[brand_font_heading]">
                                <?php foreach ($fonts_catalog as $font_key => $font_data) : ?>
                                    <option value="<?php echo esc_attr($font_key); ?>" <?php selected($options['brand_font_heading'] ?? 'system', $font_key); ?>><?php echo esc_html($font_data['label']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php echo esc_html(rbf_translate_string('Puoi utilizzare un font diverso per CTA e titoli.')); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html(rbf_translate_string('Anteprima live')); ?></th>
                        <td>
                            <div id="rbf-brand-preview" class="rbf-brand-preview rbf-form-card rbf-form-card--surface rbf-form-card--preview" data-accent="<?php echo esc_attr($options['accent_color'] ?? '#000000'); ?>" data-secondary="<?php echo esc_attr($options['secondary_color'] ?? '#f8b500'); ?>" data-radius="<?php echo esc_attr($options['border_radius'] ?? '8px'); ?>" data-font-body="<?php echo esc_attr($options['brand_font_body'] ?? 'system'); ?>" data-font-heading="<?php echo esc_attr($options['brand_font_heading'] ?? 'system'); ?>" data-logo="<?php echo esc_url($options['brand_logo_url'] ?? ''); ?>" data-brand-name="<?php echo esc_attr($options['brand_name'] ?? ''); ?>">
                                <div class="rbf-brand-preview__header">
                                    <div class="rbf-brand-preview__logo"></div>
                                    <div class="rbf-brand-preview__title"></div>
                                </div>
                                <div class="rbf-brand-preview__body">
                                    <label class="rbf-brand-preview__label"><?php echo esc_html(rbf_translate_string('Data prenotazione')); ?></label>
                                    <input type="text" class="rbf-brand-preview__input" value="18 settembre 2024" readonly>
                                    <label class="rbf-brand-preview__label"><?php echo esc_html(rbf_translate_string('Persone')); ?></label>
                                    <div class="rbf-brand-preview__counter">
                                        <button type="button" class="rbf-brand-preview__counter-btn">-</button>
                                        <span>2</span>
                                        <button type="button" class="rbf-brand-preview__counter-btn">+</button>
                                    </div>
                                    <button type="button" class="rbf-brand-preview__cta"><?php echo esc_html(rbf_translate_string('Verifica disponibilità')); ?></button>
                                </div>
                                <p class="rbf-brand-preview__note"><?php echo esc_html(rbf_translate_string('Aggiorna colori, font e logo per vedere il risultato in tempo reale.')); ?></p>
                            </div>
                        </td>
                    </tr>
                    <tr class="rbf-form-section__subheader">
                        <th colspan="2">
                            <div class="rbf-section__header">
                                <h3 class="rbf-section__subtitle"><?php echo esc_html(rbf_translate_string('Profili brand')); ?></h3>
                            </div>
                        </th>
                    </tr>
                    <tr>
                        <th><?php echo esc_html(rbf_translate_string('Salva profilo attuale')); ?></th>
                        <td>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="rbf-brand-profile-form">
                                <input type="hidden" name="action" value="rbf_save_brand_profile">
                                <?php wp_nonce_field('rbf_manage_brand_profiles'); ?>
                                <input type="text" name="profile_name" class="regular-text" placeholder="<?php echo esc_attr(rbf_translate_string('Es. Ristorante Milano')); ?>">
                                <?php submit_button(rbf_translate_string('Salva profilo'), 'secondary', '', false); ?>
                                <span class="description"><?php echo esc_html(rbf_translate_string('Salva colori, font e logo per riutilizzarli su altri siti.')); ?></span>
                            </form>
                        </td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html(rbf_translate_string('Profili salvati')); ?></th>
                        <td>
                            <?php if (empty($brand_profiles)) : ?>
                                <p class="description"><?php echo esc_html(rbf_translate_string('Nessun profilo salvato. Crea il primo profilo dal modulo qui sopra.')); ?></p>
                            <?php else : ?>
                                <div class="rbf-brand-profiles-list">
                                    <?php foreach ($brand_profiles as $profile_id => $profile_data) : ?>
                                        <div class="rbf-brand-profile-card">
                                            <strong><?php echo esc_html($profile_data['name'] ?? $profile_id); ?></strong><br>
                                            <span class="description"><?php echo esc_html(rbf_translate_string('Aggiornato il')); ?> <?php echo esc_html($profile_data['saved_at'] ?? ''); ?></span>
                                            <div class="rbf-brand-profile-card__actions">
                                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="rbf-inline-form">
                                                    <?php wp_nonce_field('rbf_manage_brand_profiles'); ?>
                                                    <input type="hidden" name="action" value="rbf_apply_brand_profile">
                                                    <input type="hidden" name="profile_id" value="<?php echo esc_attr($profile_id); ?>">
                                                    <?php submit_button(rbf_translate_string('Applica'), 'primary', '', false); ?>
                                                </form>
                                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="rbf-inline-form" onsubmit="return confirm('<?php echo esc_js(rbf_translate_string('Eliminare questo profilo brand?')); ?>');">
                                                    <?php wp_nonce_field('rbf_manage_brand_profiles'); ?>
                                                    <input type="hidden" name="action" value="rbf_delete_brand_profile">
                                                    <input type="hidden" name="profile_id" value="<?php echo esc_attr($profile_id); ?>">
                                                    <?php submit_button(rbf_translate_string('Elimina'), 'delete', '', false); ?>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html(rbf_translate_string('Importa / esporta')); ?></th>
                        <td>
                            <div class="rbf-form-card rbf-form-card--surface rbf-form-card--stack">
                                <label for="rbf-brand-profiles-export" class="description rbf-label-block"><?php echo esc_html(rbf_translate_string('Esporta profili (copia e incolla il JSON)')); ?></label>
                                <textarea readonly id="rbf-brand-profiles-export" class="large-text code rbf-code-input" rows="5"><?php echo esc_textarea($brand_profiles_export); ?></textarea>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="rbf-spacing-top-sm">
                                    <?php wp_nonce_field('rbf_manage_brand_profiles'); ?>
                                    <input type="hidden" name="action" value="rbf_import_brand_profiles">
                                    <label for="rbf-brand-profiles-import" class="description rbf-label-block"><?php echo esc_html(rbf_translate_string('Importa profili (incolla JSON e conferma)')); ?></label>
                                    <textarea id="rbf-brand-profiles-import" name="brand_profiles_json" class="large-text code rbf-code-input" rows="4" placeholder="<?php echo esc_attr('[{"profile_id":{"name":"Brand","settings":{...}}}]'); ?>"></textarea>
                                    <?php submit_button(rbf_translate_string('Importa profili'), 'secondary'); ?>
                                </form>
                            </div>
                        </td>
                    </tr>

                    <tr class="rbf-form-section__subheader">
                        <th colspan="2">
                            <div class="rbf-section__header">
                                <h3 class="rbf-section__subtitle"><?php echo esc_html(rbf_translate_string('Pagina di Conferma Prenotazione')); ?></h3>
                            </div>
                        </th>
                    </tr>
                    <tr>
                        <th><label for="rbf_booking_page_id"><?php echo esc_html(rbf_translate_string('Pagina del modulo di prenotazione')); ?></label></th>
                        <td>
                            <?php
                            wp_dropdown_pages([
                                'name' => 'rbf_settings[booking_page_id]',
                                'id' => 'rbf_booking_page_id',
                                'selected' => absint($options['booking_page_id'] ?? 0),
                                'show_option_none' => rbf_translate_string('Seleziona una pagina'),
                                'option_none_value' => '0',
                            ]);
                            ?>
                            <p class="description"><?php echo esc_html(rbf_translate_string('Utilizzata per i link di conferma generati dal backend. Se vuota, il plugin tenta di individuarla automaticamente.')); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="rbf-settings-tab-panel rbf-tab-panel" id="rbf-tab-availability" data-tab-panel="availability" role="tabpanel" aria-labelledby="rbf-tab-link-availability">
                <div class="rbf-tab-intro" role="note">
                    <div class="rbf-tab-intro__icon" aria-hidden="true">
                        <span class="dashicons dashicons-schedule"></span>
                    </div>
                    <div class="rbf-tab-intro__content">
                        <h3 class="rbf-tab-intro__title"><?php echo esc_html(rbf_translate_string('Orari e servizi offerti')); ?></h3>
                        <p class="rbf-tab-intro__description"><?php echo esc_html(rbf_translate_string('Attiva i giorni di apertura e definisci i pasti personalizzati con capienza, durate e fasce orarie dedicate.')); ?></p>
                        <p class="rbf-tab-intro__description"><?php echo esc_html(rbf_translate_string('I pasti attivati qui vengono mostrati nel form pubblico e nelle prenotazioni inserite manualmente dallo staff.')); ?></p>
                    </div>
                </div>
                <table class="form-table rbf-form-section" role="presentation">
                    <tr class="rbf-form-section__header">
                        <th colspan="2">
                            <div class="rbf-section__header">
                                <h2 class="rbf-section__title"><?php echo esc_html(rbf_translate_string('Disponibilità Settimanale')); ?></h2>
                                <p class="rbf-section__description"><?php echo esc_html(rbf_translate_string('Attiva i giorni di apertura e imposta fasce orarie coerenti con il servizio.')); ?></p>
                            </div>
                        </th>
                    </tr>
                    <tr>
                        <th><?php echo esc_html(rbf_translate_string('Giorni di apertura')); ?></th>
                        <td>
                            <div class="rbf-weekday-toggle-group">
                                <?php foreach ($day_labels as $day_key => $day_label) {
                                    $option_key = "open_{$day_key}";
                                    $is_open = ($options[$option_key] ?? 'yes') === 'yes';
                                    ?>
                                    <label class="rbf-checkbox-pill">
                                        <input type="checkbox" name="rbf_settings[<?php echo esc_attr($option_key); ?>]" value="yes" <?php checked($is_open); ?>>
                                        <span><?php echo esc_html($day_label); ?></span>
                                    </label>
                                    <?php
                                } ?>
                            </div>
                            <p class="description rbf-spacing-top-sm">
                                <?php echo esc_html(rbf_translate_string('Deseleziona i giorni in cui il ristorante resta chiuso.')); ?>
                            </p>
                        </td>
                    </tr>

                    <tr class="rbf-form-section__subheader">
                        <th colspan="2">
                            <div class="rbf-section__header">
                                <h3 class="rbf-section__subtitle"><?php echo esc_html(rbf_translate_string('Configurazione Pasti')); ?></h3>
                            </div>
                        </th>
                    </tr>
                    <tr>
                        <th><?php echo esc_html(rbf_translate_string('Pasti Personalizzati')); ?></th>
                        <td>
                            <div class="rbf-form-card rbf-form-card--surface rbf-form-card--stack">
                                <div id="custom-meals-container">
                                    <?php
                                    $custom_meals = $options['custom_meals'] ?? rbf_get_default_custom_meals();
                                    if (!is_array($custom_meals)) {
                                        $custom_meals = [];
                                    }
                                    ?>
                                    <div class="notice notice-info inline rbf-notice-stack">
                                        <p><?php echo esc_html(rbf_translate_string('Importante: dopo l\'installazione non sono presenti pasti preconfigurati. Configura i servizi del tuo ristorante utilizzando "Aggiungi Pasto" e salva le modifiche per renderli disponibili nel form.')); ?></p>
                                    </div>
                                    <?php
                                    if (empty($custom_meals)) {
                                        ?>
                                        <div class="notice notice-warning inline rbf-no-meals-notice rbf-notice-stack">
                                            <p><?php echo esc_html(rbf_translate_string('Nessun pasto è attualmente configurato. Il modulo di prenotazione rimane inattivo finché non aggiungi e attivi almeno un pasto personalizzato.')); ?></p>
                                        </div>
                                        <?php
                                    }

                                    foreach ($custom_meals as $index => $meal) {
                                        echo rbf_render_custom_meal_item($index, $meal, $day_labels);
                                    }
                                    ?>
                                </div>

                                <button type="button" id="add-meal" class="button button-primary rbf-spacing-top-sm">
                                    <?php echo esc_html(rbf_translate_string('Aggiungi Pasto')); ?>
                                </button>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="rbf-settings-tab-panel rbf-tab-panel" id="rbf-tab-calendar" data-tab-panel="calendar" role="tabpanel" aria-labelledby="rbf-tab-link-calendar">
                <div class="rbf-tab-intro" role="note">
                    <div class="rbf-tab-intro__icon" aria-hidden="true">
                        <span class="dashicons dashicons-calendar-alt"></span>
                    </div>
                    <div class="rbf-tab-intro__content">
                        <h3 class="rbf-tab-intro__title"><?php echo esc_html(rbf_translate_string('Gestione calendario avanzata')); ?></h3>
                        <p class="rbf-tab-intro__description"><?php echo esc_html(rbf_translate_string('Configura chiusure straordinarie, giornate speciali e orari estesi per mantenere il calendario sempre coerente.')); ?></p>
                        <p class="rbf-tab-intro__description"><?php echo esc_html(rbf_translate_string('Le modifiche vengono raccolte nel riquadro sottostante: puoi affinarle anche manualmente seguendo il formato guidato.')); ?></p>
                    </div>
                </div>
                <table class="form-table rbf-form-section" role="presentation">
                    <tr class="rbf-form-section__header">
                        <th colspan="2">
                            <div class="rbf-section__header">
                                <h2 class="rbf-section__title"><?php echo esc_html(rbf_translate_string('Calendario & Eccezioni')); ?></h2>
                                <p class="rbf-section__description"><?php echo esc_html(rbf_translate_string('Gestisci festivi, chiusure e giornate speciali mantenendo una chiara panoramica.')); ?></p>
                            </div>
                        </th>
                    </tr>
                    <tr>
                        <th><label for="rbf_closed_dates"><?php echo esc_html(rbf_translate_string('Gestione Eccezioni')); ?></label></th>
                        <td>
                            <div id="rbf_exceptions_manager" class="rbf-form-card rbf-form-card--surface rbf-form-card--stack">
                                <p class="description rbf-spacing-bottom-md">
                                    <?php echo esc_html(rbf_translate_string('Gestisci chiusure straordinarie, festività, eventi speciali e orari estesi.')); ?>
                                </p>

                                <div class="rbf-exception-add">
                                    <h4><?php echo esc_html(rbf_translate_string('Aggiungi Nuova Eccezione')); ?></h4>
                                    <div class="rbf-exception-add__grid">
                                        <div class="rbf-field">
                                            <label class="rbf-field__label" for="exception_date"><?php echo esc_html(rbf_translate_string('Data')); ?></label>
                                            <div class="rbf-field__control">
                                                <input type="date" id="exception_date">
                                            </div>
                                        </div>
                                        <div class="rbf-field">
                                            <label class="rbf-field__label" for="exception_type"><?php echo esc_html(rbf_translate_string('Tipo')); ?></label>
                                            <div class="rbf-field__control">
                                                <select id="exception_type">
                                                    <option value="closure"><?php echo esc_html(rbf_translate_string('Chiusura')); ?></option>
                                                    <option value="holiday"><?php echo esc_html(rbf_translate_string('Festività')); ?></option>
                                                    <option value="special"><?php echo esc_html(rbf_translate_string('Evento Speciale')); ?></option>
                                                    <option value="extended"><?php echo esc_html(rbf_translate_string('Orari Estesi')); ?></option>
                                                </select>
                                            </div>
                                        </div>
                                        <div id="special_hours_container" class="rbf-field rbf-exception-add__special">
                                            <label class="rbf-field__label" for="special_hours"><?php echo esc_html(rbf_translate_string('Orari Speciali')); ?></label>
                                            <div class="rbf-field__control">
                                                <input type="text" id="special_hours" placeholder="es. 18:00-02:00">
                                            </div>
                                        </div>
                                        <div class="rbf-field rbf-field--full rbf-exception-add__description">
                                            <label class="rbf-field__label" for="exception_description"><?php echo esc_html(rbf_translate_string('Descrizione')); ?></label>
                                            <div class="rbf-field__control">
                                                <input type="text" id="exception_description" placeholder="<?php echo esc_attr(rbf_translate_string('es. Chiusura per ferie')); ?>">
                                            </div>
                                        </div>
                                        <button type="button" id="add_exception_btn" class="button button-primary"><?php echo esc_html(rbf_translate_string('Aggiungi')); ?></button>
                                    </div>
                                </div>

                                <div class="rbf-exceptions-list">
                                    <h4><?php echo esc_html(rbf_translate_string('Eccezioni Attive')); ?></h4>
                                    <div id="exceptions_list_display"></div>
                                </div>

                                <textarea id="rbf_closed_dates" name="rbf_settings[closed_dates]" rows="8" class="large-text rbf-code-input"><?php echo esc_textarea($options['closed_dates']); ?></textarea>
                                <p class="description">
                                    <?php echo esc_html(rbf_translate_string('Formato manuale: Data|Tipo|Orari|Descrizione (es. 2024-12-25|closure||Natale) oppure formato semplice (es. 2024-12-25)')); ?>
                                </p>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="rbf-settings-tab-panel rbf-tab-panel" id="rbf-tab-integrations" data-tab-panel="integrations" role="tabpanel" aria-labelledby="rbf-tab-link-integrations">
                <div class="rbf-tab-intro" role="note">
                    <div class="rbf-tab-intro__icon" aria-hidden="true">
                        <span class="dashicons dashicons-shield"></span>
                    </div>
                    <div class="rbf-tab-intro__content">
                        <h3 class="rbf-tab-intro__title"><?php echo esc_html(rbf_translate_string('Integrazioni e sicurezza')); ?></h3>
                        <p class="rbf-tab-intro__description"><?php echo esc_html(rbf_translate_string('Controlla i preset di tracking, la gestione email e le difese anti-bot per mantenere affidabile il flusso di prenotazione.')); ?></p>
                        <p class="rbf-tab-intro__description"><?php echo esc_html(rbf_translate_string('Ricordati di salvare al termine per applicare le modifiche in modo permanente.')); ?></p>
                    </div>
                </div>
                <table class="form-table rbf-form-section" role="presentation">
                    <tr class="rbf-form-section__header">
                        <th colspan="2">
                            <div class="rbf-section__header">
                                <h2 class="rbf-section__title"><?php echo esc_html(rbf_translate_string('Integrazioni e Marketing')); ?></h2>
                                <p class="rbf-section__description"><?php echo esc_html(rbf_translate_string('Collega gli strumenti di tracciamento e comunicazione con indicazioni sempre a portata di mano.')); ?></p>
                            </div>
                        </th>
                    </tr>
                    <tr>
                        <th><label for="rbf_notification_email"><?php echo esc_html(rbf_translate_string('Email per Notifiche Ristorante')); ?></label></th>
                        <td><input type="email" id="rbf_notification_email" name="rbf_settings[notification_email]" value="<?php echo esc_attr($options['notification_email']); ?>" class="regular-text" placeholder="es. ristorante@esempio.com"></td>
                    </tr>
                    <tr>
                        <th><label for="rbf_webmaster_email"><?php echo esc_html(rbf_translate_string('Email per Notifiche Webmaster')); ?></label></th>
                        <td><input type="email" id="rbf_webmaster_email" name="rbf_settings[webmaster_email]" value="<?php echo esc_attr($options['webmaster_email']); ?>" class="regular-text" placeholder="es. webmaster@esempio.com"></td>
                    </tr>
                    <tr class="rbf-form-section__subheader">
                        <th colspan="2">
                            <div class="rbf-section__header">
                                <h3 class="rbf-section__subtitle"><?php echo esc_html(rbf_translate_string('Contatti per Modifiche Prenotazioni')); ?></h3>
                            </div>
                        </th>
                    </tr>
                    <tr>
                        <th><label for="rbf_booking_change_email"><?php echo esc_html(rbf_translate_string('Email per Richieste di Modifica')); ?></label></th>
                        <td>
                            <input type="email" id="rbf_booking_change_email" name="rbf_settings[booking_change_email]" value="<?php echo esc_attr($options['booking_change_email']); ?>" class="regular-text" placeholder="es. prenotazioni@esempio.com">
                            <p class="description"><?php echo esc_html(rbf_translate_string('Mostrata nel riepilogo di conferma per indicare dove scrivere in caso di modifiche.')); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="rbf_booking_change_phone"><?php echo esc_html(rbf_translate_string('Telefono per Richieste di Modifica')); ?></label></th>
                        <td>
                            <input type="text" id="rbf_booking_change_phone" name="rbf_settings[booking_change_phone]" value="<?php echo esc_attr($options['booking_change_phone']); ?>" class="regular-text" placeholder="es. +39 012 345 6789">
                            <p class="description"><?php echo esc_html(rbf_translate_string('Comparirà accanto all\'email nel messaggio di conferma. Lascia vuoto se non vuoi mostrarlo.')); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="rbf_ga4_id"><?php echo esc_html(rbf_translate_string('ID misurazione GA4')); ?></label></th>
                        <td><input type="text" id="rbf_ga4_id" name="rbf_settings[ga4_id]" value="<?php echo esc_attr($options['ga4_id']); ?>" class="regular-text" placeholder="G-XXXXXXXXXX"></td>
                    </tr>
                    <tr>
                        <th><label for="rbf_ga4_api_secret">GA4 API Secret (per invii server-side)</label></th>
                        <td><input type="text" id="rbf_ga4_api_secret" name="rbf_settings[ga4_api_secret]" value="<?php echo esc_attr($options['ga4_api_secret']); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="rbf_gtm_id"><?php echo esc_html(rbf_translate_string('ID GTM')); ?></label></th>
                        <td><input type="text" id="rbf_gtm_id" name="rbf_settings[gtm_id]" value="<?php echo esc_attr($options['gtm_id']); ?>" class="regular-text" placeholder="GTM-XXXXXXX"></td>
                    </tr>
                    <tr>
                        <th><label for="rbf_gtm_hybrid"><?php echo esc_html(rbf_translate_string('Modalità ibrida GTM + GA4')); ?></label></th>
                        <td><input type="checkbox" id="rbf_gtm_hybrid" name="rbf_settings[gtm_hybrid]" value="yes" <?php checked(($options['gtm_hybrid'] ?? '') === 'yes'); ?>></td>
                    </tr>
                    <tr>
                        <th><label for="rbf_google_ads_conversion_id"><?php echo esc_html(rbf_translate_string('ID Conversione Google Ads')); ?></label></th>
                        <td><input type="text" id="rbf_google_ads_conversion_id" name="rbf_settings[google_ads_conversion_id]" value="<?php echo esc_attr($options['google_ads_conversion_id'] ?? ''); ?>" class="regular-text" placeholder="AW-123456789"></td>
                    </tr>
                    <tr>
                        <th><label for="rbf_google_ads_conversion_label"><?php echo esc_html(rbf_translate_string('Etichetta Conversione Google Ads')); ?></label></th>
                        <td><input type="text" id="rbf_google_ads_conversion_label" name="rbf_settings[google_ads_conversion_label]" value="<?php echo esc_attr($options['google_ads_conversion_label'] ?? ''); ?>" class="regular-text" placeholder="abcDEF123456"></td>
                    </tr>
                    <tr>
                        <th><label for="rbf_meta_pixel_id"><?php echo esc_html(rbf_translate_string('ID Meta Pixel')); ?></label></th>
                        <td><input type="text" id="rbf_meta_pixel_id" name="rbf_settings[meta_pixel_id]" value="<?php echo esc_attr($options['meta_pixel_id']); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="rbf_meta_access_token">Meta Access Token (per invii server-side)</label></th>
                        <td><input type="password" id="rbf_meta_access_token" name="rbf_settings[meta_access_token]" value="<?php echo esc_attr($options['meta_access_token']); ?>" class="regular-text"></td>
                    </tr>

                    <tr class="rbf-form-section__subheader">
                        <th colspan="2">
                            <div class="rbf-section__header">
                                <h3 class="rbf-section__subtitle"><?php echo esc_html(rbf_translate_string('Pacchetti plug & play')); ?></h3>
                            </div>
                        </th>
                    </tr>
                    <tr>
                        <th><?php echo esc_html(rbf_translate_string('Preset disponibili')); ?></th>
                        <td>
                            <div class="rbf-tracking-packages">
                                <?php foreach ($tracking_catalog as $package_id => $package_info) :
                                    $enabled = !empty($tracking_packages[$package_id]['enabled']);
                                    $required_fields = $package_info['required_options'] ?? [];
                                    $missing_fields = [];
                                    foreach ($required_fields as $field_key) {
                                        if (empty($options[$field_key])) {
                                            $missing_fields[] = $field_key;
                                        }
                                    }
                                    ?>
                                    <div class="rbf-tracking-package <?php echo $enabled ? 'is-active' : ''; ?>">
                                        <h4><?php echo esc_html($package_info['label']); ?></h4>
                                        <p class="description"><?php echo esc_html($package_info['description']); ?></p>
                                        <?php if (!empty($missing_fields)) : ?>
                                            <p class="rbf-tracking-warning"><?php echo esc_html(rbf_translate_string('Completa prima i campi:')); ?> <strong><?php echo esc_html(implode(', ', $missing_fields)); ?></strong></p>
                                        <?php endif; ?>
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="rbf-inline-form rbf-spacing-top-xs">
                                            <?php wp_nonce_field('rbf_toggle_tracking_package'); ?>
                                            <input type="hidden" name="action" value="rbf_toggle_tracking_package">
                                            <input type="hidden" name="package" value="<?php echo esc_attr($package_id); ?>">
                                            <input type="hidden" name="enabled" value="<?php echo $enabled ? '0' : '1'; ?>">
                                            <button type="submit" class="button <?php echo $enabled ? '' : 'button-primary'; ?>" <?php disabled(!empty($missing_fields)); ?>>
                                                <?php echo esc_html($enabled ? rbf_translate_string('Disattiva preset') : rbf_translate_string('Attiva preset')); ?>
                                            </button>
                                        </form>
                                        <?php if ($package_id === 'consent_helper') :
                                            $snippets = rbf_get_consent_helper_snippets();
                                            ?>
                                            <details class="rbf-spacing-top-sm">
                                                <summary><?php echo esc_html(rbf_translate_string('Snippet CMP pronti all\'uso')); ?></summary>
                                                <ul>
                                                    <?php foreach ($snippets as $snippet) : ?>
                                                        <li>
                                                            <strong><?php echo esc_html($snippet['label']); ?></strong>
                                                            <textarea readonly class="large-text code rbf-code-input rbf-spacing-top-xs" rows="2"><?php echo esc_textarea($snippet['code']); ?></textarea>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </details>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html(rbf_translate_string('Ultimi eventi tracking')); ?></th>
                        <td>
                            <?php if (empty($recent_tracking_events)) : ?>
                                <p class="description"><?php echo esc_html(rbf_translate_string('Gli eventi appariranno qui dopo le prime interazioni.')); ?></p>
                            <?php else : ?>
                                <ul class="rbf-tracking-events">
                                    <?php foreach ($recent_tracking_events as $event) :
                                        $time = isset($event['time']) ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), (int) $event['time']) : '';
                                        ?>
                                        <li>
                                            <strong><?php echo esc_html(strtoupper($event['channel'] ?? '')); ?></strong> ·
                                            <code><?php echo esc_html($event['event'] ?? ''); ?></code>
                                            <?php if ($time) : ?>
                                                <span class="description">(<?php echo esc_html($time); ?>)</span>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <tr class="rbf-form-section__subheader">
                        <th colspan="2">
                            <div class="rbf-section__header">
                                <h3 class="rbf-section__subtitle"><?php echo esc_html(rbf_translate_string('Impostazioni Brevo')); ?></h3>
                            </div>
                        </th>
                    </tr>
                    <tr>
                        <th><label for="rbf_brevo_api"><?php echo esc_html(rbf_translate_string('API Key Brevo')); ?></label></th>
                        <td><input type="password" id="rbf_brevo_api" name="rbf_settings[brevo_api]" value="<?php echo esc_attr($options['brevo_api']); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="rbf_brevo_list_it"><?php echo esc_html(rbf_translate_string('ID Lista Brevo (IT)')); ?></label></th>
                        <td><input type="number" id="rbf_brevo_list_it" name="rbf_settings[brevo_list_it]" value="<?php echo esc_attr($options['brevo_list_it']); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="rbf_brevo_list_en"><?php echo esc_html(rbf_translate_string('ID Lista Brevo (EN)')); ?></label></th>
                        <td><input type="number" id="rbf_brevo_list_en" name="rbf_settings[brevo_list_en]" value="<?php echo esc_attr($options['brevo_list_en']); ?>"></td>
                    </tr>

                    <tr class="rbf-form-section__subheader">
                        <th colspan="2">
                            <div class="rbf-section__header">
                                <h3 class="rbf-section__subtitle"><?php echo esc_html(rbf_translate_string('Protezione Anti-Bot')); ?></h3>
                            </div>
                        </th>
                    </tr>
                    <tr>
                        <th><label for="rbf_recaptcha_site_key"><?php echo esc_html(rbf_translate_string('reCAPTCHA v3 Site Key')); ?></label></th>
                        <td>
                            <input type="text" id="rbf_recaptcha_site_key" name="rbf_settings[recaptcha_site_key]" value="<?php echo esc_attr($options['recaptcha_site_key'] ?? ''); ?>" class="regular-text" placeholder="6Le...">
                            <p class="description"><?php echo esc_html(rbf_translate_string('Chiave pubblica per reCAPTCHA v3. Lascia vuoto per disabilitare.')); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="rbf_recaptcha_secret_key"><?php echo esc_html(rbf_translate_string('reCAPTCHA v3 Secret Key')); ?></label></th>
                        <td>
                            <input type="password" id="rbf_recaptcha_secret_key" name="rbf_settings[recaptcha_secret_key]" value="<?php echo esc_attr($options['recaptcha_secret_key'] ?? ''); ?>" class="regular-text" placeholder="6Le...">
                            <p class="description"><?php echo esc_html(rbf_translate_string('Chiave segreta per reCAPTCHA v3.')); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="rbf_recaptcha_threshold"><?php echo esc_html(rbf_translate_string('Soglia reCAPTCHA')); ?></label></th>
                        <td>
                            <input type="number" id="rbf_recaptcha_threshold" name="rbf_settings[recaptcha_threshold]" value="<?php echo esc_attr($options['recaptcha_threshold'] ?? '0.5'); ?>" step="0.1" min="0" max="1" class="rbf-input-compact">
                            <p class="description"><?php echo esc_html(rbf_translate_string('Soglia minima per considerare valida una submission (0.0-1.0). Default: 0.5')); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <?php submit_button(); ?>
        </form>
    </div>
    <?php
    $meal_template_html = rbf_render_custom_meal_item('__INDEX__', [], $day_labels, true);
    ?>
    <script type="text/template" id="rbf-meal-template"><?php echo $meal_template_html; ?></script>
    <?php
    $analytics_by_status = $analytics['by_status'] ?? [];
    $analytics_by_meal = $analytics['by_meal'] ?? [];
    $analytics_daily_bookings = $analytics['daily_bookings'] ?? [];
    $analytics_source_breakdown = $source_breakdown_values ?? [];
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const statusData = <?php echo wp_json_encode($analytics_by_status); ?>;
        const mealData = <?php echo wp_json_encode($analytics_by_meal); ?>;
        const dailyData = <?php echo wp_json_encode($analytics_daily_bookings); ?>;
        const sourceBreakdown = <?php echo wp_json_encode($analytics_source_breakdown); ?>;

        const statusCanvas = document.getElementById('statusChart');
        if (statusCanvas) {
            new Chart(statusCanvas, {
                type: 'doughnut',
                data: {
                    labels: Object.keys(statusData),
                    datasets: [{
                        data: Object.values(statusData),
                        backgroundColor: ['#f59e0b', '#10b981', '#06b6d4', '#ef4444', '#8b5cf6'],
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                },
            });
        }

        const mealCanvas = document.getElementById('mealChart');
        if (mealCanvas) {
            new Chart(mealCanvas, {
                type: 'bar',
                data: {
                    labels: Object.keys(mealData),
                    datasets: [{
                        data: Object.values(mealData),
                        backgroundColor: ['#3b82f6', '#f59e0b', '#10b981'],
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    },
                },
            });
        }

        const dailyCanvas = document.getElementById('dailyChart');
        if (dailyCanvas) {
            new Chart(dailyCanvas, {
                type: 'line',
                data: {
                    labels: Object.keys(dailyData),
                    datasets: [{
                        label: '<?php echo esc_js(rbf_translate_string('Prenotazioni')); ?>',
                        data: Object.values(dailyData),
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        fill: true,
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: { beginAtZero: true }
                    },
                },
            });
        }

        const sourceCanvas = document.getElementById('sourceChart');
        if (sourceCanvas && sourceBreakdown.length) {
            const sourceLabels = sourceBreakdown.map(item => item.label);
            const bookingsDataset = sourceBreakdown.map(item => Number(item.bookings || 0));
            const completedDataset = sourceBreakdown.map(item => Number(item.completed || 0));
            const conversionDataset = sourceBreakdown.map(item => Number(item.conversion_rate || 0));

            new Chart(sourceCanvas, {
                type: 'bar',
                data: {
                    labels: sourceLabels,
                    datasets: [
                        {
                            label: '<?php echo esc_js(rbf_translate_string('Prenotazioni')); ?>',
                            data: bookingsDataset,
                            backgroundColor: '#2563eb',
                            yAxisID: 'y'
                        },
                        {
                            label: '<?php echo esc_js(rbf_translate_string('Prenotazioni Completate')); ?>',
                            data: completedDataset,
                            backgroundColor: '#10b981',
                            yAxisID: 'y'
                        },
                        {
                            type: 'line',
                            label: '<?php echo esc_js(rbf_translate_string('Tasso di Conversione')); ?>',
                            data: conversionDataset,
                            borderColor: '#f97316',
                            backgroundColor: 'rgba(249, 115, 22, 0.25)',
                            borderWidth: 2,
                            fill: false,
                            yAxisID: 'y1',
                            tension: 0.35,
                            pointRadius: 3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        },
                        y1: {
                            beginAtZero: true,
                            position: 'right',
                            min: 0,
                            max: 100,
                            grid: {
                                drawOnChartArea: false
                            },
                            ticks: {
                                callback: value => Number(value).toLocaleString('it-IT', { minimumFractionDigits: 0 }) + '%'
                            }
                        },
                    },
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const datasetLabel = context.dataset.label || '';
                                    const value = context.parsed.y;

                                    if (context.dataset.yAxisID === 'y1') {
                                        return datasetLabel + ': ' + Number(value).toLocaleString('it-IT', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + '%';
                                    }

                                    return datasetLabel + ': ' + Number(value).toLocaleString('it-IT');
                                }
                            },
                        },
                    },
                },
            });
        }
    });
    </script>
    <?php
}

/**
 * Calendar page HTML
 */
function rbf_calendar_page_html() {
    if (!rbf_require_booking_capability()) {
        return;
    }

    rbf_enqueue_fullcalendar_assets();

    if (!wp_script_is('rbf-admin-js', 'registered')) {
        wp_register_script(
            'rbf-admin-js',
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/admin.js',
            ['jquery'],
            rbf_get_asset_version('js/admin.js'),
            true
        );
    }

    wp_enqueue_script('rbf-admin-js');

    wp_localize_script('rbf-admin-js', 'rbfAdminData', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('rbf_calendar_nonce'),
        'editUrl' => admin_url('post.php?post=BOOKING_ID&action=edit')
    ]);
    ?>
    <div class="rbf-admin-wrap rbf-admin-wrap--wide">
        <h1><?php echo esc_html(rbf_translate_string('Prenotazioni')); ?></h1>
        
        <div id="rbf-calendar"></div>
    </div>
    <?php
}

/**
 * Weekly staff view page HTML
 */
function rbf_weekly_staff_page_html() {
    if (!rbf_require_booking_capability()) {
        return;
    }

    rbf_enqueue_fullcalendar_assets();
    wp_enqueue_script(
        'rbf-weekly-staff-js',
        plugin_dir_url(dirname(__FILE__)) . 'assets/js/weekly-staff.js',
        ['jquery', 'fullcalendar-js'],
        rbf_get_asset_version('js/weekly-staff.js'),
        true
    );

    wp_localize_script('rbf-weekly-staff-js', 'rbfWeeklyStaffData', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('rbf_weekly_staff_nonce'),
        'editUrl' => admin_url('post.php?post=BOOKING_ID&action=edit'),
        'labels' => [
            'moveSuccess' => rbf_translate_string('Prenotazione spostata con successo'),
            'moveError' => rbf_translate_string('Errore nello spostamento della prenotazione'),
            'conflictError' => rbf_translate_string('Conflitto di orario rilevato'),
            'confirmMove' => rbf_translate_string('Confermi lo spostamento della prenotazione?'),
        ]
    ]);
    ?>
    <div class="rbf-admin-wrap rbf-admin-wrap--wide">
        <h1><?php echo esc_html(rbf_translate_string('Vista Settimanale Staff')); ?></h1>
        <p class="description"><?php echo esc_html(rbf_translate_string('Vista compatta per lo staff con funzionalità drag & drop per spostare le prenotazioni.')); ?></p>
        
        <div id="rbf-weekly-staff-calendar"></div>
        
        <div id="rbf-move-notification" class="notice" hidden>
            <p id="rbf-move-message"></p>
        </div>
    </div>
    <?php
}

/**
 * AJAX handler for calendar bookings
 */
add_action('wp_ajax_rbf_get_bookings_for_calendar', 'rbf_get_bookings_for_calendar_callback');

/**
 * Prepare a sanitized calendar event array for a booking.
 *
 * @param WP_Post $booking Booking post object.
 * @param array   $meta    Raw meta array keyed by meta name.
 * @return array|null Sanitized event data or null when mandatory data is missing.
 */
function rbf_prepare_calendar_event_from_booking($booking, array $meta) {
    if (!($booking instanceof WP_Post)) {
        return null;
    }

    $booking_id = absint($booking->ID);

    $raw_date = isset($meta['rbf_data'][0]) ? sanitize_text_field($meta['rbf_data'][0]) : '';
    if ($raw_date === '') {
        return null;
    }

    $date_object = DateTimeImmutable::createFromFormat('Y-m-d', $raw_date);
    if (!$date_object) {
        return null;
    }

    $raw_time = '';
    if (isset($meta['rbf_time'][0])) {
        $raw_time = $meta['rbf_time'][0];
    } elseif (isset($meta['rbf_orario'][0])) {
        $raw_time = $meta['rbf_orario'][0];
    }
    $raw_time = sanitize_text_field($raw_time);

    $time_value = '';
    if ($raw_time !== '') {
        $time_object = DateTimeImmutable::createFromFormat('H:i', $raw_time);
        if ($time_object) {
            $time_value = $time_object->format('H:i');
        } else {
            $fallback_time = preg_replace('/[^0-9:]/', '', $raw_time);
            if (is_string($fallback_time) && preg_match('/^\d{2}:\d{2}$/', $fallback_time)) {
                $time_value = $fallback_time;
            }
        }
    }

    $start = $date_object->format('Y-m-d');
    if ($time_value !== '') {
        $start .= 'T' . $time_value;
    }

    $raw_title = is_string($booking->post_title) ? $booking->post_title : '';
    $title = wp_strip_all_tags($raw_title);
    $title = trim($title);
    if ($title === '') {
        $title = sprintf(rbf_translate_string('Prenotazione #%d'), $booking_id);
    }

    $people_count = isset($meta['rbf_persone'][0]) ? absint($meta['rbf_persone'][0]) : 0;
    if ($people_count > 0) {
        $people_label = sprintf(_n('%d persona', '%d persone', $people_count, 'rbf'), $people_count);
        $title = sprintf('%s (%s)', $title, $people_label);
    }

    $status_raw = isset($meta['rbf_booking_status'][0]) ? $meta['rbf_booking_status'][0] : 'confirmed';
    $status = sanitize_key($status_raw);
    $allowed_statuses = array_keys(rbf_get_booking_statuses());
    if (!in_array($status, $allowed_statuses, true)) {
        $status = 'confirmed';
    }

    $status_colors = [
        'confirmed' => '#28a745',
        'cancelled' => '#dc3545',
        'completed' => '#6c757d',
    ];
    $color = $status_colors[$status] ?? '#28a745';

    $class_name = sanitize_html_class('fc-status-' . $status, 'fc-status-confirmed');

    $first_name = isset($meta['rbf_nome'][0]) ? sanitize_text_field($meta['rbf_nome'][0]) : '';
    $last_name = isset($meta['rbf_cognome'][0]) ? sanitize_text_field($meta['rbf_cognome'][0]) : '';
    $customer_name = trim($first_name . ' ' . $last_name);

    $email = isset($meta['rbf_email'][0]) ? sanitize_email($meta['rbf_email'][0]) : '';
    $phone = isset($meta['rbf_tel'][0]) ? rbf_sanitize_phone_field($meta['rbf_tel'][0]) : '';
    $notes = isset($meta['rbf_allergie'][0]) ? sanitize_textarea_field($meta['rbf_allergie'][0]) : '';
    $meal_meta = '';
    if (isset($meta['rbf_meal'][0])) {
        $meal_meta = $meta['rbf_meal'][0];
    } elseif (isset($meta['rbf_orario'][0])) {
        $meal_meta = $meta['rbf_orario'][0];
    }
    $meal = sanitize_text_field($meal_meta);

    $booking_date_display = '';
    if (function_exists('wp_date')) {
        $booking_date_display = wp_date(get_option('date_format', 'd/m/Y'), $date_object->getTimestamp());
    } else {
        $booking_date_display = $date_object->format('d/m/Y');
    }

    $event = [
        'title' => $title,
        'start' => $start,
        'backgroundColor' => $color,
        'borderColor' => $color,
        'className' => $class_name,
        'extendedProps' => [
            'booking_id' => $booking_id,
            'customer_name' => $customer_name,
            'customer_email' => $email,
            'customer_phone' => $phone,
            'booking_date' => $booking_date_display,
            'booking_time' => $time_value,
            'people' => $people_count,
            'notes' => $notes,
            'status' => $status,
            'meal' => $meal,
        ],
    ];

    if (function_exists('apply_filters')) {
        /**
         * Allow developers to filter the sanitized calendar event data before it is returned.
         *
         * @param array   $event   Prepared event array.
         * @param WP_Post $booking Booking post object.
         * @param array   $meta    Raw meta array for the booking.
         */
        $event = apply_filters('rbf_calendar_booking_event', $event, $booking, $meta);
    }

    return $event;
}

function rbf_get_bookings_for_calendar_callback() {
    check_ajax_referer('rbf_calendar_nonce', '_ajax_nonce');

    if (!rbf_user_can_manage_bookings()) {
        wp_send_json_error('Permessi insufficienti', 403);
    }

    $sanitized = rbf_sanitize_input_fields($_POST, [
        'start' => 'text',
        'end' => 'text'
    ]);

    $start = $sanitized['start'];
    $end = $sanitized['end'];

    $args = [
        'post_type' => 'rbf_booking',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'meta_query' => [[
            'key' => 'rbf_data',
            'value' => [$start, $end],
            'compare' => 'BETWEEN',
            'type' => 'DATE'
        ]]
    ];

    $bookings = get_posts($args);
    $events = [];

    foreach ($bookings as $booking) {
        $meta = get_post_meta($booking->ID);
        $event = rbf_prepare_calendar_event_from_booking($booking, $meta);

        if ($event !== null) {
            $events[] = $event;
        }
    }

    wp_send_json_success($events);
}

/**
 * AJAX handler for updating booking status
 */
add_action('wp_ajax_rbf_update_booking_status', 'rbf_update_booking_status_callback');
function rbf_update_booking_status_callback() {
    check_ajax_referer('rbf_calendar_nonce', '_ajax_nonce');

    if (!rbf_user_can_manage_bookings()) {
        wp_send_json_error('Permessi insufficienti', 403);
    }

    $sanitized = rbf_sanitize_input_fields($_POST, [
        'booking_id' => 'int',
        'status' => 'text'
    ]);
    
    $booking_id = $sanitized['booking_id'];
    $status = $sanitized['status'];
    
    if (!$booking_id || !in_array($status, ['confirmed', 'cancelled', 'completed'])) {
        wp_send_json_error('Parametri non validi');
    }
    
    $booking = get_post($booking_id);
    if (!$booking || $booking->post_type !== 'rbf_booking') {
        wp_send_json_error('Prenotazione non trovata');
    }
    
    $updated = rbf_update_booking_status($booking_id, $status);

    if ($updated) {
        wp_send_json_success('Stato aggiornato con successo');
    }

    wp_send_json_error('Errore durante l\'aggiornamento');
}

/**
 * AJAX handler for updating complete booking data
 */
add_action('wp_ajax_rbf_update_booking_data', 'rbf_update_booking_data_callback');
function rbf_update_booking_data_callback() {
    check_ajax_referer('rbf_calendar_nonce', '_ajax_nonce');

    if (!rbf_user_can_manage_bookings()) {
        wp_send_json_error('Permessi insufficienti', 403);
    }

    $booking_id = intval($_POST['booking_id']);

    if (!isset($_POST['booking_data']) || !is_array($_POST['booking_data'])) {
        wp_send_json_error('Parametri non validi');
    }

    $booking_data = rbf_sanitize_input_fields($_POST['booking_data'], [
        'customer_name' => 'text',
        'customer_email' => 'email',
        'customer_phone' => 'text',
        'people' => 'int',
        'notes' => 'textarea',
        'status' => 'text'
    ]);

    if (!$booking_id || empty($booking_data)) {
        wp_send_json_error('Parametri non validi');
    }
    
    $booking = get_post($booking_id);
    if (!$booking || $booking->post_type !== 'rbf_booking') {
        wp_send_json_error('Prenotazione non trovata');
    }
    
    $status_update_result = true;

    // Update customer name (split into first and last name)
    if (isset($booking_data['customer_name'])) {
        $name_parts = explode(' ', $booking_data['customer_name'], 2);
        update_post_meta($booking_id, 'rbf_nome', $name_parts[0]);
        update_post_meta($booking_id, 'rbf_cognome', isset($name_parts[1]) ? $name_parts[1] : '');

        // Update post title
        wp_update_post([
            'ID' => $booking_id,
            'post_title' => $booking_data['customer_name']
        ]);
    }

    // Update email
    if (isset($booking_data['customer_email'])) {
        update_post_meta($booking_id, 'rbf_email', $booking_data['customer_email']);
    }

    // Update phone
    if (isset($booking_data['customer_phone'])) {
        update_post_meta($booking_id, 'rbf_tel', $booking_data['customer_phone']);
    }
    
    $should_recalculate_value = false;

    // Update people count
    if (isset($booking_data['people'])) {
        $people = $booking_data['people'];
        $people_max_limit = rbf_get_people_max_limit();

        if ($people < 1) {
            wp_send_json_error(rbf_translate_string('Il numero di persone deve essere almeno 1.'));
        }

        if ($people > $people_max_limit) {
            wp_send_json_error(sprintf(
                rbf_translate_string('Il numero di persone non può superare %d.'),
                $people_max_limit
            ));
        }

        $previous_people = intval(get_post_meta($booking_id, 'rbf_persone', true));
        $delta_people = $people - $previous_people;

        $booking_date = get_post_meta($booking_id, 'rbf_data', true);
        $booking_meal = get_post_meta($booking_id, 'rbf_meal', true) ?: get_post_meta($booking_id, 'rbf_orario', true);

        if ($delta_people > 0) {
            if ($booking_date && $booking_meal) {
                $capacity_result = rbf_book_slot_optimistic($booking_date, $booking_meal, $delta_people);

                if (empty($capacity_result['success'])) {
                    $error_message = rbf_translate_string('Errore durante l\'aggiornamento della capacità della prenotazione.');

                    if (!empty($capacity_result['error']) && $capacity_result['error'] === 'insufficient_capacity') {
                        $remaining = isset($capacity_result['remaining']) ? (int) $capacity_result['remaining'] : 0;
                        $error_message = sprintf(
                            rbf_translate_string('Spiacenti, non ci sono abbastanza posti. Rimasti: %d. Scegli un altro orario.'),
                            max(0, $remaining)
                        );
                    } elseif (!empty($capacity_result['message'])) {
                        $error_message = $capacity_result['message'];
                    }

                    wp_send_json_error($error_message);
                }
            } else {
                wp_send_json_error(rbf_translate_string('Errore durante l\'aggiornamento della capacità della prenotazione.'));
            }
        } elseif ($delta_people < 0 && $booking_date && $booking_meal) {
            rbf_release_slot_capacity($booking_date, $booking_meal, abs($delta_people));
        }

        update_post_meta($booking_id, 'rbf_persone', $people);
        $should_recalculate_value = true;

        if ($booking_date && $booking_meal) {
            rbf_sync_slot_version($booking_date, $booking_meal);
            delete_transient('rbf_avail_' . $booking_date . '_' . $booking_meal);
        }
    }
    
    // Update notes
    if (isset($booking_data['notes'])) {
        update_post_meta($booking_id, 'rbf_allergie', $booking_data['notes']);
    }
    
    // Update status
    if (isset($booking_data['status']) && in_array($booking_data['status'], ['confirmed', 'cancelled', 'completed'])) {
        $status_update_result = rbf_update_booking_status($booking_id, $booking_data['status']);

        if (!$status_update_result) {
            wp_send_json_error('Errore durante l\'aggiornamento dello stato');
        }
    }

    if ($should_recalculate_value) {
        $current_people = intval(get_post_meta($booking_id, 'rbf_persone', true));
        $meal = get_post_meta($booking_id, 'rbf_meal', true) ?: get_post_meta($booking_id, 'rbf_orario', true);

        $options = rbf_get_settings();
        $meal_config = rbf_get_meal_config($meal);
        if ($meal_config) {
            $valore_pp = (float) $meal_config['price'];
        } else {
            $meal_for_value = ($meal === 'brunch') ? 'pranzo' : $meal;
            $valore_pp = (float) ($options['valore_' . $meal_for_value] ?? 0);
        }

        $valore_tot = $valore_pp * $current_people;
        update_post_meta($booking_id, 'rbf_valore_pp', $valore_pp);
        update_post_meta($booking_id, 'rbf_valore_tot', $valore_tot);
    }

    if (!$status_update_result) {
        wp_send_json_error('Errore durante l\'aggiornamento della prenotazione');
    }

    wp_send_json_success('Prenotazione aggiornata con successo');
}

/**
 * Add booking page HTML
 */
function rbf_add_booking_page_html() {
    if (!rbf_require_booking_capability()) {
        return;
    }

    $options = rbf_get_settings();
    $active_meals = rbf_get_active_meals();
    $message = '';

    $form_defaults = [
        'rbf_meal'      => '',
        'rbf_data'      => '',
        'rbf_time'      => '',
        'rbf_persone'   => '',
        'rbf_nome'      => '',
        'rbf_cognome'   => '',
        'rbf_email'     => '',
        'rbf_tel'       => '',
        'rbf_allergie'  => '',
        'rbf_lang'      => 'it',
        'rbf_privacy'   => false,
        'rbf_marketing' => false,
    ];
    $form_values = $form_defaults;

    if (!empty($_POST) && check_admin_referer('rbf_add_backend_booking')) {
        $sanitized = rbf_sanitize_input_fields($_POST, [
            'rbf_meal' => 'text',
            'rbf_data' => 'text',
            'rbf_time' => 'text',
            'rbf_persone' => 'int',
            'rbf_nome' => 'text',
            'rbf_cognome' => 'text',
            'rbf_email' => 'email',
            'rbf_tel' => 'text',
            'rbf_allergie' => 'textarea',
            'rbf_lang' => 'text'
        ]);

        $meal = $sanitized['rbf_meal'] ?? '';
        $date = $sanitized['rbf_data'] ?? '';
        $time = $sanitized['rbf_time'] ?? '';
        $people = $sanitized['rbf_persone'] ?? 0;
        $first_name = $sanitized['rbf_nome'] ?? '';
        $last_name = $sanitized['rbf_cognome'] ?? '';
        $email = $sanitized['rbf_email'] ?? '';
        $tel = $sanitized['rbf_tel'] ?? '';
        $notes = $sanitized['rbf_allergie'] ?? '';
        $lang = $sanitized['rbf_lang'] ?? 'it';
        $privacy = isset($_POST['rbf_privacy']) ? 'yes' : 'no';
        $marketing = isset($_POST['rbf_marketing']) ? 'yes' : 'no';

        $form_values = array_merge($form_values, [
            'rbf_meal'      => $meal,
            'rbf_data'      => $date,
            'rbf_time'      => $time,
            'rbf_persone'   => $people > 0 ? (string) $people : '',
            'rbf_nome'      => $first_name,
            'rbf_cognome'   => $last_name,
            'rbf_email'     => $email,
            'rbf_tel'       => $tel,
            'rbf_allergie'  => $notes,
            'rbf_lang'      => $lang !== '' ? $lang : 'it',
            'rbf_privacy'   => $privacy === 'yes',
            'rbf_marketing' => $marketing === 'yes',
        ]);

        if (empty($active_meals)) {
            $message = '<div class="notice notice-error"><p>' . esc_html(rbf_translate_string('Configura almeno un servizio attivo prima di aggiungere prenotazioni manuali.')) . '</p></div>';
        } else {
            $valid_meal_ids = array_column($active_meals, 'id');

            if (!in_array($meal, $valid_meal_ids, true)) {
                $message = '<div class="notice notice-error"><p>' . esc_html(rbf_translate_string('Servizio non configurato.')) . '</p></div>';
            } else {
                $title = (!empty($first_name) && !empty($last_name)) ? ucfirst($meal) . " per {$first_name} {$last_name} - {$date} {$time}" : "Prenotazione Manuale - {$date} {$time}";

                $meal_config = rbf_get_meal_config($meal);
                if ($meal_config) {
                    $valore_pp = (float) $meal_config['price'];
                } else {
                    $meal_for_value = ($meal === 'brunch') ? 'pranzo' : $meal;
                    $valore_pp = (float) ($options['valore_' . $meal_for_value] ?? 0);
                }
                $valore_tot = $valore_pp * $people;

                $time_validation = rbf_validate_booking_time($date, $time);
                if ($time_validation !== true) {
                    $error_msg = is_array($time_validation)
                        ? ($time_validation['message'] ?? rbf_translate_string('Orario non valido.'))
                        : rbf_translate_string('Orario non valido.');
                    $message = '<div class="notice notice-error"><p>' . esc_html($error_msg) . '</p></div>';
                } else {
                    $buffer_validation = rbf_validate_buffer_time($date, $time, $meal, $people);
                    if ($buffer_validation !== true) {
                        $buffer_msg = is_array($buffer_validation)
                            ? ($buffer_validation['message'] ?? rbf_translate_string('Questo orario non rispetta il buffer richiesto. Scegli un altro orario.'))
                            : rbf_translate_string('Questo orario non rispetta il buffer richiesto. Scegli un altro orario.');
                        $message = '<div class="notice notice-error"><p>' . esc_html($buffer_msg) . '</p></div>';
                    } else {
                        $booking_result = rbf_book_slot_optimistic($date, $meal, $people);

                        if (!$booking_result['success']) {
                            $error_code = $booking_result['error'] ?? '';
                            if ($error_code === 'insufficient_capacity') {
                                $remaining = intval($booking_result['remaining'] ?? 0);
                                $error_msg = sprintf(
                                    rbf_translate_string('Non ci sono abbastanza posti disponibili. Rimasti: %d.'),
                                    $remaining
                                );
                            } elseif ($error_code === 'version_conflict') {
                                $error_msg = rbf_translate_string('Conflitto di prenotazione rilevato. Aggiorna la pagina e riprova.');
                            } elseif ($error_code === 'invalid_parameters') {
                                $error_msg = rbf_translate_string('Parametri di prenotazione non validi.');
                            } else {
                                $error_msg = rbf_translate_string('Errore durante la prenotazione dello slot. Riprova.');
                            }

                            $message = '<div class="notice notice-error"><p>' . esc_html($error_msg) . '</p></div>';
                        } else {
                            $tracking_token = wp_generate_password(20, false, false);
                            $tracking_token_hash = rbf_hash_tracking_token($tracking_token);

                            $post_id = wp_insert_post([
                                'post_type' => 'rbf_booking',
                                'post_title' => $title,
                                'post_status' => 'publish',
                                'meta_input' => [
                                    'rbf_data' => $date,
                                    'rbf_meal' => $meal,
                                    'rbf_orario' => $time,
                                    'rbf_time' => $time,
                                    'rbf_persone' => $people,
                                    'rbf_nome' => $first_name,
                                    'rbf_cognome' => $last_name,
                                    'rbf_email' => $email,
                                    'rbf_tel' => $tel,
                                    'rbf_allergie' => $notes,
                                    'rbf_lang' => $lang,
                                    'rbf_source_bucket' => 'backend',
                                    'rbf_source' => 'backend',
                                    'rbf_medium' => 'backend',
                                    'rbf_campaign' => '',
                                    'rbf_privacy' => $privacy,
                                    'rbf_marketing' => $marketing,
                                    // Enhanced booking system
                                    'rbf_booking_status' => 'confirmed', // Backend bookings start confirmed
                                    'rbf_booking_created' => current_time('Y-m-d H:i:s'),
                                    'rbf_booking_hash' => wp_generate_password(16, false, false),
                                    'rbf_valore_pp' => $valore_pp,
                                    'rbf_valore_tot' => $valore_tot,
                                    'rbf_tracking_token' => $tracking_token_hash,
                                ],
                            ]);

                            if (!is_wp_error($post_id)) {
                                if (isset($booking_result['version'])) {
                                    update_post_meta($post_id, 'rbf_slot_version', $booking_result['version']);
                                }
                                if (isset($booking_result['attempt'])) {
                                    update_post_meta($post_id, 'rbf_booking_attempt', $booking_result['attempt']);
                                }

                                $table_assignment = rbf_assign_tables_first_fit($people, $date, $time, $meal);

                                if (!$table_assignment) {
                                    $release_success = rbf_release_slot_capacity($date, $meal, $people);

                                    if (!$release_success) {
                                        if (function_exists('rbf_log')) {
                                            rbf_log('RBF Add Booking: rilascio capacità fallito dopo errore di assegnazione tavoli per prenotazione ' . $post_id . ' su ' . $date . ' - ' . $meal . '. Avvio sincronizzazione ledger.');
                                        }

                                        $synced = function_exists('rbf_sync_slot_version') ? rbf_sync_slot_version($date, $meal) : false;

                                        if ($synced) {
                                            $release_success = rbf_release_slot_capacity($date, $meal, $people);
                                        } else {
                                            if (function_exists('rbf_log')) {
                                                rbf_log('RBF Add Booking: sincronizzazione ledger fallita dopo errore di assegnazione tavoli per ' . $date . ' - ' . $meal . '.');
                                            }
                                        }
                                    }

                                    wp_delete_post($post_id, true);

                                    if (!$release_success && function_exists('rbf_log')) {
                                        rbf_log('RBF Add Booking: impossibile rilasciare la capacità dopo errore di assegnazione tavoli per prenotazione ' . $post_id . ' su ' . $date . ' - ' . $meal . ' nonostante i tentativi di ripristino.');
                                    }

                                    $base_message = rbf_translate_string('Impossibile assegnare tavoli disponibili per questa prenotazione. Riprova con un altro orario o verifica la configurazione dei tavoli.');
                                    if (!$release_success) {
                                        $base_message .= ' ' . rbf_translate_string('La disponibilità dello slot potrebbe non essere aggiornata; verifica manualmente la capacità.');
                                    }

                                    $message = '<div class="notice notice-error"><p>' . esc_html($base_message) . '</p></div>';
                                } else {
                                    rbf_save_table_assignment($post_id, $table_assignment);

                                    update_post_meta($post_id, 'rbf_table_assignment_type', $table_assignment['type']);
                                    update_post_meta($post_id, 'rbf_assigned_tables', $table_assignment['total_capacity']);

                                    if ($table_assignment['type'] === 'joined' && isset($table_assignment['group_id'])) {
                                        update_post_meta($post_id, 'rbf_table_group_id', $table_assignment['group_id']);
                                    }

                                    $event_id       = 'rbf_' . $post_id;

                                    rbf_store_booking_tracking_token($post_id, $tracking_token);

                                    // Email + Brevo (functions will be loaded from integrations module)
                                    if (function_exists('rbf_send_admin_notification_email')) {
                                        rbf_send_admin_notification_email($first_name, $last_name, $email, $date, $time, $people, $notes, $tel, $meal);
                                    }
                                    if (function_exists('rbf_trigger_brevo_automation')) {
                                        rbf_trigger_brevo_automation($first_name, $last_name, $email, $date, $time, $people, $notes, $lang, $tel, $marketing, $meal);
                                    }

                                    // Transient per tracking (anche per inserimenti manuali)
                                    set_transient('rbf_booking_data_' . $post_id, [
                                        'id'             => $post_id,
                                        'value'          => $valore_tot,
                                        'currency'       => 'EUR',
                                        'meal'           => $meal,
                                        'people'         => $people,
                                        'bucket'         => 'backend',
                                        'event_id'       => $event_id,
                                        'unit_price'     => $valore_pp,
                                        'tracking_token' => $tracking_token,
                                    ], 60 * 15);

                                    if (function_exists('rbf_clear_calendar_cache')) {
                                        rbf_clear_calendar_cache($date, $meal);
                                    }

                                    delete_transient('rbf_avail_' . $date . '_' . $meal);

                                    $success_link = '';
                                    $booking_page_url = rbf_get_booking_confirmation_base_url();
                                    $success_url = rbf_get_manual_booking_success_url($post_id, $tracking_token, $booking_page_url);
                                    if ($booking_page_url !== '' && $success_url !== '') {
                                        $success_link = ' | <a href="' . esc_url($success_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html(rbf_translate_string('Apri pagina di conferma')) . '</a>';
                                    }

                                    $message = '<div class="notice notice-success"><p>Prenotazione aggiunta con successo! <a href="' . admin_url('post.php?post=' . $post_id . '&action=edit') . '">Modifica</a>' . $success_link . '</p></div>';
                                    $form_values = $form_defaults;
                                }
                            } else {
                                $release_success = rbf_release_slot_capacity($date, $meal, $people);

                                if (!$release_success) {
                                    if (function_exists('rbf_log')) {
                                        rbf_log('RBF Add Booking: rilascio capacità fallito per ' . $date . ' - ' . $meal . ' (' . intval($people) . 'p). Avvio sincronizzazione ledger.');
                                    }

                                    $synced = function_exists('rbf_sync_slot_version') ? rbf_sync_slot_version($date, $meal) : false;

                                    if ($synced) {
                                        $release_success = rbf_release_slot_capacity($date, $meal, $people);
                                    } else {
                                        if (function_exists('rbf_log')) {
                                            rbf_log('RBF Add Booking: sincronizzazione ledger fallita per ' . $date . ' - ' . $meal . '.');
                                        }
                                    }
                                }

                                if (!$release_success) {
                                    if (function_exists('rbf_log')) {
                                        rbf_log('RBF Add Booking: impossibile rilasciare la capacità per ' . $date . ' - ' . $meal . ' (' . intval($people) . 'p) dopo i tentativi di ripristino.');
                                    }

                                    $message_text = "Errore durante l'aggiunta della prenotazione. Impossibile riallineare la disponibilità dello slot; verifica manualmente la capacità.";
                                } else {
                                    $message_text = "Errore durante l'aggiunta della prenotazione.";
                                }

                                $message = '<div class="notice notice-error"><p>' . esc_html($message_text) . '</p></div>';
                            }
                        }
                    }
                }
            }
        }
    }

    ?>
    <div class="rbf-admin-wrap rbf-admin-wrap--narrow">
        <h1><?php echo esc_html(rbf_translate_string('Aggiungi Nuova Prenotazione')); ?></h1>
        <?php echo wp_kses_post($message); ?>

        <?php if (empty($active_meals)) : ?>
            <div class="notice notice-warning"><p><?php echo esc_html(rbf_translate_string('Configura almeno un servizio attivo prima di aggiungere prenotazioni manuali.')); ?></p></div>
        <?php else : ?>
            <div class="rbf-admin-card">
                <form method="post" class="rbf-admin-form rbf-admin-form--stacked">
                    <?php wp_nonce_field('rbf_add_backend_booking'); ?>
                    <div class="rbf-admin-grid rbf-admin-grid--cols-2 rbf-add-booking-grid">
                        <fieldset class="rbf-admin-subcard">
                            <legend><?php echo esc_html(rbf_translate_string('Dettagli prenotazione')); ?></legend>
                            <div class="rbf-form-group">
                                <label for="rbf_meal"><?php echo esc_html(rbf_translate_string('Pasto')); ?></label>
                                <select id="rbf_meal" name="rbf_meal" class="rbf-field">
                                    <option value=""><?php echo esc_html(rbf_translate_string('Scegli il pasto')); ?></option>
                                    <?php foreach ($active_meals as $meal_config) : ?>
                                        <option value="<?php echo esc_attr($meal_config['id']); ?>" <?php selected($form_values['rbf_meal'], $meal_config['id']); ?>>
                                            <?php echo esc_html($meal_config['name'] ?? $meal_config['id']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="rbf-form-group">
                                <label for="rbf_data"><?php echo esc_html(rbf_translate_string('Data')); ?></label>
                                <input type="date" id="rbf_data" name="rbf_data" class="rbf-field" value="<?php echo esc_attr($form_values['rbf_data']); ?>">
                            </div>
                            <div class="rbf-form-group">
                                <label for="rbf_time"><?php echo esc_html(rbf_translate_string('Orario')); ?></label>
                                <input type="time" id="rbf_time" name="rbf_time" class="rbf-field" value="<?php echo esc_attr($form_values['rbf_time']); ?>">
                            </div>
                            <div class="rbf-form-group">
                                <label for="rbf_persone"><?php echo esc_html(rbf_translate_string('Persone')); ?></label>
                                <input type="number" id="rbf_persone" name="rbf_persone" class="rbf-field" min="1" value="<?php echo esc_attr($form_values['rbf_persone']); ?>">
                            </div>
                        </fieldset>
                        <fieldset class="rbf-admin-subcard">
                            <legend><?php echo esc_html(rbf_translate_string('Dati cliente')); ?></legend>
                            <div class="rbf-form-group">
                                <label for="rbf_nome"><?php echo esc_html(rbf_translate_string('Nome')); ?></label>
                                <input type="text" id="rbf_nome" name="rbf_nome" class="rbf-field" value="<?php echo esc_attr($form_values['rbf_nome']); ?>">
                            </div>
                            <div class="rbf-form-group">
                                <label for="rbf_cognome"><?php echo esc_html(rbf_translate_string('Cognome')); ?></label>
                                <input type="text" id="rbf_cognome" name="rbf_cognome" class="rbf-field" value="<?php echo esc_attr($form_values['rbf_cognome']); ?>">
                            </div>
                            <div class="rbf-form-group">
                                <label for="rbf_email"><?php echo esc_html(rbf_translate_string('Email')); ?></label>
                                <input type="email" id="rbf_email" name="rbf_email" class="rbf-field" value="<?php echo esc_attr($form_values['rbf_email']); ?>">
                            </div>
                            <div class="rbf-form-group">
                                <label for="rbf_tel"><?php echo esc_html(rbf_translate_string('Telefono')); ?></label>
                                <input type="tel" id="rbf_tel" name="rbf_tel" class="rbf-field" value="<?php echo esc_attr($form_values['rbf_tel']); ?>">
                            </div>
                            <div class="rbf-form-group">
                                <label for="rbf_allergie"><?php echo esc_html(rbf_translate_string('Allergie / Note')); ?></label>
                                <textarea id="rbf_allergie" name="rbf_allergie" class="rbf-field rbf-field--textarea" rows="4"><?php echo esc_textarea($form_values['rbf_allergie']); ?></textarea>
                            </div>
                        </fieldset>
                    </div>
                    <div class="rbf-admin-grid rbf-admin-grid--cols-2 rbf-add-booking-grid rbf-add-booking-grid--meta">
                        <div class="rbf-form-group">
                            <label for="rbf_lang"><?php echo esc_html(rbf_translate_string('Lingua')); ?></label>
                            <select id="rbf_lang" name="rbf_lang" class="rbf-field">
                                <option value="it" <?php selected($form_values['rbf_lang'], 'it'); ?>>IT</option>
                                <option value="en" <?php selected($form_values['rbf_lang'], 'en'); ?>>EN</option>
                            </select>
                        </div>
                        <div class="rbf-form-group rbf-form-group--toggle">
                            <span class="rbf-form-label"><?php echo esc_html(rbf_translate_string('Privacy')); ?></span>
                            <label class="rbf-toggle">
                                <input type="checkbox" name="rbf_privacy" value="yes" <?php checked($form_values['rbf_privacy']); ?>>
                                <span><?php echo esc_html(rbf_translate_string('Accettata')); ?></span>
                            </label>
                        </div>
                        <div class="rbf-form-group rbf-form-group--toggle">
                            <span class="rbf-form-label"><?php echo esc_html(rbf_translate_string('Marketing')); ?></span>
                            <label class="rbf-toggle">
                                <input type="checkbox" name="rbf_marketing" value="yes" <?php checked($form_values['rbf_marketing']); ?>>
                                <span><?php echo esc_html(rbf_translate_string('Accettato')); ?></span>
                            </label>
                        </div>
                    </div>
                    <div class="rbf-form-actions">
                        <?php submit_button(rbf_translate_string('Aggiungi Prenotazione'), 'primary', 'submit', false); ?>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
    <?php
}



/**
 * Make status column sortable
 */
add_filter('manage_edit-rbf_booking_sortable_columns', 'rbf_set_sortable_columns');
function rbf_set_sortable_columns($columns) {
    $columns['rbf_status'] = 'rbf_booking_status';
    $columns['rbf_booking_date'] = 'rbf_data';
    $columns['rbf_people'] = 'rbf_persone';
    return $columns;
}

/**
 * Handle sorting for custom columns
 */
add_action('pre_get_posts', 'rbf_handle_custom_sorting');
function rbf_handle_custom_sorting($query) {
    if (!is_admin() || !$query->is_main_query()) return;
    if ($query->get('post_type') !== 'rbf_booking') return;
    
    $orderby = $query->get('orderby');
    
    switch ($orderby) {
        case 'rbf_booking_status':
            $query->set('meta_key', 'rbf_booking_status');
            $query->set('orderby', 'meta_value');
            break;
        case 'rbf_data':
            $query->set('meta_key', 'rbf_data');
            $query->set('orderby', 'meta_value');
            break;
        case 'rbf_persone':
            $query->set('meta_key', 'rbf_persone');
            $query->set('orderby', 'meta_value_num');
            break;
    }
}

/**
 * Add status filter dropdown
 */
add_action('restrict_manage_posts', 'rbf_add_status_filter');
function rbf_add_status_filter() {
    global $typenow;
    
    if ($typenow === 'rbf_booking') {
        $raw_status = isset($_GET['rbf_status']) ? wp_unslash($_GET['rbf_status']) : '';
        $selected_status = sanitize_key($raw_status);
        $statuses = rbf_get_booking_statuses();

        echo '<select name="rbf_status">';
        echo '<option value="">' . rbf_translate_string('Tutti gli stati') . '</option>';
        foreach ($statuses as $key => $label) {
            echo '<option value="' . esc_attr($key) . '"' . selected($selected_status, $key, false) . '>';
            echo esc_html($label);
            echo '</option>';
        }
        echo '</select>';
    }
}

/**
 * Reports and Analytics page HTML
 */
function rbf_reports_page_html() {
    if (!rbf_require_booking_capability()) {
        return;
    }

    // Enqueue Chart.js for analytics
    wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js', [], '3.9.1', true);
    
    // Get date range for reports (default: last 30 days)
    $default_end_date = date('Y-m-d');
    $default_start_date = date('Y-m-d', strtotime('-30 days'));

    $end_date = isset($_GET['end_date']) ? sanitize_text_field(wp_unslash($_GET['end_date'])) : $default_end_date;
    $start_date = isset($_GET['start_date']) ? sanitize_text_field(wp_unslash($_GET['start_date'])) : $default_start_date;

    $start_dt = DateTime::createFromFormat('Y-m-d', $start_date) ?: false;
    $end_dt = DateTime::createFromFormat('Y-m-d', $end_date) ?: false;

    if (!$start_dt) {
        $start_date = $default_start_date;
        $start_dt = DateTime::createFromFormat('Y-m-d', $start_date);
    }

    if (!$end_dt) {
        $end_date = $default_end_date;
        $end_dt = DateTime::createFromFormat('Y-m-d', $end_date);
    }

    if ($start_dt && $end_dt && $start_dt > $end_dt) {
        $temp_dt = $start_dt;
        $start_dt = $end_dt;
        $end_dt = $temp_dt;

        $start_date = $start_dt->format('Y-m-d');
        $end_date = $end_dt->format('Y-m-d');
    }

    $raw_source_filters = isset($_GET['source_filter']) ? (array) $_GET['source_filter'] : [];
    $source_filters = array_values(array_filter(array_map('sanitize_key', $raw_source_filters)));

    $analytics = rbf_get_booking_analytics($start_date, $end_date, $source_filters);
    $channel_options = $analytics['channels_catalog'] ?? [];
    $channel_totals_all = $analytics['channel_counts_all'] ?? [];
    $selected_channels = $analytics['selected_channels'] ?? [];
    $source_breakdown = $analytics['source_breakdown'] ?? [];
    $top_channels = $analytics['top_channels'] ?? [];
    $top_mediums = $analytics['top_mediums'] ?? [];
    $top_campaigns = $analytics['top_campaigns'] ?? [];
    $source_breakdown_values = array_values($source_breakdown);

    $selected_channel_labels = [];
    foreach ($selected_channels as $channel_key) {
        if (isset($channel_options[$channel_key])) {
            $selected_channel_labels[] = $channel_options[$channel_key];
        }
    }
    ?>
    <div class="rbf-admin-wrap rbf-admin-wrap--wide">
        <h1><?php echo esc_html(rbf_translate_string('Report & Analytics')); ?></h1>
        
        <div class="rbf-admin-card rbf-analytics-filter">
            <form method="get" class="rbf-admin-form">
                <input type="hidden" name="page" value="rbf_reports">
                <div class="rbf-form-row">
                    <label for="start_date"><?php echo esc_html(rbf_translate_string('Da:')); ?></label>
                    <input type="date" id="start_date" name="start_date" value="<?php echo esc_attr($start_date); ?>">
                    <label for="end_date"><?php echo esc_html(rbf_translate_string('A:')); ?></label>
                    <input type="date" id="end_date" name="end_date" value="<?php echo esc_attr($end_date); ?>">
                </div>
                <?php if (!empty($channel_options)) : ?>
                    <fieldset class="rbf-admin-fieldset">
                        <legend><?php echo esc_html(rbf_translate_string('Filtra per canale')); ?></legend>
                        <div class="rbf-chip-group">
                            <?php foreach ($channel_options as $channel_key => $channel_label) : ?>
                                <?php
                                $channel_count = $channel_totals_all[$channel_key] ?? 0;
                                $is_selected = empty($selected_channels) || in_array($channel_key, $selected_channels, true);
                                ?>
                                <label class="rbf-chip">
                                    <input type="checkbox" name="source_filter[]" value="<?php echo esc_attr($channel_key); ?>" <?php checked($is_selected); ?>>
                                    <span class="rbf-chip__label"><?php echo esc_html($channel_label); ?></span>
                                    <span class="rbf-chip__badge"><?php echo esc_html(number_format_i18n($channel_count)); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <p class="description">
                            <?php echo esc_html(rbf_translate_string('Deseleziona i canali da escludere dal report. Se non selezioni nulla verranno mostrati tutti i dati disponibili.')); ?>
                        </p>
                    </fieldset>
                <?php endif; ?>
                <div class="rbf-form-actions">
                    <button type="submit" class="button button-primary"><?php echo esc_html(rbf_translate_string('Aggiorna Report')); ?></button>
                    <?php if (!empty($selected_channels)) : ?>
                        <?php
                        $reset_url = add_query_arg(
                            [
                                'page' => 'rbf_reports',
                                'start_date' => $start_date,
                                'end_date' => $end_date,
                            ],
                            admin_url('admin.php')
                        );
                        ?>
                        <a href="<?php echo esc_url($reset_url); ?>" class="button">
                            <?php echo esc_html(rbf_translate_string('Reset Filtri')); ?>
                        </a>
                    <?php endif; ?>
                </div>
                <?php if (!empty($selected_channel_labels)) : ?>
                    <p class="rbf-analytics-active-filters">
                        <strong><?php echo esc_html(rbf_translate_string('Filtri attivi:')); ?></strong>
                        <?php echo esc_html(implode(', ', $selected_channel_labels)); ?>
                    </p>
                <?php endif; ?>
            </form>
        </div>

        <div class="rbf-admin-card">
            <div class="rbf-analytics-metrics">
                <div class="rbf-analytics-metric rbf-analytics-metric--bookings">
                    <p class="rbf-analytics-metric__label"><?php echo esc_html(rbf_translate_string('Prenotazioni Totali')); ?></p>
                    <p class="rbf-analytics-metric__value"><?php echo esc_html($analytics['total_bookings']); ?></p>
                </div>
                <div class="rbf-analytics-metric rbf-analytics-metric--people">
                    <p class="rbf-analytics-metric__label"><?php echo esc_html(rbf_translate_string('Persone Totali')); ?></p>
                    <p class="rbf-analytics-metric__value"><?php echo esc_html($analytics['total_people']); ?></p>
                    <p class="rbf-analytics-metric__subtitle"><?php echo esc_html(sprintf(rbf_translate_string('Media: %.1f per prenotazione'), $analytics['avg_people_per_booking'])); ?></p>
                </div>
                <div class="rbf-analytics-metric rbf-analytics-metric--revenue">
                    <p class="rbf-analytics-metric__label"><?php echo esc_html(rbf_translate_string('Ricavi Stimati')); ?></p>
                    <p class="rbf-analytics-metric__value">€<?php echo esc_html(number_format_i18n((float) $analytics['total_revenue'], 2)); ?></p>
                    <p class="rbf-analytics-metric__subtitle"><?php echo esc_html(sprintf(rbf_translate_string('Media: €%.2f per prenotazione'), $analytics['avg_revenue_per_booking'])); ?></p>
                </div>
                <div class="rbf-analytics-metric rbf-analytics-metric--completion">
                    <p class="rbf-analytics-metric__label"><?php echo esc_html(rbf_translate_string('Tasso di Completamento')); ?></p>
                    <p class="rbf-analytics-metric__value"><?php echo esc_html(number_format_i18n((float) $analytics['completion_rate'], 1)); ?>%</p>
                    <p class="rbf-analytics-metric__subtitle"><?php echo esc_html(sprintf(rbf_translate_string('%d completate su %d confermate'), $analytics['completed_bookings'], $analytics['confirmed_bookings'])); ?></p>
                </div>
            </div>
        </div>

        <?php if (!empty($top_channels) || !empty($top_mediums) || !empty($top_campaigns)) : ?>
            <div class="rbf-admin-grid rbf-admin-grid--cols-2 rbf-analytics-insights">
                <?php if (!empty($top_channels)) : ?>
                    <div class="rbf-admin-card rbf-insights-card">
                        <h3><?php echo esc_html(rbf_translate_string('Canali con Miglior Conversione')); ?></h3>
                        <ul class="rbf-insight-list">
                            <?php foreach (array_slice($top_channels, 0, 4) as $channel_insight) : ?>
                                <li class="rbf-insight-list__item">
                                    <div class="rbf-insight-list__content">
                                        <p class="rbf-insight-list__title"><?php echo esc_html($channel_insight['label']); ?></p>
                                        <p class="rbf-insight-list__subtitle">
                                            <?php
                                            $conversion_text = sprintf(
                                                rbf_translate_string('%1$d prenotazioni completate su %2$d totali'),
                                                (int) ($channel_insight['completed'] ?? 0),
                                                (int) ($channel_insight['bookings'] ?? 0)
                                            );
                                            echo esc_html($conversion_text);
                                            ?>
                                        </p>
                                    </div>
                                    <div class="rbf-insight-list__metrics">
                                        <span class="rbf-insight-badge rbf-insight-badge--success"><?php echo esc_html(number_format_i18n((float) ($channel_insight['conversion_rate'] ?? 0), 1)); ?>%</span>
                                        <span class="rbf-insight-badge">€<?php echo esc_html(number_format_i18n((float) ($channel_insight['avg_revenue_per_booking'] ?? 0), 2)); ?></span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($top_mediums) || !empty($top_campaigns)) : ?>
                    <div class="rbf-admin-card rbf-insights-card">
                        <h3><?php echo esc_html(rbf_translate_string('Approfondimenti di Attribuzione')); ?></h3>
                        <div class="rbf-insight-subgrid">
                            <?php if (!empty($top_mediums)) : ?>
                                <div class="rbf-insight-subgrid__column">
                                    <h4><?php echo esc_html(rbf_translate_string('Medium più efficaci')); ?></h4>
                                    <ul class="rbf-mini-list">
                                        <?php foreach (array_slice($top_mediums, 0, 3) as $medium_insight) : ?>
                                            <li class="rbf-mini-list__item">
                                                <span class="rbf-mini-list__label"><?php echo esc_html($medium_insight['label']); ?></span>
                                                <span class="rbf-mini-list__value">
                                                    <?php
                                                    $medium_text = sprintf(
                                                        rbf_translate_string('%1$d pren. • %2$s%% conv.'),
                                                        (int) ($medium_insight['bookings'] ?? 0),
                                                        number_format_i18n((float) ($medium_insight['conversion_rate'] ?? 0), 1)
                                                    );
                                                    echo esc_html($medium_text);
                                                    ?>
                                                </span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($top_campaigns)) : ?>
                                <div class="rbf-insight-subgrid__column">
                                    <h4><?php echo esc_html(rbf_translate_string('Campagne con maggior valore')); ?></h4>
                                    <ul class="rbf-mini-list">
                                        <?php foreach (array_slice($top_campaigns, 0, 3) as $campaign_insight) : ?>
                                            <li class="rbf-mini-list__item">
                                                <span class="rbf-mini-list__label"><?php echo esc_html($campaign_insight['label']); ?></span>
                                                <span class="rbf-mini-list__value">
                                                    <?php
                                                    $campaign_text = sprintf(
                                                        rbf_translate_string('%1$d pren. • €%2$s a pren.'),
                                                        (int) ($campaign_insight['bookings'] ?? 0),
                                                        number_format_i18n((float) ($campaign_insight['avg_revenue_per_booking'] ?? 0), 2)
                                                    );
                                                    echo esc_html($campaign_text);
                                                    ?>
                                                </span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($analytics['total_bookings'] === 0) : ?>
            <div class="rbf-admin-card">
                <p class="rbf-empty-state"><?php echo esc_html(rbf_translate_string("Nessuna prenotazione trovata per l'intervallo selezionato.")); ?></p>
            </div>
        <?php endif; ?>

        <div class="rbf-admin-grid rbf-admin-grid--cols-2 rbf-analytics-charts">
            <div class="rbf-admin-card rbf-analytics-chart-card">
                <h3><?php echo esc_html(rbf_translate_string('Prenotazioni per Stato')); ?></h3>
                <canvas id="statusChart" height="220"></canvas>
            </div>
            <div class="rbf-admin-card rbf-analytics-chart-card">
                <h3><?php echo esc_html(rbf_translate_string('Prenotazioni per Servizio')); ?></h3>
                <canvas id="mealChart" height="220"></canvas>
            </div>
            <div class="rbf-admin-card rbf-analytics-chart-card">
                <h3><?php echo esc_html(rbf_translate_string('Andamento Prenotazioni Giornaliere')); ?></h3>
                <canvas id="dailyChart" height="220"></canvas>
            </div>
            <?php if (!empty($source_breakdown)) : ?>
                <div class="rbf-admin-card rbf-analytics-chart-card">
                    <h3><?php echo esc_html(rbf_translate_string('Confronto per Sorgente')); ?></h3>
                    <canvas id="sourceChart" height="220"></canvas>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($source_breakdown)) : ?>
            <div class="rbf-admin-card rbf-analytics-table">
                <h3><?php echo esc_html(rbf_translate_string('Dettaglio per Canale')); ?></h3>
                <div class="rbf-table-wrapper">
                    <table class="widefat striped rbf-data-table">
                        <thead>
                            <tr>
                                <th><?php echo esc_html(rbf_translate_string('Canale')); ?></th>
                                <th><?php echo esc_html(rbf_translate_string('Prenotazioni')); ?></th>
                                <th><?php echo esc_html(rbf_translate_string('Conversione')); ?></th>
                                <th><?php echo esc_html(rbf_translate_string('Persone')); ?></th>
                                <th><?php echo esc_html(rbf_translate_string('Ricavi')); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($source_breakdown as $channel_data) : ?>
                                <tr>
                                    <td><strong><?php echo esc_html($channel_data['label']); ?></strong></td>
                                    <td>
                                        <strong><?php echo esc_html(number_format_i18n($channel_data['bookings'])); ?></strong>
                                        <span class="rbf-table-subtle">
                                            <?php
                                            $share_text = sprintf(
                                                rbf_translate_string('%s%% del totale'),
                                                number_format_i18n((float) ($channel_data['share'] ?? 0), 1)
                                            );
                                            echo esc_html($share_text);
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html(number_format_i18n((int) ($channel_data['completed'] ?? 0))); ?></strong>
                                        <span class="rbf-table-subtle">
                                            <?php
                                            $conversion_rate_text = sprintf(
                                                rbf_translate_string('%s%% tasso'),
                                                number_format_i18n((float) ($channel_data['conversion_rate'] ?? 0), 1)
                                            );
                                            echo esc_html($conversion_rate_text);
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html(number_format_i18n($channel_data['people'])); ?></strong>
                                        <span class="rbf-table-subtle">
                                            <?php
                                            $avg_people_text = sprintf(
                                                rbf_translate_string('Media %s'),
                                                number_format_i18n((float) ($channel_data['avg_people_per_booking'] ?? 0), 1)
                                            );
                                            echo esc_html($avg_people_text);
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong>€<?php echo esc_html(number_format_i18n((float) $channel_data['revenue'], 2)); ?></strong>
                                        <span class="rbf-table-subtle">
                                            <?php
                                            $avg_value_text = sprintf(
                                                rbf_translate_string('€%s per pren.'),
                                                number_format_i18n((float) ($channel_data['avg_revenue_per_booking'] ?? 0), 2)
                                            );
                                            echo esc_html($avg_value_text);
                                            ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const statusData = <?php echo wp_json_encode($analytics['by_status']); ?>;
        const mealData = <?php echo wp_json_encode($analytics['by_meal']); ?>;
        const dailyData = <?php echo wp_json_encode($analytics['daily_bookings']); ?>;
        const sourceBreakdown = <?php echo wp_json_encode($source_breakdown_values); ?>;

        const statusCanvas = document.getElementById('statusChart');
        if (statusCanvas) {
            new Chart(statusCanvas, {
                type: 'doughnut',
                data: {
                    labels: Object.keys(statusData),
                    datasets: [{
                        data: Object.values(statusData),
                        backgroundColor: ['#f59e0b', '#10b981', '#06b6d4', '#ef4444', '#8b5cf6'],
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                },
            });
        }

        const mealCanvas = document.getElementById('mealChart');
        if (mealCanvas) {
            new Chart(mealCanvas, {
                type: 'bar',
                data: {
                    labels: Object.keys(mealData),
                    datasets: [{
                        data: Object.values(mealData),
                        backgroundColor: ['#3b82f6', '#f59e0b', '#10b981'],
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    },
                },
            });
        }

        const dailyCanvas = document.getElementById('dailyChart');
        if (dailyCanvas) {
            new Chart(dailyCanvas, {
                type: 'line',
                data: {
                    labels: Object.keys(dailyData),
                    datasets: [{
                        label: '<?php echo esc_js(rbf_translate_string('Prenotazioni')); ?>',
                        data: Object.values(dailyData),
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        fill: true,
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: { beginAtZero: true }
                    },
                },
            });
        }

        const sourceCanvas = document.getElementById('sourceChart');
        if (sourceCanvas && sourceBreakdown.length) {
            const sourceLabels = sourceBreakdown.map(item => item.label);
            const bookingsDataset = sourceBreakdown.map(item => Number(item.bookings));
            const peopleDataset = sourceBreakdown.map(item => Number(item.people));
            const revenueDataset = sourceBreakdown.map(item => Number(item.revenue));

            new Chart(sourceCanvas, {
                type: 'bar',
                data: {
                    labels: sourceLabels,
                    datasets: [
                        {
                            label: '<?php echo esc_js(rbf_translate_string('Prenotazioni')); ?>',
                            data: bookingsDataset,
                            backgroundColor: '#2563eb',
                            yAxisID: 'y'
                        },
                        {
                            label: '<?php echo esc_js(rbf_translate_string('Persone')); ?>',
                            data: peopleDataset,
                            backgroundColor: '#10b981',
                            yAxisID: 'y'
                        },
                        {
                            type: 'line',
                            label: '<?php echo esc_js(rbf_translate_string('Ricavi Stimati (€)')); ?>',
                            data: revenueDataset,
                            borderColor: '#f59e0b',
                            backgroundColor: 'rgba(245, 158, 11, 0.35)',
                            borderWidth: 2,
                            fill: false,
                            yAxisID: 'y1',
                            hidden: true,
                            tension: 0.3,
                            pointRadius: 3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        },
                        y1: {
                            beginAtZero: true,
                            position: 'right',
                            grid: {
                                drawOnChartArea: false
                            },
                            ticks: {
                                callback: value => '€' + Number(value).toLocaleString('it-IT')
                            }
                        },
                    },
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const datasetLabel = context.dataset.label || '';
                                    const value = context.parsed.y;

                                    if (datasetLabel.indexOf('€') !== -1) {
                                        return datasetLabel + ': €' + Number(value).toLocaleString('it-IT', { minimumFractionDigits: 0 });
                                    }

                                    return datasetLabel + ': ' + Number(value).toLocaleString('it-IT');
                                }
                            },
                        },
                    },
                },
            });
        }
    });
    </script>
    <?php
}

/**
 * Filter bookings by status
 */
add_action('pre_get_posts', 'rbf_filter_by_status');
function rbf_filter_by_status($query) {
    if (!is_admin() || !$query->is_main_query()) return;
    if ($query->get('post_type') !== 'rbf_booking') return;
    
    $raw_status = isset($_GET['rbf_status']) ? wp_unslash($_GET['rbf_status']) : '';
    $status = sanitize_key($raw_status);

    if ($status && array_key_exists($status, rbf_get_booking_statuses())) {
        $query->set('meta_query', [
            [
                'key' => 'rbf_booking_status',
                'value' => $status,
                'compare' => '='
            ]
        ]);
    }
}

/**
 * Get booking analytics for reports
 */
function rbf_get_booking_analytics($start_date, $end_date, $selected_channels = []) {
    global $wpdb;

    $selected_channels = array_values(array_filter(array_map('sanitize_key', (array) $selected_channels)));

    $format_label = static function ($value) {
        if (!is_scalar($value)) {
            return '';
        }

        $value = trim((string) wp_strip_all_tags($value));
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/[_\s-]+/', ' ', $value);

        if (function_exists('mb_convert_case')) {
            return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
        }

        return ucwords(strtolower($value));
    };

    $default_channels = [
        'gads'           => rbf_translate_string('Google Ads'),
        'google_organic' => rbf_translate_string('Google Organico'),
        'facebook_ads'   => rbf_translate_string('Facebook Ads'),
        'facebook_org'   => rbf_translate_string('Facebook Organico'),
        'direct'         => rbf_translate_string('Traffico Diretto'),
        'referral'       => rbf_translate_string('Referral'),
        'backend'        => rbf_translate_string('Inserimento Manuale'),
        'other'          => rbf_translate_string('Altre Sorgenti'),
    ];

    $channel_alias_map = [
        'manual'          => 'backend',
        'manuale'         => 'backend',
        'backend_manual'  => 'backend',
        'backendmanual'   => 'backend',
        'googleads'       => 'gads',
        'google_ads'      => 'gads',
        'google'          => 'gads',
        'sem'             => 'gads',
        'ppc'             => 'gads',
        'organic'         => 'google_organic',
        'seo'             => 'google_organic',
        'googleorganic'   => 'google_organic',
        'google_organic'  => 'google_organic',
        'googleorganico'  => 'google_organic',
        'metaads'         => 'facebook_ads',
        'facebookads'     => 'facebook_ads',
        'fbads'           => 'facebook_ads',
        'instagramads'    => 'facebook_ads',
        'facebook'        => 'facebook_org',
        'fb'              => 'facebook_org',
        'fborg'           => 'facebook_org',
        'instagram'       => 'facebook_org',
        'meta'            => 'facebook_org',
        'diretto'         => 'direct',
        'ref'             => 'referral',
        'referrals'       => 'referral',
        'partner'         => 'referral',
        'partnership'     => 'referral',
        'altro'           => 'other',
    ];

    $normalized_selected_channels = [];
    foreach ($selected_channels as $channel_key) {
        $normalized_selected_channels[] = $channel_alias_map[$channel_key] ?? $channel_key;
    }
    $selected_channels = array_values(array_unique($normalized_selected_channels));

    // Get all bookings in date range
    $bookings = $wpdb->get_results($wpdb->prepare(
        "SELECT p.ID, pm_date.meta_value as booking_date, pm_people.meta_value as people,
                COALESCE(pm_meal.meta_value, pm_meal_legacy.meta_value) as meal, pm_status.meta_value as status,
                pm_source.meta_value as source, pm_bucket.meta_value as bucket,
                pm_medium.meta_value as medium, pm_campaign.meta_value as campaign,
                pm_value_tot.meta_value as booking_value, pm_value_pp.meta_value as booking_unit_value
         FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'rbf_data'
         LEFT JOIN {$wpdb->postmeta} pm_people ON p.ID = pm_people.post_id AND pm_people.meta_key = 'rbf_persone'
         LEFT JOIN {$wpdb->postmeta} pm_meal ON p.ID = pm_meal.post_id AND pm_meal.meta_key = 'rbf_meal'
         LEFT JOIN {$wpdb->postmeta} pm_meal_legacy ON p.ID = pm_meal_legacy.post_id AND pm_meal_legacy.meta_key = 'rbf_orario'
         LEFT JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = 'rbf_booking_status'
         LEFT JOIN {$wpdb->postmeta} pm_source ON p.ID = pm_source.post_id AND pm_source.meta_key = 'rbf_source'
         LEFT JOIN {$wpdb->postmeta} pm_bucket ON p.ID = pm_bucket.post_id AND pm_bucket.meta_key = 'rbf_source_bucket'
         LEFT JOIN {$wpdb->postmeta} pm_medium ON p.ID = pm_medium.post_id AND pm_medium.meta_key = 'rbf_medium'
         LEFT JOIN {$wpdb->postmeta} pm_campaign ON p.ID = pm_campaign.post_id AND pm_campaign.meta_key = 'rbf_campaign'
         LEFT JOIN {$wpdb->postmeta} pm_value_tot ON p.ID = pm_value_tot.post_id AND pm_value_tot.meta_key = 'rbf_valore_tot'
         LEFT JOIN {$wpdb->postmeta} pm_value_pp ON p.ID = pm_value_pp.post_id AND pm_value_pp.meta_key = 'rbf_valore_pp'
         WHERE p.post_type = 'rbf_booking' AND p.post_status = 'publish'
         AND pm_date.meta_value >= %s AND pm_date.meta_value <= %s
         ORDER BY pm_date.meta_value ASC",
        $start_date, $end_date
    ));
    
    $statuses = rbf_get_booking_statuses();
    $meals = [
        'pranzo' => rbf_translate_string('Pranzo'),
        'cena' => rbf_translate_string('Cena'),
        'aperitivo' => rbf_translate_string('Aperitivo'),
        'brunch' => rbf_translate_string('Brunch')
    ];
    
    // Initialize analytics data
    $analytics = [
        'total_bookings' => 0,
        'total_people' => 0,
        'total_revenue' => 0,
        'avg_people_per_booking' => 0,
        'avg_revenue_per_booking' => 0,
        'completion_rate' => 0,
        'confirmed_bookings' => 0,
        'completed_bookings' => 0,
        'by_status' => [],
        'by_meal' => [],
        'by_source' => [],
        'daily_bookings' => [],
        'source_breakdown' => [],
        'medium_breakdown' => [],
        'campaign_breakdown' => [],
        'top_channels' => [],
        'top_mediums' => [],
        'top_campaigns' => [],
        'channels_catalog' => [],
        'channel_counts_all' => [],
        'selected_channels' => $selected_channels
    ];

    // Initialize status counts
    foreach ($statuses as $key => $label) {
        $analytics['by_status'][$label] = 0;
    }
    
    // Initialize meal counts
    foreach ($meals as $key => $label) {
        $analytics['by_meal'][$label] = 0;
    }
    
    // Initialize daily data for the range
    $current_date = new DateTime($start_date);
    $end_date_obj = new DateTime($end_date);
    while ($current_date <= $end_date_obj) {
        $analytics['daily_bookings'][$current_date->format('d/m')] = 0;
        $current_date->modify('+1 day');
    }

    $channel_options = $default_channels;
    $channel_counts_all = array_fill_keys(array_keys($default_channels), 0);

    // Process each booking
    foreach ($bookings as $booking) {
        $people = intval($booking->people ?: 0);
        $meal = $booking->meal ?: 'pranzo';
        $status = $booking->status ?: 'confirmed';
        $source = $booking->source ?: 'direct';
        $bucket = $booking->bucket ?: 'direct';
        $medium = isset($booking->medium) ? trim((string) $booking->medium) : '';
        $campaign = isset($booking->campaign) ? trim((string) $booking->campaign) : '';

        $channel_key = strtolower($bucket !== '' ? $bucket : $source);
        $channel_key = preg_replace('/[^a-z0-9_-]/', '', $channel_key);

        if ($channel_key === '') {
            $fallback_key = preg_replace('/[^a-z0-9_-]/', '', strtolower($source));
            $channel_key = $fallback_key !== '' ? $fallback_key : 'direct';
        }

        if (isset($channel_alias_map[$channel_key])) {
            $channel_key = $channel_alias_map[$channel_key];
        }

        if (!isset($channel_options[$channel_key])) {
            $fallback_label = trim((string) ($source !== '' ? $source : $bucket));
            if ($fallback_label === '') {
                $channel_key = 'other';
                $channel_options[$channel_key] = $default_channels['other'];
            } else {
                $normalized_label = ucwords(str_replace(['_', '-'], ' ', $fallback_label));
                $channel_options[$channel_key] = $normalized_label;
            }
        }

        if (!isset($channel_counts_all[$channel_key])) {
            $channel_counts_all[$channel_key] = 0;
        }
        $channel_counts_all[$channel_key]++;

        if (!empty($selected_channels) && !in_array($channel_key, $selected_channels, true)) {
            continue;
        }

        // Basic totals
        $analytics['total_bookings']++;
        $analytics['total_people'] += $people;

        // Revenue calculation prioritizing stored booking metadata
        $booking_revenue = isset($booking->booking_value) ? (float) $booking->booking_value : 0;
        if ($booking_revenue <= 0 && isset($booking->booking_unit_value) && (float) $booking->booking_unit_value > 0) {
            $booking_revenue = (float) $booking->booking_unit_value * $people;
        }
        if ($booking_revenue <= 0) {
            $meal_config = rbf_get_meal_config($meal);
            $meal_value = $meal_config ? (float) $meal_config['price'] : 0;
            $booking_revenue = $meal_value * $people;
        }

        $analytics['total_revenue'] += $booking_revenue;

        // Status tracking
        $status_label = $statuses[$status] ?? $status;
        $analytics['by_status'][$status_label]++;
        
        if ($status === 'confirmed' || $status === 'completed') {
            $analytics['confirmed_bookings']++;
        }
        if ($status === 'completed') {
            $analytics['completed_bookings']++;
        }
        
        // Meal tracking
        $meal_label = $meals[$meal] ?? ucfirst($meal);
        $analytics['by_meal'][$meal_label]++;

        // Source tracking with breakdown details
        if (!isset($analytics['source_breakdown'][$channel_key])) {
            $analytics['source_breakdown'][$channel_key] = [
                'label' => $channel_options[$channel_key] ?? ucfirst($channel_key),
                'bookings' => 0,
                'people' => 0,
                'revenue' => 0.0,
                'confirmed' => 0,
                'completed' => 0,
                'conversion_rate' => 0.0,
                'share' => 0.0,
                'avg_revenue_per_booking' => 0.0,
                'avg_people_per_booking' => 0.0,
                'status_counts' => [],
            ];
        }

        $analytics['source_breakdown'][$channel_key]['bookings']++;
        $analytics['source_breakdown'][$channel_key]['people'] += $people;
        $analytics['source_breakdown'][$channel_key]['revenue'] += $booking_revenue;

        $status_key = $status !== '' ? $status : 'confirmed';
        $current_status_count = $analytics['source_breakdown'][$channel_key]['status_counts'][$status_key] ?? 0;
        $analytics['source_breakdown'][$channel_key]['status_counts'][$status_key] = $current_status_count + 1;

        if ($status === 'completed') {
            $analytics['source_breakdown'][$channel_key]['completed']++;
        }
        if ($status === 'confirmed' || $status === 'completed') {
            $analytics['source_breakdown'][$channel_key]['confirmed']++;
        }

        if ($medium !== '') {
            $medium_key = strtolower(preg_replace('/[^a-z0-9]+/', '_', $medium));
            if ($medium_key === '') {
                $medium_key = 'medium_' . substr(md5($medium), 0, 6);
            }

            if (!isset($analytics['medium_breakdown'][$medium_key])) {
                $medium_label = $format_label($medium);
                if ($medium_label === '') {
                    $medium_label = trim((string) wp_strip_all_tags($medium));
                }
                if ($medium_label === '') {
                    $medium_label = rbf_translate_string('Medium');
                }

                $analytics['medium_breakdown'][$medium_key] = [
                    'label' => $medium_label,
                    'bookings' => 0,
                    'people' => 0,
                    'revenue' => 0.0,
                    'confirmed' => 0,
                    'completed' => 0,
                ];
            }

            $analytics['medium_breakdown'][$medium_key]['bookings']++;
            $analytics['medium_breakdown'][$medium_key]['people'] += $people;
            $analytics['medium_breakdown'][$medium_key]['revenue'] += $booking_revenue;

            if ($status === 'completed') {
                $analytics['medium_breakdown'][$medium_key]['completed']++;
            }
            if ($status === 'confirmed' || $status === 'completed') {
                $analytics['medium_breakdown'][$medium_key]['confirmed']++;
            }
        }

        if ($campaign !== '') {
            $campaign_key = strtolower(preg_replace('/[^a-z0-9]+/', '_', $campaign));
            if ($campaign_key === '') {
                $campaign_key = 'campaign_' . substr(md5($campaign), 0, 6);
            }

            if (!isset($analytics['campaign_breakdown'][$campaign_key])) {
                $campaign_label = trim((string) wp_strip_all_tags($campaign));
                if ($campaign_label === '') {
                    $campaign_label = $format_label($campaign);
                }
                if ($campaign_label === '') {
                    $campaign_label = rbf_translate_string('Campagna');
                }

                $analytics['campaign_breakdown'][$campaign_key] = [
                    'label' => $campaign_label,
                    'bookings' => 0,
                    'people' => 0,
                    'revenue' => 0.0,
                    'confirmed' => 0,
                    'completed' => 0,
                ];
            }

            $analytics['campaign_breakdown'][$campaign_key]['bookings']++;
            $analytics['campaign_breakdown'][$campaign_key]['people'] += $people;
            $analytics['campaign_breakdown'][$campaign_key]['revenue'] += $booking_revenue;

            if ($status === 'completed') {
                $analytics['campaign_breakdown'][$campaign_key]['completed']++;
            }
            if ($status === 'confirmed' || $status === 'completed') {
                $analytics['campaign_breakdown'][$campaign_key]['confirmed']++;
            }
        }

        // Daily tracking
        $booking_date = DateTime::createFromFormat('Y-m-d', $booking->booking_date);
        if ($booking_date) {
            $day_key = $booking_date->format('d/m');
            if (isset($analytics['daily_bookings'][$day_key])) {
                $analytics['daily_bookings'][$day_key]++;
            }
        }
    }

    // Calculate averages and rates
    if ($analytics['total_bookings'] > 0) {
        $analytics['avg_people_per_booking'] = $analytics['total_people'] / $analytics['total_bookings'];
        $analytics['avg_revenue_per_booking'] = $analytics['total_revenue'] / $analytics['total_bookings'];
    }

    if ($analytics['confirmed_bookings'] > 0) {
        $analytics['completion_rate'] = ($analytics['completed_bookings'] / $analytics['confirmed_bookings']) * 100;
    }

    // Sort and normalize source breakdown data
    if (!empty($analytics['source_breakdown'])) {
        foreach ($analytics['source_breakdown'] as $channel_key => &$channel_data) {
            $bookings_total = max(1, (int) ($channel_data['bookings'] ?? 0));
            $people_total = (float) ($channel_data['people'] ?? 0);
            $revenue_total = (float) ($channel_data['revenue'] ?? 0);
            $confirmed_count = (int) ($channel_data['confirmed'] ?? 0);
            $completed_count = (int) ($channel_data['completed'] ?? 0);

            $channel_data['avg_people_per_booking'] = $bookings_total > 0 ? $people_total / $bookings_total : 0;
            $channel_data['avg_revenue_per_booking'] = $bookings_total > 0 ? $revenue_total / $bookings_total : 0;
            $channel_data['conversion_rate'] = $confirmed_count > 0 ? ($completed_count / $confirmed_count) * 100 : 0;
            $channel_data['share'] = $analytics['total_bookings'] > 0
                ? (($channel_data['bookings'] ?? 0) / $analytics['total_bookings']) * 100
                : 0;
        }
        unset($channel_data);

        $top_channels_sorted = array_filter($analytics['source_breakdown'], static function ($item) {
            return ($item['bookings'] ?? 0) > 0;
        });

        uasort($top_channels_sorted, static function ($a, $b) {
            $rateA = $a['conversion_rate'] ?? 0;
            $rateB = $b['conversion_rate'] ?? 0;

            if (abs($rateA - $rateB) < 0.0001) {
                return ($b['completed'] ?? 0) <=> ($a['completed'] ?? 0);
            }

            return $rateB <=> $rateA;
        });

        $analytics['top_channels'] = array_slice(array_values($top_channels_sorted), 0, 6);

        uasort($analytics['source_breakdown'], static function ($a, $b) {
            return $b['bookings'] <=> $a['bookings'];
        });

        foreach ($analytics['source_breakdown'] as $channel_key => $channel_data) {
            $analytics['by_source'][$channel_data['label']] = $channel_data['bookings'];
        }
    }

    if (!empty($analytics['medium_breakdown'])) {
        foreach ($analytics['medium_breakdown'] as &$medium_data) {
            $bookings_total = max(1, (int) ($medium_data['bookings'] ?? 0));
            $people_total = (float) ($medium_data['people'] ?? 0);
            $revenue_total = (float) ($medium_data['revenue'] ?? 0);
            $confirmed_count = (int) ($medium_data['confirmed'] ?? 0);
            $completed_count = (int) ($medium_data['completed'] ?? 0);

            $medium_data['avg_people_per_booking'] = $bookings_total > 0 ? $people_total / $bookings_total : 0;
            $medium_data['avg_revenue_per_booking'] = $bookings_total > 0 ? $revenue_total / $bookings_total : 0;
            $medium_data['conversion_rate'] = $confirmed_count > 0 ? ($completed_count / $confirmed_count) * 100 : 0;
            $medium_data['share'] = $analytics['total_bookings'] > 0
                ? (($medium_data['bookings'] ?? 0) / $analytics['total_bookings']) * 100
                : 0;
        }
        unset($medium_data);

        $sorted_mediums = array_filter($analytics['medium_breakdown'], static function ($item) {
            return ($item['bookings'] ?? 0) > 0;
        });

        uasort($sorted_mediums, static function ($a, $b) {
            $rateA = $a['conversion_rate'] ?? 0;
            $rateB = $b['conversion_rate'] ?? 0;

            if (abs($rateA - $rateB) < 0.0001) {
                return ($b['bookings'] ?? 0) <=> ($a['bookings'] ?? 0);
            }

            return $rateB <=> $rateA;
        });

        $analytics['top_mediums'] = array_slice(array_values($sorted_mediums), 0, 5);
    }

    if (!empty($analytics['campaign_breakdown'])) {
        foreach ($analytics['campaign_breakdown'] as &$campaign_data) {
            $bookings_total = max(1, (int) ($campaign_data['bookings'] ?? 0));
            $people_total = (float) ($campaign_data['people'] ?? 0);
            $revenue_total = (float) ($campaign_data['revenue'] ?? 0);
            $confirmed_count = (int) ($campaign_data['confirmed'] ?? 0);
            $completed_count = (int) ($campaign_data['completed'] ?? 0);

            $campaign_data['avg_people_per_booking'] = $bookings_total > 0 ? $people_total / $bookings_total : 0;
            $campaign_data['avg_revenue_per_booking'] = $bookings_total > 0 ? $revenue_total / $bookings_total : 0;
            $campaign_data['conversion_rate'] = $confirmed_count > 0 ? ($completed_count / $confirmed_count) * 100 : 0;
            $campaign_data['share'] = $analytics['total_bookings'] > 0
                ? (($campaign_data['bookings'] ?? 0) / $analytics['total_bookings']) * 100
                : 0;
        }
        unset($campaign_data);

        $sorted_campaigns = array_filter($analytics['campaign_breakdown'], static function ($item) {
            return ($item['bookings'] ?? 0) > 0;
        });

        uasort($sorted_campaigns, static function ($a, $b) {
            $revenueA = $a['revenue'] ?? 0;
            $revenueB = $b['revenue'] ?? 0;

            if (abs($revenueA - $revenueB) < 0.01) {
                $rateA = $a['conversion_rate'] ?? 0;
                $rateB = $b['conversion_rate'] ?? 0;

                if (abs($rateA - $rateB) < 0.0001) {
                    return ($b['bookings'] ?? 0) <=> ($a['bookings'] ?? 0);
                }

                return $rateB <=> $rateA;
            }

            return $revenueB <=> $revenueA;
        });

        $analytics['top_campaigns'] = array_slice(array_values($sorted_campaigns), 0, 5);
    }

    // Prepare channel metadata for filters
    $filtered_channel_options = [];
    foreach ($channel_options as $channel_key => $channel_label) {
        $count = $channel_counts_all[$channel_key] ?? 0;

        if ($count > 0 || in_array($channel_key, $selected_channels, true)) {
            $filtered_channel_options[$channel_key] = $channel_label;
        }
    }

    if (!empty($filtered_channel_options)) {
        asort($filtered_channel_options, SORT_NATURAL | SORT_FLAG_CASE);
    }

    $channel_counts_all = array_intersect_key($channel_counts_all, $filtered_channel_options);

    $analytics['channels_catalog'] = $filtered_channel_options;
    $analytics['channel_counts_all'] = $channel_counts_all;

    return $analytics;
}

/**
 * Export page HTML
 */
function rbf_export_page_html() {
    if (!rbf_require_booking_capability()) {
        return;
    }

    // Handle export request
    if (isset($_POST['export_bookings']) && wp_verify_nonce($_POST['_wpnonce'], 'rbf_export')) {
        $sanitized = rbf_sanitize_input_fields($_POST, [
            'start_date' => 'text',
            'end_date' => 'text',
            'format' => 'text',
            'status_filter' => 'text'
        ]);

        $start_date = $sanitized['start_date'];
        $end_date = $sanitized['end_date'];
        $format = $sanitized['format'];
        $status_filter = $sanitized['status_filter'];

        $handled = rbf_handle_export_request($start_date, $end_date, $format, $status_filter);
        if ($handled) {
            return; // Exit after sending file
        }
    }
    
    $default_start = date('Y-m-d', strtotime('-30 days'));
    $default_end = date('Y-m-d');
    ?>
    <div class="rbf-admin-wrap rbf-admin-wrap--narrow">
        <h1><?php echo esc_html(rbf_translate_string('Esporta Dati Prenotazioni')); ?></h1>
        
        <div class="rbf-admin-card">
            <form method="post" class="rbf-admin-form rbf-admin-form--stacked">
                <?php wp_nonce_field('rbf_export'); ?>

                <div class="rbf-admin-grid rbf-admin-grid--cols-2">
                    <div class="rbf-form-group">
                        <label for="start_date"><?php echo esc_html(rbf_translate_string('Data Inizio')); ?></label>
                        <input type="date" id="start_date" name="start_date" class="rbf-field" value="<?php echo esc_attr($default_start); ?>" required>
                    </div>
                    <div class="rbf-form-group">
                        <label for="end_date"><?php echo esc_html(rbf_translate_string('Data Fine')); ?></label>
                        <input type="date" id="end_date" name="end_date" class="rbf-field" value="<?php echo esc_attr($default_end); ?>" required>
                    </div>
                    <div class="rbf-form-group">
                        <label for="status_filter"><?php echo esc_html(rbf_translate_string('Filtra per Stato')); ?></label>
                        <select id="status_filter" name="status_filter" class="rbf-field">
                            <option value=""><?php echo esc_html(rbf_translate_string('Tutti gli stati')); ?></option>
                            <?php
                            $statuses = rbf_get_booking_statuses();
                            foreach ($statuses as $key => $label) {
                                echo '<option value="' . esc_attr($key) . '">' . esc_html($label) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="rbf-form-group">
                        <label for="format"><?php echo esc_html(rbf_translate_string('Formato Export')); ?></label>
                        <select id="format" name="format" class="rbf-field">
                            <option value="csv">CSV (Excel)</option>
                            <option value="json">JSON</option>
                        </select>
                    </div>
                </div>

                <div class="rbf-form-actions">
                    <?php submit_button(rbf_translate_string('Esporta Prenotazioni'), 'primary', 'export_bookings', false); ?>
                </div>
            </form>
        </div>

        <div class="rbf-admin-callout">
            <h3><?php echo esc_html(rbf_translate_string('Informazioni Export')); ?></h3>
            <p><?php echo esc_html(rbf_translate_string('L\'export includerà tutti i dati delle prenotazioni nel periodo selezionato:')); ?></p>
            <ul class="rbf-admin-callout__list">
                <li><?php echo esc_html(rbf_translate_string('Informazioni cliente (nome, email, telefono)')); ?></li>
                <li><?php echo esc_html(rbf_translate_string('Dettagli prenotazione (data, orario, servizio, persone)')); ?></li>
                <li><?php echo esc_html(rbf_translate_string('Stato prenotazione e cronologia')); ?></li>
                <li><?php echo esc_html(rbf_translate_string('Sorgenti di traffico e parametri UTM')); ?></li>
                <li><?php echo esc_html(rbf_translate_string('Note e preferenze alimentari')); ?></li>
                <li><?php echo esc_html(rbf_translate_string('Consensi privacy e marketing')); ?></li>
            </ul>
        </div>
    </div>
    <?php
}

/**
 * Handle export request
 */
function rbf_handle_export_request($start_date, $end_date, $format, $status_filter) {
    global $wpdb;

    if (!rbf_user_can_manage_bookings()) {
        wp_die(esc_html(rbf_translate_string('Non hai le autorizzazioni per esportare le prenotazioni.')));
    }

    $start = rbf_parse_export_date($start_date);
    if (!$start) {
        rbf_add_admin_notice(rbf_translate_string('Data di inizio non valida. Usa il formato YYYY-MM-DD.'), 'error');
        return false;
    }

    $end = rbf_parse_export_date($end_date);
    if (!$end) {
        rbf_add_admin_notice(rbf_translate_string('Data di fine non valida. Usa il formato YYYY-MM-DD.'), 'error');
        return false;
    }

    if ($end < $start) {
        rbf_add_admin_notice(rbf_translate_string('La data di fine non può essere precedente alla data di inizio.'), 'error');
        return false;
    }

    $format = strtolower($format);
    if (!in_array($format, ['csv', 'json'], true)) {
        rbf_add_admin_notice(rbf_translate_string('Formato export non supportato.'), 'error');
        return false;
    }

    $valid_statuses = array_keys(rbf_get_booking_statuses());
    $status_filter = sanitize_key($status_filter);
    if (!empty($status_filter) && !in_array($status_filter, $valid_statuses, true)) {
        rbf_add_admin_notice(rbf_translate_string('Stato selezionato non valido.'), 'error');
        $status_filter = '';
    }

    $normalized_start = $start->format('Y-m-d');
    $normalized_end = $end->format('Y-m-d');

    // Build query
    $where_status = '';
    if ($status_filter) {
        $where_status = $wpdb->prepare(" AND pm_status.meta_value = %s", $status_filter);
    }

    $bookings = $wpdb->get_results($wpdb->prepare(
        "SELECT p.ID, p.post_title, p.post_date,
                pm_date.meta_value as booking_date,
                pm_time.meta_value as booking_time,
                pm_people.meta_value as people,
                COALESCE(pm_meal.meta_value, pm_meal_legacy.meta_value) as meal,
                pm_status.meta_value as status,
                pm_first_name.meta_value as first_name,
                pm_last_name.meta_value as last_name,
                pm_email.meta_value as email,
                pm_tel.meta_value as tel,
                pm_notes.meta_value as notes,
                pm_lang.meta_value as language,
                pm_privacy.meta_value as privacy,
                pm_marketing.meta_value as marketing,
                pm_source.meta_value as source,
                pm_medium.meta_value as medium,
                pm_campaign.meta_value as campaign,
                pm_bucket.meta_value as bucket,
                pm_gclid.meta_value as gclid,
                pm_fbclid.meta_value as fbclid,
                pm_created.meta_value as created_date,
                pm_value_tot.meta_value as value_tot,
                pm_value_pp.meta_value as value_pp
         FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'rbf_data'
         LEFT JOIN {$wpdb->postmeta} pm_time ON p.ID = pm_time.post_id AND pm_time.meta_key = 'rbf_time'
         LEFT JOIN {$wpdb->postmeta} pm_people ON p.ID = pm_people.post_id AND pm_people.meta_key = 'rbf_persone'
         LEFT JOIN {$wpdb->postmeta} pm_meal ON p.ID = pm_meal.post_id AND pm_meal.meta_key = 'rbf_meal'
         LEFT JOIN {$wpdb->postmeta} pm_meal_legacy ON p.ID = pm_meal_legacy.post_id AND pm_meal_legacy.meta_key = 'rbf_orario'
         LEFT JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = 'rbf_booking_status'
         LEFT JOIN {$wpdb->postmeta} pm_first_name ON p.ID = pm_first_name.post_id AND pm_first_name.meta_key = 'rbf_nome'
         LEFT JOIN {$wpdb->postmeta} pm_last_name ON p.ID = pm_last_name.post_id AND pm_last_name.meta_key = 'rbf_cognome'
         LEFT JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = 'rbf_email'
         LEFT JOIN {$wpdb->postmeta} pm_tel ON p.ID = pm_tel.post_id AND pm_tel.meta_key = 'rbf_tel'
         LEFT JOIN {$wpdb->postmeta} pm_notes ON p.ID = pm_notes.post_id AND pm_notes.meta_key = 'rbf_allergie'
         LEFT JOIN {$wpdb->postmeta} pm_lang ON p.ID = pm_lang.post_id AND pm_lang.meta_key = 'rbf_lang'
         LEFT JOIN {$wpdb->postmeta} pm_privacy ON p.ID = pm_privacy.post_id AND pm_privacy.meta_key = 'rbf_privacy'
         LEFT JOIN {$wpdb->postmeta} pm_marketing ON p.ID = pm_marketing.post_id AND pm_marketing.meta_key = 'rbf_marketing'
         LEFT JOIN {$wpdb->postmeta} pm_source ON p.ID = pm_source.post_id AND pm_source.meta_key = 'rbf_source'
         LEFT JOIN {$wpdb->postmeta} pm_medium ON p.ID = pm_medium.post_id AND pm_medium.meta_key = 'rbf_medium'
         LEFT JOIN {$wpdb->postmeta} pm_campaign ON p.ID = pm_campaign.post_id AND pm_campaign.meta_key = 'rbf_campaign'
         LEFT JOIN {$wpdb->postmeta} pm_bucket ON p.ID = pm_bucket.post_id AND pm_bucket.meta_key = 'rbf_source_bucket'
         LEFT JOIN {$wpdb->postmeta} pm_gclid ON p.ID = pm_gclid.post_id AND pm_gclid.meta_key = 'rbf_gclid'
         LEFT JOIN {$wpdb->postmeta} pm_fbclid ON p.ID = pm_fbclid.post_id AND pm_fbclid.meta_key = 'rbf_fbclid'
         LEFT JOIN {$wpdb->postmeta} pm_created ON p.ID = pm_created.post_id AND pm_created.meta_key = 'rbf_booking_created'
         LEFT JOIN {$wpdb->postmeta} pm_value_tot ON p.ID = pm_value_tot.post_id AND pm_value_tot.meta_key = 'rbf_valore_tot'
         LEFT JOIN {$wpdb->postmeta} pm_value_pp ON p.ID = pm_value_pp.post_id AND pm_value_pp.meta_key = 'rbf_valore_pp'
         WHERE p.post_type = 'rbf_booking' AND p.post_status = 'publish'
         AND pm_date.meta_value >= %s AND pm_date.meta_value <= %s
         {$where_status}
         ORDER BY pm_date.meta_value DESC, pm_time.meta_value DESC",
        $normalized_start, $normalized_end
    ));

    if (empty($bookings)) {
        rbf_add_admin_notice(rbf_translate_string('Nessuna prenotazione trovata per il periodo selezionato.'), 'warning');
        return false;
    }

    if ($format === 'csv') {
        rbf_export_csv($bookings, $normalized_start, $normalized_end);
    } else {
        rbf_export_json($bookings, $normalized_start, $normalized_end);
    }

    return true;
}

/**
 * Parse and validate export date strings.
 */
function rbf_parse_export_date($value) {
    if (!is_scalar($value)) {
        return false;
    }

    $value = trim((string) $value);
    if ($value === '') {
        return false;
    }

    $timezone = rbf_wp_timezone();
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $timezone);

    if (!$date) {
        return false;
    }

    $errors = DateTimeImmutable::getLastErrors();
    if ($errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
        return false;
    }

    return $date;
}

/**
 * Export bookings as CSV
 */
function rbf_export_csv($bookings, $start_date, $end_date) {
    $filename = 'bookings_' . $start_date . '_to_' . $end_date . '_' . date('Ymd_His') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for Excel UTF-8 support
    fwrite($output, "\xEF\xBB\xBF");
    
    // CSV Headers
    $headers = [
        'ID', 'Data Prenotazione', 'Orario', 'Nome', 'Cognome', 'Email', 'Telefono',
        'Persone', 'Servizio', 'Stato', 'Note/Allergie', 'Lingua', 'Privacy', 'Marketing',
        'Sorgente', 'Medium', 'Campagna', 'Bucket', 'Google Click ID', 'Facebook Click ID',
        'Data Creazione', 'Data Invio', 'Valore Totale', 'Prezzo Unitario'
    ];
    
    fputcsv($output, $headers);
    
    // Data rows
    foreach ($bookings as $booking) {
        $statuses = rbf_get_booking_statuses();
        $status_label = $statuses[$booking->status ?? 'confirmed'] ?? ($booking->status ?? 'confirmed');
        
        $people = intval($booking->people ?: 0);
        $value_tot = isset($booking->value_tot) ? (float) $booking->value_tot : 0;
        $value_pp = isset($booking->value_pp) ? (float) $booking->value_pp : 0;

        if ($value_tot <= 0 && $value_pp > 0) {
            $value_tot = $value_pp * $people;
        }
        if ($value_tot <= 0) {
            $meal_config = rbf_get_meal_config($booking->meal);
            $value_pp_fallback = $meal_config ? (float) $meal_config['price'] : 0;
            if ($value_pp_fallback > 0) {
                $value_tot = $value_pp_fallback * $people;
                if ($value_pp <= 0) {
                    $value_pp = $value_pp_fallback;
                }
            }
        }
        if ($value_pp <= 0 && $people > 0) {
            $value_pp = $value_tot / max(1, $people);
        }

        $row = [
            $booking->ID,
            $booking->booking_date,
            $booking->booking_time,
            $booking->first_name,
            $booking->last_name,
            $booking->email,
            $booking->tel,
            $booking->people,
            $booking->meal,
            $status_label,
            $booking->notes,
            $booking->language ?: 'it',
            $booking->privacy === 'yes' ? 'Sì' : 'No',
            $booking->marketing === 'yes' ? 'Sì' : 'No',
            $booking->source,
            $booking->medium,
            $booking->campaign,
            $booking->bucket,
            $booking->gclid,
            $booking->fbclid,
            $booking->created_date,
            $booking->post_date,
            number_format($value_tot, 2, '.', ''),
            number_format($value_pp, 2, '.', '')
        ];

        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

/**
 * Export bookings as JSON
 */
function rbf_export_json($bookings, $start_date, $end_date) {
    $filename = 'bookings_' . $start_date . '_to_' . $end_date . '_' . date('Ymd_His') . '.json';
    
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $export_data = [
        'export_info' => [
            'generated' => current_time('Y-m-d H:i:s'),
            'date_range' => ['start' => $start_date, 'end' => $end_date],
            'total_bookings' => count($bookings),
            'plugin_version' => RBF_VERSION
        ],
        'bookings' => []
    ];
    
    $statuses = rbf_get_booking_statuses();
    
    foreach ($bookings as $booking) {
        $people = intval($booking->people ?: 0);
        $value_tot = isset($booking->value_tot) ? (float) $booking->value_tot : 0;
        $value_pp = isset($booking->value_pp) ? (float) $booking->value_pp : 0;

        if ($value_tot <= 0 && $value_pp > 0) {
            $value_tot = $value_pp * $people;
        }
        if ($value_tot <= 0) {
            $meal_config = rbf_get_meal_config($booking->meal);
            $value_pp_fallback = $meal_config ? (float) $meal_config['price'] : 0;
            if ($value_pp_fallback > 0) {
                $value_tot = $value_pp_fallback * $people;
                if ($value_pp <= 0) {
                    $value_pp = $value_pp_fallback;
                }
            }
        }
        if ($value_pp <= 0 && $people > 0) {
            $value_pp = $value_tot / max(1, $people);
        }

        $export_data['bookings'][] = [
            'id' => intval($booking->ID),
            'title' => $booking->post_title,
            'booking_date' => $booking->booking_date,
            'booking_time' => $booking->booking_time,
            'customer' => [
                'first_name' => $booking->first_name,
                'last_name' => $booking->last_name,
                'email' => $booking->email,
                'phone' => $booking->tel,
                'language' => $booking->language ?: 'it'
            ],
            'booking_details' => [
                'people' => $people,
                'meal' => $booking->meal,
                'status' => $booking->status ?: 'confirmed',
                'status_label' => $statuses[$booking->status ?? 'confirmed'] ?? ($booking->status ?? 'confirmed'),
                'notes' => $booking->notes,
                'value_total' => round($value_tot, 2),
                'value_per_person' => round($value_pp, 2)
            ],
            'consent' => [
                'privacy' => $booking->privacy === 'yes',
                'marketing' => $booking->marketing === 'yes'
            ],
            'attribution' => [
                'source' => $booking->source,
                'medium' => $booking->medium,
                'campaign' => $booking->campaign,
                'bucket' => $booking->bucket,
                'google_click_id' => $booking->gclid,
                'facebook_click_id' => $booking->fbclid
            ],
            'timestamps' => [
                'created' => $booking->created_date,
                'submitted' => $booking->post_date
            ]
        ];
    }
    
    echo wp_json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Automatic status management - run daily to update completed bookings
 */
add_action('init', 'rbf_schedule_status_updates');
function rbf_schedule_status_updates() {
    if (wp_next_scheduled('rbf_update_booking_statuses')) {
        return;
    }

    $timestamp = rbf_get_next_daily_event_timestamp(6, 0);

    if ($timestamp === null) {
        // Fallback: schedule approximately one day from now.
        $timestamp = time() + DAY_IN_SECONDS;
    }

    wp_schedule_event($timestamp, 'daily', 'rbf_update_booking_statuses');
}

add_action('rbf_update_booking_statuses', 'rbf_auto_complete_past_bookings');
function rbf_auto_complete_past_bookings() {
    global $wpdb;
    
    // Get all confirmed bookings from yesterday or earlier
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    
    $bookings = $wpdb->get_results($wpdb->prepare(
        "SELECT p.ID FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'rbf_data'
         INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = 'rbf_booking_status'
         WHERE p.post_type = 'rbf_booking' AND p.post_status = 'publish'
         AND pm_date.meta_value <= %s 
         AND pm_status.meta_value = 'confirmed'",
        $yesterday
    ));
    
    foreach ($bookings as $booking) {
        rbf_update_booking_status($booking->ID, 'completed', 'Auto-completed after booking date');
    }
}

/**
 * Clear scheduled events on plugin deactivation
 */
function rbf_clear_automatic_status_events() {
    wp_clear_scheduled_hook('rbf_update_booking_statuses');
}

/**
 * Table Management admin page
 */
function rbf_tables_page_html() {
    if (!rbf_require_booking_capability()) {
        return;
    }

    // Handle form submissions
    if (isset($_POST['action'])) {
        if (!wp_verify_nonce($_POST['rbf_nonce'], 'rbf_table_management')) {
            wp_die('Nonce verification failed');
        }

        global $wpdb;

        $action = sanitize_text_field($_POST['action']);

        switch ($action) {
            case 'add_area':
                $name = sanitize_text_field($_POST['area_name']);
                $description = sanitize_textarea_field($_POST['area_description']);

                if (!empty($name)) {
                    $areas_table = $wpdb->prefix . 'rbf_areas';
                    $result = $wpdb->insert($areas_table, [
                        'name' => $name,
                        'description' => $description
                    ]);

                    if ($result) {
                        echo '<div class="notice notice-success"><p>Area aggiunta con successo!</p></div>';
                    } else {
                        echo '<div class="notice notice-error"><p>Errore nell\'aggiunta dell\'area.</p></div>';
                    }
                }
                break;

            case 'add_table':
                $area_id = intval($_POST['table_area_id']);
                $name = sanitize_text_field($_POST['table_name']);
                $capacity = intval($_POST['table_capacity']);
                $min_capacity = intval($_POST['table_min_capacity']);
                $max_capacity = intval($_POST['table_max_capacity']);

                if (!empty($name) && $capacity > 0 && $area_id > 0) {
                    $tables_table = $wpdb->prefix . 'rbf_tables';
                    $result = $wpdb->insert($tables_table, [
                        'area_id' => $area_id,
                        'name' => $name,
                        'capacity' => $capacity,
                        'min_capacity' => $min_capacity ?: max(1, $capacity - 2),
                        'max_capacity' => $max_capacity ?: $capacity + 2
                    ]);

                    if ($result) {
                        echo '<div class="notice notice-success"><p>Tavolo aggiunto con successo!</p></div>';
                    } else {
                        echo '<div class="notice notice-error"><p>Errore nell\'aggiunta del tavolo.</p></div>';
                    }
                }
                break;

            case 'add_group':
                $area_id = intval($_POST['group_area_id']);
                $name = sanitize_text_field($_POST['group_name']);
                $max_capacity = intval($_POST['group_max_capacity']);
                $table_ids = array_map('intval', $_POST['group_tables'] ?? []);

                if (!empty($name) && $area_id > 0 && !empty($table_ids)) {
                    $groups_table = $wpdb->prefix . 'rbf_table_groups';
                    $result = $wpdb->insert($groups_table, [
                        'area_id' => $area_id,
                        'name' => $name,
                        'max_combined_capacity' => $max_capacity ?: 16
                    ]);

                    if ($result) {
                        $group_id = $wpdb->insert_id;
                        $group_members_table = $wpdb->prefix . 'rbf_table_group_members';

                        foreach ($table_ids as $index => $table_id) {
                            $wpdb->insert($group_members_table, [
                                'group_id' => $group_id,
                                'table_id' => $table_id,
                                'join_order' => $index + 1
                            ]);
                        }

                        echo '<div class="notice notice-success"><p>Gruppo tavoli aggiunto con successo!</p></div>';
                    } else {
                        echo '<div class="notice notice-error"><p>Errore nell\'aggiunta del gruppo.</p></div>';
                    }
                }
                break;
            default:
                echo '<div class="notice notice-error"><p>' . esc_html(rbf_translate_string('Azione non valida.')) . '</p></div>';
                break;
        }
    }

    $areas = rbf_get_areas();
    $all_tables = rbf_get_all_tables();

    $all_groups = [];
    foreach ($areas as $area) {
        $area_groups = rbf_get_table_groups_by_area($area->id);
        foreach ($area_groups as $group) {
            $group->area_name = $area->name;
            $group->tables = rbf_get_group_tables($group->id);
            $all_groups[] = $group;
        }
    }

    $total_capacity = 0;
    foreach ($all_tables as $table) {
        $total_capacity += (int) $table->capacity;
    }

    $group_selection_texts = [
        'placeholder' => rbf_translate_string("Seleziona prima un'area per visualizzare i tavoli disponibili."),
        'empty' => rbf_translate_string("Nessun tavolo disponibile in quest'area."),
        'hint' => rbf_translate_string('Seleziona i tavoli che possono essere uniti in questo gruppo.'),
        'peopleLabel' => rbf_translate_string('persone'),
    ];
    ?>
    <div class="rbf-admin-wrap rbf-admin-wrap--wide">
        <h1><?php echo esc_html(rbf_translate_string('Gestione Tavoli')); ?></h1>
        <p class="rbf-admin-intro"><?php echo esc_html(rbf_translate_string('Organizza aree, tavoli e gruppi unibili con una vista coerente con il calendario.')); ?></p>

        <nav class="nav-tab-wrapper rbf-admin-tabs" role="tablist">
            <a href="#areas" class="nav-tab nav-tab-active rbf-tab-link" data-tab-target="areas" role="tab" aria-selected="true" aria-controls="areas"><?php echo esc_html(rbf_translate_string('Aree')); ?></a>
            <a href="#tables" class="nav-tab rbf-tab-link" data-tab-target="tables" role="tab" aria-selected="false" aria-controls="tables"><?php echo esc_html(rbf_translate_string('Tavoli')); ?></a>
            <a href="#groups" class="nav-tab rbf-tab-link" data-tab-target="groups" role="tab" aria-selected="false" aria-controls="groups"><?php echo esc_html(rbf_translate_string('Gruppi Unibili')); ?></a>
            <a href="#overview" class="nav-tab rbf-tab-link" data-tab-target="overview" role="tab" aria-selected="false" aria-controls="overview"><?php echo esc_html(rbf_translate_string('Panoramica')); ?></a>
        </nav>

        <section id="areas" class="rbf-tab-panel is-active" data-tab-panel="areas" role="tabpanel" aria-hidden="false">
            <div class="rbf-admin-grid rbf-admin-grid--cols-2">
                <div class="rbf-admin-card">
                    <h2><?php echo esc_html(rbf_translate_string('Nuova Area')); ?></h2>
                    <form method="post" class="rbf-admin-form">
                        <?php wp_nonce_field('rbf_table_management', 'rbf_nonce'); ?>
                        <input type="hidden" name="action" value="add_area">
                        <div class="rbf-form-group">
                            <label for="area_name" class="rbf-form-label"><?php echo esc_html(rbf_translate_string('Nome Area')); ?></label>
                            <input type="text" id="area_name" name="area_name" class="rbf-field" required placeholder="<?php echo esc_attr(rbf_translate_string('Es. Sala Principale')); ?>">
                        </div>
                        <div class="rbf-form-group">
                            <label for="area_description" class="rbf-form-label"><?php echo esc_html(rbf_translate_string('Descrizione')); ?></label>
                            <textarea id="area_description" name="area_description" class="rbf-field rbf-field--textarea" rows="4" placeholder="<?php echo esc_attr(rbf_translate_string('Note interne o dettagli della zona.')); ?>"></textarea>
                        </div>
                        <div class="rbf-form-actions">
                            <button type="submit" class="button button-primary"><?php echo esc_html(rbf_translate_string('Aggiungi Area')); ?></button>
                        </div>
                    </form>
                </div>

                <div class="rbf-admin-card">
                    <h2><?php echo esc_html(rbf_translate_string('Aree Configurate')); ?></h2>
                    <?php if (!empty($areas)) : ?>
                        <div class="rbf-table-wrapper">
                            <table class="widefat striped rbf-data-table">
                                <thead>
                                    <tr>
                                        <th><?php echo esc_html(rbf_translate_string('Nome')); ?></th>
                                        <th><?php echo esc_html(rbf_translate_string('Descrizione')); ?></th>
                                        <th><?php echo esc_html(rbf_translate_string('Tavoli')); ?></th>
                                        <th><?php echo esc_html(rbf_translate_string('Creata')); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($areas as $area) : ?>
                                        <?php $area_tables = rbf_get_tables_by_area($area->id); ?>
                                        <tr>
                                            <td><strong><?php echo esc_html($area->name); ?></strong></td>
                                            <td><?php echo esc_html($area->description ?: '-'); ?></td>
                                            <td><?php echo esc_html(count($area_tables)); ?></td>
                                            <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($area->created_at))); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else : ?>
                        <p class="rbf-empty-state"><?php echo esc_html(rbf_translate_string('Nessuna area configurata.')); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section id="tables" class="rbf-tab-panel" data-tab-panel="tables" role="tabpanel" aria-hidden="true" hidden>
            <div class="rbf-admin-grid rbf-admin-grid--cols-2">
                <div class="rbf-admin-card">
                    <h2><?php echo esc_html(rbf_translate_string('Nuovo Tavolo')); ?></h2>
                    <form method="post" class="rbf-admin-form">
                        <?php wp_nonce_field('rbf_table_management', 'rbf_nonce'); ?>
                        <input type="hidden" name="action" value="add_table">
                        <div class="rbf-form-group">
                            <label for="table_area_id" class="rbf-form-label"><?php echo esc_html(rbf_translate_string('Area')); ?></label>
                            <select id="table_area_id" name="table_area_id" class="rbf-field" required>
                                <option value=""><?php echo esc_html(rbf_translate_string('Seleziona Area')); ?></option>
                                <?php foreach ($areas as $area) : ?>
                                    <option value="<?php echo esc_attr($area->id); ?>"><?php echo esc_html($area->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="rbf-form-group">
                            <label for="table_name" class="rbf-form-label"><?php echo esc_html(rbf_translate_string('Nome Tavolo')); ?></label>
                            <input type="text" id="table_name" name="table_name" class="rbf-field" required placeholder="<?php echo esc_attr(rbf_translate_string('Es. Tavolo 12')); ?>">
                        </div>
                        <div class="rbf-form-group">
                            <label for="table_capacity" class="rbf-form-label"><?php echo esc_html(rbf_translate_string('Capacità Standard')); ?></label>
                            <input type="number" id="table_capacity" name="table_capacity" class="rbf-field" min="1" max="20" value="4" required>
                        </div>
                        <div class="rbf-form-group">
                            <label for="table_min_capacity" class="rbf-form-label"><?php echo esc_html(rbf_translate_string('Capacità Minima')); ?></label>
                            <input type="number" id="table_min_capacity" name="table_min_capacity" class="rbf-field" min="1" placeholder="<?php echo esc_attr(rbf_translate_string('Automatico')); ?>">
                        </div>
                        <div class="rbf-form-group">
                            <label for="table_max_capacity" class="rbf-form-label"><?php echo esc_html(rbf_translate_string('Capacità Massima')); ?></label>
                            <input type="number" id="table_max_capacity" name="table_max_capacity" class="rbf-field" min="1" placeholder="<?php echo esc_attr(rbf_translate_string('Automatico')); ?>">
                        </div>
                        <div class="rbf-form-actions">
                            <button type="submit" class="button button-primary"><?php echo esc_html(rbf_translate_string('Aggiungi Tavolo')); ?></button>
                        </div>
                    </form>
                </div>

                <div class="rbf-admin-card">
                    <h2><?php echo esc_html(rbf_translate_string('Tavoli Esistenti')); ?></h2>
                    <?php if (!empty($all_tables)) : ?>
                        <div class="rbf-table-wrapper">
                            <table class="widefat striped rbf-data-table">
                                <thead>
                                    <tr>
                                        <th><?php echo esc_html(rbf_translate_string('Nome')); ?></th>
                                        <th><?php echo esc_html(rbf_translate_string('Area')); ?></th>
                                        <th><?php echo esc_html(rbf_translate_string('Capacità')); ?></th>
                                        <th><?php echo esc_html(rbf_translate_string('Range')); ?></th>
                                        <th><?php echo esc_html(rbf_translate_string('Stato')); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_tables as $table) : ?>
                                        <tr>
                                            <td><strong><?php echo esc_html($table->name); ?></strong></td>
                                            <td><?php echo esc_html($table->area_name); ?></td>
                                            <td><?php echo esc_html($table->capacity); ?> <?php echo esc_html(rbf_translate_string('persone')); ?></td>
                                            <td><?php echo esc_html($table->min_capacity . ' - ' . $table->max_capacity); ?> <?php echo esc_html(rbf_translate_string('persone')); ?></td>
                                            <td>
                                                <?php if ($table->is_active) : ?>
                                                    <span class="rbf-status-pill rbf-status-pill--success"><?php echo esc_html(rbf_translate_string('Attivo')); ?></span>
                                                <?php else : ?>
                                                    <span class="rbf-status-pill rbf-status-pill--muted"><?php echo esc_html(rbf_translate_string('Inattivo')); ?></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else : ?>
                        <p class="rbf-empty-state"><?php echo esc_html(rbf_translate_string('Nessun tavolo configurato.')); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section id="groups" class="rbf-tab-panel" data-tab-panel="groups" role="tabpanel" aria-hidden="true" hidden>
            <div class="rbf-admin-grid rbf-admin-grid--cols-2">
                <div class="rbf-admin-card">
                    <h2><?php echo esc_html(rbf_translate_string('Nuovo Gruppo di Tavoli')); ?></h2>
                    <form method="post" class="rbf-admin-form" id="rbf-add-group-form">
                        <?php wp_nonce_field('rbf_table_management', 'rbf_nonce'); ?>
                        <input type="hidden" name="action" value="add_group">
                        <div class="rbf-form-group">
                            <label for="group_area_id" class="rbf-form-label"><?php echo esc_html(rbf_translate_string('Area')); ?></label>
                            <select id="group_area_id" name="group_area_id" class="rbf-field" required>
                                <option value=""><?php echo esc_html(rbf_translate_string('Seleziona Area')); ?></option>
                                <?php foreach ($areas as $area) : ?>
                                    <option value="<?php echo esc_attr($area->id); ?>"><?php echo esc_html($area->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="rbf-form-group">
                            <label for="group_name" class="rbf-form-label"><?php echo esc_html(rbf_translate_string('Nome Gruppo')); ?></label>
                            <input type="text" id="group_name" name="group_name" class="rbf-field" required placeholder="<?php echo esc_attr(rbf_translate_string('Es. Tavoli Unibili Sala A')); ?>">
                        </div>
                        <div class="rbf-form-group">
                            <label for="group_max_capacity" class="rbf-form-label"><?php echo esc_html(rbf_translate_string('Capacità Massima Combinata')); ?></label>
                            <input type="number" id="group_max_capacity" name="group_max_capacity" class="rbf-field" min="1" value="16">
                        </div>
                        <div class="rbf-form-group">
                            <span class="rbf-form-label"><?php echo esc_html(rbf_translate_string('Tavoli nel Gruppo')); ?></span>
                            <div id="group_tables_selection" class="rbf-card-collection"></div>
                        </div>
                        <div class="rbf-form-actions">
                            <button type="submit" class="button button-primary"><?php echo esc_html(rbf_translate_string('Aggiungi Gruppo')); ?></button>
                        </div>
                    </form>
                </div>

                <div class="rbf-admin-card">
                    <h2><?php echo esc_html(rbf_translate_string('Gruppi Configurati')); ?></h2>
                    <?php if (!empty($all_groups)) : ?>
                        <div class="rbf-table-wrapper">
                            <table class="widefat striped rbf-data-table">
                                <thead>
                                    <tr>
                                        <th><?php echo esc_html(rbf_translate_string('Nome')); ?></th>
                                        <th><?php echo esc_html(rbf_translate_string('Area')); ?></th>
                                        <th><?php echo esc_html(rbf_translate_string('Tavoli')); ?></th>
                                        <th><?php echo esc_html(rbf_translate_string('Capacità Max')); ?></th>
                                        <th><?php echo esc_html(rbf_translate_string('Stato')); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_groups as $group) : ?>
                                        <tr>
                                            <td><strong><?php echo esc_html($group->name); ?></strong></td>
                                            <td><?php echo esc_html($group->area_name); ?></td>
                                            <td><?php echo esc_html(implode(', ', array_map(static function ($table) { return $table->name; }, $group->tables))); ?></td>
                                            <td><?php echo esc_html($group->max_combined_capacity); ?> <?php echo esc_html(rbf_translate_string('persone')); ?></td>
                                            <td>
                                                <?php if ($group->is_active) : ?>
                                                    <span class="rbf-status-pill rbf-status-pill--success"><?php echo esc_html(rbf_translate_string('Attivo')); ?></span>
                                                <?php else : ?>
                                                    <span class="rbf-status-pill rbf-status-pill--muted"><?php echo esc_html(rbf_translate_string('Inattivo')); ?></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else : ?>
                        <p class="rbf-empty-state"><?php echo esc_html(rbf_translate_string('Nessun gruppo configurato.')); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section id="overview" class="rbf-tab-panel" data-tab-panel="overview" role="tabpanel" aria-hidden="true" hidden>
            <div class="rbf-admin-grid rbf-admin-grid--cols-4 rbf-admin-metrics">
                <div class="rbf-metric-card">
                    <p class="rbf-metric-card__label"><?php echo esc_html(rbf_translate_string('Aree Totali')); ?></p>
                    <p class="rbf-metric-card__value"><?php echo esc_html(count($areas)); ?></p>
                </div>
                <div class="rbf-metric-card">
                    <p class="rbf-metric-card__label"><?php echo esc_html(rbf_translate_string('Tavoli Totali')); ?></p>
                    <p class="rbf-metric-card__value"><?php echo esc_html(count($all_tables)); ?></p>
                </div>
                <div class="rbf-metric-card">
                    <p class="rbf-metric-card__label"><?php echo esc_html(rbf_translate_string('Gruppi Unibili')); ?></p>
                    <p class="rbf-metric-card__value"><?php echo esc_html(count($all_groups)); ?></p>
                </div>
                <div class="rbf-metric-card">
                    <p class="rbf-metric-card__label"><?php echo esc_html(rbf_translate_string('Capacità Totale Disponibile')); ?></p>
                    <p class="rbf-metric-card__value"><?php echo esc_html($total_capacity); ?></p>
                </div>
            </div>
            <div class="rbf-admin-card rbf-admin-card--soft">
                <h2><?php echo esc_html(rbf_translate_string('Suggerimenti Operativi')); ?></h2>
                <div class="rbf-admin-grid rbf-admin-grid--cols-2">
                    <div class="rbf-info-bubble">
                        <h3><?php echo esc_html(rbf_translate_string('Bilanciare le Aree')); ?></h3>
                        <p><?php echo esc_html(rbf_translate_string('Verifica che ogni area abbia tavoli dedicati alle diverse dimensioni di gruppo per distribuire la capacità.')); ?></p>
                    </div>
                    <div class="rbf-info-bubble">
                        <h3><?php echo esc_html(rbf_translate_string('Mantieni Aggiornati i Gruppi')); ?></h3>
                        <p><?php echo esc_html(rbf_translate_string('Aggiorna i gruppi unibili quando aggiungi nuovi tavoli per mantenere attiva la logica di assegnazione.')); ?></p>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <script>
    (function() {
        const messages = <?php echo wp_json_encode($group_selection_texts); ?>;
        const tables = <?php echo wp_json_encode($all_tables); ?>;

        const selectionContainer = document.getElementById('group_tables_selection');
        const areaSelect = document.getElementById('group_area_id');

        function renderGroupTables() {
            if (!selectionContainer || !areaSelect) {
                return;
            }

            const areaId = areaSelect.value;

            if (!areaId) {
                selectionContainer.innerHTML = '<p class="rbf-empty-state rbf-empty-state--inline">' + messages.placeholder + '</p>';
                return;
            }

            const areaTables = tables.filter(function(table) {
                return String(table.area_id) === String(areaId);
            });

            if (areaTables.length === 0) {
                selectionContainer.innerHTML = '<p class="rbf-empty-state rbf-empty-state--inline">' + messages.empty + '</p>';
                return;
            }

            let html = '<div class="rbf-checkbox-grid">';
            areaTables.forEach(function(table) {
                html += '<label class="rbf-checkbox-card">';
                html += '<input type="checkbox" name="group_tables[]" value="' + table.id + '">';
                html += '<span class="rbf-checkbox-card__content">';
                html += '<span class="rbf-checkbox-card__title">' + table.name + '</span>';
                html += '<span class="rbf-checkbox-card__meta">' + table.capacity + ' ' + messages.peopleLabel + '</span>';
                html += '</span>';
                html += '</label>';
            });
            html += '</div>';
            html += '<p class="description">' + messages.hint + '</p>';

            selectionContainer.innerHTML = html;
        }

        if (areaSelect) {
            areaSelect.addEventListener('change', renderGroupTables);
            renderGroupTables();
        }
    })();
    </script>
    <?php
}
/**
 * Email Notifications page HTML
 */
function rbf_email_notifications_page_html() {
    if (!rbf_require_settings_capability()) {
        return;
    }

    $service = rbf_get_email_failover_service();
    $admin_notices = [];

    if (!empty($_POST['rbf_email_cleanup'])) {
        check_admin_referer('rbf_email_cleanup');

        $raw_post = function_exists('wp_unslash') ? wp_unslash($_POST) : $_POST;
        $custom_days = isset($raw_post['retention_days']) ? absint($raw_post['retention_days']) : 0;

        $cleanup_result = rbf_cleanup_email_notifications($custom_days > 0 ? $custom_days : null);

        if (!empty($cleanup_result['table_exists'])) {
            $deleted = (int) ($cleanup_result['deleted'] ?? 0);
            $retention_used = (int) ($cleanup_result['retention_days'] ?? 0);

            if ($retention_used <= 0) {
                if ($custom_days > 0) {
                    $retention_used = $custom_days;
                } elseif (function_exists('rbf_get_email_log_retention_days')) {
                    $retention_used = (int) rbf_get_email_log_retention_days();
                } elseif (defined('RBF_EMAIL_LOG_DEFAULT_RETENTION_DAYS')) {
                    $retention_used = (int) RBF_EMAIL_LOG_DEFAULT_RETENTION_DAYS;
                } else {
                    $retention_used = 90;
                }
            }

            $message = sprintf(
                rbf_translate_string('Pulizia completata: %d log rimossi (retention %d giorni).'),
                $deleted,
                $retention_used
            );

            if ($deleted === 0) {
                $message .= ' ' . rbf_translate_string('Nessun record soddisfaceva i criteri di eliminazione.');
            }

            $admin_notices[] = [
                'type'    => 'success',
                'message' => $message,
            ];

            if (function_exists('rbf_schedule_email_log_cleanup')) {
                rbf_schedule_email_log_cleanup();
            }
        } else {
            $admin_notices[] = [
                'type'    => 'error',
                'message' => rbf_translate_string('Impossibile pulire i log email: tabella non disponibile.'),
            ];
        }
    }

    $default_retention_days = function_exists('rbf_get_email_log_retention_days')
        ? (int) rbf_get_email_log_retention_days()
        : (defined('RBF_EMAIL_LOG_DEFAULT_RETENTION_DAYS') ? (int) RBF_EMAIL_LOG_DEFAULT_RETENTION_DAYS : 90);

    $next_cleanup_timestamp = function_exists('wp_next_scheduled')
        ? wp_next_scheduled('rbf_cleanup_email_notifications_event')
        : false;

    $next_cleanup_readable = '';
    if ($next_cleanup_timestamp) {
        if (function_exists('wp_date')) {
            $next_cleanup_readable = wp_date(get_option('date_format') . ' ' . get_option('time_format'), $next_cleanup_timestamp);
        } else {
            $next_cleanup_readable = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next_cleanup_timestamp);
        }
    }

    // Handle actions
    if (isset($_GET['action']) && isset($_GET['log_id']) && wp_verify_nonce($_GET['_wpnonce'], 'rbf_email_action')) {
        $log_id = intval($_GET['log_id']);
        $action = sanitize_text_field($_GET['action']);

        if ($action === 'retry') {
            // Get log entry details and retry notification
            global $wpdb;
            $table_name = $wpdb->prefix . 'rbf_email_notifications';
            $log_entry = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE id = %d",
                $log_id
            ));
            
            if ($log_entry && $log_entry->status === 'failed') {
                // Decode metadata to get original notification data
                $metadata = json_decode($log_entry->metadata, true);
                if ($metadata) {
                    $result = $service->send_notification($metadata);
                    if ($result['success']) {
                        echo '<div class="notice notice-success"><p>Notifica reinviata con successo!</p></div>';
                    } else {
                        echo '<div class="notice notice-error"><p>Errore nel reinvio: ' . esc_html($result['error']) . '</p></div>';
                    }
                }
            }
        }
    }
    
    // Get filter parameters
    $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : '';
    $filter_type = isset($_GET['filter_type']) ? sanitize_text_field($_GET['filter_type']) : '';
    $filter_days = isset($_GET['filter_days']) ? intval($_GET['filter_days']) : 7;
    
    // Get notification logs
    $logs = $service->get_notification_logs(null, 100);
    
    // Filter logs
    if ($filter_status || $filter_type) {
        $logs = array_filter($logs, function($log) use ($filter_status, $filter_type) {
            $status_match = !$filter_status || $log->status === $filter_status;
            $type_match = !$filter_type || $log->notification_type === $filter_type;
            return $status_match && $type_match;
        });
    }
    
    // Get statistics
    $stats = $service->get_notification_stats($filter_days);
    
    // Process stats for display
    $stats_summary = [
        'total' => 0,
        'success' => 0,
        'failed' => 0,
        'fallback_success' => 0,
        'by_provider' => ['brevo' => 0, 'wp_mail' => 0],
        'success_rate' => 0,
        'fallback_rate' => 0
    ];
    
    foreach ($stats as $stat) {
        $stats_summary['total'] += $stat->count;
        $stats_summary[$stat->status] += $stat->count;
        $stats_summary['by_provider'][$stat->provider_used] += $stat->count;
    }
    
    if ($stats_summary['total'] > 0) {
        $total_success = $stats_summary['success'] + $stats_summary['fallback_success'];
        $stats_summary['success_rate'] = ($total_success / $stats_summary['total']) * 100;
        $stats_summary['fallback_rate'] = ($stats_summary['fallback_success'] / $stats_summary['total']) * 100;
    }
    foreach ($admin_notices as $notice) {
        printf(
            '<div class="notice notice-%1$s"><p>%2$s</p></div>',
            esc_attr($notice['type']),
            esc_html($notice['message'])
        );
    }

    ?>

    <div class="rbf-admin-wrap rbf-admin-wrap--wide">
        <h1><?php echo esc_html(rbf_translate_string('Sistema Email Failover')); ?></h1>

        <div class="rbf-admin-banner">
            <h2 class="rbf-admin-banner__title"><?php echo esc_html(rbf_translate_string('Gestione registro notifiche')); ?></h2>
            <p class="rbf-admin-banner__text">
                <?php echo esc_html(sprintf(rbf_translate_string('I log vengono conservati per %d giorni.'), $default_retention_days)); ?>
            </p>
            <p class="rbf-admin-banner__text">
                <?php if ($next_cleanup_timestamp) : ?>
                    <?php echo esc_html(sprintf(rbf_translate_string('Prossima pulizia automatica: %s'), $next_cleanup_readable)); ?>
                <?php else : ?>
                    <span class="rbf-text-danger">
                        <?php echo esc_html(rbf_translate_string('Pulizia automatica non ancora pianificata. Verrà programmata automaticamente al prossimo cron.')); ?>
                    </span>
                <?php endif; ?>
            </p>
            <form method="post" class="rbf-form-inline">
                <?php wp_nonce_field('rbf_email_cleanup'); ?>
                <input type="hidden" name="rbf_email_cleanup" value="1">
                <div class="rbf-form-inline__group">
                    <label for="rbf-retention-days" class="rbf-form-label">
                        <?php echo esc_html(rbf_translate_string('Giorni da conservare')); ?>
                    </label>
                    <input type="number" id="rbf-retention-days" name="retention_days" min="1" class="small-text" value="<?php echo esc_attr($default_retention_days); ?>">
                </div>
                <button type="submit" class="button button-secondary rbf-button--compact">
                    <?php echo esc_html(rbf_translate_string('Esegui pulizia manuale')); ?>
                </button>
            </form>
        </div>

        <?php
        $brevo_percentage = $stats_summary['total'] > 0 ? ($stats_summary['by_provider']['brevo'] / $stats_summary['total']) * 100 : 0;
        $fallback_percentage = $stats_summary['total'] > 0 ? ($stats_summary['by_provider']['wp_mail'] / $stats_summary['total']) * 100 : 0;

        $health_class = 'rbf-health-indicator--danger';
        $health_icon = '✗';

        if ($stats_summary['success_rate'] >= 90) {
            $health_class = 'rbf-health-indicator--success';
            $health_icon = '✓';
        } elseif ($stats_summary['success_rate'] >= 75) {
            $health_class = 'rbf-health-indicator--warning';
            $health_icon = '!';
        }
        ?>

        <!-- Statistics Dashboard -->
        <div class="rbf-stat-grid">
            <div class="rbf-stat-card rbf-stat-card--primary">
                <h3 class="rbf-stat-card__title"><?php echo esc_html(rbf_translate_string('Notifiche Totali')); ?></h3>
                <div class="rbf-stat-card__value"><?php echo esc_html($stats_summary['total']); ?></div>
                <div class="rbf-stat-card__hint"><?php echo esc_html(sprintf(rbf_translate_string('Ultimi %d giorni'), $filter_days)); ?></div>
            </div>
            
            <div class="rbf-stat-card rbf-stat-card--success">
                <h3 class="rbf-stat-card__title"><?php echo esc_html(rbf_translate_string('Tasso di Successo')); ?></h3>
                <div class="rbf-stat-card__value"><?php echo esc_html(number_format($stats_summary['success_rate'], 1)); ?>%</div>
                <div class="rbf-stat-card__hint">
                    <?php echo esc_html($stats_summary['success'] + $stats_summary['fallback_success']); ?>
                    / <?php echo esc_html($stats_summary['total']); ?> <?php echo esc_html(rbf_translate_string('inviate')); ?>
                </div>
            </div>
            
            <div class="rbf-stat-card rbf-stat-card--warning">
                <h3 class="rbf-stat-card__title"><?php echo esc_html(rbf_translate_string('Uso Fallback')); ?></h3>
                <div class="rbf-stat-card__value"><?php echo esc_html(number_format($stats_summary['fallback_rate'], 1)); ?>%</div>
                <div class="rbf-stat-card__hint">
                    <?php echo esc_html($stats_summary['fallback_success']); ?> <?php echo esc_html(rbf_translate_string('Notifiche fallback')); ?>
                </div>
            </div>
            
            <div class="rbf-stat-card rbf-stat-card--danger">
                <h3 class="rbf-stat-card__title"><?php echo esc_html(rbf_translate_string('Notifiche Fallite')); ?></h3>
                <div class="rbf-stat-card__value"><?php echo esc_html($stats_summary['failed']); ?></div>
                <div class="rbf-stat-card__hint"><?php echo esc_html(rbf_translate_string('Richiedono attenzione')); ?></div>
            </div>
        </div>

        <!-- Provider Usage Chart -->
        <div class="rbf-admin-card rbf-admin-card--spaced rbf-notification-breakdown">
            <h3><?php echo esc_html(rbf_translate_string('Utilizzo Provider Email')); ?></h3>
            <div class="rbf-notification-breakdown__content">
                <div class="rbf-notification-breakdown__providers">
                    <div class="rbf-notification-provider rbf-notification-provider--primary" style="--rbf-progress: <?php echo esc_attr($brevo_percentage); ?>%;">
                        <strong>Brevo (Primario)</strong>
                        <span class="rbf-notification-provider__value"><?php echo esc_html($stats_summary['by_provider']['brevo']); ?> <?php echo esc_html(rbf_translate_string('Notifiche')); ?></span>
                        <div class="rbf-progress"></div>
                    </div>
                    <div class="rbf-notification-provider rbf-notification-provider--fallback" style="--rbf-progress: <?php echo esc_attr($fallback_percentage); ?>%;">
                        <strong>wp_mail (Fallback)</strong>
                        <span class="rbf-notification-provider__value"><?php echo esc_html($stats_summary['by_provider']['wp_mail']); ?> <?php echo esc_html(rbf_translate_string('Notifiche')); ?></span>
                        <div class="rbf-progress rbf-progress--fallback"></div>
                    </div>
                </div>
                <div class="rbf-notification-breakdown__health">
                    <div class="rbf-text-muted rbf-spacing-bottom-sm"><?php echo esc_html(rbf_translate_string('Sistema funzionante')); ?></div>
                    <div class="rbf-health-indicator <?php echo esc_attr($health_class); ?>"><?php echo esc_html($health_icon); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="rbf-admin-card rbf-admin-card--spaced">
            <form method="get" class="rbf-form-inline">
                <input type="hidden" name="page" value="rbf_email_notifications">
                <div class="rbf-form-inline__group">
                    <label class="rbf-form-label" for="rbf-filter-status"><?php echo esc_html(rbf_translate_string('Stato')); ?></label>
                    <select id="rbf-filter-status" name="filter_status">
                        <option value=""><?php echo esc_html(rbf_translate_string('Tutti gli stati')); ?></option>
                        <option value="success" <?php selected($filter_status, 'success'); ?>><?php echo esc_html(rbf_translate_string('Successo')); ?></option>
                        <option value="fallback_success" <?php selected($filter_status, 'fallback_success'); ?>><?php echo esc_html(rbf_translate_string('Fallback Successo')); ?></option>
                        <option value="failed" <?php selected($filter_status, 'failed'); ?>><?php echo esc_html(rbf_translate_string('Fallito')); ?></option>
                        <option value="pending" <?php selected($filter_status, 'pending'); ?>><?php echo esc_html(rbf_translate_string('In Attesa')); ?></option>
                    </select>
                </div>
                <div class="rbf-form-inline__group">
                    <label class="rbf-form-label" for="rbf-filter-type"><?php echo esc_html(rbf_translate_string('Tipo')); ?></label>
                    <select id="rbf-filter-type" name="filter_type">
                        <option value=""><?php echo esc_html(rbf_translate_string('Tutti i tipi')); ?></option>
                        <option value="admin_notification" <?php selected($filter_type, 'admin_notification'); ?>><?php echo esc_html(rbf_translate_string('Notifica Admin')); ?></option>
                        <option value="customer_notification" <?php selected($filter_type, 'customer_notification'); ?>><?php echo esc_html(rbf_translate_string('Notifica Cliente')); ?></option>
                    </select>
                </div>
                <div class="rbf-form-inline__group">
                    <label class="rbf-form-label" for="rbf-filter-days"><?php echo esc_html(rbf_translate_string('Periodo')); ?></label>
                    <select id="rbf-filter-days" name="filter_days">
                        <option value="1" <?php selected($filter_days, 1); ?>><?php echo esc_html(rbf_translate_string('Ultimo giorno')); ?></option>
                        <option value="7" <?php selected($filter_days, 7); ?>><?php echo esc_html(rbf_translate_string('Ultimi 7 giorni')); ?></option>
                        <option value="30" <?php selected($filter_days, 30); ?>><?php echo esc_html(rbf_translate_string('Ultimi 30 giorni')); ?></option>
                    </select>
                </div>
                <div class="rbf-form-inline__actions">
                    <button type="submit" class="button button-primary rbf-button--compact"><?php echo esc_html(rbf_translate_string('Filtra')); ?></button>
                    <a href="<?php echo admin_url('admin.php?page=rbf_email_notifications'); ?>" class="button rbf-button--compact"><?php echo esc_html(rbf_translate_string('Reset')); ?></a>
                </div>
            </form>
        </div>
        
        <!-- Notification Logs -->
        <div class="rbf-admin-card">
            <h3><?php echo esc_html(rbf_translate_string('Log Notifiche Email')); ?></h3>

            <?php if (!empty($logs)): ?>
                <div class="rbf-table-responsive">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th class="rbf-column--id"><?php echo esc_html(rbf_translate_string('ID')); ?></th>
                                <th class="rbf-column--booking"><?php echo esc_html(rbf_translate_string('Prenotazione')); ?></th>
                                <th class="rbf-column--type"><?php echo esc_html(rbf_translate_string('Tipo')); ?></th>
                                <th><?php echo esc_html(rbf_translate_string('Destinatario')); ?></th>
                                <th class="rbf-column--provider"><?php echo esc_html(rbf_translate_string('Provider')); ?></th>
                                <th class="rbf-column--status"><?php echo esc_html(rbf_translate_string('Stato')); ?></th>
                                <th class="rbf-column--attempts"><?php echo esc_html(rbf_translate_string('Tentativi')); ?></th>
                                <th class="rbf-column--datetime"><?php echo esc_html(rbf_translate_string('Data/Ora')); ?></th>
                                <th class="rbf-column--actions"><?php echo esc_html(rbf_translate_string('Azioni')); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?php echo esc_html($log->id); ?></td>
                                    <td>
                                        <?php if ($log->booking_id): ?>
                                            <a href="<?php echo admin_url('post.php?post=' . $log->booking_id . '&action=edit'); ?>">
                                                #<?php echo esc_html($log->booking_id); ?>
                                            </a>
                                        <?php else: ?>
                                            <em>N/A</em>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $type_labels = [
                                            'admin_notification' => 'Admin',
                                            'customer_notification' => 'Cliente'
                                        ];
                                        echo esc_html($type_labels[$log->notification_type] ?? $log->notification_type);
                                        ?>
                                    </td>
                                    <td class="rbf-text-break">
                                        <?php echo esc_html($log->recipient_email); ?>
                                    </td>
                                    <td>
                                        <?php
                                        $provider_class = $log->provider_used === 'brevo'
                                            ? 'rbf-badge--provider-primary'
                                            : 'rbf-badge--provider-fallback';
                                        ?>
                                        <span class="rbf-badge <?php echo esc_attr($provider_class); ?>">
                                            <?php echo esc_html(ucfirst($log->provider_used)); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $status_variants = [
                                            'success' => [
                                                'label' => 'Successo',
                                                'class' => 'rbf-badge--success',
                                            ],
                                            'fallback_success' => [
                                                'label' => 'Fallback OK',
                                                'class' => 'rbf-badge--fallback',
                                            ],
                                            'failed' => [
                                                'label' => 'Fallito',
                                                'class' => 'rbf-badge--error',
                                            ],
                                            'pending' => [
                                                'label' => 'In Attesa',
                                                'class' => 'rbf-badge--pending',
                                            ],
                                        ];
                                        $variant = $status_variants[$log->status] ?? [
                                            'label' => $log->status,
                                            'class' => '',
                                        ];
                                        ?>
                                        <span class="rbf-badge <?php echo esc_attr($variant['class']); ?>">
                                            <?php echo esc_html($variant['label']); ?>
                                        </span>
                                    </td>
                                    <td class="rbf-text-center">
                                        <?php echo esc_html($log->attempt_number); ?>
                                    </td>
                                    <td>
                                        <div class="rbf-text-small">
                                            <?php echo esc_html(date('d/m/Y H:i', strtotime($log->attempted_at))); ?>
                                        </div>
                                        <?php if ($log->completed_at && $log->completed_at !== $log->attempted_at): ?>
                                            <div class="rbf-text-xs rbf-text-muted">
                                                <?php echo esc_html(rbf_translate_string('Completato')); ?>: <?php echo esc_html(date('H:i', strtotime($log->completed_at))); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="rbf-action-group">
                                            <?php if ($log->status === 'failed'): ?>
                                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=rbf_email_notifications&action=retry&log_id=' . $log->id), 'rbf_email_action'); ?>"
                                                   class="button button-small"
                                                   title="<?php echo esc_attr(rbf_translate_string('Riprova invio')); ?>"
                                                   onclick="return confirm('<?php echo esc_js(rbf_translate_string('Riprovare l\'invio di questa notifica?')); ?>')">
                                                    ↻
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($log->error_message): ?>
                                                <button type="button" 
                                                        class="button button-small" 
                                                        onclick="alert('<?php echo esc_js($log->error_message); ?>')"
                                                        title="<?php echo esc_attr(rbf_translate_string('Visualizza errore')); ?>">
                                                    !
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="rbf-empty-state">
                    <?php echo esc_html(rbf_translate_string('Nessuna notifica trovata con i filtri selezionati.')); ?>
                </p>
            <?php endif; ?>
        </div>

        <!-- Configuration Help -->
        <div class="rbf-admin-card rbf-admin-card--muted rbf-admin-card--spaced">
            <h3><?php echo esc_html(rbf_translate_string('Configurazione Sistema Failover')); ?></h3>

            <div class="rbf-admin-grid rbf-admin-grid--feature">
                <div>
                    <h4><?php echo esc_html(rbf_translate_string('Provider Primario (Brevo)')); ?></h4>
                    <ul class="rbf-admin-list">
                        <li><?php echo esc_html(rbf_translate_string('Automazioni clienti (liste e eventi)')); ?></li>
                        <li><?php echo esc_html(rbf_translate_string('Email transazionali admin')); ?></li>
                        <li><?php echo esc_html(rbf_translate_string('Supporto multilingua')); ?></li>
                        <li><?php echo esc_html(rbf_translate_string('Analytics avanzate')); ?></li>
                    </ul>
                </div>

                <div>
                    <h4><?php echo esc_html(rbf_translate_string('Provider Fallback (wp_mail)')); ?></h4>
                    <ul class="rbf-admin-list">
                        <li><?php echo esc_html(rbf_translate_string('Solo notifiche admin')); ?></li>
                        <li><?php echo esc_html(rbf_translate_string('Attivazione automatica su errore Brevo')); ?></li>
                        <li><?php echo esc_html(rbf_translate_string('Configurazione SMTP WordPress')); ?></li>
                        <li><?php echo esc_html(rbf_translate_string('Backup affidabile')); ?></li>
                    </ul>
                </div>
            </div>

            <div class="rbf-admin-card rbf-admin-card--soft rbf-admin-card--stacked">
                <strong><?php echo esc_html(rbf_translate_string('Stato Configurazione Attuale:')); ?></strong>
                <div class="rbf-spacing-top-sm">
                    <?php
                    $options = rbf_get_settings();
                    $brevo_configured = !empty($options['brevo_api']);
                    $emails_configured = !empty($options['notification_email']) || !empty($options['webmaster_email']);
                    ?>

                    <div class="rbf-status-indicator">
                        <span class="rbf-status-icon <?php echo $brevo_configured ? 'rbf-status-icon--success' : 'rbf-status-icon--error'; ?>">
                            <?php echo $brevo_configured ? '✓' : '✗'; ?>
                        </span>
                        <span class="rbf-status-text">
                            <?php echo esc_html(rbf_translate_string('Brevo API configurata')); ?>
                            <?php if (!$brevo_configured): ?>
                                - <a href="<?php echo admin_url('admin.php?page=rbf_settings'); ?>"><?php echo esc_html(rbf_translate_string('Configura ora')); ?></a>
                            <?php endif; ?>
                        </span>
                    </div>

                    <div class="rbf-status-indicator">
                        <span class="rbf-status-icon <?php echo $emails_configured ? 'rbf-status-icon--success' : 'rbf-status-icon--error'; ?>">
                            <?php echo $emails_configured ? '✓' : '✗'; ?>
                        </span>
                        <span class="rbf-status-text">
                            <?php echo esc_html(rbf_translate_string('Email amministratori configurate')); ?>
                            <?php if (!$emails_configured): ?>
                                - <a href="<?php echo admin_url('admin.php?page=rbf_settings'); ?>"><?php echo esc_html(rbf_translate_string('Configura ora')); ?></a>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php
}

/**
 * AJAX handler for moving bookings in weekly staff view
 */
add_action('wp_ajax_rbf_move_booking', 'rbf_move_booking_callback');
function rbf_move_booking_callback() {
    check_ajax_referer('rbf_weekly_staff_nonce', '_ajax_nonce');

    if (!rbf_user_can_manage_bookings()) {
        wp_send_json_error('Permessi insufficienti', 403);
    }

    $sanitized = rbf_sanitize_input_fields($_POST, [
        'booking_id' => 'int',
        'new_date' => 'text',
        'new_time' => 'text'
    ]);
    
    $booking_id = $sanitized['booking_id'];
    $new_date = $sanitized['new_date'];
    $new_time = $sanitized['new_time'];
    
    if (!$booking_id || !$new_date || !$new_time) {
        wp_send_json_error('Parametri non validi');
    }
    
    $booking = get_post($booking_id);
    if (!$booking || $booking->post_type !== 'rbf_booking') {
        wp_send_json_error('Prenotazione non trovata');
    }
    
    // Get current booking data
    $old_date = get_post_meta($booking_id, 'rbf_data', true);
    $old_time = get_post_meta($booking_id, 'rbf_time', true);
    $meal = get_post_meta($booking_id, 'rbf_meal', true) ?: get_post_meta($booking_id, 'rbf_orario', true);
    $people = intval(get_post_meta($booking_id, 'rbf_persone', true));
    
    // Validate new date and time format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $new_date) || !preg_match('/^\d{2}:\d{2}$/', $new_time)) {
        wp_send_json_error('Formato data o orario non valido');
    }
    
    // Check availability for new slot
    $availability_check = rbf_check_slot_availability(
        $new_date,
        $meal,
        $new_time,
        $people,
        ($booking && $booking->post_type === 'rbf_booking') ? $booking_id : null
    );
    if (!$availability_check) {
        wp_send_json_error('Nuovo slot non disponibile');
    }

    $buffer_validation = rbf_validate_buffer_time($new_date, $new_time, $meal, $people, $booking_id);
    if ($buffer_validation !== true) {
        wp_send_json_error($buffer_validation['message']);
    }

    // Release old slot capacity and ensure the ledger is consistent before proceeding
    $release_success = true;
    if ($old_date && $meal && $people) {
        $release_success = rbf_release_slot_capacity($old_date, $meal, $people);

        if (!$release_success) {
            if (function_exists('rbf_log')) {
                rbf_log('RBF Move Booking: rilascio capacità fallito per prenotazione ' . $booking_id . ' su ' . $old_date . ' - ' . $meal . '. Avvio sincronizzazione ledger.');
            }

            $synced = function_exists('rbf_sync_slot_version') ? rbf_sync_slot_version($old_date, $meal) : false;

            if ($synced) {
                $release_success = rbf_release_slot_capacity($old_date, $meal, $people);
            } else {
                if (function_exists('rbf_log')) {
                    rbf_log('RBF Move Booking: sincronizzazione ledger fallita per ' . $old_date . ' - ' . $meal . '.');
                }
            }
        }

        if (!$release_success) {
            if (function_exists('rbf_log')) {
                rbf_log('RBF Move Booking: impossibile rilasciare la capacità originale per prenotazione ' . $booking_id . ' su ' . $old_date . ' - ' . $meal . ' dopo i tentativi di ripristino.');
            }

            wp_send_json_error('Impossibile liberare la capacità dello slot originale. Aggiorna la disponibilità e riprova.');
        }
    }

    // Reserve new slot capacity and ensure it succeeds
    $reservation_success = rbf_reserve_slot_capacity($new_date, $meal, $people);

    if (!$reservation_success) {
        if ($old_date && $meal && $people) {
            rbf_reserve_slot_capacity($old_date, $meal, $people);
        }

        wp_send_json_error('Conflitto di capacità, riprova');
    }

    // Calculate new table assignment for the target slot
    $assignment = rbf_assign_tables_first_fit($people, $new_date, $new_time, $meal);

    if (!$assignment) {
        $release_new_success = rbf_release_slot_capacity($new_date, $meal, $people);

        if (!$release_new_success) {
            if (function_exists('rbf_log')) {
                rbf_log('RBF Move Booking: rilascio capacità fallito per il nuovo slot della prenotazione ' . $booking_id . ' su ' . $new_date . ' - ' . $meal . '. Avvio sincronizzazione ledger.');
            }

            $synced_new = function_exists('rbf_sync_slot_version') ? rbf_sync_slot_version($new_date, $meal) : false;

            if ($synced_new) {
                $release_new_success = rbf_release_slot_capacity($new_date, $meal, $people);
            } else {
                if (function_exists('rbf_log')) {
                    rbf_log('RBF Move Booking: sincronizzazione ledger fallita per il nuovo slot ' . $new_date . ' - ' . $meal . '.');
                }
            }
        }

        if (!$release_new_success && function_exists('rbf_log')) {
            rbf_log('RBF Move Booking: impossibile rilasciare la capacità del nuovo slot per prenotazione ' . $booking_id . ' su ' . $new_date . ' - ' . $meal . ' nonostante i tentativi di ripristino.');
        }

        if ($old_date && $meal && $people) {
            $restored = rbf_reserve_slot_capacity($old_date, $meal, $people);
            if (!$restored && function_exists('rbf_log')) {
                rbf_log('RBF Move Booking: impossibile ripristinare la capacità per la prenotazione ' . $booking_id . ' su ' . $old_date . ' ' . $meal);
            }
        }

        $error_message = rbf_translate_string('Nessun tavolo disponibile per l’orario selezionato');
        if (!$release_new_success) {
            $error_message .= ' ' . rbf_translate_string('La disponibilità potrebbe non essere aggiornata; verifica manualmente la capacità.');
        }

        wp_send_json_error($error_message);
    }

    rbf_save_table_assignment($booking_id, $assignment);

    update_post_meta($booking_id, 'rbf_table_assignment_type', $assignment['type']);
    update_post_meta($booking_id, 'rbf_assigned_tables', $assignment['total_capacity']);

    if ($assignment['type'] === 'joined' && isset($assignment['group_id'])) {
        update_post_meta($booking_id, 'rbf_table_group_id', $assignment['group_id']);
    } else {
        delete_post_meta($booking_id, 'rbf_table_group_id');
    }

    // Update booking data
    update_post_meta($booking_id, 'rbf_data', $new_date);
    update_post_meta($booking_id, 'rbf_time', $new_time);
    update_post_meta($booking_id, 'rbf_orario', $new_time);

    // Invalidate availability caches now that the move succeeded
    if ($old_date && $meal) {
        delete_transient('rbf_avail_' . $old_date . '_' . $meal);
    }
    delete_transient('rbf_avail_' . $new_date . '_' . $meal);

    if (function_exists('rbf_clear_calendar_cache')) {
        if ($old_date && $meal) {
            rbf_clear_calendar_cache($old_date, $meal);
        }
        rbf_clear_calendar_cache($new_date, $meal);
    }
    
    // Update post title
    $first_name = get_post_meta($booking_id, 'rbf_nome', true);
    $last_name = get_post_meta($booking_id, 'rbf_cognome', true);
    $new_title = ucfirst($meal) . " per {$first_name} {$last_name} - {$new_date} {$new_time}";
    
    wp_update_post([
        'ID' => $booking_id,
        'post_title' => $new_title
    ]);
    
    wp_send_json_success([
        'message' => 'Prenotazione spostata con successo',
        'booking_id' => $booking_id,
        'old_date' => $old_date,
        'old_time' => $old_time,
        'new_date' => $new_date,
        'new_time' => $new_time
    ]);
}

/**
 * AJAX handler for getting bookings for weekly staff view  
 */
add_action('wp_ajax_rbf_get_weekly_staff_bookings', 'rbf_get_weekly_staff_bookings_callback');
function rbf_get_weekly_staff_bookings_callback() {
    check_ajax_referer('rbf_weekly_staff_nonce', '_ajax_nonce');

    if (!rbf_user_can_manage_bookings()) {
        wp_send_json_error('Permessi insufficienti', 403);
    }

    $sanitized = rbf_sanitize_input_fields($_POST, [
        'start' => 'text',
        'end' => 'text'
    ]);
    
    $start = $sanitized['start'];
    $end = $sanitized['end'];

    $args = [
        'post_type' => 'rbf_booking',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'meta_query' => [[
            'key' => 'rbf_data',
            'value' => [$start, $end],
            'compare' => 'BETWEEN',
            'type' => 'DATE'
        ]]
    ];

    $bookings = get_posts($args);
    $events = [];
    foreach ($bookings as $booking) {
        $meta = get_post_meta($booking->ID);
        
        $date = $meta['rbf_data'][0] ?? '';
        $time = $meta['rbf_time'][0] ?? ($meta['rbf_orario'][0] ?? '');
        $people = $meta['rbf_persone'][0] ?? '';
        $first_name = $meta['rbf_nome'][0] ?? '';
        $last_name = $meta['rbf_cognome'][0] ?? '';
        $status = $meta['rbf_booking_status'][0] ?? 'confirmed';
        $meal = $meta['rbf_meal'][0] ?? ($meta['rbf_orario'][0] ?? '');
        
        $title = trim($first_name . ' ' . $last_name) . ' (' . $people . 'p)';
        
        // Color coding based on status
        $color = '#28a745'; // confirmed - green
        if ($status === 'cancelled') $color = '#dc3545'; // red
        if ($status === 'completed') $color = '#6c757d'; // gray
        
        $events[] = [
            'id' => $booking->ID,
            'title' => $title,
            'start' => $date . 'T' . $time,
            'backgroundColor' => $color,
            'borderColor' => $color,
            'className' => 'fc-status-' . $status,
            'extendedProps' => [
                'booking_id' => $booking->ID,
                'customer_name' => trim($first_name . ' ' . $last_name),
                'booking_date' => $date,
                'booking_time' => $time,
                'people' => $people,
                'status' => $status,
                'meal' => $meal
            ]
        ];
    }

    wp_send_json_success($events);
}

/**
 * Tracking Validation page HTML
 */
function rbf_tracking_validation_page_html() {
    if (!rbf_require_settings_capability()) {
        return;
    }

    // Load tracking validation functions
    if (!function_exists('rbf_validate_tracking_setup')) {
        require_once RBF_PLUGIN_DIR . 'includes/tracking-validation.php';
    }
    
    $validation_results = rbf_validate_tracking_setup();
    $options = rbf_get_settings();
    
    // Handle test tracking action
    $test_result = null;
    if (isset($_POST['test_tracking']) && wp_verify_nonce($_POST['_wpnonce'], 'rbf_tracking_test')) {
        $test_result = rbf_perform_tracking_test();
    }
    ?>

    <div class="rbf-admin-wrap rbf-admin-wrap--wide">
        <h1><?php echo esc_html(rbf_translate_string('Validazione Sistema Tracking')); ?></h1>
        
        <!-- Configuration Overview -->
        <div class="rbf-admin-card rbf-admin-card--spaced">
            <h2><?php echo esc_html(rbf_translate_string('Panoramica Configurazione')); ?></h2>

            <div class="rbf-config-grid">
                <div>
                    <h3><?php echo esc_html(rbf_translate_string('Google Analytics 4')); ?></h3>
                    <div class="rbf-config-meta">
                        <strong><?php echo esc_html(rbf_translate_string('ID Misurazione')); ?>:</strong>
                        <code><?php echo esc_html($options['ga4_id'] ?: rbf_translate_string('Non configurato')); ?></code>
                    </div>
                    <div class="rbf-config-meta">
                        <strong><?php echo esc_html(rbf_translate_string('API Secret')); ?>:</strong>
                        <code><?php echo esc_html($options['ga4_api_secret'] ? rbf_translate_string('Configurato') : rbf_translate_string('Non configurato')); ?></code>
                    </div>
                </div>

                <div>
                    <h3><?php echo esc_html(rbf_translate_string('Google Tag Manager')); ?></h3>
                    <div class="rbf-config-meta">
                        <strong><?php echo esc_html(rbf_translate_string('Container ID')); ?>:</strong>
                        <code><?php echo esc_html($options['gtm_id'] ?: rbf_translate_string('Non configurato')); ?></code>
                    </div>
                    <div class="rbf-config-meta">
                        <strong><?php echo esc_html(rbf_translate_string('Modalità Ibrida')); ?>:</strong>
                        <code><?php echo esc_html(($options['gtm_hybrid'] === 'yes') ? rbf_translate_string('Attiva') : rbf_translate_string('Disattiva')); ?></code>
                    </div>
                </div>

                <div>
                    <h3><?php echo esc_html(rbf_translate_string('Meta Pixel')); ?></h3>
                    <div class="rbf-config-meta">
                        <strong><?php echo esc_html(rbf_translate_string('Pixel ID')); ?>:</strong>
                        <code><?php echo esc_html($options['meta_pixel_id'] ?: rbf_translate_string('Non configurato')); ?></code>
                    </div>
                    <div class="rbf-config-meta">
                        <strong><?php echo esc_html(rbf_translate_string('Access Token (CAPI)')); ?>:</strong>
                        <code><?php echo esc_html($options['meta_access_token'] ? rbf_translate_string('Configurato') : rbf_translate_string('Non configurato')); ?></code>
                    </div>
                </div>

                <div>
                    <h3><?php echo esc_html(rbf_translate_string('Google Ads')); ?></h3>
                    <div class="rbf-config-meta">
                        <strong><?php echo esc_html(rbf_translate_string('ID Conversione Google Ads')); ?>:</strong>
                        <code><?php echo esc_html($options['google_ads_conversion_id'] ?: rbf_translate_string('Non configurato')); ?></code>
                    </div>
                    <div class="rbf-config-meta">
                        <strong><?php echo esc_html(rbf_translate_string('Etichetta Conversione Google Ads')); ?>:</strong>
                        <code><?php echo esc_html($options['google_ads_conversion_label'] ?: rbf_translate_string('Non configurato')); ?></code>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Validation Results -->
        <div class="rbf-admin-card rbf-admin-card--spaced">
            <h2><?php echo esc_html(rbf_translate_string('Risultati Validazione')); ?></h2>

            <div class="rbf-validation-list">
                <?php foreach ($validation_results as $check_name => $result): ?>
                    <?php
                    $status_variant = [
                        'class' => 'rbf-validation-item--info',
                        'icon'  => 'ℹ',
                    ];

                    if ($result['status'] === 'ok') {
                        $status_variant = [
                            'class' => 'rbf-validation-item--ok',
                            'icon'  => '✓',
                        ];
                    } elseif ($result['status'] === 'warning') {
                        $status_variant = [
                            'class' => 'rbf-validation-item--warning',
                            'icon'  => '⚠',
                        ];
                    }
                    ?>
                    <div class="rbf-validation-item <?php echo esc_attr($status_variant['class']); ?>">
                        <span class="rbf-validation-item__icon"><?php echo esc_html($status_variant['icon']); ?></span>
                        <div class="rbf-validation-item__content">
                            <span class="rbf-validation-item__title"><?php echo esc_html(ucfirst(str_replace('_', ' ', $check_name))); ?></span>
                            <span class="rbf-validation-item__message"><?php echo esc_html($result['message']); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Test Tracking -->
        <div class="rbf-admin-card rbf-admin-card--spaced">
            <h2><?php echo esc_html(rbf_translate_string('Test Sistema Tracking')); ?></h2>

            <?php if ($test_result): ?>
                <?php $test_class = $test_result['success'] ? 'rbf-test-result--success' : 'rbf-test-result--error'; ?>
                <div class="rbf-test-result <?php echo esc_attr($test_class); ?>">
                    <strong><?php echo esc_html($test_result['success'] ? rbf_translate_string('Test completato') : rbf_translate_string('Test fallito')); ?></strong>
                    <div class="rbf-spacing-top-xs">
                        <?php echo wp_kses_post($test_result['message']); ?>
                    </div>
                </div>
            <?php endif; ?>

            <form method="post">
                <?php wp_nonce_field('rbf_tracking_test'); ?>
                <p><?php echo esc_html(rbf_translate_string('Esegui un test del sistema di tracking per verificare che tutti i componenti funzionino correttamente.')); ?></p>
                <p class="submit">
                    <input type="submit" name="test_tracking" class="button button-primary" value="<?php echo esc_attr(rbf_translate_string('Esegui Test Tracking')); ?>">
                </p>
            </form>
        </div>
    </div>
    
    <?php
}

/**
 * Perform tracking system test
 */
function rbf_perform_tracking_test() {
    $options = rbf_get_settings();
    $results = [];
    $success = true;
    
    // Test GA4 configuration
    if (!empty($options['ga4_id'])) {
        $results[] = '✓ GA4 ID configurato: ' . $options['ga4_id'];
        
        // Test server-side tracking if API secret is available
        if (!empty($options['ga4_api_secret'])) {
            $results[] = '✓ GA4 API Secret configurato per tracking server-side';
        } else {
            $results[] = '⚠ GA4 API Secret non configurato - tracking server-side non disponibile';
        }
    } else {
        $results[] = '✗ GA4 non configurato';
        $success = false;
    }
    
    // Test GTM configuration
    if (!empty($options['gtm_id'])) {
        $results[] = '✓ GTM Container ID configurato: ' . $options['gtm_id'];
        
        if (($options['gtm_hybrid'] ?? '') === 'yes') {
            $results[] = '✓ Modalità ibrida GTM + GA4 attiva';
        } else {
            $results[] = 'ℹ Modalità ibrida disattiva - tracking standard GA4';
        }
    } else {
        $results[] = 'ℹ GTM non configurato - utilizzo tracking GA4 diretto';
    }
    
    // Test Meta Pixel configuration
    if (!empty($options['meta_pixel_id'])) {
        $results[] = '✓ Meta Pixel ID configurato: ' . $options['meta_pixel_id'];

        if (!empty($options['meta_access_token'])) {
            $results[] = '✓ Meta Access Token configurato per Conversion API';
        } else {
            $results[] = '⚠ Meta Access Token non configurato - CAPI non disponibile';
        }
    } else {
        $results[] = 'ℹ Meta Pixel non configurato';
    }

    // Test Google Ads conversion configuration
    $google_ads_conversion_id = $options['google_ads_conversion_id'] ?? '';
    $google_ads_conversion_label = $options['google_ads_conversion_label'] ?? '';

    if (!empty($google_ads_conversion_id) && !empty($google_ads_conversion_label)) {
        $results[] = '✓ ID conversione Google Ads configurati';
    } elseif (!empty($google_ads_conversion_id) || !empty($google_ads_conversion_label)) {
        $results[] = '⚠ Configurazione Google Ads incompleta - specificare sia ID conversione che etichetta';
    } else {
        $results[] = 'ℹ Conversione Google Ads non configurata';
    }

    // Test JavaScript integration
    if (function_exists('rbf_is_gtm_hybrid_mode')) {
        $results[] = '✓ Funzioni JavaScript integration caricate correttamente';
    } else {
        $results[] = '✗ Errore caricamento funzioni JavaScript integration';
        $success = false;
    }
    
    // Test validation functions
    if (function_exists('rbf_validate_tracking_setup')) {
        $results[] = '✓ Funzioni validazione tracking caricate correttamente';
    } else {
        $results[] = '✗ Errore caricamento funzioni validazione tracking';
        $success = false;
    }
    
    return [
        'success' => $success,
        'message' => '<ul><li>' . implode('</li><li>', $results) . '</li></ul>'
    ];
}
