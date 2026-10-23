<?php
require '../config/database.php';
require '../includes/functions.php';
require '../includes/auth.php';
require_admin();

$root = '../';
$pageTitle = 'Dashboard';
$activePage = 'dashboard';
$isAdmin = true;

$t = $pdo->query("
    SELECT (SELECT COUNT(*) FROM borrow_requests WHERE status = 'PENDING')                    AS pending,
           (SELECT COUNT(*) FROM issues WHERE returned_on IS NULL)                            AS open_issues,
           (SELECT COUNT(*) FROM issues WHERE returned_on IS NULL AND due_date < CURDATE())   AS overdue,
           (SELECT COALESCE(SUM(available_qty), 0) FROM equipment)                            AS available,
           (SELECT COUNT(*) FROM students)                                                    AS students,
           (SELECT COALESCE(SUM(fine_amount), 0) FROM issues)                                 AS fines
")->fetch();

$pendingList = $pdo->query("
    SELECT r.request_id, r.quantity, r.requested_at, s.name AS student, e.name AS item, e.available_qty
    FROM borrow_requests r
    INNER JOIN students s  ON r.student_id = s.student_id
    INNER JOIN equipment e ON r.equipment_id = e.equipment_id
    WHERE r.status = 'PENDING'
    ORDER BY r.requested_at
    LIMIT 5")->fetchAll();

$overdueList = $pdo->query("
    SELECT i.issue_id, i.quantity, s.name AS student, s.phone, e.name AS item, i.due_date,
           DATEDIFF(CURDATE(), i.due_date) AS days_late
    FROM issues i
    INNER JOIN students s  ON i.student_id = s.student_id
    INNER JOIN equipment e ON i.equipment_id = e.equipment_id
    WHERE i.returned_on IS NULL AND i.due_date < CURDATE()
    ORDER BY i.due_date
    LIMIT 6")->fetchAll();

$lowStock = $pdo->query("
    SELECT e.equipment_id, e.name, e.available_qty, e.total_qty, l.lab_name
    FROM equipment e INNER JOIN labs l ON e.lab_id = l.lab_id
    WHERE e.available_qty = 0
    ORDER BY e.name")->fetchAll();

include '../includes/header.php';
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Dashboard</h1>
        <span class="small text-muted-ll">Logged in as <strong><?= e($_SESSION['admin_username'] ?? 'admin') ?></strong></span>
    </div>
    <?php show_flash(); ?>
    <div class="row g-3 mb-4">
        <?php foreach ([
            ['Pending requests', $t['pending'], 'bi-inbox', 'warn', 'requests.php'],
            ['Items on loan', $t['open_issues'], 'bi-box-arrow-up-right', '', 'issues.php'],
            ['Overdue', $t['overdue'], 'bi-alarm', $t['overdue'] ? 'bad' : 'ok', 'issues.php?filter=overdue'],
            ['Units available', $t['available'], 'bi-cpu', 'ok', 'equipment.php'],
            ['Students', $t['students'], 'bi-people', '', 'students.php'],
            ['Fines charged', money($t['fines']), 'bi-cash-coin', '', 'reports.php'],
        ] as [$lbl, $num, $icon, $tone, $link]): ?>
        <div class="col-6 col-md-4 col-xl-2">
            <a href="<?= $link ?>" class="text-decoration-none text-reset"><div class="stat"><div class="ic <?= $tone ?>"><i class="bi <?= $icon ?>"></i></div><div><div class="stat-num"><?= e($num) ?></div><div class="stat-lbl"><?= e($lbl) ?></div></div></div></a>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="ll-card p-3 h-100">
                <div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h5 mb-0">Waiting for approval</h2><a href="requests.php" class="small">All requests</a></div>
                <?php if ($pendingList): ?>
                <table class="table table-sm align-middle mb-0">
                    <?php foreach ($pendingList as $p): ?>
                    <tr>
                        <td><strong><?= e($p['student']) ?></strong><div class="small text-muted-ll"><?= e($p['item']) ?> × <?= (int) $p['quantity'] ?></div></td>
                        <td class="small"><?= (int) $p['available_qty'] >= (int) $p['quantity'] ? '<span class="st st-ok">in stock</span>' : '<span class="st st-bad">no stock</span>' ?></td>
                        <td class="text-end"><a href="requests.php" class="btn btn-sm btn-outline-brand">Review</a></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <?php else: ?><p class="text-muted-ll small mb-0">No pending requests – all caught up.</p><?php endif; ?>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="ll-card p-3 h-100">
                <div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h5 mb-0 text-danger">Overdue</h2><a href="issues.php?filter=overdue" class="small">All overdue</a></div>
                <?php if ($overdueList): ?>
                <table class="table table-sm align-middle mb-0">
                    <?php foreach ($overdueList as $o): ?>
                    <tr>
                        <td><strong><?= e($o['student']) ?></strong><div class="small text-muted-ll"><?= e($o['item']) ?> · <?= e($o['phone']) ?></div></td>
                        <td class="small text-danger text-nowrap"><?= (int) $o['days_late'] ?> day(s) late</td>
                        <td class="text-end small text-nowrap"><?= money((int) $o['days_late'] * FINE_PER_DAY * (int) $o['quantity']) ?> so far</td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <?php else: ?><p class="text-muted-ll small mb-0">Nothing overdue.</p><?php endif; ?>
            </div>
        </div>
        <?php if ($lowStock): ?>
        <div class="col-12">
            <div class="ll-card p-3">
                <h2 class="h6 mb-2"><i class="bi bi-exclamation-triangle text-warning me-1"></i>Out of stock right now</h2>
                <?php foreach ($lowStock as $l): ?><span class="st st-pending me-1 mb-1"><?= e($l['name']) ?> · <?= e($l['lab_name']) ?></span><?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
