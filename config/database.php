<?php
/**
 * Database connection (PDO)
 * Change these values if your MySQL username/password are different.
 * Default XAMPP: user "root", empty password.
 */

// Block direct access to this file from the browser
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(403);
    exit('Forbidden');
}

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'lablend_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// DEVELOPMENT switch: true = show real database errors to the admin.
// Set to false before putting the site online.
define('APP_DEBUG', true);

set_exception_handler(function (Throwable $e) {
    error_log('LabLend error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo '<div style="font-family:sans-serif;max-width:560px;margin:60px auto;text-align:center">'
       . '<h2 style="color:#1d3557">Something went wrong.</h2>'
       . '<p>Please go back and try again.</p>'
       . (APP_DEBUG ? '<pre style="text-align:left;white-space:pre-wrap;background:#eef3fb;padding:12px;border-radius:6px">'
           . htmlspecialchars(get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine(), ENT_QUOTES, 'UTF-8')
           . '</pre>' : '')
       . '<p><a href="javascript:history.back()">&larr; Go back</a></p></div>';
});

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // errors become exceptions
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,                  // real prepared statements
        ]
    );
} catch (PDOException $e) {
    error_log('LabLend DB connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('<h2 style="font-family:sans-serif;color:#1d3557">Database connection failed.</h2>
          <p style="font-family:sans-serif">Start MySQL in XAMPP and import <code>database/lablend.sql</code> (see README.md).</p>'
          . (APP_DEBUG ? '<pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>' : ''));
}
