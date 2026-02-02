<?php
/**
 * Global search entry point.
 *
 * Historically, this endpoint was implemented as a "pages-only" search.
 * The site-wide search (news/pages/authors) lives in frontend/news/search.php
 * and is what the /search route is intended to serve.
 */

require __DIR__ . '/../news/search.php';
