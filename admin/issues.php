<?php
require '../config/database.php';
require '../includes/functions.php';
require '../includes/auth.php';
require '../includes/lending.php';
require_admin();

$root = '../';
$pageTitle = 'Issued items';
$activePage = 'issues';
$isAdmin = true;

/* ======================= MARK AS RETURNED ======================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'return') {
    if (!verify_csrf()) {
        set_flash('danger', 'Session expired. Please try again.');
        redirect('issues.php');
    }
    $cond = trim($_POST['condition_note'] ?? '');
    try {
        // One transaction: returned_on + fine, stock + qty (includes/lending.php)
        $r = return_issue($pdo, (int) ($_POST['id'] ?? 0), mb_substr($cond, 0, 255));
        set_flash('success', $r['equipment'] . ' returned by ' . $r['name'] . '. '
            . ($r['fine'] > 0 ? 'Late by ' . $r['days_late'] . ' day(s) – fine ' . money($r['fine']) . '.' : 'On time – no fine.'));
    } catch (Throwable $e) {
        set_flash($e instanceof LendingError ? 'warning' : 'danger', lending_error_message('record the return', $e));
    }
    redirect('issues.php' . (isset($_GET['filter']) ? '?filter=' . urlencode($_GET['filter']) : ''));
}

$filter = $_GET['filter'] ?? 'open';
if (!in_array($filter, ['open', 'overdue', 'returned'], true)) {
    $filter = 'open';
}
$where = ['open' => 'i.returned_on IS NULL', 'overdue' => 'i.returned_on IS NULL AND i.due_date < CURDATE()', 'returned' => 'i.returned_on IS NOT NULL'][$filter];

$rows = $pdo->query("
    SELECT i.*, DATEDIFF(CURDATE(), i.due_date) AS days_late,
           s.name AS student, s.prn, s.phone, e.name AS item, l.lab_name
    FROM issues i
    INNER JOIN students s  ON i.student_id = s.student_id
    INNER JOIN equipment e ON i.equipment_id = e.equipment_id
    INNER JOIN labs l      ON e.lab_id = l.lab_id
    WHERE $where
    ORDER BY " . ($filter === 'returned' ? 'i.returned_on DESC' : 'i.due_date ASC'))->fetchAll();

$counts = $pdo->query("
    SELECT SUM(returned_on IS NULL) AS open, SUM(returned_on IS NULL AND due_date < CURDATE()) AS overdue, SUM(returned_on IS NOT NULL) AS returned
    FROM issues")->fetch();

include '../includes/header.php';
?>
<div class="container py-4">
    <h1 class="h3 mb-3">Issued items</h1>
    <?php show_flash(); ?>
    <ul class="nav nav-pills mb-3 gap-1">
        <?php foreach (['open' => 'On loan', 'overdue' => 'Overdue', 'returned' => 'Returned'] as $k => $lbl): ?>
            <li class="nav-item"><a class="nav-link <?= $filter === $k ? 'active' : '' ?>" href="issues.php?filter=<?= $k ?>"><?= $lbl ?> <span class="badge rounded-pill <?= $filter === $k ? 'bg-light text-dark' : 'bg-secondary-subtle text-dark' ?>"><?= (int) $counts[$k] ?></span></a></li>
        <?php endforeach; ?>
    </ul>
    <div class="ll-table table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Student</th><th>Item</th><th class="text-center">Qty</th><th>Issued</th><th>Due</th><th><?= $filter === 'returned' ? 'Returned' : 'Status' ?></th><th class="text-end"><?= $filter === 'returned' ? 'Fine' : 'Action' ?></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): $late = $r['returned_on'] === null && (int) $r['days_late'] > 0; ?>
                <tr class="<?= $late ? 'row-overdue' : '' ?>">
                    <td><strong><?= e($r['student']) ?></strong><div class="small text-muted-ll"><?= e($r['prn']) ?> · <?= e($r['phone']) ?></div></td>
                    <td><?= e($r['item']) ?><div class="small text-muted-ll"><?= e($r['lab_name']) ?></div></td>
                    <td class="text-center"><?= (int) $r['quantity'] ?></td>
                    <td class="small"><?= e(fmt_date($r['issued_on'])) ?></td>
                    <td class="small"><?= e(fmt_date($r['due_date'])) ?></td>
                    <?php if ($filter === 'returned'): ?>
                        <td class="small"><?= e(fmt_date($r['returned_on'])) ?><div class="text-muted-ll"><?= e($r['condition_note']) ?></div></td>
                        <td class="text-end"><?= (float) $r['fine_amount'] > 0 ? '<span class="text-danger fw-semibold">' . money($r['fine_amount']) . '</span>' : '—' ?></td>
                    <?php else: ?>
                        <td><?= $late ? '<span class="st st-bad">' . (int) $r['days_late'] . ' day(s) late · ' . money((int) $r['days_late'] * FINE_PER_DAY * (int) $r['quantity']) . '</span>' : '<span class="st st-info">due in ' . -(int) $r['days_late'] . ' day(s)</span>' ?></td>
                        <td class="text-end">
                            <div class="dropdown d-inline-block">
                                <button class="btn btn-sm btn-brand dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"><i class="bi bi-arrow-return-left"></i> Mark returned</button>
                                <div class="dropdown-menu dropdown-menu-end p-3" style="min-width:260px">
                                    <form method="post" action="issues.php?filter=<?= e($filter) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="return">
                                        <input type="hidden" name="id" value="<?= (int) $r['issue_id'] ?>">
                                        <label class="form-label small" for="c<?= (int) $r['issue_id'] ?>">Condition</label>
                                        <input id="c<?= (int) $r['issue_id'] ?>" name="condition_note" class="form-control form-control-sm mb-2" maxlength="255" placeholder="Good">
                                        <?php if ($late): ?><div class="small text-danger mb-2">Fine: <?= money((int) $r['days_late'] * FINE_PER_DAY * (int) $r['quantity']) ?></div><?php endif; ?>
                                        <button class="btn btn-sm btn-brand w-100" type="submit">Confirm return</button>
                                    </form>
                                </div>
                            </div>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted-ll py-4">Nothing here.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
