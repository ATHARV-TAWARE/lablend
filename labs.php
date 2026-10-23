<?php
require 'config/database.php';
require 'includes/functions.php';
require 'includes/auth.php';

$root = '';
$pageTitle = 'Labs';
$activePage = 'labs';

// Labs with department and stock (JOIN with the lab_availability VIEW)
$labs = $pdo->query('
    SELECT l.lab_id, l.lab_name, l.room_no, l.incharge, d.dept_name,
           v.item_types, v.total_units, v.available_units, v.issued_units
    FROM labs l
    INNER JOIN departments d    ON l.dept_id = d.dept_id
    INNER JOIN lab_availability v ON v.lab_id = l.lab_id
    ORDER BY d.dept_name, l.lab_name
')->fetchAll();

include 'includes/header.php';
?>
<section class="page-head">
    <div class="container">
        <h1>Labs</h1>
        <p class="text-muted-ll mb-0"><?= count($labs) ?> labs across <?= count(array_unique(array_column($labs, 'dept_name'))) ?> departments</p>
    </div>
</section>
<div class="container mt-4">
    <div class="ll-table table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Lab</th><th>Department</th><th>Room</th><th>In-charge</th><th class="text-center">Item types</th><th style="min-width:180px">Availability</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($labs as $l): $pct = $l['total_units'] ? round($l['available_units'] / $l['total_units'] * 100) : 0; ?>
                <tr>
                    <td><strong><?= e($l['lab_name']) ?></strong></td>
                    <td class="small"><?= e($l['dept_name']) ?></td>
                    <td class="small"><?= e($l['room_no']) ?></td>
                    <td class="small"><?= e($l['incharge']) ?></td>
                    <td class="text-center"><?= (int) $l['item_types'] ?></td>
                    <td>
                        <div class="bar mb-1"><span style="width:<?= $pct ?>%"></span></div>
                        <div class="small"><?= (int) $l['available_units'] ?>/<?= (int) $l['total_units'] ?> units free</div>
                    </td>
                    <td class="text-end"><a href="equipment.php?lab=<?= (int) $l['lab_id'] ?>" class="btn btn-sm btn-outline-brand">View items</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
