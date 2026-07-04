<?php
if (!defined('ABSPATH')) {
    exit;
}

$categories = isset($categories) && is_array($categories) ? $categories : [];
$participants = isset($participants) && is_array($participants) ? $participants : [];
$functions = isset($functions) && is_array($functions) ? $functions : [];
$organizations = isset($organizations) && is_array($organizations) ? $organizations : [];
$form_values = isset($form_values) && is_array($form_values) ? $form_values : [];
$flash_errors = isset($flash_errors) && is_array($flash_errors) ? $flash_errors : [];
$success_post_id = isset($success_post_id) ? absint($success_post_id) : 0;
$success_post_url = isset($success_post_url) ? (string) $success_post_url : '';
$success_edit_url = isset($success_edit_url) ? (string) $success_edit_url : '';
$can_publish = !empty($can_publish);

$selected_categories = array_map('absint', (array) ($form_values['categories'] ?? []));
$selected_participants = array_map('absint', (array) ($form_values['participant_ids'] ?? []));
$selected_organizations = array_map('absint', (array) ($form_values['organization_ids'] ?? []));
$participant_functions = isset($form_values['participant_functions']) && is_array($form_values['participant_functions'])
    ? $form_values['participant_functions']
    : [];
$default_function = FEU_Einsatz_Installer::get_default_participant_function();
$street_suggestions = FEU_Einsatz_Template_Helpers::get_street_suggestions();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title><?php esc_html_e('Schnelleingabe', 'feuer-einsatzberichte'); ?> - <?php bloginfo('name'); ?></title>
    <link rel="stylesheet" href="<?php echo esc_url(FEU_EINSATZ_PLUGIN_URL . 'assets/admin/css/schnelleingabe.css'); ?>?v=<?php echo esc_attr(FEU_EINSATZ_VERSION); ?>">
</head>
<body class="feu-se-body">

<div class="feu-se-page">
    <header class="feu-se-header">
        <a
            href="<?php echo esc_url(admin_url('admin.php?page=feuer-einsatzberichte')); ?>"
            class="feu-se-back"
            aria-label="<?php esc_attr_e('Zurueck zum Plugin-Dashboard', 'feuer-einsatzberichte'); ?>"
        >
            &lsaquo;
        </a>
        <span class="feu-se-header-title"><?php esc_html_e('Schnelleingabe', 'feuer-einsatzberichte'); ?></span>
        <a href="<?php echo esc_url(admin_url('index.php')); ?>" class="feu-se-header-link">
            <?php esc_html_e('WP-Dashboard', 'feuer-einsatzberichte'); ?>
        </a>
    </header>

    <?php if ($success_post_id > 0) : ?>
        <section class="feu-se-success" role="alert">
            <div class="feu-se-success-icon" aria-hidden="true">&#10003;</div>
            <div class="feu-se-success-copy">
                <h1 class="feu-se-success-title"><?php esc_html_e('Einsatz gespeichert', 'feuer-einsatzberichte'); ?></h1>
                <p class="feu-se-success-text"><?php esc_html_e('Der Einsatzbericht wurde angelegt und kann jetzt weiterbearbeitet oder direkt geoeffnet werden.', 'feuer-einsatzberichte'); ?></p>
            </div>
            <div class="feu-se-success-actions">
                <a href="<?php echo esc_url(admin_url('admin.php?page=feu-einsatz-schnelleingabe')); ?>" class="feu-se-btn feu-se-btn--primary">
                    <?php esc_html_e('Naechsten Einsatz erfassen', 'feuer-einsatzberichte'); ?>
                </a>
                <?php if ('' !== $success_edit_url) : ?>
                    <a href="<?php echo esc_url($success_edit_url); ?>" class="feu-se-btn feu-se-btn--secondary">
                        <?php esc_html_e('Vollstaendig bearbeiten', 'feuer-einsatzberichte'); ?>
                    </a>
                <?php endif; ?>
                <?php if ('' !== $success_post_url) : ?>
                    <a href="<?php echo esc_url($success_post_url); ?>" class="feu-se-btn feu-se-btn--ghost" target="_blank" rel="noopener noreferrer">
                        <?php esc_html_e('Beitrag oeffnen', 'feuer-einsatzberichte'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <form
        class="feu-se-form"
        id="feu-se-form"
        method="post"
        action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
        enctype="multipart/form-data"
        data-feu-schnelleingabe-form
        novalidate
    >
        <?php wp_nonce_field('feu_einsatz_schnelleingabe', 'feu_einsatz_schnelleingabe_nonce'); ?>
        <input type="hidden" name="action" value="feu_einsatz_schnelleingabe">
        <input type="hidden" name="feu_einsatz_origin" value="full">
        <input type="hidden" name="feu_einsatz_gallery_ids" value="">

        <div class="feu-se-errors" <?php echo empty($flash_errors) ? 'hidden' : ''; ?> data-feu-errors role="alert" aria-live="polite">
            <ul class="feu-se-errors-list" data-feu-errors-list>
                <?php foreach ($flash_errors as $flash_error) : ?>
                    <li><?php echo esc_html($flash_error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <section class="feu-se-block">
            <h2 class="feu-se-block-title"><?php esc_html_e('Wann', 'feuer-einsatzberichte'); ?></h2>
            <div class="feu-se-row feu-se-row--2col">
                <div class="feu-se-field">
                    <label class="feu-se-label" for="feu-se-date">
                        <?php esc_html_e('Datum', 'feuer-einsatzberichte'); ?>
                        <span class="feu-se-required">*</span>
                    </label>
                    <input
                        type="text"
                        id="feu-se-date"
                        name="feu_einsatz_datum"
                        class="feu-se-input"
                        value="<?php echo esc_attr($form_values['date'] ?? ''); ?>"
                        placeholder="TT.MM.JJJJ"
                        inputmode="numeric"
                        autocomplete="off"
                        required
                    >
                </div>
                <div class="feu-se-field">
                    <label class="feu-se-label" for="feu-se-time">
                        <?php esc_html_e('Uhrzeit', 'feuer-einsatzberichte'); ?>
                        <span class="feu-se-required">*</span>
                    </label>
                    <input
                        type="time"
                        id="feu-se-time"
                        name="feu_einsatz_uhrzeit"
                        class="feu-se-input"
                        value="<?php echo esc_attr($form_values['time'] ?? ''); ?>"
                        required
                    >
                </div>
            </div>
        </section>

        <section class="feu-se-block">
            <h2 class="feu-se-block-title">
                <?php esc_html_e('Einsatzart', 'feuer-einsatzberichte'); ?>
                <span class="feu-se-required">*</span>
            </h2>
            <?php if (empty($categories)) : ?>
                <p class="feu-se-hint"><?php esc_html_e('Es sind noch keine Einsatzkategorien konfiguriert.', 'feuer-einsatzberichte'); ?></p>
            <?php else : ?>
                <div class="feu-se-chip-group" role="group" aria-label="<?php esc_attr_e('Einsatzarten auswaehlen', 'feuer-einsatzberichte'); ?>">
                    <?php foreach ($categories as $category) : ?>
                        <label class="feu-se-chip">
                            <input
                                type="checkbox"
                                class="feu-se-chip-input"
                                name="post_category[]"
                                value="<?php echo esc_attr($category->term_id); ?>"
                                <?php checked(in_array((int) $category->term_id, $selected_categories, true)); ?>
                            >
                            <span class="feu-se-chip-label"><?php echo esc_html($category->name); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="feu-se-block">
            <h2 class="feu-se-block-title"><?php esc_html_e('Wo', 'feuer-einsatzberichte'); ?></h2>
            <div class="feu-se-field">
                <label class="feu-se-label" for="feu-se-street">
                    <?php esc_html_e('Strasse', 'feuer-einsatzberichte'); ?>
                    <span class="feu-se-required">*</span>
                </label>
                    <input
                        type="text"
                        id="feu-se-street"
                        name="feu_einsatz_strasse"
                        class="feu-se-input feu-se-input--large"
                        value="<?php echo esc_attr($form_values['street'] ?? ''); ?>"
                        placeholder="<?php esc_attr_e('Musterstrasse', 'feuer-einsatzberichte'); ?>"
                        autocomplete="street-address"
                        list="feu-einsatz-street-suggestions"
                        required
                    >
                    <?php if (!empty($street_suggestions)) : ?>
                        <datalist id="feu-einsatz-street-suggestions">
                            <?php foreach ($street_suggestions as $street_suggestion) : ?>
                                <option value="<?php echo esc_attr($street_suggestion); ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    <?php endif; ?>
                </div>
                <div class="feu-se-row feu-se-row--3col">
                <div class="feu-se-field">
                    <label class="feu-se-label" for="feu-se-house-number">
                        <?php esc_html_e('Hausnr.', 'feuer-einsatzberichte'); ?>
                        <span class="feu-se-optional-badge"><?php esc_html_e('optional', 'feuer-einsatzberichte'); ?></span>
                    </label>
                    <input
                        type="text"
                        id="feu-se-house-number"
                        name="feu_einsatz_hausnummer"
                        class="feu-se-input"
                        value="<?php echo esc_attr($form_values['house_number'] ?? ''); ?>"
                        placeholder="12a"
                        maxlength="10"
                        autocomplete="off"
                    >
                </div>
                <div class="feu-se-field">
                    <label class="feu-se-label" for="feu-se-postal-code">
                        <?php esc_html_e('PLZ', 'feuer-einsatzberichte'); ?>
                        <span class="feu-se-required">*</span>
                    </label>
                    <input
                        type="text"
                        id="feu-se-postal-code"
                        name="feu_einsatz_plz"
                        class="feu-se-input"
                        value="<?php echo esc_attr($form_values['postal_code'] ?? ''); ?>"
                        placeholder="22547"
                        maxlength="5"
                        inputmode="numeric"
                        autocomplete="postal-code"
                        required
                    >
                </div>
                <div class="feu-se-field">
                    <label class="feu-se-label" for="feu-se-city">
                        <?php esc_html_e('Stadt', 'feuer-einsatzberichte'); ?>
                        <span class="feu-se-required">*</span>
                    </label>
                    <input
                        type="text"
                        id="feu-se-city"
                        name="feu_einsatz_stadt"
                        class="feu-se-input"
                        value="<?php echo esc_attr($form_values['city'] ?? ''); ?>"
                        autocomplete="address-level2"
                        required
                    >
                </div>
            </div>
        </section>

        <?php if (!empty($participants)) : ?>
            <section class="feu-se-block">
                <h2 class="feu-se-block-title">
                    <?php esc_html_e('Teilnehmer', 'feuer-einsatzberichte'); ?>
                    <span class="feu-se-count" data-feu-participant-count></span>
                </h2>
                <div class="feu-se-search-wrap">
                    <input
                        type="search"
                        class="feu-se-search"
                        data-feu-participant-search
                        placeholder="<?php esc_attr_e('Name suchen...', 'feuer-einsatzberichte'); ?>"
                        autocomplete="off"
                    >
                </div>
                <div class="feu-se-participant-list" data-feu-participant-list role="list">
                    <?php foreach ($participants as $participant) : ?>
                        <?php
                        if (!empty($participant->is_archived) || !empty($participant->is_deleted)) {
                            continue;
                        }
                        $participant_id = (int) $participant->id;
                        $participant_name = trim((string) $participant->vorname . ' ' . (string) $participant->nachname);
                        $participant_default_functions = is_array($participant->default_functions) ? $participant->default_functions : [];
                        $participant_default_function = !empty($participant_default_functions)
                            ? reset($participant_default_functions)
                            : $default_function;
                        $is_selected = in_array($participant_id, $selected_participants, true);
                        ?>
                        <div class="feu-se-participant<?php echo $is_selected ? ' is-selected' : ''; ?>" data-name="<?php echo esc_attr(strtolower($participant_name)); ?>" role="listitem">
                            <label class="feu-se-participant-row">
                                <input
                                    type="checkbox"
                                    class="feu-se-participant-check"
                                    name="feu_einsatz_teilnehmer_ids[]"
                                    value="<?php echo esc_attr($participant_id); ?>"
                                    <?php checked($is_selected); ?>
                                >
                                <span class="feu-se-participant-name"><?php echo esc_html($participant_name); ?></span>
                            </label>
                            <div class="feu-se-participant-func" <?php echo $is_selected ? '' : 'hidden'; ?>>
                                <select
                                    name="feu_einsatz_teilnehmer_funktion[<?php echo esc_attr($participant_id); ?>]"
                                    class="feu-se-select feu-se-func-select"
                                    aria-label="<?php echo esc_attr(sprintf(__('Funktion fuer %s', 'feuer-einsatzberichte'), $participant_name)); ?>"
                                >
                                    <option value=""><?php esc_html_e('Keine Funktion', 'feuer-einsatzberichte'); ?></option>
                                    <?php foreach ($functions as $function_name) : ?>
                                        <option
                                            value="<?php echo esc_attr($function_name); ?>"
                                            <?php selected($participant_functions[$participant_id] ?? $participant_default_function, $function_name); ?>
                                        >
                                            <?php echo esc_html($function_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if (!empty($organizations)) : ?>
            <section class="feu-se-block">
                <h2 class="feu-se-block-title"><?php esc_html_e('Kraefte vor Ort', 'feuer-einsatzberichte'); ?></h2>
                <div class="feu-se-chip-group feu-se-chip-group--orgs" role="group" aria-label="<?php esc_attr_e('Kraefte vor Ort auswaehlen', 'feuer-einsatzberichte'); ?>">
                    <?php foreach ($organizations as $organization) : ?>
                        <?php if (!empty($organization->is_archived)) { continue; } ?>
                        <label class="feu-se-chip feu-se-chip--org" style="--org-color: <?php echo esc_attr($organization->color ?? '#0a4b78'); ?>">
                            <input
                                type="checkbox"
                                class="feu-se-chip-input"
                                name="feu_einsatz_organisationen[]"
                                value="<?php echo esc_attr($organization->id); ?>"
                                <?php checked(in_array((int) $organization->id, $selected_organizations, true)); ?>
                            >
                            <span class="feu-se-chip-label"><?php echo esc_html($organization->name); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="feu-se-block feu-se-block--optional">
            <h2 class="feu-se-block-title feu-se-block-title--optional">
                <?php esc_html_e('Fotos', 'feuer-einsatzberichte'); ?>
                <span class="feu-se-optional-badge"><?php esc_html_e('optional', 'feuer-einsatzberichte'); ?></span>
            </h2>
            <p class="feu-se-hint"><?php esc_html_e('Fotos werden direkt mit dem neuen Einsatzbericht gespeichert. Das erste Foto wird automatisch als Beitragsbild verwendet.', 'feuer-einsatzberichte'); ?></p>

            <input
                type="file"
                id="feu-se-photo-input"
                name="feu_einsatz_gallery_upload[]"
                accept="image/*"
                capture="environment"
                multiple
                class="feu-se-file-input"
                data-feu-photo-input
            >
            <label for="feu-se-photo-input" class="feu-se-btn feu-se-btn--secondary feu-se-btn--icon">
                <span aria-hidden="true">&#128247;</span>
                <?php esc_html_e('Fotos hinzufuegen', 'feuer-einsatzberichte'); ?>
            </label>

            <div class="feu-se-photo-preview" data-feu-photo-preview hidden>
                <div class="feu-se-photo-grid" data-feu-photo-grid></div>
                <p class="feu-se-photo-count" data-feu-photo-count></p>
            </div>
        </section>

        <section class="feu-se-block feu-se-block--optional">
            <h2 class="feu-se-block-title feu-se-block-title--optional">
                <?php esc_html_e('Titel', 'feuer-einsatzberichte'); ?>
                <span class="feu-se-optional-badge"><?php esc_html_e('optional', 'feuer-einsatzberichte'); ?></span>
            </h2>
            <p class="feu-se-hint"><?php esc_html_e('Wenn das Feld leer bleibt, erzeugt das Plugin den Titel automatisch aus Einsatzart und Strasse.', 'feuer-einsatzberichte'); ?></p>
            <input
                type="text"
                id="feu-se-title"
                name="post_title"
                class="feu-se-input feu-se-input--large"
                value="<?php echo esc_attr($form_values['title'] ?? ''); ?>"
                placeholder="<?php esc_attr_e('z. B. FEU - Kellerbrand', 'feuer-einsatzberichte'); ?>"
                autocomplete="off"
            >
        </section>

        <div class="feu-se-submit-area">
            <button
                type="submit"
                name="feu_einsatz_report_status"
                value="<?php echo $can_publish ? 'publish' : 'draft'; ?>"
                class="feu-se-btn feu-se-btn--primary feu-se-btn--full feu-se-btn--submit"
            >
                <span class="feu-se-btn-text" data-feu-submit-text>
                    <?php echo esc_html($can_publish ? __('Einsatz veroeffentlichen', 'feuer-einsatzberichte') : __('Einsatz speichern', 'feuer-einsatzberichte')); ?>
                </span>
                <span class="feu-se-btn-spinner" data-feu-submit-spinner hidden></span>
            </button>

            <?php if ($can_publish) : ?>
                <button
                    type="submit"
                    name="feu_einsatz_report_status"
                    value="draft"
                    class="feu-se-btn feu-se-btn--ghost feu-se-btn--full"
                >
                    <?php esc_html_e('Als Entwurf speichern', 'feuer-einsatzberichte'); ?>
                </button>
            <?php endif; ?>
        </div>
    </form>
</div>

<script src="<?php echo esc_url(FEU_EINSATZ_PLUGIN_URL . 'assets/admin/js/schnelleingabe.js'); ?>?v=<?php echo esc_attr(FEU_EINSATZ_VERSION); ?>"></script>
</body>
</html>
