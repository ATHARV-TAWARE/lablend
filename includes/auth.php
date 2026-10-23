<?php
/**
 * Authentication for LabLend's two kinds of login. Visitors never log in.
 *   ADMIN   – the lab in-charge; one fixed account defined below
 *   STUDENT – rows in the `students` table (password_hash / password_verify)
 *
 * Demo admin login:  username = admin   password = Lab@2026
 * To change it:  php -r "echo password_hash('NewPassword', PASSWORD_DEFAULT);"
 */
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD_HASH', '$2y$10$Nwsb9A6/ZZOS1e/r/1NFXehPKz251NvwmMFXKjJhVd/LjvBkFLc32');

/* ======================= ADMIN ======================= */
function admin_check_credentials(string $username, string $password): bool
{
    return hash_equals(ADMIN_USERNAME, $username) && password_verify($password, ADMIN_PASSWORD_HASH);
}

function require_admin(): void
{
    if (empty($_SESSION['admin_logged_in'])) {
        set_flash('warning', 'Please log in to access the admin panel.');
        header('Location: login.php');
        exit;
    }
}

/* ======================= STUDENT ======================= */
/** The logged-in student (read fresh from the database), or null. */
function current_student(PDO $pdo): ?array
{
    static $cache = null;
    if (empty($_SESSION['student_id'])) {
        return null;
    }
    if ($cache !== null && (int) $cache['student_id'] === (int) $_SESSION['student_id']) {
        return $cache;
    }
    $st = $pdo->prepare('SELECT s.*, d.dept_name, d.dept_code FROM students s
                         INNER JOIN departments d ON s.dept_id = d.dept_id WHERE s.student_id = ?');
    $st->execute([(int) $_SESSION['student_id']]);
    $row = $st->fetch();
    if (!$row) {                       // student removed → end their session
        unset($_SESSION['student_id']);
        return null;
    }
    return $cache = $row;
}

function student_check_credentials(PDO $pdo, string $login, string $password): ?array
{
    // Log in with email OR PRN
    $st = $pdo->prepare('SELECT student_id, name, password_hash FROM students WHERE email = ? OR prn = ?');
    $st->execute([$login, $login]);
    $s = $st->fetch();
    return ($s && password_verify($password, $s['password_hash'])) ? $s : null;
}

function student_login(array $student): void
{
    session_regenerate_id(true);     // prevent session fixation
    $_SESSION['student_id'] = (int) $student['student_id'];
    $_SESSION['student_login_attempts'] = 0;
    unset($_SESSION['student_locked_until']);
}

/** Call at the top of every student page. $root = path to the project root. */
function require_student(PDO $pdo, string $root = '../'): array
{
    $s = current_student($pdo);
    if (!$s) {
        set_flash('warning', 'Please log in with your student account.');
        header('Location: ' . $root . 'login.php');
        exit;
    }
    return $s;
}
