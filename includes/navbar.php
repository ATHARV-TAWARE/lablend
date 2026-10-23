<?php
/** Navigation bar – public/student version and admin version. */
function nav_link(string $href, string $label, string $key, string $active, string $icon = ''): string
{
    $cls  = 'nav-link' . ($key === $active ? ' active' : '');
    $aria = $key === $active ? ' aria-current="page"' : '';
    $i    = $icon ? '<i class="bi ' . $icon . ' me-1"></i>' : '';
    return '<li class="nav-item"><a class="' . $cls . '" href="' . e($href) . '"' . $aria . '>' . $i . e($label) . '</a></li>';
}
?>
<nav class="navbar navbar-expand-lg ll-navbar sticky-top">
    <div class="container">
        <a class="navbar-brand" href="<?= e($root) ?><?= $isAdmin ? 'admin/dashboard.php' : 'index.php' ?>">
            <span class="brand-mark"><i class="bi bi-cpu"></i></span>
            <span class="brand-text">LabLend<?php if ($isAdmin): ?> <small class="brand-tag">Admin</small><?php endif; ?></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
        <?php if ($isAdmin): ?>
            <?php if (!empty($_SESSION['admin_logged_in'])): $pending = pending_request_count($pdo); ?>
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?= nav_link($root . 'admin/dashboard.php', 'Dashboard', 'dashboard', $activePage, 'bi-speedometer2') ?>
                <li class="nav-item">
                    <a class="nav-link<?= $activePage === 'requests' ? ' active' : '' ?>" href="<?= e($root) ?>admin/requests.php">
                        <i class="bi bi-inbox me-1"></i>Requests<?php if ($pending): ?> <span class="badge rounded-pill nav-badge"><?= $pending ?></span><?php endif; ?>
                    </a>
                </li>
                <?= nav_link($root . 'admin/issues.php',    'Issued',    'issues',    $activePage, 'bi-box-arrow-up-right') ?>
                <?= nav_link($root . 'admin/equipment.php', 'Equipment', 'equipment', $activePage, 'bi-cpu') ?>
                <?= nav_link($root . 'admin/labs.php',      'Labs',      'labs',      $activePage, 'bi-building') ?>
                <?= nav_link($root . 'admin/students.php',  'Students',  'students',  $activePage, 'bi-people') ?>
                <?= nav_link($root . 'admin/reports.php',   'Reports',   'reports',   $activePage, 'bi-bar-chart') ?>
            </ul>
            <div class="d-flex gap-2">
                <a href="<?= e($root) ?>index.php" class="btn btn-sm btn-outline-brand" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1"></i>Site</a>
                <a href="<?= e($root) ?>admin/logout.php" class="btn btn-sm btn-brand"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
            </div>
            <?php endif; ?>
        <?php else: ?>
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?= nav_link($root . 'index.php',     'Home',      'home',      $activePage) ?>
                <?= nav_link($root . 'equipment.php', 'Equipment', 'equipment', $activePage) ?>
                <?= nav_link($root . 'labs.php',      'Labs',      'labs',      $activePage) ?>
                <?php if ($student): ?>
                    <?= nav_link($root . 'student/dashboard.php', 'My Dashboard', 'my', $activePage, 'bi-person-workspace') ?>
                <?php endif; ?>
            </ul>
            <form class="d-flex me-lg-2 mb-2 mb-lg-0" action="<?= e($root) ?>equipment.php" method="get" role="search">
                <input class="form-control form-control-sm" type="search" name="q" placeholder="Search equipment" aria-label="Search equipment" value="<?= e($_GET['q'] ?? '') ?>" maxlength="80">
            </form>
            <?php if ($student): ?>
                <span class="small text-muted me-2 d-none d-xl-inline"><i class="bi bi-person-circle me-1"></i><?= e($student['name']) ?></span>
                <a href="<?= e($root) ?>logout.php" class="btn btn-sm btn-outline-brand"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
            <?php else: ?>
                <a href="<?= e($root) ?>login.php" class="btn btn-sm btn-outline-brand me-2">Student Login</a>
                <a href="<?= e($root) ?>register.php" class="btn btn-sm btn-brand">Register</a>
            <?php endif; ?>
        <?php endif; ?>
        </div>
    </div>
</nav>
