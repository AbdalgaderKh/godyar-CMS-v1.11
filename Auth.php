<?php
declare(strict_types=1);

namespace Godyar;

/**
 * Auth + RBAC (roles + permissions + inheritance)
 *
 * Compatibility:
 * - Legacy login sets $_SESSION['user'] (array).
 * - Admin pages call: Auth::isLoggedIn(), Auth::user(), Auth::requirePermission(), Auth::isWriter()...
 *
 * RBAC tables (optional):
 *   roles(id, slug, name)
 *   permissions(id, code, name)
 *   role_permissions(role_id, permission_id)
 *   user_roles(user_id, role_id)
 *   role_inherits(child_role_id, parent_role_id)   <-- optional inheritance
 *
 * If RBAC tables are missing, falls back to a conservative legacy mode:
 *   - admin role => allow everything
 *   - otherwise => deny unknown permissions
 */
final class Auth
{
    private const CACHE_ROLES_KEY = '__gdy_rbac_roles';
    private const CACHE_PERMS_KEY = '__gdy_rbac_perms';

    /** Start session with sane defaults */
    private static function bootSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // Don't break existing sessions if app already configured ini settings elsewhere.
        if (PHP_SAPI !== 'cli') {
            @ini_set('session.cookie_httponly', '1');
            // Respect HTTPS
            if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
                @ini_set('session.cookie_secure', '1');
            }
            @ini_set('session.use_strict_mode', '1');
        }

        session_start();
    }

    /** Legacy: returns the session user array (or null) */
    public static function user(): ?array
    {
        self::bootSession();
        $u = $_SESSION['user'] ?? null;
        return is_array($u) ? $u : null;
    }

    public static function id(): ?int
    {
        $u = self::user();
        $id = $u['id'] ?? null;
        if (is_numeric($id)) {
            return (int)$id;
        }
        return null;
    }

    public static function isLoggedIn(): bool
    {
        return self::id() !== null;
    }

    /** Legacy helper used in sidebar */
    public static function isWriter(): bool
    {
        // NOTE: this helper is used to limit the admin UI for 'writer/editor' roles.
        // Admin must NEVER be treated as writer (otherwise sidebar/menu disappears).
        if (self::isAdmin()) {
            return false;
        }

        $role = strtolower((string)(self::user()['role'] ?? ''));
        if (in_array($role, ['writer', 'editor'], true)) {
            return true;
        }

        // If role is not available (RBAC-only installs), infer from permissions (best effort).
        try {
            return self::hasPermission('news.create') || self::hasPermission('news.write');
        } catch (Exception $e) {
            return false;
        }
    }
    public static function isAdmin(): bool
    {
        if (self::hasPermission('*')) {
            return true;
        }
        $role = strtolower((string)(self::user()['role'] ?? ''));
        return in_array($role, ['admin','administrator','superadmin','super_admin'], true);
    }

    /** Guard: redirect to login if not logged in */
    public static function requireLogin(?string $redirectTo = null): void
    {
        if (self::isLoggedIn()) {
            return;
        }
        $to = $redirectTo ?: '/admin/login.php';
        header('Location: ' . $to);
        exit;
    }

    /**
     * Guard: requires permission (code).
     * In legacy mode: admin role allowed, otherwise denied.
     */
    public static function requirePermission(string $permissionCode): void
    {
        if (!self::isLoggedIn()) {
            self::requireLogin();
        }

        if (self::hasPermission($permissionCode)) {
            return;
        }

        http_response_code(403);
        echo "Permission denied";
        exit;
    }

    public static function logout(string $redirectTo = '/admin/login.php'): void
    {
        self::bootSession();
        // Preserve flash if any? keep simple.
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
        }
        session_destroy();
        header('Location: ' . $redirectTo);
        exit;
    }

    /** Check permission */
    public static function hasPermission(string $permissionCode): bool
    {
        if ($permissionCode === '' || $permissionCode === null) {
            return false;
        }

        // Special wildcard: only admins get it
        if ($permissionCode === '*') {
            $role = strtolower((string)(self::user()['role'] ?? ''));
            return $role === 'admin';
        }

        // If no login, never.
        if (!self::isLoggedIn()) {
            return false;
        }

        // Admin shortcut
        if (self::isAdminLegacy()) {
            return true;
        }

        // Try RBAC from DB if available
        $perms = self::getPermissionSet();
        if ($perms === null) {
            // Legacy fallback: map some common permissions based on role
            return self::legacyPermissionMap($permissionCode);
        }

        return isset($perms[$permissionCode]) || isset($perms['*']);
    }

    /** Legacy: admin role string */
    private static function isAdminLegacy(): bool
    {
        $role = strtolower((string)(self::user()['role'] ?? ''));
        return in_array($role, ['admin','administrator','superadmin','super_admin'], true);
    }

    /** Legacy role->permission mapping for smoother migration */
    private static function legacyPermissionMap(string $code): bool
    {
        $role = strtolower((string)(self::user()['role'] ?? ''));
        $tier = match ($role) {
            'admin'  => 3,
            'editor' => 2,
            'writer' => 1,
            default  => 0,
        };

        $writerPerms = [
            'news.create',
            'news.write',
            'news.edit.own',
            'media.upload',
        ];

        $editorPerms = array_merge($writerPerms, [
            'news.edit.any',
            'news.publish',
            'comments.moderate',
        ]);

        if ($tier >= 2 && in_array($code, $editorPerms, true)) return true;
        if ($tier >= 1 && in_array($code, $writerPerms, true)) return true;

        return false;
    }

    /**
     * Returns permission set as associative array [code => true], or null if RBAC unavailable.
     */
    private static function getPermissionSet(): ?array
    {
        self::bootSession();

        // Cached?
        if (isset($_SESSION[self::CACHE_PERMS_KEY]) && is_array($_SESSION[self::CACHE_PERMS_KEY])) {
            return $_SESSION[self::CACHE_PERMS_KEY];
        }

        $uid = self::id();
        if ($uid === null) {
            return null;
        }

        // RBAC tables required
        $pdo = self::pdo();
        if (!$pdo) {
            return null;
        }

        try {
            // Quick existence check (cheap for MySQL)
            $tablesOk = self::rbacTablesExist($pdo);
            if (!$tablesOk) {
                return null;
            }

            $roleIds = self::resolveRoleIdsForUser($pdo, $uid);
            if (!$roleIds) {
                // If user has legacy role, attempt to map it to roles.slug
                $legacySlug = strtolower((string)(self::user()['role'] ?? ''));
                if ($legacySlug !== '') {
                    $stmt = $pdo->prepare("SELECT id FROM roles WHERE slug = ? LIMIT 1");
                    $stmt->execute([$legacySlug]);
                    $rid = $stmt->fetchColumn();
                    if ($rid) {
                        $roleIds = [(int)$rid];
                    }
                }
            }

            if (!$roleIds) {
                return [];
            }

            // Expand via inheritance if available
            $roleIds = self::expandRoleInheritance($pdo, $roleIds);

            // Fetch permissions (compat: permissions column may be `code` or `slug`)
            $permCol = self::permissionColumn($pdo);
            $in = implode(',', array_fill(0, count($roleIds), '?'));
            $sql = "
                SELECT p.{$permCol}
                FROM role_permissions rp
                INNER JOIN permissions p ON p.id = rp.permission_id
                WHERE rp.role_id IN ($in)
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($roleIds);

            $set = [];
            while (($code = $stmt->fetchColumn()) !== false) {
                if (is_string($code) && $code !== '') {
                    $set[$code] = true;
                }
            }

            // Cache in session
            $_SESSION[self::CACHE_PERMS_KEY] = $set;
            $_SESSION[self::CACHE_ROLES_KEY] = $roleIds;

            return $set;
        } catch (Exception $e) {
            // Don't hard-fail the whole app because RBAC query failed
            return null;
        }
    }

    private static function pdo(): ?\PDO
    {
        // includes/db.php provides getDB() in global namespace
        if (!function_exists('getDB')) {
            $dbFile = __DIR__ . '/db.php';
            if (is_file($dbFile)) {
                require_once $dbFile;
            }
        }
        if (!function_exists('getDB')) {
            return null;
        }
        try {
            $pdo = \getDB();
            return ($pdo instanceof \PDO) ? $pdo : null;
        } catch (Exception $e) {
            return null;
        }
    }

    private static function rbacTablesExist(\PDO $pdo): bool
    {
        // Cache per request
        static $ok = null;
        if ($ok !== null) return $ok;

        try {
            // This will throw if table missing
            $pdo->query("SELECT 1 FROM roles LIMIT 1");
            $pdo->query("SELECT 1 FROM permissions LIMIT 1");
            $pdo->query("SELECT 1 FROM role_permissions LIMIT 1");
            $pdo->query("SELECT 1 FROM user_roles LIMIT 1");
            $ok = true;
            return true;
        } catch (Exception $e) {
            $ok = false;
            return false;
        }
    }

    /**
     * Determine the permissions identifier column.
     * New schema uses `code`, legacy uses `slug`, and some older migrations used `key`.
     */
    private static function permissionColumn(\PDO $pdo): string
    {
        static $cached = null;
        if ($cached !== null) return $cached;

        try {
            $cols = [];
            foreach ($pdo->query("SHOW COLUMNS FROM permissions") as $row) {
                if (!empty($row['Field'])) $cols[] = (string)$row['Field'];
            }
            if (in_array('code', $cols, true)) { $cached = 'code'; return $cached; }
            if (in_array('slug', $cols, true)) { $cached = 'slug'; return $cached; }
            if (in_array('key', $cols, true))  { $cached = 'key';  return $cached; }
        } catch (Exception $e) {
            // ignore
        }
        $cached = 'slug';
        return $cached;
    }

    private static function resolveRoleIdsForUser(\PDO $pdo, int $userId): array
    {
        $stmt = $pdo->prepare("
            SELECT ur.role_id
            FROM user_roles ur
            WHERE ur.user_id = ?
        ");
        $stmt->execute([$userId]);
        $ids = [];
        while (($rid = $stmt->fetchColumn()) !== false) {
            if (is_numeric($rid)) $ids[] = (int)$rid;
        }
        $ids = array_values(array_unique($ids));
        return $ids;
    }

    private static function expandRoleInheritance(\PDO $pdo, array $roleIds): array
    {
        // If table doesn't exist, return as-is
        try {
            $pdo->query("SELECT 1 FROM role_inherits LIMIT 1");
        } catch (Exception $e) {
            return $roleIds;
        }

        $seen = array_fill_keys($roleIds, true);
        $queue = $roleIds;

        while ($queue) {
            $current = array_pop($queue);
            $stmt = $pdo->prepare("SELECT parent_role_id FROM role_inherits WHERE child_role_id = ?");
            $stmt->execute([$current]);
            while (($parent = $stmt->fetchColumn()) !== false) {
                if (!is_numeric($parent)) continue;
                $pid = (int)$parent;
                if (!isset($seen[$pid])) {
                    $seen[$pid] = true;
                    $queue[] = $pid;
                }
            }
        }

        return array_values(array_map('intval', array_keys($seen)));
    }

    /** Clear cached RBAC info (call after role/permission changes) */
    public static function clearCache(): void
    {
        self::bootSession();
        unset($_SESSION[self::CACHE_PERMS_KEY], $_SESSION[self::CACHE_ROLES_KEY]);
    }
}
