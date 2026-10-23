-- =====================================================================
--  LabLend – DBMS concepts cheat-sheet for the viva
--  phpMyAdmin → lablend_db → SQL tab. Each query is used in the app (file noted).
-- =====================================================================
USE lablend_db;

-- ---------- SELECT + WHERE + LIKE  (equipment.php search) ----------
SELECT name, category, available_qty FROM equipment WHERE name LIKE '%Arduino%';

-- ---------- INNER JOIN across 3 tables  (equipment.php) ----------
SELECT e.name, l.lab_name, d.dept_code, e.available_qty, e.total_qty
FROM equipment e
INNER JOIN labs l        ON e.lab_id = l.lab_id
INNER JOIN departments d ON l.dept_id = d.dept_id
ORDER BY d.dept_code, e.name;

-- ---------- COUNT + GROUP BY + ORDER BY + LIMIT  (index.php, admin/reports.php) ----------
SELECT e.name, COUNT(i.issue_id) AS times_borrowed
FROM equipment e
INNER JOIN issues i ON i.equipment_id = e.equipment_id
GROUP BY e.equipment_id, e.name
ORDER BY times_borrowed DESC
LIMIT 5;

-- ---------- Date functions: overdue items + fine so far  (admin/issues.php) ----------
SELECT s.name, e.name AS item, i.due_date,
       DATEDIFF(CURDATE(), i.due_date)               AS days_late,
       DATEDIFF(CURDATE(), i.due_date) * 10 * i.quantity AS fine_so_far
FROM issues i
INNER JOIN students s  ON i.student_id = s.student_id
INNER JOIN equipment e ON i.equipment_id = e.equipment_id
WHERE i.returned_on IS NULL AND i.due_date < CURDATE();

-- ---------- HAVING: students with 2+ overdue items  (admin/reports.php) ----------
SELECT s.name, COUNT(*) AS overdue_items
FROM issues i INNER JOIN students s ON i.student_id = s.student_id
WHERE i.returned_on IS NULL AND i.due_date < CURDATE()
GROUP BY s.student_id, s.name
HAVING COUNT(*) >= 2;

-- ---------- LEFT JOIN … IS NULL: equipment never borrowed  (admin/reports.php) ----------
SELECT e.name FROM equipment e
LEFT JOIN issues i ON i.equipment_id = e.equipment_id
WHERE i.issue_id IS NULL;

-- ---------- Nested subquery: borrowed more than the average item  (admin/reports.php) ----------
SELECT e.name, COUNT(i.issue_id) AS times_borrowed
FROM equipment e INNER JOIN issues i ON i.equipment_id = e.equipment_id
GROUP BY e.equipment_id, e.name
HAVING COUNT(i.issue_id) > (SELECT AVG(cnt) FROM (SELECT COUNT(*) AS cnt FROM issues GROUP BY equipment_id) AS t);

-- ---------- CASE inside SUM: per-student summary  (admin/students.php) ----------
SELECT s.name,
       COALESCE(SUM(CASE WHEN i.returned_on IS NULL THEN i.quantity END), 0) AS holding,
       COALESCE(SUM(i.fine_amount), 0) AS fines
FROM students s LEFT JOIN issues i ON i.student_id = s.student_id
GROUP BY s.student_id, s.name;

-- ---------- VIEW  (index.php, labs.php) ----------
SELECT * FROM lab_availability;

-- ---------- CHECK constraint in action (this FAILS on purpose) ----------
-- UPDATE equipment SET available_qty = -1 WHERE equipment_id = 1;
--   → ERROR: CONSTRAINT `chk_equipment_qty` failed

-- ---------- Foreign key ON DELETE RESTRICT (this FAILS on purpose) ----------
-- DELETE FROM labs WHERE lab_id = 1;      → lab still has equipment

-- ---------- TRANSACTION used when a request is APPROVED  (includes/lending.php) ----------
-- START TRANSACTION;
--   SELECT * FROM borrow_requests WHERE request_id = 13 FOR UPDATE;   -- lock the request
--   SELECT available_qty FROM equipment WHERE equipment_id = 1 FOR UPDATE; -- lock the stock
--   UPDATE equipment SET available_qty = available_qty - 1 WHERE equipment_id = 1;
--   INSERT INTO issues (request_id, student_id, equipment_id, quantity, issued_on, due_date)
--        VALUES (13, 7, 1, 1, CURDATE(), CURDATE() + INTERVAL 7 DAY);
--   UPDATE borrow_requests SET status = 'APPROVED', reviewed_at = NOW() WHERE request_id = 13;
-- COMMIT;          -- or ROLLBACK; if any step fails

-- ---------- TRANSACTION used when an item is RETURNED  (includes/lending.php) ----------
-- START TRANSACTION;
--   UPDATE issues SET returned_on = CURDATE(),
--          fine_amount = GREATEST(0, DATEDIFF(CURDATE(), due_date)) * 10 * quantity
--   WHERE issue_id = 7;
--   UPDATE equipment SET available_qty = available_qty + 1 WHERE equipment_id = 17;
-- COMMIT;
