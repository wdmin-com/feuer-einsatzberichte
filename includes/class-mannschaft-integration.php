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

        $preview = $this->preview_sync();
        $review_ids = [];
        foreach ($preview['rows'] as $row) {
            if ('review' === $row['status']) {
                $review_ids[] = (int) $row['profile']['id'];
            }
        }
        $synced = 0;
        foreach (array_map('absint', (array) $member_ids) as $member_id) {
            if (in_array($member_id, $review_ids, true)) {
                continue;
            }
            if (false !== $this->sync_member($member_id)) {
                $synced++;
            }
        }

        $this->db->invalidate_statistics_dashboard_cache();
        update_option('feu_einsatz_mannschaft_last_sync', current_time('mysql'), false);

        return ['synced' => $synced, 'skipped' => false, 'review' => count($review_ids)];
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
            $profiles[] = $this->get_profile_identity($id);
        }

        return array_values(array_filter($profiles, static fn($profile) => '' !== $profile['name']));
    }

    /**
     * Build a read-only plan before a manual synchronization changes data.
     * Ambiguous names are never linked automatically.
     */
    public function preview_sync(): array {
        $result = [
            'counts' => ['update' => 0, 'connect' => 0, 'create' => 0, 'review' => 0],
            'rows' => [],
        ];
        if (!self::is_available()) {
            return $result;
        }

        $participants = $this->db->get_participants(['include_archived' => true, 'include_deleted' => false]);
        foreach ($this->get_profiles() as $profile) {
            $linked = null;
            $exact = null;
            $suggestion = null;
            foreach ($participants as $participant) {
                if (self::PROVIDER_VALUE === (string) ($participant->external_provider ?? '') && (int) ($participant->external_id ?? 0) === (int) $profile['id']) {
                    $linked = $participant;
                    break;
                }
                if (!empty($participant->external_id)) {
                    continue;
                }
                $local_first = $this->normalize_name((string) $participant->vorname);
                $local_last = $this->normalize_name((string) $participant->nachname);
                if ($local_first === $profile['normalized_first'] && $local_last === $profile['normalized_last']) {
                    $exact = $participant;
                } elseif (!$suggestion && $this->is_name_suggestion($local_first, $local_last, $profile['normalized_first'], $profile['normalized_last'])) {
                    $suggestion = $participant;
                }
            }

            if ($linked) {
                $status = 'update';
                $participant = $linked;
            } elseif ($exact) {
                $status = 'connect';
                $participant = $exact;
            } elseif ($suggestion) {
                $status = 'review';
                $participant = $suggestion;
            } else {
                $status = 'create';
                $participant = null;
            }
            $result['counts'][$status]++;
            $result['rows'][] = [
                'status' => $status,
                'profile' => $profile,
                'participant' => $participant,
                'local_name' => $participant ? trim((string) $participant->vorname . ' ' . (string) $participant->nachname) : '',
            ];
        }
        return $result;
    }

    private function get_profile_identity(int $id): array {
        $first = trim((string) get_post_meta($id, self::META_PREFIX . 'first_name', true));
        $last = trim((string) get_post_meta($id, self::META_PREFIX . 'last_name', true));
        return [
            'id' => $id,
            'name' => trim($first . ' ' . $last),
            'first_name' => $first,
            'last_name' => $last,
            'job_title' => trim((string) get_post_meta($id, self::META_PREFIX . 'job_title', true)),
            'rank_title' => trim((string) get_post_meta($id, self::META_PREFIX . 'rank', true)),
            'primary_image_id' => (int) get_post_thumbnail_id($id),
            'normalized_first' => $this->normalize_name($first),
            'normalized_last' => $this->normalize_name($last),
        ];
    }

    private function normalize_name(string $name): string {
        $name = strtolower(remove_accents(trim($name)));
        return preg_replace('/[^a-z0-9]+/u', '', $name) ?: '';
    }

    private function is_name_suggestion(string $local_first, string $local_last, string $remote_first, string $remote_last): bool {
        if ('' === $local_first || '' === $local_last || '' === $remote_first || '' === $remote_last) {
            return false;
        }
        $same_initial = $local_first[0] === $remote_first[0];
        $last_related = strlen($local_last) >= 4 && strlen($remote_last) >= 4
            && (str_starts_with($local_last, $remote_last) || str_starts_with($remote_last, $local_last));
        return $same_initial && $last_related;
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
        ], true);
    }
}
