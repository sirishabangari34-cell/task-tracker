<?php
/**
 * TEST HARNESS ONLY (auto_prepend_file for `php -S`).
 * Points the app's PDO layer at a throw-away SQLite file so the automated suite
 * can run on machines without MySQL. It is NOT used by the real XAMPP/MySQL setup.
 */
$file = getenv('TT_SQLITE') ?: __DIR__ . '/test.sqlite';
define('DB_DSN', 'sqlite:' . $file);

if (!is_file($file) || filesize($file) === 0) {
    $pdo = new PDO('sqlite:' . $file);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec(file_get_contents(__DIR__ . '/sqlite_schema.sql'));

    $hash = password_hash('Test@1234', PASSWORD_DEFAULT);
    $u = $pdo->prepare('INSERT INTO users (id, name, email, password_hash) VALUES (?, ?, ?, ?)');
    $u->execute([1, 'Alice Demo', 'alice@example.com', $hash]);
    $u->execute([2, 'Bob Demo', 'bob@example.com', $hash]);

    $t = $pdo->prepare('INSERT INTO tasks (user_id, title, description, status, priority, due_date) VALUES (?,?,?,?,?,?)');
    $seed = [
        [1, 'Design landing page mockup', 'Figma mockup', 'In Progress', 'High', date('Y-m-d', strtotime('+2 days'))],
        [1, 'Write API documentation', 'Document endpoints', 'Pending', 'Medium', date('Y-m-d', strtotime('+5 days'))],
        [1, 'Fix login redirect bug', null, 'Completed', 'High', date('Y-m-d', strtotime('-1 day'))],
        [1, 'Database backup script', 'Nightly dump', 'Pending', 'High', date('Y-m-d', strtotime('-2 days'))],
        [2, 'Bob: private grocery list', 'Only Bob may see this', 'Pending', 'Low', null],
        [2, 'Bob: quarterly tax filing', 'Confidential', 'In Progress', 'High', null],
    ];
    foreach ($seed as $row) {
        $t->execute($row);
    }
}
