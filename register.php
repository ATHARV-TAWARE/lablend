<?php
require 'config/database.php';
require 'includes/functions.php';
require 'includes/auth.php';

$root = '';
$pageTitle = 'Student registration';
$activePage = 'register';

if (current_student($pdo)) {
    redirect('student/dashboard.php');
}

$errors = [];
$old = ['prn' => '', 'name' => '', 'email' => '', 'phone' => '', 'dept_id' => '', 'year_of_study' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $k => $_) {
        $old[$k] = trim($_POST[$k] ?? '');
    }
    $old['prn'] = strtoupper($old['prn']);
    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['password_confirm'] ?? '');

    if (!verify_csrf()) {
        $errors['form'] = 'Your session expired. Please submit again.';
    }
    if (!preg_match('/^[A-Z0-9]{6,20}$/', $old['prn'])) {
        $errors['prn'] = 'PRN must be 6–20 letters/digits.';
    }
    if (mb_strlen($old['name']) < 2 || mb_strlen($old['name']) > 120) {
        $errors['name'] = 'Please enter your full name.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($old['email']) > 120) {
        $errors['email'] = 'Enter a valid email address.';
    }
    $phone = normalize_phone($old['phone']);
    if ($phone === null) {
        $errors['phone'] = 'Enter a valid 10-digit mobile number.';
    }
    $chk = $pdo->prepare('SELECT COUNT(*) FROM departments WHERE dept_id = ?');
    $chk->execute([(int) $old['dept_id']]);
    if (!$chk->fetchColumn()) {
        $errors['dept_id'] = 'Choose your department.';
    }
    if (!in_array($old['year_of_study'], ['1', '2', '3', '4'], true)) {
        $errors['year_of_study'] = 'Choose your year.';
    }
    if (strlen($password) < 8 || strlen($password) > 72) {
        $errors['password'] = 'Password must be 8–72 characters.';
    } elseif (!hash_equals($password, $confirm)) {
        $errors['password_confirm'] = 'Passwords do not match.';
    }
    if (!$errors) {
        $dup = $pdo->prepare('SELECT prn, email FROM students WHERE prn = ? OR email = ?');
        $dup->execute([$old['prn'], $old['email']]);
        foreach ($dup->fetchAll() as $d) {
            if (strcasecmp($d['prn'], $old['prn']) === 0) {
                $errors['prn'] = 'This PRN is already registered. Please log in.';
            }
            if (strcasecmp($d['email'], $old['email']) === 0) {
                $errors['email'] = 'This email is already registered. Please log in.';
            }
        }
    }
    if (!$errors) {
        try {
            $ins = $pdo->prepare('INSERT INTO students (prn, name, email, phone, dept_id, year_of_study, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $ins->execute([$old['prn'], $old['name'], $old['email'], $phone, (int) $old['dept_id'], (int) $old['year_of_study'],
                           password_hash($password, PASSWORD_DEFAULT)]);
            student_login(['student_id' => (int) $pdo->lastInsertId()]);
            set_flash('success', 'Welcome to LabLend, ' . $old['name'] . '! Browse equipment and send your first request.');
            redirect('student/dashboard.php');
        } catch (PDOException $ex) {
            if ($ex->getCode() !== '23000') {
                throw $ex;
            }
            $errors['email'] = 'This PRN or email is already registered.';   // UNIQUE hit by a simultaneous sign-up
        }
    }
}

$depts = get_departments($pdo);
include 'includes/header.php';

function field_error(array $errors, string $k): string
{
    return isset($errors[$k]) ? '<div class="invalid-feedback d-block">' . e($errors[$k]) . '</div>' : '';
}
?>
<div class="container py-5">
    <div class="ll-card mx-auto p-4 p-md-5" style="max-width:720px">
        <h1 class="h3 mb-1">Create your student account</h1>
        <p class="text-muted-ll">Already registered? <a href="login.php">Log in</a></p>
        <?php if (isset($errors['form'])): ?><div class="alert alert-danger small"><?= e($errors['form']) ?></div><?php endif; ?>
        <form method="post" class="needs-validation" novalidate>
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label" for="prn">PRN</label>
                    <input id="prn" name="prn" class="form-control <?= isset($errors['prn']) ? 'is-invalid' : '' ?>" value="<?= e($old['prn']) ?>" required maxlength="20" placeholder="2024AIML001">
                    <?= field_error($errors, 'prn') ?>
                </div>
                <div class="col-md-7">
                    <label class="form-label" for="name">Full name</label>
                    <input id="name" name="name" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" value="<?= e($old['name']) ?>" required maxlength="120" autocomplete="name">
                    <?= field_error($errors, 'name') ?>
                </div>
                <div class="col-md-7">
                    <label class="form-label" for="email">College email</label>
                    <input type="email" id="email" name="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" value="<?= e($old['email']) ?>" required maxlength="120" autocomplete="email">
                    <?= field_error($errors, 'email') ?>
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="phone">Mobile</label>
                    <input type="tel" id="phone" name="phone" class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>" value="<?= e($old['phone']) ?>" required maxlength="16" autocomplete="tel">
                    <?= field_error($errors, 'phone') ?>
                </div>
                <div class="col-md-8">
                    <label class="form-label" for="dept_id">Department</label>
                    <select id="dept_id" name="dept_id" class="form-select <?= isset($errors['dept_id']) ? 'is-invalid' : '' ?>" required>
                        <option value="">Choose…</option>
                        <?php foreach ($depts as $d): ?><option value="<?= (int) $d['dept_id'] ?>" <?= $old['dept_id'] == $d['dept_id'] ? 'selected' : '' ?>><?= e($d['dept_name']) ?></option><?php endforeach; ?>
                    </select>
                    <?= field_error($errors, 'dept_id') ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="year_of_study">Year</label>
                    <select id="year_of_study" name="year_of_study" class="form-select <?= isset($errors['year_of_study']) ? 'is-invalid' : '' ?>" required>
                        <option value="">Choose…</option>
                        <?php foreach ([1 => 'First year', 2 => 'Second year', 3 => 'Third year', 4 => 'Final year'] as $y => $lbl): ?>
                            <option value="<?= $y ?>" <?= $old['year_of_study'] == $y ? 'selected' : '' ?>><?= $lbl ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= field_error($errors, 'year_of_study') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" required minlength="8" maxlength="72" autocomplete="new-password">
                    <?= field_error($errors, 'password') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="password_confirm">Confirm password</label>
                    <input type="password" id="password_confirm" name="password_confirm" class="form-control <?= isset($errors['password_confirm']) ? 'is-invalid' : '' ?>" required minlength="8" maxlength="72" autocomplete="new-password">
                    <?= field_error($errors, 'password_confirm') ?>
                </div>
            </div>
            <button type="submit" class="btn btn-brand btn-lg w-100 mt-4">Create account</button>
        </form>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
