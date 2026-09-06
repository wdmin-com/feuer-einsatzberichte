<?php
/**
 * Minimal integration test executed through WP-CLI against a fresh WordPress.
 */

if (!defined('ABSPATH')) {
    fwrite(STDERR, "WordPress was not bootstrapped.\n");
    exit(1);
}

if (!defined('FEU_EINSATZ_VERSION') || !class_exists('Feuer_Einsatzberichte_Core')) {
    fwrite(STDERR, "Plugin did not load correctly.\n");
    exit(1);
}

$core = Feuer_Einsatzberichte_Core::get_instance();
$database = $core->get_db();

if (!$database instanceof FEU_Einsatz_Database) {
    fwrite(STDERR, "Plugin database service is unavailable.\n");
    exit(1);
}

$dashboard_data = $database->get_statistics_dashboard_data((int) gmdate('Y'));
foreach (['total', 'categories', 'participants', 'daily_stats', 'daily_report_entries', 'activity_map_points'] as $key) {
    if (!array_key_exists($key, $dashboard_data)) {
        fwrite(STDERR, "Statistics dashboard payload is incomplete: {$key}.\n");
        exit(1);
    }
}

$database->invalidate_statistics_dashboard_cache();

update_option('feu_einsatz_social_share_image_mode', 'generated');
$admin = $core->get_admin();

if (!$admin instanceof FEU_Einsatz_Admin) {
    fwrite(STDERR, "Plugin admin service is unavailable in the CLI context.\n");
    exit(1);
}

$report_id = wp_insert_post([
    'post_title' => 'Share-card CI report',
    'post_status' => 'publish',
    'post_type' => 'post',
]);
update_post_meta($report_id, '_feu_einsatz_einsatzbericht', '1');
$admin->get_report_share()->queue_share_card_generation((int) $report_id);

if (!wp_next_scheduled('feu_einsatz_generate_share_card_background', [(int) $report_id])) {
    fwrite(STDERR, "Share-card background generation was not queued.\n");
    exit(1);
}

WP_CLI::success('Plugin loaded, activated and statistics cache service responded.');
