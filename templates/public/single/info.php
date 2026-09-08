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

if (!empty($display['single_desaturate_organizations'])) {
    $organization_section_classes .= ' is-desaturated';
}
?>

<div class="<?php echo esc_attr('disabled' === $map_display_mode ? 'col-12' : 'col-12 col-lg-5'); ?> feu-einsatz-single-info-col d-flex">
    <div class="feu-einsatz-einsatz-info h-100">
        <h1 class="feu-einsatz-single-heading"><?php echo esc_html($report['title'] ?? ''); ?></h1>

        <div class="feu-einsatz-single-meta">
            <?php if (in_array('street', $info_fields, true) && !empty($report['street'])) : ?>
                <div class="feu-einsatz-single-meta-row"><b><?php echo esc_html__('Strasse:', 'feuer-einsatzberichte'); ?></b> <?php echo esc_html($report['street']); ?></div>
            <?php endif; ?>

            <?php if (in_array('location', $info_fields, true) && (!empty($report['postcode']) || !empty($report['city']))) : ?>
                <div class="feu-einsatz-single-meta-row"><b><?php echo esc_html__('Ort:', 'feuer-einsatzberichte'); ?></b> <?php echo esc_html(trim(($report['postcode'] ?? '') . ' ' . ($report['city'] ?? ''))); ?></div>
            <?php endif; ?>

            <?php if (in_array('date', $info_fields, true) && !empty($report['date_display'])) : ?>
                <div class="feu-einsatz-single-meta-row"><b><?php echo esc_html__('Datum:', 'feuer-einsatzberichte'); ?></b> <?php echo esc_html($report['date_display']); ?></div>
            <?php endif; ?>

            <?php if (in_array('time', $info_fields, true) && !empty($report['time'])) : ?>
                <div class="feu-einsatz-single-meta-row"><b><?php echo esc_html__('Uhrzeit:', 'feuer-einsatzberichte'); ?></b> <?php echo esc_html($report['time']); ?> Uhr</div>
            <?php endif; ?>

            <?php if (in_array('category', $info_fields, true) && !empty($report['category']['name'])) : ?>
                <div class="feu-einsatz-single-meta-row"><b><?php echo esc_html__('Einsatzart:', 'feuer-einsatzberichte'); ?></b> <?php echo esc_html($report['category']['name']); ?></div>
            <?php endif; ?>
        </div>

        <?php if (1 === (int) get_option('feu_einsatz_feature_organizations_enabled', 1) && in_array('organizations', $info_fields, true) && !empty($report['organizations'])) : ?>
            <div class="<?php echo esc_attr($organization_section_classes); ?>">
                <b><?php echo esc_html__('Kräfte vor Ort:', 'feuer-einsatzberichte'); ?></b><br>
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
            </div>
        <?php endif; ?>

        <?php /* if (!empty($report['participant_names'])) : ?>
            <div class="feu-einsatz-single-section">
                <b><?php echo esc_html(sprintf(__('Beteiligte Einsatzkraefte (%d)', 'feuer-einsatzberichte'), (int) ($report['participant_count'] ?? count($report['participant_names'])))); ?></b>
            </div>
        <?php endif; */ ?>
    </div>
</div>
