<?php
/**
 * Mark or edit attendance for one subject, one division, one date.
 * Re-opening the same date loads what was saved, so this screen doubles
 * as the edit screen. Saving uses INSERT ... ON DUPLICATE KEY UPDATE.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('teacher');
$teacherId = current_teacher_id($pdo);

// Classes this teacher is allowed to touch
$st = $pdo->prepare("
    SELECT ts.subject_id, ts.division_id, sub.name AS subject,
           c.code AS course, d.year, d.name AS division
    FROM teacher_subjects ts
    JOIN subjects sub ON sub.id = ts.subject_id
    JOIN divisions d  ON d.id = ts.division_id
    JOIN courses c    ON c.id = d.course_id
    WHERE ts.teacher_id = ?
    ORDER BY c.code, d.year, sub.name");
$st->execute([$teacherId]);
$classes = $st->fetchAll();

$subjectId  = int_input('subject_id',  $classes[0]['subject_id']  ?? null);
$divisionId = int_input('division_id', $classes[0]['division_id'] ?? null);
$date       = date_input('date');

/** Confirms the chosen subject+division really belongs to this teacher. */
$allowed = false;
foreach ($classes as $c) {
    if ((int) $c['subject_id'] === $subjectId && (int) $c['division_id'] === $divisionId) {
        $allowed = true;
        break;
    }
}

// ---------- save ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    if (!$allowed) {
        set_flash('error', 'You are not assigned to that class.');
    } elseif ($date > date('Y-m-d')) {
        set_flash('error', 'Attendance cannot be marked for a future date.');
    } else {
        $marks = $_POST['status'] ?? [];   // student_id => P | A
        $sql = 'INSERT INTO attendance (student_id, subject_id, teacher_id, attend_date, status)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE status = VALUES(status), teacher_id = VALUES(teacher_id)';
        $ins = $pdo->prepare($sql);
        $pdo->beginTransaction();
        $saved = 0;
        foreach ($marks as $studentId => $status) {
            if (!in_array($status, ['P', 'A'], true)) continue;
            $ins->execute([(int) $studentId, $subjectId, $teacherId, $date, $status]);
            $saved++;
        }
        $pdo->commit();
        set_flash('ok', "Attendance saved for $saved students on " . date('d M Y', strtotime($date)) . '.');
    }
    header('Location: ' . base_url('teacher/mark_attendance.php?' . http_build_query(
        ['subject_id' => $subjectId, 'division_id' => $divisionId, 'date' => $date])));
    exit;
}

// ---------- students of this division, with anything already saved ----------
$students = [];
if ($allowed) {
    $st = $pdo->prepare("
        SELECT s.id, s.roll_no, u.name, a.status
        FROM students s
        JOIN users u ON u.id = s.user_id
        LEFT JOIN attendance a
               ON a.student_id = s.id AND a.subject_id = ? AND a.attend_date = ?
        WHERE s.division_id = ?
        ORDER BY s.roll_no");
    $st->execute([$subjectId, $divisionId, $date]);
    $students = $st->fetchAll();
}
$alreadyMarked = false;
foreach ($students as $s) { if ($s['status'] !== null) { $alreadyMarked = true; break; } }

$page_title = 'Mark attendance';
require_once __DIR__ . '/../includes/header.php';
?>
<h1>Mark attendance</h1>
<p class="sub">Pick the class and date, then mark each student. Saving again on the same date updates the record.</p>

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
                    $val = $c['subject_id'] . '|' . $c['division_id'];
                    $sel = ((int) $c['subject_id'] === $subjectId && (int) $c['division_id'] === $divisionId); ?>
                    <option value="<?= e($val) ?>" <?= $sel ? 'selected' : '' ?>>
                        <?= e($c['subject'] . ' - ' . $c['course'] . ' Y' . $c['year'] . ' Div ' . $c['division']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" id="subject_id"  name="subject_id"  value="<?= (int) $subjectId ?>">
            <input type="hidden" id="division_id" name="division_id" value="<?= (int) $divisionId ?>">
        </div>
        <div class="field">
            <label for="date">Date</label>
            <input type="date" id="date" name="date" value="<?= e($date) ?>" max="<?= e(date('Y-m-d')) ?>"
                   onchange="this.form.submit()">
        </div>
        <div class="field" style="flex:0 0 auto"><button class="btn btn-line" type="submit">Load class</button></div>
    </div>
</form>

<?php if (!$classes): ?>
    <div class="empty">No classes are assigned to you yet.</div>
<?php elseif (!$allowed): ?>
    <div class="empty">You are not assigned to that class.</div>
<?php elseif (!$students): ?>
    <div class="empty">This division has no students yet.</div>
<?php else: ?>
    <?php if ($alreadyMarked): ?>
        <div class="flash flash-ok">Attendance for this date is already saved. Change any entry and save again to correct it.</div>
    <?php endif; ?>

    <form method="post">
        <?= csrf_field() ?>
        <div class="actions no-print" style="margin-bottom:12px">
            <button class="btn btn-line" type="button" onclick="setAll('P')">Mark everyone present</button>
            <button class="btn btn-line" type="button" onclick="setAll('A')">Mark everyone absent</button>
            <span id="tally" class="sub" style="margin:0 0 0 8px"></span>
        </div>

        <div class="table-wrap">
        <table id="sheet">
            <thead><tr><th style="width:120px">Roll no.</th><th>Student</th><th style="width:200px">Attendance</th></tr></thead>
            <tbody>
            <?php foreach ($students as $s):
                $status = $s['status'] ?? 'P';   // default to present, the common case ?>
                <tr>
                    <td><?= e($s['roll_no']) ?></td>
                    <td><?= e($s['name']) ?></td>
                    <td>
                        <span class="mark">
                            <label>
                                <input type="radio" name="status[<?= (int) $s['id'] ?>]" value="P" <?= $status === 'P' ? 'checked' : '' ?>>
                                <span class="p">Present</span>
                            </label>
                            <label>
                                <input type="radio" name="status[<?= (int) $s['id'] ?>]" value="A" <?= $status === 'A' ? 'checked' : '' ?>>
                                <span class="a">Absent</span>
                            </label>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <button class="btn" type="submit">Save attendance</button>
    </form>

    <script>
    function setAll(value) {
        document.querySelectorAll('#sheet input[type=radio][value="' + value + '"]')
                .forEach(function (r) { r.checked = true; });
        tally();
    }
    function tally() {
        var present = document.querySelectorAll('#sheet input[value="P"]:checked').length;
        var total   = document.querySelectorAll('#sheet tbody tr').length;
        document.getElementById('tally').textContent = present + ' present, ' + (total - present) + ' absent';
    }
    document.getElementById('sheet').addEventListener('change', tally);
    tally();
    </script>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
