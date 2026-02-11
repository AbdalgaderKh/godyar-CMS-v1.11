CREATE TABLE IF NOT EXISTS opinion_authors (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(190) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  avatar VARCHAR(255) NULL,
  specialization VARCHAR(190) NULL,
  bio TEXT NULL,
  page_title VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  display_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_opinion_authors_slug (slug),
  KEY idx_is_active (is_active),
  KEY idx_display_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
