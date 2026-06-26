# SimpleVault

SimpleVault is a small self-hosted password manager and encrypted Markdown notes app built with plain PHP.

> **Project name:** `SimpleVault` is a temporary name. Change it in one place:
> the `APP_NAME` value in your `.env` (see `config/app.php`).

## Features

- Encrypted password vault
- Encrypted Markdown notes
- Notes by client/project
- Master Password unlock
- Optional Key File (second factor)
- Password generator (`random_int`)
- Encrypted import/export (full-vault backup)
- Markdown notes export/import (single file, multiple files, ZIP)
- SQLite by default (optional MySQL)
- Bootstrap 5 UI

## Security Notice

This is a **personal self-hosted project**. It is **not** a replacement for mature
password managers such as 1Password or Bitwarden, and it has not undergone an
independent security audit.

If the VPS, server-side code, browser, session, or HTTPS configuration is
compromised, decrypted data may be at risk while the vault is unlocked.

**If you lose your Master Password or required Key File, your data cannot be
recovered.** There is no reset and no backdoor.

See [`SECURITY.md`](SECURITY.md) and [`docs/CRYPTO_DESIGN.md`](docs/CRYPTO_DESIGN.md).

## Requirements

- PHP 8.3+ (tested on 8.4)
- PHP `sodium` extension
- PHP `pdo` extension (`pdo_sqlite` and/or `pdo_mysql`)
- PHP `zip` extension (for Markdown ZIP import/export)
- SQLite or MySQL
- Composer (optional — the app runs without it via a built-in autoloader)
- HTTPS in production

## Installation

```bash
# 1. Get the code into your server, then:
cp .env.example .env
# 2. Edit .env: set APP_URL, set DB_DATABASE to an ABSOLUTE path, set APP_DEBUG=false

# 3. (Optional) install Composer autoloader / dotenv:
composer install        # optional; the app also ships a fallback autoloader

# 4. Create the database schema:
php scripts/migrate.php

# 5. Create the first user + vault (interactive):
php scripts/install.php
#    or non-interactive:
SV_EMAIL=you@example.com SV_ACCOUNT_PASSWORD='login-pass' \
SV_MASTER_PASSWORD='master-pass' php scripts/install.php
```

You can also skip `install.php` and create the first user through the web
**Setup** screen (available only while no user exists).

## Running Locally

```bash
php -S 127.0.0.1:8000 -t public
```

Open <http://127.0.0.1:8000>. On first run with an empty database you are sent to
`/setup`.

> The session cookie is marked `Secure`, so on plain `http://` localhost the
> cookie still works because PHP only enforces `Secure` over HTTPS-aware
> contexts; for real use always serve over HTTPS.

## VPS Deployment

The web server's document root **must** be the `public/` directory so that
`app/`, `config/`, `database/`, `storage/`, and `.env` stay out of the web root.

Full Nginx and Apache examples, file permissions, and a backup cron are in
[`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md).

## First Setup

1. Visit the site; with no users you land on `/setup`.
2. Provide an email, an **account password** (login) and a **Master Password**
   (encrypts your vault). They may be the same for a simple personal setup, but
   separating them is more secure.
3. Optionally generate a **Key File**. If enabled it downloads immediately and is
   then **required** every time you unlock. Store it separately from your password.
4. Confirm the no-recovery warning.

After the first user exists, `/setup` is disabled unless
`ALLOW_PUBLIC_REGISTRATION=true`.

## Vault Unlock

After logging in you must unlock the vault with your Master Password (and Key
File, if enabled). The decrypted Vault Key is held in your server-side session
only, and is removed on logout, on manual **Lock Vault**, and automatically after
`VAULT_AUTO_LOCK_MINUTES` of inactivity.

The optional **Key File** (how to generate, use, store, and what happens if you
lose it) has its own guide: [`docs/KEY_FILE.md`](docs/KEY_FILE.md).

## Markdown Notes

Notes store encrypted Markdown plus optional `client`, `project`, and `tags`.
The whole note payload is encrypted with the Vault Key. The editor includes a
live, sanitized preview (raw HTML is escaped). See
[`docs/MARKDOWN_NOTES.md`](docs/MARKDOWN_NOTES.md).

## Backup and Restore

- **Encrypted full-vault backup** (`/export`): a JSON file containing your wrapped
  vault key and all encrypted entries/notes — no plaintext.
- **Markdown notes export** (`/notes/export`): plaintext `.md` files or a ZIP —
  **not encrypted**, use with care.
- **Import** (`/import`): restore an encrypted backup in **Merge** or **Replace**
  mode. A safety backup is written automatically before destructive changes.

See [`docs/BACKUP_AND_RESTORE.md`](docs/BACKUP_AND_RESTORE.md).

## Development

- No build step. Plain PHP + Bootstrap assets vendored in `public/assets/`.
- Optional: `composer install` for PSR-4 autoloading; otherwise a fallback
  autoloader in `public/index.php` is used.
- Coding rules for contributors and AI agents are in [`AGENTS.md`](AGENTS.md).

## Testing

A dependency-free test runner covers crypto, key derivation, vault-key envelope,
Master Password change, Markdown import/export, front matter parsing, filename
sanitization, backup import/export, CSRF, rate limiting, and the password
generator.

```bash
php tests/run.php      # or: composer test
```

## License

Private/internal use unless changed.
