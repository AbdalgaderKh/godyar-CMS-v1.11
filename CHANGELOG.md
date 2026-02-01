# Godyar CMS v1.24.1
Release date: 2026-02-01

## Security
- Hardened admin sessions (Strict cookies, rotation, idle timeout) and added lightweight session fingerprint (UA + coarse IP prefix) with proxy-tolerant mode.
- Added CSRF protection + Same-Origin guard for state-changing requests (forms + AJAX/JSON).
- Hardened file uploads with allow-list + MIME verification and disabled script execution inside upload directories.
- Added portable security headers (CSP/HSTS/COOP/CORP, configurable via env) and security event logging (JSONL).

## Performance
- Fixed and enabled page cache, added list-level caching for search/category/tag, and added optional anonymous output cache (TTL configurable).
- Reduced N+1 queries by bulk-loading comment counts across list pages (frontend + API).

## Maintenance
- Portable deployment option via webroot/ and multi-environment server config snippets (Apache/Nginx/IIS).
- Added deploy_check.php for safe, token-gated deployment verification.
- General refactors to reduce duplication/complexity and satisfy strict static analysis rules.

---

# Godyar CMS v1.11.1
Release date: 2026-01-29

## Fixed
- Search engine logic bug (multi-term queries)
- Incorrect SQL WHERE clause generation

## Security
- Hardened output against XSS
- Improved parameter binding safety (SQL Injection prevention)
- Prepared codebase for CSRF token integration

## Maintenance
- Removed demo content
- Clean release build
- Added VERSION metadata

## v1.11.2 Hotfix – 2026-01-29
### Fixed
- Fixed PDO HY093 error in SearchController
- Ensured SQL placeholders always match bindings
- Stabilized search with empty or filtered terms
