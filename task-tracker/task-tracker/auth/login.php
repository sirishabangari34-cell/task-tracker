<?php
require_once __DIR__ . '/../includes/bootstrap.php';
redirect_if_logged_in();

$errors = [];
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('auth/login.php');

    $email    = strtolower(trim(input_str($_POST, 'email')));
    $password = input_str($_POST, 'password');

    if ($email === '') {
        $errors['email'] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }
    if ($password === '') {
        $errors['password'] = 'Password is required.';
    }

    if (!$errors) {
        $stmt = db()->prepare('SELECT id, password_hash FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();

        // Always run password_verify() (against a dummy hash when the user is unknown)
        // so response time does not reveal whether an e-mail is registered.
        $valid = password_verify($password, $row['password_hash'] ?? DUMMY_HASH) && $row !== false;

        if ($valid) {
            session_regenerate_id(true);                 // prevents session fixation
            unset($_SESSION['csrf_token']);              // fresh CSRF token for the new session
            $_SESSION['user_id'] = (int) $row['id'];

            if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
                $up = db()->prepare('UPDATE users SET password_hash = :h WHERE id = :id');
                $up->execute([':h' => password_hash($password, PASSWORD_DEFAULT), ':id' => (int) $row['id']]);
            }
            flash('success', 'Welcome back! You are now signed in.');
            redirect('dashboard.php');
        }
        $errors['form'] = 'Invalid email or password.';
    }
}

$layout    = 'auth';
$pageTitle = 'Sign in';
require ROOT_PATH . '/includes/header.php';
?>
<div class="auth-wrap">
    <section class="auth-hero d-none d-lg-flex">
        <div class="auth-hero-inner">
            <a class="brand brand-light mb-5" href="<?= e(url('')) ?>"><span class="brand-mark"><i class="bi bi-check2-all"></i></span><span class="brand-text">TaskFlow</span></a>
            <h2 class="display-6 fw-bold mb-3">Get things done,<br>one task at a time.</h2>
            <p class="lead opacity-75 mb-4">Plan your work, track progress and never miss a deadline with a clean, private workspace.</p>
            <ul class="hero-points list-unstyled">
                <li><i class="bi bi-check-circle-fill"></i> Private tasks &mdash; only you can see them</li>
                <li><i class="bi bi-check-circle-fill"></i> Status, priority &amp; due-date tracking</li>
                <li><i class="bi bi-check-circle-fill"></i> Live dashboard with progress charts</li>
            </ul>
        </div>
    </section>

    <section class="auth-panel">
        <div class="auth-card fade-up">
            <a class="brand d-lg-none mb-4" href="<?= e(url('')) ?>"><span class="brand-mark"><i class="bi bi-check2-all"></i></span><span class="brand-text text-dark">TaskFlow</span></a>
            <h1 class="h3 fw-bold mb-1">Welcome back</h1>
            <p class="text-muted mb-4">Sign in to manage your tasks.</p>

            <?php render_flashes(); ?>
            <?php if (isset($errors['form'])): ?>
                <div class="alert alert-danger d-flex gap-2" role="alert"><i class="bi bi-exclamation-triangle-fill"></i><div><?= e($errors['form']) ?></div></div>
            <?php endif; ?>

            <form method="post" action="<?= e(url('auth/login.php')) ?>" class="needs-validation" novalidate>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label for="email" class="form-label fw-semibold">Email address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control<?= isset($errors['email']) ? ' is-invalid' : '' ?>" id="email" name="email"
                               value="<?= e($email) ?>" placeholder="you@example.com" required autocomplete="username" autofocus>
                        <div class="invalid-feedback"><?= isset($errors['email']) ? e($errors['email']) : 'Please enter a valid email address.' ?></div>
                    </div>
                </div>
                <div class="mb-4">
                    <label for="password" class="form-label fw-semibold">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control<?= isset($errors['password']) ? ' is-invalid' : '' ?>" id="password" name="password"
                               placeholder="Your password" required autocomplete="current-password">
                        <button class="btn btn-outline-secondary" type="button" data-toggle-password="password" aria-label="Show or hide password"><i class="bi bi-eye"></i></button>
                        <div class="invalid-feedback"><?= isset($errors['password']) ? e($errors['password']) : 'Please enter your password.' ?></div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-lg w-100"><i class="bi bi-box-arrow-in-right me-1"></i> Sign in</button>
            </form>

            <p class="text-center text-muted mt-4 mb-0">New here? <a href="<?= e(url('auth/register.php')) ?>" class="fw-semibold">Create an account</a></p>
        </div>
    </section>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
