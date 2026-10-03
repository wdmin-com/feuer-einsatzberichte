<?php
/** WP-CLI integration checks for the dual-read report transition. */
if (!defined('ABSPATH')) {
    exit(1);
}

function feu_einsatz_post_type_fail(string $message): void {
    fwrite(STDERR, "::error title=Report post type::{$message}\n");
    exit(1);
}

if (!post_type_exists(FEU_Einsatz_Report_Post_Type::POST_TYPE)) {
    feu_einsatz_post_type_fail('The report post type was not registered.');
}
wp_set_current_user(1);
$post_type_object = get_post_type_object(FEU_Einsatz_Report_Post_Type::POST_TYPE);
if (!($post_type_object instanceof WP_Post_Type) || current_user_can($post_type_object->cap->create_posts)) {
    feu_einsatz_post_type_fail('Direct WordPress CPT creation is not blocked.');
}

$root = FEU_Einsatz_Template_Helpers::find_root_category();
if (!($root instanceof WP_Term)) {
    $created = wp_insert_term('Einsätze', 'category', ['slug' => 'einsaetze']);
    if (is_wp_error($created)) {
        feu_einsatz_post_type_fail('Could not create the test root category.');
    }
    $root = get_term((int) $created['term_id'], 'category');
}
$created = wp_insert_term('MIGRATION-CI', 'category', [
    'slug' => 'migration-ci',
    'parent' => (int) $root->term_id,
]);
if (is_wp_error($created) && 'term_exists' !== $created->get_error_code()) {
    feu_einsatz_post_type_fail('Could not create the test child category.');
}
$category = get_term_by('slug', 'migration-ci', 'category');
if (!($category instanceof WP_Term)) {
    feu_einsatz_post_type_fail('Test child category is unavailable.');
}

$legacy_id = wp_insert_post([
    'post_type' => 'post',
    'post_status' => 'publish',
    'post_title' => 'Legacy migration CI',
    'post_name' => 'legacy-migration-ci',
    'post_category' => [(int) $category->term_id],
], true);
$new_id = wp_insert_post([
    'post_type' => FEU_Einsatz_Report_Post_Type::POST_TYPE,
    'post_status' => 'publish',
    'post_title' => 'New migration CI',
    'post_name' => 'new-migration-ci',
    'post_category' => [(int) $category->term_id],
], true);
if (is_wp_error($legacy_id) || is_wp_error($new_id)) {
    feu_einsatz_post_type_fail('Could not create test reports.');
}

update_post_meta($legacy_id, FEU_Einsatz_Report_Post_Type::MARKER_META, '1');
update_post_meta($new_id, FEU_Einsatz_Report_Post_Type::MARKER_META, '1');
FEU_Einsatz_Report_Post_Type::set_category_permalink_meta((int) $new_id, $category);

$legacy_url = home_url(user_trailingslashit('einsaetze/legacy-migration-ci'));
$new_url = home_url(user_trailingslashit('einsaetze/migration-ci/new-migration-ci'));
if (get_permalink($legacy_id) !== $legacy_url || get_permalink($new_id) !== $new_url) {
    feu_einsatz_post_type_fail('Legacy or category permalink changed unexpectedly.');
}

$both_reports = get_posts([
    'post_type' => FEU_Einsatz_Report_Post_Type::readable_post_types(),
    'post_status' => 'publish',
    'post__in' => [(int) $legacy_id, (int) $new_id],
    'posts_per_page' => 2,
    'fields' => 'ids',
    'meta_key' => FEU_Einsatz_Report_Post_Type::MARKER_META,
    'meta_value' => '1',
]);
if (2 !== count($both_reports)) {
    feu_einsatz_post_type_fail('The transition query did not return both report types.');
}

$previous_query = $GLOBALS['wp_query'] ?? null;
$GLOBALS['wp_query'] = new WP_Query([
    'post_type' => FEU_Einsatz_Report_Post_Type::POST_TYPE,
    'p' => (int) $new_id,
]);
$GLOBALS['wp_query']->set('feu_einsatz_category_slug', 'wrong-category');
Feuer_Einsatzberichte_Core::get_instance()->get_public()->validate_report_permalink();
if (!$GLOBALS['wp_query']->is_404()) {
    feu_einsatz_post_type_fail('A mismatched URL category was not rejected.');
}
$GLOBALS['wp_query'] = $previous_query;

$inventory = FEU_Einsatz_Post_Migration::preflight();
$inventory_ids = array_map(static function (array $row): int {
    return (int) $row['id'];
}, $inventory['records']);
if (!in_array((int) $legacy_id, $inventory_ids, true) || in_array((int) $new_id, $inventory_ids, true)) {
    feu_einsatz_post_type_fail('Preflight did not isolate marked legacy posts.');
}
if ('post' !== get_post_type($legacy_id) || FEU_Einsatz_Report_Post_Type::POST_TYPE !== get_post_type($new_id)) {
    feu_einsatz_post_type_fail('Read-only preflight changed a post type.');
}

update_post_meta($legacy_id, FEU_Einsatz_Report_Post_Type::LEGACY_PATH_META, 'einsaetze/legacy-migration-ci/');
update_post_meta($legacy_id, FEU_Einsatz_Report_Post_Type::URL_SCHEME_META, 'legacy');
if (!set_post_type((int) $legacy_id, FEU_Einsatz_Report_Post_Type::POST_TYPE)) {
    feu_einsatz_post_type_fail('The WordPress post-type conversion primitive failed.');
}
if (
    get_permalink($legacy_id) !== $legacy_url
    || !in_array((int) $category->term_id, wp_get_post_categories((int) $legacy_id), true)
    || '1' !== get_post_meta($legacy_id, FEU_Einsatz_Report_Post_Type::MARKER_META, true)
) {
    feu_einsatz_post_type_fail('The conversion changed the legacy URL, category or report marker.');
}

$previous_access = get_option(FEU_Einsatz_Admin::ROLE_ACCESS_OPTION, null);
update_option(FEU_Einsatz_Admin::ROLE_ACCESS_OPTION, [
    'reports' => ['administrator'],
    'create_report' => ['administrator'],
], false);
$login = 'report-ci-editor-' . wp_generate_password(8, false, false);
$editor_id = wp_insert_user([
    'user_login' => $login,
    'user_pass' => wp_generate_password(24, true, true),
    'user_email' => $login . '@example.test',
    'role' => 'editor',
]);
if (is_wp_error($editor_id)) {
    feu_einsatz_post_type_fail('Could not create a role-isolation fixture.');
}
wp_set_current_user((int) $editor_id);
if (current_user_can('edit_post', (int) $legacy_id) || current_user_can('delete_post', (int) $new_id)) {
    feu_einsatz_post_type_fail('A role without plugin access could modify reports through WordPress post capabilities.');
}
wp_set_current_user(1);
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user((int) $editor_id);
if (null === $previous_access) {
    delete_option(FEU_Einsatz_Admin::ROLE_ACCESS_OPTION);
} else {
    update_option(FEU_Einsatz_Admin::ROLE_ACCESS_OPTION, $previous_access, false);
}

wp_delete_post((int) $legacy_id, true);
wp_delete_post((int) $new_id, true);
WP_CLI::success('Report type, both permalink formats and read-only preflight verified.');
