<?php
if (!defined('ABSPATH')) {
    exit;
}

$strasse = FEU_Einsatz_Template_Helpers::strip_house_number_from_street(get_post_meta($post->ID, '_feu_einsatz_strasse', true));
$plz = get_post_meta($post->ID, '_feu_einsatz_plz', true);
$stadt = get_post_meta($post->ID, '_feu_einsatz_stadt', true);
$vollstaendige_adresse = trim($strasse . ', ' . $plz . ' ' . $stadt);
?>


<div class="row gx-4 gx-lg-5 align-items-center">
    <div class="col-md-7">
        <!-- Karten-Container nur mit Straßenname -->
        <div class="feu-einsatz-map-container">
            <div id="feu-einsatz-einsatz-map" 
                style="height: <?php echo get_option('feu_einsatz_map_height', 400); ?>px; width: 100%;" 
                data-strasse="<?php echo esc_attr($strasse); ?>"
                data-plz="<?php echo esc_attr($plz); ?>"
                data-stadt="<?php echo esc_attr($stadt); ?>">
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <?php
    // Kategorien der aktuellen Beitrags abrufen
    $categories = get_the_category($post->ID);
    if (!empty($categories)):
    ?>
    <div class="small mb-1"><?php _e('Kategorie', 'feuer-einsatzberichte'); ?>
        
            <?php foreach ($categories as $category): ?>
                <a href="<?php echo get_category_link($category->term_id); ?>" class="feu-einsatz-kategorie-badge">
                    <?php echo esc_html($category->name); ?>
                </a>
            <?php endforeach; ?>
    </div>
    <?php endif; ?>
            <h1 class="fw-bolder pb-3"><?php echo get_the_title(); ?></h1><br/>
                <div class="fs-5 mb-5">
                    <span ><b><?php _e('Einsatzdetails', 'feuer-einsatzberichte'); ?></b></span><br/>
                    <?php if (!empty($strasse)): ?>
                        <span><?php _e('Straße:', 'feuer-einsatzberichte'); ?> <?php echo esc_html($strasse); ?></span><br>
                    <?php endif; ?>
                    <?php if (!empty($plz) || !empty($stadt)): ?>
                        <span><?php _e('Ort:', 'feuer-einsatzberichte'); ?> <?php echo trim($plz . ' ' . $stadt); ?></span><br>
                    <?php endif; ?>

                    <?php if (!empty($meta_data['datum'])): ?>
                        <span><?php _e('Datum:', 'feuer-einsatzberichte'); ?> <?php echo date_i18n('d. F Y', strtotime($meta_data['datum'])); ?></span><br>
                    <?php endif; ?>

                    <?php if (!empty($meta_data['uhrzeit'])): ?>
                    <span><?php _e('Uhrzeit:', 'feuer-einsatzberichte'); ?> <?php echo esc_html($meta_data['uhrzeit']); ?> <?php _e('Uhr', 'feuer-einsatzberichte'); ?></span><br>
                    <?php endif; ?>
                </div>
                    <p class="lead"></p>
            </div>
    </div>
</div>

