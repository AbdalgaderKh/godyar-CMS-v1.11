Hotfix: news_single_legacy.php parse error

Problem:
  PHP Parse error: syntax error, unexpected token "<"

Fix:
  Upload/replace this file exactly:
    frontend/views/news_single_legacy.php

Notes:
  - Ensure file permissions: 644 and folders: 755
  - Clear LiteSpeed cache (LSCache) and browser cache.
  - If available, validate syntax on the server:
      php -l frontend/views/news_single_legacy.php
