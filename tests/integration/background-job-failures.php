<?php
/** Real WordPress cron and image-cache failure scenarios. */
if (!defined('ABSPATH') || !defined('WP_CLI') || !WP_CLI) {
    exit(1);
}

wp_set_current_user(1);
$admin = Feuer_Einsatzberichte_Core::get_instance()->get_admin();
$previous_share_image_mode = get_option('feu_einsatz_social_share_image_mode', null);
$post_id = wp_insert_post([
    'post_type' => FEU_Einsatz_Report_Post_Type::POST_TYPE,
    'post_status' => 'draft',
    'post_title' => 'CI Hintergrund-Aufträge',
], true);
if (is_wp_error($post_id)) {
    throw new RuntimeException($post_id->get_error_message());
}

$reject_cron = static function ($pre, $event) {
    return in_array($event->hook, [FEU_Einsatz_Admin::GENERATED_MAP_BACKGROUND_HOOK, FEU_Einsatz_Admin::BACKGROUND_GEOCODE_HOOK], true)
        ? false
        : $pre;
};

try {
    update_post_meta($post_id, FEU_Einsatz_Report_Post_Type::MARKER_META, '1');
    update_post_meta($post_id, '_feu_einsatz_strasse', 'CI Musterstraße');
    add_filter('pre_schedule_event', $reject_cron, 10, 2);

    $queue_map = new ReflectionMethod(FEU_Einsatz_Admin::class, 'queue_map_preview_generation');
    if ($queue_map->invoke($admin, $post_id, 'CI Musterstraße', '22547', 'Hamburg', true, 300, 'ci_cron_failure')) {
        throw new RuntimeException('A rejected map cron event was reported as queued.');
    }
    $status = get_post_meta($post_id, FEU_Einsatz_Admin::GENERATED_MAP_STATUS_META, true);
    if (get_post_meta($post_id, FEU_Einsatz_Admin::GENERATED_MAP_QUEUE_META, true)
        || wp_next_scheduled(FEU_Einsatz_Admin::GENERATED_MAP_BACKGROUND_HOOK, [$post_id])
        || 'error' !== ($status['status'] ?? '')
        || 'cron_schedule_failed' !== ($status['reason'] ?? '')) {
        throw new RuntimeException('A failed map schedule left a phantom queued job.');
    }

    $schedule_geocode = new ReflectionMethod(FEU_Einsatz_Admin::class, 'schedule_background_geocode');
    $scheduled_for = $schedule_geocode->invoke($admin, $post_id, 300);
    $status = get_post_meta($post_id, FEU_Einsatz_Admin::GENERATED_MAP_STATUS_META, true);
    if (0 !== $scheduled_for || 'error' !== ($status['status'] ?? '') || wp_next_scheduled(FEU_Einsatz_Admin::BACKGROUND_GEOCODE_HOOK, [$post_id])) {
        throw new RuntimeException('A rejected geocode event was reported as queued.');
    }

    $queue_payload = ['street' => 'CI Musterstraße', 'plz' => '22547', 'city' => 'Hamburg', 'queued_at' => time() - 300];
    update_post_meta($post_id, FEU_Einsatz_Admin::GENERATED_MAP_QUEUE_META, $queue_payload);
    $recover_map = new ReflectionMethod(FEU_Einsatz_Admin::class, 'maybe_recover_generated_map_schedule');
    if (-1 !== $recover_map->invoke($admin, $post_id, $queue_payload)) {
        throw new RuntimeException('A rejected map recovery was reported as scheduled.');
    }
    $status = get_post_meta($post_id, FEU_Einsatz_Admin::GENERATED_MAP_STATUS_META, true);
    if ('error' !== ($status['status'] ?? '')) {
        throw new RuntimeException('A rejected map recovery did not expose an error status.');
    }
    remove_filter('pre_schedule_event', $reject_cron, 10);

    $share_post_id = wp_insert_post([
        'post_type' => FEU_Einsatz_Report_Post_Type::POST_TYPE,
        'post_status' => 'publish',
        'post_title' => 'CI Share-Karte Cache',
    ], true);
    if (is_wp_error($share_post_id)) {
        throw new RuntimeException($share_post_id->get_error_message());
    }
    update_post_meta($share_post_id, FEU_Einsatz_Report_Post_Type::MARKER_META, '1');
    $share = $admin->get_report_share();
    update_option('feu_einsatz_social_share_image_mode', 'generated', false);
    $reject_share_cron = static function ($pre, $event) {
        return 'feu_einsatz_generate_share_card_background' === $event->hook ? false : $pre;
    };
    add_filter('pre_schedule_event', $reject_share_cron, 10, 2);
    $share->queue_share_card_generation((int) $share_post_id);
    $share_status = get_post_meta($share_post_id, '_feu_einsatz_share_card_job', true);
    remove_filter('pre_schedule_event', $reject_share_cron, 10);
    if ('error' !== ($share_status['status'] ?? '')) {
        throw new RuntimeException('A rejected share image cron event did not expose an error.');
    }
    $generate_share = new ReflectionMethod(FEU_Einsatz_Report_Share::class, 'generate_share_card_cache');
    $cache_path = $generate_share->invoke($share, get_post($share_post_id));
    if ('' === $cache_path || !is_file($cache_path)) {
        throw new RuntimeException('The share image fixture could not be generated.');
    }
    file_put_contents($cache_path, '');
    $repaired_path = $generate_share->invoke($share, get_post($share_post_id));
    clearstatcache(true, $cache_path);
    if ($cache_path !== $repaired_path || filesize($cache_path) < 1) {
        throw new RuntimeException('A zero-byte share image was reused instead of regenerated.');
    }
} finally {
    remove_filter('pre_schedule_event', $reject_cron, 10);
    if (isset($reject_share_cron)) {
        remove_filter('pre_schedule_event', $reject_share_cron, 10);
    }
    if (null === $previous_share_image_mode) {
        delete_option('feu_einsatz_social_share_image_mode');
    } else {
        update_option('feu_einsatz_social_share_image_mode', $previous_share_image_mode, false);
    }
    $clear_queue = new ReflectionMethod(FEU_Einsatz_Admin::class, 'clear_generated_map_queue');
    $clear_queue->invoke($admin, $post_id, true);
    wp_clear_scheduled_hook(FEU_Einsatz_Admin::BACKGROUND_GEOCODE_HOOK, [$post_id]);
    wp_delete_post($post_id, true);
    if (isset($share_post_id) && !is_wp_error($share_post_id)) {
        $admin->get_report_share()->invalidate_share_card_cache((int) $share_post_id);
        wp_delete_post($share_post_id, true);
    }
}

WP_CLI::success('Cron rejection surfaces errors and a zero-byte share image is regenerated.');
