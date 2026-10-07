<?php
if (!defined('ABSPATH') || !defined('WP_CLI') || !WP_CLI) {
    exit(1);
}

wp_set_current_user(1);
$core = Feuer_Einsatzberichte_Core::get_instance();
$center = new FEU_Einsatz_Operations_Center($core->get_admin(), $core->get_db());
$post_id = wp_insert_post([
    'post_type' => FEU_Einsatz_Report_Post_Type::POST_TYPE,
    'post_status' => 'pending',
    'post_title' => 'Operations queue CI report',
    'post_content' => 'Test report awaiting review.',
    'post_author' => 1,
], true);
if (is_wp_error($post_id)) {
    throw new RuntimeException($post_id->get_error_message());
}
try {
    update_post_meta($post_id, FEU_Einsatz_Report_Post_Type::MARKER_META, '1');
    $reports_method = new ReflectionMethod($center, 'get_reports');
    $query = $reports_method->invoke($center, ['pending']);
    if (!in_array($post_id, wp_list_pluck($query->posts, 'ID'), true)) {
        throw new RuntimeException('Pending report absent from editorial queue.');
    }
    $_GET['tab'] = 'queue';
    $_GET['report_status'] = 'pending';
    ob_start();
    $center->render();
    $html = ob_get_clean();
    if (false === strpos($html, 'Operations queue CI report') || false === strpos($html, 'Wartet auf Prüfung')) {
        throw new RuntimeException('Editorial queue did not render pending report with review status.');
    }
    $health = $center->test_schedule_health();
    if (!isset($health['status'], $health['test']) || 'feu_einsatz_schedule' !== $health['test']) {
        throw new RuntimeException('Site Health test is unavailable.');
    }
} finally {
    unset($_GET['tab'], $_GET['report_status']);
    wp_delete_post($post_id, true);
}
WP_CLI::success('Operations centre lists pending reports and registers Site Health status.');
