# نشر GODYAR CMS على بيئات مختلفة (Apache / Nginx / IIS)

هذه الإرشادات تضيف طبقات حماية **بدون افتراض** نوع الخادم. الهدف:
- منع الوصول للملفات الحساسة (storage, config, logs)
- منع تنفيذ PHP داخل مجلدات الرفع (uploads)
- تفعيل ترويسات أمان أساسية
- المحافظة على التوافق مع الاستضافات المشتركة

## 1) أفضل ممارسة: Webroot منفصل
إن أمكن اجعل الويب يشير إلى مجلد `public/` فقط (DocumentRoot).
- الكود والتخزين يبقى خارج الوصول المباشر.
- `storage/` سيكون غير قابل للوصول افتراضياً.

إن لم تستطع (استضافة مشتركة): استخدم ملفات الحماية ضمن هذا المجلد + قواعد الخادم أدناه.

## 2) قواعد منع تنفيذ PHP داخل uploads
طبّق القواعد المناسبة لخادمك من:
- `docs/deployment/server-config/apache-uploads.htaccess`
- `docs/deployment/server-config/nginx-uploads.conf`
- `docs/deployment/server-config/iis-uploads.web.config`

> إذا كنت تستخدم CDN أو proxy، تأكد من أن uploads لا يُخدم كـ PHP بأي شكل.

## 3) حماية storage (logs/cache/queue/uploads)
طبّق القواعد من:
- `docs/deployment/server-config/apache-storage.htaccess`
- `docs/deployment/server-config/nginx-storage.conf`
- `docs/deployment/server-config/iis-storage.web.config`

## 4) ترويسات أمان (اختيارية لكن مستحبة)
استخدم:
- `SECURITY_HEADERS_SNIPPET.htaccess` (Apache)
أو القواعد المكافئة في Nginx داخل `nginx-security-headers.conf`.

## 5) جلسات PHP (Session Hardening)
تم تقوية الجلسات داخل الكود عبر `gdy_session_start()`، لكن يُفضّل أيضاً ضبط ini:
- راجع `docs/deployment/php-user-ini.example`
- في الاستضافات المشتركة ضعها كملف `.user.ini` داخل الجذر.

## 6) قائمة تحقق سريعة
- [ ] DocumentRoot = public/ (إن أمكن)
- [ ] منع تنفيذ PHP في uploads
- [ ] منع الوصول لـ storage و config و logs
- [ ] تعطيل عرض الأخطاء في الإنتاج (display_errors=0)
- [ ] تفعيل HTTPS + HSTS (إن كان مناسباً)
- [ ] صلاحيات الملفات: 644 والـ folders: 755 (ومجلدات الكتابة 775)
