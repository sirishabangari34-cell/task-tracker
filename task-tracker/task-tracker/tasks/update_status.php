<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash('warning', 'Status can only be changed from the dashboard.');
    redirect('dashboard.php');
}
require_csrf('dashboard.php');

$back   = back_query(input_str($_POST, 'back'));
$id     = valid_id($_POST['id'] ?? null);
$status = input_str($_POST, 'status');

if ($id === null || !in_array($status, TASK_STATUSES, true)) {
    flash('danger', 'Invalid task or status.');
    redirect('dashboard.php' . $back);
}

// AUTHORIZATION: confirm the task belongs to the logged-in user before changing anything.
$task = find_user_task($id, $user['id']);
if ($task === null) {
    flash('danger', 'Task not found, or you do not have permission to change it.');
    redirect('dashboard.php' . $back);
}

$stmt = db()->prepare('UPDATE tasks SET status = :status WHERE id = :id AND user_id = :uid');
$stmt->execute([':status' => $status, ':id' => $id, ':uid' => $user['id']]);

flash('success', 'Status updated to “' . $status . '”.');
redirect('dashboard.php' . $back);
