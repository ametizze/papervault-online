/* SimpleVault front-end behaviors. No inline scripts (CSP-friendly).
 * All hooks are data-attribute driven. */
(function () {
    'use strict';

    // Copy-to-clipboard buttons: <button data-copy-target="#selector">
    document.addEventListener('click', function (event) {
        var btn = event.target.closest('[data-copy-target]');
        if (!btn) {
            return;
        }
        event.preventDefault();
        var target = document.querySelector(btn.getAttribute('data-copy-target'));
        if (!target) {
            return;
        }
        var value = 'value' in target ? target.value : target.textContent;
        navigator.clipboard.writeText(value).then(function () {
            var original = btn.getAttribute('data-original') || btn.textContent;
            btn.setAttribute('data-original', original);
            btn.textContent = 'Copied!';
            setTimeout(function () {
                btn.textContent = original;
            }, 1200);
        });
    });

    // Password visibility toggles: <button data-toggle-visibility="#selector">
    document.addEventListener('click', function (event) {
        var btn = event.target.closest('[data-toggle-visibility]');
        if (!btn) {
            return;
        }
        event.preventDefault();
        var field = document.querySelector(btn.getAttribute('data-toggle-visibility'));
        if (!field) {
            return;
        }
        if (field.type === 'password') {
            field.type = 'text';
            btn.textContent = 'Hide';
        } else {
            field.type = 'password';
            btn.textContent = 'Show';
        }
    });

    // Confirmation prompts for destructive forms: <form data-confirm="message">
    document.addEventListener('submit', function (event) {
        var form = event.target.closest('[data-confirm]');
        if (!form) {
            return;
        }
        if (!window.confirm(form.getAttribute('data-confirm'))) {
            event.preventDefault();
        }
    });

    // Live tag preview / character counters: <textarea data-counter="#out">
    document.querySelectorAll('[data-counter]').forEach(function (el) {
        var out = document.querySelector(el.getAttribute('data-counter'));
        if (!out) {
            return;
        }
        var update = function () {
            out.textContent = el.value.length.toLocaleString();
        };
        el.addEventListener('input', update);
        update();
    });
})();
