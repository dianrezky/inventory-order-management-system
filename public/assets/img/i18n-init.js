// i18next bootstrap (ADR-003): the server already set <html lang> anti-FOUT, this hydrates the same locale client-side.
(function () {
    'use strict';

    function applyTranslations() {
        document.querySelectorAll('[data-i18n]').forEach(function (el) {
            var key = el.getAttribute('data-i18n');
            el.textContent = window.i18next.t(key);
        });

        document.querySelectorAll('[data-i18n-attr]').forEach(function (el) {
            var spec = el.getAttribute('data-i18n-attr');
            var parts = spec.split('|');
            var attr = parts[0];
            var key = parts[1];
            el.setAttribute(attr, window.i18next.t(key));
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof window.i18next === 'undefined') {
            return;
        }

        var locale = localStorage.getItem('locale') || document.documentElement.lang || 'en';

        window.i18next
            .use(window.i18nextHttpBackend)
            .init({
                lng: locale,
                fallbackLng: 'en',
                supportedLngs: ['en', 'id'],
                backend: {
                    loadPath: '/assets/locales/{{lng}}/translation.json',
                },
                interpolation: { escapeValue: false },
            })
            .then(function () {
                applyTranslations();
                window.i18n = window.i18next;
                document.dispatchEvent(new CustomEvent('i18n:ready'));
            });
    });

    window.toggleLocale = function toggleLocale() {
        var current = localStorage.getItem('locale') || document.documentElement.lang || 'en';
        var next = current === 'en' ? 'id' : 'en';
        localStorage.setItem('locale', next);
        // Locale lives in localStorage; nothing server-side reads a ?lang= param, so a plain reload is enough.
        window.location.reload();
    };
})();
