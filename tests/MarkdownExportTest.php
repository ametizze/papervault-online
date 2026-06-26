<?php

declare(strict_types=1);

use SimpleVault\Markdown\FilenameSanitizer;
use SimpleVault\Markdown\MarkdownExportService;
use SimpleVault\Models\Note;

function make_note(array $payload): Note
{
    return Note::fromRow([
        'uuid' => '11111111-1111-4111-8111-111111111111',
        'favorite' => 0,
        'archived' => 0,
        'created_at' => '2026-01-01T00:00:00+00:00',
        'updated_at' => '2026-01-02T00:00:00+00:00',
    ], $payload);
}

test('export produces front matter and body', function () {
    $exporter = new MarkdownExportService();
    $note = make_note([
        'title' => 'Pixtrive Upload Architecture',
        'client' => 'Pixtrive',
        'project' => 'Photo Upload System',
        'markdown' => "# Upload Flow\n\n- Step one",
        'tags' => ['laravel', 'uploads'],
    ]);
    $md = $exporter->noteToMarkdown($note);

    assert_true(str_starts_with($md, "---\n"), 'Has front matter fence');
    assert_true(str_contains($md, 'title: "Pixtrive Upload Architecture"'));
    assert_true(str_contains($md, 'client: "Pixtrive"'));
    assert_true(str_contains($md, "  - \"laravel\""));
    assert_true(str_contains($md, '# Upload Flow'));
});

test('filename is sanitized and dated', function () {
    $exporter = new MarkdownExportService();
    $note = make_note([
        'title' => 'My Note: Special/Chars!',
        'client' => 'XTOOLS USA',
        'project' => '',
        'markdown' => 'x',
        'tags' => [],
    ]);
    $name = $exporter->filenameFor($note);
    assert_equals('2026-01-02-xtools-usa-my-note-special-chars.md', $name);
});

test('filename sanitizer strips traversal and unsafe chars', function () {
    $san = new FilenameSanitizer();
    $slug = $san->slug('../../etc/passwd');
    assert_false(str_contains($slug, '/'));
    assert_false(str_contains($slug, '.'));
    assert_true(str_contains($slug, 'etc'));
});

test('zip export contains markdown files', function () {
    $exporter = new MarkdownExportService();
    $notes = [
        make_note(['title' => 'A', 'client' => '', 'project' => '', 'markdown' => 'aaa', 'tags' => []]),
        make_note(['title' => 'B', 'client' => '', 'project' => '', 'markdown' => 'bbb', 'tags' => []]),
    ];
    $zipBytes = $exporter->notesToZip($notes);

    $tmp = tempnam(sys_get_temp_dir(), 'svt');
    file_put_contents($tmp, $zipBytes);
    $zip = new ZipArchive();
    assert_true($zip->open($tmp) === true);
    assert_equals(2, $zip->numFiles);
    $zip->close();
    @unlink($tmp);
});
