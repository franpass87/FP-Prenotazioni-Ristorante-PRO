<?php
/**
 * Brand profile management (save, apply, import/export) for multi-location setups.
 *
 * @package FP_Prenotazioni_Ristorante_PRO
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Retrieve stored brand profiles.
 *
 * @return array<string, array>
 */
function rbf_get_brand_profiles() {
    $profiles = get_option('rbf_brand_profiles', []);
    return is_array($profiles) ? $profiles : [];
}

/**
 * Persist brand profiles.
 *
 * @param array $profiles Profiles to store.
 * @return void
 */
function rbf_update_brand_profiles(array $profiles) {
    update_option('rbf_brand_profiles', $profiles, false);
}

/**
 * Export profiles to JSON string.
 *
 * @return string
 */
function rbf_export_brand_profiles() {
    $profiles = rbf_get_brand_profiles();
    return wp_json_encode($profiles, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

/**
 * Create a new profile from current settings.
 *
 * @param string $profile_name Display name.
 * @return string Profile ID.
 */
function rbf_save_current_brand_as_profile($profile_name) {
    $settings = rbf_get_settings();
    $profiles = rbf_get_brand_profiles();

    $id = sanitize_title($profile_name);
    if ($id === '') {
        $id = 'profile_' . wp_generate_password(6, false, false);
    }

    $profiles[$id] = [
        'name' => sanitize_text_field($profile_name),
        'saved_at' => current_time('mysql'),
        'settings' => [
            'accent_color' => sanitize_hex_color($settings['accent_color'] ?? '#000000'),
            'secondary_color' => sanitize_hex_color($settings['secondary_color'] ?? '#f8b500'),
            'border_radius' => sanitize_text_field($settings['border_radius'] ?? '8px'),
            'brand_name' => rbf_sanitize_text_strict($settings['brand_name'] ?? ''),
            'brand_logo_id' => absint($settings['brand_logo_id'] ?? 0),
            'brand_logo_url' => esc_url_raw($settings['brand_logo_url'] ?? ''),
            'brand_font_body' => sanitize_key($settings['brand_font_body'] ?? 'system'),
            'brand_font_heading' => sanitize_key($settings['brand_font_heading'] ?? 'system'),
        ],
    ];

    rbf_update_brand_profiles($profiles);

    return $id;
}

/**
 * Apply a saved profile to plugin settings.
 *
 * @param string $profile_id Profile slug.
 * @return bool True when applied.
 */
function rbf_apply_brand_profile($profile_id) {
    $profiles = rbf_get_brand_profiles();
    if (empty($profiles[$profile_id]['settings'])) {
        return false;
    }

    $settings = rbf_get_settings();
    $profile_settings = $profiles[$profile_id]['settings'];

    $settings['accent_color'] = sanitize_hex_color($profile_settings['accent_color'] ?? $settings['accent_color']);
    $settings['secondary_color'] = sanitize_hex_color($profile_settings['secondary_color'] ?? $settings['secondary_color']);
    $settings['border_radius'] = sanitize_text_field($profile_settings['border_radius'] ?? $settings['border_radius']);
    $settings['brand_name'] = rbf_sanitize_text_strict($profile_settings['brand_name'] ?? $settings['brand_name']);
    $settings['brand_logo_id'] = absint($profile_settings['brand_logo_id'] ?? $settings['brand_logo_id']);
    $settings['brand_logo_url'] = esc_url_raw($profile_settings['brand_logo_url'] ?? $settings['brand_logo_url']);
    $settings['brand_font_body'] = sanitize_key($profile_settings['brand_font_body'] ?? $settings['brand_font_body']);
    $settings['brand_font_heading'] = sanitize_key($profile_settings['brand_font_heading'] ?? $settings['brand_font_heading']);
    $settings['brand_profile_active'] = $profile_id;

    update_option('rbf_settings', $settings);
    rbf_invalidate_settings_cache();

    return true;
}

/**
 * Delete a stored profile.
 *
 * @param string $profile_id Profile slug.
 * @return void
 */
function rbf_delete_brand_profile($profile_id) {
    $profiles = rbf_get_brand_profiles();
    if (isset($profiles[$profile_id])) {
        unset($profiles[$profile_id]);
        rbf_update_brand_profiles($profiles);
    }
}

/**
 * Import profiles from JSON payload.
 *
 * @param string $json Raw JSON.
 * @return int Number of profiles imported.
 */
function rbf_import_brand_profiles($json) {
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return 0;
    }

    $profiles = rbf_get_brand_profiles();
    $imported = 0;

    foreach ($decoded as $key => $profile) {
        if (!is_array($profile) || empty($profile['settings'])) {
            continue;
        }
        $id = sanitize_key($key);
        if ($id === '') {
            continue;
        }

        $profiles[$id] = [
            'name' => rbf_sanitize_text_strict($profile['name'] ?? $id),
            'saved_at' => !empty($profile['saved_at']) ? sanitize_text_field($profile['saved_at']) : current_time('mysql'),
            'settings' => [
                'accent_color' => sanitize_hex_color($profile['settings']['accent_color'] ?? '#000000'),
                'secondary_color' => sanitize_hex_color($profile['settings']['secondary_color'] ?? '#f8b500'),
                'border_radius' => sanitize_text_field($profile['settings']['border_radius'] ?? '8px'),
                'brand_name' => rbf_sanitize_text_strict($profile['settings']['brand_name'] ?? ''),
                'brand_logo_id' => absint($profile['settings']['brand_logo_id'] ?? 0),
                'brand_logo_url' => esc_url_raw($profile['settings']['brand_logo_url'] ?? ''),
                'brand_font_body' => sanitize_key($profile['settings']['brand_font_body'] ?? 'system'),
                'brand_font_heading' => sanitize_key($profile['settings']['brand_font_heading'] ?? 'system'),
            ],
        ];
        $imported++;
    }

    rbf_update_brand_profiles($profiles);

    return $imported;
}

// Handle admin form submissions.
add_action('admin_post_rbf_save_brand_profile', function() {
    if (!current_user_can(rbf_get_settings_capability())) {
        wp_die(__('Permesso negato.', 'rbf'));
    }
    check_admin_referer('rbf_manage_brand_profiles');

    $profile_name = sanitize_text_field($_POST['profile_name'] ?? '');
    if ($profile_name === '') {
        $profile_name = 'Profilo senza nome';
    }

    $id = rbf_save_current_brand_as_profile($profile_name);

    wp_safe_redirect(add_query_arg(['saved_profile' => $id], wp_get_referer() ?: admin_url('admin.php?page=rbf_settings#branding')));
    exit;
});

add_action('admin_post_rbf_apply_brand_profile', function() {
    if (!current_user_can(rbf_get_settings_capability())) {
        wp_die(__('Permesso negato.', 'rbf'));
    }
    check_admin_referer('rbf_manage_brand_profiles');

    $profile_id = isset($_POST['profile_id']) ? sanitize_key($_POST['profile_id']) : '';
    if ($profile_id) {
        rbf_apply_brand_profile($profile_id);
    }

    wp_safe_redirect(wp_get_referer() ?: admin_url('admin.php?page=rbf_settings#branding'));
    exit;
});

add_action('admin_post_rbf_delete_brand_profile', function() {
    if (!current_user_can(rbf_get_settings_capability())) {
        wp_die(__('Permesso negato.', 'rbf'));
    }
    check_admin_referer('rbf_manage_brand_profiles');

    $profile_id = isset($_POST['profile_id']) ? sanitize_key($_POST['profile_id']) : '';
    if ($profile_id) {
        rbf_delete_brand_profile($profile_id);
    }

    wp_safe_redirect(wp_get_referer() ?: admin_url('admin.php?page=rbf_settings#branding'));
    exit;
});

add_action('admin_post_rbf_import_brand_profiles', function() {
    if (!current_user_can(rbf_get_settings_capability())) {
        wp_die(__('Permesso negato.', 'rbf'));
    }
    check_admin_referer('rbf_manage_brand_profiles');

    $raw = isset($_POST['brand_profiles_json']) ? wp_unslash($_POST['brand_profiles_json']) : '';
    if ($raw !== '') {
        rbf_import_brand_profiles($raw);
    }

    wp_safe_redirect(wp_get_referer() ?: admin_url('admin.php?page=rbf_settings#branding'));
    exit;
});

