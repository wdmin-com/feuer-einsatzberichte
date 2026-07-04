<?php
if (!defined('ABSPATH')) {
    exit;
}

$tab_security_classes = 'feu-einsatz-tab-content';

if (isset($active_tab) && 'sicherheit' !== (string) $active_tab) {
    $tab_security_classes .= ' feu-einsatz-tab-content-hidden';
}
?>

<div id="tab-sicherheit" class="<?php echo esc_attr($tab_security_classes); ?>">
    <section class="feu-admin-settings-surface">
        <div class="feu-admin-settings-surface-head">
            <div>
                <h2><?php esc_html_e('Sicherheit und Archiv', 'feuer-einsatzberichte'); ?></h2>
                <p class="description"><?php esc_html_e('Die wichtigsten Sicherheitsoptionen liegen jetzt gesammelt unter Allgemein.', 'feuer-einsatzberichte'); ?></p>
            </div>
        </div>
        <p class="feu-admin-settings-inline-note"><?php esc_html_e('Wechseln Sie zu Allgemein, um Archiv-Aufbewahrung und den PIN fuer das Teilnehmer-Ranking zu verwalten.', 'feuer-einsatzberichte'); ?></p>
    </section>
</div>
