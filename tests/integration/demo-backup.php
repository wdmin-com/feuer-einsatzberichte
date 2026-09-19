<?php
/**
 * Restores the generated Hamburg demo archive and validates its useful content.
 */

if (!defined('ABSPATH')) {
    fwrite(STDERR, "WordPress was not bootstrapped.\n");
    exit(1);
}

function feu_einsatz_demo_ci_fail(string $message): void {
    $annotation_message = str_replace(["\r", "\n"], ' ', $message);
    fwrite(STDERR, "::error title=Hamburg demo integration test::{$annotation_message}\n");
    exit(1);
}

$archive_path = (string) getenv('DEMO_BACKUP_PATH');
if ('' === $archive_path || !is_file($archive_path)) {
    feu_einsatz_demo_ci_fail('Generated demo archive is missing.');
}

$core = Feuer_Einsatzberichte_Core::get_instance();
$backup = $core->get_backup();
$database = $core->get_db();
$restore_result = $backup->restore_archive_file($archive_path);

if (is_wp_error($restore_result)) {
    feu_einsatz_demo_ci_fail('Demo archive restore failed: ' . $restore_result->get_error_message());
}
if (true !== $restore_result) {
    feu_einsatz_demo_ci_fail('Demo archive restore did not return success.');
}

$report_ids = get_posts([
    'post_type' => 'post',
    'post_status' => 'publish',
    'numberposts' => -1,
    'fields' => 'ids',
    'meta_key' => '_feu_einsatz_einsatzbericht',
    'meta_value' => '1',
]);

if (25 !== count($report_ids)) {
    feu_einsatz_demo_ci_fail('Demo backup must restore exactly 25 published reports.');
}

$year_counts = [2026 => 0, 2025 => 0];
foreach ($report_ids as $report_id) {
    $date = (string) get_post_meta($report_id, '_feu_einsatz_datum', true);
    $year = (int) substr($date, 0, 4);
    if (isset($year_counts[$year])) {
        $year_counts[$year]++;
    }

    $geometry = get_post_meta($report_id, '_feu_einsatz_street_geometry_final', true);
    $revision = get_post_meta($report_id, FEU_Einsatz_Street_Cache::POST_META_REVISION, true);
    if (!is_array($geometry) || empty($geometry) || '' === (string) $revision) {
        feu_einsatz_demo_ci_fail("Report {$report_id} has no restorable street geometry.");
    }
}

if (10 !== $year_counts[2026] || 15 !== $year_counts[2025]) {
    feu_einsatz_demo_ci_fail('Demo report distribution must be 10 reports for 2026 and 15 for 2025.');
}

global $wpdb;
$participant_count = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . $database->get_participant_table_name());
$assignment_count = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . $database->get_stats_table_name());
if (25 !== $participant_count || $assignment_count < 25) {
    feu_einsatz_demo_ci_fail('Demo participants or report assignments were not restored.');
}

if (
    'Bredowstraße 4' !== (string) get_option('feu_einsatz_area_station_street')
    || '22113' !== (string) get_option('feu_einsatz_area_station_postcode')
    || 'Hamburg' !== (string) get_option('feu_einsatz_area_station_city')
) {
    feu_einsatz_demo_ci_fail('Feuerwehrakademie Hamburg address was not restored.');
}

if ('#e11d48' !== (string) get_option('feu_einsatz_map_preview_highlight_color')) {
    feu_einsatz_demo_ci_fail('Demo street highlight color was not restored.');
}

$station = FEU_Einsatz_Template_Helpers::get_station_feature_from_settings();
if (!is_array($station) || empty($station['latitude']) || empty($station['longitude'])) {
    feu_einsatz_demo_ci_fail('Demo fire station marker could not be built from the backup.');
}

$dashboard_current = $database->get_statistics_dashboard_data(2026);
$dashboard_previous = $database->get_statistics_dashboard_data(2025);
if (10 !== (int) ($dashboard_current['total'] ?? 0) || 15 !== (int) ($dashboard_previous['total'] ?? 0)) {
    feu_einsatz_demo_ci_fail('Statistics dashboard did not reflect restored demo years.');
}

WP_CLI::success('Hamburg demo backup restored: 25 reports, 25 participants, street maps and yearly statistics verified.');
