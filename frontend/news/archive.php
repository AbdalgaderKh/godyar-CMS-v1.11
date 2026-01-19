<?php
declare(strict_types=1);

// Legacy route: /frontend/news/archive.php
// Redirect to the modern route handled by app.php/router.

require_once __DIR__ . '/../includes/bootstrap.php';

$to = base_url('/archive');
if (!headers_sent()) {
    header('Location: ' . $to, true, 301);
    exit;
}

// Fallback when headers already sent
?><!doctype html>
<meta charset="utf-8">
<meta http-equiv="refresh" content="0;url=<?= htmlspecialchars($to, ENT_QUOTES, 'UTF-8') ?>">
<a href="<?= htmlspecialchars($to, ENT_QUOTES, 'UTF-8') ?>">Continue</a>
