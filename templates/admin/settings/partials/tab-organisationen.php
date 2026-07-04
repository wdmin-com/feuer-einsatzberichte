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
    <h2><?php _e('KrГ¤fte vor Ort', 'feuer-einsatzberichte'); ?></h2>
    <p><?php _e('Verwalten Sie die Organisationen, die an EinsГ¤tzen teilnehmen kГ¶nnen:', 'feuer-einsatzberichte'); ?></p>

    <div class="feu-einsatz-organizations-section">
        <p class="description"><?php _e('Archivierte Organisationen bleiben in bestehenden Einsatzberichten erhalten, kГ¶nnen aber nicht mehr neu ausgewГ¤hlt werden.', 'feuer-einsatzberichte'); ?></p>
        <div class="feu-einsatz-add-organization">
            <h3><?php _e('Neue Organisation hinzufГјgen', 'feuer-einsatzberichte'); ?></h3>
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
                    <?php _e('HinzufГјgen', 'feuer-einsatzberichte'); ?>
                </button>
            </div>
        </div>

        <div class="feu-einsatz-organizations-list">
            <h3><?php _e('Vorhandene Organisationen', 'feuer-einsatzberichte'); ?></h3>

            <?php if (empty($organizations)): ?>
                <p class="feu-einsatz-no-data"><?php _e('Noch keine Organisationen vorhanden.', 'feuer-einsatzberichte'); ?></p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped feu-einsatz-organizations-table">
                    <thead>
                        <tr>
                            <th><?php _e('ID', 'feuer-einsatzberichte'); ?></th>
                            <th><?php _e('Name', 'feuer-einsatzberichte'); ?></th>
                            <th><?php _e('Status', 'feuer-einsatzberichte'); ?></th>
                            <th><?php _e('Beitrags-Link', 'feuer-einsatzberichte'); ?></th>
                            <th><?php _e('Farbe', 'feuer-einsatzberichte'); ?></th>
                            <th><?php _e('Erstellt am', 'feuer-einsatzberichte'); ?></th>
                            <th><?php _e('Reihenfolge', 'feuer-einsatzberichte'); ?></th>
                            <th><?php _e('Aktionen', 'feuer-einsatzberichte'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($organizations as $org): ?>
                            <?php $org_color = sanitize_hex_color(isset($org->color) ? $org->color : '') ?: '#0a4b78'; ?>
                            <tr class="<?php echo !empty($org->is_archived) ? 'is-archived' : ''; ?>" data-organization-id="<?php echo esc_attr((int) $org->id); ?>">
                                <td data-label="<?php echo esc_attr__('ID', 'feuer-einsatzberichte'); ?>"><?php echo esc_html((int) $org->id); ?></td>
                                <td data-label="<?php echo esc_attr__('Name', 'feuer-einsatzberichte'); ?>"><?php echo esc_html($org->name); ?></td>
                                <td data-label="<?php echo esc_attr__('Status', 'feuer-einsatzberichte'); ?>">
                                    <span class="feu-einsatz-status-badge <?php echo !empty($org->is_archived) ? 'is-archived' : 'is-active'; ?>">
                                        <?php echo !empty($org->is_archived) ? esc_html__('Archiviert', 'feuer-einsatzberichte') : esc_html__('Aktiv', 'feuer-einsatzberichte'); ?>
                                    </span>
                                </td>
                                <td data-label="<?php echo esc_attr__('Beitrags-Link', 'feuer-einsatzberichte'); ?>">
                                    <?php $org_post_link = !empty($org->post_link) ? esc_url($org->post_link) : ''; ?>
                                    <?php if ('' !== $org_post_link) : ?>
                                        <a href="<?php echo esc_url($org_post_link); ?>" target="_blank" rel="noopener">
                                            <?php _e('Beitrag Г¶ffnen', 'feuer-einsatzberichte'); ?>
                                        </a>
                                    <?php else : ?>
                                        &ndash;
                                    <?php endif; ?>
                                </td>
                                <td data-label="<?php echo esc_attr__('Farbe', 'feuer-einsatzberichte'); ?>">
                                    <span class="feu-einsatz-organization-color-preview" style="--feu-einsatz-org-preview: <?php echo esc_attr($org_color); ?>;"></span>
                                    <code><?php echo esc_html($org_color); ?></code>
                                </td>
                                <td data-label="<?php echo esc_attr__('Erstellt am', 'feuer-einsatzberichte'); ?>"><?php echo esc_html(date_i18n('d.m.Y H:i', strtotime((string) $org->created_at))); ?></td>
                                <td data-label="<?php echo esc_attr__('Reihenfolge', 'feuer-einsatzberichte'); ?>" class="feu-einsatz-table-actions">
                                    <div class="feu-einsatz-organization-actions">
                                        <button type="button" class="button button-small feu-einsatz-move-organization-up" aria-label="<?php esc_attr_e('Nach oben verschieben', 'feuer-einsatzberichte'); ?>">
                                            <span class="feu-einsatz-button-icon" aria-hidden="true">↑</span>
                                        </button>
                                        <button type="button" class="button button-small feu-einsatz-move-organization-down" aria-label="<?php esc_attr_e('Nach unten verschieben', 'feuer-einsatzberichte'); ?>">
                                            <span class="feu-einsatz-button-icon" aria-hidden="true">↓</span>
                                        </button>
                                    </div>
                                </td>
                                <td data-label="<?php echo esc_attr__('Aktionen', 'feuer-einsatzberichte'); ?>" class="feu-einsatz-table-actions">
                                    <button type="button" class="button button-small feu-einsatz-edit-organization"
                                            data-id="<?php echo esc_attr((int) $org->id); ?>"
                                            data-name="<?php echo esc_attr($org->name); ?>"
                                            data-color="<?php echo esc_attr($org_color); ?>"
                                            data-post-link="<?php echo esc_attr(isset($org->post_link) ? (string) $org->post_link : ''); ?>"
                                            data-archived="<?php echo esc_attr((int) !empty($org->is_archived)); ?>">
                                        <?php _e('Bearbeiten', 'feuer-einsatzberichte'); ?>
                                    </button>
                                    <button type="button" class="button button-small feu-einsatz-delete-organization"
                                            data-id="<?php echo esc_attr((int) $org->id); ?>"
                                            data-archived="<?php echo esc_attr((int) !empty($org->is_archived)); ?>">
                                        <span class="feu-einsatz-button-icon" aria-hidden="true">×</span>
                                        <?php _e('LГ¶schen', 'feuer-einsatzberichte'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
