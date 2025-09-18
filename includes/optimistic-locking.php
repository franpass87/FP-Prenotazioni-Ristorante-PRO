<?php
/**
 * Optimistic Locking functionality for FP Prenotazioni Ristorante
 * Prevents race conditions on last available slot bookings
 * 
 * @package FP_Prenotazioni_Ristorante_PRO
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create slot version tracking table for optimistic locking
 */
function rbf_create_slot_version_table() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Slot versions table for optimistic locking
    $table_slot_versions = $wpdb->prefix . 'rbf_slot_versions';
    $sql_slot_versions = "CREATE TABLE $table_slot_versions (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        slot_date date NOT NULL,
        slot_id varchar(50) NOT NULL,
        version_number bigint(20) UNSIGNED NOT NULL DEFAULT 1,
        total_capacity int(11) NOT NULL DEFAULT 0,
        booked_capacity int(11) NOT NULL DEFAULT 0,
        last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY slot_date_id (slot_date, slot_id),
        KEY version_number (version_number),
        KEY last_updated (last_updated)
    ) $charset_collate;";
    
    
    // Only include upgrade.php if we're in WordPress environment
    if (defined('WP_ADMIN') || function_exists('is_admin')) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_slot_versions);
    } else {
        // In testing environment, just return success
        return true;
    }
}

/**
 * Initialize or get slot version record
 * 
 * @param string $date Date in Y-m-d format
 * @param string $slot_id Slot identifier
 * @return array|false Slot version data or false on error
 */
function rbf_get_slot_version($date, $slot_id) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'rbf_slot_versions';
    
    // Try to get existing record
    $slot_version = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE slot_date = %s AND slot_id = %s",
        $date, $slot_id
    ), ARRAY_A);
    
    if ($slot_version) {
        return $slot_version;
    }
    
    // Create new record if doesn't exist
    $total_capacity = rbf_get_effective_capacity($slot_id);
    $booked_capacity = rbf_calculate_current_bookings($date, $slot_id);
    
    $inserted = $wpdb->insert(
        $table_name,
        [
            'slot_date' => $date,
            'slot_id' => $slot_id,
            'version_number' => 1,
            'total_capacity' => $total_capacity,
            'booked_capacity' => $booked_capacity,
        ],
        ['%s', '%s', '%d', '%d', '%d']
    );
    
    if ($inserted === false) {
        return false;
    }
    
    // Return the newly created record
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE slot_date = %s AND slot_id = %s",
        $date, $slot_id
    ), ARRAY_A);
}

/**
 * Calculate current bookings for a slot without cache
 * 
 * @param string $date Date in Y-m-d format
 * @param string $slot_id Slot identifier
 * @return int Number of people already booked
 */
function rbf_calculate_current_bookings($date, $slot_id) {
    return rbf_sum_active_bookings($date, $slot_id);
}


/**
 * Attempt to book slot with optimistic locking
 * 
 * @param string $date Date in Y-m-d format
 * @param string $slot_id Slot identifier
 * @param int $people Number of people to book
 * @param int $max_retries Maximum number of retry attempts
 * @return array Result with success status and data
 */
function rbf_book_slot_optimistic($date, $slot_id, $people, $max_retries = 3) {
    global $wpdb;
    
    // Validate input parameters
    if (empty($date) || empty($slot_id) || $people <= 0) {
        return [
            'success' => false,
            'error' => 'invalid_parameters',
            'message' => 'Invalid booking parameters provided',
            'attempt' => 1
        ];
    }
    
    $table_name = $wpdb->prefix . 'rbf_slot_versions';
    $attempt = 0;
    
    while ($attempt < $max_retries) {
        $attempt++;
        
        // Get current slot version
        $slot_version = rbf_get_slot_version($date, $slot_id);
        if (!$slot_version) {
            return [
                'success' => false,
                'error' => 'slot_version_error',
                'message' => 'Unable to get slot version information',
                'attempt' => $attempt
            ];
        }
        
        $current_version = $slot_version['version_number'];
        $total_capacity = $slot_version['total_capacity'];
        $current_booked = $slot_version['booked_capacity'];
        
        // Check if enough capacity is available
        $remaining_capacity = $total_capacity - $current_booked;
        if ($remaining_capacity < $people) {
            return [
                'success' => false,
                'error' => 'insufficient_capacity',
                'message' => sprintf(
                    'Not enough spots available. Requested: %d, Available: %d',
                    $people, $remaining_capacity
                ),
                'remaining' => $remaining_capacity,
                'attempt' => $attempt
            ];
        }
        
        // Attempt to update with version check (optimistic locking)
        $new_booked = $current_booked + $people;
        $new_version = $current_version + 1;
        
        $updated = $wpdb->update(
            $table_name,
            [
                'booked_capacity' => $new_booked,
                'version_number' => $new_version,
            ],
            [
                'slot_date' => $date,
                'slot_id' => $slot_id,
                'version_number' => $current_version, // This is the key for optimistic locking
            ],
            ['%d', '%d'],
            ['%s', '%s', '%d']
        );
        
        // Check if update was successful (version matched)
        if ($updated === 1) {
            // Success! The version matched and we got the lock
            return [
                'success' => true,
                'version' => $new_version,
                'previous_version' => $current_version,
                'new_booked_capacity' => $new_booked,
                'remaining_capacity' => $total_capacity - $new_booked,
                'attempt' => $attempt
            ];
        }
        
        // Version conflict detected, another booking happened concurrently
        if ($attempt < $max_retries) {
            // Wait a small random time before retry to reduce collision probability
            usleep(rand(10000, 50000)); // 10-50ms random delay
        }
    }
    
    // All retry attempts failed
    return [
        'success' => false,
        'error' => 'version_conflict',
        'message' => sprintf(
            'Booking conflict detected after %d attempts. Another user may have just booked this slot.',
            $max_retries
        ),
        'attempt' => $attempt
    ];
}

/**
 * Release booked capacity (for cancellations or errors)
 * 
 * @param string $date Date in Y-m-d format
 * @param string $slot_id Slot identifier
 * @param int $people Number of people to release
 * @return bool Success status
 */
function rbf_release_slot_capacity($date, $slot_id, $people) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'rbf_slot_versions';
    $max_retries = 3;
    $attempt = 0;

    while ($attempt < $max_retries) {
        $attempt++;

        // Get current slot version
        $slot_version = rbf_get_slot_version($date, $slot_id);
        if (!$slot_version) {
            return false;
        }

        $current_version = (int) $slot_version['version_number'];
        $current_booked = (int) $slot_version['booked_capacity'];

        // Prevent negative booked capacity when releasing seats
        $new_booked = max(0, $current_booked - (int) $people);
        $new_version = $current_version + 1;

        $updated = $wpdb->update(
            $table_name,
            [
                'booked_capacity' => $new_booked,
                'version_number' => $new_version,
            ],
            [
                'slot_date' => $date,
                'slot_id' => $slot_id,
                'version_number' => $current_version,
            ],
            ['%d', '%d'],
            ['%s', '%s', '%d']
        );

        if ($updated === 1) {
            return true;
        }

        if ($attempt < $max_retries) {
            usleep(rand(10000, 50000));
        }
    }

    return false;
}

/**
 * Sync slot version with actual bookings (maintenance function)
 * 
 * @param string $date Date in Y-m-d format
 * @param string $slot_id Slot identifier
 * @return bool Success status
 */
function rbf_sync_slot_version($date, $slot_id) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'rbf_slot_versions';
    
    // Calculate actual bookings
    $actual_booked = rbf_calculate_current_bookings($date, $slot_id);
    $total_capacity = rbf_get_effective_capacity($slot_id);
    
    // Get current version record
    $slot_version = rbf_get_slot_version($date, $slot_id);
    if (!$slot_version) {
        return false;
    }
    
    $new_version = $slot_version['version_number'] + 1;
    
    $updated = $wpdb->update(
        $table_name,
        [
            'total_capacity' => $total_capacity,
            'booked_capacity' => $actual_booked,
            'version_number' => $new_version,
        ],
        [
            'slot_date' => $date,
            'slot_id' => $slot_id,
        ],
        ['%d', '%d', '%d'],
        ['%s', '%s']
    );
    
    return $updated === 1;
}