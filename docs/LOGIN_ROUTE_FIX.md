# Login route fix (Stage 12)

This package includes a routing hardening fix so `/login` works under Apache/LiteSpeed even if rewrite rules are bypassed.

Changes:
- `app.php`: adds explicit Router routes for `/login`, `/register`, `/profile`, `/logout`, and `/admin/login` (GET+POST).
- `app.php`: improves early fallback to strip base prefix for subdirectory installs.
- `.htaccess`: adds `-MultiViews` to avoid content-negotiation conflicts.

Deployment reminder:
- Ensure `public_html/.htaccess` is named correctly (leading dot) and readable (644).
- Typical perms: directories 755, files 644.
