<?php
/** Subject-wise percentages for the classes this teacher handles. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('teacher');
$teacherId = current_teacher_id($pdo);

$st = $pdo->prepare("
    SELECT ts.subject_id, ts.division_id, sub.name AS subject,
           c.code AS course, d.year, d.name AS division
    FROM teacher_subjects ts
    JOIN subjects sub ON sub.id = ts.subject_id
    JOIN divisions d  ON d.id = ts.division_id
    JOIN courses c    ON c.id = d.course_id
    WHERE ts.teacher_id = ? ORDER BY sub.name");
$st->execute([$teacherId]);
$classes = $st->fetchAll();

$subjectId  = int_input('subject_id',  $classes[0]['subject_id']  ?? null);
$divisionId = int_input('division_id', $classes[0]['division_id'] ?? null);
$from = date_input('from', date('Y-m-01'));
$to   = date_input('to', date('Y-m-d'));

$rows = [];
if ($subjectId && $divisionId) {
    $st = $pdo->prepare("
        SELECT s.roll_no, u.name,
               COUNT(a.id) AS total, COALESCE(SUM(a.status='P'),0) AS present
        FROM students s
        JOIN users u ON u.id = s.user_id
        LEFT JOIN attendance a ON a.student_id = s.id AND a.subject_id = ?
                              AND a.attend_date BETWEEN ? AND ?
        WHERE s.division_id = ?
        GROUP BY s.id, s.roll_no, u.name
        ORDER BY s.roll_no");
    $st->execute([$subjectId, $from, $to, $divisionId]);
    $rows = $st->fetchAll();
}

if (($_GET['export'] ?? '') === 'csv' && $rows) {
    $csv = [];
    foreach ($rows as $r) {
        $csv[] = [$r['roll_no'], $r['name'], (int) $r['present'], (int) $r['total'],
                  percentage((int) $r['present'], (int) $r['total']) . '%'];
    }
    send_csv('subject_report_' . $from . '_to_' . $to . '.csv',
             ['Roll no', 'Student', 'Present', 'Classes held', 'Percentage'], $csv);
}

$page_title = 'Reports';
require_once __DIR__ . '/../includes/header.php';
?>
<h1>Subject report</h1>
<p class="sub">Attendance for one of your subjects over a chosen period.</p>

<form method="get" class="card no-print">
    <div class="row">
        <div class="field">
            <label for="cls">Subject and class</label>
            <select id="cls" name="cls" onchange="
                var v = this.value.split('|');
                document.getElementById('subject_id').value = v[0];
                document.getElementById('division_id').value = v[1];
                this.form.submit();">
                <?php foreach ($classes as $c):
                    $sel = ((int) $c['subject_id'] === $subjectId && (int) $c['division_id'] === $divisionId); ?>
                    <option value="<?= e($c['subject_id'] . '|' . $c['division_id']) ?>" <?= $sel ? 'selected' : '' ?>>
                        <?= e($c['subject'] . ' - ' . $c['course'] . ' Y' . $c['year'] . ' Div ' . $c['division']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" id="subject_id"  name="subject_id"  value="<?= (int) $subjectId ?>">
            <input type="hidden" id="division_id" name="division_id" value="<?= (int) $divisionId ?>">
        </div>
        <div class="field"><label for="from">From</label><input type="date" id="from" name="from" value="<?= e($from) ?>"></div>
        <div class="field"><label for="to">To</label><input type="date" id="to" name="to" value="<?= e($to) ?>"></div>
        <div class="field" style="flex:0 0 auto"><button class="btn" type="submit">Show report</button></div>
    </div>
</form>

<?php if (!$rows): ?>
    <div class="empty">Nothing to report for this selection.</div>
<?php else: ?>
    <div class="actions no-print" style="margin-bottom:12px">
        <a class="btn btn-line" href="?<?= e(http_build_query($_GET + ['export' => 'csv', 'subject_id' => $subjectId, 'division_id' => $divisionId])) ?>">Download CSV</a>
        <button class="btn btn-line" type="button" onclick="window.print()">Print</button>
    </div>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Roll no.</th><th>Student</th><th class="num">Present</th><th class="num">Classes held</th><th class="num">Percentage</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r):
            $pct = percentage((int) $r['present'], (int) $r['total']); ?>
            <tr>
                <td><?= e($r['roll_no']) ?></td>
                <td><?= e($r['name']) ?></td>
                <td class="num"><?= (int) $r['present'] ?></td>
                <td class="num"><?= (int) $r['total'] ?></td>
                <td class="num <?= pct_class($pct) ?>"><?= number_format($pct, 2) ?>%</td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
