<?php
if (!defined('ABSPATH')) {
    exit;
}

$db = isset($db) && $db instanceof FEU_Einsatz_Database ? $db : null;
$tab_strassenregister_classes = 'feu-einsatz-tab-content';

if (!isset($active_tab) || 'strassenregister' !== (string) $active_tab) {
    $tab_strassenregister_classes .= ' feu-einsatz-tab-content-hidden';
}
?>

<div id="tab-strassenregister" class="<?php echo esc_attr($tab_strassenregister_classes); ?>">
    <div class="feu-admin-settings-stack">
        <section class="feu-admin-settings-surface">
            <div class="feu-admin-settings-surface-head">
                <div>
                    <h2><?php esc_html_e('Strassenregister', 'feuer-einsatzberichte'); ?></h2>
                    <p class="description"><?php esc_html_e('Hier pflegen Sie den lokalen Strassenbestand fuer Autocomplete, PLZ-/Orts-Vorschlaege und schnelle Einsatzort-Erfassung.', 'feuer-einsatzberichte'); ?></p>
                </div>
                <div class="feu-einsatz-street-registry-head-meta">
                    <span class="feu-einsatz-badge"><span id="feu-einsatz-street-registry-count"><?php echo esc_html(count((array) $street_registry_entries)); ?></span> <?php esc_html_e('Register-Eintraege', 'feuer-einsatzberichte'); ?></span>
                    <span class="feu-einsatz-badge"><span id="feu-einsatz-known-street-count"><?php echo esc_html(count((array) ($street_suggestion_records ?? []))); ?></span> <?php esc_html_e('Bekannte Strassen', 'feuer-einsatzberichte'); ?></span>
                </div>
            </div>

            <div class="feu-admin-settings-form-grid feu-admin-settings-form-grid--2">
                <div class="feu-admin-settings-fieldset">
                    <div class="feu-admin-settings-fieldset-head">
                        <h3><?php esc_html_e('Eintrag hinzufuegen', 'feuer-einsatzberichte'); ?></h3>
                        <p class="description"><?php esc_html_e('Gleiche Strassennamen koennen mehrfach mit unterschiedlichen PLZ oder Orten gepflegt werden.', 'feuer-einsatzberichte'); ?></p>
                    </div>

                    <div class="feu-einsatz-settings-field-grid feu-einsatz-settings-field-grid--street-registry">
                        <input type="hidden" id="feu-einsatz-street-registry-id" value="0" />
                        <input
                            type="text"
                            id="feu-einsatz-street-registry-street"
                            class="regular-text feu-einsatz-street-registry-input"
                            placeholder="<?php esc_attr_e('Strasse', 'feuer-einsatzberichte'); ?>"
                        />
                        <input
                            type="text"
                            id="feu-einsatz-street-registry-postcode"
                            class="small-text feu-einsatz-street-registry-input feu-einsatz-street-registry-input--postcode"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            maxlength="5"
                            placeholder="<?php esc_attr_e('PLZ', 'feuer-einsatzberichte'); ?>"
                        />
                        <input
                            type="text"
                            id="feu-einsatz-street-registry-city"
                            class="regular-text feu-einsatz-street-registry-input"
                            placeholder="<?php esc_attr_e('Ort', 'feuer-einsatzberichte'); ?>"
                        />
                    </div>

                    <div class="feu-admin-settings-action-stack">
                        <button type="button" id="feu-einsatz-add-street-registry-entry" class="button button-secondary">
                            <?php esc_html_e('Strasse hinzufuegen', 'feuer-einsatzberichte'); ?>
                        </button>
                    </div>

                    <p class="description feu-admin-settings-inline-note">
                        <?php esc_html_e('Die Vorschlagslogik nutzt zuerst dieses Register und erweitert es zusaetzlich mit bereits verwendeten Strassen aus Einsatzberichten.', 'feuer-einsatzberichte'); ?>
                    </p>
                </div>

                <div class="feu-admin-settings-fieldset">
                    <div class="feu-admin-settings-fieldset-head">
                        <h3><?php esc_html_e('Hinweise zur Verwendung', 'feuer-einsatzberichte'); ?></h3>
                    </div>

                    <div class="feu-admin-settings-check-grid">
                        <div class="feu-admin-settings-check-card">
                            <span class="feu-admin-settings-check-copy">
                                <strong><?php esc_html_e('Mehrdeutige Strassen', 'feuer-einsatzberichte'); ?></strong>
                                <small><?php esc_html_e('Wenn eine Strasse in mehreren PLZ-Bereichen vorkommt, legen Sie mehrere Eintraege an. Die Vorschlaege zeigen dann Strasse / PLZ / Ort getrennt an.', 'feuer-einsatzberichte'); ?></small>
                            </span>
                        </div>
                        <div class="feu-admin-settings-check-card">
                            <span class="feu-admin-settings-check-copy">
                                <strong><?php esc_html_e('Loeschschutz', 'feuer-einsatzberichte'); ?></strong>
                                <small><?php esc_html_e('Eintraege, die bereits in Einsatzberichten verwendet werden, koennen nicht geloescht werden.', 'feuer-einsatzberichte'); ?></small>
                            </span>
                        </div>
                        <div class="feu-admin-settings-check-card">
                            <span class="feu-admin-settings-check-copy">
                                <strong><?php esc_html_e('Autocomplete', 'feuer-einsatzberichte'); ?></strong>
                                <small><?php esc_html_e('Beim Tippen im Strassenfeld werden bis zu fuenf passende Vorschlaege mit PLZ und Ort angezeigt.', 'feuer-einsatzberichte'); ?></small>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="feu-einsatz-street-registry-table-wrap">
                <div class="feu-einsatz-street-registry-toolbar">
                    <input
                        type="search"
                        id="feu-einsatz-street-registry-search"
                        class="regular-text"
                        placeholder="<?php esc_attr_e('Strassen, PLZ oder Orte filtern', 'feuer-einsatzberichte'); ?>"
                    />
                </div>
                <div class="feu-einsatz-street-registry-section-head">
                    <h3><?php esc_html_e('Manuell gepflegte Register-Eintraege', 'feuer-einsatzberichte'); ?></h3>
                    <p class="description"><?php esc_html_e('Diese Liste zeigt alle Strassen, die direkt in diesem Register gepflegt werden.', 'feuer-einsatzberichte'); ?></p>
                </div>
                <?php if (empty($street_registry_entries)) : ?>
                    <p class="description feu-einsatz-street-registry-empty"><?php esc_html_e('Noch keine manuell gepflegten Strassen vorhanden.', 'feuer-einsatzberichte'); ?></p>
                <?php else : ?>
                    <table class="wp-list-table widefat striped feu-einsatz-street-registry-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Strasse / PLZ / Ort', 'feuer-einsatzberichte'); ?></th>
                                <th><?php esc_html_e('Einsaetze', 'feuer-einsatzberichte'); ?></th>
                                <th><?php esc_html_e('Aktion', 'feuer-einsatzberichte'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ((array) $street_registry_entries as $street_registry_entry) : ?>
                                <?php
                                $street_registry_id = isset($street_registry_entry->id) ? (int) $street_registry_entry->id : 0;
                                $street_registry_street = isset($street_registry_entry->street) ? (string) $street_registry_entry->street : '';
                                $street_registry_postcode = isset($street_registry_entry->postcode) ? (string) $street_registry_entry->postcode : '';
                                $street_registry_city = isset($street_registry_entry->city) ? (string) $street_registry_entry->city : '';
                                $street_usage_count = $db ? $db->count_posts_using_street_registry_entry($street_registry_id) : 0;
                                $street_report_url = add_query_arg(
                                    array_filter(
                                        [
                                            'post_type' => 'post',
                                            'feu_einsatz_filter' => '1',
                                            'feu_einsatz_street' => $street_registry_street,
                                            'feu_einsatz_postcode' => $street_registry_postcode,
                                            'feu_einsatz_city' => $street_registry_city,
                                        ],
                                        static function ($value) {
                                            return '' !== (string) $value;
                                        }
                                    ),
                                    admin_url('edit.php')
                                );
                                ?>
                                <tr
                                    data-street-registry-id="<?php echo esc_attr($street_registry_id); ?>"
                                    data-search="<?php echo esc_attr(strtolower(trim((string) (($street_registry_entry->street ?? '') . ' ' . ($street_registry_entry->postcode ?? '') . ' ' . ($street_registry_entry->city ?? ''))))); ?>"
                                >
                                    <td>
                                        <strong><?php echo esc_html($street_registry_street); ?></strong>
                                        <span class="feu-einsatz-street-registry-row-meta">
                                            <?php echo esc_html(implode(' / ', array_filter([$street_registry_postcode, $street_registry_city]))); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html((string) max(0, (int) $street_usage_count)); ?></td>
                                    <td class="feu-einsatz-table-actions">
                                        <button
                                            type="button"
                                            class="button button-small feu-einsatz-edit-street-registry-entry"
                                            data-id="<?php echo esc_attr($street_registry_id); ?>"
                                            data-street="<?php echo esc_attr($street_registry_street); ?>"
                                            data-postcode="<?php echo esc_attr($street_registry_postcode); ?>"
                                            data-city="<?php echo esc_attr($street_registry_city); ?>"
                                        >
                                            <?php esc_html_e('Bearbeiten', 'feuer-einsatzberichte'); ?>
                                        </button>
                                        <a
                                            class="button button-small"
                                            href="<?php echo esc_url($street_report_url); ?>"
                                        >
                                            <?php esc_html_e('Einsaetze ansehen', 'feuer-einsatzberichte'); ?>
                                        </a>
                                        <button
                                            type="button"
                                            class="button button-small feu-einsatz-delete-street-registry-entry"
                                            data-id="<?php echo esc_attr($street_registry_id); ?>"
                                            <?php disabled($street_usage_count > 0); ?>
                                        >
                                            <?php echo $street_usage_count > 0 ? esc_html__('In Benutzung', 'feuer-einsatzberichte') : esc_html__('Entfernen', 'feuer-einsatzberichte'); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <div class="feu-einsatz-street-registry-section-head feu-einsatz-street-registry-section-head--secondary">
                    <h3><?php esc_html_e('Alle bekannten Strassen', 'feuer-einsatzberichte'); ?></h3>
                    <p class="description"><?php esc_html_e('Diese Uebersicht kombiniert das manuelle Register mit bereits verwendeten Strassen aus Einsatzberichten. Genau diese Daten nutzt auch das Autocomplete.', 'feuer-einsatzberichte'); ?></p>
                </div>

                <?php if (empty($street_suggestion_records ?? [])) : ?>
                    <p class="description feu-einsatz-street-registry-empty"><?php esc_html_e('Aktuell sind noch keine bekannten Strassen verfuegbar.', 'feuer-einsatzberichte'); ?></p>
                <?php else : ?>
                    <table class="wp-list-table widefat striped feu-einsatz-street-registry-table feu-einsatz-street-registry-table--known">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Strasse / PLZ / Ort', 'feuer-einsatzberichte'); ?></th>
                                <th><?php esc_html_e('Verwendung', 'feuer-einsatzberichte'); ?></th>
                                <th><?php esc_html_e('Aktion', 'feuer-einsatzberichte'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ((array) ($street_suggestion_records ?? []) as $street_record) : ?>
                                <?php
                                $known_street = (string) ($street_record['street'] ?? '');
                                $known_postcode = (string) ($street_record['plz'] ?? '');
                                $known_city = (string) ($street_record['city'] ?? '');
                                $known_report_url = add_query_arg(
                                    array_filter(
                                        [
                                            'post_type' => 'post',
                                            'feu_einsatz_filter' => '1',
                                            'feu_einsatz_street' => $known_street,
                                            'feu_einsatz_postcode' => $known_postcode,
                                            'feu_einsatz_city' => $known_city,
                                        ],
                                        static function ($value) {
                                            return '' !== (string) $value;
                                        }
                                    ),
                                    admin_url('edit.php')
                                );
                                ?>
                                <tr data-search="<?php echo esc_attr(strtolower(trim((string) (($street_record['street'] ?? '') . ' ' . ($street_record['plz'] ?? '') . ' ' . ($street_record['city'] ?? ''))))); ?>">
                                    <td>
                                        <strong><?php echo esc_html($known_street); ?></strong>
                                        <span class="feu-einsatz-street-registry-row-meta">
                                            <?php echo esc_html(implode(' / ', array_filter([$known_postcode, $known_city]))); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html((string) max(0, (int) ($street_record['usage_count'] ?? 0))); ?></td>
                                    <td class="feu-einsatz-table-actions">
                                        <a class="button button-small" href="<?php echo esc_url($known_report_url); ?>">
                                            <?php esc_html_e('Einsaetze ansehen', 'feuer-einsatzberichte'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<div id="feu-einsatz-edit-street-registry-modal" class="feu-einsatz-modal" role="dialog" aria-modal="true" aria-labelledby="feu-einsatz-edit-street-registry-title">
    <div class="feu-einsatz-modal-content feu-einsatz-street-registry-modal">
        <div class="feu-einsatz-modal-header">
            <div>
                <h2 id="feu-einsatz-edit-street-registry-title"><?php esc_html_e('Strasse bearbeiten', 'feuer-einsatzberichte'); ?></h2>
                <p class="description"><?php esc_html_e('Aenderungen werden direkt im Strassenregister gespeichert, ohne die Seite neu zu laden.', 'feuer-einsatzberichte'); ?></p>
            </div>
            <button type="button" class="button-link feu-einsatz-modal-close feu-einsatz-street-registry-modal-close" aria-label="<?php esc_attr_e('Schliessen', 'feuer-einsatzberichte'); ?>">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>

        <div id="feu-einsatz-edit-street-registry-form" class="feu-einsatz-street-registry-modal-form" role="form">
            <input type="hidden" id="feu-einsatz-edit-street-registry-id" value="0" />

            <div class="feu-einsatz-settings-field-grid feu-einsatz-settings-field-grid--street-registry">
                <label>
                    <span><?php esc_html_e('Strasse', 'feuer-einsatzberichte'); ?></span>
                    <input
                        type="text"
                        id="feu-einsatz-edit-street-registry-street"
                        class="regular-text feu-einsatz-street-registry-input"
                        required
                    />
                </label>
                <label>
                    <span><?php esc_html_e('PLZ', 'feuer-einsatzberichte'); ?></span>
                    <input
                        type="text"
                        id="feu-einsatz-edit-street-registry-postcode"
                        class="small-text feu-einsatz-street-registry-input feu-einsatz-street-registry-input--postcode"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        maxlength="5"
                    />
                </label>
                <label>
                    <span><?php esc_html_e('Ort', 'feuer-einsatzberichte'); ?></span>
                    <input
                        type="text"
                        id="feu-einsatz-edit-street-registry-city"
                        class="regular-text feu-einsatz-street-registry-input"
                    />
                </label>
            </div>

            <div class="feu-admin-settings-action-stack">
                <button type="button" class="button button-primary" id="feu-einsatz-save-street-registry-modal">
                    <?php esc_html_e('Strasse speichern', 'feuer-einsatzberichte'); ?>
                </button>
                <button type="button" class="button feu-einsatz-street-registry-modal-close">
                    <?php esc_html_e('Abbrechen', 'feuer-einsatzberichte'); ?>
                </button>
            </div>
        </div>
    </div>
</div>
