<?php
if (!defined('ABSPATH') || !defined('WP_CLI') || !WP_CLI) {
    exit(1);
}

wp_set_current_user(1);
$admin = Feuer_Einsatzberichte_Core::get_instance()->get_admin();
$post_id = wp_insert_post([
    'post_type' => FEU_Einsatz_Report_Post_Type::POST_TYPE,
    'post_status' => 'draft',
    'post_title' => 'CI Kartenbild Prüfung NEU',
], true);
if (is_wp_error($post_id)) {
    throw new RuntimeException($post_id->get_error_message());
}
$older_post_id = wp_insert_post([
    'post_type' => FEU_Einsatz_Report_Post_Type::POST_TYPE,
    'post_status' => 'draft',
    'post_title' => 'CI Kartenbild Prüfung ALT',
], true);
if (is_wp_error($older_post_id)) {
    wp_delete_post($post_id, true);
    throw new RuntimeException($older_post_id->get_error_message());
}

$uploads = wp_upload_dir();
$file = trailingslashit($uploads['basedir']) . 'feu-map-review-' . $post_id . '.png';
$url = trailingslashit($uploads['baseurl']) . 'feu-map-review-' . $post_id . '.png';
$has_asset = new ReflectionMethod(FEU_Einsatz_Admin::class, 'has_generated_map_asset');
$attachment_id = 0;

try {
    update_post_meta($post_id, FEU_Einsatz_Report_Post_Type::MARKER_META, '1');
    update_post_meta($post_id, '_feu_einsatz_datum', '2024-12-20');
    update_post_meta($older_post_id, FEU_Einsatz_Report_Post_Type::MARKER_META, '1');
    update_post_meta($older_post_id, '_feu_einsatz_datum', '2023-01-10');
    update_post_meta($post_id, FEU_Einsatz_Template_Helpers::MAP_LOCATION_MODE_META, 'coordinates');
    update_post_meta($post_id, '_feu_einsatz_latitude', '53.53');
    update_post_meta($post_id, '_feu_einsatz_longitude', '10.03');
    update_post_meta($post_id, '_feu_einsatz_generated_map_preview_file', $file);
    update_post_meta($post_id, '_feu_einsatz_generated_map_preview_url', $url);

    if ($has_asset->invoke($admin, $post_id) || FEU_Einsatz_Template_Helpers::get_generated_map_preview_public_url($post_id)) {
        throw new RuntimeException('A stale map URL was reported as an existing image.');
    }

    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9l8gAAAABJRU5ErkJggg==');
    if (!wp_mkdir_p($uploads['basedir']) || false === file_put_contents($file, $png)) {
        throw new RuntimeException('Could not write map fixture.');
    }
    if (!$has_asset->invoke($admin, $post_id) || '' === FEU_Einsatz_Template_Helpers::get_generated_map_preview_public_url($post_id)) {
        throw new RuntimeException('A readable generated map was not recognized.');
    }
    $card = FEU_Einsatz_Template_Helpers::get_card_image_data($post_id);
    if (empty($card['url']) || empty($card['is_map'])) {
        throw new RuntimeException('The report list could not use the existing map as its image.');
    }

    $_GET['report_search'] = 'CI Kartenbild Prüfung';
    ob_start();
    $admin->render_reports();
    $html = ob_get_clean();
    $new_position = strpos($html, 'CI Kartenbild Prüfung NEU');
    $old_position = strpos($html, 'CI Kartenbild Prüfung ALT');
    if (false === $new_position
        || false === $old_position
        || $new_position > $old_position
        || false === strpos($html, 'Kartenbild ansehen')
        || false === strpos($html, 'Neu generieren')
        || false === strpos($html, 'data-feu-map-dialog')
        || false === strpos($html, 'name="report_year"')
        || false === strpos($html, 'name="report_date_from"')) {
        throw new RuntimeException('Compact report list has no event-date sorting, date filters, map preview or regeneration action.');
    }
    $_GET['report_year'] = '2024';
    ob_start();
    $admin->render_reports();
    $year_html = ob_get_clean();
    if (false === strpos($year_html, 'CI Kartenbild Prüfung NEU') || false !== strpos($year_html, 'CI Kartenbild Prüfung ALT')) {
        throw new RuntimeException('Report year filter did not isolate the selected event year.');
    }
    unset($_GET['report_year']);
    update_post_meta($post_id, '_feu_einsatz_strasse', 'CI Musterstraße');
    $_GET['feu_einsatz_street'] = 'CI Musterstraße';
    ob_start();
    $admin->render_reports();
    $filtered_html = ob_get_clean();
    if (false === strpos($filtered_html, 'Adressfilter:') || false === strpos($filtered_html, 'CI Kartenbild Prüfung')) {
        throw new RuntimeException('Street registry links no longer filter the new report list.');
    }

    unlink($file);
    if ($has_asset->invoke($admin, $post_id)) {
        throw new RuntimeException('A removed map remained ready after a real file check.');
    }
    $attachment_id = wp_insert_attachment([
        'post_mime_type' => 'image/png',
        'post_title' => 'CI missing map attachment',
        'post_status' => 'inherit',
    ], $file, $post_id, true);
    if (is_wp_error($attachment_id)) {
        throw new RuntimeException($attachment_id->get_error_message());
    }
    update_post_meta($post_id, '_feu_einsatz_generated_map_thumbnail_id', $attachment_id);
    if ($has_asset->invoke($admin, $post_id) || FEU_Einsatz_Template_Helpers::get_generated_map_preview_public_url($post_id)) {
        throw new RuntimeException('A generated attachment record without its file was reported ready.');
    }

    $_GET['page'] = 'feu-einsatz-bericht-bearbeiten';
    if ('feuer-einsatzberichte' !== $admin->filter_admin_parent_file('edit.php')
        || 'feu-einsatz-berichte' !== $admin->filter_admin_submenu_file('')) {
        throw new RuntimeException('Report editor is no longer highlighted inside the plugin menu.');
    }
} finally {
    unset($_GET['report_search'], $_GET['report_year'], $_GET['report_date_from'], $_GET['report_date_to'], $_GET['report_order'], $_GET['feu_einsatz_street'], $_GET['page']);
    if (is_file($file)) {
        unlink($file);
    }
    if ($attachment_id > 0 && !is_wp_error($attachment_id)) {
        wp_delete_attachment($attachment_id, true);
    }
    wp_delete_post($older_post_id, true);
    wp_delete_post($post_id, true);
}

WP_CLI::success('Report map review detects missing files, renders previews and keeps plugin navigation active.');
