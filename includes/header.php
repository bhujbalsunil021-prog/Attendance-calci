<?php
/**
 * Shared page shell: top bar, side navigation and flash message.
 * A page sets $page_title before including this file.
 */
require_once __DIR__ . '/bootstrap.php';

$role  = current_role();
$title = $page_title ?? 'Attendance';
$flash = get_flash();

$nav = [
    'admin' => [
        ['admin/dashboard.php',      'Dashboard'],
        ['admin/students.php',       'Students'],
        ['admin/teachers.php',       'Teachers'],
        ['admin/subjects.php',       'Subjects'],
        ['admin/divisions.php',      'Courses & divisions'],
        ['admin/assignments.php',    'Teaching assignments'],
        ['admin/reports.php',        'Reports'],
        ['admin/low_attendance.php', 'Low attendance'],
    ],
    'teacher' => [
        ['teacher/dashboard.php',       'Dashboard'],
        ['teacher/mark_attendance.php', 'Mark attendance'],
        ['teacher/history.php',         'Attendance history'],
        ['teacher/reports.php',         'Reports'],
    ],
    'student' => [
        ['student/dashboard.php', 'My attendance'],
        ['student/history.php',   'Day-by-day record'],
    ],
];
$current = basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> &middot; Attendance</title>
<link rel="stylesheet" href="<?= e(base_url('assets/css/style.css')) ?>">
</head>
<body>
<header class="topbar">
    <a class="brand" href="<?= e(base_url($role . '/dashboard.php')) ?>">
        <span class="brand-mark">AMS</span>
        <span class="brand-text">Attendance Management</span>
    </a>
    <div class="who">
        <span class="who-name"><?= e($_SESSION['name'] ?? '') ?></span>
        <span class="who-role"><?= e(ucfirst((string) $role)) ?></span>
        <a class="btn btn-quiet" href="<?= e(base_url('logout.php')) ?>">Sign out</a>
    </div>
</header>

<div class="shell">
    <nav class="side">
        <?php foreach (($nav[$role] ?? []) as $item): ?>
            <a class="side-link<?= basename($item[0]) === $current ? ' is-active' : '' ?>"
               href="<?= e(base_url($item[0])) ?>"><?= e($item[1]) ?></a>
        <?php endforeach; ?>
    </nav>

    <main class="main">
        <?php if ($flash): ?>
            <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['text']) ?></div>
        <?php endif; ?>
