# LabLend – College Lab Equipment Lending System

A DBMS mini project: students browse the equipment in every department lab, request items online, and the lab in-charge approves, issues and receives them. Stock, due dates and late fines are tracked automatically.
Built with **PHP 8 (PDO) + MySQL/MariaDB + Bootstrap 5** on XAMPP.

> All students and records in the sample data are fictional.

---

## 1. Setup (XAMPP)

### Quick way – one double-click
- **Windows:** extract `lablend.zip`, open the `lablend` folder and double-click **`SETUP_WINDOWS.bat`**. It copies LabLend into `C:\xampp\htdocs\lablend`, starts Apache + MySQL, creates `lablend_db` and opens the site.
- **Linux:** `cd lablend && sudo bash setup_linux.sh`

Running the setup again resets LabLend to the sample data.

### Manual way

1. Start **Apache** and **MySQL** in the XAMPP Control Panel (Linux: `sudo /opt/lampp/lampp start`).
2. Copy the `lablend` folder into htdocs:
   - Windows: `C:\xampp\htdocs\lablend\`
   - Linux: `/opt/lampp/htdocs/lablend/`
3. Open **http://localhost/phpmyadmin** → **Import** → choose `database/lablend.sql` → **Import / Go**.
   This creates `lablend_db` with 6 tables, 1 view, indexes and sample data. Re-importing resets everything.
4. Open **http://localhost/lablend/**

If your MySQL user/password is not `root` / empty, edit `config/database.php`.
`APP_DEBUG` in the same file shows real database errors while developing – set it to `false` before going online.

### Demo logins

| Role | URL | Login | Password |
|---|---|---|---|
| Lab in-charge (admin) | `/lablend/admin/` | `admin` | `Lab@2026` |
| Student | `/lablend/login.php` | `aarav@student.example` (or PRN `2024AIML001`) | `Student@123` |
| Student with overdue items | `/lablend/login.php` | `rohan@student.example` | `Student@123` |

All 8 demo students use `Student@123`. Passwords are stored only as bcrypt hashes (`password_hash` / `password_verify`).

---

## 2. Roles

| Visitor (no login) | Student | Admin (lab in-charge) |
|---|---|---|
| Browse & search equipment, see live stock, view labs | Register, log in, request items, cancel pending requests, see due dates, overdue warnings and fines | Approve / reject requests, mark items returned (fine calculated), manage equipment, labs, departments, see students and reports |

## 3. Lending rules (in `includes/functions.php`)

- A student can hold at most **3 items** at a time (items with them + items they are waiting for).
- A loan lasts **1–14 days**.
- Late fine: **₹10 per item per day** – `DATEDIFF(return date, due date) × 10 × quantity`.
- A student with an overdue item cannot request or be approved for anything new.

---

## 4. Database design

**Database:** `lablend_db` · 6 tables · all InnoDB with foreign keys

```
DEPARTMENT 1──N LAB 1──N EQUIPMENT
     │                      │
     1                      1
     │                      │
     N                      N
  STUDENT 1──N BORROW_REQUEST 1──0..1 ISSUE
     │                                 │
     └──────────────1:N────────────────┘
```

| Table | Primary key | Foreign keys | Important constraints |
|---|---|---|---|
| `departments` | dept_id | – | dept_name, dept_code UNIQUE |
| `labs` | lab_id | dept_id → departments (RESTRICT) | |
| `equipment` | equipment_id | lab_id → labs (RESTRICT) | **CHECK** 0 ≤ available_qty ≤ total_qty |
| `students` | student_id | dept_id → departments (RESTRICT) | prn, email UNIQUE · CHECK year 1–4 |
| `borrow_requests` | request_id | student_id, equipment_id (CASCADE) | status ENUM · CHECK qty 1–5, days 1–14 |
| `issues` | issue_id | request_id (UNIQUE), student_id, equipment_id (RESTRICT) | CHECK due_date ≥ issued_on · returned_on NULL = still out |

**View `lab_availability`** – one row per lab with item types, total / available / issued units (LEFT JOIN + SUM + GROUP BY).

### Why the delete rules?
- A lab with equipment, or equipment that has ever been issued, **cannot be deleted** (RESTRICT) – loan history must never disappear.
- Deleting a student or equipment with no loans removes their requests too (CASCADE).

### The two transactions (`includes/lending.php`)

```
APPROVE                                         RETURN
START TRANSACTION                               START TRANSACTION
  SELECT request … FOR UPDATE   (lock)            SELECT issue … FOR UPDATE
  SELECT equipment … FOR UPDATE (lock stock)      fine = days late × ₹10 × qty
  check: PENDING, stock, ≤3 items, no overdue     UPDATE issues  returned_on, fine_amount
  UPDATE equipment available_qty − qty            UPDATE equipment available_qty + qty
  INSERT INTO issues (due = today + days)       COMMIT
  UPDATE request status = APPROVED
COMMIT        (any error → ROLLBACK, nothing saved)
```

Row locks mean two admins (or a double-click) can never approve the same request twice or hand out the same last unit.

---

## 5. Where each DBMS concept is used

| Concept | Where |
|---|---|
| DDL: CREATE TABLE, PK, FK, UNIQUE, CHECK, ENUM, INDEX, VIEW | `database/lablend.sql` |
| INSERT / UPDATE / DELETE with prepared statements | every form |
| INNER JOIN (3 tables) | equipment.php, admin/requests.php |
| LEFT JOIN + COUNT/SUM + GROUP BY | admin/students.php, the view |
| HAVING | admin/reports.php (2+ overdue), students.php filter |
| Anti-join (LEFT JOIN … IS NULL) | reports: equipment never borrowed |
| Nested subquery + AVG | reports: borrowed more than average |
| Correlated subquery | admin/requests.php (student's current load), lablend.sql stock update |
| CASE inside SUM | admin/students.php |
| Date functions (CURDATE, DATEDIFF, INTERVAL) | due dates, overdue, fines |
| Transactions + SELECT … FOR UPDATE | includes/lending.php |
| Password hashing, CSRF tokens, XSS escaping | includes/auth.php, functions.php |

`database/demo_queries.sql` has all of these ready to run in phpMyAdmin.

---

## 6. File structure

```
lablend/
├── index.php              Home: search, live stats, most borrowed, labs
├── equipment.php          Catalog with search + filters
├── equipment_details.php  Item page + request form
├── labs.php               Labs with availability
├── register.php / login.php / logout.php   Student account
├── student/dashboard.php  My loans, due dates, requests, fines
├── admin/                 login, dashboard, requests, issues, equipment, labs, students, reports
├── includes/              functions, auth, lending (transactions), header, navbar, footer
├── config/database.php    PDO connection
├── assets/                css, js, uploaded images
└── database/              lablend.sql, demo_queries.sql
```

---

## 7. Viva demo flow (10 minutes)

1. **Visitor:** Home → search “Arduino” → open it: live stock, next due date, times borrowed.
2. **Student:** log in as `aarav@student.example` → request a **Digital Multimeter** for 5 days → dashboard shows it as PENDING.
3. **Admin:** Requests → the checks column shows stock / item limit / overdue → **Approve**. Explain the transaction. Show the stock dropped on the public page and the due date on the student's dashboard.
4. Click Approve on the same request again in another tab → *“already been reviewed”* (row lock).
5. Log in as **Rohan** → red overdue banner, running fine, request form hidden.
6. **Admin → Issued → Overdue** → mark Rohan's camera returned → fine calculated with `DATEDIFF`, stock restored.
7. **Reports** → open “Show SQL” on HAVING, anti-join and nested subquery reports.
8. Try deleting a lab → blocked by the foreign key. In phpMyAdmin run `UPDATE equipment SET available_qty = -1 …` → blocked by the CHECK constraint.

---

Developed by Atharv Taware.
