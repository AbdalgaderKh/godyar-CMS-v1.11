-- 2026_01_04_admin_notifications.sql
CREATE TABLE IF NOT EXISTS admin_notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT NULL,
  title VARCHAR(255) NOT NULL,
  body LONGTEXT NULL,
  link TEXT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_admin_notifications_user (user_id),
  KEY idx_admin_notifications_read (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
