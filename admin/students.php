<?php
/** Add, edit, search and delete students. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

$editing = null;
$errors  = [];

// ---------- delete ----------
if (($_GET['delete'] ?? '') !== '') {
    $st = $pdo->prepare('SELECT user_id FROM students WHERE id = ?');
    $st->execute([int_input('delete')]);
    if ($uid = $st->fetchColumn()) {
        // Deleting the user removes the student row and their attendance (ON DELETE CASCADE).
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]);
        set_flash('ok', 'Student removed.');
    }
    header('Location: ' . base_url('admin/students.php'));
    exit;
}

// ---------- save ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $id       = int_input('id');
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $roll     = trim($_POST['roll_no'] ?? '');
    $division = int_input('division_id');
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '')  $errors[] = 'Enter the student name.';
    if ($roll === '')  $errors[] = 'Enter a roll number.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (!$division)    $errors[] = 'Choose a division.';
    if (!$id && strlen($password) < 6) $errors[] = 'Set a password of at least 6 characters.';

    // Course and year come from the chosen division, so they can never disagree.
    $div = null;
    if ($division) {
        $st = $pdo->prepare('SELECT * FROM divisions WHERE id = ?');
        $st->execute([$division]);
        $div = $st->fetch();
        if (!$div) $errors[] = 'That division no longer exists.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            if ($id) {
                $st = $pdo->prepare('SELECT user_id FROM students WHERE id = ?');
                $st->execute([$id]);
                $uid = (int) $st->fetchColumn();

                $pdo->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?')
                    ->execute([$name, $email, $uid]);
                if ($password !== '') {
                    $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')
                        ->execute([password_hash($password, PASSWORD_DEFAULT), $uid]);
                }
                $pdo->prepare('UPDATE students SET roll_no = ?, course_id = ?, year = ?, division_id = ?, phone = ? WHERE id = ?')
                    ->execute([$roll, $div['course_id'], $div['year'], $division, $phone, $id]);
                set_flash('ok', 'Student details updated.');
            } else {
                $pdo->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, "student")')
                    ->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
                $uid = (int) $pdo->lastInsertId();
                $pdo->prepare('INSERT INTO students (user_id, roll_no, course_id, year, division_id, phone, admission_date)
                               VALUES (?, ?, ?, ?, ?, ?, CURDATE())')
                    ->execute([$uid, $roll, $div['course_id'], $div['year'], $division, $phone]);
                set_flash('ok', 'Student added.');
            }
            $pdo->commit();
            header('Location: ' . base_url('admin/students.php'));
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = ($e->getCode() === '23000')
                ? 'That email or roll number is already used by another student.'
                : 'Could not save: ' . $e->getMessage();
        }
    }
    $editing = ['id' => $id, 'name' => $name, 'email' => $email, 'roll_no' => $roll,
                'division_id' => $division, 'phone' => $phone];
}

// ---------- load one student for editing ----------
if (($_GET['edit'] ?? '') !== '' && !$editing) {
    $st = $pdo->prepare('SELECT s.*, u.name, u.email FROM students s JOIN users u ON u.id = s.user_id WHERE s.id = ?');
    $st->execute([int_input('edit')]);
    $editing = $st->fetch() ?: null;
}

$divisions = $pdo->query('SELECT d.id, d.year, d.name, c.code FROM divisions d JOIN courses c ON c.id = d.course_id
                          ORDER BY c.code, d.year, d.name')->fetchAll();

// ---------- list with search ----------
$q      = trim($_GET['q'] ?? '');
$filter = int_input('division');
$sql = 'SELECT s.id, s.roll_no, s.year, s.phone, u.name, u.email, c.code AS course, d.name AS division
        FROM students s
        JOIN users u     ON u.id = s.user_id
        JOIN courses c   ON c.id = s.course_id
        JOIN divisions d ON d.id = s.division_id
        WHERE 1 = 1';
$args = [];
if ($q !== '') {
    $sql .= ' AND (u.name LIKE ? OR s.roll_no LIKE ? OR u.email LIKE ?)';
    array_push($args, "%$q%", "%$q%", "%$q%");
}
if ($filter) { $sql .= ' AND s.division_id = ?'; $args[] = $filter; }
$sql .= ' ORDER BY s.roll_no';
$st = $pdo->prepare($sql);
$st->execute($args);
$students = $st->fetchAll();

$page_title = 'Students';
require_once __DIR__ . '/../includes/header.php';
?>
<h1>Students</h1>
<p class="sub"><?= count($students) ?> record<?= count($students) === 1 ? '' : 's' ?> shown.</p>

<?php if ($errors): ?>
    <div class="flash flash-error"><?= e(implode(' ', $errors)) ?></div>
<?php endif; ?>

<div class="card">
    <h2 style="margin-top:0"><?= !empty($editing['id']) ? 'Edit student' : 'Add a student' ?></h2>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= e($editing['id'] ?? '') ?>">
        <div class="row" style="margin-bottom:14px">
            <div class="field">
                <label for="name">Full name</label>
                <input type="text" id="name" name="name" required value="<?= e($editing['name'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="roll_no">Roll number</label>
                <input type="text" id="roll_no" name="roll_no" required value="<?= e($editing['roll_no'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="email">Email (used to sign in)</label>
                <input type="email" id="email" name="email" required value="<?= e($editing['email'] ?? '') ?>">
            </div>
        </div>
        <div class="row">
            <div class="field">
                <label for="division_id">Course, year and division</label>
                <select id="division_id" name="division_id" required>
                    <option value="">Choose one</option>
                    <?php foreach ($divisions as $d): ?>
                        <option value="<?= (int) $d['id'] ?>" <?= (int) ($editing['division_id'] ?? 0) === (int) $d['id'] ? 'selected' : '' ?>>
                            <?= e($d['code'] . ' - Year ' . $d['year'] . ' - Division ' . $d['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="phone">Phone</label>
                <input type="text" id="phone" name="phone" value="<?= e($editing['phone'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="password">Password <?= !empty($editing['id']) ? '(leave blank to keep the current one)' : '' ?></label>
                <input type="password" id="password" name="password" <?= empty($editing['id']) ? 'required' : '' ?>>
            </div>
            <div class="field" style="flex:0 0 auto">
                <button class="btn" type="submit"><?= !empty($editing['id']) ? 'Save changes' : 'Add student' ?></button>
                <?php if (!empty($editing['id'])): ?>
                    <a class="btn btn-line" href="<?= e(base_url('admin/students.php')) ?>">Cancel</a>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<form method="get" class="row" style="margin-bottom:16px">
    <div class="field">
        <label for="q">Search by name, roll number or email</label>
        <input type="text" id="q" name="q" value="<?= e($q) ?>">
    </div>
    <div class="field">
        <label for="division">Division</label>
        <select id="division" name="division">
            <option value="">All divisions</option>
            <?php foreach ($divisions as $d): ?>
                <option value="<?= (int) $d['id'] ?>" <?= $filter === (int) $d['id'] ? 'selected' : '' ?>>
                    <?= e($d['code'] . ' Y' . $d['year'] . ' ' . $d['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field" style="flex:0 0 auto">
        <button class="btn btn-line" type="submit">Search</button>
    </div>
</form>

<?php if (!$students): ?>
    <div class="empty">No students match this search. Add one using the form above.</div>
<?php else: ?>
<div class="table-wrap">
<table>
    <thead><tr><th>Roll no.</th><th>Name</th><th>Email</th><th>Class</th><th>Phone</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($students as $s): ?>
        <tr>
            <td><?= e($s['roll_no']) ?></td>
            <td><?= e($s['name']) ?></td>
            <td><?= e($s['email']) ?></td>
            <td><?= e($s['course'] . ' Y' . $s['year'] . ' - ' . $s['division']) ?></td>
            <td><?= e($s['phone']) ?></td>
            <td class="actions">
                <a class="btn btn-line btn-sm" href="?edit=<?= (int) $s['id'] ?>">Edit</a>
                <a class="btn btn-danger btn-sm" href="?delete=<?= (int) $s['id'] ?>"
                   onclick="return confirm('Remove this student and all their attendance records?')">Delete</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
