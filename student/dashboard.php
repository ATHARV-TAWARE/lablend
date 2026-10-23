<?php
require '../config/database.php';
require '../includes/functions.php';
require '../includes/auth.php';
require '../includes/lending.php';

$student = require_student($pdo);
$sid = (int) $student['student_id'];

$root = '../';
$pageTitle = 'My Dashboard';
$activePage = 'my';

/* ---------- Cancel one of my pending requests ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    if (!verify_csrf()) {
        set_flash('danger', 'Your session expired. Please try again.');
    } else {
        try {
            cancel_request($pdo, $sid, (int) ($_POST['id'] ?? 0));
            set_flash('success', 'Request cancelled.');
        } catch (LendingError $ex) {
            set_flash('warning', $ex->getMessage());
        }
    }
    redirect('dashboard.php');
}

// Items I have now (open issues) – days left computed with DATEDIFF
$st = $pdo->prepare('
    SELECT i.issue_id, i.quantity, i.issued_on, i.due_date, DATEDIFF(i.due_date, CURDATE()) AS days_left,
           e.equipment_id, e.name, l.lab_name, l.room_no
    FROM issues i
    INNER JOIN equipment e ON i.equipment_id = e.equipment_id
    INNER JOIN labs l      ON e.lab_id = l.lab_id
    WHERE i.student_id = ? AND i.returned_on IS NULL
    ORDER BY i.due_date');
$st->execute([$sid]);
$loans = $st->fetchAll();

// My requests (latest first)
$st = $pdo->prepare('
    SELECT r.request_id, r.quantity, r.days_needed, r.purpose, r.status, r.admin_note, r.requested_at,
           e.equipment_id, e.name
    FROM borrow_requests r
    INNER JOIN equipment e ON r.equipment_id = e.equipment_id
    WHERE r.student_id = ?
    ORDER BY r.requested_at DESC
    LIMIT 15');
$st->execute([$sid]);
$requests = $st->fetchAll();

// Returned items + fines
$st = $pdo->prepare('
    SELECT i.issued_on, i.due_date, i.returned_on, i.fine_amount, i.quantity, e.name
    FROM issues i INNER JOIN equipment e ON i.equipment_id = e.equipment_id
    WHERE i.student_id = ? AND i.returned_on IS NOT NULL
    ORDER BY i.returned_on DESC');
$st->execute([$sid]);
$history = $st->fetchAll();

$holding  = student_open_units($pdo, $sid);
$pending  = student_pending_units($pdo, $sid);
$overdue  = student_overdue_count($pdo, $sid);
$st = $pdo->prepare('SELECT COALESCE(SUM(fine_amount), 0) FROM issues WHERE student_id = ?');
$st->execute([$sid]);
$fines = (float) $st->fetchColumn();
// Fine building up on overdue items not yet returned
$st = $pdo->prepare('SELECT COALESCE(SUM(DATEDIFF(CURDATE(), due_date) * quantity), 0) * ? FROM issues
                     WHERE student_id = ? AND returned_on IS NULL AND due_date < CURDATE()');
$st->execute([FINE_PER_DAY, $sid]);
$runningFine = (float) $st->fetchColumn();

include '../includes/header.php';
?>
<section class="page-head">
    <div class="container d-flex flex-wrap justify-content-between align-items-end gap-2">
        <div>
            <h1>Hi, <?= e(explode(' ', $student['name'])[0]) ?></h1>
            <p class="text-muted-ll mb-0"><?= e($student['prn']) ?> · <?= e($student['dept_name']) ?> · Year <?= (int) $student['year_of_study'] ?></p>
        </div>
        <a href="../equipment.php" class="btn btn-brand"><i class="bi bi-plus-lg me-1"></i>Borrow something</a>
    </div>
</section>

<div class="container mt-4">
    <?php show_flash(); ?>
    <?php if ($overdue): ?>
        <div class="alert alert-danger"><i class="bi bi-exclamation-octagon me-1"></i><strong><?= $overdue ?> item(s) overdue.</strong>
            Please return them to the lab – the fine is growing by <?= money(FINE_PER_DAY) ?> per item per day (currently <?= money($runningFine) ?>). You can't request new items until they are returned.</div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3"><div class="stat"><div class="ic"><i class="bi bi-box-seam"></i></div><div><div class="stat-num"><?= $holding ?> / <?= MAX_ACTIVE_ITEMS ?></div><div class="stat-lbl">items with me</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="stat"><div class="ic warn"><i class="bi bi-hourglass-split"></i></div><div><div class="stat-num"><?= $pending ?></div><div class="stat-lbl">waiting for approval</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="stat"><div class="ic <?= $overdue ? 'bad' : 'ok' ?>"><i class="bi bi-alarm"></i></div><div><div class="stat-num"><?= $overdue ?></div><div class="stat-lbl">overdue</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="stat"><div class="ic <?= $fines ? 'bad' : 'ok' ?>"><i class="bi bi-cash-coin"></i></div><div><div class="stat-num"><?= money($fines) ?></div><div class="stat-lbl">fines charged so far</div></div></div></div>
    </div>

    <h2 class="h5">Items with me</h2>
    <div class="ll-table table-responsive mb-4">
        <table class="table">
            <thead><tr><th>Item</th><th>Lab</th><th class="text-center">Qty</th><th>Issued</th><th>Due</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($loans as $l): $late = (int) $l['days_left'] < 0; ?>
                <tr class="<?= $late ? 'row-overdue' : '' ?>">
                    <td><a href="../equipment_details.php?id=<?= (int) $l['equipment_id'] ?>"><strong><?= e($l['name']) ?></strong></a></td>
                    <td class="small"><?= e($l['lab_name']) ?> (<?= e($l['room_no']) ?>)</td>
                    <td class="text-center"><?= (int) $l['quantity'] ?></td>
                    <td class="small"><?= e(fmt_date($l['issued_on'])) ?></td>
                    <td class="small"><?= e(fmt_date($l['due_date'])) ?></td>
                    <td><?php if ($late): ?><span class="st st-bad">OVERDUE · <?= -(int) $l['days_left'] ?> day(s)</span>
                        <?php elseif ((int) $l['days_left'] === 0): ?><span class="st st-pending">DUE TODAY</span>
                        <?php else: ?><span class="st st-info"><?= (int) $l['days_left'] ?> day(s) left</span><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$loans): ?><tr><td colspan="6" class="text-center text-muted-ll py-3">Nothing borrowed right now.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <h2 class="h5">My requests</h2>
    <div class="ll-table table-responsive mb-4">
        <table class="table">
            <thead><tr><th>#</th><th>Item</th><th class="text-center">Qty</th><th class="text-center">Days</th><th>Requested</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($requests as $r): ?>
                <tr>
                    <td class="text-muted-ll"><?= (int) $r['request_id'] ?></td>
                    <td><a href="../equipment_details.php?id=<?= (int) $r['equipment_id'] ?>"><?= e($r['name']) ?></a><div class="small text-muted-ll"><?= e($r['purpose']) ?></div></td>
                    <td class="text-center"><?= (int) $r['quantity'] ?></td>
                    <td class="text-center"><?= (int) $r['days_needed'] ?></td>
                    <td class="small text-nowrap"><?= e(fmt_date($r['requested_at'])) ?></td>
                    <td><span class="st <?= status_class($r['status']) ?>"><?= e($r['status']) ?></span>
                        <?php if ($r['admin_note']): ?><div class="small text-muted-ll mt-1">“<?= e($r['admin_note']) ?>”</div><?php endif; ?></td>
                    <td class="text-end">
                        <?php if ($r['status'] === 'PENDING'): ?>
                        <form method="post" data-confirm="Cancel this request?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="cancel">
                            <input type="hidden" name="id" value="<?= (int) $r['request_id'] ?>">
                            <button class="btn btn-sm btn-outline-secondary" type="submit">Cancel</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$requests): ?><tr><td colspan="7" class="text-center text-muted-ll py-3">No requests yet. <a href="../equipment.php">Browse equipment</a>.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($history): ?>
    <h2 class="h5">Returned</h2>
    <div class="ll-table table-responsive">
        <table class="table table-sm">
            <thead><tr><th>Item</th><th>Issued</th><th>Due</th><th>Returned</th><th class="text-end">Fine</th></tr></thead>
            <tbody>
            <?php foreach ($history as $h): ?>
                <tr>
                    <td><?= e($h['name']) ?><?= (int) $h['quantity'] > 1 ? ' × ' . (int) $h['quantity'] : '' ?></td>
                    <td class="small"><?= e(fmt_date($h['issued_on'])) ?></td>
                    <td class="small"><?= e(fmt_date($h['due_date'])) ?></td>
                    <td class="small"><?= e(fmt_date($h['returned_on'])) ?></td>
                    <td class="text-end"><?= (float) $h['fine_amount'] > 0 ? '<span class="text-danger fw-semibold">' . money($h['fine_amount']) . '</span>' : '<span class="text-muted-ll">—</span>' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>
