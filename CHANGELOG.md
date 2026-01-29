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
