<?php
if (!defined('ABSPATH')) {
    exit;
}

$tab_manifest_classes = 'feu-einsatz-tab-content';

if (!isset($active_tab) || 'manifest' !== (string) $active_tab) {
    $tab_manifest_classes .= ' feu-einsatz-tab-content-hidden';
}

$remote_version = is_array($remote_manifest_payload) ? (string) ($remote_manifest_payload['version'] ?? '') : '';
$remote_last_updated = is_array($remote_manifest_payload) ? (string) ($remote_manifest_payload['last_updated'] ?? '') : '';
$update_status_class = 'is-neutral';
$update_status_text = __('Remote-Manifest aktuell nicht erreichbar', 'feuer-einsatzberichte');

if ('' !== $remote_version) {
    if (version_compare(FEU_EINSATZ_VERSION, $remote_version, '<')) {
        $update_status_class = 'is-warning';
        $update_status_text = sprintf(
            /* translators: %s: version */
            __('Neue Version verfuegbar: %s', 'feuer-einsatzberichte'),
            $remote_version
        );
    } else {
        $update_status_class = 'is-success';
        $update_status_text = __('Installierte Version ist aktuell', 'feuer-einsatzberichte');
    }
}

$manifest_reference_items = [
    __('Pluginbeschreibung', 'feuer-einsatzberichte') => [
        'value' => FEU_Einsatz_Updater::get_project_url(),
        'description' => __('Oeffentliche Informationsseite des Plugins ohne direkte Download-Schaltflaeche.', 'feuer-einsatzberichte'),
    ],
    __('Autorenseite', 'feuer-einsatzberichte') => [
        'value' => 'https://wdmin.com/',
        'description' => __('Oeffentliche Seite des Autors und Anbieters.', 'feuer-einsatzberichte'),
    ],
    __('Manifest-Datei lokal', 'feuer-einsatzberichte') => [
        'value' => $manifest_path,
        'description' => __('Lokale Quelle fuer die technische WordPress-Update-Pruefung.', 'feuer-einsatzberichte'),
    ],
    __('Release-Liste lokal', 'feuer-einsatzberichte') => [
        'value' => $release_notes_path,
        'description' => __('Liefert die menschenlesbare Liste der zuletzt veroeffentlichten Versionen.', 'feuer-einsatzberichte'),
    ],
    __('Manifest-Beispiel', 'feuer-einsatzberichte') => [
        'value' => $manifest_example_path,
        'description' => __('Vorlage fuer neue Releases oder Anpassungen am Manifest-Schema.', 'feuer-einsatzberichte'),
    ],
];
?>

<div id="tab-manifest" class="<?php echo esc_attr($tab_manifest_classes); ?>">
    <div class="feu-admin-settings-stack">
        <section class="feu-admin-settings-surface">
            <div class="feu-admin-settings-surface-head">
                <div>
                    <h2><?php esc_html_e('Lokaler Betrieb', 'feuer-einsatzberichte'); ?></h2>
                    <p class="description"><?php esc_html_e('Produktiver Release-Aufbau, Update-Quelle und die zuletzt veroeffentlichten Versionen im Ueberblick.', 'feuer-einsatzberichte'); ?></p>
                </div>
            </div>

            <div class="feu-einsatz-settings-note-grid">
                <div class="feu-einsatz-settings-note-card feu-einsatz-release-status-card <?php echo esc_attr($update_status_class); ?>">
                    <h3><?php esc_html_e('Update-Status', 'feuer-einsatzberichte'); ?></h3>
                    <p><strong><?php echo esc_html($update_status_text); ?></strong></p>
                    <p class="description">
                        <?php if ('' !== $remote_version) : ?>
                            <?php
                            printf(
                                /* translators: %1$s: local version, %2$s: remote version */
                                esc_html__('Installiert: %1$s, Remote: %2$s', 'feuer-einsatzberichte'),
                                esc_html(FEU_EINSATZ_VERSION),
                                esc_html($remote_version)
                            );
                            ?>
                        <?php else : ?>
                            <?php esc_html_e('Der Remote-Stand konnte noch nicht geladen werden.', 'feuer-einsatzberichte'); ?>
                        <?php endif; ?>
                    </p>
                    <?php if ('' !== $remote_last_updated) : ?>
                        <p class="description"><?php echo esc_html(sprintf(__('Letztes Remote-Datum: %s', 'feuer-einsatzberichte'), $remote_last_updated)); ?></p>
                    <?php endif; ?>
                    <p>
                        <button type="button" id="feu-einsatz-refresh-update-check" class="button button-primary">
                            <?php esc_html_e('Update-Pruefung jetzt aktualisieren', 'feuer-einsatzberichte'); ?>
                        </button>
                    </p>
                </div>

                <div class="feu-einsatz-settings-note-card">
                    <h3><?php esc_html_e('Version', 'feuer-einsatzberichte'); ?></h3>
                    <p><code><?php echo esc_html(FEU_EINSATZ_VERSION); ?></code></p>
                    <p class="description"><?php esc_html_e('Aktuell installierte Plugin-Version.', 'feuer-einsatzberichte'); ?></p>
                </div>

                <div class="feu-einsatz-settings-note-card">
                    <h3><?php esc_html_e('Teilnehmer-Standard', 'feuer-einsatzberichte'); ?></h3>
                    <p><code><?php echo esc_html($participant_fallback_function); ?></code></p>
                    <p class="description"><?php esc_html_e('Neue Einsatzteilnehmer erhalten zuerst ihre eigene Standardfunktion. Ohne Vorgabe bleibt der Wert im Einsatzbericht manuell waehlbar.', 'feuer-einsatzberichte'); ?></p>
                </div>
            </div>
        </section>

        <section class="feu-admin-settings-surface">
            <div class="feu-admin-settings-surface-head">
                <div>
                    <h2><?php esc_html_e('Release-Referenzen', 'feuer-einsatzberichte'); ?></h2>
                    <p class="description"><?php esc_html_e('Sichtbare Referenzen beschraenken sich auf Pluginbeschreibung, Autorenseite und lokale Wartungsdateien. Direkte Paket-Download-Links werden in der Admin-Oberflaeche nicht angezeigt.', 'feuer-einsatzberichte'); ?></p>
                </div>
            </div>

            <div class="feu-admin-settings-form-grid feu-admin-settings-form-grid--2">
                <?php foreach ($manifest_reference_items as $label => $item) : ?>
                    <div class="feu-admin-settings-fieldset">
                        <div class="feu-admin-settings-fieldset-head">
                            <h3><?php echo esc_html($label); ?></h3>
                        </div>
                        <code><?php echo esc_html((string) $item['value']); ?></code>
                        <p class="description"><?php echo esc_html((string) $item['description']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="feu-admin-settings-surface">
            <div class="feu-admin-settings-form-grid feu-admin-settings-form-grid--2">
                <div class="feu-admin-settings-field">
                    <span><?php esc_html_e('Produktiv-Manifest', 'feuer-einsatzberichte'); ?></span>
                    <textarea readonly rows="16" class="large-text code feu-admin-settings-textarea-code"><?php echo esc_textarea($manifest_display_contents); ?></textarea>
                    <small><?php esc_html_e('Download-Felder werden hier bewusst ausgeblendet. Das produktive Manifest behaelt sie nur fuer den technischen WordPress-Update-Prozess.', 'feuer-einsatzberichte'); ?></small>
                </div>

                <div class="feu-admin-settings-field">
                    <span><?php esc_html_e('Release-Hinweis Vorlage', 'feuer-einsatzberichte'); ?></span>
                    <textarea readonly rows="16" class="large-text code feu-admin-settings-textarea-code"><?php echo esc_textarea($manifest_example_contents); ?></textarea>
                    <small><?php esc_html_e('Diese Datei dient als Ausgangspunkt fuer kommende Versionen.', 'feuer-einsatzberichte'); ?></small>
                </div>
            </div>
        </section>

        <section class="feu-admin-settings-surface">
            <div class="feu-admin-settings-surface-head">
                <div>
                    <h2><?php esc_html_e('Letzte Versionen', 'feuer-einsatzberichte'); ?></h2>
                    <p class="description"><?php esc_html_e('Die Uebersicht wird direkt aus der lokalen Release-Datei gelesen und zeigt die zuletzt veroeffentlichten Aenderungen.', 'feuer-einsatzberichte'); ?></p>
                </div>
            </div>

            <?php if (empty($release_notes_entries)) : ?>
                <div class="feu-einsatz-log-empty-card">
                    <p class="description"><?php esc_html_e('Es konnten noch keine Release-Hinweise aus der lokalen Datei geladen werden.', 'feuer-einsatzberichte'); ?></p>
                </div>
            <?php else : ?>
                <div class="feu-einsatz-release-note-list">
                    <?php foreach (array_slice($release_notes_entries, 0, 5) as $release_note) : ?>
                        <article class="feu-einsatz-release-note-card">
                            <div class="feu-einsatz-release-note-head">
                                <div>
                                    <h4><?php echo esc_html((string) ($release_note['version'] ?? '')); ?></h4>
                                    <?php if (!empty($release_note['title'])) : ?>
                                        <p class="feu-einsatz-release-note-title"><?php echo esc_html((string) $release_note['title']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($release_note['date'])) : ?>
                                    <span class="feu-einsatz-log-badge feu-einsatz-log-badge-id"><?php echo esc_html((string) $release_note['date']); ?></span>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($release_note['summary'])) : ?>
                                <p class="description"><?php echo esc_html((string) $release_note['summary']); ?></p>
                            <?php endif; ?>

                            <?php if (!empty($release_note['changes']) && is_array($release_note['changes'])) : ?>
                                <div class="feu-einsatz-release-note-changes">
                                    <?php foreach ($release_note['changes'] as $change_text) : ?>
                                        <div class="feu-einsatz-release-note-change"><?php echo esc_html((string) $change_text); ?></div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="feu-admin-settings-surface">
            <div class="feu-einsatz-settings-note-grid">
                <div class="feu-einsatz-settings-note-card">
                    <h3><?php esc_html_e('Funktions- und Dateikarte', 'feuer-einsatzberichte'); ?></h3>
                    <p><code><?php echo esc_html($functions_reference_path); ?></code></p>
                    <p class="description"><?php esc_html_e('Beschreibt Hauptfunktionen, Dateien, Ordner und Verantwortlichkeiten des Plugins.', 'feuer-einsatzberichte'); ?></p>
                </div>
                <div class="feu-einsatz-settings-note-card">
                    <h3><?php esc_html_e('Hosting und GitHub', 'feuer-einsatzberichte'); ?></h3>
                    <p><code><?php echo esc_html($update_guide_path); ?></code></p>
                    <p class="description"><?php esc_html_e('Enthaelt die empfohlene Ablage, den Release-Ablauf und die GitHub-Vorbereitung.', 'feuer-einsatzberichte'); ?></p>
                </div>
                <div class="feu-einsatz-settings-note-card">
                    <h3><?php esc_html_e('Dokumentation', 'feuer-einsatzberichte'); ?></h3>
                    <p><code><?php echo esc_html($docs_directory); ?></code></p>
                    <p class="description"><?php esc_html_e('Technische Dokumentation, Build-Hinweise und Release-Daten liegen gesammelt im Ordner docs.', 'feuer-einsatzberichte'); ?></p>
                </div>
                <div class="feu-einsatz-settings-note-card">
                    <h3><?php esc_html_e('Templates', 'feuer-einsatzberichte'); ?></h3>
                    <p><code><?php echo esc_html($templates_directory); ?></code></p>
                    <p class="description"><?php esc_html_e('Alle Admin- und Frontend-Templates werden zentral im Ordner templates verwaltet.', 'feuer-einsatzberichte'); ?></p>
                </div>
            </div>
        </section>
    </div>
</div>
