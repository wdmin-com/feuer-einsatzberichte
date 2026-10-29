<?php
/** Independent keyword migration and one-report/one-statistic regression. */
if (!defined('ABSPATH')) {
    exit(1);
}

function feu_keyword_assert($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

wp_set_current_user(1);
feu_keyword_assert(!FEU_Einsatz_Report_Taxonomy::enabled(), 'This fixture requires legacy mode.');
$root = FEU_Einsatz_Template_Helpers::find_root_category();
feu_keyword_assert($root instanceof WP_Term, 'Legacy root missing.');
$suffix = strtolower(wp_generate_password(8, false, false));
$old_selected = get_option('feu_einsatz_categories', null);
$old_new_selected = get_option(FEU_Einsatz_Report_Taxonomy::SELECTED_OPTION, null);
$old_run = get_option('feu_einsatz_keyword_migration_run', null);
$post_ids = [];
$term_ids = [];
$new_term_ids = [];
$collision_term_id = 0;
$temporary_post_id = 0;
$editable_draft_id = 0;

try {
    foreach (['A', 'B'] as $label) {
        $result = wp_insert_term('Keyword CI ' . $label . ' ' . $suffix, 'category', [
            'slug' => 'keyword-ci-' . strtolower($label) . '-' . $suffix,
            'parent' => (int) $root->term_id,
        ]);
        feu_keyword_assert(!is_wp_error($result), 'Cannot create legacy keyword.');
        $term_ids[] = (int) $result['term_id'];
    }
    update_option('feu_einsatz_categories', $term_ids, false);
    $year = 2026;
    $before = array_sum(array_map(static function ($row): int {
        return (int) $row->anzahl;
    }, Feuer_Einsatzberichte_Core::get_instance()->get_db()->get_category_statistics($year)));

    for ($index = 0; $index < 2; $index++) {
        $post_id = wp_insert_post([
            'post_type' => $index ? 'post' : FEU_Einsatz_Report_Post_Type::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => 'Keyword CI report ' . $suffix . ' ' . $index,
            'post_name' => 'keyword-ci-report-' . $suffix . '-' . $index,
            'post_category' => $term_ids,
        ], true);
        feu_keyword_assert(!is_wp_error($post_id), 'Cannot create report.');
        $post_ids[] = (int) $post_id;
        update_post_meta($post_id, FEU_Einsatz_Report_Post_Type::MARKER_META, '1');
        update_post_meta($post_id, '_feu_einsatz_datum', '2026-09-27');
        if (!$index) {
            FEU_Einsatz_Report_Post_Type::set_category_permalink_meta((int) $post_id, get_term($term_ids[0], 'category'));
        }
    }
    $urls = array_map('get_permalink', $post_ids);
    $after = array_sum(array_map(static function ($row): int {
        return (int) $row->anzahl;
    }, Feuer_Einsatzberichte_Core::get_instance()->get_db()->get_category_statistics($year)));
    feu_keyword_assert($after === $before + 2, 'Two multi-keyword reports were double-counted.');
    feu_keyword_assert($after === (int) Feuer_Einsatzberichte_Core::get_instance()->get_db()->get_total_statistics($year)['total_einsaetze'], 'Legacy donut total differs from report total.');

    $legacy_first = get_term($term_ids[0], 'category');
    $collision = wp_insert_term('Unrelated keyword ' . $suffix, FEU_Einsatz_Report_Taxonomy::TAXONOMY, [
        'slug' => $legacy_first->slug,
    ]);
    feu_keyword_assert(!is_wp_error($collision), 'Cannot create keyword collision fixture.');
    $collision_term_id = (int) $collision['term_id'];
    $collision_preflight = FEU_Einsatz_Keyword_Migration::preflight();
    feu_keyword_assert(!empty($collision_preflight['errors']), 'Unowned keyword collision passed preflight.');
    feu_keyword_assert(is_wp_error(FEU_Einsatz_Keyword_Migration::migrate(false)), 'Unowned keyword collision started migration.');
    update_term_meta($collision_term_id, FEU_Einsatz_Report_Taxonomy::LEGACY_TERM_META, $term_ids[0]);
    $parent_preflight = FEU_Einsatz_Keyword_Migration::preflight();
    feu_keyword_assert(!empty($parent_preflight['errors']), 'Wrong keyword parent passed preflight.');
    feu_keyword_assert(is_wp_error(FEU_Einsatz_Keyword_Migration::migrate(false)), 'Wrong keyword parent started migration.');
    wp_delete_term($collision_term_id, FEU_Einsatz_Report_Taxonomy::TAXONOMY);
    $collision_term_id = 0;

    $preflight = FEU_Einsatz_Keyword_Migration::preflight();
    feu_keyword_assert(!$preflight['errors'], 'Keyword preflight failed: ' . implode('; ', $preflight['errors']));
    $migrated = FEU_Einsatz_Keyword_Migration::migrate(false);
    feu_keyword_assert(!is_wp_error($migrated), 'Keyword migration failed: ' . (is_wp_error($migrated) ? $migrated->get_error_message() : ''));
    feu_keyword_assert(FEU_Einsatz_Report_Taxonomy::enabled(), 'Cutover flag missing.');
    $new_term_ids = FEU_Einsatz_Report_Taxonomy::get_selected_ids();
    feu_keyword_assert(2 === count($new_term_ids), 'Selected keyword mapping is incomplete.');
    $admin = Feuer_Einsatzberichte_Core::get_instance()->get_admin();
    if ($admin instanceof FEU_Einsatz_Admin) {
        $available_method = new ReflectionMethod($admin, 'get_available_report_categories');
        $available_ids = array_map('intval', wp_list_pluck($available_method->invoke($admin), 'term_id'));
        feu_keyword_assert(!array_diff($new_term_ids, $available_ids), 'Report editor did not switch to keywords.');
        $edit_ids = array_map('intval', wp_list_pluck($available_method->invoke($admin, $post_ids[0]), 'term_id'));
        feu_keyword_assert(!array_diff($new_term_ids, $edit_ids), 'Existing report editor lost its keywords.');
    }
    $quick = new FEU_Einsatz_Schnelleingabe(Feuer_Einsatzberichte_Core::get_instance()->get_db());
    $quick_method = new ReflectionMethod($quick, 'get_available_categories');
    $quick_ids = array_map('intval', wp_list_pluck($quick_method->invoke($quick), 'term_id'));
    feu_keyword_assert(!array_diff($new_term_ids, $quick_ids), 'Quick entry did not switch to keywords.');
    foreach ($post_ids as $index => $post_id) {
        feu_keyword_assert(get_permalink($post_id) === $urls[$index], 'Report URL changed.');
        $assigned = wp_get_object_terms($post_id, FEU_Einsatz_Report_Taxonomy::TAXONOMY, ['fields' => 'ids']);
        feu_keyword_assert(!is_wp_error($assigned) && 2 === count($assigned), 'Report lost one of its keywords.');
        $primary = FEU_Einsatz_Template_Helpers::get_deepest_category($post_id);
        feu_keyword_assert($primary instanceof WP_Term && FEU_Einsatz_Report_Taxonomy::TAXONOMY === $primary->taxonomy, 'Public report still reads blog categories.');
    }
    $rejected = FEU_Einsatz_Report_Taxonomy::set_report_terms($post_ids[0], [$new_term_ids[1]]);
    feu_keyword_assert(is_wp_error($rejected), 'Fixed URL keyword could be removed.');
    $editable_draft = wp_insert_post([
        'post_type' => FEU_Einsatz_Report_Post_Type::POST_TYPE,
        'post_status' => 'draft',
        'post_title' => 'Editable keyword draft ' . $suffix,
    ], true);
    feu_keyword_assert(!is_wp_error($editable_draft), 'Cannot create editable keyword draft.');
    $editable_draft_id = (int) $editable_draft;
    feu_keyword_assert('' === (string) get_post_field('post_name', $editable_draft_id), 'Editable draft already has a fixed slug.');
    update_post_meta($editable_draft_id, FEU_Einsatz_Report_Post_Type::MARKER_META, '1');
    $initial_primary = FEU_Einsatz_Report_Post_Type::resolve_primary_category([$new_term_ids[0]]);
    feu_keyword_assert($initial_primary instanceof WP_Term, 'Initial keyword is unavailable.');
    FEU_Einsatz_Report_Post_Type::set_category_permalink_meta($editable_draft_id, $initial_primary);
    $initial_assignment = FEU_Einsatz_Report_Taxonomy::set_report_terms($editable_draft_id, [$new_term_ids[0]]);
    feu_keyword_assert(!is_wp_error($initial_assignment), 'Initial draft keyword assignment failed.');
    $_POST = [
        'post_id' => (string) $editable_draft_id,
        'feu_einsatz_update_report_nonce' => wp_create_nonce('feu_einsatz_update_report'),
        'post_title' => 'Editable keyword draft ' . $suffix,
        'post_content' => '<p>New keyword</p>',
        'feu_einsatz_report_status' => 'draft',
        'post_category' => [$new_term_ids[1]],
        'feu_einsatz_primary_category' => (string) $new_term_ids[1],
        'feu_einsatz_map_location_mode' => 'coordinates',
        'feu_einsatz_latitude' => '53.57',
        'feu_einsatz_longitude' => '9.89',
        'feu_einsatz_datum' => '03.10.2026',
        'feu_einsatz_uhrzeit' => '10:15',
        'feu_einsatz_availability_mode' => 'sofort',
        'feu_einsatz_teilnehmer' => [],
        'feu_einsatz_organisationen' => [],
    ];
    $_REQUEST = $_POST;
    $stop_redirect = static function () {
        throw new RuntimeException('redirect intercepted');
    };
    add_filter('wp_redirect', $stop_redirect);
    try {
        $admin->handle_update_report();
        throw new RuntimeException('Draft keyword update did not redirect.');
    } catch (RuntimeException $exception) {
        feu_keyword_assert('redirect intercepted' === $exception->getMessage(), 'Draft keyword update failed: ' . $exception->getMessage());
    } finally {
        remove_filter('wp_redirect', $stop_redirect);
    }
    feu_keyword_assert($new_term_ids[1] === (int) get_post_meta($editable_draft_id, FEU_Einsatz_Report_Taxonomy::PRIMARY_META, true), 'Draft primary keyword did not change.');
    feu_keyword_assert('' === (string) get_post_field('post_name', $editable_draft_id), 'Draft keyword update fixed the slug early.');
    $_POST['feu_einsatz_report_status'] = 'publish';
    $_POST['feu_einsatz_datum'] = current_time('d.m.Y');
    $_REQUEST = $_POST;
    add_filter('wp_redirect', $stop_redirect);
    try {
        $admin->handle_update_report();
        throw new RuntimeException('First publication did not redirect.');
    } catch (RuntimeException $exception) {
        feu_keyword_assert('redirect intercepted' === $exception->getMessage(), 'First publication failed: ' . $exception->getMessage());
    } finally {
        remove_filter('wp_redirect', $stop_redirect);
    }
    feu_keyword_assert('publish' === get_post_status($editable_draft_id), 'Edited draft was not published.');
    feu_keyword_assert(str_contains(get_permalink($editable_draft_id), '/einsaetze/' . get_term($new_term_ids[1], FEU_Einsatz_Report_Taxonomy::TAXONOMY)->slug . '/'), 'First publication used the old keyword URL.');
    $fixed_assignment = FEU_Einsatz_Report_Taxonomy::set_report_terms($editable_draft_id, [$new_term_ids[0]]);
    feu_keyword_assert(is_wp_error($fixed_assignment), 'Published report keyword could be removed.');
    wp_delete_post($editable_draft_id, true);
    $editable_draft_id = 0;
    Feuer_Einsatzberichte_Core::get_instance()->get_db()->invalidate_statistics_dashboard_cache();
    $new_post = wp_insert_post([
        'post_type' => FEU_Einsatz_Report_Post_Type::POST_TYPE,
        'post_status' => 'draft',
        'post_title' => 'Keyword native CI ' . $suffix,
        'post_name' => 'keyword-native-ci-' . $suffix,
    ], true);
    feu_keyword_assert(!is_wp_error($new_post), 'Cannot create new-mode report.');
    $temporary_post_id = (int) $new_post;
    update_post_meta($new_post, FEU_Einsatz_Report_Post_Type::MARKER_META, '1');
    $new_primary = FEU_Einsatz_Report_Post_Type::resolve_primary_category([$new_term_ids[0]]);
    feu_keyword_assert($new_primary instanceof WP_Term, 'New-mode primary keyword unavailable.');
    FEU_Einsatz_Report_Post_Type::set_category_permalink_meta((int) $new_post, $new_primary);
    $new_assignment = FEU_Einsatz_Report_Taxonomy::set_report_terms((int) $new_post, [$new_term_ids[0]]);
    feu_keyword_assert(!is_wp_error($new_assignment), 'New-mode term assignment failed.');
    feu_keyword_assert(str_contains(get_permalink($new_post), '/einsaetze/' . $new_primary->slug . '/'), 'New-mode report URL is wrong.');
    wp_delete_post((int) $new_post, true);
    $temporary_post_id = 0;
    $canonical_count = array_sum(array_map(static function ($row): int {
        return (int) $row->anzahl;
    }, Feuer_Einsatzberichte_Core::get_instance()->get_db()->get_category_statistics($year)));
    feu_keyword_assert($canonical_count === $after, 'Keyword cutover changed total statistic count.');
    feu_keyword_assert($canonical_count === (int) Feuer_Einsatzberichte_Core::get_instance()->get_db()->get_total_statistics($year)['total_einsaetze'], 'Keyword donut total differs from report total.');

    $backup = new ReflectionMethod(FEU_Einsatz_Backup_Manager::class, 'build_backup_payload');
    $manifest = $backup->invoke(Feuer_Einsatzberichte_Core::get_instance()->get_backup());
    feu_keyword_assert(!empty($manifest['keyword_terms']), 'Backup omitted keyword term definitions.');
    $saved_report = null;
    foreach ($manifest['reports'] as $record) {
        if ((int) $record['id'] === $post_ids[0]) {
            $saved_report = $record;
            break;
        }
    }
    feu_keyword_assert(is_array($saved_report) && 2 === count($saved_report['stichworte'] ?? []), 'Backup omitted report keyword relationships.');
    $restore_terms = new ReflectionMethod(FEU_Einsatz_Backup_Manager::class, 'restore_keyword_terms');
    $identity_map = [];
    foreach ($manifest['terms'] as $category_record) {
        $identity_map[(int) $category_record['term_id']] = (int) $category_record['term_id'];
    }
    $restored_map = $restore_terms->invoke(Feuer_Einsatzberichte_Core::get_instance()->get_backup(), $manifest, $identity_map);
    feu_keyword_assert(isset($restored_map[$new_term_ids[0]]) && (int) $restored_map[$new_term_ids[0]] === $new_term_ids[0], 'Backup keyword term mapping failed.');

    $rolled_back = FEU_Einsatz_Keyword_Migration::rollback();
    feu_keyword_assert(!is_wp_error($rolled_back), 'Safe rollback failed.');
    feu_keyword_assert(!FEU_Einsatz_Report_Taxonomy::enabled(), 'Rollback left cutover flag active.');
    foreach ($post_ids as $index => $post_id) {
        feu_keyword_assert(get_permalink($post_id) === $urls[$index], 'Rollback changed report URL.');
    }
} finally {
    $_POST = [];
    $_REQUEST = [];
    update_option(FEU_Einsatz_Report_Taxonomy::ENABLED_OPTION, 0, false);
    if ($editable_draft_id) {
        wp_delete_post($editable_draft_id, true);
    }
    if ($temporary_post_id) {
        wp_delete_post($temporary_post_id, true);
    }
    if ($collision_term_id) {
        wp_delete_term($collision_term_id, FEU_Einsatz_Report_Taxonomy::TAXONOMY);
    }
    foreach ($post_ids as $post_id) {
        wp_delete_post($post_id, true);
    }
    foreach ($new_term_ids as $term_id) {
        wp_delete_term($term_id, FEU_Einsatz_Report_Taxonomy::TAXONOMY);
    }
    foreach ($term_ids as $term_id) {
        wp_delete_term($term_id, 'category');
    }
    foreach (['feu_einsatz_categories' => $old_selected, FEU_Einsatz_Report_Taxonomy::SELECTED_OPTION => $old_new_selected, 'feu_einsatz_keyword_migration_run' => $old_run] as $name => $old_value) {
        if (null === $old_value) {
            delete_option($name);
        } else {
            update_option($name, $old_value, false);
        }
    }
    Feuer_Einsatzberichte_Core::get_instance()->get_db()->invalidate_statistics_dashboard_cache();
}

echo "Report keywords migration, URLs, statistics and backup payload verified.\n";
