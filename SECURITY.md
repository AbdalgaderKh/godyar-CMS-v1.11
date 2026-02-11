# Security notes

This repository is a PHP CMS and does **not** use WordPress APIs (e.g., wp_unslash()).
Some automated scanners may apply WordPress-oriented rules; for this reason we scope static analysis
to core logic directories and exclude view/template output files where escaping is handled via `h()`/`xml()` helpers.

Core rules:
- Escape all HTML output with `h()` (HTML) or `xml()` (XML feeds/sitemaps).
- Never execute PHP from uploads: uploads directories include `.htaccess` to disable PHP execution.
- Use prepared statements for database queries.
