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
