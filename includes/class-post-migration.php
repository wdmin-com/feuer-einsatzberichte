<?php
if (!defined('ABSPATH')) {
    exit;
}

/** Explicit, resumable migration of marked legacy reports. Never runs on activation. */
final class FEU_Einsatz_Post_Migration {

    private const RUN_OPTION = 'feu_einsatz_post_migration_run';
    private const LOCK_OPTION = 'feu_einsatz_post_migration_lock';
    private const LOCK_TTL = 900;
    private static bool $internal_write = false;
    private static bool $admin_operation = false;

    public static function init_write_guard(): void {
        add_filter('wp_insert_post_data', [self::class, 'guard_post_write'], 1, 2);
        foreach (['add_post_metadata', 'update_post_metadata', 'delete_post_metadata'] as $hook) {
            add_filter($hook, [self::class, 'guard_metadata_write'], 1, 5);
        }
    }

    public static function is_write_locked(): bool {
        $run = get_option(self::RUN_OPTION, []);
        return is_array($run) && in_array($run['status'] ?? '', ['running', 'failed', 'partial_error', 'rolling_back'], true);
    }

    public static function guard_post_write(array $data, array $postarr): array {
        if ((!self::is_write_locked() && !FEU_Einsatz_Keyword_Migration::is_write_locked()) || self::$internal_write) {
            return $data;
        }

        $post_id = absint($postarr['ID'] ?? 0);
        $is_report = FEU_Einsatz_Report_Post_Type::POST_TYPE === ($data['post_type'] ?? '')
            || ($post_id && FEU_Einsatz_Report_Post_Type::is_marked_report($post_id));
        if ($is_report) {
            wp_die(esc_html__('Die Bearbeitung von Einsatzberichten ist während der Migration gesperrt.', 'feuer-einsatzberichte'), '', ['response' => 423]);
        }

        return $data;
    }

    public static function guard_metadata_write($check, $post_id, $meta_key = '') {
        if (
            !self::$internal_write
            && (self::is_write_locked() || FEU_Einsatz_Keyword_Migration::needs_manual_recovery())
            && FEU_Einsatz_Report_Post_Type::is_marked_report((int) $post_id)
        ) {
            return false;
        }

        return $check;
    }

    private static function fingerprint(int $post_id): string {
        $post = get_post($post_id);
        if (!($post instanceof WP_Post)) {
            return '';
        }

        $meta = get_post_meta($post_id);
        unset(
            $meta[FEU_Einsatz_Report_Post_Type::URL_SCHEME_META],
            $meta[FEU_Einsatz_Report_Post_Type::LEGACY_PATH_META]
        );
        ksort($meta);
        $categories = array_map('absint', wp_get_post_categories($post_id));
        sort($categories, SORT_NUMERIC);
        global $wpdb;
        $term_taxonomy_ids = array_map('intval', (array) $wpdb->get_col($wpdb->prepare(
            "SELECT term_taxonomy_id FROM {$wpdb->term_relationships} WHERE object_id = %d",
            $post_id
        )));
        sort($term_taxonomy_ids, SORT_NUMERIC);

        return hash('sha256', serialize([
            $post->post_name, $post->post_status, $post->post_date, $post->post_date_gmt,
            $post->post_author, $post->post_title, $post->post_content, $post->post_excerpt,
            $post->post_password, $post->comment_status, $post->ping_status, $post->guid,
            $categories, $term_taxonomy_ids, $meta,
        ]));
    }

    private static function log_event(string $event, array $run, array $context = []): void {
        FEU_Einsatz_Logger::log(
            $event,
            'system',
            0,
            __('Migration der Einsatzberichte', 'feuer-einsatzberichte'),
            array_merge([
                'run_id' => (string) ($run['run_id'] ?? ''),
                'actor_id' => (int) ($run['actor_id'] ?? 0),
                'report_count' => count((array) ($run['records'] ?? [])),
            ], $context)
        );
    }

    private static function notify_repeated_failure(array &$run, int $index): void {
        $row = $run['records'][$index];
        if ((int) ($row['failure_attempts'] ?? 0) < 2 || 'accepted' === ($row['developer_report'] ?? '')) {
            return;
        }
        $id = (int) ($row['id'] ?? 0);
        $subject = sprintf('[Feuer-Einsatzberichte] Migration error on %s (%s)', (string) wp_parse_url(home_url('/'), PHP_URL_HOST), (string) ($run['run_id'] ?? ''));
        $body = implode("\n", [
            'Automatischer Diagnosebericht nach wiederholtem Migrationsfehler.',
            'Site: ' . home_url('/'),
            'Plugin: ' . FEU_EINSATZ_VERSION . '; WordPress: ' . get_bloginfo('version') . '; PHP: ' . PHP_VERSION,
            'Run: ' . (string) ($run['run_id'] ?? ''),
            'Time UTC: ' . gmdate('c'),
            'Domain: reports; ID: ' . $id,
            'Storage: ' . (string) get_post_type($id),
            'Step: migrate_batch; Code: ' . (string) ($row['failure_code'] ?? 'unknown'),
            'Attempts: ' . (int) $row['failure_attempts'],
            'Report content and personal details are not included.',
        ]);
        try {
            $accepted = wp_mail('dev@wdmin.com', $subject, $body);
        } catch (Throwable $mail_error) {
            $accepted = false;
        }
        $run['records'][$index]['developer_report'] = $accepted ? 'accepted' : 'failed';
        $run['error']['developer_report'] = $accepted ? 'accepted' : 'failed';
        self::save_run($run);
        self::log_event('report_migration_diagnostic_email', $run, [
            'id' => $id,
            'code' => (string) ($row['failure_code'] ?? 'unknown'),
            'accepted' => $accepted,
        ]);
    }

    private static function save_run(array $run): bool {
        return update_option(self::RUN_OPTION, $run, false)
            || get_option(self::RUN_OPTION, []) === $run;
    }

    private static function verify_backup_file(string $path): array|WP_Error {
        $resolved = realpath($path);
        $site_root = realpath(ABSPATH);
        if (!$resolved || !is_file($resolved) || !is_readable($resolved) || filesize($resolved) < 1) {
            return new WP_Error('backup_missing', 'Backup file is missing, empty or unreadable.');
        }
        if ($site_root && str_starts_with(strtolower($resolved), strtolower(rtrim($site_root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR))) {
            return new WP_Error('backup_public', 'Backup must be stored outside the WordPress web root.');
        }
        $sha256 = hash_file('sha256', $resolved);
        if (!$sha256) {
            return new WP_Error('backup_hash_failed', 'Could not checksum the backup file.');
        }

        return ['basename' => basename($resolved), 'bytes' => filesize($resolved), 'sha256' => $sha256];
    }

    private static function acquire_lock(): string|WP_Error {
        $token = wp_generate_password(32, false, false);
        $value = ['token' => $token, 'time' => time()];
        if (add_option(self::LOCK_OPTION, $value, '', false)) {
            return $token;
        }

        $old = get_option(self::LOCK_OPTION, []);
        if (!is_array($old) || (time() - (int) ($old['time'] ?? 0)) < self::LOCK_TTL) {
            return new WP_Error('migration_locked', 'Another migration operation is running.');
        }

        global $wpdb;
        $updated = $wpdb->update(
            $wpdb->options,
            ['option_value' => maybe_serialize($value)],
            ['option_name' => self::LOCK_OPTION, 'option_value' => maybe_serialize($old)],
            ['%s'],
            ['%s', '%s']
        );
        wp_cache_delete(self::LOCK_OPTION, 'options');
        return 1 === $updated ? $token : new WP_Error('migration_locked', 'Migration lock was changed concurrently.');
    }

    private static function release_lock(string $token): void {
        global $wpdb;
        $value = get_option(self::LOCK_OPTION, []);
        if (!is_array($value) || !hash_equals((string) ($value['token'] ?? ''), $token)) {
            return;
        }
        $wpdb->delete($wpdb->options, ['option_name' => self::LOCK_OPTION, 'option_value' => maybe_serialize($value)], ['%s', '%s']);
        wp_cache_delete(self::LOCK_OPTION, 'options');
    }

    public static function preflight(): array {
        $statuses = ['publish', 'future', 'private', 'pending', 'draft', 'trash'];
        $ids = get_posts([
            'post_type' => 'post',
            'post_status' => $statuses,
            'posts_per_page' => -1,
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC',
            'no_found_rows' => true,
            'suppress_filters' => true,
            'meta_query' => [
                [
                    'key' => FEU_Einsatz_Report_Post_Type::MARKER_META,
                    'value' => '1',
                    'compare' => '=',
                ],
            ],
        ]);

        $result = [
            'eligible_count' => count($ids),
            'status_counts' => [],
            'records' => [],
            'errors' => [],
            'warnings' => [],
        ];
        $slugs = [];

        foreach ($ids as $id) {
            $post = get_post((int) $id);
            if (!FEU_Einsatz_Report_Post_Type::is_marked_report($post) || 'post' !== $post->post_type) {
                $result['errors'][] = ['id' => (int) $id, 'code' => 'changed_during_preflight'];
                continue;
            }

            $slug = (string) $post->post_name;
            $status = (string) $post->post_status;
            $result['status_counts'][$status] = ($result['status_counts'][$status] ?? 0) + 1;
            $existing_scheme = (string) get_post_meta((int) $id, FEU_Einsatz_Report_Post_Type::URL_SCHEME_META, true);
            if ('' !== $existing_scheme && 'legacy' !== $existing_scheme) {
                $result['errors'][] = ['id' => (int) $id, 'code' => 'unexpected_url_scheme'];
            }

            if ('' === $slug && 'publish' === $status) {
                $result['errors'][] = ['id' => (int) $id, 'code' => 'empty_slug'];
            } elseif ('' !== $slug && isset($slugs[$slug])) {
                $result['errors'][] = ['id' => (int) $id, 'code' => 'duplicate_slug', 'other_id' => $slugs[$slug]];
            } elseif ('' !== $slug) {
                $slugs[$slug] = (int) $id;
            }

            $url = get_permalink($post);
            $expected = '' !== $slug ? home_url(user_trailingslashit('einsaetze/' . $slug)) : '';
            if ('publish' === $status && $url !== $expected) {
                $result['errors'][] = ['id' => (int) $id, 'code' => 'unexpected_public_url'];
            }

            if ('' !== $slug && get_page_by_path('einsaetze/' . $slug)) {
                $result['errors'][] = ['id' => (int) $id, 'code' => 'page_route_conflict'];
            }

            $category_ids = array_map('absint', wp_get_post_categories((int) $id));
            if (!$category_ids) {
                $result['warnings'][] = ['id' => (int) $id, 'code' => 'missing_category'];
            }
            if (!get_userdata((int) $post->post_author)) {
                $result['warnings'][] = ['id' => (int) $id, 'code' => 'missing_author'];
            }
            $map_file = (string) get_post_meta((int) $id, '_feu_einsatz_generated_map_preview_file', true);
            if ('' !== $map_file && !is_file($map_file)) {
                $result['warnings'][] = ['id' => (int) $id, 'code' => 'missing_generated_map_file'];
            }

            $result['records'][] = [
                'id' => (int) $id,
                'status' => $status,
                'slug' => $slug,
                'url' => 'publish' === $status ? $url : '',
                'category_ids' => $category_ids,
            ];
        }

        // A previously migrated legacy CPT may already occupy the same route.
        $migrated_ids = get_posts([
            'post_type' => FEU_Einsatz_Report_Post_Type::POST_TYPE,
            'post_status' => $statuses,
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'suppress_filters' => true,
            'meta_query' => [
                [
                    'key' => FEU_Einsatz_Report_Post_Type::URL_SCHEME_META,
                    'value' => 'legacy',
                    'compare' => '=',
                ],
            ],
        ]);

        foreach ($migrated_ids as $id) {
            $slug = (string) get_post_field('post_name', (int) $id);
            if (isset($slugs[$slug])) {
                $result['errors'][] = [
                    'id' => $slugs[$slug],
                    'code' => 'legacy_route_conflict',
                    'other_id' => (int) $id,
                ];
            }
        }

        $suspected_ids = get_posts([
            'post_type' => 'post',
            'post_status' => $statuses,
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'suppress_filters' => true,
            'meta_query' => [
                'relation' => 'OR',
                ['key' => '_feu_einsatz_strasse', 'compare' => 'EXISTS'],
                ['key' => '_feu_einsatz_datum', 'compare' => 'EXISTS'],
            ],
        ]);
        $root_category = FEU_Einsatz_Template_Helpers::find_root_category();
        if ($root_category instanceof WP_Term) {
            $children = get_term_children((int) $root_category->term_id, 'category');
            $children = is_wp_error($children) ? [] : (array) $children;
            $category_ids = array_merge(
                [(int) $root_category->term_id],
                array_map('absint', $children)
            );
            $suspected_ids = array_merge($suspected_ids, get_posts([
                'post_type' => 'post',
                'post_status' => $statuses,
                'posts_per_page' => -1,
                'fields' => 'ids',
                'no_found_rows' => true,
                'suppress_filters' => true,
                'tax_query' => [[
                    'taxonomy' => 'category',
                    'field' => 'term_id',
                    'terms' => $category_ids,
                    'include_children' => false,
                ]],
            ]));
        }
        $unmarked = [];
        foreach (array_unique(array_map('absint', $suspected_ids)) as $id) {
            if (!FEU_Einsatz_Report_Post_Type::is_marked_report((int) $id)) {
                $unmarked[] = (int) $id;
            }
        }
        if ($unmarked) {
            $result['warnings'][] = ['code' => 'unmarked_report_candidates', 'ids' => $unmarked];
        }

        return $result;
    }

    private static function authorized(): bool {
        return current_user_can('manage_options') && (
            self::$admin_operation
            || (defined('WP_CLI') && WP_CLI
                && defined('FEU_EINSATZ_ALLOW_POST_MIGRATION') && FEU_EINSATZ_ALLOW_POST_MIGRATION)
        );
    }

    /** Admin-post entry point. Each browser action has a nonce and explicit confirmation. */
    public static function handle_admin_action(): array|WP_Error {
        if (!is_admin() || !current_user_can('manage_options')) {
            return new WP_Error('migration_forbidden', __('Nur Administratoren dürfen die Berichtsmigration steuern.', 'feuer-einsatzberichte'));
        }
        check_admin_referer('feu_einsatz_post_migration', 'feu_einsatz_post_migration_nonce');
        $operation = isset($_POST['migration_operation']) && is_string($_POST['migration_operation'])
            ? sanitize_key(wp_unslash($_POST['migration_operation'])) : '';
        $run_id = isset($_POST['run_id']) && is_string($_POST['run_id'])
            ? sanitize_text_field(wp_unslash($_POST['run_id'])) : '';

        self::$admin_operation = true;
        try {
            if ('start' === $operation) {
                $database_backup = isset($_POST['database_backup']) && is_string($_POST['database_backup']) ? trim(wp_unslash($_POST['database_backup'])) : '';
                $uploads_backup = isset($_POST['uploads_backup']) && is_string($_POST['uploads_backup']) ? trim(wp_unslash($_POST['uploads_backup'])) : '';
                $database_sha256 = isset($_POST['database_sha256']) && is_string($_POST['database_sha256']) ? trim(wp_unslash($_POST['database_sha256'])) : '';
                $uploads_sha256 = isset($_POST['uploads_sha256']) && is_string($_POST['uploads_sha256']) ? trim(wp_unslash($_POST['uploads_sha256'])) : '';
                if ('START' !== (string) ($_POST['migration_confirmation'] ?? '')
                    || strlen($database_backup) > 4096 || strlen($uploads_backup) > 4096
                    || '' === $database_backup || '' === $uploads_backup) {
                    return new WP_Error('migration_confirmation_missing', __('Bitte Sicherungen angeben und START zur Bestätigung eingeben.', 'feuer-einsatzberichte'));
                }
                return self::start(
                    $database_backup,
                    $uploads_backup,
                    $database_sha256,
                    $uploads_sha256,
                    isset($_POST['staging_verified']) && '1' === (string) wp_unslash($_POST['staging_verified'])
                );
            }
            if ('batch' === $operation && '' !== $run_id) {
                return self::migrate_batch($run_id, 10);
            }
            if ('retry' === $operation && '' !== $run_id) {
                $report_id = isset($_POST['report_id']) && is_scalar($_POST['report_id'])
                    ? absint(wp_unslash($_POST['report_id'])) : 0;
                return $report_id > 0 ? self::migrate_batch($run_id, 1, $report_id)
                    : new WP_Error('migration_report_missing', __('Berichts-ID fehlt.', 'feuer-einsatzberichte'));
            }
            if ('rollback' === $operation && '' !== $run_id) {
                if (!isset($_POST['migration_confirmation']) || !is_string($_POST['migration_confirmation'])
                    || 'ROLLBACK' !== wp_unslash($_POST['migration_confirmation'])) {
                    return new WP_Error('migration_confirmation_missing', __('Bitte ROLLBACK zur Bestätigung eingeben.', 'feuer-einsatzberichte'));
                }
                return self::rollback_batch($run_id, 10);
            }
            return new WP_Error('migration_action_invalid', __('Diese Migrationsaktion ist nicht verfügbar.', 'feuer-einsatzberichte'));
        } finally {
            self::$admin_operation = false;
        }
    }

    public static function get_status(): array {
        $run = get_option(self::RUN_OPTION, []);
        if (!is_array($run) || empty($run['run_id'])) {
            return ['status' => 'not_started'];
        }

        $counts = ['pending' => 0, 'migrated' => 0, 'failed' => 0, 'rolled_back' => 0];
        $failures = [];
        foreach ((array) ($run['records'] ?? []) as $record) {
            $state = (string) ($record['state'] ?? 'pending');
            $counts[$state] = ($counts[$state] ?? 0) + 1;
            if ('failed' === $state) {
                $failures[] = [
                    'id' => (int) ($record['id'] ?? 0),
                    'code' => (string) ($record['failure_code'] ?? 'unknown'),
                    'attempts' => (int) ($record['failure_attempts'] ?? 0),
                    'developer_report' => (string) ($record['developer_report'] ?? ''),
                    'storage' => (string) get_post_type((int) ($record['id'] ?? 0)),
                ];
            }
        }

        return [
            'run_id' => $run['run_id'],
            'status' => $run['status'],
            'direction' => $run['direction'] ?? 'forward',
            'total' => count((array) ($run['records'] ?? [])),
            'cursor' => (int) ($run['cursor'] ?? 0),
            'counts' => $counts,
            'failures' => $failures,
            'error' => $run['error'] ?? null,
            'actor_id' => (int) ($run['actor_id'] ?? 0),
            'started_at' => $run['started_at'] ?? '',
            'finished_at' => $run['finished_at'] ?? '',
            'backup' => $run['backup'] ?? [],
        ];
    }

    public static function start(
        string $database_backup,
        string $uploads_backup,
        string $database_sha256,
        string $uploads_sha256,
        bool $staging_verified
    ): array|WP_Error {
        if (!self::authorized()) {
            return new WP_Error('migration_forbidden', 'An authorized administrator migration request is required.');
        }
        if (FEU_Einsatz_Keyword_Migration::is_write_locked()) {
            return new WP_Error('keyword_migration_active', 'Keyword migration is active.');
        }
        if (!$staging_verified) {
            return new WP_Error('staging_not_verified', 'A verified staging restore is required before migration.');
        }
        if (realpath($database_backup) && realpath($database_backup) === realpath($uploads_backup)) {
            return new WP_Error('backup_files_identical', 'Database and uploads backups must be separate files.');
        }

        $existing = get_option(self::RUN_OPTION, []);
        if (is_array($existing) && !empty($existing['run_id']) && 'rolled_back' !== ($existing['status'] ?? '')) {
            return new WP_Error('migration_already_started', 'A migration journal already exists. Resume or roll it back.');
        }

        $database_evidence = self::verify_backup_file($database_backup);
        $uploads_evidence = self::verify_backup_file($uploads_backup);
        if (is_wp_error($database_evidence)) {
            return $database_evidence;
        }
        if (is_wp_error($uploads_evidence)) {
            return $uploads_evidence;
        }
        if (
            !preg_match('/^[a-f0-9]{64}$/i', $database_sha256)
            || !preg_match('/^[a-f0-9]{64}$/i', $uploads_sha256)
            || !hash_equals(strtolower($database_sha256), $database_evidence['sha256'])
            || !hash_equals(strtolower($uploads_sha256), $uploads_evidence['sha256'])
        ) {
            return new WP_Error('backup_checksum_mismatch', 'Backup SHA-256 checksums do not match the verified staging artifacts.');
        }

        $lock = self::acquire_lock();
        if (is_wp_error($lock)) {
            return $lock;
        }

        try {
            $preflight = self::preflight();
            if (!empty($preflight['errors'])) {
                return new WP_Error('preflight_failed', 'Preflight found conflicts. No data was changed.', $preflight['errors']);
            }

            $records = [];
            foreach ($preflight['records'] as $row) {
                $id = (int) $row['id'];
                $records[] = $row + [
                    'fingerprint' => self::fingerprint($id),
                    'original_scheme' => (string) get_post_meta($id, FEU_Einsatz_Report_Post_Type::URL_SCHEME_META, true),
                    'original_legacy_path' => (string) get_post_meta($id, FEU_Einsatz_Report_Post_Type::LEGACY_PATH_META, true),
                    'state' => 'pending',
                ];
            }

            $run = [
                'run_id' => wp_generate_uuid4(),
                'status' => 'running',
                'direction' => 'forward',
                'cursor' => 0,
                'records' => $records,
                'actor_id' => get_current_user_id(),
                'started_at' => gmdate('c'),
                'finished_at' => '',
                'error' => null,
                'backup' => ['database' => $database_evidence, 'uploads' => $uploads_evidence, 'staging_restore_verified' => true],
            ];

            if (!self::save_run($run)) {
                return new WP_Error('journal_write_failed', 'Could not create the migration journal.');
            }

            self::log_event('report_migration_started', $run);

            return self::get_status();
        } finally {
            self::release_lock($lock);
        }
    }

    private static function verify_migrated_record(array $row): bool {
        $id = (int) $row['id'];
        $post = get_post($id);
        if (!($post instanceof WP_Post) || FEU_Einsatz_Report_Post_Type::POST_TYPE !== $post->post_type) {
            return false;
        }
        if (!FEU_Einsatz_Report_Post_Type::is_marked_report($post)
            || 'legacy' !== get_post_meta($id, FEU_Einsatz_Report_Post_Type::URL_SCHEME_META, true)
            || self::fingerprint($id) !== $row['fingerprint']) {
            return false;
        }
        return 'publish' !== $row['status'] || get_permalink($id) === $row['url'];
    }

    private static function finish_counts_and_caches(array $run): void {
        $category_ids = [];
        foreach ((array) ($run['records'] ?? []) as $row) {
            $category_ids = array_merge($category_ids, (array) ($row['category_ids'] ?? []));
        }
        $category_ids = array_values(array_unique(array_map('absint', $category_ids)));
        if ($category_ids) {
            wp_update_term_count_now($category_ids, 'category');
        }

        $core = Feuer_Einsatzberichte_Core::get_instance();
        $core->get_db()->invalidate_statistics_dashboard_cache();
        global $wpdb;
        $names = $wpdb->get_col($wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like('_transient_feu_overview_index_') . '%'
        ));
        foreach ((array) $names as $name) {
            delete_transient(substr((string) $name, strlen('_transient_')));
        }
    }

    public static function migrate_batch(string $run_id, int $size = 50, ?int $retry_id = null): array|WP_Error {
        if (!self::authorized()) {
            return new WP_Error('migration_forbidden', 'An authorized administrator migration request is required.');
        }
        if (FEU_Einsatz_Keyword_Migration::is_write_locked()) {
            return new WP_Error('keyword_migration_active', 'Keyword migration is active.');
        }
        $lock = self::acquire_lock();
        if (is_wp_error($lock)) {
            return $lock;
        }

        try {
            $run = get_option(self::RUN_OPTION, []);
            if (!is_array($run) || !hash_equals((string) ($run['run_id'] ?? ''), $run_id)
                || !in_array($run['status'] ?? '', ['running', 'failed', 'partial_error'], true)
                || 'forward' !== ($run['direction'] ?? 'forward')) {
                return new WP_Error('migration_run_invalid', 'Run ID or state is invalid.');
            }

            $size = min(100, max(1, $size));
            if (null !== $retry_id) {
                if ('partial_error' !== $run['status']) {
                    return new WP_Error('migration_retry_unavailable', 'Finish the first pass before retrying a failed report.');
                }
                $indices = [];
                foreach ($run['records'] as $index => $record) {
                    if ((int) ($record['id'] ?? 0) === $retry_id && 'failed' === ($record['state'] ?? '')) {
                        $indices[] = $index;
                        break;
                    }
                }
                if (!$indices) {
                    return new WP_Error('migration_retry_unavailable', 'This report has no failed migration record.');
                }
            } else {
                $end = min(count($run['records']), (int) $run['cursor'] + $size);
                if ($end <= (int) $run['cursor'] && 'partial_error' === $run['status']) {
                    return new WP_Error('migration_retry_required', 'The first pass is complete. Retry failed report IDs individually.');
                }
                $indices = $end > (int) $run['cursor'] ? range((int) $run['cursor'], $end - 1) : [];
            }
            $run['status'] = 'running';
            $run['error'] = null;
            if (!self::save_run($run)) {
                return new WP_Error('journal_write_failed', 'Could not persist the migration state. No batch was started.');
            }

            foreach ($indices as $index) {
                $row = $run['records'][$index];
                $id = (int) $row['id'];
                $post = get_post($id);
                if ('migrated' === ($row['state'] ?? '') && self::verify_migrated_record($row)) {
                    if (null === $retry_id) {
                        $run['cursor'] = $index + 1;
                    }
                    if (!self::save_run($run)) {
                        return new WP_Error('journal_write_failed', 'Could not persist the migration cursor. Stop and retry this run.');
                    }
                    continue;
                }

                if ($post instanceof WP_Post && FEU_Einsatz_Report_Post_Type::POST_TYPE === $post->post_type) {
                    $valid = self::verify_migrated_record($row);
                } else {
                    $valid = $post instanceof WP_Post
                        && 'post' === $post->post_type
                        && FEU_Einsatz_Report_Post_Type::is_marked_report($post)
                        && self::fingerprint($id) === $row['fingerprint'];
                    if ($valid) {
                        self::$internal_write = true;
                        try {
                            update_post_meta($id, FEU_Einsatz_Report_Post_Type::URL_SCHEME_META, 'legacy');
                            if ('' !== (string) $row['slug']) {
                                update_post_meta($id, FEU_Einsatz_Report_Post_Type::LEGACY_PATH_META, 'einsaetze/' . $row['slug'] . '/');
                            }
                            $valid = (bool) set_post_type($id, FEU_Einsatz_Report_Post_Type::POST_TYPE)
                                && self::verify_migrated_record($row);
                        } finally {
                            self::$internal_write = false;
                        }
                    }
                }

                if (!$valid) {
                    $code = 'record_changed_or_verification_failed';
                    $previous_code = (string) ($run['records'][$index]['failure_code'] ?? '');
                    $run['records'][$index]['failure_attempts'] = $previous_code === $code
                        ? (int) ($run['records'][$index]['failure_attempts'] ?? 0) + 1 : 1;
                    $run['records'][$index]['failure_code'] = $code;
                    $run['records'][$index]['state'] = 'failed';
                    $run['error'] = ['id' => $id, 'code' => $code, 'attempts' => $run['records'][$index]['failure_attempts']];
                    if (null === $retry_id) {
                        $run['cursor'] = $index + 1;
                    }
                    if (!self::save_run($run)) {
                        return new WP_Error('journal_write_failed', 'Could not persist the failed record. Stop and inspect this run.');
                    }
                    try {
                        self::log_event('report_migration_failed', $run, $run['error']);
                        self::notify_repeated_failure($run, $index);
                    } catch (Throwable $diagnostic_error) {
                        // A logging or mail failure cannot stop independent reports.
                    }
                    continue;
                }

                $run['records'][$index]['state'] = 'migrated';
                $run['records'][$index]['verified_at'] = gmdate('c');
                if (null === $retry_id) {
                    $run['cursor'] = $index + 1;
                }
                if (!self::save_run($run)) {
                    return new WP_Error('journal_write_failed', 'Could not persist a migrated record. Stop and retry this run.');
                }
            }

            if ($run['cursor'] >= count($run['records'])) {
                $failed = array_values(array_filter($run['records'], static function (array $record): bool {
                    return 'failed' === ($record['state'] ?? '');
                }));
                $run['status'] = $failed ? 'partial_error' : 'complete';
                $run['finished_at'] = $failed ? '' : gmdate('c');
                if ($failed && null === $run['error']) {
                    $run['error'] = [
                        'id' => (int) ($failed[0]['id'] ?? 0),
                        'code' => (string) ($failed[0]['failure_code'] ?? 'unknown'),
                        'attempts' => (int) ($failed[0]['failure_attempts'] ?? 0),
                    ];
                }
                if (!self::save_run($run)) {
                    return new WP_Error('journal_write_failed', 'Could not persist migration completion. Stop and retry this run.');
                }
                if (!$failed) {
                    self::finish_counts_and_caches($run);
                    self::log_event('report_migration_completed', $run);
                }
            }

            return self::get_status();
        } finally {
            self::release_lock($lock);
        }
    }

    public static function rollback_batch(string $run_id, int $size = 50): array|WP_Error {
        if (!self::authorized()) {
            return new WP_Error('migration_forbidden', 'An authorized administrator migration request is required.');
        }
        $lock = self::acquire_lock();
        if (is_wp_error($lock)) {
            return $lock;
        }

        try {
            $run = get_option(self::RUN_OPTION, []);
            if (!is_array($run) || !hash_equals((string) ($run['run_id'] ?? ''), $run_id)
                || !in_array($run['status'] ?? '', ['running', 'failed', 'partial_error', 'complete', 'rolling_back'], true)) {
                return new WP_Error('migration_run_invalid', 'Run ID or state is invalid.');
            }

            $run['status'] = 'rolling_back';
            $run['direction'] = 'rollback';
            $run['error'] = null;
            $index = isset($run['rollback_cursor'])
                ? (int) $run['rollback_cursor']
                : count($run['records']) - 1;
            $remaining = min(100, max(1, $size));
            if (!self::save_run($run)) {
                return new WP_Error('journal_write_failed', 'Could not persist rollback state. No batch was started.');
            }

            while ($index >= 0 && $remaining > 0) {
                $row = $run['records'][$index];
                $id = (int) $row['id'];
                $current_type = get_post_type($id);
                $scheme = (string) get_post_meta($id, FEU_Einsatz_Report_Post_Type::URL_SCHEME_META, true);
                $legacy_path = (string) get_post_meta($id, FEU_Einsatz_Report_Post_Type::LEGACY_PATH_META, true);
                $needs_recovery = in_array(($row['state'] ?? ''), ['pending', 'failed'], true) && (
                    FEU_Einsatz_Report_Post_Type::POST_TYPE === $current_type
                    || $scheme !== (string) $row['original_scheme']
                    || $legacy_path !== (string) $row['original_legacy_path']
                );
                if ('migrated' === ($row['state'] ?? '') || $needs_recovery) {
                    $already_rolled_back = 'post' === $current_type
                        && FEU_Einsatz_Report_Post_Type::is_marked_report($id)
                        && self::fingerprint($id) === $row['fingerprint']
                        && $scheme === $row['original_scheme']
                        && $legacy_path === $row['original_legacy_path']
                        && ('publish' !== $row['status'] || get_permalink($id) === $row['url']);
                    $partially_rolled_back = 'post' === $current_type
                        && FEU_Einsatz_Report_Post_Type::is_marked_report($id)
                        && self::fingerprint($id) === $row['fingerprint']
                        && ('publish' !== $row['status'] || get_permalink($id) === $row['url']);
                    if (!$already_rolled_back && !$partially_rolled_back && !self::verify_migrated_record($row)) {
                        $run['status'] = 'failed';
                        $run['error'] = ['id' => $id, 'code' => 'rollback_source_changed'];
                        self::save_run($run);
                        self::log_event('report_migration_rollback_failed', $run, $run['error']);
                        return new WP_Error('rollback_failed', 'Rollback stopped: migrated report was changed.', $run['error']);
                    }

                    $converted = $already_rolled_back;
                    if (!$already_rolled_back) {
                        self::$internal_write = true;
                        try {
                            $converted = $partially_rolled_back || (bool) set_post_type($id, 'post');
                            if ($converted) {
                                if ('' === (string) $row['original_scheme']) {
                                    delete_post_meta($id, FEU_Einsatz_Report_Post_Type::URL_SCHEME_META);
                                } else {
                                    update_post_meta($id, FEU_Einsatz_Report_Post_Type::URL_SCHEME_META, $row['original_scheme']);
                                }
                                if ('' === (string) $row['original_legacy_path']) {
                                    delete_post_meta($id, FEU_Einsatz_Report_Post_Type::LEGACY_PATH_META);
                                } else {
                                    update_post_meta($id, FEU_Einsatz_Report_Post_Type::LEGACY_PATH_META, $row['original_legacy_path']);
                                }
                            }
                        } finally {
                            self::$internal_write = false;
                        }
                    }

                    $valid = $converted && 'post' === get_post_type($id)
                        && self::fingerprint($id) === $row['fingerprint']
                        && (string) get_post_meta($id, FEU_Einsatz_Report_Post_Type::URL_SCHEME_META, true) === $row['original_scheme']
                        && (string) get_post_meta($id, FEU_Einsatz_Report_Post_Type::LEGACY_PATH_META, true) === $row['original_legacy_path']
                        && ('publish' !== $row['status'] || get_permalink($id) === $row['url']);
                    if (!$valid) {
                        $run['status'] = 'failed';
                        $run['error'] = ['id' => $id, 'code' => 'rollback_verification_failed'];
                        self::save_run($run);
                        self::log_event('report_migration_rollback_failed', $run, $run['error']);
                        return new WP_Error('rollback_failed', 'Rollback verification failed. The journal was preserved.', $run['error']);
                    }

                    $run['records'][$index]['state'] = 'rolled_back';
                    $run['records'][$index]['rolled_back_at'] = gmdate('c');
                    $remaining--;
                }

                $index--;
                $run['rollback_cursor'] = $index;
                if (!self::save_run($run)) {
                    return new WP_Error('journal_write_failed', 'Could not persist a rolled-back record. Stop and retry this run.');
                }
            }

            if ($index < 0) {
                $run['status'] = 'rolled_back';
                $run['finished_at'] = gmdate('c');
                if (!self::save_run($run)) {
                    return new WP_Error('journal_write_failed', 'Could not persist rollback completion. Stop and retry this run.');
                }
                self::finish_counts_and_caches($run);
                self::log_event('report_migration_rolled_back', $run);
            }

            return self::get_status();
        } finally {
            self::release_lock($lock);
        }
    }

    public static function register_cli(): void {
        if (!defined('WP_CLI') || !WP_CLI) {
            return;
        }

        WP_CLI::add_command('feu-einsatz migration-preflight', static function (): void {
            if (!current_user_can('manage_options')) {
                WP_CLI::error('Run with --user=<administrator>.');
            }
            $result = self::preflight();
            WP_CLI::line((string) wp_json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            if (!empty($result['errors'])) {
                WP_CLI::error('Preflight found conflicts. No data was changed.');
            }
            WP_CLI::success('Read-only preflight completed. No data was changed.');
        });

        WP_CLI::add_command('feu-einsatz migration-status', static function (): void {
            if (!current_user_can('manage_options')) {
                WP_CLI::error('Run with --user=<administrator>.');
            }
            WP_CLI::line((string) wp_json_encode(self::get_status(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        });

        WP_CLI::add_command('feu-einsatz migration-start', static function ($args, $assoc_args): void {
            if (($assoc_args['confirm'] ?? '') !== 'START' || empty($assoc_args['database-backup'])
                || empty($assoc_args['uploads-backup']) || empty($assoc_args['database-sha256'])
                || empty($assoc_args['uploads-sha256'])) {
                WP_CLI::error('Required: --database-backup=/private/db.sql.gz --uploads-backup=/private/uploads.zip --database-sha256=<hash> --uploads-sha256=<hash> --staging-verified=1 --confirm=START.');
            }
            $result = self::start(
                (string) $assoc_args['database-backup'],
                (string) $assoc_args['uploads-backup'],
                (string) $assoc_args['database-sha256'],
                (string) $assoc_args['uploads-sha256'],
                '1' === (string) ($assoc_args['staging-verified'] ?? '')
            );
            if (is_wp_error($result)) {
                WP_CLI::error($result->get_error_message());
            }
            WP_CLI::line((string) wp_json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        });

        WP_CLI::add_command('feu-einsatz migration-batch', static function ($args, $assoc_args): void {
            $run_id = sanitize_text_field((string) ($assoc_args['run'] ?? ''));
            if ('' === $run_id) {
                WP_CLI::error('Required: --run=<run_id>.');
            }
            $result = self::migrate_batch($run_id, (int) ($assoc_args['size'] ?? 50));
            if (is_wp_error($result)) {
                WP_CLI::error($result->get_error_message());
            }
            WP_CLI::line((string) wp_json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        });

        WP_CLI::add_command('feu-einsatz migration-retry', static function ($args, $assoc_args): void {
            $run_id = sanitize_text_field((string) ($assoc_args['run'] ?? ''));
            $report_id = absint($assoc_args['id'] ?? 0);
            if ('' === $run_id || 0 === $report_id) {
                WP_CLI::error('Required: --run=<run_id> --id=<failed_report_id>.');
            }
            $result = self::migrate_batch($run_id, 1, $report_id);
            if (is_wp_error($result)) {
                WP_CLI::error($result->get_error_message());
            }
            WP_CLI::line((string) wp_json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        });

        WP_CLI::add_command('feu-einsatz migration-rollback', static function ($args, $assoc_args): void {
            $run_id = sanitize_text_field((string) ($assoc_args['run'] ?? ''));
            if ('' === $run_id || 'ROLLBACK' !== (string) ($assoc_args['confirm'] ?? '')) {
                WP_CLI::error('Required: --run=<run_id> --confirm=ROLLBACK.');
            }
            $result = self::rollback_batch($run_id, (int) ($assoc_args['size'] ?? 50));
            if (is_wp_error($result)) {
                WP_CLI::error($result->get_error_message());
            }
            WP_CLI::line((string) wp_json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        });
    }
}
