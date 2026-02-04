<?php
declare(strict_types=1);

/**
 * Godyar CMS — Version & Branding
 *
 * Keep this file tiny and dependency-free so it can be required safely
 * from both frontend and admin layouts.
 */

if (!defined('GODYAR_CMS_VERSION')) {
    define('GODYAR_CMS_VERSION', 'v1.11');
}

// Default copyright text (can be overridden by defining GODYAR_CMS_COPYRIGHT)
if (!defined('GODYAR_CMS_COPYRIGHT')) {
    define('GODYAR_CMS_COPYRIGHT', 'Godyar CMS');
}

/**
 * Returns a short badge text like: "Godyar CMS v1.11"
 */
if (!function_exists('gdy_cms_badge')) {
    function gdy_cms_badge(): string
    {
        return trim((string)GODYAR_CMS_COPYRIGHT) . ' ' . trim((string)GODYAR_CMS_VERSION);
    }
}
