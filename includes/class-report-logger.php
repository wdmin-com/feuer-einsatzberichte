<?php
/**
 * FEU_Einsatz_Report_Logger
 *
 * Einsatz-spezifisches Logging: Payload-Aufbau, Change-Set-Diffing,
 * WordPress-Hooks für Papierkorb und endgültiges Löschen.
 *
 * Ausgelagert aus class-admin.php in Version 3.1.35 (Phase 1 Refactoring).
 *
 * Wird in class-admin.php instanziiert und über Getter zugänglich gemacht:
 *   $this->report_logger = new FEU_Einsatz_Report_Logger();
 *
 * Hooks werden in class-admin.php registriert:
 *   add_action('trashed_post',      [$this->report_logger, 'log_trashed_report']);
 *   add_action('before_delete_post', [$this->report_logger, 'log_deleted_report']);
 */

if (!defined('ABSPATH')) {
    exit;
}

class FEU_Einsatz_Report_Logger {

    // ── Öffentliche Hooks (direkt als WP-Action-Callbacks nutzbar) ───

    public function log_trashed_report(int $post_id): void {
        $report = $this->get_payload($post_id);

        if (!$report) {
            return;
        }

        FEU_Einsatz_Logger::log(
            'report_trashed',
            'report',
            $post_id,
            __('Einsatzbericht in den Papierkorb verschoben', 'feuer-einsatzberichte'),
            $this->build_log_context($report)
        );
    }

    public function log_deleted_report(int $post_id): void {
        $report = $this->get_payload($post_id);

        if (!$report) {
            return;
        }

        FEU_Einsatz_Logger::log(
            'report_deleted',
            'report',
            $post_id,
            __('Einsatzbericht dauerhaft gelöscht', 'feuer-einsatzberichte'),
            $this->build_log_context($report)
        );
    }

    // ── Öffentliche Helfer (für Report-Editor-Klasse) ────────────────

    /**
     * Aktuellen Snapshot eines Einsatzberichts für Log-Vergleiche laden.
     * Gibt null zurück, wenn der Post kein Einsatzbericht ist.
     *
     * @return array<string,mixed>|null
     */
    public function get_payload(int $post_id): ?array {
        $post = get_post($post_id);

        if (
            !($post instanceof WP_Post)
            || 'post' !== $post->post_type
            || '1' !== get_post_meta($post_id, '_feu_einsatz_einsatzbericht', true)
        ) {
            return null;
        }

        $category_ids    = array_map('absint', wp_get_post_categories($post_id));
        $category_labels = [];

        foreach ($category_ids as $category_id) {
            $term = get_term($category_id, 'category');

            if ($term instanceof WP_Term && !is_wp_error($term)) {
                $category_labels[] = $term->name;
            }
        }

        sort($category_ids);
        sort($category_labels);

        return [
            'post'             => $post,
            'title'            => (string) $post->post_title,
            'status'           => (string) $post->post_status,
            'street'           => (string) get_post_meta($post_id, '_feu_einsatz_strasse',           true),
            'house_number'     => (string) get_post_meta($post_id, '_feu_einsatz_hausnummer',         true),
            'plz'              => (string) get_post_meta($post_id, '_feu_einsatz_plz',               true),
            'city'             => (string) get_post_meta($post_id, '_feu_einsatz_stadt',             true),
            'date'             => (string) get_post_meta($post_id, '_feu_einsatz_datum',             true),
            'time'             => (string) get_post_meta($post_id, '_feu_einsatz_uhrzeit',           true),
            'comments_enabled' => '1' === (string) get_post_meta($post_id, '_feu_einsatz_comments_enabled', true),
            'categories'       => $category_ids,
            'category_labels'  => $category_labels,
        ];
    }

    /**
     * Change-Set zwischen zwei Payload-Snapshots berechnen.
     * Gibt nur geänderte Felder zurück (before / after / label).
     *
     * @param array<string,mixed> $before
     * @param array<string,mixed> $after
     * @return array<string,array{label:string,before:mixed,after:mixed}>
     */
    public function get_change_set(array $before, array $after): array {
        $labels = [
            'title'            => __('Titel',        'feuer-einsatzberichte'),
            'status'           => __('Status',       'feuer-einsatzberichte'),
            'street'           => __('Straße',       'feuer-einsatzberichte'),
            'house_number'     => __('Hausnummer',   'feuer-einsatzberichte'),
            'plz'              => __('PLZ',          'feuer-einsatzberichte'),
            'city'             => __('Stadt',        'feuer-einsatzberichte'),
            'date'             => __('Datum',        'feuer-einsatzberichte'),
            'time'             => __('Uhrzeit',      'feuer-einsatzberichte'),
            'comments_enabled' => __('Kommentare',  'feuer-einsatzberichte'),
            'category_labels'  => __('Kategorien',  'feuer-einsatzberichte'),
        ];

        $changes = [];

        foreach ($labels as $key => $label) {
            $before_value = $before[$key] ?? '';
            $after_value  = $after[$key]  ?? '';

            if (wp_json_encode($before_value) === wp_json_encode($after_value)) {
                continue;
            }

            $changes[$key] = [
                'label'  => $label,
                'before' => $before_value,
                'after'  => $after_value,
            ];
        }

        return $changes;
    }

    // ── Interne Helfer ───────────────────────────────────────────────

    /**
     * @param array<string,mixed> $report
     * @return array<string,mixed>
     */
    private function build_log_context(array $report): array {
        return [
            'title'            => $report['title'],
            'status'           => $report['status'],
            'street'           => $report['street'],
            'house_number'     => $report['house_number'],
            'plz'              => $report['plz'],
            'city'             => $report['city'],
            'date'             => $report['date'],
            'time'             => $report['time'],
            'comments_enabled' => $report['comments_enabled'],
            'categories'       => $report['categories'],
            'category_labels'  => $report['category_labels'],
        ];
    }
}
