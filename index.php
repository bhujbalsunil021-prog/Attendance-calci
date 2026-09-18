<?php
/** Login page - the single entry point for all three roles. */
require_once __DIR__ . '/includes/bootstrap.php';

// Already signed in? Go straight to the right dashboard.
if (is_logged_in()) {
    header('Location: ' . base_url(current_role() . '/dashboard.php'));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $st = $pdo->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1');
    $st->execute([$email]);
    $user = $st->fetch();

    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['name']    = $user['name'];
        $_SESSION['role']    = $user['role'];
        header('Location: ' . base_url($user['role'] . '/dashboard.php'));
        exit;
    }
    $error = 'That email and password do not match an account.';
}

if (($_GET['msg'] ?? '') === 'login_required') $error = 'Sign in to continue.';
if (($_GET['msg'] ?? '') === 'not_allowed')    $error = 'That page belongs to a different role.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in &middot; Attendance</title>
<link rel="stylesheet" href="<?= e(base_url('assets/css/style.css')) ?>">
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <h1>Attendance Management</h1>
        <p class="sub">Sign in with your college account.</p>

        <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

        <form method="post" novalidate>
            <?= csrf_field() ?>
            <div class="field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required autofocus
                       value="<?= e($_POST['email'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button class="btn" type="submit">Sign in</button>
        </form>

        <div class="demo">
            Test accounts, all with the password <code>password123</code>:<br>
            admin@college.edu &middot; deshmukh@college.edu &middot; aarti@student.edu
        </div>
    </div>
</div>
</body>
</html>
