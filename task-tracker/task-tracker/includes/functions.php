<?php
/**
 * Shared helpers: escaping, URLs, sessions, flash messages, CSRF,
 * authentication guards, task validation and view helpers.
 */
declare(strict_types=1);

if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Forbidden');
}

const TASK_STATUSES       = ['Pending', 'In Progress', 'Completed'];
const TASK_PRIORITIES     = ['Low', 'Medium', 'High'];
const SESSION_IDLE_SECONDS = 1800;   // auto-logout after 30 min of inactivity
const PER_PAGE            = 8;       // tasks per page on the dashboard
// Valid bcrypt hash used to keep login timing equal when an e-mail does not exist.
const DUMMY_HASH          = '$2y$10$WQIOOvS7Yz8M63/LiLje2eR0AiKoTjBuwzjZ3J.yrn1AE9mM7.hYW';

/* ------------------------------------------------------------------ */
/*  Output escaping & URLs                                             */
/* ------------------------------------------------------------------ */

/** Escape any user-generated value before printing it into HTML (XSS defence). */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** URL path of the project folder, e.g. "/task-tracker" (or "" when it is the web root). */
function base_url(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    if (defined('BASE_URL')) {                      // optional manual override
        return $base = rtrim((string) BASE_URL, '/');
    }
    $docRoot = !empty($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    $root    = realpath(ROOT_PATH);
    if ($docRoot !== false && $root !== false && stripos($root, $docRoot) === 0) {
        return $base = rtrim(str_replace('\\', '/', substr($root, strlen($docRoot))), '/');
    }
    return $base = '';
}

function url(string $path = ''): string
{
    return base_url() . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

/* ------------------------------------------------------------------ */
/*  Session & security headers                                         */
/* ------------------------------------------------------------------ */

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('TTSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => base_url() . '/',
        'secure'   => $https,
        'httponly' => true,          // JavaScript cannot read the cookie
        'samesite' => 'Lax',         // not sent on cross-site POSTs
    ]);
    session_start();

    // Idle timeout
    $now = time();
    if (isset($_SESSION['last_activity']) && ($now - (int) $_SESSION['last_activity']) > SESSION_IDLE_SECONDS) {
        $wasLoggedIn = isset($_SESSION['user_id']);
        $_SESSION = [];
        session_regenerate_id(true);
        if ($wasLoggedIn) {
            flash('warning', 'You were signed out after 30 minutes of inactivity. Please sign in again.');
        }
    }
    $_SESSION['last_activity'] = $now;
}

function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; "
         . "script-src 'self' https://cdn.jsdelivr.net; "
         . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; "
         . "font-src 'self' https://cdn.jsdelivr.net data:; "
         . "img-src 'self' data:; "
         . "form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
    // Pages contain private data -> never cache them.
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
}

/* ------------------------------------------------------------------ */
/*  Flash messages                                                     */
/* ------------------------------------------------------------------ */

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function render_flashes(): void
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    $icons = ['success' => 'check-circle-fill', 'danger' => 'exclamation-triangle-fill',
              'warning' => 'exclamation-circle-fill', 'info' => 'info-circle-fill'];
    foreach ($items as $f) {
        $type = in_array($f['type'], ['success', 'danger', 'warning', 'info'], true) ? $f['type'] : 'info';
        echo '<div class="alert alert-' . $type . ' alert-dismissible fade show d-flex align-items-center gap-2" role="alert"'
           . ($type === 'success' ? ' data-auto-dismiss' : '') . '>'
           . '<i class="bi bi-' . $icons[$type] . '"></i><div class="flex-grow-1">' . e($f['message']) . '</div>'
           . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    }
}

/* ------------------------------------------------------------------ */
/*  CSRF protection                                                    */
/* ------------------------------------------------------------------ */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Stops the request unless a valid CSRF token was submitted with the POST. */
function require_csrf(string $fallbackPath): void
{
    $known = $_SESSION['csrf_token'] ?? '';
    $sent  = $_POST['csrf_token'] ?? '';
    if (!is_string($known) || $known === '' || !is_string($sent) || !hash_equals($known, $sent)) {
        flash('danger', 'Security check failed (invalid or expired form token). Please try again.');
        redirect($fallbackPath);
    }
}

/* ------------------------------------------------------------------ */
/*  Authentication                                                     */
/* ------------------------------------------------------------------ */

function current_user(): ?array
{
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }
    $id = $_SESSION['user_id'] ?? null;
    if (!is_int($id)) {
        return $cache = null;
    }
    $stmt = db()->prepare('SELECT id, name, email, created_at FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $user = $stmt->fetch();
    if (!$user) {                       // account was deleted -> drop the session
        unset($_SESSION['user_id']);
        return $cache = null;
    }
    $user['id'] = (int) $user['id'];
    return $cache = $user;
}

/** Guard for protected pages: redirects guests to the login page. */
function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        flash('warning', 'Please sign in to continue.');
        redirect('auth/login.php');
    }
    return $user;
}

function redirect_if_logged_in(): void
{
    if (current_user() !== null) {
        redirect('dashboard.php');
    }
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?? 'Lax',
        ]);
    }
    session_destroy();
    session_start();                    // fresh, empty session for the flash message
    session_regenerate_id(true);
}

/* ------------------------------------------------------------------ */
/*  Input helpers & validation                                         */
/* ------------------------------------------------------------------ */

/** Safe string getter for $_GET / $_POST values (arrays => ''). */
function input_str(array $source, string $key): string
{
    return isset($source[$key]) && is_string($source[$key]) ? $source[$key] : '';
}

/** Positive integer id from a request value, or null. */
function valid_id(mixed $value): ?int
{
    if (!is_string($value) && !is_int($value)) {
        return null;
    }
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $id === false ? null : $id;
}

/**
 * Validate task form input (server side — the frontend checks are only for convenience).
 * @return array{0: array<string,string>, 1: array<string,string>}  [cleaned values, errors]
 */
function validate_task(array $in): array
{
    $errors   = [];
    $title    = trim(input_str($in, 'title'));
    $desc     = trim(input_str($in, 'description'));
    $status   = input_str($in, 'status')   !== '' ? input_str($in, 'status')   : 'Pending';
    $priority = input_str($in, 'priority') !== '' ? input_str($in, 'priority') : 'Medium';
    $due      = trim(input_str($in, 'due_date'));

    if ($title === '') {
        $errors['title'] = 'Task title is required.';
    } elseif (mb_strlen($title) > 150) {
        $errors['title'] = 'Title must be 150 characters or fewer.';
    }
    if (mb_strlen($desc) > 2000) {
        $errors['description'] = 'Description must be 2000 characters or fewer.';
    }
    if (!in_array($status, TASK_STATUSES, true)) {
        $errors['status'] = 'Please choose a valid status.';
    }
    if (!in_array($priority, TASK_PRIORITIES, true)) {
        $errors['priority'] = 'Please choose a valid priority.';
    }
    if ($due !== '') {
        $d   = DateTime::createFromFormat('!Y-m-d', $due);
        $err = DateTime::getLastErrors();
        if (!$d || ($err && ($err['warning_count'] > 0 || $err['error_count'] > 0)) || $d->format('Y-m-d') !== $due) {
            $errors['due_date'] = 'Please enter a valid due date.';
        }
    }

    return [[
        'title' => $title, 'description' => $desc, 'status' => $status,
        'priority' => $priority, 'due_date' => $due,
    ], $errors];
}

/**
 * Fetch a task ONLY if it belongs to the given user.
 * This is the single authorization gate used by edit, delete and status update:
 * the user_id comes from the server-side session, never from the request.
 */
function find_user_task(int $taskId, int $userId): ?array
{
    $stmt = db()->prepare('SELECT * FROM tasks WHERE id = :id AND user_id = :uid LIMIT 1');
    $stmt->execute([':id' => $taskId, ':uid' => $userId]);
    $task = $stmt->fetch();
    return $task ?: null;
}

/** Rebuild a safe dashboard query string (whitelisted keys only) from a hidden form field. */
function back_query(string $raw): string
{
    if ($raw === '') {
        return '';
    }
    parse_str($raw, $parsed);
    $out = [];
    foreach (['q', 'status', 'priority', 'sort', 'page'] as $key) {
        if (isset($parsed[$key]) && is_string($parsed[$key]) && $parsed[$key] !== '') {
            $out[$key] = $parsed[$key];
        }
    }
    return $out ? '?' . http_build_query($out) : '';
}

/** Renders a styled error page (404 / 405 …) and stops. */
function abort_page(int $code, string $title, string $message): never
{
    http_response_code($code);
    $layout    = current_user() ? 'app' : 'auth';
    $pageTitle = $title;
    $active    = '';
    require ROOT_PATH . '/includes/header.php';
    ?>
    <div class="<?= $layout === 'auth' ? 'container py-5' : '' ?>">
        <div class="card surface-card text-center p-5 mx-auto" style="max-width:520px">
            <div class="empty-icon mb-3"><i class="bi bi-shield-exclamation"></i></div>
            <h1 class="h4 mb-2"><?= e($title) ?></h1>
            <p class="text-muted mb-4"><?= e($message) ?></p>
            <a class="btn btn-primary mx-auto" href="<?= e(url($layout === 'app' ? 'dashboard.php' : 'auth/login.php')) ?>">
                <i class="bi bi-arrow-left me-1"></i>Back to <?= $layout === 'app' ? 'dashboard' : 'sign in' ?>
            </a>
        </div>
    </div>
    <?php
    require ROOT_PATH . '/includes/footer.php';
    exit;
}

/* ------------------------------------------------------------------ */
/*  View helpers                                                       */
/* ------------------------------------------------------------------ */

function status_slug(string $status): string
{
    return strtolower(str_replace(' ', '-', $status));
}

function status_icon(string $status): string
{
    return ['Pending' => 'hourglass-split', 'In Progress' => 'arrow-repeat', 'Completed' => 'check-circle-fill'][$status] ?? 'circle';
}

function fmt_date(?string $date, string $format = 'd M Y'): string
{
    if ($date === null || $date === '') {
        return '—';
    }
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : '—';
}

function is_overdue(?string $due, string $status): bool
{
    return $due !== null && $due !== '' && $status !== 'Completed' && $due < date('Y-m-d');
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $out   = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $out .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $out !== '' ? $out : '?';
}
