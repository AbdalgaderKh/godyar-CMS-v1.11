<?php
// Admin logout endpoint.
// IMPORTANT: do not protect this route with role guards; it must always be reachable.

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

\Godyar\Auth::logout();
