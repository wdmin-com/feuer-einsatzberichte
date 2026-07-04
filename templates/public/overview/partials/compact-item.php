<?php
if (!defined('ABSPATH')) {
    exit;
}

$report = isset($report) && is_array($report) ? $report : [];
?>

<a href="<?php echo esc_url($report['permalink']); ?>" class="list-group-item list-group-item-action py-3 feu-einsatz-report-link" data-feu-einsatz-open-report="1">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
        <div class="me-3">
            <div class="fw-semibold text-dark"><?php echo esc_html($report['title']); ?></div>
            <?php if (!empty($report['excerpt'])) : ?>
                <div class="small text-muted mt-1"><?php echo esc_html($report['excerpt']); ?></div>
            <?php endif; ?>
        </div>
        <span class="badge bg-light text-dark border">
            <?php
            printf(
                '%s/%s',
                esc_html(str_pad((string) $report['number'], 2, '0', STR_PAD_LEFT)),
                esc_html((string) $report['year'])
            );
            ?>
        </span>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-2">
        <?php if (!empty($report['category']['name'])) : ?>
            <span class="badge bg-secondary"><?php echo esc_html($report['category']['name']); ?></span>
        <?php endif; ?>
        <?php if (!empty($report['has_photos'])) : ?>
            <span class="badge bg-dark">+FOTO</span>
        <?php endif; ?>
    </div>

    <div class="small text-muted d-flex flex-wrap gap-3">
        <span><strong><?php echo esc_html__('Datum', 'feuer-einsatzberichte'); ?>:</strong> <?php echo esc_html($report['event_date']); ?></span>
        <?php if (!empty($report['event_time'])) : ?>
            <span><strong><?php echo esc_html__('Alarmzeit', 'feuer-einsatzberichte'); ?>:</strong> <?php echo esc_html($report['event_time']); ?></span>
        <?php endif; ?>
        <?php if (!empty($report['location'])) : ?>
            <span><strong><?php echo esc_html__('Ort', 'feuer-einsatzberichte'); ?>:</strong> <?php echo esc_html($report['location']); ?></span>
        <?php endif; ?>
    </div>
</a>
