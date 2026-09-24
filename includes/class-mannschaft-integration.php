<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Optional bridge to Feuer-Mannschaft.
 *
 * Einsatzberichte keeps its participant IDs as a small read-only projection so
 * historic report/statistics relations remain valid. The source of truth is
 * always the Mannschaft profile post when this integration is enabled.
 */
final class FEU_Einsatz_Mannschaft_Integration {

    public const PROVIDER_OPTION = 'feu_einsatz_participant_provider';
    public const PROVIDER_VALUE = 'mannschaft';
    private const PROFILE_META = '_feuer_mannschaft_profile';
    private const META_PREFIX = '_feuer_mannschaft_';

    private FEU_Einsatz_Database $db;

    public function __construct(FEU_Einsatz_Database $db) {
        $this->db = $db;
        add_action('save_post', [$this, 'sync_saved_member'], 30, 3);
        add_action('trashed_post', [$this, 'sync_member_status'], 10, 1);
        add_action('untrashed_post', [$this, 'sync_member_status'], 10, 1);
    }

    public static function is_available(): bool {
        return defined('FEUER_MANNSCHAFT_VERSION') && class_exists('Feuer_Mannschaft_Plugin');
    }

    public static function is_enabled(): bool {
        return self::PROVIDER_VALUE === get_option(self::PROVIDER_OPTION, 'local') && self::is_available();
    }

    public static function get_management_url(): string {
        return admin_url('edit.php?post_type=post&feuer_mannschaft_profile=1');
    }

    public function sync_all(): array {
        if (!self::is_available()) {
            return ['synced' => 0, 'skipped' => true];
        }

        $member_ids = get_posts([
            'post_type' => 'post',
            'post_status' => ['publish', 'private', 'draft', 'pending', 'future', 'feuer_mannschaft_archived'],
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [[
                'key' => self::PROFILE_META,
                'value' => '1',
                'compare' => '=',
            ]],
            'orderby' => 'ID',
            'order' => 'ASC',
            'no_found_rows' => true,
        ]);

        $synced = 0;
        $source_functions = [];
        foreach (array_map('absint', (array) $member_ids) as $member_id) {
            if (false !== $this->sync_member($member_id)) {
                $synced++;
            }

            $functions = get_post_meta($member_id, self::META_PREFIX . 'function', true);
            $functions = is_array($functions) ? $functions : preg_split('/\s*,\s*/', (string) $functions);
            $source_functions = array_merge($source_functions, array_map('sanitize_text_field', (array) $functions));
        }

        $source_functions = array_values(array_unique(array_filter($source_functions)));
        if (!empty($source_functions)) {
            $existing_functions = array_values(array_filter(array_map(
                'sanitize_text_field',
                (array) get_option('feu_einsatz_functions', FEU_Einsatz_Installer::get_default_functions())
            )));
            update_option('feu_einsatz_functions', array_values(array_unique(array_merge($existing_functions, $source_functions))), false);
        }

        $this->db->invalidate_statistics_dashboard_cache();
        update_option('feu_einsatz_mannschaft_last_sync', current_time('mysql'), false);

        return ['synced' => $synced, 'skipped' => false];
    }

    public function sync_saved_member($post_id, $post, $update): void {
        if (!self::is_enabled() || !$post instanceof WP_Post || 'post' !== $post->post_type || wp_is_post_revision($post_id)) {
            return;
        }

        if ('1' !== (string) get_post_meta($post_id, self::PROFILE_META, true)) {
            return;
        }

        $this->sync_member((int) $post_id);
    }

    public function sync_member_status($post_id): void {
        if (self::is_enabled() && '1' === (string) get_post_meta($post_id, self::PROFILE_META, true)) {
            $this->sync_member((int) $post_id);
        }
    }

    public function sync_member(int $member_id) {
        if (!self::is_available() || $member_id < 1 || '1' !== (string) get_post_meta($member_id, self::PROFILE_META, true)) {
            return false;
        }

        $post = get_post($member_id);
        if (!$post instanceof WP_Post) {
            return false;
        }

        $functions = get_post_meta($member_id, self::META_PREFIX . 'function', true);
        $functions = is_array($functions) ? $functions : preg_split('/\s*,\s*/', (string) $functions);
        $functions = array_values(array_filter(array_map('sanitize_text_field', (array) $functions)));
        $gallery = get_post_meta($member_id, '_feuer_mannschaft_gallery', true);
        $gallery = is_array($gallery) ? array_values(array_filter(array_map('absint', $gallery))) : [];
        $primary_image_id = (int) get_post_thumbnail_id($member_id);
        if ($primary_image_id > 0 && !in_array($primary_image_id, $gallery, true)) {
            array_unshift($gallery, $primary_image_id);
        }

        return $this->db->sync_external_participant(self::PROVIDER_VALUE, $member_id, [
            'vorname' => get_post_meta($member_id, self::META_PREFIX . 'first_name', true),
            'nachname' => get_post_meta($member_id, self::META_PREFIX . 'last_name', true),
            'job_title' => get_post_meta($member_id, self::META_PREFIX . 'job_title', true),
            'entry_date' => get_post_meta($member_id, self::META_PREFIX . 'entry_date', true),
            'rank_title' => get_post_meta($member_id, self::META_PREFIX . 'rank', true),
            'member_function' => implode(', ', $functions),
            'education' => get_post_meta($member_id, self::META_PREFIX . 'education', true),
            'description' => get_post_meta($member_id, self::META_PREFIX . 'description', true),
            'sort_order' => absint(get_post_meta($member_id, self::META_PREFIX . 'sort_order', true)),
            'category_ids' => wp_get_post_categories($member_id),
            'gallery_ids' => $gallery,
            'primary_image_id' => $primary_image_id,
            'default_functions' => $functions,
            'is_archived' => 'feuer_mannschaft_archived' === $post->post_status ? 1 : 0,
            'is_deleted' => 'trash' === $post->post_status ? 1 : 0,
        ]);
    }

    /** @return array<int, array{id:int,name:string}> */
    public function get_profiles(): array {
        if (!self::is_available()) {
            return [];
        }

        $ids = get_posts([
            'post_type' => 'post',
            'post_status' => ['publish', 'private', 'draft', 'pending', 'future', 'feuer_mannschaft_archived'],
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [[
                'key' => self::PROFILE_META,
                'value' => '1',
                'compare' => '=',
            ]],
            'orderby' => 'title',
            'order' => 'ASC',
            'no_found_rows' => true,
        ]);

        $profiles = [];
        foreach (array_map('absint', (array) $ids) as $id) {
            $profiles[] = [
                'id' => $id,
                'name' => trim((string) get_post_meta($id, self::META_PREFIX . 'first_name', true) . ' ' . (string) get_post_meta($id, self::META_PREFIX . 'last_name', true)),
            ];
        }

        return array_values(array_filter($profiles, static fn($profile) => '' !== $profile['name']));
    }

    public function connect_existing_participant(int $participant_id, int $member_id) {
        if (!self::is_available() || $participant_id < 1 || $member_id < 1 || '1' !== (string) get_post_meta($member_id, self::PROFILE_META, true)) {
            return false;
        }

        $post = get_post($member_id);
        if (!$post instanceof WP_Post || !$this->db->get_participant($participant_id)) {
            return false;
        }

        $functions = get_post_meta($member_id, self::META_PREFIX . 'function', true);
        $functions = is_array($functions) ? $functions : preg_split('/\s*,\s*/', (string) $functions);
        $functions = array_values(array_filter(array_map('sanitize_text_field', (array) $functions)));
        $gallery = get_post_meta($member_id, '_feuer_mannschaft_gallery', true);
        $gallery = is_array($gallery) ? array_values(array_filter(array_map('absint', $gallery))) : [];
        $primary_image_id = (int) get_post_thumbnail_id($member_id);
        if ($primary_image_id > 0 && !in_array($primary_image_id, $gallery, true)) {
            array_unshift($gallery, $primary_image_id);
        }

        return $this->db->map_existing_participant_to_external(self::PROVIDER_VALUE, $member_id, $participant_id, [
            'vorname' => get_post_meta($member_id, self::META_PREFIX . 'first_name', true),
            'nachname' => get_post_meta($member_id, self::META_PREFIX . 'last_name', true),
            'job_title' => get_post_meta($member_id, self::META_PREFIX . 'job_title', true),
            'entry_date' => get_post_meta($member_id, self::META_PREFIX . 'entry_date', true),
            'rank_title' => get_post_meta($member_id, self::META_PREFIX . 'rank', true),
            'member_function' => implode(', ', $functions),
            'education' => get_post_meta($member_id, self::META_PREFIX . 'education', true),
            'description' => get_post_meta($member_id, self::META_PREFIX . 'description', true),
            'sort_order' => absint(get_post_meta($member_id, self::META_PREFIX . 'sort_order', true)),
            'category_ids' => wp_get_post_categories($member_id),
            'gallery_ids' => $gallery,
            'primary_image_id' => $primary_image_id,
            'default_functions' => $functions,
            'is_archived' => 'feuer_mannschaft_archived' === $post->post_status ? 1 : 0,
            'is_deleted' => 'trash' === $post->post_status ? 1 : 0,
        ]);
    }
}
