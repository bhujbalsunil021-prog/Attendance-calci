# Student Attendance Management System

A working BCA-project implementation of the synopsis you shared: PHP + MySQL,
three roles (admin, teacher, student), runs on plain XAMPP with no extra
installs.

## Setup (XAMPP)

1. Copy the whole `attendance-system` folder into `htdocs` (e.g.
   `C:\xampp\htdocs\attendance-system` or `/Applications/XAMPP/htdocs/attendance-system`).
2. Start **Apache** and **MySQL** from the XAMPP control panel.
3. Open **phpMyAdmin** (`http://localhost/phpmyadmin`), click **Import**,
   choose `database/schema.sql`, and run it. This creates the `attendance_db`
   database and loads sample data.
4. Visit `http://localhost/attendance-system/`.

If your MySQL has a root password or a different username, edit the four
`define()` lines at the top of `config/db.php`.

## Test accounts

Every seeded account uses the password **password123**.

| Role    | Email                  |
|---------|-------------------------|
| Admin   | admin@college.edu       |
| Teacher | deshmukh@college.edu    |
| Teacher | kulkarni@college.edu    |
| Student | aarti@student.edu (and rohit@, sneha@, imran@, priya@, vikram@student.edu) |

## What's implemented, mapped to the synopsis

- **Login module** — one login screen, role-based redirect, CSRF-protected forms.
- **Student / teacher / subject management** — full add/edit/delete for admins,
  plus course & division setup and teacher-to-subject-to-class assignment.
- **Attendance module** — teachers mark Present/Absent per subject/division/date;
  re-opening a marked date reloads and lets them correct it (upsert via
  `ON DUPLICATE KEY UPDATE`, so there's no way to double-mark a day).
- **Attendance calculation** — `Percentage = (Present / Total) × 100`, computed
  in SQL, used consistently across all three dashboards.
- **Reports** — admin and teacher report screens with CSV export and a
  print-friendly layout; a subject/date-range filter.
- **Low-attendance module** — admin screen listing every student under the
  75% threshold (configurable in `config/db.php`), with CSV export.
- **Dashboards** — admin (college-wide counts, today's marking, low-attendance
  preview), teacher (assigned classes, today's tally), student (overall +
  subject-wise percentage, with a "how many classes in a row to recover"
  calculation).

## Security choices worth knowing about (useful for your viva)

- All queries use PDO prepared statements — no string-concatenated SQL with
  user input, so no SQL injection.
- Passwords are stored with `password_hash()` / checked with `password_verify()`,
  never in plain text.
- Every POST form carries a CSRF token checked with `hash_equals()`.
- A teacher can only mark attendance for classes explicitly assigned to them
  in `teacher_subjects` — the assignment is re-checked server-side on every
  save, not just hidden in the UI.
- `attendance` has a `UNIQUE(student_id, subject_id, attend_date)` constraint,
  which is what makes "mark again to correct" safe instead of creating
  duplicate rows.

## Extending it (maps to your synopsis's "Future Scope")

- QR/face-recognition attendance would plug into `teacher/mark_attendance.php`
  as an alternate way to set `status[]` before the existing save logic runs.
- Email/SMS notifications: hook into `admin/low_attendance.php`'s query.
- Parent login: add a `parent` role to the `users.role` enum, mirroring the
  student dashboard with read-only access to one student's records.

## Folder structure

```
config/db.php            connection settings + MIN_ATTENDANCE
includes/                 auth, shared helpers, header/footer shell
database/schema.sql       full schema + seed data
admin/  teacher/  student/  one folder per role, matches the synopsis's scope
assets/css/style.css       single stylesheet, no framework
index.php / logout.php     shared login
```
