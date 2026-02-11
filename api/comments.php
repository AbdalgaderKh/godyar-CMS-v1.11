<?php
declare(strict_types=1);

// Legacy endpoint intentionally disabled.
// Use the newer comments plugin/router endpoints instead.

http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok' => false,
    'error' => 'This legacy endpoint has been disabled.',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
