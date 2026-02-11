-- 2025_12_13_news_pro_columns.sql
-- Adds SEO/workflow columns to `news` (safe to rerun; duplicates are ignored by installer).

ALTER TABLE news ADD COLUMN seo_title VARCHAR(255) NULL;
ALTER TABLE news ADD COLUMN seo_description TEXT NULL;
ALTER TABLE news ADD COLUMN seo_keywords TEXT NULL;
ALTER TABLE news ADD COLUMN publish_at DATETIME NULL;
ALTER TABLE news ADD COLUMN unpublish_at DATETIME NULL;
ALTER TABLE news ADD COLUMN is_breaking TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE news ADD COLUMN view_count INT NOT NULL DEFAULT 0;
