<?php
if (!defined('ABSPATH')) {
    exit;
}

$context = isset($context) && is_array($context) ? $context : [];
$gallery = isset($context['gallery']) && is_array($context['gallery']) ? $context['gallery'] : [];
$gallery_items = isset($gallery['items']) && is_array($gallery['items']) ? $gallery['items'] : [];
$pending_count = isset($gallery['pending_count']) ? absint($gallery['pending_count']) : 0;
$photo_watermark_enabled = !empty($gallery['watermark_enabled']);
$photo_watermark_text = isset($gallery['watermark_text']) ? (string) $gallery['watermark_text'] : '';
?>

<?php if (!empty($gallery_items) || $pending_count > 0) : ?>
    <div class="card feu-einsatz-single-gallery-card">
        <div class="card-header">
            <?php echo esc_html__('Fotos', 'feuer-einsatzberichte'); ?>
        </div>
        <div class="card-body">
            <?php if ($pending_count > 0) : ?>
                <p class="feu-einsatz-photo-pending" role="status"><?php echo esc_html__('Weitere Fotos sind derzeit nicht verfügbar.', 'feuer-einsatzberichte'); ?></p>
            <?php endif; ?>
            <?php if (!empty($gallery_items)) : ?>
            <div class="feu-einsatz-single-section">
                <div class="feu-einsatz-photo-grid feu-einsatz-single-photo-grid">
                    <?php foreach ($gallery_items as $gallery_item) : ?>
                        <button type="button"
                                class="feu-einsatz-photo-card"
                                data-full-image="<?php echo esc_url($gallery_item['full']); ?>"
                                data-alt-text="<?php echo esc_attr($gallery_item['alt']); ?>"
                                data-embedded-watermark="<?php echo !empty($gallery_item['embedded_watermark']) ? '1' : '0'; ?>">
                            <img src="<?php echo esc_url($gallery_item['thumb']); ?>"
                                 alt="<?php echo esc_attr($gallery_item['alt']); ?>"
                                 loading="lazy"
                                 decoding="async"
                                 <?php if (!empty($gallery_item['width']) && !empty($gallery_item['height'])) : ?>width="<?php echo esc_attr((string) $gallery_item['width']); ?>" height="<?php echo esc_attr((string) $gallery_item['height']); ?>"<?php endif; ?>
                                 <?php if (!empty($gallery_item['srcset'])) : ?>srcset="<?php echo esc_attr($gallery_item['srcset']); ?>" sizes="(max-width: 720px) 50vw, (max-width: 1200px) 33vw, 25vw"<?php endif; ?> />
                            <?php if (empty($gallery_item['embedded_watermark']) && $photo_watermark_enabled && '' !== $photo_watermark_text) : ?>
                                <span class="feu-einsatz-photo-watermark"><?php echo esc_html($photo_watermark_text); ?></span>
                            <?php endif; ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($gallery_items)) : ?>
    <div id="feu-einsatz-photo-modal" class="feu-einsatz-photo-modal" hidden>
        <div class="feu-einsatz-photo-modal-backdrop"></div>
        <div class="feu-einsatz-photo-modal-dialog" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr__('Foto ansehen', 'feuer-einsatzberichte'); ?>">
            <button type="button" class="feu-einsatz-photo-modal-close" aria-label="<?php echo esc_attr__('Schliessen', 'feuer-einsatzberichte'); ?>">&times;</button>
            <div class="feu-einsatz-photo-modal-stage">
                <img src="" alt="" id="feu-einsatz-photo-modal-image" />
                <?php if ($photo_watermark_enabled && '' !== $photo_watermark_text) : ?>
                    <span class="feu-einsatz-photo-watermark feu-einsatz-photo-watermark-large" id="feu-einsatz-photo-modal-watermark"><?php echo esc_html($photo_watermark_text); ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var modal = document.getElementById('feu-einsatz-photo-modal');
        var modalImage = document.getElementById('feu-einsatz-photo-modal-image');
        var modalWatermark = document.getElementById('feu-einsatz-photo-modal-watermark');
        var closeButton = document.querySelector('.feu-einsatz-photo-modal-close');
        var cards = document.querySelectorAll('.feu-einsatz-photo-card');
        var returnFocus = null;

        if (!modal || !modalImage || !cards.length) {
            return;
        }

        function closeModal() {
            modal.hidden = true;
            document.body.classList.remove('feu-einsatz-photo-modal-open');
            modalImage.removeAttribute('src');
            if (returnFocus) {
                returnFocus.focus();
                returnFocus = null;
            }
        }

        cards.forEach(function(card) {
            card.addEventListener('click', function() {
                returnFocus = card;
                modal.hidden = false;
                modalImage.src = card.getAttribute('data-full-image') || '';
                modalImage.alt = card.getAttribute('data-alt-text') || '';
                document.body.classList.add('feu-einsatz-photo-modal-open');
                if (closeButton) {
                    closeButton.focus();
                }

                if (modalWatermark) {
                    modalWatermark.hidden = card.getAttribute('data-embedded-watermark') === '1';
                }
            });
        });

        if (closeButton) {
            closeButton.addEventListener('click', closeModal);
        }

        modal.addEventListener('click', function(event) {
            if (event.target === modal || event.target.classList.contains('feu-einsatz-photo-modal-backdrop')) {
                closeModal();
            }
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && !modal.hidden) {
                closeModal();
            }
        });
    });
    </script>
    <?php endif; ?>
<?php endif; ?>
