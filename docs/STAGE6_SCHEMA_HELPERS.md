# Stage 6 — Optional DB Schema Helpers

`includes/db.php` provides conservative schema utilities intended for installer/upgrade workflows.

## Safety defaults
- Schema changes are disabled by default.
- Enable temporarily:

```ini
ALLOW_SCHEMA_CHANGES=1
```

When disabled, DDL helpers throw `RuntimeException`.

## Available functions
Read-only:
- `gdy_db_driver()`
- `gdy_db_current_database()`
- `gdy_db_table_exists($table)`
- `gdy_db_column_info($table, $column)`
- `gdy_db_column_exists($table, $column)`
- `gdy_db_index_exists($table, $indexName)`

DDL / idempotent:
- `gdy_db_ensure_table($table, $createSql)`
- `gdy_db_ensure_column($table, $column, $definition, $after = null)`
- `gdy_db_add_index($table, $indexName, array $columns, $unique = false)`

Migration tracking:
- `gdy_db_apply_migration($name, callable $fn)` creates (if needed) a table named `godyar_migrations` and records applied migrations by name.

## Example (upgrade patch)

```php
require_once __DIR__ . '/../includes/db.php';

// Enable ONLY during maintenance:
// putenv('ALLOW_SCHEMA_CHANGES=1');

gdy_db_apply_migration('2026_01_26_add_news_views_index', function(PDO $pdo){
    gdy_db_add_index('news', 'idx_news_views', ['views']);
});
```

## Notes
- All identifiers (tables/columns/indexes) are validated to only allow `[A-Za-z0-9_]` (dot-separated segments are allowed).
- For production security, keep `ALLOW_SCHEMA_CHANGES` unset/false and run migrations from a controlled context only.
