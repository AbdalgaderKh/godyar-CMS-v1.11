# Healthcheck + Diagnostics (Portable)

## 1) /health.php
Purpose: machine-readable status for load balancers / uptime checks.

### Enable securely (recommended)
Set env var:
- `GDY_HEALTHCHECK_TOKEN=<random-long-token>`

Then call:
- `GET /health.php?token=<token>`
or with header:
- `X-Health-Token: <token>`

If no token is set, access is **localhost-only** (127.0.0.1/::1).

Disable:
- `GDY_HEALTHCHECK_ENABLED=0`

## 2) /diagnostics.php (admin-only)
Safe human-readable checks (no secrets printed).

Enable temporarily:
- `GDY_DIAGNOSTICS=1`

Disable:
- `GDY_DIAGNOSTICS=0`

Access requires:
- Admin session (`$_SESSION['admin'] === true`)
