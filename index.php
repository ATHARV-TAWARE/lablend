<?php
require 'config/database.php';
require 'includes/functions.php';
require 'includes/auth.php';

$root = '';
$pageTitle = 'Borrow lab equipment';
$activePage = 'home';

// Headline numbers (scalar subqueries)
$stats = $pdo->query('
    SELECT (SELECT COUNT(*) FROM equipment)                 AS item_types,
           (SELECT COALESCE(SUM(available_qty), 0) FROM equipment) AS available_units,
           (SELECT COUNT(*) FROM labs)                      AS labs,
           (SELECT COUNT(*) FROM issues WHERE returned_on IS NULL) AS out_now
')->fetch();

// Most borrowed equipment (INNER JOIN + COUNT + GROUP BY + ORDER BY + LIMIT)
$popular = $pdo->query('
    SELECT e.equipment_id, e.name, e.category, e.image, e.total_qty, e.available_qty, l.lab_name,
           COUNT(i.issue_id) AS times_borrowed
    FROM equipment e
    INNER JOIN labs l ON e.lab_id = l.lab_id
    INNER JOIN issues i ON i.equipment_id = e.equipment_id
    GROUP BY e.equipment_id, e.name, e.category, e.image, e.total_qty, e.available_qty, l.lab_name
    ORDER BY times_borrowed DESC, e.name
    LIMIT 4
')->fetchAll();

// Lab availability from the VIEW
$labs = $pdo->query('SELECT * FROM lab_availability ORDER BY dept_code, lab_name')->fetchAll();

include 'includes/header.php';
?>
<section class="hero">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <span class="badge bg-light text-dark mb-3"><i class="bi bi-lightning-charge-fill text-warning me-1"></i>No more paper registers</span>
                <h1 class="mb-3">Borrow lab equipment in a few clicks.</h1>
                <p class="lead mb-4">See what every department lab has right now – Arduinos, oscilloscopes, Jetson boards, cameras – request it online and track your due dates.</p>
                <form action="equipment.php" method="get" class="hero-search d-flex gap-2" role="search">
                    <input type="search" name="q" class="form-control form-control-lg" placeholder="Try “Arduino” or “oscilloscope”" aria-label="Search equipment" maxlength="80">
                    <button class="btn btn-light btn-lg px-4" type="submit"><i class="bi bi-search"></i></button>
                </form>
            </div>
            <div class="col-lg-5">
                <div class="row g-3">
                    <div class="col-6"><div class="hero-stat"><div class="n"><?= (int) $stats['item_types'] ?></div><div class="l">types of equipment</div></div></div>
                    <div class="col-6"><div class="hero-stat"><div class="n"><?= (int) $stats['available_units'] ?></div><div class="l">units available now</div></div></div>
                    <div class="col-6"><div class="hero-stat"><div class="n"><?= (int) $stats['labs'] ?></div><div class="l">labs</div></div></div>
                    <div class="col-6"><div class="hero-stat"><div class="n"><?= (int) $stats['out_now'] ?></div><div class="l">loans in progress</div></div></div>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="container mt-5">
    <div class="row g-3 mb-5">
        <?php foreach ([
            ['bi-search', 'Find it', 'Search by name, lab or category and see live stock.'],
            ['bi-send', 'Request it', 'Say how many, for how many days, and why.'],
            ['bi-check2-circle', 'Collect it', 'The lab in-charge approves and issues it to you.'],
            ['bi-arrow-return-left', 'Return on time', 'Late returns cost ' . money(FINE_PER_DAY) . ' per item per day.'],
        ] as $i => [$icon, $title, $text]): ?>
        <div class="col-6 col-lg-3">
            <div class="ll-card p-3 h-100">
                <div class="d-flex align-items-center gap-2 mb-1"><span class="badge rounded-pill bg-primary-subtle text-primary"><?= $i + 1 ?></span><i class="bi <?= $icon ?> text-primary"></i><strong><?= e($title) ?></strong></div>
                <div class="small text-muted-ll"><?= e($text) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($popular): ?>
    <div class="d-flex justify-content-between align-items-end mb-3">
        <h2 class="h4 mb-0">Most borrowed</h2>
        <a href="equipment.php" class="small">Browse all equipment &rarr;</a>
    </div>
    <div class="row g-3 mb-5">
        <?php foreach ($popular as $p): ?>
        <div class="col-sm-6 col-lg-3">
            <a href="equipment_details.php?id=<?= (int) $p['equipment_id'] ?>" class="text-decoration-none text-reset">
                <div class="ll-card hover h-100">
                    <?= equipment_image($p, $root) ?>
                    <div class="p-3">
                        <span class="cat-chip"><?= e($p['category']) ?></span>
                        <div class="eq-title mt-1"><?= e($p['name']) ?></div>
                        <div class="meta mb-2"><?= e($p['lab_name']) ?> · borrowed <?= (int) $p['times_borrowed'] ?>×</div>
                        <?= stock_badge((int) $p['available_qty'], (int) $p['total_qty']) ?>
                    </div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <h2 class="h4 mb-3">Labs at a glance</h2>
    <div class="row g-3">
        <?php foreach ($labs as $l): $pct = $l['total_units'] ? round($l['available_units'] / $l['total_units'] * 100) : 0; ?>
        <div class="col-md-6 col-lg-4">
            <a href="equipment.php?lab=<?= (int) $l['lab_id'] ?>" class="text-decoration-none text-reset">
                <div class="ll-card hover p-3 h-100">
                    <div class="d-flex justify-content-between"><strong><?= e($l['lab_name']) ?></strong><span class="meta"><?= e($l['dept_code']) ?></span></div>
                    <div class="meta mb-2"><?= (int) $l['item_types'] ?> item types · <?= (int) $l['issued_units'] ?> units on loan</div>
                    <div class="bar mb-1"><span style="width:<?= $pct ?>%"></span></div>
                    <div class="small"><?= (int) $l['available_units'] ?> of <?= (int) $l['total_units'] ?> units available</div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
