<?php
/**
 * Links a teacher to a subject for one division.
 * A teacher can only mark attendance for the classes listed here.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

$errors = [];

if (($_GET['delete'] ?? '') !== '') {
    $pdo->prepare('DELETE FROM teacher_subjects WHERE id = ?')->execute([int_input('delete')]);
    set_flash('ok', 'Assignment removed.');
    header('Location: ' . base_url('admin/assignments.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $teacher  = int_input('teacher_id');
    $subject  = int_input('subject_id');
    $division = int_input('division_id');

    if (!$teacher || !$subject || !$division) {
        $errors[] = 'Choose a teacher, a subject and a division.';
    } else {
        // The subject and the division must belong to the same course and year.
        $st = $pdo->prepare('SELECT s.course_id AS sc, s.year AS sy, d.course_id AS dc, d.year AS dy
                             FROM subjects s, divisions d WHERE s.id = ? AND d.id = ?');
        $st->execute([$subject, $division]);
        $m = $st->fetch();
        if (!$m || $m['sc'] !== $m['dc'] || $m['sy'] !== $m['dy']) {
            $errors[] = 'That subject is not taught to that division. Check the course and year.';
        } else {
            try {
                $pdo->prepare('INSERT INTO teacher_subjects (teacher_id, subject_id, division_id) VALUES (?, ?, ?)')
                    ->execute([$teacher, $subject, $division]);
                set_flash('ok', 'Assignment saved.');
                header('Location: ' . base_url('admin/assignments.php'));
                exit;
            } catch (PDOException $e) {
                $errors[] = ($e->getCode() === '23000') ? 'That assignment already exists.' : $e->getMessage();
            }
        }
    }
}

$teachers = $pdo->query('SELECT t.id, u.name, t.emp_code FROM teachers t JOIN users u ON u.id = t.user_id ORDER BY u.name')->fetchAll();
$subjects = $pdo->query('SELECT s.id, s.name, s.code, s.year, c.code AS course FROM subjects s JOIN courses c ON c.id = s.course_id ORDER BY c.code, s.year, s.name')->fetchAll();
$divisions = $pdo->query('SELECT d.id, d.year, d.name, c.code AS course FROM divisions d JOIN courses c ON c.id = d.course_id ORDER BY c.code, d.year, d.name')->fetchAll();

$rows = $pdo->query("
    SELECT ts.id, u.name AS teacher, sub.name AS subject, sub.code,
           c.code AS course, d.year, d.name AS division
    FROM teacher_subjects ts
    JOIN teachers t  ON t.id = ts.teacher_id
    JOIN users u     ON u.id = t.user_id
    JOIN subjects sub ON sub.id = ts.subject_id
    JOIN divisions d ON d.id = ts.division_id
    JOIN courses c   ON c.id = d.course_id
    ORDER BY u.name, sub.name")->fetchAll();

$page_title = 'Teaching assignments';
require_once __DIR__ . '/../includes/header.php';
?>
<h1>Teaching assignments</h1>
<p class="sub">A teacher sees a class on their attendance screen only after it is assigned here.</p>

<?php if ($errors): ?><div class="flash flash-error"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>

<div class="card">
    <form method="post">
        <?= csrf_field() ?>
        <div class="row">
            <div class="field">
                <label for="teacher_id">Teacher</label>
                <select id="teacher_id" name="teacher_id" required>
                    <option value="">Choose a teacher</option>
                    <?php foreach ($teachers as $t): ?>
                        <option value="<?= (int) $t['id'] ?>"><?= e($t['name'] . ' (' . $t['emp_code'] . ')') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="subject_id">Subject</label>
                <select id="subject_id" name="subject_id" required>
                    <option value="">Choose a subject</option>
                    <?php foreach ($subjects as $s): ?>
                        <option value="<?= (int) $s['id'] ?>"><?= e($s['course'] . ' Y' . $s['year'] . ' - ' . $s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="division_id">Division</label>
                <select id="division_id" name="division_id" required>
                    <option value="">Choose a division</option>
                    <?php foreach ($divisions as $d): ?>
                        <option value="<?= (int) $d['id'] ?>"><?= e($d['course'] . ' Y' . $d['year'] . ' - ' . $d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field" style="flex:0 0 auto"><button class="btn" type="submit">Assign</button></div>
        </div>
    </form>
</div>

<?php if (!$rows): ?>
    <div class="empty">No assignments yet. Teachers cannot mark attendance until you add one.</div>
<?php else: ?>
<div class="table-wrap">
<table>
    <thead><tr><th>Teacher</th><th>Subject</th><th>Class</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= e($r['teacher']) ?></td>
            <td><?= e($r['subject'] . ' (' . $r['code'] . ')') ?></td>
            <td><?= e($r['course'] . ' Y' . $r['year'] . ' - ' . $r['division']) ?></td>
            <td class="actions">
                <a class="btn btn-danger btn-sm" href="?delete=<?= (int) $r['id'] ?>"
                   onclick="return confirm('Remove this assignment?')">Remove</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
