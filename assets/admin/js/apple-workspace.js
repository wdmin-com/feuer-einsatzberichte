(function () {
    'use strict';

    function setActiveNavigation(page, activeId) {
        page.querySelectorAll('.feu-einsatz-report-section-link, .feu-apple-editor-step').forEach(function (link) {
            var isActive = link.getAttribute('href') === '#' + activeId;
            link.classList.toggle('is-active', isActive);
            if (isActive) {
                link.setAttribute('aria-current', 'step');
            } else {
                link.removeAttribute('aria-current');
            }
        });
    }

    function hasMeaningfulValue(section) {
        return Array.prototype.some.call(section.querySelectorAll('input, textarea, select'), function (field) {
            if (field.type === 'hidden' || field.disabled) {
                return false;
            }
            if (field.type === 'checkbox' || field.type === 'radio') {
                return field.checked;
            }
            return String(field.value || '').trim() !== '';
        });
    }

    function bootReportWorkspace(page) {
        document.body.classList.add('feu-apple-workspace');
        page.classList.add('is-ready');

        var navigation = page.querySelector('.feu-einsatz-report-section-nav');
        var sectionLinks = navigation ? Array.prototype.slice.call(navigation.querySelectorAll('a[href^="#"]')) : [];
        if (!navigation || !sectionLinks.length) {
            return;
        }

        // Reuse the existing navigation instead of rendering a second row.  The
        // editor stays compact while every original anchor and section remains
        // available to keyboard users and existing integrations.
        sectionLinks.forEach(function (link, index) {
            link.classList.add('feu-apple-editor-step');
            if (!link.querySelector('.feu-apple-editor-step-number')) {
                var number = document.createElement('i');
                number.className = 'feu-apple-editor-step-number';
                number.setAttribute('aria-hidden', 'true');
                number.textContent = String(index + 1);
                link.insertBefore(number, link.firstChild);
            }
        });

        function refreshProgress() {
            sectionLinks.forEach(function (link) {
                var id = link.getAttribute('href').slice(1);
                var section = document.getElementById(id);
                var complete = section && hasMeaningfulValue(section);
                page.querySelectorAll('.feu-apple-editor-step[href="#' + id + '"]').forEach(function (step) {
                    step.classList.toggle('is-complete', Boolean(complete));
                });
            });
        }

        page.addEventListener('input', refreshProgress);
        page.addEventListener('change', refreshProgress);
        refreshProgress();

        page.addEventListener('click', function (event) {
            var link = event.target.closest('.feu-einsatz-report-section-link, .feu-apple-editor-step');
            if (!link || !link.getAttribute('href')) {
                return;
            }
            setActiveNavigation(page, link.getAttribute('href').slice(1));
        });

        if ('IntersectionObserver' in window) {
            var observer = new IntersectionObserver(function (entries) {
                var visible = entries.filter(function (entry) { return entry.isIntersecting; }).sort(function (a, b) { return b.intersectionRatio - a.intersectionRatio; })[0];
                if (visible && visible.target.id) {
                    setActiveNavigation(page, visible.target.id);
                }
            }, { rootMargin: '-20% 0px -65%', threshold: [0.05, 0.25] });
            sectionLinks.forEach(function (link) {
                var section = document.getElementById(link.getAttribute('href').slice(1));
                if (section) {
                    observer.observe(section);
                }
            });
        }
    }

    function bootSettingsWorkspace() {
        var settings = document.querySelector('.wrap.feu-einsatz-settings');
        if (!settings) {
            return;
        }
        document.body.classList.add('feu-apple-settings');
        settings.classList.add('is-ready');
    }

    function boot() {
        var report = document.querySelector('.feu-einsatz-report-create-page');
        if (report) {
            bootReportWorkspace(report);
        }
        bootSettingsWorkspace();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
}());
