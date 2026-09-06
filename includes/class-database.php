<?php
if (!defined('ABSPATH')) {
    exit;
}

class FEU_Einsatz_Database {

    private const STATISTICS_DASHBOARD_CACHE_PREFIX = 'feu_einsatz_statistics_dashboard_';
    private const STATISTICS_DASHBOARD_CACHE_INDEX_OPTION = 'feu_einsatz_statistics_dashboard_cache_keys';
    private const STATISTICS_DASHBOARD_CACHE_TTL = 3600;
    
    private static $active_instance = null;
    private $wpdb;
    private $table_participants;
    private $table_stats;
    private $table_organizations;
    private $table_street_registry;
    private $table_statistics_cache;
    private $table_archives;
    private $table_logs;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_participants = $wpdb->prefix . 'feu_einsatz_teilnehmer';
        $this->table_stats = $wpdb->prefix . 'feu_einsatz_statistiken';
        $this->table_organizations = $wpdb->prefix . 'feu_einsatz_organisationen';
        $this->table_street_registry = $wpdb->prefix . 'feu_einsatz_street_registry';
        $this->table_statistics_cache = $wpdb->prefix . 'feu_einsatz_statistics_cache';
        $this->table_archives = $wpdb->prefix . 'feu_einsatz_archive';
        $this->table_logs = $wpdb->prefix . 'feu_einsatz_logs';
        $this->ensure_participant_schema();
        $this->ensure_organization_schema();
        $this->ensure_street_registry_schema();
        $this->ensure_archive_schema();
        $this->ensure_log_schema();
        self::$active_instance = $this;
    }

    public static function get_active_instance() {
        return self::$active_instance;
    }

    private function table_exists($table_name) {
        $table_name = (string) $table_name;

        if ('' === $table_name) {
            return false;
        }

        $existing_table = $this->wpdb->get_var(
            $this->wpdb->prepare(
                'SHOW TABLES LIKE %s',
                $table_name
            )
        );

        return $existing_table === $table_name;
    }

    public function street_registry_table_exists() {
        return $this->table_exists($this->table_street_registry);
    }

    private function calculate_activity_map_distance_meters($point_a, $point_b) {
        if (
            !is_array($point_a)
            || !is_array($point_b)
            || !isset($point_a[0], $point_a[1], $point_b[0], $point_b[1])
        ) {
            return INF;
        }

        $earth_radius = 6371000;
        $lat_1 = deg2rad((float) $point_a[0]);
        $lat_2 = deg2rad((float) $point_b[0]);
        $delta_lat = deg2rad((float) $point_b[0] - (float) $point_a[0]);
        $delta_lng = deg2rad((float) $point_b[1] - (float) $point_a[1]);
        $distance = sin($delta_lat / 2) * sin($delta_lat / 2)
            + cos($lat_1) * cos($lat_2) * sin($delta_lng / 2) * sin($delta_lng / 2);

        return $earth_radius * 2 * atan2(sqrt($distance), sqrt(max(0, 1 - $distance)));
    }

    private function normalize_activity_map_segment_points($segment) {
        $points_source = $segment;

        if (is_array($segment) && isset($segment['points']) && is_array($segment['points'])) {
            $points_source = $segment['points'];
        }

        $normalized = [];

        foreach ((array) $points_source as $point) {
            if (
                is_array($point)
                && isset($point[0], $point[1])
                && is_numeric($point[0])
                && is_numeric($point[1])
            ) {
                $normalized[] = [(float) $point[0], (float) $point[1]];
                continue;
            }

            if (
                is_array($point)
                && isset($point['lat'], $point['lng'])
                && is_numeric($point['lat'])
                && is_numeric($point['lng'])
            ) {
                $normalized[] = [(float) $point['lat'], (float) $point['lng']];
                continue;
            }

            if (
                is_array($point)
                && isset($point['lat'], $point['lon'])
                && is_numeric($point['lat'])
                && is_numeric($point['lon'])
            ) {
                $normalized[] = [(float) $point['lat'], (float) $point['lon']];
            }
        }

        return count($normalized) >= 2 ? $normalized : [];
    }

    private function get_activity_map_segment_distance_to_coordinates($segment_points, $coordinates) {
        if (
            empty($segment_points)
            || !is_array($coordinates)
            || !isset($coordinates['lat'], $coordinates['lng'])
            || !is_numeric($coordinates['lat'])
            || !is_numeric($coordinates['lng'])
        ) {
            return INF;
        }

        $reference = [(float) $coordinates['lat'], (float) $coordinates['lng']];
        $distance = INF;

        foreach ($segment_points as $point) {
            $distance = min($distance, $this->calculate_activity_map_distance_meters($point, $reference));
        }

        return $distance;
    }

    private function get_activity_map_segment_midpoint($segment_points) {
        if (count($segment_points) < 2) {
            return null;
        }

        $segment_lengths = [];
        $total_length = 0.0;

        for ($index = 1; $index < count($segment_points); $index++) {
            $length = $this->calculate_activity_map_distance_meters($segment_points[$index - 1], $segment_points[$index]);
            $segment_lengths[] = $length;
            $total_length += $length;
        }

        if ($total_length <= 0.01) {
            $middle_index = (int) floor(count($segment_points) / 2);

            return [
                'latitude' => (float) $segment_points[$middle_index][0],
                'longitude' => (float) $segment_points[$middle_index][1],
            ];
        }

        $target_length = $total_length / 2;
        $traversed_length = 0.0;

        foreach ($segment_lengths as $index => $segment_length) {
            if (($traversed_length + $segment_length) < $target_length) {
                $traversed_length += $segment_length;
                continue;
            }

            $start_point = $segment_points[$index];
            $end_point = $segment_points[$index + 1];
            $remaining_length = $target_length - $traversed_length;
            $ratio = $segment_length > 0 ? ($remaining_length / $segment_length) : 0.5;
            $ratio = max(0.0, min(1.0, $ratio));

            return [
                'latitude' => (float) $start_point[0] + (((float) $end_point[0] - (float) $start_point[0]) * $ratio),
                'longitude' => (float) $start_point[1] + (((float) $end_point[1] - (float) $start_point[1]) * $ratio),
            ];
        }

        $last_point = $segment_points[count($segment_points) - 1];

        return [
            'latitude' => (float) $last_point[0],
            'longitude' => (float) $last_point[1],
        ];
    }

    private function get_activity_map_segment_endpoints($segment_points) {
        if (!is_array($segment_points) || count($segment_points) < 2) {
            return null;
        }

        return [
            'start' => $segment_points[0],
            'end' => $segment_points[count($segment_points) - 1],
        ];
    }

    private function activity_map_segments_connect($left_points, $right_points, $endpoint_tolerance_meters = 35.0) {
        $left_endpoints = $this->get_activity_map_segment_endpoints($left_points);
        $right_endpoints = $this->get_activity_map_segment_endpoints($right_points);

        if (!$left_endpoints || !$right_endpoints) {
            return false;
        }

        foreach (['start', 'end'] as $left_side) {
            foreach (['start', 'end'] as $right_side) {
                if ($this->calculate_activity_map_distance_meters($left_endpoints[$left_side], $right_endpoints[$right_side]) <= $endpoint_tolerance_meters) {
                    return true;
                }
            }
        }

        return false;
    }

    private function build_activity_map_connected_group($segments, $seed_index, $endpoint_tolerance_meters = 55.0) {
        if (!isset($segments[$seed_index]) || !is_array($segments[$seed_index])) {
            return [];
        }

        $group = [$segments[$seed_index]];
        $processed_indexes = [$seed_index => true];
        $did_expand = true;

        while ($did_expand) {
            $did_expand = false;

            foreach ($segments as $index => $candidate_segment) {
                if (isset($processed_indexes[$index])) {
                    continue;
                }

                foreach ($group as $group_segment) {
                    if (!$this->activity_map_segments_connect($candidate_segment, $group_segment, $endpoint_tolerance_meters)) {
                        continue;
                    }

                    $group[] = $candidate_segment;
                    $processed_indexes[$index] = true;
                    $did_expand = true;
                    break;
                }
            }
        }

        return $group;
    }

    private function get_activity_map_group_representative_point($segments) {
        $group_points = [];

        foreach ((array) $segments as $segment_points) {
            foreach ((array) $segment_points as $point) {
                if (
                    is_array($point)
                    && isset($point[0], $point[1])
                    && is_numeric($point[0])
                    && is_numeric($point[1])
                ) {
                    $group_points[] = [(float) $point[0], (float) $point[1]];
                }
            }
        }

        if (empty($group_points)) {
            return null;
        }

        $latitudes = array_column($group_points, 0);
        $longitudes = array_column($group_points, 1);
        $target_point = [
            (min($latitudes) + max($latitudes)) / 2,
            (min($longitudes) + max($longitudes)) / 2,
        ];
        $best_point = $group_points[0];
        $best_distance = INF;

        foreach ($group_points as $point) {
            $distance = $this->calculate_activity_map_distance_meters($point, $target_point);

            if ($distance < $best_distance) {
                $best_distance = $distance;
                $best_point = $point;
            }
        }

        return [
            'latitude' => (float) $best_point[0],
            'longitude' => (float) $best_point[1],
        ];
    }

    private function get_activity_map_full_geometry_midpoint($segments, $preferred_center = null) {
        $all_points = [];

        foreach ((array) $segments as $segment_points) {
            foreach ((array) $segment_points as $point) {
                if (
                    is_array($point)
                    && isset($point[0], $point[1])
                    && is_numeric($point[0])
                    && is_numeric($point[1])
                ) {
                    $all_points[] = [(float) $point[0], (float) $point[1]];
                }
            }
        }

        if (empty($all_points)) {
            return null;
        }

        $target_point = null;

        if (
            is_array($preferred_center)
            && isset($preferred_center[0], $preferred_center[1])
            && is_numeric($preferred_center[0])
            && is_numeric($preferred_center[1])
        ) {
            $target_point = [(float) $preferred_center[0], (float) $preferred_center[1]];
        } else {
            $latitudes = array_column($all_points, 0);
            $longitudes = array_column($all_points, 1);
            $target_point = [
                (min($latitudes) + max($latitudes)) / 2,
                (min($longitudes) + max($longitudes)) / 2,
            ];
        }

        $best_point = $all_points[0];
        $best_distance = INF;

        foreach ($all_points as $point) {
            $distance = $this->calculate_activity_map_distance_meters($point, $target_point);

            if ($distance < $best_distance) {
                $best_distance = $distance;
                $best_point = $point;
            }
        }

        return [
            'latitude' => (float) $best_point[0],
            'longitude' => (float) $best_point[1],
        ];
    }

    private function get_activity_map_segment_length_meters($segment_points) {
        if (!is_array($segment_points) || count($segment_points) < 2) {
            return 0.0;
        }

        $length = 0.0;

        for ($index = 1; $index < count($segment_points); $index++) {
            $length += $this->calculate_activity_map_distance_meters($segment_points[$index - 1], $segment_points[$index]);
        }

        return $length;
    }

    private function get_activity_map_group_midpoint($segments) {
        $best_segment = null;
        $best_length = 0.0;

        foreach ((array) $segments as $segment_points) {
            if (!is_array($segment_points) || count($segment_points) < 2) {
                continue;
            }

            $segment_length = $this->get_activity_map_segment_length_meters($segment_points);

            if ($segment_length > $best_length) {
                $best_length = $segment_length;
                $best_segment = $segment_points;
            }
        }

        if (null === $best_segment) {
            return null;
        }

        return $this->get_activity_map_segment_midpoint($best_segment);
    }

    private function resolve_activity_map_street_midpoint($post_id, $coordinates) {
        $street_geometry = get_post_meta((int) $post_id, '_feu_einsatz_street_geometry_final', true);

        if (
            (!is_array($street_geometry) || empty($street_geometry))
            && class_exists('FEU_Einsatz_Street_Cache')
        ) {
            $street = (string) get_post_meta((int) $post_id, '_feu_einsatz_strasse', true);
            $postcode = (string) get_post_meta((int) $post_id, '_feu_einsatz_plz', true);
            $city = (string) get_post_meta((int) $post_id, '_feu_einsatz_stadt', true);
            $shared_street_cache = FEU_Einsatz_Street_Cache::get($street, $postcode, $city);

            if (is_array($shared_street_cache) && !empty($shared_street_cache['geometry'])) {
                $street_geometry = $shared_street_cache['geometry'];
            }
        }

        if (!is_array($street_geometry) || empty($street_geometry)) {
            return null;
        }

        $normalized_segments = [];
        $best_segment_index = null;
        $best_distance = INF;

        foreach ($street_geometry as $segment) {
            $segment_points = $this->normalize_activity_map_segment_points($segment);

            if (count($segment_points) < 2) {
                continue;
            }

            $normalized_segments[] = $segment_points;
            $segment_index = count($normalized_segments) - 1;
            $segment_distance = $this->get_activity_map_segment_distance_to_coordinates($segment_points, $coordinates);

            if ($segment_distance < $best_distance) {
                $best_distance = $segment_distance;
                $best_segment_index = $segment_index;
            }
        }

        if (empty($normalized_segments) || null === $best_segment_index) {
            return null;
        }

        $connected_group = $this->build_activity_map_connected_group($normalized_segments, $best_segment_index);
        $group_midpoint = $this->get_activity_map_group_midpoint($connected_group);

        if ($group_midpoint) {
            return $group_midpoint;
        }

        $representative_point = $this->get_activity_map_group_representative_point($connected_group);

        if ($representative_point) {
            return $representative_point;
        }

        $stored_street_center = get_post_meta((int) $post_id, '_feu_einsatz_street_center_final', true);
        $full_geometry_midpoint = $this->get_activity_map_full_geometry_midpoint($normalized_segments, $stored_street_center);

        if ($full_geometry_midpoint) {
            return $full_geometry_midpoint;
        }

        return $this->get_activity_map_segment_midpoint($normalized_segments[$best_segment_index]);
    }

    private function resolve_activity_map_coordinates($post_id, $latitude, $longitude, $house_number = '') {
        $latitude = is_numeric($latitude) ? (float) $latitude : null;
        $longitude = is_numeric($longitude) ? (float) $longitude : null;
        $house_number = trim((string) $house_number);

        if (null !== $latitude && null !== $longitude) {
            if ('' === $house_number) {
                $base_coordinates = [
                    'lat' => $latitude,
                    'lng' => $longitude,
                ];
                $street_midpoint = $this->resolve_activity_map_street_midpoint((int) $post_id, $base_coordinates);

                if ($street_midpoint) {
                    return $street_midpoint;
                }
            }

            return [
                'latitude' => $latitude,
                'longitude' => $longitude,
            ];
        }

        $street_center = get_post_meta((int) $post_id, '_feu_einsatz_street_center_final', true);

        if (
            is_array($street_center)
            && isset($street_center[0], $street_center[1])
            && is_numeric($street_center[0])
            && is_numeric($street_center[1])
        ) {
            return [
                'latitude' => (float) $street_center[0],
                'longitude' => (float) $street_center[1],
            ];
        }

        return null;
    }

    private function build_activity_map_point($point, $include_postcode = false) {
        $coordinates = $this->resolve_activity_map_coordinates(
            $point->ID,
            isset($point->latitude) ? $point->latitude : null,
            isset($point->longitude) ? $point->longitude : null,
            isset($point->hausnummer) ? $point->hausnummer : ''
        );

        if (!$coordinates) {
            return null;
        }

        $street = isset($point->strasse) ? sanitize_text_field((string) $point->strasse) : '';
        $house_number = isset($point->hausnummer) ? sanitize_text_field((string) $point->hausnummer) : '';
        $address_label = trim($street . ' ' . $house_number);
        $raw_date = !empty($point->event_date) ? (string) $point->event_date : (string) $point->post_date;
        $timestamp = '' !== trim($raw_date) ? strtotime($raw_date) : false;

        $mapped_point = [
            'id' => (int) $point->ID,
            'title' => $point->post_title,
            'date' => $raw_date,
            'date_label' => false !== $timestamp ? date_i18n('d.m.Y', $timestamp) : '',
            'street' => $address_label,
            'street_name' => $street,
            'house_number' => $house_number,
            'city' => $point->stadt,
            'latitude' => $coordinates['latitude'],
            'longitude' => $coordinates['longitude'],
            'url' => get_permalink((int) $point->ID),
            'edit_url' => current_user_can('edit_post', (int) $point->ID)
                ? admin_url('post.php?post=' . absint($point->ID) . '&action=edit&feu_einsatz_einsatzbericht=1')
                : '',
        ];

        if ($include_postcode) {
            $mapped_point['postcode'] = isset($point->plz) ? sanitize_text_field((string) $point->plz) : '';
        }

        return $mapped_point;
    }

    private function ensure_participant_schema() {
        if (!$this->table_exists($this->table_participants)) {
            return;
        }

        $columns = [
            'job_title' => "ALTER TABLE {$this->table_participants} ADD COLUMN job_title varchar(150) DEFAULT '' AFTER nachname",
            'entry_date' => "ALTER TABLE {$this->table_participants} ADD COLUMN entry_date varchar(20) DEFAULT '' AFTER job_title",
            'rank_title' => "ALTER TABLE {$this->table_participants} ADD COLUMN rank_title varchar(150) DEFAULT '' AFTER entry_date",
            'member_function' => "ALTER TABLE {$this->table_participants} ADD COLUMN member_function varchar(150) DEFAULT '' AFTER rank_title",
            'education' => "ALTER TABLE {$this->table_participants} ADD COLUMN education TEXT NULL AFTER member_function",
            'description' => "ALTER TABLE {$this->table_participants} ADD COLUMN description LONGTEXT NULL AFTER education",
            'sort_order' => "ALTER TABLE {$this->table_participants} ADD COLUMN sort_order int(11) DEFAULT 0 AFTER description",
            'category_ids' => "ALTER TABLE {$this->table_participants} ADD COLUMN category_ids LONGTEXT NULL AFTER sort_order",
            'gallery_ids' => "ALTER TABLE {$this->table_participants} ADD COLUMN gallery_ids LONGTEXT NULL AFTER category_ids",
            'primary_image_id' => "ALTER TABLE {$this->table_participants} ADD COLUMN primary_image_id int(11) DEFAULT 0 AFTER gallery_ids",
            'default_functions' => "ALTER TABLE {$this->table_participants} ADD COLUMN default_functions LONGTEXT NULL AFTER primary_image_id",
            'is_archived' => "ALTER TABLE {$this->table_participants} ADD COLUMN is_archived tinyint(1) DEFAULT 0 AFTER default_functions",
            'is_deleted' => "ALTER TABLE {$this->table_participants} ADD COLUMN is_deleted tinyint(1) DEFAULT 0 AFTER is_archived",
        ];

        foreach ($columns as $column_name => $sql) {
            $column_exists = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SHOW COLUMNS FROM {$this->table_participants} LIKE %s",
                    $column_name
                )
            );

            if (!$column_exists) {
                $this->wpdb->query($sql);
            }
        }

        $index_exists = $this->wpdb->get_var(
            "SHOW INDEX FROM {$this->table_participants} WHERE Key_name = 'archived_order'"
        );

        if (!$index_exists) {
            $this->wpdb->query(
                "ALTER TABLE {$this->table_participants} ADD KEY archived_order (is_archived, sort_order)"
            );
        }
    }

    private function ensure_organization_schema() {
        if (!$this->table_exists($this->table_organizations)) {
            return;
        }

        $schema_updates = [
            'color' => "ALTER TABLE {$this->table_organizations} ADD COLUMN color varchar(7) DEFAULT '#0a4b78' AFTER name",
            'post_link' => "ALTER TABLE {$this->table_organizations} ADD COLUMN post_link varchar(255) DEFAULT '' AFTER color",
            'is_archived' => "ALTER TABLE {$this->table_organizations} ADD COLUMN is_archived tinyint(1) DEFAULT 0 AFTER post_link",
            'sort_order' => "ALTER TABLE {$this->table_organizations} ADD COLUMN sort_order int(11) DEFAULT 0 AFTER is_archived",
        ];

        foreach ($schema_updates as $column_name => $sql) {
            $column_exists = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SHOW COLUMNS FROM {$this->table_organizations} LIKE %s",
                    $column_name
                )
            );

            if (!$column_exists) {
                $this->wpdb->query($sql);
            }
        }

        $index_exists = $this->wpdb->get_var(
            "SHOW INDEX FROM {$this->table_organizations} WHERE Key_name = 'archived_name'"
        );

        if (!$index_exists) {
            $this->wpdb->query(
                "ALTER TABLE {$this->table_organizations} ADD KEY archived_name (is_archived, name)"
            );
        }

        $sort_index_exists = $this->wpdb->get_var(
            "SHOW INDEX FROM {$this->table_organizations} WHERE Key_name = 'archived_order'"
        );

        if (!$sort_index_exists) {
            $this->wpdb->query(
                "ALTER TABLE {$this->table_organizations} ADD KEY archived_order (is_archived, sort_order)"
            );
        }
    }

    private function ensure_street_registry_schema() {
        if (!$this->table_exists($this->table_street_registry)) {
            return;
        }

        $columns = [
            'street' => "ALTER TABLE {$this->table_street_registry} ADD COLUMN street varchar(191) NOT NULL AFTER id",
            'postcode' => "ALTER TABLE {$this->table_street_registry} ADD COLUMN postcode varchar(5) DEFAULT '' AFTER street",
            'city' => "ALTER TABLE {$this->table_street_registry} ADD COLUMN city varchar(120) DEFAULT 'Hamburg' AFTER postcode",
            'sort_order' => "ALTER TABLE {$this->table_street_registry} ADD COLUMN sort_order int(11) DEFAULT 0 AFTER city",
            'created_at' => "ALTER TABLE {$this->table_street_registry} ADD COLUMN created_at datetime DEFAULT CURRENT_TIMESTAMP AFTER sort_order",
        ];

        foreach ($columns as $column_name => $sql) {
            $column_exists = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SHOW COLUMNS FROM {$this->table_street_registry} LIKE %s",
                    $column_name
                )
            );

            if (!$column_exists) {
                $this->wpdb->query($sql);
            }
        }

        $street_unique_index = $this->wpdb->get_var(
            "SHOW INDEX FROM {$this->table_street_registry} WHERE Key_name = 'street_lookup'"
        );

        if (!$street_unique_index) {
            $this->wpdb->query(
                "ALTER TABLE {$this->table_street_registry} ADD UNIQUE KEY street_lookup (street, postcode, city)"
            );
        }

        $sort_index_exists = $this->wpdb->get_var(
            "SHOW INDEX FROM {$this->table_street_registry} WHERE Key_name = 'street_order'"
        );

        if (!$sort_index_exists) {
            $this->wpdb->query(
                "ALTER TABLE {$this->table_street_registry} ADD KEY street_order (sort_order, street)"
            );
        }
    }

    private function ensure_archive_schema() {
        if (!$this->table_exists($this->table_archives)) {
            return;
        }

        $columns = [
            'archive_key' => "ALTER TABLE {$this->table_archives} ADD COLUMN archive_key varchar(64) NOT NULL AFTER id",
            'filename' => "ALTER TABLE {$this->table_archives} ADD COLUMN filename varchar(255) NOT NULL AFTER archive_key",
            'label' => "ALTER TABLE {$this->table_archives} ADD COLUMN label varchar(255) DEFAULT '' AFTER filename",
            'file_size' => "ALTER TABLE {$this->table_archives} ADD COLUMN file_size bigint(20) unsigned DEFAULT 0 AFTER label",
            'created_by' => "ALTER TABLE {$this->table_archives} ADD COLUMN created_by bigint(20) unsigned DEFAULT 0 AFTER file_size",
            'created_by_name' => "ALTER TABLE {$this->table_archives} ADD COLUMN created_by_name varchar(191) DEFAULT '' AFTER created_by",
            'source' => "ALTER TABLE {$this->table_archives} ADD COLUMN source varchar(40) DEFAULT 'created' AFTER created_by_name",
            'notes' => "ALTER TABLE {$this->table_archives} ADD COLUMN notes longtext NULL AFTER source",
            'manifest' => "ALTER TABLE {$this->table_archives} ADD COLUMN manifest longtext NULL AFTER notes",
            'created_at' => "ALTER TABLE {$this->table_archives} ADD COLUMN created_at datetime DEFAULT CURRENT_TIMESTAMP AFTER manifest",
            'restored_at' => "ALTER TABLE {$this->table_archives} ADD COLUMN restored_at datetime NULL AFTER created_at",
        ];

        foreach ($columns as $column_name => $sql) {
            $column_exists = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SHOW COLUMNS FROM {$this->table_archives} LIKE %s",
                    $column_name
                )
            );

            if (!$column_exists) {
                $this->wpdb->query($sql);
            }
        }

        $archive_key_index = $this->wpdb->get_var(
            "SHOW INDEX FROM {$this->table_archives} WHERE Key_name = 'archive_key'"
        );

        if (!$archive_key_index) {
            $this->wpdb->query(
                "ALTER TABLE {$this->table_archives} ADD UNIQUE KEY archive_key (archive_key)"
            );
        }

        $created_at_index = $this->wpdb->get_var(
            "SHOW INDEX FROM {$this->table_archives} WHERE Key_name = 'created_at'"
        );

        if (!$created_at_index) {
            $this->wpdb->query(
                "ALTER TABLE {$this->table_archives} ADD KEY created_at (created_at)"
            );
        }
    }

    private function ensure_log_schema() {
        if (!$this->table_exists($this->table_logs)) {
            return;
        }

        $columns = [
            'user_id' => "ALTER TABLE {$this->table_logs} ADD COLUMN user_id bigint(20) unsigned DEFAULT 0 AFTER id",
            'user_name' => "ALTER TABLE {$this->table_logs} ADD COLUMN user_name varchar(191) DEFAULT '' AFTER user_id",
            'action_type' => "ALTER TABLE {$this->table_logs} ADD COLUMN action_type varchar(80) NOT NULL AFTER user_name",
            'entity_type' => "ALTER TABLE {$this->table_logs} ADD COLUMN entity_type varchar(80) DEFAULT '' AFTER action_type",
            'entity_id' => "ALTER TABLE {$this->table_logs} ADD COLUMN entity_id bigint(20) unsigned DEFAULT 0 AFTER entity_type",
            'message' => "ALTER TABLE {$this->table_logs} ADD COLUMN message text NULL AFTER entity_id",
            'details' => "ALTER TABLE {$this->table_logs} ADD COLUMN details longtext NULL AFTER message",
            'page_slug' => "ALTER TABLE {$this->table_logs} ADD COLUMN page_slug varchar(191) DEFAULT '' AFTER details",
            'page_url' => "ALTER TABLE {$this->table_logs} ADD COLUMN page_url text NULL AFTER page_slug",
            'ip_address' => "ALTER TABLE {$this->table_logs} ADD COLUMN ip_address varchar(64) DEFAULT '' AFTER page_url",
            'created_at' => "ALTER TABLE {$this->table_logs} ADD COLUMN created_at datetime DEFAULT CURRENT_TIMESTAMP AFTER ip_address",
        ];

        foreach ($columns as $column_name => $sql) {
            $column_exists = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SHOW COLUMNS FROM {$this->table_logs} LIKE %s",
                    $column_name
                )
            );

            if (!$column_exists) {
                $this->wpdb->query($sql);
            }
        }

        $action_index = $this->wpdb->get_var(
            "SHOW INDEX FROM {$this->table_logs} WHERE Key_name = 'action_type'"
        );

        if (!$action_index) {
            $this->wpdb->query(
                "ALTER TABLE {$this->table_logs} ADD KEY action_type (action_type)"
            );
        }

        $created_at_index = $this->wpdb->get_var(
            "SHOW INDEX FROM {$this->table_logs} WHERE Key_name = 'created_at'"
        );

        if (!$created_at_index) {
            $this->wpdb->query(
                "ALTER TABLE {$this->table_logs} ADD KEY created_at (created_at)"
            );
        }
    }

    private function normalize_default_functions($default_functions) {
        if (!is_array($default_functions)) {
            $default_functions = [];
        }

        $default_functions = array_map('sanitize_text_field', $default_functions);
        $default_functions = array_values(array_unique(array_filter($default_functions)));

        return array_slice($default_functions, 0, 5);
    }

    private function normalize_id_list($values) {
        return array_values(
            array_unique(
                array_filter(
                    array_map('absint', (array) $values)
                )
            )
        );
    }

    private function normalize_entry_date($value) {
        $value = sanitize_text_field((string) $value);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        return '';
    }

    private function normalize_participant_data($participant) {
        $participant = is_array($participant) ? $participant : [];

        $gallery_ids = $this->normalize_id_list(
            isset($participant['gallery_ids']) ? $participant['gallery_ids'] : []
        );
        $category_ids = $this->normalize_id_list(
            isset($participant['category_ids']) ? $participant['category_ids'] : []
        );

        $primary_image_id = isset($participant['primary_image_id'])
            ? absint($participant['primary_image_id'])
            : 0;

        if (!$primary_image_id && !empty($gallery_ids)) {
            $primary_image_id = (int) $gallery_ids[0];
        }

        if ($primary_image_id && !in_array($primary_image_id, $gallery_ids, true)) {
            array_unshift($gallery_ids, $primary_image_id);
            $gallery_ids = $this->normalize_id_list($gallery_ids);
        }

        return [
            'vorname' => isset($participant['vorname']) ? sanitize_text_field($participant['vorname']) : '',
            'nachname' => isset($participant['nachname']) ? sanitize_text_field($participant['nachname']) : '',
            'job_title' => isset($participant['job_title']) ? sanitize_text_field($participant['job_title']) : '',
            'entry_date' => $this->normalize_entry_date(isset($participant['entry_date']) ? $participant['entry_date'] : ''),
            'rank_title' => isset($participant['rank_title']) ? sanitize_text_field($participant['rank_title']) : '',
            'member_function' => isset($participant['member_function']) ? sanitize_text_field($participant['member_function']) : '',
            'education' => isset($participant['education']) ? sanitize_text_field($participant['education']) : '',
            'description' => isset($participant['description']) ? sanitize_textarea_field($participant['description']) : '',
            'sort_order' => isset($participant['sort_order']) ? absint($participant['sort_order']) : 0,
            'category_ids' => $category_ids,
            'gallery_ids' => $gallery_ids,
            'primary_image_id' => $primary_image_id,
            'default_functions' => $this->normalize_default_functions(
                isset($participant['default_functions']) ? $participant['default_functions'] : []
            ),
            'is_archived' => empty($participant['is_archived']) ? 0 : 1,
            'is_deleted' => empty($participant['is_deleted']) ? 0 : 1,
        ];
    }

    private function decode_id_list($encoded) {
        if (empty($encoded)) {
            return [];
        }

        $decoded = json_decode((string) $encoded, true);

        if (!is_array($decoded)) {
            return [];
        }

        return $this->normalize_id_list($decoded);
    }

    private function hydrate_participant($participant) {
        if (!$participant) {
            return $participant;
        }

        $default_functions = [];

        if (!empty($participant->default_functions)) {
            $decoded = json_decode($participant->default_functions, true);
            if (is_array($decoded)) {
                $default_functions = $this->normalize_default_functions($decoded);
            }
        }

        $participant->job_title = isset($participant->job_title) ? (string) $participant->job_title : '';
        $participant->entry_date = isset($participant->entry_date) ? (string) $participant->entry_date : '';
        $participant->rank_title = isset($participant->rank_title) ? (string) $participant->rank_title : '';
        $participant->member_function = isset($participant->member_function) ? (string) $participant->member_function : '';
        $participant->education = isset($participant->education) ? (string) $participant->education : '';
        $participant->description = isset($participant->description) ? (string) $participant->description : '';
        $participant->sort_order = isset($participant->sort_order) ? (int) $participant->sort_order : 0;
        $participant->category_ids = $this->decode_id_list(
            isset($participant->category_ids) ? $participant->category_ids : ''
        );
        $participant->gallery_ids = $this->decode_id_list(
            isset($participant->gallery_ids) ? $participant->gallery_ids : ''
        );
        $participant->primary_image_id = isset($participant->primary_image_id)
            ? absint($participant->primary_image_id)
            : 0;
        $participant->default_functions = $default_functions;
        $participant->is_archived = !empty($participant->is_archived) ? 1 : 0;
        $participant->is_deleted = !empty($participant->is_deleted) ? 1 : 0;
        $participant->usage_count = isset($participant->usage_count) ? (int) $participant->usage_count : 0;

        if (!$participant->primary_image_id && !empty($participant->gallery_ids)) {
            $participant->primary_image_id = (int) $participant->gallery_ids[0];
        }

        return $participant;
    }
    /**
    * Tägliche Statistiken abrufen
    */
    public function get_daily_statistics($jahr = null) {
        if (!$jahr) $jahr = date('Y');
        $event_year_sql = $this->get_statistics_event_year_sql('event_date', 'p');
        $event_date_sql = $this->get_statistics_event_date_sql('event_date', 'p');
        
        $sql = $this->wpdb->prepare("
            SELECT 
                {$event_date_sql} as datum,
                COUNT(DISTINCT p.ID) as anzahl
            FROM {$this->wpdb->posts} p
            INNER JOIN {$this->wpdb->postmeta} pm ON p.ID = pm.post_id
            LEFT JOIN {$this->wpdb->postmeta} event_date ON p.ID = event_date.post_id AND event_date.meta_key = '_feu_einsatz_datum'
            WHERE p.post_type = 'post'
            AND p.post_status = 'publish'
            AND {$event_year_sql} = %d
            AND pm.meta_key = '_feu_einsatz_einsatzbericht'
            AND pm.meta_value = '1'
            GROUP BY {$event_date_sql}
            ORDER BY datum ASC
        ", $jahr);
        
        return $this->wpdb->get_results($sql);
    }
    
    /**
     * Datenbank-Tabellen erstellen
     */
    public function create_tables() {
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql1 = "CREATE TABLE IF NOT EXISTS {$this->table_participants} (
            id int(11) NOT NULL AUTO_INCREMENT,
            vorname varchar(100) NOT NULL,
            nachname varchar(100) NOT NULL,
            job_title varchar(150) DEFAULT '',
            entry_date varchar(20) DEFAULT '',
            rank_title varchar(150) DEFAULT '',
            member_function varchar(150) DEFAULT '',
            education text NULL,
            description longtext NULL,
            sort_order int(11) DEFAULT 0,
            category_ids longtext NULL,
            gallery_ids longtext NULL,
            primary_image_id int(11) DEFAULT 0,
            default_functions longtext NULL,
            is_archived tinyint(1) DEFAULT 0,
            is_deleted tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY archived_order (is_archived, sort_order)
        ) $charset_collate;";
        
        $sql2 = "CREATE TABLE IF NOT EXISTS {$this->table_stats} (
            id int(11) NOT NULL AUTO_INCREMENT,
            post_id int(11) NOT NULL,
            teilnehmer_id int(11) NOT NULL,
            funktion varchar(100) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_einsatz_teilnehmer (post_id, teilnehmer_id),
            KEY post_id (post_id),
            KEY teilnehmer_id (teilnehmer_id)
        ) $charset_collate;";
        
        $sql3 = "CREATE TABLE IF NOT EXISTS {$this->table_organizations} (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            color varchar(7) DEFAULT '#0a4b78',
            post_link varchar(255) DEFAULT '',
            is_archived tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY archived_name (is_archived, name)
        ) $charset_collate;";

        $sql3b = $this->get_street_registry_schema_sql($charset_collate);

        $sql4 = "CREATE TABLE IF NOT EXISTS {$this->table_archives} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            archive_key varchar(64) NOT NULL,
            filename varchar(255) NOT NULL,
            label varchar(255) DEFAULT '',
            file_size bigint(20) unsigned DEFAULT 0,
            created_by bigint(20) unsigned DEFAULT 0,
            created_by_name varchar(191) DEFAULT '',
            source varchar(40) DEFAULT 'created',
            notes longtext NULL,
            manifest longtext NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            restored_at datetime NULL,
            PRIMARY KEY (id),
            UNIQUE KEY archive_key (archive_key),
            KEY created_at (created_at)
        ) $charset_collate;";

        $sql5 = "CREATE TABLE IF NOT EXISTS {$this->table_logs} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned DEFAULT 0,
            user_name varchar(191) DEFAULT '',
            action_type varchar(80) NOT NULL,
            entity_type varchar(80) DEFAULT '',
            entity_id bigint(20) unsigned DEFAULT 0,
            message text NULL,
            details longtext NULL,
            page_slug varchar(191) DEFAULT '',
            page_url text NULL,
            ip_address varchar(64) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY action_type (action_type),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql1);
        dbDelta($sql2);
        dbDelta($sql3);
        dbDelta($sql3b);
        dbDelta($sql4);
        dbDelta($sql5);
    }

    /**
     * Returns the expensive data required by the statistics dashboard in one
     * annual cache entry. Report changes explicitly invalidate this cache.
     */
    public function get_statistics_dashboard_data($jahr = null) {
        $jahr = absint($jahr ?: date('Y'));
        $cache_key = self::STATISTICS_DASHBOARD_CACHE_PREFIX . $jahr;
        $cached = get_transient($cache_key);

        if (is_array($cached)) {
            return $cached;
        }

        $data = [
            'total' => $this->get_total_statistics($jahr),
            'categories' => $this->get_category_statistics($jahr),
            'participants' => $this->get_participant_statistics($jahr),
            'daily_stats' => $this->get_daily_statistics($jahr),
            'daily_report_entries' => $this->get_daily_report_entries($jahr),
            'activity_map_points' => $this->get_activity_map_points($jahr),
        ];

        set_transient($cache_key, $data, self::STATISTICS_DASHBOARD_CACHE_TTL);
        $keys = get_option(self::STATISTICS_DASHBOARD_CACHE_INDEX_OPTION, []);
        $keys = is_array($keys) ? array_values(array_unique(array_map('sanitize_key', $keys))) : [];
        $keys[] = $cache_key;
        update_option(self::STATISTICS_DASHBOARD_CACHE_INDEX_OPTION, array_values(array_unique($keys)), false);

        return $data;
    }

    public function invalidate_statistics_dashboard_cache(): void {
        $keys = get_option(self::STATISTICS_DASHBOARD_CACHE_INDEX_OPTION, []);

        foreach (is_array($keys) ? $keys : [] as $cache_key) {
            if (0 === strpos((string) $cache_key, self::STATISTICS_DASHBOARD_CACHE_PREFIX)) {
                delete_transient($cache_key);
            }
        }

        delete_option(self::STATISTICS_DASHBOARD_CACHE_INDEX_OPTION);
    }

    private function get_street_registry_schema_sql($charset_collate) {
        return "CREATE TABLE IF NOT EXISTS {$this->table_street_registry} (
            id int(11) NOT NULL AUTO_INCREMENT,
            street varchar(191) NOT NULL,
            postcode varchar(5) DEFAULT '',
            city varchar(120) DEFAULT 'Hamburg',
            sort_order int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY street_lookup (street, postcode, city),
            KEY street_order (sort_order, street)
        ) {$charset_collate};";
    }
    
    // ============ TEILNEHMER FUNKTIONEN ============
    
    /**
     * Alle Teilnehmer abrufen
     */
    public function get_participants($args = []) {
        $args = wp_parse_args($args, [
            'include_archived' => true,
            'include_deleted' => false,
            'order_by_usage' => false,
        ]);

        $where = [];

        if (empty($args['include_deleted'])) {
            $where[] = 't.is_deleted = 0';
        }

        if (empty($args['include_archived'])) {
            $where[] = 't.is_archived = 0';
        }

        $simple_sql = "SELECT t.* FROM {$this->table_participants} t";

        if (!empty($where)) {
            $simple_sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $simple_sql .= ' ORDER BY t.is_archived ASC, t.sort_order ASC, t.nachname ASC, t.vorname ASC';

        if (!empty($args['order_by_usage'])) {
            $sql = "
                SELECT
                    t.*,
                    COALESCE(usage_stats.usage_count, 0) AS usage_count
                FROM {$this->table_participants} t
                LEFT JOIN (
                    SELECT
                        s.teilnehmer_id,
                        COUNT(DISTINCT s.post_id) AS usage_count
                    FROM {$this->table_stats} s
                    INNER JOIN {$this->wpdb->posts} p ON p.ID = s.post_id
                    WHERE p.post_status = 'publish'
                    AND p.post_type = 'post'
                    GROUP BY s.teilnehmer_id
                ) usage_stats ON usage_stats.teilnehmer_id = t.id
            ";

            if (!empty($where)) {
                $sql .= ' WHERE ' . implode(' AND ', $where);
            }

            $sql .= ' ORDER BY t.is_deleted ASC, t.is_archived ASC, usage_count DESC, t.sort_order ASC, t.nachname ASC, t.vorname ASC';
            $participants = $this->wpdb->get_results($sql);

            if (!is_array($participants) || !empty($this->wpdb->last_error)) {
                $participants = $this->wpdb->get_results($simple_sql);
            }
        } else {
            $participants = $this->wpdb->get_results($simple_sql);
        }

        if (!is_array($participants)) {
            $participants = [];
        }

        return array_map([$this, 'hydrate_participant'], $participants);
    }
    
    /**
     * Einzelnen Teilnehmer abrufen
     */
    public function get_participant($id) {
        $participant = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_participants} WHERE id = %d",
                $id
            )
        );

        return $this->hydrate_participant($participant);
    }
    
    /**
     * Teilnehmer speichern (neu oder bearbeiten)
     */
    public function save_participant($id, $participant, $nachname = '', $default_functions = []) {
        if (!is_array($participant)) {
            $participant = [
                'vorname' => $participant,
                'nachname' => $nachname,
                'default_functions' => $default_functions,
            ];
        }

        $participant = $this->normalize_participant_data($participant);
        $data = [
            'vorname' => $participant['vorname'],
            'nachname' => $participant['nachname'],
            'job_title' => $participant['job_title'],
            'entry_date' => $participant['entry_date'],
            'rank_title' => $participant['rank_title'],
            'member_function' => $participant['member_function'],
            'education' => $participant['education'],
            'description' => $participant['description'],
            'sort_order' => $participant['sort_order'],
            'category_ids' => wp_json_encode($participant['category_ids']),
            'gallery_ids' => wp_json_encode($participant['gallery_ids']),
            'primary_image_id' => $participant['primary_image_id'],
            'default_functions' => wp_json_encode($participant['default_functions']),
            'is_archived' => $participant['is_archived'],
            'is_deleted' => $participant['is_deleted'],
        ];
        $formats = ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%d', '%d'];

        if ($id > 0) {
            return $this->wpdb->update($this->table_participants, $data, ['id' => $id], $formats, ['%d']);
        }

        $inserted = $this->wpdb->insert($this->table_participants, $data, $formats);

        return false === $inserted ? false : (int) $this->wpdb->insert_id;
    }
    
    /**
     * Teilnehmer löschen
     */
    public function set_participant_archive_status($id, $archived) {
        $result = $this->wpdb->update(
            $this->table_participants,
            ['is_archived' => $archived ? 1 : 0],
            ['id' => absint($id)],
            ['%d'],
            ['%d']
        );

        return false === $result ? false : true;
    }

    public function set_participant_deleted_status($id, $deleted) {
        $result = $this->wpdb->update(
            $this->table_participants,
            [
                'is_deleted' => $deleted ? 1 : 0,
                'is_archived' => $deleted ? 1 : 0,
            ],
            ['id' => absint($id)],
            ['%d', '%d'],
            ['%d']
        );

        return false === $result ? false : true;
    }

    public function delete_participant($id) {
        return $this->set_participant_deleted_status($id, true);
    }

    private function get_statistics_event_date_sql($event_meta_alias = 'event_date', $post_alias = 'p') {
        return "COALESCE(NULLIF({$event_meta_alias}.meta_value, ''), DATE({$post_alias}.post_date))";
    }

    private function get_statistics_event_year_sql($event_meta_alias = 'event_date', $post_alias = 'p') {
        return "CAST(LEFT(" . $this->get_statistics_event_date_sql($event_meta_alias, $post_alias) . ", 4) AS UNSIGNED)";
    }

    private function get_statistics_event_month_sql($event_meta_alias = 'event_date', $post_alias = 'p') {
        return "CAST(SUBSTRING(" . $this->get_statistics_event_date_sql($event_meta_alias, $post_alias) . ", 6, 2) AS UNSIGNED)";
    }

    private function get_legacy_function_aggregate_sql($stats_alias = 's') {
        $function_column = "{$stats_alias}.funktion";

        return "
                SUM(CASE WHEN {$function_column} = 'Maschinist' THEN 1 ELSE 0 END) as maschinist,
                SUM(CASE WHEN {$function_column} = 'Gruppenführer' THEN 1 ELSE 0 END) as gruppenfuhrer,
                SUM(CASE WHEN {$function_column} IN ('PA TF Träger 1', 'ATF') THEN 1 ELSE 0 END) as pa_tf_traeger1,
                SUM(CASE WHEN {$function_column} IN ('PA Träger 2', 'ATM') THEN 1 ELSE 0 END) as pa_traeger2,
                SUM(CASE WHEN {$function_column} = 'Melder' THEN 1 ELSE 0 END) as melder,
                SUM(CASE WHEN {$function_column} IN ('Wasser 1', 'WTF') THEN 1 ELSE 0 END) as wasser1,
                SUM(CASE WHEN {$function_column} IN ('Wasser 2', 'WTM') THEN 1 ELSE 0 END) as wasser2,
                SUM(CASE WHEN {$function_column} IN ('Schlauch 1', 'STF') THEN 1 ELSE 0 END) as schlauch1,
                SUM(CASE WHEN {$function_column} IN ('Schlauch 2', 'STM') THEN 1 ELSE 0 END) as schlauch2,
                SUM(CASE WHEN {$function_column} = 'Keine Funktion' OR {$function_column} = '' OR {$function_column} IS NULL THEN 1 ELSE 0 END) as keine_funktion";
    }
    
    // ============ STATISTIK FUNKTIONEN ============
    
    /**
     * Teilnehmer-Statistiken für einen Einsatz speichern
     */
    public function save_participant_stats($post_id, $teilnehmer) {
        // Lösche alte Einträge
        $this->wpdb->delete($this->table_stats, ['post_id' => $post_id]);
        
        if (empty($teilnehmer) || !is_array($teilnehmer)) {
            return;
        }
        
        // Speichere neue Einträge
        foreach ($teilnehmer as $t) {
            if (empty($t['id'])) continue;
            
            $participant = $this->get_participant((int) $t['id']);
            $participant_defaults = ($participant && !empty($participant->default_functions) && is_array($participant->default_functions))
                ? $participant->default_functions
                : [];
            $funktion = class_exists('FEU_Einsatz_Installer')
                ? FEU_Einsatz_Installer::resolve_assignment_function(
                    !empty($t['funktion']) ? sanitize_text_field($t['funktion']) : '',
                    $participant_defaults,
                    get_option('feu_einsatz_functions', FEU_Einsatz_Installer::get_default_functions())
                )
                : (!empty($t['funktion']) ? sanitize_text_field($t['funktion']) : 'Mannschaft');
            
            $this->wpdb->insert($this->table_stats, [
                'post_id' => $post_id,
                'teilnehmer_id' => intval($t['id']),
                'funktion' => $funktion
            ]);
        }
    }
    
    /**
     * Teilnehmer-Statistiken für ein Jahr abrufen
     */
    public function get_participant_statistics($jahr = null) {
        if (!$jahr) $jahr = date('Y');
        $event_year_sql = $this->get_statistics_event_year_sql('event_date', 'p');
        $legacy_function_aggregate_sql = $this->get_legacy_function_aggregate_sql('s');
        
        $sql = $this->wpdb->prepare("
            SELECT 
                t.id,
                t.vorname,
                t.nachname,
                COUNT(DISTINCT s.post_id) as gesamt_einsaetze,
                {$legacy_function_aggregate_sql}
            FROM {$this->table_participants} t
            INNER JOIN {$this->table_stats} s ON s.teilnehmer_id = t.id
            INNER JOIN {$this->wpdb->posts} p ON p.ID = s.post_id
            LEFT JOIN {$this->wpdb->postmeta} event_date ON p.ID = event_date.post_id AND event_date.meta_key = '_feu_einsatz_datum'
            WHERE {$event_year_sql} = %d
            AND p.post_status = 'publish'
            GROUP BY t.id
            ORDER BY gesamt_einsaetze DESC
        ", $jahr);
        
        return $this->wpdb->get_results($sql);
    }

    /**
     * Eintraege pro Kalendertag fuer die Kalenderansicht abrufen.
     *
     * @return array<int,object>
     */
    public function get_daily_report_entries($jahr = null) {
        if (!$jahr) {
            $jahr = date('Y');
        }

        $event_year_sql = $this->get_statistics_event_year_sql('event_date', 'p');
        $event_date_sql = $this->get_statistics_event_date_sql('event_date', 'p');

        $sql = $this->wpdb->prepare("
            SELECT
                p.ID AS post_id,
                {$event_date_sql} AS datum,
                p.post_title,
                COALESCE(event_time.meta_value, '') AS event_time,
                COALESCE(street.meta_value, '') AS street
            FROM {$this->wpdb->posts} p
            INNER JOIN {$this->wpdb->postmeta} report
                ON p.ID = report.post_id
            LEFT JOIN {$this->wpdb->postmeta} event_date
                ON p.ID = event_date.post_id
                AND event_date.meta_key = '_feu_einsatz_datum'
            LEFT JOIN {$this->wpdb->postmeta} event_time
                ON p.ID = event_time.post_id
                AND event_time.meta_key = '_feu_einsatz_uhrzeit'
            LEFT JOIN {$this->wpdb->postmeta} street
                ON p.ID = street.post_id
                AND street.meta_key = '_feu_einsatz_strasse'
            WHERE p.post_type = 'post'
              AND p.post_status = 'publish'
              AND report.meta_key = '_feu_einsatz_einsatzbericht'
              AND report.meta_value = '1'
              AND {$event_year_sql} = %d
            ORDER BY datum ASC, event_time ASC, p.post_title ASC
        ", $jahr);

        return $this->wpdb->get_results($sql);
    }

    /**
     * Verteilung der Teilnehmerzahl pro Einsatz abrufen.
     *
     * @return array<int,array<string,int>>
     */
    public function get_call_participant_distribution($period = 'all', $jahr = null) {
        $period = sanitize_key((string) $period);
        $event_date_sql = $this->get_statistics_event_date_sql('event_date', 'p');
        $where_clauses = [
            "p.post_type = 'post'",
            "p.post_status = 'publish'",
            "report.meta_key = '_feu_einsatz_einsatzbericht'",
            "report.meta_value = '1'",
        ];
        $prepare_values = [];

        if ('week' === $period || 'month' === $period || 'quarter' === $period) {
            $days = 'week' === $period ? 7 : ('month' === $period ? 30 : 90);
            $prepare_values[] = gmdate('Y-m-d', current_time('timestamp') - (DAY_IN_SECONDS * $days));
            $where_clauses[] = "{$event_date_sql} >= %s";
        } elseif ($jahr) {
            $prepare_values[] = (int) $jahr;
            $where_clauses[] = $this->get_statistics_event_year_sql('event_date', 'p') . ' = %d';
        }

        $sql = "
            SELECT
                distribution.teilnehmer_count,
                COUNT(*) AS call_count
            FROM (
                SELECT
                    p.ID AS post_id,
                    COUNT(DISTINCT s.teilnehmer_id) AS teilnehmer_count
                FROM {$this->wpdb->posts} p
                INNER JOIN {$this->wpdb->postmeta} report
                    ON p.ID = report.post_id
                LEFT JOIN {$this->table_stats} s
                    ON s.post_id = p.ID
                LEFT JOIN {$this->wpdb->postmeta} event_date
                    ON p.ID = event_date.post_id
                    AND event_date.meta_key = '_feu_einsatz_datum'
                WHERE " . implode(' AND ', $where_clauses) . "
                GROUP BY p.ID
            ) AS distribution
            GROUP BY distribution.teilnehmer_count
            ORDER BY distribution.teilnehmer_count ASC
        ";

        if (!empty($prepare_values)) {
            $sql = $this->wpdb->prepare($sql, $prepare_values);
        }

        $rows = $this->wpdb->get_results($sql, ARRAY_A);

        return array_values(array_map(static function($row) {
            return [
                'teilnehmer_count' => isset($row['teilnehmer_count']) ? (int) $row['teilnehmer_count'] : 0,
                'call_count' => isset($row['call_count']) ? (int) $row['call_count'] : 0,
            ];
        }, is_array($rows) ? $rows : []));
    }

    public function rebuild_all_statistics() {
        $event_year_sql = $this->get_statistics_event_year_sql('event_date', 'p');
        $legacy_function_aggregate_sql = $this->get_legacy_function_aggregate_sql('s');

        $delete_result = $this->wpdb->query("DELETE FROM {$this->table_statistics_cache}");

        if (false === $delete_result) {
            return false;
        }

        $insert_result = $this->wpdb->query("
            INSERT INTO {$this->table_statistics_cache} (
                teilnehmer_id,
                jahr,
                gesamt_einsaetze,
                maschinist,
                gruppenfuhrer,
                pa_tf_traeger1,
                pa_traeger2,
                melder,
                wasser1,
                wasser2,
                schlauch1,
                schlauch2,
                keine_funktion,
                letztes_update
            )
            SELECT
                s.teilnehmer_id,
                {$event_year_sql} as jahr,
                COUNT(DISTINCT s.post_id) as gesamt_einsaetze,
                {$legacy_function_aggregate_sql},
                NOW() as letztes_update
            FROM {$this->table_stats} s
            INNER JOIN {$this->wpdb->posts} p ON p.ID = s.post_id
            LEFT JOIN {$this->wpdb->postmeta} event_date ON p.ID = event_date.post_id AND event_date.meta_key = '_feu_einsatz_datum'
            WHERE p.post_status = 'publish'
            GROUP BY s.teilnehmer_id, {$event_year_sql}
        ");

        return false !== $insert_result;
    }
    
    /**
     * Details eines bestimmten Teilnehmers abrufen (Einsätze)
     */
    public function get_participant_details($teilnehmer_id, $jahr = null) {
        if (!$jahr) $jahr = date('Y');
        $event_date_sql = $this->get_statistics_event_date_sql('event_date', 'p');
        $event_year_sql = $this->get_statistics_event_year_sql('event_date', 'p');
        
        $sql = $this->wpdb->prepare("
            SELECT 
                p.ID,
                p.post_title,
                {$event_date_sql} as event_date,
                p.post_date,
                s.funktion
            FROM {$this->table_stats} s
            INNER JOIN {$this->wpdb->posts} p ON p.ID = s.post_id
            LEFT JOIN {$this->wpdb->postmeta} event_date ON p.ID = event_date.post_id AND event_date.meta_key = '_feu_einsatz_datum'
            WHERE s.teilnehmer_id = %d
            AND {$event_year_sql} = %d
            AND p.post_status = 'publish'
            ORDER BY {$event_date_sql} DESC, p.post_date DESC
        ", $teilnehmer_id, $jahr);
        
        return $this->wpdb->get_results($sql);
    }
    
    /**
     * Funktionen-Statistik eines bestimmten Teilnehmers
     */
    public function get_participant_function_stats($teilnehmer_id, $jahr = null) {
        if (!$jahr) $jahr = date('Y');
        $event_year_sql = $this->get_statistics_event_year_sql('event_date', 'p');
        
        $sql = $this->wpdb->prepare("
            SELECT 
                s.funktion,
                COUNT(s.id) as anzahl
            FROM {$this->table_stats} s
            INNER JOIN {$this->wpdb->posts} p ON p.ID = s.post_id
            LEFT JOIN {$this->wpdb->postmeta} event_date ON p.ID = event_date.post_id AND event_date.meta_key = '_feu_einsatz_datum'
            WHERE s.teilnehmer_id = %d
            AND {$event_year_sql} = %d
            AND p.post_status = 'publish'
            GROUP BY s.funktion
            ORDER BY anzahl DESC
        ", $teilnehmer_id, $jahr);
        
        return $this->wpdb->get_results($sql);
    }
    
    /**
     * Kategorie-Statistiken abrufen
     */
    public function get_category_statistics($jahr = null) {
        if (!$jahr) $jahr = date('Y');
        $event_year_sql = $this->get_statistics_event_year_sql('event_date', 'p');
        
        $sql = $this->wpdb->prepare("
            SELECT 
                t.term_id,
                t.name as kategorie_name,
                COUNT(DISTINCT p.ID) as anzahl
            FROM {$this->wpdb->terms} t
            INNER JOIN {$this->wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
            INNER JOIN {$this->wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
            INNER JOIN {$this->wpdb->posts} p ON p.ID = tr.object_id
            INNER JOIN {$this->wpdb->postmeta} pm ON p.ID = pm.post_id
            LEFT JOIN {$this->wpdb->postmeta} event_date ON p.ID = event_date.post_id AND event_date.meta_key = '_feu_einsatz_datum'
            WHERE p.post_type = 'post'
            AND p.post_status = 'publish'
            AND {$event_year_sql} = %d
            AND pm.meta_key = '_feu_einsatz_einsatzbericht'
            AND pm.meta_value = '1'
            AND tt.taxonomy = 'category'
            GROUP BY t.term_id
            ORDER BY anzahl DESC
        ", $jahr);
        
        return $this->wpdb->get_results($sql);
    }

    /**
     * Punkte fuer die Aktivitaetskarte abrufen
     */
    public function get_activity_map_points($jahr = null) {
        if (!$jahr) {
            $jahr = date('Y');
        }
        $event_date_sql = $this->get_statistics_event_date_sql('event_date', 'p');
        $event_year_sql = $this->get_statistics_event_year_sql('event_date', 'p');

        $sql = $this->wpdb->prepare("
            SELECT
                p.ID,
                p.post_title,
                {$event_date_sql} AS event_date,
                p.post_date,
                street.meta_value AS strasse,
                house_number.meta_value AS hausnummer,
                city.meta_value AS stadt,
                lat.meta_value AS latitude,
                lng.meta_value AS longitude
            FROM {$this->wpdb->posts} p
            INNER JOIN {$this->wpdb->postmeta} report ON p.ID = report.post_id
            INNER JOIN {$this->wpdb->postmeta} lat ON p.ID = lat.post_id
            INNER JOIN {$this->wpdb->postmeta} lng ON p.ID = lng.post_id
            LEFT JOIN {$this->wpdb->postmeta} event_date ON p.ID = event_date.post_id AND event_date.meta_key = '_feu_einsatz_datum'
            LEFT JOIN {$this->wpdb->postmeta} street ON p.ID = street.post_id AND street.meta_key = '_feu_einsatz_strasse'
            LEFT JOIN {$this->wpdb->postmeta} house_number ON p.ID = house_number.post_id AND house_number.meta_key = '_feu_einsatz_hausnummer'
            LEFT JOIN {$this->wpdb->postmeta} city ON p.ID = city.post_id AND city.meta_key = '_feu_einsatz_stadt'
            WHERE p.post_type = 'post'
            AND p.post_status = 'publish'
            AND {$event_year_sql} = %d
            AND report.meta_key = '_feu_einsatz_einsatzbericht'
            AND report.meta_value = '1'
            AND lat.meta_key = '_feu_einsatz_latitude'
            AND lng.meta_key = '_feu_einsatz_longitude'
            AND lat.meta_value <> ''
            AND lng.meta_value <> ''
            ORDER BY {$event_date_sql} DESC, p.post_date DESC
        ", $jahr);

        $points = $this->wpdb->get_results($sql);

        return array_values(array_filter(array_map(function($point) {
            return $this->build_activity_map_point($point, false);
        }, $points)));
    }

    public function get_activity_map_points_for_postcodes($postcodes = []) {
        $postcodes = array_values(array_unique(array_filter(array_map(static function($postcode) {
            $postcode = preg_replace('/\D+/', '', (string) $postcode);

            return preg_match('/^\d{4,6}$/', $postcode) ? $postcode : '';
        }, (array) $postcodes))));

        if (empty($postcodes)) {
            return [];
        }

        $event_date_sql = $this->get_statistics_event_date_sql('event_date', 'p');
        $sql = "
            SELECT
                p.ID,
                p.post_title,
                {$event_date_sql} AS event_date,
                p.post_date,
                street.meta_value AS strasse,
                house_number.meta_value AS hausnummer,
                city.meta_value AS stadt,
                plz.meta_value AS plz,
                lat.meta_value AS latitude,
                lng.meta_value AS longitude
            FROM {$this->wpdb->posts} p
            INNER JOIN {$this->wpdb->postmeta} report ON p.ID = report.post_id
            INNER JOIN {$this->wpdb->postmeta} lat ON p.ID = lat.post_id
            INNER JOIN {$this->wpdb->postmeta} lng ON p.ID = lng.post_id
            LEFT JOIN {$this->wpdb->postmeta} event_date ON p.ID = event_date.post_id AND event_date.meta_key = '_feu_einsatz_datum'
            LEFT JOIN {$this->wpdb->postmeta} street ON p.ID = street.post_id AND street.meta_key = '_feu_einsatz_strasse'
            LEFT JOIN {$this->wpdb->postmeta} house_number ON p.ID = house_number.post_id AND house_number.meta_key = '_feu_einsatz_hausnummer'
            LEFT JOIN {$this->wpdb->postmeta} city ON p.ID = city.post_id AND city.meta_key = '_feu_einsatz_stadt'
            LEFT JOIN {$this->wpdb->postmeta} plz ON p.ID = plz.post_id AND plz.meta_key = '_feu_einsatz_plz'
            WHERE p.post_type = 'post'
            AND p.post_status = 'publish'
            AND report.meta_key = '_feu_einsatz_einsatzbericht'
            AND report.meta_value = '1'
            AND lat.meta_key = '_feu_einsatz_latitude'
            AND lng.meta_key = '_feu_einsatz_longitude'
            AND lat.meta_value <> ''
            AND lng.meta_value <> ''
        ";
        $query_params = [];

        if (!empty($postcodes)) {
            $sql .= ' AND plz.meta_value IN (' . implode(', ', array_fill(0, count($postcodes), '%s')) . ')';
            $query_params = $postcodes;
        }

        $sql .= " ORDER BY {$event_date_sql} DESC, p.post_date DESC";

        if (!empty($query_params)) {
            $sql = $this->wpdb->prepare($sql, $query_params);
        }

        $points = $this->wpdb->get_results($sql);

        return array_values(array_filter(array_map(function($point) {
            return $this->build_activity_map_point($point, true);
        }, $points)));
    }
    
    /**
     * Monatliche Statistiken abrufen
     */
    public function get_monthly_statistics($jahr = null) {
        if (!$jahr) $jahr = date('Y');
        $event_month_sql = $this->get_statistics_event_month_sql('event_date', 'p');
        $event_year_sql = $this->get_statistics_event_year_sql('event_date', 'p');
        
        $sql = $this->wpdb->prepare("
            SELECT 
                {$event_month_sql} as monat,
                COUNT(p.ID) as anzahl
            FROM {$this->wpdb->posts} p
            INNER JOIN {$this->wpdb->postmeta} pm ON p.ID = pm.post_id
            LEFT JOIN {$this->wpdb->postmeta} event_date ON p.ID = event_date.post_id AND event_date.meta_key = '_feu_einsatz_datum'
            WHERE p.post_type = 'post'
            AND p.post_status = 'publish'
            AND {$event_year_sql} = %d
            AND pm.meta_key = '_feu_einsatz_einsatzbericht'
            AND pm.meta_value = '1'
            GROUP BY {$event_month_sql}
            ORDER BY monat ASC
        ", $jahr);
        
        $results = $this->wpdb->get_results($sql);
        
        // Array mit allen Monaten füllen (1-12)
        $monthly_stats = [];
        for ($i = 1; $i <= 12; $i++) {
            $monthly_stats[$i] = 0;
        }
        
        foreach ($results as $row) {
            $monthly_stats[$row->monat] = $row->anzahl;
        }
        
        return $monthly_stats;
    }

    public function count_report_posts($jahr = null) {
        $event_year_sql = $this->get_statistics_event_year_sql('event_date', 'p');
        $base_sql = "
            SELECT COUNT(DISTINCT p.ID)
            FROM {$this->wpdb->posts} p
            INNER JOIN {$this->wpdb->postmeta} pm ON p.ID = pm.post_id
            LEFT JOIN {$this->wpdb->postmeta} event_date ON p.ID = event_date.post_id AND event_date.meta_key = '_feu_einsatz_datum'
            WHERE p.post_type = 'post'
            AND p.post_status IN ('publish', 'future', 'draft', 'pending', 'private')
            AND pm.meta_key = '_feu_einsatz_einsatzbericht'
            AND pm.meta_value = '1'
        ";

        if (null !== $jahr && '' !== $jahr) {
            return (int) $this->wpdb->get_var(
                $this->wpdb->prepare(
                    $base_sql . " AND {$event_year_sql} = %d",
                    (int) $jahr
                )
            );
        }

        return (int) $this->wpdb->get_var($base_sql);
    }

    public function get_recent_reports($limit = 5) {
        $limit = max(1, min(20, absint($limit)));
        $event_date_sql = $this->get_statistics_event_date_sql('event_date', 'p');

        $sql = $this->wpdb->prepare(
            "
            SELECT
                p.ID,
                p.post_title,
                street.meta_value AS street,
                {$event_date_sql} AS event_date
            FROM {$this->wpdb->posts} p
            INNER JOIN {$this->wpdb->postmeta} pm ON p.ID = pm.post_id
            LEFT JOIN {$this->wpdb->postmeta} event_date ON p.ID = event_date.post_id AND event_date.meta_key = '_feu_einsatz_datum'
            LEFT JOIN {$this->wpdb->postmeta} street ON p.ID = street.post_id AND street.meta_key = '_feu_einsatz_strasse'
            WHERE p.post_type = 'post'
            AND p.post_status IN ('publish', 'future', 'draft', 'pending', 'private')
            AND pm.meta_key = '_feu_einsatz_einsatzbericht'
            AND pm.meta_value = '1'
            ORDER BY {$event_date_sql} DESC, p.post_date DESC, p.ID DESC
            LIMIT %d
            ",
            $limit
        );

        return (array) $this->wpdb->get_results($sql);
    }
    
    /**
     * Gesamtstatistiken abrufen
     */
    public function get_total_statistics($jahr = null) {
        if (!$jahr) $jahr = date('Y');
        $event_year_sql = $this->get_statistics_event_year_sql('event_date', 'p');
        
        // Gesamtanzahl Einsätze
        $total_einsaetze = $this->wpdb->get_var($this->wpdb->prepare("
            SELECT COUNT(DISTINCT p.ID)
            FROM {$this->wpdb->posts} p
            INNER JOIN {$this->wpdb->postmeta} pm ON p.ID = pm.post_id
            LEFT JOIN {$this->wpdb->postmeta} event_date ON p.ID = event_date.post_id AND event_date.meta_key = '_feu_einsatz_datum'
            WHERE p.post_type = 'post'
            AND p.post_status = 'publish'
            AND {$event_year_sql} = %d
            AND pm.meta_key = '_feu_einsatz_einsatzbericht'
            AND pm.meta_value = '1'
        ", $jahr));
        
        // Gesamtanzahl Teilnehmer (die in diesem Jahr aktiv waren)
        $total_teilnehmer_aktiv = $this->wpdb->get_var($this->wpdb->prepare("
            SELECT COUNT(DISTINCT s.teilnehmer_id)
            FROM {$this->table_stats} s
            INNER JOIN {$this->wpdb->posts} p ON p.ID = s.post_id
            LEFT JOIN {$this->wpdb->postmeta} event_date ON p.ID = event_date.post_id AND event_date.meta_key = '_feu_einsatz_datum'
            WHERE {$event_year_sql} = %d
            AND p.post_status = 'publish'
        ", $jahr));
        
        // Gesamtanzahl aller Teilnehmer
        $total_teilnehmer_alle = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table_participants}");
        
        // Durchschnittliche Teilnehmer pro Einsatz
        $avg_teilnehmer = $this->wpdb->get_var($this->wpdb->prepare("
            SELECT AVG(teilnehmer_count)
            FROM (
                SELECT s.post_id, COUNT(s.teilnehmer_id) as teilnehmer_count
                FROM {$this->table_stats} s
                INNER JOIN {$this->wpdb->posts} p ON p.ID = s.post_id
                LEFT JOIN {$this->wpdb->postmeta} event_date ON p.ID = event_date.post_id AND event_date.meta_key = '_feu_einsatz_datum'
                WHERE {$event_year_sql} = %d
                AND p.post_status = 'publish'
                GROUP BY s.post_id
            ) as counts
        ", $jahr));
        
        return [
            'total_einsaetze' => intval($total_einsaetze),
            'total_teilnehmer_aktiv' => intval($total_teilnehmer_aktiv),
            'total_teilnehmer_alle' => intval($total_teilnehmer_alle),
            'avg_teilnehmer' => round($avg_teilnehmer, 1)
        ];
    }
    
    /**
     * Funktionen-Statistik (gesamt) abrufen
     */
    public function get_function_statistics($jahr = null) {
        if (!$jahr) $jahr = date('Y');
        $event_year_sql = $this->get_statistics_event_year_sql('event_date', 'p');
        
        $sql = $this->wpdb->prepare("
            SELECT 
                s.funktion,
                COUNT(s.id) as anzahl,
                COUNT(DISTINCT s.teilnehmer_id) as verschiedene_teilnehmer
            FROM {$this->table_stats} s
            INNER JOIN {$this->wpdb->posts} p ON p.ID = s.post_id
            LEFT JOIN {$this->wpdb->postmeta} event_date ON p.ID = event_date.post_id AND event_date.meta_key = '_feu_einsatz_datum'
            WHERE {$event_year_sql} = %d
            AND p.post_status = 'publish'
            GROUP BY s.funktion
            ORDER BY anzahl DESC
        ", $jahr);
        
        return $this->wpdb->get_results($sql);
    }
    
    /**
     * Verfügbare Jahre abrufen
     */
    public function get_available_years() {
        $event_year_sql = $this->get_statistics_event_year_sql('event_date', 'p');

        $sql = "
            SELECT DISTINCT {$event_year_sql} as jahr
            FROM {$this->wpdb->posts} p
            INNER JOIN {$this->wpdb->postmeta} pm ON p.ID = pm.post_id
            LEFT JOIN {$this->wpdb->postmeta} event_date ON p.ID = event_date.post_id AND event_date.meta_key = '_feu_einsatz_datum'
            WHERE p.post_type = 'post'
            AND p.post_status = 'publish'
            AND pm.meta_key = '_feu_einsatz_einsatzbericht'
            AND pm.meta_value = '1'
            AND {$event_year_sql} IS NOT NULL
            ORDER BY jahr DESC
        ";
        
        $years = $this->wpdb->get_col($sql);
        
        return !empty($years) ? $years : [date('Y')];
    }
    
    // ============ ORGANISATIONEN FUNKTIONEN ============
    
    /**
     * Alle Organisationen abrufen
     */
    public function get_organizations($args = []) {
        $args = wp_parse_args($args, [
            'include_hidden_defaults' => false,
            'include_archived' => false,
        ]);

        $where = [];
        $params = [];

        if (empty($args['include_archived'])) {
            $where[] = 'is_archived = 0';
        }

        if (empty($args['include_hidden_defaults'])) {
            $hidden_defaults = $this->get_hidden_default_organization_names();

            if (!empty($hidden_defaults)) {
                $placeholders = implode(', ', array_fill(0, count($hidden_defaults), '%s'));
                $where[] = "name NOT IN ({$placeholders})";
                $params = array_merge($params, $hidden_defaults);
            }
        }

        $sql = "SELECT * FROM {$this->table_organizations}";

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY is_archived ASC, sort_order ASC, name ASC';

        if (!empty($params)) {
            $sql = $this->wpdb->prepare($sql, $params);
        }

        return $this->wpdb->get_results($sql);
    }
    
    /**
     * Einzelne Organisation abrufen
     */
    public function get_organization($id) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_organizations} WHERE id = %d",
                $id
            )
        );
    }
    
    /**
     * Organisation speichern
     */
    public function save_organization($id, $name, $color = '#0a4b78', $post_link = '') {
        $validated_color = sanitize_hex_color((string) $color);
        $color = $validated_color ? $validated_color : '#0a4b78';

        $data = [
            'name' => sanitize_text_field($name),
            'color' => $color,
            'post_link' => esc_url_raw((string) $post_link),
        ];
        
        if ($id > 0) {
            return $this->wpdb->update($this->table_organizations, $data, ['id' => $id], ['%s', '%s', '%s'], ['%d']);
        }

        $inserted = $this->wpdb->insert($this->table_organizations, $data, ['%s', '%s', '%s']);

        return false === $inserted ? false : (int) $this->wpdb->insert_id;
    }

    public function update_organization_sort_order(array $organization_ids) {
        $organization_ids = array_values(array_filter(array_map('absint', $organization_ids)));

        if (empty($organization_ids)) {
            return false;
        }

        foreach ($organization_ids as $index => $organization_id) {
            $this->wpdb->update(
                $this->table_organizations,
                ['sort_order' => $index + 1],
                ['id' => $organization_id],
                ['%d'],
                ['%d']
            );
        }

        return true;
    }

    public function set_organization_archive_status($id, $archived) {
        $result = $this->wpdb->update(
            $this->table_organizations,
            ['is_archived' => $archived ? 1 : 0],
            ['id' => absint($id)],
            ['%d'],
            ['%d']
        );

        return false === $result ? false : true;
    }
    
    /**
     * Organisation löschen
     */
    public function delete_organization($id) {
        return $this->set_organization_archive_status($id, true);
    }

    public function get_street_registry_entries($args = []) {
        if (!$this->street_registry_table_exists()) {
            return [];
        }

        $args = wp_parse_args($args, [
            'limit' => 500,
            'search' => '',
        ]);

        $limit = max(20, min(5000, absint($args['limit'])));
        $search = trim(sanitize_text_field((string) $args['search']));
        $sql = "SELECT * FROM {$this->table_street_registry}";
        $where = [];
        $params = [];

        if ('' !== $search) {
            $like = '%' . $this->wpdb->esc_like($search) . '%';
            $where[] = '(street LIKE %s OR postcode LIKE %s OR city LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY sort_order ASC, street ASC, postcode ASC, city ASC LIMIT %d';
        $params[] = $limit;
        $sql = $this->wpdb->prepare($sql, $params);

        return $this->wpdb->get_results($sql);
    }

    public function save_street_registry_entry($id, $street, $postcode = '', $city = '') {
        if (!$this->street_registry_table_exists()) {
            return false;
        }

        $street = trim(sanitize_text_field((string) $street));
        $postcode = preg_replace('/\D+/', '', (string) $postcode);
        $city = trim(sanitize_text_field((string) $city));

        if ('' === $street) {
            return false;
        }

        if ('' !== $postcode && !preg_match('/^\d{5}$/', $postcode)) {
            return false;
        }

        $data = [
            'street' => $street,
            'postcode' => $postcode,
            'city' => $city,
        ];

        if ($id > 0) {
            return false !== $this->wpdb->update(
                $this->table_street_registry,
                $data,
                ['id' => absint($id)],
                ['%s', '%s', '%s'],
                ['%d']
            );
        }

        $max_sort_order = (int) $this->wpdb->get_var("SELECT COALESCE(MAX(sort_order), 0) FROM {$this->table_street_registry}");
        $data['sort_order'] = $max_sort_order + 1;

        $inserted = $this->wpdb->insert(
            $this->table_street_registry,
            $data,
            ['%s', '%s', '%s', '%d']
        );

        return false === $inserted ? false : (int) $this->wpdb->insert_id;
    }

    public function bootstrap_street_registry_from_existing_reports($limit = 1000) {
        if (!$this->street_registry_table_exists()) {
            return 0;
        }

        $limit = max(20, min(5000, absint($limit)));

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "
                SELECT
                    street.meta_value AS street,
                    COALESCE(plz.meta_value, '') AS postcode,
                    COALESCE(city.meta_value, '') AS city
                FROM {$this->wpdb->postmeta} street
                LEFT JOIN {$this->wpdb->postmeta} plz
                    ON plz.post_id = street.post_id
                    AND plz.meta_key = %s
                LEFT JOIN {$this->wpdb->postmeta} city
                    ON city.post_id = street.post_id
                    AND city.meta_key = %s
                INNER JOIN {$this->wpdb->posts} posts
                    ON posts.ID = street.post_id
                WHERE street.meta_key = %s
                  AND street.meta_value <> ''
                  AND posts.post_type = 'post'
                  AND posts.post_status NOT IN ('trash', 'auto-draft')
                GROUP BY street.meta_value, plz.meta_value, city.meta_value
                ORDER BY street.meta_value ASC, plz.meta_value ASC, city.meta_value ASC
                LIMIT %d
                ",
                '_feu_einsatz_plz',
                '_feu_einsatz_stadt',
                '_feu_einsatz_strasse',
                $limit
            )
        );

        $imported = 0;

        foreach ((array) $rows as $row) {
            $street = trim(sanitize_text_field((string) ($row->street ?? '')));
            $postcode = preg_replace('/\D+/', '', (string) ($row->postcode ?? ''));
            $city = trim(sanitize_text_field((string) ($row->city ?? '')));

            if ('' === $street) {
                continue;
            }

            if ('' !== $postcode && !preg_match('/^\d{5}$/', $postcode)) {
                $postcode = '';
            }

            $result = $this->save_street_registry_entry(0, $street, $postcode, $city);

            if (false !== $result) {
                $imported++;
            }
        }

        return $imported;
    }

    public function get_street_registry_entry($id) {
        $id = absint($id);

        if ($id < 1 || !$this->street_registry_table_exists()) {
            return null;
        }

        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_street_registry} WHERE id = %d LIMIT 1",
                $id
            )
        );
    }

    public function count_posts_using_street_registry_entry($id) {
        $entry = $this->get_street_registry_entry($id);

        if (!$entry || empty($entry->street)) {
            return 0;
        }

        $where = [
            "posts.post_type = 'post'",
            "posts.post_status IN ('publish', 'future', 'draft', 'pending', 'private')",
            "street.meta_key = '_feu_einsatz_strasse'",
            'street.meta_value = %s',
        ];
        $params = [(string) $entry->street];

        if ('' !== (string) $entry->postcode) {
            $where[] = "COALESCE(plz.meta_value, '') = %s";
            $params[] = (string) $entry->postcode;
        }

        if ('' !== (string) $entry->city) {
            $where[] = "COALESCE(city.meta_value, '') = %s";
            $params[] = (string) $entry->city;
        }

        $sql = "
            SELECT COUNT(DISTINCT posts.ID)
            FROM {$this->wpdb->posts} posts
            INNER JOIN {$this->wpdb->postmeta} street
                ON street.post_id = posts.ID
            LEFT JOIN {$this->wpdb->postmeta} plz
                ON plz.post_id = posts.ID
                AND plz.meta_key = '_feu_einsatz_plz'
            LEFT JOIN {$this->wpdb->postmeta} city
                ON city.post_id = posts.ID
                AND city.meta_key = '_feu_einsatz_stadt'
            WHERE " . implode(' AND ', $where);

        return (int) $this->wpdb->get_var($this->wpdb->prepare($sql, $params));
    }

    public function delete_street_registry_entry($id) {
        if (!$this->street_registry_table_exists()) {
            return false;
        }

        if ($this->count_posts_using_street_registry_entry($id) > 0) {
            return false;
        }

        return false !== $this->wpdb->delete(
            $this->table_street_registry,
            ['id' => absint($id)],
            ['%d']
        );
    }

    public function get_street_registry_table_name() {
        return $this->table_street_registry;
    }

    private function get_hidden_default_organization_names() {
        return [
            'Krisenintervention',
            'Rotes Kreuz',
            'Stadtwerke',
            'Rettungsdienst',
            'Feuerwehr',
        ];
    }
    
    /**
     * Einsatz-Organisationen speichern
     */
    public function save_einsatz_organizations($post_id, $organizations) {
        delete_post_meta($post_id, '_feu_einsatz_organisationen');
        if (!empty($organizations)) {
            update_post_meta($post_id, '_feu_einsatz_organisationen', $organizations);
        }
    }
    
    /**
     * Einsatz-Organisationen abrufen
     */
    public function get_einsatz_organizations($post_id) {
        return get_post_meta($post_id, '_feu_einsatz_organisationen', true);
    }
    
    /**
     * Einsatz-Teilnehmer abrufen
     */
    public function get_einsatz_teilnehmer($post_id) {
        return get_post_meta($post_id, '_feu_einsatz_teilnehmer', true);
    }

    public function add_log($data) {
        $data = wp_parse_args((array) $data, [
            'user_id' => 0,
            'user_name' => '',
            'action_type' => '',
            'entity_type' => '',
            'entity_id' => 0,
            'message' => '',
            'details' => '',
            'page_slug' => '',
            'page_url' => '',
            'ip_address' => '',
            'created_at' => current_time('mysql'),
        ]);

        if ('' === $data['action_type']) {
            return false;
        }

        if (is_array($data['details']) || is_object($data['details'])) {
            $data['details'] = wp_json_encode($data['details']);
        }

        return $this->wpdb->insert(
            $this->table_logs,
            [
                'user_id' => absint($data['user_id']),
                'user_name' => sanitize_text_field($data['user_name']),
                'action_type' => sanitize_key($data['action_type']),
                'entity_type' => sanitize_key($data['entity_type']),
                'entity_id' => absint($data['entity_id']),
                'message' => sanitize_textarea_field($data['message']),
                'details' => is_string($data['details']) ? $data['details'] : '',
                'page_slug' => sanitize_text_field($data['page_slug']),
                'page_url' => esc_url_raw($data['page_url']),
                'ip_address' => sanitize_text_field($data['ip_address']),
                'created_at' => sanitize_text_field($data['created_at']),
            ],
            ['%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );
    }

    public function delete_logs_older_than($days = 180) {
        $days = max(1, absint($days));
        $cutoff = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));

        return $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->table_logs} WHERE created_at < %s",
                $cutoff
            )
        );
    }

    public function get_logs($args = []) {
        $args = wp_parse_args($args, [
            'limit' => 100,
            'offset' => 0,
            'action_type' => '',
            'entity_type' => '',
            'search' => '',
        ]);

        $where = ['1=1'];
        $params = [];

        if ('' !== $args['action_type']) {
            $where[] = 'action_type = %s';
            $params[] = sanitize_key($args['action_type']);
        }

        if ('' !== $args['entity_type']) {
            $where[] = 'entity_type = %s';
            $params[] = sanitize_key($args['entity_type']);
        }

        if ('' !== $args['search']) {
            $like = '%' . $this->wpdb->esc_like((string) $args['search']) . '%';
            $where[] = '(user_name LIKE %s OR message LIKE %s OR details LIKE %s OR page_slug LIKE %s)';
            array_push($params, $like, $like, $like, $like);
        }

        $sql = "SELECT * FROM {$this->table_logs} WHERE " . implode(' AND ', $where) . ' ORDER BY created_at DESC LIMIT %d OFFSET %d';
        $params[] = max(1, (int) $args['limit']);
        $params[] = max(0, (int) $args['offset']);

        return $this->wpdb->get_results($this->wpdb->prepare($sql, $params));
    }

    public function count_logs($args = []) {
        $args = wp_parse_args($args, [
            'action_type' => '',
            'entity_type' => '',
            'search' => '',
        ]);

        $where = ['1=1'];
        $params = [];

        if ('' !== $args['action_type']) {
            $where[] = 'action_type = %s';
            $params[] = sanitize_key($args['action_type']);
        }

        if ('' !== $args['entity_type']) {
            $where[] = 'entity_type = %s';
            $params[] = sanitize_key($args['entity_type']);
        }

        if ('' !== $args['search']) {
            $like = '%' . $this->wpdb->esc_like((string) $args['search']) . '%';
            $where[] = '(user_name LIKE %s OR message LIKE %s OR details LIKE %s OR page_slug LIKE %s)';
            array_push($params, $like, $like, $like, $like);
        }

        $sql = "SELECT COUNT(*) FROM {$this->table_logs} WHERE " . implode(' AND ', $where);

        if (empty($params)) {
            return (int) $this->wpdb->get_var($sql);
        }

        return (int) $this->wpdb->get_var($this->wpdb->prepare($sql, $params));
    }

    public function save_archive($data) {
        $data = wp_parse_args((array) $data, [
            'id' => 0,
            'archive_key' => '',
            'filename' => '',
            'label' => '',
            'file_size' => 0,
            'created_by' => 0,
            'created_by_name' => '',
            'source' => 'created',
            'notes' => '',
            'manifest' => '',
            'created_at' => current_time('mysql'),
            'restored_at' => null,
        ]);

        if ('' === $data['archive_key'] || '' === $data['filename']) {
            return false;
        }

        if (is_array($data['manifest']) || is_object($data['manifest'])) {
            $data['manifest'] = wp_json_encode($data['manifest']);
        }

        $row = [
            'archive_key' => sanitize_key($data['archive_key']),
            'filename' => sanitize_file_name($data['filename']),
            'label' => sanitize_text_field($data['label']),
            'file_size' => max(0, (int) $data['file_size']),
            'created_by' => absint($data['created_by']),
            'created_by_name' => sanitize_text_field($data['created_by_name']),
            'source' => sanitize_key($data['source']),
            'notes' => sanitize_textarea_field($data['notes']),
            'manifest' => is_string($data['manifest']) ? $data['manifest'] : '',
            'created_at' => sanitize_text_field($data['created_at']),
            'restored_at' => empty($data['restored_at']) ? null : sanitize_text_field($data['restored_at']),
        ];

        if (!empty($data['id'])) {
            return $this->wpdb->update(
                $this->table_archives,
                $row,
                ['id' => absint($data['id'])],
                ['%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s'],
                ['%d']
            );
        }

        return $this->wpdb->insert(
            $this->table_archives,
            $row,
            ['%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );
    }

    public function get_archives($limit = 100) {
        $limit = max(1, (int) $limit);

        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_archives} ORDER BY created_at DESC LIMIT %d",
                $limit
            )
        );
    }

    public function get_archive($id) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_archives} WHERE id = %d",
                absint($id)
            )
        );
    }

    public function get_archive_by_key($archive_key) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_archives} WHERE archive_key = %s",
                sanitize_key($archive_key)
            )
        );
    }

    public function delete_archive($id) {
        return $this->wpdb->delete($this->table_archives, ['id' => absint($id)], ['%d']);
    }

    public function get_log_table_name() {
        return $this->table_logs;
    }

    public function get_archive_table_name() {
        return $this->table_archives;
    }

    public function get_participant_table_name() {
        return $this->table_participants;
    }

    public function get_stats_table_name() {
        return $this->table_stats;
    }

    public function get_statistics_cache_table_name() {
        return $this->table_statistics_cache;
    }

    public function get_organization_table_name() {
        return $this->table_organizations;
    }
}
