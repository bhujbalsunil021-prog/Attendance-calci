<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('teacher');
$teacherId = current_teacher_id($pdo);

$st = $pdo->prepare("
    SELECT ts.subject_id, ts.division_id, sub.name AS subject, sub.code,
           c.code AS course, d.year, d.name AS division,
           (SELECT COUNT(*) FROM students s WHERE s.division_id = d.id) AS strength,
           (SELECT MAX(a.attend_date) FROM attendance a
             WHERE a.subject_id = ts.subject_id AND a.teacher_id = ts.teacher_id) AS last_marked
    FROM teacher_subjects ts
    JOIN subjects sub ON sub.id = ts.subject_id
    JOIN divisions d  ON d.id = ts.division_id
    JOIN courses c    ON c.id = d.course_id
    WHERE ts.teacher_id = ?
    ORDER BY c.code, d.year, sub.name");
$st->execute([$teacherId]);
$classes = $st->fetchAll();

$st = $pdo->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(status='P'),0) AS present
                     FROM attendance WHERE teacher_id = ? AND attend_date = ?");
$st->execute([$teacherId, date('Y-m-d')]);
$today = $st->fetch();

$page_title = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>
<h1>Welcome, <?= e($_SESSION['name']) ?></h1>
<p class="sub"><?= e(date('l, j F Y')) ?></p>

<div class="stats">
    <div class="stat"><div class="stat-value"><?= count($classes) ?></div><div class="stat-label">Classes assigned to you</div></div>
    <div class="stat"><div class="stat-value"><?= (int) $today['total'] ?></div><div class="stat-label">Students marked today</div></div>
    <div class="stat"><div class="stat-value"><?= (int) $today['present'] ?></div><div class="stat-label">Present today</div></div>
</div>

<h2>Your classes</h2>
<?php if (!$classes): ?>
    <div class="empty">No classes are assigned to you yet. Ask the administrator to add a teaching assignment.</div>
<?php else: ?>
<div class="table-wrap">
<table>
    <thead><tr><th>Subject</th><th>Class</th><th class="num">Students</th><th>Last marked</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($classes as $c): ?>
        <tr>
            <td><?= e($c['subject'] . ' (' . $c['code'] . ')') ?></td>
            <td><?= e($c['course'] . ' Y' . $c['year'] . ' - ' . $c['division']) ?></td>
            <td class="num"><?= (int) $c['strength'] ?></td>
            <td><?= $c['last_marked'] ? e(date('d M Y', strtotime($c['last_marked']))) : 'Not marked yet' ?></td>
            <td>
                <a class="btn btn-sm" href="<?= e(base_url('teacher/mark_attendance.php?subject_id=' . (int) $c['subject_id'] . '&division_id=' . (int) $c['division_id'])) ?>">Mark attendance</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
