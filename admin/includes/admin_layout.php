<?php
declare(strict_types=1);

/**
 * admin/includes/admin_layout.php
 * --------------------------------
 * Helper for legacy admin pages that were not migrated to app_start/app_end.
 *
 * Usage:
 *   require_once __DIR__ . '/includes/admin_layout.php';
 *   render_page('Title', 'roles', function () {
 *       echo '...';
 *   });
 */

require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../_admin_boot.php';

if (!function_exists('render_page')) {
    /**
     * @param string   $title
     * @param string   $currentPage
     * @param callable $contentCb
     * @param array    $vars
     */
    function render_page(string $title, string $currentPage, callable $contentCb, array $vars = []): void
    {
        $GLOBALS['pageTitle'] = $title;
        $GLOBALS['currentPage'] = $currentPage;

        if (!empty($vars)) {
            extract($vars, EXTR_SKIP);
        }

        require_once __DIR__ . '/../layout/app_start.php';

        try {
            $contentCb();
        } catch (Throwable $e) {
            http_response_code(500);
            echo '<div class="alert alert-danger">' . htmlspecialchars('حدث خطأ داخلي أثناء عرض الصفحة.', ENT_QUOTES, 'UTF-8') . '</div>';
            error_log('[admin_layout] ' . $e->getMessage());
        }

        require_once __DIR__ . '/../layout/app_end.php';
    }
}
