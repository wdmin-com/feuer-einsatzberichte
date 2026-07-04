<?php
if (!defined('ABSPATH')) {
    exit;
}

$map_id = isset($map_id) ? (string) $map_id : '';
$map_config = isset($map_config) && is_array($map_config) ? $map_config : [];
$areas = isset($areas) && is_array($areas) ? $areas : [];
$station = isset($station) && is_array($station) ? $station : [];
$station_display = isset($station_display) && is_array($station_display) ? $station_display : [];
$clusters = isset($clusters) && is_array($clusters) ? $clusters : [];
$show_calls = !empty($show_calls);
$station_address = '';

if (!empty($station_display['address'])) {
    $station_address = trim((string) $station_display['address']);
} elseif (!empty($station['address'])) {
    $station_address = trim((string) $station['address']);
}
?>

<div class="feu-einsatz-area-map-runtime" data-area-map-enabled="1">
    <div id="<?php echo esc_attr($map_id); ?>" class="feu-einsatz-area-map-canvas"></div>
    <script type="application/json" class="feu-einsatz-area-map-config"><?php echo wp_json_encode($map_config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
    <div class="feu-einsatz-area-map-fallback" hidden>
        <div class="feu-einsatz-area-map-fallback-inner">
            <strong><?php esc_html_e('Einsatzgebiet-Karte nicht verfügbar.', 'feuer-einsatzberichte'); ?></strong>
            <p><?php esc_html_e('Bitte prüfen Sie die hinterlegten PLZ oder die Kartendaten.', 'feuer-einsatzberichte'); ?></p>
        </div>
    </div>
</div>

<div class="feu-einsatz-area-page-meta">
    <span><?php echo esc_html(sprintf(__('Gebietsbereiche: %d', 'feuer-einsatzberichte'), count($areas))); ?></span>
    <?php if ($show_calls) : ?>
        <span><?php echo esc_html(sprintf(__('Einsatz-Zonen: %d', 'feuer-einsatzberichte'), count($clusters))); ?></span>
    <?php endif; ?>
    <?php if ('' !== $station_address) : ?>
        <span><?php echo esc_html(sprintf(__('Feuerwehrhaus: %s', 'feuer-einsatzberichte'), $station_address)); ?></span>
    <?php endif; ?>
</div>
