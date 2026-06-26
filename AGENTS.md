# AGENTS.md

## Project Overview

SimpleVault is a plain PHP self-hosted password manager and encrypted Markdown
notes application. No Laravel/Symfony. Native PHP + libsodium + PDO + Bootstrap 5.
SQLite by default, optional MySQL.

## Important Rules for AI Agents

- Do not implement custom cryptography.
- Use PHP Sodium functions only for encryption.
- Never log secrets.
- Never store plaintext passwords, entries, notes, Master Passwords, or Vault Keys.
- Do not expose decrypted data outside unlocked-vault flows.
- Keep code and comments in English.
- Keep the app framework-light.
- Do not introduce Laravel, Symfony, React, Vue, or heavy dependencies without explicit approval.
- Preserve SQLite support.
- Keep the public document root inside `/public`.

## Security Requirements

- The server must never persist: plaintext passwords, entry payloads, note
  payloads, the Master Password, the decrypted Vault Key, Key File contents, or
  recovery secrets.
- The database stores only encrypted sensitive data plus non-sensitive metadata
  (IDs, UUIDs, favorite/archived flags, timestamps, user email, audit event types).
- Authentication password: `password_hash(..., PASSWORD_ARGON2ID)` /
  `password_verify`. Never store it in the session.
- Vault encryption: envelope encryption (see Cryptography Rules).
- Sessions: `HttpOnly`, `Secure`, `SameSite=Strict`, short lifetime, ID
  regeneration on login and on vault unlock, inactivity auto-lock, manual lock.
- CSRF: every POST/PUT/PATCH/DELETE validates a synchronizer token
  (`Csrf::token()` / `Csrf::validate()`), enforced centrally in `Router`.
- Login rate limiting keyed by (IP, email); generic `Invalid credentials.`
  message; no user enumeration.
- Security headers + a strict CSP are sent on every response (see
  `Core/Response.php`). Avoid inline JS; assets are vendored under `public/assets/`.
- Output is escaped with `e()`. The only "raw" echo is the Markdown renderer
  output, which escapes input **before** applying Markdown rules.

## Cryptography Rules

- **Vault Key**: random 32-byte secretbox key
  (`random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES)`), generated once per vault.
- **Records**: each entry/note payload is JSON, encrypted with the Vault Key via
  `sodium_crypto_secretbox()` using a fresh per-record nonce
  (`random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES)`). Stored as base64
  `encrypted_payload` + `payload_nonce`.
- **Key Encryption Key (KEK)**: derived from Master Password (+ optional Key File
  material) with `sodium_crypto_pwhash()` (Argon2id), a per-vault random salt, and
  configurable ops/mem limits. The KEK is never stored.
- **Vault Key wrapping**: the Vault Key is encrypted with the KEK and stored as
  `encrypted_vault_key` + `vault_key_nonce` alongside `salt`, `kdf_ops_limit`,
  `kdf_mem_limit`.
- **Key File**: optional JSON file with high-entropy random `key_material`
  (base64). Validated on use, never stored server-side, never written to disk
  beyond the transient upload, which is deleted after reading.
- **Master Password change**: unwrap the Vault Key, re-wrap with a new salt. Do
  **not** re-encrypt individual records.

## Markdown Notes Rules

- The full note payload (`title`, `client`, `project`, `tags`, `markdown`) is
  encrypted with the Vault Key. Plain columns are metadata only.
- `client`/`project` are free-text fields inside the encrypted payload. Do not add
  CRM tables for the MVP.
- Markdown export is **plaintext**; require an unlocked vault and warn the user.
- Markdown import must validate extension/size/count, parse optional front matter
  (a limited YAML subset), encrypt before storing, and delete temp uploads.
- The preview (server `MarkdownPreviewService` and client `markdown-preview.js`)
  escapes HTML first, then applies a small Markdown subset; links restricted to
  http/https/mailto. This prevents stored XSS.

## File Structure

```
public/        Front controller + vendored assets (document root)
app/Core/      App, Router, Request, Response, Session, Csrf, RateLimiter,
               Validator, View, Uuid, Logger
app/Crypto/    CryptoService, KeyDerivationService, VaultKeyService, KeyFileService
app/Markdown/  Export/Import/Preview services + FilenameSanitizer
app/Support/   PasswordGenerator, BackupService
app/Models/    User, Vault, Entry, Note (DTOs)
app/Repositories/  PDO data access
app/Controllers/   Auth, Vault, Entry, Note, Generator, ImportExport, Settings
app/Views/     Plain PHP templates (no logic beyond presentation)
config/        app.php
database/      schema.sql, migrations
scripts/       migrate, install, backup, rotate-backups
storage/       backups, exports, temp, logs (not web-accessible)
tests/         dependency-free test runner + *Test.php
docs/          deployment, backup/restore, crypto design, markdown notes
```

## Coding Style

- PHP 8.3+.
- `declare(strict_types=1);` at the top of every PHP file.
- Small classes, clear method names.
- English comments only.
- No business logic in views; escape all output with `e()`.
- Constructor property promotion and readonly DTOs where natural.

## Testing

Run `php tests/run.php` (or `composer test`). Require tests for: crypto
(encrypt/decrypt, wrong-key failure, nonce uniqueness), key derivation, vault-key
envelope, Master Password change, Markdown export/import, front matter parsing,
filename sanitization, backup import/export, CSRF, and login rate limiting.

## Forbidden Changes

- Do not replace Sodium with weak crypto (MD5, SHA1, raw SHA256 for passwords,
  homemade/XOR/base64 "encryption").
- Do not store encryption keys in `.env`.
- Do not make `/storage` (or `/database`, `/app`, `/config`, `.env`) web-accessible.
- Do not disable CSRF.
- Do not disable authentication.
- Do not weaken session security.
- Do not store the Master Password or decrypted Vault Key anywhere persistent.
