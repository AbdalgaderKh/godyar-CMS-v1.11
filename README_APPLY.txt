Hotfix: Article cover image fallback (featured_image vs image_path)

Problem:
- News page (/news/id/1) shows broken image even though DB has image_path="uploads/news/...".

Root cause:
- frontend/views/news_single_legacy.php was only reading $post['featured_image'] (or image), ignoring image_path.

Fix:
- Use fallback: featured_image -> image_path -> image.

Install (cPanel):
1) Backup file:
   public_html/frontend/views/news_single_legacy.php -> news_single_legacy.php.bak
2) Upload this zip to public_html/
3) Extract with overwrite
4) Test:
   https://godyar.org/news/id/1
