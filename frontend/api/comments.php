<?php
declare(strict_types=1);

// Legacy endpoint removed (comments table deprecated).
// Use: /api/v1/comments.php

http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

echo json_encode([
    'ok' => false,
    'error' => 'gone',
    'message' => 'تم إيقاف هذا المسار نهائياً. استخدم /api/v1/comments.php.',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
