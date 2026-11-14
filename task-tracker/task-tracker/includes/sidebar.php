<?php
/** Sidebar navigation (offcanvas on phones/tablets, fixed column on desktop). */
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Forbidden');
}
$navItems = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'speedometer2',  'href' => 'dashboard.php'],
    ['key' => 'add',       'label' => 'Add task',  'icon' => 'plus-circle',   'href' => 'tasks/add.php'],
    ['key' => 'profile',   'label' => 'Profile',   'icon' => 'person-gear',   'href' => 'profile.php'],
];
?>
<aside class="sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="sidebar" aria-labelledby="sidebarLabel">
    <div class="sidebar-inner">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <a class="brand" href="<?= e(url('dashboard.php')) ?>" id="sidebarLabel">
                <span class="brand-mark"><i class="bi bi-check2-all"></i></span>
                <span class="brand-text">TaskFlow</span>
            </a>
            <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas"
                    data-bs-target="#sidebar" aria-label="Close"></button>
        </div>

        <div class="nav-caption">Menu</div>
        <nav class="nav flex-column gap-1">
            <?php foreach ($navItems as $item): ?>
                <a class="side-link <?= $active === $item['key'] ? 'active' : '' ?>" href="<?= e(url($item['href'])) ?>">
                    <i class="bi bi-<?= e($item['icon']) ?>"></i><span><?= e($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="nav-caption mt-4">Quick filters</div>
        <nav class="nav flex-column gap-1">
            <a class="side-link" href="<?= e(url('dashboard.php#tasks')) ?>"><i class="bi bi-list-task"></i><span>All tasks</span></a>
            <?php foreach (TASK_STATUSES as $s): ?>
                <a class="side-link" href="<?= e(url('dashboard.php?' . http_build_query(['status' => $s]) . '#tasks')) ?>">
                    <i class="bi bi-<?= e(status_icon($s)) ?>"></i><span><?= e($s) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-footer">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="avatar"><?= e(initials($authUser['name'])) ?></span>
                <div class="min-w-0">
                    <div class="text-white fw-semibold text-truncate"><?= e($authUser['name']) ?></div>
                    <div class="small text-white-50 text-truncate"><?= e($authUser['email']) ?></div>
                </div>
            </div>
            <form method="post" action="<?= e(url('auth/logout.php')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn-outline-light btn-sm w-100" type="submit">
                    <i class="bi bi-box-arrow-right me-1"></i> Sign out
                </button>
            </form>
        </div>
    </div>
</aside>
