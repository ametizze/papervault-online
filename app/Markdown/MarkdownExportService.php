<?php

declare(strict_types=1);

namespace SimpleVault\Markdown;

use RuntimeException;
use SimpleVault\Models\Note;
use ZipArchive;

/**
 * Exports decrypted notes to plaintext Markdown files.
 *
 * WARNING: Markdown export produces PLAINTEXT. The caller must require the
 * vault to be unlocked and warn the user that exported files are not encrypted.
 */
final class MarkdownExportService
{
    public function __construct(private FilenameSanitizer $filenames = new FilenameSanitizer())
    {
    }

    /**
     * Render a single note to Markdown with YAML-like front matter.
     */
    public function noteToMarkdown(Note $note): string
    {
        $front = $this->frontMatter($note);

        return $front . "\n" . $note->markdown() . "\n";
    }

    public function filenameFor(Note $note): string
    {
        return $this->filenames->buildNoteFilename(
            $note->updatedAt,
            $note->client(),
            $note->project(),
            $note->title(),
        );
    }

    /**
     * Build a ZIP archive (returned as a binary string) of multiple notes.
     *
     * @param array<int, Note> $notes
     */
    public function notesToZip(array $notes): string
    {
        if ($notes === []) {
            throw new RuntimeException('No notes to export.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'sv_zip_');
        if ($tmp === false) {
            throw new RuntimeException('Unable to create temporary file.');
        }

        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);
            throw new RuntimeException('Unable to create ZIP archive.');
        }

        $used = [];
        foreach ($notes as $note) {
            $name = $this->filenameFor($note);
            // Avoid filename collisions inside the archive.
            $name = $this->uniqueName($name, $used);
            $zip->addFromString($name, $this->noteToMarkdown($note));
        }

        $zip->close();

        $contents = file_get_contents($tmp);
        @unlink($tmp);

        if ($contents === false) {
            throw new RuntimeException('Unable to read generated ZIP archive.');
        }

        return $contents;
    }

    /**
     * Generate front matter manually (no YAML library needed for the MVP).
     */
    private function frontMatter(Note $note): string
    {
        $lines = ['---'];
        $lines[] = 'title: ' . $this->yamlString($note->title());
        $lines[] = 'client: ' . $this->yamlString($note->client());
        $lines[] = 'project: ' . $this->yamlString($note->project());
        $lines[] = 'ticket: ' . $this->yamlString($note->ticket());
        $lines[] = 'status: ' . $this->yamlString($note->status());
        $lines[] = 'expires_at: ' . $this->yamlString($note->expiresAt() ?? '');

        $tags = $note->tags();
        if ($tags === []) {
            $lines[] = 'tags: []';
        } else {
            $lines[] = 'tags:';
            foreach ($tags as $tag) {
                $lines[] = '  - ' . $this->yamlString($tag);
            }
        }

        $lines[] = 'created_at: ' . $this->yamlString($note->createdAt);
        $lines[] = 'updated_at: ' . $this->yamlString($note->updatedAt);
        $lines[] = '---';

        return implode("\n", $lines) . "\n";
    }

    private function yamlString(string $value): string
    {
        // Always quote and escape for safety; keeps generation trivial.
        $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);

        return '"' . $escaped . '"';
    }

    /**
     * @param array<string,bool> $used  by reference
     */
    private function uniqueName(string $name, array &$used): string
    {
        if (!isset($used[$name])) {
            $used[$name] = true;
            return $name;
        }

        $dot = strrpos($name, '.');
        $base = $dot === false ? $name : substr($name, 0, $dot);
        $ext = $dot === false ? '' : substr($name, $dot);

        $i = 2;
        do {
            $candidate = $base . '-' . $i . $ext;
            $i++;
        } while (isset($used[$candidate]));

        $used[$candidate] = true;

        return $candidate;
    }
}
