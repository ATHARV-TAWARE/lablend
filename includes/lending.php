<?php
/**
 * LabLend business logic – the ONLY place that changes stock.
 *
 *  REQUEST  student → INSERT borrow_requests (PENDING)
 *  APPROVE  one transaction:
 *              lock request + equipment (SELECT … FOR UPDATE)
 *              check: PENDING, enough stock, student under the item limit, no overdue items
 *              UPDATE equipment  available_qty = available_qty − qty
 *              INSERT issues     (issued today, due today + days_needed)
 *              UPDATE request    status = APPROVED
 *              COMMIT            (any error → ROLLBACK, nothing saved)
 *  REJECT   UPDATE request status = REJECTED (+ reason)
 *  CANCEL   student cancels their own PENDING request
 *  RETURN   one transaction:
 *              fine = days late × FINE_PER_DAY × qty   (DATEDIFF)
 *              UPDATE issues     returned_on = today, fine_amount
 *              UPDATE equipment  available_qty = available_qty + qty
 *              COMMIT
 */

/** A rule was broken (shown to the user as-is). */
class LendingError extends RuntimeException {}

/** Units the student currently holds (issued, not yet returned). */
function student_open_units(PDO $pdo, int $studentId): int
{
    $st = $pdo->prepare('SELECT COALESCE(SUM(quantity), 0) FROM issues WHERE student_id = ? AND returned_on IS NULL');
    $st->execute([$studentId]);
    return (int) $st->fetchColumn();
}

/** Units the student has asked for and is still waiting on. */
function student_pending_units(PDO $pdo, int $studentId): int
{
    $st = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM borrow_requests WHERE student_id = ? AND status = 'PENDING'");
    $st->execute([$studentId]);
    return (int) $st->fetchColumn();
}

/** Number of overdue items the student still holds. */
function student_overdue_count(PDO $pdo, int $studentId): int
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM issues WHERE student_id = ? AND returned_on IS NULL AND due_date < CURDATE()');
    $st->execute([$studentId]);
    return (int) $st->fetchColumn();
}

/* ======================= STUDENT: new request ======================= */
function create_request(PDO $pdo, array $student, int $equipmentId, int $qty, int $days, string $purpose): int
{
    $st = $pdo->prepare('SELECT equipment_id, name, total_qty, available_qty FROM equipment WHERE equipment_id = ?');
    $st->execute([$equipmentId]);
    $eq = $st->fetch();
    if (!$eq) {
        throw new LendingError('This equipment does not exist.');
    }
    if ($qty < 1 || $qty > MAX_QTY) {
        throw new LendingError('Quantity must be between 1 and ' . MAX_QTY . '.');
    }
    if ($qty > (int) $eq['total_qty']) {
        throw new LendingError('The lab only has ' . (int) $eq['total_qty'] . ' unit(s) of this item.');
    }
    if ($days < 1 || $days > MAX_DAYS) {
        throw new LendingError('You can borrow for 1 to ' . MAX_DAYS . ' days.');
    }
    $purpose = trim($purpose);
    if (mb_strlen($purpose) < 5 || mb_strlen($purpose) > 255) {
        throw new LendingError('Please describe the purpose (5–255 characters).');
    }
    $sid = (int) $student['student_id'];
    if (student_overdue_count($pdo, $sid) > 0) {
        throw new LendingError('You have overdue items. Return them before requesting anything new.');
    }
    if (student_open_units($pdo, $sid) + student_pending_units($pdo, $sid) + $qty > MAX_ACTIVE_ITEMS) {
        throw new LendingError('You can hold at most ' . MAX_ACTIVE_ITEMS . ' items at a time (items you have + items you are waiting for).');
    }
    $st = $pdo->prepare("SELECT COUNT(*) FROM borrow_requests WHERE student_id = ? AND equipment_id = ? AND status = 'PENDING'");
    $st->execute([$sid, $equipmentId]);
    if ($st->fetchColumn() > 0) {
        throw new LendingError('You already have a pending request for this item.');
    }

    $ins = $pdo->prepare('INSERT INTO borrow_requests (student_id, equipment_id, quantity, purpose, days_needed) VALUES (?, ?, ?, ?, ?)');
    $ins->execute([$sid, $equipmentId, $qty, $purpose, $days]);
    return (int) $pdo->lastInsertId();
}

/* ======================= STUDENT: cancel ======================= */
function cancel_request(PDO $pdo, int $studentId, int $requestId): void
{
    $upd = $pdo->prepare("UPDATE borrow_requests SET status = 'CANCELLED', reviewed_at = CURRENT_TIMESTAMP
                          WHERE request_id = ? AND student_id = ? AND status = 'PENDING'");
    $upd->execute([$requestId, $studentId]);
    if ($upd->rowCount() !== 1) {
        throw new LendingError('This request can no longer be cancelled.');
    }
}

/* ======================= ADMIN: approve ======================= */
/** Returns ['issue_id', 'due_date', 'name', 'equipment']. */
function approve_request(PDO $pdo, int $requestId, string $note = ''): array
{
    $pdo->beginTransaction();
    try {
        // 1. Lock the request – a second click waits here, then sees APPROVED
        $st = $pdo->prepare('SELECT r.*, s.name AS student_name FROM borrow_requests r
                             INNER JOIN students s ON r.student_id = s.student_id
                             WHERE r.request_id = ? FOR UPDATE');
        $st->execute([$requestId]);
        $req = $st->fetch();
        if (!$req) {
            throw new LendingError('Request not found.');
        }
        if ($req['status'] !== 'PENDING') {
            throw new LendingError('This request has already been reviewed (' . $req['status'] . ').');
        }

        // 2. Lock the equipment row so two approvals cannot take the same last unit
        $st = $pdo->prepare('SELECT equipment_id, name, available_qty FROM equipment WHERE equipment_id = ? FOR UPDATE');
        $st->execute([$req['equipment_id']]);
        $eq = $st->fetch();
        if ((int) $eq['available_qty'] < (int) $req['quantity']) {
            throw new LendingError('Not enough stock: ' . (int) $eq['available_qty'] . ' available, ' . (int) $req['quantity'] . ' requested.');
        }

        // 3. Student rules
        if (student_overdue_count($pdo, (int) $req['student_id']) > 0) {
            throw new LendingError($req['student_name'] . ' has overdue items. They must return them first.');
        }
        if (student_open_units($pdo, (int) $req['student_id']) + (int) $req['quantity'] > MAX_ACTIVE_ITEMS) {
            throw new LendingError($req['student_name'] . ' would hold more than ' . MAX_ACTIVE_ITEMS . ' items.');
        }

        // 4. Stock goes down
        $pdo->prepare('UPDATE equipment SET available_qty = available_qty - ? WHERE equipment_id = ?')
            ->execute([$req['quantity'], $req['equipment_id']]);

        // 5. Issue record (due date = today + days_needed)
        $pdo->prepare('INSERT INTO issues (request_id, student_id, equipment_id, quantity, issued_on, due_date)
                       VALUES (?, ?, ?, ?, CURDATE(), CURDATE() + INTERVAL ? DAY)')
            ->execute([$requestId, $req['student_id'], $req['equipment_id'], $req['quantity'], $req['days_needed']]);
        $issueId = (int) $pdo->lastInsertId();

        // 6. Request approved
        $pdo->prepare("UPDATE borrow_requests SET status = 'APPROVED', admin_note = ?, reviewed_at = CURRENT_TIMESTAMP WHERE request_id = ?")
            ->execute([$note !== '' ? $note : null, $requestId]);

        $pdo->commit();   // 4 + 5 + 6 saved together, or none of them

        $st = $pdo->prepare('SELECT due_date FROM issues WHERE issue_id = ?');
        $st->execute([$issueId]);
        return ['issue_id' => $issueId, 'due_date' => $st->fetchColumn(), 'name' => $req['student_name'], 'equipment' => $eq['name']];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('LabLend approve #' . $requestId . ' failed: ' . $e->getMessage());
        throw $e;
    }
}

/* ======================= ADMIN: reject ======================= */
function reject_request(PDO $pdo, int $requestId, string $note = ''): void
{
    $upd = $pdo->prepare("UPDATE borrow_requests SET status = 'REJECTED', admin_note = ?, reviewed_at = CURRENT_TIMESTAMP
                          WHERE request_id = ? AND status = 'PENDING'");
    $upd->execute([$note !== '' ? $note : null, $requestId]);
    if ($upd->rowCount() !== 1) {
        throw new LendingError('This request has already been reviewed.');
    }
}

/* ======================= ADMIN: return ======================= */
/** Returns ['fine', 'days_late', 'equipment', 'name']. */
function return_issue(PDO $pdo, int $issueId, string $condition = ''): array
{
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT i.*, DATEDIFF(CURDATE(), i.due_date) AS days_late, e.name AS equipment, s.name AS student_name
                             FROM issues i
                             INNER JOIN equipment e ON i.equipment_id = e.equipment_id
                             INNER JOIN students s  ON i.student_id = s.student_id
                             WHERE i.issue_id = ? FOR UPDATE');
        $st->execute([$issueId]);
        $is = $st->fetch();
        if (!$is) {
            throw new LendingError('Issue record not found.');
        }
        if ($is['returned_on'] !== null) {
            throw new LendingError('This item has already been returned.');
        }

        $daysLate = max(0, (int) $is['days_late']);
        $fine = $daysLate * FINE_PER_DAY * (int) $is['quantity'];

        $pdo->prepare('UPDATE issues SET returned_on = CURDATE(), fine_amount = ?, condition_note = ? WHERE issue_id = ?')
            ->execute([$fine, $condition !== '' ? $condition : 'Good', $issueId]);
        $pdo->prepare('UPDATE equipment SET available_qty = available_qty + ? WHERE equipment_id = ?')
            ->execute([$is['quantity'], $is['equipment_id']]);

        $pdo->commit();
        return ['fine' => $fine, 'days_late' => $daysLate, 'equipment' => $is['equipment'], 'name' => $is['student_name']];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('LabLend return #' . $issueId . ' failed: ' . $e->getMessage());
        throw $e;
    }
}

/** Message for a failed action: rule errors as-is, database errors only in debug mode. */
function lending_error_message(string $action, Throwable $e): string
{
    if ($e instanceof LendingError) {
        return $e->getMessage();
    }
    return APP_DEBUG
        ? ucfirst($action) . ' failed – database error: ' . $e->getMessage() . ' Nothing was saved.'
        : 'Could not ' . $action . '. Nothing was saved. (Details are in the PHP error log.)';
}
