<?php
require '../config/database.php';
require '../includes/functions.php';
require '../includes/auth.php';
require_admin();

$root = '../';
$pageTitle = 'Manage equipment';
$activePage = 'equipment';
$isAdmin = true;

$errors = [];
$form = ['equipment_id' => 0, 'lab_id' => '', 'name' => '', 'category' => '', 'description' => '', 'total_qty' => 1, 'image' => null];
$action = $_GET['action'] ?? 'list';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('danger', 'Session expired. Please try again.');
        redirect('equipment.php');
    }

    /* ---------- DELETE (blocked by the foreign key if it was ever issued) ---------- */
    if (($_POST['action'] ?? '') === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $img = $pdo->prepare('SELECT image FROM equipment WHERE equipment_id = ?');
        $img->execute([$id]);
        $old = $img->fetchColumn();
        try {
            $st = $pdo->prepare('DELETE FROM equipment WHERE equipment_id = ?');
            $st->execute([$id]);
            if ($st->rowCount()) {
                delete_image($old ?: null);
                set_flash('success', 'Equipment deleted (its requests were removed too – ON DELETE CASCADE).');
            }
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            set_flash('warning', 'This item has issue history, so it cannot be deleted (foreign key ON DELETE RESTRICT). Set its quantity instead.');
        }
        redirect('equipment.php');
    }

    /* ---------- CREATE / UPDATE ---------- */
    $form['equipment_id'] = (int) ($_POST['equipment_id'] ?? 0);
    $form['lab_id']       = (int) ($_POST['lab_id'] ?? 0);
    $form['name']         = trim($_POST['name'] ?? '');
    $form['category']     = trim($_POST['category'] ?? '');
    $form['description']  = trim($_POST['description'] ?? '');
    $form['total_qty']    = trim($_POST['total_qty'] ?? '');

    $onLoan = 0;
    if ($form['equipment_id']) {
        $cur = $pdo->prepare('SELECT image, total_qty - available_qty AS on_loan FROM equipment WHERE equipment_id = ?');
        $cur->execute([$form['equipment_id']]);
        $c = $cur->fetch();
        if (!$c) {
            redirect('equipment.php');
        }
        $form['image'] = $c['image'];
        $onLoan = (int) $c['on_loan'];
    }

    $chk = $pdo->prepare('SELECT COUNT(*) FROM labs WHERE lab_id = ?');
    $chk->execute([$form['lab_id']]);
    if (!$chk->fetchColumn()) {
        $errors['lab_id'] = 'Choose a lab.';
    }
    if ($form['name'] === '' || mb_strlen($form['name']) > 150) {
        $errors['name'] = 'Name is required (max 150 characters).';
    }
    if ($form['category'] === '' || mb_strlen($form['category']) > 60) {
        $errors['category'] = 'Category is required (max 60 characters).';
    }
    if (filter_var($form['total_qty'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1000]]) === false) {
        $errors['total_qty'] = 'Quantity must be a whole number (0–1000).';
    } elseif ((int) $form['total_qty'] < $onLoan) {
        $errors['total_qty'] = $onLoan . ' unit(s) are on loan right now, so the total cannot be lower than that.';
    }

    $newImage = null;
    if (!$errors) {
        try {
            $newImage = handle_image_upload('image');
        } catch (RuntimeException $ex) {
            $errors['image'] = $ex->getMessage();
        }
    }

    if (!$errors) {
        $old = $form['image'];
        $form['image'] = $newImage ?: (isset($_POST['remove_image']) ? null : $old);
        $total = (int) $form['total_qty'];
        if ($form['equipment_id']) {
            // available = new total − units currently on loan (keeps stock consistent)
            $pdo->prepare('UPDATE equipment SET lab_id = ?, name = ?, category = ?, description = ?, total_qty = ?, available_qty = ? - ?, image = ?
                           WHERE equipment_id = ?')
                ->execute([$form['lab_id'], $form['name'], $form['category'], $form['description'], $total, $total, $onLoan, $form['image'], $form['equipment_id']]);
            if ($old !== $form['image']) {
                delete_image($old);
            }
            set_flash('success', 'Equipment updated.');
        } else {
            $pdo->prepare('INSERT INTO equipment (lab_id, name, category, description, total_qty, available_qty, image) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([$form['lab_id'], $form['name'], $form['category'], $form['description'], $total, $total, $form['image']]);
            set_flash('success', 'Equipment added.');
        }
        redirect('equipment.php');
    }
    $action = $form['equipment_id'] ? 'edit' : 'add';
}

if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $st = $pdo->prepare('SELECT * FROM equipment WHERE equipment_id = ?');
    $st->execute([get_int('id')]);
    $form = $st->fetch() ?: redirect('equipment.php');
}

$filterLab = get_int('lab');
$st = $pdo->prepare('
    SELECT e.*, l.lab_name, d.dept_code,
           (SELECT COUNT(*) FROM issues i WHERE i.equipment_id = e.equipment_id) AS times_issued
    FROM equipment e
    INNER JOIN labs l ON e.lab_id = l.lab_id
    INNER JOIN departments d ON l.dept_id = d.dept_id
    ' . ($filterLab ? 'WHERE e.lab_id = ?' : '') . '
    ORDER BY l.lab_name, e.name');
$st->execute($filterLab ? [$filterLab] : []);
$items = $st->fetchAll();
$labs = get_labs($pdo);
$cats = get_categories($pdo);

include '../includes/header.php';
?>
<div class="container py-4">
<?php if ($action === 'add' || $action === 'edit'): ?>
    <nav aria-label="breadcrumb"><ol class="breadcrumb small"><li class="breadcrumb-item"><a href="equipment.php">Equipment</a></li><li class="breadcrumb-item active"><?= $action === 'edit' ? 'Edit' : 'Add' ?></li></ol></nav>
    <h1 class="h3 mb-3"><?= $action === 'edit' ? 'Edit equipment' : 'Add equipment' ?></h1>
    <div class="ll-card p-4" style="max-width:820px">
        <form method="post" action="equipment.php" enctype="multipart/form-data" class="needs-validation" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="equipment_id" value="<?= (int) $form['equipment_id'] ?>">
            <div class="row g-3">
                <div class="col-md-7">
                    <label class="form-label" for="name">Name</label>
                    <input id="name" name="name" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" value="<?= e($form['name']) ?>" required maxlength="150">
                    <div class="invalid-feedback"><?= e($errors['name'] ?? 'Required.') ?></div>
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="lab_id">Lab</label>
                    <select id="lab_id" name="lab_id" class="form-select <?= isset($errors['lab_id']) ? 'is-invalid' : '' ?>" required>
                        <option value="">Choose…</option>
                        <?php foreach ($labs as $l): ?><option value="<?= (int) $l['lab_id'] ?>" <?= $form['lab_id'] == $l['lab_id'] ? 'selected' : '' ?>><?= e($l['dept_code'] . ' · ' . $l['lab_name']) ?></option><?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback"><?= e($errors['lab_id'] ?? 'Choose a lab.') ?></div>
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="category">Category</label>
                    <input id="category" name="category" list="catlist" class="form-control <?= isset($errors['category']) ? 'is-invalid' : '' ?>" value="<?= e($form['category']) ?>" required maxlength="60">
                    <datalist id="catlist"><?php foreach ($cats as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
                    <div class="invalid-feedback"><?= e($errors['category'] ?? 'Required.') ?></div>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="total_qty">Total units</label>
                    <input type="number" id="total_qty" name="total_qty" min="0" max="1000" class="form-control <?= isset($errors['total_qty']) ? 'is-invalid' : '' ?>" value="<?= e($form['total_qty']) ?>" required>
                    <div class="invalid-feedback"><?= e($errors['total_qty'] ?? 'Required.') ?></div>
                </div>
                <div class="col-12">
                    <label class="form-label" for="description">Description</label>
                    <textarea id="description" name="description" rows="3" class="form-control"><?= e($form['description']) ?></textarea>
                </div>
                <div class="col-md-8">
                    <label class="form-label" for="image">Photo <span class="text-muted-ll small">(optional · JPG/PNG/WEBP · max 2 MB)</span></label>
                    <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp" class="form-control <?= isset($errors['image']) ? 'is-invalid' : '' ?>">
                    <div class="invalid-feedback"><?= e($errors['image'] ?? '') ?></div>
                    <?php if (!empty($form['image'])): ?>
                        <div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="remove_image" id="remove_image"><label class="form-check-label small" for="remove_image">Remove current photo</label></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="mt-4">
                <button class="btn btn-brand" type="submit"><?= $action === 'edit' ? 'Save changes' : 'Add equipment' ?></button>
                <a href="equipment.php" class="btn btn-outline-secondary ms-1">Cancel</a>
            </div>
        </form>
    </div>
<?php else: ?>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h1 class="h3 mb-0">Equipment</h1>
        <div class="d-flex gap-2">
            <form method="get"><select name="lab" class="form-select form-select-sm" data-autosubmit aria-label="Filter by lab"><option value="">All labs</option><?php foreach ($labs as $l): ?><option value="<?= (int) $l['lab_id'] ?>" <?= $filterLab === (int) $l['lab_id'] ? 'selected' : '' ?>><?= e($l['lab_name']) ?></option><?php endforeach; ?></select></form>
            <a href="equipment.php?action=add" class="btn btn-brand text-nowrap"><i class="bi bi-plus-lg me-1"></i>Add</a>
        </div>
    </div>
    <?php show_flash(); ?>
    <div class="ll-table table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Item</th><th>Lab</th><th>Category</th><th class="text-center">Available</th><th class="text-center">Times issued</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($items as $it): ?>
                <tr>
                    <td><strong><?= e($it['name']) ?></strong></td>
                    <td class="small"><?= e($it['lab_name']) ?> · <?= e($it['dept_code']) ?></td>
                    <td class="small"><?= e($it['category']) ?></td>
                    <td class="text-center"><?= stock_badge((int) $it['available_qty'], (int) $it['total_qty']) ?></td>
                    <td class="text-center"><?= (int) $it['times_issued'] ?></td>
                    <td class="text-end text-nowrap">
                        <a href="equipment.php?action=edit&id=<?= (int) $it['equipment_id'] ?>" class="btn btn-sm btn-outline-brand"><i class="bi bi-pencil"></i> Edit</a>
                        <form method="post" class="d-inline" data-confirm="Delete '<?= e($it['name']) ?>'?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $it['equipment_id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>
