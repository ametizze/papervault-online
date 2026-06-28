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

    // Row filtering: a free-text search (<input data-filter-target="#tbody">)
    // combined with optional quick-filter chips
    // (<button data-quick-filter data-filter-target="#tbody"
    //          data-filter-key="status" data-filter-value="open">).
    // Both constraints must pass for a row to show; state lives on the tbody.
    function applyRowFilter(tbody) {
        var q = (tbody._filterQuery || '').toLowerCase();
        var key = tbody._qfKey || '';
        var val = tbody._qfVal || '';
        tbody.querySelectorAll('tr').forEach(function (row) {
            var text = (row.getAttribute('data-search') || row.textContent || '').toLowerCase();
            var textOk = q === '' || text.indexOf(q) !== -1;
            var chipOk = !key || (row.getAttribute('data-' + key) || '') === val;
            row.style.display = (textOk && chipOk) ? '' : 'none';
        });
    }
    document.querySelectorAll('[data-filter-target]').forEach(function (input) {
        var tbody = document.querySelector(input.getAttribute('data-filter-target'));
        if (!tbody) {
            return;
        }
        input.addEventListener('input', function () {
            tbody._filterQuery = input.value.trim();
            applyRowFilter(tbody);
        });
    });
    document.addEventListener('click', function (event) {
        var chip = event.target.closest('[data-quick-filter]');
        if (!chip) {
            return;
        }
        event.preventDefault();
        var tbody = document.querySelector(chip.getAttribute('data-filter-target'));
        if (!tbody) {
            return;
        }
        var group = chip.closest('[data-quick-filters]');
        var wasActive = chip.classList.contains('active');
        if (group) {
            group.querySelectorAll('[data-quick-filter]').forEach(function (c) { c.classList.remove('active'); });
        }
        if (wasActive) {
            tbody._qfKey = '';
            tbody._qfVal = '';
        } else {
            chip.classList.add('active');
            tbody._qfKey = chip.getAttribute('data-filter-key') || '';
            tbody._qfVal = chip.getAttribute('data-filter-value') || '';
        }
        applyRowFilter(tbody);
    });

    // Quick-set a date input to N days from today:
    // <button data-due-days="7" data-due-target="#some-date-input">
    document.addEventListener('click', function (event) {
        var btn = event.target.closest('[data-due-days]');
        if (!btn) {
            return;
        }
        event.preventDefault();
        var target = document.querySelector(btn.getAttribute('data-due-target'));
        if (!target) {
            return;
        }
        var d = new Date();
        d.setDate(d.getDate() + parseInt(btn.getAttribute('data-due-days'), 10));
        target.value = d.getFullYear() + '-' +
            String(d.getMonth() + 1).padStart(2, '0') + '-' +
            String(d.getDate()).padStart(2, '0');
    });

    // Drag-reorder custom-field rows. The form submits fields in DOM order, so
    // moving a row in the DOM is all that's needed to persist the new order on
    // save. A row only becomes draggable while its handle is held, so the
    // inputs inside it stay normally interactive.
    var draggingRow = null;
    document.addEventListener('mousedown', function (event) {
        var handle = event.target.closest('[data-drag-handle]');
        if (!handle) {
            return;
        }
        var row = handle.closest('[data-field-row]');
        if (row) {
            row.setAttribute('draggable', 'true');
        }
    });
    document.addEventListener('dragstart', function (event) {
        var row = event.target.closest('[data-field-row]');
        if (!row || row.getAttribute('draggable') !== 'true') {
            return;
        }
        draggingRow = row;
        row.classList.add('dragging');
        if (event.dataTransfer) {
            event.dataTransfer.effectAllowed = 'move';
        }
    });
    document.addEventListener('dragover', function (event) {
        if (!draggingRow) {
            return;
        }
        event.preventDefault();
        var over = event.target.closest('[data-field-row]');
        var container = draggingRow.parentNode;
        if (!over || over === draggingRow || over.parentNode !== container) {
            return;
        }
        var rect = over.getBoundingClientRect();
        var after = (event.clientY - rect.top) > rect.height / 2;
        container.insertBefore(draggingRow, after ? over.nextSibling : over);
    });
    document.addEventListener('dragend', function () {
        if (draggingRow) {
            draggingRow.classList.remove('dragging');
            draggingRow.removeAttribute('draggable');
            draggingRow = null;
        }
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

    // Theme switcher: <select data-theme-select> writes a year-long cookie and
    // applies the theme live. The server reads the same cookie on the next load
    // (so there is no flash), and dark palettes also flip Bootstrap's theme.
    var themeSelect = document.querySelector('[data-theme-select]');
    if (themeSelect) {
        var darkThemes = { dracula: true, monokai: true };
        themeSelect.addEventListener('change', function () {
            var value = themeSelect.value;
            document.cookie = 'theme=' + encodeURIComponent(value) + ';path=/;max-age=31536000;samesite=lax';
            document.documentElement.setAttribute('data-theme', value);
            document.documentElement.setAttribute('data-bs-theme', darkThemes[value] ? 'dark' : 'light');
        });
    }

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
