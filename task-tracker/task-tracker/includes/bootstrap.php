<?php
/**
 * Application bootstrap — every page includes this file first.
 */
declare(strict_types=1);

// This file is only meant to be included. Refuse to run if it is requested directly.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(403);
    exit('Forbidden');
}

define('APP_BOOT', true);
define('ROOT_PATH', dirname(__DIR__));

require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/includes/functions.php';

// Never leak PHP errors / stack traces to visitors; log them instead.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

set_exception_handler(static function (Throwable $e): void {
    error_log('[TaskFlow] Uncaught ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo '<!DOCTYPE html><meta charset="utf-8"><title>Server error</title>'
       . '<body style="font-family:system-ui;max-width:520px;margin:15vh auto;padding:0 1rem">'
       . '<h2>Something went wrong</h2><p>An unexpected error occurred. Please go back and try again.</p></body>';
});

start_secure_session();
send_security_headers();
