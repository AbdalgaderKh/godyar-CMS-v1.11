<?php
declare(strict_types=1);

namespace Godyar;

/**
 * Auth (consolidated)
 * -------------------
 * توحيد ملف المصادقة لتفادي مشاكل Linux case-sensitive.
 * هذا الملف يحل محل includes/Auth.php و includes/auth.php القديم (bridge).
 */
final class Auth
{
    private static function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
            if (function_exists('gdy_session_start')) {
                gdy_session_start();
            } else {
                @session_start();
            }
        }
    }

    public static function check(): bool
    {
        self::ensureSession();
        return isset($_SESSION['user']) || isset($_SESSION['user_id']);
    }

    public static function user(): ?array
    {
        self::ensureSession();

        if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) {
            return $_SESSION['user'];
        }

        if (!empty($_SESSION['user_id'])) {
            return [
                'id'   => (int)$_SESSION['user_id'],
                'role' => $_SESSION['user_role'] ?? null,
                'name' => $_SESSION['user_name'] ?? null,
            ];
        }

        // Optional legacy global Auth
        if (class_exists('\\Auth') && method_exists('\\Auth', 'user')) {
            try {
                $u = \Auth::user();
                return is_array($u) ? $u : null;
            } catch (\Throwable) {
                // ignore
            }
        }

        return null;
    }

    public static function hasRole(string $role): bool
    {
        $u = self::user();
        if (!$u) return false;
        $r = $u['role'] ?? null;

        if (is_array($r)) return in_array($role, $r, true);
        if ($r === null || $r === '') return false;

        $roles = array_filter(array_map('trim', explode(',', (string)$r)));
        return in_array($role, $roles, true);
    }

    public static function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if (is_string($role) && self::hasRole($role)) {
                return true;
            }
        }
        return false;
    }

    public static function isAdmin(): bool
    {
        return self::hasRole('admin');
    }

    public static function isWriter(): bool
    {
        return self::hasRole('writer') || self::hasRole('author');
    }

    /**
     * Policy: restrict writers/authors داخل لوحة التحكم
     */
    public static function enforceAdminPolicy(): void
    {
        self::ensureSession();
        if (!self::check()) return;

        if (!self::isWriter()) return;

        $uri  = (string)($_SERVER['REQUEST_URI'] ?? '');
        $path = (string)(parse_url($uri, PHP_URL_PATH) ?? '');
        if ($path === '' || strpos($path, '/admin/') !== 0) return;

        $allowedPrefixes = ['/admin/news/'];
        foreach ($allowedPrefixes as $prefix) {
            if (strpos($path, $prefix) === 0) return;
        }

        $allowedExact = ['/admin/login','/admin/login.php','/admin/logout.php','/admin/index.php'];
        if (in_array($path, $allowedExact, true)) return;

        header('Location: /admin/news/index.php');
        exit;
    }

    public static function isLoggedIn(): bool
    {
        self::enforceAdminPolicy();
        return self::check();
    }
    /**
     * Permission check (compatibility).
     * Many admin pages call Auth::hasPermission('something').
     * This implementation is conservative:
     * - superadmin/admin => allow all
     * - if session contains explicit permissions list => match exact or wildcard prefix (e.g. "news.*")
     */
    public static function hasPermission(string $perm): bool
    {
        self::ensureSession();

        // Allow all for admin roles
        if (self::hasAnyRole(['superadmin', 'admin'])) {
            return true;
        }

        $perms = null;

        if (isset($_SESSION['user']['permissions'])) {
            $perms = $_SESSION['user']['permissions'];
        } elseif (isset($_SESSION['permissions'])) {
            $perms = $_SESSION['permissions'];
        }

        if (is_string($perms)) {
            $perms = array_filter(array_map('trim', explode(',', $perms)));
        }

        if (is_array($perms)) {
            foreach ($perms as $p) {
                if (!is_string($p) || $p === '') continue;
                if ($p === $perm) return true;

                // Wildcard support: "news.*"
                if (substr($p, -2) === '.*') {
                    $prefix = substr($p, 0, -2);
                    if ($prefix !== '' && ( $perm === $prefix || strpos($perm, $prefix . '.') === 0 )) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Enforce permission or stop with HTTP 403.
     */
    public static function requirePermission(string $perm): void
    {
        if (self::hasPermission($perm)) {
            return;
        }

        http_response_code(403);
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }
        echo '<h1>403</h1><p>' . htmlspecialchars('غير مصرح لك بتنفيذ هذا الإجراء.', ENT_QUOTES, 'UTF-8') . '</p>';
        exit;
    }
}
