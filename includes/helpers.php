<?php
// includes/helpers.php
// دوال مساعدة إضافية لموقع جويار

declare(strict_types=1);

if (function_exists('h') === false) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

// polyfill بسيط في حال السيرفر لا يدعم str_starts_with (احتياطاً)
if (function_exists('str_starts_with') === false) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

// محاولة تحميل إعدادات الذكاء الاصطناعي لو الملف موجود
$gdyAiConfigFile = __DIR__ . '/ai_config.php';
if (is_file($gdyAiConfigFile) === true) {
    require_once $gdyAiConfigFile;
}

/**
 * تطبيق قاموس المصطلحات على HTML المقال
 * - يبحث عن المصطلحات الفعّالة في جدول gdy_glossary
 * - يضيف span مع data-definition ليظهر التلميح في الواجهة
 * هذا هو الوضع اليدوي القديم (يمكن الإبقاء عليه أو تجاهله إذا اعتمدت على الذكاء الاصطناعي فقط).
 */
if (function_exists('gdy_apply_glossary') === false) {
    function gdy_apply_glossary(PDO $pdo, string $html): string
    {
        static $cache = null;

        if ($html === '') {
            return $html;
        }

        if ($cache === null) {
            try {
                $stmt = $pdo->query("SELECT term, short_definition FROM gdy_glossary WHERE is_active = 1 ORDER BY CHAR_LENGTH(term) DESC");
                $cache = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Exception $e) {
                error_log('[gdy_glossary] fetch terms failed: ' . $e->getMessage());
                $cache = [];
            }
        }

        if (($cache === false)) {
            return $html;
        }

        foreach ($cache as $row) {
            $term = trim((string)($row['term'] ?? ''));
            $def  = trim((string)($row['short_definition'] ?? ''));

            if ($term === '' || $def === '') {
                continue;
            }

            $pattern = '/(' . preg_quote($term, '/') . ')/u';

            // نستخدم دالة استبدال مخصصة حتى لا نغيّر داخل HTML tags
            $html = preg_replace_callback($pattern, function ($m) use ($def) {
                $text = $m[1];

                // لا نلفّ داخل attributes أو داخل tags
                if (preg_match('/^<|>$/', $text)) {
                    return $text;
                }

                return '<span class="gdy-glossary-term" data-definition="' . h($def) . '">' . $text . '</span>';
            }, $html, 3);
        }

        return $html;
    }
}

/**
 * استدعاء ChatGPT لاستخراج المصطلحات + تعريفاتها من نص عربي
 * يعمل تلقائياً بدون أي تدخل يدوي من لوحة التحكم
 */
if (function_exists('gdy_ai_glossary_suggest_terms') === false) {
    function gdy_ai_glossary_suggest_terms(string $plainText): array
    {
        $plainText = trim($plainText);
        if ($plainText === '') {
            return [];
        }

        // لو لم يتم تعريف مفتاح الـ API لا نفعل شيئاً
        if (!defined('OPENAI_API_KEY') || OPENAI_API_KEY === '') {
            error_log('[gdy_ai_glossary] OPENAI_API_KEY is not defined');
            return [];
        }

        // نختصر النص لو طويل جداً حتى لا نستهلك توكنات كثيرة
        if (mb_strlen($plainText, 'UTF-8') > 4000) {
            $plainText = mb_substr($plainText, 0, 4000, 'UTF-8');
        }

        $endpoint = 'https://api.openai.com/v1/chat/completions';

        $systemPrompt = 'أنت خبير لغة عربية لموقع أخبار.
مهمتك قراءة نص خبر أو مقال، واختيار المصطلحات أو الأسماء أو الاختصارات
التي قد تحتاج شرحاً للقارئ العربي العادي، ثم إعطاء تعريف مبسط لكل مصطلح.

القواعد:
- اختر من 3 إلى 8 مصطلحات كحد أقصى.
- تجنّب الكلمات السهلة والواضحة جداً.
- اكتب التعريف بجملتين أو ثلاث جمل قصيرة.
- أرجِع النتيجة بصيغة JSON فقط، بدون أي كلام إضافي.
- مثال:
[{"term": "الفيدرالي الأمريكي", "definition": "هو البنك المركزي للولايات المتحدة..."}]';

        $userPrompt = "النص:\n" . $plainText;

        $payload = [
            'model' => 'gpt-4.1-mini', // نموذج سريع واقتصادي من OpenAI
            'messages' => [
                [
                    'role'    => 'system',
                    'content' => $systemPrompt,
                ],
                [
                    'role'    => 'user',
                    'content' => $userPrompt,
                ],
            ],
            'max_tokens'  => 320,
            'temperature' => 0.3,
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json; charset=utf-8',
                'Authorization: ' . 'Bearer ' . OPENAI_API_KEY,
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
            CURLOPT_TIMEOUT        => 15,
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            error_log('[gdy_ai_glossary] curl error: ' . curl_error($ch));
            curl_close($ch);
            return [];
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);
        if ($status >= 400 || !is_array($data)) {
            error_log('[gdy_ai_glossary] bad status: ' . $status . ' body: ' . $response);
            return [];
        }

        $content = $data['choices'][0]['message']['content'] ?? '';
        $content = trim((string)$content);

        // أحياناً يرجّع ```json ... ```
        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```[a-zA-Z0-9]*\s*/', '', $content);
            $content = preg_replace('/```$/', '', $content);
            $content = trim($content);
        }

        $terms = json_decode($content, true);
        if (is_array($terms) === false) {
            error_log('[gdy_ai_glossary] JSON decode failed: ' . $content);
            return [];
        }

        $clean = [];
        foreach ($terms as $row) {
            $term = trim((string)($row['term'] ?? ''));
            $def  = trim((string)($row['definition'] ?? ''));

            if ($term === '' || $def === '') {
                continue;
            }

            $clean[] = [
                'term'       => $term,
                'definition' => $def,
            ];
        }

        return $clean;
    }
}

/**
 * يلفّ المصطلحات داخل HTML المقال باستخدام النتائج القادمة من ChatGPT
 * بدون أي تدخل يدوي
 */
if (function_exists('gdy_ai_glossary_annotate') === false) {
    function gdy_ai_glossary_annotate(string $html): string
    {
        $html  = (string)$html;
        $plain = trim(strip_tags($html));

        // لو النص قصير جداً، لا داعي للاتصال بالـ API
        if (mb_strlen($plain, 'UTF-8') < 80) {
            return $html;
        }

        $terms = gdy_ai_glossary_suggest_terms($plain);
        if (($terms === false)) {
            return $html;
        }

        foreach ($terms as $row) {
            $term = trim($row['term']);
            $def  = trim($row['definition']);

            if ($term === '' || $def === '') {
                continue;
            }

            // نهرب المصطلح للاستخدام في regex
            $pattern = '/' . preg_quote($term, '/') . '/u';

            $replacement = '<span class="gdy-glossary-term" data-definition="' . h($def) . '">$0</span>';

            // نستبدل أول 3 مرات فقط من كل مصطلح
            $html = preg_replace($pattern, $replacement, $html, 3);
        }

        return $html;
    }
}


if (function_exists('u') === false) {
    /**
     * Escape + validate URL to mitigate javascript: / data: injection in href/src.
     * Allows: relative URLs, http/https, mailto, tel.
     * Returns '#' for unsafe values.
     */
    function u($url): string {
        $url = (string)$url;
        // Remove ASCII control characters
        $url = preg_replace('/[\x00-\x1F\x7F]/u', '', $url) ?? '';
        $trim = trim($url);
        if ($trim === '') {
            return '#';
        }

        // Relative URL (starts with / or no scheme)
        if (preg_match('/^(\/|\.|\?|#)/', $trim) || !preg_match('/^[a-zA-Z][a-zA-Z0-9+.-]*:/', $trim)) {
            return h($trim);
        }

        $parts = parse_url($trim);
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        if (in_array($scheme, ['http','https','mailto','tel'], true)) {
            return h($trim);
        }

        return '#';
    }
}

if (function_exists('gdy_jsonld_safe') === false) {
    /**
     * Safely output JSON-LD inside <script type="application/ld+json">.
     * Prevents breaking out of the script tag.
     */
    function gdy_jsonld_safe($json): string {
        $json = (string)$json;
        // Prevent </script> injection
        $json = str_ireplace('</script', '<\/script', $json);
        return $json;
    }
}



if (function_exists('gdy_sanitize_basic_html') === false) {
    /**
     * Minimal HTML sanitizer for user-generated / CMS content when full HTML is not required.
     * This is NOT a replacement for a dedicated HTML purifier, but mitigates obvious XSS.
     */
    function gdy_sanitize_basic_html($html): string {
        $html = (string)$html;

        // Remove script/style blocks
        $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html) ?? '';

        // Allow a conservative set of tags
        $allowed = '<p><br><b><strong><i><em><u><small><span><div><blockquote><ul><ol><li><h1><h2><h3><h4><h5><h6><a><img>';
        $html = strip_tags($html, $allowed);

        // Remove on* event handlers and style attributes
        $html = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';
        $html = preg_replace('/\s+style\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';

        // Neutralize javascript: and data: URLs in href/src
        $html = preg_replace_callback('/\s+(href|src)\s*=\s*("([^"]*)"|\'([^\']*)\')/i', function($m) {
            $attr = strtolower($m[1]);
            $val  = $m[3] !== '' ? $m[3] : ($m[4] ?? '');
            $val  = preg_replace('/[\x00-\x1F\x7F]/u', '', (string)$val) ?? '';
            $valt = trim($val);
            if ($valt === '') {
                return ' ' . $attr . '="#"';
            }
            // allow relative or safe schemes only
            if (preg_match('/^(\/|\.|\?|#)/', $valt) || !preg_match('/^[a-zA-Z][a-zA-Z0-9+.-]*:/', $valt)) {
                return ' ' . $attr . '="' . htmlspecialchars($valt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
            }
            $parts = parse_url($valt);
            $scheme = strtolower((string)($parts['scheme'] ?? ''));
            if (in_array($scheme, ['http','https','mailto','tel'], true)) {
                return ' ' . $attr . '="' . htmlspecialchars($valt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
            }
            return ' ' . $attr . '="#"';
        }, $html) ?? $html;

        return $html;
    }
}
