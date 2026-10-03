<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * The report identity and its permalink scheme are independent of the blog.
 * Legacy reports remain readable as posts until an explicit migration runs.
 */
final class FEU_Einsatz_Report_Post_Type {

    public const POST_TYPE = 'einsatzbericht';
    public const MARKER_META = '_feu_einsatz_einsatzbericht';
    public const URL_SCHEME_META = '_feu_einsatz_url_scheme';
    public const LEGACY_PATH_META = '_feu_einsatz_legacy_path';
    public const PRIMARY_CATEGORY_META = '_feu_einsatz_primary_category_id';
    public const CATEGORY_SLUG_META = '_feu_einsatz_permalink_category_slug';

    public static function init_access_guard(): void {
        add_filter('map_meta_cap', [self::class, 'guard_meta_capabilities'], 20, 4);
        add_filter('wp_insert_post_data', [self::class, 'preserve_legacy_slug'], 20, 2);
    }

    /** Once a report has a slug, ordinary edits must not silently change its URL. */
    public static function preserve_legacy_slug(array $data, array $postarr): array {
        $post_id = absint($postarr['ID'] ?? 0);
        if (!$post_id) {
            return $data;
        }
        $post = get_post($post_id);
        if (
            $post instanceof WP_Post
            && '' !== (string) $post->post_name
            && self::is_marked_report($post)
        ) {
            $data['post_name'] = $post->post_name;
        }
        return $data;
    }

    public static function guard_meta_capabilities(array $caps, string $cap, int $user_id, array $args): array {
        if (!in_array($cap, ['edit_post', 'delete_post', 'read_post'], true) || empty($args[0])) {
            return $caps;
        }

        $post = get_post((int) $args[0]);
        if (!($post instanceof WP_Post)
            || !in_array($post->post_type, self::readable_post_types(), true)
            || !self::is_marked_report($post)) {
            return $caps;
        }

        // Published reports remain public; protected reports and every write
        // also require the plugin's role matrix, including XML-RPC requests.
        if ('read_post' === $cap && 'publish' === $post->post_status) {
            return $caps;
        }

        $user = get_userdata($user_id);
        if (!($user instanceof WP_User) || !class_exists('FEU_Einsatz_Admin')) {
            return ['do_not_allow'];
        }

        if ($user->has_cap('manage_options')) {
            return $caps;
        }

        $roles = (array) $user->roles;
        $full_access = FEU_Einsatz_Admin::get_plugin_full_access_roles();
        $access = FEU_Einsatz_Admin::get_plugin_role_access_settings();
        $allowed = 'delete_post' === $cap
            ? (array) ($access['reports'] ?? [])
            : array_merge((array) ($access['reports'] ?? []), (array) ($access['create_report'] ?? []));
        if (empty(array_intersect($roles, $full_access)) && empty(array_intersect($roles, $allowed))) {
            return ['do_not_allow'];
        }

        return $caps;
    }

    public static function register(): void {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => __('Einsatzberichte', 'feuer-einsatzberichte'),
                'singular_name' => __('Einsatzbericht', 'feuer-einsatzberichte'),
                'add_new_item' => __('Neuen Einsatzbericht erstellen', 'feuer-einsatzberichte'),
                'edit_item' => __('Einsatzbericht bearbeiten', 'feuer-einsatzberichte'),
            ],
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => false,
            'show_in_rest' => false,
            'has_archive' => false,
            'rewrite' => false,
            'query_var' => true,
            'hierarchical' => false,
            'supports' => ['title', 'editor', 'author', 'excerpt', 'thumbnail', 'comments', 'revisions'],
            // Keep existing tags and term relationships visible after a legacy
            // post is converted; the migration never recreates terms.
            'taxonomies' => ['category', 'post_tag', FEU_Einsatz_Report_Taxonomy::TAXONOMY],
            'capability_type' => 'post',
            // Creation goes through the plugin form, which validates the URL
            // category and writes the report marker before exposing a route.
            'capabilities' => ['create_posts' => 'do_not_allow'],
            'map_meta_cap' => true,
        ]);
    }

    public static function readable_post_types(): array {
        return ['post', self::POST_TYPE];
    }

    public static function slug_conflicts(string $slug, int $exclude_id = 0): bool {
        $slug = sanitize_title($slug);
        if ('' === $slug) {
            return false;
        }
        $matches = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => ['publish', 'future', 'private', 'pending', 'draft'],
            'name' => $slug,
            'posts_per_page' => 2,
            'fields' => 'ids',
            'no_found_rows' => true,
            'suppress_filters' => true,
        ]);
        foreach ($matches as $id) {
            if ((int) $id !== $exclude_id) {
                return true;
            }
        }
        return false;
    }

    public static function is_marked_report($post): bool {
        $post = $post instanceof WP_Post ? $post : get_post($post);

        return $post instanceof WP_Post
            && in_array($post->post_type, self::readable_post_types(), true)
            && '1' === (string) get_post_meta($post->ID, self::MARKER_META, true);
    }

    /**
     * Resolve the category only from the Einsatz tree, never from blog terms.
     */
    public static function resolve_primary_category(array $category_ids): ?WP_Term {
        if (FEU_Einsatz_Report_Taxonomy::enabled()) {
            foreach (array_unique(array_map('absint', $category_ids)) as $term_id) {
                $term = get_term($term_id, FEU_Einsatz_Report_Taxonomy::TAXONOMY);
                if ($term instanceof WP_Term && !in_array($term->slug, ['einsaetze', 'einsatze'], true)) {
                    return $term;
                }
            }
            return null;
        }
        if (!class_exists('FEU_Einsatz_Template_Helpers')) {
            return null;
        }

        $root = FEU_Einsatz_Template_Helpers::find_root_category();
        if (!($root instanceof WP_Term)) {
            return null;
        }

        foreach (array_unique(array_map('absint', $category_ids)) as $category_id) {
            $term = get_term($category_id, 'category');
            if (
                $term instanceof WP_Term
                && $category_id !== (int) $root->term_id
                && term_is_ancestor_of((int) $root->term_id, $category_id, 'category')
            ) {
                return $term;
            }
        }

        return null;
    }

    public static function set_category_permalink_meta(int $post_id, WP_Term $category): void {
        update_post_meta($post_id, self::URL_SCHEME_META, 'category');
        if (FEU_Einsatz_Report_Taxonomy::TAXONOMY === $category->taxonomy) {
            update_post_meta($post_id, FEU_Einsatz_Report_Taxonomy::PRIMARY_META, (string) $category->term_id);
        } else {
            update_post_meta($post_id, self::PRIMARY_CATEGORY_META, (string) $category->term_id);
        }
        update_post_meta($post_id, self::CATEGORY_SLUG_META, (string) $category->slug);
    }

    /** Do not accept arbitrary paths/URLs from user input. */
    public static function normalized_legacy_path(int $post_id): string {
        $path = trim((string) get_post_meta($post_id, self::LEGACY_PATH_META, true), '/');
        $slug = get_post_field('post_name', $post_id);

        if ($path === 'einsaetze/' . $slug && '' !== (string) $slug) {
            return $path;
        }

        return 'einsaetze/' . $slug;
    }
}
