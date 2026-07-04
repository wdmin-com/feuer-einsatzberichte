<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="feu-einsatz-page-preloader" class="feu-einsatz-page-preloader" hidden>
    <div class="feu-einsatz-page-preloader-inner">
        <div class="feu-einsatz-page-preloader-loader" aria-hidden="true"></div>
        <div class="feu-einsatz-page-preloader-label"><?php echo esc_html__('Einsatzbericht wird geladen...', 'feuer-einsatzberichte'); ?></div>
    </div>
</div>
<script>
(function () {
    var storageKey = 'feu_einsatz_single_preloader_pending';
    var overlay = document.getElementById('feu-einsatz-page-preloader');

    function canUseSessionStorage() {
        try {
            if (!window.sessionStorage) {
                return false;
            }

            window.sessionStorage.setItem(storageKey, '0');
            window.sessionStorage.removeItem(storageKey);

            return true;
        } catch (error) {
            return false;
        }
    }

    function isPending() {
        try {
            return window.sessionStorage.getItem(storageKey) === '1';
        } catch (error) {
            return false;
        }
    }

    if (!overlay || !canUseSessionStorage()) {
        return;
    }

    function clearPendingPreloader() {
        overlay.hidden = true;
        document.documentElement.classList.remove('feu-einsatz-preloader-pending');
        document.body.classList.remove('feu-einsatz-page-loading');
        try {
            window.sessionStorage.removeItem(storageKey);
        } catch (error) {
        }
    }

    if (isPending()) {
        overlay.hidden = false;
        document.documentElement.classList.add('feu-einsatz-preloader-pending');
        document.body.classList.add('feu-einsatz-page-loading');

        if (document.readyState === 'complete') {
            window.setTimeout(clearPendingPreloader, 180);
            return;
        }

        window.addEventListener('load', function () {
            window.setTimeout(clearPendingPreloader, 180);
        }, { once: true });
    }
})();
</script>
