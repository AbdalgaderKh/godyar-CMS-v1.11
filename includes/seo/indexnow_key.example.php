<?php
declare(strict_types=1);

/**
 * Example IndexNow configuration.
 *
 * IMPORTANT:
 * - Do NOT commit real keys to the repository.
 * - Preferred: store the key in the database setting `seo.indexnow_key`.
 * - Alternative: set env variable `GDY_INDEXNOW_KEY`.
 *
 * If you still want a file-based key (not recommended), you can copy this file
 * to `includes/seo/indexnow_key.php` and set your own key and key file name.
 */

if (!defined('GDY_INDEXNOW_KEY')) {
    define('GDY_INDEXNOW_KEY', 'REPLACE_WITH_YOUR_KEY');
}
if (!defined('GDY_INDEXNOW_KEY_FILE')) {
    define('GDY_INDEXNOW_KEY_FILE', 'REPLACE_WITH_YOUR_KEY.txt');
}
