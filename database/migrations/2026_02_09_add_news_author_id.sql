-- Best-effort: add author_id for newer admin filters.
-- If your install already uses `user_id`, the runtime code will fall back automatically.
ALTER TABLE news ADD COLUMN author_id INT NULL;
