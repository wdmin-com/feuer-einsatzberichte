<?php
if (!defined('ABSPATH')) {
    exit;
}
$storage_labels = [
    'old' => __('Bisherige Struktur', 'feuer-einsatzberichte'),
    'mixed' => __('Gemischter Zustand', 'feuer-einsatzberichte'),
    'new' => __('Neue Struktur', 'feuer-einsatzberichte'),
];
$post_status = (string) ($migration_state['post_run']['status'] ?? 'not_started');
$keyword_status = (string) ($migration_state['keyword_run']['status'] ?? 'not_started');
if ('not_started' === $post_status && 0 === $migration_state['old_reports']) {
    $post_status = 'not_required';
}
if ('not_started' === $keyword_status && $migration_state['keywords_enabled']) {
    $keyword_status = 'not_required';
}
$status_labels = [
    'not_started' => __('Noch nicht gestartet', 'feuer-einsatzberichte'),
    'not_required' => __('Kein Umzug erforderlich', 'feuer-einsatzberichte'),
    'running' => __('Läuft', 'feuer-einsatzberichte'),
    'failed' => __('Fehler', 'feuer-einsatzberichte'),
    'partial_error' => __('Teilweise übertragen – einzelne Berichte benötigen einen erneuten Versuch', 'feuer-einsatzberichte'),
    'failed_rolled_back' => __('Fehler, Änderungen zurückgesetzt', 'feuer-einsatzberichte'),
    'rollback_failed' => __('Rücksetzung fehlgeschlagen – Bearbeitung gesperrt', 'feuer-einsatzberichte'),
    'complete' => __('Abgeschlossen', 'feuer-einsatzberichte'),
    'rolling_back' => __('Wird zurückgesetzt', 'feuer-einsatzberichte'),
    'rolled_back' => __('Zurückgesetzt', 'feuer-einsatzberichte'),
];
$post_counts = (array) ($migration_state['post_run']['counts'] ?? []);
$post_total = (int) ($migration_state['post_run']['total'] ?? 0);
$keyword_total = count((array) ($migration_state['keyword_run']['reports'] ?? []));
$keyword_processed = count((array) ($migration_state['keyword_run']['processed'] ?? []));
?>
<div class="wrap feu-einsatz-migration-page">
    <h1><?php esc_html_e('Datenmigration', 'feuer-einsatzberichte'); ?></h1>
    <p><?php esc_html_e('Diese Übersicht liest den aktuellen Speicherort und die vorhandenen Migrationsjournale. Das Öffnen der Seite ändert keine Daten.', 'feuer-einsatzberichte'); ?></p>
    <div class="notice notice-info inline"><p><strong><?php esc_html_e('Speicherstatus:', 'feuer-einsatzberichte'); ?></strong>
        <?php echo esc_html($storage_labels[$migration_state['storage']] ?? $storage_labels['mixed']); ?>.
        <?php if (!empty($migration_state['completed'])) : ?><?php esc_html_e('Die bestätigte Umstellung ist abgeschlossen.', 'feuer-einsatzberichte'); ?><?php endif; ?>
        <?php if (!empty($migration_state['accepted_before']) && empty($migration_state['completed'])) : ?><?php esc_html_e('Eine frühere Abnahme ist gespeichert. Der aktuelle Speicherzustand muss erneut geprüft werden.', 'feuer-einsatzberichte'); ?><?php endif; ?>
    </p></div>

    <div class="feu-einsatz-migration-table-wrap" role="region" aria-label="<?php esc_attr_e('Status der Datenmigration', 'feuer-einsatzberichte'); ?>" tabindex="0">
    <table class="widefat striped feu-einsatz-migration-table">
        <thead><tr><th><?php esc_html_e('Bereich', 'feuer-einsatzberichte'); ?></th><th><?php esc_html_e('Bisherige Struktur', 'feuer-einsatzberichte'); ?></th><th><?php esc_html_e('Neue Struktur / Status', 'feuer-einsatzberichte'); ?></th></tr></thead>
        <tbody>
            <tr><th scope="row"><?php esc_html_e('Einsatzberichte', 'feuer-einsatzberichte'); ?></th><td><?php echo esc_html(number_format_i18n($migration_state['old_reports'])); ?></td><td><?php echo esc_html(number_format_i18n($migration_state['new_reports'])); ?> · <?php echo esc_html($status_labels[$post_status] ?? $post_status); ?><?php if ($post_total > 0) : ?><br><small><?php echo esc_html(sprintf(__('%1$d von %2$d im Journal bestätigt, %3$d Fehler', 'feuer-einsatzberichte'), (int) ($post_counts['migrated'] ?? 0), $post_total, (int) ($post_counts['failed'] ?? 0))); ?></small><?php endif; ?></td></tr>
            <tr><th scope="row"><?php esc_html_e('Einsatzstichworte', 'feuer-einsatzberichte'); ?></th><td><?php echo esc_html(number_format_i18n($migration_state['legacy_keywords'])); ?><?php if ($migration_state['keywords_enabled']) : ?><br><small><?php esc_html_e('Bleiben für bisherige URLs erhalten', 'feuer-einsatzberichte'); ?></small><?php endif; ?></td><td><?php echo esc_html($migration_state['keywords_enabled'] ? __('Aktiv', 'feuer-einsatzberichte') : __('Noch nicht aktiv', 'feuer-einsatzberichte')); ?> · <?php echo esc_html($status_labels[$keyword_status] ?? $keyword_status); ?><?php if ($keyword_total > 0) : ?><br><small><?php echo esc_html(sprintf(__('%1$d von %2$d Berichten im Journal bearbeitet', 'feuer-einsatzberichte'), $keyword_processed, $keyword_total)); ?></small><?php endif; ?></td></tr>
            <tr><th scope="row"><?php esc_html_e('WordPress-Benutzer', 'feuer-einsatzberichte'); ?></th><td colspan="2"><?php esc_html_e('Konten bleiben in WordPress; sie werden nicht kopiert.', 'feuer-einsatzberichte'); ?> <?php echo null === $missing_authors ? esc_html__('Autorenprüfung nicht verfügbar.', 'feuer-einsatzberichte') : esc_html(sprintf(__('Fehlende Autoren: %d.', 'feuer-einsatzberichte'), $missing_authors)); ?></td></tr>
            <tr><th scope="row"><?php esc_html_e('Teilnehmer, Organisationen, Medien und Kommentare', 'feuer-einsatzberichte'); ?></th><td colspan="2"><?php echo esc_html(!empty($migration_state['accepted_before']) ? __('Verknüpfungen wurden bei der Abnahme bestätigt.', 'feuer-einsatzberichte') : __('Keine Kopie erforderlich. Prüfung der Verknüpfungen bei der Abnahme noch ausstehend.', 'feuer-einsatzberichte')); ?></td></tr>
        </tbody>
    </table>
    </div>

    <?php if ($keyword_items) : ?>
        <h2><?php esc_html_e('Status der Einsatzstichworte', 'feuer-einsatzberichte'); ?></h2>
        <div class="feu-einsatz-migration-table-wrap" role="region" aria-label="<?php esc_attr_e('Status der Einsatzstichworte', 'feuer-einsatzberichte'); ?>" tabindex="0">
        <table class="widefat striped feu-einsatz-migration-table">
            <thead><tr><th><?php esc_html_e('Bisheriges Stichwort', 'feuer-einsatzberichte'); ?></th><th><?php esc_html_e('Neuer Eintrag', 'feuer-einsatzberichte'); ?></th><th><?php esc_html_e('Status', 'feuer-einsatzberichte'); ?></th></tr></thead>
            <tbody>
                <?php foreach (array_slice($keyword_items, 0, 200) as $item) : ?>
                    <tr>
                        <th scope="row"><?php echo esc_html($item['name'] ?: sprintf(__('Kategorie-ID %d', 'feuer-einsatzberichte'), $item['id'])); ?> <small>#<?php echo esc_html($item['id']); ?></small></th>
                        <td><?php echo $item['target_id'] ? esc_html('#' . $item['target_id']) : '—'; ?></td>
                        <td><?php
                            $item_statuses = [
                                'missing' => __('Fehlt in der bisherigen Struktur', 'feuer-einsatzberichte'),
                                'unavailable' => __('Status konnte nicht gelesen werden', 'feuer-einsatzberichte'),
                                'failed' => __('Fehler bei diesem Stichwort', 'feuer-einsatzberichte'),
                                'pending' => __('Ausstehend', 'feuer-einsatzberichte'),
                                'mapped' => __('Zugeordnet, noch nicht aktiviert', 'feuer-einsatzberichte'),
                                'active' => __('Aktiv', 'feuer-einsatzberichte'),
                            ];
                            echo esc_html($item_statuses[$item['status']] ?? $item['status']);
                            if ($item['attempts'] > 0) {
                                echo '<br><small>' . esc_html(sprintf(__('Versuche: %d', 'feuer-einsatzberichte'), $item['attempts'])) . '</small>';
                            }
                            if ('failed' === $item['developer_report']) {
                                echo '<br><small>' . esc_html__('Diagnose-E-Mail wurde nicht angenommen.', 'feuer-einsatzberichte') . '</small>';
                            }
                        ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php if (count($keyword_items) > 200) : ?><p><?php echo esc_html(sprintf(__('Weitere %d Einsatzstichworte werden hier nicht angezeigt.', 'feuer-einsatzberichte'), count($keyword_items) - 200)); ?></p><?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($migration_state['post_run']['error'])) : ?>
        <div class="notice notice-error inline"><p><?php esc_html_e('Fehler bei den Berichten:', 'feuer-einsatzberichte'); ?> <?php echo esc_html(wp_json_encode($migration_state['post_run']['error'])); ?></p></div>
        <?php if (2 <= (int) ($migration_state['post_run']['error']['attempts'] ?? 0)) : ?><p><?php esc_html_e('Dieser Fehler trat erneut auf. Bitte senden Sie den Diagnosebericht an den Entwickler, falls der automatische Versand nicht bestätigt wurde.', 'feuer-einsatzberichte'); ?></p><?php endif; ?>
        <?php if ('failed' === (string) ($migration_state['post_run']['error']['developer_report'] ?? '')) : ?><p><?php esc_html_e('Der Diagnosebericht konnte nicht per E-Mail angenommen werden. Bitte wenden Sie sich an den Entwickler.', 'feuer-einsatzberichte'); ?></p><?php endif; ?>
    <?php endif; ?>
    <?php if (!empty($migration_state['post_run']['failures'])) : ?>
        <h2><?php esc_html_e('Fehlgeschlagene Berichte', 'feuer-einsatzberichte'); ?></h2>
        <div class="feu-einsatz-migration-table-wrap" role="region" aria-label="<?php esc_attr_e('Fehlgeschlagene Berichte', 'feuer-einsatzberichte'); ?>" tabindex="0">
        <table class="widefat striped feu-einsatz-migration-table">
            <thead><tr><th>ID</th><th><?php esc_html_e('Speicherort', 'feuer-einsatzberichte'); ?></th><th><?php esc_html_e('Fehlercode', 'feuer-einsatzberichte'); ?></th><th><?php esc_html_e('Versuche', 'feuer-einsatzberichte'); ?></th></tr></thead>
            <tbody><?php foreach (array_slice($migration_state['post_run']['failures'], 0, 200) as $failure) : ?>
                <tr><td><?php echo esc_html((string) $failure['id']); ?></td><td><?php echo esc_html($failure['storage'] === FEU_Einsatz_Report_Post_Type::POST_TYPE ? __('Neue Struktur', 'feuer-einsatzberichte') : __('Bisherige Struktur', 'feuer-einsatzberichte')); ?></td><td><code><?php echo esc_html($failure['code']); ?></code></td><td><?php echo esc_html((string) $failure['attempts']); ?></td></tr>
            <?php endforeach; ?></tbody>
        </table>
        </div>
        <p class="description"><?php esc_html_e('Nach der Ursachenprüfung kann ein einzelner Bericht mit „wp feu-einsatz migration-retry --run=… --id=… --user=<Administrator>“ erneut geprüft werden.', 'feuer-einsatzberichte'); ?></p>
    <?php endif; ?>
    <?php if (!empty($migration_state['keyword_run']['error'])) : ?>
        <div class="notice notice-error inline"><p><?php esc_html_e('Fehler bei den Einsatzstichworten:', 'feuer-einsatzberichte'); ?> <?php echo esc_html((string) $migration_state['keyword_run']['error']); ?></p></div>
        <?php if ('failed' === (string) ($migration_state['keyword_run']['developer_report'] ?? '')) : ?><p><?php esc_html_e('Der Diagnosebericht konnte nicht per E-Mail angenommen werden. Bitte wenden Sie sich an den Entwickler.', 'feuer-einsatzberichte'); ?></p><?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($post_preflight)) : ?>
        <h2><?php esc_html_e('Vorprüfung der Berichte', 'feuer-einsatzberichte'); ?></h2>
        <p><?php echo esc_html(sprintf(__('Bereit zur Prüfung: %d · Fehler: %d · Hinweise: %d', 'feuer-einsatzberichte'), (int) ($post_preflight['eligible_count'] ?? 0), count((array) ($post_preflight['errors'] ?? [])), count((array) ($post_preflight['warnings'] ?? [])))); ?></p>
        <?php foreach (array_slice((array) ($post_preflight['errors'] ?? []), 0, 20) as $error) : ?>
            <div class="notice notice-error inline feu-einsatz-migration-preflight-error"><p><?php echo esc_html(sprintf('ID %d: %s', (int) ($error['id'] ?? 0), (string) ($error['code'] ?? 'unknown'))); ?></p></div>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php if (!empty($keyword_preflight)) : ?>
        <h2><?php esc_html_e('Vorprüfung der Einsatzstichworte', 'feuer-einsatzberichte'); ?></h2>
        <p><?php echo esc_html(sprintf(__('Berichte: %d · Stichworte: %d · Fehler: %d', 'feuer-einsatzberichte'), (int) ($keyword_preflight['report_count'] ?? 0), (int) ($keyword_preflight['selected_count'] ?? 0), count((array) ($keyword_preflight['errors'] ?? [])))); ?></p>
        <?php foreach (array_slice((array) ($keyword_preflight['errors'] ?? []), 0, 20) as $error) : ?><div class="notice notice-error inline feu-einsatz-migration-preflight-error"><p><?php echo esc_html((string) $error); ?></p></div><?php endforeach; ?>
        <?php if (empty($keyword_preflight['errors'])) : ?><p><a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=feu-einsatz-einstellungen&tab=kategorien')); ?>"><?php esc_html_e('Übertragung der Einsatzstichworte bestätigen', 'feuer-einsatzberichte'); ?></a></p><?php endif; ?>
    <?php endif; ?>
    <?php if ($migration_state['old_reports'] > 0 || !empty($keyword_preflight)) : ?>
        <p class="description"><?php esc_html_e('Bei einem zweiten Fehler desselben Elements wird ein technischer Diagnosebericht ohne Berichtsinhalt und personenbezogene Daten an dev@wdmin.com gesendet.', 'feuer-einsatzberichte'); ?></p>
    <?php endif; ?>
    <?php if ($migration_state['old_reports'] > 0) : ?>
        <p class="description"><?php esc_html_e('Der Berichtsumzug benötigt vor dem Start vollständige geprüfte Sicherungen von Datenbank und Uploads sowie einen Test auf der Staging-Seite. Er wird derzeit über die dokumentierten WP-CLI-Befehle gesteuert.', 'feuer-einsatzberichte'); ?></p>
    <?php endif; ?>
    <?php if (!empty($migration_state['ready_for_acceptance']) && empty($migration_state['accepted_before'])) : ?>
        <section class="feu-einsatz-migration-acceptance" aria-labelledby="feu-einsatz-migration-acceptance-title">
            <h2 id="feu-einsatz-migration-acceptance-title"><?php esc_html_e('Abschlussprüfung', 'feuer-einsatzberichte'); ?></h2>
            <p><?php esc_html_e('Die technische Übertragung ist abgeschlossen. Bestätigen Sie die folgenden Punkte erst nach der Prüfung auf Ihrer Website.', 'feuer-einsatzberichte'); ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="feu_einsatz_accept_migration" />
                <?php wp_nonce_field('feu_einsatz_accept_migration', 'feu_einsatz_migration_nonce'); ?>
                <p><label><input type="checkbox" name="feu_einsatz_migration_backup" value="1" required /> <?php esc_html_e('Vollständige Sicherungen von Datenbank und Uploads wurden geprüft und auf Staging wiederhergestellt.', 'feuer-einsatzberichte'); ?></label></p>
                <p><label><input type="checkbox" name="feu_einsatz_migration_urls" value="1" required /> <?php esc_html_e('Alle bisherigen öffentlichen URLs und Canonical-Adressen wurden geprüft und bleiben unverändert erreichbar.', 'feuer-einsatzberichte'); ?></label></p>
                <p><label><input type="checkbox" name="feu_einsatz_migration_related_data" value="1" required /> <?php esc_html_e('Autoren, Teilnehmer, Organisationen, Medien, Karten, Kommentare, Statistik und Archive wurden abgeglichen.', 'feuer-einsatzberichte'); ?></label></p>
                <p><button type="submit" class="button button-primary"><?php esc_html_e('Migration endgültig bestätigen', 'feuer-einsatzberichte'); ?></button></p>
            </form>
        </section>
    <?php endif; ?>
</div>
