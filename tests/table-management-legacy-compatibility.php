<?php
/**
 * Table management legacy compatibility test
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}

class MockWPDB {
    public $prefix = 'wp_';
    public $postmeta = 'wp_postmeta';
    public $posts = 'wp_posts';

    private $tables;
    private $areas;
    private $assignments;
    private $postmeta_rows;
    private $posts_rows;

    public function __construct($data) {
        $this->tables = $data['tables'];
        $this->areas = $data['areas'];
        $this->assignments = $data['assignments'];
        $this->postmeta_rows = $data['postmeta'];
        $this->posts_rows = $data['posts'];
    }

    public function prepare($query, ...$args) {
        return (object) [
            'query' => $query,
            'args'  => $args,
        ];
    }

    public function get_results($query) {
        if (is_object($query) && isset($query->query)) {
            $sql = $query->query;
            $args = $query->args;
        } else {
            $sql = $query;
            $args = [];
        }

        if (strpos($sql, $this->prefix . 'rbf_tables') !== false && strpos($sql, $this->prefix . 'rbf_table_assignments') === false) {
            $tables = [];
            foreach ($this->tables as $table) {
                $table_obj = (object) $table;
                $area = $this->find_area($table['area_id']);
                $table_obj->area_name = $area['name'];
                $tables[] = $table_obj;
            }
            return $tables;
        }

        if (strpos($sql, $this->prefix . 'rbf_table_assignments') !== false) {
            list($date, $time, $meal) = $args;

            $results = [];
            foreach ($this->assignments as $assignment) {
                $booking_id = $assignment['booking_id'];
                $post = $this->posts_rows[$booking_id] ?? ['post_status' => 'publish'];
                if (($post['post_status'] ?? 'publish') !== 'publish') {
                    continue;
                }

                $status = $this->get_meta_value($booking_id, 'rbf_booking_status');
                if (($status ?? 'confirmed') === 'cancelled') {
                    continue;
                }

                $booking_date = $this->get_meta_value($booking_id, 'rbf_data');
                if ($booking_date !== $date) {
                    continue;
                }

                $time_value = $this->get_meta_value($booking_id, 'rbf_time');
                if ($time_value === null) {
                    $time_value = $this->get_legacy_time($booking_id);
                }

                if ($time_value !== $time) {
                    continue;
                }

                $meal_value = $this->get_meta_value($booking_id, 'rbf_meal');
                if ($meal_value === null) {
                    $meal_value = $this->get_legacy_meal($booking_id);
                }

                if ($meal_value !== $meal) {
                    continue;
                }

                $results[] = (object) [
                    'table_id' => $assignment['table_id'],
                    'group_id' => $assignment['group_id'],
                    'assignment_type' => $assignment['assignment_type'],
                ];
            }

            return $results;
        }

        return [];
    }

    public function get_var($query) {
        if (is_object($query) && isset($query->query)) {
            $sql = $query->query;
        } else {
            $sql = $query;
        }

        if (stripos($sql, 'SHOW TABLES LIKE') !== false) {
            return null;
        }

        return null;
    }

    private function find_area($area_id) {
        foreach ($this->areas as $area) {
            if ($area['id'] === $area_id) {
                return $area;
            }
        }

        return ['name' => ''];
    }

    private function get_meta_value($post_id, $meta_key) {
        $values = $this->postmeta_rows[$post_id] ?? [];
        foreach ($values as $meta) {
            if ($meta['meta_key'] === $meta_key) {
                return $meta['meta_value'];
            }
        }

        return null;
    }

    private function get_legacy_time($post_id) {
        $values = $this->postmeta_rows[$post_id] ?? [];
        foreach ($values as $meta) {
            if ($meta['meta_key'] === 'rbf_orario' && preg_match('/^\d{2}:\d{2}$/', $meta['meta_value'])) {
                return $meta['meta_value'];
            }
        }

        return null;
    }

    private function get_legacy_meal($post_id) {
        $values = $this->postmeta_rows[$post_id] ?? [];
        foreach ($values as $meta) {
            if ($meta['meta_key'] === 'rbf_orario' && !preg_match('/^\d{2}:\d{2}$/', $meta['meta_value'])) {
                return $meta['meta_value'];
            }
        }

        return null;
    }
}

$mock_data = [
    'areas' => [
        ['id' => 1, 'name' => 'Sala'],
    ],
    'tables' => [
        ['id' => 1, 'area_id' => 1, 'name' => 'T1', 'capacity' => 2, 'min_capacity' => 1, 'max_capacity' => 4, 'is_active' => 1],
        ['id' => 2, 'area_id' => 1, 'name' => 'T2', 'capacity' => 4, 'min_capacity' => 2, 'max_capacity' => 6, 'is_active' => 1],
    ],
    'assignments' => [
        ['booking_id' => 101, 'table_id' => 2, 'group_id' => null, 'assignment_type' => 'single'],
    ],
    'postmeta' => [
        101 => [
            ['meta_key' => 'rbf_data', 'meta_value' => '2024-06-01'],
            ['meta_key' => 'rbf_orario', 'meta_value' => 'cena'],
            ['meta_key' => 'rbf_orario', 'meta_value' => '20:00'],
        ],
    ],
    'posts' => [
        101 => ['post_status' => 'publish'],
    ],
];

global $wpdb;
$wpdb = new MockWPDB($mock_data);

require_once __DIR__ . '/../includes/table-management.php';

$available = rbf_check_table_availability('2024-06-01', '20:00', 'cena');
$available_ids = array_column($available, 'id');

if (in_array(2, $available_ids, true)) {
    echo "❌ Legacy compatibility test failed: assigned table still available\n";
    exit(1);
}

echo "✅ Legacy compatibility test passed: legacy booking correctly excluded table 2\n";
