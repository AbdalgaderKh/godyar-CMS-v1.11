Godyar - Fix 500 عند /ar/ /en/ /fr/

السبب الشائع عندما لا يظهر شيء في لوج PHP:
- الخطأ يحدث قبل وصول PHP (Rewrite/Redirect داخلي) أو
- الراوتر يتعامل مع REQUEST_URI بشكل يسبب تعارض/تكرار.

هذا الباتش يوفر ملفين:
1) includes/lang_prefix.php  => يلتقط بادئة اللغة ويزيلها من REQUEST_URI داخلياً (بدون Redirect).
2) includes/lang.php         => Loader نظيف يحدد GDY_LANG ويحمّل includes/i18n.php إن وُجد.

طريقة الدمج (مختصرة):
- ارفع الملفين إلى:
  /home/USER/public_html/includes/lang_prefix.php
  /home/USER/public_html/includes/lang.php

- ثم تأكد أن أعلى app.php أو index.php يحتوي:
  include __DIR__ . '/includes/lang_prefix.php';
  include __DIR__ . '/includes/lang.php';

مهم: لازم يكونوا قبل أي HTML/echo.

مرفق أيضاً .htaccess حسب آخر نسخة أرسلتها.
