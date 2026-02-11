-- 2025_12_25_news_translations.sql
-- Multi-language support for news content.

CREATE TABLE IF NOT EXISTS news_translations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  news_id INT NOT NULL,
  lang VARCHAR(8) NOT NULL,
  title VARCHAR(255) NULL,
  content LONGTEXT NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_news_translations (news_id, lang),
  KEY idx_news_translations_news (news_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
