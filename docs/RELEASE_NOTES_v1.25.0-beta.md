# Release Notes — v1.25.0-beta (2026-02-02)

This is a **beta** release intended for staging and controlled production rollout.

## Highlights
- Admin 2FA (TOTP) with backup codes and an admin UI.
- New official documentation set under `docs/`.
- GitHub planning file for v1.25 milestones and issues.

## What changed

### Security
- Added an admin 2FA step during login (TOTP).
- Added single-use backup codes (stored hashed) for recovery.
- Added rate limiting on the 2FA verification step.
- Rotates the admin session after successful 2FA verification.

### Documentation
- Added developer and contributor docs: installation, architecture, security, coding standards, plugins, and API docs.

### Project planning
- Added `docs/GITHUB_MILESTONES_ISSUES_v1.25.md` with ready-to-import milestones and issue breakdown.

## Upgrade notes
1. Apply the migration to add `twofa_backup_codes` (if your DB does not have it yet).
2. Set environment toggles as needed:
   - `GDY_2FA_ENABLED=1`
   - `GDY_2FA_REQUIRED_FOR_ADMIN=1` (optional)
3. Enable 2FA from the admin panel: `Admin → Security → 2FA`.

## Beta cautions
- Roll out to staging first.
- Keep at least one super-admin account with backup codes stored securely.
