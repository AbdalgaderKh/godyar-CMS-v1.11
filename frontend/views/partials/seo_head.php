<?php
$seo_title = $seo_title ?? ($page_title ?? ($site_name ?? ''));
$seo_description = $seo_description ?? '';
$seo_image = $seo_image ?? '';
$canonical = $canonical ?? ($_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
?>
<title><?php echo htmlspecialchars($seo_title); ?></title>
<meta name="description" content="<?php echo htmlspecialchars($seo_description); ?>">
<link rel="canonical" href="<?php echo htmlspecialchars($canonical); ?>" />
<meta property="og:title" content="<?php echo htmlspecialchars($seo_title); ?>">
<meta property="og:description" content="<?php echo htmlspecialchars($seo_description); ?>">
<?php if ((empty($seo_image) === false)): ?><meta property="og:image" content="<?php echo htmlspecialchars($seo_image); ?>"><?php endif; ?>
<meta property="og:url" content="<?php echo htmlspecialchars($canonical); ?>">
<meta property="og:type" content="article">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?php echo htmlspecialchars($seo_title); ?>">
<meta name="twitter:description" content="<?php echo htmlspecialchars($seo_description); ?>">
<?php if ((empty($seo_image) === false)): ?><meta name="twitter:image" content="<?php echo htmlspecialchars($seo_image); ?>"><?php endif; ?>
