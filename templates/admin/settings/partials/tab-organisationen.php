<?php
if (!defined('ABSPATH')) {
    exit;
}

$tab_organisationen_classes = 'feu-einsatz-tab-content';

if (!isset($active_tab) || 'organisationen' !== (string) $active_tab) {
    $tab_organisationen_classes .= ' feu-einsatz-tab-content-hidden';
}
?>

<div id="tab-organisationen" class="<?php echo esc_attr($tab_organisationen_classes); ?>">
    <h2><?php _e('Kräfte vor Ort', 'feuer-einsatzberichte'); ?></h2>
    <p><?php _e('Verwalten Sie die Organisationen, die an Einsätzen teilnehmen können:', 'feuer-einsatzberichte'); ?></p>

    <label class="feu-admin-settings-toggle">
        <input type="checkbox" name="feu_einsatz_feature_organizations_enabled" value="1" <?php checked(!empty($organizations_enabled)); ?> />
        <span><?php esc_html_e('Kräfte vor Ort im Plugin aktivieren', 'feuer-einsatzberichte'); ?></span>
    </label>

    <div class="feu-einsatz-organizations-section">
        <p class="description"><?php _e('Archivierte Organisationen bleiben in bestehenden Einsatzberichten erhalten, können aber nicht mehr neu ausgewählt werden.', 'feuer-einsatzberichte'); ?></p>
        <div class="feu-einsatz-add-organization">
            <h3><?php _e('Neue Organisation hinzufügen', 'feuer-einsatzberichte'); ?></h3>
            <div class="feu-einsatz-organization-form">
                <input type="text"
                       id="feu-einsatz-new-organization"
                       class="regular-text"
                       placeholder="<?php _e('Organisationsname', 'feuer-einsatzberichte'); ?>" />
                <input type="url"
                       id="feu-einsatz-new-organization-link"
                       class="regular-text"
                       placeholder="<?php _e('Link zum Beschreibungspost (optional)', 'feuer-einsatzberichte'); ?>" />
                <label for="feu-einsatz-new-organization-color" class="screen-reader-text"><?php _e('Farbe', 'feuer-einsatzberichte'); ?></label>
                <input type="color"
                       id="feu-einsatz-new-organization-color"
                       class="feu-einsatz-organization-color-field"
                       value="#0a4b78" />
                <button type="button" id="feu-einsatz-add-organization" class="button button-primary">
                    <span class="feu-einsatz-button-icon" aria-hidden="true">+</span>
                    <?php _e('Hinzufügen', 'feuer-einsatzberichte'); ?>
                </button>
            </div>
        </div>

        <div class="feu-einsatz-organizations-list">
            <h3><?php _e('Vorhandene Organisationen', 'feuer-einsatzberichte'); ?></h3>

            <?php if (empty($organizations)): ?>
                <p class="feu-einsatz-no-data"><?php _e('Noch keine Organisationen vorhanden.', 'feuer-einsatzberichte'); ?></p>
            <?php else: ?>
                <div class="feu-einsatz-organizations-table" role="list" aria-label="<?php esc_attr_e('Vorhandene Organisationen', 'feuer-einsatzberichte'); ?>">
                    <?php foreach ($organizations as $org): ?>
                        <?php
                        $org_color = sanitize_hex_color(isset($org->color) ? $org->color : '') ?: '#0a4b78';
                        $org_post_link = !empty($org->post_link) ? esc_url($org->post_link) : '';
                        $is_archived = !empty($org->is_archived);
                        ?>
                        <article class="feu-einsatz-organization-row<?php echo $is_archived ? ' is-archived' : ''; ?>" role="listitem" data-organization-id="<?php echo esc_attr((int) $org->id); ?>">
                            <div class="feu-einsatz-organization-identity">
                                <span class="feu-einsatz-organization-color-preview" style="--feu-einsatz-org-preview: <?php echo esc_attr($org_color); ?>;"></span>
                                <div>
                                    <strong><?php echo esc_html($org->name); ?></strong>
                                    <span><?php echo esc_html(sprintf(__('Organisation #%d', 'feuer-einsatzberichte'), (int) $org->id)); ?></span>
                                </div>
                            </div>
                            <div class="feu-einsatz-organization-meta">
                                <span class="feu-einsatz-status-badge <?php echo $is_archived ? 'is-archived' : 'is-active'; ?>">
                                    <?php echo $is_archived ? esc_html__('Archiviert', 'feuer-einsatzberichte') : esc_html__('Aktiv', 'feuer-einsatzberichte'); ?>
                                </span>
                                <code><?php echo esc_html($org_color); ?></code>
                                <?php if ('' !== $org_post_link) : ?>
                                    <a href="<?php echo esc_url($org_post_link); ?>" target="_blank" rel="noopener"><?php esc_html_e('Beitrag öffnen', 'feuer-einsatzberichte'); ?></a>
                                <?php endif; ?>
                            </div>
                            <div class="feu-einsatz-organization-order" aria-label="<?php esc_attr_e('Reihenfolge', 'feuer-einsatzberichte'); ?>">
                                <button type="button" class="button button-small feu-einsatz-move-organization-up" aria-label="<?php esc_attr_e('Nach oben verschieben', 'feuer-einsatzberichte'); ?>"><span aria-hidden="true">↑</span></button>
                                <button type="button" class="button button-small feu-einsatz-move-organization-down" aria-label="<?php esc_attr_e('Nach unten verschieben', 'feuer-einsatzberichte'); ?>"><span aria-hidden="true">↓</span></button>
                            </div>
                            <div class="feu-einsatz-organization-row-actions">
                                <button type="button" class="button button-small feu-einsatz-edit-organization"
                                        data-id="<?php echo esc_attr((int) $org->id); ?>"
                                        data-name="<?php echo esc_attr($org->name); ?>"
                                        data-color="<?php echo esc_attr($org_color); ?>"
                                        data-post-link="<?php echo esc_attr(isset($org->post_link) ? (string) $org->post_link : ''); ?>"
                                        data-archived="<?php echo esc_attr((int) $is_archived); ?>"><?php esc_html_e('Bearbeiten', 'feuer-einsatzberichte'); ?></button>
                                <button type="button" class="button button-small feu-einsatz-delete-organization"
                                        data-id="<?php echo esc_attr((int) $org->id); ?>"
                                        data-archived="<?php echo esc_attr((int) $is_archived); ?>"><?php echo $is_archived ? esc_html__('Aktivieren', 'feuer-einsatzberichte') : esc_html__('Archivieren', 'feuer-einsatzberichte'); ?></button>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
