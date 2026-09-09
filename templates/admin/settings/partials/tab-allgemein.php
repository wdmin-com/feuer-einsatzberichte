<?php
if (!defined('ABSPATH')) {
    exit;
}

$tab_allgemein_classes = 'feu-einsatz-tab-content';

if (isset($active_tab) && 'allgemein' !== (string) $active_tab) {
    $tab_allgemein_classes .= ' feu-einsatz-tab-content-hidden';
}
?>

<div id="tab-allgemein" class="<?php echo esc_attr($tab_allgemein_classes); ?>">
    <div class="feu-admin-settings-stack">
        <section class="feu-admin-settings-surface">
            <div class="feu-admin-settings-surface-head">
                <div>
                    <h2><?php esc_html_e('Allgemeine Einstellungen', 'feuer-einsatzberichte'); ?></h2>
                    <p class="description"><?php esc_html_e('Grundverhalten des Plugins, Standard-Kommentare und der bevorzugte Kartenstil.', 'feuer-einsatzberichte'); ?></p>
                </div>
            </div>

            <div class="feu-admin-settings-form-grid feu-admin-settings-form-grid--2">
                <div class="feu-admin-settings-fieldset">
                    <div class="feu-admin-settings-fieldset-head">
                        <h3><?php esc_html_e('Automatisierung', 'feuer-einsatzberichte'); ?></h3>
                        <p class="description"><?php esc_html_e('Steuert die automatische Erzeugung von Kartenbildern und das Standardverhalten neuer Einsatzberichte.', 'feuer-einsatzberichte'); ?></p>
                    </div>
                    <label class="feu-admin-settings-check-card">
                        <input type="checkbox"
                               id="feu_einsatz_auto_map_image"
                               name="feu_einsatz_auto_map_image"
                               value="1"
                               <?php checked($auto_map_image, 1); ?> />
                        <span class="feu-admin-settings-check-copy">
                            <strong><?php esc_html_e('Automatisches Kartenbild verwenden', 'feuer-einsatzberichte'); ?></strong>
                            <small><?php esc_html_e('Erzeugt oder aktualisiert automatisch das Kartenbild als Beitragsbild, solange kein eigenes Foto gesetzt wurde.', 'feuer-einsatzberichte'); ?></small>
                        </span>
                    </label>

                    <label class="feu-admin-settings-check-card">
                        <input type="checkbox"
                               id="feu_einsatz_default_comments_enabled"
                               name="feu_einsatz_default_comments_enabled"
                               value="1"
                               <?php checked($default_comments_enabled, 1); ?> />
                        <span class="feu-admin-settings-check-copy">
                            <strong><?php esc_html_e('Kommentare bei neuen Berichten aktivieren', 'feuer-einsatzberichte'); ?></strong>
                            <small><?php esc_html_e('Neue Einsatzberichte erhalten Kommentare nur dann automatisch, wenn diese Option aktiv ist.', 'feuer-einsatzberichte'); ?></small>
                        </span>
                    </label>
                </div>

                <div class="feu-admin-settings-fieldset">
                    <div class="feu-admin-settings-fieldset-head">
                        <h3><?php esc_html_e('Darstellung', 'feuer-einsatzberichte'); ?></h3>
                        <p class="description"><?php esc_html_e('Vorgaben fuer Kartenlisten und optische Standardeinstellungen.', 'feuer-einsatzberichte'); ?></p>
                    </div>

                    <label class="feu-admin-settings-field">
                        <span><?php esc_html_e('Standard-Kartenstil', 'feuer-einsatzberichte'); ?></span>
                        <select id="feu_einsatz_default_card_variant"
                                name="feu_einsatz_default_card_variant"
                                class="regular-text">
                            <?php foreach ($card_variants as $variant_key => $variant_config) : ?>
                                <option value="<?php echo esc_attr($variant_key); ?>" <?php selected($default_card_variant, $variant_key); ?>>
                                    <?php echo esc_html($variant_config['label']); ?> (<?php echo esc_html($variant_key); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small><?php esc_html_e('Wird fuer Einsatzlisten, Uebersichten und letzte Einsatzberichte ohne eigenen card_variant genutzt.', 'feuer-einsatzberichte'); ?></small>
                    </label>
                </div>
            </div>
        </section>

        <section class="feu-admin-settings-surface">
            <div class="feu-admin-settings-surface-head">
                <div>
                    <h2><?php esc_html_e('Einsatz-Uebersicht', 'feuer-einsatzberichte'); ?></h2>
                    <p class="description"><?php esc_html_e('Steuert die sichtbaren Zusatzmodule auf der oeffentlichen Einsatz-Uebersicht.', 'feuer-einsatzberichte'); ?></p>
                </div>
            </div>

            <div class="feu-admin-settings-check-grid">
                <label class="feu-admin-settings-check-card">
                    <input type="checkbox"
                           id="feu_einsatz_overview_show_stats"
                           name="feu_einsatz_overview_show_stats"
                           value="1"
                           <?php checked($overview_show_stats, 1); ?> />
                    <span class="feu-admin-settings-check-copy">
                        <strong><?php esc_html_e('Sidebar-Statistik anzeigen', 'feuer-einsatzberichte'); ?></strong>
                        <small><?php esc_html_e('Zeigt die Statistik fuer Alle Jahre bzw. das ausgewaehlte Jahr in der Sidebar.', 'feuer-einsatzberichte'); ?></small>
                    </span>
                </label>

                <label class="feu-admin-settings-check-card">
                    <input type="checkbox"
                           id="feu_einsatz_overview_show_year_filter"
                           name="feu_einsatz_overview_show_year_filter"
                           value="1"
                           <?php checked($overview_show_year_filter, 1); ?> />
                    <span class="feu-admin-settings-check-copy">
                        <strong><?php esc_html_e('Jahresfilter anzeigen', 'feuer-einsatzberichte'); ?></strong>
                        <small><?php esc_html_e('Erlaubt auf der Uebersichtsseite die direkte Auswahl eines Jahres.', 'feuer-einsatzberichte'); ?></small>
                    </span>
                </label>
            </div>
        </section>

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

            <div class="feu-admin-settings-check-grid">
                <?php
                $single_info_field_options = [
                    'street' => __('Strasse', 'feuer-einsatzberichte'),
                    'location' => __('Ort', 'feuer-einsatzberichte'),
                    'date' => __('Datum', 'feuer-einsatzberichte'),
                    'time' => __('Uhrzeit', 'feuer-einsatzberichte'),
                    'category' => __('Einsatzart', 'feuer-einsatzberichte'),
                    'organizations' => __('Kraefte vor Ort', 'feuer-einsatzberichte'),
                ];
                foreach ($single_info_field_options as $field_key => $field_label) :
                ?>
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

        <section class="feu-admin-settings-surface">
            <div class="feu-admin-settings-surface-head">
                <div>
                    <h2><?php esc_html_e('Sicherheit und Archiv', 'feuer-einsatzberichte'); ?></h2>
                    <p class="description"><?php esc_html_e('Verwaltet Archivaufbewahrung und die Absicherung des Teilnehmer-Rankings.', 'feuer-einsatzberichte'); ?></p>
                </div>
            </div>

            <div class="feu-admin-settings-form-grid feu-admin-settings-form-grid--2">
                <label class="feu-admin-settings-field">
                    <span><?php esc_html_e('Gespeicherte Archive', 'feuer-einsatzberichte'); ?></span>
                    <input type="number"
                           id="feu_einsatz_backup_retention_limit"
                           name="feu_einsatz_backup_retention_limit"
                           value="<?php echo esc_attr($backup_retention_limit); ?>"
                           min="1"
                           max="50"
                           step="1"
                           class="small-text" />
                    <small><?php esc_html_e('Sobald mehr Archive vorhanden sind, werden die aeltesten Sicherungen automatisch entfernt.', 'feuer-einsatzberichte'); ?></small>
                </label>

                <label class="feu-admin-settings-field">
                    <span><?php esc_html_e('PIN fuer Teilnehmer-Ranking', 'feuer-einsatzberichte'); ?></span>
                    <input type="password"
                           id="feu_einsatz_participant_ranking_pin"
                           name="feu_einsatz_participant_ranking_pin"
                           value=""
                           class="regular-text"
                           maxlength="64"
                           autocomplete="new-password"
                           inputmode="numeric"
                           placeholder="<?php esc_attr_e('Neuen PIN eingeben', 'feuer-einsatzberichte'); ?>" />
                    <small>
                        <?php if (!empty($participant_ranking_pin_is_configured)) : ?>
                            <?php esc_html_e('Es ist bereits ein PIN gesetzt. Feld leer lassen, wenn der vorhandene PIN unveraendert bleiben soll.', 'feuer-einsatzberichte'); ?>
                        <?php else : ?>
                            <?php esc_html_e('Wenn hier ein PIN gesetzt wird, ist das Teilnehmer-Ranking nur noch nach Eingabe dieses PIN sichtbar.', 'feuer-einsatzberichte'); ?>
                        <?php endif; ?>
                    </small>
                </label>
            </div>
        </section>

        <?php if (current_user_can('manage_options')) : ?>
        <section class="feu-admin-settings-surface feu-admin-recovery">
            <div class="feu-admin-settings-surface-head">
                <div>
                    <h2><?php esc_html_e('Sicherung und Wiederherstellung', 'feuer-einsatzberichte'); ?></h2>
                    <p class="description"><?php esc_html_e('Jede reguläre Speicherung legt automatisch einen Wiederherstellungspunkt an. Bis zu zehn frühere Stände werden sicher aufbewahrt.', 'feuer-einsatzberichte'); ?></p>
                </div>
                <span class="feu-admin-recovery-status">
                    <span class="ti ti-history" aria-hidden="true"></span>
                    <?php
                    echo esc_html(sprintf(
                        /* translators: %d: number of stored settings snapshots */
                        _n('%d Wiederherstellungspunkt', '%d Wiederherstellungspunkte', count((array) ($settings_history ?? [])), 'feuer-einsatzberichte'),
                        count((array) ($settings_history ?? []))
                    ));
                    ?>
                </span>
            </div>

            <div class="feu-admin-recovery-grid">
                <article class="feu-admin-recovery-card">
                    <span class="feu-admin-recovery-icon ti ti-restore" aria-hidden="true"></span>
                    <div>
                        <h3><?php esc_html_e('Letzte Speicherung wiederherstellen', 'feuer-einsatzberichte'); ?></h3>
                        <?php if (!empty($settings_history[0]['created_at'])) : ?>
                            <p><?php echo esc_html(sprintf(__('Verfügbar vom %s. Die aktuelle Konfiguration wird durch diesen Stand ersetzt.', 'feuer-einsatzberichte'), mysql2date(get_option('date_format') . ' ' . get_option('time_format'), (string) $settings_history[0]['created_at']))); ?></p>
                        <?php else : ?>
                            <p><?php esc_html_e('Nach der ersten Änderung steht hier automatisch die zuvor gespeicherte Konfiguration bereit.', 'feuer-einsatzberichte'); ?></p>
                        <?php endif; ?>
                    </div>
                    <button type="submit"
                            name="feu_einsatz_restore_latest_settings"
                            value="1"
                            class="button button-secondary"
                            formnovalidate
                            <?php disabled(empty($settings_history)); ?>>
                        <?php esc_html_e('Vorherigen Stand laden', 'feuer-einsatzberichte'); ?>
                    </button>
                </article>

                <article class="feu-admin-recovery-card feu-admin-recovery-card--danger">
                    <span class="feu-admin-recovery-icon ti ti-shield-lock" aria-hidden="true"></span>
                    <div>
                        <h3><?php esc_html_e('Werkseinstellungen', 'feuer-einsatzberichte'); ?></h3>
                        <p><?php esc_html_e('Setzt ausschließlich die Plugin-Einstellungen zurück. Einsatzberichte, Medien, Archive und Teilnehmer werden nicht gelöscht.', 'feuer-einsatzberichte'); ?></p>
                    </div>

                    <div class="feu-admin-reset-controls">
                        <button type="submit" name="feu_einsatz_generate_factory_code" value="1" class="button button-secondary" formnovalidate>
                            <?php esc_html_e('7-stelligen Code erzeugen', 'feuer-einsatzberichte'); ?>
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
                            <?php esc_html_e('Werkseinstellungen laden', 'feuer-einsatzberichte'); ?>
                        </button>
                    </div>
                </article>
            </div>
        </section>
        <?php endif; ?>
    </div>
</div>
