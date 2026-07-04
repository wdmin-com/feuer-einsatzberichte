<?php
if (!defined('ABSPATH')) {
    exit;
}

$report = isset($report) && is_array($report) ? $report : [];
$compact_excerpt = !empty($report['excerpt']) ? wp_trim_words((string) $report['excerpt'], 16) : '';
$number_label = sprintf(
    '%s/%s',
    str_pad((string) $report['number'], 2, '0', STR_PAD_LEFT),
    (string) $report['year']
);
?>

<article class="card mb-4 shadow-sm feu-einsatz-report-card feu-einsatz-report-card-minimal">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <?php if (!empty($report['category']['name'])) : ?>
                    <span class="feu-einsatz-card-kicker"><?php echo esc_html($report['category']['name']); ?></span>
                <?php endif; ?>
                <?php if (!empty($report['has_photos'])) : ?>
                    <span class="badge text-bg-light border">+FOTO</span>
                <?php endif; ?>
            </div>
            <span class="feu-einsatz-card-number">#<?php echo esc_html($number_label); ?></span>
        </div>

        <h2 class="h4 mb-2">
            <a href="<?php echo esc_url($report['permalink']); ?>" class="link-dark text-decoration-none feu-einsatz-report-link" data-feu-einsatz-open-report="1">
                <?php echo esc_html($report['title']); ?>
            </a>
        </h2>

        <div class="feu-einsatz-report-card-minimal-meta mb-3">
            <span><strong><?php echo esc_html__('Datum', 'feuer-einsatzberichte'); ?>:</strong> <?php echo esc_html($report['event_date']); ?></span>
            <?php if (!empty($report['location'])) : ?>
                <span><strong><?php echo esc_html__('Ort', 'feuer-einsatzberichte'); ?>:</strong> <?php echo esc_html($report['location']); ?></span>
            <?php endif; ?>
        </div>

        <?php if ('' !== $compact_excerpt) : ?>
            <p class="card-text text-muted mb-0"><?php echo esc_html($compact_excerpt); ?></p>
        <?php endif; ?>
    </div>
</article>
