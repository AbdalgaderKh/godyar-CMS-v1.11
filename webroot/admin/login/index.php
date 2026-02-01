<?php
declare(strict_types=1);

// Portable wrapper: support /admin/login/ when document root is webroot/.
// Redirect to /admin/login.php (served by webroot/admin/login.php).

header('Location: ../login.php', true, 302);
return;
