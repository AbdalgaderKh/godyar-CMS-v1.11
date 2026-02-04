<?php
declare(strict_types=1);

// Legacy path bridge
// Some legacy admin classes require "includes/auth.php" (lowercase) while
// the project uses "includes/Auth.php". Linux filesystems are case-sensitive.

require_once __DIR__ . '/Auth.php';
