<?php
if (!defined('ABSPATH')) {
    exit;
}

$context = isset($context) && is_array($context) ? $context : [];
$gallery = isset($context['gallery']) && is_array($context['gallery']) ? $context['gallery'] : [];
$gallery_items = isset($gallery['items']) && is_array($gallery['items']) ? $gallery['items'] : [];
$photo_watermark_enabled = !empty($gallery['watermark_enabled']);
$photo_watermark_text = isset($gallery['watermark_text']) ? (string) $gallery['watermark_text'] : '';
?>

<?php if (!empty($gallery_items)) : ?>
    <div class="card feu-einsatz-single-gallery-card">
        <div class="card-header">
            <?php echo esc_html__('Fotos:', 'feuer-einsatzberichte'); ?>
        </div>
        <div class="card-body">
            <div class="feu-einsatz-single-section">
                <div class="feu-einsatz-photo-grid feu-einsatz-single-photo-grid">
                    <?php foreach ($gallery_items as $gallery_item) : ?>
                        <button type="button"
                                class="feu-einsatz-photo-card"
                                data-full-image="<?php echo esc_url($gallery_item['full']); ?>"
                                data-alt-text="<?php echo esc_attr($gallery_item['alt']); ?>"
                                data-embedded-watermark="<?php echo !empty($gallery_item['embedded_watermark']) ? '1' : '0'; ?>">
                            <img src="<?php echo esc_url($gallery_item['thumb']); ?>"
                                 alt="<?php echo esc_attr($gallery_item['alt']); ?>" />
                            <?php if (empty($gallery_item['embedded_watermark']) && $photo_watermark_enabled && '' !== $photo_watermark_text) : ?>
                                <span class="feu-einsatz-photo-watermark"><?php echo esc_html($photo_watermark_text); ?></span>
                            <?php endif; ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div id="feu-einsatz-photo-modal" class="feu-einsatz-photo-modal" hidden>
        <div class="feu-einsatz-photo-modal-backdrop"></div>
        <div class="feu-einsatz-photo-modal-dialog">
            <button type="button" class="feu-einsatz-photo-modal-close" aria-label="<?php echo esc_attr__('Schliessen', 'feuer-einsatzberichte'); ?>">x</button>
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

        if (!modal || !modalImage || !cards.length) {
            return;
        }

        function closeModal() {
            modal.hidden = true;
            document.body.classList.remove('feu-einsatz-photo-modal-open');
            modalImage.removeAttribute('src');
        }

        cards.forEach(function(card) {
            card.addEventListener('click', function() {
                modal.hidden = false;
                modalImage.src = card.getAttribute('data-full-image') || '';
                modalImage.alt = card.getAttribute('data-alt-text') || '';
                document.body.classList.add('feu-einsatz-photo-modal-open');

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