# Dependency / Component Security (OWASP A06)

Because Godyar CMS is deployed on different stacks (shared hosting, VPS, containers), dependency maintenance is handled per environment.

## Recommended baseline
- Use a **supported PHP version** from your provider.
- If you use Composer, commit a `composer.lock` so production matches tested versions.

## What to do on each release
1. Review `composer.json` and your `vendor/` folder.
2. Update packages in a staging environment.
3. Run a quick smoke test (login, posting comments, uploads, search, admin).

## Quick local report (no internet required)
Run:
- `php tools/dependency_report.php`

It prints the packages declared in `composer.json` so you can track what needs upgrading.

## Common items to keep fresh
- Mailer libraries (e.g., PHPMailer)
- Image processing libraries
- Any rich-text/editor components
