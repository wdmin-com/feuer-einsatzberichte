<?php
if (!defined('ABSPATH')) {
    exit;
}

$tab_funktionen_classes = 'feu-einsatz-tab-content';

if (!isset($active_tab) || 'funktionen' !== (string) $active_tab) {
    $tab_funktionen_classes .= ' feu-einsatz-tab-content-hidden';
}
?>

<div id="tab-funktionen" class="<?php echo esc_attr($tab_funktionen_classes); ?>">
    <h2><?php _e('Funktionen', 'feuer-einsatzberichte'); ?></h2>
    <p><?php _e('Definieren Sie die Einsatzfunktionen. Neue Einsatzteilnehmer erhalten zuerst die am Teilnehmer hinterlegte Standardfunktion. Ohne eigene Vorgabe wird die unten gewählte Standardfunktion gesetzt.', 'feuer-einsatzberichte'); ?></p>

    <div class="feu-einsatz-manifest-preview-card feu-einsatz-function-default-card">
        <h3><?php _e('Standardfunktion ohne Teilnehmer-Vorgabe', 'feuer-einsatzberichte'); ?></h3>
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
        <p class="description"><?php _e('Diese Funktion wird automatisch gesetzt, wenn ein ausgewählter Teilnehmer keine eigene Standardfunktion hat. Keine Funktion bleibt im Einsatzbericht weiterhin manuell waehlbar.', 'feuer-einsatzberichte'); ?></p>
    </div>

    <div id="feu-einsatz-functions-list">
        <?php foreach ($functions as $function): ?>
            <div class="feu-einsatz-function-item">
                <input type="text"
                       name="feu_einsatz_functions[]"
                       value="<?php echo esc_attr($function); ?>"
                       class="regular-text"
                       placeholder="<?php _e('Funktion eingeben', 'feuer-einsatzberichte'); ?>" />
                <div class="feu-einsatz-function-actions">
                    <button type="button" class="button feu-einsatz-move-function-up" aria-label="<?php esc_attr_e('Nach oben verschieben', 'feuer-einsatzberichte'); ?>">
                        <span class="feu-einsatz-button-icon" aria-hidden="true">↑</span>
                    </button>
                    <button type="button" class="button feu-einsatz-move-function-down" aria-label="<?php esc_attr_e('Nach unten verschieben', 'feuer-einsatzberichte'); ?>">
                        <span class="feu-einsatz-button-icon" aria-hidden="true">↓</span>
                    </button>
                    <button type="button" class="button feu-einsatz-remove-function">
                        <span class="feu-einsatz-button-icon" aria-hidden="true">×</span>
                        <?php _e('Entfernen', 'feuer-einsatzberichte'); ?>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <button type="button" id="feu-einsatz-add-function" class="button">
        <span class="feu-einsatz-button-icon" aria-hidden="true">+</span>
        <?php _e('Weitere Funktion hinzufuegen', 'feuer-einsatzberichte'); ?>
    </button>
</div>

