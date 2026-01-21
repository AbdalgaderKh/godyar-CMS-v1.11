<?php
declare(strict_types=1);

/**
 * admin/includes/bootstrap.php
 * --------------------------
 * Wrapper bootstrap for the admin area.
 *
 * This file intentionally delegates to the main application bootstrap to avoid
 * duplicated configuration (especially ENV_FILE handling) and to keep admin and
 * frontend in sync.
 */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__, 2));
}

require_once ROOT_PATH . '/includes/bootstrap.php';

// Start session if needed (admin pages rely on it)
if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
    if (function_exists('gdy_session_start')) {
        gdy_session_start();
    } else {
        @session_start();
    }
}
