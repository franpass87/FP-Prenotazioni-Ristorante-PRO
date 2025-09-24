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

    add_menu_page(rbf_translate_string('Prenotazioni'), rbf_translate_string('Prenotazioni'), $booking_capability, 'rbf_calendar', 'rbf_calendar_page_html', 'dashicons-calendar-alt', 20);
    add_submenu_page('rbf_calendar', rbf_translate_string('Prenotazioni'), rbf_translate_string('Tutte le Prenotazioni'), $booking_capability, 'rbf_calendar', 'rbf_calendar_page_html');
    add_submenu_page('rbf_calendar', rbf_translate_string('Vista Settimanale Staff'), rbf_translate_string('Vista Settimanale Staff'), $booking_capability, 'rbf_weekly_staff', 'rbf_weekly_staff_page_html');
    add_submenu_page('rbf_calendar', rbf_translate_string('Aggiungi Prenotazione'), rbf_translate_string('Aggiungi Nuova'), $booking_capability, 'rbf_add_booking', 'rbf_add_booking_page_html');
    add_submenu_page('rbf_calendar', rbf_translate_string('Gestione Tavoli'), rbf_translate_string('Gestione Tavoli'), $booking_capability, 'rbf_tables', 'rbf_tables_page_html');
    add_submenu_page('rbf_calendar', rbf_translate_string('Report & Analytics'), rbf_translate_string('Report & Analytics'), $booking_capability, 'rbf_reports', 'rbf_reports_page_html');
    add_submenu_page('rbf_calendar', rbf_translate_string('Notifiche Email'), rbf_translate_string('Notifiche Email'), $settings_capability, 'rbf_email_notifications', 'rbf_email_notifications_page_html');
    add_submenu_page('rbf_calendar', rbf_translate_string('Esporta Dati'), rbf_translate_string('Esporta Dati'), $booking_capability, 'rbf_export', 'rbf_export_page_html');
    add_submenu_page('rbf_calendar', rbf_translate_string('Impostazioni'), rbf_translate_string('Impostazioni'), $settings_capability, 'rbf_settings', 'rbf_settings_page_html');
    add_submenu_page('rbf_calendar', rbf_translate_string('Validazione Tracking'), rbf_translate_string('Validazione Tracking'), $settings_capability, 'rbf_tracking_validation', 'rbf_tracking_validation_page_html');
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
            echo '<span style="display: inline-block; padding: 4px 8px; border-radius: 4px; background: ' . esc_attr($color) . '; color: white; font-size: 12px; font-weight: bold;">';
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
                echo '<br><small><span style="color: #666;">Tipo: ' . esc_html($type_label) . '</span></small>';
                echo '<br><small><span style="color: #666;">Capacità: ' . intval($assignment['total_capacity']) . '</span></small>';
            } else {
                echo '<em style="color: #999;">Non assegnato</em>';
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
            echo '<div class="row-actions" style="position: static;">';
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
        echo 'style="color: #ef4444; font-weight: bold; text-decoration: none;" ';
        echo 'onclick="return confirm(\'' . esc_js(rbf_translate_string('Elimina definitivamente questa prenotazione?')) . '\')">';
        echo esc_html(rbf_translate_string('Elimina'));
        echo '</a>';
    } else {
        echo '<span style="color: #6b7280; font-style: italic;">' . esc_html(rbf_translate_string('Gestione Automatica')) . '</span>';
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

    // Define field types for bulk sanitization
    $field_types = [
        // Integer fields
        'capienza_pranzo' => 'int', 'capienza_cena' => 'int', 'capienza_aperitivo' => 'int',
        'brevo_list_it' => 'int', 'brevo_list_en' => 'int',
        'booking_page_id' => 'int',
        
        // Text fields
        'orari_pranzo' => 'text', 'orari_cena' => 'text', 'orari_aperitivo' => 'text',
        'brevo_api' => 'text', 'ga4_api_secret' => 'text', 'meta_access_token' => 'text',
        'border_radius' => 'text', 'google_ads_conversion_id' => 'text', 'google_ads_conversion_label' => 'text',
        
        // Email fields  
        'notification_email' => 'email', 'webmaster_email' => 'email'
    ];
    
    // Bulk sanitize using helper
    $sanitized = rbf_sanitize_input_fields($input, $field_types);
    
    // Apply sanitized values with defaults
    foreach ($field_types as $key => $type) {
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
add_action('admin_enqueue_scripts','rbf_enqueue_admin_styles');
function rbf_enqueue_admin_styles($hook) {
    if ($hook !== 'prenotazioni_page_rbf_settings' &&
        $hook !== 'toplevel_page_rbf_calendar' &&
        $hook !== 'prenotazioni_page_rbf_add_booking' &&
        $hook !== 'prenotazioni_page_rbf_reports' &&
        $hook !== 'prenotazioni_page_rbf_export' &&
        strpos($hook,'edit.php?post_type=rbf_booking') === false) return;

    wp_enqueue_style('rbf-admin-css', plugin_dir_url(dirname(__FILE__)) . 'assets/css/admin.css', [], rbf_get_asset_version());
    
    // Enqueue WordPress color picker for settings page
    if ($hook === 'prenotazioni_page_rbf_settings') {
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
    }
    
    // Inject brand CSS variables for admin
    rbf_inject_brand_css_vars_admin();
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
    ?>
    <div class="rbf-admin-wrap">
        <h1><?php echo esc_html(rbf_translate_string('Impostazioni Prenotazioni Ristorante')); ?></h1>
        <form method="post" action="options.php">
            <?php settings_fields('rbf_opts_group'); ?>
            <table class="form-table" role="presentation">
                <tr><th colspan="2"><h2><?php echo esc_html(rbf_translate_string('Configurazione Brand e Colori')); ?></h2></th></tr>
                <tr>
                    <th><label for="rbf_accent_color"><?php echo esc_html(rbf_translate_string('Colore Primario')); ?></label></th>
                    <td>
                        <input type="color" id="rbf_accent_color" name="rbf_settings[accent_color]" value="<?php echo esc_attr($options['accent_color'] ?? '#000000'); ?>" class="rbf-color-picker">
                        <p class="description"><?php echo esc_html(rbf_translate_string('Colore principale utilizzato per pulsanti, evidenziazioni e elementi attivi')); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="rbf_secondary_color"><?php echo esc_html(rbf_translate_string('Colore Secondario')); ?></label></th>
                    <td>
                        <input type="color" id="rbf_secondary_color" name="rbf_settings[secondary_color]" value="<?php echo esc_attr($options['secondary_color'] ?? '#f8b500'); ?>" class="rbf-color-picker">
                        <p class="description"><?php echo esc_html(rbf_translate_string('Colore secondario per accenti e elementi complementari')); ?></p>
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
                    <th><?php echo esc_html(rbf_translate_string('Anteprima')); ?></th>
                    <td>
                        <div id="rbf-brand-preview" style="padding: 20px; border: 1px solid #ddd; background: #f9f9f9; max-width: 400px;">
                            <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                                <button type="button" id="preview-primary-btn" style="padding: 10px 20px; background: var(--preview-accent, #000000); color: white; border: none; cursor: pointer; border-radius: var(--preview-radius, 8px);"><?php echo esc_html(rbf_translate_string('Pulsante Principale')); ?></button>
                                <button type="button" id="preview-secondary-btn" style="padding: 10px 20px; background: var(--preview-secondary, #f8b500); color: white; border: none; cursor: pointer; border-radius: var(--preview-radius, 8px);"><?php echo esc_html(rbf_translate_string('Pulsante Secondario')); ?></button>
                            </div>
                            <input type="text" placeholder="<?php echo esc_attr(rbf_translate_string('Campo di esempio')); ?>" style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: var(--preview-radius, 8px); margin-bottom: 10px;">
                            <p style="margin: 0; font-size: 14px; color: #666;"><?php echo esc_html(rbf_translate_string('Questa anteprima mostra come appariranno i colori selezionati')); ?></p>
                        </div>
                    </td>
                </tr>

                <tr><th colspan="2"><h2><?php echo esc_html(rbf_translate_string('Pagina di Conferma Prenotazione')); ?></h2></th></tr>
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

                <tr><th colspan="2"><h2><?php echo esc_html(rbf_translate_string('Disponibilità Settimanale')); ?></h2></th></tr>
                <tr>
                    <th><?php echo esc_html(rbf_translate_string('Giorni di apertura')); ?></th>
                    <td>
                        <div class="rbf-weekday-toggle-group" style="display: flex; flex-wrap: wrap; gap: 10px;">
                            <?php foreach ($day_labels as $day_key => $day_label) {
                                $option_key = "open_{$day_key}";
                                $is_open = ($options[$option_key] ?? 'yes') === 'yes';
                                ?>
                                <label style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; background: #fff;">
                                    <input type="hidden" name="rbf_settings[<?php echo esc_attr($option_key); ?>]" value="no">
                                    <input type="checkbox" name="rbf_settings[<?php echo esc_attr($option_key); ?>]" value="yes" <?php checked($is_open); ?>>
                                    <span><?php echo esc_html($day_label); ?></span>
                                </label>
                                <?php
                            } ?>
                        </div>
                        <p class="description" style="margin-top: 8px;">
                            <?php echo esc_html(rbf_translate_string('Deseleziona i giorni in cui il ristorante resta chiuso.')); ?>
                        </p>
                    </td>
                </tr>

                <tr><th colspan="2"><h2><?php echo esc_html(rbf_translate_string('Configurazione Pasti')); ?></h2></th></tr>

                <tr>
                    <th><?php echo esc_html(rbf_translate_string('Pasti Personalizzati')); ?></th>
                    <td>
                        <div id="custom-meals-container">
                            <?php
                            $custom_meals = $options['custom_meals'] ?? rbf_get_default_custom_meals();
                            if (!is_array($custom_meals)) {
                                $custom_meals = [];
                            }
                            ?>
                            <div class="notice notice-info inline" style="margin: 0 0 15px 0;">
                                <p><?php echo esc_html(rbf_translate_string('Importante: dopo l\'installazione non sono presenti pasti preconfigurati. Configura i servizi del tuo ristorante utilizzando "Aggiungi Pasto" e salva le modifiche per renderli disponibili nel form.')); ?></p>
                            </div>
                            <?php

                            if (empty($custom_meals)) {
                                ?>
                                <div class="notice notice-warning inline" style="margin: 0 0 15px 0;">
                                    <p><?php echo esc_html(rbf_translate_string('Nessun pasto è attualmente configurato. Il modulo di prenotazione rimane inattivo finché non aggiungi e attivi almeno un pasto personalizzato.')); ?></p>
                                </div>
                                <?php
                            }

                            foreach ($custom_meals as $index => $meal) {
                                ?>
                                <div class="custom-meal-item" style="border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; background: #f9f9f9;">
                                    <h4><?php echo sprintf(esc_html(rbf_translate_string('Pasto %d')), $index + 1); ?></h4>
                                    
                                    <table class="form-table">
                                        <tr>
                                            <th><label><?php echo esc_html(rbf_translate_string('Attivo')); ?></label></th>
                                            <td>
                                                <input type="checkbox" name="rbf_settings[custom_meals][<?php echo esc_attr($index); ?>][enabled]" value="1" <?php checked($meal['enabled'] ?? false); ?>>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><label><?php echo esc_html(rbf_translate_string('ID')); ?></label></th>
                                            <td>
                                                <input type="text" name="rbf_settings[custom_meals][<?php echo esc_attr($index); ?>][id]" value="<?php echo esc_attr($meal['id'] ?? ''); ?>" class="regular-text" placeholder="es: pranzo">
                                                <p class="description"><?php echo esc_html(rbf_translate_string('ID univoco del pasto (senza spazi, solo lettere e numeri)')); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><label><?php echo esc_html(rbf_translate_string('Nome')); ?></label></th>
                                            <td>
                                                <input type="text" name="rbf_settings[custom_meals][<?php echo esc_attr($index); ?>][name]" value="<?php echo esc_attr($meal['name'] ?? ''); ?>" class="regular-text" placeholder="es: Pranzo">
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><label><?php echo esc_html(rbf_translate_string('Capienza')); ?></label></th>
                                            <td>
                                                <input type="number" name="rbf_settings[custom_meals][<?php echo esc_attr($index); ?>][capacity]" value="<?php echo esc_attr($meal['capacity'] ?? 30); ?>" min="1">
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><label><?php echo esc_html(rbf_translate_string('Orari')); ?></label></th>
                                            <td>
                                                <input type="text" name="rbf_settings[custom_meals][<?php echo esc_attr($index); ?>][time_slots]" value="<?php echo esc_attr($meal['time_slots'] ?? ''); ?>" class="regular-text" placeholder="es: 12:00,12:30,13:00">
                                                <p class="description"><?php echo esc_html(rbf_translate_string('Orari separati da virgola')); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><label><?php echo esc_html(rbf_translate_string('Prezzo (€)')); ?></label></th>
                                            <td>
                                                <input type="number" step="0.01" name="rbf_settings[custom_meals][<?php echo esc_attr($index); ?>][price]" value="<?php echo esc_attr($meal['price'] ?? 0); ?>" min="0">
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><label><?php echo esc_html(rbf_translate_string('Giorni disponibili')); ?></label></th>
                                            <td>
                                                <?php
                                                $available_days = $meal['available_days'] ?? [];
                                                foreach ($day_labels as $day_key => $day_label) {
                                                    $checked = in_array($day_key, $available_days) ? 'checked' : '';
                                                    ?>
                                                    <label style="display: inline-block; margin-right: 15px;">
                                                        <input type="checkbox" name="rbf_settings[custom_meals][<?php echo esc_attr($index); ?>][available_days][]" value="<?php echo esc_attr($day_key); ?>" <?php echo $checked; ?>>
                                                        <?php echo esc_html($day_label); ?>
                                                    </label>
                                                    <?php
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><label><?php echo esc_html(rbf_translate_string('Tooltip informativo')); ?></label></th>
                                            <td>
                                                <textarea name="rbf_settings[custom_meals][<?php echo esc_attr($index); ?>][tooltip]" class="regular-text" rows="2" placeholder="es: Di Domenica il servizio è Brunch con menù alla carta."><?php echo esc_textarea($meal['tooltip'] ?? ''); ?></textarea>
                                                <p class="description"><?php echo esc_html(rbf_translate_string('Testo informativo che apparirà quando questo pasto viene selezionato (opzionale)')); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><label><?php echo esc_html(rbf_translate_string('Buffer Base (minuti)')); ?></label></th>
                                            <td>
                                                <input type="number" name="rbf_settings[custom_meals][<?php echo esc_attr($index); ?>][buffer_time_minutes]" value="<?php echo esc_attr($meal['buffer_time_minutes'] ?? 15); ?>" min="0" max="120">
                                                <p class="description"><?php echo esc_html(rbf_translate_string('Tempo minimo di buffer tra prenotazioni (minuti)')); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><label><?php echo esc_html(rbf_translate_string('Buffer per Persona (minuti)')); ?></label></th>
                                            <td>
                                                <input type="number" name="rbf_settings[custom_meals][<?php echo esc_attr($index); ?>][buffer_time_per_person]" value="<?php echo esc_attr($meal['buffer_time_per_person'] ?? 5); ?>" min="0" max="30">
                                                <p class="description"><?php echo esc_html(rbf_translate_string('Tempo aggiuntivo di buffer per ogni persona (minuti)')); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><label><?php echo esc_html(rbf_translate_string('Limite Overbooking (%)')); ?></label></th>
                                            <td>
                                                <input type="number" name="rbf_settings[custom_meals][<?php echo esc_attr($index); ?>][overbooking_limit]" value="<?php echo esc_attr($meal['overbooking_limit'] ?? 10); ?>" min="0" max="50">
                                                <p class="description"><?php echo esc_html(rbf_translate_string('Percentuale di overbooking consentita oltre la capienza normale')); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><label><?php echo esc_html(rbf_translate_string('Durata Slot (minuti)')); ?></label></th>
                                            <td>
                                                <input type="number" name="rbf_settings[custom_meals][<?php echo esc_attr($index); ?>][slot_duration_minutes]" value="<?php echo esc_attr($meal['slot_duration_minutes'] ?? 90); ?>" min="30" max="240">
                                                <p class="description"><?php echo esc_html(rbf_translate_string('Durata di occupazione del tavolo per questo servizio (minuti)')); ?></p>
                                            </td>
                                        </tr>
                                    </table>
                                    
                                    <button type="button" class="button button-secondary remove-meal" style="margin-top: 10px;"><?php echo esc_html(rbf_translate_string('Rimuovi Pasto')); ?></button>
                                </div>
                                <?php
                            }
                            ?>
                        </div>
                        
                        <button type="button" id="add-meal" class="button button-primary"><?php echo esc_html(rbf_translate_string('Aggiungi Pasto')); ?></button>
                        
                        <script>
                        jQuery(document).ready(function($) {
                            // Initialize WordPress color pickers
                            $('.rbf-color-picker').wpColorPicker({
                                change: updateBrandPreview
                            });
                            
                            // Brand preview functionality
                            function updateBrandPreview() {
                                var accentColor = $('#rbf_accent_color').val();
                                var secondaryColor = $('#rbf_secondary_color').val();
                                var borderRadius = $('#rbf_border_radius').val();
                                
                                var preview = $('#rbf-brand-preview');
                                preview.css('--preview-accent', accentColor);
                                preview.css('--preview-secondary', secondaryColor);
                                preview.css('--preview-radius', borderRadius);
                                
                                $('#preview-primary-btn').css({
                                    'background': accentColor,
                                    'border-radius': borderRadius
                                });
                                $('#preview-secondary-btn').css({
                                    'background': secondaryColor,
                                    'border-radius': borderRadius
                                });
                                preview.find('input').css('border-radius', borderRadius);
                            }
                            
                            // Update preview when colors or radius change
                            $('#rbf_accent_color, #rbf_secondary_color, #rbf_border_radius').change(updateBrandPreview);
                            
                            // Initialize preview
                            updateBrandPreview();
                            
                            // Add new meal
                            $('#add-meal').click(function() {
                                var container = $('#custom-meals-container');
                                var index = container.find('.custom-meal-item').length;
                                var newMeal = createMealItem(index);
                                container.append(newMeal);
                            });
                            
                            // Remove meal
                            $(document).on('click', '.remove-meal', function() {
                                $(this).closest('.custom-meal-item').remove();
                            });
                            
                            function createMealItem(index) {
                                var dayCheckboxes = '';
                                <?php foreach ($day_labels as $day_key => $day_label) { ?>
                                dayCheckboxes += '<label style="display: inline-block; margin-right: 15px;">';
                                dayCheckboxes += '<input type="checkbox" name="rbf_settings[custom_meals][' + index + '][available_days][]" value="<?php echo $day_key; ?>">';
                                dayCheckboxes += '<?php echo esc_js($day_label); ?>';
                                dayCheckboxes += '</label>';
                                <?php } ?>
                                
                                return `
                                <div class="custom-meal-item" style="border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; background: #f9f9f9;">
                                    <h4><?php echo esc_html(rbf_translate_string('Pasto')); ?> ` + (index + 1) + `</h4>
                                    <table class="form-table">
                                        <tr>
                                            <th><label><?php echo esc_html(rbf_translate_string('Attivo')); ?></label></th>
                                            <td><input type="checkbox" name="rbf_settings[custom_meals][` + index + `][enabled]" value="1" checked></td>
                                        </tr>
                                        <tr>
                                            <th><label><?php echo esc_html(rbf_translate_string('ID')); ?></label></th>
                                            <td>
                                                <input type="text" name="rbf_settings[custom_meals][` + index + `][id]" value="" class="regular-text" placeholder="es: pranzo">
                                                <p class="description"><?php echo esc_html(rbf_translate_string('ID univoco del pasto (senza spazi, solo lettere e numeri)')); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><label><?php echo esc_html(rbf_translate_string('Nome')); ?></label></th>
                                            <td><input type="text" name="rbf_settings[custom_meals][` + index + `][name]" value="" class="regular-text" placeholder="es: Pranzo"></td>
                                        </tr>
                                        <tr>
                                            <th><label><?php echo esc_html(rbf_translate_string('Capienza')); ?></label></th>
                                            <td><input type="number" name="rbf_settings[custom_meals][` + index + `][capacity]" value="30" min="1"></td>
                                        </tr>
                                        <tr>
                                            <th><label><?php echo esc_html(rbf_translate_string('Orari')); ?></label></th>
                                            <td>
                                                <input type="text" name="rbf_settings[custom_meals][` + index + `][time_slots]" value="" class="regular-text" placeholder="es: 12:00,12:30,13:00">
                                                <p class="description"><?php echo esc_html(rbf_translate_string('Orari separati da virgola')); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><label><?php echo esc_html(rbf_translate_string('Prezzo (€)')); ?></label></th>
                                            <td><input type="number" step="0.01" name="rbf_settings[custom_meals][` + index + `][price]" value="0" min="0"></td>
                                        </tr>
                                        <tr>
                                            <th><label><?php echo esc_html(rbf_translate_string('Giorni disponibili')); ?></label></th>
                                            <td>` + dayCheckboxes + `</td>
                                        </tr>
                                        <tr>
                                            <th><label><?php echo esc_html(rbf_translate_string('Tooltip informativo')); ?></label></th>
                                            <td>
                                                <textarea name="rbf_settings[custom_meals][` + index + `][tooltip]" class="regular-text" rows="2" placeholder="es: Di Domenica il servizio è Brunch con menù alla carta."></textarea>
                                                <p class="description"><?php echo esc_html(rbf_translate_string('Testo informativo che apparirà quando questo pasto viene selezionato (opzionale)')); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><label><?php echo esc_html(rbf_translate_string('Buffer Base (minuti)')); ?></label></th>
                                            <td>
                                                <input type="number" name="rbf_settings[custom_meals][` + index + `][buffer_time_minutes]" value="15" min="0" max="120">
                                                <p class="description"><?php echo esc_html(rbf_translate_string('Tempo minimo di buffer tra prenotazioni (minuti)')); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><label><?php echo esc_html(rbf_translate_string('Buffer per Persona (minuti)')); ?></label></th>
                                            <td>
                                                <input type="number" name="rbf_settings[custom_meals][` + index + `][buffer_time_per_person]" value="5" min="0" max="30">
                                                <p class="description"><?php echo esc_html(rbf_translate_string('Tempo aggiuntivo di buffer per ogni persona (minuti)')); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><label><?php echo esc_html(rbf_translate_string('Limite Overbooking (%)')); ?></label></th>
                                            <td>
                                                <input type="number" name="rbf_settings[custom_meals][` + index + `][overbooking_limit]" value="10" min="0" max="50">
                                                <p class="description"><?php echo esc_html(rbf_translate_string('Percentuale di overbooking consentita oltre la capienza normale')); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><label><?php echo esc_html(rbf_translate_string('Durata Slot (minuti)')); ?></label></th>
                                            <td>
                                                <input type="number" name="rbf_settings[custom_meals][` + index + `][slot_duration_minutes]" value="90" min="30" max="240">
                                                <p class="description"><?php echo esc_html(rbf_translate_string('Durata di occupazione del tavolo per questo servizio (minuti)')); ?></p>
                                            </td>
                                        </tr>
                                    </table>
                                    <button type="button" class="button button-secondary remove-meal" style="margin-top: 10px;"><?php echo esc_html(rbf_translate_string('Rimuovi Pasto')); ?></button>
                                </div>`;
                            }
                        });
                        </script>
                    </td>
                </tr>
                
                <tr><th colspan="2"><h2><?php echo esc_html(rbf_translate_string('Eccezioni Calendario')); ?></h2></th></tr>
                <tr>
                    <th><label for="rbf_closed_dates"><?php echo esc_html(rbf_translate_string('Gestione Eccezioni')); ?></label></th>
                    <td>
                        <div id="rbf_exceptions_manager">
                            <p class="description" style="margin-bottom: 15px;">
                                <?php echo esc_html(rbf_translate_string('Gestisci chiusure straordinarie, festività, eventi speciali e orari estesi.')); ?>
                            </p>
                            
                            <div class="rbf-exception-add" style="background: #f9f9f9; padding: 15px; border: 1px solid #ddd; margin-bottom: 15px;">
                                <h4 style="margin: 0 0 10px 0;"><?php echo esc_html(rbf_translate_string('Aggiungi Nuova Eccezione')); ?></h4>
                                <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: end;">
                                    <div>
                                        <label style="display: block; margin-bottom: 5px; font-weight: bold;"><?php echo esc_html(rbf_translate_string('Data')); ?></label>
                                        <input type="date" id="exception_date" style="padding: 5px;">
                                    </div>
                                    <div>
                                        <label style="display: block; margin-bottom: 5px; font-weight: bold;"><?php echo esc_html(rbf_translate_string('Tipo')); ?></label>
                                        <select id="exception_type" style="padding: 5px;">
                                            <option value="closure"><?php echo esc_html(rbf_translate_string('Chiusura')); ?></option>
                                            <option value="holiday"><?php echo esc_html(rbf_translate_string('Festività')); ?></option>
                                            <option value="special"><?php echo esc_html(rbf_translate_string('Evento Speciale')); ?></option>
                                            <option value="extended"><?php echo esc_html(rbf_translate_string('Orari Estesi')); ?></option>
                                        </select>
                                    </div>
                                    <div id="special_hours_container" style="display: none;">
                                        <label style="display: block; margin-bottom: 5px; font-weight: bold;"><?php echo esc_html(rbf_translate_string('Orari Speciali')); ?></label>
                                        <input type="text" id="special_hours" placeholder="es. 18:00-02:00" style="padding: 5px;">
                                    </div>
                                    <div style="flex-grow: 1;">
                                        <label style="display: block; margin-bottom: 5px; font-weight: bold;"><?php echo esc_html(rbf_translate_string('Descrizione')); ?></label>
                                        <input type="text" id="exception_description" placeholder="<?php echo esc_attr(rbf_translate_string('es. Chiusura per ferie')); ?>" style="padding: 5px; width: 100%;">
                                    </div>
                                    <button type="button" id="add_exception_btn" style="padding: 8px 15px; background: var(--rbf-accent-color, #000); color: white; border: none; cursor: pointer;"><?php echo esc_html(rbf_translate_string('Aggiungi')); ?></button>
                                </div>
                            </div>
                            
                            <div class="rbf-exceptions-list" style="margin-bottom: 15px;">
                                <h4><?php echo esc_html(rbf_translate_string('Eccezioni Attive')); ?></h4>
                                <div id="exceptions_list_display"></div>
                            </div>
                            
                            <textarea id="rbf_closed_dates" name="rbf_settings[closed_dates]" rows="8" class="large-text" style="font-family: monospace; font-size: 12px;"><?php echo esc_textarea($options['closed_dates']); ?></textarea>
                            <p class="description">
                                <?php echo esc_html(rbf_translate_string('Formato manuale: Data|Tipo|Orari|Descrizione (es. 2024-12-25|closure||Natale) oppure formato semplice (es. 2024-12-25)')); ?>
                            </p>
                        </div>
                        
                        <script>
                        jQuery(document).ready(function($) {
                            function updateExceptionDisplay() {
                                const textarea = $('#rbf_closed_dates');
                                const lines = textarea.val().split('\n').filter(line => line.trim());
                                const display = $('#exceptions_list_display');
                                
                                display.empty();
                                
                                if (lines.length === 0) {
                                    display.html('<p style="color: #666; font-style: italic;"><?php echo esc_js(rbf_translate_string('Nessuna eccezione configurata.')); ?></p>');
                                    return;
                                }
                                
                                lines.forEach(line => {
                                    line = line.trim();
                                    if (!line) return;
                                    
                                    let date, type, hours, description;
                                    
                                    if (line.includes('|')) {
                                        const parts = line.split('|');
                                        date = parts[0];
                                        type = parts[1] || 'closure';
                                        hours = parts[2] || '';
                                        description = parts[3] || '';
                                    } else {
                                        date = line;
                                        type = 'closure';
                                        hours = '';
                                        description = '<?php echo esc_js(rbf_translate_string('Chiusura')); ?>';
                                    }
                                    
                                    const typeLabels = {
                                        'closure': '<?php echo esc_js(rbf_translate_string('Chiusura')); ?>',
                                        'holiday': '<?php echo esc_js(rbf_translate_string('Festività')); ?>',
                                        'special': '<?php echo esc_js(rbf_translate_string('Evento Speciale')); ?>',
                                        'extended': '<?php echo esc_js(rbf_translate_string('Orari Estesi')); ?>'
                                    };
                                    
                                    const typeColors = {
                                        'closure': '#dc3545',
                                        'holiday': '#fd7e14',
                                        'special': '#20c997',
                                        'extended': '#0d6efd'
                                    };
                                    
                                    const item = $('<div>').css({
                                        'display': 'flex',
                                        'justify-content': 'space-between',
                                        'align-items': 'center',
                                        'padding': '10px',
                                        'margin': '5px 0',
                                        'background': '#fff',
                                        'border': '1px solid #ddd',
                                        'border-left': '4px solid ' + (typeColors[type] || '#666')
                                    });
                                    
                                    const info = $('<div>');
                                    info.append($('<strong>').text(date + ' - ' + (typeLabels[type] || type)));
                                    if (hours) info.append($('<br>')).append($('<span>').css('color', '#666').text('<?php echo esc_js(rbf_translate_string('Orari:')); ?> ' + hours));
                                    if (description) info.append($('<br>')).append($('<span>').css('color', '#666').text(description));
                                    
                                    const deleteBtn = $('<button>').attr('type', 'button').css({
                                        'background': '#dc3545',
                                        'color': 'white',
                                        'border': 'none',
                                        'padding': '5px 10px',
                                        'cursor': 'pointer',
                                        'border-radius': '3px'
                                    }).text('<?php echo esc_js(rbf_translate_string('Rimuovi')); ?>').click(function() {
                                        if (confirm('<?php echo esc_js(rbf_translate_string('Sei sicuro di voler rimuovere questa eccezione?')); ?>')) {
                                            const currentValue = textarea.val();
                                            const newValue = currentValue.split('\n').filter(l => l.trim() !== line).join('\n');
                                            textarea.val(newValue);
                                            updateExceptionDisplay();
                                        }
                                    });
                                    
                                    item.append(info).append(deleteBtn);
                                    display.append(item);
                                });
                            }
                            
                            // Show/hide special hours based on exception type
                            $('#exception_type').change(function() {
                                const type = $(this).val();
                                const hoursContainer = $('#special_hours_container');
                                if (type === 'special' || type === 'extended') {
                                    hoursContainer.show();
                                } else {
                                    hoursContainer.hide();
                                    $('#special_hours').val('');
                                }
                            });
                            
                            // Add exception
                            $('#add_exception_btn').click(function() {
                                const date = $('#exception_date').val();
                                const type = $('#exception_type').val();
                                const hours = $('#special_hours').val();
                                const description = $('#exception_description').val();
                                
                                if (!date) {
                                    alert('<?php echo esc_js(rbf_translate_string('Seleziona una data.')); ?>');
                                    return;
                                }
                                
                                if ((type === 'special' || type === 'extended') && !hours) {
                                    alert('<?php echo esc_js(rbf_translate_string('Specifica gli orari per questo tipo di eccezione.')); ?>');
                                    return;
                                }
                                
                                // Validate hours format if provided
                                if (hours && !(/^(\d{1,2}:\d{2}(-\d{1,2}:\d{2})?|\d{1,2}:\d{2}(,\d{1,2}:\d{2})*)$/.test(hours))) {
                                    alert('<?php echo esc_js(rbf_translate_string('Formato orari non valido. Usa: HH:MM-HH:MM o HH:MM,HH:MM,HH:MM')); ?>');
                                    return;
                                }
                                
                                const line = date + '|' + type + '|' + (hours || '') + '|' + (description || '');
                                const textarea = $('#rbf_closed_dates');
                                const currentValue = textarea.val().trim();
                                const newValue = currentValue ? currentValue + '\n' + line : line;
                                textarea.val(newValue);
                                
                                // Clear form
                                $('#exception_date').val('');
                                $('#exception_type').val('closure');
                                $('#special_hours').val('');
                                $('#exception_description').val('');
                                $('#special_hours_container').hide();
                                
                                updateExceptionDisplay();
                            });
                            
                            // Update display on page load and textarea change
                            updateExceptionDisplay();
                            $('#rbf_closed_dates').on('input', updateExceptionDisplay);
                        });
                        </script>
                    </td>
                </tr>

                <!-- Advance booking time limits removed as per user request - now using fixed 1-hour minimum -->
                <!-- <tr><th colspan="2"><h2><?php echo esc_html(rbf_translate_string('Limiti Temporali Prenotazioni')); ?></h2></th></tr> -->

                <tr><th colspan="2"><h2><?php echo esc_html(rbf_translate_string('Integrazioni e Marketing')); ?></h2></th></tr>
                <tr><th><label for="rbf_notification_email"><?php echo esc_html(rbf_translate_string('Email per Notifiche Ristorante')); ?></label></th>
                    <td><input type="email" id="rbf_notification_email" name="rbf_settings[notification_email]" value="<?php echo esc_attr($options['notification_email']); ?>" class="regular-text" placeholder="es. ristorante@esempio.com"></td></tr>
                <tr><th><label for="rbf_webmaster_email"><?php echo esc_html(rbf_translate_string('Email per Notifiche Webmaster')); ?></label></th>
                    <td><input type="email" id="rbf_webmaster_email" name="rbf_settings[webmaster_email]" value="<?php echo esc_attr($options['webmaster_email']); ?>" class="regular-text" placeholder="es. webmaster@esempio.com"></td></tr>
                <tr><th><label for="rbf_ga4_id"><?php echo esc_html(rbf_translate_string('ID misurazione GA4')); ?></label></th>
                    <td><input type="text" id="rbf_ga4_id" name="rbf_settings[ga4_id]" value="<?php echo esc_attr($options['ga4_id']); ?>" class="regular-text" placeholder="G-XXXXXXXXXX"></td></tr>
                <tr><th><label for="rbf_ga4_api_secret">GA4 API Secret (per invii server-side)</label></th>
                    <td><input type="text" id="rbf_ga4_api_secret" name="rbf_settings[ga4_api_secret]" value="<?php echo esc_attr($options['ga4_api_secret']); ?>" class="regular-text"></td></tr>
                <tr><th><label for="rbf_gtm_id"><?php echo esc_html(rbf_translate_string('ID GTM')); ?></label></th>
                    <td><input type="text" id="rbf_gtm_id" name="rbf_settings[gtm_id]" value="<?php echo esc_attr($options['gtm_id']); ?>" class="regular-text" placeholder="GTM-XXXXXXX"></td></tr>
                <tr><th><label for="rbf_gtm_hybrid"><?php echo esc_html(rbf_translate_string('Modalità ibrida GTM + GA4')); ?></label></th>
                    <td><input type="checkbox" id="rbf_gtm_hybrid" name="rbf_settings[gtm_hybrid]" value="yes" <?php checked($options['gtm_hybrid'] === 'yes'); ?>></td></tr>
                <tr><th><label for="rbf_google_ads_conversion_id"><?php echo esc_html(rbf_translate_string('ID Conversione Google Ads')); ?></label></th>
                    <td><input type="text" id="rbf_google_ads_conversion_id" name="rbf_settings[google_ads_conversion_id]" value="<?php echo esc_attr($options['google_ads_conversion_id'] ?? ''); ?>" class="regular-text" placeholder="AW-123456789"></td></tr>
                <tr><th><label for="rbf_google_ads_conversion_label"><?php echo esc_html(rbf_translate_string('Etichetta Conversione Google Ads')); ?></label></th>
                    <td><input type="text" id="rbf_google_ads_conversion_label" name="rbf_settings[google_ads_conversion_label]" value="<?php echo esc_attr($options['google_ads_conversion_label'] ?? ''); ?>" class="regular-text" placeholder="abcDEF123456"></td></tr>
                <tr><th><label for="rbf_meta_pixel_id"><?php echo esc_html(rbf_translate_string('ID Meta Pixel')); ?></label></th>
                    <td><input type="text" id="rbf_meta_pixel_id" name="rbf_settings[meta_pixel_id]" value="<?php echo esc_attr($options['meta_pixel_id']); ?>" class="regular-text"></td></tr>
                <tr><th><label for="rbf_meta_access_token">Meta Access Token (per invii server-side)</label></th>
                    <td><input type="password" id="rbf_meta_access_token" name="rbf_settings[meta_access_token]" value="<?php echo esc_attr($options['meta_access_token']); ?>" class="regular-text"></td></tr>

                <tr><th colspan="2"><h3><?php echo esc_html(rbf_translate_string('Impostazioni Brevo')); ?></h3></th></tr>
                <tr><th><label for="rbf_brevo_api"><?php echo esc_html(rbf_translate_string('API Key Brevo')); ?></label></th>
                    <td><input type="password" id="rbf_brevo_api" name="rbf_settings[brevo_api]" value="<?php echo esc_attr($options['brevo_api']); ?>" class="regular-text"></td></tr>
                <tr><th><label for="rbf_brevo_list_it"><?php echo esc_html(rbf_translate_string('ID Lista Brevo (IT)')); ?></label></th>
                    <td><input type="number" id="rbf_brevo_list_it" name="rbf_settings[brevo_list_it]" value="<?php echo esc_attr($options['brevo_list_it']); ?>"></td></tr>
                <tr><th><label for="rbf_brevo_list_en"><?php echo esc_html(rbf_translate_string('ID Lista Brevo (EN)')); ?></label></th>
                    <td><input type="number" id="rbf_brevo_list_en" name="rbf_settings[brevo_list_en]" value="<?php echo esc_attr($options['brevo_list_en']); ?>"></td></tr>

                <tr><th colspan="2"><h3><?php echo esc_html(rbf_translate_string('Protezione Anti-Bot')); ?></h3></th></tr>
                <tr><th><label for="rbf_recaptcha_site_key"><?php echo esc_html(rbf_translate_string('reCAPTCHA v3 Site Key')); ?></label></th>
                    <td><input type="text" id="rbf_recaptcha_site_key" name="rbf_settings[recaptcha_site_key]" value="<?php echo esc_attr($options['recaptcha_site_key'] ?? ''); ?>" class="regular-text" placeholder="6Le...">
                    <p class="description"><?php echo esc_html(rbf_translate_string('Chiave pubblica per reCAPTCHA v3. Lascia vuoto per disabilitare.')); ?></p></td></tr>
                <tr><th><label for="rbf_recaptcha_secret_key"><?php echo esc_html(rbf_translate_string('reCAPTCHA v3 Secret Key')); ?></label></th>
                    <td><input type="password" id="rbf_recaptcha_secret_key" name="rbf_settings[recaptcha_secret_key]" value="<?php echo esc_attr($options['recaptcha_secret_key'] ?? ''); ?>" class="regular-text" placeholder="6Le...">
                    <p class="description"><?php echo esc_html(rbf_translate_string('Chiave segreta per reCAPTCHA v3.')); ?></p></td></tr>
                <tr><th><label for="rbf_recaptcha_threshold"><?php echo esc_html(rbf_translate_string('Soglia reCAPTCHA')); ?></label></th>
                    <td><input type="number" id="rbf_recaptcha_threshold" name="rbf_settings[recaptcha_threshold]" value="<?php echo esc_attr($options['recaptcha_threshold'] ?? '0.5'); ?>" step="0.1" min="0" max="1" style="width: 80px;">
                    <p class="description"><?php echo esc_html(rbf_translate_string('Soglia minima per considerare valida una submission (0.0-1.0). Default: 0.5')); ?></p></td></tr>

            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

/**
 * Calendar page HTML
 */
function rbf_calendar_page_html() {
    if (!rbf_require_booking_capability()) {
        return;
    }

    wp_enqueue_style('fullcalendar-css', 'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css', [], '5.11.3');
    wp_enqueue_script('fullcalendar-js', 'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js', ['jquery'], '5.11.3', true);
    wp_enqueue_script('rbf-admin-js', plugin_dir_url(dirname(__FILE__)) . 'assets/js/admin.js', ['jquery', 'fullcalendar-js'], rbf_get_asset_version(), true);
    
    wp_localize_script('rbf-admin-js', 'rbfAdminData', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('rbf_calendar_nonce'),
        'editUrl' => admin_url('post.php?post=BOOKING_ID&action=edit')
    ]);
    ?>
    <div class="rbf-admin-wrap">
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

    wp_enqueue_style('fullcalendar-css', 'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css', [], '5.11.3');
    wp_enqueue_script('fullcalendar-js', 'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js', ['jquery'], '5.11.3', true);
    wp_enqueue_script('rbf-weekly-staff-js', plugin_dir_url(dirname(__FILE__)) . 'assets/js/weekly-staff.js', ['jquery', 'fullcalendar-js'], rbf_get_asset_version(), true);
    
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
    <div class="rbf-admin-wrap">
        <h1><?php echo esc_html(rbf_translate_string('Vista Settimanale Staff')); ?></h1>
        <p class="description"><?php echo esc_html(rbf_translate_string('Vista compatta per lo staff con funzionalità drag & drop per spostare le prenotazioni.')); ?></p>
        
        <div id="rbf-weekly-staff-calendar"></div>
        
        <div id="rbf-move-notification" class="notice" style="display: none;">
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
    $selected_meal = '';

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
        $selected_meal = $meal;
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
    <div class="rbf-admin-wrap">
        <h1><?php echo esc_html(rbf_translate_string('Aggiungi Nuova Prenotazione')); ?></h1>
        <?php echo wp_kses_post($message); ?>

        <?php if (empty($active_meals)) : ?>
            <div class="notice notice-warning"><p><?php echo esc_html(rbf_translate_string('Configura almeno un servizio attivo prima di aggiungere prenotazioni manuali.')); ?></p></div>
        <?php else : ?>
            <form method="post">
                <?php wp_nonce_field('rbf_add_backend_booking'); ?>
                <table class="form-table">
                    <tr><th><label for="rbf_meal"><?php echo esc_html(rbf_translate_string('Pasto')); ?></label></th>
                        <td><select id="rbf_meal" name="rbf_meal">
                            <option value=""><?php echo esc_html(rbf_translate_string('Scegli il pasto')); ?></option>
                            <?php foreach ($active_meals as $meal_config) : ?>
                                <option value="<?php echo esc_attr($meal_config['id']); ?>" <?php selected($selected_meal, $meal_config['id']); ?>><?php echo esc_html($meal_config['name'] ?? $meal_config['id']); ?></option>
                            <?php endforeach; ?>
                        </select></td></tr>
                    <tr><th><label for="rbf_data"><?php echo esc_html(rbf_translate_string('Data')); ?></label></th>
                        <td><input type="date" id="rbf_data" name="rbf_data"></td></tr>
                    <tr><th><label for="rbf_time"><?php echo esc_html(rbf_translate_string('Orario')); ?></label></th>
                        <td><input type="time" id="rbf_time" name="rbf_time"></td></tr>
                    <tr><th><label for="rbf_persone"><?php echo esc_html(rbf_translate_string('Persone')); ?></label></th>
                        <td><input type="number" id="rbf_persone" name="rbf_persone" min="0"></td></tr>
                    <tr><th><label for="rbf_nome"><?php echo esc_html(rbf_translate_string('Nome')); ?></label></th>
                        <td><input type="text" id="rbf_nome" name="rbf_nome"></td></tr>
                    <tr><th><label for="rbf_cognome"><?php echo esc_html(rbf_translate_string('Cognome')); ?></label></th>
                        <td><input type="text" id="rbf_cognome" name="rbf_cognome"></td></tr>
                    <tr><th><label for="rbf_email"><?php echo esc_html(rbf_translate_string('Email')); ?></label></th>
                        <td><input type="email" id="rbf_email" name="rbf_email"></td></tr>
                    <tr><th><label for="rbf_tel"><?php echo esc_html(rbf_translate_string('Telefono')); ?></label></th>
                        <td><input type="tel" id="rbf_tel" name="rbf_tel"></td></tr>
                    <tr><th><label for="rbf_allergie"><?php echo esc_html(rbf_translate_string('Allergie/Note')); ?></label></th>
                        <td><textarea id="rbf_allergie" name="rbf_allergie"></textarea></td></tr>
                    <tr><th><label for="rbf_lang"><?php echo esc_html(rbf_translate_string('Lingua')); ?></label></th>
                        <td><select id="rbf_lang" name="rbf_lang"><option value="it">IT</option><option value="en">EN</option></select></td></tr>
                    <tr><th><?php echo esc_html(rbf_translate_string('Privacy')); ?></th>
                        <td><label><input type="checkbox" name="rbf_privacy" value="yes"> <?php echo esc_html(rbf_translate_string('Accettata')); ?></label></td></tr>
                    <tr><th><?php echo esc_html(rbf_translate_string('Marketing')); ?></th>
                        <td><label><input type="checkbox" name="rbf_marketing" value="yes"> <?php echo esc_html(rbf_translate_string('Accettato')); ?></label></td></tr>
                </table>
                <?php submit_button(rbf_translate_string('Aggiungi Prenotazione')); ?>
            </form>
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

    $analytics = rbf_get_booking_analytics($start_date, $end_date);
    ?>
    <div class="rbf-admin-wrap">
        <h1><?php echo esc_html(rbf_translate_string('Report & Analytics')); ?></h1>
        
        <!-- Date Range Filter -->
        <div class="rbf-date-filter" style="background: #f9f9f9; padding: 15px; margin-bottom: 20px; border-radius: 8px;">
            <form method="get" style="display: flex; align-items: center; gap: 15px;">
                <input type="hidden" name="page" value="rbf_reports">
                <label for="start_date"><?php echo esc_html(rbf_translate_string('Da:')); ?></label>
                <input type="date" id="start_date" name="start_date" value="<?php echo esc_attr($start_date); ?>">
                <label for="end_date"><?php echo esc_html(rbf_translate_string('A:')); ?></label>
                <input type="date" id="end_date" name="end_date" value="<?php echo esc_attr($end_date); ?>">
                <button type="submit" class="button button-primary"><?php echo esc_html(rbf_translate_string('Aggiorna Report')); ?></button>
            </form>
        </div>
        
        <!-- Key Metrics Cards -->
        <div class="rbf-metrics-cards" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div class="rbf-metric-card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3 style="margin: 0 0 10px 0; color: #1f2937; font-size: 16px;"><?php echo esc_html(rbf_translate_string('Prenotazioni Totali')); ?></h3>
                <div style="font-size: 32px; font-weight: bold; color: #10b981;"><?php echo esc_html($analytics['total_bookings']); ?></div>
                <small style="color: #6b7280;"><?php echo esc_html(sprintf(rbf_translate_string('Dal %s al %s'), date('d/m/Y', strtotime($start_date)), date('d/m/Y', strtotime($end_date)))); ?></small>
            </div>
            
            <div class="rbf-metric-card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3 style="margin: 0 0 10px 0; color: #1f2937; font-size: 16px;"><?php echo esc_html(rbf_translate_string('Persone Totali')); ?></h3>
                <div style="font-size: 32px; font-weight: bold; color: #3b82f6;"><?php echo esc_html($analytics['total_people']); ?></div>
                <small style="color: #6b7280;"><?php echo esc_html(sprintf(rbf_translate_string('Media: %.1f per prenotazione'), $analytics['avg_people_per_booking'])); ?></small>
            </div>
            
            <div class="rbf-metric-card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3 style="margin: 0 0 10px 0; color: #1f2937; font-size: 16px;"><?php echo esc_html(rbf_translate_string('Valore Stimato')); ?></h3>
                <div style="font-size: 32px; font-weight: bold; color: #f59e0b;">€<?php echo esc_html(number_format($analytics['total_revenue'], 2)); ?></div>
                <small style="color: #6b7280;"><?php echo esc_html(sprintf(rbf_translate_string('Media: €%.2f per prenotazione'), $analytics['avg_revenue_per_booking'])); ?></small>
            </div>
            
            <div class="rbf-metric-card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3 style="margin: 0 0 10px 0; color: #1f2937; font-size: 16px;"><?php echo esc_html(rbf_translate_string('Tasso Completamento')); ?></h3>
                <div style="font-size: 32px; font-weight: bold; color: #8b5cf6;"><?php echo esc_html(number_format($analytics['completion_rate'], 1)); ?>%</div>
                <small style="color: #6b7280;"><?php echo esc_html(sprintf(rbf_translate_string('%d completate su %d confermate'), $analytics['completed_bookings'], $analytics['confirmed_bookings'])); ?></small>
            </div>
        </div>
        
        <!-- Charts Row -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
            <!-- Bookings by Status Chart -->
            <div class="rbf-chart-container" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3 style="margin: 0 0 20px 0;"><?php echo esc_html(rbf_translate_string('Prenotazioni per Stato')); ?></h3>
                <canvas id="statusChart" width="400" height="200"></canvas>
            </div>
            
            <!-- Bookings by Meal Type Chart -->
            <div class="rbf-chart-container" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3 style="margin: 0 0 20px 0;"><?php echo esc_html(rbf_translate_string('Prenotazioni per Servizio')); ?></h3>
                <canvas id="mealChart" width="400" height="200"></canvas>
            </div>
        </div>
        
        <!-- Daily Bookings Chart -->
        <div class="rbf-chart-container" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 30px;">
            <h3 style="margin: 0 0 20px 0;"><?php echo esc_html(rbf_translate_string('Andamento Prenotazioni Giornaliere')); ?></h3>
            <canvas id="dailyChart" width="800" height="300"></canvas>
        </div>
        
        <!-- Source Attribution Analysis -->
        <div class="rbf-chart-container" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h3 style="margin: 0 0 20px 0;"><?php echo esc_html(rbf_translate_string('Analisi Sorgenti di Traffico')); ?></h3>
            <canvas id="sourceChart" width="800" height="300"></canvas>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const statusData = <?php echo wp_json_encode($analytics['by_status']); ?>;
        const mealData = <?php echo wp_json_encode($analytics['by_meal']); ?>;
        const dailyData = <?php echo wp_json_encode($analytics['daily_bookings']); ?>;
        const sourceData = <?php echo wp_json_encode($analytics['by_source']); ?>;
        
        // Status Chart
        new Chart(document.getElementById('statusChart'), {
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
            }
        });
        
        // Meal Chart
        new Chart(document.getElementById('mealChart'), {
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
                }
            }
        });
        
        // Daily Chart
        new Chart(document.getElementById('dailyChart'), {
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
                }
            }
        });
        
        // Source Chart
        new Chart(document.getElementById('sourceChart'), {
            type: 'bar',
            data: {
                labels: Object.keys(sourceData),
                datasets: [{
                    label: '<?php echo esc_js(rbf_translate_string('Prenotazioni')); ?>',
                    data: Object.values(sourceData),
                    backgroundColor: ['#3b82f6', '#f59e0b', '#10b981', '#ef4444', '#8b5cf6', '#6b7280'],
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
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
function rbf_get_booking_analytics($start_date, $end_date) {
    global $wpdb;
    
    // Get all bookings in date range
    $bookings = $wpdb->get_results($wpdb->prepare(
        "SELECT p.ID, pm_date.meta_value as booking_date, pm_people.meta_value as people,
                COALESCE(pm_meal.meta_value, pm_meal_legacy.meta_value) as meal, pm_status.meta_value as status,
                pm_source.meta_value as source, pm_bucket.meta_value as bucket,
                pm_value_tot.meta_value as booking_value, pm_value_pp.meta_value as booking_unit_value
         FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'rbf_data'
         LEFT JOIN {$wpdb->postmeta} pm_people ON p.ID = pm_people.post_id AND pm_people.meta_key = 'rbf_persone'
         LEFT JOIN {$wpdb->postmeta} pm_meal ON p.ID = pm_meal.post_id AND pm_meal.meta_key = 'rbf_meal'
         LEFT JOIN {$wpdb->postmeta} pm_meal_legacy ON p.ID = pm_meal_legacy.post_id AND pm_meal_legacy.meta_key = 'rbf_orario'
         LEFT JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = 'rbf_booking_status'
         LEFT JOIN {$wpdb->postmeta} pm_source ON p.ID = pm_source.post_id AND pm_source.meta_key = 'rbf_source'
         LEFT JOIN {$wpdb->postmeta} pm_bucket ON p.ID = pm_bucket.post_id AND pm_bucket.meta_key = 'rbf_source_bucket'
         LEFT JOIN {$wpdb->postmeta} pm_value_tot ON p.ID = pm_value_tot.post_id AND pm_value_tot.meta_key = 'rbf_valore_tot'
         LEFT JOIN {$wpdb->postmeta} pm_value_pp ON p.ID = pm_value_pp.post_id AND pm_value_pp.meta_key = 'rbf_valore_pp'
         WHERE p.post_type = 'rbf_booking' AND p.post_status = 'publish'
         AND pm_date.meta_value >= %s AND pm_date.meta_value <= %s
         ORDER BY pm_date.meta_value ASC",
        $start_date, $end_date
    ));
    
    $options = rbf_get_settings();
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
        'daily_bookings' => []
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
    
    // Process each booking
    foreach ($bookings as $booking) {
        $people = intval($booking->people ?: 0);
        $meal = $booking->meal ?: 'pranzo';
        $status = $booking->status ?: 'confirmed';
        $source = $booking->source ?: 'direct';
        $bucket = $booking->bucket ?: 'direct';
        
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
        
        // Source tracking
        $source_label = ucfirst($bucket ?: $source);
        if (!isset($analytics['by_source'][$source_label])) {
            $analytics['by_source'][$source_label] = 0;
        }
        $analytics['by_source'][$source_label]++;
        
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
    
    // Sort sources by count
    arsort($analytics['by_source']);
    
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
    <div class="rbf-admin-wrap">
        <h1><?php echo esc_html(rbf_translate_string('Esporta Dati Prenotazioni')); ?></h1>
        
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <form method="post">
                <?php wp_nonce_field('rbf_export'); ?>
                
                <table class="form-table">
                    <tr>
                        <th><label for="start_date"><?php echo esc_html(rbf_translate_string('Data Inizio')); ?></label></th>
                        <td><input type="date" id="start_date" name="start_date" value="<?php echo esc_attr($default_start); ?>" required></td>
                    </tr>
                    <tr>
                        <th><label for="end_date"><?php echo esc_html(rbf_translate_string('Data Fine')); ?></label></th>
                        <td><input type="date" id="end_date" name="end_date" value="<?php echo esc_attr($default_end); ?>" required></td>
                    </tr>
                    <tr>
                        <th><label for="status_filter"><?php echo esc_html(rbf_translate_string('Filtra per Stato')); ?></label></th>
                        <td>
                            <select id="status_filter" name="status_filter">
                                <option value=""><?php echo esc_html(rbf_translate_string('Tutti gli stati')); ?></option>
                                <?php
                                $statuses = rbf_get_booking_statuses();
                                foreach ($statuses as $key => $label) {
                                    echo '<option value="' . esc_attr($key) . '">' . esc_html($label) . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="format"><?php echo esc_html(rbf_translate_string('Formato Export')); ?></label></th>
                        <td>
                            <select id="format" name="format">
                                <option value="csv">CSV (Excel)</option>
                                <option value="json">JSON</option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="export_bookings" class="button button-primary" value="<?php echo esc_attr(rbf_translate_string('Esporta Prenotazioni')); ?>">
                </p>
            </form>
        </div>
        
        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 20px;">
            <h3><?php echo esc_html(rbf_translate_string('Informazioni Export')); ?></h3>
            <p><?php echo esc_html(rbf_translate_string('L\'export includerà tutti i dati delle prenotazioni nel periodo selezionato:')); ?></p>
            <ul>
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
    ?>
    
    <div class="wrap">
        <h1><?php echo esc_html(rbf_translate_string('Gestione Tavoli')); ?></h1>
        
        <div class="nav-tab-wrapper">
            <a href="#areas" class="nav-tab nav-tab-active" onclick="switchTab(event, 'areas')"><?php echo esc_html(rbf_translate_string('Aree')); ?></a>
            <a href="#tables" class="nav-tab" onclick="switchTab(event, 'tables')"><?php echo esc_html(rbf_translate_string('Tavoli')); ?></a>
            <a href="#groups" class="nav-tab" onclick="switchTab(event, 'groups')"><?php echo esc_html(rbf_translate_string('Gruppi Unibili')); ?></a>
            <a href="#overview" class="nav-tab" onclick="switchTab(event, 'overview')"><?php echo esc_html(rbf_translate_string('Panoramica')); ?></a>
        </div>
        
        <!-- Areas Tab -->
        <div id="areas" class="tab-content">
            <h2><?php echo esc_html(rbf_translate_string('Gestione Aree')); ?></h2>
            
            <div class="postbox" style="margin-top: 20px;">
                <div class="postbox-header">
                    <h3><?php echo esc_html(rbf_translate_string('Aggiungi Nuova Area')); ?></h3>
                </div>
                <div class="inside">
                    <form method="post">
                        <?php wp_nonce_field('rbf_table_management', 'rbf_nonce'); ?>
                        <input type="hidden" name="action" value="add_area">
                        
                        <table class="form-table">
                            <tr>
                                <th><label for="area_name"><?php echo esc_html(rbf_translate_string('Nome Area')); ?></label></th>
                                <td><input type="text" id="area_name" name="area_name" class="regular-text" required></td>
                            </tr>
                            <tr>
                                <th><label for="area_description"><?php echo esc_html(rbf_translate_string('Descrizione')); ?></label></th>
                                <td><textarea id="area_description" name="area_description" class="large-text" rows="3"></textarea></td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" class="button button-primary" value="<?php echo esc_attr(rbf_translate_string('Aggiungi Area')); ?>">
                        </p>
                    </form>
                </div>
            </div>
            
            <div class="postbox">
                <div class="postbox-header">
                    <h3><?php echo esc_html(rbf_translate_string('Aree Esistenti')); ?></h3>
                </div>
                <div class="inside">
                    <?php if (!empty($areas)): ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php echo esc_html(rbf_translate_string('Nome')); ?></th>
                                    <th><?php echo esc_html(rbf_translate_string('Descrizione')); ?></th>
                                    <th><?php echo esc_html(rbf_translate_string('Tavoli')); ?></th>
                                    <th><?php echo esc_html(rbf_translate_string('Creata')); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($areas as $area): ?>
                                    <?php $area_tables = rbf_get_tables_by_area($area->id); ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($area->name); ?></strong></td>
                                        <td><?php echo esc_html($area->description ?: '-'); ?></td>
                                        <td><?php echo count($area_tables); ?> tavoli</td>
                                        <td><?php echo esc_html(date('d/m/Y', strtotime($area->created_at))); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p><?php echo esc_html(rbf_translate_string('Nessuna area configurata.')); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Tables Tab -->
        <div id="tables" class="tab-content" style="display: none;">
            <h2><?php echo esc_html(rbf_translate_string('Gestione Tavoli')); ?></h2>
            
            <div class="postbox" style="margin-top: 20px;">
                <div class="postbox-header">
                    <h3><?php echo esc_html(rbf_translate_string('Aggiungi Nuovo Tavolo')); ?></h3>
                </div>
                <div class="inside">
                    <form method="post">
                        <?php wp_nonce_field('rbf_table_management', 'rbf_nonce'); ?>
                        <input type="hidden" name="action" value="add_table">
                        
                        <table class="form-table">
                            <tr>
                                <th><label for="table_area_id"><?php echo esc_html(rbf_translate_string('Area')); ?></label></th>
                                <td>
                                    <select id="table_area_id" name="table_area_id" required>
                                        <option value=""><?php echo esc_html(rbf_translate_string('Seleziona Area')); ?></option>
                                        <?php foreach ($areas as $area): ?>
                                            <option value="<?php echo esc_attr($area->id); ?>"><?php echo esc_html($area->name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="table_name"><?php echo esc_html(rbf_translate_string('Nome Tavolo')); ?></label></th>
                                <td><input type="text" id="table_name" name="table_name" class="regular-text" required placeholder="es: T1, Tavolo 1"></td>
                            </tr>
                            <tr>
                                <th><label for="table_capacity"><?php echo esc_html(rbf_translate_string('Capacità Standard')); ?></label></th>
                                <td><input type="number" id="table_capacity" name="table_capacity" min="1" max="20" required value="4"></td>
                            </tr>
                            <tr>
                                <th><label for="table_min_capacity"><?php echo esc_html(rbf_translate_string('Capacità Minima')); ?></label></th>
                                <td><input type="number" id="table_min_capacity" name="table_min_capacity" min="1" placeholder="Auto"></td>
                            </tr>
                            <tr>
                                <th><label for="table_max_capacity"><?php echo esc_html(rbf_translate_string('Capacità Massima')); ?></label></th>
                                <td><input type="number" id="table_max_capacity" name="table_max_capacity" min="1" placeholder="Auto"></td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" class="button button-primary" value="<?php echo esc_attr(rbf_translate_string('Aggiungi Tavolo')); ?>">
                        </p>
                    </form>
                </div>
            </div>
            
            <div class="postbox">
                <div class="postbox-header">
                    <h3><?php echo esc_html(rbf_translate_string('Tavoli Esistenti')); ?></h3>
                </div>
                <div class="inside">
                    <?php if (!empty($all_tables)): ?>
                        <table class="wp-list-table widefat fixed striped">
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
                                <?php foreach ($all_tables as $table): ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($table->name); ?></strong></td>
                                        <td><?php echo esc_html($table->area_name); ?></td>
                                        <td><?php echo esc_html($table->capacity); ?> persone</td>
                                        <td><?php echo esc_html($table->min_capacity . '-' . $table->max_capacity); ?> persone</td>
                                        <td>
                                            <?php if ($table->is_active): ?>
                                                <span style="color: green;">●</span> Attivo
                                            <?php else: ?>
                                                <span style="color: red;">●</span> Inattivo
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p><?php echo esc_html(rbf_translate_string('Nessun tavolo configurato.')); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Groups Tab -->
        <div id="groups" class="tab-content" style="display: none;">
            <h2><?php echo esc_html(rbf_translate_string('Gestione Gruppi Tavoli Unibili')); ?></h2>
            
            <div class="postbox" style="margin-top: 20px;">
                <div class="postbox-header">
                    <h3><?php echo esc_html(rbf_translate_string('Aggiungi Gruppo di Tavoli')); ?></h3>
                </div>
                <div class="inside">
                    <form method="post">
                        <?php wp_nonce_field('rbf_table_management', 'rbf_nonce'); ?>
                        <input type="hidden" name="action" value="add_group">
                        
                        <table class="form-table">
                            <tr>
                                <th><label for="group_area_id"><?php echo esc_html(rbf_translate_string('Area')); ?></label></th>
                                <td>
                                    <select id="group_area_id" name="group_area_id" required onchange="updateGroupTables()">
                                        <option value=""><?php echo esc_html(rbf_translate_string('Seleziona Area')); ?></option>
                                        <?php foreach ($areas as $area): ?>
                                            <option value="<?php echo esc_attr($area->id); ?>"><?php echo esc_html($area->name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="group_name"><?php echo esc_html(rbf_translate_string('Nome Gruppo')); ?></label></th>
                                <td><input type="text" id="group_name" name="group_name" class="regular-text" required placeholder="es: Tavoli Piccoli Sala"></td>
                            </tr>
                            <tr>
                                <th><label for="group_max_capacity"><?php echo esc_html(rbf_translate_string('Capacità Massima Combinata')); ?></label></th>
                                <td><input type="number" id="group_max_capacity" name="group_max_capacity" min="1" value="16"></td>
                            </tr>
                            <tr>
                                <th><label><?php echo esc_html(rbf_translate_string('Tavoli nel Gruppo')); ?></label></th>
                                <td>
                                    <div id="group_tables_selection">
                                        <p><em><?php echo esc_html(rbf_translate_string('Seleziona prima un\'area per visualizzare i tavoli disponibili.')); ?></em></p>
                                    </div>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" class="button button-primary" value="<?php echo esc_attr(rbf_translate_string('Aggiungi Gruppo')); ?>">
                        </p>
                    </form>
                </div>
            </div>
            
            <div class="postbox">
                <div class="postbox-header">
                    <h3><?php echo esc_html(rbf_translate_string('Gruppi Esistenti')); ?></h3>
                </div>
                <div class="inside">
                    <?php 
                    $all_groups = [];
                    foreach ($areas as $area) {
                        $area_groups = rbf_get_table_groups_by_area($area->id);
                        foreach ($area_groups as $group) {
                            $group->area_name = $area->name;
                            $group->tables = rbf_get_group_tables($group->id);
                            $all_groups[] = $group;
                        }
                    }
                    ?>
                    
                    <?php if (!empty($all_groups)): ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php echo esc_html(rbf_translate_string('Nome Gruppo')); ?></th>
                                    <th><?php echo esc_html(rbf_translate_string('Area')); ?></th>
                                    <th><?php echo esc_html(rbf_translate_string('Tavoli')); ?></th>
                                    <th><?php echo esc_html(rbf_translate_string('Capacità Max')); ?></th>
                                    <th><?php echo esc_html(rbf_translate_string('Stato')); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_groups as $group): ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($group->name); ?></strong></td>
                                        <td><?php echo esc_html($group->area_name); ?></td>
                                        <td>
                                            <?php 
                                            $table_names = array_map(function($t) { return $t->name; }, $group->tables);
                                            echo esc_html(implode(', ', $table_names));
                                            ?>
                                        </td>
                                        <td><?php echo esc_html($group->max_combined_capacity); ?> persone</td>
                                        <td>
                                            <?php if ($group->is_active): ?>
                                                <span style="color: green;">●</span> Attivo
                                            <?php else: ?>
                                                <span style="color: red;">●</span> Inattivo
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p><?php echo esc_html(rbf_translate_string('Nessun gruppo configurato.')); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Overview Tab -->
        <div id="overview" class="tab-content" style="display: none;">
            <h2><?php echo esc_html(rbf_translate_string('Panoramica Sistema Tavoli')); ?></h2>
            
            <div class="postbox" style="margin-top: 20px;">
                <div class="postbox-header">
                    <h3><?php echo esc_html(rbf_translate_string('Statistiche Generali')); ?></h3>
                </div>
                <div class="inside">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                        <div style="background: #f0f8ff; padding: 20px; border-radius: 8px; text-align: center;">
                            <h3 style="margin: 0; color: #2271b1;"><?php echo count($areas); ?></h3>
                            <p style="margin: 5px 0 0 0;"><?php echo esc_html(rbf_translate_string('Aree Totali')); ?></p>
                        </div>
                        <div style="background: #f0fff0; padding: 20px; border-radius: 8px; text-align: center;">
                            <h3 style="margin: 0; color: #00a32a;"><?php echo count($all_tables); ?></h3>
                            <p style="margin: 5px 0 0 0;"><?php echo esc_html(rbf_translate_string('Tavoli Totali')); ?></p>
                        </div>
                        <div style="background: #fff8f0; padding: 20px; border-radius: 8px; text-align: center;">
                            <h3 style="margin: 0; color: #dba617;"><?php echo count($all_groups); ?></h3>
                            <p style="margin: 5px 0 0 0;"><?php echo esc_html(rbf_translate_string('Gruppi Unibili')); ?></p>
                        </div>
                        <div style="background: #f8f0ff; padding: 20px; border-radius: 8px; text-align: center;">
                            <h3 style="margin: 0; color: #8c44ad;"><?php echo array_sum(array_column($all_tables, 'capacity')); ?></h3>
                            <p style="margin: 5px 0 0 0;"><?php echo esc_html(rbf_translate_string('Capacità Totale')); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="postbox">
                <div class="postbox-header">
                    <h3><?php echo esc_html(rbf_translate_string('Algoritmo di Assegnazione')); ?></h3>
                </div>
                <div class="inside">
                    <p><strong><?php echo esc_html(rbf_translate_string('Strategia First-Fit Implementata:')); ?></strong></p>
                    <ol>
                        <li><?php echo esc_html(rbf_translate_string('Ricerca tavolo singolo ottimale (capacità minima sufficiente)')); ?></li>
                        <li><?php echo esc_html(rbf_translate_string('Se non disponibile, ricerca combinazioni di tavoli unibili')); ?></li>
                        <li><?php echo esc_html(rbf_translate_string('Ordinamento per area e capacità per ottimizzazione')); ?></li>
                        <li><?php echo esc_html(rbf_translate_string('Supporto per tavoli joined con limite capacità gruppo')); ?></li>
                    </ol>
                    <p><em><?php echo esc_html(rbf_translate_string('L\'assegnazione automatica avviene al momento della prenotazione e può gestire split/merge intelligente.')); ?></em></p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    function switchTab(evt, tabName) {
        var i, tabcontent, tablinks;
        tabcontent = document.getElementsByClassName("tab-content");
        for (i = 0; i < tabcontent.length; i++) {
            tabcontent[i].style.display = "none";
        }
        tablinks = document.getElementsByClassName("nav-tab");
        for (i = 0; i < tablinks.length; i++) {
            tablinks[i].classList.remove("nav-tab-active");
        }
        document.getElementById(tabName).style.display = "block";
        evt.currentTarget.classList.add("nav-tab-active");
        
        // Update URL hash without triggering scroll
        history.replaceState(null, null, '#' + tabName);
    }
    
    // Load tab from URL hash on page load
    document.addEventListener('DOMContentLoaded', function() {
        var hash = window.location.hash.substring(1);
        if (hash && document.getElementById(hash)) {
            var tabLink = document.querySelector('a[href="#' + hash + '"]');
            if (tabLink) {
                tabLink.click();
            }
        }
    });
    
    function updateGroupTables() {
        var areaId = document.getElementById('group_area_id').value;
        var selectionDiv = document.getElementById('group_tables_selection');
        
        if (!areaId) {
            selectionDiv.innerHTML = '<p><em><?php echo esc_js(rbf_translate_string('Seleziona prima un\'area per visualizzare i tavoli disponibili.')); ?></em></p>';
            return;
        }
        
        // Get tables for selected area
        var tables = <?php echo wp_json_encode($all_tables); ?>;
        var areaTables = tables.filter(function(table) {
            return table.area_id == areaId;
        });
        
        if (areaTables.length === 0) {
            selectionDiv.innerHTML = '<p><em><?php echo esc_js(rbf_translate_string('Nessun tavolo disponibile in quest\'area.')); ?></em></p>';
            return;
        }
        
        var html = '<div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;">';
        areaTables.forEach(function(table) {
            html += '<label style="display: block; margin-bottom: 8px;">';
            html += '<input type="checkbox" name="group_tables[]" value="' + table.id + '"> ';
            html += table.name + ' (' + table.capacity + ' persone)';
            html += '</label>';
        });
        html += '</div>';
        html += '<p class="description"><?php echo esc_js(rbf_translate_string('Seleziona i tavoli che possono essere uniti in questo gruppo.')); ?></p>';
        
        selectionDiv.innerHTML = html;
    }
    </script>
    
    <style>
    .tab-content {
        margin-top: 20px;
    }
    .nav-tab-wrapper {
        margin-bottom: 0;
    }
    .postbox .inside {
        margin: 0;
        padding: 20px;
    }
    .wp-list-table th,
    .wp-list-table td {
        padding: 12px;
    }
    </style>
    
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
    ?>
    
    <div class="rbf-admin-wrap">
        <h1><?php echo esc_html(rbf_translate_string('Sistema Email Failover')); ?></h1>
        
        <!-- Statistics Dashboard -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3 style="margin: 0 0 10px 0; color: #2271b1;"><?php echo esc_html(rbf_translate_string('Notifiche Totali')); ?></h3>
                <div style="font-size: 32px; font-weight: bold; color: #2271b1;"><?php echo esc_html($stats_summary['total']); ?></div>
                <div style="font-size: 14px; color: #666;">Ultimi <?php echo esc_html($filter_days); ?> giorni</div>
            </div>
            
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3 style="margin: 0 0 10px 0; color: #00a32a;"><?php echo esc_html(rbf_translate_string('Tasso di Successo')); ?></h3>
                <div style="font-size: 32px; font-weight: bold; color: #00a32a;"><?php echo number_format($stats_summary['success_rate'], 1); ?>%</div>
                <div style="font-size: 14px; color: #666;">
                    <?php echo esc_html($stats_summary['success'] + $stats_summary['fallback_success']); ?> 
                    / <?php echo esc_html($stats_summary['total']); ?> inviate
                </div>
            </div>
            
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3 style="margin: 0 0 10px 0; color: #dba617;"><?php echo esc_html(rbf_translate_string('Uso Fallback')); ?></h3>
                <div style="font-size: 32px; font-weight: bold; color: #dba617;"><?php echo number_format($stats_summary['fallback_rate'], 1); ?>%</div>
                <div style="font-size: 14px; color: #666;">
                    <?php echo esc_html($stats_summary['fallback_success']); ?> tramite wp_mail
                </div>
            </div>
            
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3 style="margin: 0 0 10px 0; color: #d63638;"><?php echo esc_html(rbf_translate_string('Notifiche Fallite')); ?></h3>
                <div style="font-size: 32px; font-weight: bold; color: #d63638;"><?php echo esc_html($stats_summary['failed']); ?></div>
                <div style="font-size: 14px; color: #666;">
                    Richiedono attenzione
                </div>
            </div>
        </div>
        
        <!-- Provider Usage Chart -->
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 30px;">
            <h3><?php echo esc_html(rbf_translate_string('Utilizzo Provider Email')); ?></h3>
            <div style="display: flex; gap: 30px; align-items: center;">
                <div style="flex: 1;">
                    <div style="background: #f0f8ff; padding: 15px; border-radius: 6px; margin-bottom: 10px;">
                        <strong>Brevo (Primario):</strong> <?php echo esc_html($stats_summary['by_provider']['brevo']); ?> notifiche
                        <div style="background: #2271b1; height: 8px; border-radius: 4px; width: <?php echo $stats_summary['total'] > 0 ? ($stats_summary['by_provider']['brevo'] / $stats_summary['total']) * 100 : 0; ?>%; margin-top: 5px;"></div>
                    </div>
                    <div style="background: #fff8f0; padding: 15px; border-radius: 6px;">
                        <strong>wp_mail (Fallback):</strong> <?php echo esc_html($stats_summary['by_provider']['wp_mail']); ?> notifiche
                        <div style="background: #dba617; height: 8px; border-radius: 4px; width: <?php echo $stats_summary['total'] > 0 ? ($stats_summary['by_provider']['wp_mail'] / $stats_summary['total']) * 100 : 0; ?>%; margin-top: 5px;"></div>
                    </div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 14px; color: #666; margin-bottom: 10px;">Sistema Funzionante</div>
                    <div style="width: 60px; height: 60px; border-radius: 50%; background: <?php echo $stats_summary['success_rate'] >= 90 ? '#00a32a' : ($stats_summary['success_rate'] >= 75 ? '#dba617' : '#d63638'); ?>; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                        <?php echo $stats_summary['success_rate'] >= 90 ? '✓' : ($stats_summary['success_rate'] >= 75 ? '!' : '✗'); ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px;">
            <form method="get">
                <input type="hidden" name="page" value="rbf_email_notifications">
                <div style="display: flex; gap: 15px; align-items: end; flex-wrap: wrap;">
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;"><?php echo esc_html(rbf_translate_string('Stato')); ?></label>
                        <select name="filter_status">
                            <option value=""><?php echo esc_html(rbf_translate_string('Tutti gli stati')); ?></option>
                            <option value="success" <?php selected($filter_status, 'success'); ?>><?php echo esc_html(rbf_translate_string('Successo')); ?></option>
                            <option value="fallback_success" <?php selected($filter_status, 'fallback_success'); ?>><?php echo esc_html(rbf_translate_string('Fallback Successo')); ?></option>
                            <option value="failed" <?php selected($filter_status, 'failed'); ?>><?php echo esc_html(rbf_translate_string('Fallito')); ?></option>
                            <option value="pending" <?php selected($filter_status, 'pending'); ?>><?php echo esc_html(rbf_translate_string('In Attesa')); ?></option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;"><?php echo esc_html(rbf_translate_string('Tipo')); ?></label>
                        <select name="filter_type">
                            <option value=""><?php echo esc_html(rbf_translate_string('Tutti i tipi')); ?></option>
                            <option value="admin_notification" <?php selected($filter_type, 'admin_notification'); ?>><?php echo esc_html(rbf_translate_string('Notifica Admin')); ?></option>
                            <option value="customer_notification" <?php selected($filter_type, 'customer_notification'); ?>><?php echo esc_html(rbf_translate_string('Notifica Cliente')); ?></option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;"><?php echo esc_html(rbf_translate_string('Periodo')); ?></label>
                        <select name="filter_days">
                            <option value="1" <?php selected($filter_days, 1); ?>><?php echo esc_html(rbf_translate_string('Ultimo giorno')); ?></option>
                            <option value="7" <?php selected($filter_days, 7); ?>><?php echo esc_html(rbf_translate_string('Ultimi 7 giorni')); ?></option>
                            <option value="30" <?php selected($filter_days, 30); ?>><?php echo esc_html(rbf_translate_string('Ultimi 30 giorni')); ?></option>
                        </select>
                    </div>
                    <button type="submit" class="button"><?php echo esc_html(rbf_translate_string('Filtra')); ?></button>
                    <a href="<?php echo admin_url('admin.php?page=rbf_email_notifications'); ?>" class="button"><?php echo esc_html(rbf_translate_string('Reset')); ?></a>
                </div>
            </form>
        </div>
        
        <!-- Notification Logs -->
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h3><?php echo esc_html(rbf_translate_string('Log Notifiche Email')); ?></h3>
            
            <?php if (!empty($logs)): ?>
                <div style="overflow-x: auto;">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th style="width: 80px;"><?php echo esc_html(rbf_translate_string('ID')); ?></th>
                                <th style="width: 100px;"><?php echo esc_html(rbf_translate_string('Prenotazione')); ?></th>
                                <th style="width: 120px;"><?php echo esc_html(rbf_translate_string('Tipo')); ?></th>
                                <th><?php echo esc_html(rbf_translate_string('Destinatario')); ?></th>
                                <th style="width: 100px;"><?php echo esc_html(rbf_translate_string('Provider')); ?></th>
                                <th style="width: 120px;"><?php echo esc_html(rbf_translate_string('Stato')); ?></th>
                                <th style="width: 60px;"><?php echo esc_html(rbf_translate_string('Tentativi')); ?></th>
                                <th style="width: 150px;"><?php echo esc_html(rbf_translate_string('Data/Ora')); ?></th>
                                <th style="width: 100px;"><?php echo esc_html(rbf_translate_string('Azioni')); ?></th>
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
                                    <td style="word-break: break-all;">
                                        <?php echo esc_html($log->recipient_email); ?>
                                    </td>
                                    <td>
                                        <span style="padding: 3px 8px; border-radius: 3px; font-size: 12px; font-weight: bold; color: white; background: <?php echo $log->provider_used === 'brevo' ? '#2271b1' : '#dba617'; ?>;">
                                            <?php echo esc_html(ucfirst($log->provider_used)); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $status_colors = [
                                            'success' => '#00a32a',
                                            'fallback_success' => '#dba617',
                                            'failed' => '#d63638',
                                            'pending' => '#666'
                                        ];
                                        $status_labels = [
                                            'success' => 'Successo',
                                            'fallback_success' => 'Fallback OK',
                                            'failed' => 'Fallito',
                                            'pending' => 'In Attesa'
                                        ];
                                        $color = $status_colors[$log->status] ?? '#666';
                                        $label = $status_labels[$log->status] ?? $log->status;
                                        ?>
                                        <span style="padding: 3px 8px; border-radius: 3px; font-size: 12px; font-weight: bold; color: white; background: <?php echo esc_attr($color); ?>;">
                                            <?php echo esc_html($label); ?>
                                        </span>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php echo esc_html($log->attempt_number); ?>
                                    </td>
                                    <td>
                                        <div style="font-size: 12px;">
                                            <?php echo esc_html(date('d/m/Y H:i', strtotime($log->attempted_at))); ?>
                                        </div>
                                        <?php if ($log->completed_at && $log->completed_at !== $log->attempted_at): ?>
                                            <div style="font-size: 11px; color: #666;">
                                                <?php echo esc_html(rbf_translate_string('Completato')); ?>: <?php echo esc_html(date('H:i', strtotime($log->completed_at))); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 5px;">
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
                <p style="text-align: center; color: #666; font-style: italic; padding: 40px;">
                    <?php echo esc_html(rbf_translate_string('Nessuna notifica trovata con i filtri selezionati.')); ?>
                </p>
            <?php endif; ?>
        </div>
        
        <!-- Configuration Help -->
        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-top: 30px;">
            <h3><?php echo esc_html(rbf_translate_string('Configurazione Sistema Failover')); ?></h3>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                <div>
                    <h4><?php echo esc_html(rbf_translate_string('Provider Primario (Brevo)')); ?></h4>
                    <ul style="margin: 10px 0; padding-left: 20px;">
                        <li><?php echo esc_html(rbf_translate_string('Automazioni clienti (liste e eventi)')); ?></li>
                        <li><?php echo esc_html(rbf_translate_string('Email transazionali admin')); ?></li>
                        <li><?php echo esc_html(rbf_translate_string('Supporto multilingua')); ?></li>
                        <li><?php echo esc_html(rbf_translate_string('Analytics avanzate')); ?></li>
                    </ul>
                </div>
                
                <div>
                    <h4><?php echo esc_html(rbf_translate_string('Provider Fallback (wp_mail)')); ?></h4>
                    <ul style="margin: 10px 0; padding-left: 20px;">
                        <li><?php echo esc_html(rbf_translate_string('Solo notifiche admin')); ?></li>
                        <li><?php echo esc_html(rbf_translate_string('Attivazione automatica su errore Brevo')); ?></li>
                        <li><?php echo esc_html(rbf_translate_string('Configurazione SMTP WordPress')); ?></li>
                        <li><?php echo esc_html(rbf_translate_string('Backup affidabile')); ?></li>
                    </ul>
                </div>
            </div>
            
            <div style="background: white; padding: 15px; border-radius: 6px; margin-top: 20px;">
                <strong><?php echo esc_html(rbf_translate_string('Stato Configurazione Attuale:')); ?></strong>
                <div style="margin-top: 10px;">
                    <?php
                    $options = rbf_get_settings();
                    $brevo_configured = !empty($options['brevo_api']);
                    $emails_configured = !empty($options['notification_email']) || !empty($options['webmaster_email']);
                    ?>
                    
                    <div style="display: flex; align-items: center; margin-bottom: 8px;">
                        <span style="color: <?php echo $brevo_configured ? '#00a32a' : '#d63638'; ?>; margin-right: 10px;">
                            <?php echo $brevo_configured ? '✓' : '✗'; ?>
                        </span>
                        <span>
                            <?php echo esc_html(rbf_translate_string('Brevo API configurata')); ?>
                            <?php if (!$brevo_configured): ?>
                                - <a href="<?php echo admin_url('admin.php?page=rbf_settings'); ?>"><?php echo esc_html(rbf_translate_string('Configura ora')); ?></a>
                            <?php endif; ?>
                        </span>
                    </div>
                    
                    <div style="display: flex; align-items: center;">
                        <span style="color: <?php echo $emails_configured ? '#00a32a' : '#d63638'; ?>; margin-right: 10px;">
                            <?php echo $emails_configured ? '✓' : '✗'; ?>
                        </span>
                        <span>
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
    
    <style>
    .rbf-admin-wrap h1 {
        margin-bottom: 20px;
    }
    
    .rbf-admin-wrap table th,
    .rbf-admin-wrap table td {
        padding: 12px 8px;
        vertical-align: middle;
    }
    
    .rbf-admin-wrap .button-small {
        padding: 3px 8px;
        font-size: 11px;
        line-height: 1.2;
        min-height: auto;
    }
    
    @media (max-width: 768px) {
        .rbf-admin-wrap table {
            font-size: 12px;
        }
        .rbf-admin-wrap table th,
        .rbf-admin-wrap table td {
            padding: 8px 4px;
        }
    }
    </style>
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
    
    <div class="rbf-admin-wrap">
        <h1><?php echo esc_html(rbf_translate_string('Validazione Sistema Tracking')); ?></h1>
        
        <!-- Configuration Overview -->
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 30px;">
            <h2><?php echo esc_html(rbf_translate_string('Panoramica Configurazione')); ?></h2>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                <div>
                    <h3><?php echo esc_html(rbf_translate_string('Google Analytics 4')); ?></h3>
                    <div style="margin-bottom: 10px;">
                        <strong><?php echo esc_html(rbf_translate_string('ID Misurazione')); ?>:</strong>
                        <code><?php echo esc_html($options['ga4_id'] ?: 'Non configurato'); ?></code>
                    </div>
                    <div style="margin-bottom: 10px;">
                        <strong><?php echo esc_html(rbf_translate_string('API Secret')); ?>:</strong>
                        <code><?php echo esc_html($options['ga4_api_secret'] ? 'Configurato' : 'Non configurato'); ?></code>
                    </div>
                </div>
                
                <div>
                    <h3><?php echo esc_html(rbf_translate_string('Google Tag Manager')); ?></h3>
                    <div style="margin-bottom: 10px;">
                        <strong><?php echo esc_html(rbf_translate_string('Container ID')); ?>:</strong>
                        <code><?php echo esc_html($options['gtm_id'] ?: 'Non configurato'); ?></code>
                    </div>
                    <div style="margin-bottom: 10px;">
                        <strong><?php echo esc_html(rbf_translate_string('Modalità Ibrida')); ?>:</strong>
                        <code><?php echo esc_html(($options['gtm_hybrid'] === 'yes') ? 'Attiva' : 'Disattiva'); ?></code>
                    </div>
                </div>
                
                <div>
                    <h3><?php echo esc_html(rbf_translate_string('Meta Pixel')); ?></h3>
                    <div style="margin-bottom: 10px;">
                        <strong><?php echo esc_html(rbf_translate_string('Pixel ID')); ?>:</strong>
                        <code><?php echo esc_html($options['meta_pixel_id'] ?: 'Non configurato'); ?></code>
                    </div>
                    <div style="margin-bottom: 10px;">
                        <strong><?php echo esc_html(rbf_translate_string('Access Token (CAPI)')); ?>:</strong>
                        <code><?php echo esc_html($options['meta_access_token'] ? 'Configurato' : 'Non configurato'); ?></code>
                    </div>
                </div>

                <div>
                    <h3><?php echo esc_html(rbf_translate_string('Google Ads')); ?></h3>
                    <div style="margin-bottom: 10px;">
                        <strong><?php echo esc_html(rbf_translate_string('ID Conversione Google Ads')); ?>:</strong>
                        <code><?php echo esc_html($options['google_ads_conversion_id'] ?: rbf_translate_string('Non configurato')); ?></code>
                    </div>
                    <div style="margin-bottom: 10px;">
                        <strong><?php echo esc_html(rbf_translate_string('Etichetta Conversione Google Ads')); ?>:</strong>
                        <code><?php echo esc_html($options['google_ads_conversion_label'] ?: rbf_translate_string('Non configurato')); ?></code>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Validation Results -->
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 30px;">
            <h2><?php echo esc_html(rbf_translate_string('Risultati Validazione')); ?></h2>
            
            <?php foreach ($validation_results as $check_name => $result): ?>
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
        
        <!-- Test Tracking -->
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 30px;">
            <h2><?php echo esc_html(rbf_translate_string('Test Sistema Tracking')); ?></h2>
            
            <?php if ($test_result): ?>
                <div style="padding: 15px; margin-bottom: 20px; border-radius: 6px; background: <?php echo $test_result['success'] ? '#f0f9ff' : '#fff0f0'; ?>; border: 1px solid <?php echo $test_result['success'] ? '#00a32a' : '#d63638'; ?>;">
                    <strong><?php echo esc_html($test_result['success'] ? 'Test Completato' : 'Test Fallito'); ?></strong>
                    <div style="margin-top: 10px;">
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
    
    <style>
    .rbf-admin-wrap h1 {
        margin-bottom: 20px;
    }
    
    .rbf-admin-wrap h2 {
        margin-top: 0;
        margin-bottom: 20px;
        color: #1f2937;
    }
    
    .rbf-admin-wrap h3 {
        margin-top: 0;
        margin-bottom: 15px;
        color: #374151;
    }
    
    .rbf-admin-wrap code {
        background: #f3f4f6;
        padding: 2px 6px;
        border-radius: 3px;
        font-family: monospace;
    }
    </style>
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
