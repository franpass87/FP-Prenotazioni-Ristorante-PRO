<?php
/**
 * Booking operations dashboard with upcoming metrics.
 *
 * @package FP_Prenotazioni_Ristorante_PRO
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', 'rbf_register_booking_dashboard_page', 6);
/**
 * Register the Booking Dashboard submenu page.
 */
function rbf_register_booking_dashboard_page() {
    add_submenu_page(
        'rbf_calendar',
        rbf_translate_string('Cruscotto prenotazioni'),
        rbf_translate_string('Cruscotto'),
        rbf_get_booking_capability(),
        'rbf_booking_dashboard',
        'rbf_render_booking_dashboard_page',
        0
    );
}

/**
 * Retrieve booking posts within a date range and normalize the useful metadata.
 *
 * @param string $start_date Inclusive start in Y-m-d format.
 * @param string $end_date   Inclusive end in Y-m-d format.
 * @return array<int, array<string, mixed>>
 */
function rbf_booking_dashboard_collect_entries($start_date, $end_date) {
    $start_date = sanitize_text_field($start_date);
    $end_date = sanitize_text_field($end_date);

    if ($start_date === '' || $end_date === '') {
        return [];
    }

    if ($start_date > $end_date) {
        $tmp = $start_date;
        $start_date = $end_date;
        $end_date = $tmp;
    }

    $query_args = [
        'post_type'        => 'rbf_booking',
        'post_status'      => 'publish',
        'posts_per_page'   => -1,
        'orderby'          => 'meta_value',
        'order'            => 'ASC',
        'meta_key'         => 'rbf_data',
        'meta_type'        => 'DATE',
        'suppress_filters' => true,
        'meta_query'       => [
            [
                'key'     => 'rbf_data',
                'value'   => [$start_date, $end_date],
                'compare' => 'BETWEEN',
                'type'    => 'DATE',
            ],
        ],
    ];

    $posts = get_posts($query_args);
    if (empty($posts)) {
        return [];
    }

    $statuses = rbf_get_booking_statuses();
    $tz = rbf_wp_timezone();
    $entries = [];

    foreach ($posts as $post) {
        if (!($post instanceof WP_Post)) {
            continue;
        }

        $meta = get_post_meta($post->ID);
        $date = isset($meta['rbf_data'][0]) ? sanitize_text_field($meta['rbf_data'][0]) : '';
        if ($date === '') {
            continue;
        }

        $time_raw = '';
        if (!empty($meta['rbf_time'][0])) {
            $time_raw = sanitize_text_field($meta['rbf_time'][0]);
        } elseif (!empty($meta['rbf_orario'][0])) {
            $time_raw = sanitize_text_field($meta['rbf_orario'][0]);
        }

        $time = '';
        if ($time_raw !== '') {
            $time_obj = DateTimeImmutable::createFromFormat('H:i', $time_raw, $tz);
            if ($time_obj instanceof DateTimeImmutable) {
                $time = $time_obj->format('H:i');
            }
        }

        $status = isset($meta['rbf_booking_status'][0]) ? sanitize_key($meta['rbf_booking_status'][0]) : 'confirmed';
        if (!isset($statuses[$status])) {
            $status = 'confirmed';
        }

        $people = isset($meta['rbf_persone'][0]) ? absint($meta['rbf_persone'][0]) : 0;
        $value_tot = isset($meta['rbf_valore_tot'][0]) ? (float) $meta['rbf_valore_tot'][0] : 0.0;

        $first_name = isset($meta['rbf_nome'][0]) ? rbf_sanitize_text_strict($meta['rbf_nome'][0]) : '';
        $last_name = isset($meta['rbf_cognome'][0]) ? rbf_sanitize_text_strict($meta['rbf_cognome'][0]) : '';
        $customer = trim($first_name . ' ' . $last_name);
        if ($customer === '') {
            $customer = rbf_sanitize_text_strict($post->post_title);
        }

        $meal_id = isset($meta['rbf_meal'][0]) ? sanitize_key($meta['rbf_meal'][0]) : '';
        $meal_label = rbf_booking_dashboard_get_meal_label($meal_id);

        $timestamp = 0;
        $datetime_string = $time !== '' ? $date . ' ' . $time : $date . ' 00:00';
        $datetime = DateTimeImmutable::createFromFormat('Y-m-d H:i', $datetime_string, $tz);
        if ($datetime instanceof DateTimeImmutable) {
            $timestamp = $datetime->getTimestamp();
        }

        $entries[] = [
            'id'           => $post->ID,
            'date'         => $date,
            'time'         => $time,
            'status'       => $status,
            'status_label' => $statuses[$status],
            'people'       => $people,
            'value'        => $value_tot,
            'customer'     => $customer,
            'meal_id'      => $meal_id,
            'meal_label'   => $meal_label,
            'timestamp'    => $timestamp,
        ];
    }

    return $entries;
}

/**
 * Helper to memoize meal label lookup.
 *
 * @param string $meal_id Meal identifier.
 * @return string
 */
function rbf_booking_dashboard_get_meal_label($meal_id) {
    static $cache = null;

    if ($cache === null) {
        $cache = [];
        $meals = rbf_get_active_meals();
        foreach ($meals as $meal) {
            if (!empty($meal['id'])) {
                $cache[$meal['id']] = rbf_sanitize_text_strict($meal['name'] ?? $meal['id']);
            }
        }
    }

    if ($meal_id === '') {
        return '';
    }

    return $cache[$meal_id] ?? $meal_id;
}

/**
 * Prepare summary metrics, occupancy data and upcoming rows.
 *
 * @return array<string, mixed>
 */
function rbf_booking_dashboard_prepare_data() {
    $timezone = rbf_wp_timezone();
    $now = new DateTimeImmutable('now', $timezone);
    $today = $now->setTime(0, 0, 0);
    $tomorrow = $today->modify('+1 day');
    $week_end = $today->modify('+6 days');
    $two_weeks = $today->modify('+13 days');
    $last_week_start = $today->modify('-6 days');

    $today_str = $today->format('Y-m-d');
    $tomorrow_str = $tomorrow->format('Y-m-d');
    $week_end_str = $week_end->format('Y-m-d');
    $two_weeks_str = $two_weeks->format('Y-m-d');
    $last_week_str = $last_week_start->format('Y-m-d');

    $upcoming_entries = rbf_booking_dashboard_collect_entries($today_str, $two_weeks_str);
    $recent_entries = rbf_booking_dashboard_collect_entries($last_week_str, $today_str);

    $summary = [
        'today_count'     => 0,
        'today_people'    => 0,
        'tomorrow_count'  => 0,
        'tomorrow_people' => 0,
        'week_count'      => 0,
        'week_people'     => 0,
        'week_value'      => 0.0,
        'upcoming_total'  => 0,
    ];

    $upcoming_rows = [];
    $now_ts = $now->getTimestamp();

    foreach ($upcoming_entries as $entry) {
        $date = $entry['date'];
        $status = $entry['status'];
        $people = $entry['people'];
        $value = $entry['value'];

        if ($status !== 'cancelled') {
            if ($date === $today_str) {
                $summary['today_count']++;
                $summary['today_people'] += $people;
            }
            if ($date === $tomorrow_str) {
                $summary['tomorrow_count']++;
                $summary['tomorrow_people'] += $people;
            }
            if ($date >= $today_str && $date <= $week_end_str) {
                $summary['week_count']++;
                $summary['week_people'] += $people;
                $summary['week_value'] += $value;
            }
            $summary['upcoming_total']++;
        }

        if ($status !== 'cancelled' && $entry['timestamp'] >= $now_ts) {
            $upcoming_rows[] = $entry;
        }
    }

    usort($upcoming_rows, function($a, $b) {
        return $a['timestamp'] <=> $b['timestamp'];
    });

    $recent_summary = [
        'completed'       => 0,
        'cancelled'       => 0,
        'people_served'   => 0,
        'completed_value' => 0.0,
    ];

    foreach ($recent_entries as $entry) {
        $status = $entry['status'];
        $people = $entry['people'];
        $value = $entry['value'];

        if ($status === 'completed') {
            $recent_summary['completed']++;
            $recent_summary['people_served'] += $people;
            $recent_summary['completed_value'] += $value;
        } elseif ($status === 'cancelled') {
            $recent_summary['cancelled']++;
        }
    }

    return [
        'summary'    => $summary,
        'recent'     => $recent_summary,
        'upcoming'   => $upcoming_rows,
        'today_date' => $today_str,
        'timezone'   => $timezone,
    ];
}

/**
 * Retrieve occupancy data for today's active meals.
 *
 * @param string $today_date Date in Y-m-d format.
 * @return array<int, array<string, mixed>>
 */
function rbf_booking_dashboard_get_today_occupancy($today_date) {
    $today_date = sanitize_text_field($today_date);
    if ($today_date === '') {
        return [];
    }

    $meals = rbf_get_active_meals();
    if (empty($meals)) {
        return [];
    }

    $occupancy = [];
    foreach ($meals as $meal) {
        if (empty($meal['id'])) {
            continue;
        }

        $meal_id = $meal['id'];
        $label = rbf_sanitize_text_strict($meal['name'] ?? $meal_id);
        $status = rbf_get_availability_status($today_date, $meal_id);
        $remaining = $status['remaining'];
        $total = $status['total'];
        $percentage = isset($status['occupancy']) ? (float) $status['occupancy'] : 0.0;
        $level = $status['level'] ?? 'available';

        $occupancy[] = [
            'id'         => $meal_id,
            'label'      => $label,
            'remaining'  => $remaining,
            'total'      => $total,
            'percentage' => $percentage,
            'level'      => $level,
        ];
    }

    return $occupancy;
}

/**
 * Render the booking dashboard page.
 */
function rbf_render_booking_dashboard_page() {
    if (!rbf_require_booking_capability()) {
        return;
    }

    $data = rbf_booking_dashboard_prepare_data();
    $summary = $data['summary'];
    $recent = $data['recent'];
    $upcoming = $data['upcoming'];
    $today_date = $data['today_date'];
    $timezone = $data['timezone'];

    $occupancy = rbf_booking_dashboard_get_today_occupancy($today_date);
    $date_format = get_option('date_format', 'Y-m-d');
    $time_format = get_option('time_format', 'H:i');

    echo '<div class="wrap rbf-admin-wrap rbf-admin-wrap--full rbf-booking-dashboard">';
    echo '<h1>' . esc_html(rbf_translate_string('Cruscotto prenotazioni')) . '</h1>';
    echo '<p class="rbf-admin-intro">' . esc_html(rbf_translate_string('Panoramica rapida di capacità, cancellazioni e attività consigliate per lo staff.')) . '</p>';

    echo '<div class="rbf-admin-grid rbf-admin-grid--cols-4 rbf-admin-metrics">';
    rbf_booking_dashboard_render_metric(
        rbf_translate_string('Prenotazioni di oggi'),
        number_format_i18n($summary['today_count']),
        sprintf(rbf_translate_string('Coperti oggi: %s'), number_format_i18n($summary['today_people']))
    );
    rbf_booking_dashboard_render_metric(
        rbf_translate_string('Prenotazioni di domani'),
        number_format_i18n($summary['tomorrow_count']),
        sprintf(rbf_translate_string('Coperti domani: %s'), number_format_i18n($summary['tomorrow_people']))
    );
    rbf_booking_dashboard_render_metric(
        rbf_translate_string('Prenotazioni prossimi 7 giorni'),
        number_format_i18n($summary['week_count']),
        sprintf(rbf_translate_string('Valore previsto settimana: €%s'), number_format_i18n($summary['week_value'], 2))
    );
    rbf_booking_dashboard_render_metric(
        rbf_translate_string('Prenotazioni totali in arrivo'),
        number_format_i18n($summary['upcoming_total']),
        rbf_translate_string('Monitorare cancellazioni per reagire rapidamente.')
    );
    echo '</div>';

    echo '<div class="rbf-admin-grid rbf-admin-grid--cols-2">';
    echo '<div class="rbf-admin-card">';
    echo '<h2>' . esc_html(rbf_translate_string('Prossime prenotazioni')) . '</h2>';
    if (empty($upcoming)) {
        echo '<p>' . esc_html(rbf_translate_string('Nessuna prenotazione pianificata.')) . '</p>';
    } else {
        echo '<table class="wp-list-table widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html(rbf_translate_string('Data')) . '</th>';
        echo '<th>' . esc_html(rbf_translate_string('Ora')) . '</th>';
        echo '<th>' . esc_html(rbf_translate_string('Cliente')) . '</th>';
        echo '<th>' . esc_html(rbf_translate_string('Servizio')) . '</th>';
        echo '<th>' . esc_html(rbf_translate_string('Persone')) . '</th>';
        echo '<th>' . esc_html(rbf_translate_string('Valore')) . '</th>';
        echo '<th class="column-primary">' . esc_html(rbf_translate_string('Azioni')) . '</th>';
        echo '</tr></thead><tbody>';

        $max_rows = 6;
        $rendered = 0;
        foreach ($upcoming as $entry) {
            if ($rendered >= $max_rows) {
                break;
            }
            $rendered++;

            $timestamp = $entry['timestamp'] > 0 ? $entry['timestamp'] : strtotime($entry['date']);
            $date_output = $timestamp ? wp_date($date_format, $timestamp, $timezone) : $entry['date'];
            $time_output = '—';
            if ($entry['time'] !== '') {
                $time_obj = DateTimeImmutable::createFromFormat('H:i', $entry['time'], $timezone);
                if ($time_obj instanceof DateTimeImmutable) {
                    $time_output = wp_date($time_format, $time_obj->getTimestamp(), $timezone);
                }
            }
            $status_class = 'rbf-status-' . $entry['status'];
            $edit_link = get_edit_post_link($entry['id'], '');

            echo '<tr>';
            echo '<td data-colname="' . esc_attr(rbf_translate_string('Data')) . '">' . esc_html($date_output) . '</td>';
            echo '<td data-colname="' . esc_attr(rbf_translate_string('Ora')) . '">' . esc_html($time_output) . '</td>';
            echo '<td data-colname="' . esc_attr(rbf_translate_string('Cliente')) . '">';
            echo esc_html($entry['customer']);
            echo '<div class="rbf-status-badge ' . esc_attr($status_class) . '">' . esc_html($entry['status_label']) . '</div>';
            echo '</td>';
            echo '<td data-colname="' . esc_attr(rbf_translate_string('Servizio')) . '">' . esc_html($entry['meal_label']) . '</td>';
            echo '<td data-colname="' . esc_attr(rbf_translate_string('Persone')) . '">' . esc_html(number_format_i18n($entry['people'])) . '</td>';
            $value_display = $entry['value'] > 0 ? '€' . number_format_i18n($entry['value'], 2) : '—';
            echo '<td data-colname="' . esc_attr(rbf_translate_string('Valore')) . '">' . esc_html($value_display) . '</td>';
            echo '<td data-colname="' . esc_attr(rbf_translate_string('Azioni')) . '">';
            if ($edit_link) {
                echo '<a class="button button-small" href="' . esc_url($edit_link) . '">' . esc_html(rbf_translate_string('Apri dettaglio')) . '</a>';
            }
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }
    echo '</div>';

    echo '<div class="rbf-admin-card rbf-admin-card--soft">';
    echo '<h2>' . esc_html(rbf_translate_string('Capienza di oggi')) . '</h2>';
    if (empty($occupancy)) {
        echo '<p>' . esc_html(rbf_translate_string('Nessun servizio attivo configurato.')) . '</p>';
    } else {
        echo '<ul class="rbf-occupancy-list">';
        foreach ($occupancy as $item) {
            $remaining = $item['remaining'];
            $total = $item['total'];
            $percentage = min(100, max(0, $item['percentage']));
            $level_class = 'available';
            if ($item['level'] === 'limited') {
                $level_class = 'limited';
            } elseif ($item['level'] === 'full') {
                $level_class = 'full';
            }

            echo '<li class="rbf-occupancy-item">';
            echo '<div class="rbf-occupancy-item__header">';
            echo '<span class="rbf-occupancy-item__label">' . esc_html($item['label']) . '</span>';
            if ($total !== null) {
                echo '<span class="rbf-occupancy-item__stats">';
                echo esc_html(sprintf(rbf_translate_string('Capienza residua: %d / %d'), (int) $remaining, (int) $total));
                echo '</span>';
            }
            echo '</div>';
            echo '<div class="rbf-occupancy-bar">';
            echo '<span class="rbf-occupancy-bar__fill rbf-occupancy-bar__fill--' . esc_attr($level_class) . '" style="width:' . esc_attr($percentage) . '%"></span>';
            echo '</div>';
            echo '</li>';
        }
        echo '</ul>';
        echo '<div class="rbf-info-bubble">';
        echo '<h3>' . esc_html(rbf_translate_string('Suggerimento')) . '</h3>';
        echo '<p>' . esc_html(rbf_translate_string("Le fasce con capacità limitata richiedono attenzione: valuta l'apertura di tavoli extra.")) . '</p>';
        echo '</div>';
    }
    echo '</div>';
    echo '</div>';

    echo '<div class="rbf-admin-grid rbf-admin-grid--cols-2">';
    echo '<div class="rbf-admin-card">';
    echo '<h2>' . esc_html(rbf_translate_string('Storico ultimi 7 giorni')) . '</h2>';
    if ($recent['completed'] === 0 && $recent['cancelled'] === 0) {
        echo '<p>' . esc_html(rbf_translate_string('Nessun dato disponibile per il periodo selezionato.')) . '</p>';
    } else {
        echo '<ul class="rbf-admin-callout__list">';
        echo '<li><strong>' . esc_html(rbf_translate_string('Completate')) . ':</strong> ' . esc_html(number_format_i18n($recent['completed'])) . '</li>';
        echo '<li><strong>' . esc_html(rbf_translate_string('Annullate')) . ':</strong> ' . esc_html(number_format_i18n($recent['cancelled'])) . '</li>';
        echo '<li><strong>' . esc_html(rbf_translate_string('Coperti serviti')) . ':</strong> ' . esc_html(number_format_i18n($recent['people_served'])) . '</li>';
        echo '<li><strong>' . esc_html(rbf_translate_string('Incasso registrato')) . ':</strong> €' . esc_html(number_format_i18n($recent['completed_value'], 2)) . '</li>';
        echo '</ul>';
    }
    echo '</div>';

    echo '<div class="rbf-admin-card">';
    echo '<h2>' . esc_html(rbf_translate_string('Azioni rapide')) . '</h2>';
    echo '<ul class="rbf-admin-callout__list">';
    echo '<li><a class="button button-secondary" href="' . esc_url(admin_url('admin.php?page=rbf_calendar')) . '">' . esc_html(rbf_translate_string('Apri calendario')) . '</a></li>';
    echo '<li><a class="button button-secondary" href="' . esc_url(admin_url('admin.php?page=rbf_weekly_staff')) . '">' . esc_html(rbf_translate_string('Vista settimanale staff')) . '</a></li>';
    if (!function_exists('rbf_get_setup_wizard_admin_url')) {
        require_once RBF_PLUGIN_DIR . '/includes/onboarding.php';
    }

    $setup_url = rbf_get_setup_wizard_admin_url();
    echo '<li><a class="button button-secondary" href="' . esc_url($setup_url) . '">' . esc_html(rbf_translate_string('Setup guidato')) . '</a></li>';
    echo '<li><a class="button button-secondary" href="' . esc_url(admin_url('admin.php?page=rbf_system_health')) . '">' . esc_html(rbf_translate_string('Stato sistema')) . '</a></li>';
    echo '<li><a class="button button-secondary" href="' . esc_url(admin_url('admin.php?page=rbf_accessibility_checker')) . '">' . esc_html(rbf_translate_string('Verifica accessibilità')) . '</a></li>';
    echo '</ul>';
    echo '<p class="description">' . esc_html(rbf_translate_string('Consulta la vista staff per ottimizzare gli spostamenti con drag & drop e mantenere aggiornato il monitoraggio marketing.')) . '</p>';
    echo '</div>';
    echo '</div>';

    echo '</div>';
}

/**
 * Render a metric card element.
 *
 * @param string $label Metric label.
 * @param string $value Main value.
 * @param string $hint  Optional hint text.
 */
function rbf_booking_dashboard_render_metric($label, $value, $hint = '') {
    echo '<div class="rbf-metric-card">';
    echo '<p class="rbf-metric-card__label">' . esc_html($label) . '</p>';
    echo '<p class="rbf-metric-card__value">' . esc_html($value) . '</p>';
    if ($hint !== '') {
        echo '<p class="rbf-metric-card__hint">' . esc_html($hint) . '</p>';
    }
    echo '</div>';
}
