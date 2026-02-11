-- 2026_01_05_admin_saved_filters_default.sql
-- Seed a couple of safe defaults (won't fail if already present due to non-unique constraints on purpose).

INSERT INTO admin_saved_filters (user_id, page_key, name, querystring, created_at, is_default)
SELECT NULL, 'news', 'الكل', '', NOW(), 1
WHERE NOT EXISTS (SELECT 1 FROM admin_saved_filters WHERE page_key='news' AND is_default=1);

INSERT INTO admin_saved_filters (user_id, page_key, name, querystring, created_at, is_default)
SELECT NULL, 'comments', 'بانتظار المراجعة', 'status=pending', NOW(), 1
WHERE NOT EXISTS (SELECT 1 FROM admin_saved_filters WHERE page_key='comments' AND is_default=1);
