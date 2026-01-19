<?php
declare(strict_types=1);

// Legacy route: /frontend/news/single_news.php
// Historically this file served a single article. The project now uses:
//   /news/id/{id}  (or slug routes handled by app.php)

require_once __DIR__ . '/../includes/bootstrap.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$to = ($id > 0) ? base_url('/news/id/' . $id) : base_url('/');

if (!headers_sent()) {
    header('Location: ' . $to, true, 301);
    exit;
}

?><!doctype html>
<meta charset="utf-8">
<meta http-equiv="refresh" content="0;url=<?= htmlspecialchars($to, ENT_QUOTES, 'UTF-8') ?>">
<a href="<?= htmlspecialchars($to, ENT_QUOTES, 'UTF-8') ?>">Continue</a>
