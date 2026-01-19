# باتش تحديث التوافق (Safari/Firefox) — Godyar CMS

هذا الباتش يعالج التحذيرات التالية:
- Safari: backdrop-filter يحتاج -webkit-backdrop-filter
- Safari: scrollbar-width غير مدعوم ويحتاج WebKit fallback
- Firefox: meta theme-color (تحذير معلوماتي) — نضيف metas مكملة

## 1) رفع ملف CSS
ارفع الملف التالي إلى:
`public_html/assets/css/compat.css`

(من cPanel → File Manager → public_html → assets → css)

## 2) ربط الملف داخل الهيدر
افتح الملف:
`public_html/frontend/views/partials/header.php`

وأضف هذا السطر **بعد ملفات CSS الأساسية مباشرة** (وقبل </head>):

```html
<link rel="stylesheet" href="/assets/css/compat.css?v=1">
```

## 3) إضافة metas (اختياري لكنها مفيدة)
داخل نفس ملف الهيدر (داخل <head>) أضف:

```html
<meta name="color-scheme" content="light dark">
<meta name="msapplication-TileColor" content="#0b1220">
```

> لا تحذف meta theme-color. التحذير في Firefox لا يعني وجود مشكلة تشغيل.

## 4) تحذير CSP (eval) — يحتاج تعديل JS (ليس ضمن هذا الباتش)
إذا ظهرت رسالة أن CSP يمنع eval:
- ابحث في ملفات JS عن: `eval(` أو `new Function` أو `setTimeout("` أو `setInterval("`
- واستبدلها بصيغ آمنة (callbacks / arrow functions)

### مثال
بدل:
`setTimeout("doSomething()", 1000);`

اكتب:
`setTimeout(doSomething, 1000);`

## 5) اختبار سريع
- افتح الصفحة: https://godyar.org/ar/?source=pwa
- ثم Ctrl+F5
- راجع Console: يجب أن تختفي تحذيرات backdrop-filter و scrollbar-width أو تقل بشكل واضح.
