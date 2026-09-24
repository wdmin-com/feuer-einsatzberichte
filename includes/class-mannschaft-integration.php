<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Optional bridge to Feuer-Mannschaft.
 *
 * Mannschaft can be used as a manual import source. Einsatzberichte always
 * keeps one local participant list and stable participant IDs, so existing
 * report and statistics relations never depend on another plugin.
 */
final class FEU_Einsatz_Mannschaft_Integration {

    public const PROVIDER_OPTION = 'feu_einsatz_participant_provider';
    public const PROVIDER_VALUE = 'mannschaft';
    private const PROFILE_META = '_feuer_mannschaft_profile';
    private const META_PREFIX = '_feuer_mannschaft_';

    private FEU_Einsatz_Database $db;

    public function __construct(FEU_Einsatz_Database $db) {
        $this->db = $db;
    }

    public static function is_available(): bool {
        return defined('FEUER_MANNSCHAFT_VERSION') && class_exists('Feuer_Mannschaft_Plugin');
    }

    public static function is_enabled(): bool {
        // Kept for backwards compatibility with extensions that called this
        // method. Since 3.2.76 Mannschaft is never an automatic data source.
        return false;
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
        foreach (array_map('absint', (array) $member_ids) as $member_id) {
            if (false !== $this->sync_member($member_id)) {
                $synced++;
            }
        }

        $this->db->invalidate_statistics_dashboard_cache();
        update_option('feu_einsatz_mannschaft_last_sync', current_time('mysql'), false);

        return ['synced' => $synced, 'skipped' => false];
    }

    public function sync_member(int $member_id) {
        if (!self::is_available() || $member_id < 1 || '1' !== (string) get_post_meta($member_id, self::PROFILE_META, true)) {
            return false;
        }

        $primary_image_id = (int) get_post_thumbnail_id($member_id);

        return $this->db->sync_external_participant_identity(self::PROVIDER_VALUE, $member_id, [
            'vorname' => get_post_meta($member_id, self::META_PREFIX . 'first_name', true),
            'nachname' => get_post_meta($member_id, self::META_PREFIX . 'last_name', true),
            'job_title' => get_post_meta($member_id, self::META_PREFIX . 'job_title', true),
            'rank_title' => get_post_meta($member_id, self::META_PREFIX . 'rank', true),
            'primary_image_id' => $primary_image_id,
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

        if (!$this->db->get_participant($participant_id)) {
            return false;
        }

        $primary_image_id = (int) get_post_thumbnail_id($member_id);

        return $this->db->map_existing_participant_identity_to_external(self::PROVIDER_VALUE, $member_id, $participant_id, [
            'vorname' => get_post_meta($member_id, self::META_PREFIX . 'first_name', true),
            'nachname' => get_post_meta($member_id, self::META_PREFIX . 'last_name', true),
            'job_title' => get_post_meta($member_id, self::META_PREFIX . 'job_title', true),
            'rank_title' => get_post_meta($member_id, self::META_PREFIX . 'rank', true),
            'primary_image_id' => $primary_image_id,
        ]);
    }
}
