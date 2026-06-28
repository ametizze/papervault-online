<?php

declare(strict_types=1);

namespace SimpleVault\Markdown;

use RuntimeException;
use SimpleVault\Models\Note;
use ZipArchive;

/**
 * Imports Markdown files into normalized note payloads ready for encryption.
 *
 * Supports optional front matter (a small, deliberately limited YAML subset:
 * scalar key/value lines and simple "- item" tag lists). No external YAML
 * parser is used; unknown constructs are ignored rather than executed.
 *
 * Imported plaintext is never persisted to disk. Callers must encrypt the
 * returned payloads before storing, and remove any temporary uploads.
 */
final class MarkdownImportService
{
    private int $maxContentBytes;

    public function __construct()
    {
        $this->maxContentBytes = (int) config('max_markdown_note_kb', 512) * 1024;
    }

    /**
     * Parse a single Markdown document into a normalized note payload.
     *
     * @return array{title:string,client:string,project:string,ticket:string,status:string,expiresAt:string,markdown:string,tags:array<int,string>}
     */
    public function parse(string $content, string $fallbackTitle): array
    {
        if (strlen($content) > $this->maxContentBytes) {
            throw new RuntimeException('Markdown content exceeds the maximum allowed size.');
        }

        // Normalize line endings.
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        [$frontMatter, $body] = $this->splitFrontMatter($content);

        $title = $frontMatter['title'] ?? $this->titleFromFilename($fallbackTitle);
        if (trim($title) === '') {
            $title = $this->titleFromFilename($fallbackTitle);
        }

        $status = is_string($frontMatter['status'] ?? null) ? trim((string) $frontMatter['status']) : '';
        if (!isset(Note::STATUSES[$status])) {
            $status = '';
        }
        $expiresAt = $this->clampScalar($frontMatter['expires_at'] ?? '', 10);
        if ($expiresAt !== '') {
            $date = \DateTimeImmutable::createFromFormat('Y-m-d', $expiresAt);
            if ($date === false || $date->format('Y-m-d') !== $expiresAt) {
                $expiresAt = '';
            }
        }

        return [
            'title' => $this->clampScalar($title, 200),
            'client' => $this->clampScalar($frontMatter['client'] ?? '', 200),
            'project' => $this->clampScalar($frontMatter['project'] ?? '', 200),
            'ticket' => $this->clampScalar($frontMatter['ticket'] ?? '', 100),
            'status' => $status,
            'expiresAt' => $expiresAt,
            'markdown' => $body,
            'tags' => $this->normalizeTags($frontMatter['tags'] ?? []),
        ];
    }

    /**
     * Extract Markdown files from an uploaded ZIP archive.
     *
     * @return array<int, array{name:string, content:string}>
     */
    public function extractZip(string $zipPath, int $maxFiles): array
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Unable to open ZIP archive.');
        }

        $results = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) {
                continue;
            }

            $name = $stat['name'];

            // Skip directories and anything that is not a .md file.
            if (str_ends_with($name, '/')) {
                continue;
            }
            if (!$this->hasMarkdownExtension($name)) {
                continue;
            }
            // Reject path traversal inside the archive.
            if (str_contains($name, '..') || str_starts_with($name, '/')) {
                continue;
            }
            if ($stat['size'] > $this->maxContentBytes) {
                continue;
            }

            if (count($results) >= $maxFiles) {
                break;
            }

            $content = $zip->getFromIndex($i);
            if ($content === false) {
                continue;
            }

            $results[] = ['name' => basename($name), 'content' => $content];
        }

        $zip->close();

        if ($results === []) {
            throw new RuntimeException('No valid Markdown files were found in the archive.');
        }

        return $results;
    }

    public function hasMarkdownExtension(string $filename): bool
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return in_array($ext, ['md', 'markdown', 'txt'], true);
    }

    public function titleFromFilename(string $filename): string
    {
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $base = preg_replace('/^\d{4}-\d{2}-\d{2}-/', '', $base) ?? $base;
        $base = str_replace(['-', '_'], ' ', $base);

        return ucwords(trim($base)) ?: 'Imported Note';
    }

    /**
     * @return array{0: array<string,mixed>, 1: string}  [frontMatter, body]
     */
    private function splitFrontMatter(string $content): array
    {
        if (!str_starts_with($content, "---\n")) {
            return [[], $content];
        }

        $end = strpos($content, "\n---", 3);
        if ($end === false) {
            return [[], $content];
        }

        $rawFront = substr($content, 4, $end - 4);
        // Body starts after the closing fence line.
        $afterFence = substr($content, $end + 4);
        $body = ltrim($afterFence, "\n");

        return [$this->parseFrontMatter($rawFront), $body];
    }

    /**
     * Parse the limited front matter subset.
     *
     * @return array<string,mixed>
     */
    private function parseFrontMatter(string $raw): array
    {
        $result = [];
        $currentListKey = null;

        foreach (explode("\n", $raw) as $line) {
            // List item belonging to the previous key.
            if (preg_match('/^\s*-\s+(.*)$/', $line, $m) && $currentListKey !== null) {
                if (!isset($result[$currentListKey]) || !is_array($result[$currentListKey])) {
                    $result[$currentListKey] = [];
                }
                $result[$currentListKey][] = $this->unquote(trim($m[1]));
                continue;
            }

            if (!preg_match('/^([A-Za-z0-9_]+):\s*(.*)$/', $line, $m)) {
                continue;
            }

            $key = strtolower($m[1]);
            $value = trim($m[2]);

            if ($value === '' || $value === '[]') {
                // Possibly the start of a list (next lines) or empty scalar.
                $result[$key] = $value === '[]' ? [] : '';
                $currentListKey = $value === '' ? $key : null;
                continue;
            }

            // Inline list: [a, b, c]
            if (str_starts_with($value, '[') && str_ends_with($value, ']')) {
                $inner = substr($value, 1, -1);
                $items = array_map(
                    fn (string $s): string => $this->unquote(trim($s)),
                    array_filter(explode(',', $inner), fn (string $s): bool => trim($s) !== '')
                );
                $result[$key] = array_values($items);
                $currentListKey = null;
                continue;
            }

            $result[$key] = $this->unquote($value);
            $currentListKey = null;
        }

        return $result;
    }

    private function unquote(string $value): string
    {
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
                $value = str_replace(['\\"', '\\\\'], ['"', '\\'], $value);
            }
        }

        return $value;
    }

    /**
     * @param mixed $tags
     * @return array<int,string>
     */
    private function normalizeTags(mixed $tags): array
    {
        if (is_string($tags)) {
            $tags = array_map('trim', explode(',', $tags));
        }
        if (!is_array($tags)) {
            return [];
        }

        $clean = [];
        foreach ($tags as $tag) {
            $tag = trim((string) $tag);
            if ($tag !== '') {
                $clean[] = mb_substr($tag, 0, 50);
            }
            if (count($clean) >= 50) {
                break;
            }
        }

        return array_values(array_unique($clean));
    }

    private function clampScalar(string $value, int $max): string
    {
        return mb_substr(trim($value), 0, $max);
    }
}
