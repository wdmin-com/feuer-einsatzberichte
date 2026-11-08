<?php
if (!defined('ABSPATH')) {
    exit;
}

$context = isset($context) && is_array($context) ? $context : [];
$report = isset($context['report']) && is_array($context['report']) ? $context['report'] : [];
$display = isset($context['display']) && is_array($context['display']) ? $context['display'] : [];
$info_fields = isset($display['single_info_fields']) && is_array($display['single_info_fields'])
    ? $display['single_info_fields']
    : FEU_Einsatz_Template_Helpers::get_default_single_info_fields();
$map_display_mode = isset($display['single_map_display_mode'])
    ? FEU_Einsatz_Template_Helpers::normalize_single_map_display_mode($display['single_map_display_mode'])
    : 'live';
$organization_section_classes = 'feu-einsatz-single-section feu-einsatz-single-organizations';
$facts_only = !empty($facts_only);
$header_only = !empty($header_only);
$organizations_display = isset($display['single_organizations_display']) && 'text' === $display['single_organizations_display'] ? 'text' : 'chips';
$show_category = in_array('category', $info_fields, true) && !empty($report['category']['name']);
$show_date = in_array('date', $info_fields, true) && !empty($report['date_display']);
$show_time = in_array('time', $info_fields, true) && !empty($report['time']);

if ('text' === $organizations_display) {
    $organization_section_classes .= ' is-plain-text';
}

if (!empty($display['single_desaturate_organizations'])) {
    $organization_section_classes .= ' is-desaturated';
}
?>

<div class="<?php echo esc_attr('disabled' === $map_display_mode ? 'col-12' : 'col-12 col-lg-5'); ?> feu-einsatz-single-info-col d-flex">
    <div class="feu-einsatz-einsatz-info h-100">
        <?php if (!$facts_only) : ?>
            <h1 class="feuer-call-detail-title"><?php echo esc_html($report['title'] ?? ''); ?></h1>
            <?php if ($show_category || $show_date || $show_time) : ?>
                <div class="feuer-call-detail-meta">
                    <?php if ($show_category) : ?><span class="feuer-call-category"><?php echo esc_html($report['category']['name']); ?></span><?php endif; ?>
                    <?php if ($show_date || $show_time) : ?>
                        <time>
                            <?php if ($show_date) : ?><span><?php echo esc_html($report['date_display']); ?></span><?php endif; ?>
                            <?php if ($show_date && $show_time) : ?><span class="feuer-call-detail-meta-separator" aria-hidden="true">·</span><?php endif; ?>
                            <?php if ($show_time) : ?><span><?php echo esc_html($report['time']); ?> Uhr</span><?php endif; ?>
                        </time>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (!$header_only) : ?>
        <?php if ($facts_only) : ?><h2 class="feuer-call-facts-title"><?php echo esc_html__('Einsatzdaten', 'feuer-einsatzberichte'); ?></h2><?php endif; ?>
        <div class="feu-einsatz-single-meta feuer-call-facts">
            <?php if (in_array('category', $info_fields, true) && !empty($report['category']['name'])) : ?>
                <div class="feu-einsatz-single-meta-row"><b><?php echo esc_html__('Einsatzart', 'feuer-einsatzberichte'); ?></b><span><?php echo esc_html($report['category']['name']); ?></span></div>
            <?php endif; ?>

            <?php if (in_array('street', $info_fields, true) && !empty($report['street'])) : ?>
                <div class="feu-einsatz-single-meta-row"><b><?php echo esc_html__('Bereich', 'feuer-einsatzberichte'); ?></b><span><?php echo esc_html($report['street']); ?></span></div>
            <?php endif; ?>

            <?php
            $location_parts = [];
            $postcode_city = trim(implode(' ', array_filter([
                trim((string) ($report['postcode'] ?? '')),
                trim((string) ($report['city'] ?? '')),
            ])));
            if ('' !== $postcode_city) {
                $location_parts[] = $postcode_city;
            }
            $district = trim((string) ($report['district'] ?? ''));
            if ('' !== $district && !in_array($district, $location_parts, true)) {
                $location_parts[] = $district;
            }
            ?>
            <?php if (in_array('location', $info_fields, true) && !empty($location_parts)) : ?>
                <div class="feu-einsatz-single-meta-row"><b><?php echo esc_html__('Ort', 'feuer-einsatzberichte'); ?></b><span><?php echo esc_html(implode(', ', $location_parts)); ?></span></div>
            <?php endif; ?>

            <?php if (in_array('date', $info_fields, true) && !empty($report['date_display'])) : ?>
                <div class="feu-einsatz-single-meta-row"><b><?php echo esc_html__('Einsatzdatum', 'feuer-einsatzberichte'); ?></b><span><?php echo esc_html($report['date_display']); ?></span></div>
            <?php endif; ?>

            <?php if (in_array('time', $info_fields, true) && !empty($report['time'])) : ?>
                <div class="feu-einsatz-single-meta-row"><b><?php echo esc_html__('Uhrzeit:', 'feuer-einsatzberichte'); ?></b><span><?php echo esc_html($report['time']); ?> Uhr</span></div>
            <?php endif; ?>

        </div><?php endif; ?>

        <?php if (!$header_only && 1 === (int) get_option('feu_einsatz_feature_organizations_enabled', 1) && in_array('organizations', $info_fields, true) && !empty($report['organizations'])) : ?>
            <div class="<?php echo esc_attr($organization_section_classes); ?>">
                <b><?php echo esc_html__('Kräfte vor Ort:', 'feuer-einsatzberichte'); ?></b><br>
                <?php if ('text' === $organizations_display) : ?>
                    <span class="feuer-call-organizations-text"><?php echo esc_html(implode(', ', array_filter(array_map(static function ($organization) { return (string) ($organization['name'] ?? ''); }, (array) $report['organizations'])))); ?></span>
                <?php else : ?>
                    <?php foreach ($report['organizations'] as $organization) : ?>
                        <?php $organization_link = !empty($organization['post_link']) ? esc_url($organization['post_link']) : ''; ?>
                        <?php if ('' !== $organization_link) : ?>
                            <a href="<?php echo esc_url($organization_link); ?>" class="feu-einsatz-single-chip feu-einsatz-single-chip-organization feu-einsatz-single-chip-link" style="--feu-einsatz-org-color: <?php echo esc_attr($organization['color']); ?>;">
                                <?php echo esc_html($organization['name']); ?>
                            </a>
                        <?php else : ?>
                            <span class="feu-einsatz-single-chip feu-einsatz-single-chip-organization" style="--feu-einsatz-org-color: <?php echo esc_attr($organization['color']); ?>;">
                                <?php echo esc_html($organization['name']); ?>
                            </span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php /* if (!empty($report['participant_names'])) : ?>
            <div class="feu-einsatz-single-section">
                <b><?php echo esc_html(sprintf(__('Beteiligte Einsatzkraefte (%d)', 'feuer-einsatzberichte'), (int) ($report['participant_count'] ?? count($report['participant_names'])))); ?></b>
            </div>
        <?php endif; */ ?>
    </div>
</div>
