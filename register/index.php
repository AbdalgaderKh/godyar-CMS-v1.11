<?php
declare(strict_types=1);

/**
 * Fallback for hosts where /register/ is treated as a physical directory request.
 * We intentionally include the canonical script to avoid routing ambiguity.
 */

$__target = dirname(__DIR__) . '/register.php';
if (is_file($__target)) {
    require $__target;
    exit;
}

http_response_code(500);
echo 'register.php not found.';
