<?php
declare(strict_types=1);

// Portable wrapper: support /admin/login/ without rewrite rules.
// Redirect to the real login endpoint so relative form actions work.

header('Location: ../login.php', true, 302);
return;
