<?php
/** Public overview index cache and invalidation regression check. */
if (!defined('ABSPATH')) {
    exit(1);
}

$root = FEU_Einsatz_Template_Helpers::find_root_category();
if (!($root instanceof WP_Term)) {
    throw new RuntimeException('Overview root category is missing.');
}

$suffix = strtolower(wp_generate_password(8, false, false));
$term = wp_insert_term('Overview cache ' . $suffix, 'category', [
    'parent' => (int) $root->term_id,
    'slug' => 'overview-cache-' . $suffix,
]);
if (is_wp_error($term)) {
    throw new RuntimeException('Could not create overview cache category.');
}
$term_id = (int) $term['term_id'];
$post_id = 0;

try {
    $post_id = wp_insert_post([
        'post_type' => 'post',
        'post_status' => 'publish',
        'post_title' => 'Overview cache ' . $suffix,
        'post_name' => 'overview-cache-' . $suffix,
    ], true);
    if (is_wp_error($post_id)) {
        throw new RuntimeException('Could not create overview cache report.');
    }
    update_post_meta($post_id, FEU_Einsatz_Report_Post_Type::MARKER_META, '1');
    update_post_meta($post_id, '_feu_einsatz_datum', '2041-02-10');
    wp_set_post_categories($post_id, [$term_id]);

    $first = FEU_Einsatz_Template_Helpers::get_overview_context(['selected_year' => '2041']);
    if (1 !== (int) ($first['year_counts']['2041'] ?? 0)
        || 1 !== (int) ($first['scoped_total_posts'] ?? 0)
        || 'Overview cache ' . $suffix !== (string) ($first['scoped_category_stats'][$term_id]['name'] ?? '')) {
        throw new RuntimeException('The overview index has incorrect year or category data.');
    }

    $full_archive_queries = 0;
    $track_archive_queries = static function ($query) use (&$full_archive_queries) {
        if ($query instanceof WP_Query && -1 === (int) $query->get('posts_per_page')
            && 'ids' === (string) $query->get('fields')) {
            $full_archive_queries++;
        }
    };
    add_action('pre_get_posts', $track_archive_queries);
    try {
        FEU_Einsatz_Template_Helpers::get_overview_context(['selected_year' => '2041', 'posts_per_page' => 7]);
    } finally {
        remove_action('pre_get_posts', $track_archive_queries);
    }
    if ($full_archive_queries > 0) {
        throw new RuntimeException('A warm overview still queried every report.');
    }

    $revision = (string) get_option('feu_einsatz_overview_cache_revision', '');
    update_post_meta($post_id, '_feu_einsatz_datum', '2042-03-11');
    if ($revision === (string) get_option('feu_einsatz_overview_cache_revision', '')) {
        throw new RuntimeException('Changing the event date did not invalidate the overview index.');
    }
    $old_year = FEU_Einsatz_Template_Helpers::get_overview_context(['selected_year' => '2041']);
    $new_year = FEU_Einsatz_Template_Helpers::get_overview_context(['selected_year' => '2042']);
    if (0 !== (int) ($old_year['scoped_total_posts'] ?? -1)
        || 1 !== (int) ($new_year['scoped_total_posts'] ?? 0)
        || 1 !== (int) ($new_year['scoped_category_stats'][$term_id]['count'] ?? 0)) {
        throw new RuntimeException('The overview served stale data after a report update.');
    }

    $renamed = wp_update_term($term_id, 'category', ['name' => 'Renamed overview ' . $suffix]);
    if (is_wp_error($renamed)) {
        throw new RuntimeException('Could not rename overview cache category.');
    }
    $after_term_edit = FEU_Einsatz_Template_Helpers::get_overview_context(['selected_year' => '2042']);
    if ('Renamed overview ' . $suffix !== (string) ($after_term_edit['scoped_category_stats'][$term_id]['name'] ?? '')) {
        throw new RuntimeException('The overview served a stale category name.');
    }
} finally {
    if ($post_id && !is_wp_error($post_id)) {
        wp_delete_post((int) $post_id, true);
    }
    wp_delete_term($term_id, 'category');
}

echo "Overview index cache invalidation and year/category aggregation passed.\n";

$map_post_id = wp_insert_post([
    'post_type' => 'post',
    'post_status' => 'publish',
    'post_title' => 'Background map ' . $suffix,
    'post_name' => 'background-map-' . $suffix,
], true);
if (is_wp_error($map_post_id)) {
    throw new RuntimeException('Could not create background map report.');
}
try {
    update_post_meta($map_post_id, FEU_Einsatz_Report_Post_Type::MARKER_META, '1');
    update_post_meta($map_post_id, '_feu_einsatz_strasse', 'Performance Test Street');
    update_post_meta($map_post_id, '_feu_einsatz_plz', '22113');
    update_post_meta($map_post_id, '_feu_einsatz_stadt', 'Hamburg');
    $map_requests = 0;
    $block_map_requests = static function ($pre, $args, $url) use (&$map_requests) {
        if (str_contains((string) $url, 'nominatim.openstreetmap.org')
            || str_contains((string) $url, 'overpass')) {
            $map_requests++;
            return new WP_Error('unexpected_public_map_request', 'Public report rendering attempted remote map lookup.');
        }
        return $pre;
    };
    add_filter('pre_http_request', $block_map_requests, 10, 3);
    try {
        FEU_Einsatz_Template_Helpers::get_single_context(get_post($map_post_id));
    } finally {
        remove_filter('pre_http_request', $block_map_requests, 10);
    }
    if ($map_requests > 0 || !wp_next_scheduled('feu_einsatz_prime_public_map', [$map_post_id])) {
        throw new RuntimeException('Public report rendering did not defer remote map repair.');
    }
} finally {
    wp_clear_scheduled_hook('feu_einsatz_prime_public_map', [$map_post_id]);
    wp_delete_post((int) $map_post_id, true);
}

echo "Public map repair is deferred to a background event.\n";
