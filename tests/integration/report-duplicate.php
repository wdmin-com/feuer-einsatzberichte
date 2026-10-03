<?php
/** A copied report is a separate draft with editor data and no generated publication state. */
if (!defined('ABSPATH')) {
    exit(1);
}

wp_set_current_user(1);
$admin = Feuer_Einsatzberichte_Core::get_instance()->get_admin();
$available = new ReflectionMethod($admin, 'get_available_report_categories');
$categories = $available->invoke($admin);
$primary = null;
foreach ($categories as $category) {
    $candidate = FEU_Einsatz_Report_Post_Type::resolve_primary_category([(int) $category->term_id]);
    if ($candidate instanceof WP_Term) {
        $primary = $candidate;
        break;
    }
}
if (!($primary instanceof WP_Term)) {
    throw new RuntimeException('No valid keyword for duplicate fixture.');
}

$source_id = 0;
$duplicate_id = 0;
$alternate_id = 0;
$previous_selected = get_option('feu_einsatz_categories', null);
$previous_auto_map = get_option('feu_einsatz_auto_map_image', null);
try {
    $root = FEU_Einsatz_Template_Helpers::find_root_category();
    if (!($root instanceof WP_Term)) {
        throw new RuntimeException('No report category root.');
    }
    $alternate = wp_insert_term('Copy alternate ' . strtolower(wp_generate_password(8, false, false)), 'category', [
        'parent' => (int) $root->term_id,
    ]);
    if (is_wp_error($alternate)) {
        throw new RuntimeException($alternate->get_error_message());
    }
    $alternate_id = (int) $alternate['term_id'];
    update_option('feu_einsatz_categories', [(int) $primary->term_id, $alternate_id], false);
    update_option('feu_einsatz_auto_map_image', 0, false);

    $source_id = wp_insert_post([
        'post_type' => FEU_Einsatz_Report_Post_Type::POST_TYPE,
        'post_status' => 'publish',
        'post_title' => 'Duplicate fixture ' . wp_generate_password(6, false, false),
        'post_content' => '<p>Copy this body</p>',
    ], true);
    if (is_wp_error($source_id)) {
        throw new RuntimeException($source_id->get_error_message());
    }
    $source_id = (int) $source_id;
    update_post_meta($source_id, FEU_Einsatz_Report_Post_Type::MARKER_META, '1');
    if (FEU_Einsatz_Report_Taxonomy::enabled()) {
        $assigned = FEU_Einsatz_Report_Taxonomy::set_report_terms($source_id, [(int) $primary->term_id]);
    } else {
        $assigned = wp_set_post_categories($source_id, [(int) $primary->term_id], false);
    }
    if (is_wp_error($assigned)) {
        throw new RuntimeException($assigned->get_error_message());
    }
    FEU_Einsatz_Report_Post_Type::set_category_permalink_meta($source_id, $primary);
    update_post_meta($source_id, '_feu_einsatz_strasse', 'Musterstraße');
    update_post_meta($source_id, '_feu_einsatz_plz', '22525');
    update_post_meta($source_id, '_feu_einsatz_stadt', 'Hamburg');
    update_post_meta($source_id, '_feu_einsatz_datum', '2026-10-03');
    update_post_meta($source_id, '_feu_einsatz_uhrzeit', '10:15');
    update_post_meta($source_id, '_feu_einsatz_availability_mode', 'date');
    update_post_meta($source_id, '_feu_einsatz_generated_map_signature', 'source-only');

    $copy = new ReflectionMethod($admin, 'duplicate_report_to_draft');
    $duplicate_id = $copy->invoke($admin, $source_id);
    if (is_wp_error($duplicate_id)) {
        throw new RuntimeException($duplicate_id->get_error_message());
    }
    $duplicate_id = (int) $duplicate_id;
    $duplicate = get_post($duplicate_id);
    if (!($duplicate instanceof WP_Post) || 'draft' !== $duplicate->post_status || $duplicate->ID === $source_id) {
        throw new RuntimeException('Duplicate is not a separate draft.');
    }
    if ('' !== (string) $duplicate->post_name) {
        throw new RuntimeException('Duplicate reserved a public URL before publication.');
    }
    if (!str_starts_with($duplicate->post_title, 'Kopie: ') || $duplicate->post_content !== '<p>Copy this body</p>') {
        throw new RuntimeException('Duplicate lost report text.');
    }
    if ('Musterstraße' !== get_post_meta($duplicate_id, '_feu_einsatz_strasse', true)
        || 'sofort' !== get_post_meta($duplicate_id, '_feu_einsatz_availability_mode', true)
        || metadata_exists('post', $duplicate_id, '_feu_einsatz_generated_map_signature')) {
        throw new RuntimeException('Duplicate copied the wrong editor or generated state.');
    }
    $copied_ids = FEU_Einsatz_Report_Taxonomy::get_report_term_ids($duplicate_id);
    if (!in_array((int) $primary->term_id, $copied_ids, true)) {
        throw new RuntimeException('Duplicate lost its keyword.');
    }
    $_POST = [
        'post_id' => (string) $duplicate_id,
        'feu_einsatz_update_report_nonce' => wp_create_nonce('feu_einsatz_update_report'),
        'post_title' => 'Edited duplicate ' . wp_generate_password(6, false, false),
        'post_content' => '<p>New incident</p>',
        'feu_einsatz_report_status' => 'draft',
        'post_category' => [$alternate_id],
        'feu_einsatz_primary_category' => (string) $alternate_id,
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
        throw new RuntimeException('Draft update did not redirect.');
    } catch (RuntimeException $exception) {
        if ('redirect intercepted' !== $exception->getMessage()) {
            throw $exception;
        }
    } finally {
        remove_filter('wp_redirect', $stop_redirect);
    }
    if ($alternate_id !== (int) get_post_meta($duplicate_id, FEU_Einsatz_Report_Post_Type::PRIMARY_CATEGORY_META, true)
        || !in_array($alternate_id, FEU_Einsatz_Report_Taxonomy::get_report_term_ids($duplicate_id), true)) {
        throw new RuntimeException('Copied draft did not accept its new primary keyword.');
    }
    if ('' !== (string) get_post_field('post_name', $duplicate_id)) {
        throw new RuntimeException('Saving the copied draft fixed its URL early.');
    }
    $review_url = new ReflectionMethod($admin, 'get_report_url_review_payload');
    $source_review = $review_url->invoke($admin, get_the_title($source_id), '', [(int) $primary->term_id], (int) $primary->term_id, 0);
    if ('conflict' !== $source_review['status']) {
        throw new RuntimeException('Prepublication review missed an existing report URL.');
    }
    $ambiguous_review = $review_url->invoke($admin, 'Two keywords', '', [(int) $primary->term_id, $alternate_id], 0, 0);
    if ('missing_category' !== $ambiguous_review['status']) {
        throw new RuntimeException('Prepublication review accepted an ambiguous primary keyword.');
    }
    $fixed_review = $review_url->invoke($admin, 'Changed title', '', [(int) $primary->term_id], (int) $primary->term_id, $source_id);
    if ('fixed' !== $fixed_review['status'] || get_permalink($source_id) !== $fixed_review['url']) {
        throw new RuntimeException('Prepublication review changed an existing public URL.');
    }
    $draft_review = $review_url->invoke($admin, get_the_title($duplicate_id), '', [$alternate_id], $alternate_id, $duplicate_id);
    if ('available' !== $draft_review['status'] || !str_contains($draft_review['url'], '/einsaetze/' . get_term($alternate_id, 'category')->slug . '/')) {
        throw new RuntimeException('Prepublication review did not use the draft primary keyword.');
    }
    $fallback_review = $review_url->invoke($admin, '', 'Teststraße 7', [$alternate_id], $alternate_id, 0);
    $fallback_title = new ReflectionMethod($admin, 'build_default_report_title_from_request');
    $expected_slug = sanitize_title($fallback_title->invoke($admin, [$alternate_id], 'Teststraße 7'));
    if ('available' !== $fallback_review['status'] || !str_ends_with($fallback_review['url'], '/' . $expected_slug . '/')) {
        throw new RuntimeException('Prepublication review did not derive the empty-title URL.');
    }
    wp_set_current_user(0);
    if (!is_wp_error($copy->invoke($admin, $source_id))) {
        throw new RuntimeException('Anonymous user duplicated a report.');
    }
} finally {
    $_POST = [];
    $_REQUEST = [];
    wp_set_current_user(1);
    if ($duplicate_id && !is_wp_error($duplicate_id)) {
        wp_delete_post((int) $duplicate_id, true);
    }
    if ($source_id && !is_wp_error($source_id)) {
        wp_delete_post((int) $source_id, true);
    }
    if ($alternate_id) {
        wp_delete_term($alternate_id, 'category');
    }
    if (null === $previous_selected) {
        delete_option('feu_einsatz_categories');
    } else {
        update_option('feu_einsatz_categories', $previous_selected, false);
    }
    if (null === $previous_auto_map) {
        delete_option('feu_einsatz_auto_map_image');
    } else {
        update_option('feu_einsatz_auto_map_image', $previous_auto_map, false);
    }
}
echo "Report duplication preserved editor data, permissions and an editable draft keyword.\n";
