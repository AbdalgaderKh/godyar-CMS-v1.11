<?php
require_once dirname(__DIR__) . '/_admin_boot.php';

$currentPage  = 'roles';
$pageTitle    = __('roles_title', 'الأدوار والصلاحيات');
$pageSubtitle = __('roles_subtitle', 'إدارة الأدوار والصلاحيات المرتبطة بلوحة التحكم.');

$adminBase = (function_exists('base_url') ? rtrim(base_url(), '/') : '') . '/admin';
$breadcrumbs = [
    __('home', 'الرئيسية') => $adminBase . '/index.php',
    __('roles', 'الأدوار')  => null,
];

require_once __DIR__ . '/../layout/app_start.php';

// Permission gate (fallback if Auth permission methods are unavailable)
try {
    if (class_exists('Godyar\\Auth') && method_exists('Godyar\\Auth', 'requirePermission')) {
        \Godyar\Auth::requirePermission('manage_roles');
    } else {
        $role = (string)($_SESSION['user']['role'] ?? $_SESSION['admin']['role'] ?? '');
        if (!in_array($role, ['admin', 'superadmin'], true)) {
            http_response_code(403);
            echo '<div class="alert alert-danger">' . h(__('forbidden', 'غير مصرح')) . '</div>';
            require_once __DIR__ . '/../layout/app_end.php';
            exit;
        }
    }
} catch (Throwable $e) {
    http_response_code(403);
    echo '<div class="alert alert-danger">' . h(__('forbidden', 'غير مصرح')) . '</div>';
    require_once __DIR__ . '/../layout/app_end.php';
    exit;
}

$pdo = (isset($pdo) && $pdo instanceof PDO) ? $pdo : (function_exists('gdy_pdo_safe') ? gdy_pdo_safe() : null);

$rolesError = null;
$roles = [];

if (($pdo instanceof PDO) === false) {
    $rolesError = __('db_error', 'تعذّر الاتصال بقاعدة البيانات.');
} else {
    try {
        $stmt = $pdo->query("SELECT id, name, label, description, is_system, created_at FROM roles ORDER BY is_system DESC, id ASC");
        $roles = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    } catch (Throwable $e) {
        try {
            $stmt = $pdo->query("SELECT id, name, label, is_system, created_at FROM roles ORDER BY is_system DESC, id ASC");
            $roles = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
            foreach ($roles as &$r) { $r['description'] = ''; }
        } catch (Throwable $e2) {
            $rolesError = __('roles_load_error', 'تعذر تحميل الأدوار.');
            error_log('[Roles] ' . $e2->getMessage());
        }
    }
}
?>
<div class="card">
  <div class="card-body">
    <?php if ($rolesError): ?>
      <div class="alert alert-warning mb-3"><?= h($rolesError) ?></div>
    <?php endif; ?>

    <?php if (empty($roles)): ?>
      <p class="mb-0"><?= h(__('no_roles', 'لا توجد أدوار مسجلة.')) ?></p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
          <thead>
            <tr>
              <th style="width:70px;">#</th>
              <th><?= h(__('tech_name', 'الاسم التقني')) ?></th>
              <th><?= h(__('display_name', 'اسم العرض')) ?></th>
              <th><?= h(__('desc', 'وصف')) ?></th>
              <th style="width:110px;" class="text-center"><?= h(__('system', 'نظامي')) ?></th>
              <th style="width:170px;" class="text-nowrap"><?= h(__('created_at', 'تاريخ الإنشاء')) ?></th>
              <th style="width:120px;" class="text-center"><?= h(__('actions', 'إجراءات')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($roles as $roleRow): ?>
              <tr>
                <td><?= (int)($roleRow['id'] ?? 0) ?></td>
                <td class="text-nowrap"><code><?= h((string)($roleRow['name'] ?? '')) ?></code></td>
                <td><?= h((string)($roleRow['label'] ?? '')) ?></td>
                <td><?= h((string)($roleRow['description'] ?? '')) ?></td>
                <td class="text-center">
                  <?php if ((int)($roleRow['is_system'] ?? 0) === 1): ?>
                    <span class="badge text-bg-secondary"><?= h(__('yes', 'نعم')) ?></span>
                  <?php else: ?>
                    <span class="badge text-bg-light"><?= h(__('no', 'لا')) ?></span>
                  <?php endif; ?>
                </td>
                <td class="text-nowrap small text-muted"><?= h((string)($roleRow['created_at'] ?? '')) ?></td>
                <td class="text-center">
                  <a class="btn btn-sm btn-outline-secondary" href="edit.php?id=<?= (int)($roleRow['id'] ?? 0) ?>">
                    <?= h(__('edit', 'تعديل')) ?>
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php require_once __DIR__ . '/../layout/app_end.php'; ?>
