<?php
if (!defined('ABSPATH') || !defined('WP_CLI') || !WP_CLI) {
    exit(1);
}

wp_set_current_user(1);
$post_id = wp_insert_post([
    'post_type' => FEU_Einsatz_Report_Post_Type::POST_TYPE,
    'post_status' => 'draft',
    'post_title' => 'CI statische Karte',
], true);
if (is_wp_error($post_id)) {
    throw new RuntimeException($post_id->get_error_message());
}

$uploads = wp_upload_dir();
$file = trailingslashit($uploads['basedir']) . 'feu-static-map-' . $post_id . '.png';
$url = trailingslashit($uploads['baseurl']) . 'feu-static-map-' . $post_id . '.png';

try {
    update_post_meta($post_id, FEU_Einsatz_Report_Post_Type::MARKER_META, '1');
    update_post_meta($post_id, '_feu_einsatz_generated_map_preview_file', $file);
    update_post_meta($post_id, '_feu_einsatz_generated_map_preview_url', $url);
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9l8gAAAABJRU5ErkJggg==');
    if (false === file_put_contents($file, $png)) {
        throw new RuntimeException('Could not write static map fixture.');
    }

    $fingerprint = new ReflectionMethod(FEU_Einsatz_Template_Helpers::class, 'get_single_context_cache_fingerprint');
    $first_fingerprint = $fingerprint->invoke(null, get_post($post_id));
    file_put_contents($file, $png . $png);
    clearstatcache(true, $file);
    $second_fingerprint = $fingerprint->invoke(null, get_post($post_id));
    if ($first_fingerprint === $second_fingerprint) {
        throw new RuntimeException('Regenerated map file did not invalidate the cached report context.');
    }

    $context = [
        'display' => [
            'single_map_display_mode' => 'live',
            'single_map_privacy_mode' => 'always',
        ],
        'map' => [
            'height' => 300,
            'canvas_id' => 'ci-static-map',
            'config' => ['highlight_mode' => 'radius', 'latitude' => 53.5, 'longitude' => 10.0],
            'has_live_data' => true,
            'fallback_image_url' => $url,
            'fallback_alt' => 'CI Karte',
            'preview_markup' => '<div id="ci-svg-fallback">SVG fallback</div>',
        ],
    ];
    ob_start();
    include dirname(__DIR__, 2) . '/templates/public/single/map.php';
    $html = ob_get_clean();
    if (false === strpos($html, $url)
        || false === strpos($html, 'data-feu-map-action="activate"')
        || false !== strpos($html, 'ci-svg-fallback')
        || false !== strpos($html, 'tile.openstreetmap.org')) {
        throw new RuntimeException('The report did not render the local map image before the interactive map.');
    }
} finally {
    if (is_file($file)) {
        unlink($file);
    }
    wp_delete_post($post_id, true);
}

WP_CLI::success('Single report prefers its local map image and refreshes cached context after regeneration.');
