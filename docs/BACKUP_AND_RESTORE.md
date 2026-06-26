# Backup and Restore

SimpleVault offers two very different export types. Know which one you are using.

## 1. Encrypted full-vault backup (recommended)

- Route: **`/export`** → "Download encrypted backup".
- A JSON file containing your **wrapped vault key**, KDF salt/params, and all
  **encrypted** entries and notes.
- Contains **no plaintext secrets**. Safe to copy off-server (e.g. to cloud
  storage), but it still requires your Master Password (and Key File, if used) to
  restore/decrypt.
- CLI equivalent for all users: `php scripts/backup.php` (writes to
  `storage/backups/`). Rotate with `php scripts/rotate-backups.php [days]`.

## 2. Plaintext Markdown notes export

- Route: **`/notes/export`** (or per-note `/notes/{id}/export-md`).
- Produces unencrypted `.md` files (or a ZIP). **Anyone with the files can read
  them.** Requires an unlocked vault and shows a plaintext warning.
- Use this for portability/migration, not as a secure backup.

## Restoring an encrypted backup

Route: **`/import`**. Upload the backup JSON and choose a mode.

### Import modes

**Merge** (default)
- Adds the backup's records into your current vault.
- Requires the **Master Password that created the backup** (and its Key File, if
  that backup required one), because the backup's records are decrypted with the
  backup's vault key and **re-encrypted under your current vault key**.
- Duplicate UUIDs are skipped, so merging is safe to repeat.
- Your current vault envelope and Master Password are unchanged.

**Replace**
- Overwrites your vault: the vault envelope (wrapped key, salt, KDF params) and all
  entries/notes are replaced by the backup's.
- After replacing, the vault is locked and you must unlock with the **Master
  Password from the backup** (and its Key File, if any).

### Automatic safety backup

Before any destructive import (merge or replace), SimpleVault writes an automatic
encrypted backup to `storage/backups/auto-<userId>-<timestamp>.json`. These are
encrypted, like all backups.

## Validation on import

Imported backups are validated for: app name, version, required fields, base64
encoding, salt length, nonce lengths, and per-record structure/UUID validity. A
file that fails validation is rejected without modifying your vault.

## Recovery limitations

- There is **no recovery** without the Master Password (and required Key File).
- An encrypted backup is only as recoverable as your memory of its Master Password.
- Keep at least one encrypted backup **and** the credentials needed to open it,
  stored separately.
- Plaintext Markdown exports are readable without any password — store them
  accordingly or avoid creating them.

## Suggested routine

1. Periodic encrypted backup via cron (`scripts/backup.php`) — see
   `docs/DEPLOYMENT.md`.
2. Copy `storage/backups/*.json` off-server regularly.
3. Rotate old backups (`scripts/rotate-backups.php 30`).
4. Test a restore into a throwaway instance occasionally.
