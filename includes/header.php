<?php
/**
 * Common <head> + navbar. A page may set before including:
 *   $root (path to project root), $pageTitle, $activePage, $isAdmin
 */
$root       = $root ?? '';
$pageTitle  = $pageTitle ?? 'LabLend';
$activePage = $activePage ?? '';
$isAdmin    = $isAdmin ?? false;
$student    = $isAdmin ? null : current_student($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | LabLend</title>
    <meta name="description" content="LabLend – borrow college lab equipment online: browse, request, track due dates.">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="<?= e($root) ?>assets/css/style.css" rel="stylesheet">
</head>
<body class="<?= $isAdmin ? 'admin-body' : '' ?>">
<?php include __DIR__ . '/navbar.php'; ?>
<main>
