/* =========================================================
   Godyar CMS - v1.11 cleanup & compatibility migration
   Purpose:
   1) Remove elections module tables (if any still exist)
   2) Remove elections-related settings / page / menu leftovers (best-effort)
   3) Add compatibility columns expected by code:
      - settings.value (synced from setting_value)
      - opinion_authors.page_title, slug, social_* (synced from legacy facebook/twitter if present)
   Notes:
   - Safe to run multiple times.
   - MySQL/MariaDB compatible.
   ========================================================= */

SET FOREIGN_KEY_CHECKS = 0;

-- ---------- (A) Drop elections tables (any table name containing election/elections)
SET @db := DATABASE();

SELECT GROUP_CONCAT(CONCAT('`', table_name, '`') SEPARATOR ', ')
INTO @drop_list
FROM information_schema.tables
WHERE table_schema = @db
  AND (table_name LIKE '%election%' OR table_name LIKE '%elections%');

SET @sql := IF(@drop_list IS NULL OR @drop_list = '',
               'SELECT "v1.11: No election tables found" AS info;',
               CONCAT('DROP TABLE IF EXISTS ', @drop_list, ';'));

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ---------- (B) Remove elections leftovers from settings (if table/columns exist)
SET @has_settings := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=@db AND table_name='settings');

SET @has_setting_key := (SELECT COUNT(*) FROM information_schema.columns
                         WHERE table_schema=@db AND table_name='settings' AND column_name IN ('setting_key','key') );

-- Delete rows by key where possible
SET @sql := IF(@has_settings=0,
  'SELECT "v1.11: settings table not found - skipped" AS info;',
  IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='settings' AND column_name='setting_key')>0,
    "DELETE FROM `settings`
      WHERE setting_key LIKE 'election_%'
         OR setting_key LIKE '%election%'
         OR setting_key IN ('show_elections_link','elections_enabled','elections_home_block');",
    IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='settings' AND column_name='key')>0,
      "DELETE FROM `settings`
        WHERE `key` LIKE 'election_%'
           OR `key` LIKE '%election%'
           OR `key` IN ('show_elections_link','elections_enabled','elections_home_block');",
      'SELECT "v1.11: settings key column not found - skipped delete" AS info;'
    )
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- (C) Compatibility fix: settings.value expected by code
SET @col_value := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='settings' AND column_name='value');
SET @col_setting_value := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='settings' AND column_name='setting_value');

SET @sql :=
IF(@has_settings = 0,
   'SELECT "v1.11: No settings table to patch" AS info;',
   IF(@col_value = 0 AND @col_setting_value > 0,
      "ALTER TABLE `settings` ADD COLUMN `value` LONGTEXT NULL;",
      'SELECT "v1.11: settings.value already exists OR setting_value missing" AS info;'
   )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql :=
IF(@has_settings = 0,
   'SELECT "v1.11: No settings table" AS info;',
   IF(@col_setting_value > 0 AND (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='settings' AND column_name='value')>0,
      "UPDATE `settings` SET `value` = COALESCE(`value`, `setting_value`)
        WHERE (`value` IS NULL OR `value`='') AND `setting_value` IS NOT NULL;",
      'SELECT "v1.11: settings.value sync skipped" AS info;'
   )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- (D) Compatibility fix: opinion_authors columns used by frontend/admin queries
SET @has_authors := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=@db AND table_name='opinion_authors');

-- page_title
SET @c := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='opinion_authors' AND column_name='page_title');
SET @sql := IF(@has_authors=0, 'SELECT "v1.11: No opinion_authors table" AS info;',
               IF(@c=0, "ALTER TABLE `opinion_authors` ADD COLUMN `page_title` VARCHAR(255) NULL AFTER `avatar`;", 'SELECT "v1.11: opinion_authors.page_title exists" AS info;'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- slug
SET @c := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='opinion_authors' AND column_name='slug');
SET @sql := IF(@has_authors=0, 'SELECT "v1.11: No opinion_authors table" AS info;',
               IF(@c=0, "ALTER TABLE `opinion_authors` ADD COLUMN `slug` VARCHAR(190) NULL AFTER `name`;", 'SELECT "v1.11: opinion_authors.slug exists" AS info;'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- social_facebook
SET @c := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='opinion_authors' AND column_name='social_facebook');
SET @sql := IF(@has_authors=0, 'SELECT "v1.11: No opinion_authors table" AS info;',
               IF(@c=0, "ALTER TABLE `opinion_authors` ADD COLUMN `social_facebook` VARCHAR(255) NULL;", 'SELECT "v1.11: opinion_authors.social_facebook exists" AS info;'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- social_twitter
SET @c := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='opinion_authors' AND column_name='social_twitter');
SET @sql := IF(@has_authors=0, 'SELECT "v1.11: No opinion_authors table" AS info;',
               IF(@c=0, "ALTER TABLE `opinion_authors` ADD COLUMN `social_twitter` VARCHAR(255) NULL;", 'SELECT "v1.11: opinion_authors.social_twitter exists" AS info;'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- social_website
SET @c := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='opinion_authors' AND column_name='social_website');
SET @sql := IF(@has_authors=0, 'SELECT "v1.11: No opinion_authors table" AS info;',
               IF(@c=0, "ALTER TABLE `opinion_authors` ADD COLUMN `social_website` VARCHAR(255) NULL;", 'SELECT "v1.11: opinion_authors.social_website exists" AS info;'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- sync legacy facebook/twitter if present
SET @has_fb := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='opinion_authors' AND column_name='facebook');
SET @has_tw := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='opinion_authors' AND column_name='twitter');

SET @sql := IF(@has_authors=0, 'SELECT "v1.11: No opinion_authors table" AS info;',
               IF(@has_fb>0, "UPDATE `opinion_authors` SET `social_facebook` = COALESCE(`social_facebook`, `facebook`)
                              WHERE `facebook` IS NOT NULL AND `facebook`<>'';",
                              'SELECT "v1.11: no legacy facebook column" AS info;'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@has_authors=0, 'SELECT "v1.11: No opinion_authors table" AS info;',
               IF(@has_tw>0, "UPDATE `opinion_authors` SET `social_twitter` = COALESCE(`social_twitter`, `twitter`)
                              WHERE `twitter` IS NOT NULL AND `twitter`<>'';",
                              'SELECT "v1.11: no legacy twitter column" AS info;'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;

SELECT "v1.11 migration completed" AS status;
