<?php
declare(strict_types=1);

/**
 * TrendingController (portable, quality-clean)
 * - Avoids direct superglobals for static analyzers
 * - Avoids dynamic file includes / file checks
 * - Keeps output-cache behavior (anonymous GET only)
 */

require '../../includes/bootstrap.php';

/** @var \PDO|null $pdo */
$pdo = $pdo ?? ($GLOBALS['pdo'] ?? null);

// output cache (anonymous GET only)
$__oc = (function_exists('gdy_output_cache_begin') === TRUE)
    ? gdy_output_cache_begin('trending', ['page' => 1])
    : ['served' => FALSE, 'did' => FALSE, 'key' => '', 'ttl' => 0];

if ((isset($__oc['served']) === TRUE) && ($__oc['served'] === TRUE)) { return; }

$pageTitle = 'الأكثر قراءة';
$siteDescription = '';
require 'frontend/views/partials/header.php';

$items = [];
try {
    $svc = (function_exists('gdy_service') === TRUE) ? gdy_service('news') : null;
    if ($svc && method_exists($svc, 'mostRead')) {
        $period = (string)gdy_get_query_raw('period', 'week');
        $period = in_array($period, ['day','week','month'], true) ? $period : 'week';
        $items = (array)$svc->mostRead(20, $period);
    }
} catch (Throwable $e) {
    $items = [];
}

echo '<main class="container my-5">';
echo '<h1 style="margin-bottom: 1rem;">' . h($pageTitle) . '</h1>';

if (empty($items) === TRUE) {
    echo '<p>لا توجد بيانات.</p>';
} else {
    echo '<ul>';
    foreach ($items as $row) {
        $title = (string)($row['title'] ?? '');
        $url = (string)($row['url'] ?? '#');
        echo '<li><a href="' . h($url) . '">' . h($title) . '</a></li>';
    }
    echo '</ul>';
}

echo '</main>';

require 'frontend/views/partials/footer.php';

gdy_output_cache_end($__oc);
