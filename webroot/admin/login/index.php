<?php
declare(strict_types=1);

// Wrapper to support /admin/login when document root is webroot/.
// Delegates to the real admin login script in project root.
require '../../../admin/login.php';
