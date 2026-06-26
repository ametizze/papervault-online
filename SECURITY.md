# Security Policy

## Supported Use

SimpleVault is intended for **personal/private self-hosted use** on a VPS you
control, served over HTTPS. It has not been independently audited and makes no
claim of parity with mature password managers such as 1Password or Bitwarden.

## Threat Model

**SimpleVault aims to protect against:**

- Disclosure of vault contents if the **database file alone** is stolen (entries,
  notes, and the vault key are encrypted; the vault key is wrapped by your Master
  Password via Argon2id).
- Disclosure of an **exported encrypted backup** if it leaks (same protection).
- Common web attacks: CSRF (synchronizer tokens on all state-changing requests),
  session fixation (ID regeneration), clickjacking (`X-Frame-Options`/CSP
  `frame-ancestors`), stored XSS in notes (HTML escaped before Markdown
  rendering), and online password guessing (Argon2id + login rate limiting).

**SimpleVault does NOT protect against:**

- A compromised server / VPS, malicious server-side code, or a compromised PHP
  runtime. While the vault is unlocked, the decrypted Vault Key lives in the
  server-side session and could be read by an attacker with server access.
- A compromised client device, browser, or browser extension.
- A broken or absent HTTPS configuration (traffic interception).
- A weak Master Password (the Master Password is the root of all protection).
- An attacker who already has your Master Password and Key File.
- Sophisticated targeted attacks, side channels, or memory forensics.

Encryption is at rest. Decryption happens server-side while unlocked; this is a
documented usability/security tradeoff (see `docs/CRYPTO_DESIGN.md`).

## What Is Encrypted

- Password entries (the entire JSON payload):
  - Title, URL, username, password, entry notes, client, project, tags
- Markdown notes (the entire JSON payload):
  - Title, client name, project name, tags, and Markdown body
- The Vault Key itself (wrapped by the Key Encryption Key)

## What Is Not Encrypted

- User email
- Database row IDs and record UUIDs
- Created/updated timestamps
- Favorite / archived flags
- Audit event types (and request IP / user-agent metadata)
- KDF parameters and the per-vault salt (these are not secret by design)

## Master Password

The Master Password derives the key that unwraps your Vault Key. It is never
stored (not in the database, not in the session). **There is no recovery.** If you
forget it, your encrypted data is permanently unreadable.

## Key File

If you enable a Key File, it becomes a required second factor for unlocking. Its
contents are never stored server-side. **If a required Key File is lost, the vault
cannot be unlocked**, even with the correct Master Password.

## Backups

- **Encrypted full-vault backup** (`/export`): contains only encrypted data and
  the wrapped vault key. Safe to store off-server; still requires the Master
  Password (and Key File, if used) to restore/decrypt.
- **Plaintext Markdown export** (`/notes/export`): produces unencrypted `.md`
  files. Anyone with the files can read them. Treat these as sensitive.

Automatic encrypted safety backups are written to `storage/backups/` before any
destructive import.

## Reporting Security Issues

This is a private project. Report issues to the repository owner directly. Do not
include secrets, real Master Passwords, or unredacted vault data in any report.
