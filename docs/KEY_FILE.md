# Key File

The **Key File** is an optional second factor for unlocking your vault. When
enabled, the key that decrypts your vault is derived from **Master Password +
Key File material** instead of the Master Password alone. Anyone who has only one
of the two cannot open the vault.

This is a single, consolidated guide. Related background lives in
[`CRYPTO_DESIGN.md`](CRYPTO_DESIGN.md) (how it enters key derivation) and
[`../SECURITY.md`](../SECURITY.md) (threat model).

---

## What it is

A small JSON file containing 32 high-entropy random bytes:

```json
{
  "type": "simplevault-keyfile",
  "version": 1,
  "created_at": "2026-01-01T00:00:00+00:00",
  "key_material": "<base64 random data>"
}
```

- The `key_material` is **never stored on the server** — not in the database, not
  on disk. SimpleVault keeps only the wrapped vault key; the Key File stays with
  you.
- At unlock, the uploaded file is read transiently to derive the key, then the
  temporary upload is deleted immediately. It is not logged.
- It is hashed (`sodium_crypto_generichash`) and combined with your Master
  Password before Argon2id key derivation (see `CRYPTO_DESIGN.md`).

## Is it required?

No — it is **optional and off by default**. A vault either uses a Key File or it
does not, and that choice is recorded per vault (`key_file_required`).

- If your vault does **not** use a Key File: unlock with the Master Password only.
- If your vault **does** use a Key File: you must provide it every time you unlock.

## How to generate one

**At first setup (`/setup`):**

1. Tick **"Generate an optional Key File"**.
2. When you submit, `simplevault-keyfile.json` **downloads immediately**.
3. From then on, the vault **requires** that file to unlock.

**Later, from Settings → Change Master Password:**

The "Key File" selector offers three modes:

| Mode   | Effect                                                                 |
|--------|-----------------------------------------------------------------------|
| `keep` | Leave the current setting unchanged.                                  |
| `new`  | Generate a **new** Key File (downloads now) and require it.           |
| `none` | **Disable** the Key File requirement (Master Password only).          |

> Generating a `new` Key File replaces the old one. The previous file will no
> longer unlock the vault after the change completes.

## How to use it at unlock

On the **Unlock your vault** screen:

- If your vault requires a Key File, the field is shown as **(required)** — select
  your `simplevault-keyfile.json` together with your Master Password.
- **If your vault does NOT use a Key File, leave this field empty.**
  Uploading a Key File into a vault that was not set up with one will cause key
  derivation to fail and the unlock will be rejected. The "(optional)" label means
  "only if your vault has one", not "any file is accepted".

## If you lose it

There is **no recovery**. If a Key File is **required** and you lose it, the vault
**cannot be unlocked even with the correct Master Password**. There is no reset
and no backdoor — this is intentional.

Mitigations:

- Keep at least one safe backup copy of the file (see below).
- If you still have an unlocked session, you can go to **Settings → Change Master
  Password** and either generate a `new` Key File or set `none` to remove the
  requirement — but only while the vault is currently unlocked with the existing
  one.

## How to store it

The whole point of a second factor is separation:

- **Store the Key File separately from your Master Password**, ideally on a
  different medium/location (e.g. an encrypted USB stick, another password
  manager, or an offline printed copy of the JSON).
- An attacker who obtains only the file **or** only the password cannot unlock the
  vault — they need **both**.
- Treat the file as a secret. Back it up; losing the only copy means losing the
  vault.
- The same Key File is also needed to **restore/merge an encrypted backup** that
  was created by a Key-File-protected vault (see
  [`BACKUP_AND_RESTORE.md`](BACKUP_AND_RESTORE.md)).

## Quick checklist

- [ ] Decided whether you want a Key File (extra security vs. extra thing to keep).
- [ ] Downloaded the file at setup (or via Settings) and confirmed it opens as JSON.
- [ ] Stored it **separately** from the Master Password.
- [ ] Made at least one safe backup copy.
- [ ] Verified you can unlock with Master Password **+** Key File.
