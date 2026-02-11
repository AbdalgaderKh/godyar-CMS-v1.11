# Godyar CMS v1.25 Roadmap

> هدف v1.25: تحويل النظام من “Production-ready” إلى “قابل للتوسع والمساهمات” مع تعزيز أمن لوحة التحكم وتجربة المطور.

## Milestone 1 — Security+ (2FA & Admin Hardening) ✅ priority: P0
**الهدف:** حماية لوحة التحكم ضد اختراق الحسابات حتى مع تسريب كلمة المرور.

**المخرجات:**
- TOTP 2FA للأدوار الإدارية (admin/editor/writer/author/super_admin)
- Backup codes (one-time) + regenerate
- Hardening للجلسة (rotation + fingerprint الحالي)
- Rate-limit محسّن لخطوة 2FA
- توثيق security + runbooks

## Milestone 2 — Developer Experience (DX) ✅ priority: P1
**المخرجات:**
- توثيق رسمي (install/architecture/security/contributing/coding-standards)
- قوالب Issues/PR في GitHub
- Plugin SDK (manifest + hooks) وتوثيق واضح
- سكربتات dev (lint, tests, cache clear)

## Milestone 3 — Performance & Scale ✅ priority: P2
**المخرجات:**
- Cache drivers (file/apcu/redis) مع fallback
- Profiling بسيط للاستعلامات
- تحسينات pagination والبحث

## Milestone 4 — Cleanup & Consistency ✅ priority: P2
**المخرجات:**
- توحيد نمط Controllers/Views
- إزالة legacy helpers تدريجيًا
- تحسين naming وstructure

## Definition of Done
- Quality Gate: 0 issues
- Security regression tests تمر
- Backward compatibility للتركيب على subfolder والاستضافات المشتركة
