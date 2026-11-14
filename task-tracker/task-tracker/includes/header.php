<?php
/**
 * Page header. Expected variables (set by the including page):
 *   $layout    'app' (sidebar + topbar) or 'auth' (bare page)   default 'app'
 *   $pageTitle text for <title> and the topbar
 *   $active    sidebar item to highlight: dashboard | add | profile
 */
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Forbidden');
}
$layout    = $layout    ?? 'app';
$pageTitle = $pageTitle ?? 'Task Tracker';
$active    = $active    ?? '';
$authUser  = $layout === 'app' ? current_user() : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> · TaskFlow</title>
    <link rel="icon" type="image/svg+xml" href="<?= e(url('assets/img/favicon.svg')) ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body class="<?= $layout === 'auth' ? 'auth-body' : 'app-body' ?>">
<?php if ($layout === 'app' && $authUser): ?>
<div class="app-shell">
    <?php require ROOT_PATH . '/includes/sidebar.php'; ?>

    <div class="app-main">
        <header class="topbar">
            <button class="btn btn-light btn-icon d-lg-none" type="button" data-bs-toggle="offcanvas"
                    data-bs-target="#sidebar" aria-controls="sidebar" aria-label="Open menu">
                <i class="bi bi-list fs-4"></i>
            </button>
            <h1 class="topbar-title"><?= e($pageTitle) ?></h1>
            <div class="ms-auto d-flex align-items-center gap-2">
                <a class="btn btn-primary btn-sm d-none d-sm-inline-flex align-items-center gap-1" href="<?= e(url('tasks/add.php')) ?>">
                    <i class="bi bi-plus-lg"></i> New task
                </a>
                <div class="dropdown">
                    <button class="avatar-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"
                            aria-label="Account menu">
                        <span class="avatar"><?= e(initials($authUser['name'])) ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li class="px-3 py-2">
                            <div class="fw-semibold"><?= e($authUser['name']) ?></div>
                            <div class="small text-muted"><?= e($authUser['email']) ?></div>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= e(url('profile.php')) ?>"><i class="bi bi-person me-2"></i>Profile</a></li>
                        <li>
                            <form method="post" action="<?= e(url('auth/logout.php')) ?>">
                                <?= csrf_field() ?>
                                <button class="dropdown-item text-danger" type="submit"><i class="bi bi-box-arrow-right me-2"></i>Sign out</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <main class="app-content">
            <?php render_flashes(); ?>
<?php endif; ?>
