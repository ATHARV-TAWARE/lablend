<?php
require '../config/database.php';
require '../includes/functions.php';
require '../includes/auth.php';
require '../includes/lending.php';
require_admin();

$root = '../';
$pageTitle = 'Borrow requests';
$activePage = 'requests';
$isAdmin = true;

$statuses = ['PENDING', 'APPROVED', 'REJECTED', 'CANCELLED'];

/* ======================= APPROVE / REJECT ======================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('danger', 'Session expired. Please try again.');
        redirect('requests.php');
    }
    $id = (int) ($_POST['id'] ?? 0);
    $note = trim($_POST['admin_note'] ?? '');
    if (mb_strlen($note) > 255) {
        set_flash('danger', 'The note must be under 255 characters.');
        redirect('requests.php');
    }
    try {
        if (($_POST['action'] ?? '') === 'approve') {
            // One transaction: stock − qty, INSERT issue, request → APPROVED (includes/lending.php)
            $r = approve_request($pdo, $id, $note);
            set_flash('success', 'Approved: issue ' . $r['equipment'] . ' to ' . $r['name'] . ' – due back on ' . fmt_date($r['due_date']) . '.');
        } elseif (($_POST['action'] ?? '') === 'reject') {
            reject_request($pdo, $id, $note);
            set_flash('success', 'Request #' . $id . ' rejected.');
        }
    } catch (Throwable $e) {
        set_flash($e instanceof LendingError ? 'warning' : 'danger', lending_error_message('update this request', $e));
    }
    redirect('requests.php');
}

/* ======================= LIST ======================= */
$filter = strtoupper($_GET['status'] ?? 'PENDING');
if (!in_array($filter, [...$statuses, 'ALL'], true)) {
    $filter = 'PENDING';
}
$counts = array_fill_keys($statuses, 0);
foreach ($pdo->query('SELECT status, COUNT(*) AS n FROM borrow_requests GROUP BY status') as $row) {
    $counts[$row['status']] = (int) $row['n'];
}

// For each request also show the student's current load and overdue count (correlated subqueries)
$sql = "
    SELECT r.*, s.name AS student, s.prn, d.dept_code, e.name AS item, e.available_qty, l.lab_name,
           (SELECT COALESCE(SUM(i.quantity), 0) FROM issues i WHERE i.student_id = r.student_id AND i.returned_on IS NULL) AS holding,
           (SELECT COUNT(*) FROM issues i WHERE i.student_id = r.student_id AND i.returned_on IS NULL AND i.due_date < CURDATE()) AS overdue
    FROM borrow_requests r
    INNER JOIN students s    ON r.student_id = s.student_id
    INNER JOIN departments d ON s.dept_id = d.dept_id
    INNER JOIN equipment e   ON r.equipment_id = e.equipment_id
    INNER JOIN labs l        ON e.lab_id = l.lab_id
    " . ($filter !== 'ALL' ? 'WHERE r.status = ?' : '') . "
    ORDER BY r.requested_at " . ($filter === 'PENDING' ? 'ASC' : 'DESC');
$st = $pdo->prepare($sql);
$st->execute($filter !== 'ALL' ? [$filter] : []);
$rows = $st->fetchAll();

include '../includes/header.php';
?>
<div class="container py-4">
    <h1 class="h3 mb-3">Borrow requests</h1>
    <?php show_flash(); ?>
    <ul class="nav nav-pills mb-3 gap-1">
        <?php foreach ([...$statuses, 'ALL'] as $s): $n = $s === 'ALL' ? array_sum($counts) : $counts[$s]; ?>
            <li class="nav-item"><a class="nav-link <?= $filter === $s ? 'active' : '' ?>" href="requests.php?status=<?= $s ?>"><?= ucfirst(strtolower($s)) ?> <span class="badge rounded-pill <?= $filter === $s ? 'bg-light text-dark' : 'bg-secondary-subtle text-dark' ?>"><?= $n ?></span></a></li>
        <?php endforeach; ?>
    </ul>

    <div class="ll-table table-responsive">
        <table class="table table-hover">
            <thead><tr><th>#</th><th>Student</th><th>Item</th><th class="text-center">Qty</th><th class="text-center">Days</th><th>Checks</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r):
                $stockOk = (int) $r['available_qty'] >= (int) $r['quantity'];
                $limitOk = (int) $r['holding'] + (int) $r['quantity'] <= MAX_ACTIVE_ITEMS;
                $clean   = (int) $r['overdue'] === 0; ?>
                <tr>
                    <td class="text-muted-ll"><?= (int) $r['request_id'] ?></td>
                    <td><strong><?= e($r['student']) ?></strong><div class="small text-muted-ll"><?= e($r['prn']) ?> · <?= e($r['dept_code']) ?></div></td>
                    <td><?= e($r['item']) ?><div class="small text-muted-ll"><?= e($r['lab_name']) ?> · “<?= e($r['purpose']) ?>”</div></td>
                    <td class="text-center"><?= (int) $r['quantity'] ?></td>
                    <td class="text-center"><?= (int) $r['days_needed'] ?></td>
                    <td class="small text-nowrap">
                        <?php if ($r['status'] === 'PENDING'): ?>
                            <div><?= $stockOk ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle-fill text-danger"></i>' ?> stock <?= (int) $r['available_qty'] ?></div>
                            <div><?= $limitOk ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle-fill text-danger"></i>' ?> holds <?= (int) $r['holding'] ?>/<?= MAX_ACTIVE_ITEMS ?></div>
                            <div><?= $clean ? '<i class="bi bi-check-circle-fill text-success"></i> no overdue' : '<i class="bi bi-x-circle-fill text-danger"></i> ' . (int) $r['overdue'] . ' overdue' ?></div>
                        <?php else: ?>
                            <span class="text-muted-ll"><?= e(fmt_date($r['reviewed_at'] ?? $r['requested_at'])) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><span class="st <?= status_class($r['status']) ?>"><?= e($r['status']) ?></span>
                        <?php if ($r['admin_note']): ?><div class="small text-muted-ll mt-1">“<?= e($r['admin_note']) ?>”</div><?php endif; ?></td>
                    <td class="text-end text-nowrap">
                        <?php if ($r['status'] === 'PENDING'): ?>
                            <form method="post" class="d-inline" data-confirm="Approve and issue <?= e($r['item']) ?> to <?= e($r['student']) ?>?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="id" value="<?= (int) $r['request_id'] ?>">
                                <button class="btn btn-sm btn-success" type="submit" <?= $stockOk && $limitOk && $clean ? '' : 'title="A check failed – approval will explain why"' ?>><i class="bi bi-check-lg"></i> Approve</button>
                            </form>
                            <div class="dropdown d-inline-block">
                                <button class="btn btn-sm btn-outline-danger dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"><i class="bi bi-x-lg"></i> Reject</button>
                                <div class="dropdown-menu dropdown-menu-end p-3" style="min-width:260px">
                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="id" value="<?= (int) $r['request_id'] ?>">
                                        <label class="form-label small" for="n<?= (int) $r['request_id'] ?>">Reason (shown to the student)</label>
                                        <textarea id="n<?= (int) $r['request_id'] ?>" name="admin_note" rows="2" maxlength="255" class="form-control form-control-sm mb-2"></textarea>
                                        <button class="btn btn-sm btn-danger w-100" type="submit">Reject request</button>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="8" class="text-center text-muted-ll py-4"><?= $filter === 'PENDING' ? 'No pending requests – all caught up.' : 'Nothing here.' ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
