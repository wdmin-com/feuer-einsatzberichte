<?php
if (!defined('ABSPATH')) {
    exit;
}
$center_url = admin_url('admin.php?page=feu-einsatz-arbeitszentrale');
$tab_url = static fn($value) => add_query_arg('tab', $value, $center_url);
$status_labels = [
    'pending' => __('Wartet auf Prüfung', 'feuer-einsatzberichte'),
    'draft' => __('Entwurf', 'feuer-einsatzberichte'),
    'future' => __('Geplant', 'feuer-einsatzberichte'),
    'publish' => __('Veröffentlicht', 'feuer-einsatzberichte'),
];
$retention_years = (int) get_option('feu_einsatz_participant_retention_years', 0);
$retention_cutoff = $retention_years > 0 ? strtotime('-' . $retention_years . ' years', current_time('timestamp')) : 0;
?>
<div class="wrap feu-einsatz-operations">
    <div class="feu-einsatz-operations-hero">
        <div>
            <span class="feu-einsatz-operations-eyebrow"><?php esc_html_e('Einsatzberichte · Redaktion', 'feuer-einsatzberichte'); ?></span>
            <h1><?php esc_html_e('Arbeitszentrale', 'feuer-einsatzberichte'); ?></h1>
            <p><?php esc_html_e('Berichte, Hintergrundaufgaben und Teilnehmerdaten an einem Ort prüfen.', 'feuer-einsatzberichte'); ?></p>
        </div>
        <?php if (FEU_Einsatz_Admin::current_user_can_access_plugin_section('create_report')) : ?>
            <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=feu-einsatz-neuer-bericht')); ?>"><?php esc_html_e('Neuen Bericht erstellen', 'feuer-einsatzberichte'); ?></a>
        <?php endif; ?>
    </div>
    <nav class="feu-einsatz-operations-tabs" aria-label="<?php esc_attr_e('Bereiche der Arbeitszentrale', 'feuer-einsatzberichte'); ?>">
        <a href="<?php echo esc_url($tab_url('status')); ?>" <?php if ('status' === $tab) : ?>aria-current="page"<?php endif; ?>><?php esc_html_e('Systemstatus', 'feuer-einsatzberichte'); ?></a>
        <a href="<?php echo esc_url($tab_url('queue')); ?>" <?php if ('queue' === $tab) : ?>aria-current="page"<?php endif; ?>><?php esc_html_e('Redaktionsliste', 'feuer-einsatzberichte'); ?></a>
        <?php if (current_user_can('manage_options')) : ?>
            <a href="<?php echo esc_url($tab_url('privacy')); ?>" <?php if ('privacy' === $tab) : ?>aria-current="page"<?php endif; ?>><?php esc_html_e('Teilnehmerdaten', 'feuer-einsatzberichte'); ?></a>
        <?php endif; ?>
    </nav>

    <?php if ('status' === $tab) : ?>
        <?php if (isset($_GET['job_result'])) : ?>
            <div class="notice <?php echo 'queued' === sanitize_key(wp_unslash($_GET['job_result'])) ? 'notice-success' : 'notice-error'; ?>"><p><?php echo 'queued' === sanitize_key(wp_unslash($_GET['job_result'])) ? esc_html__('Aufgabe wurde erneut eingeplant.', 'feuer-einsatzberichte') : esc_html__('Aufgabe konnte nicht eingeplant werden. Bericht prüfen.', 'feuer-einsatzberichte'); ?></p></div>
        <?php endif; ?>
        <section class="feu-einsatz-operations-summary" aria-label="<?php esc_attr_e('Aufgabenübersicht', 'feuer-einsatzberichte'); ?>">
            <?php foreach ([
                'error' => __('Fehler', 'feuer-einsatzberichte'),
                'delayed' => __('Verspätet', 'feuer-einsatzberichte'),
                'queued' => __('In Bearbeitung', 'feuer-einsatzberichte'),
                'ready' => __('Bereit', 'feuer-einsatzberichte'),
            ] as $key => $label) : ?>
                <div class="feu-einsatz-operations-kpi feu-einsatz-operations-kpi--<?php echo esc_attr($key); ?>"><span><?php echo esc_html($label); ?></span><strong><?php echo esc_html((string) ($status_overview['counts'][$key] ?? 0)); ?></strong></div>
            <?php endforeach; ?>
        </section>
        <p class="description"><?php echo esc_html(sprintf(__('Übersicht über die letzten %d Berichte. Fehler werden pro Aufgabe isoliert; andere Berichte laufen weiter.', 'feuer-einsatzberichte'), (int) $status_overview['sample_size'])); ?></p>
        <section class="feu-einsatz-operations-panel">
            <div class="feu-einsatz-operations-panel-heading"><div><h2><?php esc_html_e('Hintergrundaufgaben', 'feuer-einsatzberichte'); ?></h2><p><?php esc_html_e('Kartenbild, Link-Vorschaubild und geschützte Fotos werden unabhängig voneinander verarbeitet.', 'feuer-einsatzberichte'); ?></p></div></div>
            <?php if (!$status_overview['items']) : ?>
                <p class="feu-einsatz-operations-empty"><?php esc_html_e('Noch keine Aufgaben für die letzten Berichte vorhanden.', 'feuer-einsatzberichte'); ?></p>
            <?php endif; ?>
            <div class="feu-einsatz-operations-task-list">
                <?php foreach ($status_overview['items'] as $entry) : ?>
                    <?php $report = $entry['post']; ?>
                    <article class="feu-einsatz-operations-report">
                        <div class="feu-einsatz-operations-report-heading"><h3><a href="<?php echo esc_url($this->get_edit_url((int) $report->ID)); ?>"><?php echo esc_html(get_the_title($report)); ?></a></h3><span>#<?php echo esc_html((string) $report->ID); ?></span></div>
                        <div class="feu-einsatz-operations-jobs">
                            <?php foreach ($entry['jobs'] as $job) : ?>
                                <div class="feu-einsatz-operations-job">
                                    <span class="feu-einsatz-operations-job-state is-<?php echo esc_attr($job['status']); ?>"><?php echo esc_html($job['label']); ?></span>
                                    <span><?php echo esc_html($job['message']); ?></span>
                                    <?php if ($job['retryable'] && current_user_can('edit_post', (int) $report->ID)) : ?>
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                            <input type="hidden" name="action" value="feu_einsatz_retry_job" />
                                            <input type="hidden" name="post_id" value="<?php echo esc_attr((string) $report->ID); ?>" />
                                            <input type="hidden" name="job" value="<?php echo esc_attr($job['kind']); ?>" />
                                            <?php wp_nonce_field('feu_einsatz_retry_job_' . (int) $report->ID . '_' . $job['kind']); ?>
                                            <button type="submit" class="button button-small"><?php esc_html_e('Erneut versuchen', 'feuer-einsatzberichte'); ?></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

    <?php elseif ('queue' === $tab) : ?>
        <section class="feu-einsatz-operations-panel">
            <div class="feu-einsatz-operations-panel-heading"><div><h2><?php esc_html_e('Redaktionsliste', 'feuer-einsatzberichte'); ?></h2><p><?php esc_html_e('Prüfen Sie einen Bericht im Plugin, bevor Sie ihn freigeben.', 'feuer-einsatzberichte'); ?></p></div></div>
            <nav class="feu-einsatz-operations-filters" aria-label="<?php esc_attr_e('Berichte nach Status', 'feuer-einsatzberichte'); ?>">
                <?php foreach (['pending' => __('Zur Prüfung', 'feuer-einsatzberichte'), 'draft' => __('Entwürfe', 'feuer-einsatzberichte'), 'future' => __('Geplant', 'feuer-einsatzberichte'), 'publish' => __('Veröffentlicht', 'feuer-einsatzberichte'), 'all' => __('Alle', 'feuer-einsatzberichte')] as $key => $label) : ?>
                    <a class="<?php echo $queue_status === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg(['tab' => 'queue', 'report_status' => $key], $center_url)); ?>" <?php if ($queue_status === $key) : ?>aria-current="page"<?php endif; ?>><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </nav>
            <?php if (!$queue->posts) : ?><p class="feu-einsatz-operations-empty"><?php esc_html_e('Keine Berichte mit diesem Status.', 'feuer-einsatzberichte'); ?></p><?php endif; ?>
            <div class="feu-einsatz-operations-queue">
                <?php foreach ($queue->posts as $report) : ?>
                    <?php if (!($report instanceof WP_Post)) continue; ?>
                    <article class="feu-einsatz-operations-queue-row">
                        <div><h3><?php echo esc_html(get_the_title($report)); ?></h3><p><?php echo esc_html(get_the_author_meta('display_name', (int) $report->post_author)); ?> · <?php echo esc_html(get_the_modified_date(get_option('date_format'), $report)); ?></p></div>
                        <span class="feu-einsatz-operations-status is-<?php echo esc_attr($report->post_status); ?>"><?php echo esc_html($status_labels[$report->post_status] ?? $report->post_status); ?></span>
                        <div class="feu-einsatz-operations-actions">
                            <?php if (current_user_can('edit_post', (int) $report->ID)) : ?><a class="button button-small" href="<?php echo esc_url($this->get_edit_url((int) $report->ID)); ?>"><?php echo esc_html('pending' === $report->post_status ? __('Prüfen', 'feuer-einsatzberichte') : __('Bearbeiten', 'feuer-einsatzberichte')); ?></a><?php endif; ?>
                            <?php if ('publish' === $report->post_status) : ?><a class="button button-small" href="<?php echo esc_url(get_permalink($report)); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Ansehen', 'feuer-einsatzberichte'); ?></a><?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php if ($queue->max_num_pages > 1) : ?><div class="tablenav"><div class="tablenav-pages"><?php echo wp_kses_post(paginate_links(['base' => add_query_arg(['tab' => 'queue', 'report_status' => $queue_status, 'paged' => '%#%'], $center_url), 'current' => $page, 'total' => (int) $queue->max_num_pages])); ?></div></div><?php endif; ?>
        </section>

    <?php else : ?>
        <section class="feu-einsatz-operations-panel">
            <div class="feu-einsatz-operations-panel-heading"><div><h2><?php esc_html_e('Teilnehmerdaten', 'feuer-einsatzberichte'); ?></h2><p><?php esc_html_e('Die Teilnehmer sind Einsatzpersonen im Plugin und keine WordPress-Benutzerkonten.', 'feuer-einsatzberichte'); ?></p></div></div>
            <?php if (isset($_GET['saved'])) : ?><div class="notice notice-success"><p><?php esc_html_e('Datenschutz-Einstellungen gespeichert.', 'feuer-einsatzberichte'); ?></p></div><?php endif; ?>
            <?php if (isset($_GET['anonymized'])) : ?><div class="notice notice-success"><p><?php esc_html_e('Teilnehmerprofil anonymisiert. Berichtstexte und Fotos bitte separat prüfen.', 'feuer-einsatzberichte'); ?></p></div><?php endif; ?>
            <form class="feu-einsatz-operations-privacy-settings" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="feu_einsatz_privacy_settings" /><?php wp_nonce_field('feu_einsatz_privacy_settings'); ?>
                <label><input type="checkbox" name="public_names" value="1" <?php checked(1, (int) get_option('feu_einsatz_public_participant_names', 1)); ?> /> <?php esc_html_e('Teilnehmernamen in öffentlichen Einsatzberichten anzeigen', 'feuer-einsatzberichte'); ?></label>
                <label><?php esc_html_e('Aufbewahrung prüfen nach', 'feuer-einsatzberichte'); ?> <select name="retention_years"><?php foreach ([0, 1, 2, 3, 5, 10, 15, 20] as $years) : ?><option value="<?php echo esc_attr((string) $years); ?>" <?php selected($years, (int) get_option('feu_einsatz_participant_retention_years', 0)); ?>><?php echo $years ? esc_html(sprintf(__('%d Jahren', 'feuer-einsatzberichte'), $years)) : esc_html__('manueller Entscheidung', 'feuer-einsatzberichte'); ?></option><?php endforeach; ?></select></label>
                <p class="description"><?php esc_html_e('Die Frist kennzeichnet alte Profile zur manuellen Prüfung. Es werden keine Teilnehmer automatisch gelöscht.', 'feuer-einsatzberichte'); ?></p>
                <button class="button button-primary" type="submit"><?php esc_html_e('Einstellungen speichern', 'feuer-einsatzberichte'); ?></button>
            </form>
            <form method="get" class="feu-einsatz-operations-search"><input type="hidden" name="page" value="feu-einsatz-arbeitszentrale" /><input type="hidden" name="tab" value="privacy" /><label for="feu-operations-search"><?php esc_html_e('Teilnehmer suchen', 'feuer-einsatzberichte'); ?></label><input id="feu-operations-search" name="s" value="<?php echo esc_attr($privacy_search); ?>" /><button type="submit" class="button"><?php esc_html_e('Suchen', 'feuer-einsatzberichte'); ?></button></form>
            <div class="feu-einsatz-operations-queue">
                <?php foreach ($participants['items'] as $participant) : ?>
                    <?php $participant_id = (int) $participant->id; ?>
                    <article class="feu-einsatz-operations-queue-row">
                        <div><h3><?php echo esc_html(trim($participant->vorname . ' ' . $participant->nachname)); ?></h3><p>#<?php echo esc_html((string) $participant_id); ?> · <?php echo !empty($participant->is_archived) ? esc_html__('Archiviert', 'feuer-einsatzberichte') : esc_html__('Aktiv', 'feuer-einsatzberichte'); ?><?php if ($retention_cutoff && !empty($participant->is_archived) && !empty($participant->last_activity) && strtotime((string) $participant->last_activity) < $retention_cutoff) : ?> · <strong><?php esc_html_e('Aufbewahrung prüfen', 'feuer-einsatzberichte'); ?></strong><?php endif; ?></p></div>
                        <div class="feu-einsatz-operations-actions">
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="feu_einsatz_participant_data" /><input type="hidden" name="participant_id" value="<?php echo esc_attr((string) $participant_id); ?>" /><input type="hidden" name="operation" value="export" /><?php wp_nonce_field('feu_einsatz_participant_data_' . $participant_id . '_export'); ?><button type="submit" class="button button-small"><?php esc_html_e('JSON exportieren', 'feuer-einsatzberichte'); ?></button></form>
                            <?php if (empty($participant->external_provider)) : ?><details class="feu-einsatz-operations-anonymize"><summary><?php esc_html_e('Anonymisieren', 'feuer-einsatzberichte'); ?></summary><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><p><?php esc_html_e('Name und Profilfelder entfernen; historische Einsätze und Funktionen bleiben erhalten. Texte und Mediendateien separat prüfen.', 'feuer-einsatzberichte'); ?></p><input type="hidden" name="action" value="feu_einsatz_participant_data" /><input type="hidden" name="participant_id" value="<?php echo esc_attr((string) $participant_id); ?>" /><input type="hidden" name="operation" value="anonymize" /><?php wp_nonce_field('feu_einsatz_participant_data_' . $participant_id . '_anonymize'); ?><label><?php esc_html_e('ANONYMISIEREN eingeben', 'feuer-einsatzberichte'); ?> <input name="confirm" autocomplete="off" required /></label><button type="submit" class="button button-small"><?php esc_html_e('Profil anonymisieren', 'feuer-einsatzberichte'); ?></button></form></details><?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php if (!$participants['items']) : ?><p class="feu-einsatz-operations-empty"><?php esc_html_e('Keine Teilnehmer gefunden.', 'feuer-einsatzberichte'); ?></p><?php endif; ?>
            <?php if ($participants['total'] > 20) : ?><div class="tablenav"><div class="tablenav-pages"><?php echo wp_kses_post(paginate_links(['base' => add_query_arg(['tab' => 'privacy', 's' => $privacy_search, 'paged' => '%#%'], $center_url), 'current' => $page, 'total' => (int) ceil($participants['total'] / 20)])); ?></div></div><?php endif; ?>
        </section>
    <?php endif; ?>
</div>
