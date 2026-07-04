<?php
if (!defined('ABSPATH')) {
    exit;
}

$report = isset($report) && is_array($report) ? $report : [];
$image_loading = isset($image_loading) ? (string) $image_loading : 'lazy';
$image_fetchpriority = isset($image_fetchpriority) ? (string) $image_fetchpriority : 'auto';
$magazine_excerpt = !empty($report['excerpt']) ? wp_trim_words((string) $report['excerpt'], 24) : '';
$number_label = sprintf(
    '%s/%s',
    str_pad((string) $report['number'], 2, '0', STR_PAD_LEFT),
    (string) $report['year']
);
?>

<article class="card mb-4 shadow-sm overflow-hidden feu-einsatz-report-card feu-einsatz-report-card-magazine">
    <div class="row g-0">
        <div class="<?php echo !empty($report['image_url']) ? 'col-lg-7 order-2 order-lg-1' : 'col-12'; ?>">
            <div class="card-body p-4 p-lg-5">
                <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                    <?php if (!empty($report['category']['name'])) : ?>
                        <span class="feu-einsatz-card-kicker"><?php echo esc_html($report['category']['name']); ?></span>
                    <?php endif; ?>
                    <span class="feu-einsatz-card-meta-pill"><?php echo esc_html($report['event_date']); ?></span>
                    <span class="feu-einsatz-card-number">#<?php echo esc_html($number_label); ?></span>
                </div>

                <h2 class="h3 mb-3">
                    <a href="<?php echo esc_url($report['permalink']); ?>" class="link-dark text-decoration-none feu-einsatz-report-link" data-feu-einsatz-open-report="1">
                        <?php echo esc_html($report['title']); ?>
                    </a>
                </h2>

                <?php if ('' !== $magazine_excerpt) : ?>
                    <p class="card-text text-muted mb-3"><?php echo esc_html($magazine_excerpt); ?></p>
                <?php endif; ?>

                <div class="feu-einsatz-report-card-magazine-footer">
                    <?php if (!empty($report['location'])) : ?>
                        <span><?php echo esc_html($report['location']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($report['has_photos'])) : ?>
                        <span>+FOTO</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (!empty($report['image_url'])) : ?>
            <div class="col-lg-5 order-1 order-lg-2">
                <a href="<?php echo esc_url($report['permalink']); ?>" class="d-block h-100 feu-einsatz-report-card-magazine-image-wrap feu-einsatz-report-link" aria-label="<?php echo esc_attr($report['title']); ?>" data-feu-einsatz-open-report="1">
                    <?php
                    echo FEU_Einsatz_Template_Helpers::render_overview_card_image(
                        $report,
                        'feu-einsatz-overview-list-image feu-einsatz-report-card-magazine-image' . (!empty($report['image_is_map']) ? ' feu-einsatz-overview-list-image-map' : ''),
                        [
                            'loading' => $image_loading,
                            'fetchpriority' => $image_fetchpriority,
                        ]
                    );
                    ?>
                </a>
            </div>
        <?php endif; ?>
    </div>
</article>
