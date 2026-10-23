<?php
require '../config/database.php';
require '../includes/functions.php';
require '../includes/auth.php';
require_admin();

$root = '../';
$pageTitle = 'Labs & departments';
$activePage = 'labs';
$isAdmin = true;

$errors = [];
$form = ['lab_id' => 0, 'dept_id' => '', 'lab_name' => '', 'room_no' => '', 'incharge' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('danger', 'Session expired. Please try again.');
        redirect('labs.php');
    }
    $act = $_POST['action'] ?? '';

    if ($act === 'add_dept') {
        $name = trim($_POST['dept_name'] ?? '');
        $code = strtoupper(trim($_POST['dept_code'] ?? ''));
        if (mb_strlen($name) < 2 || !preg_match('/^[A-Z]{2,10}$/', $code)) {
            set_flash('warning', 'Enter a department name and a 2–10 letter code.');
        } else {
            try {
                $pdo->prepare('INSERT INTO departments (dept_name, dept_code) VALUES (?, ?)')->execute([$name, $code]);
                set_flash('success', 'Department added.');
            } catch (PDOException $e) {
                if ($e->getCode() !== '23000') {
                    throw $e;
                }
                set_flash('warning', 'That department name or code already exists (UNIQUE).');
            }
        }
        redirect('labs.php');
    }

    if ($act === 'delete') {
        try {
            $st = $pdo->prepare('DELETE FROM labs WHERE lab_id = ?');
            $st->execute([(int) ($_POST['id'] ?? 0)]);
            set_flash('success', 'Lab deleted.');
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            set_flash('warning', 'This lab still has equipment, so it cannot be deleted (foreign key ON DELETE RESTRICT).');
        }
        redirect('labs.php');
    }

    if ($act === 'save') {
        $form['lab_id']   = (int) ($_POST['lab_id'] ?? 0);
        $form['dept_id']  = (int) ($_POST['dept_id'] ?? 0);
        $form['lab_name'] = trim($_POST['lab_name'] ?? '');
        $form['room_no']  = trim($_POST['room_no'] ?? '');
        $form['incharge'] = trim($_POST['incharge'] ?? '');
        $chk = $pdo->prepare('SELECT COUNT(*) FROM departments WHERE dept_id = ?');
        $chk->execute([$form['dept_id']]);
        if (!$chk->fetchColumn()) {
            $errors['dept_id'] = 'Choose a department.';
        }
        if (mb_strlen($form['lab_name']) < 2 || mb_strlen($form['lab_name']) > 120) {
            $errors['lab_name'] = 'Lab name is required.';
        }
        if (!$errors) {
            $vals = [$form['dept_id'], $form['lab_name'], $form['room_no'], $form['incharge']];
            if ($form['lab_id']) {
                $pdo->prepare('UPDATE labs SET dept_id = ?, lab_name = ?, room_no = ?, incharge = ? WHERE lab_id = ?')->execute([...$vals, $form['lab_id']]);
                set_flash('success', 'Lab updated.');
            } else {
                $pdo->prepare('INSERT INTO labs (dept_id, lab_name, room_no, incharge) VALUES (?, ?, ?, ?)')->execute($vals);
                set_flash('success', 'Lab added.');
            }
            redirect('labs.php');
        }
    }
}

if ($editId = get_int('edit')) {
    $st = $pdo->prepare('SELECT * FROM labs WHERE lab_id = ?');
    $st->execute([$editId]);
    $form = $st->fetch() ?: $form;
}

$labs = $pdo->query('SELECT v.*, l.room_no, l.incharge, d.dept_name FROM lab_availability v
                     INNER JOIN labs l ON l.lab_id = v.lab_id INNER JOIN departments d ON l.dept_id = d.dept_id
                     ORDER BY v.dept_code, v.lab_name')->fetchAll();
// Departments with their number of labs and students (two LEFT JOIN subqueries)
$depts = $pdo->query('SELECT d.*, (SELECT COUNT(*) FROM labs l WHERE l.dept_id = d.dept_id) AS labs,
                             (SELECT COUNT(*) FROM students s WHERE s.dept_id = d.dept_id) AS students
                      FROM departments d ORDER BY d.dept_name')->fetchAll();

include '../includes/header.php';
?>
<div class="container py-4">
    <h1 class="h3 mb-3">Labs &amp; departments</h1>
    <?php show_flash(); ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="ll-table table-responsive mb-4">
                <table class="table table-hover">
                    <thead><tr><th>Lab</th><th>Dept</th><th>Room</th><th>In-charge</th><th class="text-center">Units free</th><th class="text-end"></th></tr></thead>
                    <tbody>
                    <?php foreach ($labs as $l): ?>
                        <tr>
                            <td><strong><?= e($l['lab_name']) ?></strong><div class="small text-muted-ll"><?= (int) $l['item_types'] ?> item types</div></td>
                            <td><?= e($l['dept_code']) ?></td>
                            <td class="small"><?= e($l['room_no']) ?></td>
                            <td class="small"><?= e($l['incharge']) ?></td>
                            <td class="text-center"><?= (int) $l['available_units'] ?>/<?= (int) $l['total_units'] ?></td>
                            <td class="text-end text-nowrap">
                                <a href="labs.php?edit=<?= (int) $l['lab_id'] ?>" class="btn btn-sm btn-outline-brand"><i class="bi bi-pencil"></i></a>
                                <form method="post" class="d-inline" data-confirm="Delete lab '<?= e($l['lab_name']) ?>'?">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $l['lab_id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <h2 class="h5">Departments</h2>
            <div class="ll-table table-responsive">
                <table class="table table-sm">
                    <thead><tr><th>Department</th><th>Code</th><th class="text-center">Labs</th><th class="text-center">Students</th></tr></thead>
                    <tbody><?php foreach ($depts as $d): ?><tr><td><?= e($d['dept_name']) ?></td><td><?= e($d['dept_code']) ?></td><td class="text-center"><?= (int) $d['labs'] ?></td><td class="text-center"><?= (int) $d['students'] ?></td></tr><?php endforeach; ?></tbody>
                </table>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="ll-card p-3 mb-3">
                <h2 class="h6"><?= $form['lab_id'] ? 'Edit lab' : 'Add a lab' ?></h2>
                <form method="post" action="labs.php" class="needs-validation" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="lab_id" value="<?= (int) $form['lab_id'] ?>">
                    <div class="mb-2">
                        <label class="form-label small" for="dept_id">Department</label>
                        <select id="dept_id" name="dept_id" class="form-select form-select-sm <?= isset($errors['dept_id']) ? 'is-invalid' : '' ?>" required>
                            <option value="">Choose…</option>
                            <?php foreach ($depts as $d): ?><option value="<?= (int) $d['dept_id'] ?>" <?= $form['dept_id'] == $d['dept_id'] ? 'selected' : '' ?>><?= e($d['dept_name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small" for="lab_name">Lab name</label>
                        <input id="lab_name" name="lab_name" class="form-control form-control-sm <?= isset($errors['lab_name']) ? 'is-invalid' : '' ?>" value="<?= e($form['lab_name']) ?>" required maxlength="120">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-5"><label class="form-label small" for="room_no">Room</label><input id="room_no" name="room_no" class="form-control form-control-sm" value="<?= e($form['room_no']) ?>" maxlength="20"></div>
                        <div class="col-7"><label class="form-label small" for="incharge">In-charge</label><input id="incharge" name="incharge" class="form-control form-control-sm" value="<?= e($form['incharge']) ?>" maxlength="100"></div>
                    </div>
                    <button class="btn btn-sm btn-brand" type="submit"><?= $form['lab_id'] ? 'Save' : 'Add lab' ?></button>
                    <?php if ($form['lab_id']): ?><a href="labs.php" class="btn btn-sm btn-outline-secondary">Cancel</a><?php endif; ?>
                </form>
            </div>
            <div class="ll-card p-3">
                <h2 class="h6">Add a department</h2>
                <form method="post" action="labs.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_dept">
                    <input name="dept_name" class="form-control form-control-sm mb-2" placeholder="Civil Engineering" required maxlength="100" aria-label="Department name">
                    <input name="dept_code" class="form-control form-control-sm mb-2" placeholder="CIVIL" required maxlength="10" aria-label="Department code">
                    <button class="btn btn-sm btn-outline-brand" type="submit">Add department</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
