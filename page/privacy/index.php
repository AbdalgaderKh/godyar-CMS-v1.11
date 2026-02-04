<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

if (!function_exists('h')) {
    function h($v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// Page meta (used by the shared frontend header)
$pageTitle       = "سياسة الخصوصية وشروط الاستخدام";
$metaTitle       = $pageTitle;
$metaDescription = "شروط استخدام السكربت وسياسة الخصوصية.";

// Optional helpers used by some templates
$currentLang = $currentLang ?? 'ar';
$pageDir = 'rtl';

require_once __DIR__ . '/../../frontend/views/partials/header.php';
?>
<main class="gdy-page" style="padding:18px 0 42px;">
  <div class="container">
    <?php
      $html = <<<GDYHTML
<div class="container">
        <div class="header">
            <h1>شروط الاستخدام وسياسة الخصوصية</h1>
            <p class="subtitle">— <span class="cms-name">Godyar CMS</span> —</p>
        </div>
        
        <div class="content-wrapper">
            <!-- قسم شروط الاستخدام -->
            <div class="section-card">
                <div class="section-header">
                    <div class="section-icon">
                        <i class="fas fa-file-contract"></i>
                    </div>
                    <h2>أولًا: شروط استخدام السكربت</h2>
                </div>
                
                <p>باستخدامك لهذا السكربت أو أي نسخة مشتقة منه، فأنت توافق على الشروط التالية:</p>
                
                <div class="section-title">
                    <i class="fas fa-balance-scale"></i>
                    الاستخدام المشروع
                </div>
                <p>يجب استخدام السكربت في إطار قانوني وأخلاقي، ويُمنع استخدامه في نشر محتوى مخالف للأنظمة أو ينتهك حقوق الآخرين.</p>
                
                <div class="section-title">
                    <i class="fas fa-user-shield"></i>
                    المسؤولية عن المحتوى
                </div>
                <p>المسؤولية الكاملة عن المحتوى المنشور (نصوص، صور، روابط، فيديوهات) تقع على عاتق مالك الموقع أو فريق الإدارة. مطوّر السكربت غير مسؤول عن أي محتوى يتم نشره بواسطة المستخدمين.</p>
                
                <div class="section-title">
                    <i class="fas fa-copyright"></i>
                    الملكية الفكرية
                </div>
                <p>يجب احترام حقوق الملكية الفكرية للغير. يمنع نسخ أو إعادة نشر محتوى محمي دون إذن أو ترخيص مناسب.</p>
                
                <div class="section-title">
                    <i class="fas fa-shield-alt"></i>
                    الأمان والصيانة
                </div>
                <p>يوصى بتحديث السكربت باستمرار وتحديثات الخادم (PHP/قاعدة البيانات) وإعدادات الأمان. أي إهمال في الصيانة قد يؤدي لثغرات أو أعطال.</p>
                
                <div class="highlight-box">
                    <p><strong>تعديل الشروط:</strong> قد يتم تحديث هذه الشروط عند الحاجة. استمرار استخدام السكربت يعني موافقتك على أي تحديثات مستقبلية.</p>
                </div>
            </div>
            
            <!-- قسم سياسة الخصوصية -->
            <div class="section-card">
                <div class="section-header">
                    <div class="section-icon">
                        <i class="fas fa-user-lock"></i>
                    </div>
                    <h2>ثانيًا: سياسة الخصوصية</h2>
                </div>
                
                <p>نحرص على حماية بيانات المستخدمين وخصوصيتهم، وتشمل السياسة ما يلي:</p>
                
                <div class="section-title">
                    <i class="fas fa-database"></i>
                    البيانات التي قد نجمعها
                </div>
                
                <ul class="privacy-list">
                    <li>
                        <div class="list-number">1</div>
                        بيانات الحساب عند التسجيل (مثل البريد الإلكتروني واسم المستخدم).
                    </li>
                    <li>
                        <div class="list-number">2</div>
                        بيانات تقنية مثل عنوان IP، نوع المتصفح، وسجلات الوصول لأغراض الحماية وتحسين الأداء.
                    </li>
                    <li>
                        <div class="list-number">3</div>
                        رسائل التواصل التي يرسلها المستخدم عبر نموذج "اتصل بنا".
                    </li>
                </ul>
                
                <div class="section-title">
                    <i class="fas fa-cogs"></i>
                    استخدام البيانات
                </div>
                
                <ul class="terms-list">
                    <li>
                        <div class="list-number">1</div>
                        تحسين تجربة الاستخدام وتقديم الخدمات الأساسية للموقع.
                    </li>
                    <li>
                        <div class="list-number">2</div>
                        حماية الموقع من إساءة الاستخدام ومحاولات الاختراق.
                    </li>
                    <li>
                        <div class="list-number">3</div>
                        الرد على الاستفسارات والرسائل.
                    </li>
                </ul>
                
                <div class="section-title">
                    <i class="fas fa-share-alt"></i>
                    مشاركة البيانات
                </div>
                <p>لا يتم بيع أو تأجير بيانات المستخدمين لطرف ثالث. قد تتم مشاركة البيانات فقط عند وجود متطلبات قانونية أو لحماية حقوق الموقع.</p>
                
                <div class="section-title">
                    <i class="fas fa-cookie-bite"></i>
                    ملفات تعريف الارتباط (Cookies)
                </div>
                <p>قد يستخدم الموقع ملفات تعريف الارتباط لتحسين التجربة (مثل تذكر الجلسة/تفضيلات اللغة). يمكنك تعطيلها من إعدادات المتصفح، وقد يؤثر ذلك على بعض الميزات.</p>
                
                <div class="section-title">
                    <i class="fas fa-lock"></i>
                    حماية البيانات
                </div>
                <p>نستخدم إجراءات تنظيمية وتقنية مناسبة لحماية البيانات، لكن لا يمكن ضمان الأمان بنسبة 100% على الإنترنت.</p>
                
                <div class="note-box">
                    <div class="note-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="section-title" style="border: none; padding: 0; margin: 0 0 10px 0;">
                        حقوق المستخدم
                    </div>
                    <p>يمكن للمستخدم طلب تعديل/حذف بياناته حسب الإمكانيات المتاحة في النظام، أو عبر التواصل مع إدارة الموقع.</p>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p><span class="cms-name">Godyar CMS</span> - نظام إدارة محتوى إخباري متعدد اللغات</p>
            <div class="last-updated">
                آخر تحديث: جمادى الأولى 1445هـ
            </div>
        </div>
    </div>

    <script>
        // إضافة تأثيرات تفاعلية بسيطة
        document.addEventListener('DOMContentLoaded', function() {
            const listItems = document.querySelectorAll('.terms-list li, .privacy-list li');
            
            listItems.forEach(item => {
                item.addEventListener('mouseenter', function() {
                    this.style.boxShadow = '0 5px 15px rgba(0, 0, 0, 0.1)';
                });
                
                item.addEventListener('mouseleave', function() {
                    this.style.boxShadow = 'none';
                });
            });
            
            // إضافة تاريخ التحديث التلقائي
            const hijriMonths = ["محرم", "صفر", "ربيع الأول", "ربيع الآخر", "جمادى الأولى", "جمادى الآخرة", "رجب", "شعبان", "رمضان", "شوال", "ذو القعدة", "ذو الحجة"];
            const today = new Date();
            const hijriYear = Math.floor((today.getFullYear() - 622) * (33/32));
            const hijriMonth = hijriMonths[today.getMonth()];
            const hijriDay = Math.floor(Math.random() * 28) + 1; // تقريب للتواريخ الهجرية
            
            const lastUpdatedElement = document.querySelector('.last-updated');
            if (lastUpdatedElement) {
                lastUpdatedElement.textContent = `آخر تحديث: ${hijriMonth} ${hijriYear}هـ`;
            }
        });
    </script>
GDYHTML;
      echo $html;
    ?>
  </div>
</main>
<?php require_once __DIR__ . '/../../frontend/views/partials/footer.php'; ?>
