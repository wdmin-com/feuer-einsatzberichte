<?php
if (!defined('ABSPATH')) {
    exit;
}

$purge_sections = [
    'participants' => [
        'label' => __('Teilnehmer', 'feuer-einsatzberichte'),
        'description' => __('Entfernt Teilnehmer, Zuordnungen in Einsatzberichten und davon abhängige Statistik.', 'feuer-einsatzberichte'),
    ],
    'reports' => [
        'label' => __('Einsatzberichte', 'feuer-einsatzberichte'),
        'description' => __('Löscht alle als Einsatz markierten Beiträge und erzeugten Kartenbilder. Andere Medien bleiben erhalten.', 'feuer-einsatzberichte'),
    ],
    'statistics' => [
        'label' => __('Statistik', 'feuer-einsatzberichte'),
        'description' => __('Entfernt Statistikdatensätze und alle berechneten Statistik-Caches.', 'feuer-einsatzberichte'),
    ],
    'settings' => [
        'label' => __('Einstellungen', 'feuer-einsatzberichte'),
        'description' => __('Löscht Konfiguration und Wiederherstellungspunkte und startet die Ersteinrichtung neu.', 'feuer-einsatzberichte'),
    ],
    'logs' => [
        'label' => __('Protokolle', 'feuer-einsatzberichte'),
        'description' => __('Löscht bisherige Protokolle. Der neue Audit-Eintrag dieser Löschung bleibt erhalten.', 'feuer-einsatzberichte'),
    ],
    'archives' => [
        'label' => __('Archive', 'feuer-einsatzberichte'),
        'description' => __('Löscht alle Archivdatensätze und die zugehörigen ZIP-Dateien.', 'feuer-einsatzberichte'),
    ],
];
?>

<div id="tab-daten" class="feu-einsatz-tab-content <?php echo 'daten' !== $active_tab ? 'feu-einsatz-tab-content-hidden' : ''; ?>">
    <section class="feu-admin-settings-surface feu-admin-recovery">
        <div class="feu-admin-settings-surface-head">
            <div>
                <h2><?php esc_html_e('Daten dauerhaft löschen', 'feuer-einsatzberichte'); ?></h2>
                <p class="description"><?php esc_html_e('Dieser getrennte Sicherheitsbereich löscht ausschließlich die von Ihnen ausgewählten Plugin-Daten.', 'feuer-einsatzberichte'); ?></p>
            </div>
            <span class="feu-admin-recovery-status">
                <span class="ti ti-shield-lock" aria-hidden="true"></span>
                <?php esc_html_e('Geschützter Bereich', 'feuer-einsatzberichte'); ?>
            </span>
        </div>

        <article class="feu-admin-recovery-card feu-admin-recovery-card--danger">
            <div class="feu-admin-reset-controls">
                <div class="feu-admin-purge-warning" role="alert">
                    <span class="ti ti-alert-triangle" aria-hidden="true"></span>
                    <strong><?php esc_html_e('Die ausgewählten Daten werden vollständig und ohne Wiederherstellungsmöglichkeit gelöscht.', 'feuer-einsatzberichte'); ?></strong>
                </div>

                <div class="feu-admin-purge-head">
                    <strong><?php esc_html_e('Zu löschende Daten', 'feuer-einsatzberichte'); ?></strong>
                    <button type="button" class="button-link" data-feu-select-all-purge><?php esc_html_e('Alle auswählen', 'feuer-einsatzberichte'); ?></button>
                </div>
                <div class="feu-admin-purge-options">
                    <?php foreach ($purge_sections as $section_key => $section_config) : ?>
                        <label class="feu-admin-purge-option">
                            <input type="checkbox"
                                   name="feu_einsatz_purge_sections[]"
                                   value="<?php echo esc_attr($section_key); ?>"
                                   <?php checked(in_array($section_key, (array) ($selected_purge_sections ?? []), true)); ?> />
                            <span>
                                <strong><?php echo esc_html($section_config['label']); ?></strong>
                                <small><?php echo esc_html($section_config['description']); ?></small>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <button type="submit" name="feu_einsatz_generate_factory_code" value="1" class="button button-secondary" formnovalidate>
                    <?php esc_html_e('Sicherheitscode erzeugen', 'feuer-einsatzberichte'); ?>
                </button>

                <?php if (!empty($factory_reset_code)) : ?>
                    <div class="feu-admin-reset-code" role="status" aria-live="polite">
                        <span><?php esc_html_e('Einmaliger Sicherheitscode', 'feuer-einsatzberichte'); ?></span>
                        <output><?php echo esc_html($factory_reset_code); ?></output>
                        <small><?php esc_html_e('Gültig für 10 Minuten.', 'feuer-einsatzberichte'); ?></small>
                    </div>
                <?php endif; ?>

                <label class="feu-admin-settings-field" for="feu_einsatz_factory_reset_code">
                    <span><?php esc_html_e('Sicherheitscode bestätigen', 'feuer-einsatzberichte'); ?></span>
                    <input type="text"
                           id="feu_einsatz_factory_reset_code"
                           name="feu_einsatz_factory_reset_code"
                           class="regular-text"
                           value=""
                           inputmode="numeric"
                           maxlength="7"
                           autocomplete="one-time-code"
                           placeholder="0000000" />
                </label>
                <button type="submit" name="feu_einsatz_factory_reset" value="1" class="button feu-admin-danger-button">
                    <?php esc_html_e('Ausgewählte Daten endgültig löschen', 'feuer-einsatzberichte'); ?>
                </button>
            </div>
        </article>
    </section>
</div>
