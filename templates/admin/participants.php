<?php
if (!defined('ABSPATH')) {
    exit;
}

$teilnehmer = $this->db->get_participants(['include_archived' => true]);
$funktionen = get_option('feu_einsatz_functions', []);
$can_delete_participants = is_multisite()
    ? is_super_admin()
    : current_user_can('manage_options') && current_user_can('delete_users') && current_user_can('activate_plugins');
$active_participants = 0;
$archived_participants = 0;
$participants_with_photo = 0;
$active_participant_rows = [];
$archived_participant_rows = [];

foreach ($teilnehmer as $participant_entry) {
    if (!empty($participant_entry->is_archived)) {
        $archived_participants++;
        $archived_participant_rows[] = $participant_entry;
    } else {
        $active_participants++;
        $active_participant_rows[] = $participant_entry;
    }

    if (!empty($participant_entry->primary_image_id)) {
        $participants_with_photo++;
    }
}

if (!function_exists('feu_einsatz_render_default_function_selects')) {
    function feu_einsatz_render_default_function_selects($all_functions, $selected = []) {
        $selected = is_array($selected) ? array_values($selected) : [];

        for ($slot = 0; $slot < 5; $slot++) {
            $selected_value = isset($selected[$slot]) ? $selected[$slot] : '';
            ?>
            <div class="feu-einsatz-default-function-slot">
                <label class="feu-einsatz-default-function-label">
                    <?php echo esc_html($slot + 1); ?>.
                </label>
                <select name="default_functions[]" class="regular-text">
                    <option value=""><?php esc_html_e('Keine Standardfunktion', 'feuer-einsatzberichte'); ?></option>
                    <?php foreach ($all_functions as $funktion) : ?>
                        <option value="<?php echo esc_attr($funktion); ?>" <?php selected($selected_value, $funktion); ?>>
                            <?php echo esc_html($funktion); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php
        }
    }
}
?>

<div class="wrap feu-einsatz-participants feu-admin-page">
    <div class="feu-admin-page-header">
        <div class="feu-admin-page-heading">
            <span class="feu-admin-page-eyebrow"><?php esc_html_e('Mannschaftsverwaltung', 'feuer-einsatzberichte'); ?></span>
            <h1 class="wp-heading-inline"><?php esc_html_e('Teilnehmer verwalten', 'feuer-einsatzberichte'); ?></h1>
            <p class="description feu-einsatz-participants-intro">
                <?php esc_html_e('Hier pflegen Sie die komplette Mannschaftsakte. Archivierte Teilnehmer werden auf der Website nicht mehr angezeigt, bleiben aber in Statistik und bestehenden Einsatzberichten erhalten.', 'feuer-einsatzberichte'); ?>
            </p>
        </div>
    </div>

    <div class="feu-admin-stat-grid feu-admin-stat-grid--compact">
        <article class="feu-admin-stat-card">
            <div class="feu-admin-stat-icon"><span class="ti ti-users-group"></span></div>
            <div class="feu-admin-stat-content">
                <span class="feu-admin-stat-label"><?php esc_html_e('Aktive Teilnehmer', 'feuer-einsatzberichte'); ?></span>
                <strong class="feu-admin-stat-value"><?php echo esc_html($active_participants); ?></strong>
            </div>
        </article>
        <article class="feu-admin-stat-card">
            <div class="feu-admin-stat-icon"><span class="ti ti-archive"></span></div>
            <div class="feu-admin-stat-content">
                <span class="feu-admin-stat-label"><?php esc_html_e('Archiviert', 'feuer-einsatzberichte'); ?></span>
                <strong class="feu-admin-stat-value"><?php echo esc_html($archived_participants); ?></strong>
            </div>
        </article>
        <article class="feu-admin-stat-card">
            <div class="feu-admin-stat-icon"><span class="ti ti-camera"></span></div>
            <div class="feu-admin-stat-content">
                <span class="feu-admin-stat-label"><?php esc_html_e('Mit Foto', 'feuer-einsatzberichte'); ?></span>
                <strong class="feu-admin-stat-value"><?php echo esc_html($participants_with_photo); ?></strong>
            </div>
        </article>
    </div>

    <div class="feu-einsatz-add-participant-trigger">
        <button type="button" id="feu-einsatz-open-add-modal" class="button button-primary">
            <span class="feu-einsatz-button-icon feu-einsatz-button-icon--plus" aria-hidden="true"></span>
            <?php esc_html_e('Neue Mannschaftsakte anlegen', 'feuer-einsatzberichte'); ?>
        </button>
    </div>

    <div class="feu-einsatz-participant-list">
        <div class="feu-admin-card-header">
            <div>
                <h2><?php esc_html_e('Vorhandene Teilnehmer', 'feuer-einsatzberichte'); ?></h2>
                <p class="description"><?php esc_html_e('Suche, Foto, Status und Standardfunktionen direkt im Ueberblick.', 'feuer-einsatzberichte'); ?></p>
            </div>
            <div class="feu-admin-toolbar-search">
                <span class="ti ti-search"></span>
                <input type="search" id="feu-einsatz-search-participant" placeholder="<?php esc_attr_e('Teilnehmer suchen...', 'feuer-einsatzberichte'); ?>" />
            </div>
        </div>

        <?php if (empty($teilnehmer)) : ?>
            <div class="feu-admin-empty-state">
                <span class="ti ti-users"></span>
                <strong><?php esc_html_e('Noch keine Teilnehmer vorhanden.', 'feuer-einsatzberichte'); ?></strong>
                <p><?php esc_html_e('Legen Sie die erste Mannschaftsakte direkt oben im Formular an.', 'feuer-einsatzberichte'); ?></p>
            </div>
        <?php else : ?>
            <div class="feu-admin-table-wrap">
                <table class="wp-list-table widefat fixed striped feu-einsatz-participant-table feu-admin-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Foto', 'feuer-einsatzberichte'); ?></th>
                            <th><?php esc_html_e('Name', 'feuer-einsatzberichte'); ?></th>
                            <th><?php esc_html_e('Status', 'feuer-einsatzberichte'); ?></th>
                            <th><?php esc_html_e('Standardfunktionen', 'feuer-einsatzberichte'); ?></th>
                            <th><?php esc_html_e('Aktionen', 'feuer-einsatzberichte'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($active_participant_rows as $person) : ?>
                            <?php
                            $gallery_ids = !empty($person->gallery_ids) && is_array($person->gallery_ids)
                                ? array_values(array_map('absint', $person->gallery_ids))
                                : [];
                            $primary_image_id = !empty($person->primary_image_id) ? (int) $person->primary_image_id : 0;
                            $primary_image_url = $primary_image_id > 0 ? wp_get_attachment_image_url($primary_image_id, 'medium') : '';
                            $initials = strtoupper(substr((string) $person->vorname, 0, 1) . substr((string) $person->nachname, 0, 1));
                            ?>
                            <tr class="<?php echo !empty($person->is_archived) ? 'is-archived' : ''; ?>">
                                <td class="feu-einsatz-participant-photo-cell" data-label="<?php echo esc_attr__('Foto', 'feuer-einsatzberichte'); ?>">
                                    <div class="feu-admin-participant-media">
                                        <?php if ($primary_image_id > 0) : ?>
                                            <?php echo wp_get_attachment_image($primary_image_id, [72, 72], false, ['class' => 'feu-einsatz-participant-thumb']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                        <?php else : ?>
                                            <span class="feu-einsatz-participant-thumb feu-einsatz-participant-thumb-placeholder">
                                                <?php echo esc_html($initials); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td data-label="<?php echo esc_attr__('Name', 'feuer-einsatzberichte'); ?>">
                                    <strong><?php echo esc_html(FEU_Einsatz_Template_Helpers::format_participant_name($person->vorname, $person->nachname)); ?></strong>
                                </td>
                                <td data-label="<?php echo esc_attr__('Status', 'feuer-einsatzberichte'); ?>">
                                    <span class="feu-einsatz-status-badge <?php echo !empty($person->is_archived) ? 'is-archived' : 'is-active'; ?>">
                                        <?php echo !empty($person->is_archived) ? esc_html__('Archiviert', 'feuer-einsatzberichte') : esc_html__('Aktiv', 'feuer-einsatzberichte'); ?>
                                    </span>
                                </td>
                                <td data-label="<?php echo esc_attr__('Standardfunktionen', 'feuer-einsatzberichte'); ?>">
                                    <?php if (!empty($person->default_functions)) : ?>
                                        <div class="feu-einsatz-default-function-tags">
                                            <?php foreach ($person->default_functions as $default_function) : ?>
                                                <span class="feu-einsatz-function-tag"><?php echo esc_html($default_function); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else : ?>
                                        <span class="description"><?php esc_html_e('Keine hinterlegt', 'feuer-einsatzberichte'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="<?php echo esc_attr__('Aktionen', 'feuer-einsatzberichte'); ?>" class="feu-einsatz-table-actions">
                                    <div class="feu-einsatz-action-stack feu-admin-inline-actions">
                                        <button
                                            class="button button-small feu-einsatz-edit-participant"
                                            data-id="<?php echo esc_attr($person->id); ?>"
                                            data-vorname="<?php echo esc_attr($person->vorname); ?>"
                                            data-nachname="<?php echo esc_attr($person->nachname); ?>"
                                            data-archived="<?php echo esc_attr((int) $person->is_archived); ?>"
                                            data-default-functions="<?php echo esc_attr(wp_json_encode($person->default_functions)); ?>"
                                            data-gallery-existing="<?php echo esc_attr(wp_json_encode($gallery_ids)); ?>"
                                            data-primary-image-url="<?php echo esc_attr((string) $primary_image_url); ?>"
                                            data-primary-image-id="<?php echo esc_attr($primary_image_id); ?>"
                                            data-photo-count="<?php echo esc_attr(count($gallery_ids)); ?>"
                                            data-initials="<?php echo esc_attr($initials); ?>">
                                            <?php esc_html_e('Bearbeiten', 'feuer-einsatzberichte'); ?>
                                        </button>
                                        <button
                                            class="button button-small feu-einsatz-toggle-archive"
                                            data-id="<?php echo esc_attr($person->id); ?>"
                                            data-archived="<?php echo esc_attr((int) $person->is_archived); ?>">
                                            <?php echo !empty($person->is_archived) ? esc_html__('Aktivieren', 'feuer-einsatzberichte') : esc_html__('Archivieren', 'feuer-einsatzberichte'); ?>
                                        </button>
                                        <?php if ($can_delete_participants) : ?>
                                            <button
                                                class="button button-small button-link-delete feu-einsatz-delete-participant"
                                                data-id="<?php echo esc_attr($person->id); ?>">
                                                <?php esc_html_e('Entfernen', 'feuer-einsatzberichte'); ?>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <?php if (!empty($archived_participant_rows)) : ?>
                        <tbody class="feu-einsatz-participant-table-archived">
                            <tr class="feu-einsatz-participant-separator">
                                <td colspan="5">
                                    <strong><?php esc_html_e('Archivierte Teilnehmer', 'feuer-einsatzberichte'); ?></strong>
                                    <span class="description"><?php esc_html_e('Nicht mehr aktiv auf der Website, aber weiter fuer Historie, Statistik und bestehende Berichte verfuegbar.', 'feuer-einsatzberichte'); ?></span>
                                </td>
                            </tr>
                            <?php foreach ($archived_participant_rows as $person) : ?>
                                <?php
                                $gallery_ids = !empty($person->gallery_ids) && is_array($person->gallery_ids)
                                    ? array_values(array_map('absint', $person->gallery_ids))
                                    : [];
                                $primary_image_id = !empty($person->primary_image_id) ? (int) $person->primary_image_id : 0;
                                $primary_image_url = $primary_image_id > 0 ? wp_get_attachment_image_url($primary_image_id, 'medium') : '';
                                $initials = strtoupper(substr((string) $person->vorname, 0, 1) . substr((string) $person->nachname, 0, 1));
                                ?>
                                <tr class="is-archived">
                                    <td class="feu-einsatz-participant-photo-cell" data-label="<?php echo esc_attr__('Foto', 'feuer-einsatzberichte'); ?>">
                                        <div class="feu-admin-participant-media">
                                            <?php if ($primary_image_id > 0) : ?>
                                                <?php echo wp_get_attachment_image($primary_image_id, [72, 72], false, ['class' => 'feu-einsatz-participant-thumb']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                            <?php else : ?>
                                                <span class="feu-einsatz-participant-thumb feu-einsatz-participant-thumb-placeholder">
                                                    <?php echo esc_html($initials); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td data-label="<?php echo esc_attr__('Name', 'feuer-einsatzberichte'); ?>">
                                        <strong><?php echo esc_html(FEU_Einsatz_Template_Helpers::format_participant_name($person->vorname, $person->nachname)); ?></strong>
                                    </td>
                                    <td data-label="<?php echo esc_attr__('Status', 'feuer-einsatzberichte'); ?>">
                                        <span class="feu-einsatz-status-badge is-archived">
                                            <?php esc_html_e('Archiviert', 'feuer-einsatzberichte'); ?>
                                        </span>
                                    </td>
                                    <td data-label="<?php echo esc_attr__('Standardfunktionen', 'feuer-einsatzberichte'); ?>">
                                        <?php if (!empty($person->default_functions)) : ?>
                                            <div class="feu-einsatz-default-function-tags">
                                                <?php foreach ($person->default_functions as $default_function) : ?>
                                                    <span class="feu-einsatz-function-tag"><?php echo esc_html($default_function); ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else : ?>
                                            <span class="description"><?php esc_html_e('Keine hinterlegt', 'feuer-einsatzberichte'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="<?php echo esc_attr__('Aktionen', 'feuer-einsatzberichte'); ?>" class="feu-einsatz-table-actions">
                                        <div class="feu-einsatz-action-stack feu-admin-inline-actions">
                                            <button
                                                class="button button-small feu-einsatz-edit-participant"
                                                data-id="<?php echo esc_attr($person->id); ?>"
                                                data-vorname="<?php echo esc_attr($person->vorname); ?>"
                                                data-nachname="<?php echo esc_attr($person->nachname); ?>"
                                                data-archived="<?php echo esc_attr((int) $person->is_archived); ?>"
                                                data-default-functions="<?php echo esc_attr(wp_json_encode($person->default_functions)); ?>"
                                                data-gallery-existing="<?php echo esc_attr(wp_json_encode($gallery_ids)); ?>"
                                                data-primary-image-url="<?php echo esc_attr((string) $primary_image_url); ?>"
                                                data-primary-image-id="<?php echo esc_attr($primary_image_id); ?>"
                                                data-photo-count="<?php echo esc_attr(count($gallery_ids)); ?>"
                                                data-initials="<?php echo esc_attr($initials); ?>">
                                                <?php esc_html_e('Bearbeiten', 'feuer-einsatzberichte'); ?>
                                            </button>
                                            <button
                                                class="button button-small feu-einsatz-toggle-archive"
                                                data-id="<?php echo esc_attr($person->id); ?>"
                                                data-archived="<?php echo esc_attr((int) $person->is_archived); ?>">
                                                <?php esc_html_e('Aktivieren', 'feuer-einsatzberichte'); ?>
                                            </button>
                                            <?php if ($can_delete_participants) : ?>
                                                <button
                                                    class="button button-small button-link-delete feu-einsatz-delete-participant"
                                                    data-id="<?php echo esc_attr($person->id); ?>">
                                                    <?php esc_html_e('Entfernen', 'feuer-einsatzberichte'); ?>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    <?php endif; ?>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    function parseJsonAttr(value, fallback) {
        if (!value) {
            return fallback;
        }

        try {
            return JSON.parse(value);
        } catch (error) {
            return fallback;
        }
    }

    function fillDefaultFunctions($scope, values) {
        var safeValues = Array.isArray(values) ? values : [];

        $scope.find('select[name="default_functions[]"]').each(function(index) {
            $(this).val(safeValues[index] || '');
        });
    }

    function bindParticipantForm(selector, idleText, busyText) {
        $(selector).on('submit', function(e) {
            e.preventDefault();

            var form = this;
            var formData = new FormData(form);
            var $submitButton = $(form).find('button[type="submit"]').first();

            formData.append('action', 'feu_einsatz_save_participant');
            formData.append('nonce', feu_einsatz_ajax.nonce);

            $submitButton.prop('disabled', true).text(busyText);

            $.ajax({
                url: feu_einsatz_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        location.reload();
                        return;
                    }

                    alert((response.data && response.data.message) ? response.data.message : '<?php echo esc_js(__('Fehler beim Speichern.', 'feuer-einsatzberichte')); ?>');
                    $submitButton.prop('disabled', false).text(idleText);
                },
                error: function(xhr) {
                    var message = '<?php echo esc_js(__('Verbindungsfehler. Bitte versuchen Sie es erneut.', 'feuer-einsatzberichte')); ?>';

                    if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        message = xhr.responseJSON.data.message;
                    }

                    alert(message);
                    $submitButton.prop('disabled', false).text(idleText);
                }
            });
        });
    }

    function renderParticipantPreview($preview, data) {
        if (!$preview.length) {
            return;
        }

        var imageUrl = data.imageUrl || '';
        var initials = data.initials || '--';
        var photoCount = Number(data.photoCount || 0);
        var name = data.name || '';
        var statusText = data.statusText || '';
        var avatarHtml = imageUrl
            ? '<img src="' + imageUrl + '" alt="" />'
            : '<span class="feu-einsatz-participant-thumb feu-einsatz-participant-thumb-placeholder">' + initials + '</span>';

        $preview.html(
            '<div class="feu-einsatz-participant-preview-card">' +
                '<div class="feu-einsatz-participant-preview-avatar">' + avatarHtml + '</div>' +
                '<div class="feu-einsatz-participant-preview-copy">' +
                    '<strong>' + name + '</strong>' +
                    '<span>' + (photoCount > 0
                        ? photoCount + ' <?php echo esc_js(__('Bild(er) gespeichert', 'feuer-einsatzberichte')); ?>'
                        : '<?php echo esc_js(__('Noch kein Foto hinterlegt', 'feuer-einsatzberichte')); ?>') + '</span>' +
                    (statusText ? '<em>' + statusText + '</em>' : '') +
                '</div>' +
            '</div>'
        );
    }

    function fillExistingGalleryInputs($container, galleryIds) {
        if (!$container.length) {
            return;
        }

        var safeIds = Array.isArray(galleryIds) ? galleryIds : [];
        var html = '';

        safeIds.forEach(function(id) {
            html += '<input type="hidden" name="gallery_existing[]" value="' + String(id).replace(/"/g, '&quot;') + '" />';
        });

        $container.html(html);
    }

    function resetParticipantPhotoState($preview, $galleryContainer, data) {
        fillExistingGalleryInputs($galleryContainer, []);
        renderParticipantPreview($preview, data || {
            imageUrl: '',
            initials: '--',
            photoCount: 0,
            name: '',
            statusText: ''
        });
    }

    /* 3-step add modal */
    (function() {
        var currentStep = 1;
        var totalSteps = 3;
        var $modal = $('#feu-einsatz-add-participant-modal');
        var $form  = $('#feu-einsatz-add-participant-form');

        function goToStep(step) {
            currentStep = step;
            $modal.find('.feu-einsatz-wizard-step').hide();
            $modal.find('.feu-einsatz-wizard-step[data-step="' + step + '"]').show();
            $modal.find('.feu-einsatz-wizard-indicator-item').removeClass('is-active is-done');
            $modal.find('.feu-einsatz-wizard-indicator-item').each(function() {
                var s = Number($(this).attr('data-step'));
                if (s < step)  { $(this).addClass('is-done'); }
                if (s === step){ $(this).addClass('is-active'); }
            });
        }

        function openModal() {
            $form[0].reset();
            goToStep(1);
            $modal.fadeIn(180);
            $modal.find('#add_vorname').focus();
        }

        function closeModal() {
            $modal.fadeOut(180);
        }

        $('#feu-einsatz-open-add-modal').on('click', openModal);

        $modal.on('click', '.feu-einsatz-wizard-next', function() {
            if (currentStep === 1) {
                var vorname  = $.trim($('#add_vorname').val());
                var nachname = $.trim($('#add_nachname').val());
                if (!vorname || !nachname) {
                    $('#add_vorname').focus();
                    return;
                }
            }
            if (currentStep < totalSteps) { goToStep(currentStep + 1); }
        });

        $modal.on('click', '.feu-einsatz-wizard-back', function() {
            if (currentStep > 1) { goToStep(currentStep - 1); }
        });

        $modal.on('click', '.feu-einsatz-add-modal-close', closeModal);

        $(window).on('click', function(e) {
            if ($(e.target).is('#feu-einsatz-add-participant-modal')) { closeModal(); }
        });

        $(document).on('keyup', function(e) {
            if (e.key === 'Escape' && $modal.is(':visible')) { closeModal(); }
        });

        bindParticipantForm(
            '#feu-einsatz-add-participant-form',
            '<?php echo esc_js(__('Speichern', 'feuer-einsatzberichte')); ?>',
            '<?php echo esc_js(__('Wird gespeichert...', 'feuer-einsatzberichte')); ?>'
        );
    })();

    bindParticipantForm(
        '#feu-einsatz-edit-participant-form',
        '<?php echo esc_js(__('Aenderungen speichern', 'feuer-einsatzberichte')); ?>',
        '<?php echo esc_js(__('Wird gespeichert...', 'feuer-einsatzberichte')); ?>'
    );

    $('.feu-einsatz-edit-participant').on('click', function() {
        var $button = $(this);
        var defaultFunctions = parseJsonAttr($button.attr('data-default-functions'), []);
        var galleryExisting = parseJsonAttr($button.attr('data-gallery-existing'), []);
        var isArchived = Number($button.attr('data-archived')) === 1;
        var fullName = $.trim(($button.attr('data-vorname') || '') + ' ' + ($button.attr('data-nachname') || ''));

        $('#edit_id').val($button.attr('data-id'));
        $('#edit_is_archived').val(isArchived ? '1' : '0');
        $('#edit_vorname').val($button.attr('data-vorname') || '');
        $('#edit_nachname').val($button.attr('data-nachname') || '');
        $('#edit_participant_gallery').val('');
        $('#feu-einsatz-edit-status-text').text(
            isArchived
                ? '<?php echo esc_js(__('Dieser Teilnehmer ist archiviert. Er bleibt in Statistik und alten Einsatzberichten sichtbar.', 'feuer-einsatzberichte')); ?>'
                : '<?php echo esc_js(__('Aktiver Teilnehmer', 'feuer-einsatzberichte')); ?>'
        );

        fillDefaultFunctions($('#feu-einsatz-edit-participant-form'), defaultFunctions);
        fillExistingGalleryInputs($('#feu-einsatz-edit-gallery-existing'), galleryExisting);
        renderParticipantPreview($('#feu-einsatz-edit-photo-preview'), {
            imageUrl: $button.attr('data-primary-image-url') || '',
            initials: $button.attr('data-initials') || '--',
            photoCount: Number($button.attr('data-photo-count') || 0),
            name: fullName,
            statusText: isArchived
                ? '<?php echo esc_js(__('Archivierter Teilnehmer', 'feuer-einsatzberichte')); ?>'
                : '<?php echo esc_js(__('Aktiver Teilnehmer', 'feuer-einsatzberichte')); ?>'
        });

        $('#feu-einsatz-edit-participant-modal').fadeIn(200);
    });

    $('#feu-einsatz-remove-photo').on('click', function() {
        var fullName = $.trim(($('#edit_vorname').val() || '') + ' ' + ($('#edit_nachname').val() || ''));
        var isArchived = $('#edit_is_archived').val() === '1';

        resetParticipantPhotoState(
            $('#feu-einsatz-edit-photo-preview'),
            $('#feu-einsatz-edit-gallery-existing'),
            {
                imageUrl: '',
                initials: fullName
                    ? fullName.split(/\s+/).map(function(part) { return part.charAt(0).toUpperCase(); }).join('').slice(0, 2)
                    : '--',
                photoCount: 0,
                name: fullName,
                statusText: isArchived
                    ? '<?php echo esc_js(__('Archivierter Teilnehmer', 'feuer-einsatzberichte')); ?>'
                    : '<?php echo esc_js(__('Aktiver Teilnehmer', 'feuer-einsatzberichte')); ?>'
            }
        );

        $('#edit_participant_gallery').val('');
    });

    $('.feu-einsatz-toggle-archive').on('click', function() {
        var $button = $(this);
        var id = Number($button.attr('data-id') || 0);
        var currentlyArchived = Number($button.attr('data-archived')) === 1;
        var nextArchived = currentlyArchived ? 0 : 1;
        var question = currentlyArchived
            ? '<?php echo esc_js(__('Teilnehmer wieder aktivieren?', 'feuer-einsatzberichte')); ?>'
            : '<?php echo esc_js(__('Teilnehmer archivieren? Er bleibt in Statistik und bestehenden Einsatzberichten erhalten.', 'feuer-einsatzberichte')); ?>';

        if (!id || !confirm(question)) {
            return;
        }

        $button.prop('disabled', true);

        $.ajax({
            url: feu_einsatz_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'feu_einsatz_toggle_participant_archive',
                nonce: feu_einsatz_ajax.nonce,
                id: id,
                archived: nextArchived
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                    return;
                }

                alert((response.data && response.data.message) ? response.data.message : '<?php echo esc_js(__('Status konnte nicht geaendert werden.', 'feuer-einsatzberichte')); ?>');
                $button.prop('disabled', false);
            },
            error: function(xhr) {
                var message = '<?php echo esc_js(__('Verbindungsfehler. Bitte versuchen Sie es erneut.', 'feuer-einsatzberichte')); ?>';

                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    message = xhr.responseJSON.data.message;
                }

                alert(message);
                $button.prop('disabled', false);
            }
        });
    });

    $('.feu-einsatz-delete-participant').on('click', function() {
        var $button = $(this);
        var id = Number($button.attr('data-id') || 0);

        if (!id) {
            return;
        }

        if (!confirm('<?php echo esc_js(__('Teilnehmer wirklich aus der Verwaltung entfernen? Die Statistik und bestehende Einsatzberichte bleiben erhalten.', 'feuer-einsatzberichte')); ?>')) {
            return;
        }

        $button.prop('disabled', true);

        $.ajax({
            url: feu_einsatz_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'feu_einsatz_delete_participant',
                nonce: feu_einsatz_ajax.nonce,
                id: id
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                    return;
                }

                alert((response.data && response.data.message) ? response.data.message : '<?php echo esc_js(__('Teilnehmer konnte nicht entfernt werden.', 'feuer-einsatzberichte')); ?>');
                $button.prop('disabled', false);
            },
            error: function(xhr) {
                var message = '<?php echo esc_js(__('Verbindungsfehler. Bitte versuchen Sie es erneut.', 'feuer-einsatzberichte')); ?>';

                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    message = xhr.responseJSON.data.message;
                }

                alert(message);
                $button.prop('disabled', false);
            }
        });
    });

    $('.feu-einsatz-modal-close').on('click', function() {
        $('#feu-einsatz-edit-participant-modal').fadeOut(200);
    });

    $(window).on('click', function(e) {
        if ($(e.target).is('#feu-einsatz-edit-participant-modal')) {
            $('#feu-einsatz-edit-participant-modal').fadeOut(200);
        }
    });

    $(document).on('keyup', function(e) {
        if (e.key === 'Escape' && $('#feu-einsatz-edit-participant-modal').is(':visible')) {
            $('#feu-einsatz-edit-participant-modal').fadeOut(200);
        }
    });

    $('#feu-einsatz-search-participant').on('input', function() {
        var needle = $.trim($(this).val() || '').toLowerCase();

        $('.feu-einsatz-participant-table tbody tr').each(function() {
            var haystack = $(this).text().toLowerCase();
            $(this).toggle(needle === '' || haystack.indexOf(needle) !== -1);
        });
    });
});
</script>

<div id="feu-einsatz-add-participant-modal" style="display:none;">
    <div class="feu-einsatz-modal-content feu-einsatz-wizard-modal">
        <div class="feu-einsatz-modal-header">
            <div>
                <h3><?php esc_html_e('Neue Mannschaftsakte anlegen', 'feuer-einsatzberichte'); ?></h3>
            </div>
            <button type="button" class="button-link feu-einsatz-add-modal-close" aria-label="<?php echo esc_attr__('Schliessen', 'feuer-einsatzberichte'); ?>">
                <span class="feu-einsatz-close-icon" aria-hidden="true"></span>
            </button>
        </div>

        <div class="feu-einsatz-wizard-indicator">
            <div class="feu-einsatz-wizard-indicator-item is-active" data-step="1">
                <span class="feu-einsatz-wizard-indicator-num">1</span>
                <span><?php esc_html_e('Name', 'feuer-einsatzberichte'); ?></span>
            </div>
            <div class="feu-einsatz-wizard-indicator-sep"></div>
            <div class="feu-einsatz-wizard-indicator-item" data-step="2">
                <span class="feu-einsatz-wizard-indicator-num">2</span>
                <span><?php esc_html_e('Funktionen', 'feuer-einsatzberichte'); ?></span>
            </div>
            <div class="feu-einsatz-wizard-indicator-sep"></div>
            <div class="feu-einsatz-wizard-indicator-item" data-step="3">
                <span class="feu-einsatz-wizard-indicator-num">3</span>
                <span><?php esc_html_e('Foto', 'feuer-einsatzberichte'); ?></span>
            </div>
        </div>

        <form id="feu-einsatz-add-participant-form" method="post" enctype="multipart/form-data">

            <div class="feu-einsatz-wizard-step" data-step="1">
                <div class="feu-einsatz-form-stack">
                    <div class="feu-einsatz-field">
                        <label class="feu-einsatz-field-label" for="add_vorname"><?php esc_html_e('Vorname', 'feuer-einsatzberichte'); ?></label>
                        <input type="text" id="add_vorname" name="vorname" class="regular-text" autocomplete="given-name" />
                    </div>
                    <div class="feu-einsatz-field">
                        <label class="feu-einsatz-field-label" for="add_nachname"><?php esc_html_e('Nachname', 'feuer-einsatzberichte'); ?></label>
                        <input type="text" id="add_nachname" name="nachname" class="regular-text" autocomplete="family-name" />
                    </div>
                </div>
                <div class="feu-einsatz-wizard-actions">
                    <button type="button" class="button button-primary feu-einsatz-wizard-next"><?php esc_html_e('Weiter', 'feuer-einsatzberichte'); ?> &rarr;</button>
                </div>
            </div>

            <div class="feu-einsatz-wizard-step" data-step="2" style="display:none;">
                <div class="feu-einsatz-default-functions-grid">
                    <?php feu_einsatz_render_default_function_selects($funktionen); ?>
                </div>
                <p class="description feu-einsatz-field-hint" style="margin-top:8px;"><?php esc_html_e('Bis zu 5 bevorzugte Funktionen fuer Einsatzberichte und Statistik.', 'feuer-einsatzberichte'); ?></p>
                <div class="feu-einsatz-wizard-actions">
                    <button type="button" class="button feu-einsatz-wizard-back">&larr; <?php esc_html_e('Zurück', 'feuer-einsatzberichte'); ?></button>
                    <button type="button" class="button button-primary feu-einsatz-wizard-next"><?php esc_html_e('Weiter', 'feuer-einsatzberichte'); ?> &rarr;</button>
                </div>
            </div>

            <div class="feu-einsatz-wizard-step" data-step="3" style="display:none;">
                <div class="feu-einsatz-field">
                    <label class="feu-einsatz-field-label" for="add_participant_gallery"><?php esc_html_e('Profilfoto', 'feuer-einsatzberichte'); ?></label>
                    <input type="file" id="add_participant_gallery" name="participant_gallery[]" class="regular-text" accept="image/*" multiple />
                    <p class="description feu-einsatz-field-hint"><?php esc_html_e('Das erste Bild wird automatisch als Hauptfoto verwendet.', 'feuer-einsatzberichte'); ?></p>
                </div>
                <div class="feu-einsatz-wizard-actions">
                    <button type="button" class="button feu-einsatz-wizard-back">&larr; <?php esc_html_e('Zurück', 'feuer-einsatzberichte'); ?></button>
                    <button type="submit" class="button button-primary"><?php esc_html_e('Speichern', 'feuer-einsatzberichte'); ?></button>
                </div>
            </div>

        </form>
    </div>
</div>

<div id="feu-einsatz-edit-participant-modal">
    <div class="feu-einsatz-modal-content">
        <div class="feu-einsatz-modal-header">
            <div>
                <h3><?php esc_html_e('Teilnehmer bearbeiten', 'feuer-einsatzberichte'); ?></h3>
                <p class="description" id="feu-einsatz-edit-status-text"><?php esc_html_e('Aktiver Teilnehmer', 'feuer-einsatzberichte'); ?></p>
            </div>
            <button type="button" class="button-link feu-einsatz-modal-close" aria-label="<?php echo esc_attr__('Schliessen', 'feuer-einsatzberichte'); ?>">x</button>
        </div>

        <form id="feu-einsatz-edit-participant-form" method="post" enctype="multipart/form-data" class="feu-einsatz-participant-modern-form">
            <input type="hidden" id="edit_id" name="id" value="" />
            <input type="hidden" id="edit_is_archived" name="is_archived" value="0" />

            <div class="feu-einsatz-participant-panel-grid feu-einsatz-participant-panel-grid--triple">
                <section class="feu-einsatz-participant-panel">
                    <h3><?php esc_html_e('Person', 'feuer-einsatzberichte'); ?></h3>
                    <div class="feu-einsatz-form-stack">
                        <div class="feu-einsatz-field">
                            <label class="feu-einsatz-field-label" for="edit_vorname"><?php esc_html_e('Vorname', 'feuer-einsatzberichte'); ?></label>
                            <input type="text" id="edit_vorname" name="vorname" class="regular-text" required />
                        </div>
                        <div class="feu-einsatz-field">
                            <label class="feu-einsatz-field-label" for="edit_nachname"><?php esc_html_e('Nachname', 'feuer-einsatzberichte'); ?></label>
                            <input type="text" id="edit_nachname" name="nachname" class="regular-text" required />
                        </div>
                    </div>
                </section>

                <section class="feu-einsatz-participant-panel">
                    <h3><?php esc_html_e('Foto', 'feuer-einsatzberichte'); ?></h3>
                    <div class="feu-einsatz-form-stack">
                        <div id="feu-einsatz-edit-photo-preview"></div>
                        <div id="feu-einsatz-edit-gallery-existing"></div>
                        <div class="feu-einsatz-participant-photo-actions">
                            <button type="button" class="button button-secondary" id="feu-einsatz-remove-photo"><?php esc_html_e('Foto entfernen', 'feuer-einsatzberichte'); ?></button>
                        </div>
                        <div class="feu-einsatz-field">
                            <label class="feu-einsatz-field-label" for="edit_participant_gallery"><?php esc_html_e('Weitere Bilder hinzufuegen', 'feuer-einsatzberichte'); ?></label>
                            <input type="file" id="edit_participant_gallery" name="participant_gallery[]" class="regular-text" accept="image/*" multiple />
                            <p class="description feu-einsatz-field-hint">
                                <?php esc_html_e('Bestehende Bilder bleiben erhalten, wenn kein neues Bild hochgeladen wird.', 'feuer-einsatzberichte'); ?>
                            </p>
                        </div>
                    </div>
                </section>
                <section class="feu-einsatz-participant-panel">
                    <h3><?php esc_html_e('Standardfunktionen', 'feuer-einsatzberichte'); ?></h3>
                    <div class="feu-einsatz-default-functions-grid feu-einsatz-edit-default-functions">
                        <?php feu_einsatz_render_default_function_selects($funktionen); ?>
                    </div>
                </section>
            </div>

            <p class="submit feu-einsatz-form-actions">
                <button type="submit" class="button button-primary"><?php esc_html_e('Aenderungen speichern', 'feuer-einsatzberichte'); ?></button>
                <button type="button" class="button feu-einsatz-modal-close"><?php esc_html_e('Abbrechen', 'feuer-einsatzberichte'); ?></button>
            </p>
        </form>
    </div>
</div>
