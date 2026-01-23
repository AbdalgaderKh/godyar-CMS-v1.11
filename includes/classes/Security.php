<?php
declare(strict_types=1);

/**
 * Legacy global Security helper.
 *
 * بعض أجزاء النظام القديمة تستدعي Security::cleanInput() و Security::logSecurityEvent()
 * بدون namespace. هذا الملف يعيد توفير تلك الواجهة بشكل آمن.
 */
final class Security
{
    /**
     * تنظيف إدخال بشكل محافظ (لا يزيل HTML).
     * الهدف: إزالة NULL bytes ومحارف التحكم غير المرئية.
     */
    public static function cleanInput(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $s = (string)$value;
        // Remove NULL bytes
        $s = str_replace("\0", '', $s);
        // Strip ASCII control chars except \n \r \t
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? $s;
        return trim($s);
    }

    /**
     * تسجيل حدث أمني في ملف منفصل.
     */
	/**
	 * Legacy-safe logger.
	 * بعض الاستدعاءات القديمة تمرر قيمة عددية (مثل user_id) بدل مصفوفة.
	 * نحولها إلى مصفوفة لمنع TypeError على بيئات الإنتاج.
	 */
	public static function logSecurityEvent(string $event, mixed $context = []): void
    {
		// Normalize context to array
		if (!is_array($context)) {
			$context = ['value' => $context];
		}
        try {
            $root = defined('ROOT_PATH') ? rtrim((string)ROOT_PATH, '/\\') : dirname(__DIR__, 2);

            // افتراضياً داخل المشروع
            $logDir = $root . '/storage/logs';

            // إذا كان هناك godyar_private خارج public_html، نفضّل التسجيل فيه
            $priv = rtrim(dirname($root), '/\\') . '/godyar_private';
            if (is_dir($priv)) {
                $logDir = $priv . '/logs';
            }

            if (!is_dir($logDir)) {
                if (function_exists('gdy_mkdir')) {
                    @gdy_mkdir($logDir, 0755, true);
                } else {
                    mkdir($logDir, 0755, true);
                }
            }

            $file = rtrim($logDir, '/\\') . '/security.log';
            $row = [
                'ts'    => date('Y-m-d H:i:s'),
                'ip'    => $_SERVER['REMOTE_ADDR'] ?? '',
                'uid'   => $_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? null),
                'event' => $event,
                'ctx'   => $context,
            ];

            @file_put_contents(
                $file,
                json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
                FILE_APPEND | LOCK_EX
            );
        } catch (Throwable) {
            // ignore
        }
    }
}
