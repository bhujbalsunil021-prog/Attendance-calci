<?php
/** Overview of the whole college: counts, today's marking, problem cases. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

$today = date('Y-m-d');

$totalStudents = (int) $pdo->query('SELECT COUNT(*) FROM students')->fetchColumn();
$totalTeachers = (int) $pdo->query('SELECT COUNT(*) FROM teachers')->fetchColumn();
$totalSubjects = (int) $pdo->query('SELECT COUNT(*) FROM subjects')->fetchColumn();

$st = $pdo->prepare("SELECT COUNT(*) AS marked, COALESCE(SUM(status='P'),0) AS present
                     FROM attendance WHERE attend_date = ?");
$st->execute([$today]);
$todayRow     = $st->fetch();
$markedToday  = (int) $todayRow['marked'];
$presentToday = (int) $todayRow['present'];
$absentToday  = $markedToday - $presentToday;

$avgRow = $pdo->query("SELECT COUNT(*) AS total, COALESCE(SUM(status='P'),0) AS present FROM attendance")->fetch();
$overallPct = percentage((int) $avgRow['present'], (int) $avgRow['total']);

$lowStudents = $pdo->query("
    SELECT u.name, s.roll_no, COUNT(a.id) AS total, SUM(a.status='P') AS present
    FROM students s
    JOIN users u ON u.id = s.user_id
    JOIN attendance a ON a.student_id = s.id
    GROUP BY s.id, u.name, s.roll_no
    HAVING total > 0 AND (SUM(a.status='P') / COUNT(a.id)) * 100 < " . (int) MIN_ATTENDANCE . "
    ORDER BY (SUM(a.status='P') / COUNT(a.id)) ASC
    LIMIT 8")->fetchAll();

$recent = $pdo->query("
    SELECT a.attend_date, sub.name AS subject, u.name AS teacher,
           COUNT(*) AS total, SUM(a.status='P') AS present
    FROM attendance a
    JOIN subjects sub ON sub.id = a.subject_id
    JOIN teachers t   ON t.id = a.teacher_id
    JOIN users u      ON u.id = t.user_id
    GROUP BY a.attend_date, a.subject_id, a.teacher_id, sub.name, u.name
    ORDER BY a.attend_date DESC, sub.name
    LIMIT 8")->fetchAll();

$page_title = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>
<h1>Dashboard</h1>
<p class="sub"><?= e(date('l, j F Y')) ?></p>

<div class="stats">
    <div class="stat"><div class="stat-value"><?= $totalStudents ?></div><div class="stat-label">Students</div></div>
    <div class="stat"><div class="stat-value"><?= $totalTeachers ?></div><div class="stat-label">Teachers</div></div>
    <div class="stat"><div class="stat-value"><?= $totalSubjects ?></div><div class="stat-label">Subjects</div></div>
    <div class="stat"><div class="stat-value"><?= $presentToday ?></div><div class="stat-label">Present today</div></div>
    <div class="stat"><div class="stat-value"><?= $absentToday ?></div><div class="stat-label">Absent today</div></div>
    <div class="stat">
        <div class="stat-value <?= pct_class($overallPct) ?>"><?= number_format($overallPct, 1) ?>%</div>
        <div class="stat-label">Average attendance</div>
    </div>
</div>

<h2>Students below <?= (int) MIN_ATTENDANCE ?>%</h2>
<?php if (!$lowStudents): ?>
    <div class="empty">No student is below the required percentage.</div>
<?php else: ?>
<div class="table-wrap">
<table>
    <thead><tr><th>Roll no.</th><th>Name</th><th class="num">Attended</th><th class="num">Percentage</th></tr></thead>
    <tbody>
    <?php foreach ($lowStudents as $r):
        $pct = percentage((int) $r['present'], (int) $r['total']); ?>
        <tr>
            <td><?= e($r['roll_no']) ?></td>
            <td><?= e($r['name']) ?></td>
            <td class="num"><?= (int) $r['present'] ?> / <?= (int) $r['total'] ?></td>
            <td class="num <?= pct_class($pct) ?>"><?= number_format($pct, 2) ?>%</td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<p><a href="<?= e(base_url('admin/low_attendance.php')) ?>">See the full list</a></p>
<?php endif; ?>

<h2>Recently marked classes</h2>
<?php if (!$recent): ?>
    <div class="empty">No attendance has been marked yet.</div>
<?php else: ?>
<div class="table-wrap">
<table>
    <thead><tr><th>Date</th><th>Subject</th><th>Teacher</th><th class="num">Present</th></tr></thead>
    <tbody>
    <?php foreach ($recent as $r): ?>
        <tr>
            <td><?= e(date('d M Y', strtotime($r['attend_date']))) ?></td>
            <td><?= e($r['subject']) ?></td>
            <td><?= e($r['teacher']) ?></td>
            <td class="num"><?= (int) $r['present'] ?> / <?= (int) $r['total'] ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
