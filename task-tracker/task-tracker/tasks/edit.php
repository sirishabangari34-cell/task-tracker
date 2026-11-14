<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_login();

$id = valid_id($_GET['id'] ?? null);
if ($id === null) {
    abort_page(404, 'Task not found', 'The task you are looking for does not exist or you do not have access to it.');
}

// AUTHORIZATION: the task is loaded with "WHERE id = ? AND user_id = <session user>".
// Another user's task id simply returns nothing -> 404, for both GET and POST.
$task = find_user_task($id, $user['id']);
if ($task === null) {
    abort_page(404, 'Task not found', 'The task you are looking for does not exist or you do not have access to it.');
}

$errors = [];
$old = [
    'title'       => (string) $task['title'],
    'description' => (string) ($task['description'] ?? ''),
    'status'      => (string) $task['status'],
    'priority'    => (string) $task['priority'],
    'due_date'    => (string) ($task['due_date'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('tasks/edit.php?id=' . $id);

    [$old, $errors] = validate_task($_POST);

    if (!$errors) {
        $stmt = db()->prepare(
            'UPDATE tasks
                SET title = :title, description = :description, status = :status,
                    priority = :priority, due_date = :due
              WHERE id = :id AND user_id = :uid'
        );
        $stmt->execute([
            ':title'       => $old['title'],
            ':description' => $old['description'] !== '' ? $old['description'] : null,
            ':status'      => $old['status'],
            ':priority'    => $old['priority'],
            ':due'         => $old['due_date'] !== '' ? $old['due_date'] : null,
            ':id'          => $id,
            ':uid'         => $user['id'],
        ]);
        flash('success', 'Task was updated successfully.');
        redirect('dashboard.php');
    }
}

$layout      = 'app';
$pageTitle   = 'Edit task';
$active      = 'dashboard';
$formAction  = url('tasks/edit.php?id=' . $id);
$submitLabel = 'Save changes';
$cancelUrl   = url('dashboard.php');
require ROOT_PATH . '/includes/header.php';
?>
<div class="row justify-content-center">
    <div class="col-xl-8 col-lg-10">
        <div class="card surface-card fade-up">
            <div class="card-body p-4 p-md-5">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <span class="stat-icon tone-indigo-solid"><i class="bi bi-pencil-square"></i></span>
                    <div>
                        <h2 class="h4 fw-bold mb-0">Edit task</h2>
                        <p class="text-muted mb-0">Created on <?= e(fmt_date($task['created_at'], 'd M Y, H:i')) ?></p>
                    </div>
                </div>
                <?php require ROOT_PATH . '/includes/task_form.php'; ?>
            </div>
        </div>
    </div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
