<?php
declare(strict_types=1);

require_once __DIR__ . '/../_admin_guard.php';

// admin/system/index.php was previously corrupted by an incomplete merge.
// Keep index stable by redirecting to the dedicated health page.
header('Location: health.php');
exit;
