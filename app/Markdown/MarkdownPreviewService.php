<?php

declare(strict_types=1);

namespace SimpleVault\Markdown;

/**
 * Minimal, security-first Markdown renderer.
 *
 * Strategy: HTML-escape the entire input FIRST, then apply a small set of
 * Markdown transformations on the already-escaped text. Because the input is
 * escaped up front, no raw HTML from the note can ever reach the browser, so
 * stored-XSS via note content is not possible through this renderer.
 *
 * Supported: headings, bold, italic, inline code, fenced code blocks,
 * unordered/ordered lists, links (href validated to http/https/mailto),
 * paragraphs and line breaks.
 */
final class MarkdownPreviewService
{
    public function toHtml(string $markdown): string
    {
        $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);

        // 1) Escape everything up front.
        $escaped = e($markdown);

        $lines = explode("\n", $escaped);
        $html = [];
        $inCode = false;
        $listType = null; // 'ul' | 'ol' | null
        $paragraph = [];

        $flushParagraph = function () use (&$paragraph, &$html): void {
            if ($paragraph !== []) {
                $html[] = '<p>' . $this->inline(implode('<br>', $paragraph)) . '</p>';
                $paragraph = [];
            }
        };
        $closeList = function () use (&$listType, &$html): void {
            if ($listType !== null) {
                $html[] = '</' . $listType . '>';
                $listType = null;
            }
        };

        foreach ($lines as $line) {
            // Fenced code blocks.
            if (preg_match('/^```/', trim($line))) {
                $flushParagraph();
                $closeList();
                if ($inCode) {
                    $html[] = '</code></pre>';
                    $inCode = false;
                } else {
                    $html[] = '<pre><code>';
                    $inCode = true;
                }
                continue;
            }

            if ($inCode) {
                $html[] = $line;
                continue;
            }

            $trimmed = trim($line);

            if ($trimmed === '') {
                $flushParagraph();
                $closeList();
                continue;
            }

            // Headings.
            if (preg_match('/^(#{1,6})\s+(.*)$/', $trimmed, $m)) {
                $flushParagraph();
                $closeList();
                $level = strlen($m[1]);
                $html[] = "<h$level>" . $this->inline($m[2]) . "</h$level>";
                continue;
            }

            // Unordered list.
            if (preg_match('/^[-*+]\s+(.*)$/', $trimmed, $m)) {
                $flushParagraph();
                if ($listType !== 'ul') {
                    $closeList();
                    $html[] = '<ul>';
                    $listType = 'ul';
                }
                $html[] = '<li>' . $this->inline($m[1]) . '</li>';
                continue;
            }

            // Ordered list.
            if (preg_match('/^\d+\.\s+(.*)$/', $trimmed, $m)) {
                $flushParagraph();
                if ($listType !== 'ol') {
                    $closeList();
                    $html[] = '<ol>';
                    $listType = 'ol';
                }
                $html[] = '<li>' . $this->inline($m[1]) . '</li>';
                continue;
            }

            // Otherwise paragraph text.
            $closeList();
            $paragraph[] = $trimmed;
        }

        if ($inCode) {
            $html[] = '</code></pre>';
        }
        $flushParagraph();
        $closeList();

        return implode("\n", $html);
    }

    /**
     * Inline transformations on already-escaped text.
     */
    private function inline(string $text): string
    {
        // Inline code first so other rules don't touch its contents.
        $text = preg_replace_callback('/`([^`]+)`/', static function ($m) {
            return '<code>' . $m[1] . '</code>';
        }, $text) ?? $text;

        // Bold then italic.
        $text = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text) ?? $text;
        $text = preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/', '<em>$1</em>', $text) ?? $text;
        $text = preg_replace('/_([^_]+)_/', '<em>$1</em>', $text) ?? $text;

        // Links: [text](url) — validate the URL scheme.
        $text = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', function ($m) {
            $label = $m[1];
            $url = $this->safeUrl($m[2]);
            if ($url === null) {
                return $label;
            }

            return '<a href="' . $url . '" rel="noopener noreferrer nofollow" target="_blank">' . $label . '</a>';
        }, $text) ?? $text;

        return $text;
    }

    /**
     * Allow only http(s) and mailto links. The URL is already HTML-escaped.
     */
    private function safeUrl(string $url): ?string
    {
        // The text was HTML-escaped earlier, so "&" appears as "&amp;" etc.
        $decoded = html_entity_decode($url, ENT_QUOTES, 'UTF-8');
        $scheme = strtolower((string) parse_url($decoded, PHP_URL_SCHEME));

        if (!in_array($scheme, ['http', 'https', 'mailto'], true)) {
            return null;
        }

        // Re-escape for safe attribute output.
        return e($decoded);
    }
}
