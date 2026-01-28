# Stage 11 — Remove legacy `comments` table + endpoints (news_comments only)

## الهدف
إيقاف دعم جدول `comments` نهائيًا وتوحيد نظام التعليقات على جدول `news_comments` فقط.

## ما تم تنفيذه
### 1) تعطيل/إزالة endpoints القديمة
- `ajax/comments.php`:
  - تم تحويله إلى Stub يعيد **HTTP 410 (Gone)** برسالة واضحة.
  - لا يوجد أي تعامل مع DB أو جدول `comments`.
- `frontend/api/comments.php`:
  - تم تحويله إلى Stub يعيد **HTTP 410 (Gone)**.

المسارات المعتمدة الآن:
- الواجهة: `frontend/ajax/comments.php`
- API: `api/v1/comments.php` و `api/comments.php`

### 2) إزالة مكونات legacy التي كانت تُنشئ جدول `comments`
- حذف migrations الخاصة بجدول `comments`:
  - `database/migrations/2025_12_13_comments.sql`
  - `database/migrations/2025_12_27_comments_replies.sql`
  - وكذلك نسخ PostgreSQL ونسخ لوحة الإدارة ضمن `admin/db/migrations/**`.
- إزالة إنشاء جدول `comments` من ملف التثبيت:
  - `install/sql/schema_core.sql`

### 3) إزالة Plugin التعليقات القديم المعتمد على جدول `comments`
- حذف:
  - `plugins/comments/`
  - `admin/plugins/comments/`
- إزالة الرابط الثابت لهذا الـ Plugin من قائمة لوحة التحكم:
  - `admin/layout/sidebar.php`

### 4) تحديث صفحات الإدارة التي كانت تعتمد على جدول `comments`
- لوحة التحكم (Dashboard):
  - `admin/index.php` أصبح يحسب الإحصاءات من `news_comments`.
- التصدير:
  - `admin/export.php` أصبح يصدّر من `news_comments`.
- تحليلات Heatmap:
  - `admin/analytics/heatmap.php` أصبح يعتمد على `news_comments` بدل `comments`.
- صفحة إدارة التعليقات القديمة:
  - `admin/comments/index.php` أصبحت Redirect إلى `admin/comments.php`.
- البحث:
  - `admin/search/index.php` تم قصر البحث على `news_comments` فقط.

## ملاحظات تشغيل
- إذا كان لديك بيانات تاريخية داخل جدول `comments` في قواعد بيانات قديمة:
  - لم يتم حذفها تلقائيًا.
  - يوصى لاحقًا بعمل **Migration نقل بيانات** من `comments` إلى `news_comments` إذا رغبت بالحفاظ عليها.

