-- 2026_01_04_admin_saved_filters.sql
CREATE TABLE IF NOT EXISTS admin_saved_filters (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT NULL,
  page_key VARCHAR(64) NOT NULL,
  name VARCHAR(190) NOT NULL,
  querystring TEXT NOT NULL,
  created_at DATETIME NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_admin_saved_filters_user (user_id),
  KEY idx_admin_saved_filters_page (page_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
