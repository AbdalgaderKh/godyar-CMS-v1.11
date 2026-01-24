<?php
/**
 * godyar-fix.php
 * فحص وصحة مشروع PHP على الاستضافة مع إخراج JSON/CSV/HTML.
 *
 * ملاحظات مهمة (خاصة لبيئات الاستضافة المشتركة):
 * - يمكن أن تكون logs/tmp وملف .env خارج public_html (مثل: /home/USER/godyar_private/).
 * - هذا السكربت يدعم الآن private_root لتوجيه الفحوصات إلى المسارات الخاصة.
 *
 * الاستخدام:
 *   https://your-domain.com/godyar-fix.php?token=YOUR_TOKEN
 *
 * JSON:
 *   ?token=...&format=json
 *
 * CSV:
 *   ?token=...&format=csv
 *
 * تفعيل lint (قد يكون بطيئًا):
 *   ?token=...&lint=1
 *
 * فحص HTTP (اختياري):
 *   ?token=...&base_url=https://your-domain.com
 *
 * DB (اختياري - أو اترك auto_db=1 لمحاولة الاكتشاف):
 *   ?token=...&db_dsn=...&db_user=...&db_pass=...
 *
 * مسار الخاص (Private Root) لاستخدام logs/tmp/.env خارج public_html:
 *   ?token=...&private_root=/home/USER/godyar_private
 *
 * مهم: احذف الملف بعد الانتهاء.
 */

declare(strict_types=1);

// =====================
// إعدادات أمان وتشغيل
// =====================
const SECRET_TOKEN = 'REPLACE_WITH_YOUR_TOKEN';

// افتراضيات مجلدات الكتابة داخل المشروع (public_html)
const DEFAULT_WRITABLE_DIRS = ['uploads', 'cache', 'storage', 'logs', 'tmp'];

// امتدادات شائعة يحتاجها كثير من مشاريع PHP (عدّلها عند الحاجة)
const DEFAULT_REQUIRED_EXTS = ['curl', 'mbstring', 'openssl', 'pdo', 'json', 'xml', 'gd', 'pdo_mysql', 'pdo_pgsql'];

// إعداد افتراضي لمسار خاص (يمكن تجاوزه عبر private_root في الرابط)
// ضع مسارك الحقيقي هنا (كما ذكرت: /home/USER/godyar_private)
const DEFAULT_PRIVATE_ROOT = '/home/USER/godyar_private';

const MAX_SECURITY_FILE_SIZE = 2_000_000; // 2MB لتسريع فحص الأنماط
const MAX_SECURITY_HITS_PER_PATTERN = 10;
const MAX_LINT_FILES = 2000; // حد أقصى لتجنب تحميل زائد
const MAX_FILE_SCAN = 20_000; // حد عام لعدد الملفات

const EXCLUDE_DIRS = ['vendor', 'node_modules', '.git', '.svn', '.idea'];

// =====================
// Helpers: Request / Output
// =====================
function isCli(): bool { return PHP_SAPI === 'cli'; }

function getParam(string $key, $default = null) {
    if (isCli()) {
        global $argv;
        // صيغة: --key=value
        foreach ($argv as $arg) {
            if (strpos($arg, "--{$key}=") === 0) {
                return substr($arg, strlen("--{$key}="));
            }
        }
        return $default;
    }
    return $_GET[$key] ?? $default;
}

function requireAuth(): void {
    $token = (string)getParam('token', '');
    if ($token === '' || !hash_equals(SECRET_TOKEN, $token)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "403 Forbidden\n";
        echo "Missing/invalid token.\n";
        echo "Usage: ?token=YOUR_TOKEN\n";
        exit;
    }
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// =====================
// Report structure
// =====================
function record(array &$rows, array &$summary, string $status, string $section, string $check, string $message, array $extra = []): void {
    $rows[] = [
        'ts' => date('c'),
        'status' => $status,
        'section' => $section,
        'check' => $check,
        'message' => $message,
        'extra' => $extra,
    ];
    if (!isset($summary['counts'][$status])) $summary['counts'][$status] = 0;
    $summary['counts'][$status]++;

    if (in_array($status, ['warn', 'fail'], true)) {
        $summary['issues'][] = [
            'status' => $status,
            'section' => $section,
            'check' => $check,
            'message' => $message,
            'extra' => $extra,
        ];
    }
}

function initSummary(): array {
    return [
        'generated_at' => date('c'),
        'root' => null,
        'private_root' => null,
        'counts' => ['ok' => 0, 'warn' => 0, 'fail' => 0],
        'issues' => [],
    ];
}

// =====================
// Core Checks
// =====================
function phpIniValue(string $key): string {
    $v = ini_get($key);
    if ($v === false) return '';
    return (string)$v;
}

function listLoadedExtensionsLower(): array {
    $exts = get_loaded_extensions();
    $out = [];
    foreach ($exts as $e) $out[strtolower($e)] = true;
    return $out;
}

function findProjectRoot(): string {
    $root = (string)getParam('root', '');
    if ($root !== '') {
        $root = rtrim($root, DIRECTORY_SEPARATOR);
        if (is_dir($root)) return $root;
    }
    return realpath(__DIR__) ?: __DIR__;
}

function findPrivateRoot(): string {
    $p = (string)getParam('private_root', '');
    if ($p !== '') {
        $p = rtrim($p, DIRECTORY_SEPARATOR);
        if (is_dir($p)) return $p;
    }
    if (is_dir(DEFAULT_PRIVATE_ROOT)) return rtrim(DEFAULT_PRIVATE_ROOT, DIRECTORY_SEPARATOR);
    return ''; // غير متاح
}

function isExcludedDir(string $path): bool {
    foreach (EXCLUDE_DIRS as $d) {
        if (strpos($path, DIRECTORY_SEPARATOR . $d . DIRECTORY_SEPARATOR) !== false) return true;
    }
    return false;
}

function iterFiles(string $root, string $extFilter = ''): Generator {
    $flags = FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, $flags),
        RecursiveIteratorIterator::SELF_FIRST
    );

    $count = 0;
    foreach ($it as $fileInfo) {
        if (++$count > MAX_FILE_SCAN) break;

        /** @var SplFileInfo $fileInfo */
        $path = $fileInfo->getPathname();

        if ($fileInfo->isDir()) {
            foreach (EXCLUDE_DIRS as $d) {
                if (basename($path) === $d) {
                    $it->next();
                    continue 2;
                }
            }
            continue;
        }

        if (isExcludedDir($path)) continue;

        if ($extFilter !== '') {
            if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== strtolower($extFilter)) continue;
        }

        yield $path;
    }
}

/**
 * ترجمة "اسم مجلد" إلى "مسار فعلي":
 * - إذا كان الاسم مسارًا مطلقًا -> يُستخدم كما هو.
 * - logs/tmp: إذا كان موجودًا ضمن private_root يُفضل private_root/{logs|tmp}.
 * - غير ذلك -> {root}/{name}
 */
function resolveDirPath(string $root, string $privateRoot, string $name): string {
    $name = trim($name);
    if ($name === '') return $root;

    // مسار مطلق
    if ($name[0] === '/' || preg_match('~^[A-Za-z]:[\\\\/]~', $name)) {
        return rtrim($name, DIRECTORY_SEPARATOR);
    }

    // تفضيل الخاص لـ logs/tmp إذا توفر
    if ($privateRoot !== '' && in_array($name, ['logs', 'tmp'], true)) {
        $candidate = $privateRoot . DIRECTORY_SEPARATOR . $name;
        if (is_dir($candidate)) return $candidate;
    }

    return $root . DIRECTORY_SEPARATOR . $name;
}

function checkHtaccess(array &$rows, array &$summary, string $root): void {
    $section = 'Rewrite/.htaccess';
    $ht = $root . DIRECTORY_SEPARATOR . '.htaccess';
    if (is_file($ht)) {
        record($rows, $summary, 'ok', $section, '.htaccess', 'يوجد ملف .htaccess في الجذر');
        $content = @file_get_contents($ht);
        if ($content !== false) {
            if (preg_match('/RewriteEngine\s+On/i', $content)) {
                record($rows, $summary, 'ok', $section, 'RewriteEngine', 'RewriteEngine On موجود');
            } else {
                record($rows, $summary, 'warn', $section, 'RewriteEngine', 'RewriteEngine On غير موجود (قد يكون طبيعيًا حسب الإعداد)');
            }
            if (preg_match('/RewriteRule|RewriteCond/i', $content)) {
                record($rows, $summary, 'ok', $section, 'RewriteRule/Cond', 'RewriteRule/RewriteCond موجودة');
            } else {
                record($rows, $summary, 'warn', $section, 'RewriteRule/Cond', 'لا توجد RewriteRule/RewriteCond في .htaccess');
            }
        } else {
            record($rows, $summary, 'warn', $section, '.htaccess read', 'تعذر قراءة ملف .htaccess');
        }
    } else {
        record($rows, $summary, 'warn', $section, '.htaccess', 'لا يوجد .htaccess في الجذر (طبيعي إذا كنت تستخدم Nginx أو قواعد أخرى)');
    }
}

function checkRuntime(array &$rows, array &$summary): void {
    $section = 'PHP Runtime';
    record($rows, $summary, 'ok', $section, 'PHP Version', PHP_VERSION);

    $logErrors = phpIniValue('log_errors');
    $displayErrors = phpIniValue('display_errors');
    $errorLog = phpIniValue('error_log');
    $tz = phpIniValue('date.timezone');
    $mem = phpIniValue('memory_limit');
    $up = phpIniValue('upload_max_filesize');
    $post = phpIniValue('post_max_size');
    $maxExec = phpIniValue('max_execution_time');

    record($rows, $summary, 'ok', $section, 'log_errors', "log_errors={$logErrors}");
    record(
        $rows,
        $summary,
        ($displayErrors === '0' || strtolower($displayErrors) === 'off') ? 'ok' : 'warn',
        $section,
        'display_errors',
        "display_errors={$displayErrors} (يفضل Off على الإنتاج)"
    );
    record($rows, $summary, 'ok', $section, 'error_log', $errorLog !== '' ? $errorLog : '<default>');
    if ($tz === '') {
        record($rows, $summary, 'warn', $section, 'date.timezone', 'date.timezone غير مضبوط');
    } else {
        record($rows, $summary, 'ok', $section, 'date.timezone', $tz);
    }
    record($rows, $summary, 'ok', $section, 'memory_limit', $mem);
    record($rows, $summary, 'ok', $section, 'upload/post', "upload_max_filesize={$up} | post_max_size={$post}");
    record($rows, $summary, 'ok', $section, 'max_execution_time', $maxExec);
}

function checkExtensions(array &$rows, array &$summary, array $requiredExts): void {
    $section = 'Extensions';
    $loaded = listLoadedExtensionsLower();
    foreach ($requiredExts as $ext) {
        $extLower = strtolower($ext);
        if (isset($loaded[$extLower])) {
            record($rows, $summary, 'ok', $section, "ext:{$extLower}", 'موجود');
        } else {
            record($rows, $summary, 'warn', $section, "ext:{$extLower}", 'مفقود (قد يسبب أخطاء وقت التشغيل حسب استخدام المشروع)');
        }
    }
}

function checkWritableDirs(array &$rows, array &$summary, string $root, string $privateRoot, array $dirs): void {
    $section = 'Writable Directories';

    foreach ($dirs as $d) {
        $path = resolveDirPath($root, $privateRoot, (string)$d);

        if (!is_dir($path)) {
            record($rows, $summary, 'warn', $section, (string)$d, 'المجلد غير موجود (تحقق من المسار أو أنشئه)', ['path' => $path]);
            continue;
        }

        if (is_writable($path)) {
            record($rows, $summary, 'ok', $section, (string)$d, 'قابل للكتابة', ['path' => $path]);
        } else {
            record($rows, $summary, 'warn', $section, (string)$d, 'غير قابل للكتابة (تحقق من صلاحيات مستخدم PHP-FPM/Apache)', ['path' => $path]);
        }

        $testFile = $path . DIRECTORY_SEPARATOR . '.godyar_write_test_' . getmypid() . '_' . bin2hex(random_bytes(4));
        $ok = @file_put_contents($testFile, "test\n");
        if ($ok !== false) {
            @unlink($testFile);
            record($rows, $summary, 'ok', $section, (string)$d . ':write-test', 'اختبار كتابة ناجح', ['path' => $path]);
        } else {
            record($rows, $summary, 'warn', $section, (string)$d . ':write-test', 'فشل اختبار الكتابة داخل المجلد', ['path' => $path]);
        }
    }
}

function canRunShell(): bool {
    $disabled = phpIniValue('disable_functions');
    if ($disabled !== '') {
        $disabledList = array_map('trim', explode(',', $disabled));
        $blocked = array_flip($disabledList);
        foreach (['shell_exec', 'exec', 'system', 'passthru', 'proc_open'] as $fn) {
            if (isset($blocked[$fn])) return false;
        }
    }
    return function_exists('proc_open');
}

function checkLint(array &$rows, array &$summary, string $root): void {
    $section = 'PHP Lint (php -l)';
    if (!canRunShell()) {
        record($rows, $summary, 'warn', $section, 'shell', 'لا يمكن تشغيل فحص lint عبر shell (proc_open/exec معطلة غالبًا) — تم التخطي');
        return;
    }

    $phpPath = PHP_BINARY ?: 'php';

    $count = 0;
    $errors = 0;
    foreach (iterFiles($root, 'php') as $file) {
        if (++$count > MAX_LINT_FILES) {
            record($rows, $summary, 'warn', $section, 'limit', 'تم الوصول للحد الأقصى لعدد ملفات lint لتجنب الحمل الزائد', ['max' => MAX_LINT_FILES]);
            break;
        }

        $cmd = escapeshellarg($phpPath) . ' -l ' . escapeshellarg($file);
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = @proc_open($cmd, $descriptors, $pipes, null, null, ['bypass_shell' => true]);
        if (!is_resource($proc)) {
            record($rows, $summary, 'warn', $section, 'proc_open', 'تعذر تشغيل php -l (proc_open)');
            return;
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]); fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]); fclose($pipes[2]);
        $exitCode = proc_close($proc);

        if ($exitCode !== 0) {
            $errors++;
            record($rows, $summary, 'fail', $section, 'Lint Error', 'خطأ lint في ملف', [
                'file' => substr($file, strlen($root) + 1),
                'stdout' => trim((string)$stdout),
                'stderr' => trim((string)$stderr),
            ]);
        }
    }

    if ($errors === 0) {
        record($rows, $summary, 'ok', $section, 'result', 'جميع ملفات PHP التي تم فحصها اجتازت lint بدون أخطاء', ['files_checked' => $count]);
    } else {
        record($rows, $summary, 'warn', $section, 'result', 'تم العثور على أخطاء lint', ['errors' => $errors, 'files_checked' => $count]);
    }
}

function checkSecurityPatterns(array &$rows, array &$summary, string $root): void {
    $section = 'Security Patterns';

    $selfReal = realpath(__FILE__) ?: __FILE__;

    // Scan PHP files for potentially dangerous function calls using token_get_all()
    // so matches inside comments/strings do not trigger false positives.
    $targets = [
        'eval'          => 'eval',
        'shell_exec'    => 'shell_exec',
        'exec'          => 'exec',
        'system'        => 'system',
        'passthru'      => 'passthru',
        'popen'         => 'popen',
        'proc_open'     => 'proc_open',
        'assert'        => 'assert',
        // Data handling primitives (not inherently dangerous, but worth reviewing when used unsafely)
        'base64_decode' => 'base64_decode',
        'gzinflate'     => 'gzinflate',
    ];

    $hitsAny = false;

    $isIgnorable = static function($tok): bool {
        if (is_array($tok)) {
            return in_array($tok[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
        }
        return ($tok === ' ' || $tok === "\n" || $tok === "\r" || $tok === "\t");
    };

    $isTrueToken = static function($tok): bool {
        if (is_array($tok)) {
            if (defined('T_TRUE') && $tok[0] === T_TRUE) return true;
            return ($tok[0] === T_STRING && strtolower($tok[1]) === 'true');
        }
        return false;
    };

    foreach ($targets as $label => $fnName) {
        $hits = 0;

        foreach (iterFiles($root, 'php') as $file) {
            $real = realpath($file) ?: $file;

            // Exclude this checker file itself
            if ($real === $selfReal) continue;

            // Optional: ignore vendor-ish libs that are expected to contain such primitives
            // (kept off by default; do not exclude in "real fix" mode)

            $size = @filesize($file);
            if ($size === false || $size > MAX_SECURITY_FILE_SIZE) continue;

            $content = @file_get_contents($file);
            if ($content === false) continue;

            try {
                $tokens = @token_get_all($content);
                if (!is_array($tokens) || !$tokens) continue;
            } catch (Throwable $e) {
                continue;
            }

            $countTokens = count($tokens);
            for ($i = 0; $i < $countTokens; $i++) {
                $tok = $tokens[$i];

                if (!is_array($tok) || $tok[0] !== T_STRING) {
                    continue;
                }

                $name = strtolower($tok[1]);
                if ($name !== $fnName) continue;

                // Find previous significant token
                $j = $i - 1;
                while ($j >= 0 && $isIgnorable($tokens[$j])) $j--;

                // Avoid methods like $pdo->exec() or Class::exec()
                if ($j >= 0) {
                    $prev = $tokens[$j];
                    if (is_array($prev) && in_array($prev[0], [T_OBJECT_OPERATOR, T_DOUBLE_COLON], true)) {
                        continue;
                    }
                    if (!is_array($prev) && ($prev === '->' || $prev === '::')) {
                        continue;
                    }
                }

                // Find next significant token
                $k = $i + 1;
                while ($k < $countTokens && $isIgnorable($tokens[$k])) $k++;

                // Confirm it's a function call: next token is '('
                $next = $tokens[$k] ?? null;
                if ($next !== '(') {
                    continue;
                }

                // Special handling: base64_decode is OK when used in strict mode (second arg true).
                if ($fnName === 'base64_decode') {
                    $parenLevel = 0;
                    $sawCommaAtTop = false;
                    $strictTrue = false;

                    for ($p = $k; $p < $countTokens; $p++) {
                        $t = $tokens[$p];
                        if ($t === '(') {
                            $parenLevel++;
                            continue;
                        }
                        if ($t === ')') {
                            $parenLevel--;
                            if ($parenLevel <= 0) break;
                            continue;
                        }
                        if ($parenLevel === 1 && $t === ',') {
                            $sawCommaAtTop = true;
                            // Next significant token should be 'true'
                            $q = $p + 1;
                            while ($q < $countTokens && $isIgnorable($tokens[$q])) $q++;
                            if ($q < $countTokens && $isTrueToken($tokens[$q])) {
                                $strictTrue = true;
                            }
                            break;
                        }
                    }

                    if ($strictTrue) {
                        record($rows, $summary, 'ok', $section, 'base64_decode(strict)', 'base64_decode مستخدمة بوضع strict=true (جيد)', [
                            'file' => substr($file, strlen($root) + 1),
                        ]);
                        continue;
                    }
                    // Otherwise: warn, because non-strict decoding is easier to misuse.
                }

                $hits++;
                $hitsAny = true;

                record($rows, $summary, 'warn', $section, $label . '(', 'تم العثور على نمط عالي الخطورة/الحساسية (راجع الاستخدام والسياق)', [
                    'file' => substr($file, strlen($root) + 1),
                ]);

                if ($hits >= MAX_SECURITY_HITS_PER_PATTERN) break 2; // stop scanning this pattern further
            }
        }

        if ($hits === 0) {
            record($rows, $summary, 'ok', $section, $label . '(', 'لا توجد نتائج ضمن حدود البحث');
        }
    }

    if (!$hitsAny) {
        record($rows, $summary, 'ok', $section, 'summary', 'لم يتم العثور على أنماط عالية الخطورة ضمن الحدود');
    }
}

function parseEnvFile(string $envPath): ?array {
    if (!is_file($envPath)) return null;

    $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines) return null;

    $map = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        $v = trim($v, "\"'");
        $map[$k] = $v;
    }
    return $map ?: null;
}

function detectDbFromEnv(string $root, string $privateRoot): ?array {
    $candidates = [
        $root . DIRECTORY_SEPARATOR . '.env',
    ];
    if ($privateRoot !== '') {
        $candidates[] = $privateRoot . DIRECTORY_SEPARATOR . '.env';
    }

    foreach ($candidates as $env) {
        $map = parseEnvFile($env);
        if (!$map) continue;

        $driver = strtolower($map['DB_CONNECTION'] ?? $map['DB_DRIVER'] ?? 'mysql');
        $host = $map['DB_HOST'] ?? '';
        $port = $map['DB_PORT'] ?? '';
        $name = $map['DB_DATABASE'] ?? $map['DB_NAME'] ?? '';
        $user = $map['DB_USERNAME'] ?? $map['DB_USER'] ?? '';
        $pass = $map['DB_PASSWORD'] ?? $map['DB_PASS'] ?? '';

        if ($host === '' || $name === '') continue;

        if (in_array($driver, ['pgsql', 'postgres', 'postgresql'], true)) {
            $dsn = "pgsql:host={$host};dbname={$name}";
            if ($port !== '') $dsn = "pgsql:host={$host};port={$port};dbname={$name}";
        } else {
            $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
            if ($port !== '') $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        }

        $src = (strpos($env, $privateRoot) === 0) ? 'private_root/.env' : '.env';
        return ['dsn' => $dsn, 'user' => $user, 'pass' => $pass, 'source' => $src];
    }

    return null;
}

function detectDbFromCommonConfigs(string $root): ?array {
    $candidates = [
        'config.php',
        'config/config.php',
        'includes/config.php',
        'include/config.php',
        'inc/config.php',
        'settings.php',
        'db.php',
        'database.php',
        'app/config.php',
        'app/settings.php',
    ];

    foreach ($candidates as $rel) {
        $file = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (!is_file($file)) continue;

        $content = @file_get_contents($file);
        if ($content === false) continue;

        $get = function(string $key) use ($content): ?string {
            $patterns = [
                '/[\'"]' . preg_quote($key, '/') . '[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/i',
                '/define\(\s*[\'"]' . preg_quote($key, '/') . '[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]\s*\)/i',
                '/\$' . preg_quote($key, '/') . '\s*=\s*[\'"]([^\'"]+)[\'"]/i',
                '/' . preg_quote($key, '/') . '\s*=\s*[\'"]([^\'"]+)[\'"]/i',
            ];
            foreach ($patterns as $p) {
                if (preg_match($p, $content, $m)) return $m[1];
            }
            return null;
        };

        $host = $get('DB_HOST');
        $port = $get('DB_PORT');
        $name = $get('DB_NAME') ?? $get('DB_DATABASE');
        $user = $get('DB_USER') ?? $get('DB_USERNAME');
        $pass = $get('DB_PASS') ?? $get('DB_PASSWORD');
        $driver = strtolower((string)($get('DB_DRIVER') ?? $get('DB_CONNECTION') ?? 'mysql'));

        if (!$host || !$name) continue;

        if (in_array($driver, ['pgsql', 'postgres', 'postgresql'], true)) {
            $dsn = "pgsql:host={$host};dbname={$name}";
            if ($port) $dsn = "pgsql:host={$host};port={$port};dbname={$name}";
        } else {
            $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
            if ($port) $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        }

        return ['dsn' => $dsn, 'user' => (string)$user, 'pass' => (string)$pass, 'source' => $rel];
    }

    return null;
}

function checkDb(array &$rows, array &$summary, string $root, string $privateRoot): void {
    $section = 'Database';

    $dsn = (string)getParam('db_dsn', '');
    $user = (string)getParam('db_user', '');
    $pass = (string)getParam('db_pass', '');

    $auto = (string)getParam('auto_db', '1');
    $autoEnabled = ($auto !== '0');

    $detected = null;
    if ($dsn === '' && $autoEnabled) {
        $detected = detectDbFromEnv($root, $privateRoot) ?? detectDbFromCommonConfigs($root);
        if ($detected) {
            $dsn = $detected['dsn'];
            $user = $detected['user'];
            $pass = $detected['pass'];
            record($rows, $summary, 'ok', $section, 'auto-detect', 'تم اكتشاف إعدادات DB تلقائيًا', ['source' => $detected['source']]);
        } else {
            record($rows, $summary, 'warn', $section, 'auto-detect', 'لم يتم العثور على إعدادات DB تلقائيًا (.env أو ملفات config شائعة)');
        }
    } elseif ($dsn !== '') {
        record($rows, $summary, 'ok', $section, 'manual', 'تم تمرير DSN يدويًا');
    }

    if ($dsn === '') {
        record($rows, $summary, 'warn', $section, 'skip', 'تم تخطي اختبار DB (لا يوجد DSN)');
        return;
    }

    if (!class_exists(PDO::class)) {
        record($rows, $summary, 'fail', $section, 'PDO', 'PDO غير متوفر');
        return;
    }

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        $pdo->query('SELECT 1');
        record($rows, $summary, 'ok', $section, 'connection', 'اتصال DB ناجح', ['dsn' => $dsn]);
    } catch (Throwable $e) {
        record($rows, $summary, 'fail', $section, 'connection', 'فشل اتصال DB', [
            'dsn' => $dsn,
            'error' => $e->getMessage(),
        ]);
    }
}

function checkHttp(array &$rows, array &$summary): void {
    $baseUrl = (string)getParam('base_url', '');
    if ($baseUrl === '') {
        record($rows, $summary, 'ok', 'HTTP Smoke Tests', 'skip', 'تم تخطي فحص HTTP (لم يتم تمرير base_url)');
        return;
    }
    $section = 'HTTP Smoke Tests';

    if (!function_exists('curl_init')) {
        record($rows, $summary, 'warn', $section, 'curl', 'امتداد curl غير متوفر في PHP — تم التخطي');
        return;
    }

    $baseUrl = rtrim($baseUrl, '/');
    $targets = [
        'Home' => $baseUrl . '/',
        'Admin Login' => $baseUrl . '/admin/login.php',
        'robots.txt' => $baseUrl . '/robots.txt',
        'sitemap.xml' => $baseUrl . '/sitemap.xml',
        'news.php' => $baseUrl . '/news.php',
        'Rewrite/404 sanity' => $baseUrl . '/this-should-not-exist-12345',
    ];

    foreach ($targets as $name => $url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'godyar-fix/1.1',
        ]);
        curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($code >= 200 && $code < 400) {
            record($rows, $summary, 'ok', $section, $name, "HTTP {$code}", ['url' => $url]);
        } else {
            record($rows, $summary, 'warn', $section, $name, "HTTP {$code}" . ($err ? " ({$err})" : ''), ['url' => $url]);
        }
    }
}

function runAllChecks(): array {
    $rows = [];
    $summary = initSummary();

    $root = findProjectRoot();
    $privateRoot = findPrivateRoot();
    $summary['root'] = $root;
    $summary['private_root'] = $privateRoot !== '' ? $privateRoot : null;

    // توثيق private_root
    if ($privateRoot !== '') {
        record($rows, $summary, 'ok', 'Paths', 'private_root', 'تم ضبط المسار الخاص (للـ logs/tmp/.env)', ['private_root' => $privateRoot]);
    } else {
        record($rows, $summary, 'warn', 'Paths', 'private_root', 'لم يتم العثور على private_root (يمكن تمريره عبر ?private_root=...)');
    }

    checkRuntime($rows, $summary);

    $requiredExts = (string)getParam('exts', '');
    $exts = DEFAULT_REQUIRED_EXTS;
    if ($requiredExts !== '') {
        $exts = array_values(array_filter(array_map('trim', explode(',', $requiredExts))));
    }
    checkExtensions($rows, $summary, $exts);

    checkHtaccess($rows, $summary, $root);

    $writable = (string)getParam('writable', '');
    $dirs = DEFAULT_WRITABLE_DIRS;
    if ($writable !== '') {
        $dirs = array_values(array_filter(array_map('trim', explode(',', $writable))));
    }
    checkWritableDirs($rows, $summary, $root, $privateRoot, $dirs);

    // DB اختياري (افتراضيًا ON)
    $skipDb = (string)getParam('skip_db', '0');
    if ($skipDb === '1') {
        record($rows, $summary, 'warn', 'Database', 'skip', 'تم تخطي اختبار DB (skip_db=1)');
    } else {
        checkDb($rows, $summary, $root, $privateRoot);
    }

    // Security patterns (افتراضيًا ON)
    $skipSecurity = (string)getParam('skip_security', '0');
    if ($skipSecurity === '1') {
        record($rows, $summary, 'warn', 'Security Patterns', 'skip', 'تم تخطي فحص الأنماط الأمنية (skip_security=1)');
    } else {
        checkSecurityPatterns($rows, $summary, $root);
    }

    // HTTP smoke tests (اختياري إذا base_url موجود)
    $skipHttp = (string)getParam('skip_http', '0');
    if ($skipHttp === '1') {
        record($rows, $summary, 'warn', 'HTTP Smoke Tests', 'skip', 'تم تخطي فحص HTTP (skip_http=1)');
    } else {
        checkHttp($rows, $summary);
    }

    // Lint (افتراضيًا OFF)
    $lint = (string)getParam('lint', '0');
    if ($lint === '1') {
        checkLint($rows, $summary, $root);
    } else {
        record($rows, $summary, 'ok', 'PHP Lint (php -l)', 'skip', 'تم تخطي lint (مرر lint=1 للتفعيل)');
    }

    return ['summary' => $summary, 'rows' => $rows];
}

// =====================
// Render outputs
// =====================
requireAuth();
$report = runAllChecks();

$format = strtolower((string)getParam('format', 'html'));
if ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="godyar-report.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ts', 'status', 'section', 'check', 'message', 'extra_json']);
    foreach ($report['rows'] as $r) {
        fputcsv($out, [
            $r['ts'] ?? '',
            $r['status'] ?? '',
            $r['section'] ?? '',
            $r['check'] ?? '',
            $r['message'] ?? '',
            json_encode($r['extra'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }
    fclose($out);
    exit;
}

// HTML UI
header('Content-Type: text/html; charset=utf-8');

$token = (string)getParam('token', '');
$self = basename(__FILE__);
$base = $self . '?token=' . rawurlencode($token);

$counts = $report['summary']['counts'] ?? ['ok' => 0, 'warn' => 0, 'fail' => 0];
$issues = $report['summary']['issues'] ?? [];

?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Godyar Fix - تقرير الفحص</title>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial; margin:20px; line-height:1.5;}
    .cards{display:flex; gap:12px; flex-wrap:wrap; margin:12px 0;}
    .card{border:1px solid #ddd; border-radius:10px; padding:12px; min-width:160px;}
    .ok{color:#0a7;}
    .warn{color:#b80;}
    .fail{color:#c00;}
    table{border-collapse:collapse; width:100%; margin-top:14px;}
    th,td{border:1px solid #eee; padding:8px; vertical-align:top;}
    th{background:#fafafa;}
    code{background:#f6f6f6; padding:2px 6px; border-radius:6px;}
    .actions a{display:inline-block; margin-inline:6px; padding:8px 10px; border:1px solid #ddd; border-radius:8px; text-decoration:none;}
  </style>
</head>
<body>
  <h2>تقرير فحص المشروع</h2>
  <div>المسار (root): <code><?=h((string)($report['summary']['root'] ?? ''))?></code></div>
  <div>المسار الخاص (private_root): <code><?=h((string)($report['summary']['private_root'] ?? ''))?></code></div>

  <div class="cards">
    <div class="card"><div class="ok">OK</div><div><?= (int)$counts['ok'] ?></div></div>
    <div class="card"><div class="warn">WARN</div><div><?= (int)$counts['warn'] ?></div></div>
    <div class="card"><div class="fail">FAIL</div><div><?= (int)$counts['fail'] ?></div></div>
  </div>

  <div class="actions">
    <a href="<?=h($base . '&format=json')?>">تحميل JSON</a>
    <a href="<?=h($base . '&format=csv')?>">تحميل CSV</a>
    <a href="<?=h($base)?>">إعادة الفحص</a>
    <a href="<?=h($base . '&lint=1')?>">إعادة الفحص مع Lint</a>
  </div>

  <h3>أهم التحذيرات/الأخطاء</h3>
  <?php if (empty($issues)): ?>
    <div class="ok">لا توجد مشاكل مصنفة كـ WARN/FAIL ضمن الفحوصات الحالية.</div>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>الحالة</th><th>القسم</th><th>التحقق</th><th>الرسالة</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($issues as $it): ?>
        <tr>
          <td class="<?=h((string)$it['status'])?>"><?=h(strtoupper((string)$it['status']))?></td>
          <td><?=h((string)$it['section'])?></td>
          <td><?=h((string)$it['check'])?></td>
          <td>
            <?=h((string)$it['message'])?>
            <?php if (!empty($it['extra'])): ?>
              <div><small><code><?=h(json_encode($it['extra'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))?></code></small></div>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <h3>كل النتائج</h3>
  <table>
    <thead>
      <tr>
        <th>الوقت</th><th>الحالة</th><th>القسم</th><th>التحقق</th><th>الرسالة</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($report['rows'] as $r): ?>
      <tr>
        <td><code><?=h((string)($r['ts'] ?? ''))?></code></td>
        <td class="<?=h((string)($r['status'] ?? ''))?>"><?=h(strtoupper((string)($r['status'] ?? '')))?></td>
        <td><?=h((string)($r['section'] ?? ''))?></td>
        <td><?=h((string)($r['check'] ?? ''))?></td>
        <td>
          <?=h((string)($r['message'] ?? ''))?>
          <?php if (!empty($r['extra'])): ?>
            <div><small><code><?=h(json_encode($r['extra'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))?></code></small></div>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <hr>
  <div style="color:#666">
    تنبيه: هذا الملف مخصص للفحص فقط. يُفضّل حذفه بعد الانتهاء.
  </div>
</body>
</html>
