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

$single_templates = FEU_Einsatz_Template_Manager::get_templates('single');
$overview_templates = FEU_Einsatz_Template_Manager::get_templates('overview');
$sidebar_templates = FEU_Einsatz_Template_Manager::get_templates('sidebar');
if (
    !isset($single_templates['custom_single_feuer-theme.php'], $single_templates['custom_single_macros.html'])
    || !isset($overview_templates['custom_overview_macros.html'])
    || !isset($sidebar_templates['custom_sidebar_macros.html'])
) {
    feu_einsatz_ci_fail('Bundled custom templates were not discovered.');
}
FEU_Einsatz_Template_Manager::update_selected('single', 'custom_single_feuer-theme.php');
if ('custom_single_feuer-theme.php' !== FEU_Einsatz_Template_Manager::get_selected('single')) {
    feu_einsatz_ci_fail('Custom single template selection was not persisted.');
}
FEU_Einsatz_Template_Manager::update_selected('single', 'default');

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
if ('radius' !== ($radius_highlight['mode'] ?? '') || 120 !== (int) ($radius_highlight['radius_meters'] ?? 0) || !empty($radius_highlight['geometry'])) {
    feu_einsatz_ci_fail('Radius highlight must render only the circle, without a street line.');
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
if (!empty($side_radius_highlight['geometry'])) {
    feu_einsatz_ci_fail('A report radius must not contain a clipped street line.');
}
foreach ([$side_length_highlight] as $side_highlight) {
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
    'geometry' => $street_geometry['geometry'], // The circle must still suppress a stale road line.
    'address' => 'Koordinatenbasierter Einsatzbereich',
    'highlight_mode' => 'radius',
    'highlight_radius_meters' => 360,
]);
if (
    false === strpos($radius_preview_markup, '<ellipse')
    || false === strpos($radius_preview_markup, 'Feuerwehr-Einsatzbereich')
    || false !== strpos($radius_preview_markup, 'vector-effect="non-scaling-stroke"')
) {
    feu_einsatz_ci_fail('A coordinate-only radius did not produce a labelled SVG fallback circle.');
}

$map_label_method = new ReflectionMethod(FEU_Einsatz_Admin::class, 'build_map_preview_street_label');
$radius_map_label = $map_label_method->invoke($admin, $report_id, 'Bredowstraße, 22113 Hamburg', 'radius');
$street_map_label = $map_label_method->invoke($admin, $report_id, 'Bredowstraße, 22113 Hamburg', 'full');
if ('Feuerwehr-Einsatzbereich' !== $radius_map_label || false === strpos($street_map_label, 'Bredowstraße')) {
    feu_einsatz_ci_fail('Map labels did not distinguish a radius from a street highlight.');
}

$svg_map_method = new ReflectionMethod(FEU_Einsatz_Admin::class, 'build_svg_map_preview');
$radius_svg = $svg_map_method->invoke($admin, $report_id, 'Bredowstraße, 22113 Hamburg', $manual_coordinates, [
    'geometry' => $street_geometry['geometry'], // Historic cached lines must be ignored in radius mode.
    'highlight_mode' => 'radius',
    'highlight_radius_meters' => 360,
]);
if (
    false === strpos($radius_svg, '<ellipse')
    || false === strpos($radius_svg, 'Feuerwehr-Einsatzbereich')
    || false !== strpos($radius_svg, '<polyline')
) {
    feu_einsatz_ci_fail('Generated radius SVG must contain a labelled circle and no highlighted street.');
}

$unverified_house_preview_markup = FEU_Einsatz_Template_Helpers::build_local_map_preview_markup([
    'latitude' => 53.528320,
    'longitude' => 10.083210,
    'geometry' => $street_geometry['geometry'],
    'address' => 'Bredowstraße, 22113 Hamburg',
    'highlight_mode' => 'full',
    'show_marker' => false,
]);
if (
    false === strpos($unverified_house_preview_markup, 'vector-effect="non-scaling-stroke"')
    || false !== strpos($unverified_house_preview_markup, '<circle')
    || false !== strpos($unverified_house_preview_markup, '53.528320, 10.083210')
) {
    feu_einsatz_ci_fail('Street-only preview must retain the street line without a false house marker or exact-coordinate label.');
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
    false === strpos($statistics_markup, 'feu-einsatz-category-ranking')
    || false === strpos($statistics_markup, 'feu-einsatz-category-history-table')
    || false === strpos($statistics_markup, 'feu-einsatz-overview-category-list')
    || false !== strpos($statistics_markup, 'id="categoryDonutChart"')
) {
    feu_einsatz_ci_fail('Statistics category ranking or yearly comparison markup is incomplete.');
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

// The editor and public renderer must share a precise address result even if
// Nominatim ranks a different building ahead of the requested house.
$geocoder_calls = 0;
$geocoder_fixture = static function ($preempt, $args, $url) use (&$geocoder_calls) {
    if (false === strpos((string) $url, 'nominatim.openstreetmap.org/search')) {
        return $preempt;
    }
    $geocoder_calls++;
    $body = wp_json_encode([
        [
            'lat' => '53.520000', 'lon' => '10.020000', 'display_name' => 'CI Testweg 13, Hamburg',
            'address' => ['road' => 'CI Testweg', 'house_number' => '13', 'postcode' => '22113', 'city' => 'Hamburg'],
        ],
        [
            'lat' => '53.530000', 'lon' => '10.030000', 'display_name' => 'CI Testweg 12, Hamburg',
            'address' => ['road' => 'CI Testweg', 'house_number' => '12', 'postcode' => '22113', 'city' => 'Hamburg'],
        ],
    ]);
    return ['headers' => [], 'body' => $body, 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => []];
};
add_filter('pre_http_request', $geocoder_fixture, 10, 3);
$precise_address = FEU_Einsatz_Template_Helpers::request_geocoded_address_data('CI Testweg', '22113', 'Hamburg', '12');
remove_filter('pre_http_request', $geocoder_fixture, 10);
if (!is_array($precise_address) || '12' !== ($precise_address['house_number'] ?? '') || 53.53 !== (float) ($precise_address['lat'] ?? 0) || 1 !== $geocoder_calls) {
    feu_einsatz_ci_fail('Precise geocoder accepted a wrong first result or queried twice despite an exact candidate.');
}

$parent = wp_insert_term('CI Einsätze', 'category');
if (is_wp_error($parent) && 'term_exists' === $parent->get_error_code()) {
    $parent = ['term_id' => (int) $parent->get_error_data('term_exists')];
}
$child = !is_wp_error($parent) ? wp_insert_term('CI FEUMANV', 'category', [
    'parent' => (int) $parent['term_id'],
    'description' => 'Feuer mit einem Massenanfall von Verletzten (Großschadenlage) (ab fünf Verletzten)',
]) : $parent;
if (is_wp_error($child) && 'term_exists' === $child->get_error_code()) {
    $child = ['term_id' => (int) $child->get_error_data('term_exists')];
}
if (is_wp_error($parent) || is_wp_error($child)) {
    feu_einsatz_ci_fail('Could not prepare report text categories.');
}
$_POST = [
    'feu_einsatz_strasse' => 'CI Testweg',
    'feu_einsatz_stadt' => 'Hamburg',
    'feu_einsatz_stadtteil' => 'Lurup',
    'feu_einsatz_datum' => '12.06.2026',
];
$title_builder = new ReflectionMethod($admin, 'build_default_report_title_from_request');
$description_builder = new ReflectionMethod($admin, 'build_default_report_description_from_request');
$selected = [(int) $parent['term_id'], (int) $child['term_id']];
$generated_title = $title_builder->invoke($admin, $selected);
$generated_description = $description_builder->invoke($admin, $selected);
if ('CI FEUMANV - CI Testweg' !== $generated_title || false === strpos($generated_description, 'CI FEUMANV - Feuer mit einem Massenanfall') || false === strpos($generated_description, 'auf der CI Testweg in Hamburg Lurup am 12.06.2026.')) {
    feu_einsatz_ci_fail('Auto-generated title or report description does not match the selected incident data.');
}
update_option('feu_einsatz_categories', [(int) $parent['term_id'], (int) $child['term_id']], false);
$submission_validator = new ReflectionMethod($admin, 'validate_report_submission_request');
$_POST['feu_einsatz_map_location_mode'] = 'address';
$_POST['feu_einsatz_map_highlight_override'] = 'radius';
$_POST['feu_einsatz_plz'] = '22113';
$_POST['feu_einsatz_uhrzeit'] = '12:30';
$_POST['post_category'] = [(int) $child['term_id']];
$missing_house = $submission_validator->invoke($admin);
if (empty($missing_house['errors']) || false === strpos(implode(' ', $missing_house['errors']), 'Hausnummer')) {
    feu_einsatz_ci_fail('A radius without a precise address was accepted.');
}
$_POST['feu_einsatz_hausnummer'] = '12';
$confirmed_house = $submission_validator->invoke($admin);
if (!empty($confirmed_house['errors']) || '12' !== ($confirmed_house['geocoded_data']['house_number'] ?? '')) {
    feu_einsatz_ci_fail('A confirmed house number could not be saved in radius mode.');
}
$_POST['feu_einsatz_hausnummer'] = '99';
add_filter('pre_http_request', $geocoder_fixture, 10, 3);
$unconfirmed_house = $submission_validator->invoke($admin);
remove_filter('pre_http_request', $geocoder_fixture, 10);
if (empty($unconfirmed_house['errors']) || false === strpos(implode(' ', $unconfirmed_house['errors']), 'Hausnummer')) {
    feu_einsatz_ci_fail('An unconfirmed house number was accepted in radius mode.');
}
$_POST['feu_einsatz_map_location_mode'] = 'coordinates';
$_POST['feu_einsatz_latitude'] = '53,530000';
$_POST['feu_einsatz_longitude'] = '10,030000';
$coordinate_report = $submission_validator->invoke($admin);
if (!empty($coordinate_report['errors'])) {
    feu_einsatz_ci_fail('A valid coordinate-based report was rejected: ' . implode(' ', $coordinate_report['errors']));
}
$_POST = [];

// A pre-existing road-centre coordinate must not become the centre of a new
// short segment or radius when the requested house cannot be confirmed.
$anchor_report_id = wp_insert_post([
    'post_title' => 'CI map anchor report',
    'post_status' => 'draft',
    'post_type' => 'post',
]);
if (is_wp_error($anchor_report_id) || !$anchor_report_id) {
    feu_einsatz_ci_fail('Could not prepare precise map-anchor report.');
}
update_post_meta($anchor_report_id, FEU_Einsatz_Template_Helpers::MAP_LOCATION_MODE_META, 'address');
update_post_meta($anchor_report_id, FEU_Einsatz_Template_Helpers::MAP_HIGHLIGHT_OVERRIDE_META, 'radius');
update_post_meta($anchor_report_id, '_feu_einsatz_hausnummer', '99');
update_post_meta($anchor_report_id, '_feu_einsatz_latitude', '53.520000');
update_post_meta($anchor_report_id, '_feu_einsatz_longitude', '10.020000');
$wrong_house_fixture = static function ($preempt, $args, $url) {
    if (false === strpos((string) $url, 'nominatim.openstreetmap.org/search')) {
        return $preempt;
    }
    return [
        'headers' => [],
        'body' => wp_json_encode([[
            'lat' => '53.525000', 'lon' => '10.025000', 'display_name' => 'CI Anchorweg, Hamburg',
            'address' => ['road' => 'CI Anchorweg', 'postcode' => '22113', 'city' => 'Hamburg'],
        ]]),
        'response' => ['code' => 200, 'message' => 'OK'],
        'cookies' => [],
    ];
};
add_filter('pre_http_request', $wrong_house_fixture, 10, 3);
$unverified_anchor = FEU_Einsatz_Template_Helpers::get_report_incident_coordinates($anchor_report_id, 'CI Anchorweg', '22113', 'Hamburg');
$coordinate_writer = new ReflectionMethod($admin, 'get_and_save_coordinates');
$unverified_write = $coordinate_writer->invoke($admin, $anchor_report_id, 'CI Anchorweg', '22113', 'Hamburg', '99');
remove_filter('pre_http_request', $wrong_house_fixture, 10);
if (false !== $unverified_anchor || false !== $unverified_write || '53.520000' !== (string) get_post_meta($anchor_report_id, '_feu_einsatz_latitude', true)) {
    feu_einsatz_ci_fail('An unverified house reused or overwrote the old road-centre coordinate.');
}
update_post_meta($anchor_report_id, '_feu_einsatz_hausnummer', '');
if (false !== FEU_Einsatz_Template_Helpers::get_report_incident_coordinates($anchor_report_id, 'CI Anchorweg', '22113', 'Hamburg')) {
    feu_einsatz_ci_fail('A radius without a house number reused old coordinates.');
}
update_post_meta($anchor_report_id, '_feu_einsatz_strasse', 'CI Anchorweg');
update_post_meta($anchor_report_id, '_feu_einsatz_plz', '22113');
update_post_meta($anchor_report_id, '_feu_einsatz_stadt', 'Hamburg');
set_transient('feu_einsatz_single_geometry_prime_v4_' . $anchor_report_id, 1, 600);
$unverified_public_context = FEU_Einsatz_Template_Helpers::get_single_context(get_post($anchor_report_id));
delete_transient('feu_einsatz_single_geometry_prime_v4_' . $anchor_report_id);
if (empty($unverified_public_context['map']['anchor_unverified']) || empty($unverified_public_context['map']['publicly_hidden']) || null !== ($unverified_public_context['map']['config']['latitude'] ?? null) || !empty($unverified_public_context['map']['fallback_image_url'])) {
    feu_einsatz_ci_fail('Public map exposed an unverified radius anchor or an old generated map.');
}
$map_image_generator = new ReflectionMethod($admin, 'generate_map_image');
$invalid_map_image = $map_image_generator->invoke($admin, $anchor_report_id, 'CI Anchorweg, 22113 Hamburg');
if (!is_wp_error($invalid_map_image) || 'feu_einsatz_unverified_map_anchor' !== $invalid_map_image->get_error_code()) {
    feu_einsatz_ci_fail('Image generation accepted an unverified radius anchor.');
}

WP_CLI::success('Plugin loaded; recovery, selective purge, audit logging and post-purge saving succeeded.');
