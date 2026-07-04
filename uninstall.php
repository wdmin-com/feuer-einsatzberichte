<?php
/**
 * Uninstall Script
 * Runs when the plugin is uninstalled from WordPress.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

if (!function_exists('feu_einsatz_uninstall_delete_path')) {
    function feu_einsatz_uninstall_delete_path($path) {
        if (!file_exists($path)) {
            return;
        }

        if (is_file($path) || is_link($path)) {
            wp_delete_file($path);
            return;
        }

        $items = scandir($path);

        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ('.' === $item || '..' === $item) {
                continue;
            }

            feu_einsatz_uninstall_delete_path(trailingslashit($path) . $item);
        }

        @rmdir($path);
    }
}

global $wpdb;

if (!function_exists('feu_einsatz_uninstall_is_owned_attachment')) {
    function feu_einsatz_uninstall_is_owned_attachment($attachment_id) {
        $attachment_id = absint($attachment_id);

        if (!$attachment_id || 'attachment' !== get_post_type($attachment_id)) {
            return false;
        }

        if ('1' === (string) get_post_meta($attachment_id, '_feu_einsatz_generated_map_preview', true)) {
            return true;
        }

        $attached_file = wp_normalize_path((string) get_attached_file($attachment_id));
        if ('' === $attached_file) {
            return false;
        }

        $upload_data = wp_upload_dir();
        $upload_base = trailingslashit(wp_normalize_path((string) $upload_data['basedir']));
        $relative_file = 0 === strpos($attached_file, $upload_base)
            ? ltrim(substr($attached_file, strlen($upload_base)), '/')
            : '';

        return '' !== $relative_file && (
            0 === strpos($relative_file, 'feuer-einsatzberichte/')
            || 0 === strpos($relative_file, 'feuer-einsatzberichte-watermarked/')
            || 0 === strpos($relative_file, 'feuer-einsatzberichte-area/')
        );
    }
}

$report_post_ids = get_posts([
    'post_type' => 'post',
    'post_status' => 'any',
    'posts_per_page' => -1,
    'fields' => 'ids',
    'meta_query' => [
        [
            'key' => '_feu_einsatz_einsatzbericht',
            'value' => '1',
            'compare' => '=',
        ],
    ],
]);

$attachment_ids = [];

foreach ((array) $report_post_ids as $report_post_id) {
    $report_post_id = absint($report_post_id);

    if (!$report_post_id) {
        continue;
    }

    $attachment_ids[] = (int) get_post_meta($report_post_id, '_feu_einsatz_generated_map_thumbnail_id', true);
}

$attachment_ids = array_values(array_unique(array_filter(array_map('absint', $attachment_ids))));

foreach ((array) $report_post_ids as $report_post_id) {
    wp_delete_post((int) $report_post_id, true);
}

foreach ($attachment_ids as $attachment_id) {
    if (feu_einsatz_uninstall_is_owned_attachment($attachment_id)) {
        wp_delete_attachment($attachment_id, true);
    }
}

$tables = [
    $wpdb->prefix . 'feu_einsatz_teilnehmer',
    $wpdb->prefix . 'feu_einsatz_statistiken',
    $wpdb->prefix . 'feu_einsatz_organisationen',
    $wpdb->prefix . 'feu_einsatz_statistics_cache',
    $wpdb->prefix . 'feu_einsatz_archive',
    $wpdb->prefix . 'feu_einsatz_logs',
];

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
}

$options = [
    'feu_einsatz_version',
    'feu_einsatz_schema_version',
    'feu_einsatz_update_manifest_url',
    'feu_einsatz_functions',
    'feu_einsatz_categories',
    'feu_einsatz_map_zoom',
    'feu_einsatz_map_height',
    'feu_einsatz_auto_map_image',
    'feu_einsatz_default_comments_enabled',
    'feu_einsatz_related_reports_display',
    'feu_einsatz_related_reports_count',
    'feu_einsatz_backup_retention_limit',
    'feu_einsatz_participant_ranking_pin',
    'feu_einsatz_default_participant_function',
    'feu_einsatz_single_preloader_css',
    'feu_einsatz_single_desaturate_organizations',
    'feu_einsatz_single_info_fields',
    'feu_einsatz_single_map_display_mode',
    'feu_einsatz_single_map_privacy_mode',
    'feu_einsatz_overview_show_stats',
    'feu_einsatz_overview_show_year_filter',
    'feu_einsatz_overview_enable_scroll_animations',
    'feu_einsatz_social_share_enabled_networks',
    'feu_einsatz_social_share_image_mode',
    'feu_einsatz_social_share_background_id',
    'feu_einsatz_social_share_fields',
    'feu_einsatz_role_access',
    'feu_einsatz_photo_watermark_enabled',
    'feu_einsatz_photo_watermark_text',
    'feu_einsatz_photo_watermark_image_id',
    'feu_einsatz_photo_watermark_opacity',
    'feu_einsatz_photo_watermark_scale',
    'feu_einsatz_street_cache_keys',
];

foreach ($options as $option) {
    delete_option($option);
}

$meta_keys = [
    '_feu_einsatz_einsatzbericht',
    '_feu_einsatz_strasse',
    '_feu_einsatz_plz',
    '_feu_einsatz_stadt',
    '_feu_einsatz_datum',
    '_feu_einsatz_uhrzeit',
    '_feu_einsatz_latitude',
    '_feu_einsatz_longitude',
    '_feu_einsatz_display_address',
    '_feu_einsatz_teilnehmer',
    '_feu_einsatz_organisationen',
    '_feu_einsatz_gallery',
    '_feu_einsatz_comments_enabled',
    '_feu_einsatz_availability_mode',
    '_feu_einsatz_available_from',
    '_feu_einsatz_generated_map_thumbnail_id',
    '_feu_einsatz_generated_map_preview',
    '_feu_einsatz_generated_map_preview_url',
    '_feu_einsatz_generated_map_preview_file',
    '_feu_einsatz_street_geometry',
    '_feu_einsatz_street_center',
    '_feu_einsatz_street_bounds',
    '_feu_einsatz_street_line_data',
    '_feu_einsatz_street_geometry_final',
    '_feu_einsatz_street_center_final',
    '_feu_einsatz_street_cache_version',
    '_feu_einsatz_generated_map_queue',  // 3.1.34: neu in 3.1.33, fehlte bei Deinstallation
];

foreach ($meta_keys as $meta_key) {
    $wpdb->delete($wpdb->postmeta, ['meta_key' => $meta_key]);
}

delete_metadata('user', 0, '_feu_einsatz_participant_ranking_unlocked_until', '', true);

$upload_dir = wp_upload_dir();
$plugin_directories = [
    trailingslashit($upload_dir['basedir']) . 'feuer-einsatzberichte',
    trailingslashit($upload_dir['basedir']) . 'feuer-einsatzberichte/share-cards',
    trailingslashit($upload_dir['basedir']) . 'feuer-einsatzberichte-watermarked',
    trailingslashit($upload_dir['basedir']) . 'feuer-einsatzberichte-archives',
    trailingslashit($upload_dir['basedir']) . 'feuer-einsatzberichte-temp',
];

foreach ($plugin_directories as $plugin_directory) {
    feu_einsatz_uninstall_delete_path($plugin_directory);
}

// 3.1.34 Korrektur: Präfix war 'eb_%' (falsch) — konnte Daten anderer Plugins löschen.
// Geändert auf korrekten Präfix 'feu_einsatz_%'. Escape-Zeichen für alle LIKE ergänzt.
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'feu\_einsatz\_%' ESCAPE '\\\\'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_feu\_einsatz\_%' ESCAPE '\\\\'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_timeout\_feu\_einsatz\_%' ESCAPE '\\\\'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_feu\_einsatz\_street\_geometry\_%' ESCAPE '\\\\'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_timeout\_feu\_einsatz\_street\_geometry\_%' ESCAPE '\\\\'");
// phpcs:enable

wp_cache_flush();
