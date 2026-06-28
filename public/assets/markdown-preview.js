/* Client-side Markdown preview.
 *
 * SECURITY: the input is HTML-escaped FIRST, then a small set of Markdown
 * rules is applied to the escaped text. Raw HTML in the note can therefore
 * never be injected into the preview. This mirrors the server-side
 * MarkdownPreviewService used when displaying a saved note.
 *
 * Wire-up (supports multiple independent editors on one page): each editor
 * shares a group id across its parts —
 *   <textarea data-md-source="ID">
 *   <div data-md-preview="ID">
 *   <button data-md-toggle="ID">  (optional)
 * Parts with the same ID value are paired together.
 */
(function () {
    'use strict';

    function escapeHtml(text) {
        return text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function inline(text) {
        // Inline code first.
        text = text.replace(/`([^`]+)`/g, function (_, c) {
            return '<code>' + c + '</code>';
        });
        text = text.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        text = text.replace(/(^|[^*])\*([^*]+)\*(?!\*)/g, '$1<em>$2</em>');
        text = text.replace(/_([^_]+)_/g, '<em>$1</em>');
        // Links: [text](url) limited to http/https/mailto.
        text = text.replace(/\[([^\]]+)\]\(([^)]+)\)/g, function (_, label, url) {
            var decoded = url.replace(/&amp;/g, '&');
            if (!/^(https?:|mailto:)/i.test(decoded)) {
                return label;
            }
            return '<a href="' + escapeHtml(decoded) + '" target="_blank" rel="noopener noreferrer nofollow">' + label + '</a>';
        });
        return text;
    }

    function render(markdown) {
        markdown = markdown.replace(/\r\n?/g, '\n');
        var escaped = escapeHtml(markdown);
        var lines = escaped.split('\n');
        var html = [];
        var inCode = false;
        var listType = null;
        var paragraph = [];

        function flushParagraph() {
            if (paragraph.length) {
                html.push('<p>' + inline(paragraph.join('<br>')) + '</p>');
                paragraph = [];
            }
        }
        function closeList() {
            if (listType) {
                html.push('</' + listType + '>');
                listType = null;
            }
        }

        lines.forEach(function (line) {
            var trimmed = line.trim();

            if (/^```/.test(trimmed)) {
                flushParagraph();
                closeList();
                if (inCode) {
                    html.push('</code></pre>');
                    inCode = false;
                } else {
                    html.push('<pre><code>');
                    inCode = true;
                }
                return;
            }
            if (inCode) {
                html.push(line);
                return;
            }
            if (trimmed === '') {
                flushParagraph();
                closeList();
                return;
            }
            var h = trimmed.match(/^(#{1,6})\s+(.*)$/);
            if (h) {
                flushParagraph();
                closeList();
                var level = h[1].length;
                html.push('<h' + level + '>' + inline(h[2]) + '</h' + level + '>');
                return;
            }
            var ul = trimmed.match(/^[-*+]\s+(.*)$/);
            if (ul) {
                flushParagraph();
                if (listType !== 'ul') {
                    closeList();
                    html.push('<ul>');
                    listType = 'ul';
                }
                var task = ul[1].match(/^\[([ xX])\]\s+(.*)$/);
                if (task) {
                    var checked = task[1].toLowerCase() === 'x' ? ' checked' : '';
                    html.push('<li class="task-list-item"><input type="checkbox" disabled' + checked + '> ' + inline(task[2]) + '</li>');
                } else {
                    html.push('<li>' + inline(ul[1]) + '</li>');
                }
                return;
            }
            var ol = trimmed.match(/^\d+\.\s+(.*)$/);
            if (ol) {
                flushParagraph();
                if (listType !== 'ol') {
                    closeList();
                    html.push('<ol>');
                    listType = 'ol';
                }
                html.push('<li>' + inline(ol[1]) + '</li>');
                return;
            }
            closeList();
            paragraph.push(trimmed);
        });

        if (inCode) {
            html.push('</code></pre>');
        }
        flushParagraph();
        closeList();
        return html.join('\n');
    }

    function attrSelector(name, id) {
        return '[' + name + '="' + (window.CSS && CSS.escape ? CSS.escape(id) : id) + '"]';
    }

    function wire(source) {
        var id = source.getAttribute('data-md-source');
        var preview = document.querySelector(attrSelector('data-md-preview', id));
        if (!preview) {
            return;
        }

        function update() {
            preview.innerHTML = render(source.value);
        }
        source.addEventListener('input', update);
        update();

        var toggle = document.querySelector(attrSelector('data-md-toggle', id));
        if (toggle) {
            toggle.addEventListener('click', function (e) {
                e.preventDefault();
                var showingPreview = preview.classList.toggle('d-none') === false;
                source.classList.toggle('d-none', showingPreview);
                toggle.textContent = showingPreview ? 'Write' : 'Preview';
            });
        }
    }

    document.querySelectorAll('[data-md-source]').forEach(wire);
})();
