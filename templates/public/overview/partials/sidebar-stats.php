<?php
if (!defined('ABSPATH')) {
    exit;
}

$context = isset($context) && is_array($context) ? $context : [];
$selected_year = isset($context['selected_year']) ? (string) $context['selected_year'] : '';
$scoped_category_stats = isset($context['scoped_category_stats']) && is_array($context['scoped_category_stats']) ? $context['scoped_category_stats'] : [];
$scoped_total_posts = isset($context['scoped_total_posts']) ? (int) $context['scoped_total_posts'] : 0;
?>

<section class="card border-0 shadow-sm">
    <div class="card-header bg-light">
        <h2 class="h5 mb-0">
            <?php
            if ($selected_year) {
                printf(
                    esc_html__('Statistik %s', 'feuer-einsatzberichte'),
                    esc_html($selected_year)
                );
            } else {
                echo esc_html__('Statistik Alle Jahre', 'feuer-einsatzberichte');
            }
            ?>
        </h2>
    </div>
    <div class="card-body">
        <?php if (!empty($context['warning'])) : ?>
            <p class="text-muted small mb-0"><?php echo esc_html__('Die Statistik ist erst verfügbar, wenn die Kategorie Einsätze vorhanden ist.', 'feuer-einsatzberichte'); ?></p>
        <?php elseif (!empty($scoped_category_stats)) : ?>
            <?php $total_posts_in_scope = max(1, $scoped_total_posts); ?>
            <div class="feu-einsatz-overview-stats">
                <?php foreach ($scoped_category_stats as $stats) : ?>
                    <?php $percent = round(((int) $stats['count'] / $total_posts_in_scope) * 100, 1); ?>
                    <div class="feu-einsatz-overview-stat-item">
                        <div class="d-flex justify-content-between gap-3 mb-1">
                            <span class="feu-einsatz-overview-stat-name"><?php echo esc_html($stats['name']); ?></span>
                            <span class="text-muted"><?php echo esc_html($stats['count']); ?> (<?php echo esc_html($percent); ?>%)</span>
                        </div>
                        <div class="progress feu-einsatz-overview-progress">
                            <div class="progress-bar bg-secondary" style="width: <?php echo esc_attr($percent); ?>%;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <p class="text-muted small mb-0">
                <?php
                if ($selected_year) {
                    printf(
                        esc_html__('Für das Jahr %s sind noch keine Statistikdaten verfügbar.', 'feuer-einsatzberichte'),
                        esc_html($selected_year)
                    );
                } else {
                    echo esc_html__('Noch keine Statistikdaten verfügbar.', 'feuer-einsatzberichte');
                }
                ?>
            </p>
        <?php endif; ?>
    </div>
</section>
