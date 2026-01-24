<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

// Optional: fast indexing helper
$fast = __DIR__ . '/includes/seo/fast_index.php';
if (is_file($fast)) {
    require_once $fast;
}

header('Content-Type: application/json; charset=utf-8');

$base = function_exists('gdy_base_url') ? (string)gdy_base_url() : (defined('GODYAR_BASE_URL') ? (string)GODYAR_BASE_URL : '');
$base = rtrim($base, '/');

$url = trim((string)($_GET['url'] ?? ''));
if ($url === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'missing url'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!preg_match('#^https?://#i', $url)) {
    $url = $base !== '' ? ($base . '/' . ltrim($url, '/')) : $url;
}

if (!function_exists('gdy_indexnow_submit')) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'indexnow not available'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $rf = new ReflectionFunction('gdy_indexnow_submit');
    $req = $rf->getNumberOfRequiredParameters();

    if ($req >= 2) {
        // Legacy signature: (PDO $pdo, array $urls)
        if (!isset($pdo) || !($pdo instanceof PDO)) {
            throw new RuntimeException('PDO not available for indexnow_submit');
        }
        $ok = (bool)gdy_indexnow_submit($pdo, [$url]);
    } else {
        // Newer signature: (array $urlList, ?string $baseOverride = null)
        $ok = (bool)gdy_indexnow_submit([$url]);
    }
echo json_encode(['ok' => $ok, 'url' => $url], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage(), 'url' => $url], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
