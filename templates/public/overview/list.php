<?php
if (!defined('ABSPATH')) {
    exit;
}

$context = isset($context) && is_array($context) ? $context : [];
$selected_year = isset($context['selected_year']) ? (string) $context['selected_year'] : '';
$posts = isset($context['posts']) && is_array($context['posts']) ? $context['posts'] : [];
$display = isset($context['display']) && is_array($context['display']) ? $context['display'] : [];
$map_mode = isset($display['overview_map_mode']) ? FEU_Einsatz_Template_Helpers::normalize_overview_map_mode($display['overview_map_mode']) : 'all';
$paged = max(1, (int) ($context['paged'] ?? 1));
$has_featured_report = 1 === $paged;

echo FEU_Einsatz_Template_Helpers::render('templates/public/overview/partials/header.php', [
    'context' => $context,
]);
?>

<?php if (!empty($context['warning'])) : ?>
    <div class="alert alert-warning">
        <?php echo esc_html($context['warning']); ?>
    </div>
<?php elseif (!empty($posts)) : ?>
    <?php if ($has_featured_report) : ?>
        <section class="feuer-calls-featured" aria-label="<?php echo esc_attr__('Aktuellster Einsatz', 'feuer-einsatzberichte'); ?>">
            <?php echo FEU_Einsatz_Template_Helpers::render('templates/public/overview/partials/report-card-featured.php', [
                'report' => $posts[0], 'show_map' => 'none' !== $map_mode,
            ]); ?>
        </section>
    <?php endif; ?>
    <div class="feuer-calls-lower-layout">
        <div class="feuer-calls-grid" aria-label="<?php echo esc_attr__('Weitere Einsätze', 'feuer-einsatzberichte'); ?>">
            <?php foreach (array_slice($posts, $has_featured_report ? 1 : 0) as $report) : ?>
                <?php
                $show_map = 'all' === $map_mode;
                $image_report = $report;
                if (!$show_map && !empty($image_report['image_is_map'])) {
                    $image_report['image_url'] = '';
                }
                echo FEU_Einsatz_Template_Helpers::render('templates/public/overview/partials/report-card-grid.php', [
                    'report' => $image_report,
                    'image_loading' => 'lazy',
                ]);
                ?>
            <?php endforeach; ?>
        </div>
    </div>

    <?php
    echo FEU_Einsatz_Template_Helpers::render('templates/public/overview/partials/pagination.php', [
        'context' => $context,
    ]);
    ?>
<?php else : ?>
    <?php
    echo FEU_Einsatz_Template_Helpers::render('templates/public/overview/partials/empty.php', [
        'selected_year' => $selected_year,
    ]);
    ?>
<?php endif; ?>
