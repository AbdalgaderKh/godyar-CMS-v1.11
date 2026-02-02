<?php
defined('GDY') or exit;

/**
 * Fast indexing helpers (IndexNow).
 *
 * This file originally contained duplicated / interleaved blocks. It has been normalized
 * to a single implementation to avoid syntax and runtime issues.
 */

if (!function_exists('gdy_indexnow_key')) {
    function gdy_indexnow_key(): string
    {
        // From settings table (preferred)
        $k = '';
        if (isset($GLOBALS['site_settings']) && is_array($GLOBALS['site_settings'])) {
            $k = (string)($GLOBALS['site_settings']['seo.indexnow_key'] ?? '');
        }
        $k = trim($k);
        if ($k !== '') {
            return $k;
        }

        // Fallback: environment variable (do NOT commit real keys to repo)
        $env = getenv('GDY_INDEXNOW_KEY');
        if (is_string($env) && trim($env) !== '') {
            return trim($env);
        }

        // No bundled key in repository.
        return '';
    }
}

if (!function_exists('gdy_indexnow_submit')) {
    /**
     * Submit URLs to IndexNow (Bing and compatible engines).
     *
     * @param array       $urlList       Absolute public URLs.
     * @param string|null $baseOverride  Optional base URL override (e.g. https://example.com).
     */
    function gdy_indexnow_submit(array $urlList, ?string $baseOverride = null): bool
    {
        $key = trim(gdy_indexnow_key());
        if ($key === '') return false;

        $base = $baseOverride;
        if ($base === null) {
            if (function_exists('gdy_base_url')) {
                $base = (string)gdy_base_url();
            } elseif (function_exists('base_url')) {
                $base = (string)base_url();
            } else {
                $base = '';
            }
        }
        $base = rtrim((string)$base, '/');

        $host = (string)parse_url($base, PHP_URL_HOST);
        if ($host === '') return false;

        // Normalize URL list
        $urlList = array_values(array_unique(array_filter(array_map(
            static fn($u) => is_string($u) ? trim($u) : '',
            $urlList
        ))));
        if (!$urlList) return false;

        $payload = json_encode([
            'host' => $host,
            'key' => $key,
            'keyLocation' => $base . '/' . $key . '.txt',
            'urlList' => $urlList,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (!is_string($payload) || $payload === '') return false;

        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content' => $payload,
                'timeout' => 5,
            ],
        ]);

        // Prefer safe wrappers when available.
        if (function_exists('gdy_file_get_contents')) {
            $res = gdy_file_get_contents('https://www.bing.com/indexnow', false, $ctx);
        } else {
            $res = @file_get_contents('https://www.bing.com/indexnow', false, $ctx);
        }

        return $res !== false;
    }
}
