<?php
declare(strict_types=1);

// Compatibility wrapper: loads the unified header from views/partials.
// Also marks the request as "wrapped" so views avoid re-including header/footer.

if (!defined('GDY_TPL_WRAPPED')) {
    define('GDY_TPL_WRAPPED', true);
}

require_once __DIR__ . '/../views/partials/header.php';
