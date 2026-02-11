<?php
declare(strict_types=1);

require_once __DIR__ . '/_admin_guard.php';
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

use Godyar\Auth;

$entity = strtolower(trim((string)($_GET['entity'] ?? '')));
$allowed = ['news','users','comments','tags','categories','media'];
if (in_array($entity, $allowed, true) === false) {
  http_response_code(400);
  echo "Invalid entity";
  exit;
}

// Basic permission mapping (adjust via DB permissions if desired)
$permMap = [
  'news' => 'posts.view',
  'users' => 'manage_users',
  'comments' => 'comments.view',
  'tags' => 'tags.view',
  'categories' => 'categories.view',
  'media' => 'media.view',
];
$perm = $permMap[$entity] ?? '';
if ($perm !== '' && class_exists('\\Godyar\\Auth')) {
  // fail-open to avoid breaking existing installs; if you want strict, change to requirePermission always.
  try { \Godyar\Auth::requirePermission($perm); } catch (Exception $e) {}
}

$pdo = \Godyar\DB::pdo();

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="'.$entity.'_export_'.date('Y-m-d_H-i').'.csv"');

// UTF-8 BOM for Excel
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

function row(array $r): void {
  global $out;
  fputcsv($out, $r);
}

try {
  switch ($entity) {
    case 'news':
      row(['id','title','status','author_id','category_id','created_at','published_at']);
      $stmt = $pdo->query("SELECT id,title,status,{$newsOwnerCol} AS author_id,category_id,created_at,published_at FROM news ORDER BY id DESC LIMIT 5000");
      while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) row($r);
      break;

    case 'users':
      row(['id','username','email','role','status','created_at']);
      $stmt = $pdo->query("SELECT id,username,email,role,status,created_at FROM users ORDER BY id DESC LIMIT 5000");
      while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) row($r);
      break;

case 'comments':
  // news_comments only (legacy comments table removed)
  $chk = gdy_db_stmt_table_exists($pdo, 'news_comments');
  $has = $chk && $chk->fetchColumn();
  if (($has === false)) { row(['error']); row(['news_comments table missing']); break; }

  $hasScore = true;
  try {
    if (function_exists('gdy_db_column_exists')) { $hasScore = gdy_db_column_exists($pdo, 'news_comments', 'score'); }
    elseif (function_exists('db_column_exists')) { $hasScore = db_column_exists($pdo, 'news_comments', 'score'); }
  } catch (Exception $e) { $hasScore = true; }

  $scoreSel = $hasScore ? 'score' : '0 AS score';

  row(['id','news_id','user_id','parent_id','name','email','status','score','created_at','body']);
  $stmt = $pdo->query("SELECT id,news_id,user_id,parent_id,name,email,status,{$scoreSel} AS score,created_at,body FROM news_comments ORDER BY id DESC LIMIT 5000");
  while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) row($r);
  break;

    case 'tags':
      row(['id','name','slug','created_at']);
      $stmt = $pdo->query("SELECT id,name,slug,created_at FROM tags ORDER BY id DESC LIMIT 5000");
      while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) row($r);
      break;

    case 'categories':
      row(['id','name','slug','created_at']);
      $stmt = $pdo->query("SELECT id,name,slug,created_at FROM categories ORDER BY id DESC LIMIT 5000");
      while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) row($r);
      break;

    case 'media':
      row(['id','file_name','file_type','created_at']);
      $stmt = $pdo->query("SELECT id,file_name,file_type,created_at FROM media ORDER BY id DESC LIMIT 5000");
      while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) row($r);
      break;
  }
} catch (Exception $e) {
  row(['error']);
  row([$e->getMessage()]);
}

fclose($out);
exit;
