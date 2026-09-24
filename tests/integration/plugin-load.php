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
update_option('feu_einsatz_map_preview_highlight_color', '#2563eb', false);
$report_id = wp_insert_post([
    'post_title' => 'Share-card CI report',
    'post_status' => 'publish',
    'post_type' => 'post',
]);
update_post_meta($report_id, '_feu_einsatz_einsatzbericht', '1');
$ci_category_ids = array_values(array_filter(array_map('absint', (array) get_option('feu_einsatz_categories', []))));
if (!empty($ci_category_ids)) {
    wp_set_post_categories($report_id, [(int) $ci_category_ids[0]], false);
}
update_post_meta($report_id, '_feu_einsatz_strasse', 'Bredowstraße');
update_post_meta($report_id, '_feu_einsatz_hausnummer', '4');
update_post_meta($report_id, '_feu_einsatz_plz', '22113');
update_post_meta($report_id, '_feu_einsatz_stadt', 'Hamburg');
update_post_meta($report_id, '_feu_einsatz_latitude', '53.528320');
update_post_meta($report_id, '_feu_einsatz_longitude', '10.083210');
$street_geometry = [
    'geometry' => [[
        'points' => [
            ['lat' => 53.527900, 'lng' => 10.081900],
            ['lat' => 53.528320, 'lng' => 10.083210],
            ['lat' => 53.528780, 'lng' => 10.084650],
        ],
        'highway' => 'secondary',
        'kind' => 'road',
    ]],
    'center' => [53.528320, 10.083210],
];
if (!FEU_Einsatz_Street_Cache::set_post_cache((int) $report_id, $street_geometry)) {
    feu_einsatz_ci_fail('Street geometry fixture could not be saved.');
}

update_option('feu_einsatz_street_highlight_mode', 'length', false);
update_option('feu_einsatz_street_highlight_length_meters', 120, false);
$length_highlight = FEU_Einsatz_Template_Helpers::apply_street_highlight_mode(
    $street_geometry['geometry'],
    53.528320,
    10.083210
);
if ('length' !== ($length_highlight['mode'] ?? '') || empty($length_highlight['geometry'])) {
    feu_einsatz_ci_fail('Street-length highlight did not retain a visible geometry.');
}

update_option('feu_einsatz_street_highlight_mode', 'radius', false);
update_option('feu_einsatz_street_highlight_radius_meters', 120, false);
$radius_highlight = FEU_Einsatz_Template_Helpers::apply_street_highlight_mode(
    $street_geometry['geometry'],
    53.528320,
    10.083210
);
if ('radius' !== ($radius_highlight['mode'] ?? '') || 120 !== (int) ($radius_highlight['radius_meters'] ?? 0) || empty($radius_highlight['geometry'])) {
    feu_einsatz_ci_fail('Radius highlight did not retain a visible geometry and radius.');
}

$side_length_highlight = FEU_Einsatz_Template_Helpers::apply_street_highlight_mode(
    $street_geometry['geometry'],
    53.527980,
    10.082180,
    [
        'mode' => 'length',
        'length_meters' => 40,
        'radius_meters' => 100,
        'include_pedestrian' => true,
    ]
);
$side_radius_highlight = FEU_Einsatz_Template_Helpers::apply_street_highlight_mode(
    $street_geometry['geometry'],
    53.527980,
    10.082180,
    [
        'mode' => 'radius',
        'length_meters' => 100,
        'radius_meters' => 40,
        'include_pedestrian' => true,
    ]
);
foreach ([$side_length_highlight, $side_radius_highlight] as $side_highlight) {
    foreach ((array) ($side_highlight['geometry'] ?? []) as $segment) {
        foreach ((array) ($segment['points'] ?? []) as $point) {
            if ((float) ($point['lng'] ?? 0) > 10.082800) {
                feu_einsatz_ci_fail('Limited street highlighting drifted away from the supplied house-address coordinates.');
            }
        }
    }
}

$address_key_4 = FEU_Einsatz_Template_Helpers::get_report_address_coordinates_key('Bredowstraße', '4', '22113', 'Hamburg');
$address_key_5 = FEU_Einsatz_Template_Helpers::get_report_address_coordinates_key('Bredowstraße', '5', '22113', 'Hamburg');
if ($address_key_4 === $address_key_5) {
    feu_einsatz_ci_fail('House-number coordinate keys must not share the same map anchor.');
}

update_post_meta($report_id, FEU_Einsatz_Template_Helpers::MAP_HIGHLIGHT_OVERRIDE_META, 'radius');
update_post_meta($report_id, FEU_Einsatz_Template_Helpers::MAP_HIGHLIGHT_RADIUS_META, 360);
update_post_meta($report_id, FEU_Einsatz_Template_Helpers::MAP_LOCATION_MODE_META, 'coordinates');
$report_highlight_settings = FEU_Einsatz_Template_Helpers::get_report_street_highlight_settings((int) $report_id);
if ('radius' !== ($report_highlight_settings['mode'] ?? '') || 360 !== (int) ($report_highlight_settings['radius_meters'] ?? 0)) {
    feu_einsatz_ci_fail('A report-specific highlight override was not resolved.');
}
$manual_coordinates = FEU_Einsatz_Template_Helpers::get_report_incident_coordinates((int) $report_id, '', '', 'Hamburg', true);
if (!is_array($manual_coordinates) || 53.528320 !== (float) ($manual_coordinates['lat'] ?? 0) || 10.083210 !== (float) ($manual_coordinates['lng'] ?? 0)) {
    feu_einsatz_ci_fail('Manual report coordinates were not preserved.');
}
$manual_radius_highlight = FEU_Einsatz_Template_Helpers::apply_street_highlight_mode([], $manual_coordinates['lat'], $manual_coordinates['lng'], $report_highlight_settings);
if ('radius' !== ($manual_radius_highlight['mode'] ?? '') || 360 !== (int) ($manual_radius_highlight['radius_meters'] ?? 0)) {
    feu_einsatz_ci_fail('A coordinate-only radius did not remain renderable.');
}

$radius_preview_markup = FEU_Einsatz_Template_Helpers::build_local_map_preview_markup([
    'latitude' => $manual_coordinates['lat'],
    'longitude' => $manual_coordinates['lng'],
    'geometry' => [],
    'address' => 'Koordinatenbasierter Einsatzbereich',
    'highlight_mode' => 'radius',
    'highlight_radius_meters' => 360,
]);
if (false === strpos($radius_preview_markup, '<ellipse')) {
    feu_einsatz_ci_fail('A coordinate-only radius did not produce an SVG fallback circle.');
}

$extra_streets = FEU_Einsatz_Template_Helpers::normalize_report_map_extra_streets("Bredowstraße\nBredowstraße\nMusterweg");
if (['Bredowstraße', 'Musterweg'] !== $extra_streets) {
    feu_einsatz_ci_fail('Additional report streets were not normalized deterministically.');
}
$area_geojson = FEU_Einsatz_Template_Helpers::normalize_report_map_area_geojson([
    'type' => 'Polygon',
    'coordinates' => [[[10.082000, 53.528000], [10.084000, 53.528000], [10.083000, 53.530000]]],
]);
if ('Polygon' !== ($area_geojson['type'] ?? '') || 4 !== count($area_geojson['coordinates'][0] ?? [])) {
    feu_einsatz_ci_fail('A report incident area was not normalized as a closed GeoJSON polygon.');
}
// Pass the normalized polygon directly to validate the generic SVG fallback.
$area_preview_markup = FEU_Einsatz_Template_Helpers::build_local_map_preview_markup([
    'latitude' => 53.528320,
    'longitude' => 10.083210,
    'geometry' => $street_geometry['geometry'],
    'area_geometry' => array_map(static function ($point) {
        return ['lat' => $point[1], 'lng' => $point[0]];
    }, $area_geojson['coordinates'][0]),
    'address' => 'Bredowstraße 4, 22113 Hamburg',
]);
if (false === strpos($area_preview_markup, '<polygon')) {
    feu_einsatz_ci_fail('The local map preview did not render the saved incident area.');
}
update_post_meta($report_id, FEU_Einsatz_Template_Helpers::MAP_EXTRA_STREETS_META, $extra_streets);
update_post_meta($report_id, FEU_Einsatz_Template_Helpers::MAP_AREA_GEOJSON_META, $area_geojson);
update_post_meta($report_id, FEU_Einsatz_Template_Helpers::MAP_PUBLIC_PRECISION_META, 'approx_500');
$rounded_public_coordinates = FEU_Einsatz_Template_Helpers::get_report_map_public_coordinates($report_id, 53.528320, 10.083210);
if (!is_array($rounded_public_coordinates) || 'approx_500' !== ($rounded_public_coordinates['precision'] ?? '') || 500 !== (int) ($rounded_public_coordinates['meters'] ?? 0)) {
    feu_einsatz_ci_fail('The public map precision profile did not protect a report coordinate.');
}
update_post_meta($report_id, FEU_Einsatz_Template_Helpers::MAP_PUBLIC_PRECISION_META, 'hidden');
if (false !== FEU_Einsatz_Template_Helpers::get_report_map_public_coordinates($report_id, 53.528320, 10.083210)) {
    feu_einsatz_ci_fail('A hidden public map still exposed report coordinates.');
}
delete_post_meta($report_id, FEU_Einsatz_Template_Helpers::MAP_EXTRA_STREETS_META);
delete_post_meta($report_id, FEU_Einsatz_Template_Helpers::MAP_AREA_GEOJSON_META);
delete_post_meta($report_id, FEU_Einsatz_Template_Helpers::MAP_PUBLIC_PRECISION_META);

// A stored manual point must never survive a switch back to address mode. The
// next background job is then forced to resolve the verified address anew.
update_post_meta($report_id, FEU_Einsatz_Template_Helpers::MAP_LOCATION_MODE_META, 'coordinates');
update_post_meta($report_id, '_feu_einsatz_latitude', '53.528320');
update_post_meta($report_id, '_feu_einsatz_longitude', '10.083210');
update_option('feu_einsatz_auto_map_image', 0, false);
$_POST = [
    'feu_einsatz_meta_box_nonce' => wp_create_nonce('feu_einsatz_save_post_data'),
    'feu_einsatz_is_einsatzbericht' => '1',
    'feu_einsatz_map_location_mode' => 'address',
    'feu_einsatz_map_highlight_override' => 'full',
    'feu_einsatz_map_highlight_length_meters' => '100',
    'feu_einsatz_map_highlight_radius_meters' => '100',
    'feu_einsatz_map_public_precision' => 'exact',
    'feu_einsatz_strasse' => 'Bredowstraße',
    'feu_einsatz_hausnummer' => '4',
    'feu_einsatz_plz' => '22113',
    'feu_einsatz_stadt' => 'Hamburg',
    'feu_einsatz_datum' => '2026-09-23',
    'feu_einsatz_uhrzeit' => '12:00',
];
$admin->save_post_data($report_id, get_post($report_id), true);
if (
    'address' !== FEU_Einsatz_Template_Helpers::get_report_map_location_mode($report_id)
    || '' !== (string) get_post_meta($report_id, '_feu_einsatz_latitude', true)
    || '' !== (string) get_post_meta($report_id, '_feu_einsatz_longitude', true)
) {
    feu_einsatz_ci_fail('Switching from manual coordinates back to address mode retained the old map point.');
}
$_POST = [];
delete_post_meta($report_id, FEU_Einsatz_Template_Helpers::MAP_HIGHLIGHT_OVERRIDE_META);
delete_post_meta($report_id, FEU_Einsatz_Template_Helpers::MAP_HIGHLIGHT_RADIUS_META);
delete_post_meta($report_id, FEU_Einsatz_Template_Helpers::MAP_LOCATION_MODE_META);

// The preceding assertion intentionally removes a manually entered point.
// Reproduce the subsequent successful address-geocoding job before testing the
// public renderer; otherwise the test asks a public page to display data that
// it correctly refuses to invent.
update_post_meta($report_id, '_feu_einsatz_latitude', '53.528320');
update_post_meta($report_id, '_feu_einsatz_longitude', '10.083210');
FEU_Einsatz_Template_Helpers::mark_report_address_coordinates(
    $report_id,
    'Bredowstraße',
    '4',
    '22113',
    'Hamburg'
);
if (!FEU_Einsatz_Street_Cache::set_post_cache((int) $report_id, $street_geometry)) {
    feu_einsatz_ci_fail('Restored address geometry fixture could not be saved.');
}

update_option('feu_einsatz_street_highlight_mode', 'full', false);
$street_revision = get_post_meta($report_id, FEU_Einsatz_Street_Cache::POST_META_REVISION, true);
$single_context = FEU_Einsatz_Template_Helpers::get_single_context(get_post($report_id));
if (
    '' === (string) $street_revision
    || '#2563eb' !== (string) ($single_context['map']['config']['highlight_color'] ?? '')
    || empty($single_context['map']['config']['geometry'])
) {
    feu_einsatz_ci_fail('Street geometry or selected highlight color did not reach the public map context.');
}

$_GET['year'] = (string) gmdate('Y');
$_GET['tab'] = 'categories';
ob_start();
$admin->render_statistics();
$statistics_markup = (string) ob_get_clean();
if (
    false === strpos($statistics_markup, 'id="categoryDonutChart"')
    || false === strpos($statistics_markup, 'feuCategoryCenterLabel')
    || false === strpos($statistics_markup, 'feu-einsatz-admindek-category-key')
) {
    feu_einsatz_ci_fail('Statistics category chart markup is incomplete.');
}
unset($_GET['year'], $_GET['tab']);

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
