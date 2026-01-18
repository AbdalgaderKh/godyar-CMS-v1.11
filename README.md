# Godyar CMS v1.11 — Clean GitHub Release

This repository is a cleaned, deployment-ready release of **Godyar CMS v1.11** prepared for GitHub.

## Highlights (maintenance patch — 2026-01-17)
- Admin: responsive layout fixes so pages do not overflow under the sidebar.
- Admin: sidebar “News” now contains a proper nested submenu (as a single group).
- News: fixed create/edit persistence for `content`, `category_id`, and image fields.
- Frontend: fixed article featured image rendering.
- Frontend: author avatars hidden everywhere except “Opinion Writers”.
- Services: Category/Tag service compatibility fixes.

See `CHANGELOG.md` for details.

## Quick start
- Installation: `docs/INSTALL.md`
- Shared hosting (cPanel): `docs/DEPLOY_SHARED_HOSTING.md`

## Configuration & secrets
- Do **not** commit `.env`, `.user.ini`, or `includes/env_path.php`.
- Put secrets in `.env` (project root) or environment variables.

## Language prefixes
The app supports language prefixes (`/ar`, `/en`, `/fr`) and legacy includes via a compatibility layer.
