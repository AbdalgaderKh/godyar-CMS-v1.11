<?php
declare(strict_types=1);

/**
 * Apply basic security headers in a hosting-portable way.
 * Can be disabled by setting environment variable: GDY_SECURITY_HEADERS=0
 */
function gdy_apply_security_headers(): void
{
    if (headers_sent()) { return; }

    $enabled = getenv('GDY_SECURITY_HEADERS');
    if ($enabled !== false && trim((string)$enabled) === '0') {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

    // Conservative CSP (may be adjusted per theme/plugins).
    // Disable via GDY_CSP=0 if any integration breaks.
    $cspEnabled = getenv('GDY_CSP');
    if ($cspEnabled !== false && trim((string)$cspEnabled) === '0') {
        return;
    }

    // Keep it permissive enough for legacy inline styles/scripts.
    header("Content-Security-Policy: default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline' https:; script-src 'self' 'unsafe-inline' https:; connect-src 'self' https:; ");
}
