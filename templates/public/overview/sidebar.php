<?php
if (!defined('ABSPATH')) {
    exit;
}

$context = isset($context) && is_array($context) ? $context : [];
$selected_year = isset($context['selected_year']) ? (string) $context['selected_year'] : '';
$all_years = isset($context['all_years']) && is_array($context['all_years']) ? $context['all_years'] : [];
$year_counts = isset($context['year_counts']) && is_array($context['year_counts']) ? $context['year_counts'] : [];
$display = isset($context['display']) && is_array($context['display']) ? $context['display'] : [];
$show_year_filter = !isset($display['overview_show_year_filter']) || !empty($display['overview_show_year_filter']);
$show_stats = !isset($display['overview_show_stats']) || !empty($display['overview_show_stats']);
?>

<div class="sticky-top feu-einsatz-overview-sidebar">
    <?php if ($show_year_filter) : ?>
        <?php
        echo FEU_Einsatz_Template_Helpers::render('templates/public/overview/partials/sidebar-year-filter.php', [
            'selected_year' => $selected_year,
            'all_years' => $all_years,
            'year_counts' => $year_counts,
        ]);
        ?>
    <?php endif; ?>

    <?php if ($show_stats) : ?>
        <?php
        echo FEU_Einsatz_Template_Helpers::render('templates/public/overview/partials/sidebar-stats.php', [
            'context' => $context,
        ]);
        ?>
    <?php endif; ?>
</div>
