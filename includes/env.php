<?php
declare(strict_types=1);

// Idempotent include
if (defined('GODY_ENV_LOADED')) {
    return;
}
define('GODY_ENV_LOADED', true);

// Project root (shared hosting)
if (!defined('ABSPATH')) {
    define('ABSPATH', str_replace('\\', '/', dirname(__DIR__)));
}

if (!function_exists('gody_env_bool')) {
    function gody_env_bool($v): bool
    {
        if (is_bool($v)) {
            return $v;
        }
        $s = strtolower(trim((string)$v));
        return in_array($s, ['1','true','on','yes','y'], true);
    }
}

if (!function_exists('gody_unquote')) {
    function gody_unquote(string $v): string
    {
        $v = trim($v);
        if ($v === '') {
            return $v;
        }
        $q = $v[0];
        if (($q === '"' || $q === "'") && substr($v, -1) === $q) {
            return substr($v, 1, -1);
        }
        return $v;
    }
}

if (!function_exists('gody_parse_env_file')) {
    function gody_parse_env_file(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            return [];
        }

        if (function_exists('gdy_file_lines')) {
            $lines = gdy_file_lines($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (!is_array($lines)) {
                $lines = [];
            }
        } else {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        }

        $out = [];
        foreach ($lines as $line) {
            $line = trim((string)$line);
            if ($line === '' || $line[0] === '#' || $line[0] === ';') {
                continue;
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            $val = trim(substr($line, $pos + 1));

            // Strip inline comments for unquoted values
            if ($val !== '' && $val[0] !== '"' && $val[0] !== "'") {
                $hash = strpos($val, '#');
                if ($hash !== false) {
                    $val = trim(substr($val, 0, $hash));
                }
            }

            $val = gody_unquote($val);
            if ($key !== '') {
                $out[$key] = $val;
            }
        }
        return $out;
    }
}

if (!function_exists('env')) {
    function env(string $key, $default = null)
    {
        if (array_key_exists($key, $_SERVER)) {
            return $_SERVER[$key];
        }
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }
        $g = getenv($key);
        if ($g !== false) {
            return $g;
        }
        global $GODYAR_ENV_ARR;
        if (is_array($GODYAR_ENV_ARR) && array_key_exists($key, $GODYAR_ENV_ARR)) {
            return $GODYAR_ENV_ARR[$key];
        }
        return $default;
    }
}

// Load .env
$GODYAR_ENV_ARR = [];
$explicitEnvFile = getenv('ENV_FILE');
if ((!is_string($explicitEnvFile) || $explicitEnvFile === '') && isset($_SERVER['ENV_FILE'])) {
    $explicitEnvFile = (string)$_SERVER['ENV_FILE'];
}
if ((!is_string($explicitEnvFile) || $explicitEnvFile === '') && isset($_ENV['ENV_FILE'])) {
    $explicitEnvFile = (string)$_ENV['ENV_FILE'];
}
if (!$explicitEnvFile && defined('ENV_FILE')) {
    $explicitEnvFile = ENV_FILE;
}

$candidates = [];
if (is_string($explicitEnvFile) && $explicitEnvFile !== '') {
    $candidates[] = $explicitEnvFile;
}
$candidates[] = ABSPATH . '/.env';
$candidates[] = dirname(ABSPATH) . '/.env';

foreach ($candidates as $f) {
    if (is_string($f) && $f !== '' && is_file($f) && is_readable($f)) {
        $GODYAR_ENV_ARR = gody_parse_env_file($f);
        break;
    }
}

$cfg = is_array($GODYAR_ENV_ARR) ? $GODYAR_ENV_ARR : [];

/**
 * Defaults map (NO SECRETS).
 *
 * Important:
 * - Do not hardcode DB credentials or encryption keys in Git.
 * - Provide these via .env / environment variables on the server.
 */
$defaults = [
    'APP_ENV'        => 'production',
    'APP_DEBUG'      => 'false',
    'APP_URL'        => '',
    'DB_DRIVER'      => 'auto',
    'DB_HOST'        => 'localhost',
    'DB_PORT'        => '3306',
    'DB_DATABASE'    => '',   // set in .env (DB_DATABASE / DB_NAME)
    'DB_USERNAME'    => '',   // set in .env (DB_USERNAME / DB_USER)
    'DB_PASSWORD'    => '',   // set in .env (DB_PASSWORD / DB_PASS)
    'DB_CHARSET'     => 'utf8mb4',
    'DB_COLLATION'   => 'utf8mb4_unicode_ci',
    'DB_DSN'         => '',
    'TIMEZONE'       => 'Asia/Riyadh',
    'ENCRYPTION_KEY' => '',   // set in .env (never commit real value)
];

if (!function_exists('gody_env_db')) {
    function gody_env_db(string $primary, string $alt, $default = ''): string
    {
        $v = env($primary, null);
        if ($v !== null && $v !== '') {
            return (string)$v;
        }
        $v2 = env($alt, null);
        if ($v2 !== null && $v2 !== '') {
            return (string)$v2;
        }
        return (string)$default;
    }
}

// Define constants if not defined
foreach ($defaults as $key => $def) {
    if (!defined($key)) {
        if ($key === 'DB_DATABASE') {
            $val = gody_env_db('DB_DATABASE', 'DB_NAME', $def);
        } elseif ($key === 'DB_USERNAME') {
            $val = gody_env_db('DB_USERNAME', 'DB_USER', $def);
        } elseif ($key === 'DB_PASSWORD') {
            $val = gody_env_db('DB_PASSWORD', 'DB_PASS', $def);
        } else {
            $val = env($key, $def);
        }

        if ($key === 'APP_DEBUG') {
            define($key, gody_env_bool($val));
        } else {
            define($key, (string)$val);
        }
    }
}

// Build DB_DSN if empty
if (defined('DB_DSN') && DB_DSN === '') {
    $drv = defined('DB_DRIVER') ? strtolower((string)DB_DRIVER) : 'auto';
    $drv = strtolower((string)$drv);
    if ($drv === 'postgres' || $drv === 'postgresql') {
        $drv = 'pgsql';
    }
    if ($drv === '' || $drv === 'auto') {
        if (extension_loaded('pdo_mysql')) {
            $drv = 'mysql';
        } elseif (extension_loaded('pdo_pgsql')) {
            $drv = 'pgsql';
        } else {
            $drv = 'mysql';
        }
    }

    $host = (string)(defined('DB_HOST') ? DB_HOST : 'localhost');
    $port = (string)(defined('DB_PORT') ? DB_PORT : ($drv === 'pgsql' ? '5432' : '3306'));
    $name = (string)(defined('DB_DATABASE') ? DB_DATABASE : '');

    if ($drv === 'pgsql') {
        $dsn = "pgsql:host={$host};port={$port};dbname={$name}";
    } else {
        $charset = (string)(defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4');
        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";
    }

    // Constants already defined, cannot redefine; expose DSN via global for downstream if needed.
    $cfg['DB_DSN'] = $dsn;
}

// Aliases
if (!defined('DB_NAME')) {
    define('DB_NAME', defined('DB_DATABASE') ? DB_DATABASE : (string)env('DB_NAME', ''));
}
if (!defined('DB_USER')) {
    define('DB_USER', defined('DB_USERNAME') ? DB_USERNAME : (string)env('DB_USER', ''));
}
if (!defined('DB_PASS')) {
    define('DB_PASS', defined('DB_PASSWORD') ? DB_PASSWORD : (string)env('DB_PASS', ''));
}

// Timezone
if (defined('TIMEZONE') && TIMEZONE) {
    date_default_timezone_set(TIMEZONE);
}

// Error reporting
if (defined('APP_DEBUG') && APP_DEBUG) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
}

// PDO helpers
if (!function_exists('gody_determine_pdo_driver')) {
    function gody_determine_pdo_driver(string $dsn, string $override = ''): string
    {
        $drv = strtolower(trim($override));
        if ($drv === '' || $drv === 'auto') {
            if (stripos($dsn, 'pgsql:') === 0) {
                $drv = 'pgsql';
            } elseif (stripos($dsn, 'mysql:') === 0) {
                $drv = 'mysql';
            }
        }
        if ($drv === 'postgres' || $drv === 'postgresql') {
            $drv = 'pgsql';
        }
        if ($drv === '') {
            $drv = 'mysql';
        }
        return $drv;
    }
}

if (!function_exists('gody_build_pdo_options')) {
    function gody_build_pdo_options(string $drv): array
    {
        $opts = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        if ($drv === 'mysql') {
            $charset   = defined('DB_CHARSET') ? (string)DB_CHARSET : 'utf8mb4';
            $collation = defined('DB_COLLATION') && (string)DB_COLLATION !== '' ? (string)DB_COLLATION : 'utf8mb4_unicode_ci';
            if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
                $opts[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES {$charset} COLLATE {$collation}";
            }
        }

        return $opts;
    }
}

if (!function_exists('gody_create_pdo_connection')) {
    function gody_create_pdo_connection(): ?PDO
    {
        $dsn = defined('DB_DSN') ? (string)DB_DSN : '';
        if ($dsn === '' && isset($GLOBALS['cfg']['DB_DSN'])) {
            $dsn = (string)$GLOBALS['cfg']['DB_DSN'];
        }
        if ($dsn === '' && isset($GLOBALS['cfg']) && is_array($GLOBALS['cfg']) && isset($GLOBALS['cfg']['DB_DSN'])) {
            $dsn = (string)$GLOBALS['cfg']['DB_DSN'];
        }
        if ($dsn === '') {
            return null;
        }

        $drv  = gody_determine_pdo_driver($dsn, defined('DB_DRIVER') ? (string)DB_DRIVER : '');
        $opts = gody_build_pdo_options($drv);

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $opts);

            if ($drv === 'pgsql') {
                try {
                    $pdo->exec("SET client_encoding TO 'UTF8'");
                } catch (Throwable $e) {
                    // ignored
                }
            }

            return $pdo;
        } catch (Throwable $e) {
            if (defined('APP_DEBUG') && APP_DEBUG) {
                error_log('[PDO] Connection failed: ' . $e->getMessage());
            }
            return null;
        }
    }
}

if (!function_exists('gody_pdo')) {
    function gody_pdo(): ?PDO
    {
        static $pdo = null;
        if (($pdo instanceof PDO) === false) {
            $pdo = gody_create_pdo_connection();
        }
        return $pdo;
    }
}

// Convenience: APP_URL auto-detect (if empty)
if (defined('APP_URL') && APP_URL === '' && !headers_sent()) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? '';
    if ($host) {
        $uri  = $_SERVER['REQUEST_URI'] ?? '/';
        $base = (strpos($uri, '/godyar/') !== false) ? '/godyar' : '';
        $auto = $scheme . '://' . $host . $base;
        if (!defined('APP_URL_AUTO')) {
            define('APP_URL_AUTO', $auto);
        }
    }
}
