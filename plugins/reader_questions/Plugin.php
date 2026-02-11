<?php
// plugins/reader_questions/Plugin.php
declare(strict_types=1);

return new class implements GodyarPluginInterface
{
    public function register(PluginManager $pm): void
    {
        // عرض ويدجت أسئلة القرّاء تحت الخبر
        $pm->addHook('news.after_content', function (int $newsId, array $news) {
            if ($newsId <= 0) return;

            $questionsCount = (int) g_apply_filters('questions.count', 0, $newsId);

            $view = __DIR__ . '/views/widget.php';
            if (is_file($view)) {
                $endpoint = rtrim((string)(function_exists('base_url') ? base_url() : ''), '/') . '/plugins/reader_questions/public/questions.php';
                include $view;
            }
        }, 20);

        // فلتر عدد الأسئلة المنشورة (approved/answered)
        $pm->addHook('questions.count', function ($value, int $newsId) {
            $pdo = function_exists('gdy_pdo_safe') ? gdy_pdo_safe() : null;
            if (($pdo instanceof PDO) === false) return (int)$value;

            try {
                $st = $pdo->prepare("SELECT COUNT(*) FROM reader_questions WHERE news_id=? AND status IN ('approved','answered')");
                $st->execute([$newsId]);
                return (int)$st->fetchColumn();
            } catch (Exception $e) {
                error_log('[ReaderQuestionsPlugin] count: ' . $e->getMessage());
                return (int)$value;
            }
        }, 10);

        // عنصر قائمة الأدمن
        $pm->addHook('admin.menu', function (&$adminMenu) {
            if (!is_array($adminMenu)) return;
            $adminMenu[] = [
                'key'   => 'plugin_reader_questions',
                'title' => 'أسئلة القرّاء',
                'sub'   => 'مراجعة وإجابة الأسئلة',
                'url'   => '/admin/plugins/reader_questions/index.php',
                'icon'  => 'question',
            ];
        }, 21);
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
    }

    private function createTablesV1(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS reader_questions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                news_id INT NOT NULL,
                author_name VARCHAR(150) NULL,
                author_email VARCHAR(190) NULL,
                question TEXT NOT NULL,
                answer TEXT NULL,
                status ENUM('pending','approved','answered','spam') NOT NULL DEFAULT 'pending',
                ip VARCHAR(64) NULL,
                user_agent VARCHAR(255) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                answered_at DATETIME NULL,
                INDEX (news_id, status),
                INDEX (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function upgradeToV2(PDO $pdo): void
    {
        // دعم "تعديل" السؤال عبر token عشوائي (نص فقط) + تتبع updated_at.
        if (!$this->columnExists($pdo, 'reader_questions', 'edit_token_hash')) {
            $pdo->exec("ALTER TABLE reader_questions ADD COLUMN edit_token_hash VARCHAR(255) NULL AFTER user_agent");
        }
        if (!$this->columnExists($pdo, 'reader_questions', 'updated_at')) {
            $pdo->exec("ALTER TABLE reader_questions ADD COLUMN updated_at DATETIME NULL AFTER created_at");
        }
        if (!$this->columnExists($pdo, 'reader_questions', 'updated_ip')) {
            $pdo->exec("ALTER TABLE reader_questions ADD COLUMN updated_ip VARCHAR(64) NULL AFTER updated_at");
        }
    }

private function upgradeToV3(PDO $pdo): void
{
    // إصلاح توافق: جدول reader_questions قديم بدون author_name/author_email
    if (!$this->columnExists($pdo, 'reader_questions', 'author_name')) {
        $pdo->exec("ALTER TABLE reader_questions ADD COLUMN author_name VARCHAR(150) NULL AFTER news_id");
    }
    if (!$this->columnExists($pdo, 'reader_questions', 'author_email')) {
        $pdo->exec("ALTER TABLE reader_questions ADD COLUMN author_email VARCHAR(190) NULL AFTER author_name");
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
        } catch (Exception $e) {
            return false;
        }
    }
};
