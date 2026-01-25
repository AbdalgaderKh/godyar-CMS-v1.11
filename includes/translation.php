<?php
declare(strict_types=1);

/**
 * Translation layer.
 *
 * NOTE: In this build, automatic translation is disabled.
 * This file provides safe stubs and optional helpers so the rest of the CMS
 * can call translation-related functions without causing parse/runtime errors.
 */

if (!function_exists('gdy_translation_enabled')) {
    function gdy_translation_enabled(): bool
    {
        // Translation feature is disabled.
        return false;
    }
}

if (!function_exists('gdy_ensure_news_translations_table')) {
    /**
     * Ensure translations table exists.
     *
     * In this build the translation feature is disabled; this is a safe no-op
     * to avoid fatals in optional CRON scripts.
     */
    function gdy_ensure_news_translations_table(?PDO $pdo = null): void
    {
        // no-op
    }
}

if (!function_exists('gdy_translation_auto_on_view')) {
    function gdy_translation_auto_on_view(): bool
    {
        // Auto-translate on view is disabled.
        return false;
    }
}

if (!function_exists('gdy_get_news_translation')) {
    function gdy_get_news_translation(PDO $pdo, int $newsId, string $lang): ?array
    {
        // Disabled: always fall back to Arabic/base content.
        return null;
    }
}

if (!function_exists('gdy_save_news_translation')) {
    function gdy_save_news_translation(PDO $pdo, int $newsId, string $lang, array $data): bool
    {
        // Disabled: do not store translations.
        return false;
    }
}

if (!function_exists('gdy_translate_and_store_news')) {
    function gdy_translate_and_store_news(PDO $pdo, int $newsId, string $lang): bool
    {
        // Disabled.
        return false;
    }
}

if (!function_exists('gdy_news_field')) {
    /**
     * Return the requested field from the base (Arabic) row.
     *
     * @param array $newsRow Row from `news` table.
     * @param string $field One of: title|excerpt|content|seo_title|seo_description
     */
    function gdy_news_field(PDO $pdo, array $newsRow, string $field): string
    {
        return (string)($newsRow[$field] ?? '');
    }
}
