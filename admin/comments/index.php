<?php
declare(strict_types=1);

// Legacy admin comments page removed (comments table deprecated).
// Redirect to the current moderation page that uses news_comments.

header('Location: /admin/comments.php', true, 302);
exit;
