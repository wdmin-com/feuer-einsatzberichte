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

update_option('feu_einsatz_social_share_image_mode', 'generated');
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

$participant_id = $database->save_participant(0, [
    'vorname' => 'CI',
    'nachname' => 'Teilnehmer',
    'member_function' => 'Test',
]);
if (!$participant_id) {
    feu_einsatz_ci_fail('Participant fixture could not be saved before purge.');
}

$backup = $core->get_backup();
$archive_file = trailingslashit($backup->get_archive_storage_dir()) . 'ci-purge-test.zip';
file_put_contents($archive_file, 'CI archive fixture');
$archive_saved = $database->save_archive([
    'archive_key' => 'ci-purge-test',
    'filename' => basename($archive_file),
    'label' => 'CI purge test',
]);
if (!$archive_saved) {
    feu_einsatz_ci_fail('Archive fixture could not be saved before purge.');
}

FEU_Einsatz_Logger::log('settings_saved', 'settings', 0, 'CI log before purge');
$purge_code = $admin->generate_factory_reset_code();
$purge_result = $admin->purge_selected_data_with_code($purge_code, [
    'participants',
    'reports',
    'statistics',
    'settings',
    'logs',
    'archives',
]);

if (is_wp_error($purge_result)) {
    feu_einsatz_ci_fail('Selective purge failed: ' . $purge_result->get_error_message());
}
if (get_post($report_id) || $database->get_participant((int) $participant_id)) {
    feu_einsatz_ci_fail('Reports or participants remained after selective purge.');
}
if (file_exists($archive_file) || !empty($database->get_archives(10))) {
    feu_einsatz_ci_fail('Archive files or records remained after selective purge.');
}
if (wp_next_scheduled('feu_einsatz_generate_share_card_background', [(int) $report_id])) {
    feu_einsatz_ci_fail('A deleted report retained its share-card cron event.');
}
if (1 !== $database->count_logs(['action_type' => 'data_purge_completed'])) {
    feu_einsatz_ci_fail('The final user audit record was not preserved after log purge.');
}
if (1 !== (int) get_option('feu_einsatz_setup_wizard_pending', 0) || false !== get_option('feu_einsatz_settings_history', false)) {
    feu_einsatz_ci_fail('Settings purge did not create a clean first-run state.');
}

$saved_after_purge = $database->save_participant(0, [
    'vorname' => 'Neu',
    'nachname' => 'Gespeichert',
    'member_function' => 'Test',
]);
update_option('feu_einsatz_map_zoom', 14, false);
if (!$saved_after_purge || 14 !== (int) get_option('feu_einsatz_map_zoom')) {
    feu_einsatz_ci_fail('New data could not be saved after selective purge.');
}

WP_CLI::success('Plugin loaded; recovery, selective purge, audit logging and post-purge saving succeeded.');
