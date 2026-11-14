<?php
/**
 * Shared add/edit task form.
 * Needs: $old (field values), $errors, $formAction (URL), $submitLabel, $cancelUrl
 */
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Forbidden');
}
$fieldClass = static fn (string $f): string => isset($errors[$f]) ? ' is-invalid' : '';
?>
<form method="post" action="<?= e($formAction) ?>" class="needs-validation" novalidate autocomplete="off">
    <?= csrf_field() ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger d-flex gap-2" role="alert">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div>Please fix the highlighted fields and try again.</div>
        </div>
    <?php endif; ?>

    <div class="mb-3">
        <label for="title" class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
        <input type="text" class="form-control form-control-lg<?= $fieldClass('title') ?>" id="title" name="title"
               maxlength="150" required data-no-blank data-counter="titleCount"
               placeholder="e.g. Prepare weekly report" value="<?= e($old['title']) ?>">
        <div class="invalid-feedback"><?= isset($errors['title']) ? e($errors['title']) : 'Please enter a task title.' ?></div>
        <div class="form-text text-end"><span id="titleCount">0</span>/150</div>
    </div>

    <div class="mb-3">
        <label for="description" class="form-label fw-semibold">Description</label>
        <textarea class="form-control<?= $fieldClass('description') ?>" id="description" name="description" rows="5"
                  maxlength="2000" data-counter="descCount"
                  placeholder="Add details, links or notes (optional)"><?= e($old['description']) ?></textarea>
        <div class="invalid-feedback"><?= isset($errors['description']) ? e($errors['description']) : 'Description is too long.' ?></div>
        <div class="form-text text-end"><span id="descCount">0</span>/2000</div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <label for="status" class="form-label fw-semibold">Status</label>
            <select class="form-select<?= $fieldClass('status') ?>" id="status" name="status" required>
                <?php foreach (TASK_STATUSES as $s): ?>
                    <option value="<?= e($s) ?>" <?= $old['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="invalid-feedback"><?= isset($errors['status']) ? e($errors['status']) : 'Choose a status.' ?></div>
        </div>
        <div class="col-md-4">
            <label for="priority" class="form-label fw-semibold">Priority</label>
            <select class="form-select<?= $fieldClass('priority') ?>" id="priority" name="priority" required>
                <?php foreach (TASK_PRIORITIES as $p): ?>
                    <option value="<?= e($p) ?>" <?= $old['priority'] === $p ? 'selected' : '' ?>><?= e($p) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="invalid-feedback"><?= isset($errors['priority']) ? e($errors['priority']) : 'Choose a priority.' ?></div>
        </div>
        <div class="col-md-4">
            <label for="due_date" class="form-label fw-semibold">Due date</label>
            <input type="date" class="form-control<?= $fieldClass('due_date') ?>" id="due_date" name="due_date"
                   value="<?= e($old['due_date']) ?>">
            <div class="invalid-feedback"><?= isset($errors['due_date']) ? e($errors['due_date']) : 'Enter a valid date.' ?></div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <button type="submit" class="btn btn-primary btn-lg px-4"><i class="bi bi-check2-circle me-1"></i><?= e($submitLabel) ?></button>
        <a href="<?= e($cancelUrl) ?>" class="btn btn-outline-secondary btn-lg">Cancel</a>
    </div>
</form>
