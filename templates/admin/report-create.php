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
$can_publish_report = current_user_can('publish_posts');
if (!$can_publish_report) {
    $submit_primary_label = __('Zur Prüfung einreichen', 'feuer-einsatzberichte');
}
$post_title_value = isset($post->post_title) ? (string) $post->post_title : '';
$post_content_value = isset($post->post_content) ? (string) $post->post_content : '';
$comments_feature_enabled = 1 === (int) get_option('feu_einsatz_default_comments_enabled', 0);
$selected_category_ids = $is_edit_mode && !empty($post->ID) ? FEU_Einsatz_Report_Taxonomy::get_report_term_ids((int) $post->ID) : [];
$active_report_category_ids = isset($active_report_category_ids) ? array_map('absint', (array) $active_report_category_ids) : [];
$inactive_selected_category_ids = array_values(array_diff($selected_category_ids, $active_report_category_ids));
$primary_category_id = $is_edit_mode && !empty($post->ID)
    ? (int) get_post_meta($post->ID, FEU_Einsatz_Report_Taxonomy::enabled() ? FEU_Einsatz_Report_Taxonomy::PRIMARY_META : FEU_Einsatz_Report_Post_Type::PRIMARY_CATEGORY_META, true)
    : 0;
$report_categories = array_values(array_filter($report_categories, static function ($category) {
    return $category instanceof WP_Term;
}));
$report_category_taxonomy = FEU_Einsatz_Report_Taxonomy::enabled() ? FEU_Einsatz_Report_Taxonomy::TAXONOMY : 'category';
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
$has_fixed_public_url = $is_edit_mode && !empty($post->post_name);
$status_badge_class = 'is-archived';
$status_label = __('Entwurf', 'feuer-einsatzberichte');
$status_hint = __('Der Bericht ist aktuell als Entwurf gespeichert.', 'feuer-einsatzberichte');
if ('publish' === $current_post_status) {
    $status_badge_class = 'is-active';
    $status_label = __('Veröffentlicht', 'feuer-einsatzberichte');
    $status_hint = __('Der Bericht ist bereits öffentlich sichtbar.', 'feuer-einsatzberichte');
} elseif ('future' === $current_post_status) {
    $status_badge_class = 'is-scheduled';
    $status_label = __('Geplant', 'feuer-einsatzberichte');
    $status_hint = __('Für eine sofortige Veröffentlichung „Sofort“ wählen und anschließend speichern.', 'feuer-einsatzberichte');
    $submit_primary_label = __('Status aktualisieren', 'feuer-einsatzberichte');
} elseif ('pending' === $current_post_status) {
    $status_badge_class = 'is-scheduled';
    $status_label = __('Wartet auf Prüfung', 'feuer-einsatzberichte');
    $status_hint = __('Der Bericht ist gespeichert und wartet auf die Freigabe durch eine Person mit Veröffentlichungsrecht.', 'feuer-einsatzberichte');
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
            <span class="feu-einsatz-admin-hero-path"><?php echo esc_html($is_edit_mode ? __('Aenderungen sind erst nach dem Speichern sichtbar.', 'feuer-einsatzberichte') : __('Alle Angaben koennen vor dem Speichern kontrolliert werden.', 'feuer-einsatzberichte')); ?></span>
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

    <?php if ($is_edit_mode) : ?>
        <?php if (isset($_GET['duplicated_from']) && absint(wp_unslash($_GET['duplicated_from'])) > 0) : ?>
            <div class="notice notice-success"><p><?php esc_html_e('Kopie als neuer Entwurf erstellt. Bitte Einsatzdaten und Veröffentlichungszeitpunkt vor dem Speichern prüfen.', 'feuer-einsatzberichte'); ?></p></div>
        <?php endif; ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="feu-einsatz-report-duplicate-form">
            <input type="hidden" name="action" value="feu_einsatz_duplicate_report" />
            <input type="hidden" name="source_id" value="<?php echo esc_attr((int) $post->ID); ?>" />
            <?php wp_nonce_field('feu_einsatz_duplicate_report_' . (int) $post->ID, 'feu_einsatz_duplicate_report_nonce'); ?>
            <button type="submit" class="button button-secondary"><?php esc_html_e('Gespeicherten Bericht als neuen Entwurf kopieren', 'feuer-einsatzberichte'); ?></button>
        </form>
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
        <a class="feu-einsatz-report-section-link" href="#feu-einsatz-report-box-categories"><?php esc_html_e('Einsatzstichworte', 'feuer-einsatzberichte'); ?></a>
        <a class="feu-einsatz-report-section-link" href="#feu-einsatz-report-box-publish"><?php esc_html_e('Veröffentlichung', 'feuer-einsatzberichte'); ?></a>
    </nav>

    <div id="feu-einsatz-report-validation-notice" class="notice notice-error feu-einsatz-report-validation-notice" role="alert" hidden>
        <p><strong><?php _e('Pflichtfelder pruefen', 'feuer-einsatzberichte'); ?></strong></p>
        <ul class="feu-einsatz-report-validation-list"></ul>
    </div>

    <div class="notice notice-info feu-einsatz-draft-recovery" data-feu-draft-recovery hidden>
        <p><?php esc_html_e('Nicht gespeicherte Formulareingaben aus dieser Browser-Sitzung gefunden. Wiederherstellen?', 'feuer-einsatzberichte'); ?></p>
        <p><button type="button" class="button button-primary" data-feu-draft-restore><?php esc_html_e('Formular wiederherstellen', 'feuer-einsatzberichte'); ?></button> <button type="button" class="button" data-feu-draft-discard><?php esc_html_e('Verwerfen', 'feuer-einsatzberichte'); ?></button></p>
    </div>

    <div class="notice notice-warning feu-einsatz-draft-recovery-warning" data-feu-draft-recovery-warning role="alert" hidden>
        <p><strong><?php esc_html_e('Wiederherstellung unvollständig', 'feuer-einsatzberichte'); ?></strong> <?php esc_html_e('Bitte diese Angaben vor dem Speichern prüfen:', 'feuer-einsatzberichte'); ?></p>
        <ul data-feu-draft-recovery-warning-list></ul>
        <p><button type="button" class="button" data-feu-draft-recovery-warning-dismiss><?php esc_html_e('Hinweis schließen', 'feuer-einsatzberichte'); ?></button></p>
    </div>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="feu-einsatz-report-create-form" data-feu-report-id="<?php echo esc_attr((int) $post->ID); ?>" data-feu-user-id="<?php echo esc_attr(get_current_user_id()); ?>" data-feu-current-status="<?php echo esc_attr($current_post_status); ?>" data-feu-saved="<?php echo !empty($success_notice['message']) ? '1' : '0'; ?>" novalidate>
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
                                <div class="feu-einsatz-autofill-help">
                                    <p class="description feu-einsatz-field-hint"><?php esc_html_e('Der Titel wird aus Einsatzstichwort und Straße vorgeschlagen. Eigene Änderungen bleiben erhalten.', 'feuer-einsatzberichte'); ?></p>
                                    <button type="button" class="button button-link" data-feu-autofill-title-reset><?php esc_html_e('Titelvorschlag übernehmen', 'feuer-einsatzberichte'); ?></button>
                                </div>
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
                                <div class="feu-einsatz-autofill-help">
                                    <p class="description feu-einsatz-field-hint"><?php esc_html_e('Aus dem Einsatzstichwort, der Straße, Stadt, dem Stadtteil und Datum entsteht ein bearbeitbarer Textvorschlag.', 'feuer-einsatzberichte'); ?></p>
                                    <button type="button" class="button button-link" data-feu-autofill-content-reset><?php esc_html_e('Textvorschlag übernehmen', 'feuer-einsatzberichte'); ?></button>
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
                        <h2 class="hndle"><?php _e('Einsatzstichworte', 'feuer-einsatzberichte'); ?></h2>
                    </div>
                    <div class="inside">
                        <?php if (empty($report_categories)) : ?>
                            <p class="description"><?php _e('Keine Einsatzstichworte verfuegbar. Lege zuerst passende Kategorien in WordPress an oder waehle sie in den Plugin-Einstellungen aus.', 'feuer-einsatzberichte'); ?></p>
                        <?php else : ?>
                            <?php if (!empty($inactive_selected_category_ids)) : ?>
                                <p class="description"><?php esc_html_e('Einige bisherige Einsatzstichworte sind in den aktuellen Einstellungen deaktiviert. Sie bleiben für diesen Bericht erhalten, solange sie ausgewählt sind.', 'feuer-einsatzberichte'); ?></p>
                            <?php endif; ?>
                            <div class="feu-einsatz-report-category-groups" data-feu-category-list role="group" aria-label="<?php esc_attr_e('Einsatzstichworte', 'feuer-einsatzberichte'); ?>" aria-describedby="feu-einsatz-category-validation-status">
                                <div class="feu-einsatz-report-category-block">
                                    <h3><?php esc_html_e('Hauefig genutzt', 'feuer-einsatzberichte'); ?></h3>
                                    <div class="feu-einsatz-report-category-list feu-einsatz-report-category-list--popular">
                                        <?php foreach ($popular_report_categories as $category) : ?>
                                            <?php $depth = count(get_ancestors($category->term_id, $report_category_taxonomy)); ?>
                                            <label class="feu-einsatz-report-category-item" style="--feu-einsatz-category-indent: <?php echo esc_attr(12 + ($depth * 18)); ?>px;">
                                                <input type="checkbox"
                                                       name="post_category[]"
                                                       value="<?php echo esc_attr($category->term_id); ?>"
                                                       data-feu-category-name="<?php echo esc_attr($category->name); ?>"
                                                       data-feu-category-slug="<?php echo esc_attr($category->slug); ?>"
                                                       data-feu-category-description="<?php echo esc_attr(trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(wp_strip_all_tags((string) $category->description), ENT_QUOTES | ENT_HTML5, 'UTF-8')))); ?>"
                                                       data-feu-category-depth="<?php echo esc_attr($depth); ?>"
                                                       <?php checked(in_array((int) $category->term_id, $selected_category_ids, true)); ?> />
                                                <span><?php echo esc_html($category->name); ?><?php if (in_array((int) $category->term_id, $inactive_selected_category_ids, true)) : ?> <small><?php esc_html_e('(deaktiviert)', 'feuer-einsatzberichte'); ?></small><?php endif; ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <?php if (!empty($other_report_categories)) : ?>
                                    <div class="feu-einsatz-report-category-block">
                                        <h3><?php esc_html_e('Weitere Einsatzstichworte', 'feuer-einsatzberichte'); ?></h3>
                                        <div class="feu-einsatz-report-category-list feu-einsatz-report-category-list--scroll">
                                            <?php foreach ($other_report_categories as $category) : ?>
                                                <?php $depth = count(get_ancestors($category->term_id, $report_category_taxonomy)); ?>
                                                <label class="feu-einsatz-report-category-item" style="--feu-einsatz-category-indent: <?php echo esc_attr(12 + ($depth * 18)); ?>px;">
                                                    <input type="checkbox"
                                                           name="post_category[]"
                                                           value="<?php echo esc_attr($category->term_id); ?>"
                                                           data-feu-category-name="<?php echo esc_attr($category->name); ?>"
                                                           data-feu-category-slug="<?php echo esc_attr($category->slug); ?>"
                                                           data-feu-category-description="<?php echo esc_attr(trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(wp_strip_all_tags((string) $category->description), ENT_QUOTES | ENT_HTML5, 'UTF-8')))); ?>"
                                                           data-feu-category-depth="<?php echo esc_attr($depth); ?>"
                                                           <?php checked(in_array((int) $category->term_id, $selected_category_ids, true)); ?> />
                                                    <span><?php echo esc_html($category->name); ?><?php if (in_array((int) $category->term_id, $inactive_selected_category_ids, true)) : ?> <small><?php esc_html_e('(deaktiviert)', 'feuer-einsatzberichte'); ?></small><?php endif; ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <p id="feu-einsatz-category-validation-status" class="feu-einsatz-field-error feu-einsatz-category-error" data-feu-category-error aria-live="polite" hidden>
                                <?php _e('Bitte waehle mindestens ein Einsatzstichwort aus.', 'feuer-einsatzberichte'); ?>
                            </p>
                            <?php if (!$is_edit_mode || (FEU_Einsatz_Report_Post_Type::POST_TYPE === $post->post_type && !$has_fixed_public_url)) : ?>
                                <p class="feu-einsatz-field">
                                    <label for="feu-einsatz-primary-category"><?php esc_html_e('Hauptkategorie für die URL', 'feuer-einsatzberichte'); ?></label>
                                    <select id="feu-einsatz-primary-category" name="feu_einsatz_primary_category" aria-describedby="feu-einsatz-primary-category-help feu-einsatz-primary-category-error">
                                        <option value="0"><?php esc_html_e('Bei einem Stichwort automatisch', 'feuer-einsatzberichte'); ?></option>
                                        <?php foreach ($sorted_report_categories as $category) : ?>
                                            <?php if (!(FEU_Einsatz_Report_Post_Type::resolve_primary_category([(int) $category->term_id]) instanceof WP_Term)) { continue; } ?>
                                            <option value="<?php echo esc_attr($category->term_id); ?>" <?php selected($primary_category_id, (int) $category->term_id); ?>><?php echo esc_html($category->name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span id="feu-einsatz-primary-category-help" class="description"><?php esc_html_e('Bei mehreren Stichworten bitte eines auswählen. Der URL-Teil bleibt nach der Veröffentlichung fest.', 'feuer-einsatzberichte'); ?></span>
                                    <span id="feu-einsatz-primary-category-error" class="feu-einsatz-field-error" data-feu-primary-category-error aria-live="polite" hidden></span>
                                </p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="postbox feu-einsatz-form-section feu-einsatz-form-section--side" id="feu-einsatz-report-box-publish">
                    <div class="postbox-header">
                        <h2 class="hndle"><?php esc_html_e('Veröffentlichung', 'feuer-einsatzberichte'); ?></h2>
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
                        <?php if ($has_fixed_public_url) : ?>
                            <p class="description feu-einsatz-report-permalink"><?php esc_html_e('Die bestehende URL bleibt beim Bearbeiten erhalten:', 'feuer-einsatzberichte'); ?> <code><?php echo esc_html(get_permalink($post->ID)); ?></code></p>
                        <?php endif; ?>
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
                        <section class="feu-einsatz-prepublish-review" data-feu-prepublish-review data-feu-fixed-url="<?php echo esc_attr($has_fixed_public_url ? get_permalink($post->ID) : ''); ?>" hidden aria-labelledby="feu-einsatz-prepublish-heading">
                            <h3 id="feu-einsatz-prepublish-heading" tabindex="-1"><?php esc_html_e('Vor Veröffentlichung prüfen', 'feuer-einsatzberichte'); ?></h3>
                            <dl>
                                <div><dt><?php esc_html_e('Einsatzort', 'feuer-einsatzberichte'); ?></dt><dd data-feu-review-address></dd></div>
                                <div><dt><?php esc_html_e('Karte', 'feuer-einsatzberichte'); ?></dt><dd data-feu-review-map></dd></div>
                                <div><dt><?php esc_html_e('Öffentliche URL', 'feuer-einsatzberichte'); ?></dt><dd data-feu-review-url></dd></div>
                            </dl>
                            <h4><?php esc_html_e('Prüfliste', 'feuer-einsatzberichte'); ?></h4>
                            <ul class="feu-einsatz-review-checklist" data-feu-review-checklist aria-live="polite"></ul>
                            <p class="feu-einsatz-review-url-status" data-feu-review-url-status role="status" aria-live="polite"></p>
                            <?php if (!$has_fixed_public_url) : ?><p class="description"><?php esc_html_e('Die endgültige URL wird beim Speichern von WordPress festgelegt.', 'feuer-einsatzberichte'); ?></p><?php endif; ?>
                            <button type="submit" name="feu_einsatz_report_status" value="<?php echo $can_publish_report ? 'publish' : 'pending'; ?>" class="button button-primary" data-feu-review-confirm><?php echo esc_html($can_publish_report ? __('Veröffentlichung bestätigen', 'feuer-einsatzberichte') : __('Zur Prüfung einreichen', 'feuer-einsatzberichte')); ?></button>
                        </section>
                        <p class="feu-einsatz-report-status-actions">
                            <button type="submit" name="feu_einsatz_report_status" value="draft" class="button button-secondary">
                                <?php echo esc_html($submit_secondary_label); ?>
                            </button>
                            <?php if ($can_publish_report && !in_array($current_post_status, ['publish', 'future'], true)) : ?>
                                <button type="button" class="button button-secondary" data-feu-review-open data-feu-review-status="pending"><?php esc_html_e('Zur Prüfung einreichen', 'feuer-einsatzberichte'); ?></button>
                            <?php endif; ?>
                            <button type="button" value="publish" class="button button-primary" data-feu-review-open data-feu-review-status="<?php echo $can_publish_report ? 'publish' : 'pending'; ?>">
                                <?php echo esc_html($submit_primary_label); ?>
                            </button>
                        </p>
                    </div>
                </div>
            </aside>
        </div>
    </form>
</div>
