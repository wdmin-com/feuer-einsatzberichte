<?php
if (!defined('ABSPATH')) {
    fwrite(STDERR, "WordPress was not bootstrapped.\n");
    exit(1);
}

function feu_category_admin_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "::error title=Legacy category admin integration test::{$message}\n");
        exit(1);
    }
}

$root = FEU_Einsatz_Template_Helpers::find_root_category();
feu_category_admin_assert($root instanceof WP_Term, 'The Einsatz root category is missing.');

$previous_enabled = get_option(FEU_Einsatz_Report_Taxonomy::ENABLED_OPTION, null);
$suffix = wp_generate_password(8, false, false);
$parent_id = 0;
$child_id = 0;
$outside_id = 0;

try {
    update_option(FEU_Einsatz_Report_Taxonomy::ENABLED_OPTION, 1, false);
    $parent = wp_insert_term('Admin exclusion parent ' . $suffix, 'category', [
        'slug' => 'admin-exclusion-parent-' . strtolower($suffix),
        'parent' => (int) $root->term_id,
    ]);
    feu_category_admin_assert(!is_wp_error($parent), 'Could not create a child category under the Einsatz root.');
    $parent_id = (int) $parent['term_id'];

    $child = wp_insert_term('Admin exclusion child ' . $suffix, 'category', [
        'slug' => 'admin-exclusion-child-' . strtolower($suffix),
        'parent' => $parent_id,
    ]);
    feu_category_admin_assert(!is_wp_error($child), 'Could not create a nested Einsatz category.');
    $child_id = (int) $child['term_id'];

    $outside = wp_insert_term('Admin exclusion outside ' . $suffix, 'category', [
        'slug' => 'admin-exclusion-outside-' . strtolower($suffix),
    ]);
    feu_category_admin_assert(!is_wp_error($outside), 'Could not create an ordinary blog category.');
    $outside_id = (int) $outside['term_id'];

    set_current_screen('edit-category');
    get_current_screen()->post_type = 'post';

    $terms = get_terms([
        'taxonomy' => 'category',
        'hide_empty' => false,
    ]);
    feu_category_admin_assert(!is_wp_error($terms), 'The blog category list query failed.');
    $visible_ids = array_map(static function ($term): int {
        return (int) $term->term_id;
    }, $terms);
    feu_category_admin_assert(
        !in_array((int) $root->term_id, $visible_ids, true)
            && !in_array($parent_id, $visible_ids, true)
            && !in_array($child_id, $visible_ids, true)
            && in_array($outside_id, $visible_ids, true),
        'The blog category list did not exclude only the complete Einsatz branch.'
    );

    $edited_term = get_term($child_id, 'category');
    feu_category_admin_assert($edited_term instanceof WP_Term, 'The hidden category could not be loaded for editing.');
    $first_description = 'Admin edit first ' . $suffix;
    $first_save = wp_update_term($child_id, 'category', ['description' => $first_description]);
    feu_category_admin_assert(!is_wp_error($first_save), 'The first category description save failed.');
    $second_description = 'Admin edit saved again ' . $suffix;
    $second_save = wp_update_term($child_id, 'category', ['description' => $second_description]);
    feu_category_admin_assert(!is_wp_error($second_save), 'Saving the category description a second time failed.');
    $saved_term = get_term($child_id, 'category');
    feu_category_admin_assert(
        $saved_term instanceof WP_Term && $second_description === $saved_term->description,
        'The category description was not preserved after the second save.'
    );
} finally {
    set_current_screen('dashboard');
    foreach ([$child_id, $parent_id, $outside_id] as $term_id) {
        if ($term_id > 0) {
            wp_delete_term($term_id, 'category');
        }
    }
    if (null === $previous_enabled) {
        delete_option(FEU_Einsatz_Report_Taxonomy::ENABLED_OPTION);
    } else {
        update_option(FEU_Einsatz_Report_Taxonomy::ENABLED_OPTION, $previous_enabled, false);
    }
}

echo "Legacy Einsatz branch exclusion and repeated category description saves passed.\n";
