<?php
// /public_html/includes/auth.php (compat bridge)

$base = __DIR__;
$candidates = [
    $base . '/Auth.php',
    $base . '/auth/Auth.php',
    $base . '/auth.php',
];

foreach ($candidates as $f) {
    if (is_file($f)) {
        require_once $f;
        return;
    }
}

error_log('[Godyar Auth] includes/auth.php not found. Candidates tried: ' . implode(', ', $candidates));
http_response_code(500);
exit('Auth bootstrap missing.');
