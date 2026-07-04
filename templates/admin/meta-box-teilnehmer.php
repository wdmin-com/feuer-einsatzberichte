<?php
if (!defined('ABSPATH')) {
    exit;
}

$show_teilnehmer_section = !isset($feu_einsatz_show_teilnehmer) || (bool) $feu_einsatz_show_teilnehmer;
$show_kraefte_section = !isset($feu_einsatz_show_kraefte) || (bool) $feu_einsatz_show_kraefte;
$suppress_section_titles = !empty($feu_einsatz_suppress_section_titles);

$teilnehmer = get_post_meta($post->ID, '_feu_einsatz_teilnehmer', true);
if (!is_array($teilnehmer)) {
    $teilnehmer = [];
}

$organisationen = get_post_meta($post->ID, '_feu_einsatz_organisationen', true);
if (!is_array($organisationen)) {
    $organisationen = [];
}

$alle_teilnehmer = $this->db->get_participants([
    'include_archived' => false,
    'include_deleted' => true,
    'order_by_usage' => true,
]);
$alle_teilnehmer_mit_archiv = $this->db->get_participants([
    'include_archived' => true,
    'include_deleted' => true,
    'order_by_usage' => true,
]);
$alle_organisationen = $this->db->get_organizations();
$alle_organisationen_mit_versteckten = $this->db->get_organizations([
    'include_hidden_defaults' => true,
    'include_archived' => true,
]);
$funktionen = get_option('feu_einsatz_functions', []);
$default_participant_function = FEU_Einsatz_Installer::get_default_participant_function();
$selected_participant_ids = array_values(array_filter(array_map('intval', array_column($teilnehmer, 'id'))));
$selected_organization_ids = array_values(array_filter(array_map('intval', $organisationen)));
$organization_lookup = [];
$picker_organization_ids = [];
$picker_organizations = [];

foreach ((array) $alle_organisationen_mit_versteckten as $organization) {
    $organization_lookup[(int) $organization->id] = $organization;
}

foreach ((array) $alle_organisationen as $organization) {
    $organization_id = (int) $organization->id;
    $picker_organizations[] = $organization;
    $picker_organization_ids[$organization_id] = true;
}

foreach ($selected_organization_ids as $organization_id) {
    if (!isset($picker_organization_ids[$organization_id]) && isset($organization_lookup[$organization_id])) {
        $picker_organizations[] = $organization_lookup[$organization_id];
        $picker_organization_ids[$organization_id] = true;
    }
}

if (!function_exists('feu_einsatz_find_participant_defaults')) {
    function feu_einsatz_find_participant_defaults($participants, $participant_id) {
        foreach ($participants as $participant) {
            if ((int) $participant->id === (int) $participant_id) {
                return !empty($participant->default_functions) && is_array($participant->default_functions)
                    ? $participant->default_functions
                    : [];
            }
        }

        return [];
    }
}

if (!function_exists('feu_einsatz_get_participant_initials')) {
    function feu_einsatz_get_participant_initials(string $first_name = '', string $last_name = ''): string {
        $first = trim($first_name);
        $last = trim($last_name);
        $initials = '';
        $substring = static function(string $value): string {
            if (function_exists('mb_substr')) {
                return (string) mb_substr($value, 0, 1);
            }

            return substr($value, 0, 1);
        };

        if ('' !== $first) {
            $initials .= strtoupper($substring($first));
        }

        if ('' !== $last) {
            $initials .= strtoupper($substring($last));
        }

        return '' !== $initials ? $initials : '--';
    }
}

if (!function_exists('feu_einsatz_get_participant_function_summary')) {
    function feu_einsatz_get_participant_function_summary($default_functions): string {
        $default_functions = array_values(array_unique(array_filter(array_map('trim', (array) $default_functions))));

        if (empty($default_functions)) {
            return __('Keine Standardfunktion', 'feuer-einsatzberichte');
        }

        $summary = implode(' / ', array_slice($default_functions, 0, 2));

        if (count($default_functions) > 2) {
            $summary .= ' +' . (count($default_functions) - 2);
        }

        return $summary;
    }
}

if (!function_exists('feu_einsatz_get_participant_avatar_markup')) {
    function feu_einsatz_get_participant_avatar_markup($participant, int $size = 44): string {
        $first_name = isset($participant->vorname) ? (string) $participant->vorname : '';
        $last_name = isset($participant->nachname) ? (string) $participant->nachname : '';
        $initials = feu_einsatz_get_participant_initials($first_name, $last_name);
        $image_id = !empty($participant->primary_image_id) ? (int) $participant->primary_image_id : 0;

        if ($image_id > 0) {
            $image = wp_get_attachment_image(
                $image_id,
                [$size, $size],
                false,
                [
                    'class' => 'feu-einsatz-person-avatar-image',
                    'loading' => 'lazy',
                    'decoding' => 'async',
                    'alt' => FEU_Einsatz_Template_Helpers::format_participant_name($first_name, $last_name),
                ]
            );

            if ($image) {
                return '<span class="feu-einsatz-person-avatar feu-einsatz-person-avatar--image">' . $image . '</span>';
            }
        }

        return '<span class="feu-einsatz-person-avatar feu-einsatz-person-avatar--placeholder">' . esc_html($initials) . '</span>';
    }
}

if (!function_exists('feu_einsatz_render_function_options')) {
    function feu_einsatz_render_function_options($all_functions, $default_functions, $selected_value = '', $fallback_value = '') {
        $selected_value = FEU_Einsatz_Installer::resolve_assignment_function(
            $selected_value,
            $default_functions,
            $all_functions
        );
        $default_functions = array_values(array_unique(array_filter((array) $default_functions)));
        $other_functions = array_values(array_filter($all_functions, static function ($function) use ($default_functions) {
            return !in_array($function, $default_functions, true);
        }));
        ?>
        <option value=""><?php _e('Funktion waehlen', 'feuer-einsatzberichte'); ?></option>
        <?php if (!empty($default_functions)) : ?>
            <optgroup label="<?php esc_attr_e('Standardfunktionen', 'feuer-einsatzberichte'); ?>">
                <?php foreach ($default_functions as $funktion) : ?>
                    <option value="<?php echo esc_attr($funktion); ?>" <?php selected($selected_value, $funktion); ?>>
                        <?php echo esc_html($funktion); ?>
                    </option>
                <?php endforeach; ?>
            </optgroup>
        <?php endif; ?>
        <?php if (!empty($other_functions)) : ?>
            <optgroup label="<?php esc_attr_e('Weitere Funktionen', 'feuer-einsatzberichte'); ?>">
                <?php foreach ($other_functions as $funktion) : ?>
                    <option value="<?php echo esc_attr($funktion); ?>" <?php selected($selected_value, $funktion); ?>>
                        <?php echo esc_html($funktion); ?>
                    </option>
                <?php endforeach; ?>
            </optgroup>
        <?php endif; ?>
        <option value="Keine Funktion" <?php selected($selected_value, 'Keine Funktion'); ?>>
            <?php _e('Keine Funktion', 'feuer-einsatzberichte'); ?>
        </option>
        <?php
    }
}

if (!function_exists('feu_einsatz_render_function_quick_actions')) {
    function feu_einsatz_render_function_quick_actions($default_functions, $selected_value = '') {
        $default_functions = array_values(array_unique(array_filter(array_map('trim', (array) $default_functions))));
        $selected_value = trim((string) $selected_value);

        if (empty($default_functions)) {
            return;
        }
        ?>
        <div class="feu-einsatz-function-quick-actions" data-feu-function-quick-actions>
            <?php foreach ($default_functions as $funktion) : ?>
                <button
                    type="button"
                    class="button feu-einsatz-function-quick-button<?php echo $selected_value === $funktion ? ' is-active' : ''; ?>"
                    data-feu-function-value="<?php echo esc_attr($funktion); ?>"
                >
                    <?php echo esc_html($funktion); ?>
                </button>
            <?php endforeach; ?>
        </div>
        <?php
    }
}
?>

<div class="feu-einsatz-meta-box-container feu-einsatz-meta-box-container--selectors">
    <?php if ($show_teilnehmer_section) : ?>
        <section class="feu-einsatz-meta-section feu-einsatz-meta-section--participants">
            <div class="feu-einsatz-meta-section-head">
                <div class="feu-einsatz-meta-section-copy">
                    <?php if (!$suppress_section_titles) : ?>
                        <h3 class="feu-einsatz-meta-section-title">
                            <span class="dashicons dashicons-groups"></span>
                            <?php _e('Teilnehmer', 'feuer-einsatzberichte'); ?>
                        </h3>
                    <?php endif; ?>
                    <p class="description">
                        <?php _e('Teilnehmer per Klick auswaehlen. Die Funktion im Einsatz erscheint sofort direkt daneben.', 'feuer-einsatzberichte'); ?>
                    </p>
                </div>
                <span class="feu-einsatz-badge" id="feu-einsatz-teilnehmer-count"><?php echo esc_html(count($teilnehmer)); ?></span>
            </div>

            <div id="feu-einsatz-participant-picker" class="feu-einsatz-picker-grid feu-einsatz-picker-grid-participants">
                <?php foreach ($alle_teilnehmer as $participant) : ?>
                    <?php
                    $participant_id = (int) $participant->id;
                    $is_selected = in_array($participant_id, $selected_participant_ids, true);
                    $is_archived = !empty($participant->is_archived);
                    $is_deleted = !empty($participant->is_deleted);
                    $is_locked = ($is_archived || $is_deleted) && !$is_selected;
                    $label = FEU_Einsatz_Template_Helpers::format_participant_name($participant->vorname, $participant->nachname);
                    $default_functions = feu_einsatz_find_participant_defaults($alle_teilnehmer_mit_archiv, $participant_id);
                    $function_summary = feu_einsatz_get_participant_function_summary($default_functions);
                    $status_note = $is_deleted
                        ? __('Entfernt', 'feuer-einsatzberichte')
                        : ($is_archived ? __('Archiviert', 'feuer-einsatzberichte') : '');
                    ?>
                    <button type="button"
                            class="button feu-einsatz-toggle-chip feu-einsatz-participant-chip-card<?php echo $is_selected ? ' is-active' : ''; ?><?php echo $is_locked ? ' is-disabled' : ''; ?>"
                            data-id="<?php echo esc_attr($participant_id); ?>"
                            data-archived="<?php echo esc_attr($is_archived ? 1 : 0); ?>"
                            data-deleted="<?php echo esc_attr($is_deleted ? 1 : 0); ?>"
                            aria-pressed="<?php echo $is_selected ? 'true' : 'false'; ?>"
                            <?php disabled($is_locked); ?>>
                        <span class="feu-einsatz-person-chip-avatar">
                            <?php echo feu_einsatz_get_participant_avatar_markup($participant, 44); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </span>
                        <span class="feu-einsatz-person-chip-body">
                            <strong class="feu-einsatz-person-chip-name"><?php echo esc_html($label); ?></strong>
                            <span class="feu-einsatz-person-chip-meta"><?php echo esc_html($function_summary); ?></span>
                        </span>
                        <?php if ('' !== $status_note) : ?>
                            <span class="feu-einsatz-person-chip-state"><?php echo esc_html($status_note); ?></span>
                        <?php endif; ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div id="feu-einsatz-teilnehmer-list" class="feu-einsatz-list-container feu-einsatz-list-container--participants">
                <?php if (empty($teilnehmer)) : ?>
                    <p class="feu-einsatz-empty-message"><?php _e('Noch keine Teilnehmer ausgewaehlt.', 'feuer-einsatzberichte'); ?></p>
                <?php else : ?>
                    <?php foreach (array_values($teilnehmer) as $index => $teilnehmer_data) : ?>
                        <?php
                        $participant_id = isset($teilnehmer_data['id']) ? (int) $teilnehmer_data['id'] : 0;
                        $participant = $this->db->get_participant($participant_id);

                        if (!$participant) {
                            continue;
                        }

                        $participant_name = FEU_Einsatz_Template_Helpers::format_participant_name($participant->vorname, $participant->nachname);
                        $default_functions = feu_einsatz_find_participant_defaults($alle_teilnehmer, $participant_id);
                        $selected_function = isset($teilnehmer_data['funktion']) ? trim((string) $teilnehmer_data['funktion']) : '';
                        $function_summary = feu_einsatz_get_participant_function_summary($default_functions);
                        ?>
                        <div class="feu-einsatz-list-item feu-einsatz-participant-row feu-einsatz-participant-row--compact" data-id="<?php echo esc_attr($participant_id); ?>">
                            <input type="hidden"
                                   class="feu-einsatz-participant-id-field"
                                   name="feu_einsatz_teilnehmer[<?php echo esc_attr($index); ?>][id]"
                                   value="<?php echo esc_attr($participant_id); ?>" />
                            <div class="feu-einsatz-pr-top">
                                <div class="feu-einsatz-pr-copy">
                                    <div class="feu-einsatz-pr-name">
                                        <span class="feu-einsatz-item-number"><?php echo esc_html($index + 1); ?>.</span>
                                        <strong class="feu-einsatz-item-name"><?php echo esc_html($participant_name); ?></strong>
                                    </div>
                                    <span class="feu-einsatz-pr-copy-meta">
                                        <?php echo esc_html(sprintf(__('Standard: %s', 'feuer-einsatzberichte'), $function_summary)); ?>
                                    </span>
                                </div>
                                <div class="feu-einsatz-pr-function">
                                    <select name="feu_einsatz_teilnehmer[<?php echo esc_attr($index); ?>][funktion]" class="feu-einsatz-function-select form-select">
                                        <?php feu_einsatz_render_function_options($funktionen, $default_functions, $selected_function, $default_participant_function); ?>
                                    </select>
                                </div>
                                <button type="button"
                                        class="button feu-einsatz-remove-item feu-einsatz-pr-delete"
                                        data-type="teilnehmer"
                                        data-id="<?php echo esc_attr($participant_id); ?>"
                                        aria-label="<?php esc_attr_e('Teilnehmer entfernen', 'feuer-einsatzberichte'); ?>">
                                    &#x2715;
                                </button>
                            </div>
                            <div class="feu-einsatz-pr-presets">
                                <?php feu_einsatz_render_function_quick_actions($default_functions, $selected_function); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </section>
    <?php endif; ?>

    <?php if ($show_kraefte_section) : ?>
        <section class="feu-einsatz-meta-section feu-einsatz-meta-section--organizations">
            <div class="feu-einsatz-meta-section-head">
                <div class="feu-einsatz-meta-section-copy">
                    <?php if (!$suppress_section_titles) : ?>
                        <h3 class="feu-einsatz-meta-section-title">
                            <span class="dashicons dashicons-building"></span>
                            <?php _e('Kraefte vor Ort', 'feuer-einsatzberichte'); ?>
                        </h3>
                    <?php endif; ?>
                    <p class="description">
                        <?php _e('Organisationen direkt ueber die Schaltflaechen zu- oder abwaehlen.', 'feuer-einsatzberichte'); ?>
                    </p>
                </div>
                <span class="feu-einsatz-badge" id="feu-einsatz-organisation-count"><?php echo esc_html(count($selected_organization_ids)); ?></span>
            </div>

            <div id="feu-einsatz-organization-picker" class="feu-einsatz-picker-grid feu-einsatz-picker-grid-organizations">
                <?php foreach ($picker_organizations as $organization) : ?>
                    <?php
                    $organization_id = (int) $organization->id;
                    $is_selected = in_array($organization_id, $selected_organization_ids, true);
                    $is_archived = !empty($organization->is_archived);
                    $is_locked = $is_archived && !$is_selected;
                    $organization_color = sanitize_hex_color(isset($organization->color) ? $organization->color : '') ?: '#0a4b78';
                    $label = (string) $organization->name;

                    if ($is_archived) {
                        $label .= ' (' . __('Archiviert', 'feuer-einsatzberichte') . ')';
                    }
                    ?>
                    <button type="button"
                            class="button feu-einsatz-toggle-chip feu-einsatz-organization-chip<?php echo $is_selected ? ' is-active' : ''; ?><?php echo $is_locked ? ' is-disabled' : ''; ?>"
                            data-id="<?php echo esc_attr($organization_id); ?>"
                            data-archived="<?php echo esc_attr($is_archived ? 1 : 0); ?>"
                            style="--feu-einsatz-toggle-color: <?php echo esc_attr($organization_color); ?>;"
                            aria-pressed="<?php echo $is_selected ? 'true' : 'false'; ?>"
                            <?php disabled($is_locked); ?>>
                        <?php echo esc_html($label); ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div id="feu-einsatz-organisation-list" class="feu-einsatz-list-container feu-einsatz-list-container--organizations">
                <?php if (empty($selected_organization_ids)) : ?>
                    <p class="feu-einsatz-empty-message"><?php _e('Noch keine Organisation ausgewaehlt.', 'feuer-einsatzberichte'); ?></p>
                <?php else : ?>
                    <?php foreach (array_values($selected_organization_ids) as $index => $organization_id) : ?>
                        <?php if (!isset($organization_lookup[(int) $organization_id])) { continue; } ?>
                        <?php
                        $organization = $organization_lookup[(int) $organization_id];
                        $organization_color = sanitize_hex_color(isset($organization->color) ? $organization->color : '') ?: '#0a4b78';
                        $organization_name = (string) $organization->name;
                        ?>
                        <div class="feu-einsatz-list-item feu-einsatz-organization-row" data-id="<?php echo esc_attr((int) $organization_id); ?>">
                            <input type="hidden"
                                   class="feu-einsatz-organization-id-field"
                                   name="feu_einsatz_organisationen[<?php echo esc_attr($index); ?>]"
                                   value="<?php echo esc_attr((int) $organization_id); ?>" />
                            <div class="feu-einsatz-organization-copy">
                                <span class="feu-einsatz-organization-swatch" style="--feu-einsatz-org-swatch: <?php echo esc_attr($organization_color); ?>;"></span>
                                <strong class="feu-einsatz-item-name"><?php echo esc_html($organization_name); ?></strong>
                            </div>
                            <div class="feu-einsatz-organization-actions">
                                <button type="button" class="button feu-einsatz-move-item feu-einsatz-move-up" data-type="organisation" aria-label="<?php esc_attr_e('Nach oben verschieben', 'feuer-einsatzberichte'); ?>">
                                    <span class="dashicons dashicons-arrow-up-alt2"></span>
                                </button>
                                <button type="button" class="button feu-einsatz-move-item feu-einsatz-move-down" data-type="organisation" aria-label="<?php esc_attr_e('Nach unten verschieben', 'feuer-einsatzberichte'); ?>">
                                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                                </button>
                                <button type="button" class="button feu-einsatz-remove-item feu-einsatz-org-delete" data-type="organisation" data-id="<?php echo esc_attr((int) $organization_id); ?>" aria-label="<?php esc_attr_e('Organisation entfernen', 'feuer-einsatzberichte'); ?>">
                                    &#x2715;
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div id="feu-einsatz-organisation-inputs" class="feu-einsatz-hidden-inputs" hidden></div>
            <p class="description feu-einsatz-organization-order-hint">
                <?php _e('Die Reihenfolge kann im ausgewaehlten Bereich mit den Pfeilen nach oben oder unten angepasst werden.', 'feuer-einsatzberichte'); ?>
            </p>
        </section>
    <?php endif; ?>

    <?php if ($show_teilnehmer_section) : ?>
        <div class="feu-einsatz-meta-hinweis">
            <p class="description">
                <span class="dashicons dashicons-info"></span>
                <?php _e('Archivierte oder entfernte Teilnehmer koennen nicht neu hinzugefuegt werden, bleiben aber in bestehenden Einsatzberichten erhalten.', 'feuer-einsatzberichte'); ?>
                <?php echo ' ' . esc_html__('Archivierte Organisationen koennen nicht neu hinzugefuegt werden, bleiben aber in bestehenden Einsatzberichten erhalten.', 'feuer-einsatzberichte'); ?>
            </p>
        </div>
    <?php endif; ?>
</div>

<?php if (empty($GLOBALS['feu_einsatz_meta_box_teilnehmer_script_rendered'])) : ?>
<?php $GLOBALS['feu_einsatz_meta_box_teilnehmer_script_rendered'] = true; ?>
<script>
jQuery(document).ready(function($) {
    var participants = <?php
        echo wp_json_encode(array_map(static function($participant) use ($alle_teilnehmer_mit_archiv) {
            $default_functions = feu_einsatz_find_participant_defaults($alle_teilnehmer_mit_archiv, (int) $participant->id);

            return [
                'id' => (int) $participant->id,
                'name' => FEU_Einsatz_Template_Helpers::format_participant_name($participant->vorname, $participant->nachname),
                'functions' => !empty($participant->default_functions) ? array_values($participant->default_functions) : [],
                'archived' => !empty($participant->is_archived) ? 1 : 0,
                'deleted' => !empty($participant->is_deleted) ? 1 : 0,
                'avatarHtml' => feu_einsatz_get_participant_avatar_markup($participant, 46),
                'functionSummary' => feu_einsatz_get_participant_function_summary($default_functions),
            ];
        }, $alle_teilnehmer_mit_archiv));
    ?>;
    var allFunctions = <?php echo wp_json_encode(array_values($funktionen)); ?>;
    var fallbackParticipantFunction = <?php echo wp_json_encode($default_participant_function); ?>;
    var organizations = <?php
        echo wp_json_encode(array_map(static function($organization) {
            return [
                'id' => (int) $organization->id,
                'name' => (string) $organization->name,
                'color' => sanitize_hex_color(isset($organization->color) ? $organization->color : '') ?: '#0a4b78',
                'archived' => !empty($organization->is_archived) ? 1 : 0,
            ];
        }, array_values($organization_lookup)));
    ?>;
    var selectedOrganizations = <?php echo wp_json_encode(array_values(array_map('strval', $selected_organization_ids))); ?>;
    var participantEmptyMessage = <?php echo wp_json_encode(__('Noch keine Teilnehmer ausgewaehlt.', 'feuer-einsatzberichte')); ?>;
    var participantRoleLabel = <?php echo wp_json_encode(__('Funktion im Einsatz', 'feuer-einsatzberichte')); ?>;
    var participantRemoveLabel = <?php echo wp_json_encode(__('Teilnehmer entfernen', 'feuer-einsatzberichte')); ?>;
    var participantStandardPrefix = <?php echo wp_json_encode(__('Standard:', 'feuer-einsatzberichte')); ?>;
    var participantQuickActionHint = <?php echo wp_json_encode(__('Oft genutzt', 'feuer-einsatzberichte')); ?>;
    var organizationEmptyMessage = <?php echo wp_json_encode(__('Noch keine Organisation ausgewaehlt.', 'feuer-einsatzberichte')); ?>;
    var organizationRemoveLabel = <?php echo wp_json_encode(__('Organisation entfernen', 'feuer-einsatzberichte')); ?>;
    var organizationMoveUpLabel = <?php echo wp_json_encode(__('Nach oben verschieben', 'feuer-einsatzberichte')); ?>;
    var organizationMoveDownLabel = <?php echo wp_json_encode(__('Nach unten verschieben', 'feuer-einsatzberichte')); ?>;

    selectedOrganizations = selectedOrganizations.filter(function(id, index, items) {
        id = String(id);
        return id !== '0' && items.indexOf(id) === index;
    });

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function getParticipantById(id) {
        return participants.find(function(participant) {
            return String(participant.id) === String(id);
        }) || null;
    }

    function getOrganizationById(id) {
        return organizations.find(function(organization) {
            return String(organization.id) === String(id);
        }) || null;
    }

    function getSelectedParticipantIds() {
        return $('#feu-einsatz-teilnehmer-list .feu-einsatz-participant-row').map(function() {
            return String($(this).data('id'));
        }).get();
    }

    function buildFunctionOptions(participantId, selectedValue) {
        var participant = getParticipantById(participantId);
        var defaultFunctions = participant && Array.isArray(participant.functions) ? participant.functions : [];
        var otherFunctions = allFunctions.filter(function(item) {
            return defaultFunctions.indexOf(item) === -1;
        });
        selectedValue = resolveParticipantFunctionValue(participantId, selectedValue);
        var html = '<option value=\"\"><?php echo esc_js(__('Funktion waehlen', 'feuer-einsatzberichte')); ?></option>';

        if (defaultFunctions.length) {
            html += '<optgroup label=\"<?php echo esc_js(__('Standardfunktionen', 'feuer-einsatzberichte')); ?>\">';
            defaultFunctions.forEach(function(item) {
                html += '<option value=\"' + escapeHtml(item) + '\"' + (selectedValue === item ? ' selected' : '') + '>' + escapeHtml(item) + '</option>';
            });
            html += '</optgroup>';
        }

        if (otherFunctions.length) {
            html += '<optgroup label=\"<?php echo esc_js(__('Weitere Funktionen', 'feuer-einsatzberichte')); ?>\">';
            otherFunctions.forEach(function(item) {
                html += '<option value=\"' + escapeHtml(item) + '\"' + (selectedValue === item ? ' selected' : '') + '>' + escapeHtml(item) + '</option>';
            });
            html += '</optgroup>';
        }

        html += '<option value=\"Keine Funktion\"' + (selectedValue === 'Keine Funktion' ? ' selected' : '') + '><?php echo esc_js(__('Keine Funktion', 'feuer-einsatzberichte')); ?></option>';
        return html;
    }

    function getParticipantDefaultFunction(participant) {
        var defaults = participant && Array.isArray(participant.functions) ? participant.functions : [];

        for (var i = 0; i < defaults.length; i++) {
            if (allFunctions.indexOf(defaults[i]) !== -1) {
                return defaults[i];
            }
        }

        if (allFunctions.indexOf(fallbackParticipantFunction) !== -1) {
            return fallbackParticipantFunction;
        }

        return allFunctions.length ? allFunctions[allFunctions.length - 1] : '';
    }

    function resolveParticipantFunctionValue(participantId, selectedValue) {
        var cleanValue = String(selectedValue || '').trim();

        if (cleanValue === 'Keine Funktion') {
            return cleanValue;
        }

        if (cleanValue) {
            return cleanValue;
        }

        return getParticipantDefaultFunction(getParticipantById(participantId));
    }

    function buildParticipantMeta(participant) {
        var summary = participant && participant.functionSummary ? String(participant.functionSummary) : '<?php echo esc_js(__('Keine Standardfunktion', 'feuer-einsatzberichte')); ?>';
        return participantStandardPrefix + ' ' + summary;
    }

    function buildFunctionQuickActions(participantId, selectedValue) {
        var participant = getParticipantById(participantId);
        var defaultFunctions = participant && Array.isArray(participant.functions) ? participant.functions : [];
        var resolvedValue = resolveParticipantFunctionValue(participantId, selectedValue);

        if (!defaultFunctions.length) {
            return '';
        }

        var html = '<div class=\"feu-einsatz-function-quick-actions\" data-feu-function-quick-actions>';
        defaultFunctions.forEach(function(item) {
            html += '<button type=\"button\" class=\"button feu-einsatz-function-quick-button' + (resolvedValue === item ? ' is-active' : '') + '\" data-feu-function-value=\"' + escapeHtml(item) + '\">' + escapeHtml(item) + '</button>';
        });
        html += '</div>';

        return html;
    }

    function createParticipantRow(participantId, selectedFunction) {
        var participant = getParticipantById(participantId);

        if (!participant) {
            return null;
        }

        var $row = $('<div class=\"feu-einsatz-list-item feu-einsatz-participant-row feu-einsatz-participant-row--compact\" data-id=\"' + escapeHtml(participant.id) + '\"></div>');
        $row.append('<input type=\"hidden\" class=\"feu-einsatz-participant-id-field\" value=\"' + escapeHtml(participant.id) + '\" />');
        $row.append(
            '<div class=\"feu-einsatz-pr-top\">' +
                '<div class=\"feu-einsatz-pr-copy\">' +
                    '<div class=\"feu-einsatz-pr-name\">' +
                        '<span class=\"feu-einsatz-item-number\"></span>' +
                        '<strong class=\"feu-einsatz-item-name\">' + escapeHtml(participant.name) + '</strong>' +
                    '</div>' +
                    '<span class=\"feu-einsatz-pr-copy-meta\">' + escapeHtml(buildParticipantMeta(participant)) + '</span>' +
                '</div>' +
                '<div class=\"feu-einsatz-pr-function\">' +
                    '<select class=\"feu-einsatz-function-select form-select\"></select>' +
                '</div>' +
                '<button type=\"button\" class=\"button feu-einsatz-remove-item feu-einsatz-pr-delete\" data-type=\"teilnehmer\" data-id=\"' + escapeHtml(participant.id) + '\" aria-label=\"' + escapeHtml(participantRemoveLabel) + '\">&#x2715;</button>' +
            '</div>'
        );
        $row.append(
            '<div class=\"feu-einsatz-pr-presets\">' +
                buildFunctionQuickActions(participant.id, selectedFunction || '') +
            '</div>'
        );
        $row.find('.feu-einsatz-function-select').html(buildFunctionOptions(participant.id, selectedFunction || ''));

        return $row;
    }

    function reindexParticipantRows() {
        $('#feu-einsatz-teilnehmer-list .feu-einsatz-participant-row').each(function(index) {
            $(this).find('.feu-einsatz-item-number').text((index + 1) + '.');
            $(this).find('.feu-einsatz-participant-id-field').attr('name', 'feu_einsatz_teilnehmer[' + index + '][id]');
            $(this).find('.feu-einsatz-function-select').attr('name', 'feu_einsatz_teilnehmer[' + index + '][funktion]');
        });
    }

    function syncParticipantButtons() {
        var selectedIds = getSelectedParticipantIds();

        $('#feu-einsatz-participant-picker .feu-einsatz-participant-chip-card').each(function() {
            var $button = $(this);
            var participantId = String($button.data('id'));
            var isSelected = selectedIds.indexOf(participantId) !== -1;
            var isLocked = (Number($button.data('archived')) === 1 || Number($button.data('deleted')) === 1) && !isSelected;

            $button.toggleClass('is-active', isSelected);
            $button.toggleClass('is-disabled', isLocked);
            $button.prop('disabled', isLocked);
            $button.attr('aria-pressed', isSelected ? 'true' : 'false');
        });

        $('#feu-einsatz-teilnehmer-count').text(selectedIds.length);
    }

    function syncParticipantListState() {
        if (!$('#feu-einsatz-teilnehmer-list .feu-einsatz-participant-row').length) {
            if (!$('#feu-einsatz-teilnehmer-list .feu-einsatz-empty-message').length) {
                $('#feu-einsatz-teilnehmer-list').append('<p class=\"feu-einsatz-empty-message\">' + escapeHtml(participantEmptyMessage) + '</p>');
            }
        } else {
            $('#feu-einsatz-teilnehmer-list .feu-einsatz-empty-message').remove();
        }

        reindexParticipantRows();
        syncParticipantButtons();
    }

    function syncParticipantQuickActions($row) {
        var $select = $row.find('.feu-einsatz-function-select');
        var selectedValue = String($select.val() || '');

        $row.find('[data-feu-function-quick-actions] .feu-einsatz-function-quick-button').each(function() {
            var isActive = String($(this).data('feu-function-value')) === selectedValue;
            $(this).toggleClass('is-active', isActive);
        });
    }

    function addParticipant(participantId) {
        if (getSelectedParticipantIds().indexOf(String(participantId)) !== -1) {
            return;
        }

        var $row = createParticipantRow(participantId, '');

        if (!$row) {
            return;
        }

        $('#feu-einsatz-teilnehmer-list .feu-einsatz-empty-message').remove();
        $('#feu-einsatz-teilnehmer-list').append($row);
        syncParticipantQuickActions($row);
        syncParticipantListState();

        $row.addClass('is-newly-added');
        window.setTimeout(function() {
            $row.removeClass('is-newly-added');
        }, 1800);
    }

    function removeParticipant(participantId) {
        $('#feu-einsatz-teilnehmer-list .feu-einsatz-participant-row').filter(function() {
            return String($(this).data('id')) === String(participantId);
        }).remove();

        syncParticipantListState();
    }

    function focusParticipantRow(participantId) {
        var $row = $('#feu-einsatz-teilnehmer-list .feu-einsatz-participant-row').filter(function() {
            return String($(this).data('id')) === String(participantId);
        }).first();

        if (!$row.length) {
            return false;
        }

        $('#feu-einsatz-teilnehmer-list .feu-einsatz-participant-row').removeClass('is-selected-row');
        $row.addClass('is-selected-row');

        var $select = $row.find('.feu-einsatz-function-select').first();

        if ($select.length) {
            $select.trigger('focus');
        }

        var rowNode = $row.get(0);

        if (rowNode && typeof rowNode.scrollIntoView === 'function') {
            rowNode.scrollIntoView({ block: 'center', behavior: 'smooth' });
        }

        return true;
    }

    function buildOrganizationRow(organizationId, index) {
        var organization = getOrganizationById(organizationId);

        if (!organization) {
            return '';
        }

        return '' +
            '<div class=\"feu-einsatz-list-item feu-einsatz-organization-row\" data-id=\"' + escapeHtml(organization.id) + '\">' +
                '<input type=\"hidden\" class=\"feu-einsatz-organization-id-field\" name=\"feu_einsatz_organisationen[' + index + ']\" value=\"' + escapeHtml(organization.id) + '\" />' +
                '<div class=\"feu-einsatz-organization-copy\">' +
                    '<span class=\"feu-einsatz-organization-swatch\" style=\"--feu-einsatz-org-swatch: ' + escapeHtml(organization.color) + ';\"></span>' +
                    '<strong class=\"feu-einsatz-item-name\">' + escapeHtml(organization.name) + '</strong>' +
                '</div>' +
                '<div class=\"feu-einsatz-organization-actions\">' +
                    '<button type=\"button\" class=\"button feu-einsatz-move-item feu-einsatz-move-up\" data-type=\"organisation\" aria-label=\"' + escapeHtml(organizationMoveUpLabel) + '\">' +
                        '<span class=\"dashicons dashicons-arrow-up-alt2\"></span>' +
                    '</button>' +
                    '<button type=\"button\" class=\"button feu-einsatz-move-item feu-einsatz-move-down\" data-type=\"organisation\" aria-label=\"' + escapeHtml(organizationMoveDownLabel) + '\">' +
                        '<span class=\"dashicons dashicons-arrow-down-alt2\"></span>' +
                    '</button>' +
                    '<button type=\"button\" class=\"button feu-einsatz-remove-item feu-einsatz-org-delete\" data-type=\"organisation\" data-id=\"' + escapeHtml(organization.id) + '\" aria-label=\"' + escapeHtml(organizationRemoveLabel) + '\">&#x2715;</button>' +
                '</div>' +
            '</div>';
    }

    function syncOrganizationMoveButtons() {
        var $rows = $('#feu-einsatz-organisation-list .feu-einsatz-organization-row');

        $rows.each(function(index) {
            $(this).find('.feu-einsatz-move-up').prop('disabled', index === 0);
            $(this).find('.feu-einsatz-move-down').prop('disabled', index === ($rows.length - 1));
        });
    }

    function renderOrganizationRows() {
        var html = '';

        selectedOrganizations.forEach(function(id, index) {
            html += buildOrganizationRow(id, index);
        });

        if (html) {
            $('#feu-einsatz-organisation-list').html(html);
        } else {
            $('#feu-einsatz-organisation-list').html('<p class=\"feu-einsatz-empty-message\">' + escapeHtml(organizationEmptyMessage) + '</p>');
        }

        syncOrganizationMoveButtons();
    }

    function syncOrganizationInputs() {
        $('#feu-einsatz-organisation-inputs').empty();
        renderOrganizationRows();
        $('#feu-einsatz-organisation-count').text(selectedOrganizations.length);
        $('#feu-einsatz-organization-picker .feu-einsatz-organization-chip').each(function() {
            var isSelected = selectedOrganizations.indexOf(String($(this).data('id'))) !== -1;
            var isLocked = Number($(this).data('archived')) === 1 && !isSelected;
            $(this)
                .toggleClass('is-active', isSelected)
                .toggleClass('is-disabled', isLocked)
                .prop('disabled', isLocked)
                .attr('aria-pressed', isSelected ? 'true' : 'false');
        });
    }

    $(document).on('click', '#feu-einsatz-participant-picker .feu-einsatz-participant-chip-card', function() {
        if ($(this).hasClass('is-disabled')) {
            return;
        }

        var participantId = $(this).data('id');

        if ($(this).hasClass('is-active')) {
            removeParticipant(participantId);
            return;
        }

        addParticipant(participantId);
    });

    $(document).on('click', '#feu-einsatz-teilnehmer-list .feu-einsatz-remove-item[data-type=\"teilnehmer\"]', function() {
        removeParticipant($(this).data('id'));
    });

    $(document).on('change', '#feu-einsatz-teilnehmer-list .feu-einsatz-function-select', function() {
        syncParticipantQuickActions($(this).closest('.feu-einsatz-participant-row'));
    });

    $(document).on('click', '#feu-einsatz-teilnehmer-list .feu-einsatz-function-quick-button', function() {
        var $button = $(this);
        var $row = $button.closest('.feu-einsatz-participant-row');
        var $select = $row.find('.feu-einsatz-function-select');
        var value = String($button.data('feu-function-value') || '');

        if (!$select.length || !value) {
            return;
        }

        $select.val(value).trigger('change').trigger('focus');
    });

    $(document).on('click', '#feu-einsatz-organization-picker .feu-einsatz-organization-chip', function() {
        if ($(this).hasClass('is-disabled')) {
            return;
        }

        var organizationId = String($(this).data('id'));
        var existingIndex = selectedOrganizations.indexOf(organizationId);

        if (existingIndex === -1) {
            selectedOrganizations.push(organizationId);
        } else {
            selectedOrganizations.splice(existingIndex, 1);
        }

        syncOrganizationInputs();
    });

    $(document).on('click', '#feu-einsatz-organisation-list .feu-einsatz-org-delete', function() {
        var organizationId = String($(this).data('id') || '');
        var existingIndex = selectedOrganizations.indexOf(organizationId);

        if (existingIndex === -1) {
            return;
        }

        selectedOrganizations.splice(existingIndex, 1);
        syncOrganizationInputs();
    });

    $(document).on('click', '#feu-einsatz-organisation-list .feu-einsatz-move-up', function() {
        var $row = $(this).closest('.feu-einsatz-organization-row');
        var organizationId = String($row.data('id') || '');
        var currentIndex = selectedOrganizations.indexOf(organizationId);

        if (currentIndex <= 0) {
            return;
        }

        var swapValue = selectedOrganizations[currentIndex - 1];
        selectedOrganizations[currentIndex - 1] = organizationId;
        selectedOrganizations[currentIndex] = swapValue;
        syncOrganizationInputs();
    });

    $(document).on('click', '#feu-einsatz-organisation-list .feu-einsatz-move-down', function() {
        var $row = $(this).closest('.feu-einsatz-organization-row');
        var organizationId = String($row.data('id') || '');
        var currentIndex = selectedOrganizations.indexOf(organizationId);

        if (currentIndex === -1 || currentIndex >= selectedOrganizations.length - 1) {
            return;
        }

        var swapValue = selectedOrganizations[currentIndex + 1];
        selectedOrganizations[currentIndex + 1] = organizationId;
        selectedOrganizations[currentIndex] = swapValue;
        syncOrganizationInputs();
    });

    syncParticipantListState();
    $('#feu-einsatz-teilnehmer-list .feu-einsatz-participant-row').each(function() {
        syncParticipantQuickActions($(this));
    });
    syncOrganizationInputs();
});
</script>
<?php endif; ?>
