-- Godyar CMS standard schema (mysql)
-- Generated at: 2026-02-02T09:01:00.714871Z

/*!40101 SET NAMES utf8mb4 */;
SET FOREIGN_KEY_CHECKS=0;
CREATE TABLE IF NOT EXISTS schema_migrations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(190) NOT NULL,
  checksum CHAR(64) NOT NULL,
  applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_schema_migrations_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- INSTALL: install/sql/schema_core.sql (patched for mysql)
SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS news_tags;
DROP TABLE IF EXISTS role_permissions;
DROP TABLE IF EXISTS user_roles;
DROP TABLE IF EXISTS news;
DROP TABLE IF EXISTS tags;
DROP TABLE IF EXISTS pages;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS permissions;
DROP TABLE IF EXISTS roles;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS=1;
CREATE TABLE users ( id INT UNSIGNED NOT NULL AUTO_INCREMENT, name VARCHAR(190) NULL, username VARCHAR(60) NOT NULL, email VARCHAR(190) NOT NULL, password_hash VARCHAR(255) NOT NULL, /* Legacy compatibility: some parts still reference password */ password VARCHAR(255) NULL, role VARCHAR(100) NOT NULL DEFAULT 'user', is_admin BOOLEAN NOT NULL DEFAULT false, status VARCHAR(20) NOT NULL DEFAULT 'active', avatar VARCHAR(255) NULL, last_login_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY uniq_users_email (email), UNIQUE KEY uniq_users_username (username), KEY idx_users_role (role), KEY idx_users_status (status), KEY idx_users_is_admin (is_admin) );
CREATE TABLE roles ( id INT UNSIGNED NOT NULL AUTO_INCREMENT, PRIMARY KEY (`id`),
  name VARCHAR(60) NOT NULL, label VARCHAR(120) NOT NULL, description TEXT NULL, is_system BOOLEAN NOT NULL DEFAULT true, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uniq_roles_name (name) );
CREATE TABLE permissions ( id INT UNSIGNED NOT NULL AUTO_INCREMENT, PRIMARY KEY (`id`),
  code VARCHAR(120) NOT NULL, label VARCHAR(190) NOT NULL, description TEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uniq_permissions_code (code) );
CREATE TABLE role_permissions ( role_id INT NOT NULL, permission_id INT NOT NULL, PRIMARY KEY (role_id, permission_id) );
CREATE TABLE user_roles ( user_id INT NOT NULL, role_id INT NOT NULL, PRIMARY KEY (user_id, role_id) );
INSERT INTO roles (name,label,description,is_system) SELECT 'admin','مدير النظام','صلاحيات كاملة',1 WHERE NOT EXISTS (SELECT 1 FROM roles WHERE name='admin');
INSERT INTO roles (name,label,description,is_system) SELECT 'writer','كاتب','كتابة وتعديل أخبار',1 WHERE NOT EXISTS (SELECT 1 FROM roles WHERE name='writer');
INSERT INTO roles (name,label,description,is_system) SELECT 'user','مستخدم','حساب مستخدم عادي',1 WHERE NOT EXISTS (SELECT 1 FROM roles WHERE name='user');
INSERT INTO permissions (code,label,description) SELECT '*','صلاحيات كاملة','جميع الصلاحيات' WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code='*');
INSERT INTO permissions (code,label,description) SELECT 'manage_users','إدارة المستخدمين',NULL WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code='manage_users');
INSERT INTO permissions (code,label,description) SELECT 'manage_roles','إدارة الأدوار والصلاحيات',NULL WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code='manage_roles');
INSERT INTO permissions (code,label,description) SELECT 'manage_security','إعدادات الأمان',NULL WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code='manage_security');
INSERT INTO permissions (code,label,description) SELECT 'manage_plugins','إدارة الإضافات',NULL WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code='manage_plugins');
INSERT INTO permissions (code,label,description) SELECT 'posts.*','إدارة الأخبار',NULL WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code='posts.*');
INSERT INTO role_permissions (role_id, permission_id) SELECT r.id, p.id FROM roles r, permissions p WHERE r.name='admin' AND p.code='*' AND NOT EXISTS ( SELECT 1 FROM role_permissions rp WHERE rp.role_id=r.id AND rp.permission_id=p.id );
CREATE TABLE categories ( id INT UNSIGNED NOT NULL AUTO_INCREMENT, PRIMARY KEY (`id`),
  name VARCHAR(190) NOT NULL, slug VARCHAR(190) NOT NULL, parent_id INT NULL, sort_order INT NOT NULL DEFAULT 0, is_active BOOLEAN NOT NULL DEFAULT true, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uniq_categories_slug (slug), KEY idx_categories_parent (parent_id) );
CREATE TABLE news ( id INT UNSIGNED NOT NULL AUTO_INCREMENT, PRIMARY KEY (`id`),
  category_id INT NULL, opinion_author_id INT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, excerpt TEXT NULL, content LONGTEXT NULL, status VARCHAR(30) NOT NULL DEFAULT 'published', featured_image VARCHAR(255) NULL, image_path VARCHAR(255) NULL, image VARCHAR(255) NULL, is_breaking BOOLEAN NOT NULL DEFAULT false, view_count INT NOT NULL DEFAULT 0, published_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP, deleted_at DATETIME NULL, UNIQUE KEY uniq_news_slug (slug), KEY idx_news_category (category_id), KEY idx_news_opinion_author (opinion_author_id), KEY idx_news_status (status), KEY idx_news_published (published_at) );
CREATE TABLE tags ( id INT UNSIGNED NOT NULL AUTO_INCREMENT, PRIMARY KEY (`id`),
  name VARCHAR(120) NOT NULL, slug VARCHAR(190) NOT NULL, is_active BOOLEAN NOT NULL DEFAULT true, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uniq_tags_slug (slug) );
CREATE TABLE news_tags ( news_id INT NOT NULL, tag_id INT NOT NULL, PRIMARY KEY (news_id, tag_id) );
CREATE TABLE pages ( id INT UNSIGNED NOT NULL AUTO_INCREMENT, PRIMARY KEY (`id`),
  title VARCHAR(255) NOT NULL, slug VARCHAR(190) NOT NULL, content LONGTEXT NULL, status VARCHAR(30) NOT NULL DEFAULT 'published', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uniq_pages_slug (slug) );
CREATE TABLE settings ( setting_key VARCHAR(120) NOT NULL PRIMARY KEY, value LONGTEXT NULL, updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP );
UPDATE settings SET value='Godyar', updated_at=CURRENT_TIMESTAMP WHERE setting_key='site_name';
INSERT INTO settings(setting_key,value,updated_at) SELECT 'site_name','Godyar',CURRENT_TIMESTAMP WHERE NOT EXISTS (SELECT 1 FROM settings WHERE setting_key='site_name');
UPDATE settings SET value='ar', updated_at=CURRENT_TIMESTAMP WHERE setting_key='site_lang';
INSERT INTO settings(setting_key,value,updated_at) SELECT 'site_lang','ar',CURRENT_TIMESTAMP WHERE NOT EXISTS (SELECT 1 FROM settings WHERE setting_key='site_lang');
UPDATE settings SET value='rtl', updated_at=CURRENT_TIMESTAMP WHERE setting_key='site_dir';
INSERT INTO settings(setting_key,value,updated_at) SELECT 'site_dir','rtl',CURRENT_TIMESTAMP WHERE NOT EXISTS (SELECT 1 FROM settings WHERE setting_key='site_dir');
CREATE TABLE news_translations ( id INT UNSIGNED NOT NULL AUTO_INCREMENT, news_id INT NOT NULL, lang VARCHAR(12) NOT NULL, title VARCHAR(255) NULL, content LONGTEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY uniq_news_lang (news_id, lang), KEY idx_lang (lang), KEY idx_news (news_id) ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE featured_videos ( id INT UNSIGNED NOT NULL AUTO_INCREMENT, title VARCHAR(255) NOT NULL, url VARCHAR(500) NOT NULL, thumbnail VARCHAR(500) NULL, sort_order INT UNSIGNED NOT NULL DEFAULT 0, is_active TINYINT(1) NOT NULL DEFAULT 1, created_by INT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY idx_active (is_active), KEY idx_sort (sort_order) ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE opinion_authors ( id INT UNSIGNED NOT NULL AUTO_INCREMENT, name VARCHAR(190) NOT NULL, slug VARCHAR(190) NOT NULL, bio TEXT NULL, avatar VARCHAR(255) NULL, is_active BOOLEAN NOT NULL DEFAULT true, display_order INT NOT NULL DEFAULT 0, articles_count INT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY uniq_opinion_authors_slug (slug), KEY idx_opinion_authors_active (is_active), KEY idx_opinion_authors_order (display_order) );
-- INSTALL: install/sql/schema_optional_fks.sql
ALTER TABLE user_roles ADD CONSTRAINT fk_ur_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, ADD CONSTRAINT fk_ur_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE;
ALTER TABLE role_permissions ADD CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE, ADD CONSTRAINT fk_rp_perm FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE;
ALTER TABLE news ADD CONSTRAINT fk_news_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL;
ALTER TABLE news_tags ADD CONSTRAINT fk_nt_news FOREIGN KEY (news_id) REFERENCES news(id) ON DELETE CASCADE, ADD CONSTRAINT fk_nt_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE;
ALTER TABLE admin_saved_filters ADD CONSTRAINT fk_saved_filters_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
ALTER TABLE admin_notifications ADD CONSTRAINT fk_admin_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;
ALTER TABLE comments ADD CONSTRAINT fk_comments_news FOREIGN KEY (news_id) REFERENCES news(id) ON DELETE CASCADE;
-- INSTALL: install/sql/2026_01_14_runtime_patch_opinion_authors.sql
/* Godyar CMS - runtime patch for opinion_authors compatibility (MariaDB/MySQL)
   Adds missing columns expected by frontend queries.
*/

SET @tbl_exists := (
  SELECT COUNT(*) FROM information_schema.tables
  WHERE table_schema = DATABASE() AND table_name='opinion_authors'
);
-- If table doesn't exist, create it (minimal compatible schema)
SET @create_sql := IF(@tbl_exists=0,
  'CREATE TABLE opinion_authors (id INT UNSIGNED NOT NULL AUTO_INCREMENT, name VARCHAR(190) NOT NULL, slug VARCHAR(190) NOT NULL, bio TEXT NULL, avatar VARCHAR(255) NULL, specialization VARCHAR(190) NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY(id), UNIQUE KEY uniq_slug(slug), KEY idx_active(is_active)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
',
  'DO 0;
'
);
PREPARE stmt1 FROM @create_sql;
EXECUTE stmt1;
DEALLOCATE PREPARE stmt1;
-- Add specialization column if missing
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name='opinion_authors' AND column_name='specialization'
);
SET @alter_sql := IF(@tbl_exists=1 AND @col_exists=0,
  'ALTER TABLE opinion_authors ADD COLUMN specialization VARCHAR(190) NULL AFTER avatar;
',
  'DO 0;
'
);
PREPARE stmt2 FROM @alter_sql;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;
-- MIGRATION: database/migrations/2025_11_21_0000_create_pages.sql
CREATE TABLE IF NOT EXISTS pages ( id INT NOT NULL AUTO_INCREMENT, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, content LONGTEXT NULL, status ENUM('published','draft') NOT NULL DEFAULT 'published', created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY uq_pages_slug (slug) );
-- MIGRATION: database/migrations/2025_11_21_0001_create_user_bookmarks.sql
CREATE TABLE IF NOT EXISTS user_bookmarks ( id INT NOT NULL AUTO_INCREMENT, user_id INT NOT NULL, news_id INT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY uniq_user_news (user_id, news_id), KEY idx_user_id (user_id), KEY idx_news_id (news_id) );
-- MIGRATION: database/migrations/2025_11_21_0002_alter_opinion_optional.sql
DO 0;
-- MIGRATION: database/migrations/2025_11_21_0004_create_password_resets.sql
CREATE TABLE IF NOT EXISTS password_resets ( id INT NOT NULL AUTO_INCREMENT, email VARCHAR(190) NOT NULL, token VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ip_address VARCHAR(64) DEFAULT NULL, PRIMARY KEY (id), KEY idx_email (email), KEY idx_token (token) );
-- MIGRATION: database/migrations/2025_11_21_0005_create_news_reactions.sql
/* جدول لتخزين تفاعلات القرّاء على الأخبار */ CREATE TABLE IF NOT EXISTS news_reactions ( id INT NOT NULL AUTO_INCREMENT, news_id INT NOT NULL, type VARCHAR(20) NOT NULL, ip_address VARCHAR(64) DEFAULT NULL, user_agent VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY idx_news (news_id), KEY idx_news_type (news_id, type) ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- MIGRATION: database/migrations/2025_11_21_0007_create_settings_snapshots.sql
CREATE TABLE IF NOT EXISTS settings_snapshots ( id INT NOT NULL AUTO_INCREMENT, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, created_by INT DEFAULT NULL, data_json LONGTEXT NOT NULL, PRIMARY KEY (id), KEY idx_created_at (created_at) );
-- MIGRATION: database/migrations/2025_11_21_0008_seed_default_pages.sql




-- MIGRATION: database/migrations/2025_11_21_0009_create_contact_messages.sql
CREATE TABLE IF NOT EXISTS contact_messages ( id INT NOT NULL AUTO_INCREMENT, name VARCHAR(190) NOT NULL, email VARCHAR(190) NOT NULL, subject VARCHAR(255) NULL, message TEXT NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'new', is_read BOOLEAN NOT NULL DEFAULT false, replied_at DATETIME NULL, replied_by INT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY idx_status (status), KEY idx_created_at (created_at) );
ALTER TABLE contact_messages ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'new', ADD COLUMN IF NOT EXISTS is_read BOOLEAN NOT NULL DEFAULT false, ADD COLUMN IF NOT EXISTS replied_at DATETIME NULL, ADD COLUMN IF NOT EXISTS replied_by INT NULL, ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
-- MIGRATION: database/migrations/2025_12_13_add_writer_role.sql
ALTER TABLE users MODIFY role ENUM('admin','editor','writer','author','user') NOT NULL DEFAULT 'user';
UPDATE users SET role='writer' WHERE role='user' AND email IN ('writer@example.com');
-- MIGRATION: database/migrations/2025_12_24_0000_create_news_imports.sql
CREATE TABLE IF NOT EXISTS news_imports ( id INT NOT NULL AUTO_INCREMENT, news_id INT NOT NULL, feed_id INT NOT NULL, item_hash CHAR(40) NOT NULL, item_link VARCHAR(1000) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY uq_item_hash (item_hash), KEY idx_feed (feed_id), KEY idx_news (news_id) );
-- MIGRATION: database/migrations/2025_12_25_120000_news_workflow.sql
CREATE TABLE IF NOT EXISTS news_notes ( id INT NOT NULL AUTO_INCREMENT, news_id INT NOT NULL, user_id INT NULL, note TEXT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY idx_news_id (news_id), KEY idx_created_at (created_at) );
CREATE TABLE IF NOT EXISTS news_revisions ( id INT NOT NULL AUTO_INCREMENT, news_id INT NOT NULL, user_id INT NULL, action VARCHAR(30) NOT NULL DEFAULT 'update', payload LONGTEXT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY idx_news_id (news_id), KEY idx_created_at (created_at) );
-- MIGRATION: database/migrations/2026_01_02_add_members_only.sql
ALTER TABLE categories ADD COLUMN is_members_only BOOLEAN NOT NULL DEFAULT false;
ALTER TABLE news ADD COLUMN is_members_only BOOLEAN NOT NULL DEFAULT false;
CREATE INDEX idx_categories_members_only ON categories (is_members_only);
CREATE INDEX idx_news_members_only ON news (is_members_only);
-- MIGRATION: database/migrations/2026_01_02_create_news_questions.sql
CREATE TABLE IF NOT EXISTS news_questions ( id INT NOT NULL AUTO_INCREMENT, news_id INT NOT NULL, user_id INT NULL, name VARCHAR(120) NULL, email VARCHAR(190) NULL, question TEXT NOT NULL, answer TEXT NULL, status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending', created_at DATETIME NULL, answered_at DATETIME NULL, PRIMARY KEY (id), KEY idx_news (news_id), KEY idx_status (status) );
-- MIGRATION: database/migrations/2026_01_10_schema_runtime_compat.sql
ALTER TABLE users ADD COLUMN twofa_enabled BOOLEAN NOT NULL DEFAULT false;
ALTER TABLE users ADD COLUMN twofa_secret VARCHAR(255) NULL;
ALTER TABLE users ADD COLUMN session_version INT NOT NULL DEFAULT true;
ALTER TABLE news ADD COLUMN views INT NOT NULL DEFAULT false;
CREATE TABLE IF NOT EXISTS visits ( id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, PRIMARY KEY (id), page VARCHAR(60) NOT NULL, news_id INT NULL, source VARCHAR(20) NOT NULL DEFAULT 'direct', referrer VARCHAR(255) NULL, user_ip VARCHAR(45) NULL, user_agent VARCHAR(255) NULL, os VARCHAR(40) NULL, browser VARCHAR(40) NULL, device VARCHAR(20) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_created (created_at), KEY idx_news (news_id), KEY idx_source (source) ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE visits ADD COLUMN news_id INT NULL;
ALTER TABLE visits ADD COLUMN source VARCHAR(20) NOT NULL DEFAULT 'direct';
ALTER TABLE visits ADD COLUMN referrer VARCHAR(255) NULL;
ALTER TABLE visits ADD COLUMN user_ip VARCHAR(45) NULL;
ALTER TABLE visits ADD COLUMN user_agent VARCHAR(255) NULL;
ALTER TABLE visits ADD COLUMN os VARCHAR(40) NULL;
ALTER TABLE visits ADD COLUMN browser VARCHAR(40) NULL;
ALTER TABLE visits ADD COLUMN device VARCHAR(20) NULL;
ALTER TABLE visits ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;
CREATE TABLE IF NOT EXISTS opinion_authors ( id INT UNSIGNED NOT NULL AUTO_INCREMENT, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL UNIQUE, page_title VARCHAR(255) NULL, bio TEXT NULL, specialization VARCHAR(255) NULL, social_website VARCHAR(255) NULL, social_twitter VARCHAR(255) NULL, social_facebook VARCHAR(255) NULL, email VARCHAR(190) NULL, avatar VARCHAR(255) NULL, is_active BOOLEAN NOT NULL DEFAULT true, sort_order INT NOT NULL DEFAULT false, display_order INT NOT NULL DEFAULT false, articles_count INT NOT NULL DEFAULT false, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id) );
-- MIGRATION: database/migrations/2026_01_14_0001_create_opinion_authors.sql
CREATE TABLE IF NOT EXISTS opinion_authors ( id INT UNSIGNED NOT NULL AUTO_INCREMENT, name VARCHAR(190) NOT NULL, slug VARCHAR(190) NOT NULL, bio TEXT NULL, avatar VARCHAR(255) NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, display_order INT NOT NULL DEFAULT 0, articles_count INT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY uniq_opinion_authors_slug (slug), KEY idx_opinion_authors_active (is_active), KEY idx_opinion_authors_order (display_order) ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- MIGRATION: database/migrations/2026_02_02_add_users_2fa_backup_codes.sql
-- Add backup codes column (optional if already exists)
ALTER TABLE users
ADD COLUMN twofa_backup_codes TEXT NULL AFTER twofa_secret;
-- (Optional) index for faster lookups (if needed)
-- CREATE INDEX idx_users_twofa_enabled ON users(twofa_enabled);
-- MIGRATION: admin/db/migrations/2025_12_13_news_pro_columns.sql
ALTER TABLE news ADD COLUMN seo_title VARCHAR(255) NULL, ADD COLUMN seo_description VARCHAR(300) NULL, ADD COLUMN seo_keywords VARCHAR(255) NULL, ADD COLUMN publish_at DATETIME NULL, ADD COLUMN unpublish_at DATETIME NULL;
-- MIGRATION: admin/db/migrations/2026_01_04_admin_notifications.sql
CREATE TABLE IF NOT EXISTS admin_notifications ( id INT NOT NULL AUTO_INCREMENT, user_id INT NULL DEFAULT NULL, title VARCHAR(255) NOT NULL, body TEXT NULL, link VARCHAR(500) NULL, is_read BOOLEAN NOT NULL DEFAULT false, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), INDEX idx_admin_notifications_user (user_id), INDEX idx_admin_notifications_read (is_read) );
-- MIGRATION: admin/db/migrations/2026_01_04_admin_saved_filters.sql
CREATE TABLE IF NOT EXISTS admin_saved_filters ( id INT NOT NULL AUTO_INCREMENT, user_id INT NOT NULL, page_key VARCHAR(64) NOT NULL, name VARCHAR(120) NOT NULL, querystring VARCHAR(1000) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY idx_saved_filters_user_page (user_id, page_key) );
-- MIGRATION: admin/db/migrations/2026_01_05_admin_saved_filters_default.sql
ALTER TABLE admin_saved_filters ADD COLUMN is_default BOOLEAN NOT NULL DEFAULT false;
CREATE INDEX idx_admin_saved_filters_default ON admin_saved_filters(user_id, page_key, is_default);
-- MIGRATION: admin/db/migrations/2026_01_10_schema_runtime_compat.sql
ALTER TABLE users ADD COLUMN twofa_enabled BOOLEAN NOT NULL DEFAULT false;
ALTER TABLE users ADD COLUMN twofa_secret VARCHAR(255) NULL;
ALTER TABLE users ADD COLUMN session_version INT NOT NULL DEFAULT true;
ALTER TABLE news ADD COLUMN views INT NOT NULL DEFAULT false;
CREATE TABLE IF NOT EXISTS visits ( id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, PRIMARY KEY (id), page VARCHAR(60) NOT NULL, news_id INT NULL, source VARCHAR(20) NOT NULL DEFAULT 'direct', referrer VARCHAR(255) NULL, user_ip VARCHAR(45) NULL, user_agent VARCHAR(255) NULL, os VARCHAR(40) NULL, browser VARCHAR(40) NULL, device VARCHAR(20) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_created (created_at), KEY idx_news (news_id), KEY idx_source (source) ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE visits ADD COLUMN news_id INT NULL;
ALTER TABLE visits ADD COLUMN source VARCHAR(20) NOT NULL DEFAULT 'direct';
ALTER TABLE visits ADD COLUMN referrer VARCHAR(255) NULL;
ALTER TABLE visits ADD COLUMN user_ip VARCHAR(45) NULL;
ALTER TABLE visits ADD COLUMN user_agent VARCHAR(255) NULL;
ALTER TABLE visits ADD COLUMN os VARCHAR(40) NULL;
ALTER TABLE visits ADD COLUMN browser VARCHAR(40) NULL;
ALTER TABLE visits ADD COLUMN device VARCHAR(20) NULL;
ALTER TABLE visits ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;
CREATE TABLE IF NOT EXISTS opinion_authors ( id INT UNSIGNED NOT NULL AUTO_INCREMENT, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL UNIQUE, page_title VARCHAR(255) NULL, bio TEXT NULL, specialization VARCHAR(255) NULL, social_website VARCHAR(255) NULL, social_twitter VARCHAR(255) NULL, social_facebook VARCHAR(255) NULL, email VARCHAR(190) NULL, avatar VARCHAR(255) NULL, is_active BOOLEAN NOT NULL DEFAULT true, sort_order INT NOT NULL DEFAULT false, display_order INT NOT NULL DEFAULT false, articles_count INT NOT NULL DEFAULT false, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id) );
-- MIGRATION: admin/db/migrations/2026_01_14_0001_create_opinion_authors.sql
CREATE TABLE IF NOT EXISTS opinion_authors ( id INT UNSIGNED NOT NULL AUTO_INCREMENT, name VARCHAR(190) NOT NULL, slug VARCHAR(190) NOT NULL, bio TEXT NULL, avatar VARCHAR(255) NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, display_order INT NOT NULL DEFAULT 0, articles_count INT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY uniq_opinion_authors_slug (slug), KEY idx_opinion_authors_active (is_active), KEY idx_opinion_authors_order (display_order) ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- MIGRATION: migrations/2025_11_12_add_missing_tables_and_columns.sql
CREATE TABLE IF NOT EXISTS visits ( id INT PRIMARY KEY AUTO_INCREMENT, page VARCHAR(500) NOT NULL, ip_address VARCHAR(45) NOT NULL, user_agent VARCHAR(500) NULL, referrer VARCHAR(500) NULL, visit_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_visits_time (visit_time), KEY idx_visits_page (page(191)) );
CREATE TABLE IF NOT EXISTS sessions ( id INT PRIMARY KEY AUTO_INCREMENT, session_id VARCHAR(191) NOT NULL, user_id INT NULL, ip_address VARCHAR(45) NULL, user_agent VARCHAR(500) NULL, data MEDIUMTEXT NULL, last_activity DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_sessions_session_id (session_id), KEY idx_sessions_last_activity (last_activity), KEY idx_sessions_user_id (user_id) );
CREATE TABLE IF NOT EXISTS feeds ( id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(255) NOT NULL, url VARCHAR(500) NOT NULL, category_id INT NULL, is_active BOOLEAN NOT NULL DEFAULT true, fetch_interval_minutes INT NOT NULL DEFAULT 60, last_fetched_at DATETIME NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_feeds_url (url(191)), KEY idx_feeds_category_id (category_id), KEY idx_feeds_is_active (is_active) );
ALTER TABLE news ADD COLUMN excerpt VARCHAR(500) NULL, ADD COLUMN views INT NOT NULL DEFAULT 0, ADD COLUMN is_featured BOOLEAN NOT NULL DEFAULT 0, ADD COLUMN priority TINYINT NOT NULL DEFAULT 0, ADD COLUMN seo_title VARCHAR(255) NULL, ADD COLUMN seo_description VARCHAR(300) NULL, ADD COLUMN seo_keywords VARCHAR(500) NULL;
CREATE INDEX idx_news_status_publish ON news (status, published_at);
CREATE INDEX idx_news_category ON news (category_id);
CREATE INDEX idx_news_tags_news ON news_tags (news_id);
CREATE INDEX idx_news_tags_tag ON news_tags (tag_id);
ALTER TABLE settings MODIFY COLUMN value LONGTEXT NULL;
-- MIGRATION: migrations/2025_12_25_news_translations.sql
CREATE TABLE IF NOT EXISTS news_translations ( id INT UNSIGNED NOT NULL AUTO_INCREMENT, news_id INT NOT NULL, lang CHAR(2) NOT NULL, title VARCHAR(255) NULL, excerpt VARCHAR(700) NULL, content MEDIUMTEXT NULL, seo_title VARCHAR(255) NULL, seo_description VARCHAR(400) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uniq_news_lang (news_id, lang), KEY idx_news_trans_news (news_id), KEY idx_lang (lang) );
SET FOREIGN_KEY_CHECKS=1;
