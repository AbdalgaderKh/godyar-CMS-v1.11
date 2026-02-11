# 2FA Rollout Checklist (Admin)

Use this checklist to safely enable Admin 2FA in production.

## Pre-rollout
- [ ] Confirm you can log in to the admin panel as a super-admin.
- [ ] Take a backup of the database.
- [ ] Ensure `storage/` is writable (for logs and cache).
- [ ] Apply the migration:
  - `database/migrations/2026_02_02_add_users_2fa_backup_codes.sql`

## Config
- [ ] Set env:
  - `GDY_2FA_ENABLED=1`
- [ ] Optional (recommended for high-security installs):
  - `GDY_2FA_REQUIRED_FOR_ADMIN=1`
- [ ] Verify your session settings are stable on your hosting (HTTPS, cookies).

## Enable 2FA (pilot)
- [ ] Enable 2FA for **one** super-admin first:
  - Admin → Security → 2FA
- [ ] Scan QR in an authenticator app.
- [ ] Enter the generated 6-digit code to confirm.
- [ ] Generate and securely store backup codes (password manager).

## Verification
- [ ] Log out.
- [ ] Log in again:
  - password step → 2FA verify step → admin dashboard
- [ ] Test backup code login (use one code; confirm it becomes invalid after use).

## Full rollout
- [ ] Enable 2FA for all admin accounts.
- [ ] Require 2FA for admin (if desired) by setting `GDY_2FA_REQUIRED_FOR_ADMIN=1`.
- [ ] Announce the change and provide internal instructions.

## Recovery & incident
- [ ] Ensure at least one super-admin has backup codes stored securely.
- [ ] Document the recovery procedure in `docs/SECURITY.md`.
- [ ] Monitor `storage/logs/security.log` for repeated failures or suspicious attempts.

## Rollback
- [ ] Temporarily disable:
  - `GDY_2FA_ENABLED=0`
- [ ] Or per-user disable from the DB (set `twofa_enabled=0` for the affected admin).
