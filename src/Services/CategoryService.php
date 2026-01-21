<?php
declare(strict_types=1);

namespace Godyar\Services;

// Compatibility wrapper:
$root = dirname(__DIR__, 2);
$target = $root . '/includes/classes/Services/CategoryService.php';
if (is_file($target)) {
    require_once $target;
}
