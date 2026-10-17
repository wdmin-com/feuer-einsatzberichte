<?php
if (!defined('ABSPATH')) {
    exit;
}

/** Explicit, backed-up cutover from blog categories to report keywords. */
final class FEU_Einsatz_Keyword_Migration {
    private const RUN_OPTION = 'feu_einsatz_keyword_migration_run';
    private const LOCK_OPTION = 'feu_einsatz_keyword_migration_lock';
    private const FAILURES_OPTION = 'feu_einsatz_keyword_migration_failures';

    private static function record_failure(string $run_id, string $domain, int $id): string {
        $failures = get_option(self::FAILURES_OPTION, []);
        $failures = is_array($failures) ? $failures : [];
        $key = hash('sha256', $run_id . ':' . $domain . ':' . $id . ':verification_failed');
        $record = (array) ($failures[$key] ?? []);
        $record['attempts'] = (int) ($record['attempts'] ?? 0) + 1;
        $record['domain'] = $domain;
        $record['id'] = $id;
        $record['run_id'] = $run_id;
        $record['at_utc'] = gmdate('c');
        $failures[$key] = $record;
        update_option(self::FAILURES_OPTION, $failures, false);
        if ($record['attempts'] < 2 || 'accepted' === ($record['developer_report'] ?? '')) {
            return (string) ($record['developer_report'] ?? 'not_required');
        }
        $body = implode("\n", [
            'Automatischer Diagnosebericht nach wiederholtem Migrationsfehler.',
            'Site: ' . home_url('/'),
            'Plugin: ' . FEU_EINSATZ_VERSION . '; WordPress: ' . get_bloginfo('version') . '; PHP: ' . PHP_VERSION,
            'Time UTC: ' . gmdate('c'),
            'Run: ' . $run_id,
            'Domain: ' . $domain . '; ID: ' . $id,
            'Step: keyword_migration; Code: verification_failed',
            'Attempts: ' . (int) $record['attempts'],
            'Report content and personal details are not included.',
        ]);
        try {
            $accepted = wp_mail(
                'dev@wdmin.com',
                sprintf('[Feuer-Einsatzberichte] Keyword migration error on %s', (string) wp_parse_url(home_url('/'), PHP_URL_HOST)),
                $body
            );
        } catch (Throwable $mail_error) {
            $accepted = false;
        }
        $failures[$key]['developer_report'] = $accepted ? 'accepted' : 'failed';
        update_option(self::FAILURES_OPTION, $failures, false);
        FEU_Einsatz_Logger::log('keyword_migration_diagnostic_email', 'system', 0, __('Diagnosebericht zur Datenmigration', 'feuer-einsatzberichte'), [
            'domain' => $domain,
            'id' => $id,
            'accepted' => $accepted,
        ]);
        return $accepted ? 'accepted' : 'failed';
    }

    public static function get_run(): array {
        $run = get_option(self::RUN_OPTION, []);
        return is_array($run) ? $run : [];
    }

    public static function get_failures(string $run_id): array {
        $stored = get_option(self::FAILURES_OPTION, []);
        if (!is_array($stored) || '' === $run_id) {
            return [];
        }
        $failures = [];
        foreach ($stored as $record) {
            if (is_array($record) && $run_id === (string) ($record['run_id'] ?? '')) {
                $domain = (string) ($record['domain'] ?? '');
                $id = (int) ($record['id'] ?? 0);
                $failures[$domain . ':' . $id] = $record;
            }
        }
        return $failures;
    }

    public static function is_write_locked(): bool {
        return false !== get_option(self::LOCK_OPTION, false)
            || self::needs_manual_recovery();
    }

    public static function needs_manual_recovery(): bool {
        return 'rollback_failed' === (string) (self::get_run()['status'] ?? '');
    }

    private static function report_ids(): array {
        return array_map('intval', (array) get_posts([
            'post_type' => FEU_Einsatz_Report_Post_Type::readable_post_types(),
            'post_status' => ['publish', 'future', 'private', 'pending', 'draft'],
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'meta_key' => FEU_Einsatz_Report_Post_Type::MARKER_META,
            'meta_value' => '1',
            'suppress_filters' => true,
        ]));
    }

    private static function term_snapshot(int $term_id): ?array {
        $term = get_term($term_id, FEU_Einsatz_Report_Taxonomy::TAXONOMY);
        if (!($term instanceof WP_Term)) {
            return null;
        }
        return [
            'name' => (string) $term->name,
            'slug' => (string) $term->slug,
            'description' => (string) $term->description,
            'parent' => (int) $term->parent,
            'legacy_category_id' => (int) get_term_meta($term_id, FEU_Einsatz_Report_Taxonomy::LEGACY_TERM_META, true),
        ];
    }

    public static function preflight(): array {
        $ids = self::report_ids();
        $legacy_ids = FEU_Einsatz_Report_Taxonomy::get_selected_ids();
        $errors = [];
        $root = FEU_Einsatz_Template_Helpers::find_root_category();
        if (!($root instanceof WP_Term)) {
            $errors[] = __('Die bisherige Kategorie „Einsätze“ fehlt.', 'feuer-einsatzberichte');
        }
        if (!$legacy_ids) {
            $errors[] = __('Es sind keine Einsatzstichworte für die Übertragung ausgewählt.', 'feuer-einsatzberichte');
        }
        $checked_targets = [];
        foreach ($legacy_ids as $legacy_id) {
            $term = get_term($legacy_id, 'category');
            if (!($term instanceof WP_Term)) {
                $errors[] = sprintf(__('Kategorie-ID %d fehlt.', 'feuer-einsatzberichte'), $legacy_id);
                continue;
            }
            if ($root instanceof WP_Term && ((int) $term->term_id === (int) $root->term_id
                || !term_is_ancestor_of((int) $root->term_id, (int) $term->term_id, 'category'))) {
                $errors[] = sprintf(__('Kategorie %s gehört nicht zu Einsätze.', 'feuer-einsatzberichte'), $term->name);
                continue;
            }
            // A selected term also copies its ancestors. Check every existing target before writing anything.
            $lineage = array_reverse(array_merge([$legacy_id], get_ancestors($legacy_id, 'category')));
            foreach ($lineage as $source_id) {
                if (isset($checked_targets[$source_id])) {
                    continue;
                }
                $checked_targets[$source_id] = true;
                $source = get_term((int) $source_id, 'category');
                if (!($source instanceof WP_Term)) {
                    $errors[] = sprintf(__('Kategorie-ID %d fehlt.', 'feuer-einsatzberichte'), $source_id);
                    continue;
                }
                $existing = get_term_by('slug', (string) $source->slug, FEU_Einsatz_Report_Taxonomy::TAXONOMY);
                if (!($existing instanceof WP_Term)) {
                    continue;
                }
                $owner = (int) get_term_meta((int) $existing->term_id, FEU_Einsatz_Report_Taxonomy::LEGACY_TERM_META, true);
                $legacy_parent = (int) $source->parent > 0 ? get_term((int) $source->parent, 'category') : null;
                $expected_parent = $legacy_parent instanceof WP_Term
                    ? get_term_by('slug', (string) $legacy_parent->slug, FEU_Einsatz_Report_Taxonomy::TAXONOMY)
                    : null;
                if ($owner !== (int) $source_id
                    || ($legacy_parent instanceof WP_Term && !($expected_parent instanceof WP_Term))
                    || (int) $existing->parent !== (int) ($expected_parent instanceof WP_Term ? $expected_parent->term_id : 0)) {
                    $errors[] = sprintf(__('Slug-Konflikt: %s.', 'feuer-einsatzberichte'), $source->slug);
                }
            }
        }
        foreach ($ids as $post_id) {
            foreach (wp_get_post_categories($post_id) as $legacy_id) {
                if ($root instanceof WP_Term && (int) $legacy_id !== (int) $root->term_id
                    && !term_is_ancestor_of((int) $root->term_id, (int) $legacy_id, 'category')) {
                    continue;
                }
                if (!get_term((int) $legacy_id, 'category') instanceof WP_Term) {
                    $errors[] = sprintf(__('Bericht %d hat ein fehlendes Einsatzstichwort.', 'feuer-einsatzberichte'), $post_id);
                }
            }
        }
        return [
            'report_count' => count($ids),
            'selected_count' => count($legacy_ids),
            'errors' => array_values(array_unique($errors)),
            'already_enabled' => FEU_Einsatz_Report_Taxonomy::enabled(),
        ];
    }

    public static function migrate(bool $create_archive = true): array|WP_Error {
        if (!current_user_can('manage_options')) {
            return new WP_Error('forbidden', __('Keine Berechtigung.', 'feuer-einsatzberichte'));
        }
        if (FEU_Einsatz_Report_Taxonomy::enabled()) {
            return new WP_Error('already_enabled', __('Die neue Einsatzstichwort-Struktur ist bereits aktiv.', 'feuer-einsatzberichte'));
        }
        if (FEU_Einsatz_Post_Migration::is_write_locked()) {
            return new WP_Error('post_migration_active', __('Die Beitragsmigration ist noch aktiv.', 'feuer-einsatzberichte'));
        }
        if (self::needs_manual_recovery()) {
            return new WP_Error('rollback_failed', __('Die fehlgeschlagene Rücksetzung muss zuerst manuell geprüft werden.', 'feuer-einsatzberichte'));
        }
        if (!add_option(self::LOCK_OPTION, (string) time(), '', false)) {
            return new WP_Error('migration_locked', __('Eine Übertragung läuft bereits.', 'feuer-einsatzberichte'));
        }
        try {
            $preflight = self::preflight();
            if ($preflight['errors']) {
                return new WP_Error('preflight_failed', implode(' ', $preflight['errors']));
            }
            $archive = [];
            if ($create_archive) {
                $archive = Feuer_Einsatzberichte_Core::get_instance()->get_backup()->create_archive(
                    __('Sicherung vor Einsatzstichwort-Umstellung', 'feuer-einsatzberichte'), false
                );
                if (is_wp_error($archive)) {
                    return $archive;
                }
            }
            $ids = self::report_ids();
            $previous = [];
            foreach ($ids as $post_id) {
                $previous[$post_id] = [
                    'term_ids' => wp_get_object_terms($post_id, FEU_Einsatz_Report_Taxonomy::TAXONOMY, ['fields' => 'ids']),
                    'primary' => get_post_meta($post_id, FEU_Einsatz_Report_Taxonomy::PRIMARY_META, true),
                    'url' => get_permalink($post_id),
                    'modified' => (string) get_post_field('post_modified_gmt', $post_id),
                    'legacy_terms' => array_map('intval', wp_get_post_categories($post_id)),
                ];
                if (is_wp_error($previous[$post_id]['term_ids'])) {
                    return $previous[$post_id]['term_ids'];
                }
            }
            $old_selected = FEU_Einsatz_Report_Taxonomy::get_selected_ids();
            $previous_run = self::get_run();
            $retry_run_id = 'failed_rolled_back' === ($previous_run['status'] ?? '')
                && (array) ($previous_run['old_selected'] ?? []) === $old_selected
                ? (string) ($previous_run['run_id'] ?? '') : '';
            $run = [
                'run_id' => '' !== $retry_run_id ? $retry_run_id : wp_generate_uuid4(),
                'status' => 'running',
                'started_at' => current_time('mysql', true),
                'actor_id' => get_current_user_id(),
                'archive_key' => is_array($archive) ? (string) ($archive['archive_key'] ?? '') : '',
                'old_selected' => $old_selected,
                'new_selected_before' => get_option(FEU_Einsatz_Report_Taxonomy::SELECTED_OPTION, null),
                'reports' => $previous,
                'processed' => [],
            ];
            update_option(self::RUN_OPTION, $run, false);
            $new_selected = [];
            $failure_domain = 'categories';
            $failure_id = 0;
            try {
                foreach ($run['old_selected'] as $legacy_id) {
                    $failure_id = (int) $legacy_id;
                    $term_id = FEU_Einsatz_Report_Taxonomy::copy_legacy_term((int) $legacy_id);
                    if (is_wp_error($term_id)) {
                        throw new RuntimeException($term_id->get_error_message());
                    }
                    $new_selected[] = (int) $term_id;
                }
                foreach ($ids as $post_id) {
                    $failure_domain = 'reports';
                    $failure_id = (int) $post_id;
                    $copied = FEU_Einsatz_Report_Taxonomy::copy_report_terms($post_id);
                    if (is_wp_error($copied)) {
                        throw new RuntimeException($copied->get_error_message());
                    }
                    if (get_permalink($post_id) !== $previous[$post_id]['url']) {
                        throw new RuntimeException(sprintf('URL changed for report %d.', $post_id));
                    }
                    $run['processed'][] = $post_id;
                    update_option(self::RUN_OPTION, $run, false);
                }
                $failure_domain = 'global';
                $failure_id = 0;
                $current_ids = self::report_ids();
                sort($current_ids);
                $expected_ids = $ids;
                sort($expected_ids);
                if ($current_ids !== $expected_ids) {
                    throw new RuntimeException(__('Während der Übertragung wurden Berichte hinzugefügt oder entfernt.', 'feuer-einsatzberichte'));
                }
                foreach ($ids as $post_id) {
                    $failure_domain = 'reports';
                    $failure_id = (int) $post_id;
                    $current_legacy = array_map('intval', wp_get_post_categories($post_id));
                    $old_legacy = (array) $previous[$post_id]['legacy_terms'];
                    sort($current_legacy);
                    sort($old_legacy);
                    if ((string) get_post_field('post_modified_gmt', $post_id) !== $previous[$post_id]['modified']
                        || $current_legacy !== $old_legacy
                        || get_permalink($post_id) !== $previous[$post_id]['url']) {
                        throw new RuntimeException(sprintf(__('Bericht %d wurde während der Übertragung geändert.', 'feuer-einsatzberichte'), $post_id));
                    }
                }
                $failure_domain = 'global';
                $failure_id = 0;
                update_option(FEU_Einsatz_Report_Taxonomy::SELECTED_OPTION, array_values(array_unique($new_selected)), false);
                update_option(FEU_Einsatz_Report_Taxonomy::ENABLED_OPTION, 1, false);
                $run['cutover_terms'] = [];
                foreach ($ids as $post_id) {
                    $current_terms = wp_get_object_terms($post_id, FEU_Einsatz_Report_Taxonomy::TAXONOMY, ['fields' => 'ids']);
                    if (is_wp_error($current_terms)) {
                        throw new RuntimeException($current_terms->get_error_message());
                    }
                    $run['cutover_terms'][$post_id] = [
                        'ids' => array_values(array_map('intval', $current_terms)),
                        'primary' => (int) get_post_meta($post_id, FEU_Einsatz_Report_Taxonomy::PRIMARY_META, true),
                    ];
                }
                $run['cutover_selected'] = FEU_Einsatz_Report_Taxonomy::get_selected_ids();
                $cutover_term_ids = $run['cutover_selected'];
                foreach ($run['cutover_terms'] as $record) {
                    $cutover_term_ids = array_merge($cutover_term_ids, (array) $record['ids']);
                }
                $run['cutover_term_state'] = [];
                foreach (array_unique(array_map('intval', $cutover_term_ids)) as $term_id) {
                    $run['cutover_term_state'][$term_id] = self::term_snapshot($term_id);
                }
                $run['status'] = 'complete';
                $run['completed_at'] = current_time('mysql', true);
                update_option(self::RUN_OPTION, $run, false);
                Feuer_Einsatzberichte_Core::get_instance()->get_db()->invalidate_statistics_dashboard_cache();
                FEU_Einsatz_Logger::log('keyword_migration_completed', 'system', 0, __('Einsatzstichworte übertragen', 'feuer-einsatzberichte'), [
                    'actor_id' => get_current_user_id(),
                    'report_count' => count($ids),
                    'archive_key' => $run['archive_key'],
                ]);
                return ['report_count' => count($ids), 'archive_key' => $run['archive_key']];
            } catch (Throwable $error) {
                try {
                    self::restore_previous($run);
                    $run['status'] = 'failed_rolled_back';
                } catch (Throwable $rollback_error) {
                    $run['status'] = 'rollback_failed';
                    $run['rollback_error'] = $rollback_error->getMessage();
                }
                $run['error'] = $error->getMessage();
                update_option(self::RUN_OPTION, $run, false);
                try {
                    $run['developer_report'] = self::record_failure((string) $run['run_id'], $failure_domain, $failure_id);
                    update_option(self::RUN_OPTION, $run, false);
                } catch (Throwable $diagnostic_error) {
                    // The failed migration journal must survive a separate logging/mail failure.
                }
                return new WP_Error('keyword_migration_failed', $error->getMessage());
            }
        } catch (Throwable $error) {
            return new WP_Error('keyword_migration_failed', $error->getMessage());
        } finally {
            delete_option(self::LOCK_OPTION);
        }
    }

    private static function restore_previous(array $run): void {
        foreach ((array) ($run['reports'] ?? []) as $post_id => $record) {
            if (!get_post((int) $post_id)) {
                continue;
            }
            $restored = wp_set_object_terms((int) $post_id, array_map('intval', (array) ($record['term_ids'] ?? [])), FEU_Einsatz_Report_Taxonomy::TAXONOMY, false);
            if (is_wp_error($restored)) {
                throw new RuntimeException($restored->get_error_message());
            }
            if ('' === (string) ($record['primary'] ?? '')) {
                delete_post_meta((int) $post_id, FEU_Einsatz_Report_Taxonomy::PRIMARY_META);
            } else {
                update_post_meta((int) $post_id, FEU_Einsatz_Report_Taxonomy::PRIMARY_META, $record['primary']);
            }
        }
        if (null === ($run['new_selected_before'] ?? null)) {
            delete_option(FEU_Einsatz_Report_Taxonomy::SELECTED_OPTION);
        } else {
            update_option(FEU_Einsatz_Report_Taxonomy::SELECTED_OPTION, $run['new_selected_before'], false);
        }
        update_option(FEU_Einsatz_Report_Taxonomy::ENABLED_OPTION, 0, false);
        Feuer_Einsatzberichte_Core::get_instance()->get_db()->invalidate_statistics_dashboard_cache();
    }

    public static function rollback(): array|WP_Error {
        if (!current_user_can('manage_options')) {
            return new WP_Error('forbidden', __('Keine Berechtigung.', 'feuer-einsatzberichte'));
        }
        $run = self::get_run();
        if ('complete' !== ($run['status'] ?? '') || !FEU_Einsatz_Report_Taxonomy::enabled()) {
            return new WP_Error('rollback_unavailable', __('Keine abgeschlossene Übertragung zum Zurücksetzen vorhanden.', 'feuer-einsatzberichte'));
        }
        if (!add_option(self::LOCK_OPTION, (string) time(), '', false)) {
            return new WP_Error('migration_locked', __('Eine Übertragung läuft bereits.', 'feuer-einsatzberichte'));
        }
        try {
            return self::rollback_locked($run);
        } catch (Throwable $error) {
            return new WP_Error('keyword_rollback_failed', $error->getMessage());
        } finally {
            delete_option(self::LOCK_OPTION);
        }
    }

    private static function rollback_locked(array $run): array|WP_Error {
        $current_ids = self::report_ids();
        $original_ids = array_map('intval', array_keys((array) ($run['reports'] ?? [])));
        sort($current_ids);
        sort($original_ids);
        if ($current_ids !== $original_ids) {
            return new WP_Error('reports_changed', __('Seit der Übertragung wurden Berichte hinzugefügt oder gelöscht. Automatischer Rückweg ist nicht mehr sicher.', 'feuer-einsatzberichte'));
        }
        foreach ((array) $run['reports'] as $post_id => $record) {
            if ((string) get_post_field('post_modified_gmt', (int) $post_id) !== (string) ($record['modified'] ?? '')) {
                return new WP_Error('reports_changed', __('Seit der Übertragung wurde ein Bericht bearbeitet. Automatischer Rückweg ist nicht mehr sicher.', 'feuer-einsatzberichte'));
            }
            $terms = wp_get_object_terms((int) $post_id, FEU_Einsatz_Report_Taxonomy::TAXONOMY, ['fields' => 'ids']);
            if (is_wp_error($terms)) {
                return $terms;
            }
            $terms = array_map('intval', $terms);
            $original_terms = array_map('intval', (array) ($run['cutover_terms'][$post_id]['ids'] ?? []));
            sort($terms);
            sort($original_terms);
            if ($terms !== $original_terms
                || (int) get_post_meta((int) $post_id, FEU_Einsatz_Report_Taxonomy::PRIMARY_META, true)
                    !== (int) ($run['cutover_terms'][$post_id]['primary'] ?? 0)) {
                return new WP_Error('keywords_changed', __('Seit der Übertragung wurden Einsatzstichworte geändert. Automatischer Rückweg ist nicht mehr sicher.', 'feuer-einsatzberichte'));
            }
            $legacy_now = array_map('intval', wp_get_post_categories((int) $post_id));
            $legacy_before = array_map('intval', (array) ($record['legacy_terms'] ?? []));
            sort($legacy_now);
            sort($legacy_before);
            if ($legacy_now !== $legacy_before) {
                return new WP_Error('legacy_categories_changed', __('Seit der Übertragung wurden alte Kategorie-Zuordnungen geändert. Automatischer Rückweg ist nicht mehr sicher.', 'feuer-einsatzberichte'));
            }
        }
        $selected = FEU_Einsatz_Report_Taxonomy::get_selected_ids();
        $at_cutover = array_map('intval', (array) ($run['cutover_selected'] ?? []));
        sort($selected);
        sort($at_cutover);
        if ($selected !== $at_cutover) {
            return new WP_Error('keywords_changed', __('Die aktiven Einsatzstichworte wurden nach der Übertragung geändert. Automatischer Rückweg ist nicht mehr sicher.', 'feuer-einsatzberichte'));
        }
        foreach ((array) ($run['cutover_term_state'] ?? []) as $term_id => $snapshot) {
            if (self::term_snapshot((int) $term_id) !== $snapshot) {
                return new WP_Error('keywords_changed', __('Ein Einsatzstichwort wurde nach der Übertragung bearbeitet. Automatischer Rückweg ist nicht mehr sicher.', 'feuer-einsatzberichte'));
            }
        }
        self::restore_previous($run);
        $run['status'] = 'rolled_back';
        $run['rolled_back_at'] = current_time('mysql', true);
        update_option(self::RUN_OPTION, $run, false);
        FEU_Einsatz_Logger::log('keyword_migration_rolled_back', 'system', 0, __('Einsatzstichwort-Umstellung zurückgesetzt', 'feuer-einsatzberichte'), [
            'actor_id' => get_current_user_id(),
            'report_count' => count($current_ids),
        ]);
        return ['report_count' => count($current_ids)];
    }
}
