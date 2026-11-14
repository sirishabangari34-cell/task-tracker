<?php
/**
 * Database configuration + PDO connection helper.
 *
 * Default values match a fresh XAMPP install (user "root", empty password).
 * Change them here if your MySQL setup is different.
 */
declare(strict_types=1);

if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Forbidden');
}

defined('DB_HOST')    || define('DB_HOST', 'localhost');
defined('DB_PORT')    || define('DB_PORT', '3306');
defined('DB_NAME')    || define('DB_NAME', 'task_tracker');
defined('DB_USER')    || define('DB_USER', 'root');
defined('DB_PASS')    || define('DB_PASS', '');
defined('DB_CHARSET') || define('DB_CHARSET', 'utf8mb4');

/**
 * Returns one shared PDO connection (created on first use).
 * - Exceptions on errors, associative fetch mode
 * - Real prepared statements (emulation OFF) => SQL injection safe
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    // DB_DSN may be pre-defined (the automated test-suite uses a SQLite file).
    $dsn = defined('DB_DSN')
        ? DB_DSN
        : sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
    } catch (PDOException $e) {
        error_log('[TaskFlow] DB connection failed: ' . $e->getMessage());
        http_response_code(500);
        exit('<!DOCTYPE html><meta charset="utf-8"><title>Database error</title>'
           . '<body style="font-family:system-ui;max-width:560px;margin:15vh auto;padding:0 1rem">'
           . '<h2>Database connection failed</h2>'
           . '<p>Make sure <strong>MySQL</strong> is running in the XAMPP Control Panel, that you imported '
           . '<code>database.sql</code>, and that the credentials in <code>config/database.php</code> are correct.</p></body>');
    }

    return $pdo;
}
