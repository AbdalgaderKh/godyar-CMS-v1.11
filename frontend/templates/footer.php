<?php
declare(strict_types=1);

// Compatibility wrapper: loads the unified footer from views/partials.

if (!defined('GDY_TPL_WRAPPED')) {
    define('GDY_TPL_WRAPPED', true);
}

require_once __DIR__ . '/../views/partials/footer.php';
