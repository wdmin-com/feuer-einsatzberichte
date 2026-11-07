<?php
if (!defined('ABSPATH')) {
    exit;
}

$context = isset($context) && is_array($context) ? $context : [];
$display = isset($context['display']) && is_array($context['display']) ? $context['display'] : [];
$show_sidebar = (!isset($display['overview_show_stats']) || !empty($display['overview_show_stats']))
    || (!isset($display['overview_show_year_filter']) || !empty($display['overview_show_year_filter']));
?>

<div class="container py-4 feuer-public-calls">
    <?php
    echo FEU_Einsatz_Template_Helpers::render('templates/public/overview/list.php', [
        'context' => $context,
    ]);
    ?>
    <?php if ($show_sidebar) : ?>
        <aside class="feuer-public-calls-sidebar">
            <?php echo FEU_Einsatz_Template_Manager::render('sidebar', ['context' => $context], 'templates/public/overview/sidebar.php'); ?>
        </aside>
    <?php endif; ?>
</div>
