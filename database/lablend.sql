-- =====================================================================
--  LabLend – College Lab Equipment Lending System
--  DBMS Mini Project : Database script (MySQL 8 / MariaDB 10.4+)
--  6 tables: departments, labs, equipment, students, borrow_requests, issues
--  1 view  : lab_availability
--
--  Import in phpMyAdmin (Import tab) or run:
--      /opt/lampp/bin/mysql -u root < database/lablend.sql
--  Re-importing resets everything to the sample data below.
--
--  All students and data below are FICTIONAL demo data.
--  Sample dates are relative to the day you import (CURDATE()), so the
--  "overdue" examples always look current during a demo.
-- =====================================================================
SET NAMES utf8mb4;
DROP DATABASE IF EXISTS lablend_db;
CREATE DATABASE lablend_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lablend_db;

-- ---------------------------------------------------------------------
-- DDL : CREATE TABLE
-- ---------------------------------------------------------------------

-- TABLE 1 : departments
CREATE TABLE departments (
    dept_id   INT PRIMARY KEY AUTO_INCREMENT,
    dept_name VARCHAR(100) NOT NULL UNIQUE,
    dept_code VARCHAR(10)  NOT NULL UNIQUE
) ENGINE=InnoDB;

-- TABLE 2 : labs   (Department 1 : N Lab)
CREATE TABLE labs (
    lab_id    INT PRIMARY KEY AUTO_INCREMENT,
    dept_id   INT NOT NULL,
    lab_name  VARCHAR(120) NOT NULL,
    room_no   VARCHAR(20),
    incharge  VARCHAR(100),
    CONSTRAINT fk_lab_dept FOREIGN KEY (dept_id)
        REFERENCES departments(dept_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- TABLE 3 : equipment   (Lab 1 : N Equipment)
-- available_qty can never go below 0 or above total_qty (CHECK constraint)
CREATE TABLE equipment (
    equipment_id  INT PRIMARY KEY AUTO_INCREMENT,
    lab_id        INT NOT NULL,
    name          VARCHAR(150) NOT NULL,
    category      VARCHAR(60)  NOT NULL,
    description   TEXT,
    total_qty     INT NOT NULL DEFAULT 1,
    available_qty INT NOT NULL DEFAULT 1,
    image         VARCHAR(255),
    CONSTRAINT chk_equipment_qty CHECK (available_qty >= 0 AND available_qty <= total_qty AND total_qty >= 0),
    CONSTRAINT fk_equipment_lab FOREIGN KEY (lab_id)
        REFERENCES labs(lab_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- TABLE 4 : students   (Department 1 : N Student)
-- password stored only as a bcrypt hash (PHP password_hash)
CREATE TABLE students (
    student_id    INT PRIMARY KEY AUTO_INCREMENT,
    prn           VARCHAR(20)  NOT NULL UNIQUE,
    name          VARCHAR(120) NOT NULL,
    email         VARCHAR(120) NOT NULL UNIQUE,
    phone         VARCHAR(15)  NOT NULL,
    dept_id       INT NOT NULL,
    year_of_study TINYINT NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_student_year CHECK (year_of_study BETWEEN 1 AND 4),
    CONSTRAINT fk_student_dept FOREIGN KEY (dept_id)
        REFERENCES departments(dept_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- TABLE 5 : borrow_requests   (Student 1 : N Request, Equipment 1 : N Request)
CREATE TABLE borrow_requests (
    request_id    INT PRIMARY KEY AUTO_INCREMENT,
    student_id    INT NOT NULL,
    equipment_id  INT NOT NULL,
    quantity      INT NOT NULL DEFAULT 1,
    purpose       VARCHAR(255) NOT NULL,
    days_needed   INT NOT NULL DEFAULT 7,
    status        ENUM('PENDING', 'APPROVED', 'REJECTED', 'CANCELLED') NOT NULL DEFAULT 'PENDING',
    admin_note    VARCHAR(255),
    requested_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at   TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT chk_request_qty  CHECK (quantity BETWEEN 1 AND 5),
    CONSTRAINT chk_request_days CHECK (days_needed BETWEEN 1 AND 14),
    CONSTRAINT fk_request_student FOREIGN KEY (student_id)
        REFERENCES students(student_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_request_equipment FOREIGN KEY (equipment_id)
        REFERENCES equipment(equipment_id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- TABLE 6 : issues   (Request 1 : 0..1 Issue)
-- Created when a request is APPROVED. returned_on stays NULL until return.
CREATE TABLE issues (
    issue_id       INT PRIMARY KEY AUTO_INCREMENT,
    request_id     INT NOT NULL UNIQUE,
    student_id     INT NOT NULL,
    equipment_id   INT NOT NULL,
    quantity       INT NOT NULL,
    issued_on      DATE NOT NULL,
    due_date       DATE NOT NULL,
    returned_on    DATE NULL,
    fine_amount    DECIMAL(8,2) NOT NULL DEFAULT 0,
    condition_note VARCHAR(255),
    CONSTRAINT chk_issue_dates CHECK (due_date >= issued_on),
    CONSTRAINT fk_issue_request FOREIGN KEY (request_id)
        REFERENCES borrow_requests(request_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_issue_student FOREIGN KEY (student_id)
        REFERENCES students(student_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_issue_equipment FOREIGN KEY (equipment_id)
        REFERENCES equipment(equipment_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- DDL : INDEXES on frequently searched / filtered columns
-- ---------------------------------------------------------------------
CREATE INDEX idx_equipment_name   ON equipment(name);
CREATE INDEX idx_equipment_cat    ON equipment(category);
CREATE INDEX idx_request_status   ON borrow_requests(status);
CREATE INDEX idx_issue_open       ON issues(returned_on, due_date);

-- ---------------------------------------------------------------------
-- DML : sample data
-- ---------------------------------------------------------------------
INSERT INTO departments (dept_name, dept_code) VALUES
('Computer Science & Engineering', 'CSE'),     -- 1
('Electronics & Telecommunication', 'ENTC'),   -- 2
('Mechanical Engineering', 'MECH'),            -- 3
('Artificial Intelligence & ML', 'AIML');      -- 4

INSERT INTO labs (dept_id, lab_name, room_no, incharge) VALUES
(1, 'IoT & Embedded Systems Lab', 'CSE-204', 'Prof. A. Kulkarni'),   -- 1
(1, 'Networking Lab',             'CSE-108', 'Prof. S. Patil'),      -- 2
(2, 'Electronics Workshop',       'ENTC-012','Prof. R. Joshi'),      -- 3
(3, 'Robotics & Automation Lab',  'MECH-301','Prof. V. Deshmukh'),   -- 4
(4, 'AI Research Lab',            'AIML-110','Prof. N. Shinde'),     -- 5
(4, 'Media & Vision Lab',         'AIML-112','Prof. K. More');       -- 6

-- available_qty is recalculated from open issues at the end of this script
INSERT INTO equipment (lab_id, name, category, description, total_qty, available_qty) VALUES
(1, 'Arduino Uno Kit',            'Microcontroller', 'Arduino Uno R3 with USB cable, breadboard, jumper wires and starter components.', 12, 12),  -- 1
(1, 'Raspberry Pi 4 (4 GB)',      'Microcontroller', 'Raspberry Pi 4 Model B with power supply, 32 GB SD card and case.', 6, 6),                -- 2
(1, 'ESP32 Dev Board',            'Microcontroller', 'Wi-Fi + Bluetooth development board for IoT projects.', 10, 10),                          -- 3
(1, 'Sensor Kit (37 sensors)',    'Sensors',         'Temperature, ultrasonic, IR, PIR, gas, soil moisture and more.', 5, 5),                    -- 4
(2, 'Cisco Router 1941',          'Networking',      'Enterprise router for routing and VLAN practicals.', 3, 3),                                -- 5
(2, 'Crimping Tool Set',          'Tools',           'RJ45 crimper, cable tester and connectors.', 6, 6),                                        -- 6
(3, 'Digital Multimeter',         'Measurement',     'Auto-ranging digital multimeter with probes.', 15, 15),                                    -- 7
(3, 'Soldering Station',          'Tools',           'Temperature-controlled soldering station with stand.', 8, 8),                              -- 8
(3, 'Digital Storage Oscilloscope','Measurement',    '2-channel 100 MHz DSO with probes.', 2, 2),                                               -- 9
(3, 'Function Generator',         'Measurement',     'Sine/square/triangle signal generator up to 5 MHz.', 3, 3),                                -- 10
(4, 'Servo Motor Pack (6 pcs)',   'Robotics',        'SG90 and MG996R servo motors.', 6, 6),                                                     -- 11
(4, 'Robotic Arm Kit',            'Robotics',        '4-DOF acrylic robotic arm kit with servo controller.', 2, 2),                              -- 12
(4, 'Line Follower Robot Kit',    'Robotics',        'Chassis, motors, IR array and motor driver.', 4, 4),                                       -- 13
(5, 'NVIDIA Jetson Nano',         'AI Hardware',     'Edge AI developer kit for running neural networks.', 3, 3),                                -- 14
(5, 'Intel RealSense D435',       'AI Hardware',     'Depth camera for 3D vision projects.', 2, 2),                                              -- 15
(5, 'Google Coral USB Accelerator','AI Hardware',    'Edge TPU accelerator for TensorFlow Lite.', 2, 2),                                         -- 16
(6, 'DSLR Camera (Canon 1500D)',  'Camera',          '24 MP DSLR with 18–55 mm lens, battery and charger.', 2, 2),                               -- 17
(6, 'USB Webcam 1080p',           'Camera',          'Full-HD webcam for computer-vision practicals.', 8, 8),                                    -- 18
(6, 'Tripod Stand',               'Accessories',     'Adjustable aluminium tripod.', 4, 4),                                                      -- 19
(6, 'Drone (DJI Tello)',          'Robotics',        'Programmable mini drone (Python SDK).', 1, 1);                                            -- 20

-- Demo students – password for all: Student@123
INSERT INTO students (prn, name, email, phone, dept_id, year_of_study, password_hash) VALUES
('2024AIML001', 'Aarav Kulkarni', 'aarav@student.example',  '9000000101', 4, 2, '$2y$10$LHt8Tq0fOdfUXxYu/KjxfudGTHpQvJepNE.xSm1p2YjSF/wKDX9ba'),  -- 1
('2024AIML002', 'Diya Patil',     'diya@student.example',   '9000000102', 4, 2, '$2y$10$LHt8Tq0fOdfUXxYu/KjxfudGTHpQvJepNE.xSm1p2YjSF/wKDX9ba'),  -- 2
('2023CSE010',  'Rohan Desai',    'rohan@student.example',  '9000000103', 1, 3, '$2y$10$LHt8Tq0fOdfUXxYu/KjxfudGTHpQvJepNE.xSm1p2YjSF/wKDX9ba'),  -- 3
('2023CSE022',  'Sneha Jadhav',   'sneha@student.example',  '9000000104', 1, 3, '$2y$10$LHt8Tq0fOdfUXxYu/KjxfudGTHpQvJepNE.xSm1p2YjSF/wKDX9ba'),  -- 4
('2025ENTC005', 'Omkar Pawar',    'omkar@student.example',  '9000000105', 2, 1, '$2y$10$LHt8Tq0fOdfUXxYu/KjxfudGTHpQvJepNE.xSm1p2YjSF/wKDX9ba'),  -- 5
('2022MECH014', 'Isha Naik',      'isha@student.example',   '9000000106', 3, 4, '$2y$10$LHt8Tq0fOdfUXxYu/KjxfudGTHpQvJepNE.xSm1p2YjSF/wKDX9ba'),  -- 6
('2024CSE031',  'Kunal Shah',     'kunal@student.example',  '9000000107', 1, 2, '$2y$10$LHt8Tq0fOdfUXxYu/KjxfudGTHpQvJepNE.xSm1p2YjSF/wKDX9ba'),  -- 7
('2023AIML019', 'Tanvi Gokhale',  'tanvi@student.example',  '9000000108', 4, 3, '$2y$10$LHt8Tq0fOdfUXxYu/KjxfudGTHpQvJepNE.xSm1p2YjSF/wKDX9ba');  -- 8

-- Requests (dates relative to today)
INSERT INTO borrow_requests (request_id, student_id, equipment_id, quantity, purpose, days_needed, status, admin_note, requested_at, reviewed_at) VALUES
-- returned on time
(1,  1, 1,  1, 'Mini project: smart irrigation prototype', 7,  'APPROVED', NULL, NOW() - INTERVAL 40 DAY, NOW() - INTERVAL 39 DAY),
-- returned late (fine)
(2,  3, 9,  1, 'Signal analysis practical',                 3,  'APPROVED', NULL, NOW() - INTERVAL 30 DAY, NOW() - INTERVAL 30 DAY),
(3,  6, 12, 1, 'Final-year robotics project demo',          7,  'APPROVED', NULL, NOW() - INTERVAL 28 DAY, NOW() - INTERVAL 27 DAY),
-- currently issued, on time
(4,  2, 14, 1, 'Object detection on the edge (SIH prep)',   14, 'APPROVED', NULL, NOW() - INTERVAL 5 DAY,  NOW() - INTERVAL 5 DAY),
(5,  1, 2,  1, 'Home automation hub',                       10, 'APPROVED', NULL, NOW() - INTERVAL 3 DAY,  NOW() - INTERVAL 3 DAY),
(6,  4, 3,  2, 'IoT weather station',                       7,  'APPROVED', NULL, NOW() - INTERVAL 2 DAY,  NOW() - INTERVAL 2 DAY),
-- currently issued, OVERDUE
(7,  3, 17, 1, 'Dataset collection for face recognition',   5,  'APPROVED', NULL, NOW() - INTERVAL 12 DAY, NOW() - INTERVAL 12 DAY),
(8,  3, 15, 1, 'Depth estimation experiments',              7,  'APPROVED', NULL, NOW() - INTERVAL 15 DAY, NOW() - INTERVAL 15 DAY),
(9,  3, 4,  1, 'Sensor comparison study',                   7,  'APPROVED', NULL, NOW() - INTERVAL 11 DAY, NOW() - INTERVAL 11 DAY),
(10, 5, 7,  2, 'Electronics workshop assignments',          5,  'APPROVED', NULL, NOW() - INTERVAL 9 DAY,  NOW() - INTERVAL 9 DAY),
-- rejected / cancelled
(11, 7, 20, 1, 'Want to try flying',                        7,  'REJECTED', 'Drone is reserved for final-year projects only.', NOW() - INTERVAL 6 DAY, NOW() - INTERVAL 6 DAY),
(12, 8, 18, 1, 'Webcam for online class',                   3,  'CANCELLED', NULL, NOW() - INTERVAL 4 DAY, NULL),
-- waiting for the lab in-charge
(13, 7, 1,  1, 'Learning Arduino basics',                   7,  'PENDING',  NULL, NOW() - INTERVAL 1 DAY, NULL),
(14, 8, 14, 1, 'Pose estimation project',                   10, 'PENDING',  NULL, NOW() - INTERVAL 20 HOUR, NULL),
(15, 2, 17, 1, 'Club event photography (ASTRA)',             2,  'PENDING',  NULL, NOW() - INTERVAL 3 HOUR, NULL),
(16, 6, 11, 2, 'Gripper mechanism for robotic arm',         7,  'PENDING',  NULL, NOW() - INTERVAL 2 HOUR, NULL),
-- older history (returned) – gives the reports something to rank
(17, 4, 1,  1, 'Line follower workshop',                    5,  'APPROVED', NULL, NOW() - INTERVAL 60 DAY, NOW() - INTERVAL 60 DAY),
(18, 7, 1,  1, 'Arduino basics lab',                        7,  'APPROVED', NULL, NOW() - INTERVAL 55 DAY, NOW() - INTERVAL 55 DAY),
(19, 8, 1,  2, 'Hackathon prototype',                       3,  'APPROVED', NULL, NOW() - INTERVAL 50 DAY, NOW() - INTERVAL 50 DAY),
(20, 5, 7,  1, 'Circuit debugging',                         3,  'APPROVED', NULL, NOW() - INTERVAL 45 DAY, NOW() - INTERVAL 45 DAY),
(21, 2, 14, 1, 'Model benchmarking',                        7,  'APPROVED', NULL, NOW() - INTERVAL 35 DAY, NOW() - INTERVAL 35 DAY),
(22, 6, 11, 2, 'Servo calibration',                         5,  'APPROVED', NULL, NOW() - INTERVAL 33 DAY, NOW() - INTERVAL 33 DAY);

INSERT INTO issues (request_id, student_id, equipment_id, quantity, issued_on, due_date, returned_on, fine_amount, condition_note) VALUES
(1,  1, 1,  1, CURDATE() - INTERVAL 39 DAY, CURDATE() - INTERVAL 32 DAY, CURDATE() - INTERVAL 33 DAY, 0,  'Good'),
(2,  3, 9,  1, CURDATE() - INTERVAL 30 DAY, CURDATE() - INTERVAL 27 DAY, CURDATE() - INTERVAL 23 DAY, 40, 'Good'),
(3,  6, 12, 1, CURDATE() - INTERVAL 27 DAY, CURDATE() - INTERVAL 20 DAY, CURDATE() - INTERVAL 18 DAY, 20, 'One servo loose'),
(4,  2, 14, 1, CURDATE() - INTERVAL 5 DAY,  CURDATE() + INTERVAL 9 DAY,  NULL, 0, NULL),
(5,  1, 2,  1, CURDATE() - INTERVAL 3 DAY,  CURDATE() + INTERVAL 7 DAY,  NULL, 0, NULL),
(6,  4, 3,  2, CURDATE() - INTERVAL 2 DAY,  CURDATE() + INTERVAL 5 DAY,  NULL, 0, NULL),
(7,  3, 17, 1, CURDATE() - INTERVAL 12 DAY, CURDATE() - INTERVAL 7 DAY,  NULL, 0, NULL),
(8,  3, 15, 1, CURDATE() - INTERVAL 15 DAY, CURDATE() - INTERVAL 8 DAY,  NULL, 0, NULL),
(9,  3, 4,  1, CURDATE() - INTERVAL 11 DAY, CURDATE() - INTERVAL 4 DAY,  NULL, 0, NULL),
(10, 5, 7,  2, CURDATE() - INTERVAL 9 DAY,  CURDATE() - INTERVAL 4 DAY,  NULL, 0, NULL),
(17, 4, 1,  1, CURDATE() - INTERVAL 60 DAY, CURDATE() - INTERVAL 55 DAY, CURDATE() - INTERVAL 56 DAY, 0,  'Good'),
(18, 7, 1,  1, CURDATE() - INTERVAL 55 DAY, CURDATE() - INTERVAL 48 DAY, CURDATE() - INTERVAL 46 DAY, 20, 'Good'),
(19, 8, 1,  2, CURDATE() - INTERVAL 50 DAY, CURDATE() - INTERVAL 47 DAY, CURDATE() - INTERVAL 47 DAY, 0,  'Good'),
(20, 5, 7,  1, CURDATE() - INTERVAL 45 DAY, CURDATE() - INTERVAL 42 DAY, CURDATE() - INTERVAL 42 DAY, 0,  'Good'),
(21, 2, 14, 1, CURDATE() - INTERVAL 35 DAY, CURDATE() - INTERVAL 28 DAY, CURDATE() - INTERVAL 29 DAY, 0,  'Good'),
(22, 6, 11, 2, CURDATE() - INTERVAL 33 DAY, CURDATE() - INTERVAL 28 DAY, CURDATE() - INTERVAL 26 DAY, 40, 'One horn missing');

-- DML : UPDATE with a correlated subquery – stock = total − items still out
UPDATE equipment e
SET e.available_qty = e.total_qty - (SELECT COALESCE(SUM(i.quantity), 0)
                                     FROM issues i
                                     WHERE i.equipment_id = e.equipment_id AND i.returned_on IS NULL);

-- ---------------------------------------------------------------------
-- VIEW : lab_availability – one row per lab (LEFT JOIN + SUM + GROUP BY)
-- ---------------------------------------------------------------------
CREATE VIEW lab_availability AS
SELECT  l.lab_id,
        l.lab_name,
        d.dept_code,
        COUNT(e.equipment_id)                        AS item_types,
        COALESCE(SUM(e.total_qty), 0)                AS total_units,
        COALESCE(SUM(e.available_qty), 0)           AS available_units,
        COALESCE(SUM(e.total_qty - e.available_qty), 0) AS issued_units
FROM labs l
INNER JOIN departments d ON l.dept_id = d.dept_id
LEFT JOIN equipment e    ON e.lab_id = l.lab_id
GROUP BY l.lab_id, l.lab_name, d.dept_code;
