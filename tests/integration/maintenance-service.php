<?php
if (!defined('ABSPATH') || !defined('WP_CLI') || !WP_CLI) {
    exit(1);
}

wp_set_current_user(1);
$service = Feuer_Einsatzberichte_Core::get_instance()->get_maintenance_service();
if (!$service instanceof FEU_Einsatz_Maintenance_Service) {
    throw new RuntimeException('Maintenance service was not initialized.');
}
$service->ensure_schedule();
if (!wp_next_scheduled(FEU_Einsatz_Maintenance_Service::IMAGE_AUDIT_HOOK)) {
    throw new RuntimeException('Daily image integrity audit was not scheduled.');
}

$post_id = wp_insert_post([
    'post_type' => FEU_Einsatz_Report_Post_Type::POST_TYPE,
    'post_status' => 'draft',
    'post_title' => 'CI Cache-Warmup',
], true);
if (is_wp_error($post_id)) {
    throw new RuntimeException($post_id->get_error_message());
}
$uploads = wp_upload_dir();
$map_file = trailingslashit($uploads['basedir']) . 'feu-maintenance-attachment-map-' . $post_id . '.png';
$attachment_id = 0;

try {
    update_post_meta($post_id, FEU_Einsatz_Report_Post_Type::MARKER_META, '1');
    update_post_meta($post_id, FEU_Einsatz_Template_Helpers::MAP_LOCATION_MODE_META, 'coordinates');
    update_post_meta($post_id, '_feu_einsatz_latitude', '53.53');
    update_post_meta($post_id, '_feu_einsatz_longitude', '10.03');
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9l8gAAAABJRU5ErkJggg==');
    if (!wp_mkdir_p($uploads['basedir']) || false === file_put_contents($map_file, $png)) {
        throw new RuntimeException('Could not write the attachment-only map fixture.');
    }
    $attachment_id = wp_insert_attachment([
        'post_mime_type' => 'image/png',
        'post_title' => 'CI attachment-only generated map',
        'post_status' => 'inherit',
    ], $map_file, $post_id, true);
    if (is_wp_error($attachment_id)) {
        throw new RuntimeException($attachment_id->get_error_message());
    }
    update_post_meta($post_id, '_feu_einsatz_generated_map_thumbnail_id', $attachment_id);
    wp_update_post(['ID' => $post_id, 'post_status' => 'publish']);
    if (!wp_next_scheduled(FEU_Einsatz_Maintenance_Service::CACHE_WARM_HOOK, [$post_id])) {
        throw new RuntimeException('Published report did not queue cache warm-up.');
    }
    $job = get_post_meta($post_id, '_feu_einsatz_cache_warm_job', true);
    if ('queued' !== ($job['status'] ?? '')) {
        throw new RuntimeException('Cache warm-up status is not visible.');
    }
    $state = $service->run_image_integrity_audit();
    if (2 !== (int) ($state['schema_version'] ?? 0) || empty($state['checked_at']) || !isset($state['issue_count'], $state['issue_counts'], $state['pending_count'], $state['not_required_count'], $state['thumbnail_fallback_count'], $state['warm_queued'])) {
        throw new RuntimeException('Image integrity audit did not persist a complete summary.');
    }
    foreach ((array) ($state['issues'] ?? []) as $issue) {
        if ((int) ($issue['post_id'] ?? 0) === (int) $post_id && 'map' === ($issue['kind'] ?? '')) {
            throw new RuntimeException('An existing attachment-only generated map was reported as missing.');
        }
    }
} finally {
    wp_clear_scheduled_hook(FEU_Einsatz_Maintenance_Service::CACHE_WARM_HOOK, [$post_id]);
    if ($attachment_id > 0 && !is_wp_error($attachment_id)) {
        wp_delete_attachment($attachment_id, true);
    }
    if (is_file($map_file)) {
        unlink($map_file);
    }
    wp_delete_post($post_id, true);
}

WP_CLI::success('Maintenance scheduling, cache warm-up and image audit are operational.');
