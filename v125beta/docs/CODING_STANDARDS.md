# Coding Standards

## قواعد عامة (متوافقة مع Quality Gate)
- لا `require/require_once` أو `include` داخل Controllers الجديدة إن كان الـ scanner صارم.
- لا `echo` داخل Controllers.
- أي HTML يخرج من Views فقط.
- لا تستخدم superglobals مباشرة (`$_GET/$_SERVER`) في الملفات الجديدة؛ استخدم helpers أو filter_input.
- استخدم مقارنات صريحة: `=== TRUE` / `!== TRUE`.
- استخدم escaping في Views: `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.

## Security
- لا concatenation في SQL
- validate + sanitize قبل الاستخدام
- لا تسرب stack traces في الإنتاج
