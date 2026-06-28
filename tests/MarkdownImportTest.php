<?php

declare(strict_types=1);

use SimpleVault\Markdown\MarkdownImportService;
use SimpleVault\Markdown\MarkdownPreviewService;

test('parses front matter and body', function () {
    $importer = new MarkdownImportService();
    $content = <<<MD
    ---
    title: "Example Note"
    client: "Client Name"
    project: "Project Name"
    tags:
      - tag1
      - tag2
    ---

    # Content

    Body text.
    MD;

    $note = $importer->parse($content, 'fallback.md');
    assert_equals('Example Note', $note['title']);
    assert_equals('Client Name', $note['client']);
    assert_equals('Project Name', $note['project']);
    assert_equals(['tag1', 'tag2'], $note['tags']);
    assert_true(str_starts_with($note['markdown'], '# Content'));
});

test('parses inline tag list', function () {
    $importer = new MarkdownImportService();
    $content = "---\ntitle: T\ntags: [a, b, c]\n---\nbody";
    $note = $importer->parse($content, 'x.md');
    assert_equals(['a', 'b', 'c'], $note['tags']);
});

test('falls back to filename title when no front matter', function () {
    $importer = new MarkdownImportService();
    $note = $importer->parse("Just some text", '2026-01-01-vps-hardening-checklist.md');
    assert_equals('Vps Hardening Checklist', $note['title']);
    assert_equals('', $note['client']);
    assert_equals('Just some text', $note['markdown']);
});

test('rejects oversized content', function () {
    $importer = new MarkdownImportService();
    $big = str_repeat('a', (int) config('max_markdown_note_kb', 512) * 1024 + 10);
    assert_throws(fn () => $importer->parse($big, 'x.md'));
});

test('markdown extension detection', function () {
    $importer = new MarkdownImportService();
    assert_true($importer->hasMarkdownExtension('note.md'));
    assert_true($importer->hasMarkdownExtension('note.markdown'));
    assert_false($importer->hasMarkdownExtension('note.php'));
});

test('preview escapes raw HTML (no XSS)', function () {
    $preview = new MarkdownPreviewService();
    $html = $preview->toHtml('<script>alert(1)</script>');
    assert_false(str_contains($html, '<script>'), 'Raw script tag must be escaped');
    assert_true(str_contains($html, '&lt;script&gt;'));
});

test('preview renders headings, bold, code, and safe links', function () {
    $preview = new MarkdownPreviewService();
    $html = $preview->toHtml("# Title\n\n**bold** and `code`\n\n[link](https://example.com)");
    assert_true(str_contains($html, '<h1>Title</h1>'));
    assert_true(str_contains($html, '<strong>bold</strong>'));
    assert_true(str_contains($html, '<code>code</code>'));
    assert_true(str_contains($html, 'href="https://example.com"'));
});

test('preview drops javascript links', function () {
    $preview = new MarkdownPreviewService();
    $html = $preview->toHtml('[click](javascript:alert(1))');
    assert_false(str_contains($html, 'javascript:'), 'javascript: scheme must be removed');
});

test('preview renders task list checkboxes', function () {
    $preview = new MarkdownPreviewService();
    $html = $preview->toHtml("- [ ] write spec\n- [x] ship it\n- plain item");
    assert_true(str_contains($html, 'task-list-item'));
    assert_true(str_contains($html, '<input type="checkbox" disabled> write spec'));
    assert_true(str_contains($html, '<input type="checkbox" disabled checked> ship it'));
    // A normal bullet stays a normal <li>.
    assert_true(str_contains($html, '<li>plain item</li>'));
});

test('inline render applies inline markdown without block wrapping', function () {
    $preview = new MarkdownPreviewService();
    $html = $preview->toInline('**bold** [link](https://example.com)');
    assert_true(str_contains($html, '<strong>bold</strong>'));
    assert_true(str_contains($html, 'href="https://example.com"'));
    assert_false(str_contains($html, '<p>'), 'Inline render must not wrap in block elements');
});

test('inline render escapes raw HTML and collapses newlines', function () {
    $preview = new MarkdownPreviewService();
    $html = $preview->toInline("<script>alert(1)</script>\nsecond line");
    assert_false(str_contains($html, '<script>'), 'Raw script tag must be escaped');
    assert_true(str_contains($html, '&lt;script&gt;'));
    assert_false(str_contains($html, "\n"), 'Newlines must collapse to spaces');
});

test('inline render drops javascript links', function () {
    $preview = new MarkdownPreviewService();
    $html = $preview->toInline('[click](javascript:alert(1))');
    assert_false(str_contains($html, 'javascript:'), 'javascript: scheme must be removed');
});
