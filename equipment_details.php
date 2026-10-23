<?php
require 'config/database.php';
require 'includes/functions.php';
require 'includes/auth.php';
require 'includes/lending.php';

$root = '';
$activePage = 'equipment';
$id = get_int('id');

$st = $pdo->prepare('SELECT e.*, l.lab_name, l.room_no, l.incharge, d.dept_name, d.dept_code
                     FROM equipment e
                     INNER JOIN labs l        ON e.lab_id = l.lab_id
                     INNER JOIN departments d ON l.dept_id = d.dept_id
                     WHERE e.equipment_id = ?');
$st->execute([$id]);
$eq = $st->fetch();
if (!$eq) {
    http_response_code(404);
    $pageTitle = 'Not found';
    include 'includes/header.php';
    echo '<div class="container py-5"><div class="empty-state"><i class="bi bi-question-circle"></i><h1 class="h4">Equipment not found</h1><a class="btn btn-brand mt-2" href="equipment.php">Back to equipment</a></div></div>';
    include 'includes/footer.php';
    exit;
}
$pageTitle = $eq['name'];
$student = current_student($pdo);

/* ---------- Student submits a borrow request ---------- */
$errors = [];
$old = ['quantity' => 1, 'days_needed' => 7, 'purpose' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$student) {
        set_flash('warning', 'Please log in to request equipment.');
        redirect('login.php?next=' . urlencode('equipment_details.php?id=' . $id));
    }
    if (!verify_csrf()) {
        set_flash('danger', 'Your session expired. Please try again.');
        redirect('equipment_details.php?id=' . $id);
    }
    $old['quantity']    = (int) ($_POST['quantity'] ?? 1);
    $old['days_needed'] = (int) ($_POST['days_needed'] ?? 7);
    $old['purpose']     = trim($_POST['purpose'] ?? '');
    try {
        $rid = create_request($pdo, $student, $id, $old['quantity'], $old['days_needed'], $old['purpose']);
        set_flash('success', 'Request #' . $rid . ' sent. The lab in-charge will review it – track it on your dashboard.');
        redirect('student/dashboard.php');
    } catch (LendingError $ex) {
        $errors[] = $ex->getMessage();
    }
}

// Next unit expected back (MIN over open issues) and how often it has been borrowed (COUNT)
$st = $pdo->prepare('SELECT MIN(due_date) AS next_due, SUM(quantity) AS out_units FROM issues WHERE equipment_id = ? AND returned_on IS NULL');
$st->execute([$id]);
$out = $st->fetch();
$st = $pdo->prepare('SELECT COUNT(*) FROM issues WHERE equipment_id = ?');
$st->execute([$id]);
$timesBorrowed = (int) $st->fetchColumn();

// Student's own state for this page
$canRequest = false;
$limitNote = '';
if ($student) {
    $sid = (int) $student['student_id'];
    $overdue = student_overdue_count($pdo, $sid);
    $used = student_open_units($pdo, $sid) + student_pending_units($pdo, $sid);
    if ($overdue) {
        $limitNote = 'You have ' . $overdue . ' overdue item(s). Return them before requesting more.';
    } elseif ($used >= MAX_ACTIVE_ITEMS) {
        $limitNote = 'You already hold or are waiting for ' . MAX_ACTIVE_ITEMS . ' items – the maximum.';
    } else {
        $canRequest = true;
    }
}

include 'includes/header.php';
?>
<div class="container py-4">
    <nav aria-label="breadcrumb"><ol class="breadcrumb small"><li class="breadcrumb-item"><a href="equipment.php">Equipment</a></li><li class="breadcrumb-item"><a href="equipment.php?lab=<?= (int) $eq['lab_id'] ?>"><?= e($eq['lab_name']) ?></a></li><li class="breadcrumb-item active" aria-current="page"><?= e($eq['name']) ?></li></ol></nav>
    <?php show_flash(); ?>
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="ll-card p-3">
                <?php if (!empty($eq['image'])): ?>
                    <img src="assets/images/uploads/<?= e($eq['image']) ?>" alt="<?= e($eq['name']) ?>" class="eq-img eq-img-lg">
                <?php else: ?>
                    <div class="eq-placeholder lg"><i class="bi <?= category_icon($eq['category']) ?>"></i></div>
                <?php endif; ?>
                <div class="mt-3">
                    <span class="cat-chip"><?= e($eq['category']) ?></span>
                    <h1 class="h3 mt-1 mb-1"><?= e($eq['name']) ?></h1>
                    <div class="mb-3"><?= stock_badge((int) $eq['available_qty'], (int) $eq['total_qty']) ?></div>
                    <p class="mb-3"><?= nl2br(e($eq['description'])) ?></p>
                    <dl class="row small mb-0">
                        <dt class="col-sm-4">Lab</dt><dd class="col-sm-8"><?= e($eq['lab_name']) ?> (Room <?= e($eq['room_no']) ?>)</dd>
                        <dt class="col-sm-4">Department</dt><dd class="col-sm-8"><?= e($eq['dept_name']) ?></dd>
                        <dt class="col-sm-4">Lab in-charge</dt><dd class="col-sm-8"><?= e($eq['incharge']) ?></dd>
                        <dt class="col-sm-4">On loan now</dt><dd class="col-sm-8"><?= (int) $out['out_units'] ?> unit(s)<?= $out['next_due'] ? ' · next due back ' . e(fmt_date($out['next_due'])) : '' ?></dd>
                        <dt class="col-sm-4">Times borrowed</dt><dd class="col-sm-8"><?= $timesBorrowed ?></dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="ll-card p-4">
                <h2 class="h5 mb-3"><i class="bi bi-send me-1 text-primary"></i>Request to borrow</h2>
                <?php foreach ($errors as $er): ?><div class="alert alert-danger small"><?= e($er) ?></div><?php endforeach; ?>

                <?php if (!$student): ?>
                    <p class="text-muted-ll">Students can request this item after logging in.</p>
                    <a href="login.php?next=<?= e(urlencode('equipment_details.php?id=' . $id)) ?>" class="btn btn-brand w-100 mb-2">Student login</a>
                    <a href="register.php" class="btn btn-outline-brand w-100">Create a student account</a>
                <?php elseif (!$canRequest): ?>
                    <div class="alert alert-warning small mb-2"><?= e($limitNote) ?></div>
                    <a href="student/dashboard.php" class="btn btn-outline-brand w-100">Go to my dashboard</a>
                <?php else: ?>
                    <?php if ((int) $eq['available_qty'] === 0): ?>
                        <div class="alert alert-info small">All units are on loan right now. You can still request it – it will be approved when a unit comes back.</div>
                    <?php endif; ?>
                    <form method="post" class="needs-validation" novalidate>
                        <?= csrf_field() ?>
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label" for="quantity">Quantity</label>
                                <input type="number" id="quantity" name="quantity" class="form-control" min="1" max="<?= min(MAX_QTY, (int) $eq['total_qty']) ?>" value="<?= (int) $old['quantity'] ?>" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="days_needed">For how many days?</label>
                                <input type="number" id="days_needed" name="days_needed" class="form-control" min="1" max="<?= MAX_DAYS ?>" value="<?= (int) $old['days_needed'] ?>" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="purpose">Purpose</label>
                                <textarea id="purpose" name="purpose" rows="3" class="form-control" minlength="5" maxlength="255" required placeholder="e.g. Mini project: smart irrigation prototype"><?= e($old['purpose']) ?></textarea>
                                <div class="invalid-feedback">Please describe what you need it for.</div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-brand w-100 mt-3"><i class="bi bi-send me-1"></i>Send request</button>
                        <p class="small text-muted-ll mt-2 mb-0">Late returns are fined <?= money(FINE_PER_DAY) ?> per item per day.</p>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
