<?php
require 'config/database.php';
require 'includes/functions.php';
require 'includes/auth.php';

$root = '';
$pageTitle = 'Student login';
$activePage = 'login';

// Only allow redirects to pages inside this site (no "//evil.com", no "http:")
$next = (string) ($_GET['next'] ?? $_POST['next'] ?? '');
if (!preg_match('#^[a-z_/]+\.php(\?[a-z_]+=\d+)?$#i', $next) || str_contains($next, '//')) {
    $next = 'student/dashboard.php';
}

if (current_student($pdo)) {
    redirect($next);
}

$error = '';
$login = '';
$_SESSION['student_login_attempts'] = $_SESSION['student_login_attempts'] ?? 0;
$lockedUntil = $_SESSION['student_locked_until'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    if (time() < $lockedUntil) {
        $error = 'Too many failed attempts. Please wait ' . ($lockedUntil - time()) . ' seconds.';
    } elseif (!verify_csrf()) {
        $error = 'Your session expired. Please try again.';
    } elseif ($login === '' || $password === '') {
        $error = 'Enter your email (or PRN) and password.';
    } elseif ($s = student_check_credentials($pdo, $login, $password)) {
        student_login($s);
        set_flash('success', 'Welcome back, ' . $s['name'] . '!');
        redirect($next);
    } else {
        if (++$_SESSION['student_login_attempts'] >= 5) {
            $_SESSION['student_locked_until'] = time() + 60;
            $_SESSION['student_login_attempts'] = 0;
        }
        $error = 'Invalid email/PRN or password.';
    }
}
include 'includes/header.php';
?>
<div class="container py-5">
    <div class="ll-card login-card mx-auto p-4 p-md-5">
        <div class="text-center mb-4">
            <span class="brand-mark mx-auto mb-2" style="width:52px;height:52px;font-size:1.5rem"><i class="bi bi-person-badge"></i></span>
            <h1 class="h3 mb-1">Student login</h1>
            <p class="small text-muted-ll mb-0">Request equipment and track your loans</p>
        </div>
        <?php show_flash(); ?>
        <?php if ($error): ?><div class="alert alert-danger small"><?= e($error) ?></div><?php endif; ?>
        <form method="post" class="needs-validation" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="next" value="<?= e($next) ?>">
            <div class="mb-3">
                <label class="form-label" for="login">Email or PRN</label>
                <input id="login" name="login" class="form-control" value="<?= e($login) ?>" required autocomplete="username" autofocus>
            </div>
            <div class="mb-4">
                <label class="form-label" for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-brand btn-lg w-100">Log in</button>
        </form>
        <p class="text-center small mt-3 mb-0">New here? <a href="register.php">Create an account</a></p>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
