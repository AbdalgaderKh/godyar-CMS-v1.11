<?php

require_once __DIR__ . '/../_admin_guard.php';
// متوقع وجود المتغيرات التالية قبل تضمين هذا الملف:
// $mode = 'create' أو 'edit'
// $values = [ 'title' => '', 'slug' => '', 'content' => '', 'status' => 'draft', 'meta_title' => '', 'meta_description' => '', 'is_system' => 0 ]
// $errors = []  (مصفوفة أخطاء نصية حسب الحقول)
?>
<div class="card shadow-sm border-0">
  <div class="card-body">
    <div class="mb-3">
      <label class="form-label"><?php echo h(__('t_3463295a54', 'عنوان الصفحة')); ?> <span class="text-danger">*</span></label>
      <input type="text" name="title" class="form-control <?php echo isset($errors['title']) ? 'is-invalid' : ''; ?>"
             value="<?php echo htmlspecialchars($values['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
      <?php if (!empty($errors['title'])): ?>
        <div class="invalid-feedback"><?php echo htmlspecialchars($errors['title'], ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>
    <hr>

    <div class="mb-3">
      <label class="form-label">
        <?php echo h(__('t_0781965540', 'الرابط (Slug)')); ?>
        <span class="text-muted small d-block"><?php echo h(__('t_c6a2240b97', 'يُستخدم في الرابط مثل: /page/slug-هنا')); ?></span>
      </label>
      <input type="text" name="slug" class="form-control <?php echo isset($errors['slug']) ? 'is-invalid' : ''; ?>"
             dir="ltr"
             value="<?php echo htmlspecialchars($values['slug'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
      <?php if (!empty($errors['slug'])): ?>
        <div class="invalid-feedback"><?php echo htmlspecialchars($errors['slug'], ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>
    </div>

    <div class="mb-3">
      <label class="form-label"><?php echo h(__('t_e261adf643', 'محتوى الصفحة')); ?></label>
      <textarea name="content" rows="8" class="form-control"><?php echo htmlspecialchars($values['content'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
    </div>

    <div class="row">
      <div class="col-md-4 mb-3">
        <label class="form-label"><?php echo h(__('t_1253eb5642', 'الحالة')); ?></label>
        <select name="status" class="form-select">
          <option value="draft"     <?php echo ($values['status'] ?? '') === 'draft' ? 'selected' : ''; ?>><?php echo h(__('t_9071af8f2d', 'مسودة')); ?></option>
          <option value="published" <?php echo ($values['status'] ?? '') === 'published' ? 'selected' : ''; ?>><?php echo h(__('t_c67d973434', 'منشورة')); ?></option>
        </select>
      </div>
      <div class="col-md-4 mb-3">
        <label class="form-label"><?php echo h(__('t_12d1224b79', 'صفحة نظامية؟')); ?></label>
        <select name="is_system" class="form-select">
          <option value="0" <?php echo !empty($values['is_system']) ? '' : 'selected'; ?>><?php echo h(__('t_b27ea934ef', 'لا')); ?></option>
          <option value="1" <?php echo !empty($values['is_system']) ? 'selected' : ''; ?>><?php echo h(__('t_e1dadf4c7c', 'نعم')); ?></option>
        </select>
      </div>
    </div>

    <hr>

      <a href="index.php" class="btn btn-outline-secondary btn-sm">
        <svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="#more-h"></use></svg> <?php echo h(__('t_b6a95f6cdd', 'رجوع للقائمة')); ?>
      </a>

    <div class="mb-3">
      <label class="form-label"><?php echo h(__('t_6267a6f940', 'عنوان الميتا (Meta Title)')); ?></label>
      <input type="text" name="meta_title" class="form-control"
             value="<?php echo htmlspecialchars($values['meta_title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    </div>

    <div class="mb-3">
      <label class="form-label"><?php echo h(__('t_f53c7c0b21', 'وصف الميتا (Meta Description)')); ?></label>
      <textarea name="meta_description" rows="2" class="form-control"><?php echo htmlspecialchars($values['meta_description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
    </div>

    <div class="d-flex justify-content-between align-items-center">
      <a href="index.php" class="btn btn-outline-secondary btn-sm">
        <svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="#more-h"></use></svg> <?php echo h(__('t_b6a95f6cdd', 'رجوع للقائمة')); ?>
      </a>
      <button type="submit" class="btn btn-primary btn-sm">
        <svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="#more-h"></use></svg>
        <?php echo $mode === 'edit' ? __('t_02f31ae27c', 'حفظ التغييرات') : __('t_1c7c16fd30', 'حفظ الصفحة'); ?>
      </button>
    </div>
  </div>
</div>
