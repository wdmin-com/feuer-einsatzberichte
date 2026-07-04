<?php
if (!defined('ABSPATH')) {
    exit;
}

$backup_manager = FEU_Einsatz_Backup_Manager::get_instance();
$archives = $backup_manager ? $backup_manager->get_archives() : [];
$retention_limit = max(1, absint(get_option('feu_einsatz_backup_retention_limit', 5)));

if (!function_exists('feu_einsatz_render_archive_notice')) {
    function feu_einsatz_render_archive_notice($message, $type = 'success') {
        if ('' === trim((string) $message)) {
            return;
        }

        echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }
}

if (!empty($_GET['archive_error'])) {
    feu_einsatz_render_archive_notice(rawurldecode((string) wp_unslash($_GET['archive_error'])), 'error');
}

if (!empty($_GET['archive_success'])) {
    feu_einsatz_render_archive_notice(__('Archiv wurde erstellt.', 'feuer-einsatzberichte'));
}

if (!empty($_GET['archive_uploaded'])) {
    feu_einsatz_render_archive_notice(__('Archiv wurde hochgeladen.', 'feuer-einsatzberichte'));
}

if (!empty($_GET['archive_restored'])) {
    feu_einsatz_render_archive_notice(__('Archiv wurde wiederhergestellt.', 'feuer-einsatzberichte'));
}

if (!empty($_GET['archive_deleted'])) {
    feu_einsatz_render_archive_notice(__('Archiv wurde gelöscht.', 'feuer-einsatzberichte'));
}
?>

<div class="wrap feu-einsatz-archive-page feu-admin-page">
    <div class="feu-admin-page-header">
        <div class="feu-admin-page-heading">
            <span class="feu-admin-page-eyebrow"><?php esc_html_e('Sicherung & Wiederherstellung', 'feuer-einsatzberichte'); ?></span>
            <h1><?php esc_html_e('Archive', 'feuer-einsatzberichte'); ?></h1>
        </div>
    </div>
    <p class="description"><?php esc_html_e('Hier können komplette Plugin-Archive erstellt, hochgeladen, wiederhergestellt und gelöscht werden. Ein Archiv enthält Einstellungen, Einsatzberichte, Bilder, Karten-Daten, Cache und Protokolle.', 'feuer-einsatzberichte'); ?></p>

    <div class="feu-einsatz-archive-grid">
        <section class="feu-einsatz-archive-card">
            <h2><?php esc_html_e('Neues Archiv erstellen', 'feuer-einsatzberichte'); ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="feu_einsatz_create_archive" />
                <?php wp_nonce_field('feu_einsatz_create_archive'); ?>
                <p>
                    <label for="feu_einsatz_archive_label"><strong><?php esc_html_e('Bezeichnung', 'feuer-einsatzberichte'); ?></strong></label><br />
                    <input type="text" class="regular-text" id="feu_einsatz_archive_label" name="feu_einsatz_archive_label" placeholder="<?php esc_attr_e('z.B. Stand vor grossem Update', 'feuer-einsatzberichte'); ?>" />
                </p>
                <p class="description"><?php echo esc_html(sprintf(__('Es werden maximal %d Archive aufbewahrt. Aeltere Dateien werden automatisch entfernt.', 'feuer-einsatzberichte'), $retention_limit)); ?></p>
                <p><button type="submit" class="button button-primary"><?php esc_html_e('Archiv erstellen', 'feuer-einsatzberichte'); ?></button></p>
            </form>
        </section>

        <section class="feu-einsatz-archive-card">
            <h2><?php esc_html_e('Archiv hochladen', 'feuer-einsatzberichte'); ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <input type="hidden" name="action" value="feu_einsatz_upload_archive" />
                <?php wp_nonce_field('feu_einsatz_upload_archive'); ?>
                <p>
                    <label for="feu_einsatz_archive_file"><strong><?php esc_html_e('ZIP-Datei', 'feuer-einsatzberichte'); ?></strong></label><br />
                    <input type="file" id="feu_einsatz_archive_file" name="feu_einsatz_archive_file" accept=".zip" required />
                </p>
                <p class="description"><?php esc_html_e('Hochgeladene Archive erscheinen unten in der Liste und können danach wiederhergestellt oder heruntergeladen werden.', 'feuer-einsatzberichte'); ?></p>
                <p><button type="submit" class="button"><?php esc_html_e('Archiv hochladen', 'feuer-einsatzberichte'); ?></button></p>
            </form>
        </section>
    </div>

    <section class="feu-einsatz-archive-card">
        <h2><?php esc_html_e('Vorhandene Archive', 'feuer-einsatzberichte'); ?></h2>

        <?php if (empty($archives)) : ?>
            <p class="description"><?php esc_html_e('Aktuell sind keine Archive vorhanden.', 'feuer-einsatzberichte'); ?></p>
        <?php else : ?>
            <div class="feu-einsatz-table-scroll">
                <table class="wp-list-table widefat striped feu-admin-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Archiv', 'feuer-einsatzberichte'); ?></th>
                            <th><?php esc_html_e('Erstellt am', 'feuer-einsatzberichte'); ?></th>
                            <th><?php esc_html_e('Von', 'feuer-einsatzberichte'); ?></th>
                            <th><?php esc_html_e('Inhalt', 'feuer-einsatzberichte'); ?></th>
                            <th><?php esc_html_e('Aktionen', 'feuer-einsatzberichte'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($archives as $archive) : ?>
                            <?php $summary = !empty($archive->manifest) ? json_decode((string) $archive->manifest, true) : []; ?>
                            <tr>
                                <td data-label="<?php echo esc_attr__('Archiv', 'feuer-einsatzberichte'); ?>">
                                    <strong><?php echo esc_html($archive->label ?: $archive->filename); ?></strong><br />
                                    <code><?php echo esc_html($archive->filename); ?></code><br />
                                    <span class="description"><?php echo esc_html(size_format((int) $archive->file_size)); ?></span>
                                </td>
                                <td data-label="<?php echo esc_attr__('Erstellt am', 'feuer-einsatzberichte'); ?>">
                                    <?php echo esc_html(date_i18n('d.m.Y H:i', strtotime((string) $archive->created_at))); ?>
                                    <?php if (!empty($archive->restored_at)) : ?>
                                        <br /><span class="description"><?php echo esc_html(sprintf(__('Zuletzt wiederhergestellt: %s', 'feuer-einsatzberichte'), date_i18n('d.m.Y H:i', strtotime((string) $archive->restored_at)))); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="<?php echo esc_attr__('Von', 'feuer-einsatzberichte'); ?>">
                                    <?php echo esc_html($archive->created_by_name ?: __('Unbekannt', 'feuer-einsatzberichte')); ?><br />
                                    <span class="description"><?php echo esc_html($archive->source); ?></span>
                                </td>
                                <td data-label="<?php echo esc_attr__('Inhalt', 'feuer-einsatzberichte'); ?>">
                                    <div class="feu-einsatz-archive-summary">
                                        <span><?php echo esc_html(sprintf(__('Berichte: %d', 'feuer-einsatzberichte'), isset($summary['reports']) ? (int) $summary['reports'] : 0)); ?></span>
                                        <span><?php echo esc_html(sprintf(__('Kommentare: %d', 'feuer-einsatzberichte'), isset($summary['comments']) ? (int) $summary['comments'] : 0)); ?></span>
                                        <span><?php echo esc_html(sprintf(__('Anhaenge: %d', 'feuer-einsatzberichte'), isset($summary['attachments']) ? (int) $summary['attachments'] : 0)); ?></span>
                                        <span><?php echo esc_html(sprintf(__('Statistik-Cache: %d', 'feuer-einsatzberichte'), isset($summary['statistics_cache']) ? (int) $summary['statistics_cache'] : 0)); ?></span>
                                        <span><?php echo esc_html(sprintf(__('Logs: %d', 'feuer-einsatzberichte'), isset($summary['logs']) ? (int) $summary['logs'] : 0)); ?></span>
                                    </div>
                                </td>
                                <td data-label="<?php echo esc_attr__('Aktionen', 'feuer-einsatzberichte'); ?>" class="feu-einsatz-table-actions">
                                    <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=feu_einsatz_download_archive&archive_id=' . (int) $archive->id), 'feu_einsatz_download_archive')); ?>">
                                        <?php esc_html_e('Download', 'feuer-einsatzberichte'); ?>
                                    </a>
                                    <a class="button button-primary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=feu_einsatz_restore_archive&archive_id=' . (int) $archive->id), 'feu_einsatz_restore_archive')); ?>" onclick="return confirm('<?php echo esc_js(__('Dieses Archiv ersetzt die aktuellen Plugin-Daten. Wirklich fortfahren?', 'feuer-einsatzberichte')); ?>');">
                                        <?php esc_html_e('Wiederherstellen', 'feuer-einsatzberichte'); ?>
                                    </a>
                                    <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=feu_einsatz_delete_archive&archive_id=' . (int) $archive->id), 'feu_einsatz_delete_archive')); ?>" onclick="return confirm('<?php echo esc_js(__('Archiv wirklich loeschen?', 'feuer-einsatzberichte')); ?>');">
                                        <?php esc_html_e('Loeschen', 'feuer-einsatzberichte'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

