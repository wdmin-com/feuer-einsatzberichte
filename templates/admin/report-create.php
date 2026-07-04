<?php
if (!defined('ABSPATH')) {
    exit;
}

$report_categories = isset($report_categories) && is_array($report_categories) ? $report_categories : [];
$is_edit_mode = !empty($is_edit_mode);
$success_notice = isset($success_notice) && is_array($success_notice) ? $success_notice : [];
$form_action_name = $is_edit_mode ? 'feu_einsatz_update_report' : 'feu_einsatz_create_report';
$form_nonce_action = $is_edit_mode ? 'feu_einsatz_update_report' : 'feu_einsatz_create_report';
$form_nonce_name = $is_edit_mode ? 'feu_einsatz_update_report_nonce' : 'feu_einsatz_create_report_nonce';
$page_title = $is_edit_mode ? __('Einsatzbericht bearbeiten', 'feuer-einsatzberichte') : __('Neuen Einsatzbericht erstellen', 'feuer-einsatzberichte');
$page_description = $is_edit_mode
    ? __('Diese Seite bearbeitet den Einsatzbericht komplett innerhalb des Plugins. Der normale WordPress-Editor bleibt nur noch ein Fallback.', 'feuer-einsatzberichte')
    : __('Diese Seite ist nur fuer Einsatzberichte gedacht. Normale News-Beitraege bleiben dadurch im regulaeren WordPress-Editor ohne einsatzspezifische Felder.', 'feuer-einsatzberichte');
$submit_secondary_label = $is_edit_mode ? __('Aenderungen als Entwurf speichern', 'feuer-einsatzberichte') : __('Als Entwurf speichern', 'feuer-einsatzberichte');
$submit_primary_label = $is_edit_mode ? __('Aenderungen veroeffentlichen', 'feuer-einsatzberichte') : __('Bericht veroeffentlichen', 'feuer-einsatzberichte');
$post_title_value = isset($post->post_title) ? (string) $post->post_title : '';
$post_content_value = isset($post->post_content) ? (string) $post->post_content : '';
$comments_feature_enabled = 1 === (int) get_option('feu_einsatz_default_comments_enabled', 0);
$selected_category_ids = $is_edit_mode && !empty($post->ID) ? array_map('absint', wp_get_post_categories($post->ID)) : [];
$report_categories = array_values(array_filter($report_categories, static function ($category) {
    return $category instanceof WP_Term;
}));
$sorted_report_categories = $report_categories;
usort($sorted_report_categories, static function ($left, $right) use ($selected_category_ids) {
    $left_selected = in_array((int) $left->term_id, $selected_category_ids, true) ? 1 : 0;
    $right_selected = in_array((int) $right->term_id, $selected_category_ids, true) ? 1 : 0;

    if ($left_selected !== $right_selected) {
        return $right_selected <=> $left_selected;
    }

    $left_count = isset($left->count) ? (int) $left->count : 0;
    $right_count = isset($right->count) ? (int) $right->count : 0;

    if ($left_count !== $right_count) {
        return $right_count <=> $left_count;
    }

    return strnatcasecmp((string) $left->name, (string) $right->name);
});
$popular_category_ids = [];
$popular_report_categories = [];
$other_report_categories = [];

foreach ($sorted_report_categories as $index => $category) {
    if ($index < 6 || in_array((int) $category->term_id, $selected_category_ids, true)) {
        $popular_report_categories[] = $category;
        $popular_category_ids[(int) $category->term_id] = true;
        continue;
    }

    $other_report_categories[] = $category;
}

if (empty($popular_report_categories)) {
    $popular_report_categories = array_slice($sorted_report_categories, 0, 6);
}

$current_post_status = isset($post->post_status) ? (string) $post->post_status : 'draft';
$status_badge_class = 'is-archived';
$status_label = __('Entwurf', 'feuer-einsatzberichte');
$status_hint = __('Der Bericht ist aktuell als Entwurf gespeichert.', 'feuer-einsatzberichte');
if ('publish' === $current_post_status) {
    $status_badge_class = 'is-active';
    $status_label = __('Veroeffentlicht', 'feuer-einsatzberichte');
    $status_hint = __('Der Bericht ist bereits oeffentlich sichtbar.', 'feuer-einsatzberichte');
} elseif ('future' === $current_post_status) {
    $status_badge_class = 'is-scheduled';
    $status_label = __('Geplant', 'feuer-einsatzberichte');
    $status_hint = __('Fuer eine sofortige Veroeffentlichung im Block "Verfuegbarkeit" die Option "Sofort" waehlen und anschliessend speichern.', 'feuer-einsatzberichte');
    $submit_primary_label = __('Status aktualisieren', 'feuer-einsatzberichte');
}
?>

<div class="wrap feu-einsatz-report-create-page">
    <div class="feu-einsatz-admin-hero">
        <div class="feu-einsatz-admin-hero-copy">
            <span class="feu-einsatz-admin-hero-kicker"><?php echo esc_html($is_edit_mode ? __('Bearbeiten im Plugin', 'feuer-einsatzberichte') : __('Neuer Einsatzbericht', 'feuer-einsatzberichte')); ?></span>
            <h1 class="wp-heading-inline"><?php echo esc_html($page_title); ?></h1>
            <p class="description"><?php echo esc_html($page_description); ?></p>
        </div>
        <div class="feu-einsatz-admin-hero-meta">
            <span class="feu-einsatz-admin-hero-badge"><?php echo esc_html($is_edit_mode ? __('Bearbeitungsmodus', 'feuer-einsatzberichte') : __('Erstellungsmodus', 'feuer-einsatzberichte')); ?></span>
            <span class="feu-einsatz-admin-hero-path"><code><?php echo esc_html($is_edit_mode ? 'admin.php?page=feu-einsatz-bericht-bearbeiten' : 'admin.php?page=feu-einsatz-neuer-bericht'); ?></code></span>
        </div>
    </div>

    <?php if (!empty($success_notice['message'])) : ?>
        <div class="notice notice-success is-dismissible feu-einsatz-report-success-notice">
            <p><?php echo esc_html($success_notice['message']); ?></p>
            <p class="feu-einsatz-report-success-actions">
                <?php if (!empty($success_notice['view_url'])) : ?>
                    <a class="button button-secondary" href="<?php echo esc_url($success_notice['view_url']); ?>" target="_blank" rel="noopener noreferrer">
                        <?php _e('Auf Website ansehen', 'feuer-einsatzberichte'); ?>
                    </a>
                <?php endif; ?>
                <?php if (!empty($success_notice['plugin_edit_url'])) : ?>
                    <a class="button button-primary" href="<?php echo esc_url($success_notice['plugin_edit_url']); ?>">
                        <?php _e('Im Plugin bearbeiten', 'feuer-einsatzberichte'); ?>
                    </a>
                <?php endif; ?>
                <?php if (!empty($success_notice['standard_edit_url'])) : ?>
                    <a class="button" href="<?php echo esc_url($success_notice['standard_edit_url']); ?>">
                        <?php _e('Im Standard-Editor bearbeiten', 'feuer-einsatzberichte'); ?>
                    </a>
                <?php endif; ?>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=feu-einsatz-neuer-bericht')); ?>">
                    <?php _e('Naechsten Einsatzbericht erstellen', 'feuer-einsatzberichte'); ?>
                </a>
            </p>
        </div>
    <?php endif; ?>

    <nav class="feu-einsatz-report-section-nav" aria-label="<?php echo esc_attr__('Abschnitte im Formular', 'feuer-einsatzberichte'); ?>">
        <a class="feu-einsatz-report-section-link" href="#feu-einsatz-report-box-details"><?php esc_html_e('Einsatzdetails', 'feuer-einsatzberichte'); ?></a>
        <a class="feu-einsatz-report-section-link" href="#feu-einsatz-report-section-basics"><?php esc_html_e('Grunddaten', 'feuer-einsatzberichte'); ?></a>
        <a class="feu-einsatz-report-section-link" href="#feu-einsatz-report-box-teilnehmer"><?php esc_html_e('Teilnehmer', 'feuer-einsatzberichte'); ?></a>
        <a class="feu-einsatz-report-section-link" href="#feu-einsatz-report-box-kraefte"><?php esc_html_e('Kraefte vor Ort', 'feuer-einsatzberichte'); ?></a>
        <a class="feu-einsatz-report-section-link" href="#feu-einsatz-report-box-map"><?php esc_html_e('Kartenbild', 'feuer-einsatzberichte'); ?></a>
        <a class="feu-einsatz-report-section-link" href="#feu-einsatz-report-box-photos"><?php esc_html_e('Fotos', 'feuer-einsatzberichte'); ?></a>
        <?php if ($comments_feature_enabled) : ?>
            <a class="feu-einsatz-report-section-link" href="#feu-einsatz-report-box-comments"><?php esc_html_e('Kommentare', 'feuer-einsatzberichte'); ?></a>
        <?php endif; ?>
        <a class="feu-einsatz-report-section-link" href="#feu-einsatz-report-box-categories"><?php esc_html_e('Kategorien', 'feuer-einsatzberichte'); ?></a>
        <a class="feu-einsatz-report-section-link" href="#feu-einsatz-report-box-publish"><?php esc_html_e('Veroeffentlichung', 'feuer-einsatzberichte'); ?></a>
    </nav>

    <div id="feu-einsatz-report-validation-notice" class="notice notice-error feu-einsatz-report-validation-notice" hidden>
        <p><strong><?php _e('Pflichtfelder pruefen', 'feuer-einsatzberichte'); ?></strong></p>
        <ul class="feu-einsatz-report-validation-list"></ul>
    </div>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="feu-einsatz-report-create-form" novalidate>
        <input type="hidden" name="action" value="<?php echo esc_attr($form_action_name); ?>" />
        <?php wp_nonce_field($form_nonce_action, $form_nonce_name); ?>
        <?php if ($is_edit_mode) : ?>
            <input type="hidden" name="post_id" value="<?php echo esc_attr($post->ID); ?>" />
        <?php endif; ?>

        <div class="feu-einsatz-report-create-grid">
            <div class="feu-einsatz-report-create-main">
                <div class="postbox feu-einsatz-form-section" id="feu-einsatz-report-box-details">
                    <div class="postbox-header">
                        <h2 class="hndle"><?php _e('Einsatzdetails', 'feuer-einsatzberichte'); ?></h2>
                    </div>
                    <div class="inside">
                        <?php
                        $feu_einsatz_show_basic_fields = true;
                        $feu_einsatz_show_availability_panel = false;
                        $feu_einsatz_show_media_panels = false;
                        $feu_einsatz_use_modern_form = true;
                        $feu_einsatz_skip_nonce_field = false;
                        $feu_einsatz_suppress_section_titles = true;
                        include FEU_EINSATZ_PLUGIN_DIR . 'templates/admin/meta-box-details.php';
                        unset(
                            $feu_einsatz_show_basic_fields,
                            $feu_einsatz_show_availability_panel,
                            $feu_einsatz_show_media_panels,
                            $feu_einsatz_use_modern_form,
                            $feu_einsatz_skip_nonce_field,
                            $feu_einsatz_suppress_section_titles
                        );
                        ?>
                    </div>
                </div>

                <div class="postbox feu-einsatz-form-section" id="feu-einsatz-report-section-basics">
                    <div class="postbox-header">
                        <h2 class="hndle"><?php _e('Grunddaten', 'feuer-einsatzberichte'); ?></h2>
                    </div>
                    <div class="inside">
                        <div class="feu-einsatz-form-stack">
                            <div class="feu-einsatz-field">
                                <label class="feu-einsatz-field-label" for="feu_einsatz_new_report_title"><?php _e('Titel', 'feuer-einsatzberichte'); ?></label>
                                <input type="text"
                                       id="feu_einsatz_new_report_title"
                                       name="post_title"
                                       class="widefat"
                                       value="<?php echo esc_attr($post_title_value); ?>"
                                       placeholder="<?php esc_attr_e('z.B. ALARM - Ueckerstrasse', 'feuer-einsatzberichte'); ?>" />
                                <p class="description feu-einsatz-field-hint">
                                    <?php _e('Wenn leer, erzeugt das Plugin beim Speichern automatisch einen Titel aus der ersten gewaehlten Kategorie und der Strasse, z.B. ALARM - Ueckerstrasse.', 'feuer-einsatzberichte'); ?>
                                </p>
                            </div>

                            <div class="feu-einsatz-field feu-einsatz-field--editor">
                                <label class="feu-einsatz-field-label" for="feu_einsatz_new_report_content"><?php _e('Berichtstext', 'feuer-einsatzberichte'); ?></label>
                                <div class="feu-einsatz-editor-shell">
                                    <?php
                                    wp_editor(
                                        $post_content_value,
                                        'feu_einsatz_new_report_content',
                                        [
                                            'textarea_name' => 'post_content',
                                            'textarea_rows' => 12,
                                            'media_buttons' => true,
                                            'teeny' => false,
                                        ]
                                    );
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="postbox feu-einsatz-form-section" id="feu-einsatz-report-box-teilnehmer">
                    <div class="postbox-header">
                        <h2 class="hndle"><?php _e('Teilnehmer', 'feuer-einsatzberichte'); ?></h2>
                    </div>
                    <div class="inside">
                        <?php
                        $feu_einsatz_show_teilnehmer = true;
                        $feu_einsatz_show_kraefte = false;
                        $feu_einsatz_use_modern_form = true;
                        $feu_einsatz_suppress_section_titles = true;
                        include FEU_EINSATZ_PLUGIN_DIR . 'templates/admin/meta-box-teilnehmer.php';
                        unset($feu_einsatz_show_teilnehmer, $feu_einsatz_show_kraefte, $feu_einsatz_use_modern_form, $feu_einsatz_suppress_section_titles);
                        ?>
                    </div>
                </div>

                <div class="postbox feu-einsatz-form-section" id="feu-einsatz-report-box-kraefte">
                    <div class="postbox-header">
                        <h2 class="hndle"><?php _e('Kraefte vor Ort', 'feuer-einsatzberichte'); ?></h2>
                    </div>
                    <div class="inside">
                        <?php
                        $feu_einsatz_show_teilnehmer = false;
                        $feu_einsatz_show_kraefte = true;
                        $feu_einsatz_use_modern_form = true;
                        $feu_einsatz_suppress_section_titles = true;
                        include FEU_EINSATZ_PLUGIN_DIR . 'templates/admin/meta-box-teilnehmer.php';
                        unset($feu_einsatz_show_teilnehmer, $feu_einsatz_show_kraefte, $feu_einsatz_use_modern_form, $feu_einsatz_suppress_section_titles);
                        ?>
                    </div>
                </div>

                <div class="postbox feu-einsatz-form-section" id="feu-einsatz-report-box-map">
                    <div class="postbox-header">
                        <h2 class="hndle"><?php _e('Kartenbild', 'feuer-einsatzberichte'); ?></h2>
                    </div>
                    <div class="inside">
                        <?php
                        $feu_einsatz_show_basic_fields = false;
                        $feu_einsatz_show_availability_panel = false;
                        $feu_einsatz_show_media_panels = false;
                        $feu_einsatz_show_kartenbild = true;
                        $feu_einsatz_show_fotos = false;
                        $feu_einsatz_show_kommentare = false;
                        $feu_einsatz_use_modern_form = true;
                        $feu_einsatz_skip_nonce_field = true;
                        $feu_einsatz_suppress_section_titles = true;
                        include FEU_EINSATZ_PLUGIN_DIR . 'templates/admin/meta-box-details.php';
                        unset(
                            $feu_einsatz_show_basic_fields,
                            $feu_einsatz_show_availability_panel,
                            $feu_einsatz_show_media_panels,
                            $feu_einsatz_show_kartenbild,
                            $feu_einsatz_show_fotos,
                            $feu_einsatz_show_kommentare,
                            $feu_einsatz_use_modern_form,
                            $feu_einsatz_skip_nonce_field,
                            $feu_einsatz_suppress_section_titles
                        );
                        ?>
                    </div>
                </div>

                <div class="postbox feu-einsatz-form-section" id="feu-einsatz-report-box-photos">
                    <div class="postbox-header">
                        <h2 class="hndle"><?php _e('Fotos', 'feuer-einsatzberichte'); ?></h2>
                    </div>
                    <div class="inside">
                        <?php
                        $feu_einsatz_show_basic_fields = false;
                        $feu_einsatz_show_availability_panel = false;
                        $feu_einsatz_show_media_panels = false;
                        $feu_einsatz_show_kartenbild = false;
                        $feu_einsatz_show_fotos = true;
                        $feu_einsatz_show_kommentare = false;
                        $feu_einsatz_use_modern_form = true;
                        $feu_einsatz_skip_nonce_field = true;
                        $feu_einsatz_suppress_section_titles = true;
                        include FEU_EINSATZ_PLUGIN_DIR . 'templates/admin/meta-box-details.php';
                        unset(
                            $feu_einsatz_show_basic_fields,
                            $feu_einsatz_show_availability_panel,
                            $feu_einsatz_show_media_panels,
                            $feu_einsatz_show_kartenbild,
                            $feu_einsatz_show_fotos,
                            $feu_einsatz_show_kommentare,
                            $feu_einsatz_use_modern_form,
                            $feu_einsatz_skip_nonce_field,
                            $feu_einsatz_suppress_section_titles
                        );
                        ?>
                    </div>
                </div>

                <?php if ($comments_feature_enabled) : ?>
                    <div class="postbox feu-einsatz-form-section" id="feu-einsatz-report-box-comments">
                        <div class="postbox-header">
                            <h2 class="hndle"><?php _e('Kommentare', 'feuer-einsatzberichte'); ?></h2>
                        </div>
                        <div class="inside">
                            <?php
                            $feu_einsatz_show_basic_fields = false;
                            $feu_einsatz_show_availability_panel = false;
                            $feu_einsatz_show_media_panels = false;
                            $feu_einsatz_show_kartenbild = false;
                            $feu_einsatz_show_fotos = false;
                            $feu_einsatz_show_kommentare = true;
                            $feu_einsatz_use_modern_form = true;
                            $feu_einsatz_skip_nonce_field = true;
                            $feu_einsatz_suppress_section_titles = true;
                            include FEU_EINSATZ_PLUGIN_DIR . 'templates/admin/meta-box-details.php';
                            unset(
                                $feu_einsatz_show_basic_fields,
                                $feu_einsatz_show_availability_panel,
                                $feu_einsatz_show_media_panels,
                                $feu_einsatz_show_kartenbild,
                                $feu_einsatz_show_fotos,
                                $feu_einsatz_show_kommentare,
                                $feu_einsatz_use_modern_form,
                                $feu_einsatz_skip_nonce_field,
                                $feu_einsatz_suppress_section_titles
                            );
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <aside class="feu-einsatz-report-create-side">
                <div class="postbox feu-einsatz-form-section feu-einsatz-form-section--side" id="feu-einsatz-report-box-categories">
                    <div class="postbox-header">
                        <h2 class="hndle"><?php _e('Kategorien', 'feuer-einsatzberichte'); ?></h2>
                    </div>
                    <div class="inside">
                        <?php if (empty($report_categories)) : ?>
                            <p class="description"><?php _e('Keine Kategorien verfuegbar. Lege zuerst Kategorien in WordPress an oder waehle sie in den Plugin-Einstellungen aus.', 'feuer-einsatzberichte'); ?></p>
                        <?php else : ?>
                            <div class="feu-einsatz-report-category-groups" data-feu-category-list>
                                <div class="feu-einsatz-report-category-block">
                                    <h3><?php esc_html_e('Hauefig genutzt', 'feuer-einsatzberichte'); ?></h3>
                                    <div class="feu-einsatz-report-category-list feu-einsatz-report-category-list--popular">
                                        <?php foreach ($popular_report_categories as $category) : ?>
                                            <?php $depth = count(get_ancestors($category->term_id, 'category')); ?>
                                            <label class="feu-einsatz-report-category-item" style="--feu-einsatz-category-indent: <?php echo esc_attr(12 + ($depth * 18)); ?>px;">
                                                <input type="checkbox"
                                                       name="post_category[]"
                                                       value="<?php echo esc_attr($category->term_id); ?>"
                                                       <?php checked(in_array((int) $category->term_id, $selected_category_ids, true)); ?> />
                                                <span><?php echo esc_html($category->name); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <?php if (!empty($other_report_categories)) : ?>
                                    <div class="feu-einsatz-report-category-block">
                                        <h3><?php esc_html_e('Weitere Kategorien', 'feuer-einsatzberichte'); ?></h3>
                                        <div class="feu-einsatz-report-category-list feu-einsatz-report-category-list--scroll">
                                            <?php foreach ($other_report_categories as $category) : ?>
                                                <?php $depth = count(get_ancestors($category->term_id, 'category')); ?>
                                                <label class="feu-einsatz-report-category-item" style="--feu-einsatz-category-indent: <?php echo esc_attr(12 + ($depth * 18)); ?>px;">
                                                    <input type="checkbox"
                                                           name="post_category[]"
                                                           value="<?php echo esc_attr($category->term_id); ?>"
                                                           <?php checked(in_array((int) $category->term_id, $selected_category_ids, true)); ?> />
                                                    <span><?php echo esc_html($category->name); ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <p class="feu-einsatz-field-error feu-einsatz-category-error" data-feu-category-error hidden>
                                <?php _e('Bitte waehle mindestens eine Kategorie aus.', 'feuer-einsatzberichte'); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="postbox feu-einsatz-form-section feu-einsatz-form-section--side" id="feu-einsatz-report-box-publish">
                    <div class="postbox-header">
                        <h2 class="hndle"><?php _e('Veroeffentlichung', 'feuer-einsatzberichte'); ?></h2>
                    </div>
                    <div class="inside">
                        <p class="feu-einsatz-report-current-status">
                            <span class="feu-einsatz-status-badge <?php echo esc_attr($status_badge_class); ?>">
                                <?php echo esc_html($status_label); ?>
                            </span>
                        </p>
                        <p class="description">
                            <?php echo esc_html($status_hint); ?>
                        </p>
                        <?php
                        $feu_einsatz_show_basic_fields = false;
                        $feu_einsatz_show_availability_panel = true;
                        $feu_einsatz_show_media_panels = false;
                        $feu_einsatz_use_modern_form = true;
                        $feu_einsatz_skip_nonce_field = true;
                        $feu_einsatz_suppress_section_titles = true;
                        include FEU_EINSATZ_PLUGIN_DIR . 'templates/admin/meta-box-details.php';
                        unset(
                            $feu_einsatz_show_basic_fields,
                            $feu_einsatz_show_availability_panel,
                            $feu_einsatz_show_media_panels,
                            $feu_einsatz_use_modern_form,
                            $feu_einsatz_skip_nonce_field,
                            $feu_einsatz_suppress_section_titles
                        );
                        ?>
                        <p class="feu-einsatz-report-status-actions">
                            <button type="submit" name="feu_einsatz_report_status" value="draft" class="button button-secondary">
                                <?php echo esc_html($submit_secondary_label); ?>
                            </button>
                            <button type="submit" name="feu_einsatz_report_status" value="publish" class="button button-primary">
                                <?php echo esc_html($submit_primary_label); ?>
                            </button>
                        </p>
                    </div>
                </div>
            </aside>
        </div>
    </form>
</div>

<script>
(function() {
    var form = document.querySelector('.feu-einsatz-report-create-form');

    if (!form) {
        return;
    }

    var notice = document.getElementById('feu-einsatz-report-validation-notice');
    var noticeList = notice ? notice.querySelector('.feu-einsatz-report-validation-list') : null;
    var detailsBox = document.getElementById('feu-einsatz-report-box-details');
    var categoriesBox = document.getElementById('feu-einsatz-report-box-categories');
    var categoryList = document.querySelector('[data-feu-category-list]');
    var categoryError = document.querySelector('[data-feu-category-error]');
    var fields = [
        {
            id: 'feu_einsatz_strasse',
            label: '<?php echo esc_js(__('Strasse', 'feuer-einsatzberichte')); ?>',
            validate: function(value) {
                return value.trim() !== ''
                    ? ''
                    : '<?php echo esc_js(__('Bitte gib eine Strasse an.', 'feuer-einsatzberichte')); ?>';
            }
        },
        {
            id: 'feu_einsatz_plz',
            label: '<?php echo esc_js(__('PLZ', 'feuer-einsatzberichte')); ?>',
            validate: function(value) {
                return /^\d{5}$/.test(value.trim())
                    ? ''
                    : '<?php echo esc_js(__('Bitte gib eine fuenfstellige PLZ an.', 'feuer-einsatzberichte')); ?>';
            }
        },
        {
            id: 'feu_einsatz_stadt',
            label: '<?php echo esc_js(__('Stadt', 'feuer-einsatzberichte')); ?>',
            validate: function(value) {
                return value.trim() !== ''
                    ? ''
                    : '<?php echo esc_js(__('Bitte gib eine Stadt an.', 'feuer-einsatzberichte')); ?>';
            }
        },
        {
            id: 'feu_einsatz_datum',
            label: '<?php echo esc_js(__('Datum', 'feuer-einsatzberichte')); ?>',
            validate: function(value) {
                var trimmed = value.trim();
                return /^(\d{2}\.\d{2}\.\d{4}|\d{4}-\d{2}-\d{2})$/.test(trimmed)
                    ? ''
                    : '<?php echo esc_js(__('Bitte gib ein Datum im Format TT.MM.JJJJ an.', 'feuer-einsatzberichte')); ?>';
            }
        },
        {
            id: 'feu_einsatz_uhrzeit',
            label: '<?php echo esc_js(__('Uhrzeit', 'feuer-einsatzberichte')); ?>',
            validate: function(value) {
                return /^([01]\d|2[0-3]):[0-5]\d$/.test(value.trim())
                    ? ''
                    : '<?php echo esc_js(__('Bitte gib eine Uhrzeit im Format HH:MM an.', 'feuer-einsatzberichte')); ?>';
            }
        }
    ];

    function getField(id) {
        return document.getElementById(id);
    }

    function ensureFieldErrorNode(field) {
        var existing = form.querySelector('[data-feu-field-error-for="' + field.id + '"]');

        if (existing) {
            return existing;
        }

        var node = document.createElement('p');
        node.className = 'feu-einsatz-field-error';
        node.setAttribute('data-feu-field-error-for', field.id);
        node.hidden = true;
        (field.closest('.feu-einsatz-form-row') || field.parentNode).appendChild(node);

        return node;
    }

    function setFieldError(field, message) {
        var errorNode = ensureFieldErrorNode(field);
        field.classList.add('feu-einsatz-field-invalid');
        field.setAttribute('aria-invalid', 'true');
        errorNode.textContent = message;
        errorNode.hidden = false;
    }

    function clearFieldError(field) {
        var errorNode = form.querySelector('[data-feu-field-error-for="' + field.id + '"]');
        field.classList.remove('feu-einsatz-field-invalid');
        field.removeAttribute('aria-invalid');

        if (errorNode) {
            errorNode.hidden = true;
            errorNode.textContent = '';
        }
    }

    function validateCategorySelection() {
        var checkboxes = form.querySelectorAll('input[name="post_category[]"]');
        var hasSelection = Array.prototype.some.call(checkboxes, function(checkbox) {
            return checkbox.checked;
        });

        if (categoryList) {
            categoryList.classList.toggle('feu-einsatz-field-invalid', !hasSelection);
        }

        if (categoriesBox) {
            categoriesBox.classList.toggle('feu-einsatz-section-invalid', !hasSelection);
        }

        if (categoryError) {
            categoryError.hidden = hasSelection;
        }

        return hasSelection
            ? ''
            : '<?php echo esc_js(__('Bitte waehle mindestens eine Kategorie aus.', 'feuer-einsatzberichte')); ?>';
    }

    function validateField(config) {
        var field = getField(config.id);

        if (!field) {
            return '';
        }

        var message = config.validate(field.value);

        if (message) {
            setFieldError(field, message);
        } else {
            clearFieldError(field);
        }

        return message;
    }

    function updateNotice(messages) {
        if (!notice || !noticeList) {
            return;
        }

        if (!messages.length) {
            notice.hidden = true;
            noticeList.innerHTML = '';
            return;
        }

        noticeList.innerHTML = messages.map(function(message) {
            return '<li>' + message + '</li>';
        }).join('');
        notice.hidden = false;
    }

    function validateForm() {
        var messages = [];
        var hasDetailErrors = false;

        fields.forEach(function(config) {
            var message = validateField(config);

            if (message) {
                hasDetailErrors = true;
                messages.push('<strong>' + config.label + ':</strong> ' + message);
            }
        });

        var categoryMessage = validateCategorySelection();
        if (categoryMessage) {
            messages.push('<strong><?php echo esc_js(__('Kategorien', 'feuer-einsatzberichte')); ?>:</strong> ' + categoryMessage);
        }

        if (detailsBox) {
            detailsBox.classList.toggle('feu-einsatz-section-invalid', hasDetailErrors);
        }

        updateNotice(messages);

        return messages.length === 0;
    }

    fields.forEach(function(config) {
        var field = getField(config.id);

        if (!field) {
            return;
        }

        ['input', 'change', 'blur'].forEach(function(eventName) {
            field.addEventListener(eventName, function() {
                if (notice && !notice.hidden) {
                    validateForm();
                    return;
                }

                validateField(config);

                if (detailsBox) {
                    var remainingInvalidFields = detailsBox.querySelectorAll('.feu-einsatz-field-invalid').length;
                    detailsBox.classList.toggle('feu-einsatz-section-invalid', remainingInvalidFields > 0);
                }
            });
        });
    });

    Array.prototype.forEach.call(form.querySelectorAll('input[name="post_category[]"]'), function(checkbox) {
        checkbox.addEventListener('change', function() {
            if (notice && !notice.hidden) {
                validateForm();
                return;
            }

            validateCategorySelection();
        });
    });

    form.addEventListener('submit', function(event) {
        if (validateForm()) {
            return;
        }

        event.preventDefault();

        if (notice) {
            notice.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
})();
</script>
