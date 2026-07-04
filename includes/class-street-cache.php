<?php
if (!defined('ABSPATH')) {
    exit;
}

class FEU_Einsatz_Street_Cache {

    const CACHE_VERSION = 13;
    const LEGACY_CACHE_VERSIONS = [12, 11, 10, 9, 8, 7, 6, 5, 4, 3, 2, 1];
    const CACHE_PREFIX = 'feu_einsatz_street_geometry_';
    const CACHE_INDEX_OPTION = 'feu_einsatz_street_cache_keys';
    const POST_META_VERSION = '_feu_einsatz_street_cache_version';

    public static function normalize_address_parts($street, $plz = '', $city = '') {
        $street = self::normalize_string($street);
        $street = str_replace(
            ["stra\xC3\x9Fe", "\xC3\xA4", "\xC3\xB6", "\xC3\xBC"],
            ['strasse', 'ae', 'oe', 'ue'],
            $street
        );
        $plz = preg_replace('/[^0-9]/', '', (string) $plz);
        $city = self::normalize_string($city);

        if ('' === $city) {
            $city = 'hamburg';
        }

        return [$street, $plz, $city];
    }

    public static function build_cache_key($street, $plz = '', $city = '') {
        return self::build_cache_key_for_version(self::CACHE_VERSION, $street, $plz, $city);
    }

    public static function build_cache_key_for_version($version, $street, $plz = '', $city = '') {
        $parts = self::normalize_address_parts($street, $plz, $city);
        return md5((int) $version . '|' . implode('|', $parts));
    }

    public static function get($street, $plz = '', $city = '') {
        $payload = false;
        $cache_key = self::build_cache_key($street, $plz, $city);
        $payload = get_transient(self::CACHE_PREFIX . $cache_key);

        if (!self::is_valid_payload($payload)) {
            foreach (self::LEGACY_CACHE_VERSIONS as $legacy_version) {
                $legacy_key = self::build_cache_key_for_version($legacy_version, $street, $plz, $city);
                $legacy_payload = get_transient(self::CACHE_PREFIX . $legacy_key);

                if (self::is_valid_payload($legacy_payload)) {
                    self::set($street, $plz, $city, $legacy_payload);
                    $payload = $legacy_payload;
                    break;
                }
            }
        }

        if (!self::is_valid_payload($payload)) {
            return false;
        }

        return [
            'geometry' => $payload['geometry'],
            'center' => $payload['center'],
        ];
    }

    public static function set($street, $plz = '', $city = '', $data = []) {
        if (!self::is_valid_payload($data)) {
            return false;
        }

        $cache_key = self::build_cache_key($street, $plz, $city);

        $payload = [
            'geometry' => $data['geometry'],
            'center' => $data['center'],
            'version' => self::CACHE_VERSION,
            'cached_at' => time(),
        ];

        $stored = set_transient(self::CACHE_PREFIX . $cache_key, $payload, self::get_ttl());
        if ($stored) {
            self::remember_cache_key($cache_key);
        }

        return $stored;
    }

    public static function get_post_cache($post_id) {
        $version = (int) get_post_meta($post_id, self::POST_META_VERSION, true);

        if ($version !== self::CACHE_VERSION) {
            return false;
        }

        $geometry = get_post_meta($post_id, '_feu_einsatz_street_geometry_final', true);
        $center = get_post_meta($post_id, '_feu_einsatz_street_center_final', true);

        $payload = [
            'geometry' => is_array($geometry) ? $geometry : [],
            'center' => is_array($center) ? $center : [],
        ];

        if (!self::is_valid_payload($payload)) {
            return false;
        }

        return $payload;
    }

    public static function set_post_cache($post_id, $data = []) {
        if (!self::is_valid_payload($data)) {
            return false;
        }

        update_post_meta($post_id, '_feu_einsatz_street_geometry_final', $data['geometry']);
        update_post_meta($post_id, '_feu_einsatz_street_center_final', $data['center']);
        update_post_meta($post_id, self::POST_META_VERSION, self::CACHE_VERSION);

        return true;
    }

    public static function clear_post_cache($post_id) {
        foreach (self::get_post_meta_keys() as $meta_key) {
            delete_post_meta($post_id, $meta_key);
        }
    }

    public static function clear_all_post_caches() {
        global $wpdb;

        foreach (self::get_post_meta_keys() as $meta_key) {
            $wpdb->delete($wpdb->postmeta, ['meta_key' => $meta_key]);
        }
    }

    public static function clear_all() {
        global $wpdb;

        $keys = get_option(self::CACHE_INDEX_OPTION, []);
        if (is_array($keys)) {
            foreach ($keys as $cache_key) {
                delete_transient(self::CACHE_PREFIX . $cache_key);
            }
        }

        delete_option(self::CACHE_INDEX_OPTION);

        $like_value = $wpdb->esc_like('_transient_' . self::CACHE_PREFIX) . '%';
        $timeout_like_value = $wpdb->esc_like('_transient_timeout_' . self::CACHE_PREFIX) . '%';

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $like_value
            )
        );

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $timeout_like_value
            )
        );
    }

    public static function get_cache_entry_count() {
        $keys = get_option(self::CACHE_INDEX_OPTION, []);
        return is_array($keys) ? count($keys) : 0;
    }

    public static function get_post_meta_keys() {
        return [
            '_feu_einsatz_street_geometry',
            '_feu_einsatz_street_center',
            '_feu_einsatz_street_bounds',
            '_feu_einsatz_street_line_data',
            '_feu_einsatz_street_geometry_final',
            '_feu_einsatz_street_center_final',
            '_feu_einsatz_display_address',
            self::POST_META_VERSION,
        ];
    }

    private static function get_ttl() {
        if (defined('WEEK_IN_SECONDS')) {
            return WEEK_IN_SECONDS * 12;
        }

        return 7257600;
    }

    private static function remember_cache_key($cache_key) {
        $keys = get_option(self::CACHE_INDEX_OPTION, []);
        if (!is_array($keys)) {
            $keys = [];
        }

        if (!in_array($cache_key, $keys, true)) {
            $keys[] = $cache_key;
            update_option(self::CACHE_INDEX_OPTION, $keys, false);
        }
    }

    private static function is_valid_payload($payload) {
        if (!is_array($payload)) {
            return false;
        }

        if (empty($payload['geometry']) || !is_array($payload['geometry'])) {
            return false;
        }

        if (empty($payload['center']) || !is_array($payload['center'])) {
            return false;
        }

        return isset($payload['center'][0], $payload['center'][1]);
    }

    private static function normalize_string($value) {
        $value = trim((string) $value);
        $value = preg_replace('/\s+/', ' ', $value);

        if (function_exists('mb_strtolower')) {
            return mb_strtolower($value, 'UTF-8');
        }

        return strtolower($value);
    }
}
