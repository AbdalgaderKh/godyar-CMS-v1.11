<?php
declare(strict_types=1);

/**
 * Bridge file for legacy includes:
 * Some parts of the app still require /includes/auth.php (lowercase),
 * while the canonical implementation is /includes/Auth.php.
 */

require_once __DIR__ . '/Auth.php';
