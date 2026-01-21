# Changelog

All notable changes to this repository are documented in this file.

## [Unreleased]
- (Reserved)

## [1.12.0-clean.1] - 2026-01-21
### Fixed
- Stabilized admin bootstrap loading and i18n initialization (ensures `__()` is available across admin/frontend).
- Resolved common PHP syntax issues discovered during hardening (unbalanced braces, stray tokens).
- Normalized admin layout wrapper usage (`app_start/app_end`) to prevent overflow and sidebar overlap.

### Security
- Removed runtime artifacts from version control (logs, uploads, private `.env`).
- Added safe defaults and hardening notes in installation guide.

### Repo
- Added GitHub Actions workflow to lint PHP files on push/PR.
