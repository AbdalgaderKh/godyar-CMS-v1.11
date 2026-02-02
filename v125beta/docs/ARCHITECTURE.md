# Architecture

## Request Lifecycle (مبسّط)
1) Router (`app.php` / `src/Core/Router.php`) يحدد المسار.
2) Controller يحضر البيانات (بدون إخراج HTML مباشر).
3) View يعرض HTML.
4) Middlewares/Guards:
   - admin guard
   - CSRF
   - Origin guard
   - Rate limiting

## Caching Layers
- Cache (File-based): settings/categories/tags
- List cache (قصير): search/category/tag
- Output cache (للزوار فقط)

## Auth & Sessions
- جلسة الأدمن منفصلة عن جلسة المستخدم العام.
- Rotation عند تغير الامتيازات.
- Fingerprint خفيف للأدمن (UA + IP prefix) متسامح مع proxies.
