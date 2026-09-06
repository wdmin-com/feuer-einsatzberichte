<?php
/**
 * FEU_Einsatz_Report_Share
 *
 * Social-Sharing für Einsatzberichte: Share-Texte, Netzwerk-URLs,
 * PNG-Share-Karte (GD-Canvas) und öffentliche/Admin-Download-Handler.
 *
 * Ausgelagert aus class-admin.php in Version 3.1.35 (Phase 1 Refactoring).
 *
 * Abhängigkeiten:
 *   - FEU_Einsatz_Template_Helpers (statisch, keine Injection nötig)
 *   - Map-Text-Methoden: draw_map_preview_text(), get_map_preview_font_path(),
 *     prepare_map_preview_text(), get_map_preview_ttf_text_width(),
 *     fit_map_preview_text_to_width() werden via $map_text_helper übergeben.
 *
 * Integration in class-admin.php:
 *   $this->report_share = new FEU_Einsatz_Report_Share(
 *       fn($canvas,$x,$y,$text,$size,$color,$max) =>
 *           $this->draw_map_preview_text($canvas,$x,$y,$text,$size,$color,$max),
 *       fn() => $this->get_map_preview_font_path(),
 *       fn($text) => $this->prepare_map_preview_text($text),
 *       fn($text,$size,$path) => $this->get_map_preview_ttf_text_width($text,$size,$path),
 *       fn($text,$size,$path,$maxW) => $this->fit_map_preview_text_to_width($text,$size,$path,$maxW)
 *   );
 *
 * Hooks in class-admin.php:
 *   add_action('admin_post_feu_einsatz_share_image',
 *              [$this->report_share, 'handle_share_image_download']);
 *   add_action('admin_post_feu_einsatz_share_image_public',
 *              [$this->report_share, 'handle_public_share_image_request']);
 *   add_action('admin_post_nopriv_feu_einsatz_share_image_public',
 *              [$this->report_share, 'handle_public_share_image_request']);
 */

if (!defined('ABSPATH')) {
    exit;
}

class FEU_Einsatz_Report_Share {
    private const PUBLIC_SHARE_TTL = DAY_IN_SECONDS;
    private const BACKGROUND_GENERATION_HOOK = 'feu_einsatz_generate_share_card_background';
    private const CACHE_REVISIONS_TO_KEEP = 2;

    /** @var callable */
    private $fn_draw_text;

    /** @var callable */
    private $fn_font_path;

    /** @var callable */
    private $fn_prepare_text;

    /** @var callable */
    private $fn_text_width;

    /** @var callable */
    private $fn_fit_text;

    /** @var callable */
    private $fn_is_einsatzbericht;

    /** @var callable */
    private $fn_get_editable_report;

    /**
     * @param callable $fn_draw_text             draw_map_preview_text()
     * @param callable $fn_font_path             get_map_preview_font_path()
     * @param callable $fn_prepare_text          prepare_map_preview_text()
     * @param callable $fn_text_width            get_map_preview_ttf_text_width()
     * @param callable $fn_fit_text              fit_map_preview_text_to_width()
     * @param callable $fn_is_einsatzbericht     is_einsatzbericht_post()
     * @param callable $fn_get_editable_report   get_editable_report()
     */
    public function __construct(
        callable $fn_draw_text,
        callable $fn_font_path,
        callable $fn_prepare_text,
        callable $fn_text_width,
        callable $fn_fit_text,
        callable $fn_is_einsatzbericht,
        callable $fn_get_editable_report
    ) {
        $this->fn_draw_text           = $fn_draw_text;
        $this->fn_font_path           = $fn_font_path;
        $this->fn_prepare_text        = $fn_prepare_text;
        $this->fn_text_width          = $fn_text_width;
        $this->fn_fit_text            = $fn_fit_text;
        $this->fn_is_einsatzbericht   = $fn_is_einsatzbericht;
        $this->fn_get_editable_report = $fn_get_editable_report;
        add_action(self::BACKGROUND_GENERATION_HOOK, [$this, 'handle_background_share_card_generation']);
    }

    private function get_share_capability(): string {
        /**
         * Filtert die minimale Capability für den Share-Bereich.
         *
         * Beispiel:
         * add_filter('feu_einsatz_share_capability', fn() => 'edit_posts');
         */
        $capability = (string) apply_filters('feu_einsatz_share_capability', 'manage_options');

        return '' !== $capability ? $capability : 'manage_options';
    }

    private function get_share_card_cache_path(int $post_id, string $revision = ''): string {
        if ($post_id < 1) {
            return '';
        }

        $upload_dir = wp_upload_dir();

        if (!empty($upload_dir['error'])) {
            return '';
        }

        $revision = preg_replace('/[^a-z0-9_-]/i', '', strtolower(trim($revision)));
        $filename = $post_id . '.png';

        if ('' !== $revision) {
            $filename = $post_id . '-' . $revision . '.png';
        }

        return trailingslashit($upload_dir['basedir'])
            . 'feuer-einsatzberichte/share-cards/'
            . $filename;
    }

    public function invalidate_share_card_cache(int $post_id): void {
        $post_id = absint($post_id);

        if ($post_id < 1) {
            return;
        }

        $base_path = $this->get_share_card_cache_path($post_id);

        if ('' !== $base_path && file_exists($base_path)) {
            wp_delete_file($base_path);
        }

        $upload_dir = wp_upload_dir();

        if (!empty($upload_dir['error'])) {
            return;
        }

        $pattern = trailingslashit($upload_dir['basedir']) . 'feuer-einsatzberichte/share-cards/' . $post_id . '-*.png';

        foreach ((array) glob($pattern) as $file) {
            if (is_file($file)) {
                wp_delete_file($file);
            }
        }
    }

    /** Queue a generated Open Graph card so social crawlers never trigger GD work. */
    public function queue_share_card_generation(int $post_id): void {
        $post_id = absint($post_id);
        $post = $post_id ? get_post($post_id) : null;
        $settings = $this->get_social_share_settings();

        if (
            !$post instanceof WP_Post
            || 'publish' !== $post->post_status
            || !($this->fn_is_einsatzbericht)($post)
            || 'generated' !== $settings['image_mode']
            || wp_next_scheduled(self::BACKGROUND_GENERATION_HOOK, [$post_id])
        ) {
            return;
        }

        wp_schedule_single_event(time() + 15, self::BACKGROUND_GENERATION_HOOK, [$post_id]);
    }

    public function handle_background_share_card_generation($post_id): void {
        $post_id = absint($post_id);
        $post = $post_id ? get_post($post_id) : null;

        if (!$post instanceof WP_Post || 'publish' !== $post->post_status || !($this->fn_is_einsatzbericht)($post)) {
            return;
        }

        $this->generate_share_card_cache($post);
    }

    private function generate_share_card_cache(WP_Post $post): string {
        $share_data = $this->build_public_share_card_data($post);

        if (empty($share_data)) {
            return '';
        }

        $cache_revision = $this->build_share_card_cache_revision($post, $share_data);
        $cached = $this->get_share_card_cache_path((int) $post->ID, $cache_revision);

        if ('' === $cached) {
            return '';
        }

        if (file_exists($cached) && is_readable($cached)) {
            return $cached;
        }

        $canvas = $this->build_generated_share_card_canvas($share_data);

        if (!$canvas) {
            return '';
        }

        $directory = dirname($cached);
        if (!is_dir($directory) && !wp_mkdir_p($directory)) {
            imagedestroy($canvas);
            return '';
        }

        $written = imagepng($canvas, $cached, 8);
        imagedestroy($canvas);

        if (!$written) {
            return '';
        }

        $this->prune_share_card_cache((int) $post->ID, $cached);

        return $cached;
    }

    private function prune_share_card_cache(int $post_id, string $current_path): void {
        $upload_dir = wp_upload_dir();

        if ($post_id < 1 || !empty($upload_dir['error'])) {
            return;
        }

        $pattern = trailingslashit($upload_dir['basedir']) . 'feuer-einsatzberichte/share-cards/' . $post_id . '-*.png';
        $files = array_values(array_filter((array) glob($pattern), 'is_file'));

        usort($files, static function ($left, $right): int {
            return (int) @filemtime($right) <=> (int) @filemtime($left);
        });

        $kept = 0;
        foreach ($files as $file) {
            if ($file === $current_path || $kept < self::CACHE_REVISIONS_TO_KEEP) {
                $kept++;
                continue;
            }

            wp_delete_file($file);
        }
    }

    public function invalidate_all_share_card_caches(): void {
        $upload_dir = wp_upload_dir();

        if (!empty($upload_dir['error'])) {
            return;
        }

        $dir = trailingslashit($upload_dir['basedir']) . 'feuer-einsatzberichte/share-cards';

        if (!is_dir($dir)) {
            return;
        }

        foreach ((array) glob(trailingslashit($dir) . '*.png') as $file) {
            if (is_file($file)) {
                wp_delete_file($file);
            }
        }
    }

    // Р Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљ Р вЂњРІР‚вЂњffentliche Hooks Р Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљ

    public function handle_share_image_download(): void {
        if (!current_user_can($this->get_share_capability())) {
            wp_die(esc_html__('Keine Berechtigung', 'feuer-einsatzberichte'));
        }

        $post_id = isset($_GET['post_id']) ? absint(wp_unslash($_GET['post_id'])) : 0;

        if ($post_id < 1) {
            wp_die(esc_html__('Einsatzbericht nicht gefunden.', 'feuer-einsatzberichte'));
        }

        check_admin_referer('feu_einsatz_share_image_' . $post_id);

        $post = ($this->fn_get_editable_report)($post_id);

        if (!($post instanceof WP_Post)) {
            wp_die(esc_html__('Einsatzbericht nicht gefunden oder keine Berechtigung.', 'feuer-einsatzberichte'));
        }

        $share_data = $this->build_admin_share_box_data($post);
        $canvas     = $this->build_generated_share_card_canvas($share_data);

        if (!$canvas) {
            wp_die(esc_html__('Share-Karte konnte nicht erzeugt werden. Pruefen Sie bitte die Bildbibliothek und die GD-Unterstuetzung des Servers.', 'feuer-einsatzberichte'));
        }

        $download = isset($_GET['download']) && '1' === sanitize_text_field(wp_unslash($_GET['download']));
        $filename = $this->build_share_filename($post_id);

        nocache_headers();
        header('Content-Type: image/png');
        header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $filename . '"');

        imagepng($canvas, null, 9);
        imagedestroy($canvas);
        exit;
    }

    public function handle_public_share_image_request(): void {
        $post_id   = isset($_GET['post_id']) ? absint(wp_unslash($_GET['post_id'])) : 0;
        $signature = isset($_GET['sig']) ? sanitize_text_field(wp_unslash($_GET['sig'])) : '';
        $expires_at = isset($_GET['exp']) ? absint(wp_unslash($_GET['exp'])) : 0;

        if ($post_id < 1 || '' === $signature || $expires_at < 1) {
            wp_die(esc_html__('Ungueltige Share-Anfrage.', 'feuer-einsatzberichte'), '', ['response' => 400]);
        }

        if ($expires_at < time()) {
            wp_die(esc_html__('Die Freigabe fuer dieses Share-Bild ist abgelaufen.', 'feuer-einsatzberichte'), '', ['response' => 403]);
        }

        if (($expires_at - time()) > (self::PUBLIC_SHARE_TTL + HOUR_IN_SECONDS)) {
            wp_die(esc_html__('Ungueltige Share-Anfrage.', 'feuer-einsatzberichte'), '', ['response' => 400]);
        }

        $expected_signature = FEU_Einsatz_Template_Helpers::get_public_share_image_signature($post_id, $expires_at);

        if ('' === $expected_signature || !hash_equals($expected_signature, $signature)) {
            wp_die(esc_html__('Share-Bild konnte nicht freigegeben werden.', 'feuer-einsatzberichte'), '', ['response' => 403]);
        }

        $rate_key = 'feu_share_rate_' . md5((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $rate_count = (int) get_transient($rate_key);

        if ($rate_count >= 30) {
            status_header(429);
            header('Retry-After: 60');
            exit;
        }

        set_transient($rate_key, $rate_count + 1, 60);

        $download = isset($_GET['download']) && '1' === sanitize_text_field(wp_unslash($_GET['download']));

        $post = get_post($post_id);

        if (
            !($post instanceof WP_Post)
            || 'publish' !== get_post_status($post_id)
            || !($this->fn_is_einsatzbericht)($post)
        ) {
            wp_die(esc_html__('Einsatzbericht nicht gefunden.', 'feuer-einsatzberichte'), '', ['response' => 404]);
        }

        $share_data = $this->build_public_share_card_data($post);
        $cache_revision = $this->build_share_card_cache_revision($post, $share_data);

        $cached = $this->get_share_card_cache_path($post_id, $cache_revision);

        if ('' !== $cached && file_exists($cached) && is_readable($cached)) {
            header('Content-Type: image/png');
            header('Cache-Control: public, max-age=3600');
            header('X-Robots-Tag: noindex');
            header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $this->build_share_filename($post_id) . '"');
            readfile($cached);
            exit;
        }

        $cached = $this->generate_share_card_cache($post);

        if ('' === $cached || !is_readable($cached)) {
            wp_die(esc_html__('Share-Karte konnte nicht erzeugt werden.', 'feuer-einsatzberichte'), '', ['response' => 500]);
        }

        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=900');
        header('X-Robots-Tag: noindex');
        header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $this->build_share_filename($post_id) . '"');
        readfile($cached);
        exit;
    }

    private function build_share_card_cache_revision(WP_Post $post, array $share_data): string {
        $post_id = (int) $post->ID;
        $background_id = !empty($share_data['background_image_id']) ? absint($share_data['background_image_id']) : 0;
        $background_file = $background_id > 0 ? (string) get_attached_file($background_id) : '';
        $background_mtime = ('' !== $background_file && file_exists($background_file)) ? (int) @filemtime($background_file) : 0;
        $logo_id = !empty($share_data['logo_id']) ? absint($share_data['logo_id']) : 0;
        $logo_file = $logo_id > 0 ? (string) get_attached_file($logo_id) : '';
        $logo_mtime = ('' !== $logo_file && file_exists($logo_file)) ? (int) @filemtime($logo_file) : 0;
        $post_image_id = !empty($share_data['post_image_attachment_id']) ? absint($share_data['post_image_attachment_id']) : 0;
        $post_image_file = $post_image_id > 0 ? (string) get_attached_file($post_image_id) : '';
        $post_image_mtime = ('' !== $post_image_file && file_exists($post_image_file)) ? (int) @filemtime($post_image_file) : 0;
        $map_preview_file = !empty($share_data['map_preview_file']) && is_string($share_data['map_preview_file'])
            ? trim($share_data['map_preview_file'])
            : '';
        $map_preview_mtime = ('' !== $map_preview_file && file_exists($map_preview_file)) ? (int) @filemtime($map_preview_file) : 0;
        $fingerprint = [
            'post_id' => $post_id,
            'post_modified' => (string) get_post_modified_time('U', true, $post_id),
            'title' => (string) ($share_data['title'] ?? ''),
            'number' => (string) ($share_data['number_label'] ?? ''),
            'selected_fields' => array_values((array) ($share_data['selected_fields'] ?? [])),
            'background_id' => $background_id,
            'background_mtime' => $background_mtime,
            'layout' => (string) ($share_data['layout'] ?? 'wide'),
            'logo_id' => $logo_id,
            'logo_mtime' => $logo_mtime,
            'badge_text' => (string) ($share_data['badge_text'] ?? ''),
            'cta_text' => (string) ($share_data['cta_text'] ?? ''),
            'title_color' => (string) ($share_data['title_color'] ?? ''),
            'description_color' => (string) ($share_data['description_color'] ?? ''),
            'panel_color' => (string) ($share_data['panel_color'] ?? ''),
            'accent_color' => (string) ($share_data['accent_color'] ?? ''),
            'title_scale' => (int) ($share_data['title_scale'] ?? 118),
            'description_scale' => (int) ($share_data['description_scale'] ?? 112),
            'description_max_lines' => (int) ($share_data['description_max_lines'] ?? 5),
            'logo_scale' => (int) ($share_data['logo_scale'] ?? 100),
            'logo_width' => (int) ($share_data['logo_width'] ?? 220),
            'post_image_id' => $post_image_id,
            'post_image_mtime' => $post_image_mtime,
            'map_preview_file' => '' !== $map_preview_file ? wp_basename($map_preview_file) : '',
            'map_preview_mtime' => $map_preview_mtime,
        ];

        return substr(md5((string) wp_json_encode($fingerprint)), 0, 16);
    }

    // Р Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљ Р вЂњРІР‚вЂњffentliche Daten-Builder (fР вЂњРЎВr Templates) Р Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљ

    /**
     * @return array<string,mixed>
     */
    public function build_admin_share_box_data(WP_Post $post): array {
        if (!current_user_can($this->get_share_capability())) {
            return [];
        }

        $post_id        = (int) $post->ID;
        $settings       = $this->get_social_share_settings();
        $report_context = $this->get_report_context($post);
        $permalink      = get_permalink($post_id);
        $post_status    = (string) get_post_status($post_id);
        $description    = $this->get_report_share_description($post);

        $field_values = [
            'title'       => (string) ($report_context['title']    ?? get_the_title($post_id)),
            'date'        => (string) ($report_context['date']     ?? ''),
            'time'        => (string) ($report_context['time']     ?? ''),
            'number'      => (string) ($report_context['number']   ?? ''),
            'category'    => (string) ($report_context['category'] ?? ''),
            'street'      => (string) ($report_context['street']   ?? ''),
            'location'    => (string) ($report_context['location'] ?? ''),
            'description' => $description,
            'url'         => (string) $permalink,
        ];

        $share_text          = $this->build_share_text($settings['fields'], $field_values);
        $network_definitions = FEU_Einsatz_Template_Helpers::get_social_share_network_definitions();
        $networks            = [];

        foreach ((array) $settings['enabled_networks'] as $network_key) {
            if (!isset($network_definitions[$network_key])) {
                continue;
            }

            $share_url = $this->build_network_url(
                $network_key,
                $field_values['url'],
                $share_text,
                $field_values['title'],
                (string) ($settings['twitter_handle'] ?? '')
            );

            if (!in_array($network_key, ['share', 'instagram'], true) && '' === $share_url) {
                continue;
            }

            $networks[] = [
                'key'         => $network_key,
                'label'       => (string) $network_definitions[$network_key]['label'],
                'description' => (string) $network_definitions[$network_key]['description'],
                'url'         => $share_url,
                'mode'        => in_array($network_key, ['share', 'instagram'], true) ? 'native' : 'direct',
            ];
        }

        $background_image_url     = $settings['background_id'] ? wp_get_attachment_image_url($settings['background_id'], 'large') : '';
        $post_image_url           = trim((string) ($report_context['image_url'] ?? ''));
        $post_image_attachment_id = absint($report_context['image_attachment_id'] ?? 0);
        $map_preview_url          = trim((string) ($report_context['map_preview_url'] ?? ''));
        $map_preview_file         = trim((string) ($report_context['map_preview_file'] ?? ''));
        $preview_image_url        = '';
        $download_image_url       = '';
        $image_notice             = '';

        if ('generated' === $settings['image_mode']) {
            $preview_image_url  = $this->build_share_image_endpoint_url($post_id, false);
            $download_image_url = $this->build_share_image_endpoint_url($post_id, true);
            $image_notice       = $background_image_url
                ? __('Die Share-Karte verwendet das eingestellte Hintergrundbild und blendet die freigegebenen Einsatzdaten darueber ein.', 'feuer-einsatzberichte')
                : __('Die Share-Karte verwendet mangels eigenem Hintergrund zuerst das Beitragsbild des Einsatzberichts und sonst einen neutralen Farbverlauf.', 'feuer-einsatzberichte');
        } else {
            $generated_share_preview_url = $this->build_share_image_endpoint_url($post_id, false);
            $generated_share_download_url = $this->build_share_image_endpoint_url($post_id, true);

            $preview_image_url  = '' !== $post_image_url ? $post_image_url : $generated_share_preview_url;
            $download_image_url = '' !== $post_image_url ? $post_image_url : $generated_share_download_url;
            $image_notice       = '' !== $post_image_url
                ? __('Es wird das vorhandene Beitragsbild beziehungsweise das aktuell fuer den Beitrag genutzte Einsatzbild geteilt.', 'feuer-einsatzberichte')
                : __('Kein verwendbares Beitragsbild gefunden. Die Vorschau nutzt deshalb automatisch die generierte Share-Karte.', 'feuer-einsatzberichte');
        }

        return [
            'post_id'                  => $post_id,
            'post_status'              => $post_status,
            'is_public'                => 'publish' === $post_status,
            'title'                    => $field_values['title'],
            'description'              => $description,
            'date_display'             => $field_values['date'],
            'time'                     => $field_values['time'],
            'category_name'            => $field_values['category'],
            'street_label'             => $field_values['street'],
            'location_label'           => $field_values['location'],
            'permalink'                => (string) $permalink,
            'share_text'               => $share_text,
            'share_text_preview'       => nl2br(esc_html($share_text)),
            'networks'                 => $networks,
            'image_mode'               => $settings['image_mode'],
            'image_mode_label'         => 'generated' === $settings['image_mode']
                                            ? __('Generierte Share-Karte', 'feuer-einsatzberichte')
                                            : __('Vorhandenes Beitragsbild', 'feuer-einsatzberichte'),
            'background_image_url'     => $background_image_url,
            'background_image_id'      => $settings['background_id'],
            'layout'                   => $settings['layout'],
            'layout_label'             => FEU_Einsatz_Template_Helpers::get_social_share_layout_definitions()[$settings['layout']]['label'] ?? $settings['layout'],
            'logo_id'                  => $settings['logo_id'],
            'logo_url'                 => $settings['logo_id'] > 0 ? (string) wp_get_attachment_image_url($settings['logo_id'], 'medium') : '',
            'badge_text'               => $settings['badge_text'],
            'cta_text'                 => $settings['cta_text'],
            'title_color'              => $settings['title_color'],
            'description_color'        => $settings['description_color'],
            'panel_color'              => $settings['panel_color'],
            'accent_color'             => $settings['accent_color'],
            'title_scale'              => $settings['title_scale'],
            'description_scale'        => $settings['description_scale'],
            'description_max_lines'    => $settings['description_max_lines'],
            'logo_scale'               => $settings['logo_scale'],
            'logo_width'               => $settings['logo_width'],
            'post_image_url'           => $post_image_url,
            'post_image_attachment_id' => $post_image_attachment_id,
            'map_preview_url'          => $map_preview_url,
            'map_preview_file'         => $map_preview_file,
            'preview_image_url'        => $preview_image_url,
            'download_image_url'       => $download_image_url,
            'has_preview_image'        => '' !== trim((string) $preview_image_url),
            'image_notice'             => $image_notice,
            'number_label'             => (string) ($report_context['number'] ?? ''),
            'selected_fields'          => $settings['fields'],
            'status_notice'            => 'publish' === $post_status
                                            ? ''
                                            : __('Share-Links fuer soziale Netzwerke werden erst nach der Veroeffentlichung freigegeben, damit keine nicht oeffentlichen Beitragslinks verteilt werden.', 'feuer-einsatzberichte'),
        ];
    }

    // Р Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљ Private Helfer Р Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљ

    /**
     * @return array<string,mixed>
     */
    private function build_public_share_card_data(WP_Post $post): array {
        $post_id = (int) $post->ID;

        if ($post_id < 1 || 'publish' !== get_post_status($post_id) || !($this->fn_is_einsatzbericht)($post)) {
            return [];
        }

        $settings       = $this->get_social_share_settings();
        $report_context = $this->get_report_context($post);

        if (empty($report_context)) {
            return [];
        }

        return [
            'post_id'                  => $post_id,
            'title'                    => (string) ($report_context['title']      ?? get_the_title($post_id)),
            'description'              => $this->get_report_share_description($post),
            'date_display'             => (string) ($report_context['date']       ?? ''),
            'time'                     => (string) ($report_context['time']       ?? ''),
            'category_name'            => (string) ($report_context['category']   ?? ''),
            'street_label'             => (string) ($report_context['street']     ?? ''),
            'location_label'           => (string) ($report_context['location']   ?? ''),
            'permalink'                => (string) get_permalink($post_id),
            'background_image_id'      => absint($settings['background_id'] ?? 0),
            'layout'                   => (string) ($settings['layout'] ?? 'wide'),
            'logo_id'                  => absint($settings['logo_id'] ?? 0),
            'badge_text'               => (string) ($settings['badge_text'] ?? ''),
            'cta_text'                 => (string) ($settings['cta_text'] ?? ''),
            'title_color'              => (string) ($settings['title_color'] ?? '#ffffff'),
            'description_color'        => (string) ($settings['description_color'] ?? '#dbeafe'),
            'panel_color'              => (string) ($settings['panel_color'] ?? '#0f2f5f'),
            'accent_color'             => (string) ($settings['accent_color'] ?? '#ef233c'),
            'title_scale'              => (int) ($settings['title_scale'] ?? 118),
            'description_scale'        => (int) ($settings['description_scale'] ?? 112),
            'description_max_lines'    => (int) ($settings['description_max_lines'] ?? 5),
            'logo_scale'               => (int) ($settings['logo_scale'] ?? 100),
            'logo_width'               => (int) ($settings['logo_width'] ?? 220),
            'overlay_enabled'          => !empty($settings['overlay_enabled']),
            'image_blur'               => (int) ($settings['image_blur'] ?? 0),
            'panel_radius'             => (int) ($settings['panel_radius'] ?? 30),
            'badge_radius'             => (int) ($settings['badge_radius'] ?? 40),
            'link_radius'              => (int) ($settings['link_radius'] ?? 14),
            'text_align'               => (string) ($settings['text_align'] ?? 'auto'),
            'logo_position'            => (string) ($settings['logo_position'] ?? 'bottom-right'),
            'post_image_attachment_id' => absint($report_context['image_attachment_id'] ?? 0),
            'post_image_url'           => (string) ($report_context['image_url'] ?? ''),
            'map_preview_url'          => (string) ($report_context['map_preview_url'] ?? ''),
            'map_preview_file'         => (string) ($report_context['map_preview_file'] ?? ''),
            'number_label'             => (string) ($report_context['number']     ?? ''),
            'selected_fields'          => FEU_Einsatz_Template_Helpers::normalize_social_share_fields($settings['fields'] ?? []),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function get_social_share_settings(): array {
        return [
            'enabled_networks' => FEU_Einsatz_Template_Helpers::normalize_social_share_networks(
                get_option('feu_einsatz_social_share_enabled_networks', FEU_Einsatz_Template_Helpers::get_default_social_share_networks())
            ),
            'image_mode'  => FEU_Einsatz_Template_Helpers::normalize_social_share_image_mode(
                get_option('feu_einsatz_social_share_image_mode', 'post_image')
            ),
            'background_id' => absint(get_option('feu_einsatz_social_share_background_id', 0)),
            'fields' => FEU_Einsatz_Template_Helpers::normalize_social_share_fields(
                get_option('feu_einsatz_social_share_fields', FEU_Einsatz_Template_Helpers::get_default_social_share_fields())
            ),
            'layout' => FEU_Einsatz_Template_Helpers::normalize_social_share_layout(
                get_option('feu_einsatz_social_share_layout', 'wide')
            ),
            'logo_id' => absint(get_option('feu_einsatz_social_share_logo_id', 0)),
            'badge_text' => FEU_Einsatz_Template_Helpers::normalize_social_share_card_text(
                get_option('feu_einsatz_social_share_badge_text', 'PRESSEMITTEILUNG'),
                'PRESSEMITTEILUNG'
            ),
            'cta_text' => FEU_Einsatz_Template_Helpers::normalize_social_share_card_text(
                get_option('feu_einsatz_social_share_cta_text', 'Weitere Infos'),
                'Weitere Infos'
            ),
            'cta_fill_color' => sanitize_hex_color((string) get_option('feu_einsatz_social_share_cta_fill_color', '#ffffff')) ?: '#ffffff',
            'cta_text_color' => sanitize_hex_color((string) get_option('feu_einsatz_social_share_cta_text_color', '#0f2f5f')) ?: '#0f2f5f',
            'title_color' => sanitize_hex_color((string) get_option('feu_einsatz_social_share_title_color', '#ffffff')) ?: '#ffffff',
            'description_color' => sanitize_hex_color((string) get_option('feu_einsatz_social_share_description_color', '#dbeafe')) ?: '#dbeafe',
            'panel_color' => sanitize_hex_color((string) get_option('feu_einsatz_social_share_panel_color', '#0f2f5f')) ?: '#0f2f5f',
            'accent_color' => sanitize_hex_color((string) get_option('feu_einsatz_social_share_accent_color', '#ef233c')) ?: '#ef233c',
            'title_scale' => max(70, min(180, absint(get_option('feu_einsatz_social_share_title_scale', 118)))),
            'description_scale' => max(70, min(180, absint(get_option('feu_einsatz_social_share_description_scale', 112)))),
            'description_max_lines' => max(2, min(10, absint(get_option('feu_einsatz_social_share_description_max_lines', 5)))),
            'logo_scale' => max(40, min(220, absint(get_option('feu_einsatz_social_share_logo_scale', 100)))),
            'logo_width' => max(60, min(520, absint(get_option('feu_einsatz_social_share_logo_width', 220)))),
            'overlay_enabled' => 1 === (int) get_option('feu_einsatz_social_share_overlay_enabled', 1),
            'image_blur' => max(0, min(20, absint(get_option('feu_einsatz_social_share_image_blur', 0)))),
            'panel_radius' => max(0, min(120, absint(get_option('feu_einsatz_social_share_panel_radius', 30)))),
            'badge_radius' => max(0, min(120, absint(get_option('feu_einsatz_social_share_badge_radius', 40)))),
            'link_radius' => max(0, min(80, absint(get_option('feu_einsatz_social_share_link_radius', 14)))),
            'text_align' => FEU_Einsatz_Template_Helpers::normalize_social_share_text_align(
                get_option('feu_einsatz_social_share_text_align', 'auto')
            ),
            'logo_position' => FEU_Einsatz_Template_Helpers::normalize_social_share_logo_position(
                get_option('feu_einsatz_social_share_logo_position', 'bottom-right')
            ),
            'twitter_handle' => FEU_Einsatz_Template_Helpers::normalize_social_share_account_handle(
                get_option('feu_einsatz_social_meta_twitter_site', '')
            ),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function get_report_context(WP_Post $post): array {
        $post_id        = (int) $post->ID;
        $event_datetime = FEU_Einsatz_Template_Helpers::get_event_datetime($post_id);
        $deepest_cat    = FEU_Einsatz_Template_Helpers::get_deepest_category($post_id);
        $number_data    = FEU_Einsatz_Template_Helpers::get_report_number_data($post_id);
        $card_image     = FEU_Einsatz_Template_Helpers::get_report_photo_image_data($post_id, [], 'large');
        $street         = FEU_Einsatz_Template_Helpers::strip_house_number_from_street(get_post_meta($post_id, '_feu_einsatz_strasse', true));
        $postcode       = trim((string) get_post_meta($post_id, '_feu_einsatz_plz',   true));
        $city           = trim((string) get_post_meta($post_id, '_feu_einsatz_stadt', true));
        $number_label   = !empty($number_data['number']) && !empty($number_data['year'])
                            ? absint($number_data['number']) . '/' . absint($number_data['year'])
                            : '';
        $map_preview_url = FEU_Einsatz_Template_Helpers::get_generated_map_preview_public_url($post_id);
        $map_preview_file = trim((string) get_post_meta($post_id, '_feu_einsatz_generated_map_preview_file', true));

        if ('' !== $map_preview_file && !file_exists($map_preview_file)) {
            $map_preview_file = '';
        }

        $location_label = trim($postcode . ' ' . $city);

        return [
            'title'              => get_the_title($post_id),
            'date'               => isset($event_datetime['date'])            ? (string) $event_datetime['date'] : '',
            'time'               => isset($event_datetime['time'])            ? (string) $event_datetime['time'] : '',
            'number'             => $number_label,
            'category'           => $deepest_cat instanceof WP_Term          ? (string) $deepest_cat->name : '',
            'street'             => $street,
            'location'           => $location_label,
            'image_url'          => isset($card_image['url'])                 ? (string) $card_image['url'] : '',
            'image_attachment_id'=> isset($card_image['attachment_id'])       ? absint($card_image['attachment_id']) : 0,
            'map_preview_url'    => $map_preview_url,
            'map_preview_file'   => $map_preview_file,
        ];
    }

    private function get_report_share_description(WP_Post $post): string {
        $raw = '' !== trim((string) $post->post_excerpt)
            ? (string) $post->post_excerpt
            : (string) $post->post_content;

        $raw = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags(strip_shortcodes($raw))));

        return '' !== $raw ? wp_trim_words($raw, 26, ' ...') : '';
    }

    /**
     * @param array<string,string> $field_values
     */
    private function build_share_text(array $selected_fields, array $field_values): string {
        $selected_fields = FEU_Einsatz_Template_Helpers::normalize_social_share_fields($selected_fields);
        $lines = [];
        $meta_parts = [];

        if (in_array('title', $selected_fields, true) && '' !== trim((string) ($field_values['title'] ?? ''))) {
            $lines[] = trim((string) $field_values['title']);
        }

        if (in_array('number', $selected_fields, true) && '' !== trim((string) ($field_values['number'] ?? ''))) {
            $meta_parts[] = sprintf(__('Einsatz-Nr. %s', 'feuer-einsatzberichte'), trim((string) $field_values['number']));
        }

        if (in_array('date', $selected_fields, true) && '' !== trim((string) ($field_values['date'] ?? ''))) {
            $meta_parts[] = sprintf(__('Datum: %s', 'feuer-einsatzberichte'), trim((string) $field_values['date']));
        }

        if (in_array('time', $selected_fields, true) && '' !== trim((string) ($field_values['time'] ?? ''))) {
            $meta_parts[] = sprintf(__('Uhrzeit: %s', 'feuer-einsatzberichte'), trim((string) $field_values['time']));
        }

        if (!empty($meta_parts)) {
            $lines[] = implode(' | ', $meta_parts);
        }

        foreach ([
            'category' => __('Einsatzart: %s', 'feuer-einsatzberichte'),
            'street'   => __('Strasse: %s', 'feuer-einsatzberichte'),
            'location' => __('Ort: %s', 'feuer-einsatzberichte'),
        ] as $field_key => $line_format) {
            if (!in_array($field_key, $selected_fields, true)) {
                continue;
            }

            $value = trim((string) ($field_values[$field_key] ?? ''));

            if ('' !== $value) {
                $lines[] = sprintf($line_format, $value);
            }
        }

        if (in_array('description', $selected_fields, true) && '' !== trim((string) ($field_values['description'] ?? ''))) {
            $lines[] = '';
            $lines[] = trim((string) $field_values['description']);
        }

        $lines[] = '';
        $lines[] = sprintf(__('Link: %s', 'feuer-einsatzberichte'), trim((string) ($field_values['url'] ?? '')));

        return trim(implode("\n", array_values(array_filter($lines, static function ($line, $index) {
            return !('' === $line && 0 === $index);
        }, ARRAY_FILTER_USE_BOTH))));
    }

    private function build_network_url(string $network_key, string $url, string $share_text, string $title, string $twitter_handle = ''): string {
        $url        = trim($url);
        $share_text = str_replace(["\r\n", "\r"], "\n", trim($share_text));
        $share_text = preg_replace("/\n{3,}/", "\n\n", $share_text);
        $share_text = str_replace("\n", "\r\n", $share_text);
        $title      = trim($title);
        $twitter_handle = trim($twitter_handle);

        switch ($network_key) {
            case 'facebook':  return 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($url);
            case 'x':
                $x_url = 'https://x.com/intent/post?text=' . rawurlencode($share_text) . '&url=' . rawurlencode($url);

                if ('' !== $twitter_handle) {
                    $x_url .= '&via=' . rawurlencode(ltrim($twitter_handle, '@'));
                }

                return $x_url;
            case 'whatsapp':  return 'https://wa.me/?text=' . rawurlencode($share_text);
            case 'telegram':  return 'https://t.me/share/url?url=' . rawurlencode($url) . '&text=' . rawurlencode($share_text);
            case 'instagram': return '';
            case 'email':     return 'mailto:?subject=' . rawurlencode($title) . '&body=' . rawurlencode($share_text);
        }

        return '';
    }

    private function build_share_image_endpoint_url(int $post_id, bool $download = false): string {
        if ($post_id < 1) {
            return '';
        }

        $url = add_query_arg(
            [
                'action' => 'feu_einsatz_share_image',
                'post_id' => $post_id,
            ],
            admin_url('admin-post.php')
        );

        if ($download) {
            $url = add_query_arg('download', '1', $url);
        }

        return wp_nonce_url($url, 'feu_einsatz_share_image_' . $post_id);
    }

    private function build_share_filename(int $post_id): string {
        $filename   = 'einsatzbericht-share-' . $post_id . '.png';
        $title_slug = sanitize_title((string) get_the_title($post_id));

        if ('' !== $title_slug) {
            $filename = 'einsatzbericht-share-' . $title_slug . '-' . $post_id . '.png';
        }

        return $filename;
    }

    // Р Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљ GD-Canvas-Methoden Р Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљР Р†РІР‚СњР вЂљ

    /**
     * @param array<string,mixed> $share_data
     * @return resource|GdImage|false
     */
    private function build_generated_share_card_canvas(array $share_data) {
        return $this->build_generated_share_card_canvas_v2($share_data);

        if (!function_exists('imagecreatetruecolor')) {
            return false;
        }

        $width  = 1200;
        $height = 630;
        $canvas = imagecreatetruecolor($width, $height);

        if (!$canvas) {
            return false;
        }

        imagealphablending($canvas, true);
        imagesavealpha($canvas, true);

        $background_id = !empty($share_data['background_image_id'])
            ? absint($share_data['background_image_id'])
            : 0;

        if ($background_id < 1 && !empty($share_data['post_image_attachment_id'])) {
            $background_id = absint($share_data['post_image_attachment_id']);
        }

        $background_resource = false;

        if ($background_id > 0) {
            $background_resource = $this->create_image_resource_from_path(
                (string) get_attached_file($background_id)
            );
        }

        if (
            !$background_resource
            && !empty($share_data['map_preview_file'])
            && is_string($share_data['map_preview_file'])
        ) {
            $background_resource = $this->create_image_resource_from_path($share_data['map_preview_file']);
        }

        if ($background_resource) {
            $this->draw_cover_image_on_canvas($canvas, $background_resource, $width, $height);
            imagedestroy($background_resource);
        } else {
            $gradient_steps = 12;

            for ($step = 0; $step < $gradient_steps; $step++) {
                $blend = $step / max(1, $gradient_steps - 1);
                $y_start = (int) floor(($height / $gradient_steps) * $step);
                $y_end = (int) ceil(($height / $gradient_steps) * ($step + 1));
                $fill_color = imagecolorallocate(
                    $canvas,
                    (int) round(12 + (34 - 12) * $blend),
                    (int) round(22 + (55 - 22) * $blend),
                    (int) round(38 + (80 - 38) * $blend)
                );

                imagefilledrectangle($canvas, 0, $y_start, $width, $y_end, $fill_color);
            }
        }

        $overlay_dark = imagecolorallocatealpha($canvas, 9,  15, 25, 32);
        $panel_color  = imagecolorallocatealpha($canvas, 10, 18, 30, 36);
        $accent_color = imagecolorallocate($canvas, 220, 38, 38);
        $white        = imagecolorallocate($canvas, 255, 255, 255);
        $muted        = imagecolorallocate($canvas, 210, 221, 233);
        $soft         = imagecolorallocate($canvas, 166, 179, 194);

        imagefilledrectangle($canvas, 0,  0,    $width, $height, $overlay_dark);
        imagefilledrectangle($canvas, 54, 54,   1146,   576,     $panel_color);
        imagefilledrectangle($canvas, 54, 54,   74,     576,     $accent_color);

        $font_path       = ($this->fn_font_path)();
        $site_label      = trim((string) get_bloginfo('name'));
        $number_label    = trim((string) ($share_data['number_label']  ?? ''));
        $street_label    = trim((string) ($share_data['street_label']  ?? ''));
        $location_label  = trim((string) ($share_data['location_label'] ?? ''));
        $selected_fields = FEU_Einsatz_Template_Helpers::normalize_social_share_fields($share_data['selected_fields'] ?? []);
        $show_title      = in_array('title', $selected_fields, true) && !empty($share_data['title']);
        $title_y         = 190;
        $meta_y          = 252;
        $meta_parts      = [];
        $detail_parts    = [];

        if ('' !== $site_label) {
            $this->draw_text($canvas, 96, 116, $site_label, 18, $muted, 580);
        }

        if ($show_title) {
            $title_lines = $this->wrap_text_lines($share_data['title'] ?? '', 38, $font_path, 920, 3);

            foreach ($title_lines as $line) {
                $this->draw_text($canvas, 96, $title_y, $line, 38, $white, 920);
                $title_y += 56;
            }

            $meta_y = max(384, $title_y + 24);
        }

        if (in_array('number', $selected_fields, true) && '' !== $number_label) {
            $meta_parts[] = sprintf(__('Nr. %s', 'feuer-einsatzberichte'), $number_label);
        }

        if (in_array('date', $selected_fields, true) && !empty($share_data['date_display'])) {
            $meta_parts[] = (string) $share_data['date_display'];
        }

        if (in_array('time', $selected_fields, true) && !empty($share_data['time'])) {
            $meta_parts[] = (string) $share_data['time'];
        }

        if (in_array('category', $selected_fields, true) && !empty($share_data['category_name'])) {
            $meta_parts[] = (string) $share_data['category_name'];
        }

        if (!empty($meta_parts)) {
            $this->draw_text($canvas, 96, $meta_y, implode(' | ', $meta_parts), 20, $muted, 930);
        }

        if (in_array('street', $selected_fields, true) && '' !== $street_label) {
            $detail_parts[] = sprintf(__('Strasse: %s', 'feuer-einsatzberichte'), $street_label);
        }

        if (in_array('location', $selected_fields, true) && '' !== $location_label) {
            $detail_parts[] = sprintf(__('Ort: %s', 'feuer-einsatzberichte'), $location_label);
        }

        $detail_y = !empty($meta_parts)
            ? $meta_y + 40
            : ($show_title ? max(344, $title_y + 16) : 252);

        foreach ($detail_parts as $detail_line) {
            $this->draw_text($canvas, 96, $detail_y, $detail_line, 18, $muted, 930);
            $detail_y += 28;
        }

        if (in_array('description', $selected_fields, true) && !empty($share_data['description'])) {
            $desc_lines = $this->wrap_text_lines((string) $share_data['description'], 20, $font_path, 930, 3);
            $desc_y = !empty($detail_parts)
                ? $detail_y + 18
                : (!empty($meta_parts) ? $meta_y + 60 : ($show_title ? max(444, $title_y + 16) : 300));

            foreach ($desc_lines as $desc_line) {
                $this->draw_text($canvas, 96, $desc_y, $desc_line, 20, $muted, 930);
                $desc_y += 32;
            }
        }

        $link_text    = trim((string) ($share_data['permalink'] ?? ''));
        $link_display = $link_text;

        if ('' !== $link_text) {
            $parsed = wp_parse_url($link_text);

            if (!empty($parsed['host'])) {
                $link_display = $parsed['host'] . (!empty($parsed['path']) ? $parsed['path'] : '/');
            }
        }

        if ('' !== $link_display) {
            $this->draw_text($canvas, 96, 548, $link_display, 18, $soft, 920);
        }

        return $canvas;
    }

    /**
     * @return resource|GdImage|false
     */
    private function build_generated_share_card_canvas_v2(array $share_data) {
        if (!function_exists('imagecreatetruecolor')) {
            return false;
        }

        $layout = FEU_Einsatz_Template_Helpers::normalize_social_share_layout($share_data['layout'] ?? 'wide');
        $dimensions = FEU_Einsatz_Template_Helpers::get_social_share_card_dimensions($layout);
        $width = isset($dimensions['width']) ? max(320, (int) $dimensions['width']) : 1200;
        $height = isset($dimensions['height']) ? max(320, (int) $dimensions['height']) : 630;
        $canvas = imagecreatetruecolor($width, $height);

        if (!$canvas) {
            return false;
        }

        imagealphablending($canvas, true);
        imagesavealpha($canvas, true);

        $background_id = !empty($share_data['background_image_id']) ? absint($share_data['background_image_id']) : 0;

        if ($background_id < 1 && !empty($share_data['post_image_attachment_id'])) {
            $background_id = absint($share_data['post_image_attachment_id']);
        }

        $background_resource = false;
        $used_map_preview_background = false;

        if ($background_id > 0) {
            $background_resource = $this->create_image_resource_from_path((string) get_attached_file($background_id));
        }

        if (!$background_resource && !empty($share_data['map_preview_file']) && is_string($share_data['map_preview_file'])) {
            $background_resource = $this->create_image_resource_from_path($share_data['map_preview_file']);
            $used_map_preview_background = (bool) $background_resource;
        }

        $background_blur = max(0, min(20, (int) ($share_data['image_blur'] ?? 0)));

        if ($background_resource && $background_blur > 0 && function_exists('imagefilter')) {
            $blur_iterations = max(1, min(8, (int) ceil($background_blur / 3)));

            for ($blur_index = 0; $blur_index < $blur_iterations; $blur_index++) {
                imagefilter($background_resource, IMG_FILTER_GAUSSIAN_BLUR);
            }
        }

        if ($background_resource) {
            $this->draw_cover_image_on_canvas($canvas, $background_resource, $width, $height);
            imagedestroy($background_resource);
        } else {
            $fallback = imagecolorallocate($canvas, 15, 23, 42);
            imagefilledrectangle($canvas, 0, 0, $width, $height, $fallback);
        }

        $config = $this->get_share_card_layout_config_v2($layout, $width, $height);
        $font_path = ($this->fn_font_path)();
        $has_background = $background_id > 0 || $used_map_preview_background;
        $title_scale = max(70, min(180, (int) ($share_data['title_scale'] ?? 118)));
        $description_scale = max(70, min(180, (int) ($share_data['description_scale'] ?? 112)));
        $description_max_lines = max(2, min(10, (int) ($share_data['description_max_lines'] ?? $config['description_max_lines'])));
        $logo_scale = max(40, min(220, (int) ($share_data['logo_scale'] ?? 100)));
        $logo_width_setting = array_key_exists('logo_width', $share_data)
            ? max(60, min(520, (int) $share_data['logo_width']))
            : 0;
        $overlay_enabled = !array_key_exists('overlay_enabled', $share_data) || !empty($share_data['overlay_enabled']);
        $panel_radius = max(0, min(120, (int) ($share_data['panel_radius'] ?? $config['panel_radius'])));
        $badge_radius = max(0, min(120, (int) ($share_data['badge_radius'] ?? (int) round($config['badge_height'] / 2))));
        $link_radius = max(0, min(80, (int) ($share_data['link_radius'] ?? 14)));
        $text_align_setting = FEU_Einsatz_Template_Helpers::normalize_social_share_text_align($share_data['text_align'] ?? 'auto');
        $logo_position = FEU_Einsatz_Template_Helpers::normalize_social_share_logo_position($share_data['logo_position'] ?? 'bottom-right');

        $overlay_dark = imagecolorallocatealpha($canvas, 8, 15, 28, $has_background ? 92 : 118);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        $title_color = $this->allocate_hex_color_v2($canvas, (string) ($share_data['title_color'] ?? '#ffffff'));
        $description_color = $this->allocate_hex_color_v2($canvas, (string) ($share_data['description_color'] ?? '#dbeafe'));
        $meta_color = $this->allocate_hex_color_v2($canvas, (string) ($share_data['description_color'] ?? '#dbeafe'));
        $panel_color = $this->allocate_hex_color_v2($canvas, (string) ($share_data['panel_color'] ?? '#0f2f5f'), $config['panel_alpha']);
        $accent_color = $this->allocate_hex_color_v2($canvas, (string) ($share_data['accent_color'] ?? '#ef233c'));
        $link_color = $this->allocate_hex_color_v2($canvas, '#bfdbfe');
        $link_background_color = $this->allocate_hex_color_v2($canvas, (string) ($share_data['panel_color'] ?? '#0f2f5f'), 42);

        if ($has_background && $overlay_enabled) {
            imagefilledrectangle($canvas, 0, 0, $width, $height, $overlay_dark);
        }

        if ($overlay_enabled) {
            $this->draw_filled_rounded_rectangle_v2(
                $canvas,
                $config['panel_left'],
                $config['panel_top'],
                $config['panel_left'] + $config['panel_width'],
                $config['panel_top'] + $config['panel_height'],
                $panel_radius,
                $panel_color
            );
        }

        $site_label = trim((string) get_bloginfo('name'));
        $number_label = trim((string) ($share_data['number_label'] ?? ''));
        $street_label = trim((string) ($share_data['street_label'] ?? ''));
        $location_label = trim((string) ($share_data['location_label'] ?? ''));
        $selected_fields = FEU_Einsatz_Template_Helpers::normalize_social_share_fields($share_data['selected_fields'] ?? []);
        $badge_text = trim((string) ($share_data['badge_text'] ?? 'PRESSEMITTEILUNG'));
        $home_host = wp_parse_url(home_url(), PHP_URL_HOST);
        $url_label = $home_host ? preg_replace('/^www\./i', '', (string) $home_host) : $site_label;
        if ($url_label && stripos($url_label, 'www.') !== 0) {
            $url_label = 'www.' . $url_label;
        }
        $time_label = preg_replace('/\s*uhr\s*$/iu', '', trim((string) ($share_data['time'] ?? '')));
        $time_label = is_string($time_label) ? trim($time_label) : '';

        $site_font_size = $this->scale_share_card_font_size_v2($config['site_font_size'], $description_scale, 14);
        $title_font_size = $this->scale_share_card_font_size_v2($config['title_font_size'], $title_scale, 26);
        $meta_font_size = $this->scale_share_card_font_size_v2($config['meta_font_size'], $description_scale, 15);
        $detail_font_size = $this->scale_share_card_font_size_v2($config['detail_font_size'], $description_scale, 15);
        $meta_line_height = max($meta_font_size + 8, (int) round(($config['detail_line_height'] + 2) * ($description_scale / 100)));
        $detail_line_height = max($detail_font_size + 8, (int) round($config['detail_line_height'] * ($description_scale / 100)));
        $description_font_size = $this->scale_share_card_font_size_v2($config['description_font_size'], $description_scale, 16);
        $description_line_height = max($description_font_size + 10, (int) round($config['description_line_height'] * ($description_scale / 100)));
        $link_font_size = $this->scale_share_card_font_size_v2($config['link_font_size'], $description_scale, 14);
        $logo_width = $logo_width_setting > 0 ? $logo_width_setting : max(80, (int) round($config['logo_width'] * ($logo_scale / 100)));

        $content_left = $config['content_left'];
        $content_right = $config['content_right'];
        $content_width = max(160, $content_right - $content_left);
        $align = 'auto' === $text_align_setting ? $config['text_align'] : $text_align_setting;
        $cursor_y = $config['content_top'];

        if ('' !== $badge_text) {
            $badge_width = min($config['badge_max_width'], max((int) round($content_width * 0.54), 260));
            $badge_left = 'center' === $align
                ? (int) round($content_left + (($content_width - $badge_width) / 2))
                : ('right' === $align ? $content_right - $badge_width : $content_left);
            $badge_top = $cursor_y;
            $this->draw_filled_rounded_rectangle_v2(
                $canvas,
                $badge_left,
                $badge_top,
                $badge_left + $badge_width,
                $badge_top + $config['badge_height'],
                $badge_radius,
                $accent_color
            );
            $this->draw_text_within_box_v2($canvas, strtoupper($badge_text), $config['badge_font_size'], $white, $badge_left + 20, $badge_left + $badge_width - 20, $badge_top + $config['badge_text_baseline'], 'center');
            $cursor_y = $badge_top + $config['badge_height'] + $config['gap_after_badge'];
        }

        if ('' !== $site_label) {
            $this->draw_text_within_box_v2($canvas, $site_label, $site_font_size, $meta_color, $content_left, $content_right, $cursor_y, $align);
            $cursor_y += $config['gap_after_site'];
        }

        if (in_array('title', $selected_fields, true) && !empty($share_data['title'])) {
            $title_layout = $this->fit_share_card_title_layout_v2(
                (string) $share_data['title'],
                $title_font_size,
                $config['title_line_height'],
                $title_scale,
                $font_path,
                $content_width,
                $config['title_max_lines']
            );
            $title_font_size = $title_layout['font_size'];
            $title_line_height = $title_layout['line_height'];
            $title_lines = $title_layout['lines'];

            foreach ($title_lines as $line) {
                $this->draw_text_within_box_v2($canvas, $line, $title_font_size, $title_color, $content_left, $content_right, $cursor_y, $align);
                $cursor_y += $title_line_height;
            }

            $cursor_y += $config['gap_after_title'];
        }

        $meta_lines = [];
        if (in_array('number', $selected_fields, true) && '' !== $number_label) {
            $meta_lines[] = sprintf(__('Einsatz Nr. %s', 'feuer-einsatzberichte'), $number_label);
        }
        if (in_array('date', $selected_fields, true) && !empty($share_data['date_display'])) {
            $meta_lines[] = sprintf(__('Datum %s', 'feuer-einsatzberichte'), (string) $share_data['date_display']);
        }
        if (in_array('time', $selected_fields, true) && '' !== $time_label) {
            $meta_lines[] = sprintf(__('um %s Uhr', 'feuer-einsatzberichte'), $time_label);
        }
        if (in_array('category', $selected_fields, true) && !empty($share_data['category_name'])) {
            $meta_lines[] = sprintf(__('Einsatzart %s', 'feuer-einsatzberichte'), (string) $share_data['category_name']);
        }

        foreach ($meta_lines as $meta_line) {
            $this->draw_text_within_box_v2($canvas, $meta_line, $meta_font_size, $meta_color, $content_left, $content_right, $cursor_y, $align);
            $cursor_y += $meta_line_height;
        }

        if (!empty($meta_lines)) {
            $cursor_y += $config['gap_after_meta'];
        }

        $detail_lines = [];
        if (in_array('street', $selected_fields, true) && '' !== $street_label) {
            $detail_lines[] = $street_label;
        }
        if (in_array('location', $selected_fields, true) && '' !== $location_label) {
            $detail_lines[] = sprintf(__('Ort: %s', 'feuer-einsatzberichte'), $location_label);
        }

        foreach ($detail_lines as $detail_line) {
            $this->draw_text_within_box_v2($canvas, $detail_line, $detail_font_size, $meta_color, $content_left, $content_right, $cursor_y, $align);
            $cursor_y += $detail_line_height;
        }

        if (!empty($detail_lines)) {
            $cursor_y += $config['gap_after_details'];
        }

        if (in_array('description', $selected_fields, true) && !empty($share_data['description'])) {
            if (!empty($meta_lines) || !empty($detail_lines)) {
                $cursor_y += max(8, (int) round($description_line_height * 0.22));
            }

            $desc_lines = $this->wrap_text_lines((string) $share_data['description'], $description_font_size, $font_path, $content_width, $description_max_lines);

            foreach ($desc_lines as $desc_line) {
                $this->draw_text_within_box_v2($canvas, $desc_line, $description_font_size, $description_color, $content_left, $content_right, $cursor_y, $align);
                $cursor_y += $description_line_height;
            }
        }

        if (in_array('url', $selected_fields, true) && '' !== $url_label) {
            $link_baseline = $config['cta_y'] + $link_font_size;
            $link_padding_x = 18;
            $link_box_width = min(
                $content_width,
                max(160, $this->measure_text_width_v2($url_label, $link_font_size, $font_path) + ($link_padding_x * 2))
            );
            $link_box_left = $content_left;

            if ('center' === $align) {
                $link_box_left = (int) round($content_left + (($content_width - $link_box_width) / 2));
            } elseif ('right' === $align) {
                $link_box_left = $content_right - $link_box_width;
            }

            if ($overlay_enabled) {
                $this->draw_filled_rounded_rectangle_v2(
                    $canvas,
                    $link_box_left,
                    $link_baseline - $link_font_size - 10,
                    $link_box_left + $link_box_width,
                    $link_baseline + 10,
                    $link_radius,
                    $link_background_color
                );
            }

            $this->draw_text_within_box_v2($canvas, $url_label, $link_font_size, $link_color, $content_left, $content_right, $link_baseline, $align);
        }

        $logo_id = !empty($share_data['logo_id']) ? absint($share_data['logo_id']) : 0;

        if ($logo_id > 0 && 'hidden' !== $logo_position) {
            $logo_resource = $this->create_image_resource_from_path((string) get_attached_file($logo_id));

            if ($logo_resource) {
                $src_w = imagesx($logo_resource);
                $src_h = imagesy($logo_resource);
                $logo_height = ($src_w > 0 && $src_h > 0)
                    ? max(10, (int) round($logo_width * ($src_h / $src_w)))
                    : $logo_width;
                $logo_left = false !== strpos($logo_position, 'right')
                    ? $width - $logo_width - $config['logo_offset_x']
                    : $config['logo_offset_x'];
                $logo_top = false !== strpos($logo_position, 'bottom')
                    ? $height - $logo_height - $config['logo_offset_y']
                    : $config['logo_offset_y'];
                $this->draw_contain_image_on_canvas_v2($canvas, $logo_resource, $logo_left, $logo_top, $logo_width, $logo_height);
                imagedestroy($logo_resource);
            }
        }

        if ($used_map_preview_background) {
            $attribution_color = imagecolorallocatealpha($canvas, 255, 255, 255, 24);
            $this->draw_text_within_box_v2(
                $canvas,
                'Leaflet | ' . html_entity_decode('&copy;', ENT_QUOTES, 'UTF-8') . ' OpenStreetMap contributors',
                max(14, (int) round($width / 84)),
                $attribution_color,
                18,
                $width - 18,
                $height - 22,
                'left'
            );
        }

        return $canvas;
    }

    private function get_share_card_layout_config_v2(string $layout, int $width, int $height): array {
        if ('story' === $layout) {
            return [
                'panel_left' => 76,
                'panel_top' => 118,
                'panel_width' => 836,
                'panel_height' => 1508,
                'panel_radius' => 48,
                'panel_alpha' => 62,
                'content_left' => 120,
                'content_right' => 868,
                'content_top' => 188,
                'text_align' => 'center',
                'badge_height' => 84,
                'badge_max_width' => 660,
                'badge_font_size' => 28,
                'badge_text_baseline' => 56,
                'gap_after_badge' => 72,
                'site_font_size' => 28,
                'gap_after_site' => 126,
                'title_font_size' => 56,
                'title_line_height' => 76,
                'title_max_lines' => 4,
                'gap_after_title' => 46,
                'meta_font_size' => 28,
                'gap_after_meta' => 20,
                'detail_font_size' => 28,
                'detail_line_height' => 40,
                'gap_after_details' => 22,
                'description_font_size' => 34,
                'description_line_height' => 48,
                'description_max_lines' => 7,
                'cta_y' => 1290,
                'cta_height' => 92,
                'cta_max_width' => 540,
                'cta_font_size' => 30,
                'url_gap_after_cta' => 62,
                'link_font_size' => 22,
                'logo_width' => 290,
                'logo_offset_x' => 26,
                'logo_offset_y' => 30,
            ];
        }

        if ('feed' === $layout) {
            return [
                'panel_left' => 72,
                'panel_top' => 90,
                'panel_width' => 870,
                'panel_height' => 1130,
                'panel_radius' => 42,
                'panel_alpha' => 62,
                'content_left' => 114,
                'content_right' => 900,
                'content_top' => 154,
                'text_align' => 'center',
                'badge_height' => 78,
                'badge_max_width' => 620,
                'badge_font_size' => 24,
                'badge_text_baseline' => 51,
                'gap_after_badge' => 54,
                'site_font_size' => 22,
                'gap_after_site' => 96,
                'title_font_size' => 46,
                'title_line_height' => 62,
                'title_max_lines' => 4,
                'gap_after_title' => 34,
                'meta_font_size' => 24,
                'gap_after_meta' => 18,
                'detail_font_size' => 24,
                'detail_line_height' => 34,
                'gap_after_details' => 18,
                'description_font_size' => 28,
                'description_line_height' => 40,
                'description_max_lines' => 6,
                'cta_y' => 980,
                'cta_height' => 82,
                'cta_max_width' => 470,
                'cta_font_size' => 25,
                'url_gap_after_cta' => 54,
                'link_font_size' => 18,
                'logo_width' => 260,
                'logo_offset_x' => 22,
                'logo_offset_y' => 24,
            ];
        }

        return [
            'panel_left' => 42,
            'panel_top' => 42,
            'panel_width' => $width - 84,
            'panel_height' => $height - 84,
            'panel_radius' => 30,
            'panel_alpha' => 70,
            'content_left' => 82,
            'content_right' => $width - 86,
            'content_top' => 82,
            'text_align' => 'left',
            'badge_height' => 62,
            'badge_max_width' => 520,
            'badge_font_size' => 20,
            'badge_text_baseline' => 42,
            'gap_after_badge' => 34,
            'site_font_size' => 18,
            'gap_after_site' => 58,
            'title_font_size' => 46,
            'title_line_height' => 60,
            'title_max_lines' => 3,
            'gap_after_title' => 18,
            'meta_font_size' => 22,
            'gap_after_meta' => 14,
            'detail_font_size' => 22,
            'detail_line_height' => 30,
            'gap_after_details' => 14,
            'description_font_size' => 24,
            'description_line_height' => 34,
            'description_max_lines' => 4,
            'cta_y' => $height - 136,
            'cta_height' => 60,
            'cta_max_width' => 360,
            'cta_font_size' => 19,
            'url_gap_after_cta' => 42,
            'link_font_size' => 16,
            'logo_width' => 220,
            'logo_offset_x' => 22,
            'logo_offset_y' => 18,
        ];
    }

    private function scale_share_card_font_size_v2(int $base_size, int $scale, int $minimum = 12): int {
        $scale = max(50, min(220, $scale));

        return max($minimum, (int) round($base_size * ($scale / 100)));
    }

    private function measure_text_width_v2(string $text, int $font_size, string $font_path): int {
        $text = ($this->fn_prepare_text)($text);

        if ('' === $text) {
            return 0;
        }

        if ('' !== $font_path && function_exists('imagettfbbox')) {
            return (int) ($this->fn_text_width)($text, $font_size, $font_path);
        }

        return (int) round(strlen($text) * max(7, $font_size * 0.58));
    }

    private function format_share_url_label_v2(string $url, string $cta_text = ''): string {
        $url = trim($url);

        if ('' === $url) {
            return '';
        }

        $parsed = wp_parse_url($url);

        if (empty($parsed['host'])) {
            return $url;
        }

        $host = preg_replace('/^www\./i', '', (string) $parsed['host']);
        $path = !empty($parsed['path']) ? trim((string) $parsed['path'], '/') : '';
        $path_segments = '' !== $path ? array_slice(array_filter(explode('/', $path)), 0, 2) : [];
        $label = $host;

        if (!empty($path_segments)) {
            $label .= '/' . implode('/', $path_segments);
        }

        if (strlen($label) > 42) {
            $label = substr($label, 0, 39) . '...';
        }

        return $label;
    }

    /**
     * @return array{font_size:int,line_height:int,lines:array<int,string>}
     */
    private function fit_share_card_title_layout_v2(
        string $title,
        int $base_font_size,
        int $base_line_height,
        int $title_scale,
        string $font_path,
        int $max_width,
        int $max_lines
    ): array {
        $font_size = max(20, $base_font_size);
        $minimum_font_size = max(20, (int) floor($base_font_size * 0.68));

        while ($font_size >= $minimum_font_size) {
            $lines = $this->wrap_text_lines($title, $font_size, $font_path, $max_width, $max_lines, false);

            if (count($lines) <= $max_lines) {
                $ratio = $font_size / max(1, $base_font_size);

                return [
                    'font_size' => $font_size,
                    'line_height' => max($font_size + 10, (int) round(($base_line_height * ($title_scale / 100)) * $ratio)),
                    'lines' => $lines,
                ];
            }

            $font_size -= 2;
        }

        $lines = $this->wrap_text_lines($title, $minimum_font_size, $font_path, $max_width, $max_lines);
        $ratio = $minimum_font_size / max(1, $base_font_size);

        return [
            'font_size' => $minimum_font_size,
            'line_height' => max($minimum_font_size + 10, (int) round(($base_line_height * ($title_scale / 100)) * $ratio)),
            'lines' => $lines,
        ];
    }

    private function allocate_hex_color_v2($canvas, string $hex, int $alpha = 0) {
        $hex = sanitize_hex_color($hex);

        if (!$hex) {
            $hex = '#ffffff';
        }

        $rgb = sscanf(ltrim($hex, '#'), '%02x%02x%02x');
        $alpha = max(0, min(127, $alpha));

        return imagecolorallocatealpha($canvas, (int) $rgb[0], (int) $rgb[1], (int) $rgb[2], $alpha);
    }

    private function draw_filled_rounded_rectangle_v2($canvas, int $left, int $top, int $right, int $bottom, int $radius, $color): void {
        $radius = max(0, min($radius, (int) floor(min($right - $left, $bottom - $top) / 2)));

        if ($radius < 1) {
            imagefilledrectangle($canvas, $left, $top, $right, $bottom, $color);
            return;
        }

        imagefilledrectangle($canvas, $left + $radius, $top, $right - $radius, $bottom, $color);
        imagefilledrectangle($canvas, $left, $top + $radius, $right, $bottom - $radius, $color);
        imagefilledellipse($canvas, $left + $radius, $top + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($canvas, $right - $radius, $top + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($canvas, $left + $radius, $bottom - $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($canvas, $right - $radius, $bottom - $radius, $radius * 2, $radius * 2, $color);
    }

    private function draw_text_within_box_v2($canvas, string $text, int $font_size, $color, int $left, int $right, int $baseline_y, string $align = 'left'): void {
        $text = ($this->fn_prepare_text)($text);

        if ('' === $text) {
            return;
        }

        $font_path = ($this->fn_font_path)();
        $max_width = max(40, $right - $left);
        $text = $font_path ? ($this->fn_fit_text)($text, $font_size, $font_path, $max_width) : $text;

        if ('center' === $align && $font_path) {
            $text_width = (int) ($this->fn_text_width)($text, $font_size, $font_path);
            $x = $left + (int) floor(max(0, ($max_width - $text_width)) / 2);
            $this->draw_text($canvas, $x, $baseline_y, $text, $font_size, $color, $max_width);
            return;
        }

        if ('right' === $align && $font_path) {
            $text_width = (int) ($this->fn_text_width)($text, $font_size, $font_path);
            $x = $left + (int) floor(max(0, $max_width - $text_width));
            $this->draw_text($canvas, $x, $baseline_y, $text, $font_size, $color, $max_width);
            return;
        }

        $this->draw_text($canvas, $left, $baseline_y, $text, $font_size, $color, $max_width);
    }

    private function draw_contain_image_on_canvas_v2($canvas, $image_resource, int $dest_x, int $dest_y, int $dest_width, int $dest_height): void {
        if (!$canvas || !$image_resource || $dest_width < 1 || $dest_height < 1) {
            return;
        }

        $source_width = imagesx($image_resource);
        $source_height = imagesy($image_resource);

        if ($source_width < 1 || $source_height < 1) {
            return;
        }

        $scale = min($dest_width / $source_width, $dest_height / $source_height);
        $draw_width = max(1, (int) floor($source_width * $scale));
        $draw_height = max(1, (int) floor($source_height * $scale));
        $draw_x = $dest_x + (int) floor(($dest_width - $draw_width) / 2);
        $draw_y = $dest_y + (int) floor(($dest_height - $draw_height) / 2);

        imagecopyresampled($canvas, $image_resource, $draw_x, $draw_y, 0, 0, $draw_width, $draw_height, $source_width, $source_height);
    }

    /**
     * @return resource|GdImage|false
     */
    private function create_image_resource_from_path(string $file_path) {
        if ('' === $file_path || !file_exists($file_path) || !is_readable($file_path)) {
            return false;
        }

        $mime_type = '';

        if (function_exists('wp_check_filetype')) {
            $filetype  = wp_check_filetype($file_path);
            $mime_type = isset($filetype['type']) ? (string) $filetype['type'] : '';
        }

        if ('' === $mime_type && function_exists('mime_content_type')) {
            $mime_type = (string) mime_content_type($file_path);
        }

        switch ($mime_type) {
            case 'image/jpeg': return function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($file_path) : false;
            case 'image/png':  return function_exists('imagecreatefrompng')  ? @imagecreatefrompng($file_path)  : false;
            case 'image/gif':  return function_exists('imagecreatefromgif')  ? @imagecreatefromgif($file_path)  : false;
            case 'image/webp': return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file_path) : false;
        }

        if (!function_exists('imagecreatefromstring')) {
            return false;
        }

        $contents = @file_get_contents($file_path);

        return false !== $contents ? @imagecreatefromstring($contents) : false;
    }

    /**
     * @param resource|GdImage $canvas
     * @param resource|GdImage $image_resource
     */
    private function draw_cover_image_on_canvas($canvas, $image_resource, int $dest_width, int $dest_height): void {
        if (!$canvas || !$image_resource) {
            return;
        }

        $source_width  = imagesx($image_resource);
        $source_height = imagesy($image_resource);

        if ($source_width < 1 || $source_height < 1) {
            return;
        }

        $source_ratio      = $source_width / $source_height;
        $destination_ratio = $dest_width / $dest_height;
        $crop_width        = $source_width;
        $crop_height       = $source_height;
        $source_x          = 0;
        $source_y          = 0;

        if ($source_ratio > $destination_ratio) {
            $crop_width = (int) round($source_height * $destination_ratio);
            $source_x   = (int) max(0, floor(($source_width - $crop_width) / 2));
        } else {
            $crop_height = (int) round($source_width / $destination_ratio);
            $source_y    = (int) max(0, floor(($source_height - $crop_height) / 2));
        }

        imagecopyresampled($canvas, $image_resource, 0, 0, $source_x, $source_y, $dest_width, $dest_height, $crop_width, $crop_height);
    }

    /**
     * @param resource|GdImage $canvas
     * @param int              $color GD-Farbe
     */
    private function draw_text($canvas, int $x, int $y, string $text, int $font_size, $color, int $max_width = 0): void {
        ($this->fn_draw_text)($canvas, $x, $y, $text, $font_size, $color, $max_width);
    }

    /**
     * @return string[]
     */
    private function wrap_text_lines(string $text, int $font_size, string $font_path, int $max_width, int $max_lines = 3, bool $truncate = true): array {
        $text      = ($this->fn_prepare_text)($text);
        $max_lines = max(1, $max_lines);

        if ('' === $text) {
            return [];
        }

        if ('' === $font_path || !function_exists('imagettfbbox')) {
            $fallback = remove_accents($text);
            $wrapped  = preg_split('/\r\n|\r|\n/', wordwrap($fallback, 32, "\n", true));

            return array_slice(array_filter(array_map('trim', (array) $wrapped)), 0, $max_lines);
        }

        $words        = preg_split('/\s+/', $text);
        $lines        = [];
        $current_line = '';
        $truncated    = false;

        foreach ((array) $words as $word) {
            $candidate       = '' === $current_line ? $word : $current_line . ' ' . $word;
            $candidate_width = ($this->fn_text_width)($candidate, $font_size, $font_path);

            if ($candidate_width <= $max_width || '' === $current_line) {
                $current_line = $candidate;
                continue;
            }

            $lines[] = $current_line;

            if ($truncate && count($lines) >= $max_lines) {
                $truncated = true;
                break;
            }

            $current_line = $word;
        }

        if (count($lines) < $max_lines && '' !== $current_line) {
            $lines[] = $current_line;
        } elseif ('' !== $current_line) {
            $truncated = true;
        }

        if (!$truncate) {
            return array_values(array_filter(array_map('trim', $lines)));
        }

        if ($truncated && !empty($lines)) {
            $last = count($lines) - 1;
            $lines[$last] = ($this->fn_fit_text)(
                rtrim($lines[$last]) . ' ...',
                $font_size,
                $font_path,
                $max_width
            );
        }

        return array_values(array_filter(array_map('trim', $lines)));
    }
}
