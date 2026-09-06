<?php
if (!defined('ABSPATH')) {
    exit;
}

class FEU_Einsatz_Installer {

    const SCHEMA_VERSION = '2026-07-03-1';

    public static function get_default_functions() {
        return [
            'Maschinist',
            'Gruppenführer',
            'ATF',
            'ATM',
            'Melder',
            'WTF',
            'WTM',
            'STF',
            'STM',
            'Mannschaft',
        ];
    }

    public static function get_default_participant_function() {
        return self::resolve_default_participant_function(
            get_option('feu_einsatz_default_participant_function', self::get_builtin_default_participant_function()),
            get_option('feu_einsatz_functions', self::get_default_functions())
        );
    }

    public static function get_builtin_default_participant_function() {
        return 'Mannschaft';
    }

    public static function resolve_default_participant_function($selected, $functions = null) {
        $functions = self::normalize_default_value_list(
            null === $functions ? get_option('feu_einsatz_functions', self::get_default_functions()) : $functions
        );
        $selected = sanitize_text_field((string) $selected);

        if ('' !== $selected && in_array($selected, $functions, true)) {
            return $selected;
        }

        $fallback = self::get_builtin_default_participant_function();

        if (in_array($fallback, $functions, true)) {
            return $fallback;
        }

        if (!empty($functions)) {
            return (string) reset($functions);
        }

        return $fallback;
    }

    public static function resolve_assignment_function($selected, $participant_defaults = [], $functions = null) {
        $functions = self::normalize_default_value_list(
            null === $functions ? get_option('feu_einsatz_functions', self::get_default_functions()) : $functions
        );
        $selected = sanitize_text_field((string) $selected);

        if ('Keine Funktion' === $selected) {
            return $selected;
        }

        if ('' !== $selected && in_array($selected, $functions, true)) {
            return $selected;
        }

        $participant_defaults = self::normalize_default_value_list($participant_defaults);

        foreach ($participant_defaults as $participant_default) {
            if (in_array($participant_default, $functions, true)) {
                return $participant_default;
            }
        }

        return self::resolve_default_participant_function(self::get_default_participant_function(), $functions);
    }

    public static function get_legacy_default_functions() {
        return [
            'Maschinist',
            'Gruppenführer',
            'PA TF Träger 1',
            'PA Träger 2',
            'Melder',
            'Wasser 1',
            'Wasser 2',
            'Schlauch 1',
            'Schlauch 2',
        ];
    }

    public static function get_default_organizations() {
        return [
            'Berufsfeuerwehr' => '#b91c1c',
            'Drehleiter' => '#ea580c',
            'Notarzt' => '#dc2626',
            'Polizei' => '#1d4ed8',
            'RTW' => '#0f766e',
            'THW' => '#d97706',
        ];
    }
    
    public static function activate() {
        self::check_requirements();
        self::create_tables();
        self::set_default_options();
        self::maybe_upgrade_default_functions_option();
        self::ensure_default_participant_function_option();
        self::migrate_legacy_participant_ranking_pin();
        self::create_upload_directory();
        self::add_default_organizations();
        self::migrate_existing_data();
        update_option('feu_einsatz_schema_version', self::SCHEMA_VERSION);
        flush_rewrite_rules();
    }
    
    public static function deactivate() {
        flush_rewrite_rules();
    }
    
    private static function check_requirements() {
        global $wp_version;
        $required_php_version = defined('FEU_EINSATZ_MIN_PHP_VERSION') ? FEU_EINSATZ_MIN_PHP_VERSION : '8.1';
        $required_wp_version = defined('FEU_EINSATZ_MIN_WP_VERSION') ? FEU_EINSATZ_MIN_WP_VERSION : '7.1';
        $plugin_basename = defined('FEU_EINSATZ_PLUGIN_FILE')
            ? plugin_basename(FEU_EINSATZ_PLUGIN_FILE)
            : plugin_basename(dirname(__DIR__) . '/feuer-einsatzberichte.php');
        
        if (version_compare(PHP_VERSION, $required_php_version, '<')) {
            deactivate_plugins($plugin_basename);
            wp_die(sprintf('Einsatzberichte benötigt PHP %s oder höher.', $required_php_version));
        }
        
        if (version_compare($wp_version, $required_wp_version, '<')) {
            deactivate_plugins($plugin_basename);
            wp_die(sprintf('Einsatzberichte benötigt WordPress %s oder höher.', $required_wp_version));
        }
    }
    
    private static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $table_participants = $wpdb->prefix . 'feu_einsatz_teilnehmer';
        $table_stats = $wpdb->prefix . 'feu_einsatz_statistiken';
        $table_organizations = $wpdb->prefix . 'feu_einsatz_organisationen';
        $table_street_registry = $wpdb->prefix . 'feu_einsatz_street_registry';
        $table_statistics_cache = $wpdb->prefix . 'feu_einsatz_statistics_cache';
        $table_archives = $wpdb->prefix . 'feu_einsatz_archive';
        $table_logs = $wpdb->prefix . 'feu_einsatz_logs';
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Tabelle: Teilnehmer
        $sql1 = "CREATE TABLE IF NOT EXISTS $table_participants (
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
        dbDelta($sql1);
        
        // Tabelle: Statistiken (Einsätze pro Teilnehmer)
        $sql2 = "CREATE TABLE IF NOT EXISTS $table_stats (
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
        dbDelta($sql2);
        
        // Tabelle: Organisationen
        $sql3 = "CREATE TABLE IF NOT EXISTS $table_organizations (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            color varchar(7) DEFAULT '#0a4b78',
            post_link varchar(255) DEFAULT '',
            is_archived tinyint(1) DEFAULT 0,
            sort_order int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY archived_name (is_archived, name),
            KEY archived_order (is_archived, sort_order)
        ) $charset_collate;";
        dbDelta($sql3);

        $sql3b = "CREATE TABLE IF NOT EXISTS $table_street_registry (
            id int(11) NOT NULL AUTO_INCREMENT,
            street varchar(191) NOT NULL,
            postcode varchar(5) DEFAULT '',
            city varchar(120) DEFAULT 'Hamburg',
            sort_order int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY street_lookup (street, postcode, city),
            KEY street_order (sort_order, street)
        ) $charset_collate;";
        dbDelta($sql3b);
        
        // Tabelle: Statistik-Cache
        $sql4 = "CREATE TABLE IF NOT EXISTS $table_statistics_cache (
            id int(11) NOT NULL AUTO_INCREMENT,
            teilnehmer_id int(11) NOT NULL,
            jahr int(4) NOT NULL,
            maschinist int(11) DEFAULT 0,
            gruppenfuhrer int(11) DEFAULT 0,
            pa_tf_traeger1 int(11) DEFAULT 0,
            pa_traeger2 int(11) DEFAULT 0,
            melder int(11) DEFAULT 0,
            wasser1 int(11) DEFAULT 0,
            wasser2 int(11) DEFAULT 0,
            schlauch1 int(11) DEFAULT 0,
            schlauch2 int(11) DEFAULT 0,
            keine_funktion int(11) DEFAULT 0,
            gesamt_einsaetze int(11) DEFAULT 0,
            letztes_update datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_teilnehmer_jahr (teilnehmer_id, jahr),
            KEY teilnehmer_id (teilnehmer_id),
            KEY jahr (jahr)
        ) $charset_collate;";
        dbDelta($sql4);

        $sql5 = "CREATE TABLE IF NOT EXISTS $table_archives (
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
        dbDelta($sql5);

        $sql6 = "CREATE TABLE IF NOT EXISTS $table_logs (
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
        dbDelta($sql6);
        
    }
    
    private static function set_default_options() {
        $default_options = [
            'feu_einsatz_version' => FEU_EINSATZ_VERSION,
            'feu_einsatz_update_manifest_url' => FEU_Einsatz_Updater::get_default_manifest_url(),
            'feu_einsatz_functions' => self::get_default_functions(),
            'feu_einsatz_default_participant_function' => self::get_builtin_default_participant_function(),
            'feu_einsatz_categories' => [],
            'feu_einsatz_map_zoom' => 16,
            'feu_einsatz_map_height' => 400,
            'feu_einsatz_auto_map_image' => 1,
            'feu_einsatz_map_preview_heading_text' => '',
            'feu_einsatz_map_preview_show_panel' => 1,
            'feu_einsatz_map_preview_show_panel_heading' => 1,
            'feu_einsatz_map_preview_show_panel_address' => 1,
            'feu_einsatz_map_preview_panel_position' => 'bottom-left',
            'feu_einsatz_map_preview_show_street_label' => 1,
            'feu_einsatz_map_preview_street_label_prefix' => 'Einsatz Straße',
            'feu_einsatz_map_preview_street_label_position' => 'auto',
            'feu_einsatz_map_preview_show_attribution' => 1,
            'feu_einsatz_map_preview_attribution_text' => 'Leaflet | ' . html_entity_decode('&copy;', ENT_QUOTES, 'UTF-8') . ' OpenStreetMap contributors',
            'feu_einsatz_map_preview_attribution_position' => 'bottom-right',
            'feu_einsatz_map_label_style' => 'bubble',
            'feu_einsatz_map_label_text_color' => '#ffffff',
            'feu_einsatz_map_preview_highlight_color' => '#d92d20',
            'feu_einsatz_map_preview_stroke_width' => 8,
            'feu_einsatz_map_preview_font_family' => 'auto',
            'feu_einsatz_area_page_enabled' => 0,
            'feu_einsatz_area_show_calls' => 1,
            'feu_einsatz_area_postcodes' => [],
            'feu_einsatz_default_comments_enabled' => 0,
            'feu_einsatz_backup_retention_limit' => 5,
            'feu_einsatz_related_reports_count' => 6,
            'feu_einsatz_single_desaturate_organizations' => 0,
            'feu_einsatz_single_info_fields' => ['street', 'location', 'date', 'time', 'category', 'organizations'],
            'feu_einsatz_single_map_display_mode' => 'live',
            'feu_einsatz_single_map_privacy_mode' => 'always',
            'feu_einsatz_single_live_map_show_station' => 0,
            'feu_einsatz_overview_show_stats' => 1,
            'feu_einsatz_overview_show_year_filter' => 1,
            'feu_einsatz_social_share_enabled_networks' => FEU_Einsatz_Template_Helpers::get_default_social_share_networks(),
            'feu_einsatz_social_share_image_mode' => 'post_image',
            'feu_einsatz_social_share_background_id' => 0,
            'feu_einsatz_social_share_fields' => FEU_Einsatz_Template_Helpers::get_default_social_share_fields(),
            'feu_einsatz_social_share_layout' => 'wide',
            'feu_einsatz_social_share_logo_id' => 0,
            'feu_einsatz_social_share_badge_text' => 'PRESSEMITTEILUNG',
            'feu_einsatz_social_share_cta_text' => 'Weitere Infos',
            'feu_einsatz_social_share_title_color' => '#ffffff',
            'feu_einsatz_social_share_description_color' => '#dbeafe',
            'feu_einsatz_social_share_panel_color' => '#0f2f5f',
            'feu_einsatz_social_share_accent_color' => '#ef233c',
            'feu_einsatz_social_share_cta_fill_color' => '#ffffff',
            'feu_einsatz_social_share_cta_text_color' => '#0f2f5f',
            'feu_einsatz_social_share_title_scale' => 118,
            'feu_einsatz_social_share_description_scale' => 112,
            'feu_einsatz_social_share_description_max_lines' => 5,
            'feu_einsatz_social_share_logo_scale' => 100,
            'feu_einsatz_social_share_logo_width' => 220,
            'feu_einsatz_social_share_overlay_enabled' => 1,
            'feu_einsatz_social_share_image_blur' => 0,
            'feu_einsatz_social_share_panel_radius' => 30,
            'feu_einsatz_social_share_badge_radius' => 40,
            'feu_einsatz_social_share_link_radius' => 14,
            'feu_einsatz_social_share_text_align' => 'auto',
            'feu_einsatz_social_share_logo_position' => 'bottom-right',
            'feu_einsatz_social_meta_enabled' => 1,
            'feu_einsatz_social_meta_canonical_enabled' => 1,
            'feu_einsatz_social_meta_schema_enabled' => 1,
            'feu_einsatz_social_meta_twitter_site' => '',
            'feu_einsatz_role_access' => [],
            'feu_einsatz_photo_watermark_enabled' => 1,
            'feu_einsatz_photo_watermark_text' => get_bloginfo('name'),
            'feu_einsatz_photo_watermark_image_id' => 0,
            'feu_einsatz_photo_watermark_opacity' => 36,
            'feu_einsatz_photo_watermark_scale' => 42,
        ];
        
        foreach ($default_options as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }

    private static function normalize_default_value_list($values) {
        return array_values(
            array_filter(
                array_map('sanitize_text_field', (array) $values),
                static function($value) {
                    return '' !== trim((string) $value);
                }
            )
        );
    }

    private static function maybe_upgrade_default_functions_option() {
        $current_functions = get_option('feu_einsatz_functions', false);

        if (false === $current_functions) {
            add_option('feu_einsatz_functions', self::get_default_functions());
            return;
        }

        $normalized_current = self::normalize_default_value_list($current_functions);
        $legacy_defaults = self::normalize_default_value_list(self::get_legacy_default_functions());

        if (empty($normalized_current) || $normalized_current === $legacy_defaults) {
            update_option('feu_einsatz_functions', self::get_default_functions());
        }
    }

    private static function ensure_default_participant_function_option() {
        $functions = get_option('feu_einsatz_functions', self::get_default_functions());
        $resolved = self::resolve_default_participant_function(
            get_option('feu_einsatz_default_participant_function', self::get_builtin_default_participant_function()),
            $functions
        );

        if (false === get_option('feu_einsatz_default_participant_function', false)) {
            add_option('feu_einsatz_default_participant_function', $resolved);
            return;
        }

        update_option('feu_einsatz_default_participant_function', $resolved);
    }

    private static function normalize_default_organization_key($name) {
        $name = sanitize_text_field((string) $name);
        $name = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);

        return sanitize_title(remove_accents($name));
    }

    private static function get_legacy_participant_ranking_pin_file_path() {
        return trailingslashit(FEU_EINSATZ_PLUGIN_DIR) . 'feuer-einsatzberichte-participant-ranking-pin.php';
    }

    private static function migrate_legacy_participant_ranking_pin() {
        $stored_pin = get_option('feu_einsatz_participant_ranking_pin', '');

        if (is_scalar($stored_pin) && '' !== trim((string) $stored_pin)) {
            self::cleanup_legacy_participant_ranking_pin_file();
            return;
        }

        $pin_file = self::get_legacy_participant_ranking_pin_file_path();

        if (!file_exists($pin_file) || !is_readable($pin_file)) {
            return;
        }

        $pin = '';
        $file_contents = @file_get_contents($pin_file);

        if (is_string($file_contents) && '' !== $file_contents) {
            if (0 === strpos($file_contents, "\xEF\xBB\xBF")) {
                $file_contents = substr($file_contents, 3);
            }

            if (preg_match('/return\s+[\'"]([^\'"]+)[\'"]\s*;/i', $file_contents, $matches)) {
                $pin = (string) ($matches[1] ?? '');
            } else {
                $plain_contents = preg_replace('/<\?(php)?/i', '', $file_contents);
                $plain_contents = preg_replace('/return\s+/i', '', (string) $plain_contents);
                $plain_contents = str_replace([';', '?', '>'], '', (string) $plain_contents);
                $pin = trim((string) $plain_contents);
            }
        }

        if ('' !== $pin) {
            $pin = sanitize_text_field($pin);
            $pin = trim($pin);

            if ('' !== $pin) {
                update_option('feu_einsatz_participant_ranking_pin', substr($pin, 0, 64), false);
            }
        }

        self::cleanup_legacy_participant_ranking_pin_file();
    }

    private static function cleanup_legacy_participant_ranking_pin_file() {
        $pin_file = self::get_legacy_participant_ranking_pin_file_path();

        if (!file_exists($pin_file)) {
            return;
        }

        if (function_exists('wp_delete_file')) {
            wp_delete_file($pin_file);
        } else {
            @unlink($pin_file);
        }
    }
    
    private static function create_upload_directory() {
        $upload_dir = wp_upload_dir();
        $directories = [
            [
                'path' => $upload_dir['basedir'] . '/feuer-einsatzberichte',
                'private' => false,
            ],
        ];

        foreach ($directories as $directory) {
            if (!file_exists($directory['path'])) {
                wp_mkdir_p($directory['path']);
            }

            $index_file = $directory['path'] . '/index.html';
            if (!file_exists($index_file)) {
                file_put_contents($index_file, '');
            }

            $index_php_file = $directory['path'] . '/index.php';
            if (!file_exists($index_php_file)) {
                file_put_contents($index_php_file, "<?php\n// Silence is golden.\n");
            }

            $htaccess_file = $directory['path'] . '/.htaccess';
            $web_config_file = $directory['path'] . '/web.config';

            if (!$directory['private']) {
                if (file_exists($htaccess_file)) {
                    @unlink($htaccess_file);
                }

                if (file_exists($web_config_file)) {
                    @unlink($web_config_file);
                }

                continue;
            }

            if (!file_exists($htaccess_file)) {
                file_put_contents(
                    $htaccess_file,
                    "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\ndeny from all\n</IfModule>\n"
                );
            }

            if (!file_exists($web_config_file)) {
                file_put_contents(
                    $web_config_file,
                    "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration>\n  <system.webServer>\n    <directoryBrowse enabled=\"false\" />\n    <security>\n      <authorization>\n        <remove users=\"*\" roles=\"\" verbs=\"\" />\n        <add accessType=\"Deny\" users=\"*\" />\n      </authorization>\n    </security>\n  </system.webServer>\n</configuration>\n"
                );
            }
        }
    }
    
    private static function add_default_organizations() {
        global $wpdb;
        $table = $wpdb->prefix . 'feu_einsatz_organisationen';

        $existing_rows = $wpdb->get_results("SELECT name FROM $table");
        $existing_names = [];

        foreach ((array) $existing_rows as $row) {
            if (!isset($row->name)) {
                continue;
            }

            $existing_names[self::normalize_default_organization_key($row->name)] = true;
        }

        foreach (self::get_default_organizations() as $org => $color) {
            $organization_key = self::normalize_default_organization_key($org);

            if (isset($existing_names[$organization_key])) {
                continue;
            }

            $wpdb->insert($table, ['name' => $org, 'color' => $color], ['%s', '%s']);
            $existing_names[$organization_key] = true;
        }
    }
    
    private static function migrate_existing_data() {
        global $wpdb;
        $cache_table = $wpdb->prefix . 'feu_einsatz_statistics_cache';
        $stats_table = $wpdb->prefix . 'feu_einsatz_statistiken';
        
        // Prüfen ob bereits Daten im Cache existieren
        $cache_count = $wpdb->get_var("SELECT COUNT(*) FROM $cache_table");
        
        if ($cache_count == 0) {
            // Prüfen ob Daten in der Statistik-Tabelle existieren
            $stats_count = $wpdb->get_var("SELECT COUNT(*) FROM $stats_table");
            
            if ($stats_count > 0) {
                self::build_initial_cache();
            }
        }
    }
    
    private static function build_initial_cache() {
        global $wpdb;
        $cache_table = $wpdb->prefix . 'feu_einsatz_statistics_cache';
        $stats_table = $wpdb->prefix . 'feu_einsatz_statistiken';
        $event_year_sql = "CAST(LEFT(COALESCE(NULLIF(event_date.meta_value, ''), DATE(p.post_date)), 4) AS UNSIGNED)";
        
        // Cache mit Daten füllen
        $sql = "
            INSERT INTO $cache_table (teilnehmer_id, jahr, gesamt_einsaetze, maschinist, gruppenfuhrer, 
                                        pa_tf_traeger1, pa_traeger2, melder, wasser1, wasser2, 
                                        schlauch1, schlauch2, keine_funktion, letztes_update)
            SELECT 
                s.teilnehmer_id,
                {$event_year_sql} as jahr,
                COUNT(DISTINCT s.post_id) as gesamt_einsaetze,
                SUM(CASE WHEN s.funktion = 'Maschinist' THEN 1 ELSE 0 END) as maschinist,
                SUM(CASE WHEN s.funktion = 'Gruppenführer' THEN 1 ELSE 0 END) as gruppenfuhrer,
                SUM(CASE WHEN s.funktion IN ('PA TF Träger 1', 'ATF') THEN 1 ELSE 0 END) as pa_tf_traeger1,
                SUM(CASE WHEN s.funktion IN ('PA Träger 2', 'ATM') THEN 1 ELSE 0 END) as pa_traeger2,
                SUM(CASE WHEN s.funktion = 'Melder' THEN 1 ELSE 0 END) as melder,
                SUM(CASE WHEN s.funktion IN ('Wasser 1', 'WTF') THEN 1 ELSE 0 END) as wasser1,
                SUM(CASE WHEN s.funktion IN ('Wasser 2', 'WTM') THEN 1 ELSE 0 END) as wasser2,
                SUM(CASE WHEN s.funktion IN ('Schlauch 1', 'STF') THEN 1 ELSE 0 END) as schlauch1,
                SUM(CASE WHEN s.funktion IN ('Schlauch 2', 'STM') THEN 1 ELSE 0 END) as schlauch2,
                SUM(CASE WHEN s.funktion = 'Keine Funktion' OR s.funktion = '' OR s.funktion IS NULL THEN 1 ELSE 0 END) as keine_funktion,
                NOW() as letztes_update
            FROM $stats_table s
            INNER JOIN {$wpdb->posts} p ON p.ID = s.post_id
            LEFT JOIN {$wpdb->postmeta} event_date ON p.ID = event_date.post_id AND event_date.meta_key = '_feu_einsatz_datum'
            WHERE p.post_status = 'publish'
            GROUP BY s.teilnehmer_id, {$event_year_sql}
            ON DUPLICATE KEY UPDATE
                gesamt_einsaetze = VALUES(gesamt_einsaetze),
                maschinist = VALUES(maschinist),
                gruppenfuhrer = VALUES(gruppenfuhrer),
                pa_tf_traeger1 = VALUES(pa_tf_traeger1),
                pa_traeger2 = VALUES(pa_traeger2),
                melder = VALUES(melder),
                wasser1 = VALUES(wasser1),
                wasser2 = VALUES(wasser2),
                schlauch1 = VALUES(schlauch1),
                schlauch2 = VALUES(schlauch2),
                keine_funktion = VALUES(keine_funktion),
                letztes_update = VALUES(letztes_update)
        ";
        
        $wpdb->query($sql);
        
    }
    
    public static function update() {
        $current_version = get_option('feu_einsatz_version', '0.0.0');
        $schema_version = get_option('feu_einsatz_schema_version', '');

        self::set_default_options();
        delete_option('feu_einsatz_single_preloader_css');
        delete_option('feu_einsatz_overview_enable_scroll_animations');
        delete_option('feu_einsatz_overview_scroll_animation');
        self::maybe_upgrade_default_functions_option();
        self::ensure_default_participant_function_option();
        self::migrate_legacy_participant_ranking_pin();
        self::add_default_organizations();

        if (self::SCHEMA_VERSION !== $schema_version) {
            self::create_tables();
            update_option('feu_einsatz_schema_version', self::SCHEMA_VERSION);
            flush_rewrite_rules();
        }

        if (version_compare($current_version, FEU_EINSATZ_VERSION, '<')) {
            self::migrate_existing_data();
            update_option('feu_einsatz_version', FEU_EINSATZ_VERSION);
        }
    }
    
    /**
     * Debug-Funktion: Zeigt den Status aller Tabellen
     */
    public static function debug_tables() {
        global $wpdb;
        
        $tables = [
            'feu_einsatz_teilnehmer',
            'feu_einsatz_statistiken',
            'feu_einsatz_organisationen',
            'feu_einsatz_statistics_cache'
        ];
        
        $html = '<h3>Datenbank-Status</h3>';
        $html .= '<table class="wp-list-table widefat fixed striped">';
        $html .= '<thead><tr><th>Tabelle</th><th>Existiert</th><th>Einträge</th></tr></thead>';
        $html .= '<tbody>';
        
        foreach ($tables as $table) {
            $full_name = $wpdb->prefix . $table;
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_name'");
            $count = $exists ? $wpdb->get_var("SELECT COUNT(*) FROM $full_name") : 0;
            
            $html .= '<tr>';
            $html .= '<td>' . esc_html($table) . '</td>';
            $html .= '<td>' . ($exists ? 'вњ… Ja' : 'вќЊ Nein') . '</td>';
            $html .= '<td>' . intval($count) . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        
        return $html;
    }
}
