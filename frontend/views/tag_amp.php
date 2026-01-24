?>
<!doctype html><html ⚡ lang="ar" dir="rtl"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,minimum-scale=1,initial-scale=1">
$baseUrl = function_exists('base_url')
    ? rtrim((string)base_url(), '/')
    : (defined('BASE_URL') ? rtrim((string)BASE_URL, '/') : '');
$items = $items ?? [];
?>
<!doctype html><html ⚡ lang="ar" dir="rtl"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,minimum-scale=1,initial-scale=1">
<?php
/** @var array $items */
$items = $items ?? [];
$baseUrl = function_exists('base_url')
    ? rtrim((string)base_url(), '/')
    : (defined('BASE_URL') ? rtrim((string)BASE_URL, '/') : '');
$baseUrl = function_exists('base_url')
    ? rtrim((string)base_url(), '/')
    : (defined('BASE_URL') ? rtrim((string)BASE_URL, '/') : '');
?>
<!doctype html><html ⚡ lang="ar" dir="rtl"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,minimum-scale=1,initial-scale=1">
<title><?php echo htmlspecialchars($tag['name'] ?? ''); ?> | AMP</title>
<link rel="canonical" href="<?php echo htmlspecialchars(rtrim($baseUrl,'/') . '/tag/' . (string)($tag['slug'] ?? '')); ?>">
<style amp-boilerplate>body{visibility:hidden}</style><style amp-custom>body{font-family:'Cairo',system-ui,sans-serif;padding:12px}.grid{display:grid;grid-template-columns:1fr;gap:8px}</style>
</head><body>
<h1><?php echo htmlspecialchars($tag['name'] ?? ''); ?></h1>
<div class="grid">
<?php foreach ($items as $n): ?>
	<a href="<?php echo htmlspecialchars(rtrim($baseUrl,'/') . '/news/id/' . (int)($n['id'] ?? 0) . '/amp'); ?>">
	  <amp-img src="<?php echo htmlspecialchars(rtrim($baseUrl,'/') . '/img.php?src=' . rawurlencode((string)($n['featured_image'] ?? '')) . '&w=800'); ?>" width="800" height="450" layout="responsive"></amp-img>
    <div><?php echo htmlspecialchars($n['title']); ?></div>
  </a>
<?php endforeach; ?>
</div>
</body></html>
