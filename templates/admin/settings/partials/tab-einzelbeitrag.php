<?php
if (!defined('ABSPATH')) {
    exit;
}

$tab_single_classes = 'feu-einsatz-tab-content';

if (isset($active_tab) && 'einzelbeitrag' !== (string) $active_tab) {
    $tab_single_classes .= ' feu-einsatz-tab-content-hidden';
}

$single_info_field_options = [
    'street' => __('Strasse', 'feuer-einsatzberichte'),
    'location' => __('Ort', 'feuer-einsatzberichte'),
    'date' => __('Datum', 'feuer-einsatzberichte'),
    'time' => __('Uhrzeit', 'feuer-einsatzberichte'),
    'category' => __('Einsatzart', 'feuer-einsatzberichte'),
    'organizations' => __('Kraefte vor Ort', 'feuer-einsatzberichte'),
];
?>

<div id="tab-einzelbeitrag" class="<?php echo esc_attr($tab_single_classes); ?>">
    <div class="feu-admin-settings-stack">
        <section class="feu-admin-settings-surface">
            <div class="feu-admin-settings-surface-head">
                <div>
                    <h2><?php esc_html_e('Einzelbeitrag', 'feuer-einsatzberichte'); ?></h2>
                    <p class="description"><?php esc_html_e('Steuert, wie einzelne Einsatzberichte unterhalb von Galerie und Inhaltsbereich aufgebaut werden.', 'feuer-einsatzberichte'); ?></p>
                </div>
            </div>

            <div class="feu-admin-settings-form-grid feu-admin-settings-form-grid--2">
                <label class="feu-admin-settings-field">
                    <span><?php esc_html_e('Weitere Einsatzberichte unter dem Beitrag', 'feuer-einsatzberichte'); ?></span>
                    <select id="feu_einsatz_related_reports_display"
                            name="feu_einsatz_related_reports_display"
                            class="regular-text">
                        <option value="disabled" <?php selected($related_reports_display, 'disabled'); ?>><?php esc_html_e('Deaktiviert', 'feuer-einsatzberichte'); ?></option>
                        <option value="cards" <?php selected($related_reports_display, 'cards'); ?>><?php esc_html_e('Als Karten', 'feuer-einsatzberichte'); ?></option>
                        <option value="list" <?php selected($related_reports_display, 'list'); ?>><?php esc_html_e('Als Liste', 'feuer-einsatzberichte'); ?></option>
                    </select>
                    <small><?php esc_html_e('Zeigt nach der Galerie weitere Einsatzberichte als Karten oder als kompakte Liste mit Titel, Datum, Uhrzeit und Nummer.', 'feuer-einsatzberichte'); ?></small>
                </label>

                <label class="feu-admin-settings-field">
                    <span><?php esc_html_e('Anzahl weiterer Einsatzberichte', 'feuer-einsatzberichte'); ?></span>
                    <input type="number"
                           id="feu_einsatz_related_reports_count"
                           name="feu_einsatz_related_reports_count"
                           value="<?php echo esc_attr($related_reports_count); ?>"
                           min="1"
                           max="12"
                           step="1"
                           class="small-text" />
                    <small><?php esc_html_e('Legt fest, wie viele weitere Einsatzberichte unterhalb eines einzelnen Beitrags gezeigt werden.', 'feuer-einsatzberichte'); ?></small>
                </label>
            </div>
        </section>

        <section class="feu-admin-settings-surface">
            <div class="feu-admin-settings-surface-head">
                <div>
                    <h2><?php esc_html_e('Sichtbare Einsatzinformationen', 'feuer-einsatzberichte'); ?></h2>
                    <p class="description"><?php esc_html_e('Waehlt aus, welche Metadaten oberhalb der Beschreibung sichtbar bleiben sollen.', 'feuer-einsatzberichte'); ?></p>
                </div>
            </div>

            <div class="feu-admin-settings-check-grid">
                <?php foreach ($single_info_field_options as $field_key => $field_label) : ?>
                    <label class="feu-admin-settings-check-card">
                        <input type="checkbox"
                               name="feu_einsatz_single_info_fields[]"
                               value="<?php echo esc_attr($field_key); ?>"
                               <?php checked(in_array($field_key, (array) $single_info_fields, true)); ?> />
                        <span class="feu-admin-settings-check-copy">
                            <strong><?php echo esc_html($field_label); ?></strong>
                            <small><?php esc_html_e('Im Einzelbeitrag sichtbar.', 'feuer-einsatzberichte'); ?></small>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="feu-admin-settings-surface">
            <div class="feu-admin-settings-surface-head">
                <div>
                    <h2><?php esc_html_e('Karte und Datenschutz', 'feuer-einsatzberichte'); ?></h2>
                    <p class="description"><?php esc_html_e('Steuert, wann die Live-Karte geladen wird und wie sie ohne Einwilligung behandelt wird.', 'feuer-einsatzberichte'); ?></p>
                </div>
            </div>

            <div class="feu-admin-settings-form-grid feu-admin-settings-form-grid--2">
                <label class="feu-admin-settings-field">
                    <span><?php esc_html_e('Darstellung von Karte und Beitragsbild', 'feuer-einsatzberichte'); ?></span>
                    <select id="feu_einsatz_single_map_display_mode"
                            name="feu_einsatz_single_map_display_mode"
                            class="regular-text">
                        <option value="live" <?php selected($single_map_display_mode, 'live'); ?>><?php esc_html_e('Live-Karte anzeigen', 'feuer-einsatzberichte'); ?></option>
                        <option value="image" <?php selected($single_map_display_mode, 'image'); ?>><?php esc_html_e('Nur Beitragsbild anzeigen', 'feuer-einsatzberichte'); ?></option>
                        <option value="disabled" <?php selected($single_map_display_mode, 'disabled'); ?>><?php esc_html_e('Karte vollstaendig ausblenden', 'feuer-einsatzberichte'); ?></option>
                    </select>
                </label>

                <label class="feu-admin-settings-field">
                    <span><?php esc_html_e('Datenschutz-Verhalten der Live-Karte', 'feuer-einsatzberichte'); ?></span>
                    <select id="feu_einsatz_single_map_privacy_mode"
                            name="feu_einsatz_single_map_privacy_mode"
                            class="regular-text">
                        <option value="always" <?php selected($single_map_privacy_mode, 'always'); ?>><?php esc_html_e('Karte direkt laden', 'feuer-einsatzberichte'); ?></option>
                        <option value="consent_hide" <?php selected($single_map_privacy_mode, 'consent_hide'); ?>><?php esc_html_e('Ohne Zustimmung Karte verbergen', 'feuer-einsatzberichte'); ?></option>
                        <option value="consent_image" <?php selected($single_map_privacy_mode, 'consent_image'); ?>><?php esc_html_e('Ohne Zustimmung Beitragsbild anzeigen', 'feuer-einsatzberichte'); ?></option>
                    </select>
                </label>
            </div>

            <div class="feu-admin-settings-check-grid">
                <label class="feu-admin-settings-check-card">
                    <input type="checkbox"
                           id="feu_einsatz_single_desaturate_organizations"
                           name="feu_einsatz_single_desaturate_organizations"
                           value="1"
                           <?php checked($single_desaturate_organizations, 1); ?> />
                    <span class="feu-admin-settings-check-copy">
                        <strong><?php esc_html_e('Farben bei Kraefte vor Ort entschaerfen', 'feuer-einsatzberichte'); ?></strong>
                        <small><?php esc_html_e('Die Organisationsfarben werden im Einzelbeitrag entsaettigt dargestellt.', 'feuer-einsatzberichte'); ?></small>
                    </span>
                </label>
            </div>
        </section>
    </div>
</div>
