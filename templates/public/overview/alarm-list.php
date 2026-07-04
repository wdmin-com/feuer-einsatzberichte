<?php
if (!defined('ABSPATH')) {
    exit;
}

$context = isset($context) && is_array($context) ? $context : [];
$posts = isset($context['posts']) && is_array($context['posts']) ? $context['posts'] : [];
$settings = isset($context['alarm_list']) && is_array($context['alarm_list']) ? $context['alarm_list'] : [];
$visible_fields = isset($settings['visible_fields']) && is_array($settings['visible_fields'])
    ? $settings['visible_fields']
    : ['zeit', 'nummer', 'ort', 'fotos'];
$show_description = !isset($settings['show_description']) || !empty($settings['show_description']);
$eyebrow = trim((string) ($settings['eyebrow'] ?? ''));
$title = trim((string) ($settings['title'] ?? ''));
$all_text = trim((string) ($settings['all_text'] ?? ''));
$all_url = trim((string) ($settings['all_url'] ?? ''));
?>

<section class="feu-einsatz-alarm-list" aria-label="<?php echo esc_attr($title ?: __('Letzte Einsätze', 'feuer-einsatzberichte')); ?>">
    <header class="feu-einsatz-alarm-list__header">
        <div class="feu-einsatz-alarm-list__heading">
            <?php if ('' !== $eyebrow) : ?>
                <p class="feu-einsatz-alarm-list__eyebrow"><?php echo esc_html($eyebrow); ?></p>
            <?php endif; ?>

            <?php if ('' !== $title) : ?>
                <h2><?php echo esc_html($title); ?></h2>
            <?php endif; ?>
        </div>

        <?php if ('' !== $all_text && '' !== $all_url) : ?>
            <a class="feu-einsatz-alarm-list__all-link" href="<?php echo esc_url($all_url); ?>">
                <span><?php echo esc_html($all_text); ?></span>
                <span aria-hidden="true">→</span>
            </a>
        <?php endif; ?>
    </header>

    <?php if (!empty($context['warning'])) : ?>
        <div class="feu-einsatz-alarm-list__notice"><?php echo esc_html($context['warning']); ?></div>
    <?php elseif (empty($posts)) : ?>
        <div class="feu-einsatz-alarm-list__notice">
            <?php esc_html_e('Noch keine Einsatzberichte vorhanden.', 'feuer-einsatzberichte'); ?>
        </div>
    <?php else : ?>
        <div class="feu-einsatz-alarm-list__items">
            <?php foreach ($posts as $report) : ?>
                <?php
                $report_title = !empty($report['street']) ? (string) $report['street'] : (string) ($report['title'] ?? '');
                $number_label = sprintf(
                    '%s/%s',
                    str_pad((string) ($report['number'] ?? 0), 2, '0', STR_PAD_LEFT),
                    (string) ($report['year'] ?? '')
                );
                $meta_items = [];

                if (in_array('nummer', $visible_fields, true) && !empty($report['number'])) {
                    $meta_items[] = sprintf(__('Einsatz Nr. %s', 'feuer-einsatzberichte'), $number_label);
                }

                if (in_array('ort', $visible_fields, true) && !empty($report['location'])) {
                    $meta_items[] = (string) $report['location'];
                }

                if (in_array('fotos', $visible_fields, true) && !empty($report['has_photos'])) {
                    $meta_items[] = __('Mit Fotos', 'feuer-einsatzberichte');
                }
                ?>

                <article class="feu-einsatz-alarm-list__item">
                    <a
                        class="feu-einsatz-alarm-list__item-link feu-einsatz-report-link"
                        href="<?php echo esc_url((string) ($report['permalink'] ?? '')); ?>"
                        data-feu-einsatz-open-report="1"
                    >
                        <div class="feu-einsatz-alarm-list__date">
                            <strong><?php echo esc_html((string) ($report['event_date'] ?? '')); ?></strong>
                            <?php if (in_array('zeit', $visible_fields, true) && !empty($report['event_time'])) : ?>
                                <span><?php echo esc_html((string) $report['event_time']); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="feu-einsatz-alarm-list__category">
                            <?php echo esc_html((string) ($report['category']['name'] ?? '')); ?>
                        </div>

                        <div class="feu-einsatz-alarm-list__content">
                            <h3><?php echo esc_html($report_title); ?></h3>

                            <?php if ($show_description && !empty($report['excerpt'])) : ?>
                                <p><?php echo esc_html(wp_trim_words((string) $report['excerpt'], 18)); ?></p>
                            <?php endif; ?>

                            <?php if (!empty($meta_items)) : ?>
                                <div class="feu-einsatz-alarm-list__meta">
                                    <?php foreach ($meta_items as $meta_item) : ?>
                                        <span><?php echo esc_html($meta_item); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <span class="feu-einsatz-alarm-list__arrow" aria-hidden="true">↗</span>
                    </a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
