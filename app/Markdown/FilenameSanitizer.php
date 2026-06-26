<?php

declare(strict_types=1);

namespace SimpleVault\Markdown;

/**
 * Produces safe, predictable filenames for Markdown exports.
 *
 * Rules: lowercase, spaces to hyphens, strip unsafe characters, collapse
 * repeated hyphens, and clamp length. The result can never contain path
 * separators or traversal sequences.
 */
final class FilenameSanitizer
{
    private const MAX_SLUG_LENGTH = 80;

    /**
     * Build a filename like: 2026-01-01-pixtrive-photo-upload-architecture.md
     */
    public function buildNoteFilename(
        string $date,
        string $client,
        string $project,
        string $title,
        string $extension = 'md',
    ): string {
        $parts = array_filter([
            $this->datePart($date),
            $this->slug($client),
            $this->slug($project),
            $this->slug($title),
        ], static fn (string $p): bool => $p !== '');

        $base = implode('-', $parts);
        $base = $this->clamp($base);

        if ($base === '') {
            $base = 'note';
        }

        return $base . '.' . $this->slug($extension);
    }

    /**
     * Convert arbitrary text to a filesystem-safe slug.
     */
    public function slug(string $value): string
    {
        $value = strtolower(trim($value));
        // Replace anything not a-z0-9 with a hyphen.
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        // Collapse and trim hyphens.
        $value = trim((string) preg_replace('/-+/', '-', $value), '-');

        return $value;
    }

    private function datePart(string $date): string
    {
        // Accept ISO timestamps or plain dates; keep only YYYY-MM-DD.
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $date, $m)) {
            return $m[1];
        }

        return date('Y-m-d');
    }

    private function clamp(string $value): string
    {
        if (strlen($value) <= self::MAX_SLUG_LENGTH) {
            return $value;
        }

        return trim(substr($value, 0, self::MAX_SLUG_LENGTH), '-');
    }
}
