<?php
/** Every student below the required percentage, with contact details. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

$threshold = int_input('threshold', MIN_ATTENDANCE);
$threshold = max(1, min(100, (int) $threshold));

$st = $pdo->prepare("
    SELECT s.roll_no, u.name, u.email, s.phone, c.code AS course, s.year, d.name AS division,
           COUNT(a.id) AS total, COALESCE(SUM(a.status='P'),0) AS present
    FROM students s
    JOIN users u     ON u.id = s.user_id
    JOIN courses c   ON c.id = s.course_id
    JOIN divisions d ON d.id = s.division_id
    JOIN attendance a ON a.student_id = s.id
    GROUP BY s.id, s.roll_no, u.name, u.email, s.phone, c.code, s.year, d.name
    HAVING total > 0 AND (SUM(a.status='P') / COUNT(a.id)) * 100 < ?
    ORDER BY (SUM(a.status='P') / COUNT(a.id)) ASC");
$st->execute([$threshold]);
$rows = $st->fetchAll();

if (($_GET['export'] ?? '') === 'csv' && $rows) {
    $csv = [];
    foreach ($rows as $r) {
        $csv[] = [$r['roll_no'], $r['name'], $r['course'] . ' Y' . $r['year'] . ' ' . $r['division'],
                  $r['email'], $r['phone'], (int) $r['present'], (int) $r['total'],
                  percentage((int) $r['present'], (int) $r['total']) . '%'];
    }
    send_csv('low_attendance_below_' . $threshold . '.csv',
             ['Roll no', 'Student', 'Class', 'Email', 'Phone', 'Present', 'Total', 'Percentage'], $csv);
}

$page_title = 'Low attendance';
require_once __DIR__ . '/../includes/header.php';
?>
<h1>Low attendance</h1>
<p class="sub">Students whose overall attendance is under the limit.</p>

<form method="get" class="row no-print" style="margin-bottom:18px">
    <div class="field" style="flex:0 0 160px">
        <label for="threshold">Below this percentage</label>
        <input type="number" id="threshold" name="threshold" min="1" max="100" value="<?= (int) $threshold ?>">
    </div>
    <div class="field" style="flex:0 0 auto"><button class="btn" type="submit">Apply</button></div>
</form>

<?php if (!$rows): ?>
    <div class="empty">No student is below <?= (int) $threshold ?>%.</div>
<?php else: ?>
    <div class="actions no-print" style="margin-bottom:12px">
        <a class="btn btn-line" href="?threshold=<?= (int) $threshold ?>&amp;export=csv">Download CSV</a>
        <button class="btn btn-line" type="button" onclick="window.print()">Print</button>
    </div>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Roll no.</th><th>Student</th><th>Class</th><th>Contact</th>
                   <th class="num">Attended</th><th class="num">Percentage</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r):
            $pct = percentage((int) $r['present'], (int) $r['total']); ?>
            <tr>
                <td><?= e($r['roll_no']) ?></td>
                <td><?= e($r['name']) ?></td>
                <td><?= e($r['course'] . ' Y' . $r['year'] . ' - ' . $r['division']) ?></td>
                <td><?= e($r['phone'] ?: $r['email']) ?></td>
                <td class="num"><?= (int) $r['present'] ?> / <?= (int) $r['total'] ?></td>
                <td class="num <?= pct_class($pct) ?>"><?= number_format($pct, 2) ?>%</td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
