<?php
require 'config/database.php';
require 'includes/functions.php';
require 'includes/auth.php';

$root = '';
$pageTitle = 'Equipment';
$activePage = 'equipment';

// Filters (all optional)
$q        = trim($_GET['q'] ?? '');
$lab      = get_int('lab');
$dept     = get_int('dept');
$category = trim($_GET['category'] ?? '');
$onlyAvail = isset($_GET['available']);

$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(e.name LIKE ? OR e.description LIKE ? OR e.category LIKE ?)';
    array_push($params, "%$q%", "%$q%", "%$q%");
}
if ($lab) {
    $where[] = 'e.lab_id = ?';
    $params[] = $lab;
}
if ($dept) {
    $where[] = 'l.dept_id = ?';
    $params[] = $dept;
}
if ($category !== '') {
    $where[] = 'e.category = ?';
    $params[] = $category;
}
if ($onlyAvail) {
    $where[] = 'e.available_qty > 0';
}

// SELECT with two INNER JOINs + dynamic WHERE (prepared statement)
$sql = 'SELECT e.*, l.lab_name, d.dept_code
        FROM equipment e
        INNER JOIN labs l        ON e.lab_id = l.lab_id
        INNER JOIN departments d ON l.dept_id = d.dept_id'
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . ' ORDER BY e.available_qty = 0, e.name';
$st = $pdo->prepare($sql);
$st->execute($params);
$items = $st->fetchAll();

$labs = get_labs($pdo);
$depts = get_departments($pdo);
$cats = get_categories($pdo);

include 'includes/header.php';
?>
<section class="page-head">
    <div class="container">
        <h1>Equipment</h1>
        <p class="text-muted-ll mb-0"><?= count($items) ?> item<?= count($items) === 1 ? '' : 's' ?><?= $q !== '' ? ' matching “' . e($q) . '”' : '' ?> · live stock from every lab</p>
    </div>
</section>

<div class="container mt-4">
    <form method="get" class="ll-card p-3 mb-4">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1" for="fq">Search</label>
                <input type="search" id="fq" name="q" class="form-control form-control-sm" value="<?= e($q) ?>" placeholder="Name or description" maxlength="80">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1" for="fdept">Department</label>
                <select id="fdept" name="dept" class="form-select form-select-sm" data-autosubmit>
                    <option value="">All</option>
                    <?php foreach ($depts as $d): ?><option value="<?= (int) $d['dept_id'] ?>" <?= $dept === (int) $d['dept_id'] ? 'selected' : '' ?>><?= e($d['dept_code']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small mb-1" for="flab">Lab</label>
                <select id="flab" name="lab" class="form-select form-select-sm" data-autosubmit>
                    <option value="">All labs</option>
                    <?php foreach ($labs as $l): ?><option value="<?= (int) $l['lab_id'] ?>" <?= $lab === (int) $l['lab_id'] ? 'selected' : '' ?>><?= e($l['lab_name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1" for="fcat">Category</label>
                <select id="fcat" name="category" class="form-select form-select-sm" data-autosubmit>
                    <option value="">All</option>
                    <?php foreach ($cats as $c): ?><option <?= $category === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2 d-flex gap-2 align-items-center">
                <div class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" id="favail" name="available" <?= $onlyAvail ? 'checked' : '' ?> onchange="this.form.submit()">
                    <label class="form-check-label small" for="favail">Available only</label>
                </div>
            </div>
        </div>
        <div class="mt-2 d-flex gap-2">
            <button class="btn btn-sm btn-brand" type="submit"><i class="bi bi-funnel me-1"></i>Apply</button>
            <a href="equipment.php" class="btn btn-sm btn-outline-secondary">Clear</a>
        </div>
    </form>

    <div class="row g-3">
        <?php foreach ($items as $it): ?>
        <div class="col-sm-6 col-lg-4 col-xl-3">
            <a href="equipment_details.php?id=<?= (int) $it['equipment_id'] ?>" class="text-decoration-none text-reset">
                <div class="ll-card hover h-100">
                    <?= equipment_image($it, $root) ?>
                    <div class="p-3">
                        <span class="cat-chip"><?= e($it['category']) ?></span>
                        <div class="eq-title mt-1"><?= e($it['name']) ?></div>
                        <div class="meta mb-2"><i class="bi bi-building me-1"></i><?= e($it['lab_name']) ?> · <?= e($it['dept_code']) ?></div>
                        <?= stock_badge((int) $it['available_qty'], (int) $it['total_qty']) ?>
                    </div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
        <?php if (!$items): ?>
            <div class="col-12"><div class="empty-state"><i class="bi bi-search"></i>No equipment found. Try another word or clear the filters.</div></div>
        <?php endif; ?>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
