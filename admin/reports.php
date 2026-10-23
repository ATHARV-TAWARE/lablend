<?php
require '../config/database.php';
require '../includes/functions.php';
require '../includes/auth.php';
require_admin();

$root = '../';
$pageTitle = 'Reports';
$activePage = 'reports';
$isAdmin = true;

/**
 * Each report = [title, DBMS concept, SQL]. The SQL shown on the page is
 * exactly the SQL that produced the table – useful for the viva.
 */
$reports = [
    ['Most borrowed equipment', 'INNER JOIN · COUNT · GROUP BY · ORDER BY · LIMIT',
     "SELECT e.name, l.lab_name, COUNT(i.issue_id) AS times_borrowed
FROM equipment e
INNER JOIN labs l   ON e.lab_id = l.lab_id
INNER JOIN issues i ON i.equipment_id = e.equipment_id
GROUP BY e.equipment_id, e.name, l.lab_name
ORDER BY times_borrowed DESC
LIMIT 5"],
    ['Students with 2 or more overdue items', 'GROUP BY · HAVING · DATEDIFF',
     "SELECT s.prn, s.name, COUNT(*) AS overdue_items,
       SUM(DATEDIFF(CURDATE(), i.due_date) * i.quantity) * " . FINE_PER_DAY . " AS fine_so_far
FROM issues i
INNER JOIN students s ON i.student_id = s.student_id
WHERE i.returned_on IS NULL AND i.due_date < CURDATE()
GROUP BY s.student_id, s.prn, s.name
HAVING COUNT(*) >= 2"],
    ['Equipment never borrowed', 'LEFT JOIN … IS NULL (anti-join)',
     "SELECT e.name, l.lab_name, e.total_qty
FROM equipment e
INNER JOIN labs l ON e.lab_id = l.lab_id
LEFT JOIN issues i ON i.equipment_id = e.equipment_id
WHERE i.issue_id IS NULL
ORDER BY l.lab_name, e.name"],
    ['Borrowed more than the average item', 'Subquery in HAVING · AVG',
     "SELECT e.name, COUNT(i.issue_id) AS times_borrowed
FROM equipment e
INNER JOIN issues i ON i.equipment_id = e.equipment_id
GROUP BY e.equipment_id, e.name
HAVING COUNT(i.issue_id) > (
    SELECT AVG(cnt) FROM (SELECT COUNT(*) AS cnt FROM issues GROUP BY equipment_id) AS per_item
)"],
    ['Usage by department', 'Multi-table JOIN · SUM · GROUP BY',
     "SELECT d.dept_code, COUNT(i.issue_id) AS loans,
       SUM(i.quantity) AS units, SUM(i.fine_amount) AS fines
FROM departments d
INNER JOIN students s ON s.dept_id = d.dept_id
INNER JOIN issues i   ON i.student_id = s.student_id
GROUP BY d.dept_id, d.dept_code
ORDER BY loans DESC"],
    ['Request outcomes', 'GROUP BY on ENUM · COUNT',
     "SELECT status, COUNT(*) AS requests
FROM borrow_requests
GROUP BY status
ORDER BY requests DESC"],
    ['Lab availability (VIEW)', 'CREATE VIEW · LEFT JOIN · SUM',
     "SELECT lab_name, dept_code, item_types, total_units, available_units, issued_units
FROM lab_availability
ORDER BY issued_units DESC"],
];

$results = [];
foreach ($reports as $i => [$title, $concept, $sql]) {
    $results[$i] = $pdo->query($sql)->fetchAll();
}

include '../includes/header.php';
?>
<div class="container py-4">
    <h1 class="h3 mb-1">Reports</h1>
    <p class="text-muted-ll mb-4">Live results from <code>lablend_db</code>. Click “Show SQL” to see the exact query.</p>
    <div class="row g-4">
    <?php foreach ($reports as $i => [$title, $concept, $sql]): $rows = $results[$i]; ?>
        <div class="col-lg-6">
            <div class="ll-card p-3 h-100">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                    <div><h2 class="h6 mb-0"><?= e($title) ?></h2><span class="cat-chip"><?= e($concept) ?></span></div>
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#sql<?= $i ?>" aria-expanded="false" aria-controls="sql<?= $i ?>">Show SQL</button>
                </div>
                <pre class="collapse small bg-light p-2 rounded" id="sql<?= $i ?>"><?= e($sql) ?></pre>
                <?php if ($rows): ?>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><?php foreach (array_keys($rows[0]) as $col): ?><th class="small"><?= e($col) ?></th><?php endforeach; ?></tr></thead>
                        <tbody><?php foreach ($rows as $r): ?><tr><?php foreach ($r as $v): ?><td class="small"><?= e($v) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
                    </table>
                </div>
                <?php else: ?><p class="small text-muted-ll mb-0">No rows.</p><?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
