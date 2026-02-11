# Security

## Escaping & Output
This project uses project-level escaping helpers (e.g. `h()` for HTML and `xml()` for XML).
It is **not** a WordPress project, so WordPress-specific rules (e.g. `wp_unslash()`) do not apply.

## Configuration Secrets
Do **not** commit `.env` or any secret keys. Use `.env.example` as a template.

## Uploads
Uploads directories should not allow script execution. The repository includes protective `.htaccess` rules.

## Reporting
If you discover a security issue, please open a private report (if available) or create an issue with minimal details.
