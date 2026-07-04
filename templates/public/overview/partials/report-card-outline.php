<?php
if (!defined('ABSPATH')) {
    exit;
}

$report = isset($report) && is_array($report) ? $report : [];
$image_loading = isset($image_loading) ? (string) $image_loading : 'lazy';
$image_fetchpriority = isset($image_fetchpriority) ? (string) $image_fetchpriority : 'auto';
$outline_excerpt = !empty($report['excerpt']) ? wp_trim_words((string) $report['excerpt'], 17) : '';
$number_label = sprintf(
    '%s/%s',
    str_pad((string) $report['number'], 2, '0', STR_PAD_LEFT),
    (string) $report['year']
);
?>

<article class="card mb-4 feu-einsatz-report-card feu-einsatz-report-card-outline">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <?php if (!empty($report['category']['name'])) : ?>
                    <span class="feu-einsatz-card-kicker"><?php echo esc_html($report['category']['name']); ?></span>
                <?php endif; ?>
                <span class="feu-einsatz-card-meta-pill"><?php echo esc_html($report['event_date']); ?></span>
            </div>
            <span class="feu-einsatz-card-number">#<?php echo esc_html($number_label); ?></span>
        </div>

        <div class="row g-3 align-items-start">
            <div class="<?php echo !empty($report['image_url']) ? 'col-md-8' : 'col-12'; ?>">
                <h2 class="h4 mb-2">
                    <a href="<?php echo esc_url($report['permalink']); ?>" class="link-dark text-decoration-none feu-einsatz-report-link" data-feu-einsatz-open-report="1">
                        <?php echo esc_html($report['title']); ?>
                    </a>
                </h2>

                <?php if ('' !== $outline_excerpt) : ?>
                    <p class="card-text text-muted mb-3"><?php echo esc_html($outline_excerpt); ?></p>
                <?php endif; ?>

                <?php if (!empty($report['location'])) : ?>
                    <div class="small text-muted"><?php echo esc_html($report['location']); ?></div>
                <?php endif; ?>
            </div>

            <?php if (!empty($report['image_url'])) : ?>
                <div class="col-md-4">
                    <a href="<?php echo esc_url($report['permalink']); ?>" class="d-block feu-einsatz-report-card-outline-thumb-wrap feu-einsatz-report-link" aria-label="<?php echo esc_attr($report['title']); ?>" data-feu-einsatz-open-report="1">
                        <?php
                        echo FEU_Einsatz_Template_Helpers::render_overview_card_image(
                            $report,
                            'feu-einsatz-report-card-outline-thumb' . (!empty($report['image_is_map']) ? ' feu-einsatz-overview-list-image-map' : ''),
                            [
                                'size' => 'medium',
                                'loading' => $image_loading,
                                'fetchpriority' => $image_fetchpriority,
                            ]
                        );
                        ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</article>
