<?php
/** Public overview pagination must work on a WordPress page without a page/2 rewrite. */
if (!defined('ABSPATH')) {
    exit(1);
}

$root = FEU_Einsatz_Template_Helpers::find_root_category();
if (!($root instanceof WP_Term)) {
    throw new RuntimeException('Overview root category is missing.');
}

$suffix = strtolower(wp_generate_password(8, false, false));
$ids = [];
$old_get = $_GET;
$old_request = $_REQUEST;
try {
    foreach (['newer' => '2047-09-12', 'older' => '2047-01-13'] as $label => $date) {
        $id = wp_insert_post([
            'post_type' => 'post',
            'post_status' => 'publish',
            'post_title' => 'Pagination ' . $label . ' ' . $suffix,
            'post_name' => 'pagination-' . $label . '-' . $suffix,
            'post_category' => [(int) $root->term_id],
        ], true);
        if (is_wp_error($id)) {
            throw new RuntimeException('Could not create pagination fixture.');
        }
        $ids[] = (int) $id;
        update_post_meta($id, FEU_Einsatz_Report_Post_Type::MARKER_META, '1');
        update_post_meta($id, '_feu_einsatz_datum', $date);
    }

    $_GET['feu_page'] = '2';
    $_REQUEST['jahr'] = '2047';
    $html = Feuer_Einsatzberichte_Core::get_instance()->get_public()->render_overview_list_shortcode([
        'jahr' => '2047',
        'posts_per_page' => 1,
    ]);
    if (false === stripos($html, 'Pagination older ' . $suffix)
        || false !== stripos($html, 'Pagination newer ' . $suffix)
        || false === strpos($html, 'feu_page=1')
        || false === strpos($html, 'jahr=2047')
        || false !== strpos($html, '/page/2/')) {
        throw new RuntimeException('The overview pagination does not render the second page with query-based links.');
    }
} finally {
    $_GET = $old_get;
    $_REQUEST = $old_request;
    foreach ($ids as $id) {
        wp_delete_post($id, true);
    }
}

echo "Public overview pagination uses a working query parameter.\n";
