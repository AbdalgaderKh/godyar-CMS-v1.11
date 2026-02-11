<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

// Optional: fast indexing helper
$fast = __DIR__ . '/includes/seo/fast_index.php';
if (is_file($fast)) {
    require_once $fast;
}

header('Content-Type: application/json; charset=utf-8');

// Some deployments expose this endpoint directly; avoid relying on implicit globals.
$pdo = function_exists('gdy_pdo_safe') ? gdy_pdo_safe() : null;

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

// Safety: allow only same-host URLs to prevent abuse.
try {
    $u = parse_url($url) ?: [];
    $host = strtolower((string)($u['host'] ?? ''));

    $allowedHost = '';
    if ($base !== '') {
        $b = parse_url($base) ?: [];
        $allowedHost = strtolower((string)($b['host'] ?? ''));
    }
    if ($allowedHost === '' && !empty($_SERVER['HTTP_HOST'])) {
        $allowedHost = strtolower((string)$_SERVER['HTTP_HOST']);
    }

    if ($allowedHost !== '' && $host !== '' && $host !== $allowedHost) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'host not allowed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
} catch (Exception $e) {
    // ignore and continue
}

if (!function_exists('gdy_indexnow_submit')) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'indexnow not available'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $rf = new ReflectionFunction('gdy_indexnow_submit');
    $params = $rf->getParameters();
    $firstType = isset($params[0]) ? $params[0]->getType() : null;

    $legacy = ($firstType instanceof ReflectionNamedType && $firstType->getName() === 'PDO');
    if ($legacy) {
        // Legacy signature: (PDO $pdo, array $urls)
        if (!($pdo instanceof PDO)) {
            throw new RuntimeException('PDO not available for indexnow_submit');
        }
        $ok = (bool)gdy_indexnow_submit($pdo, [$url]);
    } else {
        // Newer signature: (array $urlList, ?string $baseOverride = null)
        $ok = (bool)gdy_indexnow_submit([$url]);
    }

    echo json_encode(['ok' => $ok, 'url' => $url], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage(), 'url' => $url], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
