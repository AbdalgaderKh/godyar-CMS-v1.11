# Security Logging & Monitoring (Godyar CMS)

This build adds **portable security logging** (file-based) so it works on shared hosting and different stacks.

## Where logs go
- `storage/logs/security.log` (JSON Lines)
- Rotates automatically when the file exceeds ~5MB (renames to `security.log.1`).

## Events currently logged
- `csrf_block` – CSRF validation failed.
- `origin_block` – Same-origin guard blocked a cross-site cookie request.
- `rate_limited` – Rate limiter triggered (e.g., contact form / comments).

## Enable/Disable
- Enabled by default.
- Disable via environment:
  - `GDY_SECURITY_LOG=0`

## Operational tips
- On shared hosting, ensure `storage/logs/` is writable (0755/0775 depending on owner/group).
- Ship the log file into your central logging solution if available.
