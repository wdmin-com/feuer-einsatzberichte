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

try {
    update_post_meta($post_id, FEU_Einsatz_Report_Post_Type::MARKER_META, '1');
    wp_update_post(['ID' => $post_id, 'post_status' => 'publish']);
    if (!wp_next_scheduled(FEU_Einsatz_Maintenance_Service::CACHE_WARM_HOOK, [$post_id])) {
        throw new RuntimeException('Published report did not queue cache warm-up.');
    }
    $job = get_post_meta($post_id, '_feu_einsatz_cache_warm_job', true);
    if ('queued' !== ($job['status'] ?? '')) {
        throw new RuntimeException('Cache warm-up status is not visible.');
    }
    $state = $service->run_image_integrity_audit();
    if (empty($state['checked_at']) || !isset($state['issue_count'], $state['warm_queued'])) {
        throw new RuntimeException('Image integrity audit did not persist a complete summary.');
    }
} finally {
    wp_clear_scheduled_hook(FEU_Einsatz_Maintenance_Service::CACHE_WARM_HOOK, [$post_id]);
    wp_delete_post($post_id, true);
}

WP_CLI::success('Maintenance scheduling, cache warm-up and image audit are operational.');
