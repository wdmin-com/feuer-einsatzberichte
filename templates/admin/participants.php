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
$mannschaft_available = FEU_Einsatz_Mannschaft_Integration::is_available();
$mannschaft_integration = Feuer_Einsatzberichte_Core::get_instance()->get_mannschaft_integration();
$mannschaft_profiles = $mannschaft_available
    ? $mannschaft_integration->get_profiles()
    : [];
$mannschaft_sync_preview = $mannschaft_available
    ? $mannschaft_integration->preview_sync()
    : ['counts' => [], 'rows' => []];
$ignored_mannschaft_profile_ids = $mannschaft_available ? $mannschaft_integration->get_ignored_profile_ids() : [];
$mannschaft_profile_index = [];
foreach ($mannschaft_profiles as $mannschaft_profile) {
    $mannschaft_profile_index[(int) $mannschaft_profile['id']] = $mannschaft_profile;
}
$linked_mannschaft_participants = array_values(array_filter($teilnehmer, static function ($participant) {
    return FEU_Einsatz_Mannschaft_Integration::PROVIDER_VALUE === (string) ($participant->external_provider ?? '')
        && (int) ($participant->external_id ?? 0) > 0;
}));
$linked_mannschaft_profile_ids = array_values(array_filter(array_map(
    'absint',
    wp_list_pluck($teilnehmer, 'external_id')
)));
$unlinked_participant_rows = array_values(array_filter($teilnehmer, static function ($participant) {
    return empty($participant->external_provider) || empty($participant->external_id);
}));
$unlinked_mannschaft_profiles = array_values(array_filter($mannschaft_profiles, static function ($profile) use ($linked_mannschaft_profile_ids, $ignored_mannschaft_profile_ids) {
    return !in_array((int) $profile['id'], $linked_mannschaft_profile_ids, true)
        && !in_array((int) $profile['id'], $ignored_mannschaft_profile_ids, true);
}));
$merge_participant_rows = $this->db->get_participants([
    'include_archived' => true,
    'include_deleted' => false,
    'order_by_usage' => true,
]);

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

$sync_panel_open = isset($_GET['feu_mannschaft_synced'])
    || isset($_GET['feu_mannschaft_connected'])
    || isset($_GET['feu_mannschaft_disconnected'])
    || isset($_GET['feu_mannschaft_allowed']);
$merge_panel_open = isset($_GET['feu_mannschaft_merged']);

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
            <h1 class="wp-heading-inline"><?php esc_html_e('Mannschaft verwalten', 'feuer-einsatzberichte'); ?></h1>
            <p class="description feu-einsatz-participants-intro">
                <?php esc_html_e('Hier pflegen Sie die komplette Mannschaftsakte. Archivierte Mitglieder werden auf der Website nicht mehr angezeigt, bleiben aber in Statistik und bestehenden Einsatzberichten erhalten.', 'feuer-einsatzberichte'); ?>
            </p>
        </div>
    </div>

    <?php if (isset($_GET['feu_mannschaft_synced'])) : ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html(sprintf(__('Manuelle Synchronisierung abgeschlossen: %d Mitgliedsprofile aktualisiert. Funktionen, Ausbildung und lokale Einsatzdaten wurden nicht verändert.', 'feuer-einsatzberichte'), absint($_GET['feu_mannschaft_synced']))); ?></p></div>
        <?php if (!empty($_GET['feu_mannschaft_review'])) : ?><div class="notice notice-warning is-dismissible"><p><?php echo esc_html(sprintf(__('%d mögliche Namensabweichung wurde aus Sicherheitsgründen nicht automatisch übernommen. Bitte Zuordnung prüfen.', 'feuer-einsatzberichte'), absint($_GET['feu_mannschaft_review']))); ?></p></div><?php endif; ?>
    <?php endif; ?>
    <?php if (isset($_GET['feu_mannschaft_connected'])) : ?>
        <div class="notice <?php echo '1' === sanitize_key(wp_unslash($_GET['feu_mannschaft_connected'])) ? 'notice-success' : 'notice-error'; ?> is-dismissible"><p><?php echo '1' === sanitize_key(wp_unslash($_GET['feu_mannschaft_connected'])) ? esc_html__('Profil wurde verbunden. Die bisherigen Einsatz- und Statistikzuordnungen bleiben erhalten.', 'feuer-einsatzberichte') : esc_html__('Profile konnten nicht verbunden werden. Prüfen Sie die Auswahl und ob das Mannschaftsprofil bereits verknüpft ist.', 'feuer-einsatzberichte'); ?></p></div>
    <?php endif; ?>
    <?php if (isset($_GET['feu_mannschaft_disconnected'])) : ?>
        <?php $disconnect_succeeded = '1' === sanitize_key(wp_unslash($_GET['feu_mannschaft_disconnected'])); ?>
        <div class="notice <?php echo $disconnect_succeeded ? 'notice-success' : 'notice-error'; ?> is-dismissible"><p><?php echo $disconnect_succeeded ? esc_html__('Die Verbindung wurde gelöst. Das lokale Profil, alle Einsätze und Statistiken bleiben vollständig erhalten. Das externe Profil ist für spätere Synchronisierungen gesperrt.', 'feuer-einsatzberichte') : esc_html__('Die Verbindung konnte nicht gelöst werden. Möglicherweise ist das Profil nicht mehr mit Feuer-Mannschaft verbunden.', 'feuer-einsatzberichte'); ?></p></div>
    <?php endif; ?>
    <?php if (isset($_GET['feu_mannschaft_allowed'])) : ?>
        <?php $allow_succeeded = '1' === sanitize_key(wp_unslash($_GET['feu_mannschaft_allowed'])); ?>
        <div class="notice <?php echo $allow_succeeded ? 'notice-success' : 'notice-error'; ?> is-dismissible"><p><?php echo $allow_succeeded ? esc_html__('Das Feuer-Mannschaft-Profil darf wieder manuell synchronisiert oder zugeordnet werden.', 'feuer-einsatzberichte') : esc_html__('Das Profil konnte nicht wieder für die Synchronisierung freigegeben werden.', 'feuer-einsatzberichte'); ?></p></div>
    <?php endif; ?>
    <?php if (isset($_GET['feu_mannschaft_merged'])) : ?>
        <?php
        $merge_succeeded = '1' === sanitize_key(wp_unslash($_GET['feu_mannschaft_merged']));
        $merge_error = isset($_GET['feu_mannschaft_merge_error']) ? sanitize_key(wp_unslash($_GET['feu_mannschaft_merge_error'])) : '';
        $merge_error_messages = [
            'same_profile' => __('Bitte wählen Sie zwei unterschiedliche Profile.', 'feuer-einsatzberichte'),
            'participant_missing' => __('Mindestens eines der ausgewählten Profile ist nicht mehr verfügbar.', 'feuer-einsatzberichte'),
            'conflicting_external_identity' => __('Beide Profile sind mit unterschiedlichen Feuer-Mannschaft-Profilen verbunden. Lösen Sie zuerst die falsche Verbindung.', 'feuer-einsatzberichte'),
            'confirmation_missing' => __('Bitte bestätigen Sie die Zusammenführung ausdrücklich.', 'feuer-einsatzberichte'),
            'merge_failed' => __('Die Zusammenführung wurde sicher abgebrochen. Es wurden keine Teiländerungen gespeichert.', 'feuer-einsatzberichte'),
        ];
        $merge_message = $merge_succeeded
            ? sprintf(
                __('Profile wurden zusammengeführt: %1$d Berichte und %2$d Einsatzzuordnungen wurden geprüft. Die Statistik verwendet jetzt nur noch das Hauptprofil.', 'feuer-einsatzberichte'),
                absint($_GET['feu_mannschaft_merge_reports'] ?? 0),
                absint($_GET['feu_mannschaft_merge_assignments'] ?? 0)
            )
            : ($merge_error_messages[$merge_error] ?? __('Profile konnten nicht zusammengeführt werden.', 'feuer-einsatzberichte'));
        ?>
        <div class="notice <?php echo $merge_succeeded ? 'notice-success' : 'notice-error'; ?> is-dismissible"><p><?php echo esc_html($merge_message); ?></p></div>
    <?php endif; ?>

    <details class="feu-einsatz-mannschaft-sync" <?php echo $sync_panel_open ? 'open' : ''; ?>>
        <summary class="feu-einsatz-mannschaft-sync-toggle">
            <span class="feu-einsatz-mannschaft-sync-toggle-icon ti ti-refresh" aria-hidden="true"></span>
            <span class="feu-einsatz-mannschaft-sync-toggle-copy">
                <strong><?php esc_html_e('Feuer-Mannschaft synchronisieren', 'feuer-einsatzberichte'); ?></strong>
                <small><?php esc_html_e('Vorschau, manuelle Zuordnung und Verbindungen nur bei Bedarf öffnen.', 'feuer-einsatzberichte'); ?></small>
            </span>
            <?php if ($mannschaft_available) : ?>
                <span class="feu-einsatz-mannschaft-sync-toggle-status"><?php echo esc_html(sprintf(__('%d verbunden', 'feuer-einsatzberichte'), count($linked_mannschaft_participants))); ?></span>
            <?php endif; ?>
            <span class="feu-einsatz-mannschaft-sync-toggle-chevron ti ti-chevron-down" aria-hidden="true"></span>
        </summary>
        <div class="feu-einsatz-mannschaft-sync-body">
            <div class="feu-einsatz-mannschaft-sync-heading">
                <div>
                    <h2><?php esc_html_e('Feuer-Mannschaft: manuelle Synchronisierung', 'feuer-einsatzberichte'); ?></h2>
                    <p class="description"><?php esc_html_e('Importiert werden nur Vorname, Nachname, Dienstgrad/Position und Profilfoto. Funktionen, Ausbildung, lokale Akte, Archivstatus, Einsätze und Statistik werden nicht überschrieben.', 'feuer-einsatzberichte'); ?></p>
                </div>
                <?php if ($mannschaft_available) : ?><a class="button" href="<?php echo esc_url(FEU_Einsatz_Mannschaft_Integration::get_management_url()); ?>"><?php esc_html_e('Feuer-Mannschaft öffnen', 'feuer-einsatzberichte'); ?></a><?php endif; ?>
            </div>

            <?php if ($mannschaft_available) : ?>
                <div class="feu-einsatz-sync-preview" aria-label="<?php esc_attr_e('Synchronisierungsvorschau', 'feuer-einsatzberichte'); ?>">
                    <?php foreach ([
                        'update' => __('Aktualisieren', 'feuer-einsatzberichte'),
                        'connect' => __('Sicher verbinden', 'feuer-einsatzberichte'),
                        'create' => __('Neu anlegen', 'feuer-einsatzberichte'),
                        'review' => __('Prüfung nötig', 'feuer-einsatzberichte'),
                        'ignored' => __('Ausgeschlossen', 'feuer-einsatzberichte'),
                    ] as $preview_key => $preview_label) : ?>
                        <span class="feu-einsatz-sync-preview-item is-<?php echo esc_attr($preview_key); ?>"><strong><?php echo esc_html((string) ($mannschaft_sync_preview['counts'][$preview_key] ?? 0)); ?></strong> <?php echo esc_html($preview_label); ?></span>
                    <?php endforeach; ?>
                </div>

                <?php foreach ($mannschaft_sync_preview['rows'] as $preview_row) : ?>
                    <?php if ('review' !== $preview_row['status']) continue; ?>
                    <div class="feu-einsatz-sync-review-row">
                        <div><strong><?php echo esc_html($preview_row['profile']['name']); ?></strong><span aria-hidden="true"> → </span><?php echo esc_html($preview_row['local_name']); ?><br><small><?php esc_html_e('Ähnliche Namen – keine automatische Zuordnung.', 'feuer-einsatzberichte'); ?></small></div>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <input type="hidden" name="action" value="feu_einsatz_connect_mannschaft_profile" />
                            <input type="hidden" name="feu_einsatz_participant_id" value="<?php echo esc_attr((string) $preview_row['participant']->id); ?>" />
                            <input type="hidden" name="feu_einsatz_mannschaft_profile_id" value="<?php echo esc_attr((string) $preview_row['profile']['id']); ?>" />
                            <?php wp_nonce_field('feu_einsatz_connect_mannschaft_profile'); ?>
                            <button type="submit" class="button button-small"><?php esc_html_e('Zuordnung bestätigen', 'feuer-einsatzberichte'); ?></button>
                        </form>
                    </div>
                <?php endforeach; ?>

                <div class="feu-einsatz-mannschaft-sync-actions">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="feu_einsatz_sync_mannschaft_participants" />
                        <?php wp_nonce_field('feu_einsatz_sync_mannschaft_participants'); ?>
                        <button type="submit" class="button button-primary"><?php esc_html_e('Jetzt manuell synchronisieren', 'feuer-einsatzberichte'); ?></button>
                    </form>
                    <?php $last_sync = (string) get_option('feu_einsatz_mannschaft_last_sync', ''); ?>
                    <?php if ('' !== $last_sync) : ?><span class="description"><?php echo esc_html(sprintf(__('Letzte manuelle Synchronisierung: %s', 'feuer-einsatzberichte'), $last_sync)); ?></span><?php endif; ?>
                </div>

                <?php if (!empty($unlinked_participant_rows) && !empty($unlinked_mannschaft_profiles)) : ?>
                    <section class="feu-einsatz-mannschaft-sync-section">
                        <h3><?php esc_html_e('Abweichende Namen verbinden', 'feuer-einsatzberichte'); ?></h3>
                        <p class="description"><?php esc_html_e('Ordnen Sie ein bestehendes lokales Mitglied einmalig dem richtigen Feuer-Mannschaft-Profil zu.', 'feuer-einsatzberichte'); ?></p>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="feu-einsatz-mannschaft-connect-form">
                            <input type="hidden" name="action" value="feu_einsatz_connect_mannschaft_profile" />
                            <?php wp_nonce_field('feu_einsatz_connect_mannschaft_profile'); ?>
                            <label><span><?php esc_html_e('Lokales Mitglied', 'feuer-einsatzberichte'); ?></span><select name="feu_einsatz_participant_id" required><?php foreach ($unlinked_participant_rows as $participant) : ?><option value="<?php echo esc_attr($participant->id); ?>"><?php echo esc_html(FEU_Einsatz_Template_Helpers::format_participant_name($participant->vorname, $participant->nachname)); ?></option><?php endforeach; ?></select></label>
                            <label><span><?php esc_html_e('Feuer-Mannschaft-Profil', 'feuer-einsatzberichte'); ?></span><select name="feu_einsatz_mannschaft_profile_id" required><?php foreach ($unlinked_mannschaft_profiles as $profile) : ?><option value="<?php echo esc_attr($profile['id']); ?>"><?php echo esc_html($profile['name']); ?></option><?php endforeach; ?></select></label>
                            <button type="submit" class="button button-secondary"><?php esc_html_e('Profile verbinden', 'feuer-einsatzberichte'); ?></button>
                        </form>
                    </section>
                <?php endif; ?>

                <?php if (!empty($linked_mannschaft_participants)) : ?>
                    <section class="feu-einsatz-mannschaft-sync-section">
                        <h3><?php esc_html_e('Verbundene Profile', 'feuer-einsatzberichte'); ?></h3>
                        <p class="description"><?php esc_html_e('Beim Lösen bleiben das lokale Profil, alle Einsätze und Statistiken erhalten. Das externe Profil wird von späteren Synchronisierungen ausgeschlossen.', 'feuer-einsatzberichte'); ?></p>
                        <div class="feu-einsatz-mannschaft-link-list">
                            <?php foreach ($linked_mannschaft_participants as $participant) : ?>
                                <div class="feu-einsatz-mannschaft-link-row">
                                    <div><strong><?php echo esc_html(FEU_Einsatz_Template_Helpers::format_participant_name($participant->vorname, $participant->nachname)); ?></strong><small><?php echo esc_html($mannschaft_profile_index[(int) $participant->external_id]['name'] ?? sprintf(__('Feuer-Mannschaft #%d', 'feuer-einsatzberichte'), (int) $participant->external_id)); ?></small></div>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Verbindung wirklich lösen? Lokale Einsätze und Statistik bleiben erhalten.', 'feuer-einsatzberichte')); ?>');">
                                        <input type="hidden" name="action" value="feu_einsatz_disconnect_mannschaft_profile" />
                                        <input type="hidden" name="feu_einsatz_participant_id" value="<?php echo esc_attr((string) $participant->id); ?>" />
                                        <?php wp_nonce_field('feu_einsatz_disconnect_mannschaft_profile_' . (int) $participant->id); ?>
                                        <button type="submit" class="button button-small feu-einsatz-button-danger-subtle"><?php esc_html_e('Verbindung lösen', 'feuer-einsatzberichte'); ?></button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if (!empty($ignored_mannschaft_profile_ids)) : ?>
                    <section class="feu-einsatz-mannschaft-sync-section">
                        <h3><?php esc_html_e('Von der Synchronisierung ausgeschlossen', 'feuer-einsatzberichte'); ?></h3>
                        <div class="feu-einsatz-mannschaft-link-list">
                            <?php foreach ($ignored_mannschaft_profile_ids as $profile_id) : ?>
                                <div class="feu-einsatz-mannschaft-link-row">
                                    <div><strong><?php echo esc_html($mannschaft_profile_index[$profile_id]['name'] ?? sprintf(__('Feuer-Mannschaft #%d', 'feuer-einsatzberichte'), $profile_id)); ?></strong><small><?php esc_html_e('Wird bei manuellen Synchronisierungen übersprungen.', 'feuer-einsatzberichte'); ?></small></div>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                        <input type="hidden" name="action" value="feu_einsatz_allow_mannschaft_profile" />
                                        <input type="hidden" name="feu_einsatz_mannschaft_profile_id" value="<?php echo esc_attr((string) $profile_id); ?>" />
                                        <?php wp_nonce_field('feu_einsatz_allow_mannschaft_profile_' . $profile_id); ?>
                                        <button type="submit" class="button button-small"><?php esc_html_e('Wieder zulassen', 'feuer-einsatzberichte'); ?></button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>
            <?php else : ?>
                <p class="description"><?php esc_html_e('Feuer-Mannschaft ist derzeit nicht aktiv oder nicht installiert. Die lokale Mannschaftsverwaltung funktioniert weiterhin vollständig.', 'feuer-einsatzberichte'); ?></p>
            <?php endif; ?>
        </div>
    </details>

    <?php if (count($merge_participant_rows) > 1) : ?>
        <details class="feu-einsatz-mannschaft-sync feu-einsatz-profile-merge-disclosure" <?php echo $merge_panel_open ? 'open' : ''; ?>>
            <summary class="feu-einsatz-mannschaft-sync-toggle">
                <span class="feu-einsatz-mannschaft-sync-toggle-icon ti ti-user-check" aria-hidden="true"></span>
                <span class="feu-einsatz-mannschaft-sync-toggle-copy">
                    <strong><?php esc_html_e('Mannschaftsprofile zusammenführen', 'feuer-einsatzberichte'); ?></strong>
                    <small><?php esc_html_e('Nur öffnen, wenn dieselbe Person doppelt angelegt wurde.', 'feuer-einsatzberichte'); ?></small>
                </span>
                <span class="feu-einsatz-mannschaft-sync-toggle-status is-neutral"><?php esc_html_e('Optional', 'feuer-einsatzberichte'); ?></span>
                <span class="feu-einsatz-mannschaft-sync-toggle-chevron ti ti-chevron-down" aria-hidden="true"></span>
            </summary>
            <div class="feu-einsatz-mannschaft-sync-body">
        <section class="feu-einsatz-profile-merge-card" aria-labelledby="feu-einsatz-profile-merge-title">
            <div class="feu-einsatz-profile-merge-head">
                <div>
                    <span class="feu-admin-page-eyebrow"><?php esc_html_e('Doppelte Identitäten bereinigen', 'feuer-einsatzberichte'); ?></span>
                    <h2 id="feu-einsatz-profile-merge-title"><?php esc_html_e('Mannschaftsprofile zusammenführen', 'feuer-einsatzberichte'); ?></h2>
                    <p class="description"><?php esc_html_e('Verwenden Sie diese Funktion, wenn dieselbe Person wegen unterschiedlicher Namen zweimal angelegt wurde. Alle Berichte und Statistiken werden dem Hauptprofil zugeordnet; doppelte Zuordnungen im selben Einsatz werden automatisch entfernt.', 'feuer-einsatzberichte'); ?></p>
                </div>
                <span class="feu-einsatz-profile-merge-icon ti ti-user-check" aria-hidden="true"></span>
            </div>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="feu-einsatz-profile-merge-form">
                <input type="hidden" name="action" value="feu_einsatz_merge_participant_profiles" />
                <?php wp_nonce_field('feu_einsatz_merge_participant_profiles'); ?>
                <div class="feu-einsatz-profile-merge-grid">
                    <label>
                        <span><?php esc_html_e('Hauptprofil, das erhalten bleibt', 'feuer-einsatzberichte'); ?></span>
                        <select name="feu_einsatz_primary_participant_id" required>
                            <option value=""><?php esc_html_e('Hauptprofil auswählen', 'feuer-einsatzberichte'); ?></option>
                            <?php foreach ($merge_participant_rows as $participant) : ?>
                                <?php
                                $participant_name = FEU_Einsatz_Template_Helpers::format_participant_name($participant->vorname, $participant->nachname);
                                $participant_source = !empty($participant->external_provider) && !empty($participant->external_id)
                                    ? __(' · Feuer-Mannschaft verbunden', 'feuer-einsatzberichte')
                                    : __(' · Lokal', 'feuer-einsatzberichte');
                                ?>
                                <option value="<?php echo esc_attr($participant->id); ?>"><?php echo esc_html(sprintf('%1$s · %2$d Einsätze%3$s', $participant_name, (int) $participant->usage_count, $participant_source)); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small><?php esc_html_e('Name, lokale Akte und ID dieses Profils bleiben bestehen.', 'feuer-einsatzberichte'); ?></small>
                    </label>
                    <span class="feu-einsatz-profile-merge-arrow" aria-hidden="true">→</span>
                    <label>
                        <span><?php esc_html_e('Doppeltes Profil, das aufgenommen wird', 'feuer-einsatzberichte'); ?></span>
                        <select name="feu_einsatz_duplicate_participant_id" required>
                            <option value=""><?php esc_html_e('Doppeltes Profil auswählen', 'feuer-einsatzberichte'); ?></option>
                            <?php foreach ($merge_participant_rows as $participant) : ?>
                                <?php
                                $participant_name = FEU_Einsatz_Template_Helpers::format_participant_name($participant->vorname, $participant->nachname);
                                $participant_source = !empty($participant->external_provider) && !empty($participant->external_id)
                                    ? __(' · Feuer-Mannschaft verbunden', 'feuer-einsatzberichte')
                                    : __(' · Lokal', 'feuer-einsatzberichte');
                                ?>
                                <option value="<?php echo esc_attr($participant->id); ?>"><?php echo esc_html(sprintf('%1$s · %2$d Einsätze%3$s', $participant_name, (int) $participant->usage_count, $participant_source)); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small><?php esc_html_e('Einsatzzuordnungen und die externe Verbindung werden in das Hauptprofil übertragen.', 'feuer-einsatzberichte'); ?></small>
                    </label>
                </div>
                <div class="feu-einsatz-profile-merge-options">
                    <label><input type="checkbox" name="feu_einsatz_keep_primary_name" value="1" checked /> <span><?php esc_html_e('Namen des Hauptprofils dauerhaft behalten, auch bei späteren Synchronisierungen', 'feuer-einsatzberichte'); ?></span></label>
                    <label><input type="checkbox" name="feu_einsatz_confirm_profile_merge" value="1" required /> <span><?php esc_html_e('Ich bestätige, dass beide Profile dieselbe Person darstellen.', 'feuer-einsatzberichte'); ?></span></label>
                </div>
                <div class="feu-einsatz-profile-merge-actions">
                    <button type="submit" class="button button-primary"><?php esc_html_e('Profile sicher zusammenführen', 'feuer-einsatzberichte'); ?></button>
                    <p class="description"><?php esc_html_e('Das doppelte Profil wird anschließend ausgeblendet. Historische Einsätze bleiben vollständig erhalten.', 'feuer-einsatzberichte'); ?></p>
                </div>
            </form>
        </section>
            </div>
        </details>
    <?php endif; ?>

    <div class="feu-admin-stat-grid feu-admin-stat-grid--compact">
        <article class="feu-admin-stat-card">
            <div class="feu-admin-stat-icon"><span class="ti ti-users-group"></span></div>
            <div class="feu-admin-stat-content">
                <span class="feu-admin-stat-label"><?php esc_html_e('Aktive Mitglieder', 'feuer-einsatzberichte'); ?></span>
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
                <h2><?php esc_html_e('Mannschaftsmitglieder', 'feuer-einsatzberichte'); ?></h2>
                <p class="description"><?php esc_html_e('Suche, Foto, Status, Dienstgrad, Position und Standardfunktionen direkt im Überblick.', 'feuer-einsatzberichte'); ?></p>
            </div>
            <div class="feu-admin-toolbar-search">
                <span class="ti ti-search" aria-hidden="true"></span>
                <input type="search" id="feu-einsatz-search-participant" aria-label="<?php esc_attr_e('Mannschaftsmitglieder suchen', 'feuer-einsatzberichte'); ?>" placeholder="<?php esc_attr_e('Mitglieder suchen...', 'feuer-einsatzberichte'); ?>" />
            </div>
        </div>
        <div class="feu-einsatz-participant-filters" role="group" aria-label="<?php echo esc_attr__('Mannschaft filtern', 'feuer-einsatzberichte'); ?>">
            <button type="button" class="is-active" data-feu-participant-filter="all" aria-pressed="true"><?php esc_html_e('Alle', 'feuer-einsatzberichte'); ?></button>
            <button type="button" data-feu-participant-filter="active" aria-pressed="false"><?php esc_html_e('Aktiv', 'feuer-einsatzberichte'); ?></button>
            <button type="button" data-feu-participant-filter="archived" aria-pressed="false"><?php esc_html_e('Archiviert', 'feuer-einsatzberichte'); ?></button>
            <button type="button" data-feu-participant-filter="linked" aria-pressed="false"><?php esc_html_e('Synchronisiert', 'feuer-einsatzberichte'); ?></button>
            <button type="button" data-feu-participant-filter="without-photo" aria-pressed="false"><?php esc_html_e('Ohne Foto', 'feuer-einsatzberichte'); ?></button>
        </div>

        <?php if (empty($teilnehmer)) : ?>
            <div class="feu-admin-empty-state">
                <span class="ti ti-users"></span>
                <strong><?php esc_html_e('Noch keine Mannschaftsmitglieder vorhanden.', 'feuer-einsatzberichte'); ?></strong>
                <p><?php esc_html_e('Legen Sie die erste Mannschaftsakte direkt oben im Formular an.', 'feuer-einsatzberichte'); ?></p>
            </div>
        <?php else : ?>
            <div class="feu-admin-table-wrap">
                <table class="wp-list-table widefat fixed feu-einsatz-participant-table feu-einsatz-participant-table--compact feu-admin-table">
                    <thead>
                        <tr>
                            <th class="feu-einsatz-participant-member-column"><?php esc_html_e('Mitglied', 'feuer-einsatzberichte'); ?></th>
                            <th class="feu-einsatz-participant-rank-column"><?php esc_html_e('Dienstgrad', 'feuer-einsatzberichte'); ?></th>
                            <th class="feu-einsatz-participant-position-column"><?php esc_html_e('Position', 'feuer-einsatzberichte'); ?></th>
                            <th><?php esc_html_e('Standardfunktionen', 'feuer-einsatzberichte'); ?></th>
                            <th class="feu-einsatz-participant-actions-column"><?php esc_html_e('Aktionen', 'feuer-einsatzberichte'); ?></th>
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
                            <tr class="<?php echo !empty($person->is_archived) ? 'is-archived' : ''; ?>"
                                data-participant-id="<?php echo esc_attr((string) $person->id); ?>"
                                data-status="active"
                                data-source="<?php echo !empty($person->external_provider) && !empty($person->external_id) ? 'linked' : 'local'; ?>"
                                data-photo="<?php echo $primary_image_id > 0 ? 'with-photo' : 'without-photo'; ?>">
                                <td data-label="<?php echo esc_attr__('Mitglied', 'feuer-einsatzberichte'); ?>">
                                    <div class="feu-einsatz-participant-identity">
                                        <div class="feu-admin-participant-media">
                                        <?php if ($primary_image_id > 0) : ?>
                                            <?php echo wp_get_attachment_image($primary_image_id, [48, 48], false, ['class' => 'feu-einsatz-participant-thumb']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                        <?php else : ?>
                                            <span class="feu-einsatz-participant-thumb feu-einsatz-participant-thumb-placeholder">
                                                <?php echo esc_html($initials); ?>
                                            </span>
                                        <?php endif; ?>
                                        </div>
                                        <div class="feu-einsatz-participant-identity-copy">
                                            <strong><?php echo esc_html(FEU_Einsatz_Template_Helpers::format_participant_name($person->vorname, $person->nachname)); ?></strong>
                                            <span class="feu-einsatz-participant-meta">
                                                <span class="feu-einsatz-participant-state is-active"><?php esc_html_e('Aktiv', 'feuer-einsatzberichte'); ?></span>
                                                <?php if (!empty($person->external_provider) && !empty($person->external_id)) : ?>
                                                    <span class="feu-einsatz-participant-source"><?php esc_html_e('Feuer-Mannschaft', 'feuer-einsatzberichte'); ?></span>
                                                <?php else : ?>
                                                    <span class="feu-einsatz-participant-source"><?php esc_html_e('Lokal', 'feuer-einsatzberichte'); ?></span>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="<?php echo esc_attr__('Dienstgrad', 'feuer-einsatzberichte'); ?>" class="feu-einsatz-participant-detail-cell">
                                    <span class="feu-einsatz-participant-detail-label"><?php esc_html_e('Dienstgrad', 'feuer-einsatzberichte'); ?></span>
                                    <?php if ('' !== trim((string) $person->rank_title)) : ?>
                                        <strong class="feu-einsatz-participant-detail-value"><?php echo esc_html($person->rank_title); ?></strong>
                                    <?php else : ?>
                                        <span class="feu-einsatz-participant-detail-empty" aria-label="<?php esc_attr_e('Nicht hinterlegt', 'feuer-einsatzberichte'); ?>">—</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="<?php echo esc_attr__('Position', 'feuer-einsatzberichte'); ?>" class="feu-einsatz-participant-detail-cell">
                                    <span class="feu-einsatz-participant-detail-label"><?php esc_html_e('Position', 'feuer-einsatzberichte'); ?></span>
                                    <?php if ('' !== trim((string) $person->job_title)) : ?>
                                        <strong class="feu-einsatz-participant-detail-value"><?php echo esc_html($person->job_title); ?></strong>
                                    <?php else : ?>
                                        <span class="feu-einsatz-participant-detail-empty" aria-label="<?php esc_attr_e('Nicht hinterlegt', 'feuer-einsatzberichte'); ?>">—</span>
                                    <?php endif; ?>
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
                                    <div class="feu-einsatz-participant-row-actions">
                                        <button
                                            type="button"
                                            class="button button-small feu-einsatz-edit-participant feu-einsatz-participant-edit-button"
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
                                            <span class="ti ti-pencil" aria-hidden="true"></span>
                                            <?php esc_html_e('Bearbeiten', 'feuer-einsatzberichte'); ?>
                                        </button>
                                        <details class="feu-einsatz-participant-action-menu">
                                            <summary aria-label="<?php echo esc_attr__('Weitere Aktionen', 'feuer-einsatzberichte'); ?>"><span class="ti ti-dots-vertical" aria-hidden="true"></span></summary>
                                            <div class="feu-einsatz-participant-action-popover">
                                                <button type="button" class="feu-einsatz-toggle-archive" data-id="<?php echo esc_attr($person->id); ?>" data-archived="<?php echo esc_attr((int) $person->is_archived); ?>">
                                                    <span class="ti ti-archive" aria-hidden="true"></span><?php esc_html_e('Archivieren', 'feuer-einsatzberichte'); ?>
                                                </button>
                                                <?php if ($can_delete_participants) : ?>
                                                    <button type="button" class="feu-einsatz-delete-participant is-destructive"
                                                data-id="<?php echo esc_attr($person->id); ?>">
                                                        <span class="ti ti-trash" aria-hidden="true"></span><?php esc_html_e('Entfernen', 'feuer-einsatzberichte'); ?>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </details>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <?php if (!empty($archived_participant_rows)) : ?>
                        <tbody class="feu-einsatz-participant-table-archived">
                            <tr class="feu-einsatz-participant-separator">
                                <td colspan="5">
                                    <strong><?php esc_html_e('Archivierte Mitglieder', 'feuer-einsatzberichte'); ?></strong>
                                    <span class="description"><?php esc_html_e('Nicht mehr aktiv auf der Website, aber weiterhin für Historie, Statistik und bestehende Berichte verfügbar.', 'feuer-einsatzberichte'); ?></span>
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
                                <tr class="is-archived"
                                    data-participant-id="<?php echo esc_attr((string) $person->id); ?>"
                                    data-status="archived"
                                    data-source="<?php echo !empty($person->external_provider) && !empty($person->external_id) ? 'linked' : 'local'; ?>"
                                    data-photo="<?php echo $primary_image_id > 0 ? 'with-photo' : 'without-photo'; ?>">
                                    <td data-label="<?php echo esc_attr__('Mitglied', 'feuer-einsatzberichte'); ?>">
                                        <div class="feu-einsatz-participant-identity">
                                            <div class="feu-admin-participant-media">
                                            <?php if ($primary_image_id > 0) : ?>
                                                <?php echo wp_get_attachment_image($primary_image_id, [48, 48], false, ['class' => 'feu-einsatz-participant-thumb']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                            <?php else : ?>
                                                <span class="feu-einsatz-participant-thumb feu-einsatz-participant-thumb-placeholder">
                                                    <?php echo esc_html($initials); ?>
                                                </span>
                                            <?php endif; ?>
                                            </div>
                                            <div class="feu-einsatz-participant-identity-copy">
                                                <strong><?php echo esc_html(FEU_Einsatz_Template_Helpers::format_participant_name($person->vorname, $person->nachname)); ?></strong>
                                                <span class="feu-einsatz-participant-meta">
                                                    <span class="feu-einsatz-participant-state is-archived"><?php esc_html_e('Archiviert', 'feuer-einsatzberichte'); ?></span>
                                                    <?php if (!empty($person->external_provider) && !empty($person->external_id)) : ?>
                                                        <span class="feu-einsatz-participant-source"><?php esc_html_e('Feuer-Mannschaft', 'feuer-einsatzberichte'); ?></span>
                                                    <?php else : ?>
                                                        <span class="feu-einsatz-participant-source"><?php esc_html_e('Lokal', 'feuer-einsatzberichte'); ?></span>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td data-label="<?php echo esc_attr__('Dienstgrad', 'feuer-einsatzberichte'); ?>" class="feu-einsatz-participant-detail-cell">
                                        <span class="feu-einsatz-participant-detail-label"><?php esc_html_e('Dienstgrad', 'feuer-einsatzberichte'); ?></span>
                                        <?php if ('' !== trim((string) $person->rank_title)) : ?>
                                            <strong class="feu-einsatz-participant-detail-value"><?php echo esc_html($person->rank_title); ?></strong>
                                        <?php else : ?>
                                            <span class="feu-einsatz-participant-detail-empty" aria-label="<?php esc_attr_e('Nicht hinterlegt', 'feuer-einsatzberichte'); ?>">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="<?php echo esc_attr__('Position', 'feuer-einsatzberichte'); ?>" class="feu-einsatz-participant-detail-cell">
                                        <span class="feu-einsatz-participant-detail-label"><?php esc_html_e('Position', 'feuer-einsatzberichte'); ?></span>
                                        <?php if ('' !== trim((string) $person->job_title)) : ?>
                                            <strong class="feu-einsatz-participant-detail-value"><?php echo esc_html($person->job_title); ?></strong>
                                        <?php else : ?>
                                            <span class="feu-einsatz-participant-detail-empty" aria-label="<?php esc_attr_e('Nicht hinterlegt', 'feuer-einsatzberichte'); ?>">—</span>
                                        <?php endif; ?>
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
                                        <div class="feu-einsatz-participant-row-actions">
                                            <button
                                                type="button"
                                                class="button button-small feu-einsatz-edit-participant feu-einsatz-participant-edit-button"
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
                                                <span class="ti ti-pencil" aria-hidden="true"></span>
                                                <?php esc_html_e('Bearbeiten', 'feuer-einsatzberichte'); ?>
                                            </button>
                                            <details class="feu-einsatz-participant-action-menu">
                                                <summary aria-label="<?php echo esc_attr__('Weitere Aktionen', 'feuer-einsatzberichte'); ?>"><span class="ti ti-dots-vertical" aria-hidden="true"></span></summary>
                                                <div class="feu-einsatz-participant-action-popover">
                                                    <button type="button" class="feu-einsatz-toggle-archive" data-id="<?php echo esc_attr($person->id); ?>" data-archived="<?php echo esc_attr((int) $person->is_archived); ?>">
                                                        <span class="ti ti-refresh" aria-hidden="true"></span><?php esc_html_e('Aktivieren', 'feuer-einsatzberichte'); ?>
                                                    </button>
                                                    <?php if ($can_delete_participants) : ?>
                                                        <button type="button" class="feu-einsatz-delete-participant is-destructive" data-id="<?php echo esc_attr($person->id); ?>">
                                                            <span class="ti ti-trash" aria-hidden="true"></span><?php esc_html_e('Entfernen', 'feuer-einsatzberichte'); ?>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </details>
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

<div class="feu-einsatz-action-toast" data-feu-participant-toast role="status" aria-live="polite" hidden>
    <span data-feu-participant-toast-message></span>
    <button type="button" data-feu-participant-undo><?php esc_html_e('Rückgängig', 'feuer-einsatzberichte'); ?></button>
</div>

<script>
jQuery(document).ready(function($) {
    var participantActionMenus = document.querySelectorAll('.feu-einsatz-participant-action-menu');
    var participantFilter = 'all';
    var archiveReloadTimer = 0;

    function showArchiveToast(id, name) {
        var $toast = $('[data-feu-participant-toast]');
        $toast.find('[data-feu-participant-toast-message]').text(
            (name || '<?php echo esc_js(__('Mannschaftsmitglied', 'feuer-einsatzberichte')); ?>')
            + ' <?php echo esc_js(__('wurde archiviert.', 'feuer-einsatzberichte')); ?>'
        );
        $toast.removeAttr('hidden').addClass('is-visible');
        $toast.find('[data-feu-participant-undo]').off('click').on('click', function() {
            window.clearTimeout(archiveReloadTimer);
            $(this).prop('disabled', true);
            $.post(feu_einsatz_ajax.ajax_url, {
                action: 'feu_einsatz_toggle_participant_archive',
                nonce: feu_einsatz_ajax.nonce,
                id: id,
                archived: 0
            }).always(function() { location.reload(); });
        });
        archiveReloadTimer = window.setTimeout(function() { location.reload(); }, 7000);
    }

    participantActionMenus.forEach(function(menu) {
        menu.addEventListener('toggle', function() {
            if (!menu.open) {
                return;
            }
            participantActionMenus.forEach(function(otherMenu) {
                if (otherMenu !== menu) {
                    otherMenu.removeAttribute('open');
                }
            });
        });
    });

    $(document).on('click.feuParticipantActions', function(event) {
        if (!$(event.target).closest('.feu-einsatz-participant-action-menu').length) {
            participantActionMenus.forEach(function(menu) {
                menu.removeAttribute('open');
            });
        }
    });

    $(document).on('keydown.feuParticipantActions', function(event) {
        if (event.key !== 'Escape') {
            return;
        }
        participantActionMenus.forEach(function(menu) {
            if (menu.open) {
                menu.removeAttribute('open');
                var summary = menu.querySelector('summary');
                if (summary) {
                    summary.focus();
                }
            }
        });
    });

    function openParticipantDialog($dialog, trigger, focusSelector) {
        $dialog.data('returnFocus', trigger);
        $dialog.fadeIn(180, function() {
            $dialog.find(focusSelector).trigger('focus');
        });
    }

    function closeParticipantDialog($dialog) {
        var trigger = $dialog.data('returnFocus');
        $dialog.stop(true, true).fadeOut(180, function() {
            if (trigger && document.contains(trigger)) {
                $(trigger).trigger('focus');
            }
        });
    }

    $(document).on('keydown.feuParticipantDialog', function(event) {
        var $dialog = $('#feu-einsatz-add-participant-modal:visible, #feu-einsatz-edit-participant-modal:visible').first();
        if (!$dialog.length) {
            return;
        }
        if (event.key === 'Escape') {
            event.preventDefault();
            closeParticipantDialog($dialog);
            return;
        }
        if (event.key !== 'Tab') {
            return;
        }
        var $focusable = $dialog.find('a[href], button, input, select, textarea, [tabindex]:not([tabindex="-1"])')
            .filter(':visible:not(:disabled)');
        if (!$focusable.length) {
            event.preventDefault();
            return;
        }
        var first = $focusable[0];
        var last = $focusable[$focusable.length - 1];
        if (!$.contains($dialog[0], document.activeElement)) {
            event.preventDefault();
            $(event.shiftKey ? last : first).trigger('focus');
        } else if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            $(last).trigger('focus');
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            $(first).trigger('focus');
        }
    });
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
            openParticipantDialog($modal, this, '#add_vorname');
        }

        function closeModal() {
            closeParticipantDialog($modal);
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

        bindParticipantForm(
            '#feu-einsatz-add-participant-form',
            '<?php echo esc_js(__('Speichern', 'feuer-einsatzberichte')); ?>',
            '<?php echo esc_js(__('Wird gespeichert...', 'feuer-einsatzberichte')); ?>'
        );
    })();

    bindParticipantForm(
        '#feu-einsatz-edit-participant-form',
        '<?php echo esc_js(__('Änderungen speichern', 'feuer-einsatzberichte')); ?>',
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
                ? '<?php echo esc_js(__('Dieses Mannschaftsmitglied ist archiviert. Es bleibt in Statistik und alten Einsatzberichten sichtbar.', 'feuer-einsatzberichte')); ?>'
                : '<?php echo esc_js(__('Aktives Mannschaftsmitglied', 'feuer-einsatzberichte')); ?>'
        );

        fillDefaultFunctions($('#feu-einsatz-edit-participant-form'), defaultFunctions);
        fillExistingGalleryInputs($('#feu-einsatz-edit-gallery-existing'), galleryExisting);
        renderParticipantPreview($('#feu-einsatz-edit-photo-preview'), {
            imageUrl: $button.attr('data-primary-image-url') || '',
            initials: $button.attr('data-initials') || '--',
            photoCount: Number($button.attr('data-photo-count') || 0),
            name: fullName,
            statusText: isArchived
                ? '<?php echo esc_js(__('Archiviertes Mannschaftsmitglied', 'feuer-einsatzberichte')); ?>'
                : '<?php echo esc_js(__('Aktives Mannschaftsmitglied', 'feuer-einsatzberichte')); ?>'
        });

        openParticipantDialog($('#feu-einsatz-edit-participant-modal'), this, '#edit_vorname');
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
                    ? '<?php echo esc_js(__('Archiviertes Mannschaftsmitglied', 'feuer-einsatzberichte')); ?>'
                    : '<?php echo esc_js(__('Aktives Mannschaftsmitglied', 'feuer-einsatzberichte')); ?>'
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
            ? '<?php echo esc_js(__('Mitglied wieder aktivieren?', 'feuer-einsatzberichte')); ?>'
            : '<?php echo esc_js(__('Mitglied archivieren? Es bleibt in Statistik und bestehenden Einsatzberichten erhalten.', 'feuer-einsatzberichte')); ?>';

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
                    if (nextArchived === 1) {
                        var $row = $('.feu-einsatz-participant-table tr[data-participant-id="' + id + '"]');
                        var name = $.trim($row.find('.feu-einsatz-participant-identity-copy strong').first().text());
                        $row.addClass('is-pending-archive').attr('aria-disabled', 'true');
                        showArchiveToast(id, name);
                    } else {
                        location.reload();
                    }
                    return;
                }

                alert((response.data && response.data.message) ? response.data.message : '<?php echo esc_js(__('Status konnte nicht geändert werden.', 'feuer-einsatzberichte')); ?>');
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

        if (!confirm('<?php echo esc_js(__('Mannschaftsmitglied wirklich aus der Verwaltung entfernen? Die Statistik und bestehende Einsatzberichte bleiben erhalten.', 'feuer-einsatzberichte')); ?>')) {
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

                alert((response.data && response.data.message) ? response.data.message : '<?php echo esc_js(__('Mannschaftsmitglied konnte nicht entfernt werden.', 'feuer-einsatzberichte')); ?>');
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
        closeParticipantDialog($('#feu-einsatz-edit-participant-modal'));
    });

    $(window).on('click', function(e) {
        if ($(e.target).is('#feu-einsatz-edit-participant-modal')) {
            closeParticipantDialog($('#feu-einsatz-edit-participant-modal'));
        }
    });

    function filterParticipants() {
        var needle = $.trim($('#feu-einsatz-search-participant').val() || '').toLowerCase();
        $('.feu-einsatz-participant-table tr[data-participant-id]').each(function() {
            var $row = $(this);
            var textMatch = needle === '' || $row.text().toLowerCase().indexOf(needle) !== -1;
            var filterMatch = participantFilter === 'all'
                || $row.attr('data-status') === participantFilter
                || $row.attr('data-source') === participantFilter
                || $row.attr('data-photo') === participantFilter;
            $row.toggle(textMatch && filterMatch);
        });
        $('.feu-einsatz-participant-separator').toggle(participantFilter === 'all' || participantFilter === 'archived');
    }

    $('#feu-einsatz-search-participant').on('input', filterParticipants);
    $('[data-feu-participant-filter]').on('click', function() {
        participantFilter = $(this).attr('data-feu-participant-filter') || 'all';
        $('[data-feu-participant-filter]').removeClass('is-active').attr('aria-pressed', 'false');
        $(this).addClass('is-active').attr('aria-pressed', 'true');
        filterParticipants();
    });
});
</script>

<div id="feu-einsatz-add-participant-modal" role="dialog" aria-modal="true" aria-labelledby="feu-einsatz-add-participant-title" style="display:none;">
    <div class="feu-einsatz-modal-content feu-einsatz-wizard-modal">
        <div class="feu-einsatz-modal-header">
            <div>
                <h3 id="feu-einsatz-add-participant-title"><?php esc_html_e('Neue Mannschaftsakte anlegen', 'feuer-einsatzberichte'); ?></h3>
            </div>
            <button type="button" class="button-link feu-einsatz-add-modal-close" aria-label="<?php echo esc_attr__('Schließen', 'feuer-einsatzberichte'); ?>">
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
                <p class="description feu-einsatz-field-hint" style="margin-top:8px;"><?php esc_html_e('Bis zu 5 bevorzugte Funktionen für Einsatzberichte und Statistik.', 'feuer-einsatzberichte'); ?></p>
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

<div id="feu-einsatz-edit-participant-modal" role="dialog" aria-modal="true" aria-labelledby="feu-einsatz-edit-participant-title">
    <div class="feu-einsatz-modal-content">
        <div class="feu-einsatz-modal-header">
            <div>
                <h3 id="feu-einsatz-edit-participant-title"><?php esc_html_e('Mannschaftsmitglied bearbeiten', 'feuer-einsatzberichte'); ?></h3>
                <p class="description" id="feu-einsatz-edit-status-text"><?php esc_html_e('Aktives Mannschaftsmitglied', 'feuer-einsatzberichte'); ?></p>
            </div>
            <button type="button" class="button-link feu-einsatz-modal-close" aria-label="<?php echo esc_attr__('Schließen', 'feuer-einsatzberichte'); ?>">&times;</button>
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
                <button type="submit" class="button button-primary"><?php esc_html_e('Änderungen speichern', 'feuer-einsatzberichte'); ?></button>
                <button type="button" class="button feu-einsatz-modal-close"><?php esc_html_e('Abbrechen', 'feuer-einsatzberichte'); ?></button>
            </p>
        </form>
    </div>
</div>
