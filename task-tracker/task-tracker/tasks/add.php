<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_login();

$errors = [];
$old    = ['title' => '', 'description' => '', 'status' => 'Pending', 'priority' => 'Medium', 'due_date' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('tasks/add.php');

    [$old, $errors] = validate_task($_POST);

    if (!$errors) {
        // user_id comes from the SESSION, so a task can only ever be created for the logged-in user.
        $stmt = db()->prepare(
            'INSERT INTO tasks (user_id, title, description, status, priority, due_date)
             VALUES (:uid, :title, :description, :status, :priority, :due)'
        );
        $stmt->execute([
            ':uid'         => $user['id'],
            ':title'       => $old['title'],
            ':description' => $old['description'] !== '' ? $old['description'] : null,
            ':status'      => $old['status'],
            ':priority'    => $old['priority'],
            ':due'         => $old['due_date'] !== '' ? $old['due_date'] : null,
        ]);
        flash('success', 'Task “' . $old['title'] . '” was added successfully.');
        redirect('dashboard.php');
    }
}

$layout      = 'app';
$pageTitle   = 'Add task';
$active      = 'add';
$formAction  = url('tasks/add.php');
$submitLabel = 'Create task';
$cancelUrl   = url('dashboard.php');
require ROOT_PATH . '/includes/header.php';
?>
<div class="row justify-content-center">
    <div class="col-xl-8 col-lg-10">
        <div class="card surface-card fade-up">
            <div class="card-body p-4 p-md-5">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <span class="stat-icon tone-indigo-solid"><i class="bi bi-plus-lg"></i></span>
                    <div>
                        <h2 class="h4 fw-bold mb-0">Create a new task</h2>
                        <p class="text-muted mb-0">Fields marked with * are required.</p>
                    </div>
                </div>
                <?php require ROOT_PATH . '/includes/task_form.php'; ?>
            </div>
        </div>
    </div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
