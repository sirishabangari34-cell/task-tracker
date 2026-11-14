<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_login();
$pdo  = db();

$errors = ['profile' => [], 'password' => []];
$name   = $user['name'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('profile.php');
    $action = input_str($_POST, 'action');

    if ($action === 'profile') {
        $name = trim(input_str($_POST, 'name'));
        if ($name === '') {
            $errors['profile']['name'] = 'Full name is required.';
        } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors['profile']['name'] = 'Name must be between 2 and 100 characters.';
        }
        if (!$errors['profile']) {
            $st = $pdo->prepare('UPDATE users SET name = :name WHERE id = :id');
            $st->execute([':name' => $name, ':id' => $user['id']]);
            flash('success', 'Profile updated successfully.');
            redirect('profile.php');
        }
    } elseif ($action === 'password') {
        $current = input_str($_POST, 'current_password');
        $new     = input_str($_POST, 'new_password');
        $confirm = input_str($_POST, 'confirm_password');

        $st = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id');
        $st->execute([':id' => $user['id']]);
        $hash = (string) $st->fetchColumn();

        if ($current === '') {
            $errors['password']['current_password'] = 'Enter your current password.';
        } elseif (!password_verify($current, $hash)) {
            $errors['password']['current_password'] = 'Current password is incorrect.';
        }
        if ($new === '') {
            $errors['password']['new_password'] = 'Enter a new password.';
        } elseif (strlen($new) < 8) {
            $errors['password']['new_password'] = 'Password must be at least 8 characters.';
        } elseif (strlen($new) > 72) {
            $errors['password']['new_password'] = 'Password must be at most 72 characters.';
        } elseif (!preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) {
            $errors['password']['new_password'] = 'Password must contain at least one letter and one number.';
        }
        if ($confirm === '' || !hash_equals($new, $confirm)) {
            $errors['password']['confirm_password'] = 'Passwords do not match.';
        }

        if (!$errors['password']) {
            $st = $pdo->prepare('UPDATE users SET password_hash = :h WHERE id = :id');
            $st->execute([':h' => password_hash($new, PASSWORD_DEFAULT), ':id' => $user['id']]);
            session_regenerate_id(true);                       // new session id after a credential change
            flash('success', 'Password changed successfully.');
            redirect('profile.php');
        }
    }
}

$cs = $pdo->prepare("SELECT COUNT(*) AS total, SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS done FROM tasks WHERE user_id = :uid");
$cs->execute([':uid' => $user['id']]);
$counts = $cs->fetch() ?: [];
$total  = (int) ($counts['total'] ?? 0);
$done   = (int) ($counts['done'] ?? 0);

$layout    = 'app';
$pageTitle = 'Profile';
$active    = 'profile';
require ROOT_PATH . '/includes/header.php';
$bad = static fn (string $form, string $f): string => isset($errors[$form][$f]) ? ' is-invalid' : '';
?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card surface-card text-center fade-up">
            <div class="card-body p-4">
                <span class="avatar avatar-xl mx-auto mb-3"><?= e(initials($user['name'])) ?></span>
                <h2 class="h5 fw-bold mb-0"><?= e($user['name']) ?></h2>
                <p class="text-muted mb-3"><?= e($user['email']) ?></p>
                <div class="row g-2 text-start">
                    <div class="col-6"><div class="mini-stat"><span>Tasks</span><strong><?= $total ?></strong></div></div>
                    <div class="col-6"><div class="mini-stat"><span>Completed</span><strong><?= $done ?></strong></div></div>
                </div>
                <p class="small text-muted mt-3 mb-0"><i class="bi bi-calendar-check me-1"></i>Member since <?= e(fmt_date($user['created_at'])) ?></p>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card surface-card mb-4 fade-up">
            <div class="card-body p-4">
                <h3 class="h5 fw-bold mb-3"><i class="bi bi-person me-2 text-primary"></i>Profile details</h3>
                <form method="post" action="<?= e(url('profile.php')) ?>" class="needs-validation" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="profile">
                    <div class="mb-3">
                        <label for="name" class="form-label fw-semibold">Full name</label>
                        <input type="text" class="form-control<?= $bad('profile', 'name') ?>" id="name" name="name" value="<?= e($name) ?>"
                               minlength="2" maxlength="100" required data-no-blank>
                        <div class="invalid-feedback"><?= isset($errors['profile']['name']) ? e($errors['profile']['name']) : 'Please enter your name (2–100 characters).' ?></div>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label fw-semibold">Email address</label>
                        <input type="email" class="form-control" id="email" value="<?= e($user['email']) ?>" disabled>
                        <div class="form-text">Your email is used to sign in and cannot be changed.</div>
                    </div>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-check2 me-1"></i>Save changes</button>
                </form>
            </div>
        </div>

        <div class="card surface-card fade-up">
            <div class="card-body p-4">
                <h3 class="h5 fw-bold mb-3"><i class="bi bi-shield-lock me-2 text-primary"></i>Change password</h3>
                <form method="post" action="<?= e(url('profile.php')) ?>" class="needs-validation" novalidate autocomplete="off">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="password">
                    <div class="mb-3">
                        <label for="current_password" class="form-label fw-semibold">Current password</label>
                        <input type="password" class="form-control<?= $bad('password', 'current_password') ?>" id="current_password" name="current_password" required autocomplete="current-password">
                        <div class="invalid-feedback"><?= isset($errors['password']['current_password']) ? e($errors['password']['current_password']) : 'Enter your current password.' ?></div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="new_password" class="form-label fw-semibold">New password</label>
                            <input type="password" class="form-control<?= $bad('password', 'new_password') ?>" id="new_password" name="new_password"
                                   minlength="8" maxlength="72" required data-password autocomplete="new-password">
                            <div class="invalid-feedback"><?= isset($errors['password']['new_password']) ? e($errors['password']['new_password']) : 'Use at least 8 characters with a letter and a number.' ?></div>
                        </div>
                        <div class="col-md-6">
                            <label for="confirm_password" class="form-label fw-semibold">Confirm new password</label>
                            <input type="password" class="form-control<?= $bad('password', 'confirm_password') ?>" id="confirm_password" name="confirm_password"
                                   required data-password-confirm autocomplete="new-password">
                            <div class="invalid-feedback"><?= isset($errors['password']['confirm_password']) ? e($errors['password']['confirm_password']) : 'Passwords must match.' ?></div>
                        </div>
                    </div>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-key me-1"></i>Update password</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
