<?php
/**
 * Reusable helper functions + project rules used across LabLend.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ---------------- Lending rules (one place to change them) ---------------- */
const FINE_PER_DAY     = 10;   // ₹ per item per late day
const MAX_ACTIVE_ITEMS = 3;    // items a student may hold at once (open issues)
const MAX_DAYS         = 14;   // longest borrowing period
const MAX_QTY          = 5;    // most units in one request

/** Escape any value before printing it in HTML (prevents XSS). */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function show_flash(): void
{
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        $icon = $f['type'] === 'success' ? 'check-circle' : 'exclamation-triangle';
        echo '<div class="alert alert-' . e($f['type']) . ' alert-dismissible fade show" role="alert">'
           . '<i class="bi bi-' . $icon . ' me-2"></i>' . e($f['message'])
           . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    }
}

/* ---------------- CSRF protection for every POST form ---------------- */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): bool
{
    return isset($_POST['csrf_token']) && hash_equals(csrf_token(), (string) $_POST['csrf_token']);
}

/* ---------------- Small helpers ---------------- */
function get_int(string $key): int
{
    $v = filter_input(INPUT_GET, $key, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $v ? (int) $v : 0;
}

function fmt_date(?string $d): string
{
    return $d ? date('d M Y', strtotime($d)) : '—';
}

function money($v): string
{
    return '₹' . number_format((float) $v, 0);
}

/** Badge class for a request / issue status. */
function status_class(string $status): string
{
    return [
        'PENDING'   => 'st-pending',
        'APPROVED'  => 'st-ok',
        'ISSUED'    => 'st-info',
        'RETURNED'  => 'st-ok',
        'OVERDUE'   => 'st-bad',
        'REJECTED'  => 'st-bad',
        'CANCELLED' => 'st-muted',
    ][$status] ?? 'st-muted';
}

/** Availability label for an equipment row. */
function stock_badge(int $available, int $total): string
{
    if ($available <= 0) {
        return '<span class="st st-bad">Not available</span>';
    }
    $cls = $available <= max(1, intdiv($total, 4)) ? 'st-pending' : 'st-ok';
    return '<span class="st ' . $cls . '">' . $available . ' of ' . $total . ' available</span>';
}

/** Icon per equipment category (used when there is no photo). */
function category_icon(?string $cat): string
{
    return [
        'Microcontroller' => 'bi-cpu',
        'Sensors'         => 'bi-thermometer-half',
        'Networking'      => 'bi-router',
        'Tools'           => 'bi-tools',
        'Measurement'     => 'bi-speedometer',
        'Robotics'        => 'bi-robot',
        'AI Hardware'     => 'bi-gpu-card',
        'Camera'          => 'bi-camera',
        'Accessories'     => 'bi-box',
    ][$cat] ?? 'bi-box-seam';
}

function equipment_image(array $eq, string $root): string
{
    if (!empty($eq['image'])) {
        return '<img src="' . e($root . 'assets/images/uploads/' . $eq['image']) . '" alt="' . e($eq['name']) . '" class="eq-img">';
    }
    return '<div class="eq-placeholder"><i class="bi ' . category_icon($eq['category'] ?? null) . '"></i></div>';
}

/* ---------------- Dropdown data ---------------- */
function get_departments(PDO $pdo): array
{
    return $pdo->query('SELECT dept_id, dept_name, dept_code FROM departments ORDER BY dept_name')->fetchAll();
}

function get_labs(PDO $pdo): array
{
    return $pdo->query('SELECT l.lab_id, l.lab_name, d.dept_code FROM labs l INNER JOIN departments d ON l.dept_id = d.dept_id ORDER BY d.dept_code, l.lab_name')->fetchAll();
}

function get_categories(PDO $pdo): array
{
    return $pdo->query('SELECT DISTINCT category FROM equipment ORDER BY category')->fetchAll(PDO::FETCH_COLUMN);
}

function pending_request_count(PDO $pdo): int
{
    return (int) $pdo->query("SELECT COUNT(*) FROM borrow_requests WHERE status = 'PENDING'")->fetchColumn();
}

/** Normalise an Indian mobile number to 10 digits, or null. */
function normalize_phone(string $phone): ?string
{
    $d = preg_replace('/\D/', '', $phone);
    if (strlen($d) === 12 && str_starts_with($d, '91')) {
        $d = substr($d, 2);
    } elseif (strlen($d) === 11 && $d[0] === '0') {
        $d = substr($d, 1);
    }
    return preg_match('/^[6-9]\d{9}$/', $d) ? $d : null;
}

/** Optional image upload (admin equipment form). Returns file name or null. */
function handle_image_upload(string $field): ?string
{
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Image upload failed. Please try again.');
    }
    if ($f['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('Image must be smaller than 2 MB.');
    }
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $info = @getimagesize($f['tmp_name']);
    if ($info === false || !isset($allowed[$info['mime']])) {
        throw new RuntimeException('Only JPG, PNG or WEBP images are allowed.');
    }
    $name = bin2hex(random_bytes(8)) . '.' . $allowed[$info['mime']];
    if (!move_uploaded_file($f['tmp_name'], __DIR__ . '/../assets/images/uploads/' . $name)) {
        throw new RuntimeException('Could not save the uploaded image.');
    }
    return $name;
}

function delete_image(?string $name): void
{
    if ($name && preg_match('/^[a-f0-9]{16}\.(jpg|png|webp)$/', $name)) {
        $p = __DIR__ . '/../assets/images/uploads/' . $name;
        if (is_file($p)) {
            unlink($p);
        }
    }
}
