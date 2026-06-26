/* SimpleVault front-end behaviors. No inline scripts (CSP-friendly).
 * All hooks are data-attribute driven. */
(function () {
    'use strict';

    // Write text to the clipboard, with a fallback for non-secure contexts
    // (e.g. plain http during local testing where navigator.clipboard is absent).
    function copyToClipboard(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }
        return new Promise(function (resolve, reject) {
            try {
                var ta = document.createElement('textarea');
                ta.value = text;
                ta.setAttribute('readonly', '');
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                var ok = document.execCommand('copy');
                document.body.removeChild(ta);
                ok ? resolve() : reject(new Error('copy failed'));
            } catch (err) {
                reject(err);
            }
        });
    }

    // Briefly show feedback text on a button, then restore its label.
    function flashButton(btn, message) {
        var original = btn.getAttribute('data-original') || btn.textContent;
        btn.setAttribute('data-original', original);
        btn.textContent = message;
        setTimeout(function () {
            btn.textContent = original;
        }, 1200);
    }

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
        copyToClipboard(value)
            .then(function () { flashButton(btn, 'Copied!'); })
            .catch(function () { flashButton(btn, 'Failed'); });
    });

    // Quick "copy from a list row" without putting the secret in the page:
    // the value is fetched on demand from a CSRF-protected, vault-gated endpoint
    // (which returns {"value": "..."}) and written straight to the clipboard.
    // Usage: <button type="button" data-copy-fetch="/entries/{uuid}/copy">
    document.addEventListener('click', function (event) {
        var btn = event.target.closest('[data-copy-fetch]');
        if (!btn) {
            return;
        }
        event.preventDefault();
        var url = btn.getAttribute('data-copy-fetch');
        var form = btn.closest('form');
        var tokenEl = form ? form.querySelector('input[name="_csrf"]') : null;
        var token = tokenEl ? tokenEl.value : '';

        btn.disabled = true;
        fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: '_csrf=' + encodeURIComponent(token)
        }).then(function (res) {
            if (!res.ok) {
                throw new Error('request failed');
            }
            return res.json();
        }).then(function (data) {
            return copyToClipboard((data && data.value) || '');
        }).then(function () {
            flashButton(btn, 'Copied!');
        }).catch(function () {
            flashButton(btn, 'Failed');
        }).finally(function () {
            btn.disabled = false;
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

    // Confirmation prompts. Supports both a form-level data-confirm and a
    // submit-button-level data-confirm (the clicked button wins).
    document.addEventListener('submit', function (event) {
        var source = (event.submitter && event.submitter.hasAttribute('data-confirm'))
            ? event.submitter
            : event.target.closest('[data-confirm]');
        if (!source) {
            return;
        }
        if (!window.confirm(source.getAttribute('data-confirm'))) {
            event.preventDefault();
        }
    });

    // Select-all checkbox: <input data-check-all="#form-selector">
    document.addEventListener('change', function (event) {
        var master = event.target.closest('[data-check-all]');
        if (!master) {
            return;
        }
        var scope = document.querySelector(master.getAttribute('data-check-all'));
        if (!scope) {
            return;
        }
        scope.querySelectorAll('input[type="checkbox"][data-row-check]').forEach(function (cb) {
            // Only toggle rows that are currently visible (respect filtering).
            var row = cb.closest('tr');
            if (!row || row.style.display !== 'none') {
                cb.checked = master.checked;
            }
        });
        updateSelectedCount();
    });

    // Keep the "N selected" counter in sync as individual rows change.
    document.addEventListener('change', function (event) {
        if (event.target.closest('[data-row-check]')) {
            updateSelectedCount();
        }
    });

    function updateSelectedCount() {
        document.querySelectorAll('[data-selected-count]').forEach(function (out) {
            var form = out.closest('form') || document;
            var checked = form.querySelectorAll('input[type="checkbox"][data-row-check]:checked').length;
            out.textContent = checked;
        });
    }

    // Live row filter: <input data-filter-target="#tbody-selector">
    document.querySelectorAll('[data-filter-target]').forEach(function (input) {
        var tbody = document.querySelector(input.getAttribute('data-filter-target'));
        if (!tbody) {
            return;
        }
        input.addEventListener('input', function () {
            var q = input.value.trim().toLowerCase();
            tbody.querySelectorAll('tr').forEach(function (row) {
                var text = (row.getAttribute('data-search') || row.textContent || '').toLowerCase();
                row.style.display = (q === '' || text.indexOf(q) !== -1) ? '' : 'none';
            });
        });
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
