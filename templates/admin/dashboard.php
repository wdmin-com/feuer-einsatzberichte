<?php
if (!defined('ABSPATH')) {
    exit;
}

$total_einsaetze = (int) $this->db->count_report_posts();
$total_teilnehmer = count($this->db->get_participants([
    'include_archived' => false,
    'include_deleted' => false,
]));
$current_year = (int) current_time('Y');
$einsaetze_dieses_jahr_count = (int) $this->db->count_report_posts($current_year);
$selected_categories = get_option('feu_einsatz_categories', []);
$kategorien_count = count((array) $selected_categories);
$letzte_einsaetze = (array) $this->db->get_recent_reports(5);
$quick_links = [
    [
        'url' => admin_url('admin.php?page=feu-einsatz-neuer-bericht'),
        'label' => __('Neuen Einsatzbericht', 'feuer-einsatzberichte'),
        'description' => __('Vollstaendige Bearbeitung mit Karten, Medien und Teilnehmern.', 'feuer-einsatzberichte'),
        'icon' => 'ti ti-file-plus',
    ],
    [
        'url' => admin_url('admin.php?page=feu-einsatz-schnelleingabe'),
        'label' => __('Schnelleingabe', 'feuer-einsatzberichte'),
        'description' => __('Mobile Kurzerfassung direkt aus dem Dashboard.', 'feuer-einsatzberichte'),
        'icon' => 'ti ti-device-mobile-plus',
    ],
    [
        'url' => admin_url('admin.php?page=feu-einsatz-statistiken'),
        'label' => __('Statistiken', 'feuer-einsatzberichte'),
        'description' => __('Kennzahlen, Kalender, Aktivitaetskarte und Ranking.', 'feuer-einsatzberichte'),
        'icon' => 'ti ti-chart-donut-3',
    ],
    [
        'url' => admin_url('admin.php?page=feu-einsatz-einstellungen'),
        'label' => __('Einstellungen', 'feuer-einsatzberichte'),
        'description' => __('Layout, Karten, Rollen, Medien und Social Share steuern.', 'feuer-einsatzberichte'),
        'icon' => 'ti ti-settings',
    ],
];
?>

<div class="wrap feu-einsatz-dashboard feu-admin-page">
    <div class="feu-admin-page-header">
        <div class="feu-admin-page-heading">
            <span class="feu-admin-page-eyebrow"><?php esc_html_e('Plugin-Administration', 'feuer-einsatzberichte'); ?></span>
            <h1><?php esc_html_e('Einsatzberichte Dashboard', 'feuer-einsatzberichte'); ?></h1>
            <p><?php esc_html_e('Schneller Zugriff auf Redaktionsablauf, Kennzahlen und die letzten Einsatzberichte.', 'feuer-einsatzberichte'); ?></p>
        </div>
        <div class="feu-admin-page-actions">
            <a href="<?php echo esc_url(admin_url('edit.php?post_type=post&feu_einsatz_filter=1')); ?>" class="button button-secondary">
                <span class="ti ti-list-details"></span>
                <?php esc_html_e('Alle Berichte', 'feuer-einsatzberichte'); ?>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=feu-einsatz-neuer-bericht')); ?>" class="button button-primary">
                <span class="ti ti-plus"></span>
                <?php esc_html_e('Neuer Bericht', 'feuer-einsatzberichte'); ?>
            </a>
        </div>
    </div>

    <div class="feu-admin-stat-grid">
        <article class="feu-admin-stat-card">
            <div class="feu-admin-stat-icon"><span class="ti ti-alarm"></span></div>
            <div class="feu-admin-stat-content">
                <span class="feu-admin-stat-label"><?php esc_html_e('Einsaetze gesamt', 'feuer-einsatzberichte'); ?></span>
                <strong class="feu-admin-stat-value"><?php echo esc_html($total_einsaetze); ?></strong>
            </div>
        </article>
        <article class="feu-admin-stat-card">
            <div class="feu-admin-stat-icon"><span class="ti ti-calendar-stats"></span></div>
            <div class="feu-admin-stat-content">
                <span class="feu-admin-stat-label"><?php echo esc_html(sprintf(__('Einsaetze %d', 'feuer-einsatzberichte'), $current_year)); ?></span>
                <strong class="feu-admin-stat-value"><?php echo esc_html($einsaetze_dieses_jahr_count); ?></strong>
            </div>
        </article>
        <article class="feu-admin-stat-card">
            <div class="feu-admin-stat-icon"><span class="ti ti-users-group"></span></div>
            <div class="feu-admin-stat-content">
                <span class="feu-admin-stat-label"><?php esc_html_e('Aktive Teilnehmer', 'feuer-einsatzberichte'); ?></span>
                <strong class="feu-admin-stat-value"><?php echo esc_html($total_teilnehmer); ?></strong>
            </div>
        </article>
        <article class="feu-admin-stat-card">
            <div class="feu-admin-stat-icon"><span class="ti ti-tags"></span></div>
            <div class="feu-admin-stat-content">
                <span class="feu-admin-stat-label"><?php esc_html_e('Aktive Kategorien', 'feuer-einsatzberichte'); ?></span>
                <strong class="feu-admin-stat-value"><?php echo esc_html($kategorien_count); ?></strong>
            </div>
        </article>
    </div>

    <?php
    $map_queue_overview = $this->get_generated_map_dashboard_overview(6, 30);
    $map_queue_items = isset($map_queue_overview['items']) && is_array($map_queue_overview['items']) ? $map_queue_overview['items'] : [];
    $map_queue_counters = isset($map_queue_overview['counters']) && is_array($map_queue_overview['counters']) ? $map_queue_overview['counters'] : ['geocode' => 0, 'map' => 0, 'error' => 0, 'total' => 0];
    ?>

    <section class="feu-admin-card">
        <div class="feu-admin-card-header">
            <div>
                <h2><?php esc_html_e('Kartenbild-Status', 'feuer-einsatzberichte'); ?></h2>
                <p><?php esc_html_e('Zeigt, ob ein Bericht auf Geocoding, Kartenbild oder manuelle Korrektur wartet.', 'feuer-einsatzberichte'); ?></p>
            </div>
            <span class="feu-einsatz-dashboard-mapqueue-count"><?php echo esc_html((string) ($map_queue_counters['total'] ?? 0)); ?></span>
        </div>
        <div class="feu-einsatz-dashboard-mapqueue-summary">
            <span class="feu-einsatz-dashboard-mapqueue-chip feu-einsatz-dashboard-mapqueue-chip--geocode"><?php echo esc_html(sprintf(__('Geocoding: %d', 'feuer-einsatzberichte'), (int) ($map_queue_counters['geocode'] ?? 0))); ?></span>
            <span class="feu-einsatz-dashboard-mapqueue-chip feu-einsatz-dashboard-mapqueue-chip--map"><?php echo esc_html(sprintf(__('Kartenbild: %d', 'feuer-einsatzberichte'), (int) ($map_queue_counters['map'] ?? 0))); ?></span>
            <span class="feu-einsatz-dashboard-mapqueue-chip feu-einsatz-dashboard-mapqueue-chip--error"><?php echo esc_html(sprintf(__('Fehler: %d', 'feuer-einsatzberichte'), (int) ($map_queue_counters['error'] ?? 0))); ?></span>
        </div>
        <?php if (!empty($map_queue_items)) : ?>
        <div class="feu-einsatz-dashboard-mapqueue-list">
            <?php foreach ($map_queue_items as $_mq_item) :
                $_mq_post = $_mq_item['post'];
                $_mq_st   = $_mq_item['status'];
                $_mq_id   = (int) $_mq_post->ID;
                $_mq_bucket = sanitize_key((string) ($_mq_st['bucket'] ?? 'idle'));
                $_mq_label = (string) ($_mq_st['dashboard_label'] ?? __('Status', 'feuer-einsatzberichte'));
            ?>
                <div class="feu-einsatz-dashboard-mapqueue-item">
                    <div class="feu-einsatz-dashboard-mapqueue-main">
                        <div class="feu-einsatz-dashboard-mapqueue-head">
                            <a class="feu-einsatz-dashboard-mapqueue-title" href="<?php echo esc_url(get_edit_post_link($_mq_id)); ?>"><?php echo esc_html(get_the_title($_mq_id)); ?></a>
                            <span class="feu-einsatz-dashboard-mapqueue-status feu-einsatz-dashboard-mapqueue-status--<?php echo esc_attr($_mq_bucket); ?>"><?php echo esc_html($_mq_label); ?></span>
                        </div>
                        <div class="feu-einsatz-dashboard-mapqueue-meta"><?php echo esc_html((string) ($_mq_st['message'] ?? '')); ?></div>
                    </div>
                    <div class="feu-einsatz-dashboard-mapqueue-actions">
                        <a class="button button-secondary button-small" href="<?php echo esc_url(get_edit_post_link($_mq_id)); ?>"><?php esc_html_e('Bearbeiten', 'feuer-einsatzberichte'); ?></a>
                        <form class="feu-einsatz-dashboard-inline-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('feu_einsatz_generate_map_image_now_' . $_mq_id); ?>
                            <input type="hidden" name="action" value="feu_einsatz_generate_map_image_now" />
                            <input type="hidden" name="post_id" value="<?php echo esc_attr((string) $_mq_id); ?>" />
                            <input type="hidden" name="redirect_to" value="<?php echo esc_attr(admin_url('admin.php?page=feuer-einsatzberichte')); ?>" />
                            <button type="submit" class="button button-primary button-small"><?php esc_html_e('Jetzt generieren', 'feuer-einsatzberichte'); ?></button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php else : ?>
            <p class="feu-einsatz-dashboard-widget-note"><?php esc_html_e('Aktuell warten keine Berichte auf Geocoding oder Kartenbild-Generierung.', 'feuer-einsatzberichte'); ?></p>
        <?php endif; ?>
    </section>

    <div class="feu-admin-two-column">
        <section class="feu-admin-card">
            <div class="feu-admin-card-header">
                <div>
                    <h2><?php esc_html_e('Schnellzugriff', 'feuer-einsatzberichte'); ?></h2>
                    <p><?php esc_html_e('Die wichtigsten Bereiche des Plugins ohne Umwege erreichen.', 'feuer-einsatzberichte'); ?></p>
                </div>
            </div>
            <div class="feu-admin-action-grid">
                <?php foreach ($quick_links as $link) : ?>
                    <a href="<?php echo esc_url($link['url']); ?>" class="feu-admin-action-card">
                        <span class="feu-admin-action-icon <?php echo esc_attr($link['icon']); ?>"></span>
                        <strong><?php echo esc_html($link['label']); ?></strong>
                        <span><?php echo esc_html($link['description']); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="feu-admin-card">
            <div class="feu-admin-card-header">
                <div>
                    <h2><?php esc_html_e('Letzte Einsatzberichte', 'feuer-einsatzberichte'); ?></h2>
                    <p><?php esc_html_e('Direkter Zugriff auf die juengsten Einsaetze inklusive Bearbeitung.', 'feuer-einsatzberichte'); ?></p>
                </div>
            </div>

            <?php if (!empty($letzte_einsaetze)) : ?>
                <div class="feu-admin-table-wrap">
                    <table class="wp-list-table widefat fixed striped feu-admin-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Datum', 'feuer-einsatzberichte'); ?></th>
                                <th><?php esc_html_e('Titel', 'feuer-einsatzberichte'); ?></th>
                                <th><?php esc_html_e('Strasse', 'feuer-einsatzberichte'); ?></th>
                                <th><?php esc_html_e('Aktionen', 'feuer-einsatzberichte'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($letzte_einsaetze as $einsatz) : ?>
                                <?php
                                $einsatz_id = isset($einsatz->ID) ? (int) $einsatz->ID : 0;
                                $datum = isset($einsatz->event_date) ? (string) $einsatz->event_date : '';
                                $strasse = FEU_Einsatz_Template_Helpers::strip_house_number_from_street((string) ($einsatz->street ?? ''));
                                $timestamp = $datum ? strtotime($datum) : false;
                                ?>
                                <tr>
                                    <td data-label="<?php echo esc_attr__('Datum', 'feuer-einsatzberichte'); ?>">
                                        <?php echo $timestamp ? esc_html(wp_date('d.m.Y H:i', $timestamp)) : '&mdash;'; ?>
                                    </td>
                                    <td data-label="<?php echo esc_attr__('Titel', 'feuer-einsatzberichte'); ?>">
                                        <strong><?php echo esc_html(get_the_title($einsatz_id)); ?></strong>
                                    </td>
                                    <td data-label="<?php echo esc_attr__('Strasse', 'feuer-einsatzberichte'); ?>">
                                        <?php echo esc_html($strasse); ?>
                                    </td>
                                    <td data-label="<?php echo esc_attr__('Aktionen', 'feuer-einsatzberichte'); ?>">
                                        <div class="feu-admin-inline-actions">
                                            <a href="<?php echo esc_url(get_edit_post_link($einsatz_id)); ?>" class="button button-small">
                                                <?php esc_html_e('Bearbeiten', 'feuer-einsatzberichte'); ?>
                                            </a>
                                            <a href="<?php echo esc_url(get_permalink($einsatz_id)); ?>" class="button button-small" target="_blank" rel="noopener noreferrer">
                                                <?php esc_html_e('Ansehen', 'feuer-einsatzberichte'); ?>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else : ?>
                <div class="feu-admin-empty-state">
                    <span class="ti ti-file-search"></span>
                    <strong><?php esc_html_e('Noch keine Einsatzberichte vorhanden.', 'feuer-einsatzberichte'); ?></strong>
                    <p><?php esc_html_e('Legen Sie den ersten Bericht an oder verwenden Sie die Schnelleingabe im Dashboard.', 'feuer-einsatzberichte'); ?></p>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
