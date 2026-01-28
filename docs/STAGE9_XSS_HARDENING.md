# Stage 9 — XSS Hardening (Front-End Views)

## Scope
This stage focuses on reducing XSS risk in public-facing views/templates without breaking CMS formatting.

## Changes
### 1) Centralized escaping + URL safety helpers
File: `includes/helpers.php`
- Hardened `h()` to use `ENT_QUOTES | ENT_SUBSTITUTE` and UTF-8.
- Added `u()` to escape + validate URLs in `href/src` (blocks `javascript:` / `data:`).
- Added `gdy_jsonld_safe()` to prevent `</script>` break-out in JSON-LD blocks.
- Added `gdy_sanitize_basic_html()` as a conservative sanitizer for contexts that must not allow full HTML (e.g., AMP).

### 2) SEO meta / OpenGraph / Twitter meta escaping
Files:
- `header.php`
- `frontend/views/partials/header.php`
- Escaped dynamic meta values (`seoTitle`, `seoDesc`, `ogTitle`, `ogDesc`).
- Secured JSON-LD output using `gdy_jsonld_safe()`.
- Applied `u()` for canonical/RSS/Sitemap and OG/Twitter image/url attributes.

### 3) AMP content sanitization
File: `frontend/views/news_amp.php`
- News content is now rendered through `gdy_sanitize_basic_html()` to mitigate XSS in AMP output.

### 4) Minor defensive casting
File: `frontend/views/archive.php`
- Cast iteration index output to integer.

## Notes
- Full HTML purification requires a dedicated sanitizer library (e.g., HTML Purifier). `gdy_sanitize_basic_html()` is intentionally minimal and conservative for high-risk contexts.
