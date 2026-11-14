<?php
require_once __DIR__ . '/../includes/bootstrap.php';
redirect_if_logged_in();

$errors = [];
$old    = ['name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('auth/register.php');

    $name    = trim(input_str($_POST, 'name'));
    $email   = strtolower(trim(input_str($_POST, 'email')));
    $pass    = input_str($_POST, 'password');
    $confirm = input_str($_POST, 'password_confirm');
    $old     = ['name' => $name, 'email' => $email];

    if ($name === '') {
        $errors['name'] = 'Full name is required.';
    } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $errors['name'] = 'Name must be between 2 and 100 characters.';
    }

    if ($email === '') {
        $errors['email'] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    if ($pass === '') {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($pass) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    } elseif (strlen($pass) > 72) {                      // bcrypt only uses the first 72 bytes
        $errors['password'] = 'Password must be at most 72 characters.';
    } elseif (!preg_match('/[A-Za-z]/', $pass) || !preg_match('/\d/', $pass)) {
        $errors['password'] = 'Password must contain at least one letter and one number.';
    }

    if ($confirm === '') {
        $errors['password_confirm'] = 'Please confirm your password.';
    } elseif (!hash_equals($pass, $confirm)) {
        $errors['password_confirm'] = 'Passwords do not match.';
    }

    if (!$errors) {
        $pdo   = db();
        $check = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $check->execute([':email' => $email]);
        if ($check->fetch()) {
            $errors['email'] = 'An account with this email already exists.';
        } else {
            try {
                $ins = $pdo->prepare('INSERT INTO users (name, email, password_hash) VALUES (:name, :email, :hash)');
                $ins->execute([':name' => $name, ':email' => $email, ':hash' => password_hash($pass, PASSWORD_DEFAULT)]);
                flash('success', 'Account created successfully. Please sign in.');
                redirect('auth/login.php');
            } catch (PDOException $ex) {
                // Duplicate e-mail inserted by a parallel request (unique index) -> friendly message.
                if (in_array((string) $ex->getCode(), ['23000', '23505'], true)) {
                    $errors['email'] = 'An account with this email already exists.';
                } else {
                    throw $ex;
                }
            }
        }
    }
}

$layout    = 'auth';
$pageTitle = 'Create account';
require ROOT_PATH . '/includes/header.php';
$bad = static fn (string $f): string => isset($errors[$f]) ? ' is-invalid' : '';
?>
<div class="auth-wrap">
    <section class="auth-hero d-none d-lg-flex">
        <div class="auth-hero-inner">
            <a class="brand brand-light mb-5" href="<?= e(url('')) ?>"><span class="brand-mark"><i class="bi bi-check2-all"></i></span><span class="brand-text">TaskFlow</span></a>
            <h2 class="display-6 fw-bold mb-3">Start organising<br>your work today.</h2>
            <p class="lead opacity-75 mb-4">Create a free account in seconds. Your tasks stay private and are protected by secure authentication.</p>
            <ul class="hero-points list-unstyled">
                <li><i class="bi bi-shield-lock-fill"></i> Passwords are hashed &mdash; never stored in plain text</li>
                <li><i class="bi bi-person-lock"></i> Strict per-user task ownership</li>
                <li><i class="bi bi-bar-chart-fill"></i> Track completion with charts</li>
            </ul>
        </div>
    </section>

    <section class="auth-panel">
        <div class="auth-card fade-up">
            <a class="brand d-lg-none mb-4" href="<?= e(url('')) ?>"><span class="brand-mark"><i class="bi bi-check2-all"></i></span><span class="brand-text text-dark">TaskFlow</span></a>
            <h1 class="h3 fw-bold mb-1">Create your account</h1>
            <p class="text-muted mb-4">It only takes a minute.</p>

            <?php render_flashes(); ?>

            <form method="post" action="<?= e(url('auth/register.php')) ?>" class="needs-validation" novalidate autocomplete="off">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label for="name" class="form-label fw-semibold">Full name</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control<?= $bad('name') ?>" id="name" name="name" value="<?= e($old['name']) ?>"
                               minlength="2" maxlength="100" placeholder="Jane Doe" required data-no-blank autofocus>
                        <div class="invalid-feedback"><?= isset($errors['name']) ? e($errors['name']) : 'Please enter your name (2–100 characters).' ?></div>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label fw-semibold">Email address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control<?= $bad('email') ?>" id="email" name="email" value="<?= e($old['email']) ?>"
                               maxlength="150" placeholder="you@example.com" required>
                        <div class="invalid-feedback"><?= isset($errors['email']) ? e($errors['email']) : 'Please enter a valid email address.' ?></div>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label fw-semibold">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control<?= $bad('password') ?>" id="password" name="password"
                               minlength="8" maxlength="72" placeholder="At least 8 characters" required data-password autocomplete="new-password">
                        <button class="btn btn-outline-secondary" type="button" data-toggle-password="password" aria-label="Show or hide password"><i class="bi bi-eye"></i></button>
                        <div class="invalid-feedback"><?= isset($errors['password']) ? e($errors['password']) : 'Use at least 8 characters with a letter and a number.' ?></div>
                    </div>
                    <div class="strength mt-2" aria-hidden="true"><div class="strength-bar" id="strengthBar"></div></div>
                    <div class="form-text" id="strengthText">Use 8+ characters with letters and numbers.</div>
                </div>
                <div class="mb-4">
                    <label for="password_confirm" class="form-label fw-semibold">Confirm password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                        <input type="password" class="form-control<?= $bad('password_confirm') ?>" id="password_confirm" name="password_confirm"
                               placeholder="Repeat your password" required data-password-confirm autocomplete="new-password">
                        <div class="invalid-feedback"><?= isset($errors['password_confirm']) ? e($errors['password_confirm']) : 'Passwords must match.' ?></div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-lg w-100"><i class="bi bi-person-plus me-1"></i> Create account</button>
            </form>

            <p class="text-center text-muted mt-4 mb-0">Already have an account? <a href="<?= e(url('auth/login.php')) ?>" class="fw-semibold">Sign in</a></p>
        </div>
    </section>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
