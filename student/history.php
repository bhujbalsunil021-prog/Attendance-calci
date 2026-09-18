<?php
/** Every individual attendance entry for the signed-in student. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('student');
$studentId = current_student_id($pdo);

$from = date_input('from', date('Y-m-01'));
$to   = date_input('to', date('Y-m-d'));

$st = $pdo->prepare("
    SELECT a.attend_date, a.status, sub.name AS subject, u.name AS teacher
    FROM attendance a
    JOIN subjects sub ON sub.id = a.subject_id
    JOIN teachers t   ON t.id = a.teacher_id
    JOIN users u      ON u.id = t.user_id
    WHERE a.student_id = ? AND a.attend_date BETWEEN ? AND ?
    ORDER BY a.attend_date DESC, sub.name");
$st->execute([$studentId, $from, $to]);
$rows = $st->fetchAll();

$present = 0;
foreach ($rows as $r) if ($r['status'] === 'P') $present++;

$page_title = 'Day-by-day record';
require_once __DIR__ . '/../includes/header.php';
?>
<h1>Day-by-day record</h1>
<p class="sub"><?= $present ?> present out of <?= count($rows) ?> classes in this period.</p>

<form method="get" class="row no-print" style="margin-bottom:18px">
    <div class="field"><label for="from">From</label><input type="date" id="from" name="from" value="<?= e($from) ?>"></div>
    <div class="field"><label for="to">To</label><input type="date" id="to" name="to" value="<?= e($to) ?>"></div>
    <div class="field" style="flex:0 0 auto"><button class="btn btn-line" type="submit">Show</button></div>
</form>

<?php if (!$rows): ?>
    <div class="empty">No records in this period.</div>
<?php else: ?>
<div class="table-wrap">
<table>
    <thead><tr><th>Date</th><th>Subject</th><th>Teacher</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= e(date('d M Y', strtotime($r['attend_date']))) ?></td>
            <td><?= e($r['subject']) ?></td>
            <td><?= e($r['teacher']) ?></td>
            <td>
                <span class="tag <?= $r['status'] === 'P' ? 'tag-p' : 'tag-a' ?>">
                    <?= $r['status'] === 'P' ? 'Present' : 'Absent' ?>
                </span>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
