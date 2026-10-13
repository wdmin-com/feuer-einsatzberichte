<?php
if (!defined('ABSPATH')) {
    exit;
}

$report_list_url = admin_url('admin.php?page=feu-einsatz-berichte');
$report_return_url = add_query_arg(array_filter([
    'report_search' => $report_search,
    'report_status' => 'all' !== $report_status ? $report_status : '',
    'report_page' => $report_page > 1 ? $report_page : '',
    'feu_einsatz_street' => $street_filter,
    'feu_einsatz_postcode' => $postcode_filter,
    'feu_einsatz_city' => $city_filter,
]), $report_list_url);
$status_labels = [
    'all' => __('Alle', 'feuer-einsatzberichte'),
    'publish' => __('Veröffentlicht', 'feuer-einsatzberichte'),
    'draft' => __('Entwürfe', 'feuer-einsatzberichte'),
    'pending' => __('In Prüfung', 'feuer-einsatzberichte'),
    'future' => __('Geplant', 'feuer-einsatzberichte'),
    'private' => __('Privat', 'feuer-einsatzberichte'),
];
?>
<div class="wrap feu-admin-page feu-report-list">
    <div class="feu-admin-page-header">
        <div class="feu-admin-page-heading">
            <span class="feu-admin-page-eyebrow"><?php esc_html_e('Einsatzberichte', 'feuer-einsatzberichte'); ?></span>
            <h1><?php esc_html_e('Alle Berichte', 'feuer-einsatzberichte'); ?></h1>
            <p><?php esc_html_e('Berichte finden, Kartenbilder ansehen und fehlende Bilder direkt neu generieren.', 'feuer-einsatzberichte'); ?></p>
        </div>
        <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=feu-einsatz-neuer-bericht')); ?>"><?php esc_html_e('Neuer Bericht', 'feuer-einsatzberichte'); ?></a>
    </div>

    <?php if (isset($_GET['feu_map_generation'])) :
        $notice_type = 'success' === sanitize_key(wp_unslash($_GET['feu_map_generation'])) ? 'success' : 'error';
        $notice_message = isset($_GET['feu_map_message']) ? sanitize_text_field(wp_unslash($_GET['feu_map_message'])) : '';
        ?>
        <div class="notice notice-<?php echo esc_attr($notice_type); ?> is-dismissible"><p><?php echo esc_html($notice_message); ?></p></div>
    <?php endif; ?>

    <?php if ('' !== $street_filter || '' !== $postcode_filter || '' !== $city_filter) : ?>
        <p class="feu-report-list-address-filter">
            <?php echo esc_html(sprintf(__('Adressfilter: %s', 'feuer-einsatzberichte'), trim(implode(' ', array_filter([$street_filter, $postcode_filter, $city_filter]))))); ?>
            <a href="<?php echo esc_url($report_list_url); ?>"><?php esc_html_e('Alle Berichte anzeigen', 'feuer-einsatzberichte'); ?></a>
        </p>
    <?php endif; ?>
    <form class="feu-report-list-filters" method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
        <input type="hidden" name="page" value="feu-einsatz-berichte" />
        <?php if ('' !== $street_filter) : ?><input type="hidden" name="feu_einsatz_street" value="<?php echo esc_attr($street_filter); ?>" /><?php endif; ?>
        <?php if ('' !== $postcode_filter) : ?><input type="hidden" name="feu_einsatz_postcode" value="<?php echo esc_attr($postcode_filter); ?>" /><?php endif; ?>
        <?php if ('' !== $city_filter) : ?><input type="hidden" name="feu_einsatz_city" value="<?php echo esc_attr($city_filter); ?>" /><?php endif; ?>
        <label>
            <span><?php esc_html_e('Bericht suchen', 'feuer-einsatzberichte'); ?></span>
            <input type="search" name="report_search" value="<?php echo esc_attr($report_search); ?>" placeholder="<?php esc_attr_e('Titel oder Berichtstext', 'feuer-einsatzberichte'); ?>" />
        </label>
        <label>
            <span><?php esc_html_e('Status', 'feuer-einsatzberichte'); ?></span>
            <select name="report_status">
                <?php foreach ($status_labels as $status_value => $status_label) : ?>
                    <option value="<?php echo esc_attr($status_value); ?>" <?php selected($report_status, $status_value); ?>><?php echo esc_html($status_label); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="button button-secondary" type="submit"><?php esc_html_e('Anzeigen', 'feuer-einsatzberichte'); ?></button>
        <?php if ('' !== $report_search || 'all' !== $report_status) : ?>
            <a href="<?php echo esc_url($report_list_url); ?>"><?php esc_html_e('Filter zurücksetzen', 'feuer-einsatzberichte'); ?></a>
        <?php endif; ?>
    </form>

    <div class="feu-report-list-count">
        <?php echo esc_html(sprintf(_n('%d Bericht', '%d Berichte', (int) $report_query->found_posts, 'feuer-einsatzberichte'), (int) $report_query->found_posts)); ?>
    </div>

    <?php if (!empty($report_query->posts)) : ?>
        <div class="feu-report-list-items">
            <?php foreach ($report_query->posts as $report) :
                $report_id = (int) $report->ID;
                $map_url = FEU_Einsatz_Template_Helpers::get_generated_map_preview_public_url($report_id);
                $card_image = FEU_Einsatz_Template_Helpers::get_card_image_data($report_id, 'thumbnail');
                $map_status = $this->get_generated_map_admin_status($report_id);
                $status_key = sanitize_key((string) ($map_status['status'] ?? 'missing'));
                $report_status_object = get_post_status_object($report->post_status);
                $street = trim((string) get_post_meta($report_id, '_feu_einsatz_strasse', true));
                $house_number = trim((string) get_post_meta($report_id, '_feu_einsatz_hausnummer', true));
                $postcode = trim((string) get_post_meta($report_id, '_feu_einsatz_plz', true));
                $city = trim((string) get_post_meta($report_id, '_feu_einsatz_stadt', true));
                $address = trim($street . ' ' . $house_number . ', ' . $postcode . ' ' . $city, ' ,');
                $can_edit = current_user_can('edit_post', $report_id);
                $can_generate = $can_edit && 'privacy_hidden' !== $status_key
                    && ('' !== $street || (is_numeric(get_post_meta($report_id, '_feu_einsatz_latitude', true)) && is_numeric(get_post_meta($report_id, '_feu_einsatz_longitude', true))));
                ?>
                <article class="feu-report-list-item">
                    <div class="feu-report-list-image">
                        <?php if ('' !== $map_url) : ?>
                            <button type="button" class="feu-report-list-image-button" data-feu-map-dialog-open data-map-url="<?php echo esc_url($map_url); ?>" data-map-title="<?php echo esc_attr(get_the_title($report_id)); ?>" aria-label="<?php echo esc_attr(sprintf(__('Kartenbild für %s ansehen', 'feuer-einsatzberichte'), get_the_title($report_id))); ?>">
                                <img src="<?php echo esc_url($map_url); ?>" alt="" loading="lazy" />
                            </button>
                        <?php else : ?>
                            <span class="feu-report-list-image-empty"><?php esc_html_e('Kein Kartenbild', 'feuer-einsatzberichte'); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="feu-report-list-main">
                        <div class="feu-report-list-heading">
                            <h2><?php if ($can_edit) : ?><a href="<?php echo esc_url(get_edit_post_link($report_id)); ?>"><?php echo esc_html(get_the_title($report_id)); ?></a><?php else : ?><?php echo esc_html(get_the_title($report_id)); ?><?php endif; ?></h2>
                            <span class="feu-report-list-post-status"><?php echo esc_html($report_status_object ? $report_status_object->label : $report->post_status); ?></span>
                        </div>
                        <p class="feu-report-list-meta"><?php echo esc_html('' !== $address ? $address : __('Ort nicht angegeben', 'feuer-einsatzberichte')); ?> · <?php echo esc_html(get_the_date(get_option('date_format'), $report)); ?></p>
                        <div class="feu-report-list-health">
                            <span class="feu-report-list-health-badge feu-report-list-health-badge--<?php echo esc_attr($status_key); ?>"><?php echo esc_html('' !== $map_url ? __('Kartenbild vorhanden', 'feuer-einsatzberichte') : ('privacy_hidden' === $status_key ? __('Karte deaktiviert', 'feuer-einsatzberichte') : __('Kartenbild fehlt', 'feuer-einsatzberichte'))); ?></span>
                            <span><?php echo esc_html((string) ($map_status['message'] ?? '')); ?></span>
                            <span><?php echo esc_html(!empty($card_image['url']) ? (!empty($card_image['is_map']) ? __('Listenbild: Karte', 'feuer-einsatzberichte') : __('Listenbild: Foto', 'feuer-einsatzberichte')) : __('Listenbild fehlt', 'feuer-einsatzberichte')); ?></span>
                        </div>
                    </div>
                    <div class="feu-report-list-actions">
                        <?php if ('' !== $map_url) : ?>
                            <button type="button" class="button button-secondary" data-feu-map-dialog-open data-map-url="<?php echo esc_url($map_url); ?>" data-map-title="<?php echo esc_attr(get_the_title($report_id)); ?>"><?php esc_html_e('Kartenbild ansehen', 'feuer-einsatzberichte'); ?></button>
                        <?php endif; ?>
                        <?php if ($can_generate) : ?>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <?php wp_nonce_field('feu_einsatz_generate_map_image_now_' . $report_id); ?>
                                <input type="hidden" name="action" value="feu_einsatz_generate_map_image_now" />
                                <input type="hidden" name="post_id" value="<?php echo esc_attr((string) $report_id); ?>" />
                                <input type="hidden" name="redirect_to" value="<?php echo esc_attr($report_return_url); ?>" />
                                <button type="submit" class="button button-primary"><?php echo esc_html('' !== $map_url ? __('Neu generieren', 'feuer-einsatzberichte') : __('Kartenbild generieren', 'feuer-einsatzberichte')); ?></button>
                            </form>
                        <?php endif; ?>
                        <?php if ($can_edit) : ?><a class="button button-link" href="<?php echo esc_url(get_edit_post_link($report_id)); ?>"><?php esc_html_e('Bearbeiten', 'feuer-einsatzberichte'); ?></a><?php endif; ?>
                        <?php if ('publish' === $report->post_status) : ?><a class="button button-link" href="<?php echo esc_url(get_permalink($report_id)); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Auf Website ansehen', 'feuer-einsatzberichte'); ?></a><?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <?php if ((int) $report_query->max_num_pages > 1) : ?>
            <nav class="feu-report-list-pagination" aria-label="<?php esc_attr_e('Berichtsseiten', 'feuer-einsatzberichte'); ?>">
                <?php echo wp_kses_post(paginate_links([
                    'base' => str_replace('999999999', '%#%', add_query_arg('report_page', '999999999', $report_return_url)),
                    'format' => '',
                    'current' => $report_page,
                    'total' => (int) $report_query->max_num_pages,
                    'prev_text' => __('Zurück', 'feuer-einsatzberichte'),
                    'next_text' => __('Weiter', 'feuer-einsatzberichte'),
                ])); ?>
            </nav>
        <?php endif; ?>
    <?php else : ?>
        <div class="feu-report-list-empty"><?php esc_html_e('Keine Berichte gefunden. Suche oder Statusfilter anpassen.', 'feuer-einsatzberichte'); ?></div>
    <?php endif; ?>

    <dialog class="feu-report-map-dialog" data-feu-map-dialog aria-label="<?php esc_attr_e('Kartenbild ansehen', 'feuer-einsatzberichte'); ?>">
        <div class="feu-report-map-dialog-header">
            <div><span class="feu-admin-page-eyebrow"><?php esc_html_e('Generiertes Kartenbild', 'feuer-einsatzberichte'); ?></span><h2 data-feu-map-dialog-title></h2></div>
            <button type="button" class="button button-secondary" data-feu-map-dialog-close><?php esc_html_e('Schließen', 'feuer-einsatzberichte'); ?></button>
        </div>
        <p data-feu-map-dialog-loading><?php esc_html_e('Kartenbild wird geladen …', 'feuer-einsatzberichte'); ?></p>
        <img data-feu-map-dialog-image alt="<?php esc_attr_e('Generiertes Kartenbild', 'feuer-einsatzberichte'); ?>" />
        <p class="feu-report-map-dialog-error" data-feu-map-dialog-error hidden><?php esc_html_e('Die Bilddatei konnte im Browser nicht geladen werden. Bitte das Kartenbild neu generieren.', 'feuer-einsatzberichte'); ?></p>
    </dialog>
</div>
