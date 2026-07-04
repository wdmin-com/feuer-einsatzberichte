<?php
if (!defined('ABSPATH')) {
    exit;
}

$tab_overview_classes = 'feu-einsatz-tab-content';

if (isset($active_tab) && 'uebersicht' !== (string) $active_tab) {
    $tab_overview_classes .= ' feu-einsatz-tab-content-hidden';
}
?>

<div id="tab-uebersicht" class="<?php echo esc_attr($tab_overview_classes); ?>">
    <section class="feu-admin-settings-surface">
        <div class="feu-admin-settings-surface-head">
            <div>
                <h2><?php esc_html_e('Einsatz-Uebersicht', 'feuer-einsatzberichte'); ?></h2>
                <p class="description"><?php esc_html_e('Diese Optionen wurden in die allgemeinen Einstellungen verschoben, damit die Navigation kompakter bleibt.', 'feuer-einsatzberichte'); ?></p>
            </div>
        </div>
        <p class="feu-admin-settings-inline-note"><?php esc_html_e('Bitte wechseln Sie zu Allgemein. Dort finden Sie jetzt die Steuerung fuer Sidebar-Statistik und Jahresfilter.', 'feuer-einsatzberichte'); ?></p>
    </section>
</div>
