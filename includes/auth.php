<?php
/**
 * Session handling and role checks.
 * Every protected page starts by calling require_role().
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

function current_role(): ?string {
    return $_SESSION['role'] ?? null;
}

function current_user_id(): ?int {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Sends the visitor to the login page unless they hold one of the roles given.
 * $roles: 'admin', 'teacher' or 'student'.
 */
function require_role(string ...$roles): void {
    if (!is_logged_in()) {
        header('Location: ' . base_url('index.php?msg=login_required'));
        exit;
    }
    if ($roles && !in_array(current_role(), $roles, true)) {
        header('Location: ' . base_url('index.php?msg=not_allowed'));
        exit;
    }
}

/** The teachers.id of the logged-in teacher (not the users.id). */
function current_teacher_id(PDO $pdo): ?int {
    $st = $pdo->prepare('SELECT id FROM teachers WHERE user_id = ?');
    $st->execute([current_user_id()]);
    $id = $st->fetchColumn();
    return $id === false ? null : (int) $id;
}

/** The students.id of the logged-in student. */
function current_student_id(PDO $pdo): ?int {
    $st = $pdo->prepare('SELECT id FROM students WHERE user_id = ?');
    $st->execute([current_user_id()]);
    $id = $st->fetchColumn();
    return $id === false ? null : (int) $id;
}
