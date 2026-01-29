<?php
/**
 * سكربت تعديل أذونات الملفات والمجلدات للاستضافة المشتركة
 * المجلدات: 755
 * الملفات: 644
 */

// تعطيل وقت التنفيذ لتجنب المهلة
set_time_limit(0);

// إعداد رفع الملفات الكبيرة إذا لزم الأمر
ini_set('upload_max_filesize', '100M');
ini_set('post_max_size', '100M');

// ============================================
// إعدادات القابلية للتخصيص
// ============================================
$config = [
    'folders_permission' => 0755,
    'files_permission' => 0644,
    'excluded_dirs' => ['.', '..', '.git', '.htaccess', '.well-known'],
    'excluded_files' => ['.htaccess', 'web.config', 'php.ini'],
    'max_execution_time' => 300, // 5 دقائق
    'show_errors' => true
];

// عرض الأخطاء للتصحيح
if ($config['show_errors']) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// ============================================
// دالة الأمان - التحقق من صلاحيات الوصول
// ============================================
function checkSecurity() {
    // منع الوصول المباشر من عنوان IP خارجي إذا لزم الأمر
    $allowed_ips = ['127.0.0.1', '::1'];
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    
    // يمكنك إضافة عناوين IP مسموحة هنا
    // if (!in_array($client_ip, $allowed_ips)) {
    //     die("❌ الوصول غير مسموح من عنوان IP هذا");
    // }
    
    // التحقق من وجود كلمة مرور إذا لزم الأمر
    session_start();
    if (!isset($_SESSION['authenticated']) && isset($_POST['password'])) {
        $correct_password = 'admin123'; // غير هذه كلمة المرور!
        if ($_POST['password'] === $correct_password) {
            $_SESSION['authenticated'] = true;
        } else {
            die("❌ كلمة المرور غير صحيحة");
        }
    }
    
    if (!isset($_SESSION['authenticated'])) {
        showLoginForm();
        exit;
    }
}

// ============================================
// نموذج تسجيل الدخول
// ============================================
function showLoginForm() {
    echo '<!DOCTYPE html>
    <html dir="rtl" lang="ar">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>تسجيل الدخول - أداة تعديل الصلاحيات</title>
        <style>
            * {
                box-sizing: border-box;
                margin: 0;
                padding: 0;
                font-family: Arial, sans-serif;
            }
            
            body {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                justify-content: center;
                align-items: center;
                padding: 20px;
            }
            
            .login-container {
                background: white;
                border-radius: 15px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                width: 100%;
                max-width: 400px;
                padding: 40px;
                text-align: center;
            }
            
            h1 {
                color: #333;
                margin-bottom: 10px;
                font-size: 24px;
            }
            
            .subtitle {
                color: #666;
                margin-bottom: 30px;
                font-size: 14px;
            }
            
            input[type="password"] {
                width: 100%;
                padding: 12px 20px;
                margin: 10px 0;
                border: 2px solid #ddd;
                border-radius: 8px;
                font-size: 16px;
                transition: border 0.3s;
            }
            
            input[type="password"]:focus {
                border-color: #667eea;
                outline: none;
            }
            
            button {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                padding: 12px 30px;
                border-radius: 8px;
                font-size: 16px;
                cursor: pointer;
                width: 100%;
                margin-top: 10px;
                transition: transform 0.2s;
            }
            
            button:hover {
                transform: translateY(-2px);
            }
            
            .warning {
                background: #fff3cd;
                border: 1px solid #ffeaa7;
                color: #856404;
                padding: 15px;
                border-radius: 8px;
                margin-top: 20px;
                font-size: 12px;
                text-align: right;
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <h1>🔒 أداة تعديل الصلاحيات</h1>
            <p class="subtitle">أدخل كلمة المرور للوصول إلى الأداة</p>
            
            <form method="POST">
                <input type="password" name="password" placeholder="كلمة المرور" required>
                <button type="submit">دخول</button>
            </form>
            
            <div class="warning">
                ⚠️ <strong>تنبيه:</strong> هذه الأداة حساسة. تأكد من:
                <ul style="margin-top: 10px; padding-right: 15px;">
                    <li>تغيير كلمة المرور الافتراضية</li>
                    <li>حذف الملف بعد الاستخدام</li>
                    <li>عدم ترك الملف في الخادم</li>
                </ul>
            </div>
        </div>
    </body>
    </html>';
}

// ============================================
// دالة عرض الواجهة
// ============================================
function showInterface($config) {
    $current_dir = isset($_GET['dir']) ? $_GET['dir'] : '.';
    $current_dir = realpath($current_dir) ?: '.';
    
    // منع الخروج خارج الدليل الرئيسي للاستضافة (حسب الحاجة)
    $base_dir = realpath('.');
    if (strpos($current_dir, $base_dir) !== 0) {
        $current_dir = $base_dir;
    }
    
    // معالجة الإجراءات
    $message = '';
    if (isset($_GET['action'])) {
        switch ($_GET['action']) {
            case 'fix':
                $message = fixPermissions($current_dir, $config);
                break;
            case 'scan':
                $stats = scanDirectory($current_dir, $config);
                $message = "تم فحص: " . $stats['folders'] . " مجلد و " . $stats['files'] . " ملف";
                break;
        }
    }
    
    // الحصول على قائمة الملفات والمجلدات
    $items = scandir($current_dir);
    $folders = [];
    $files = [];
    
    foreach ($items as $item) {
        if (in_array($item, $config['excluded_dirs'])) continue;
        
        $full_path = $current_dir . '/' . $item;
        if (is_dir($full_path)) {
            $folders[] = $item;
        } else {
            $files[] = $item;
        }
    }
    
    // عرض الواجهة
    echo '<!DOCTYPE html>
    <html dir="rtl" lang="ar">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>أداة تعديل صلاحيات الملفات - الاستضافة المشتركة</title>
        <style>
            * {
                box-sizing: border-box;
                margin: 0;
                padding: 0;
                font-family: Arial, sans-serif;
            }
            
            body {
                background: #f5f7fa;
                color: #333;
                line-height: 1.6;
                padding: 20px;
            }
            
            .container {
                max-width: 1200px;
                margin: 0 auto;
                background: white;
                border-radius: 15px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.1);
                overflow: hidden;
            }
            
            header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 30px;
                text-align: center;
            }
            
            h1 {
                font-size: 28px;
                margin-bottom: 10px;
            }
            
            .subtitle {
                opacity: 0.9;
                font-size: 14px;
            }
            
            .info-box {
                background: #e8f4fd;
                border-right: 4px solid #2196F3;
                padding: 20px;
                margin: 20px;
                border-radius: 8px;
            }
            
            .permissions-info {
                display: flex;
                justify-content: space-around;
                flex-wrap: wrap;
                padding: 20px;
                background: #f9f9f9;
                margin: 20px;
                border-radius: 8px;
            }
            
            .perm-item {
                text-align: center;
                padding: 15px;
                min-width: 200px;
            }
            
            .perm-item h3 {
                color: #667eea;
                margin-bottom: 10px;
            }
            
            .current-path {
                background: #fff3cd;
                padding: 15px;
                margin: 20px;
                border-radius: 8px;
                font-family: monospace;
                word-break: break-all;
            }
            
            .controls {
                padding: 20px;
                text-align: center;
            }
            
            .btn {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                padding: 12px 30px;
                margin: 10px;
                border-radius: 8px;
                cursor: pointer;
                font-size: 16px;
                text-decoration: none;
                display: inline-block;
                transition: transform 0.2s;
            }
            
            .btn:hover {
                transform: translateY(-2px);
            }
            
            .btn-danger {
                background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            }
            
            .btn-success {
                background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            }
            
            .message {
                padding: 15px;
                margin: 20px;
                border-radius: 8px;
                text-align: center;
            }
            
            .success {
                background: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
            }
            
            .warning {
                background: #fff3cd;
                color: #856404;
                border: 1px solid #ffeaa7;
            }
            
            .directory-listing {
                margin: 20px;
                border: 1px solid #ddd;
                border-radius: 8px;
                overflow: hidden;
            }
            
            .section-title {
                background: #f8f9fa;
                padding: 15px;
                font-weight: bold;
                border-bottom: 1px solid #ddd;
            }
            
            .items-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 10px;
                padding: 15px;
            }
            
            .item {
                padding: 10px;
                border: 1px solid #eee;
                border-radius: 6px;
                transition: background 0.2s;
                cursor: pointer;
            }
            
            .item:hover {
                background: #f0f0f0;
            }
            
            .folder {
                color: #2196F3;
            }
            
            .file {
                color: #4CAF50;
            }
            
            .permission-badge {
                display: inline-block;
                background: #eee;
                padding: 2px 8px;
                border-radius: 4px;
                font-family: monospace;
                font-size: 12px;
                margin-left: 10px;
            }
            
            footer {
                text-align: center;
                padding: 20px;
                color: #666;
                font-size: 12px;
                border-top: 1px solid #eee;
                margin-top: 20px;
            }
            
            @media (max-width: 768px) {
                .permissions-info {
                    flex-direction: column;
                }
                
                .perm-item {
                    min-width: 100%;
                }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <header>
                <h1>🛠️ أداة تعديل صلاحيات الملفات والمجلدات</h1>
                <p class="subtitle">خاص بالاستضافة المشتركة - المجلدات: 755 | الملفات: 644</p>
            </header>';
    
    if ($message) {
        echo '<div class="message success">' . $message . '</div>';
    }
    
    echo '<div class="info-box">
                ⚠️ <strong>تنبيه:</strong> تأكد من فهمك للأذونات قبل التطبيق. بعض الملفات قد تحتاج أذونات مختلفة.
            </div>
            
            <div class="permissions-info">
                <div class="perm-item">
                    <h3>📁 المجلدات</h3>
                    <p>سيتم تعديلها إلى: <span class="permission-badge">755</span></p>
                    <p style="font-size: 12px; color: #666;">rwxr-xr-x (القراءة والكتابة والتنفيذ للمالك، قراءة وتنفيذ للآخرين)</p>
                </div>
                
                <div class="perm-item">
                    <h3>📄 الملفات</h3>
                    <p>سيتم تعديلها إلى: <span class="permission-badge">644</span></p>
                    <p style="font-size: 12px; color: #666;">rw-r--r-- (القراءة والكتابة للمالك، قراءة فقط للآخرين)</p>
                </div>
            </div>
            
            <div class="current-path">
                <strong>المسار الحالي:</strong><br>
                ' . $current_dir . '
            </div>
            
            <div class="controls">
                <a href="?dir=' . urlencode($current_dir) . '&action=scan" class="btn">
                    🔍 فحص الملفات
                </a>
                
                <a href="?dir=' . urlencode($current_dir) . '&action=fix" class="btn btn-success" onclick="return confirm(\'⚠️ هل أنت متأكد من تعديل أذونات جميع الملفات والمجلدات؟\')">
                    ⚡ تطبيق التعديلات
                </a>
                
                <a href="?dir=' . urlencode(dirname($current_dir)) . '" class="btn">
                    📂 مجلد أعلى
                </a>
                
                <a href="?dir=' . urlencode($base_dir) . '" class="btn">
                    🏠 المجلد الرئيسي
                </a>
            </div>';
    
    // عرض محتويات المجلد
    if (!empty($folders) || !empty($files)) {
        echo '<div class="directory-listing">';
        
        if (!empty($folders)) {
            echo '<div class="section-title">📁 المجلدات (' . count($folders) . ')</div>
                  <div class="items-grid">';
            
            foreach ($folders as $folder) {
                $folder_path = $current_dir . '/' . $folder;
                $perms = substr(sprintf('%o', fileperms($folder_path)), -4);
                echo '<div class="item folder" onclick="window.location=\'?dir=' . urlencode($folder_path) . '\'">
                        📁 ' . $folder . '
                        <span class="permission-badge">' . $perms . '</span>
                      </div>';
            }
            
            echo '</div>';
        }
        
        if (!empty($files)) {
            echo '<div class="section-title">📄 الملفات (' . count($files) . ')</div>
                  <div class="items-grid">';
            
            foreach ($files as $file) {
                if (in_array($file, $config['excluded_files'])) continue;
                
                $file_path = $current_dir . '/' . $file;
                if (is_file($file_path)) {
                    $perms = substr(sprintf('%o', fileperms($file_path)), -4);
                    $size = filesize($file_path);
                    $size_formatted = $size > 1024 ? round($size/1024, 2) . ' KB' : $size . ' B';
                    
                    echo '<div class="item file">
                            📄 ' . $file . '
                            <span class="permission-badge">' . $perms . '</span>
                            <div style="font-size: 11px; color: #888;">' . $size_formatted . '</div>
                          </div>';
                }
            }
            
            echo '</div>';
        }
        
        echo '</div>';
    } else {
        echo '<div class="message warning">المجلد فارغ</div>';
    }
    
    echo '<footer>
                ⚠️ <strong>هام:</strong> احذف هذا الملف بعد الانتهاء من استخدامه لأغراض أمنية.<br>
                تم تطويره خصيصاً للاستضافة المشتركة | ' . date('Y-m-d H:i:s') . '
            </footer>
        </div>
        
        <script>
            // تأكيد قبل التطبيق
            function confirmFix() {
                return confirm("⚠️ هل أنت متأكد من تعديل أذونات جميع الملفات والمجلدات؟\\nهذا الإجراء لا يمكن التراجع عنه!");
            }
            
            // تحديث تلقائي كل 30 ثانية لمنع المهلة
            setTimeout(function() {
                location.reload();
            }, 30000);
        </script>
    </body>
    </html>';
}

// ============================================
// دالة فحص المجلد
// ============================================
function scanDirectory($dir, $config) {
    $folders_count = 0;
    $files_count = 0;
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $item) {
        if (in_array($item->getFilename(), $config['excluded_dirs'])) {
            continue;
        }
        
        if ($item->isDir()) {
            $folders_count++;
        } else {
            if (!in_array($item->getFilename(), $config['excluded_files'])) {
                $files_count++;
            }
        }
    }
    
    return ['folders' => $folders_count, 'files' => $files_count];
}

// ============================================
// دالة تعديل الأذونات
// ============================================
function fixPermissions($dir, $config) {
    $folders_modified = 0;
    $files_modified = 0;
    $errors = [];
    
    // تعديل أذونات المجلدات أولاً
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    // المجلدات أولاً
    foreach ($iterator as $item) {
        if (in_array($item->getFilename(), $config['excluded_dirs'])) {
            continue;
        }
        
        if ($item->isDir()) {
            try {
                if (chmod($item->getPathname(), $config['folders_permission'])) {
                    $folders_modified++;
                } else {
                    $errors[] = "فشل تعديل مجلد: " . $item->getPathname();
                }
            } catch (Exception $e) {
                $errors[] = "خطأ في مجلد: " . $item->getPathname() . " - " . $e->getMessage();
            }
        }
    }
    
    // ثم الملفات
    $iterator->rewind();
    foreach ($iterator as $item) {
        if (in_array($item->getFilename(), $config['excluded_dirs'])) {
            continue;
        }
        
        if ($item->isFile()) {
            if (in_array($item->getFilename(), $config['excluded_files'])) {
                continue;
            }
            
            try {
                if (chmod($item->getPathname(), $config['files_permission'])) {
                    $files_modified++;
                } else {
                    $errors[] = "فشل تعديل ملف: " . $item->getPathname();
                }
            } catch (Exception $e) {
                $errors[] = "خطأ في ملف: " . $item->getPathname() . " - " . $e->getMessage();
            }
        }
    }
    
    // تحديث أذونات المجلد الحالي نفسه
    chmod($dir, $config['folders_permission']);
    
    $result = "✅ تم الانتهاء بنجاح!<br>";
    $result .= "📁 المجلدات المعدلة: " . $folders_modified . "<br>";
    $result .= "📄 الملفات المعدلة: " . $files_modified . "<br>";
    
    if (!empty($errors)) {
        $result .= "<br>⚠️ بعض الأخطاء:<br>" . implode("<br>", array_slice($errors, 0, 5));
        if (count($errors) > 5) {
            $result .= "<br>... والمزيد (" . (count($errors) - 5) . " خطأ)";
        }
    }
    
    return $result;
}

// ============================================
// بدء التنفيذ
// ============================================

// التحقق من الأمان
checkSecurity();

// عرض الواجهة
showInterface($config);
?>