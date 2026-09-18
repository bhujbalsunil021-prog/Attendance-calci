<?php
/**
 * Attendance reports: pick a class, an optional subject and a date range.
 * The same query powers the on-screen table and the CSV download.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

$divisions = $pdo->query('SELECT d.id, d.year, d.name, c.code AS course FROM divisions d
                          JOIN courses c ON c.id = d.course_id ORDER BY c.code, d.year, d.name')->fetchAll();

$divisionId = int_input('division_id', $divisions[0]['id'] ?? null);
$subjectId  = int_input('subject_id');
$from       = date_input('from', date('Y-m-01'));
$to         = date_input('to', date('Y-m-d'));

// Subjects taught to the chosen division
$subjects = [];
if ($divisionId) {
    $st = $pdo->prepare('SELECT s.id, s.name, s.code FROM subjects s
                         JOIN divisions d ON d.course_id = s.course_id AND d.year = s.year
                         WHERE d.id = ? ORDER BY s.name');
    $st->execute([$divisionId]);
    $subjects = $st->fetchAll();
}

$rows = [];
if ($divisionId) {
    $sql = "SELECT s.roll_no, u.name,
                   COUNT(a.id) AS total,
                   COALESCE(SUM(a.status = 'P'), 0) AS present
            FROM students s
            JOIN users u ON u.id = s.user_id
            LEFT JOIN attendance a
                   ON a.student_id = s.id
                  AND a.attend_date BETWEEN ? AND ?
                  " . ($subjectId ? 'AND a.subject_id = ?' : '') . "
            WHERE s.division_id = ?
            GROUP BY s.id, s.roll_no, u.name
            ORDER BY s.roll_no";
    $args = [$from, $to];
    if ($subjectId) $args[] = $subjectId;
    $args[] = $divisionId;
    $st = $pdo->prepare($sql);
    $st->execute($args);
    $rows = $st->fetchAll();
}

// CSV download uses exactly the rows shown on screen.
if (($_GET['export'] ?? '') === 'csv' && $rows) {
    $csv = [];
    foreach ($rows as $r) {
        $pct = percentage((int) $r['present'], (int) $r['total']);
        $csv[] = [$r['roll_no'], $r['name'], (int) $r['present'], (int) $r['total'], $pct . '%'];
    }
    send_csv('attendance_' . $from . '_to_' . $to . '.csv',
             ['Roll no', 'Student', 'Present', 'Total classes', 'Percentage'], $csv);
}

$page_title = 'Reports';
require_once __DIR__ . '/../includes/header.php';
?>
<h1>Attendance reports</h1>
<p class="sub">Choose a class and a period. Leave the subject blank for an all-subject total.</p>

<form method="get" class="card no-print">
    <div class="row">
        <div class="field">
            <label for="division_id">Class</label>
            <select id="division_id" name="division_id" onchange="this.form.subject_id.value=''; this.form.submit()">
                <?php foreach ($divisions as $d): ?>
                    <option value="<?= (int) $d['id'] ?>" <?= $divisionId === (int) $d['id'] ? 'selected' : '' ?>>
                        <?= e($d['course'] . ' Year ' . $d['year'] . ' - Division ' . $d['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="subject_id">Subject</label>
            <select id="subject_id" name="subject_id">
                <option value="">All subjects</option>
                <?php foreach ($subjects as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= $subjectId === (int) $s['id'] ? 'selected' : '' ?>>
                        <?= e($s['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="from">From</label>
            <input type="date" id="from" name="from" value="<?= e($from) ?>">
        </div>
        <div class="field">
            <label for="to">To</label>
            <input type="date" id="to" name="to" value="<?= e($to) ?>">
        </div>
        <div class="field" style="flex:0 0 auto">
            <button class="btn" type="submit">Show report</button>
        </div>
    </div>
</form>

<?php if (!$rows): ?>
    <div class="empty">No students found for this class.</div>
<?php else: ?>
    <div class="actions no-print" style="margin-bottom:12px">
        <a class="btn btn-line" href="?<?= e(http_build_query($_GET + ['export' => 'csv'])) ?>">Download CSV</a>
        <button class="btn btn-line" type="button" onclick="window.print()">Print</button>
    </div>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Roll no.</th><th>Student</th><th class="num">Present</th><th class="num">Classes held</th>
                   <th class="num">Percentage</th><th>Level</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r):
            $pct = percentage((int) $r['present'], (int) $r['total']); ?>
            <tr>
                <td><?= e($r['roll_no']) ?></td>
                <td><?= e($r['name']) ?></td>
                <td class="num"><?= (int) $r['present'] ?></td>
                <td class="num"><?= (int) $r['total'] ?></td>
                <td class="num <?= pct_class($pct) ?>"><?= number_format($pct, 2) ?>%</td>
                <td><div class="bar"><span style="width:<?= min(100, $pct) ?>%"></span></div></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
