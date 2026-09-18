<?php
/** Day-by-day record of what this teacher has marked. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('teacher');
$teacherId = current_teacher_id($pdo);

$from = date_input('from', date('Y-m-01'));
$to   = date_input('to', date('Y-m-d'));

$st = $pdo->prepare("
    SELECT a.attend_date, a.subject_id, sub.name AS subject,
           c.code AS course, d.year, d.name AS division, d.id AS division_id,
           COUNT(*) AS total, SUM(a.status='P') AS present
    FROM attendance a
    JOIN subjects sub ON sub.id = a.subject_id
    JOIN students s   ON s.id = a.student_id
    JOIN divisions d  ON d.id = s.division_id
    JOIN courses c    ON c.id = d.course_id
    WHERE a.teacher_id = ? AND a.attend_date BETWEEN ? AND ?
    GROUP BY a.attend_date, a.subject_id, sub.name, c.code, d.year, d.name, d.id
    ORDER BY a.attend_date DESC, sub.name");
$st->execute([$teacherId, $from, $to]);
$rows = $st->fetchAll();

$page_title = 'Attendance history';
require_once __DIR__ . '/../includes/header.php';
?>
<h1>Attendance history</h1>
<p class="sub">Every class you have marked. Open one to correct it.</p>

<form method="get" class="row no-print" style="margin-bottom:18px">
    <div class="field"><label for="from">From</label><input type="date" id="from" name="from" value="<?= e($from) ?>"></div>
    <div class="field"><label for="to">To</label><input type="date" id="to" name="to" value="<?= e($to) ?>"></div>
    <div class="field" style="flex:0 0 auto"><button class="btn btn-line" type="submit">Show</button></div>
</form>

<?php if (!$rows): ?>
    <div class="empty">Nothing marked in this period.</div>
<?php else: ?>
<div class="table-wrap">
<table>
    <thead><tr><th>Date</th><th>Subject</th><th>Class</th><th class="num">Present</th><th class="num">Absent</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r):
        $absent = (int) $r['total'] - (int) $r['present']; ?>
        <tr>
            <td><?= e(date('d M Y', strtotime($r['attend_date']))) ?></td>
            <td><?= e($r['subject']) ?></td>
            <td><?= e($r['course'] . ' Y' . $r['year'] . ' - ' . $r['division']) ?></td>
            <td class="num"><?= (int) $r['present'] ?></td>
            <td class="num"><?= $absent ?></td>
            <td>
                <a class="btn btn-line btn-sm" href="<?= e(base_url('teacher/mark_attendance.php?' . http_build_query([
                    'subject_id' => $r['subject_id'], 'division_id' => $r['division_id'], 'date' => $r['attend_date']]))) ?>">Open and edit</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
