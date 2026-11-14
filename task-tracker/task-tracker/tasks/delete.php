<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_login();

// Deleting is state-changing: POST only (the confirmation modal on the dashboard submits this form).
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash('warning', 'Tasks can only be deleted from the dashboard.');
    redirect('dashboard.php');
}
require_csrf('dashboard.php');

$back = back_query(input_str($_POST, 'back'));
$id   = valid_id($_POST['id'] ?? null);

if ($id === null) {
    flash('danger', 'Invalid task selected.');
    redirect('dashboard.php' . $back);
}

// AUTHORIZATION: ownership is part of the DELETE itself (user_id from the session).
// If the task belongs to someone else, zero rows match and nothing is deleted.
$stmt = db()->prepare('DELETE FROM tasks WHERE id = :id AND user_id = :uid');
$stmt->execute([':id' => $id, ':uid' => $user['id']]);

if ($stmt->rowCount() === 1) {
    flash('success', 'Task deleted successfully.');
} else {
    flash('danger', 'Task not found, or you do not have permission to delete it.');
}
redirect('dashboard.php' . $back);
