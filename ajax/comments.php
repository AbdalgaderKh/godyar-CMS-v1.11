<?php
declare(strict_types=1);

// Legacy endpoint removed (comments table deprecated).
// Use: /frontend/ajax/comments.php (AJAX) or /api/v1/comments.php (API)

http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

echo json_encode([
    'ok' => false,
    'error' => 'gone',
    'message' => 'هذا المسار قديم وتمت إزالته نهائياً. استخدم /frontend/ajax/comments.php أو /api/v1/comments.php.',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
