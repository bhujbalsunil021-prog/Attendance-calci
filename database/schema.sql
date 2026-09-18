-- ============================================================
-- Student Attendance Management System
-- Database schema + starter data
-- Run once in phpMyAdmin (Import tab) or:  mysql -u root -p < schema.sql
-- ============================================================

DROP DATABASE IF EXISTS attendance_db;
CREATE DATABASE attendance_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE attendance_db;

-- ---------- Users (one login table for all three roles) ----------
CREATE TABLE users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    email      VARCHAR(120) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,            -- password_hash() output
    role       ENUM('admin','teacher','student') NOT NULL,
    is_active  TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------- Courses ----------
CREATE TABLE courses (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(100) NOT NULL,
    code           VARCHAR(20)  NOT NULL UNIQUE,
    duration_years TINYINT NOT NULL DEFAULT 3
) ENGINE=InnoDB;

-- ---------- Divisions (a class group inside a course + year) ----------
CREATE TABLE divisions (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    year      TINYINT NOT NULL,
    name      VARCHAR(20) NOT NULL,
    UNIQUE KEY uq_division (course_id, year, name),
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- Subjects ----------
CREATE TABLE subjects (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    year      TINYINT NOT NULL,
    name      VARCHAR(120) NOT NULL,
    code      VARCHAR(20) NOT NULL UNIQUE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- Teachers ----------
CREATE TABLE teachers (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    user_id  INT NOT NULL UNIQUE,
    emp_code VARCHAR(20) NOT NULL UNIQUE,
    phone    VARCHAR(15),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- Students ----------
CREATE TABLE students (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    user_id        INT NOT NULL UNIQUE,
    roll_no        VARCHAR(20) NOT NULL UNIQUE,
    course_id      INT NOT NULL,
    year           TINYINT NOT NULL,
    division_id    INT NOT NULL,
    phone          VARCHAR(15),
    admission_date DATE,
    FOREIGN KEY (user_id)     REFERENCES users(id)     ON DELETE CASCADE,
    FOREIGN KEY (course_id)   REFERENCES courses(id),
    FOREIGN KEY (division_id) REFERENCES divisions(id)
) ENGINE=InnoDB;

-- ---------- Which teacher teaches which subject to which division ----------
CREATE TABLE teacher_subjects (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id  INT NOT NULL,
    subject_id  INT NOT NULL,
    division_id INT NOT NULL,
    UNIQUE KEY uq_assignment (teacher_id, subject_id, division_id),
    FOREIGN KEY (teacher_id)  REFERENCES teachers(id)  ON DELETE CASCADE,
    FOREIGN KEY (subject_id)  REFERENCES subjects(id)  ON DELETE CASCADE,
    FOREIGN KEY (division_id) REFERENCES divisions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- Attendance ----------
-- One row per student, per subject, per date. The UNIQUE key is what makes
-- re-marking safe: an edit overwrites instead of creating a duplicate.
CREATE TABLE attendance (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    student_id  INT NOT NULL,
    subject_id  INT NOT NULL,
    teacher_id  INT NOT NULL,
    attend_date DATE NOT NULL,
    status      ENUM('P','A') NOT NULL,
    marked_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_attendance (student_id, subject_id, attend_date),
    KEY idx_date (attend_date),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id)
) ENGINE=InnoDB;

-- ============================================================
-- Starter data. Every seeded account uses the password: password123
-- ============================================================

INSERT INTO courses (name, code, duration_years) VALUES
('Bachelor of Computer Applications', 'BCA', 3),
('Bachelor of Commerce', 'BCOM', 3);

INSERT INTO divisions (course_id, year, name) VALUES
(1, 1, 'A'), (1, 1, 'B'), (1, 2, 'A'), (1, 3, 'A'), (2, 1, 'A');

INSERT INTO subjects (course_id, year, name, code) VALUES
(1, 1, 'Programming in C',            'BCA101'),
(1, 1, 'Digital Electronics',         'BCA102'),
(1, 1, 'Business Communication',      'BCA103'),
(1, 2, 'Data Structures',             'BCA201'),
(1, 2, 'Database Management Systems', 'BCA202'),
(1, 3, 'Web Technologies',            'BCA301');

INSERT INTO users (name, email, password, role) VALUES
('System Administrator', 'admin@college.edu', '$2y$10$4HLSahtsJTm5jv0qP1dCH.4Ru1jPZrL6MwHWqYwaFEd/UCzMCFrJm', 'admin'),
('Prof. A. R. Deshmukh', 'deshmukh@college.edu', '$2y$10$4HLSahtsJTm5jv0qP1dCH.4Ru1jPZrL6MwHWqYwaFEd/UCzMCFrJm', 'teacher'),
('Prof. S. M. Kulkarni', 'kulkarni@college.edu', '$2y$10$4HLSahtsJTm5jv0qP1dCH.4Ru1jPZrL6MwHWqYwaFEd/UCzMCFrJm', 'teacher');

INSERT INTO teachers (user_id, emp_code, phone) VALUES
(2, 'EMP001', '9876500001'),
(3, 'EMP002', '9876500002');

INSERT INTO teacher_subjects (teacher_id, subject_id, division_id) VALUES
(1, 1, 1), (1, 1, 2), (1, 4, 3),
(2, 2, 1), (2, 5, 3);

INSERT INTO users (name, email, password, role) VALUES
('Aarti Joshi',   'aarti@student.edu',  '$2y$10$4HLSahtsJTm5jv0qP1dCH.4Ru1jPZrL6MwHWqYwaFEd/UCzMCFrJm', 'student'),
('Rohit Pawar',   'rohit@student.edu',  '$2y$10$4HLSahtsJTm5jv0qP1dCH.4Ru1jPZrL6MwHWqYwaFEd/UCzMCFrJm', 'student'),
('Sneha Kadam',   'sneha@student.edu',  '$2y$10$4HLSahtsJTm5jv0qP1dCH.4Ru1jPZrL6MwHWqYwaFEd/UCzMCFrJm', 'student'),
('Imran Shaikh',  'imran@student.edu',  '$2y$10$4HLSahtsJTm5jv0qP1dCH.4Ru1jPZrL6MwHWqYwaFEd/UCzMCFrJm', 'student'),
('Priya Nair',    'priya@student.edu',  '$2y$10$4HLSahtsJTm5jv0qP1dCH.4Ru1jPZrL6MwHWqYwaFEd/UCzMCFrJm', 'student'),
('Vikram Sawant', 'vikram@student.edu', '$2y$10$4HLSahtsJTm5jv0qP1dCH.4Ru1jPZrL6MwHWqYwaFEd/UCzMCFrJm', 'student');

INSERT INTO students (user_id, roll_no, course_id, year, division_id, phone, admission_date) VALUES
(4, 'BCA2401', 1, 1, 1, '9800000001', '2024-06-15'),
(5, 'BCA2402', 1, 1, 1, '9800000002', '2024-06-15'),
(6, 'BCA2403', 1, 1, 1, '9800000003', '2024-06-16'),
(7, 'BCA2404', 1, 1, 1, '9800000004', '2024-06-16'),
(8, 'BCA2405', 1, 1, 1, '9800000005', '2024-06-17'),
(9, 'BCA2406', 1, 1, 1, '9800000006', '2024-06-17');

-- A few days of sample attendance so dashboards are not empty on first run.
INSERT INTO attendance (student_id, subject_id, teacher_id, attend_date, status) VALUES
(1,1,1,'2026-09-08','P'), (2,1,1,'2026-09-08','P'), (3,1,1,'2026-09-08','A'),
(4,1,1,'2026-09-08','P'), (5,1,1,'2026-09-08','P'), (6,1,1,'2026-09-08','A'),
(1,1,1,'2026-09-09','P'), (2,1,1,'2026-09-09','A'), (3,1,1,'2026-09-09','P'),
(4,1,1,'2026-09-09','P'), (5,1,1,'2026-09-09','P'), (6,1,1,'2026-09-09','A'),
(1,1,1,'2026-09-10','P'), (2,1,1,'2026-09-10','P'), (3,1,1,'2026-09-10','P'),
(4,1,1,'2026-09-10','A'), (5,1,1,'2026-09-10','P'), (6,1,1,'2026-09-10','A'),
(1,2,2,'2026-09-08','P'), (2,2,2,'2026-09-08','P'), (3,2,2,'2026-09-08','P'),
(4,2,2,'2026-09-08','P'), (5,2,2,'2026-09-08','A'), (6,2,2,'2026-09-08','A');
