<?php
/**
 * Table Management functionality for Restaurant Booking Plugin
 * 
 * @package FP_Prenotazioni_Ristorante_PRO
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create database tables for table management on plugin activation
 */
function rbf_create_table_management_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Areas table (e.g., sala, dehors, terrazza)
    $table_areas = $wpdb->prefix . 'rbf_areas';
    $sql_areas = "CREATE TABLE $table_areas (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name varchar(100) NOT NULL,
        description text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY name (name)
    ) $charset_collate;";
    
    // Tables table
    $table_tables = $wpdb->prefix . 'rbf_tables';
    $sql_tables = "CREATE TABLE $table_tables (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        area_id mediumint(9) NOT NULL,
        name varchar(100) NOT NULL,
        capacity tinyint(4) NOT NULL DEFAULT 2,
        min_capacity tinyint(4) NOT NULL DEFAULT 1,
        max_capacity tinyint(4) NOT NULL DEFAULT 8,
        position_x int(11) DEFAULT NULL,
        position_y int(11) DEFAULT NULL,
        is_active tinyint(1) DEFAULT 1,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY area_id (area_id),
        KEY capacity (capacity),
        KEY is_active (is_active),
        UNIQUE KEY area_table_name (area_id, name)
    ) $charset_collate;";
    
    // Table groups (for joinable tables)
    $table_groups = $wpdb->prefix . 'rbf_table_groups';
    $sql_groups = "CREATE TABLE $table_groups (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name varchar(100) NOT NULL,
        area_id mediumint(9) NOT NULL,
        max_combined_capacity tinyint(4) NOT NULL DEFAULT 16,
        is_active tinyint(1) DEFAULT 1,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY area_id (area_id),
        KEY is_active (is_active)
    ) $charset_collate;";
    
    // Table group members (which tables can be joined)
    $table_group_members = $wpdb->prefix . 'rbf_table_group_members';
    $sql_group_members = "CREATE TABLE $table_group_members (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        group_id mediumint(9) NOT NULL,
        table_id mediumint(9) NOT NULL,
        join_order tinyint(4) NOT NULL DEFAULT 1,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY group_id (group_id),
        KEY table_id (table_id),
        UNIQUE KEY group_table (group_id, table_id)
    ) $charset_collate;";
    
    // Table assignments (which tables are assigned to which bookings)
    $table_assignments = $wpdb->prefix . 'rbf_table_assignments';
    $sql_assignments = "CREATE TABLE $table_assignments (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        booking_id bigint(20) NOT NULL,
        table_id mediumint(9) NOT NULL,
        group_id mediumint(9) DEFAULT NULL,
        assignment_type enum('single','joined') DEFAULT 'single',
        assigned_capacity tinyint(4) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY booking_id (booking_id),
        KEY table_id (table_id),
        KEY group_id (group_id),
        KEY assignment_type (assignment_type)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    dbDelta($sql_areas);
    dbDelta($sql_tables);
    dbDelta($sql_groups);
    dbDelta($sql_group_members);
    dbDelta($sql_assignments);
    
    // Create default area and tables
    rbf_create_default_table_setup();
}

/**
 * Create default table setup for immediate use
 */
function rbf_create_default_table_setup() {
    global $wpdb;
    
    $areas_table = $wpdb->prefix . 'rbf_areas';
    $tables_table = $wpdb->prefix . 'rbf_tables';
    $groups_table = $wpdb->prefix . 'rbf_table_groups';
    $group_members_table = $wpdb->prefix . 'rbf_table_group_members';
    
    // Check if default data already exists
    $existing_areas = $wpdb->get_var("SELECT COUNT(*) FROM $areas_table");
    if ($existing_areas > 0) {
        return; // Already setup
    }
    
    // Create default areas
    $wpdb->insert($areas_table, [
        'name' => 'Sala Principale',
        'description' => 'Area principale del ristorante'
    ]);
    $sala_id = $wpdb->insert_id;
    
    $wpdb->insert($areas_table, [
        'name' => 'Dehors',
        'description' => 'Area esterna'
    ]);
    $dehors_id = $wpdb->insert_id;
    
    // Create default tables for Sala Principale
    $default_tables_sala = [
        ['name' => 'T1', 'capacity' => 2],
        ['name' => 'T2', 'capacity' => 2],
        ['name' => 'T3', 'capacity' => 4],
        ['name' => 'T4', 'capacity' => 4],
        ['name' => 'T5', 'capacity' => 6],
        ['name' => 'T6', 'capacity' => 6],
        ['name' => 'T7', 'capacity' => 8],
        ['name' => 'T8', 'capacity' => 8]
    ];
    
    $table_ids_sala = [];
    foreach ($default_tables_sala as $table) {
        $wpdb->insert($tables_table, [
            'area_id' => $sala_id,
            'name' => $table['name'],
            'capacity' => $table['capacity'],
            'min_capacity' => max(1, $table['capacity'] - 2),
            'max_capacity' => $table['capacity'] + 2
        ]);
        $table_ids_sala[] = $wpdb->insert_id;
    }
    
    // Create default tables for Dehors
    $default_tables_dehors = [
        ['name' => 'D1', 'capacity' => 4],
        ['name' => 'D2', 'capacity' => 4],
        ['name' => 'D3', 'capacity' => 6],
        ['name' => 'D4', 'capacity' => 6]
    ];
    
    $table_ids_dehors = [];
    foreach ($default_tables_dehors as $table) {
        $wpdb->insert($tables_table, [
            'area_id' => $dehors_id,
            'name' => $table['name'],
            'capacity' => $table['capacity'],
            'min_capacity' => max(1, $table['capacity'] - 2),
            'max_capacity' => $table['capacity'] + 2
        ]);
        $table_ids_dehors[] = $wpdb->insert_id;
    }
    
    // Create joinable table groups
    // Group small tables in sala
    $wpdb->insert($groups_table, [
        'name' => 'Piccoli Tavoli Sala',
        'area_id' => $sala_id,
        'max_combined_capacity' => 8
    ]);
    $small_group_id = $wpdb->insert_id;
    
    // Add small tables to group (T1, T2, T3, T4)
    for ($i = 0; $i < 4; $i++) {
        $wpdb->insert($group_members_table, [
            'group_id' => $small_group_id,
            'table_id' => $table_ids_sala[$i],
            'join_order' => $i + 1
        ]);
    }
    
    // Group medium tables in sala
    $wpdb->insert($groups_table, [
        'name' => 'Tavoli Medi Sala',
        'area_id' => $sala_id,
        'max_combined_capacity' => 12
    ]);
    $medium_group_id = $wpdb->insert_id;
    
    // Add medium tables to group (T5, T6)
    for ($i = 4; $i < 6; $i++) {
        $wpdb->insert($group_members_table, [
            'group_id' => $medium_group_id,
            'table_id' => $table_ids_sala[$i],
            'join_order' => $i - 3
        ]);
    }
    
    // Group dehors tables
    $wpdb->insert($groups_table, [
        'name' => 'Tavoli Dehors',
        'area_id' => $dehors_id,
        'max_combined_capacity' => 12
    ]);
    $dehors_group_id = $wpdb->insert_id;
    
    // Add dehors tables to group
    foreach ($table_ids_dehors as $index => $table_id) {
        $wpdb->insert($group_members_table, [
            'group_id' => $dehors_group_id,
            'table_id' => $table_id,
            'join_order' => $index + 1
        ]);
    }
}

/**
 * Get all areas
 */
function rbf_get_areas() {
    global $wpdb;
    $table = $wpdb->prefix . 'rbf_areas';
    return $wpdb->get_results("SELECT * FROM $table ORDER BY name");
}

/**
 * Get tables by area
 */
function rbf_get_tables_by_area($area_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'rbf_tables';
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE area_id = %d AND is_active = 1 ORDER BY name",
        $area_id
    ));
}

/**
 * Get all available tables
 */
function rbf_get_all_tables() {
    global $wpdb;
    $tables_table = $wpdb->prefix . 'rbf_tables';
    $areas_table = $wpdb->prefix . 'rbf_areas';
    
    return $wpdb->get_results("
        SELECT t.*, a.name as area_name 
        FROM $tables_table t 
        LEFT JOIN $areas_table a ON t.area_id = a.id 
        WHERE t.is_active = 1 
        ORDER BY a.name, t.name
    ");
}

/**
 * Get table groups by area
 */
function rbf_get_table_groups_by_area($area_id) {
    global $wpdb;
    $groups_table = $wpdb->prefix . 'rbf_table_groups';
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $groups_table WHERE area_id = %d AND is_active = 1 ORDER BY name",
        $area_id
    ));
}

/**
 * Get tables in a group
 */
function rbf_get_group_tables($group_id) {
    global $wpdb;
    $tables_table = $wpdb->prefix . 'rbf_tables';
    $group_members_table = $wpdb->prefix . 'rbf_table_group_members';
    
    return $wpdb->get_results($wpdb->prepare("
        SELECT t.*, gm.join_order
        FROM $tables_table t
        INNER JOIN $group_members_table gm ON t.id = gm.table_id
        WHERE gm.group_id = %d AND t.is_active = 1
        ORDER BY gm.join_order
    ", $group_id));
}

/**
 * Check table availability for a specific date, time and meal
 */
function rbf_check_table_availability($date, $time, $meal) {
    global $wpdb;
    
    // Get all active tables
    $all_tables = rbf_get_all_tables();
    
    // Get existing assignments for this date/time/meal
    $assignments_table = $wpdb->prefix . 'rbf_table_assignments';
    $bookings_table = $wpdb->prefix . 'posts';
    
    $assigned_tables = $wpdb->get_results($wpdb->prepare("
        SELECT ta.table_id, ta.group_id, ta.assignment_type
        FROM $assignments_table ta
        INNER JOIN {$wpdb->postmeta} pm_date ON ta.booking_id = pm_date.post_id AND pm_date.meta_key = 'rbf_data'
        INNER JOIN {$wpdb->postmeta} pm_time ON ta.booking_id = pm_time.post_id AND pm_time.meta_key = 'rbf_orario'
        INNER JOIN {$wpdb->postmeta} pm_meal ON ta.booking_id = pm_meal.post_id AND pm_meal.meta_key = 'rbf_meal'
        INNER JOIN $bookings_table p ON ta.booking_id = p.ID
        WHERE pm_date.meta_value = %s 
        AND pm_time.meta_value = %s 
        AND pm_meal.meta_value = %s
        AND p.post_status = 'publish'
    ", $date, $time, $meal));
    
    $assigned_table_ids = array_column($assigned_tables, 'table_id');
    
    // Filter available tables
    $available_tables = array_filter($all_tables, function($table) use ($assigned_table_ids) {
        return !in_array($table->id, $assigned_table_ids);
    });
    
    return $available_tables;
}

/**
 * Table assignment algorithm - First Fit strategy
 */
function rbf_assign_tables_first_fit($people_count, $date, $time, $meal) {
    $available_tables = rbf_check_table_availability($date, $time, $meal);
    
    if (empty($available_tables)) {
        return null; // No tables available
    }
    
    // Sort tables by capacity (smallest first for optimal allocation)
    usort($available_tables, function($a, $b) {
        return $a->capacity - $b->capacity;
    });
    
    // First try: find a single table that can accommodate the party
    foreach ($available_tables as $table) {
        if ($table->capacity >= $people_count && $people_count >= $table->min_capacity) {
            return [
                'type' => 'single',
                'tables' => [$table],
                'total_capacity' => $table->capacity
            ];
        }
    }
    
    // Second try: find joinable tables
    $areas = rbf_get_areas();
    foreach ($areas as $area) {
        $groups = rbf_get_table_groups_by_area($area->id);
        foreach ($groups as $group) {
            $group_tables = rbf_get_group_tables($group->id);
            
            // Filter only available tables in this group
            $available_group_tables = array_filter($group_tables, function($table) use ($available_tables) {
                return in_array($table->id, array_column($available_tables, 'id'));
            });
            
            if (empty($available_group_tables)) {
                continue;
            }
            
            // Try different combinations of tables in this group
            $combination = rbf_find_table_combination($available_group_tables, $people_count, $group->max_combined_capacity);
            if ($combination) {
                return [
                    'type' => 'joined',
                    'tables' => $combination['tables'],
                    'group_id' => $group->id,
                    'total_capacity' => $combination['total_capacity']
                ];
            }
        }
    }
    
    return null; // No suitable assignment found
}

/**
 * Find optimal table combination within a group
 */
function rbf_find_table_combination($available_tables, $people_count, $max_capacity) {
    if (empty($available_tables) || $people_count > $max_capacity) {
        return null;
    }
    
    // Sort tables by capacity
    usort($available_tables, function($a, $b) {
        return $a->capacity - $b->capacity;
    });
    
    // Try combinations starting with smallest tables
    $n = count($available_tables);
    
    // Try single table first
    foreach ($available_tables as $table) {
        if ($table->capacity >= $people_count && $people_count >= $table->min_capacity) {
            return [
                'tables' => [$table],
                'total_capacity' => $table->capacity
            ];
        }
    }
    
    // Try pairs
    for ($i = 0; $i < $n - 1; $i++) {
        for ($j = $i + 1; $j < $n; $j++) {
            $total_capacity = $available_tables[$i]->capacity + $available_tables[$j]->capacity;
            if ($total_capacity >= $people_count && $total_capacity <= $max_capacity) {
                return [
                    'tables' => [$available_tables[$i], $available_tables[$j]],
                    'total_capacity' => $total_capacity
                ];
            }
        }
    }
    
    // Try triplets (for large parties)
    for ($i = 0; $i < $n - 2; $i++) {
        for ($j = $i + 1; $j < $n - 1; $j++) {
            for ($k = $j + 1; $k < $n; $k++) {
                $total_capacity = $available_tables[$i]->capacity + $available_tables[$j]->capacity + $available_tables[$k]->capacity;
                if ($total_capacity >= $people_count && $total_capacity <= $max_capacity) {
                    return [
                        'tables' => [$available_tables[$i], $available_tables[$j], $available_tables[$k]],
                        'total_capacity' => $total_capacity
                    ];
                }
            }
        }
    }
    
    return null;
}

/**
 * Save table assignment for a booking
 */
function rbf_save_table_assignment($booking_id, $assignment) {
    global $wpdb;
    
    if (!$assignment || empty($assignment['tables'])) {
        return false;
    }
    
    $assignments_table = $wpdb->prefix . 'rbf_table_assignments';
    
    // Remove existing assignments for this booking
    $wpdb->delete($assignments_table, ['booking_id' => $booking_id]);
    
    // Add new assignments
    foreach ($assignment['tables'] as $table) {
        $wpdb->insert($assignments_table, [
            'booking_id' => $booking_id,
            'table_id' => $table->id,
            'group_id' => $assignment['group_id'] ?? null,
            'assignment_type' => $assignment['type'],
            'assigned_capacity' => $table->capacity
        ]);
    }
    
    return true;
}

/**
 * Get table assignment for a booking
 */
function rbf_get_booking_table_assignment($booking_id) {
    global $wpdb;
    
    $assignments_table = $wpdb->prefix . 'rbf_table_assignments';
    $tables_table = $wpdb->prefix . 'rbf_tables';
    $areas_table = $wpdb->prefix . 'rbf_areas';
    
    $assignments = $wpdb->get_results($wpdb->prepare("
        SELECT ta.*, t.name as table_name, t.capacity, a.name as area_name
        FROM $assignments_table ta
        INNER JOIN $tables_table t ON ta.table_id = t.id
        INNER JOIN $areas_table a ON t.area_id = a.id
        WHERE ta.booking_id = %d
        ORDER BY ta.id
    ", $booking_id));
    
    if (empty($assignments)) {
        return null;
    }
    
    return [
        'type' => $assignments[0]->assignment_type,
        'group_id' => $assignments[0]->group_id,
        'tables' => $assignments,
        'total_capacity' => array_sum(array_column($assignments, 'capacity'))
    ];
}

/**
 * Remove table assignment for a booking
 */
function rbf_remove_table_assignment($booking_id) {
    global $wpdb;
    
    $assignments_table = $wpdb->prefix . 'rbf_table_assignments';
    return $wpdb->delete($assignments_table, ['booking_id' => $booking_id]);
}