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

    // Add a custom-field row by cloning a <template>, giving it a unique index
    // so its "fields[IDX][...]" inputs stay grouped:
    //   <button data-add-field="#container" data-template="#tpl">
    document.addEventListener('click', function (event) {
        var btn = event.target.closest('[data-add-field]');
        if (!btn) {
            return;
        }
        event.preventDefault();
        var container = document.querySelector(btn.getAttribute('data-add-field'));
        var tpl = document.querySelector(btn.getAttribute('data-template'));
        if (!container || !tpl) {
            return;
        }
        var idx = 'new' + Date.now() + Math.floor(Math.random() * 1000);
        var markup = tpl.innerHTML.replace(/__INDEX__/g, idx);
        var holder = document.createElement('div');
        holder.innerHTML = markup.trim();
        var row = holder.firstElementChild;
        if (row) {
            container.appendChild(row);
            var firstInput = row.querySelector('input[type="text"]');
            if (firstInput) {
                firstInput.focus();
            }
            checkDuplicateFieldNames();
        }
    });

    // Remove a dynamic row: <button data-remove-row> inside <... data-field-row>
    document.addEventListener('click', function (event) {
        var btn = event.target.closest('[data-remove-row]');
        if (!btn) {
            return;
        }
        event.preventDefault();
        var row = btn.closest('[data-field-row]');
        if (row) {
            row.remove();
            checkDuplicateFieldNames();
        }
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

    // Flag custom-field rows whose name duplicates another row's name. Defined
    // as a hoisted function so the add/remove handlers above can call it.
    function checkDuplicateFieldNames() {
        var inputs = document.querySelectorAll('[data-field-name]');
        var counts = {};
        inputs.forEach(function (input) {
            var key = input.value.trim().toLowerCase();
            if (key) {
                counts[key] = (counts[key] || 0) + 1;
            }
        });
        inputs.forEach(function (input) {
            var key = input.value.trim().toLowerCase();
            var dup = !!key && counts[key] > 1;
            var row = input.closest('[data-field-row]');
            var warn = row ? row.querySelector('[data-dup-warning]') : null;
            if (warn) {
                warn.classList.toggle('d-none', !dup);
            }
            input.classList.toggle('is-invalid', dup);
        });
    }
    document.addEventListener('input', function (event) {
        if (event.target.closest('[data-field-name]')) {
            checkDuplicateFieldNames();
        }
    });
    checkDuplicateFieldNames();

    // TOTP widgets: fetch the current code on demand from a CSRF-gated endpoint
    // and count down locally, refetching when the 30s window rolls over. The
    // base32 secret never reaches the browser — only the rotating code does.
    // <... data-totp data-totp-url="/entries/{id}/fields/{fieldId}/totp">
    function initTotp(widget) {
        var url = widget.getAttribute('data-totp-url');
        var codeEl = widget.querySelector('[data-totp-code]');
        var remainEl = widget.querySelector('[data-totp-remaining]');
        var copyBtn = widget.querySelector('[data-totp-copy]');
        var tokenEl = document.querySelector('input[name="_csrf"]');
        var token = tokenEl ? tokenEl.value : '';
        var current = '';
        var timer = null;

        function startCountdown(remaining) {
            if (timer) {
                clearInterval(timer);
            }
            function tick() {
                if (remainEl) {
                    remainEl.textContent = remaining + 's';
                }
                if (remaining <= 0) {
                    clearInterval(timer);
                    fetchCode();
                    return;
                }
                remaining -= 1;
            }
            tick();
            timer = setInterval(tick, 1000);
        }

        function fetchCode() {
            return fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: '_csrf=' + encodeURIComponent(token)
            }).then(function (res) {
                return res.ok ? res.json() : Promise.reject(new Error('failed'));
            }).then(function (data) {
                current = (data && data.code) || '';
                if (codeEl) {
                    codeEl.textContent = current ? current.replace(/(\d{3})(\d{3})/, '$1 $2') : 'error';
                }
                startCountdown((data && data.remaining) || 0);
            }).catch(function () {
                if (codeEl) {
                    codeEl.textContent = 'error';
                }
            });
        }

        if (copyBtn) {
            copyBtn.addEventListener('click', function (event) {
                event.preventDefault();
                copyToClipboard(current)
                    .then(function () { flashButton(copyBtn, 'Copied!'); })
                    .catch(function () { flashButton(copyBtn, 'Failed'); });
            });
        }
        fetchCode();
    }
    document.querySelectorAll('[data-totp]').forEach(initTotp);

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
