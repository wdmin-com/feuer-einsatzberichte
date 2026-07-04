<?php
if (!defined('ABSPATH')) {
    exit;
}

$context = isset($context) && is_array($context) ? $context : [];
$related = isset($context['related']) && is_array($context['related']) ? $context['related'] : [];
$related_items = isset($related['items']) && is_array($related['items']) ? $related['items'] : [];
$related_display = isset($related['display']) ? FEU_Einsatz_Template_Helpers::normalize_related_reports_display($related['display']) : 'disabled';
?>

<?php if ('disabled' !== $related_display && !empty($related_items)) : ?>
    <section class="feu-einsatz-related-reports">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h2 class="h3 mb-1"><?php echo esc_html__('Weitere Einsatzberichte', 'feuer-einsatzberichte'); ?></h2>
                <p class="text-muted mb-0"><?php echo esc_html__('Weitere Einsaetze aus unterschiedlichen Bereichen als schneller Blick in das Archiv.', 'feuer-einsatzberichte'); ?></p>
            </div>
        </div>

        <?php if ('cards' === $related_display) : ?>
            <div class="row g-4">
                <?php foreach ($related_items as $report) : ?>
                    <div class="col-12 col-md-6 col-xl-4 feu-einsatz-related-report">
                        <?php
                        echo FEU_Einsatz_Template_Helpers::render('templates/public/single/related-card.php', [
                            'report' => $report,
                        ]);
                        ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <div class="card feu-einsatz-related-list-card">
                <div class="list-group list-group-flush">
                    <?php foreach ($related_items as $report) : ?>
                        <a href="<?php echo esc_url($report['permalink']); ?>"
                           class="list-group-item list-group-item-action feu-einsatz-related-report-item feu-einsatz-report-link"
                           data-feu-einsatz-open-report="1">
                            <div class="row g-2 align-items-center">
                                <div class="col-12 col-lg-6">
                                    <div class="fw-semibold text-dark"><?php echo esc_html($report['title']); ?></div>
                                </div>
                                <div class="col-12 col-sm-6 col-lg-3 text-muted small">
                                    <?php echo esc_html($report['event_date']); ?>
                                    <?php if (!empty($report['event_time'])) : ?>
                                        <span class="d-inline-block ms-2"><?php echo esc_html($report['event_time']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="col-6 col-sm-3 col-lg-1 text-muted small">
                                    #<?php echo esc_html(str_pad((string) $report['number'], 2, '0', STR_PAD_LEFT)); ?>
                                </div>
                                <div class="col-6 col-sm-3 col-lg-2 text-muted small">
                                    <?php echo esc_html((string) $report['year']); ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
