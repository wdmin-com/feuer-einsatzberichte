<?php
if (!defined('ABSPATH')) {
    exit;
}

/** Editorial queue, background-job overview and participant data controls. */
class FEU_Einsatz_Operations_Center {
    private FEU_Einsatz_Admin $admin;
    private FEU_Einsatz_Database $db;

    public function __construct(FEU_Einsatz_Admin $admin, FEU_Einsatz_Database $db) {
        $this->admin = $admin;
        $this->db = $db;
        add_action('admin_init', [$this, 'redirect_legacy_page']);
        add_action('admin_post_feu_einsatz_retry_job', [$this, 'handle_retry_job']);
        add_action('admin_post_feu_einsatz_participant_data', [$this, 'handle_participant_data']);
        add_action('admin_post_feu_einsatz_privacy_settings', [$this, 'handle_privacy_settings']);
        add_filter('site_status_tests', [$this, 'register_site_health_test']);
    }

    public static function can_view(): bool {
        return current_user_can('edit_posts')
            && FEU_Einsatz_Admin::current_user_can_access_plugin_section('dashboard')
            && FEU_Einsatz_Admin::current_user_can_access_plugin_section('reports');
    }

    public function redirect_legacy_page(): void {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if ('feu-einsatz-arbeitszentrale' !== $page || !self::can_view()) {
            return;
        }
        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'status';
        if (!in_array($tab, ['status', 'queue', 'privacy'], true) || ('privacy' === $tab && !current_user_can('manage_options'))) {
            $tab = 'status';
        }
        $args = ['page' => 'feuer-einsatzberichte', 'ops_tab' => $tab];
        if ('queue' === $tab && isset($_GET['report_status'])) {
            $args['report_status'] = sanitize_key(wp_unslash($_GET['report_status']));
        }
        if ('privacy' === $tab && isset($_GET['s'])) {
            $args['s'] = sanitize_text_field(wp_unslash($_GET['s']));
        }
        if (isset($_GET['paged'])) {
            $args['paged'] = max(1, absint(wp_unslash($_GET['paged'])));
        }
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    private function get_reports(array $statuses, int $page = 1, int $per_page = 20): WP_Query {
        return new WP_Query([
            'post_type' => FEU_Einsatz_Report_Post_Type::readable_post_types(),
            'post_status' => $statuses,
            'posts_per_page' => $per_page,
            'paged' => max(1, $page),
            'orderby' => 'modified',
            'order' => 'DESC',
            'meta_query' => [[
                'key' => FEU_Einsatz_Report_Post_Type::MARKER_META,
                'value' => '1',
            ]],
        ]);
    }

    private function get_edit_url(int $post_id): string {
        if (!FEU_Einsatz_Admin::current_user_can_access_plugin_section('create_report')) {
            return (string) get_edit_post_link($post_id, 'raw');
        }
        return add_query_arg(
            ['page' => 'feu-einsatz-bericht-bearbeiten', 'post' => $post_id],
            admin_url('admin.php')
        );
    }

    private function get_report_jobs(WP_Post $post): array {
        $post_id = (int) $post->ID;
        $jobs = [];
        $map = $this->admin->get_generated_map_admin_status($post_id);
        $map_status = sanitize_key((string) ($map['status'] ?? 'idle'));
        if (!in_array($map_status, ['idle', 'privacy_hidden'], true)) {
            $jobs[] = [
                'kind' => 'map',
                'status' => $map_status,
                'label' => __('Kartenbild', 'feuer-einsatzberichte'),
                'message' => (string) ($map['message'] ?? ''),
                'retryable' => in_array($map_status, ['missing', 'error'], true),
            ];
        }

        $share = $this->admin->get_report_share()->get_share_card_job_status($post_id);
        if ('not_required' !== $share['status']) {
            $jobs[] = [
                'kind' => 'share',
                'status' => $share['status'],
                'label' => __('Link-Vorschaubild', 'feuer-einsatzberichte'),
                'message' => $share['message'],
                'retryable' => in_array($share['status'], ['missing', 'error'], true),
            ];
        }

        if (FEU_Einsatz_Image_Protection::is_image_watermark_enabled()) {
            $gallery_ids = array_slice(array_filter(array_map('absint', (array) get_post_meta($post_id, '_feu_einsatz_gallery', true))), 0, 12);
            $pending = 0;
            $errors = 0;
            foreach ($gallery_ids as $attachment_id) {
                $photo = FEU_Einsatz_Image_Protection::get_attachment_job_status($attachment_id);
                $pending += in_array($photo['status'], ['missing', 'queued', 'processing'], true) ? 1 : 0;
                $errors += 'error' === $photo['status'] ? 1 : 0;
            }
            if ($gallery_ids) {
                $jobs[] = [
                    'kind' => 'watermark',
                    'status' => $errors ? 'error' : ($pending ? 'queued' : 'ready'),
                    'label' => __('Foto-Wasserzeichen', 'feuer-einsatzberichte'),
                    'message' => $errors
                        ? sprintf(__('%d Foto(s) mit Fehler', 'feuer-einsatzberichte'), $errors)
                        : ($pending ? sprintf(__('%d Foto(s) in Bearbeitung', 'feuer-einsatzberichte'), $pending) : __('Alle geprüften Fotos bereit', 'feuer-einsatzberichte')),
                    'retryable' => $errors > 0 || $pending > 0,
                ];
            }
        }

        if ('future' === $post->post_status && strtotime($post->post_date_gmt . ' UTC') < time() - 5 * MINUTE_IN_SECONDS) {
            $jobs[] = [
                'kind' => 'schedule',
                'status' => 'delayed',
                'label' => __('Zeitplanung', 'feuer-einsatzberichte'),
                'message' => __('Veröffentlichungszeit ist überschritten. WP-Cron prüfen.', 'feuer-einsatzberichte'),
                'retryable' => false,
            ];
        }

        return $jobs;
    }

    private function get_status_overview(): array {
        $query = $this->get_reports(['draft', 'pending', 'future', 'publish'], 1, 40);
        $items = [];
        $counts = ['ready' => 0, 'queued' => 0, 'error' => 0, 'delayed' => 0];
        foreach ($query->posts as $post) {
            if (!$post instanceof WP_Post) {
                continue;
            }
            $jobs = $this->get_report_jobs($post);
            foreach ($jobs as $job) {
                $bucket = in_array($job['status'], ['error', 'delayed', 'ready'], true) ? $job['status'] : 'queued';
                $counts[$bucket]++;
            }
            if ($jobs) {
                $items[] = ['post' => $post, 'jobs' => $jobs];
            }
        }
        return ['counts' => $counts, 'items' => $items, 'sample_size' => count($query->posts)];
    }

    private function get_privacy_participants(int $page, string $search): array {
        global $wpdb;
        $table = $this->db->get_participant_table_name();
        $where = 'WHERE is_deleted = 0';
        $params = [];
        if ('' !== $search) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where .= ' AND (vorname LIKE %s OR nachname LIKE %s)';
            $params = [$like, $like];
        }
        $count_sql = "SELECT COUNT(*) FROM {$table} {$where}";
        $total = (int) $wpdb->get_var($params ? $wpdb->prepare($count_sql, $params) : $count_sql);
        $params[] = 20;
        $params[] = max(0, $page - 1) * 20;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} {$where} ORDER BY is_archived ASC, nachname ASC, vorname ASC LIMIT %d OFFSET %d",
            $params
        ));
        if (is_array($rows) && $rows) {
            $ids = array_map(static fn($row) => (int) $row->id, $rows);
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            $stats_table = $this->db->get_participant_statistics_table_name();
            $activity = $wpdb->get_results($wpdb->prepare(
                "SELECT teilnehmer_id, MAX(created_at) AS last_activity FROM {$stats_table} WHERE teilnehmer_id IN ({$placeholders}) GROUP BY teilnehmer_id",
                $ids
            ), OBJECT_K);
            foreach ($rows as $row) {
                $row->last_activity = isset($activity[$row->id]) ? (string) $activity[$row->id]->last_activity : (string) $row->created_at;
            }
        }
        return ['items' => is_array($rows) ? $rows : [], 'total' => $total];
    }

    public function render_dashboard_panel(string $tab): void {
        if (!self::can_view()) {
            wp_die(esc_html__('Keine Berechtigung', 'feuer-einsatzberichte'));
        }
        if (!in_array($tab, ['status', 'queue', 'privacy'], true)) {
            $tab = 'status';
        }
        if ('privacy' === $tab && !current_user_can('manage_options')) {
            wp_die(esc_html__('Keine Berechtigung', 'feuer-einsatzberichte'));
        }
        $status_overview = 'status' === $tab ? $this->get_status_overview() : [];
        $queue_status = isset($_GET['report_status']) ? sanitize_key(wp_unslash($_GET['report_status'])) : 'pending';
        if (!in_array($queue_status, ['pending', 'draft', 'future', 'publish', 'all'], true)) {
            $queue_status = 'pending';
        }
        $page = isset($_GET['paged']) ? max(1, absint(wp_unslash($_GET['paged']))) : 1;
        $queue = 'queue' === $tab
            ? $this->get_reports('all' === $queue_status ? ['pending', 'draft', 'future', 'publish'] : [$queue_status], $page)
            : null;
        $privacy_search = 'privacy' === $tab && isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $participants = 'privacy' === $tab ? $this->get_privacy_participants($page, $privacy_search) : ['items' => [], 'total' => 0];
        include FEU_EINSATZ_PLUGIN_DIR . 'templates/admin/operations-center.php';
    }

    public function handle_retry_job(): void {
        $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        $kind = isset($_POST['job']) ? sanitize_key(wp_unslash($_POST['job'])) : '';
        check_admin_referer('feu_einsatz_retry_job_' . $post_id . '_' . $kind);
        if (!self::can_view() || !current_user_can('edit_post', $post_id) || !FEU_Einsatz_Report_Post_Type::is_marked_report($post_id)) {
            wp_die(esc_html__('Keine Berechtigung', 'feuer-einsatzberichte'));
        }
        $queued = false;
        if ('map' === $kind) {
            $queued = $this->admin->retry_generated_map_from_operations($post_id);
        } elseif ('share' === $kind) {
            $queued = $this->admin->get_report_share()->retry_share_card_generation($post_id);
        } elseif ('watermark' === $kind) {
            $ids = array_filter(array_map('absint', (array) get_post_meta($post_id, '_feu_einsatz_gallery', true)));
            FEU_Einsatz_Image_Protection::retry_attachment_cache($ids);
            $queued = (bool) $ids;
        }
        wp_safe_redirect(add_query_arg(
            ['page' => 'feuer-einsatzberichte', 'ops_tab' => 'status', 'job_result' => $queued ? 'queued' : 'failed'],
            admin_url('admin.php')
        ));
        exit;
    }

    public function handle_privacy_settings(): void {
        check_admin_referer('feu_einsatz_privacy_settings');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Keine Berechtigung', 'feuer-einsatzberichte'));
        }
        update_option('feu_einsatz_public_participant_names', isset($_POST['public_names']) ? 1 : 0, false);
        $years = isset($_POST['retention_years']) ? absint(wp_unslash($_POST['retention_years'])) : 0;
        update_option('feu_einsatz_participant_retention_years', min(20, $years), false);
        wp_safe_redirect(admin_url('admin.php?page=feuer-einsatzberichte&ops_tab=privacy&saved=1'));
        exit;
    }

    public function handle_participant_data(): void {
        $participant_id = isset($_POST['participant_id']) ? absint(wp_unslash($_POST['participant_id'])) : 0;
        $operation = isset($_POST['operation']) ? sanitize_key(wp_unslash($_POST['operation'])) : '';
        check_admin_referer('feu_einsatz_participant_data_' . $participant_id . '_' . $operation);
        if (!current_user_can('manage_options') || !FEU_Einsatz_Admin::current_user_can_access_plugin_section('participants')) {
            wp_die(esc_html__('Keine Berechtigung', 'feuer-einsatzberichte'));
        }
        $participant = $this->db->get_participant($participant_id);
        if (!$participant || !empty($participant->is_deleted)) {
            wp_die(esc_html__('Teilnehmer nicht gefunden', 'feuer-einsatzberichte'));
        }
        global $wpdb;
        $stats = $wpdb->get_results($wpdb->prepare(
            'SELECT post_id, funktion, created_at FROM ' . $this->db->get_participant_statistics_table_name() . ' WHERE teilnehmer_id = %d ORDER BY created_at DESC',
            $participant_id
        ), ARRAY_A);
        if ('export' === $operation) {
            nocache_headers();
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="einsatz-teilnehmer-' . $participant_id . '.json"');
            echo wp_json_encode(['participant' => $participant, 'report_links' => $stats], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ('anonymize' !== $operation
            || 'ANONYMISIEREN' !== (isset($_POST['confirm']) ? sanitize_text_field(wp_unslash($_POST['confirm'])) : '')
            || '' !== trim((string) ($participant->external_provider ?? ''))) {
            wp_die(esc_html__('Anonymisierung nicht bestätigt oder extern verwaltetes Profil.', 'feuer-einsatzberichte'));
        }
        $updated = $wpdb->update($this->db->get_participant_table_name(), [
            'vorname' => __('Anonymisiert', 'feuer-einsatzberichte'),
            'nachname' => '#' . $participant_id,
            'job_title' => '', 'entry_date' => '', 'rank_title' => '',
            'member_function' => '', 'education' => '', 'description' => '',
            'category_ids' => '[]', 'gallery_ids' => '[]', 'primary_image_id' => 0,
            'default_functions' => '[]', 'external_id' => 0, 'is_archived' => 1,
        ], ['id' => $participant_id]);
        if (false === $updated) {
            wp_die(esc_html__('Anonymisierung fehlgeschlagen.', 'feuer-einsatzberichte'));
        }
        $this->db->invalidate_statistics_dashboard_cache();
        update_option('feu_einsatz_participant_data_version', (int) get_option('feu_einsatz_participant_data_version', 0) + 1, false);
        FEU_Einsatz_Logger::log('participant_anonymized', 'participant', $participant_id, 'Participant profile anonymized.');
        wp_safe_redirect(admin_url('admin.php?page=feuer-einsatzberichte&ops_tab=privacy&anonymized=1'));
        exit;
    }

    public function register_site_health_test(array $tests): array {
        $tests['direct']['feu_einsatz_schedule'] = [
            'label' => __('Einsatzberichte: Zeitplanung', 'feuer-einsatzberichte'),
            'test' => [$this, 'test_schedule_health'],
        ];
        return $tests;
    }

    public function test_schedule_health(): array {
        $overdue = get_posts([
            'post_type' => FEU_Einsatz_Report_Post_Type::readable_post_types(),
            'post_status' => 'future',
            'posts_per_page' => 1,
            'date_query' => [['column' => 'post_date_gmt', 'before' => gmdate('Y-m-d H:i:s', time() - 5 * MINUTE_IN_SECONDS)]],
            'meta_query' => [['key' => FEU_Einsatz_Report_Post_Type::MARKER_META, 'value' => '1']],
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);
        return [
            'label' => $overdue ? __('Geplante Einsatzberichte sind überfällig', 'feuer-einsatzberichte') : __('Geplante Einsatzberichte sind aktuell', 'feuer-einsatzberichte'),
            'status' => $overdue ? 'recommended' : 'good',
            'badge' => ['label' => __('Einsatzberichte', 'feuer-einsatzberichte'), 'color' => 'blue'],
            'description' => '<p>' . esc_html($overdue ? __('Prüfen Sie WP-Cron und die Arbeitszentrale.', 'feuer-einsatzberichte') : __('Keine überfällige Veröffentlichung gefunden.', 'feuer-einsatzberichte')) . '</p>',
            'actions' => '<a href="' . esc_url(admin_url('admin.php?page=feuer-einsatzberichte&ops_tab=status')) . '">' . esc_html__('Dashboard: Systemstatus öffnen', 'feuer-einsatzberichte') . '</a>',
            'test' => 'feu_einsatz_schedule',
        ];
    }
}
