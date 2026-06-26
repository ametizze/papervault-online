# Markdown Notes

SimpleVault stores technical/business notes as **encrypted Markdown**. The whole
note payload is encrypted with the Vault Key; nothing readable is stored in plain
columns.

## How Encrypted Notes Work

Each note's payload is JSON, encrypted with `sodium_crypto_secretbox()` using the
Vault Key and a fresh nonce:

```json
{
  "title": "Pixtrive Upload Architecture",
  "client": "Pixtrive",
  "project": "Photo Upload System",
  "markdown": "# Upload Flow\n\n- Browser uploads file...",
  "tags": ["laravel", "uploads", "r2", "architecture"]
}
```

The database row only holds `uuid`, `encrypted_payload`, `payload_nonce`,
`favorite`, `archived`, and timestamps. Notes can only be listed/read after the
vault is unlocked. When locked, the UI shows *“Unlock your vault to view notes.”*

## Clients and Projects

`client` and `project` are **free-text** fields inside the encrypted payload. A
note may be tied to a client, a project, both, or neither (general notes). No CRM
tables are used. Password entries optionally carry `client`/`project` too.

Examples:
- Client note: `XTOOLS USA`
- Project note: `Pixtrive Upload Architecture`
- General note: `VPS Hardening Checklist`

## Markdown Editor & Preview

The editor is a textarea with a live **Preview** toggle. Both the client preview
(`markdown-preview.js`) and the server renderer (`MarkdownPreviewService`) **escape
all HTML first**, then apply a small Markdown subset:

- Headings (`#`…`######`)
- Bold (`**`), italic (`*` / `_`)
- Inline code (`` `code` ``) and fenced code blocks (```` ``` ````)
- Unordered and ordered lists
- Links `[text](url)` — restricted to `http`, `https`, `mailto`

Because input is escaped before rendering, raw/unsafe HTML in a note can never be
injected (no stored XSS).

## Markdown Export

Export is available per note (`/notes/{id}/export-md`) or in bulk
(`/notes/export`). Options:

1. Export one note as Markdown
2. Export all notes as a ZIP of Markdown files
3. Export notes filtered by client
4. Export notes filtered by project
5. (Encrypted backup of everything is separate — see `BACKUP_AND_RESTORE.md`)

> **Plaintext warning:** Markdown export creates plaintext files. Anyone with
> access to these files can read your notes. The vault must be unlocked to export.

### Filename format

`YYYY-MM-DD-client-project-note-title.md`, sanitized to:

- lowercase
- spaces → hyphens
- unsafe characters removed (no path separators, no `..`)
- collapsed/trimmed hyphens
- length-limited

Example: `2026-01-02-pixtrive-photo-upload-system-pixtrive-upload-architecture.md`

### Front matter

Generated manually (no YAML library needed):

```md
---
title: "Pixtrive Upload Architecture"
client: "Pixtrive"
project: "Photo Upload System"
tags:
  - laravel
  - uploads
created_at: "2026-01-01T00:00:00+00:00"
updated_at: "2026-01-01T00:00:00+00:00"
---

# Pixtrive Upload Architecture
...
```

## Markdown Import

Import a single file, multiple files, or a ZIP (`/notes/import`). Validation:

- Extension (`.md`, `.markdown`, `.txt`)
- File size (`MAX_MARKDOWN_NOTE_KB`) and per-file content limit
- Number of files (`MAX_IMPORT_FILES`)
- Filename safety (basename only; archive entries with `..` or absolute paths are
  skipped)
- ZIP entries are size-checked before extraction

Optional front matter (a deliberately limited YAML subset: scalar `key: value`
lines, inline `[a, b]` lists, and `- item` list blocks) is parsed for `title`,
`client`, `project`, and `tags`. If no front matter exists:

- Title comes from the filename (date prefix stripped, hyphens → spaces)
- `client`, `project`, `tags` are empty
- The full file content becomes the Markdown body

Imported notes are **encrypted before saving**. Uploaded temp files are deleted
after processing; plaintext is never persisted to disk.

## Import Limitations

- The front matter parser supports a small subset of YAML only; complex YAML is
  ignored rather than executed.
- Imported notes always get a new UUID; there is no in-place update via import.
- ZIP import ignores non-Markdown entries and anything over the size limit.
