<?php
// plugins/comments/Plugin.php
declare(strict_types=1);

return new class implements GodyarPluginInterface
{
    public function register(PluginManager $pm): void
    {
        // عرض ويدجت التعليقات تحت الخبر
        $pm->addHook('news.after_content', function (int $newsId, array $news) {
            if ($newsId <= 0) return;

            $commentsCount = (int) g_apply_filters('comments.count', 0, $newsId);

            $view = __DIR__ . '/views/widget.php';
            if (is_file($view)) {
                $endpoint = rtrim((string)(function_exists('base_url') ? base_url() : ''), '/') . '/plugins/comments/public/comments.php';
                include $view;
            }
        }, 10);

        // فلتر عدد التعليقات (approved فقط)
        $pm->addHook('comments.count', function ($value, int $newsId) {
            $pdo = function_exists('gdy_pdo_safe') ? gdy_pdo_safe() : null;
            if (($pdo instanceof PDO) === false) return (int)$value;

            try {
                $st = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE news_id=? AND status='approved'");
                $st->execute([$newsId]);
                return (int)$st->fetchColumn();
            } catch (Throwable $e) {
                error_log('[CommentsPlugin] count: ' . $e->getMessage());
                return (int)$value;
            }
        }, 10);

        // عنصر قائمة الأدمن
        $pm->addHook('admin.menu', function (&$adminMenu) {
            if (!is_array($adminMenu)) return;
            $adminMenu[] = [
                'key'   => 'plugin_comments',
                'title' => 'التعليقات',
                'sub'   => 'مراجعة وإدارة التعليقات',
                'url'   => '/admin/plugins/comments/index.php',
                'icon'  => 'comment',
            ];
        }, 20);
    }
    /**
     * Install/Migrate on enable
     */
    public function migrate(PDO $pdo, int $from, int $to, string $pluginPath = '', array $meta = []): void
    {
        if ($from < 1 && $to >= 1) {
            $this->createTablesV1($pdo);
        }

        if ($from < 2 && $to >= 2) {
            $this->upgradeToV2($pdo);
        }

        if ($from < 3 && $to >= 3) {
            $this->upgradeToV3($pdo);
        }

        if ($from < 4 && $to >= 4) {
            $this->upgradeToV4($pdo);
        }

        if ($from < 5 && $to >= 5) {
            $this->upgradeToV5($pdo);
        }
    }

    private function createTablesV1(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS comments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                news_id INT NOT NULL,
                user_id INT NULL,
                author_name VARCHAR(150) NULL,
                author_email VARCHAR(190) NULL,
                body TEXT NOT NULL,
                status ENUM('pending','approved','spam') NOT NULL DEFAULT 'pending',
                ip VARCHAR(64) NULL,
                user_agent VARCHAR(255) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX (news_id, status),
                INDEX (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function upgradeToV2(PDO $pdo): void
    {
        // دعم "تعديل" التعليق عبر token عشوائي (نص فقط) + تتبع updated_at.
        if (!$this->columnExists($pdo, 'comments', 'edit_token_hash')) {
            $pdo->exec("ALTER TABLE comments ADD COLUMN edit_token_hash VARCHAR(255) NULL AFTER user_agent");
        }
        if (!$this->columnExists($pdo, 'comments', 'updated_at')) {
            $pdo->exec("ALTER TABLE comments ADD COLUMN updated_at DATETIME NULL AFTER created_at");
        }
        if (!$this->columnExists($pdo, 'comments', 'updated_ip')) {
            $pdo->exec("ALTER TABLE comments ADD COLUMN updated_ip VARCHAR(64) NULL AFTER updated_at");
        }
    }

private function upgradeToV3(PDO $pdo): void
{
    // إصلاح توافق: جدول comments قديم بدون author_name/author_email
    if (!$this->columnExists($pdo, 'comments', 'author_name')) {
        $pdo->exec("ALTER TABLE comments ADD COLUMN author_name VARCHAR(150) NULL AFTER user_id");
    }
    if (!$this->columnExists($pdo, 'comments', 'author_email')) {
        $pdo->exec("ALTER TABLE comments ADD COLUMN author_email VARCHAR(190) NULL AFTER author_name");
    }
}


private function upgradeToV4(PDO $pdo): void
{
    // ردود (threading) مستوى واحد عبر parent_id
    if (!$this->columnExists($pdo, 'comments', 'parent_id')) {
        $pdo->exec("ALTER TABLE comments ADD COLUMN parent_id INT NULL AFTER news_id");
    }
    try {
        $pdo->exec("ALTER TABLE comments ADD INDEX idx_comments_parent (parent_id)");
    } catch (Throwable $e) {
        // ignore (قد يكون موجودًا)
    }
}

	private function upgradeToV5(PDO $pdo): void
	{
	    // V5: لا تغييرات كاسرة مطلوبة للبيانات، لكن نضيف فهارس مفيدة إن لم تكن موجودة.
	    // جميع الأوامر أدناه Best-effort حتى لا يفشل ترحيل الإصدارات في بيئات مختلفة.
	    try {
	        $pdo->exec("ALTER TABLE comments ADD INDEX idx_comments_news_status (news_id, status)");
	    } catch (Throwable $e) {
	        // ignore (قد يكون موجودًا)
	    }
	    try {
	        $pdo->exec("ALTER TABLE comments ADD INDEX idx_comments_news_created (news_id, created_at)");
	    } catch (Throwable $e) {
	        // ignore (قد يكون موجودًا)
	    }
	}

private function columnExists(PDO $pdo, string $table, string $column): bool
    {
        try {
            $db = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
            if ($db === '') return false;
            $st = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?');
            $st->execute([$db, $table, $column]);
            return ((int)$st->fetchColumn()) > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
};
