# Cryptographic Design

SimpleVault uses **envelope encryption** built entirely on PHP's libsodium
(`ext-sodium`). No custom cryptography is implemented; only thin wrappers around
Sodium primitives.

## Overview

```
Master Password (+ optional Key File material) + per-vault salt
    │
    ▼  sodium_crypto_pwhash()  (Argon2id)
Key Encryption Key (KEK, 32 bytes, never stored)
    │
    ▼  sodium_crypto_secretbox_open()  (unwrap)
Vault Key (32 bytes, random, generated once)
    │
    ▼  sodium_crypto_secretbox_open()  (per record, unique nonce)
Plaintext entry / note JSON
```

## Components

### Authentication password (login)

Separate from encryption. Stored with `password_hash($p, PASSWORD_ARGON2ID)` and
checked with `password_verify`. Rehashed automatically if parameters change. It is
never used as an encryption key and never stored in the session.

### Vault Key

- A random 32-byte key: `random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES)`.
- Encrypts every password entry and note.
- Generated once at setup and never changes (so Master Password changes are cheap).

### Key Encryption Key (KEK)

- Derived on demand from the Master Password (+ optional Key File material) and the
  vault's random salt via `sodium_crypto_pwhash()` (Argon2id, alg
  `SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13`).
- Output length is `SODIUM_CRYPTO_SECRETBOX_KEYBYTES` (32 bytes).
- Ops/mem limits default to `*_INTERACTIVE` and are stored per vault
  (`kdf_ops_limit`, `kdf_mem_limit`) so they can be tuned without breaking existing
  vaults.
- The KEK is never persisted and is zeroed (`sodium_memzero`) after use.

### Sodium secretbox

- All payloads use `sodium_crypto_secretbox()` (XSalsa20-Poly1305): authenticated
  encryption, so tampering is detected on decrypt (decryption returns `false`,
  which the code converts to a generic failure).

### Nonces

- Every encryption uses a fresh random nonce:
  `random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES)` (24 bytes).
- Nonces are stored (base64) next to the ciphertext. Uniqueness per encryption is
  covered by a test.

### Optional Key File

- JSON envelope: `{ "type": "simplevault-keyfile", "version": 1, "created_at":
  "...", "key_material": "<base64 random>" }`.
- Combined with the Master Password before derivation:
  `generichash(key_material)` is hex-encoded and appended:
  `master_password . ':' . hex(generichash(material))`.
- Validated on every use (type, version, base64, minimum length). Never stored;
  the transient upload is deleted immediately after reading.
- For the user-facing guide (generate, use, store, lose), see
  [`KEY_FILE.md`](KEY_FILE.md).

## Storage Layout

`vaults` row (per user):

| column                | meaning                                  |
|-----------------------|------------------------------------------|
| `salt`                | base64 Argon2id salt (per vault)         |
| `encrypted_vault_key` | base64 secretbox(VaultKey, KEK)          |
| `vault_key_nonce`     | base64 nonce for the wrap                |
| `kdf_ops_limit`       | Argon2id ops limit                       |
| `kdf_mem_limit`       | Argon2id memory limit                    |
| `key_file_required`   | 0/1                                      |

`entries` / `notes` rows store `encrypted_payload` + `payload_nonce` (both
base64), plus metadata (`uuid`, `favorite`, `archived`, timestamps).

## Backup Format

```json
{
  "app": "SimpleVault",
  "version": 1,
  "exported_at": "2026-01-01T00:00:00+00:00",
  "crypto": { "algorithm": "sodium_crypto_secretbox", "kdf": "sodium_crypto_pwhash" },
  "payload": {
    "encrypted_vault_key": "...",
    "vault_key_nonce": "...",
    "salt": "...",
    "kdf_ops_limit": 2,
    "kdf_mem_limit": 67108864,
    "key_file_required": 0,
    "entries": [ { "uuid": "...", "encrypted_payload": "...", "payload_nonce": "...", "favorite": 0, "archived": 0, "created_at": "...", "updated_at": "..." } ],
    "notes":   [ { "uuid": "...", "encrypted_payload": "...", "payload_nonce": "...", "favorite": 0, "archived": 0, "created_at": "...", "updated_at": "..." } ]
  }
}
```

Because records are already encrypted under the Vault Key (wrapped by the Master
Password), the backup contains **no plaintext secrets**. Import validates app
name, version, required fields, base64, salt length, and nonce lengths.

## Master Password Change Flow

1. Unwrap the Vault Key with the **current** Master Password (+ Key File).
2. Generate a **new salt** and derive a new KEK from the **new** Master Password.
3. Re-encrypt (re-wrap) the **same** Vault Key; update the `vaults` row.
4. Entries and notes are untouched — they still use the unchanged Vault Key.

This makes password changes O(1) instead of re-encrypting every record.

## Session Handling of the Vault Key

After unlock, the raw Vault Key is stored (base64) in the server-side PHP session
so the user need not re-enter the Master Password per request. It is removed on
logout, manual lock, and inactivity auto-lock (`VAULT_AUTO_LOCK_MINUTES`). The
session cookie is `HttpOnly`, `Secure`, `SameSite=Strict`, and the session ID is
regenerated on login and on unlock.

## Limitations

- Decryption is server-side; a compromised server with an unlocked vault can read
  plaintext. SimpleVault is not end-to-end encrypted in the browser.
- The Master Password strength is the ultimate bound on security.
- No protection against memory scraping or a malicious PHP runtime.
- No clientside zero-knowledge guarantees; treat this as at-rest encryption for a
  personal server.
