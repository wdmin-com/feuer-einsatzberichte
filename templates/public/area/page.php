<?php
if (!defined('ABSPATH')) {
    exit;
}

$context = is_array($context ?? null) ? $context : [];
$area_entries = isset($context['area_entries']) && is_array($context['area_entries']) ? $context['area_entries'] : [];
$areas = isset($context['areas']) && is_array($context['areas']) ? $context['areas'] : [];
$station = isset($context['station']) && is_array($context['station']) ? $context['station'] : [];
$station_display = isset($context['station_display']) && is_array($context['station_display']) ? $context['station_display'] : [];
$clusters = isset($context['clusters']) && is_array($context['clusters']) ? $context['clusters'] : [];
$show_calls = !empty($context['show_calls']);
$has_map_data = !empty($context['has_map_data']);
$map_id = 'feu-einsatz-area-map-' . wp_unique_id();
$map_config = [
    'areaEntries' => $area_entries,
    'areas' => $areas,
    'station' => $station,
    'clusters' => $clusters,
    'showCalls' => $show_calls,
];
?>

<div class="feu-einsatz-area-page">
    <div class="feu-einsatz-area-page-shell">
        <?php
        echo FEU_Einsatz_Template_Helpers::render('templates/public/area/partials/header.php', [
            'area_entries' => $area_entries,
            'station_display' => $station_display,
        ]);
        ?>

        <?php if ($has_map_data) : ?>
            <?php
            echo FEU_Einsatz_Template_Helpers::render('templates/public/area/partials/map.php', [
                'map_id' => $map_id,
                'map_config' => $map_config,
                'areas' => $areas,
                'station' => $station,
                'station_display' => $station_display,
                'clusters' => $clusters,
                'show_calls' => $show_calls,
            ]);
            ?>
        <?php else : ?>
            <?php echo FEU_Einsatz_Template_Helpers::render('templates/public/area/partials/empty.php'); ?>
        <?php endif; ?>
    </div>
</div>
