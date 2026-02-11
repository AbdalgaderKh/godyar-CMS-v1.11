# Godyar CMS — GitHub Clean Build

This repository is prepared to be **safe for public GitHub**:
- No `.env` secrets committed (use `.env.example`).
- Runtime folders are kept with placeholders, but their contents are ignored by `.gitignore`.
- Root `.htaccess` includes security hardening and blocks direct access to internal directories.

## Install
1. Upload to your hosting
2. Copy `.env.example` → `.env` and set DB credentials
3. Run the installer (`/install`) once
4. After install: restrict `/install` (optional) by server rules
