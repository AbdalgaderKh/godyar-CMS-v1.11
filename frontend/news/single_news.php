<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Direct Single News Loader
|--------------------------------------------------------------------------
| تم إلغاء التحويل 301
| سيتم تحميل show.php مباشرة
*/

require_once __DIR__ . '/../includes/bootstrap.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    http_response_code(404);
    exit('Article not found');
}

/*
|--------------------------------------------------------------------------
| Load the new show.php
|--------------------------------------------------------------------------
*/

$showFile = __DIR__ . '/show.php';

if (is_file($showFile)) {
    require $showFile;
    exit;
}

http_response_code(500);
exit('Show file not found');
