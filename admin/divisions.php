<?php
/** Courses and the divisions (class groups) inside them. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

$errors = [];

if (($_GET['delete_course'] ?? '') !== '') {
    try {
        $pdo->prepare('DELETE FROM courses WHERE id = ?')->execute([int_input('delete_course')]);
        set_flash('ok', 'Course removed.');
    } catch (PDOException $e) {
        set_flash('error', 'Cannot remove a course that still has students or attendance records.');
    }
    header('Location: ' . base_url('admin/divisions.php'));
    exit;
}

if (($_GET['delete_division'] ?? '') !== '') {
    try {
        $pdo->prepare('DELETE FROM divisions WHERE id = ?')->execute([int_input('delete_division')]);
        set_flash('ok', 'Division removed.');
    } catch (PDOException $e) {
        set_flash('error', 'Cannot remove a division that still has students in it.');
    }
    header('Location: ' . base_url('admin/divisions.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    try {
        if (($_POST['form'] ?? '') === 'course') {
            $name = trim($_POST['name'] ?? '');
            $code = trim($_POST['code'] ?? '');
            $yrs  = int_input('duration_years', 3);
            if ($name === '' || $code === '') {
                $errors[] = 'A course needs both a name and a code.';
            } else {
                $pdo->prepare('INSERT INTO courses (name, code, duration_years) VALUES (?, ?, ?)')
                    ->execute([$name, $code, $yrs]);
                set_flash('ok', 'Course added.');
            }
        } else {
            $course = int_input('course_id');
            $year   = int_input('year');
            $name   = trim($_POST['div_name'] ?? '');
            if (!$course || !$year || $name === '') {
                $errors[] = 'A division needs a course, a year and a name.';
            } else {
                $pdo->prepare('INSERT INTO divisions (course_id, year, name) VALUES (?, ?, ?)')
                    ->execute([$course, $year, $name]);
                set_flash('ok', 'Division added.');
            }
        }
        if (!$errors) { header('Location: ' . base_url('admin/divisions.php')); exit; }
    } catch (PDOException $e) {
        $errors[] = ($e->getCode() === '23000') ? 'That course code or division already exists.' : $e->getMessage();
    }
}

$courses   = $pdo->query('SELECT * FROM courses ORDER BY code')->fetchAll();
$divisions = $pdo->query("
    SELECT d.*, c.code AS course,
           (SELECT COUNT(*) FROM students s WHERE s.division_id = d.id) AS student_count
    FROM divisions d JOIN courses c ON c.id = d.course_id
    ORDER BY c.code, d.year, d.name")->fetchAll();

$page_title = 'Courses & divisions';
require_once __DIR__ . '/../includes/header.php';
?>
<h1>Courses and divisions</h1>
<p class="sub">A division is one class group, for example BCA Year 1 Division A.</p>

<?php if ($errors): ?><div class="flash flash-error"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>

<div class="card">
    <h2 style="margin-top:0">Add a course</h2>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="course">
        <div class="row">
            <div class="field"><label for="cname">Course name</label>
                <input type="text" id="cname" name="name" required placeholder="Bachelor of Computer Applications"></div>
            <div class="field"><label for="ccode">Code</label>
                <input type="text" id="ccode" name="code" required placeholder="BCA"></div>
            <div class="field"><label for="dur">Duration in years</label>
                <input type="number" id="dur" name="duration_years" min="1" max="6" value="3"></div>
            <div class="field" style="flex:0 0 auto"><button class="btn" type="submit">Add course</button></div>
        </div>
    </form>
</div>

<div class="table-wrap">
<table>
    <thead><tr><th>Code</th><th>Course</th><th>Years</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($courses as $c): ?>
        <tr>
            <td><?= e($c['code']) ?></td>
            <td><?= e($c['name']) ?></td>
            <td><?= (int) $c['duration_years'] ?></td>
            <td class="actions">
                <a class="btn btn-danger btn-sm" href="?delete_course=<?= (int) $c['id'] ?>"
                   onclick="return confirm('Remove this course and its divisions and subjects?')">Delete</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<div class="card">
    <h2 style="margin-top:0">Add a division</h2>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="division">
        <div class="row">
            <div class="field"><label for="course_id">Course</label>
                <select id="course_id" name="course_id" required>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?= (int) $c['id'] ?>"><?= e($c['code']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field"><label for="year">Year</label>
                <select id="year" name="year" required>
                    <?php for ($y = 1; $y <= 4; $y++): ?><option value="<?= $y ?>">Year <?= $y ?></option><?php endfor; ?>
                </select>
            </div>
            <div class="field"><label for="div_name">Division name</label>
                <input type="text" id="div_name" name="div_name" required placeholder="A"></div>
            <div class="field" style="flex:0 0 auto"><button class="btn" type="submit">Add division</button></div>
        </div>
    </form>
</div>

<div class="table-wrap">
<table>
    <thead><tr><th>Course</th><th>Year</th><th>Division</th><th class="num">Students</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($divisions as $d): ?>
        <tr>
            <td><?= e($d['course']) ?></td>
            <td>Year <?= (int) $d['year'] ?></td>
            <td><?= e($d['name']) ?></td>
            <td class="num"><?= (int) $d['student_count'] ?></td>
            <td class="actions">
                <a class="btn btn-danger btn-sm" href="?delete_division=<?= (int) $d['id'] ?>"
                   onclick="return confirm('Remove this division?')">Delete</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
