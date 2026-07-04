<?php
if (!defined('ABSPATH')) {
    exit;
}

$context = isset($context) && is_array($context) ? $context : [];
$display = isset($context['display']) && is_array($context['display']) ? $context['display'] : [];
$show_sidebar = (!isset($display['overview_show_stats']) || !empty($display['overview_show_stats']))
    || (!isset($display['overview_show_year_filter']) || !empty($display['overview_show_year_filter']));
?>

<div class="container py-4 feu-einsatz-overview-page">
    <div class="row g-4">
        <div class="<?php echo esc_attr($show_sidebar ? 'col-lg-8' : 'col-12'); ?>">
            <?php
            echo FEU_Einsatz_Template_Helpers::render('templates/public/overview/list.php', [
                'context' => $context,
            ]);
            ?>
        </div>

        <?php if ($show_sidebar) : ?>
            <aside class="col-lg-4">
                <?php
                echo FEU_Einsatz_Template_Helpers::render('templates/public/overview/sidebar.php', [
                    'context' => $context,
                ]);
                ?>
            </aside>
        <?php endif; ?>
    </div>
</div>
