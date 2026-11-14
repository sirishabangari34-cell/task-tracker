<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_login();
$uid  = $user['id'];            // always taken from the session — never from the request
$pdo  = db();
$today = date('Y-m-d');

/* ---------- summary numbers (this user only) ---------- */
$stmt = $pdo->prepare(
    "SELECT COUNT(*) AS total,
            SUM(CASE WHEN status = 'Pending'     THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) AS in_progress,
            SUM(CASE WHEN status = 'Completed'   THEN 1 ELSE 0 END) AS completed,
            SUM(CASE WHEN status <> 'Completed' AND due_date IS NOT NULL AND due_date < :today THEN 1 ELSE 0 END) AS overdue
       FROM tasks WHERE user_id = :uid"
);
$stmt->execute([':today' => $today, ':uid' => $uid]);
$row   = $stmt->fetch() ?: [];
$stats = [
    'total'       => (int) ($row['total'] ?? 0),
    'pending'     => (int) ($row['pending'] ?? 0),
    'in_progress' => (int) ($row['in_progress'] ?? 0),
    'completed'   => (int) ($row['completed'] ?? 0),
    'overdue'     => (int) ($row['overdue'] ?? 0),
];
$completionRate = $stats['total'] > 0 ? (int) round($stats['completed'] / $stats['total'] * 100) : 0;

$prio = ['Low' => 0, 'Medium' => 0, 'High' => 0];
$pst  = $pdo->prepare('SELECT priority, COUNT(*) AS c FROM tasks WHERE user_id = :uid GROUP BY priority');
$pst->execute([':uid' => $uid]);
foreach ($pst->fetchAll() as $r) {
    if (isset($prio[$r['priority']])) {
        $prio[$r['priority']] = (int) $r['c'];
    }
}

/* ---------- filters: search / status / priority / sort ---------- */
$q        = mb_substr(trim(input_str($_GET, 'q')), 0, 100);
$status   = in_array(input_str($_GET, 'status'), TASK_STATUSES, true) ? input_str($_GET, 'status') : '';
$priority = in_array(input_str($_GET, 'priority'), TASK_PRIORITIES, true) ? input_str($_GET, 'priority') : '';
$sorts    = [
    'newest'   => ['Newest first',     'id DESC'],
    'oldest'   => ['Oldest first',     'id ASC'],
    'due'      => ['Due date (soonest)', '(due_date IS NULL) ASC, due_date ASC, id DESC'],
    'priority' => ['Priority (high → low)', "CASE priority WHEN 'High' THEN 1 WHEN 'Medium' THEN 2 ELSE 3 END ASC, id DESC"],
];
$sort = array_key_exists(input_str($_GET, 'sort'), $sorts) ? input_str($_GET, 'sort') : 'newest';

$where  = ['user_id = :uid'];
$params = [':uid' => $uid];
if ($q !== '') {
    // "!" is the LIKE escape character so a typed % or _ is searched literally.
    $where[]      = "title LIKE :q ESCAPE '!'";
    $params[':q'] = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q) . '%';
}
if ($status !== '') {
    $where[]           = 'status = :status';
    $params[':status'] = $status;
}
if ($priority !== '') {
    $where[]             = 'priority = :priority';
    $params[':priority'] = $priority;
}
$whereSql = implode(' AND ', $where);

$bind = static function (PDOStatement $st, array $p): void {
    foreach ($p as $k => $v) {
        $st->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
};

/* ---------- pagination ---------- */
$cst = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE $whereSql");
$bind($cst, $params);
$cst->execute();
$matching = (int) $cst->fetchColumn();
$pages    = max(1, (int) ceil($matching / PER_PAGE));
$page     = min(max(1, (int) (valid_id($_GET['page'] ?? null) ?? 1)), $pages);
$offset   = ($page - 1) * PER_PAGE;

$lst = $pdo->prepare(
    "SELECT id, title, description, status, priority, due_date, created_at
       FROM tasks WHERE $whereSql ORDER BY {$sorts[$sort][1]} LIMIT :lim OFFSET :off"
);
$bind($lst, $params);
$lst->bindValue(':lim', PER_PAGE, PDO::PARAM_INT);
$lst->bindValue(':off', $offset, PDO::PARAM_INT);
$lst->execute();
$tasks = $lst->fetchAll();

$filtersActive = ($q !== '' || $status !== '' || $priority !== '');
$queryState    = array_filter(['q' => $q, 'status' => $status, 'priority' => $priority, 'sort' => $sort === 'newest' ? '' : $sort, 'page' => $page > 1 ? (string) $page : ''],
                              static fn ($v) => $v !== '');
$pageUrl = static function (int $p) use ($queryState): string {
    $qs = $queryState;
    unset($qs['page']);
    if ($p > 1) {
        $qs['page'] = $p;
    }
    return url('dashboard.php' . ($qs ? '?' . http_build_query($qs) : '') . '#tasks');
};
$backQuery = http_build_query($queryState);

$layout     = 'app';
$pageTitle  = 'Dashboard';
$active     = 'dashboard';
$withCharts = true;
require ROOT_PATH . '/includes/header.php';

$cards = [
    ['label' => 'Total tasks',  'value' => $stats['total'],       'icon' => 'collection',     'tone' => 'indigo',  'status' => ''],
    ['label' => 'Pending',      'value' => $stats['pending'],     'icon' => 'hourglass-split', 'tone' => 'amber',   'status' => 'Pending'],
    ['label' => 'In progress',  'value' => $stats['in_progress'], 'icon' => 'arrow-repeat',   'tone' => 'sky',     'status' => 'In Progress'],
    ['label' => 'Completed',    'value' => $stats['completed'],   'icon' => 'check2-circle',  'tone' => 'emerald', 'status' => 'Completed'],
];
?>
<div class="page-head fade-up">
    <div>
        <h2 class="h4 fw-bold mb-1">Hello, <?= e(explode(' ', $user['name'])[0]) ?> 👋</h2>
        <p class="text-muted mb-0">
            <?php if ($stats['total'] === 0): ?>
                Your workspace is empty &mdash; add your first task to get started.
            <?php elseif ($stats['overdue'] > 0): ?>
                You have <strong class="text-danger"><?= $stats['overdue'] ?> overdue</strong> task<?= $stats['overdue'] === 1 ? '' : 's' ?>. Let&rsquo;s catch up!
            <?php else: ?>
                You are on track &mdash; <?= $completionRate ?>% of your tasks are complete.
            <?php endif; ?>
        </p>
    </div>
    <a class="btn btn-primary btn-lg d-inline-flex align-items-center gap-2" href="<?= e(url('tasks/add.php')) ?>"><i class="bi bi-plus-lg"></i> Add task</a>
</div>

<!-- Summary cards -->
<div class="row g-3 mb-4">
    <?php foreach ($cards as $i => $c): ?>
        <div class="col-6 col-xl-3 fade-up" style="animation-delay: <?= $i * 60 ?>ms">
            <a class="stat-card tone-<?= e($c['tone']) ?>" href="<?= e(url('dashboard.php' . ($c['status'] ? '?' . http_build_query(['status' => $c['status']]) : '') . '#tasks')) ?>">
                <span class="stat-icon"><i class="bi bi-<?= e($c['icon']) ?>"></i></span>
                <span class="stat-body">
                    <span class="stat-value" data-count="<?= (int) $c['value'] ?>"><?= (int) $c['value'] ?></span>
                    <span class="stat-label"><?= e($c['label']) ?></span>
                </span>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<!-- Charts -->
<div class="row g-3 mb-4">
    <div class="col-lg-4 fade-up">
        <div class="card surface-card h-100">
            <div class="card-body">
                <h3 class="card-title h6 fw-bold">Completion progress</h3>
                <?php if ($stats['total'] > 0): ?>
                    <div class="chart-box chart-box-sm">
                        <canvas id="statusChart" data-chart="status"
                                data-labels="<?= e(json_encode(TASK_STATUSES)) ?>"
                                data-values="<?= e(json_encode([$stats['pending'], $stats['in_progress'], $stats['completed']])) ?>"></canvas>
                        <div class="chart-center"><strong><?= $completionRate ?>%</strong><span>done</span></div>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-5"><i class="bi bi-pie-chart fs-1 d-block mb-2 opacity-50"></i>No data yet</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4 fade-up" style="animation-delay: 60ms">
        <div class="card surface-card h-100">
            <div class="card-body">
                <h3 class="card-title h6 fw-bold">Tasks by priority</h3>
                <?php if ($stats['total'] > 0): ?>
                    <div class="chart-box">
                        <canvas id="priorityChart" data-chart="priority"
                                data-labels="<?= e(json_encode(array_keys($prio))) ?>"
                                data-values="<?= e(json_encode(array_values($prio))) ?>"></canvas>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-5"><i class="bi bi-bar-chart fs-1 d-block mb-2 opacity-50"></i>No data yet</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4 fade-up" style="animation-delay: 120ms">
        <div class="card surface-card h-100">
            <div class="card-body">
                <h3 class="card-title h6 fw-bold">Overview</h3>
                <div class="d-flex justify-content-between small mb-1"><span class="text-muted">Overall completion</span><strong><?= $completionRate ?>%</strong></div>
                <div class="progress mb-4" role="progressbar" aria-label="Completion" aria-valuenow="<?= $completionRate ?>" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar progress-gradient" style="width: <?= $completionRate ?>%"></div>
                </div>
                <ul class="list-unstyled overview-list mb-0">
                    <li><span><i class="bi bi-exclamation-octagon text-danger"></i> Overdue</span><strong><?= $stats['overdue'] ?></strong></li>
                    <li><span><i class="bi bi-flag-fill text-danger"></i> High priority</span><strong><?= $prio['High'] ?></strong></li>
                    <li><span><i class="bi bi-flag-fill text-warning"></i> Medium priority</span><strong><?= $prio['Medium'] ?></strong></li>
                    <li><span><i class="bi bi-flag-fill text-success"></i> Low priority</span><strong><?= $prio['Low'] ?></strong></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Task list -->
<div class="card surface-card fade-up" id="tasks">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h3 class="h5 fw-bold mb-0">My tasks <span class="badge text-bg-light border ms-1"><?= $matching ?></span></h3>
        </div>

        <form method="get" action="<?= e(url('dashboard.php')) ?>#tasks" class="filter-bar mb-3" role="search">
            <div class="input-group search-group">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="search" class="form-control" name="q" value="<?= e($q) ?>" maxlength="100" placeholder="Search tasks by title…" aria-label="Search tasks by title">
            </div>
            <select class="form-select" name="status" aria-label="Filter by status" data-autosubmit>
                <option value="">All statuses</option>
                <?php foreach (TASK_STATUSES as $s): ?>
                    <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select" name="priority" aria-label="Filter by priority" data-autosubmit>
                <option value="">All priorities</option>
                <?php foreach (TASK_PRIORITIES as $p): ?>
                    <option value="<?= e($p) ?>" <?= $priority === $p ? 'selected' : '' ?>><?= e($p) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select" name="sort" aria-label="Sort tasks" data-autosubmit>
                <?php foreach ($sorts as $key => [$label]): ?>
                    <option value="<?= e($key) ?>" <?= $sort === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="d-flex gap-2">
                <button class="btn btn-primary" type="submit"><i class="bi bi-funnel me-1"></i>Apply</button>
                <?php if ($filtersActive || $sort !== 'newest'): ?>
                    <a class="btn btn-outline-secondary" href="<?= e(url('dashboard.php#tasks')) ?>">Reset</a>
                <?php endif; ?>
            </div>
        </form>

        <?php if (!$tasks): ?>
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-<?= $filtersActive ? 'search' : 'clipboard-check' ?>"></i></div>
                <?php if ($filtersActive): ?>
                    <h4 class="h5">No tasks match your filters</h4>
                    <p class="text-muted">Try a different search term or clear the filters.</p>
                    <a class="btn btn-outline-primary" href="<?= e(url('dashboard.php#tasks')) ?>">Clear filters</a>
                <?php else: ?>
                    <h4 class="h5">No tasks yet</h4>
                    <p class="text-muted">Create your first task and it will show up here.</p>
                    <a class="btn btn-primary" href="<?= e(url('tasks/add.php')) ?>"><i class="bi bi-plus-lg me-1"></i>Add your first task</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table task-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Task</th>
                            <th scope="col" class="d-none d-md-table-cell">Priority</th>
                            <th scope="col" class="d-none d-sm-table-cell">Due</th>
                            <th scope="col" style="min-width:150px">Status</th>
                            <th scope="col" class="d-none d-lg-table-cell">Created</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($tasks as $t):
                        $overdue = is_overdue($t['due_date'], $t['status']); ?>
                        <tr>
                            <td class="task-cell">
                                <div class="task-title <?= $t['status'] === 'Completed' ? 'is-done' : '' ?>"><?= e($t['title']) ?></div>
                                <?php if ($t['description'] !== null && $t['description'] !== ''): ?>
                                    <div class="task-desc"><?= e(mb_strimwidth($t['description'], 0, 90, '…')) ?></div>
                                <?php endif; ?>
                                <div class="d-md-none mt-1 small">
                                    <span class="badge prio prio-<?= e(strtolower($t['priority'])) ?>"><?= e($t['priority']) ?></span>
                                    <?php if ($t['due_date']): ?><span class="ms-1 <?= $overdue ? 'text-danger fw-semibold' : 'text-muted' ?>"><i class="bi bi-calendar-event"></i> <?= e(fmt_date($t['due_date'], 'd M')) ?></span><?php endif; ?>
                                </div>
                            </td>
                            <td class="d-none d-md-table-cell"><span class="badge prio prio-<?= e(strtolower($t['priority'])) ?>"><i class="bi bi-flag-fill me-1"></i><?= e($t['priority']) ?></span></td>
                            <td class="d-none d-sm-table-cell text-nowrap">
                                <?php if ($t['due_date']): ?>
                                    <span class="<?= $overdue ? 'text-danger fw-semibold' : 'text-muted' ?>"><i class="bi bi-calendar-event me-1"></i><?= e(fmt_date($t['due_date'])) ?></span>
                                    <?php if ($overdue): ?><div class="small text-danger">Overdue</div><?php endif; ?>
                                <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                            </td>
                            <td>
                                <form method="post" action="<?= e(url('tasks/update_status.php')) ?>" class="status-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                    <input type="hidden" name="back" value="<?= e($backQuery) ?>">
                                    <select name="status" class="form-select form-select-sm status-select status-<?= e(status_slug($t['status'])) ?>"
                                            aria-label="Status of <?= e($t['title']) ?>" data-autosubmit>
                                        <?php foreach (TASK_STATUSES as $s): ?>
                                            <option value="<?= e($s) ?>" <?= $t['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <noscript><button class="btn btn-sm btn-outline-primary mt-1" type="submit">Save</button></noscript>
                                </form>
                            </td>
                            <td class="d-none d-lg-table-cell text-muted small text-nowrap"><?= e(fmt_date($t['created_at'])) ?></td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-icon btn-soft-primary" href="<?= e(url('tasks/edit.php?id=' . (int) $t['id'])) ?>" title="Edit task" aria-label="Edit <?= e($t['title']) ?>"><i class="bi bi-pencil-square"></i></a>
                                <button type="button" class="btn btn-sm btn-icon btn-soft-danger" title="Delete task" aria-label="Delete <?= e($t['title']) ?>"
                                        data-bs-toggle="modal" data-bs-target="#deleteModal"
                                        data-task-id="<?= (int) $t['id'] ?>" data-task-title="<?= e($t['title']) ?>"><i class="bi bi-trash3"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($pages > 1): ?>
                <nav class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3" aria-label="Task pages">
                    <div class="small text-muted">
                        Showing <?= $offset + 1 ?>&ndash;<?= $offset + count($tasks) ?> of <?= $matching ?>
                    </div>
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= e($pageUrl(max(1, $page - 1))) ?>" aria-label="Previous">&laquo;</a></li>
                        <?php for ($i = 1; $i <= $pages; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="<?= e($pageUrl($i)) ?>"><?= $i ?></a></li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>"><a class="page-link" href="<?= e($pageUrl(min($pages, $page + 1))) ?>" aria-label="Next">&raquo;</a></li>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Delete confirmation modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="post" action="<?= e(url('tasks/delete.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="deleteTaskId" value="">
            <input type="hidden" name="back" value="<?= e($backQuery) ?>">
            <div class="modal-body text-center p-4">
                <div class="empty-icon danger mb-3"><i class="bi bi-trash3"></i></div>
                <h5 class="modal-title mb-2" id="deleteModalLabel">Delete this task?</h5>
                <p class="text-muted mb-0">&ldquo;<strong id="deleteTaskTitle"></strong>&rdquo; will be permanently removed. This cannot be undone.</p>
            </div>
            <div class="modal-footer justify-content-center border-0 pt-0 pb-4 gap-2">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger px-4"><i class="bi bi-trash3 me-1"></i>Yes, delete</button>
            </div>
        </form>
    </div>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
