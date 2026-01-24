# Godyar CMS (Clean for GitHub)

This repository contains **code only** (no private content, no runtime logs, no uploads).  
It is intended for clean GitHub hosting and repeatable deployments.

## Requirements

- PHP 8.1+ (recommended)
- MySQL/MariaDB
- Apache (with `.htaccess`) or Nginx equivalent rules
- Composer (optional, only if you use `composer.json` dependencies)

## Recommended directory layout (production)

Place the web root inside `public_html` (or equivalent) and keep private files outside:

```
/home/USER/
  godyar_private/
    .env
    logs/
  public_html/
    (this repo contents)
```

### Private directory (`godyar_private`)
Create a directory outside public root, e.g.:

- `/home/USER/godyar_private/`

Recommended permissions:
- `godyar_private` : **700**
- `godyar_private/.env` : **600**
- `godyar_private/logs` : **700**

## Configure ENV file location

1) Copy the example override file:

- `includes/env_path.php.example` → `includes/env_path.php`

2) Edit it and set the correct absolute path:

```php
$envFile = '/home/USER/godyar_private/.env';
```

> Do **not** commit `includes/env_path.php`. It is excluded by `.gitignore`.

## Create `.env`

Create `/home/USER/godyar_private/.env` with at least:

```
APP_ENV=production
APP_DEBUG=0

DB_HOST=localhost
DB_NAME=your_db
DB_USER=your_user
DB_PASS=your_pass

BASE_URL=https://example.com
```

> Your project may have additional keys depending on enabled features.

## Database setup

1) Create the database and user in MySQL/MariaDB.
2) Import your schema/dump (if you have an existing database).
3) Ensure UTF-8 support (recommended):

```sql
ALTER DATABASE your_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

If you have tables with Arabic text, consider converting those tables too:

```sql
ALTER TABLE roles CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## Runtime directories and permissions

Ensure the following are writable by PHP (create if missing):

- `storage/logs/`
- `storage/cache/`
- `storage/ratelimit/` (if rate limiting is enabled)

Typical permissions:
- Directories: **755** (or **775** if group-based)
- Files: **644**

## Upload limits (optional)

If you need large uploads (e.g. up to 100MB), set in PHP configuration (or `.user.ini` if supported):

```
upload_max_filesize=100M
post_max_size=120M
max_execution_time=120
memory_limit=256M
```

This repo includes a template:
- `.user.ini.example`

## Web server configuration

### Apache
Ensure `.htaccess` is enabled (AllowOverride).  
If you use a reverse proxy/CDN, also ensure correct `BASE_URL` in `.env`.

### Nginx
Translate `.htaccess` rules into Nginx equivalents (deny access to private/runtime paths like `/storage`, `/includes`, `/vendor`, etc.).

## Composer (optional)

If your installation uses Composer dependencies:

```bash
composer install --no-dev --optimize-autoloader
```

If you do **not** use Composer in production, you can ignore this step.

## Deployment to GitHub

1) Initialize and push:

```bash
git init
git add .
git commit -m "Initial clean import (1.12.0-clean.1)"
git branch -M main
git remote add origin <YOUR_GITHUB_REPO>
git push -u origin main
```

2) On the server, deploy with Git (recommended):

```bash
cd /home/USER/public_html
git clone <YOUR_GITHUB_REPO> .
```

3) Create `includes/env_path.php` and `/home/USER/godyar_private/.env` (as above).

## Troubleshooting

### If you see “old errors” after overwriting files
You may be seeing OPcache serving cached code. If available, reset OPcache once:

```php
<?php
var_dump(function_exists('opcache_reset') ? opcache_reset() : 'no-opcache');
```

Create a temporary file, open it once, then delete it.

### Arabic text shows as `????`
That means the text was stored as `?` in the DB (hex `3F`), not a display issue.
Fix by:
1) Ensuring UTF-8 connection (DSN `charset=utf8mb4`)
2) Converting tables to `utf8mb4`
3) Re-entering the affected text values.

## License
Add a license file if you plan to open-source this project.
