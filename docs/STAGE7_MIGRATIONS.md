# Stage 7 — Database Migrations (CLI)

This release adds a **conservative, CLI-only migration runner** that applies the existing `*.sql` migration files in a controlled way and tracks what was applied.

## Safety model

- **CLI only**: `tools/migrate.php` refuses web execution.
- **Opt-in schema changes**: migrations require `ALLOW_SCHEMA_CHANGES=1`.
- **Tracking**: applied migrations are recorded in `schema_migrations` with a SHA-256 checksum.
- **Checksum enforcement**: if a previously applied file changed, the runner stops (unless `ALLOW_MIGRATION_CHECKSUM_MISMATCH=1` is explicitly set).

## Where migrations are read from

The runner scans (in this order) and de-duplicates by filename:

1. `database/migrations`
2. `admin/db/migrations`
3. `migrations`

For PostgreSQL deployments, it automatically prefers the `postgresql/` subfolder inside each directory when present.

## How to run

### Apply pending migrations

```bash
export ALLOW_SCHEMA_CHANGES=1
php tools/migrate.php
```

### Show status (applied/pending)

```bash
php tools/migrate.php --status
```

### Dry run (no DB changes)

```bash
php tools/migrate.php --dry-run
# or:
export MIGRATION_DRY_RUN=1
php tools/migrate.php
```

## Recommended environment variables

- `ALLOW_SCHEMA_CHANGES=1` (required to apply)
- `MIGRATION_DRY_RUN=1` (optional)
- `ALLOW_MIGRATION_CHECKSUM_MISMATCH=1` (not recommended)

## Notes

- The SQL splitter is conservative and is intended for typical `CREATE/ALTER/INSERT` statements.
- If you introduce stored procedures/triggers that require custom delimiters, keep them in a single-statement migration (or update the runner accordingly).
