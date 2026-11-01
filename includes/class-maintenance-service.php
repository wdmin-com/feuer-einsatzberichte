<?php
if (!defined('ABSPATH')) {
    exit;
}

/** Background media audit and non-blocking cache warm-up for published reports. */
final class FEU_Einsatz_Maintenance_Service {
    public const IMAGE_AUDIT_HOOK = 'feu_einsatz_daily_image_integrity';
    public const CACHE_WARM_HOOK = 'feu_einsatz_warm_report_cache';
    public const IMAGE_AUDIT_OPTION = 'feu_einsatz_image_integrity_state';

    private FEU_Einsatz_Admin $admin;

    public function __construct(FEU_Einsatz_Admin $admin) {
        $this->admin = $admin;
        add_action('init', [$this, 'ensure_schedule']);
        add_action(self::IMAGE_AUDIT_HOOK, [$this, 'run_image_integrity_audit']);
        add_action(self::CACHE_WARM_HOOK, [$this, 'warm_report_cache'], 10, 1);
        add_action('save_post', [$this, 'queue_report_cache_warm'], 120, 3);
        add_action('added_post_meta', [$this, 'queue_report_cache_warm_for_marker'], 20, 4);
        add_action('admin_post_feu_einsatz_run_image_integrity', [$this, 'handle_manual_image_integrity']);
    }

    public function ensure_schedule(): void {
        if (!wp_next_scheduled(self::IMAGE_AUDIT_HOOK)) {
            wp_schedule_event(time() + 10 * MINUTE_IN_SECONDS, 'daily', self::IMAGE_AUDIT_HOOK);
        }
    }

    public function queue_report_cache_warm($post_id, $post, $update): void {
        $post_id = absint($post_id);
        if (!$post instanceof WP_Post || 'publish' !== $post->post_status || wp_is_post_revision($post_id)
            || !FEU_Einsatz_Report_Post_Type::is_marked_report($post_id)) {
            return;
        }
        wp_clear_scheduled_hook(self::CACHE_WARM_HOOK, [$post_id]);
        if (wp_schedule_single_event(time() + ($update ? 30 : 10), self::CACHE_WARM_HOOK, [$post_id])) {
            update_post_meta($post_id, '_feu_einsatz_cache_warm_job', ['status' => 'queued', 'updated_at' => time()]);
        } else {
            update_post_meta($post_id, '_feu_einsatz_cache_warm_job', ['status' => 'error', 'updated_at' => time()]);
        }
    }

    public function queue_report_cache_warm_for_marker($meta_id, $post_id, $meta_key, $meta_value): void {
        if (FEU_Einsatz_Report_Post_Type::MARKER_META !== (string) $meta_key || '1' !== (string) $meta_value) {
            return;
        }
        $post = get_post(absint($post_id));
        if ($post instanceof WP_Post) {
            $this->queue_report_cache_warm((int) $post->ID, $post, false);
        }
    }

    public function warm_report_cache($post_id): void {
        $post_id = absint($post_id);
        $post = $post_id ? get_post($post_id) : null;
        if (!$post instanceof WP_Post || 'publish' !== $post->post_status || !FEU_Einsatz_Report_Post_Type::is_marked_report($post_id)) {
            return;
        }
        update_post_meta($post_id, '_feu_einsatz_cache_warm_job', ['status' => 'processing', 'updated_at' => time()]);
        try {
            $map = $this->admin->get_generated_map_admin_status($post_id);
            if (1 === (int) get_option('feu_einsatz_auto_map_image', 1)
                && in_array((string) ($map['status'] ?? ''), ['missing', 'error'], true)) {
                $this->admin->retry_generated_map_from_operations($post_id);
            }
            $this->admin->get_report_share()->queue_share_card_generation($post_id);
            $gallery_ids = array_values(array_filter(array_map('absint', (array) get_post_meta($post_id, '_feu_einsatz_gallery', true))));
            if ($gallery_ids && FEU_Einsatz_Image_Protection::is_image_watermark_enabled()) {
                FEU_Einsatz_Image_Protection::prime_attachment_cache($gallery_ids);
            }
            FEU_Einsatz_Template_Helpers::get_single_context($post);
            FEU_Einsatz_Template_Helpers::get_overview_context(['selected_year' => (int) get_the_date('Y', $post)]);
            update_post_meta($post_id, '_feu_einsatz_cache_warm_job', ['status' => 'ready', 'updated_at' => time()]);
        } catch (Throwable $error) {
            update_post_meta($post_id, '_feu_einsatz_cache_warm_job', ['status' => 'error', 'updated_at' => time()]);
            FEU_Einsatz_Logger::log('cache_warm_failed', 'report', $post_id, 'Cache warm-up failed.', ['error' => $error->getMessage()]);
        }
    }

    public function run_image_integrity_audit(): array {
        $report_ids = get_posts([
            'post_type' => FEU_Einsatz_Report_Post_Type::readable_post_types(),
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'orderby' => 'modified',
            'order' => 'DESC',
            'no_found_rows' => true,
            'meta_query' => [['key' => FEU_Einsatz_Report_Post_Type::MARKER_META, 'value' => '1']],
        ]);
        $issues = [];
        $checked_files = 0;
        $warm_queued = 0;
        foreach (array_map('absint', (array) $report_ids) as $index => $post_id) {
            $warm = get_post_meta($post_id, '_feu_einsatz_cache_warm_job', true);
            $warm_time = is_array($warm) ? (int) ($warm['updated_at'] ?? 0) : 0;
            $warm_status = is_array($warm) ? sanitize_key((string) ($warm['status'] ?? '')) : '';
            $modified_time = (int) get_post_modified_time('U', true, $post_id);
            if (('error' === $warm_status || $warm_time < $modified_time) && !wp_next_scheduled(self::CACHE_WARM_HOOK, [$post_id])) {
                if (wp_schedule_single_event(time() + min(600, 15 + ($index * 3)), self::CACHE_WARM_HOOK, [$post_id])) {
                    update_post_meta($post_id, '_feu_einsatz_cache_warm_job', ['status' => 'queued', 'updated_at' => time()]);
                    $warm_queued++;
                }
            }
            $map_path = trim((string) get_post_meta($post_id, '_feu_einsatz_generated_map_preview_file', true));
            if ('' !== $map_path) {
                $checked_files++;
                if (!$this->is_valid_image($map_path)) {
                    $issues[] = $this->issue($post_id, 'map', __('Kartenbild fehlt oder ist beschädigt.', 'feuer-einsatzberichte'));
                }
            } elseif (1 === (int) get_option('feu_einsatz_auto_map_image', 1)) {
                $issues[] = $this->issue($post_id, 'map', __('Kartenbild fehlt.', 'feuer-einsatzberichte'));
            }

            $share = $this->admin->get_report_share()->inspect_share_card_integrity($post_id);
            if (in_array((string) ($share['status'] ?? ''), ['missing', 'corrupt'], true)) {
                $issues[] = $this->issue($post_id, 'share', 'corrupt' === ($share['status'] ?? '')
                    ? __('Link-Vorschaubild ist beschädigt.', 'feuer-einsatzberichte')
                    : __('Link-Vorschaubild fehlt.', 'feuer-einsatzberichte'));
            }

            foreach (array_values(array_filter(array_map('absint', (array) get_post_meta($post_id, '_feu_einsatz_gallery', true)))) as $attachment_id) {
                $checked_files++;
                $source = get_attached_file($attachment_id);
                if (!$source || !$this->is_valid_image((string) $source)) {
                    $issues[] = $this->issue($post_id, 'photo', sprintf(__('Originalfoto #%d fehlt oder ist beschädigt.', 'feuer-einsatzberichte'), $attachment_id));
                    continue;
                }
                if (FEU_Einsatz_Image_Protection::is_image_watermark_enabled()) {
                    $watermark = FEU_Einsatz_Image_Protection::get_attachment_job_status($attachment_id);
                    if (in_array((string) ($watermark['status'] ?? ''), ['missing', 'error'], true)) {
                        $issues[] = $this->issue($post_id, 'watermark', sprintf(__('Geschützte Fotovariante #%d fehlt.', 'feuer-einsatzberichte'), $attachment_id));
                    }
                }
            }
        }
        $state = [
            'checked_at' => time(),
            'reports' => count($report_ids),
            'files' => $checked_files,
            'issues' => array_slice($issues, 0, 100),
            'issue_count' => count($issues),
            'warm_queued' => $warm_queued,
        ];
        update_option(self::IMAGE_AUDIT_OPTION, $state, false);
        return $state;
    }

    public function get_image_integrity_state(): array {
        $state = get_option(self::IMAGE_AUDIT_OPTION, []);
        return is_array($state) ? $state : [];
    }

    public function handle_manual_image_integrity(): void {
        check_admin_referer('feu_einsatz_run_image_integrity');
        if (!FEU_Einsatz_Operations_Center::can_view() || !current_user_can('manage_options')) {
            wp_die(esc_html__('Keine Berechtigung', 'feuer-einsatzberichte'));
        }
        $state = $this->run_image_integrity_audit();
        wp_safe_redirect(add_query_arg([
            'page' => 'feuer-einsatzberichte',
            'ops_tab' => 'status',
            'integrity_checked' => '1',
            'integrity_issues' => (int) ($state['issue_count'] ?? 0),
        ], admin_url('admin.php')));
        exit;
    }

    private function is_valid_image(string $path): bool {
        clearstatcache(true, $path);
        return is_file($path) && is_readable($path) && filesize($path) > 0 && false !== @getimagesize($path);
    }

    private function issue(int $post_id, string $kind, string $message): array {
        return ['post_id' => $post_id, 'kind' => $kind, 'message' => $message];
    }
}
