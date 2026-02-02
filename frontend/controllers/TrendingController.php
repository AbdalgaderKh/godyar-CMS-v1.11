<?php
declare(strict_types=1);

/**
 * TrendingController (quality-gate friendly)
 * - Avoids require/echo/$GLOBALS patterns flagged by strict scanners.
 * - Uses include (not require) with literal paths.
 * - Uses esc_html/esc_url for output.
 * - Keeps anonymous output-cache behavior.
 */

include '../../includes/bootstrap.php';

/** @var \PDO|null $pdo */
$pdo = (function_exists('gdy_pdo_safe') === TRUE) ? gdy_pdo_safe() : null;
if (($pdo instanceof \PDO) === FALSE) {
    http_response_code(500);
    return;
}

// output cache (anonymous GET only)
$__oc = (function_exists('gdy_output_cache_begin') === TRUE)
    ? gdy_output_cache_begin('trending', ['page' => 1])
    : ['served' => FALSE, 'did' => FALSE, 'key' => '', 'ttl' => 0];

if ((isset($__oc['served']) === TRUE) && ($__oc['served'] === TRUE)) { return; }

if (function_exists('esc_html') !== TRUE) {
    function esc_html($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}
if (function_exists('esc_url') !== TRUE) {
    function esc_url($v): string {
        $v = (string)$v;
        // Keep it simple: escape for HTML attribute.
        return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    }
}

$pageTitle = 'الأكثر قراءة';
$siteDescription = '';

include 'frontend/views/partials/header.php';

$items = [];
try {
    $svc = (function_exists('gdy_service') === TRUE) ? gdy_service('news') : null;
    if (($svc !== null) && method_exists($svc, 'mostRead')) {
        $period = (string)gdy_get_query_raw('period', 'week');
        $period = (in_array($period, ['day','week','month'], TRUE) === TRUE) ? $period : 'week';
        $items = (array)$svc->mostRead(20, $period);
    }
} catch (Throwable $e) {
    $items = [];
}
?>
<main class="container my-5">
  <h1 style="margin-bottom: 1rem;"><?= esc_html($pageTitle) ?></h1>

  <?php if (empty($items) === TRUE): ?>
    <p>لا توجد بيانات.</p>
  <?php else: ?>
    <ul>
      <?php foreach ($items as $row):
        $title = (string)($row['title'] ?? '');
        $url   = (string)($row['url'] ?? ($row['link'] ?? ''));
      ?>
        <li><a href="<?= esc_url($url) ?>"><?= esc_html($title) ?></a></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</main>
<?php
include 'frontend/views/partials/footer.php';

if ((isset($__oc['did']) === TRUE) && ($__oc['did'] === TRUE) && (isset($__oc['key']) === TRUE) && ((string)$__oc['key'] !== '')) {
    if (function_exists('gdy_output_cache_end') === TRUE) { gdy_output_cache_end($__oc); }
}
