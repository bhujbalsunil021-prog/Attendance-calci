<?php
/** Add, edit and remove teachers. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

$editing = null;
$errors  = [];

if (($_GET['delete'] ?? '') !== '') {
    $st = $pdo->prepare('SELECT user_id FROM teachers WHERE id = ?');
    $st->execute([int_input('delete')]);
    if ($uid = $st->fetchColumn()) {
        try {
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]);
            set_flash('ok', 'Teacher removed.');
        } catch (PDOException $e) {
            set_flash('error', 'This teacher has already marked attendance, so the record cannot be deleted.');
        }
    }
    header('Location: ' . base_url('admin/teachers.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $id       = int_input('id');
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $emp      = trim($_POST['emp_code'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '') $errors[] = 'Enter the teacher name.';
    if ($emp === '')  $errors[] = 'Enter an employee code.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (!$id && strlen($password) < 6) $errors[] = 'Set a password of at least 6 characters.';

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            if ($id) {
                $st = $pdo->prepare('SELECT user_id FROM teachers WHERE id = ?');
                $st->execute([$id]);
                $uid = (int) $st->fetchColumn();
                $pdo->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?')->execute([$name, $email, $uid]);
                if ($password !== '') {
                    $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')
                        ->execute([password_hash($password, PASSWORD_DEFAULT), $uid]);
                }
                $pdo->prepare('UPDATE teachers SET emp_code = ?, phone = ? WHERE id = ?')->execute([$emp, $phone, $id]);
                set_flash('ok', 'Teacher details updated.');
            } else {
                $pdo->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, "teacher")')
                    ->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
                $pdo->prepare('INSERT INTO teachers (user_id, emp_code, phone) VALUES (?, ?, ?)')
                    ->execute([(int) $pdo->lastInsertId(), $emp, $phone]);
                set_flash('ok', 'Teacher added.');
            }
            $pdo->commit();
            header('Location: ' . base_url('admin/teachers.php'));
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = ($e->getCode() === '23000')
                ? 'That email or employee code is already in use.'
                : 'Could not save: ' . $e->getMessage();
        }
    }
    $editing = ['id' => $id, 'name' => $name, 'email' => $email, 'emp_code' => $emp, 'phone' => $phone];
}

if (($_GET['edit'] ?? '') !== '' && !$editing) {
    $st = $pdo->prepare('SELECT t.*, u.name, u.email FROM teachers t JOIN users u ON u.id = t.user_id WHERE t.id = ?');
    $st->execute([int_input('edit')]);
    $editing = $st->fetch() ?: null;
}

$teachers = $pdo->query("
    SELECT t.id, t.emp_code, t.phone, u.name, u.email,
           (SELECT COUNT(*) FROM teacher_subjects ts WHERE ts.teacher_id = t.id) AS assignments
    FROM teachers t JOIN users u ON u.id = t.user_id
    ORDER BY u.name")->fetchAll();

$page_title = 'Teachers';
require_once __DIR__ . '/../includes/header.php';
?>
<h1>Teachers</h1>
<p class="sub">Accounts that can mark attendance for their assigned subjects.</p>

<?php if ($errors): ?><div class="flash flash-error"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>

<div class="card">
    <h2 style="margin-top:0"><?= !empty($editing['id']) ? 'Edit teacher' : 'Add a teacher' ?></h2>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= e($editing['id'] ?? '') ?>">
        <div class="row" style="margin-bottom:14px">
            <div class="field">
                <label for="name">Full name</label>
                <input type="text" id="name" name="name" required value="<?= e($editing['name'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="emp_code">Employee code</label>
                <input type="text" id="emp_code" name="emp_code" required value="<?= e($editing['emp_code'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="email">Email (used to sign in)</label>
                <input type="email" id="email" name="email" required value="<?= e($editing['email'] ?? '') ?>">
            </div>
        </div>
        <div class="row">
            <div class="field">
                <label for="phone">Phone</label>
                <input type="text" id="phone" name="phone" value="<?= e($editing['phone'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="password">Password <?= !empty($editing['id']) ? '(leave blank to keep the current one)' : '' ?></label>
                <input type="password" id="password" name="password" <?= empty($editing['id']) ? 'required' : '' ?>>
            </div>
            <div class="field" style="flex:0 0 auto">
                <button class="btn" type="submit"><?= !empty($editing['id']) ? 'Save changes' : 'Add teacher' ?></button>
                <?php if (!empty($editing['id'])): ?>
                    <a class="btn btn-line" href="<?= e(base_url('admin/teachers.php')) ?>">Cancel</a>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<?php if (!$teachers): ?>
    <div class="empty">No teachers yet. Add the first one above.</div>
<?php else: ?>
<div class="table-wrap">
<table>
    <thead><tr><th>Code</th><th>Name</th><th>Email</th><th>Phone</th><th class="num">Subjects taught</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($teachers as $t): ?>
        <tr>
            <td><?= e($t['emp_code']) ?></td>
            <td><?= e($t['name']) ?></td>
            <td><?= e($t['email']) ?></td>
            <td><?= e($t['phone']) ?></td>
            <td class="num"><?= (int) $t['assignments'] ?></td>
            <td class="actions">
                <a class="btn btn-line btn-sm" href="?edit=<?= (int) $t['id'] ?>">Edit</a>
                <a class="btn btn-danger btn-sm" href="?delete=<?= (int) $t['id'] ?>"
                   onclick="return confirm('Remove this teacher?')">Delete</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
