-- 2025_11_12_add_missing_tables_and_columns.sql
-- Idempotent-ish migration: creates missing feature tables and adds commonly-missing columns.
-- Safe to rerun: installer ignores duplicate table/column errors.

-- 1) Extra columns (categories)
ALTER TABLE categories ADD COLUMN updated_at DATETIME NULL;
ALTER TABLE categories ADD COLUMN is_members_only TINYINT(1) NOT NULL DEFAULT 0;

-- 2) Extra columns (settings)
ALTER TABLE settings ADD COLUMN setting_value LONGTEXT NULL;

-- 3) Extra columns (users)
ALTER TABLE users ADD COLUMN github_id VARCHAR(190) NULL;
ALTER TABLE users ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN last_login_at DATETIME NULL;
ALTER TABLE users ADD COLUMN twofa_enabled TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN twofa_secret VARCHAR(255) NULL;
ALTER TABLE users ADD COLUMN twofa_backup_codes LONGTEXT NULL;
ALTER TABLE users ADD COLUMN session_version INT NOT NULL DEFAULT 1;

-- 4) Extra columns (news)
ALTER TABLE news ADD COLUMN opinion_author_id INT NULL;
ALTER TABLE news ADD COLUMN image_path VARCHAR(255) NULL;
ALTER TABLE news ADD COLUMN image VARCHAR(255) NULL;
ALTER TABLE news ADD COLUMN is_members_only TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE news ADD COLUMN views INT NOT NULL DEFAULT 0;

-- 5) Feeds (import sources)
CREATE TABLE IF NOT EXISTS feeds (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(190) NOT NULL,
  url TEXT NOT NULL,
  category_id INT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  fetch_interval_minutes INT NOT NULL DEFAULT 60,
  last_fetched_at DATETIME NULL,
  created_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_feeds_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6) Imported items
CREATE TABLE IF NOT EXISTS news_import_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  news_id INT NOT NULL,
  feed_id INT NULL,
  item_hash CHAR(64) NOT NULL,
  item_link TEXT NULL,
  created_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_news_import_items_news (news_id),
  KEY idx_news_import_items_feed (feed_id),
  UNIQUE KEY uniq_news_import_items_hash (item_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7) Comments
CREATE TABLE IF NOT EXISTS comments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  news_id INT NOT NULL,
  user_id INT NULL,
  name VARCHAR(190) NULL,
  email VARCHAR(190) NULL,
  body LONGTEXT NOT NULL,
  parent_id BIGINT UNSIGNED NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'pending',
  score INT NOT NULL DEFAULT 0,
  ip VARCHAR(64) NULL,
  user_agent TEXT NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_comments_news (news_id),
  KEY idx_comments_parent (parent_id),
  KEY idx_comments_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS comment_votes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  comment_id BIGINT UNSIGNED NOT NULL,
  user_id INT NULL,
  ip VARCHAR(64) NULL,
  value TINYINT NOT NULL,
  created_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_comment_votes_comment (comment_id),
  KEY idx_comment_votes_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8) News notes (internal)
CREATE TABLE IF NOT EXISTS news_notes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  news_id INT NOT NULL,
  user_id INT NULL,
  note LONGTEXT NOT NULL,
  created_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_news_notes_news (news_id),
  KEY idx_news_notes_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9) News views / analytics
CREATE TABLE IF NOT EXISTS news_views (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  news_id INT NOT NULL,
  type VARCHAR(32) NOT NULL DEFAULT 'view',
  ip_address VARCHAR(64) NULL,
  user_agent TEXT NULL,
  created_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_news_views_news (news_id),
  KEY idx_news_views_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS analytics_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  page VARCHAR(64) NOT NULL,
  news_id INT NULL,
  source VARCHAR(64) NULL,
  referrer TEXT NULL,
  user_ip VARCHAR(64) NULL,
  user_agent TEXT NULL,
  os VARCHAR(64) NULL,
  browser VARCHAR(64) NULL,
  device VARCHAR(64) NULL,
  created_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_analytics_events_page (page),
  KEY idx_analytics_events_news (news_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10) News audit log (admin actions)
CREATE TABLE IF NOT EXISTS news_audit_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  news_id INT NOT NULL,
  user_id INT NULL,
  action VARCHAR(64) NOT NULL,
  payload LONGTEXT NULL,
  created_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_news_audit_log_news (news_id),
  KEY idx_news_audit_log_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11) Optional: DB sessions table (if app is configured to use it)
CREATE TABLE IF NOT EXISTS sessions (
  id VARCHAR(128) NOT NULL,
  user_id INT NULL,
  ip_address VARCHAR(64) NULL,
  user_agent TEXT NULL,
  data LONGTEXT NULL,
  last_activity INT NULL,
  PRIMARY KEY (id),
  KEY idx_sessions_user (user_id),
  KEY idx_sessions_last_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12) Tag meta (optional UX fields)
CREATE TABLE IF NOT EXISTS tag_meta (
  tag_id INT NOT NULL,
  intro TEXT NULL,
  cover_path VARCHAR(255) NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (tag_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
