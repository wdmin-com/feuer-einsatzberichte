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
    'post_type' => FEU_Einsatz_Report_Post_Type::readable_post_types(),
    'post_status' => 'publish',
    'numberposts' => -1,
    'fields' => 'ids',
    'meta_key' => '_feu_einsatz_einsatzbericht',
    'meta_value' => '1',
]);

if (30 !== count($report_ids)) {
    feu_einsatz_demo_ci_fail('Demo backup must restore exactly 30 published reports, including 5 QA scenarios.');
}

$year_counts = [2026 => 0, 2025 => 0];
$qa_scenarios = [];
foreach ($report_ids as $report_id) {
    if (FEU_Einsatz_Report_Post_Type::POST_TYPE !== get_post_type($report_id)) {
        feu_einsatz_demo_ci_fail("Report {$report_id} was not restored as a separate report post type.");
    }
    if ('legacy' !== get_post_meta($report_id, FEU_Einsatz_Report_Post_Type::URL_SCHEME_META, true)) {
        feu_einsatz_demo_ci_fail("Report {$report_id} lost its legacy permalink scheme.");
    }
    $date = (string) get_post_meta($report_id, '_feu_einsatz_datum', true);
    $year = (int) substr($date, 0, 4);
    if (isset($year_counts[$year])) {
        $year_counts[$year]++;
    }
    $scenario = (string) get_post_meta($report_id, '_feu_einsatz_demo_scenario', true);
    if ('' !== $scenario) {
        $qa_scenarios[$scenario] = $report_id;
    }

    $latitude = get_post_meta($report_id, '_feu_einsatz_latitude', true);
    $longitude = get_post_meta($report_id, '_feu_einsatz_longitude', true);
    if (!is_numeric($latitude) || !is_numeric($longitude)) {
        feu_einsatz_demo_ci_fail("Report {$report_id} has no usable coordinates for street geometry lookup.");
    }
}

if (15 !== $year_counts[2026] || 15 !== $year_counts[2025]) {
    feu_einsatz_demo_ci_fail('Demo report distribution must be 15 reports for 2026 and 15 for 2025.');
}
foreach (['length', 'radius', 'full', 'coordinate_only', 'district'] as $required_scenario) {
    if (empty($qa_scenarios[$required_scenario])) {
        feu_einsatz_demo_ci_fail("QA report scenario {$required_scenario} is missing.");
    }
}
$coordinate_only_context = FEU_Einsatz_Template_Helpers::get_single_context(get_post($qa_scenarios['coordinate_only']));
if (
    'radius' !== ($coordinate_only_context['map']['config']['highlight_mode'] ?? '')
    || empty($coordinate_only_context['map']['has_live_data'])
    || empty($coordinate_only_context['map']['preview_markup'])
    || !empty($coordinate_only_context['map']['config']['geometry'])
) {
    feu_einsatz_demo_ci_fail('Coordinate-only QA report did not render a circle-only live map.');
}
if ('Hamburg-Altstadt' !== (string) get_post_meta($qa_scenarios['district'], '_feu_einsatz_stadtteil', true)) {
    feu_einsatz_demo_ci_fail('QA district was lost during demo restore.');
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
if (
    15 !== (int) ($dashboard_current['total']['total_einsaetze'] ?? 0)
    || 15 !== (int) ($dashboard_previous['total']['total_einsaetze'] ?? 0)
) {
    feu_einsatz_demo_ci_fail('Statistics dashboard did not reflect restored demo years.');
}

WP_CLI::success('Hamburg demo backup restored: 30 reports, 25 participants, five QA map scenarios and yearly statistics verified.');
