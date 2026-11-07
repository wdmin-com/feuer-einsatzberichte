<?php
if (!defined('ABSPATH')) {
    exit;
}

/** Separate keyword vocabulary for incident reports; legacy categories remain readable. */
final class FEU_Einsatz_Report_Taxonomy {
    public const TAXONOMY = 'feu_einsatzstichwort';
    public const ENABLED_OPTION = 'feu_einsatz_stichwort_taxonomy_enabled';
    public const SELECTED_OPTION = 'feu_einsatz_stichwort_ids';
    public const PRIMARY_META = '_feu_einsatz_primary_stichwort_id';
    public const LEGACY_TERM_META = '_feu_einsatz_legacy_category_id';

    public static function register(): void {
        register_taxonomy(self::TAXONOMY, FEU_Einsatz_Report_Post_Type::readable_post_types(), [
            'labels' => [
                'name' => __('Einsatzstichworte', 'feuer-einsatzberichte'),
                'singular_name' => __('Einsatzstichwort', 'feuer-einsatzberichte'),
                'add_new_item' => __('Neues Einsatzstichwort', 'feuer-einsatzberichte'),
                'edit_item' => __('Einsatzstichwort bearbeiten', 'feuer-einsatzberichte'),
            ],
            'hierarchical' => true,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'show_in_rest' => false,
            'show_admin_column' => false,
            'meta_box_cb' => false,
            'capabilities' => [
                'manage_terms' => 'manage_options',
                'edit_terms' => 'manage_options',
                'delete_terms' => 'manage_options',
                'assign_terms' => 'manage_options',
            ],
            'rewrite' => false,
            'query_var' => false,
        ]);
        add_action('pre_get_terms', [self::class, 'hide_legacy_terms_from_blog_admin']);
        add_action('set_object_terms', [self::class, 'invalidate_after_assignment'], 10, 4);
        add_action('edited_term', [self::class, 'invalidate_after_term_edit'], 10, 3);
        add_action('delete_term', [self::class, 'invalidate_after_term_edit'], 10, 3);
        add_filter('wp_update_term_data', [self::class, 'preserve_mapped_term_slug'], 10, 4);
        add_filter('wp_update_term_parent', [self::class, 'preserve_mapped_term_parent'], 10, 3);
    }

    /** Mapped slugs are part of existing report URLs and must not drift. */
    public static function preserve_mapped_term_slug(array $data, int $term_id, string $taxonomy, array $args): array {
        if (self::TAXONOMY === $taxonomy && (int) get_term_meta($term_id, self::LEGACY_TERM_META, true) > 0) {
            $term = get_term($term_id, $taxonomy);
            if ($term instanceof WP_Term) {
                $data['slug'] = (string) $term->slug;
            }
        }
        return $data;
    }

    public static function preserve_mapped_term_parent($parent_id, $term_id, $taxonomy) {
        if (self::TAXONOMY === $taxonomy && (int) get_term_meta((int) $term_id, self::LEGACY_TERM_META, true) > 0) {
            $term = get_term((int) $term_id, $taxonomy);
            if ($term instanceof WP_Term) {
                return (int) $term->parent;
            }
        }
        return $parent_id;
    }

    public static function invalidate_after_assignment($post_id, $term_ids, $term_taxonomy_ids, $taxonomy): void {
        if (self::TAXONOMY === $taxonomy && class_exists('Feuer_Einsatzberichte_Core')) {
            Feuer_Einsatzberichte_Core::get_instance()->get_db()->invalidate_statistics_dashboard_cache();
        }
    }

    public static function invalidate_after_term_edit($term_id, $term_taxonomy_id, $taxonomy): void {
        if (self::TAXONOMY === $taxonomy && class_exists('Feuer_Einsatzberichte_Core')) {
            Feuer_Einsatzberichte_Core::get_instance()->get_db()->invalidate_statistics_dashboard_cache();
        }
    }

    /** Keep the compatibility mirror out of the ordinary blog category manager. */
    public static function hide_legacy_terms_from_blog_admin(WP_Term_Query $query): void {
        static $resolving_root = false;
        if ($resolving_root) {
            return;
        }
        if (!self::enabled() || !is_admin() || !function_exists('get_current_screen')) {
            return;
        }
        $screen = get_current_screen();
        if (!$screen || 'post' !== $screen->post_type || !in_array($screen->id, ['edit-category', 'post'], true)) {
            return;
        }
        if ('post' === $screen->id) {
            $editing_id = isset($_GET['post']) ? absint(wp_unslash($_GET['post'])) : 0;
            if ($editing_id && FEU_Einsatz_Report_Post_Type::is_marked_report($editing_id)) {
                return;
            }
        }
        // Explicit term lookups and object-term queries must remain complete.
        // Only ordinary blog category pickers and the list screen are filtered.
        $taxonomies = (array) ($query->query_vars['taxonomy'] ?? []);
        if (!in_array('category', $taxonomies, true)
            || !empty($query->query_vars['include'])
            || !empty($query->query_vars['slug'])
            || !empty($query->query_vars['name'])
            || !empty($query->query_vars['child_of'])
            || !empty($query->query_vars['object_ids'])) {
            return;
        }
        $resolving_root = true;
        try {
            $root = get_category_by_slug('einsatze');
            if (!($root instanceof WP_Term)) {
                $root = get_category_by_slug('einsaetze');
            }
        } finally {
            $resolving_root = false;
        }
        if ($root instanceof WP_Term) {
            $excluded = array_map('absint', (array) ($query->query_vars['exclude_tree'] ?? []));
            $excluded[] = (int) $root->term_id;
            $query->query_vars['exclude_tree'] = array_values(array_unique($excluded));
        }
    }

    public static function enabled(): bool {
        return 1 === (int) get_option(self::ENABLED_OPTION, 0);
    }

    public static function find_root_term(string $taxonomy): ?WP_Term {
        if ('category' === $taxonomy) {
            $root = FEU_Einsatz_Template_Helpers::find_root_category();
            return $root instanceof WP_Term ? $root : null;
        }
        foreach (['einsatze', 'einsaetze'] as $slug) {
            $root = get_term_by('slug', $slug, $taxonomy);
            if ($root instanceof WP_Term) {
                return $root;
            }
        }
        return null;
    }

    public static function get_report_terms(int $post_id): array {
        if (self::enabled()) {
            $terms = taxonomy_exists(self::TAXONOMY) ? get_the_terms($post_id, self::TAXONOMY) : [];
            return is_array($terms) ? array_values($terms) : [];
        }
        $legacy = get_the_terms($post_id, 'category');
        return is_array($legacy) ? array_values($legacy) : [];
    }

    public static function get_report_term_ids(int $post_id): array {
        return array_map('intval', wp_list_pluck(self::get_report_terms($post_id), 'term_id'));
    }

    public static function get_selected_ids(): array {
        $option = self::enabled() ? self::SELECTED_OPTION : 'feu_einsatz_categories';
        return array_values(array_unique(array_filter(array_map('absint', (array) get_option($option, [])))));
    }

    public static function get_primary_term(int $post_id): ?WP_Term {
        $terms = self::get_report_terms($post_id);
        $preferred_new = (int) get_post_meta($post_id, self::PRIMARY_META, true);
        $preferred_old = (int) get_post_meta($post_id, FEU_Einsatz_Report_Post_Type::PRIMARY_CATEGORY_META, true);
        foreach ($terms as $term) {
            $preferred = self::TAXONOMY === $term->taxonomy ? $preferred_new : $preferred_old;
            if ($preferred > 0 && $preferred === (int) $term->term_id) {
                return $term;
            }
        }
        $root = FEU_Einsatz_Template_Helpers::find_root_category();
        $terms = array_values(array_filter($terms, static function ($term) use ($root) {
            return self::TAXONOMY === $term->taxonomy
                ? !in_array($term->slug, ['einsaetze', 'einsatze'], true)
                : !($root instanceof WP_Term) || (int) $root->term_id !== (int) $term->term_id;
        }));
        usort($terms, static function ($a, $b) {
            return count(get_ancestors((int) $b->term_id, $b->taxonomy)) <=> count(get_ancestors((int) $a->term_id, $a->taxonomy))
                ?: (int) $a->term_id <=> (int) $b->term_id;
        });
        return $terms[0] ?? null;
    }

    public static function copy_legacy_term(int $legacy_id): int|WP_Error {
        $legacy = get_term($legacy_id, 'category');
        if (!($legacy instanceof WP_Term)) {
            return new WP_Error('invalid_legacy_category', __('Altes Einsatzstichwort nicht gefunden.', 'feuer-einsatzberichte'));
        }
        $parent = 0;
        if ((int) $legacy->parent > 0) {
            $parent = self::copy_legacy_term((int) $legacy->parent);
            if (is_wp_error($parent)) {
                return $parent;
            }
        }
        $existing = get_term_by('slug', (string) $legacy->slug, self::TAXONOMY);
        if ($existing instanceof WP_Term) {
            $owner = (int) get_term_meta((int) $existing->term_id, self::LEGACY_TERM_META, true);
            if ($owner !== $legacy_id || (int) $existing->parent !== (int) $parent) {
                return new WP_Error('keyword_slug_conflict', __('Slug-Konflikt beim Kopieren der Einsatzstichworte.', 'feuer-einsatzberichte'));
            }
            $term_id = (int) $existing->term_id;
        } else {
            $created = wp_insert_term((string) $legacy->name, self::TAXONOMY, [
                'slug' => (string) $legacy->slug,
                'description' => (string) $legacy->description,
                'parent' => (int) $parent,
            ]);
            if (is_wp_error($created)) {
                return $created;
            }
            $term_id = (int) $created['term_id'];
        }
        update_term_meta($term_id, self::LEGACY_TERM_META, $legacy_id);
        if ((int) get_term_meta($term_id, self::LEGACY_TERM_META, true) !== $legacy_id) {
            return new WP_Error('keyword_mapping_failed', __('Die Zuordnung des Einsatzstichworts konnte nicht gespeichert werden.', 'feuer-einsatzberichte'));
        }
        return $term_id;
    }

    /** Copy, never move: the source category and public permalink remain intact. */
    public static function copy_report_terms(int $post_id): array|WP_Error {
        $legacy_ids = array_map('intval', wp_get_post_categories($post_id));
        $root = FEU_Einsatz_Template_Helpers::find_root_category();
        $root_id = $root instanceof WP_Term ? (int) $root->term_id : 0;
        $new_ids = [];
        $map = [];
        foreach ($legacy_ids as $legacy_id) {
            if ($legacy_id === $root_id || ($root_id && !term_is_ancestor_of($root_id, $legacy_id, 'category'))) {
                continue;
            }
            $new_id = self::copy_legacy_term($legacy_id);
            if (is_wp_error($new_id)) {
                return $new_id;
            }
            $new_ids[] = $new_id;
            $map[$legacy_id] = $new_id;
        }
        if (!$new_ids) {
            return [];
        }
        $assigned = wp_set_object_terms($post_id, $new_ids, self::TAXONOMY, false);
        if (is_wp_error($assigned)) {
            return $assigned;
        }
        $preferred = (int) get_post_meta($post_id, FEU_Einsatz_Report_Post_Type::PRIMARY_CATEGORY_META, true);
        if (!isset($map[$preferred])) {
            $legacy_candidates = array_keys($map);
            usort($legacy_candidates, static function ($left, $right) {
                return count(get_ancestors((int) $right, 'category')) <=> count(get_ancestors((int) $left, 'category'))
                    ?: (int) $left <=> (int) $right;
            });
            $preferred = (int) reset($legacy_candidates);
        }
        $primary_id = $map[$preferred];
        update_post_meta($post_id, self::PRIMARY_META, (int) $primary_id);
        return $new_ids;
    }

    public static function legacy_counterpart(int $term_id): int|WP_Error {
        $term = get_term($term_id, self::TAXONOMY);
        if (!($term instanceof WP_Term)) {
            return new WP_Error('invalid_keyword', __('Einsatzstichwort nicht gefunden.', 'feuer-einsatzberichte'));
        }
        $root = FEU_Einsatz_Template_Helpers::find_root_category();
        if (!($root instanceof WP_Term)) {
            return new WP_Error('missing_legacy_root', __('Die bisherige Kategorie „Einsätze“ fehlt.', 'feuer-einsatzberichte'));
        }
        $mapped_id = (int) get_term_meta($term_id, self::LEGACY_TERM_META, true);
        if ($mapped_id && get_term($mapped_id, 'category') instanceof WP_Term) {
            if ($mapped_id === (int) $root->term_id || term_is_ancestor_of((int) $root->term_id, $mapped_id, 'category')) {
                return $mapped_id;
            }
            return new WP_Error('invalid_legacy_mapping', __('Ein Einsatzstichwort verweist auf eine fremde Blog-Kategorie.', 'feuer-einsatzberichte'));
        }
        if (in_array($term->slug, ['einsaetze', 'einsatze'], true)) {
            return (int) $root->term_id;
        }
        $parent_id = (int) $root->term_id;
        if ((int) $term->parent > 0) {
            $parent = self::legacy_counterpart((int) $term->parent);
            if (is_wp_error($parent)) {
                return $parent;
            }
            $parent_id = (int) $parent;
        }
        $existing = get_term_by('slug', (string) $term->slug, 'category');
        if ($existing instanceof WP_Term) {
            if ((int) $existing->term_id !== $parent_id && !term_is_ancestor_of((int) $root->term_id, (int) $existing->term_id, 'category')) {
                return new WP_Error('legacy_slug_conflict', __('Ein Blog-Kategorie-Slug ist bereits vergeben.', 'feuer-einsatzberichte'));
            }
            $mapped_id = (int) $existing->term_id;
        } else {
            $created = wp_insert_term((string) $term->name, 'category', [
                'slug' => (string) $term->slug,
                'description' => (string) $term->description,
                'parent' => $parent_id,
            ]);
            if (is_wp_error($created)) {
                return $created;
            }
            $mapped_id = (int) $created['term_id'];
        }
        update_term_meta($term_id, self::LEGACY_TERM_META, $mapped_id);
        return $mapped_id;
    }

    /** Temporary compatibility bridge for old public category archives. The override is only for a draft without a fixed slug. */
    public static function set_report_terms(int $post_id, array $term_ids, bool $allow_unpublished_primary_change = false): array|WP_Error {
        $term_ids = array_values(array_unique(array_filter(array_map('absint', $term_ids))));
        $previous_primary = (int) get_post_meta($post_id, self::PRIMARY_META, true);
        if (!$allow_unpublished_primary_change && $previous_primary && !in_array($previous_primary, $term_ids, true)
            && FEU_Einsatz_Report_Post_Type::POST_TYPE === get_post_type($post_id)
            && 'category' === (string) get_post_meta($post_id, FEU_Einsatz_Report_Post_Type::URL_SCHEME_META, true)) {
            return new WP_Error('fixed_permalink_keyword', __('Das Hauptstichwort gehört zur festen URL und darf nicht entfernt werden.', 'feuer-einsatzberichte'));
        }
        $previous = wp_get_object_terms($post_id, self::TAXONOMY, ['fields' => 'ids']);
        if (is_wp_error($previous)) {
            return $previous;
        }
        $legacy_ids = [];
        foreach ($term_ids as $term_id) {
            $legacy_id = self::legacy_counterpart($term_id);
            if (is_wp_error($legacy_id)) {
                return $legacy_id;
            }
            $legacy_ids[] = (int) $legacy_id;
        }
        $assigned = wp_set_object_terms($post_id, $term_ids, self::TAXONOMY, false);
        if (is_wp_error($assigned)) {
            return $assigned;
        }
        $mirrored = wp_set_post_categories($post_id, $legacy_ids, false);
        if (is_wp_error($mirrored)) {
            wp_set_object_terms($post_id, array_map('intval', $previous), self::TAXONOMY, false);
            return $mirrored;
        }
        if (!$previous_primary || !in_array($previous_primary, $term_ids, true)) {
            $ranked = $term_ids;
            usort($ranked, static function ($left, $right) {
                return count(get_ancestors($right, self::TAXONOMY)) <=> count(get_ancestors($left, self::TAXONOMY))
                    ?: $left <=> $right;
            });
            if ($ranked) {
                update_post_meta($post_id, self::PRIMARY_META, (int) $ranked[0]);
            } else {
                delete_post_meta($post_id, self::PRIMARY_META);
            }
        }
        return $term_ids;
    }
}
