<?php
if (!defined('ABSPATH')) {
    exit;
}

$report = isset($report) && is_array($report) ? $report : [];
$image_loading = isset($image_loading) ? (string) $image_loading : 'lazy';
$image_fetchpriority = isset($image_fetchpriority) ? (string) $image_fetchpriority : 'auto';
$number_label = sprintf(
    '%s/%s',
    str_pad((string) $report['number'], 2, '0', STR_PAD_LEFT),
    (string) $report['year']
);
?>

<article class="card mb-4 shadow-sm overflow-hidden feu-einsatz-report-card feu-einsatz-report-card-modern">
    <div class="row g-0">
        <?php if (!empty($report['image_url'])) : ?>
            <div class="col-md-4">
                <a href="<?php echo esc_url($report['permalink']); ?>" class="d-block h-100 bg-light feu-einsatz-report-link" aria-label="<?php echo esc_attr($report['title']); ?>" data-feu-einsatz-open-report="1">
                    <?php
                    echo FEU_Einsatz_Template_Helpers::render_overview_card_image(
                        $report,
                        'feu-einsatz-overview-list-image' . (!empty($report['image_is_map']) ? ' feu-einsatz-overview-list-image-map' : ''),
                        [
                            'loading' => $image_loading,
                            'fetchpriority' => $image_fetchpriority,
                        ]
                    );
                    ?>
                </a>
            </div>
            <div class="col-md-8">
        <?php else : ?>
            <div class="col-12">
        <?php endif; ?>
            <div class="card-body p-4 d-flex flex-column">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                    <div class="d-flex flex-wrap gap-2">
                        <?php if (!empty($report['category']['name'])) : ?>
                            <span class="text-danger feu-einsatz-card-category"><?php echo esc_html($report['category']['name']); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($report['has_photos'])) : ?>
                            <span class="badge bg-dark">+FOTO</span>
                        <?php endif; ?>
                    </div>
                    <span class="badge bg-light text-dark border">#<?php echo esc_html($number_label); ?></span>
                </div>

                <h2 class="h4 card-title mb-2">
                    <a href="<?php echo esc_url($report['permalink']); ?>" class="link-dark text-decoration-none feu-einsatz-report-link" data-feu-einsatz-open-report="1">
                        <?php echo esc_html($report['title']); ?>
                    </a>
                </h2>

                <p class="card-text text-muted mb-2">
                    <?php echo esc_html($report['excerpt']); ?>
                </p>

                <ul class="list-unstyled small text-muted">
                    <li class="mb-2">
                        <span class="fw-semibold text-dark"><?php echo esc_html__('Datum', 'feuer-einsatzberichte'); ?> - </span>
                        <?php echo esc_html($report['event_date']); ?>
                    </li>
                    <?php /* if (!empty($report['event_time'])) : ?>
                        <li class="mb-2">
                            <span class="fw-semibold text-dark"><?php echo esc_html__('Alarmzeit', 'feuer-einsatzberichte'); ?> - </span>
                            <?php echo esc_html($report['event_time']); ?>
                        </li>
                    <?php endif; */ ?>
                    <?php /* if (!empty($report['category']['name'])) : ?>
                        <li class="mb-2">
                            <span class="fw-semibold text-dark"><?php echo esc_html__('Einsatzart', 'feuer-einsatzberichte'); ?> - </span>
                            <?php echo esc_html($report['category']['name']); ?>
                        </li>
                    <?php endif; */ ?>
                    <?php /* if (!empty($report['location'])) : ?>
                        <li>
                            <span class="fw-semibold text-dark"><?php echo esc_html__('Ort', 'feuer-einsatzberichte'); ?> - </span>
                            <?php echo esc_html($report['location']); ?>
                        </li>
                    <?php endif; */ ?>
                </ul>
                <?php /* ?>
                <div class="mt-auto">
                    <a href="<?php echo esc_url($report['permalink']); ?>" class="btn btn-outline-secondary btn-sm feu-einsatz-report-link" data-feu-einsatz-open-report="1">
                        <?php echo esc_html__('Weiterlesen', 'feuer-einsatzberichte'); ?>
                    </a>
                </div>
                <?php */ ?>
            </div>
        </div>
    </div>
</article>
