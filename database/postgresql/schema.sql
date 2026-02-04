-- Godyar CMS standard schema (generated)

-- Generated at: 2026-02-02T08:33:51.124631


-- PostgreSQL

CREATE TABLE IF NOT EXISTS schema_migrations (
  id BIGSERIAL PRIMARY KEY,
  name VARCHAR(255) NOT NULL UNIQUE,
  checksum CHAR(64) NOT NULL,
  applied_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);


SET time_zone = '+03:00'; SET FOREIGN_KEY_CHECKS=0; DROP TABLE IF EXISTS news_tags; DROP TABLE IF EXISTS role_permissions; DROP TABLE IF EXISTS user_roles; DROP TABLE IF EXISTS news; DROP TABLE IF EXISTS tags; DROP TABLE IF EXISTS pages; DROP TABLE IF EXISTS settings; DROP TABLE IF EXISTS categories; DROP TABLE IF EXISTS permissions; DROP TABLE IF EXISTS roles; DROP TABLE IF EXISTS users; SET FOREIGN_KEY_CHECKS=1; CREATE TABLE users ( id SERIAL PRIMARY KEY, name VARCHAR(190) NULL, username VARCHAR(60) NOT NULL, email VARCHAR(190) NOT NULL, password_hash VARCHAR(255) NOT NULL, /* Legacy compatibility: some parts still reference password */ password VARCHAR(255) NULL, role VARCHAR(100) NOT NULL DEFAULT 'user', is_admin BOOLEAN NOT NULL DEFAULT false, status VARCHAR(20) NOT NULL DEFAULT 'active', avatar VARCHAR(255) NULL, last_login_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uniq_users_email (email), UNIQUE KEY uniq_users_username (username), KEY idx_users_role (role), KEY idx_users_status (status), KEY idx_users_is_admin (is_admin) ); CREATE TABLE roles ( id SERIAL PRIMARY KEY, name VARCHAR(60) NOT NULL, label VARCHAR(120) NOT NULL, description TEXT NULL, is_system BOOLEAN NOT NULL DEFAULT true, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uniq_roles_name (name) ); CREATE TABLE permissions ( id SERIAL PRIMARY KEY, code VARCHAR(120) NOT NULL, label VARCHAR(190) NOT NULL, description TEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uniq_permissions_code (code) ); CREATE TABLE role_permissions ( role_id INT NOT NULL, permission_id INT NOT NULL, PRIMARY KEY (role_id, permission_id) ); CREATE TABLE user_roles ( user_id INT NOT NULL, role_id INT NOT NULL, PRIMARY KEY (user_id, role_id) ); INSERT INTO roles (name,label,description,is_system) SELECT 'admin','مدير النظام','صلاحيات كاملة',1 WHERE NOT EXISTS (SELECT 1 FROM roles WHERE name='admin'); INSERT INTO roles (name,label,description,is_system) SELECT 'writer','كاتب','كتابة وتعديل أخبار',1 WHERE NOT EXISTS (SELECT 1 FROM roles WHERE name='writer'); INSERT INTO roles (name,label,description,is_system) SELECT 'user','مستخدم','حساب مستخدم عادي',1 WHERE NOT EXISTS (SELECT 1 FROM roles WHERE name='user'); INSERT INTO permissions (code,label,description) SELECT '*','صلاحيات كاملة','جميع الصلاحيات' WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code='*'); INSERT INTO permissions (code,label,description) SELECT 'manage_users','إدارة المستخدمين',NULL WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code='manage_users'); INSERT INTO permissions (code,label,description) SELECT 'manage_roles','إدارة الأدوار والصلاحيات',NULL WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code='manage_roles'); INSERT INTO permissions (code,label,description) SELECT 'manage_security','إعدادات الأمان',NULL WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code='manage_security'); INSERT INTO permissions (code,label,description) SELECT 'manage_plugins','إدارة الإضافات',NULL WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code='manage_plugins'); INSERT INTO permissions (code,label,description) SELECT 'posts.*','إدارة الأخبار',NULL WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code='posts.*'); INSERT INTO role_permissions (role_id, permission_id) SELECT r.id, p.id FROM roles r, permissions p WHERE r.name='admin' AND p.code='*' AND NOT EXISTS ( SELECT 1 FROM role_permissions rp WHERE rp.role_id=r.id AND rp.permission_id=p.id ); CREATE TABLE categories ( id SERIAL PRIMARY KEY, name VARCHAR(190) NOT NULL, slug VARCHAR(190) NOT NULL, parent_id INT NULL, sort_order INT NOT NULL DEFAULT 0, is_active BOOLEAN NOT NULL DEFAULT true, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uniq_categories_slug (slug), KEY idx_categories_parent (parent_id) ); CREATE TABLE news ( id SERIAL PRIMARY KEY, category_id INT NULL, opinion_author_id INT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, excerpt TEXT NULL, content LONGTEXT NULL, status VARCHAR(30) NOT NULL DEFAULT 'published', featured_image VARCHAR(255) NULL, image_path VARCHAR(255) NULL, image VARCHAR(255) NULL, is_breaking BOOLEAN NOT NULL DEFAULT false, view_count INT NOT NULL DEFAULT 0, published_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP, deleted_at DATETIME NULL, UNIQUE KEY uniq_news_slug (slug), KEY idx_news_category (category_id), KEY idx_news_opinion_author (opinion_author_id), KEY idx_news_status (status), KEY idx_news_published (published_at) ); CREATE TABLE tags ( id SERIAL PRIMARY KEY, name VARCHAR(120) NOT NULL, slug VARCHAR(190) NOT NULL, is_active BOOLEAN NOT NULL DEFAULT true, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uniq_tags_slug (slug) ); CREATE TABLE news_tags ( news_id INT NOT NULL, tag_id INT NOT NULL, PRIMARY KEY (news_id, tag_id) ); CREATE TABLE pages ( id SERIAL PRIMARY KEY, title VARCHAR(255) NOT NULL, slug VARCHAR(190) NOT NULL, content LONGTEXT NULL, status VARCHAR(30) NOT NULL DEFAULT 'published', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uniq_pages_slug (slug) ); CREATE TABLE settings ( setting_key VARCHAR(120) NOT NULL PRIMARY KEY, value LONGTEXT NULL, updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP ); UPDATE settings SET value='Godyar', updated_at=CURRENT_TIMESTAMP WHERE setting_key='site_name'; INSERT INTO settings(setting_key,value,updated_at) SELECT 'site_name','Godyar',CURRENT_TIMESTAMP WHERE NOT EXISTS (SELECT 1 FROM settings WHERE setting_key='site_name'); UPDATE settings SET value='ar', updated_at=CURRENT_TIMESTAMP WHERE setting_key='site_lang'; INSERT INTO settings(setting_key,value,updated_at) SELECT 'site_lang','ar',CURRENT_TIMESTAMP WHERE NOT EXISTS (SELECT 1 FROM settings WHERE setting_key='site_lang'); UPDATE settings SET value='rtl', updated_at=CURRENT_TIMESTAMP WHERE setting_key='site_dir'; INSERT INTO settings(setting_key,value,updated_at) SELECT 'site_dir','rtl',CURRENT_TIMESTAMP WHERE NOT EXISTS (SELECT 1 FROM settings WHERE setting_key='site_dir'); CREATE TABLE news_translations ( id INT UNSIGNED NOT NULL AUTO_INCREMENT, news_id INT NOT NULL, lang VARCHAR(12) NOT NULL, title VARCHAR(255) NULL, content LONGTEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY uniq_news_lang (news_id, lang), KEY idx_lang (lang), KEY idx_news (news_id) ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci; CREATE TABLE featured_videos ( id INT UNSIGNED NOT NULL AUTO_INCREMENT, title VARCHAR(255) NOT NULL, url VARCHAR(500) NOT NULL, thumbnail VARCHAR(500) NULL, sort_order INT UNSIGNED NOT NULL DEFAULT 0, is_active TINYINT(1) NOT NULL DEFAULT 1, created_by INT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY idx_active (is_active), KEY idx_sort (sort_order) ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci; CREATE TABLE opinion_authors ( id INT UNSIGNED NOT NULL AUTO_INCREMENT, name VARCHAR(190) NOT NULL, slug VARCHAR(190) NOT NULL, bio TEXT NULL, avatar VARCHAR(255) NULL, is_active BOOLEAN NOT NULL DEFAULT true, display_order INT NOT NULL DEFAULT 0, articles_count INT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY uniq_opinion_authors_slug (slug), KEY idx_opinion_authors_active (is_active), KEY idx_opinion_authors_order (display_order) );



-- Migration: 2025_11_12_add_missing_tables_and_columns.sql

CREATE TABLE IF NOT EXISTS visits ( id INTEGER PRIMARY KEY GENERATED BY DEFAULT AS IDENTITY, page VARCHAR(500) NOT NULL, ip_address VARCHAR(45) NOT NULL, user_agent VARCHAR(500) NULL, referrer VARCHAR(500) NULL, visit_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_visits_time (visit_time), KEY idx_visits_page (page(191)) ); CREATE TABLE IF NOT EXISTS sessions ( id INTEGER PRIMARY KEY GENERATED BY DEFAULT AS IDENTITY, session_id VARCHAR(191) NOT NULL, user_id INTEGER NULL, ip_address VARCHAR(45) NULL, user_agent VARCHAR(500) NULL, data TEXT NULL, last_activity TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_sessions_session_id (session_id), KEY idx_sessions_last_activity (last_activity), KEY idx_sessions_user_id (user_id) ); CREATE TABLE IF NOT EXISTS feeds ( id INTEGER PRIMARY KEY GENERATED BY DEFAULT AS IDENTITY, name VARCHAR(255) NOT NULL, url VARCHAR(500) NOT NULL, category_id INTEGER NULL, is_active BOOLEAN NOT NULL DEFAULT true, fetch_interval_minutes INTEGER NOT NULL DEFAULT 60, last_fetched_at TIMESTAMP NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_feeds_url (url(191)), KEY idx_feeds_category_id (category_id), KEY idx_feeds_is_active (is_active) ); ALTER TABLE news ADD COLUMN excerpt VARCHAR(500) NULL, ADD COLUMN views INTEGER NOT NULL DEFAULT false, ADD COLUMN is_featured BOOLEAN NOT NULL DEFAULT false, ADD COLUMN priority SMALLINT NOT NULL DEFAULT false, ADD COLUMN seo_title VARCHAR(255) NULL, ADD COLUMN seo_description VARCHAR(300) NULL, ADD COLUMN seo_keywords VARCHAR(500) NULL; CREATE INDEX idx_news_status_publish ON news (status, publish_at); CREATE INDEX idx_news_category ON news (category_id); CREATE INDEX idx_news_tags_news ON news_tags (news_id); CREATE INDEX idx_news_tags_tag ON news_tags (tag_id); ALTER TABLE settings ALTER COLUMN value TYPE TEXT;


-- Migration: 2025_11_21_0000_create_pages.sql

CREATE TABLE IF NOT EXISTS pages ( id INTEGER NOT NULL GENERATED BY DEFAULT AS IDENTITY, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, content TEXT NULL, status ENUM('published','draft') NOT NULL DEFAULT 'published', created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY uq_pages_slug (slug) );


-- Migration: 2025_11_21_0001_create_user_bookmarks.sql

CREATE TABLE IF NOT EXISTS user_bookmarks ( id INTEGER NOT NULL GENERATED BY DEFAULT AS IDENTITY, user_id INTEGER NOT NULL, news_id INTEGER NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY uniq_user_news (user_id, news_id), KEY idx_user_id (user_id), KEY idx_news_id (news_id) );


-- Migration: 2025_11_21_0002_alter_opinion_optional.sql

ALTER TABLE opinion ADD COLUMN content TEXT NULL; ALTER TABLE opinion ADD COLUMN author_slug VARCHAR(190) NULL; ALTER TABLE opinion ADD COLUMN views INTEGER NOT NULL DEFAULT 0;


-- Migration: 2025_11_21_0004_create_password_resets.sql

CREATE TABLE IF NOT EXISTS password_resets ( id INTEGER NOT NULL GENERATED BY DEFAULT AS IDENTITY, email VARCHAR(190) NOT NULL, token VARCHAR(190) NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, ip_address VARCHAR(64) DEFAULT NULL, PRIMARY KEY (id), KEY idx_email (email), KEY idx_token (token) );


-- Migration: 2025_11_21_0005_create_news_reactions.sql

/* جدول لتخزين تفاعلات القرّاء على الأخبار */ CREATE TABLE IF NOT EXISTS news_reactions ( id BIGINT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY, news_id BIGINT NOT NULL, type VARCHAR(20) NOT NULL, ip_address VARCHAR(64) DEFAULT NULL, user_agent VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ); CREATE INDEX IF NOT EXISTS idx_news_reactions_news_id ON news_reactions (news_id); CREATE INDEX IF NOT EXISTS idx_news_reactions_news_id_type ON news_reactions (news_id, type);


-- Migration: 2025_11_21_0007_create_settings_snapshots.sql

CREATE TABLE IF NOT EXISTS settings_snapshots ( id INTEGER NOT NULL GENERATED BY DEFAULT AS IDENTITY, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, created_by INTEGER DEFAULT NULL, data_json TEXT NOT NULL, PRIMARY KEY (id), KEY idx_created_at (created_at) );


-- Migration: 2025_11_21_0008_seed_default_pages.sql




-- Migration: 2025_11_21_0009_create_contact_messages.sql

CREATE TABLE IF NOT EXISTS contact_messages ( id INTEGER NOT NULL GENERATED BY DEFAULT AS IDENTITY, name VARCHAR(190) NOT NULL, email VARCHAR(190) NOT NULL, subject VARCHAR(255) NULL, message TEXT NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'new', is_read BOOLEAN NOT NULL DEFAULT false, replied_at TIMESTAMP NULL, replied_by INTEGER NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY idx_status (status), KEY idx_created_at (created_at) ); ALTER TABLE contact_messages ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'new', ADD COLUMN IF NOT EXISTS is_read BOOLEAN NOT NULL DEFAULT false, ADD COLUMN IF NOT EXISTS replied_at TIMESTAMP NULL, ADD COLUMN IF NOT EXISTS replied_by INTEGER NULL, ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;


-- Migration: 2025_12_13_add_writer_role.sql

ALTER TABLE users MODIFY role ENUM('admin','editor','writer','author','user') NOT NULL DEFAULT 'user'; UPDATE users SET role='writer' WHERE role='user' AND email IN ('writer@example.com');


-- Migration: 2025_12_13_news_pro_columns.sql

ALTER TABLE news ADD COLUMN seo_title VARCHAR(255) NULL, ADD COLUMN seo_description VARCHAR(300) NULL, ADD COLUMN seo_keywords VARCHAR(255) NULL, ADD COLUMN publish_at TIMESTAMP NULL, ADD COLUMN unpublish_at TIMESTAMP NULL;


-- Migration: 2025_12_24_0000_create_news_imports.sql

CREATE TABLE IF NOT EXISTS news_imports ( id INTEGER NOT NULL GENERATED BY DEFAULT AS IDENTITY, news_id INTEGER NOT NULL, feed_id INTEGER NOT NULL, item_hash CHAR(40) NOT NULL, item_link VARCHAR(1000) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY uq_item_hash (item_hash), KEY idx_feed (feed_id), KEY idx_news (news_id) );


-- Migration: 2025_12_25_120000_news_workflow.sql

CREATE TABLE IF NOT EXISTS news_notes ( id INTEGER NOT NULL GENERATED BY DEFAULT AS IDENTITY, news_id INTEGER NOT NULL, user_id INTEGER NULL, note TEXT NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY idx_news_id (news_id), KEY idx_created_at (created_at) ); CREATE TABLE IF NOT EXISTS news_revisions ( id INTEGER NOT NULL GENERATED BY DEFAULT AS IDENTITY, news_id INTEGER NOT NULL, user_id INTEGER NULL, action VARCHAR(30) NOT NULL DEFAULT 'update', payload TEXT NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY idx_news_id (news_id), KEY idx_created_at (created_at) );


-- Migration: 2025_12_25_news_translations.sql

CREATE TABLE IF NOT EXISTS news_translations ( id SERIAL PRIMARY KEY, news_id INTEGER NOT NULL, lang CHAR(2) NOT NULL, title VARCHAR(255) NULL, excerpt VARCHAR(700) NULL, content TEXT NULL, seo_title VARCHAR(255) NULL, seo_description VARCHAR(400) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uniq_news_lang (news_id, lang), KEY idx_news_trans_news (news_id), KEY idx_lang (lang) );


-- Migration: 2026_01_02_add_members_only.sql

ALTER TABLE categories ADD COLUMN is_members_only BOOLEAN NOT NULL DEFAULT false; ALTER TABLE news ADD COLUMN is_members_only BOOLEAN NOT NULL DEFAULT false; CREATE INDEX idx_categories_members_only ON categories (is_members_only); CREATE INDEX idx_news_members_only ON news (is_members_only);


-- Migration: 2026_01_02_create_news_questions.sql

Create table for "Ask the writer" Q&A feature CREATE TABLE IF NOT EXISTS news_questions ( id INTEGER NOT NULL GENERATED BY DEFAULT AS IDENTITY, news_id INTEGER NOT NULL, user_id INTEGER NULL, name VARCHAR(120) NULL, email VARCHAR(190) NULL, question TEXT NOT NULL, answer TEXT NULL, status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending', created_at TIMESTAMP NULL, answered_at TIMESTAMP NULL, PRIMARY KEY (id), KEY idx_news (news_id), KEY idx_status (status) );


-- Migration: 2026_01_04_admin_notifications.sql

CREATE TABLE IF NOT EXISTS admin_notifications ( id INTEGER NOT NULL GENERATED BY DEFAULT AS IDENTITY, user_id INTEGER NULL DEFAULT NULL, title VARCHAR(255) NOT NULL, body TEXT NULL, link VARCHAR(500) NULL, is_read BOOLEAN NOT NULL DEFAULT false, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), INDEX idx_admin_notifications_user (user_id), INDEX idx_admin_notifications_read (is_read) );


-- Migration: 2026_01_04_admin_saved_filters.sql

CREATE TABLE IF NOT EXISTS admin_saved_filters ( id INTEGER NOT NULL GENERATED BY DEFAULT AS IDENTITY, user_id INTEGER NOT NULL, page_key VARCHAR(64) NOT NULL, name VARCHAR(120) NOT NULL, querystring VARCHAR(1000) NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY idx_saved_filters_user_page (user_id, page_key) );


-- Migration: 2026_01_05_admin_saved_filters_default.sql

ALTER TABLE admin_saved_filters ADD COLUMN is_default BOOLEAN NOT NULL DEFAULT false; CREATE INDEX idx_admin_saved_filters_default ON admin_saved_filters(user_id, page_key, is_default);


-- Migration: 2026_01_10_schema_runtime_compat.sql

ALTER TABLE users ADD COLUMN twofa_enabled BOOLEAN NOT NULL DEFAULT false; ALTER TABLE users ADD COLUMN twofa_secret VARCHAR(255) NULL; ALTER TABLE users ADD COLUMN session_version INTEGER NOT NULL DEFAULT 1; ALTER TABLE news ADD COLUMN views INTEGER NOT NULL DEFAULT 0; CREATE TABLE IF NOT EXISTS visits ( id BIGSERIAL PRIMARY KEY, page VARCHAR(60) NOT NULL, news_id INTEGER NULL, source VARCHAR(20) NOT NULL DEFAULT 'direct', referrer VARCHAR(255) NULL, user_ip VARCHAR(45) NULL, user_agent VARCHAR(255) NULL, os VARCHAR(40) NULL, browser VARCHAR(40) NULL, device VARCHAR(20) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_created (created_at), KEY idx_news (news_id), KEY idx_source (source) ); ALTER TABLE visits ADD COLUMN news_id INTEGER NULL; ALTER TABLE visits ADD COLUMN source VARCHAR(20) NOT NULL DEFAULT 'direct'; ALTER TABLE visits ADD COLUMN referrer VARCHAR(255) NULL; ALTER TABLE visits ADD COLUMN user_ip VARCHAR(45) NULL; ALTER TABLE visits ADD COLUMN user_agent VARCHAR(255) NULL; ALTER TABLE visits ADD COLUMN os VARCHAR(40) NULL; ALTER TABLE visits ADD COLUMN browser VARCHAR(40) NULL; ALTER TABLE visits ADD COLUMN device VARCHAR(20) NULL; ALTER TABLE visits ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP; CREATE TABLE IF NOT EXISTS opinion_authors ( id SERIAL PRIMARY KEY, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL UNIQUE, page_title VARCHAR(255) NULL, bio TEXT NULL, specialization VARCHAR(255) NULL, social_website VARCHAR(255) NULL, social_twitter VARCHAR(255) NULL, social_facebook VARCHAR(255) NULL, email VARCHAR(190) NULL, avatar VARCHAR(255) NULL, is_active BOOLEAN NOT NULL DEFAULT true, sort_order INTEGER NOT NULL DEFAULT false, display_order INTEGER NOT NULL DEFAULT false, articles_count INTEGER NOT NULL DEFAULT false, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP ); 
-- Optional foreign keys
ALTER TABLE user_roles ADD CONSTRAINT fk_ur_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, ADD CONSTRAINT fk_ur_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE; ALTER TABLE role_permissions ADD CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE, ADD CONSTRAINT fk_rp_perm FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE; ALTER TABLE news ADD CONSTRAINT fk_news_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL; ALTER TABLE news_tags ADD CONSTRAINT fk_nt_news FOREIGN KEY (news_id) REFERENCES news(id) ON DELETE CASCADE, ADD CONSTRAINT fk_nt_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE;
ALTER TABLE admin_saved_filters ADD CONSTRAINT fk_saved_filters_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE; ALTER TABLE admin_notifications ADD CONSTRAINT fk_admin_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL; ALTER TABLE comments ADD CONSTRAINT fk_comments_news FOREIGN KEY (news_id) REFERENCES news(id) ON DELETE CASCADE;
