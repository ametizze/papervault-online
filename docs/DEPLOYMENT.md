# Deployment

SimpleVault is a plain-PHP app intended to run behind Nginx or Apache with
PHP-FPM, over HTTPS, on a VPS you control.

## PHP Requirements

- PHP 8.3+ with extensions: `sodium`, `pdo` (+ `pdo_sqlite` and/or `pdo_mysql`),
  `json`, `zip`, `mbstring`.
- Verify: `php -m | grep -iE 'sodium|pdo|json|zip|mbstring'`.

## Document Root

The web server **must** serve the `public/` directory only. Everything else
(`app/`, `config/`, `database/`, `storage/`, `.env`) must stay outside the web
root and be unreachable over HTTP.

## Nginx

```nginx
server {
    listen 443 ssl http2;
    server_name vault.example.com;

    root /var/www/simplevault/public;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/vault.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/vault.example.com/privkey.pem;

    client_max_body_size 10M;       # match MAX_UPLOAD_MB
    autoindex off;                  # no directory listing

    location / {
        try_files $uri /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    # Block dotfiles (e.g. .env) but allow ACME challenges.
    location ~ /\.(?!well-known).* {
        deny all;
    }
}

# Redirect HTTP to HTTPS.
server {
    listen 80;
    server_name vault.example.com;
    return 301 https://$host$request_uri;
}
```

Because the document root is `public/`, the `app/`, `storage/`, `database/`, and
`config/` directories are already outside the served tree. The deny-dotfiles rule
is defense in depth.

## Apache

Apache may serve the app with the bundled `public/.htaccess` (front-controller
rewrite + dotfile denial). Point the vhost `DocumentRoot` at `public/` and allow
overrides:

```apache
<VirtualHost *:443>
    ServerName vault.example.com
    DocumentRoot /var/www/simplevault/public

    SSLEngine on
    SSLCertificateFile      /etc/letsencrypt/live/vault.example.com/fullchain.pem
    SSLCertificateKeyFile   /etc/letsencrypt/live/vault.example.com/privkey.pem

    <Directory /var/www/simplevault/public>
        AllowOverride All
        Require all granted
        Options -Indexes
    </Directory>

    # Never serve these directories even if misplaced.
    <Directory /var/www/simplevault/app>      Require all denied </Directory>
    <Directory /var/www/simplevault/storage>  Require all denied </Directory>
    <Directory /var/www/simplevault/database> Require all denied </Directory>
    <Directory /var/www/simplevault/config>   Require all denied </Directory>
</VirtualHost>
```

The repo also ships deny-all `.htaccess` files inside `storage/`, `database/`,
`app/`, and `config/` as a safety net.

## File Permissions

Run PHP-FPM as a dedicated, non-login user (e.g. `www-data`) and restrict access:

```bash
cd /var/www/simplevault
sudo chown -R www-data:www-data .
# App code: read-only to the runtime where possible.
find app config public -type d -exec chmod 750 {} \;
find app config public -type f -exec chmod 640 {} \;
# Writable runtime areas.
chmod -R 770 storage database
chmod 640 .env
```

Only `storage/` and `database/` need to be writable by PHP-FPM.

## HTTPS

HTTPS is **required** in production. The session cookie is issued with the
`Secure` flag, so without TLS the session will not work as intended and traffic
would be in the clear. Use Let's Encrypt / certbot or a TLS-terminating proxy.

If you run behind a reverse proxy that terminates TLS, ensure it sets
`X-Forwarded-Proto: https` and a trustworthy `REMOTE_ADDR` (SimpleVault trusts
`REMOTE_ADDR` for rate-limit keying and only reads `X-Forwarded-Proto` for the
cookie-secure decision).

## SQLite Path

Set `DB_DATABASE` in `.env` to an **absolute** path outside the web root, e.g.:

```
DB_DATABASE=/var/www/simplevault/database/database.sqlite
```

Create the schema and first user:

```bash
php scripts/migrate.php
php scripts/install.php
```

For MySQL, set `DB_CONNECTION=mysql` and the `DB_HOST/DB_PORT/DB_NAME/DB_USER/
DB_PASS` values, then run `php scripts/migrate.php`.

## Backup Strategy & Cron

Encrypted backups contain no plaintext and are safe to copy off-server.

```cron
# Encrypted backup every night at 02:30 (writes to storage/backups/).
30 2 * * * cd /var/www/simplevault && /usr/bin/php scripts/backup.php >> storage/logs/backup.log 2>&1

# Prune backups older than 30 days, weekly.
0 3 * * 0 cd /var/www/simplevault && /usr/bin/php scripts/rotate-backups.php 30 >> storage/logs/backup.log 2>&1
```

Then sync `storage/backups/` to off-site storage (rsync/object storage). Keep the
Master Password (and Key File) needed to restore them stored separately.

## Protecting storage/database/app

- They live outside the `public/` document root — never inside it.
- Bundled deny-all `.htaccess` files guard against misconfiguration on Apache.
- Permissions limit access to the PHP-FPM user.
- Never expose `.env`; it holds configuration (no encryption keys live there, by
  design, but it should still be private).
