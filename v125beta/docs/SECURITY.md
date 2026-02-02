# Security

## الإبلاغ عن ثغرات
- يرجى فتح Issue بعنوان `[SECURITY]` أو إرسال التفاصيل بشكل خاص للإدارة.
- لا تنشر PoC علنًا قبل الإصلاح.

## أهم الضوابط
- SQLi: Prepared Statements
- XSS: Output encoding في Views
- CSRF: tokens + Origin guard
- Sessions: HttpOnly/Secure + rotation + strict settings
- Uploads: allow-list + MIME + random names

## 2FA (TOTP) للأدمن
- يتم تفعيل 2FA من: `/admin/security/2fa.php`
- عند تفعيل 2FA، يتم إنشاء:
  - secret
  - Backup codes (مرة واحدة)
- التحقق أثناء الدخول يتم عبر: `/admin/security/2fa_verify.php`
- Backup code صالح مرة واحدة ويتم “استهلاكه” بعد الاستخدام.

## Incident Response (مختصر)
1) تعطيل الدخول الإداري مؤقتًا (إن لزم).
2) تدوير كلمات المرور/الجلسات.
3) مراجعة `storage/logs/security.log`.
4) تحديث النسخة وإعادة فحص deploy_check.
