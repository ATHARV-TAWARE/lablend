<?php
require '../config/database.php';
require '../includes/functions.php';
require '../includes/auth.php';
require_admin();

$root = '../';
$pageTitle = 'Students';
$activePage = 'students';
$isAdmin = true;

$q = trim($_GET['q'] ?? '');
$only = $_GET['only'] ?? '';

// One row per student: LEFT JOIN issues + conditional SUMs + GROUP BY
$sql = '
    SELECT s.student_id, s.prn, s.name, s.email, s.phone, s.year_of_study, d.dept_code,
           COALESCE(SUM(CASE WHEN i.returned_on IS NULL THEN i.quantity END), 0)                           AS holding,
           COALESCE(SUM(CASE WHEN i.returned_on IS NULL AND i.due_date < CURDATE() THEN 1 END), 0)        AS overdue,
           COUNT(i.issue_id)                                                                               AS total_loans,
           COALESCE(SUM(i.fine_amount), 0)                                                                 AS fines
    FROM students s
    INNER JOIN departments d ON s.dept_id = d.dept_id
    LEFT JOIN issues i       ON i.student_id = s.student_id
    ' . ($q !== '' ? 'WHERE s.name LIKE ? OR s.prn LIKE ? OR s.email LIKE ?' : '') . '
    GROUP BY s.student_id, s.prn, s.name, s.email, s.phone, s.year_of_study, d.dept_code
    ' . ($only === 'overdue' ? 'HAVING overdue > 0' : '') . '
    ORDER BY overdue DESC, s.name';
$st = $pdo->prepare($sql);
$st->execute($q !== '' ? ["%$q%", "%$q%", "%$q%"] : []);
$students = $st->fetchAll();

include '../includes/header.php';
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h1 class="h3 mb-0">Students</h1>
        <form method="get" class="d-flex gap-2">
            <input type="search" name="q" class="form-control form-control-sm" value="<?= e($q) ?>" placeholder="Name, PRN or email" aria-label="Search students">
            <select name="only" class="form-select form-select-sm" data-autosubmit aria-label="Filter"><option value="">All</option><option value="overdue" <?= $only === 'overdue' ? 'selected' : '' ?>>With overdue items</option></select>
            <button class="btn btn-sm btn-brand" type="submit">Go</button>
        </form>
    </div>
    <div class="ll-table table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Student</th><th>PRN</th><th>Dept · Year</th><th>Phone</th><th class="text-center">Holding</th><th class="text-center">Overdue</th><th class="text-center">Loans</th><th class="text-end">Fines</th></tr></thead>
            <tbody>
            <?php foreach ($students as $s): ?>
                <tr class="<?= $s['overdue'] ? 'row-overdue' : '' ?>">
                    <td><strong><?= e($s['name']) ?></strong><div class="small text-muted-ll"><?= e($s['email']) ?></div></td>
                    <td class="small"><?= e($s['prn']) ?></td>
                    <td class="small"><?= e($s['dept_code']) ?> · Y<?= (int) $s['year_of_study'] ?></td>
                    <td class="small"><?= e($s['phone']) ?></td>
                    <td class="text-center"><?= (int) $s['holding'] ?>/<?= MAX_ACTIVE_ITEMS ?></td>
                    <td class="text-center"><?= $s['overdue'] ? '<span class="st st-bad">' . (int) $s['overdue'] . '</span>' : '0' ?></td>
                    <td class="text-center"><?= (int) $s['total_loans'] ?></td>
                    <td class="text-end"><?= (float) $s['fines'] ? money($s['fines']) : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$students): ?><tr><td colspan="8" class="text-center text-muted-ll py-4">No students found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
