<?php
/** A student's own overall and subject-wise attendance. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('student');
$studentId = current_student_id($pdo);

$st = $pdo->prepare("SELECT s.roll_no, s.year, c.code AS course, c.name AS course_name, d.name AS division
                     FROM students s JOIN courses c ON c.id = s.course_id
                     JOIN divisions d ON d.id = s.division_id WHERE s.id = ?");
$st->execute([$studentId]);
$me = $st->fetch();

$st = $pdo->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(status='P'),0) AS present
                     FROM attendance WHERE student_id = ?");
$st->execute([$studentId]);
$overall = $st->fetch();
$overallPct = percentage((int) $overall['present'], (int) $overall['total']);

$st = $pdo->prepare("
    SELECT sub.name AS subject, sub.code,
           COUNT(a.id) AS total, COALESCE(SUM(a.status='P'),0) AS present
    FROM attendance a
    JOIN subjects sub ON sub.id = a.subject_id
    WHERE a.student_id = ?
    GROUP BY sub.id, sub.name, sub.code
    ORDER BY sub.name");
$st->execute([$studentId]);
$subjects = $st->fetchAll();

// How many more classes must be attended in a row to reach the minimum.
$needed = 0;
$total   = (int) $overall['total'];
$present = (int) $overall['present'];
if ($total > 0 && $overallPct < MIN_ATTENDANCE) {
    $m = MIN_ATTENDANCE / 100;
    $needed = (int) ceil(($m * $total - $present) / (1 - $m));
}

$page_title = 'My attendance';
require_once __DIR__ . '/../includes/header.php';
?>
<h1><?= e($_SESSION['name']) ?></h1>
<p class="sub">Roll no. <?= e($me['roll_no']) ?> &middot; <?= e($me['course'] . ' Year ' . $me['year'] . ', Division ' . $me['division']) ?></p>

<div class="stats">
    <div class="stat">
        <div class="stat-value <?= pct_class($overallPct) ?>"><?= number_format($overallPct, 2) ?>%</div>
        <div class="stat-label">Overall attendance</div>
    </div>
    <div class="stat"><div class="stat-value"><?= $present ?></div><div class="stat-label">Classes attended</div></div>
    <div class="stat"><div class="stat-value"><?= $total ?></div><div class="stat-label">Classes held</div></div>
    <div class="stat"><div class="stat-value"><?= (int) MIN_ATTENDANCE ?>%</div><div class="stat-label">Required minimum</div></div>
</div>

<?php if ($total === 0): ?>
    <div class="empty">No attendance has been recorded for you yet.</div>
<?php elseif ($overallPct < MIN_ATTENDANCE): ?>
    <div class="flash flash-error">
        You are below the required <?= (int) MIN_ATTENDANCE ?>%.
        Attending the next <?= $needed ?> class<?= $needed === 1 ? '' : 'es' ?> without a break brings you back to the minimum.
    </div>
<?php endif; ?>

<h2>Subject by subject</h2>
<?php if (!$subjects): ?>
    <div class="empty">Subject records appear here once your teachers start marking attendance.</div>
<?php else: ?>
<div class="table-wrap">
<table>
    <thead><tr><th>Subject</th><th class="num">Attended</th><th class="num">Held</th><th class="num">Percentage</th><th>Level</th></tr></thead>
    <tbody>
    <?php foreach ($subjects as $s):
        $pct = percentage((int) $s['present'], (int) $s['total']); ?>
        <tr>
            <td><?= e($s['subject']) ?> <span class="sub" style="margin:0">(<?= e($s['code']) ?>)</span></td>
            <td class="num"><?= (int) $s['present'] ?></td>
            <td class="num"><?= (int) $s['total'] ?></td>
            <td class="num <?= pct_class($pct) ?>"><?= number_format($pct, 2) ?>%</td>
            <td><div class="bar"><span style="width:<?= min(100, $pct) ?>%"></span></div></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
