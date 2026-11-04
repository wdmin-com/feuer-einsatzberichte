<?php
if (!defined('ABSPATH')) {
    exit;
}

$tab_funktionen_classes = 'feu-einsatz-tab-content feu-admin-settings-panel--card';

if (!isset($active_tab) || 'funktionen' !== (string) $active_tab) {
    $tab_funktionen_classes .= ' feu-einsatz-tab-content-hidden';
}
?>

<div id="tab-funktionen" class="<?php echo esc_attr($tab_funktionen_classes); ?>">
    <h2><?php _e('Funktionen', 'feuer-einsatzberichte'); ?></h2>
    <p><?php _e('Definieren Sie die Einsatzfunktionen. Neue Mannschaftsmitglieder erhalten zuerst die am Mannschaftsmitglied hinterlegte Standardfunktion. Ohne eigene Vorgabe wird die unten gewählte Standardfunktion gesetzt.', 'feuer-einsatzberichte'); ?></p>

    <section class="feu-einsatz-function-default-card" aria-labelledby="feu-einsatz-function-default-title">
        <h3 id="feu-einsatz-function-default-title"><?php esc_html_e('Standardfunktion ohne Mitgliedsvorgabe', 'feuer-einsatzberichte'); ?></h3>
        <label for="feu_einsatz_default_participant_function" class="screen-reader-text"><?php _e('Standardfunktion auswählen', 'feuer-einsatzberichte'); ?></label>
        <select id="feu_einsatz_default_participant_function"
                name="feu_einsatz_default_participant_function"
                class="regular-text">
            <?php foreach ($functions as $function): ?>
                <option value="<?php echo esc_attr($function); ?>" <?php selected($participant_fallback_function, $function); ?>>
                    <?php echo esc_html($function); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description"><?php esc_html_e('Diese Funktion wird automatisch gesetzt, wenn ein ausgewähltes Mannschaftsmitglied keine eigene Standardfunktion hat. „Keine Funktion“ bleibt im Einsatzbericht weiterhin manuell wählbar.', 'feuer-einsatzberichte'); ?></p>
    </section>

    <section class="feu-einsatz-functions-editor" aria-labelledby="feu-einsatz-functions-editor-title">
        <div class="feu-einsatz-functions-list-head">
            <h3 id="feu-einsatz-functions-editor-title"><?php esc_html_e('Verfügbare Funktionen', 'feuer-einsatzberichte'); ?></h3>
            <span><?php esc_html_e('Reihenfolge und Aktionen', 'feuer-einsatzberichte'); ?></span>
        </div>
        <div id="feu-einsatz-functions-list">
        <?php foreach ($functions as $function_index => $function): ?>
            <div class="feu-einsatz-function-item">
                <input type="text"
                       name="feu_einsatz_functions[]"
                       value="<?php echo esc_attr($function); ?>"
                       class="regular-text"
                       aria-label="<?php echo esc_attr(sprintf(__('Funktion %d', 'feuer-einsatzberichte'), $function_index + 1)); ?>"
                       placeholder="<?php esc_attr_e('Funktion eingeben', 'feuer-einsatzberichte'); ?>" />
                <div class="feu-einsatz-function-actions">
                    <button type="button" class="button feu-einsatz-move-function-up" aria-label="<?php esc_attr_e('Nach oben verschieben', 'feuer-einsatzberichte'); ?>">
                        <span class="feu-einsatz-button-icon" aria-hidden="true">↑</span>
                    </button>
                    <button type="button" class="button feu-einsatz-move-function-down" aria-label="<?php esc_attr_e('Nach unten verschieben', 'feuer-einsatzberichte'); ?>">
                        <span class="feu-einsatz-button-icon" aria-hidden="true">↓</span>
                    </button>
                    <button type="button" class="button feu-einsatz-remove-function" aria-label="<?php echo esc_attr(sprintf(__('Funktion „%s“ entfernen', 'feuer-einsatzberichte'), $function)); ?>" title="<?php esc_attr_e('Funktion entfernen', 'feuer-einsatzberichte'); ?>">
                        <span class="feu-einsatz-button-icon" aria-hidden="true">×</span>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </section>

    <button type="button" id="feu-einsatz-add-function" class="button">
        <span class="feu-einsatz-button-icon" aria-hidden="true">+</span>
        <?php _e('Weitere Funktion hinzufügen', 'feuer-einsatzberichte'); ?>
    </button>
</div>

