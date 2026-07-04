<?php
if (!defined('ABSPATH')) {
    exit;
}

$context = isset($context) && is_array($context) ? $context : [];
$share_data = isset($share_data) && is_array($share_data) ? $share_data : [];

if (empty($share_data)) {
    return;
}

$network_items = isset($share_data['networks']) && is_array($share_data['networks']) ? $share_data['networks'] : [];
$is_public = !empty($share_data['is_public']);
$share_file_url = '';
$timepoint_label = trim((string) ($share_data['date_display'] ?? '') . ' ' . (string) ($share_data['time'] ?? ''));
$meta_label_parts = array_filter([
    isset($share_data['number_label']) ? (string) $share_data['number_label'] : '',
    $timepoint_label,
]);
$meta_label = implode(' | ', $meta_label_parts);

$icon_renderer = static function ($icon_key) {
    switch ((string) $icon_key) {
        case 'facebook':
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13.5 22v-8h2.7l.4-3.1h-3.1V8.9c0-.9.3-1.5 1.6-1.5H17V4.6c-.3 0-1.3-.1-2.4-.1-2.4 0-4.1 1.5-4.1 4.3v2.1H7.8V14h2.7v8h3z"/></svg>';
        case 'instagram':
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 2h10a5 5 0 0 1 5 5v10a5 5 0 0 1-5 5H7a5 5 0 0 1-5-5V7a5 5 0 0 1 5-5zm0 2.2A2.8 2.8 0 0 0 4.2 7v10A2.8 2.8 0 0 0 7 19.8h10a2.8 2.8 0 0 0 2.8-2.8V7A2.8 2.8 0 0 0 17 4.2H7zm10.5 1.6a1.1 1.1 0 1 1 0 2.2 1.1 1.1 0 0 1 0-2.2zM12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10zm0 2.2A2.8 2.8 0 1 0 12 14.8 2.8 2.8 0 0 0 12 9.2z"/></svg>';
        case 'x':
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18.9 3H22l-6.8 7.7L23 21h-6.2l-4.9-6.4L6.3 21H3.2l7.3-8.3L1 3h6.4l4.4 5.8L18.9 3zm-1.1 16h1.7L6.5 4.9H4.7L17.8 19z"/></svg>';
        case 'whatsapp':
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11.8A8.9 8.9 0 0 1 6.8 19.6L3 21l1.5-3.7A8.9 8.9 0 1 1 20 11.8zm-8.9-7.4a7.4 7.4 0 0 0-6.3 11.2l.2.3-.9 2.2 2.2-.9.3.2a7.4 7.4 0 1 0 4.5-13zm4.4 9.5c-.2-.1-1.3-.6-1.5-.7-.2-.1-.3-.1-.5.1l-.4.5c-.1.2-.3.2-.5.1a6.1 6.1 0 0 1-1.8-1.1 6.7 6.7 0 0 1-1.2-1.5c-.1-.2 0-.3.1-.5l.3-.4.2-.3v-.4c-.1-.1-.5-1.2-.7-1.6-.2-.4-.3-.3-.5-.3h-.4c-.2 0-.4.1-.6.3-.2.2-.8.8-.8 1.9 0 1.1.8 2.2.9 2.3.1.2 1.6 2.5 4 3.5.6.2 1 .4 1.4.5.6.2 1.1.1 1.5.1.5-.1 1.3-.5 1.5-.9.2-.5.2-.8.1-.9l-.3-.2z"/></svg>';
        case 'telegram':
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21.5 4.6L18.4 19c-.2 1-.8 1.2-1.6.8l-4.4-3.2-2.1 2c-.2.2-.4.4-.8.4l.3-4.5 8.2-7.4c.4-.4-.1-.5-.5-.3L7.5 13 3.2 11.7c-.9-.3-.9-.9.2-1.3L20.1 4c.8-.3 1.6.2 1.4.6z"/></svg>';
        case 'email':
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 5h18a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1zm0 2v.5l9 6.2 9-6.2V7H3zm18 10V9.8l-8.4 5.8a1 1 0 0 1-1.2 0L3 9.8V17h18z"/></svg>';
        case 'share':
        default:
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 16a3 3 0 0 0-2.4 1.2L8.9 13a3.3 3.3 0 0 0 0-2l6.7-4.2A3 3 0 1 0 14.7 5L8 9.2a3 3 0 1 0 0 5.6l6.7 4.2A3 3 0 1 0 18 16z"/></svg>';
    }
};

if (!empty($share_data['preview_image_url'])) {
    $share_file_url = (string) $share_data['preview_image_url'];
} elseif (!empty($share_data['download_image_url'])) {
    $share_file_url = (string) $share_data['download_image_url'];
}
?>

<details class="feu-einsatz-single-share-disclosure">
    <summary class="feu-einsatz-single-share-summary">
        <span class="feu-einsatz-single-share-summary-icon" aria-hidden="true"><?php echo $icon_renderer('share'); ?></span>
        <span class="feu-einsatz-single-share-summary-copy">
            <strong><?php echo esc_html__('Einsatzbericht teilen', 'feuer-einsatzberichte'); ?></strong>
            <small><?php echo esc_html__('Nur fuer Administratoren sichtbar', 'feuer-einsatzberichte'); ?></small>
        </span>
        <span class="feu-einsatz-single-share-summary-meta">
            <span class="feu-einsatz-single-share-badge"><?php echo esc_html($share_data['image_mode_label']); ?></span>
            <span class="feu-einsatz-single-share-summary-toggle"><?php echo esc_html__('Oeffnen', 'feuer-einsatzberichte'); ?></span>
        </span>
    </summary>

    <section class="card feu-einsatz-single-share-card">
        <div class="card-body">
            <?php if (!empty($share_data['status_notice'])) : ?>
                <div class="alert alert-warning feu-einsatz-single-share-alert" role="alert">
                    <?php echo esc_html($share_data['status_notice']); ?>
                </div>
            <?php endif; ?>

            <div class="feu-einsatz-single-share-tools">
                <div class="feu-einsatz-single-share-tools-head">
                    <strong><?php echo esc_html__('Direkt teilen', 'feuer-einsatzberichte'); ?></strong>
                    <small><?php echo esc_html__('Direkte Share-Links fuer unterstuetzte Netzwerke plus native Teilen-Funktion mit Bild, Text und Link.', 'feuer-einsatzberichte'); ?></small>
                </div>

                <div class="feu-einsatz-single-share-inline-preview">
                    <div class="feu-einsatz-single-share-inline-preview-media">
                        <?php if (!empty($share_data['has_preview_image']) && !empty($share_data['preview_image_url'])) : ?>
                            <img src="<?php echo esc_url($share_data['preview_image_url']); ?>"
                                 alt="<?php echo esc_attr($share_data['title']); ?>"
                                 class="feu-einsatz-single-share-inline-preview-image" />
                        <?php else : ?>
                            <div class="feu-einsatz-single-share-inline-preview-placeholder">
                                <?php echo esc_html__('Kein Share-Bild verfuegbar', 'feuer-einsatzberichte'); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="feu-einsatz-single-share-inline-preview-copy">
                        <strong><?php echo esc_html($share_data['title']); ?></strong>
                        <?php if ('' !== $meta_label) : ?>
                            <span><?php echo esc_html($meta_label); ?></span>
                        <?php endif; ?>
                        <small><?php echo esc_html($share_data['image_notice']); ?></small>
                    </div>
                </div>

                <?php if (!empty($network_items)) : ?>
                    <div class="feu-einsatz-single-share-networks" aria-label="<?php echo esc_attr__('Netzwerke', 'feuer-einsatzberichte'); ?>">
                        <?php foreach ($network_items as $network) : ?>
                            <?php
                            $network_key = isset($network['key']) ? (string) $network['key'] : '';
                            $network_mode = isset($network['mode']) ? (string) $network['mode'] : 'direct';
                            ?>

                            <?php if ('native' === $network_mode) : ?>
                                <button type="button"
                                        class="btn btn-sm feu-einsatz-single-share-network feu-einsatz-single-share-network-<?php echo esc_attr($network_key); ?>"
                                        data-feu-native-share="1"
                                        data-feu-icon-only="1"
                                        data-feu-share-service="<?php echo esc_attr($network_key); ?>"
                                        data-feu-share-title="<?php echo esc_attr($share_data['title']); ?>"
                                        data-feu-share-text="<?php echo esc_attr($share_data['share_text']); ?>"
                                        data-feu-share-url="<?php echo esc_attr($share_data['permalink']); ?>"
                                        data-feu-share-file="<?php echo esc_attr($share_file_url); ?>"
                                        data-feu-share-use-file-url="1"
                                        data-feu-share-success="<?php echo esc_attr__('Teilen gestartet', 'feuer-einsatzberichte'); ?>"
                                        data-feu-share-error="<?php echo esc_attr__('Teilen nicht verfuegbar', 'feuer-einsatzberichte'); ?>"
                                        title="<?php echo esc_attr($network['label']); ?>"
                                        aria-label="<?php echo esc_attr($network['label']); ?>"
                                        <?php echo $is_public ? '' : 'disabled'; ?>>
                                    <span class="feu-einsatz-single-share-network-icon" aria-hidden="true"><?php echo $icon_renderer($network_key); ?></span>
                                </button>
                                <?php continue; ?>
                            <?php endif; ?>

                            <?php if ($is_public) : ?>
                                <a class="btn btn-sm feu-einsatz-single-share-network feu-einsatz-single-share-network-<?php echo esc_attr($network_key); ?>"
                                   href="<?php echo esc_url($network['url']); ?>"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   title="<?php echo esc_attr($network['label']); ?>"
                                   aria-label="<?php echo esc_attr($network['label']); ?>">
                                    <span class="feu-einsatz-single-share-network-icon" aria-hidden="true"><?php echo $icon_renderer($network_key); ?></span>
                                </a>
                            <?php else : ?>
                                <button type="button"
                                        class="btn btn-sm feu-einsatz-single-share-network is-disabled feu-einsatz-single-share-network-<?php echo esc_attr($network_key); ?>"
                                        title="<?php echo esc_attr($network['label']); ?>"
                                        aria-label="<?php echo esc_attr($network['label']); ?>"
                                        disabled>
                                    <span class="feu-einsatz-single-share-network-icon" aria-hidden="true"><?php echo $icon_renderer($network_key); ?></span>
                                </button>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($share_data['share_text'])) : ?>
                    <div class="feu-einsatz-single-share-text-block">
                        <div class="feu-einsatz-single-share-text-head">
                            <strong><?php echo esc_html__('Share-Text', 'feuer-einsatzberichte'); ?></strong>
                            <button type="button"
                                    class="feu-einsatz-single-share-copy-btn"
                                    data-feu-copy-target="feu-share-text-<?php echo esc_attr($share_data['post_id']); ?>"
                                    aria-label="<?php echo esc_attr__('Share-Text kopieren', 'feuer-einsatzberichte'); ?>">
                                <?php echo esc_html__('Kopieren', 'feuer-einsatzberichte'); ?>
                            </button>
                        </div>
                        <pre id="feu-share-text-<?php echo esc_attr($share_data['post_id']); ?>"
                             class="feu-einsatz-single-share-text-content"><?php echo esc_html($share_data['share_text']); ?></pre>
                    </div>
                <?php endif; ?>

                <p class="feu-einsatz-single-share-footer-note">
                    <?php echo esc_html__('Facebook, WhatsApp, Telegram, X und E-Mail oeffnen direkte Share-Dialoge. Instagram und System teilen nutzen die native Teilen-Funktion des Geraets und uebergeben das ausgewaehlte Share-Bild zusammen mit Text und Link, sofern der Browser dies unterstuetzt.', 'feuer-einsatzberichte'); ?>
                </p>
            </div>
        </div>
    </section>
</details>
