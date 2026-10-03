<?php
/** WP-CLI regression matrix for editing unmigrated and migrated reports. */
if (!defined('ABSPATH')) {
    exit(1);
}

function feu_report_edit_assert($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

wp_set_current_user(1);
$admin = Feuer_Einsatzberichte_Core::get_instance()->get_admin();
feu_report_edit_assert($admin instanceof FEU_Einsatz_Admin, 'Admin controller unavailable.');
$root = FEU_Einsatz_Template_Helpers::find_root_category();
if (!($root instanceof WP_Term)) {
    $created = wp_insert_term('Einsätze', 'category', ['slug' => 'einsaetze']);
    feu_report_edit_assert(!is_wp_error($created), 'Cannot create category root.');
    $root = get_term((int) $created['term_id'], 'category');
}

$suffix = strtolower(wp_generate_password(6, false, false));
$active_term = wp_insert_term('Edit active ' . $suffix, 'category', [
    'slug' => 'edit-active-' . $suffix,
    'parent' => (int) $root->term_id,
]);
$retired_term = wp_insert_term('Edit retired ' . $suffix, 'category', [
    'slug' => 'edit-retired-' . $suffix,
    'parent' => (int) $root->term_id,
]);
feu_report_edit_assert(!is_wp_error($active_term) && !is_wp_error($retired_term), 'Cannot create edit categories.');
$active_id = (int) $active_term['term_id'];
$retired_id = (int) $retired_term['term_id'];
$previous_categories = get_option('feu_einsatz_categories', null);
$previous_auto_map = get_option('feu_einsatz_auto_map_image', null);
$fixtures = [];
$participant_id = 0;
$attachment_id = 0;

try {
    update_option('feu_einsatz_categories', [$active_id], false);
    update_option('feu_einsatz_auto_map_image', 0, false);
    $participant_id = (int) Feuer_Einsatzberichte_Core::get_instance()->get_db()->save_participant(0, [
        'vorname' => 'Edit',
        'nachname' => 'Regression ' . $suffix,
    ]);
    feu_report_edit_assert($participant_id > 0, 'Cannot create participant fixture.');
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL7WQAAAABJRU5ErkJggg==', true);
    $upload = wp_upload_bits('report-edit-' . $suffix . '.png', null, $png);
    feu_report_edit_assert(empty($upload['error']) && !empty($upload['file']), 'Cannot create gallery image fixture.');
    $attachment_id = (int) wp_insert_attachment([
        'post_mime_type' => 'image/png',
        'post_title' => 'Report edit fixture ' . $suffix,
        'post_status' => 'inherit',
        'guid' => $upload['url'],
    ], $upload['file']);
    feu_report_edit_assert($attachment_id > 0, 'Cannot create gallery attachment.');
    $category_method = new ReflectionMethod(FEU_Einsatz_Admin::class, 'get_available_report_categories');
    $validate_method = new ReflectionMethod(FEU_Einsatz_Admin::class, 'validate_report_submission_request');

    $new_category_ids = array_map('intval', wp_list_pluck($category_method->invoke($admin), 'term_id'));
    feu_report_edit_assert(!in_array($retired_id, $new_category_ids, true), 'Retired category leaked into new reports.');

    foreach (['post', FEU_Einsatz_Report_Post_Type::POST_TYPE] as $type) {
        foreach (['draft', 'publish'] as $status) {
            foreach (['address', 'coordinates'] as $location) {
                $slug = 'edit-regression-' . $suffix . '-' . count($fixtures);
                $post_id = wp_insert_post([
                    'post_type' => $type,
                    'post_status' => $status,
                    'post_title' => 'Before edit',
                    'post_name' => $slug,
                    'post_category' => [$retired_id],
                ], true);
                feu_report_edit_assert(!is_wp_error($post_id) && $post_id > 0, 'Cannot create report fixture.');
                $post_id = (int) $post_id;
                $fixtures[] = $post_id;
                update_post_meta($post_id, FEU_Einsatz_Report_Post_Type::MARKER_META, '1');
                if ($type === FEU_Einsatz_Report_Post_Type::POST_TYPE) {
                    FEU_Einsatz_Report_Post_Type::set_category_permalink_meta($post_id, get_term($retired_id, 'category'));
                }
                foreach (['strasse' => 'Teststrasse', 'hausnummer' => '1', 'plz' => '22547', 'stadt' => 'Hamburg', 'datum' => '20.09.2026', 'uhrzeit' => '12:30'] as $field => $value) {
                    update_post_meta($post_id, '_feu_einsatz_' . $field, $value);
                }
                update_post_meta($post_id, '_feu_einsatz_latitude', '53.57');
                update_post_meta($post_id, '_feu_einsatz_longitude', '9.89');
                $before_url = get_permalink($post_id);
                $edit_category_ids = array_map('intval', wp_list_pluck($category_method->invoke($admin, $post_id), 'term_id'));
                feu_report_edit_assert(in_array($retired_id, $edit_category_ids, true), 'Existing category missing from edit form.');

                $_POST = [
                    'post_id' => (string) $post_id,
                    'feu_einsatz_update_report_nonce' => wp_create_nonce('feu_einsatz_update_report'),
                    'post_title' => 'After edit ' . $slug,
                    'post_content' => '<p>Edited body</p>',
                    'feu_einsatz_report_status' => $status,
                    'post_category' => [$retired_id],
                    'feu_einsatz_map_location_mode' => $location,
                    'feu_einsatz_map_highlight_override' => $location === 'coordinates' ? 'radius' : 'full',
                    'feu_einsatz_strasse' => $location === 'address' ? 'Teststrasse' : '',
                    'feu_einsatz_hausnummer' => $location === 'address' ? '1' : '',
                    'feu_einsatz_plz' => $location === 'address' ? '22547' : '',
                    'feu_einsatz_stadt' => $location === 'address' ? 'Hamburg' : '',
                    'feu_einsatz_latitude' => '53.57',
                    'feu_einsatz_longitude' => '9.89',
                    'feu_einsatz_datum' => '20.09.2026',
                    'feu_einsatz_uhrzeit' => '12:30',
                    'feu_einsatz_availability_mode' => 'sofort',
                    'feu_einsatz_gallery_ids' => (string) $attachment_id,
                    'feu_einsatz_teilnehmer' => [['id' => (string) $participant_id, 'funktion' => '']],
                    'feu_einsatz_organisationen' => [],
                ];
                $_REQUEST = $_POST;
                if ($type === 'post' && $status === 'publish' && $location === 'address') {
                    unset($_POST['feu_einsatz_report_status'], $_REQUEST['feu_einsatz_report_status']);
                }
                $validation = $validate_method->invoke($admin, $post_id);
                feu_report_edit_assert(empty($validation['errors']), 'Edit validation failed: ' . implode('; ', $validation['errors']));

                $stop_redirect = static function () {
                    throw new RuntimeException('redirect intercepted');
                };
                add_filter('wp_redirect', $stop_redirect);
                try {
                    $admin->handle_update_report();
                    throw new RuntimeException('Edit handler did not redirect.');
                } catch (RuntimeException $exception) {
                    feu_report_edit_assert('redirect intercepted' === $exception->getMessage(), 'Edit failed: ' . $exception->getMessage());
                } finally {
                    remove_filter('wp_redirect', $stop_redirect);
                }
                clean_post_cache($post_id);
                $edited = get_post($post_id);
                feu_report_edit_assert($edited instanceof WP_Post && $edited->post_status === $status, 'Post status changed unexpectedly.');
                feu_report_edit_assert($edited->post_title === 'After edit ' . $slug && false !== strpos($edited->post_content, 'Edited body'), 'Title or content was not saved.');
                feu_report_edit_assert(get_permalink($post_id) === $before_url, 'Report permalink changed after editing.');
                feu_report_edit_assert(in_array($retired_id, wp_get_post_categories($post_id), true), 'Retired category was lost after save.');
                feu_report_edit_assert(FEU_Einsatz_Template_Helpers::get_report_map_location_mode($post_id) === $location, 'Map location mode was not saved.');
                $assigned = get_post_meta($post_id, '_feu_einsatz_teilnehmer', true);
                $gallery = get_post_meta($post_id, '_feu_einsatz_gallery', true);
                feu_report_edit_assert(is_array($assigned) && (int) ($assigned[0]['id'] ?? 0) === $participant_id, 'Participant was lost after save.');
                feu_report_edit_assert(is_array($gallery) && in_array($attachment_id, array_map('intval', $gallery), true), 'Gallery image was lost after save.');

                wp_update_post(['ID' => $post_id, 'post_name' => $slug . '-changed']);
                feu_report_edit_assert(get_post_field('post_name', $post_id) === $slug, 'Standard WordPress editor changed report URL slug.');
            }
        }
    }
    $valid_gallery_attachment = new ReflectionMethod(FEU_Einsatz_Admin::class, 'is_valid_gallery_attachment');
    feu_report_edit_assert($valid_gallery_attachment->invoke($admin, $attachment_id), 'Valid gallery attachment was rejected.');
    wp_update_post(['ID' => $attachment_id, 'post_status' => 'trash']);
    feu_report_edit_assert(!$valid_gallery_attachment->invoke($admin, $attachment_id), 'Trashed gallery attachment was accepted.');
} finally {
    $_POST = [];
    $_REQUEST = [];
    foreach ($fixtures as $post_id) {
        wp_delete_post($post_id, true);
    }
    if ($attachment_id) {
        wp_delete_attachment($attachment_id, true);
    }
    if ($participant_id) {
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'feu_einsatz_statistiken', ['teilnehmer_id' => $participant_id], ['%d']);
        $wpdb->delete($wpdb->prefix . 'feu_einsatz_teilnehmer', ['id' => $participant_id], ['%d']);
    }
    wp_delete_term($active_id, 'category');
    wp_delete_term($retired_id, 'category');
    if (null === $previous_categories) {
        delete_option('feu_einsatz_categories');
    } else {
        update_option('feu_einsatz_categories', $previous_categories, false);
    }
    if (null === $previous_auto_map) {
        delete_option('feu_einsatz_auto_map_image');
    } else {
        update_option('feu_einsatz_auto_map_image', $previous_auto_map, false);
    }
}

WP_CLI::success('Eight old/new edit scenarios preserved URL, category, status, map mode, participant and photo.');
