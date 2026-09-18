<?php
/** Subjects belong to a course and a year. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

$errors = [];
$editing = null;

if (($_GET['delete'] ?? '') !== '') {
    try {
        $pdo->prepare('DELETE FROM subjects WHERE id = ?')->execute([int_input('delete')]);
        set_flash('ok', 'Subject removed.');
    } catch (PDOException $e) {
        set_flash('error', 'Cannot remove a subject that already has attendance records.');
    }
    header('Location: ' . base_url('admin/subjects.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $id     = int_input('id');
    $name   = trim($_POST['name'] ?? '');
    $code   = trim($_POST['code'] ?? '');
    $course = int_input('course_id');
    $year   = int_input('year');

    if ($name === '') $errors[] = 'Enter the subject name.';
    if ($code === '') $errors[] = 'Enter a subject code.';
    if (!$course)     $errors[] = 'Choose a course.';
    if (!$year)       $errors[] = 'Choose a year.';

    if (!$errors) {
        try {
            if ($id) {
                $pdo->prepare('UPDATE subjects SET name = ?, code = ?, course_id = ?, year = ? WHERE id = ?')
                    ->execute([$name, $code, $course, $year, $id]);
                set_flash('ok', 'Subject updated.');
            } else {
                $pdo->prepare('INSERT INTO subjects (name, code, course_id, year) VALUES (?, ?, ?, ?)')
                    ->execute([$name, $code, $course, $year]);
                set_flash('ok', 'Subject added.');
            }
            header('Location: ' . base_url('admin/subjects.php'));
            exit;
        } catch (PDOException $e) {
            $errors[] = ($e->getCode() === '23000') ? 'That subject code already exists.' : $e->getMessage();
        }
    }
    $editing = compact('id', 'name', 'code') + ['course_id' => $course, 'year' => $year];
}

if (($_GET['edit'] ?? '') !== '' && !$editing) {
    $st = $pdo->prepare('SELECT * FROM subjects WHERE id = ?');
    $st->execute([int_input('edit')]);
    $editing = $st->fetch() ?: null;
}

$courses  = $pdo->query('SELECT * FROM courses ORDER BY code')->fetchAll();
$subjects = $pdo->query('SELECT s.*, c.code AS course FROM subjects s JOIN courses c ON c.id = s.course_id
                         ORDER BY c.code, s.year, s.code')->fetchAll();

$page_title = 'Subjects';
require_once __DIR__ . '/../includes/header.php';
?>
<h1>Subjects</h1>
<p class="sub">Attendance is recorded per subject, so every taught subject needs an entry here.</p>

<?php if ($errors): ?><div class="flash flash-error"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>

<div class="card">
    <h2 style="margin-top:0"><?= !empty($editing['id']) ? 'Edit subject' : 'Add a subject' ?></h2>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= e($editing['id'] ?? '') ?>">
        <div class="row">
            <div class="field">
                <label for="name">Subject name</label>
                <input type="text" id="name" name="name" required value="<?= e($editing['name'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="code">Subject code</label>
                <input type="text" id="code" name="code" required value="<?= e($editing['code'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="course_id">Course</label>
                <select id="course_id" name="course_id" required>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= (int) ($editing['course_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
                            <?= e($c['code'] . ' - ' . $c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="year">Year</label>
                <select id="year" name="year" required>
                    <?php for ($y = 1; $y <= 4; $y++): ?>
                        <option value="<?= $y ?>" <?= (int) ($editing['year'] ?? 1) === $y ? 'selected' : '' ?>>Year <?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="field" style="flex:0 0 auto">
                <button class="btn" type="submit"><?= !empty($editing['id']) ? 'Save changes' : 'Add subject' ?></button>
                <?php if (!empty($editing['id'])): ?>
                    <a class="btn btn-line" href="<?= e(base_url('admin/subjects.php')) ?>">Cancel</a>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<div class="table-wrap">
<table>
    <thead><tr><th>Code</th><th>Subject</th><th>Course</th><th>Year</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($subjects as $s): ?>
        <tr>
            <td><?= e($s['code']) ?></td>
            <td><?= e($s['name']) ?></td>
            <td><?= e($s['course']) ?></td>
            <td>Year <?= (int) $s['year'] ?></td>
            <td class="actions">
                <a class="btn btn-line btn-sm" href="?edit=<?= (int) $s['id'] ?>">Edit</a>
                <a class="btn btn-danger btn-sm" href="?delete=<?= (int) $s['id'] ?>"
                   onclick="return confirm('Remove this subject?')">Delete</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
