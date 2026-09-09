<?php
/**
 * Minimal integration test executed through WP-CLI against a fresh WordPress.
 */

if (!defined('ABSPATH')) {
    fwrite(STDERR, "WordPress was not bootstrapped.\n");
    exit(1);
}

function feu_einsatz_ci_fail(string $message): void {
    $annotation_message = str_replace(["\r", "\n"], ' ', $message);
    fwrite(STDERR, "::error title=Plugin integration test::{$annotation_message}\n");
    exit(1);
}

if (!defined('FEU_EINSATZ_VERSION') || !class_exists('Feuer_Einsatzberichte_Core')) {
    feu_einsatz_ci_fail('Plugin did not load correctly.');
}

$core = Feuer_Einsatzberichte_Core::get_instance();
$database = $core->get_db();

if (!$database instanceof FEU_Einsatz_Database) {
    feu_einsatz_ci_fail('Plugin database service is unavailable.');
}

$dashboard_data = $database->get_statistics_dashboard_data((int) gmdate('Y'));
foreach (['total', 'categories', 'participants', 'daily_stats', 'daily_report_entries', 'activity_map_points'] as $key) {
    if (!array_key_exists($key, $dashboard_data)) {
        feu_einsatz_ci_fail("Statistics dashboard payload is incomplete: {$key}.");
    }
}

$database->invalidate_statistics_dashboard_cache();

update_option('feu_einsatz_social_share_image_mode', 'generated');
$admin = $core->get_admin();

if (!$admin instanceof FEU_Einsatz_Admin) {
    feu_einsatz_ci_fail('Plugin admin service is unavailable in the CLI context.');
}

wp_set_current_user(1);
update_option('feu_einsatz_map_zoom', 15, false);
$admin->save_settings_snapshot(['feu_einsatz_map_zoom' => 15]);
update_option('feu_einsatz_map_zoom', 17, false);

if (!$admin->restore_latest_settings_snapshot() || 15 !== (int) get_option('feu_einsatz_map_zoom')) {
    feu_einsatz_ci_fail('Settings history could not restore the previous value.');
}

$first_reset_code = $admin->generate_factory_reset_code();
$second_reset_code = $admin->generate_factory_reset_code();
if (!preg_match('/^\d{7}$/', $first_reset_code) || !preg_match('/^\d{7}$/', $second_reset_code) || $first_reset_code === $second_reset_code) {
    feu_einsatz_ci_fail('Factory reset codes are invalid or were repeated.');
}
if ($admin->reset_settings_with_code($first_reset_code) || !$admin->reset_settings_with_code($second_reset_code)) {
    feu_einsatz_ci_fail('Factory reset code expiry or validation failed.');
}

$report_id = wp_insert_post([
    'post_title' => 'Share-card CI report',
    'post_status' => 'publish',
    'post_type' => 'post',
]);
update_post_meta($report_id, '_feu_einsatz_einsatzbericht', '1');
$admin->get_report_share()->queue_share_card_generation((int) $report_id);

if (!wp_next_scheduled('feu_einsatz_generate_share_card_background', [(int) $report_id])) {
    feu_einsatz_ci_fail('Share-card background generation was not queued.');
}

WP_CLI::success('Plugin loaded, activated and statistics cache service responded.');
