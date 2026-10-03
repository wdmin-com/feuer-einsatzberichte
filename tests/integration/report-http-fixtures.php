<?php
/** Published fixtures for a real HTTP rewrite/canonical smoke test in CI. */
if (!defined('ABSPATH') || !defined('WP_CLI') || !WP_CLI) {
    exit(1);
}

wp_set_current_user(1);
$root = FEU_Einsatz_Template_Helpers::find_root_category();
if (!($root instanceof WP_Term)) {
    $created = wp_insert_term('Einsätze', 'category', ['slug' => 'einsaetze']);
    if (is_wp_error($created)) {
        WP_CLI::error($created->get_error_message());
    }
    $root = get_term((int) $created['term_id'], 'category');
}

$created = wp_insert_term('ROUTE-CI', 'category', [
    'slug' => 'route-ci',
    'parent' => (int) $root->term_id,
]);
if (is_wp_error($created) && 'term_exists' !== $created->get_error_code()) {
    WP_CLI::error($created->get_error_message());
}
$category = get_term_by('slug', 'route-ci', 'category');
if (!($category instanceof WP_Term)) {
    WP_CLI::error('Route CI category is unavailable.');
}

$legacy_id = wp_insert_post([
    'post_type' => 'post',
    'post_status' => 'publish',
    'post_title' => 'HTTP legacy route CI',
    'post_name' => 'http-route-ci-legacy',
    'post_category' => [(int) $category->term_id],
], true);
$category_id = wp_insert_post([
    'post_type' => FEU_Einsatz_Report_Post_Type::POST_TYPE,
    'post_status' => 'publish',
    'post_title' => 'HTTP category route CI',
    'post_name' => 'http-route-ci-category',
    'post_category' => [(int) $category->term_id],
], true);
if (is_wp_error($legacy_id) || is_wp_error($category_id)) {
    WP_CLI::error('Could not create HTTP route fixtures.');
}

update_post_meta($legacy_id, FEU_Einsatz_Report_Post_Type::MARKER_META, '1');
update_post_meta($legacy_id, FEU_Einsatz_Report_Post_Type::URL_SCHEME_META, 'legacy');
update_post_meta($legacy_id, FEU_Einsatz_Report_Post_Type::LEGACY_PATH_META, 'einsaetze/http-route-ci-legacy/');
$legacy_url_before = get_permalink($legacy_id);
if (!set_post_type((int) $legacy_id, FEU_Einsatz_Report_Post_Type::POST_TYPE)
    || $legacy_url_before !== get_permalink($legacy_id)) {
    WP_CLI::error('The migrated HTTP fixture changed its legacy URL.');
}
update_post_meta($category_id, FEU_Einsatz_Report_Post_Type::MARKER_META, '1');
FEU_Einsatz_Report_Post_Type::set_category_permalink_meta((int) $category_id, $category);

if (
    get_permalink($legacy_id) !== home_url('/einsaetze/http-route-ci-legacy/')
    || get_permalink($category_id) !== home_url('/einsaetze/route-ci/http-route-ci-category/')
) {
    WP_CLI::error('HTTP fixture URLs do not match the permalink contract.');
}

WP_CLI::success('HTTP route fixtures created.');
