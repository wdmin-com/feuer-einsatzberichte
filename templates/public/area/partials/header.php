<?php
if (!defined('ABSPATH')) {
    exit;
}

$area_entries = isset($area_entries) && is_array($area_entries) ? $area_entries : [];
$station_display = isset($station_display) && is_array($station_display) ? $station_display : [];
$station_address = isset($station_display['address']) ? trim((string) $station_display['address']) : '';
$station_label = isset($station_display['label']) ? (string) $station_display['label'] : __('Feuerwehrhaus', 'feuer-einsatzberichte');
?>

<div class="feu-einsatz-area-page-header">
    <div class="feu-einsatz-area-page-header-main">
        <h1 class="feu-einsatz-area-page-title"><?php esc_html_e('Einsatzgebiet', 'feuer-einsatzberichte'); ?></h1>
        <p class="feu-einsatz-area-page-subtitle">
            <?php esc_html_e('Darstellung des Einsatzgebiets auf Basis der hinterlegten PLZ, Stadtteile oder Gebietsangaben und optionaler Einsatz-Zonen.', 'feuer-einsatzberichte'); ?>
        </p>

        <?php if ('' !== $station_address) : ?>
            <div class="feu-einsatz-area-station-summary">
                <span class="feu-einsatz-area-station-summary-label"><?php echo esc_html($station_label); ?></span>
                <strong class="feu-einsatz-area-station-summary-address"><?php echo esc_html($station_address); ?></strong>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($area_entries)) : ?>
        <div class="feu-einsatz-area-postcodes">
            <?php foreach ($area_entries as $area_entry) : ?>
                <span class="feu-einsatz-area-postcode-badge"><?php echo esc_html($area_entry); ?></span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
