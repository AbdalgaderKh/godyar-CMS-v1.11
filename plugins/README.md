# نظام الإضافات (Plugins)

## المعيار الجديد
- كل إضافة يجب أن تكون داخل مجلد: `plugins/<slug>/`
- يجب أن يحتوي المجلد على:
  - `plugin.json` (إلزامي)
  - `Plugin.php` (إلزامي) ويُرجع كائنًا يطبّق `GodyarPluginInterface`

## plugin.json (مثال)
```json
{
  "name": "My Plugin",
  "slug": "my_plugin",
  "enabled": true,
  "version": "1.0.0",
  "description": "...",
  "schema_version": 1
}
```

## ملاحظات أمنية
- لا يتم تحميل أي إضافة بدون `plugin.json`.
- يتم تجاهل المجلدات التي ليست slugs آمنة أو التي هي symlinks.
