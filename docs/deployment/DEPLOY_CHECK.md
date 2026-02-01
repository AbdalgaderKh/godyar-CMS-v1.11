# Deploy Check (Portable)

This project includes a safe deployment self-check page:

- `/deploy_check.php` (and `/webroot/deploy_check.php` when using webroot)

## Enable

Set one of these environment variables:

- `GDY_DEPLOY_CHECK=1` (preferred)
- or `GDY_DIAGNOSTICS=1`

## Access control

Recommended:

- Set `GDY_DEPLOY_CHECK_TOKEN` to a long random string.
- Open: `/deploy_check.php?token=...` or send header `X-Deploy-Token: ...`

If no token is set, access is allowed only from localhost (127.0.0.1 / ::1).

## Disable

- Set `GDY_DEPLOY_CHECK=0` (or unset)
