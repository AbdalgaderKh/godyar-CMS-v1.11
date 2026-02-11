Godyar Patch v35 - strict_types / BOM fixer

سبب الخطأ:
PHP يعطي Fatal: strict_types declaration must be the very first statement
إذا كان الملف يحتوي BOM أو أي محارف/مسافات قبل <?php أو قبل declare.

المحتوى:
1) fix_strict_types_bom.php  (سكربت CLI يصلح BOM + يضع declare بعد <?php مباشرة)
2) admin/layout/sidebar.php  (نسخة نظيفة مع declare على نفس سطر <?php)

طريقة التطبيق:
- ارفع محتويات هذا الباتش فوق موقعك (replace).
- ثم شغل السكربت مرة واحدة عبر SSH:
  php /home/USER/public_html/fix_strict_types_bom.php \
    /home/USER/public_html/includes/auth.php \
    /home/USER/public_html/admin/layout/sidebar.php

- بعد التأكد احذف:
  fix_strict_types_bom.php

ملاحظة:
السكربت ينشئ نسخة احتياطية لكل ملف قبل التعديل (امتداد .bak.YYYYmmdd_HHMMSS)
