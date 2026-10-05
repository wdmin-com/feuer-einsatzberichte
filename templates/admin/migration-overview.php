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
$post_direction = (string) ($migration_state['post_run']['direction'] ?? 'forward');
$keyword_status = (string) ($migration_state['keyword_run']['status'] ?? 'not_started');
$status_labels = [
    'not_started' => __('Noch nicht gestartet', 'feuer-einsatzberichte'),
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
$missing_authors = count(array_filter((array) ($post_preflight['warnings'] ?? []), static function ($item) {
    return is_array($item) && 'missing_author' === ($item['code'] ?? '');
}));
?>
<div class="wrap feu-einsatz-migration-page">
    <h1><?php esc_html_e('Datenmigration', 'feuer-einsatzberichte'); ?></h1>
    <p><?php esc_html_e('Diese Übersicht liest den aktuellen Speicherort und die vorhandenen Migrationsjournale. Das Öffnen der Seite ändert keine Daten.', 'feuer-einsatzberichte'); ?></p>
    <?php if (is_array($post_migration_notice ?? null)) : ?>
        <div class="notice notice-<?php echo esc_attr('error' === ($post_migration_notice['type'] ?? '') ? 'error' : 'success'); ?> inline"><p><?php echo esc_html((string) ($post_migration_notice['message'] ?? '')); ?></p></div>
    <?php endif; ?>
    <div class="notice notice-info inline"><p><strong><?php esc_html_e('Speicherstatus:', 'feuer-einsatzberichte'); ?></strong>
        <?php echo esc_html($storage_labels[$migration_state['storage']] ?? $storage_labels['mixed']); ?>.
        <?php if (!empty($migration_state['completed'])) : ?><?php esc_html_e('Die bestätigte Umstellung ist abgeschlossen.', 'feuer-einsatzberichte'); ?><?php endif; ?>
        <?php if (!empty($migration_state['accepted_before']) && empty($migration_state['completed'])) : ?><?php esc_html_e('Eine frühere Abnahme ist gespeichert. Der aktuelle Speicherzustand muss erneut geprüft werden.', 'feuer-einsatzberichte'); ?><?php endif; ?>
    </p></div>

    <table class="widefat striped feu-einsatz-migration-table">
        <thead><tr><th><?php esc_html_e('Bereich', 'feuer-einsatzberichte'); ?></th><th><?php esc_html_e('Bisherige Struktur', 'feuer-einsatzberichte'); ?></th><th><?php esc_html_e('Neue Struktur / Status', 'feuer-einsatzberichte'); ?></th></tr></thead>
        <tbody>
            <tr><th scope="row"><?php esc_html_e('Einsatzberichte', 'feuer-einsatzberichte'); ?></th><td><?php echo esc_html(number_format_i18n($migration_state['old_reports'])); ?></td><td><?php echo esc_html(number_format_i18n($migration_state['new_reports'])); ?> · <?php echo esc_html($status_labels[$post_status] ?? $post_status); ?><?php if ($post_total > 0) : ?><br><small><?php echo esc_html(sprintf(__('%1$d von %2$d im Journal bestätigt, %3$d Fehler', 'feuer-einsatzberichte'), (int) ($post_counts['migrated'] ?? 0), $post_total, (int) ($post_counts['failed'] ?? 0))); ?></small><?php endif; ?></td></tr>
            <tr><th scope="row"><?php esc_html_e('Einsatzstichworte', 'feuer-einsatzberichte'); ?></th><td><?php echo esc_html(number_format_i18n($migration_state['legacy_keywords'])); ?></td><td><?php echo esc_html($migration_state['keywords_enabled'] ? __('Aktiv', 'feuer-einsatzberichte') : __('Noch nicht aktiv', 'feuer-einsatzberichte')); ?> · <?php echo esc_html($status_labels[$keyword_status] ?? $keyword_status); ?><?php if ($keyword_total > 0) : ?><br><small><?php echo esc_html(sprintf(__('%1$d von %2$d Berichten im Journal bearbeitet', 'feuer-einsatzberichte'), $keyword_processed, $keyword_total)); ?></small><?php endif; ?></td></tr>
            <tr><th scope="row"><?php esc_html_e('WordPress-Benutzer', 'feuer-einsatzberichte'); ?></th><td colspan="2"><?php esc_html_e('Konten bleiben in WordPress; sie werden nicht kopiert.', 'feuer-einsatzberichte'); ?> <?php echo esc_html(sprintf(__('Fehlende Autoren in der Vorprüfung: %d.', 'feuer-einsatzberichte'), $missing_authors)); ?></td></tr>
            <tr><th scope="row"><?php esc_html_e('Teilnehmer, Organisationen, Medien und Kommentare', 'feuer-einsatzberichte'); ?></th><td colspan="2"><?php esc_html_e('Keine Kopie erforderlich. Prüfung der Verknüpfungen bei der Abnahme noch ausstehend.', 'feuer-einsatzberichte'); ?></td></tr>
        </tbody>
    </table>

    <?php if ($keyword_items) : ?>
        <h2><?php esc_html_e('Status der Einsatzstichworte', 'feuer-einsatzberichte'); ?></h2>
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
        <?php if (count($keyword_items) > 200) : ?><p><?php echo esc_html(sprintf(__('Weitere %d Einsatzstichworte werden hier nicht angezeigt.', 'feuer-einsatzberichte'), count($keyword_items) - 200)); ?></p><?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($migration_state['post_run']['error'])) : ?>
        <div class="notice notice-error inline"><p><?php esc_html_e('Fehler bei den Berichten:', 'feuer-einsatzberichte'); ?> <?php echo esc_html(wp_json_encode($migration_state['post_run']['error'])); ?></p></div>
        <?php if (2 <= (int) ($migration_state['post_run']['error']['attempts'] ?? 0)) : ?><p><?php esc_html_e('Dieser Fehler trat erneut auf. Bitte senden Sie den Diagnosebericht an den Entwickler, falls der automatische Versand nicht bestätigt wurde.', 'feuer-einsatzberichte'); ?></p><?php endif; ?>
        <?php if ('failed' === (string) ($migration_state['post_run']['error']['developer_report'] ?? '')) : ?><p><?php esc_html_e('Der Diagnosebericht konnte nicht per E-Mail angenommen werden. Bitte wenden Sie sich an den Entwickler.', 'feuer-einsatzberichte'); ?></p><?php endif; ?>
    <?php endif; ?>
    <?php if (!empty($migration_state['post_run']['failures'])) : ?>
        <h2><?php esc_html_e('Fehlgeschlagene Berichte', 'feuer-einsatzberichte'); ?></h2>
        <table class="widefat striped feu-einsatz-migration-table">
            <thead><tr><th>ID</th><th><?php esc_html_e('Speicherort', 'feuer-einsatzberichte'); ?></th><th><?php esc_html_e('Fehlercode', 'feuer-einsatzberichte'); ?></th><th><?php esc_html_e('Versuche', 'feuer-einsatzberichte'); ?></th><th><?php esc_html_e('Aktion', 'feuer-einsatzberichte'); ?></th></tr></thead>
            <tbody><?php foreach ($migration_state['post_run']['failures'] as $failure) : ?>
                <tr><td><?php echo esc_html((string) $failure['id']); ?></td><td><?php echo esc_html($failure['storage'] === FEU_Einsatz_Report_Post_Type::POST_TYPE ? __('Neue Struktur', 'feuer-einsatzberichte') : __('Bisherige Struktur', 'feuer-einsatzberichte')); ?></td><td><code><?php echo esc_html($failure['code']); ?></code></td><td><?php echo esc_html((string) $failure['attempts']); ?></td><td>
                    <?php if (current_user_can('edit_post', (int) $failure['id'])) : ?><a class="button" href="<?php echo esc_url(get_edit_post_link((int) $failure['id'], '')); ?>"><?php esc_html_e('Bericht prüfen', 'feuer-einsatzberichte'); ?></a><?php endif; ?>
                    <?php if ('partial_error' === $post_status) : ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <input type="hidden" name="action" value="feu_einsatz_post_migration">
                            <input type="hidden" name="migration_operation" value="retry">
                            <input type="hidden" name="run_id" value="<?php echo esc_attr((string) ($migration_state['post_run']['run_id'] ?? '')); ?>">
                            <input type="hidden" name="report_id" value="<?php echo esc_attr((string) $failure['id']); ?>">
                            <?php wp_nonce_field('feu_einsatz_post_migration', 'feu_einsatz_post_migration_nonce'); ?>
                            <button type="submit" class="button"><?php esc_html_e('Erneut versuchen', 'feuer-einsatzberichte'); ?></button>
                        </form>
                    <?php else : ?>—<?php endif; ?>
                </td></tr>
            <?php endforeach; ?></tbody>
        </table>
        <p class="description"><?php esc_html_e('Beheben Sie zuerst die angezeigte Ursache. Anschließend können Sie jeden betroffenen Bericht einzeln erneut prüfen.', 'feuer-einsatzberichte'); ?></p>
    <?php endif; ?>
    <?php if (!empty($migration_state['keyword_run']['error'])) : ?>
        <div class="notice notice-error inline"><p><?php esc_html_e('Fehler bei den Einsatzstichworten:', 'feuer-einsatzberichte'); ?> <?php echo esc_html((string) $migration_state['keyword_run']['error']); ?></p></div>
        <?php if ('failed' === (string) ($migration_state['keyword_run']['developer_report'] ?? '')) : ?><p><?php esc_html_e('Der Diagnosebericht konnte nicht per E-Mail angenommen werden. Bitte wenden Sie sich an den Entwickler.', 'feuer-einsatzberichte'); ?></p><?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($post_preflight)) : ?>
        <h2><?php esc_html_e('Vorprüfung der Berichte', 'feuer-einsatzberichte'); ?></h2>
        <p><?php echo esc_html(sprintf(__('Bereit zur Prüfung: %d · Fehler: %d · Hinweise: %d', 'feuer-einsatzberichte'), (int) ($post_preflight['eligible_count'] ?? 0), count((array) ($post_preflight['errors'] ?? [])), count((array) ($post_preflight['warnings'] ?? [])))); ?></p>
        <?php foreach ((array) ($post_preflight['errors'] ?? []) as $error) : ?>
            <p class="notice notice-error inline"><?php echo esc_html(sprintf('ID %d: %s', (int) ($error['id'] ?? 0), (string) ($error['code'] ?? 'unknown'))); ?>
                <?php if (!empty($error['id']) && current_user_can('edit_post', (int) $error['id'])) : ?><a href="<?php echo esc_url(get_edit_post_link((int) $error['id'], '')); ?>"><?php esc_html_e('Bericht prüfen', 'feuer-einsatzberichte'); ?></a><?php endif; ?>
            </p>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php if ($migration_state['old_reports'] > 0 || in_array($post_status, ['running', 'failed', 'partial_error', 'rolling_back'], true) || ('complete' === $post_status && empty($migration_state['accepted_before']))) : ?>
        <section class="feu-einsatz-migration-action" aria-labelledby="feu-einsatz-post-migration-action-title">
            <h2 id="feu-einsatz-post-migration-action-title"><?php esc_html_e('Einsatzberichte übertragen', 'feuer-einsatzberichte'); ?></h2>
            <?php if (in_array($post_status, ['not_started', 'rolled_back'], true)) : ?>
                <?php if (!empty($post_preflight['errors'])) : ?>
                    <p><?php esc_html_e('Der Start ist gesperrt, bis die Fehler der Vorprüfung behoben sind. Öffnen Sie die betroffenen Berichte oben und laden Sie diese Seite danach erneut.', 'feuer-einsatzberichte'); ?></p>
                <?php else : ?>
                    <p><?php esc_html_e('Der Umzug behält Berichts-IDs und öffentliche URLs bei. Er beginnt erst nach Ihrer Bestätigung und wird in Gruppen von zehn Berichten ausgeführt.', 'feuer-einsatzberichte'); ?></p>
                    <p><?php esc_html_e('Erforderlich sind vollständige, auf einer Staging-Seite wiederhergestellte Sicherungen von Datenbank und Uploads. Beide Dateien müssen für PHP lesbar außerhalb des WordPress-Webverzeichnisses liegen.', 'feuer-einsatzberichte'); ?></p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="feu-einsatz-migration-start-form">
                        <input type="hidden" name="action" value="feu_einsatz_post_migration">
                        <input type="hidden" name="migration_operation" value="start">
                        <?php wp_nonce_field('feu_einsatz_post_migration', 'feu_einsatz_post_migration_nonce'); ?>
                        <label><?php esc_html_e('Pfad zur Datenbanksicherung', 'feuer-einsatzberichte'); ?><input type="text" name="database_backup" required autocomplete="off" placeholder="/private/backups/database.sql.gz"></label>
                        <label><?php esc_html_e('SHA-256 der Datenbanksicherung', 'feuer-einsatzberichte'); ?><input type="text" name="database_sha256" required pattern="[a-fA-F0-9]{64}" maxlength="64" autocomplete="off"></label>
                        <label><?php esc_html_e('Pfad zur Uploads-Sicherung', 'feuer-einsatzberichte'); ?><input type="text" name="uploads_backup" required autocomplete="off" placeholder="/private/backups/uploads.zip"></label>
                        <label><?php esc_html_e('SHA-256 der Uploads-Sicherung', 'feuer-einsatzberichte'); ?><input type="text" name="uploads_sha256" required pattern="[a-fA-F0-9]{64}" maxlength="64" autocomplete="off"></label>
                        <label class="feu-einsatz-migration-check"><input type="checkbox" name="staging_verified" value="1" required> <?php esc_html_e('Beide Sicherungen wurden auf Staging wiederhergestellt und geprüft.', 'feuer-einsatzberichte'); ?></label>
                        <label class="feu-einsatz-migration-confirm"><?php esc_html_e('Zur Bestätigung START eingeben', 'feuer-einsatzberichte'); ?><input type="text" name="migration_confirmation" required pattern="START" autocomplete="off" placeholder="START"></label>
                        <button type="submit" class="button button-primary"><?php esc_html_e('Berichtsumzug starten', 'feuer-einsatzberichte'); ?></button>
                    </form>
                <?php endif; ?>
            <?php elseif ('rollback' === $post_direction && in_array($post_status, ['rolling_back', 'failed'], true)) : ?>
                <p><?php esc_html_e('Die Rücksetzung wurde unterbrochen. Setzen Sie sie mit der nächsten Gruppe fort. Bei einem erneuten Fehler prüfen Sie zuerst den betroffenen Bericht und das Journal.', 'feuer-einsatzberichte'); ?></p>
            <?php elseif (in_array($post_status, ['running', 'failed'], true)) : ?>
                <p><?php echo esc_html(sprintf(__('%1$d von %2$d Berichten bearbeitet. Jeder Klick überträgt höchstens zehn weitere Berichte.', 'feuer-einsatzberichte'), (int) ($migration_state['post_run']['cursor'] ?? 0), $post_total)); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="feu_einsatz_post_migration"><input type="hidden" name="migration_operation" value="batch">
                    <input type="hidden" name="run_id" value="<?php echo esc_attr((string) ($migration_state['post_run']['run_id'] ?? '')); ?>">
                    <?php wp_nonce_field('feu_einsatz_post_migration', 'feu_einsatz_post_migration_nonce'); ?>
                    <button type="submit" class="button button-primary"><?php esc_html_e('Nächste 10 Berichte übertragen', 'feuer-einsatzberichte'); ?></button>
                </form>
            <?php elseif ('partial_error' === $post_status) : ?>
                <p><?php esc_html_e('Alle unabhängigen Berichte wurden bearbeitet. Prüfen Sie die Fehler oben und verwenden Sie dort „Erneut versuchen“ nur für den betroffenen Bericht.', 'feuer-einsatzberichte'); ?></p>
            <?php elseif ('complete' === $post_status && 0 === (int) $migration_state['old_reports']) : ?>
                <p><?php esc_html_e('Die Berichte sind übertragen. Prüfen Sie die öffentlichen URLs und die verknüpften Daten vor der abschließenden Bestätigung.', 'feuer-einsatzberichte'); ?></p>
            <?php else : ?>
                <p><?php esc_html_e('Der gespeicherte Migrationsstatus passt nicht zur Anzahl alter Berichte. Prüfen Sie die neu hinzugekommenen Berichte und das Journal, bevor weitere Daten geändert werden.', 'feuer-einsatzberichte'); ?></p>
            <?php endif; ?>
            <?php if (in_array($post_status, ['running', 'failed', 'partial_error', 'rolling_back'], true) || ('complete' === $post_status && empty($migration_state['accepted_before']))) : ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="feu-einsatz-migration-rollback-form">
                    <input type="hidden" name="action" value="feu_einsatz_post_migration">
                    <input type="hidden" name="migration_operation" value="rollback">
                    <input type="hidden" name="run_id" value="<?php echo esc_attr((string) ($migration_state['post_run']['run_id'] ?? '')); ?>">
                    <?php wp_nonce_field('feu_einsatz_post_migration', 'feu_einsatz_post_migration_nonce'); ?>
                    <label><?php esc_html_e('Falls der Umzug nicht abgenommen werden kann: ROLLBACK eingeben', 'feuer-einsatzberichte'); ?>
                        <input type="text" name="migration_confirmation" required pattern="ROLLBACK" autocomplete="off" placeholder="ROLLBACK"></label>
                    <button type="submit" class="button"><?php echo esc_html('rollback' === $post_direction ? __('Rücksetzung fortsetzen', 'feuer-einsatzberichte') : __('Nächste 10 Berichte zurücksetzen', 'feuer-einsatzberichte')); ?></button>
                </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>
    <?php if (!empty($keyword_preflight)) : ?>
        <h2><?php esc_html_e('Vorprüfung der Einsatzstichworte', 'feuer-einsatzberichte'); ?></h2>
        <p><?php echo esc_html(sprintf(__('Berichte: %d · Stichworte: %d · Fehler: %d', 'feuer-einsatzberichte'), (int) ($keyword_preflight['report_count'] ?? 0), (int) ($keyword_preflight['selected_count'] ?? 0), count((array) ($keyword_preflight['errors'] ?? [])))); ?></p>
        <?php foreach (array_slice((array) ($keyword_preflight['errors'] ?? []), 0, 20) as $error) : ?><p class="notice notice-error inline"><?php echo esc_html((string) $error); ?></p><?php endforeach; ?>
        <p><a class="button <?php echo empty($keyword_preflight['errors']) ? 'button-primary' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=feu-einsatz-einstellungen&tab=kategorien')); ?>"><?php echo esc_html(empty($keyword_preflight['errors']) ? __('Übertragung der Einsatzstichworte bestätigen', 'feuer-einsatzberichte') : __('Einsatzstichworte prüfen', 'feuer-einsatzberichte')); ?></a></p>
    <?php endif; ?>
    <?php if ($migration_state['old_reports'] > 0 || !empty($keyword_preflight)) : ?>
        <p class="description"><?php esc_html_e('Bei einem zweiten Fehler desselben Elements wird ein technischer Diagnosebericht ohne Berichtsinhalt und personenbezogene Daten an dev@wdmin.com gesendet.', 'feuer-einsatzberichte'); ?></p>
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
