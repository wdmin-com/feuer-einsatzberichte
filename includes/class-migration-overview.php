<?php
if (!defined('ABSPATH')) {
    exit;
}

/** Read-only view of the report and keyword cutovers. No migration starts here. */
final class FEU_Einsatz_Migration_Overview {
    public const COMPLETE_OPTION = 'feu_einsatz_storage_migration_complete';

    private static function count_reports(string $post_type): int {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
             WHERE p.post_type = %s AND p.post_status NOT IN ('auto-draft', 'inherit')
               AND m.meta_key = %s AND m.meta_value = '1'",
            $post_type,
            FEU_Einsatz_Report_Post_Type::MARKER_META
        ));
    }

    public static function keyword_items(array $state): array {
        $legacy_ids = (array) ($state['keyword_run']['old_selected'] ?? []);
        if (!$legacy_ids && !$state['keywords_enabled']) {
            $legacy_ids = FEU_Einsatz_Report_Taxonomy::get_selected_ids();
        }
        $legacy_ids = array_values(array_unique(array_filter(array_map('absint', $legacy_ids))));
        if (!$legacy_ids) {
            return [];
        }

        $keyword_run = (array) ($state['keyword_run'] ?? []);
        $show_failures = in_array((string) ($keyword_run['status'] ?? ''), ['failed_rolled_back', 'rollback_failed', 'partial_error'], true);
        $failures = $show_failures
            ? FEU_Einsatz_Keyword_Migration::get_failures((string) ($keyword_run['run_id'] ?? '')) : [];

        $mapped = [];
        $active_ids = !empty($state['keywords_enabled']) ? FEU_Einsatz_Report_Taxonomy::get_selected_ids() : [];
        $terms = get_terms([
            'taxonomy' => FEU_Einsatz_Report_Taxonomy::TAXONOMY,
            'hide_empty' => false,
        ]);
        $terms_unavailable = is_wp_error($terms) || !is_array($terms);
        if (!$terms_unavailable) {
            foreach ($terms as $term) {
                $source_id = (int) get_term_meta((int) $term->term_id, FEU_Einsatz_Report_Taxonomy::LEGACY_TERM_META, true);
                if ($source_id > 0) {
                    $mapped[$source_id] = (int) $term->term_id;
                }
            }
        }

        $items = [];
        foreach ($legacy_ids as $id) {
            $term = get_term($id, 'category');
            $failure = (array) ($failures['categories:' . $id] ?? []);
            $items[] = [
                'id' => $id,
                'name' => $term instanceof WP_Term ? (string) $term->name : '',
                'target_id' => $mapped[$id] ?? 0,
                'status' => $failure ? 'failed' : ($terms_unavailable ? 'unavailable'
                    : (!($term instanceof WP_Term) ? 'missing'
                        : (isset($mapped[$id])
                            ? (in_array($mapped[$id], $active_ids, true) ? 'active' : 'mapped')
                            : 'pending'))),
                'attempts' => (int) ($failure['attempts'] ?? 0),
                'developer_report' => (string) ($failure['developer_report'] ?? ''),
            ];
        }
        return $items;
    }

    public static function status(): array {
        $old_reports = self::count_reports('post');
        $new_reports = self::count_reports(FEU_Einsatz_Report_Post_Type::POST_TYPE);
        $post_run = FEU_Einsatz_Post_Migration::get_status();
        $keyword_run = FEU_Einsatz_Keyword_Migration::get_run();
        $keywords_enabled = FEU_Einsatz_Report_Taxonomy::enabled();
        $legacy_keywords = $keywords_enabled ? [] : FEU_Einsatz_Report_Taxonomy::get_selected_ids();
        $accepted = 1 === (int) get_option(self::COMPLETE_OPTION, 0);
        $counts = (array) ($post_run['counts'] ?? []);
        $keyword_items = self::keyword_items([
            'keyword_run' => $keyword_run,
            'keywords_enabled' => $keywords_enabled,
        ]);
        $keywords_verified = !in_array(false, array_map(static function (array $item): bool {
            return 'active' === $item['status'];
        }, $keyword_items), true);
        $ready_for_acceptance = 0 === $old_reports && $keywords_enabled && $keywords_verified
            && 'complete' === ($post_run['status'] ?? '')
            && 'complete' === ($keyword_run['status'] ?? '')
            && (int) ($counts['migrated'] ?? -1) === (int) ($post_run['total'] ?? -2);

        $completed = $accepted && $ready_for_acceptance;
        $needs_attention = !$accepted
            && ($ready_for_acceptance || $old_reports > 0 || !$keywords_verified
                || (!$keywords_enabled && count($legacy_keywords) > 0));
        $has_legacy = $old_reports > 0 || !$keywords_verified
            || (!$keywords_enabled && count($legacy_keywords) > 0);
        $storage = $has_legacy && $new_reports > 0 ? 'mixed' : ($has_legacy ? 'old' : 'new');

        return [
            'needs_attention' => $needs_attention,
            'completed' => $completed,
            'accepted_before' => $accepted,
            'ready_for_acceptance' => $ready_for_acceptance,
            'storage' => $storage,
            'old_reports' => $old_reports,
            'new_reports' => $new_reports,
            'legacy_keywords' => count($legacy_keywords),
            'keywords_enabled' => $keywords_enabled,
            'post_run' => $post_run,
            'keyword_run' => $keyword_run,
            'keyword_items' => $keyword_items,
        ];
    }

    /** Verify the persisted journals against WordPress immediately before final acceptance. */
    public static function verify_for_acceptance(): bool {
        $state = self::status();
        if (!$state['ready_for_acceptance']) {
            return false;
        }

        $post_run = get_option('feu_einsatz_post_migration_run', []);
        $keyword_run = $state['keyword_run'];
        if (!is_array($post_run) || !is_array($keyword_run)) {
            return false;
        }
        foreach ((array) ($post_run['records'] ?? []) as $record) {
            if (!is_array($record)) {
                return false;
            }
            $id = (int) ($record['id'] ?? 0);
            if ($id < 1 || 'migrated' !== ($record['state'] ?? '')
                || FEU_Einsatz_Report_Post_Type::POST_TYPE !== get_post_type($id)
                || !FEU_Einsatz_Report_Post_Type::is_marked_report($id)
                || 'legacy' !== get_post_meta($id, FEU_Einsatz_Report_Post_Type::URL_SCHEME_META, true)
                || ('publish' === ($record['status'] ?? '') && get_permalink($id) !== ($record['url'] ?? ''))) {
                return false;
            }
        }

        $reports = (array) ($keyword_run['reports'] ?? []);
        $processed = array_values(array_unique(array_map('intval', (array) ($keyword_run['processed'] ?? []))));
        if (count($reports) !== count($processed)) {
            return false;
        }
        $selected = FEU_Einsatz_Report_Taxonomy::get_selected_ids();
        $cutover_selected = array_map('intval', (array) ($keyword_run['cutover_selected'] ?? []));
        sort($selected);
        sort($cutover_selected);
        if ($selected !== $cutover_selected) {
            return false;
        }
        foreach ($reports as $post_id => $before) {
            if (!is_array($before)) {
                return false;
            }
            $id = (int) $post_id;
            if (!in_array($id, $processed, true) || !get_post($id)
                || ('publish' === (string) ($before['status'] ?? 'publish')
                    && get_permalink($id) !== ($before['url'] ?? ''))) {
                return false;
            }
            $current = wp_get_object_terms($id, FEU_Einsatz_Report_Taxonomy::TAXONOMY, ['fields' => 'ids']);
            if (is_wp_error($current)) {
                return false;
            }
            $current = array_map('intval', $current);
            $expected = array_map('intval', (array) ($keyword_run['cutover_terms'][$id]['ids'] ?? []));
            sort($current);
            sort($expected);
            if ($current !== $expected
                || (int) get_post_meta($id, FEU_Einsatz_Report_Taxonomy::PRIMARY_META, true)
                    !== (int) ($keyword_run['cutover_terms'][$id]['primary'] ?? 0)) {
                return false;
            }
        }
        return true;
    }
}
