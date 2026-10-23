<?php
require '../config/database.php';
require '../includes/functions.php';
require '../includes/auth.php';

$root = '../';
$pageTitle = 'Admin login';
$isAdmin = true;

if (!empty($_SESSION['admin_logged_in'])) {
    redirect('dashboard.php');
}

$error = '';
$username = '';
$_SESSION['login_attempts'] = $_SESSION['login_attempts'] ?? 0;
$lockedUntil = $_SESSION['login_locked_until'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    if (time() < $lockedUntil) {
        $error = 'Too many failed attempts. Please wait ' . ($lockedUntil - time()) . ' seconds.';
    } elseif (!verify_csrf()) {
        $error = 'Your session expired. Please try again.';
    } elseif (admin_check_credentials($username, $password)) {
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        $_SESSION['login_attempts'] = 0;
        unset($_SESSION['login_locked_until']);
        redirect('dashboard.php');
    } else {
        if (++$_SESSION['login_attempts'] >= 5) {
            $_SESSION['login_locked_until'] = time() + 60;
            $_SESSION['login_attempts'] = 0;
        }
        $error = 'Invalid username or password.';
    }
}
include '../includes/header.php';
?>
<div class="container py-5">
    <div class="ll-card login-card mx-auto p-4 p-md-5">
        <div class="text-center mb-4">
            <span class="brand-mark mx-auto mb-2" style="width:52px;height:52px;font-size:1.5rem"><i class="bi bi-shield-lock"></i></span>
            <h1 class="h3 mb-1">Lab in-charge login</h1>
            <p class="small text-muted-ll mb-0">Approve requests, issue and receive equipment</p>
        </div>
        <?php show_flash(); ?>
        <?php if ($error): ?><div class="alert alert-danger small"><?= e($error) ?></div><?php endif; ?>
        <form method="post" class="needs-validation" novalidate>
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="username">Username</label>
                <input id="username" name="username" class="form-control" value="<?= e($username) ?>" required autocomplete="username" autofocus>
            </div>
            <div class="mb-4">
                <label class="form-label" for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-brand btn-lg w-100">Log in</button>
        </form>
        <p class="text-center small mt-3 mb-0"><a href="../index.php">&larr; Back to LabLend</a></p>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
