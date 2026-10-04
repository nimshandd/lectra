<?php
require_once __DIR__ . '/../includes/auth.php';
requireHOD();
$pdo = db();
$departmentId = (int) $_SESSION['user']['department_id'];
$batchId = (int) ($_REQUEST['batch_id'] ?? 0);
$semesterId = (int) ($_REQUEST['semester_id'] ?? 0);
$subjectId = (int) ($_REQUEST['subject_id'] ?? 0);
$sessionId = (int) ($_REQUEST['session_id'] ?? 0);
$error = '';
$notice = '';

$query = $pdo->prepare('SELECT id,batch_name FROM batches WHERE department_id=? ORDER BY batch_name');
$query->execute([$departmentId]);
$batches = $query->fetchAll();
if ($batchId) {
    $query = $pdo->prepare('SELECT id FROM batches WHERE id=? AND department_id=?');
    $query->execute([$batchId, $departmentId]);
    if (!$query->fetchColumn()) {
        $batchId = $semesterId = $subjectId = $sessionId = 0;
        $error = 'The selected batch is not available in your department.';
    }
}
$semesters = $pdo->query('SELECT id,semester_name FROM semesters ORDER BY semester_number')->fetchAll();
$subjects = [];
$sessions = [];
if ($batchId && $semesterId) {
    $query = $pdo->prepare("SELECT DISTINCT s.id,s.subject_code,s.subject_name FROM batch_subjects bs JOIN subjects s ON s.id=bs.subject_id WHERE bs.batch_id=? AND bs.semester_id=? AND s.department_id=? ORDER BY s.subject_code");
    $query->execute([$batchId, $semesterId, $departmentId]);
    $subjects = $query->fetchAll();
}
if ($batchId && $semesterId && $subjectId) {
    $query = $pdo->prepare('SELECT a.id,a.attendance_date,a.lecture_number,a.session_type FROM attendance_sessions a JOIN batch_subjects bs ON bs.id=a.batch_subject_id JOIN subjects s ON s.id=bs.subject_id WHERE bs.batch_id=? AND bs.semester_id=? AND bs.subject_id=? AND s.department_id=? ORDER BY a.attendance_date,a.lecture_number');
    $query->execute([$batchId, $semesterId, $subjectId, $departmentId]);
    $sessions = $query->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $password = $_POST['password'] ?? '';
    $confirm = trim($_POST['confirmation'] ?? '');
    if ($confirm !== 'CLEAR') $error = 'Type CLEAR to confirm this permanent action.';
    elseif (!$batchId || !$semesterId || $password === '') $error = 'Select a batch and semester, then enter your password.';
    else {
        $query = $pdo->prepare("SELECT password FROM users WHERE id=? AND department_id=? AND role='hod' AND status='active'");
        $query->execute([$_SESSION['user']['id'], $departmentId]);
        if (!password_verify($password, (string) $query->fetchColumn())) $error = 'The HOD password is incorrect.';
        else {
            $where = 'b.department_id=? AND bs.batch_id=? AND bs.semester_id=?';
            $args = [$departmentId, $batchId, $semesterId];
            if ($subjectId) { $where .= ' AND bs.subject_id=?'; $args[] = $subjectId; }
            if ($sessionId) { $where .= ' AND a.id=?'; $args[] = $sessionId; }
            try {
                $pdo->beginTransaction();
                $count = $pdo->prepare("SELECT COUNT(DISTINCT a.id),COUNT(ar.id) FROM attendance_sessions a JOIN batch_subjects bs ON bs.id=a.batch_subject_id JOIN batches b ON b.id=bs.batch_id LEFT JOIN attendance_records ar ON ar.attendance_session_id=a.id WHERE $where");
                $count->execute($args);
                [$sessionCount, $recordCount] = $count->fetch(PDO::FETCH_NUM);
                $deleteMedical = $pdo->prepare("DELETE ma FROM medical_adjustments ma JOIN attendance_records ar ON ar.id=ma.attendance_record_id JOIN attendance_sessions a ON a.id=ar.attendance_session_id JOIN batch_subjects bs ON bs.id=a.batch_subject_id JOIN batches b ON b.id=bs.batch_id WHERE $where");
                $deleteMedical->execute($args);
                $deleteRecords = $pdo->prepare("DELETE ar FROM attendance_records ar JOIN attendance_sessions a ON a.id=ar.attendance_session_id JOIN batch_subjects bs ON bs.id=a.batch_subject_id JOIN batches b ON b.id=bs.batch_id WHERE $where");
                $deleteRecords->execute($args);
                $deleteSessions = $pdo->prepare("DELETE a FROM attendance_sessions a JOIN batch_subjects bs ON bs.id=a.batch_subject_id JOIN batches b ON b.id=bs.batch_id WHERE $where");
                $deleteSessions->execute($args);
                $pdo->commit();
                $notice = "Cleared $recordCount attendance records and $sessionCount sessions.";
                $sessionId = 0;
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'The attendance records could not be cleared.';
            }
        }
    }
}

$where = 'b.department_id=? AND bs.batch_id=? AND bs.semester_id=?';
$args = [$departmentId, $batchId, $semesterId];
if ($subjectId) { $where .= ' AND bs.subject_id=?'; $args[] = $subjectId; }
if ($sessionId) { $where .= ' AND a.id=?'; $args[] = $sessionId; }
$sessionCount = $recordCount = 0;
if ($batchId && $semesterId) {
    $count = $pdo->prepare("SELECT COUNT(DISTINCT a.id),COUNT(ar.id) FROM attendance_sessions a JOIN batch_subjects bs ON bs.id=a.batch_subject_id JOIN batches b ON b.id=bs.batch_id LEFT JOIN attendance_records ar ON ar.attendance_session_id=a.id WHERE $where");
    $count->execute($args);
    [$sessionCount, $recordCount] = $count->fetch(PDO::FETCH_NUM);
}
$title = 'Clear Attendance Records';
require __DIR__ . '/../includes/header.php';
?>
<section class="panel">
    <h2>Clear attendance records</h2>
    <p>Filter by batch, semester, subject, or one session. Only the matching attendance data will be deleted.</p>
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif ?>
    <?php if ($notice): ?><div class="alert success"><?= e($notice) ?></div><?php endif ?>
    <form method="get" class="inline-form">
        <label>Batch<select name="batch_id" required><option value="">Select batch</option><?php foreach ($batches as $batch): ?><option value="<?= $batch['id'] ?>" <?= $batchId === (int) $batch['id'] ? 'selected' : '' ?>><?= e($batch['batch_name']) ?></option><?php endforeach ?></select></label>
        <label>Semester<select name="semester_id" required><option value="">Select semester</option><?php foreach ($semesters as $semester): ?><option value="<?= $semester['id'] ?>" <?= $semesterId === (int) $semester['id'] ? 'selected' : '' ?>><?= e($semester['semester_name']) ?></option><?php endforeach ?></select></label>
        <?php if ($batchId && $semesterId): ?><label>Subject<select name="subject_id"><option value="">All subjects</option><?php foreach ($subjects as $subject): ?><option value="<?= $subject['id'] ?>" <?= $subjectId === (int) $subject['id'] ? 'selected' : '' ?>><?= e($subject['subject_code'] . ' - ' . $subject['subject_name']) ?></option><?php endforeach ?></select></label><?php endif ?>
        <?php if ($sessions): ?><label>Specific Session<select name="session_id"><option value="">All matching sessions</option><?php foreach ($sessions as $session): ?><option value="<?= $session['id'] ?>" <?= $sessionId === (int) $session['id'] ? 'selected' : '' ?>><?= e($session['attendance_date'] . ' / Lecture ' . $session['lecture_number'] . ' / ' . ucfirst($session['session_type'])) ?></option><?php endforeach ?></select></label><?php endif ?>
        <button type="submit">Filter Records</button>
    </form>
</section>
<?php if ($batchId && $semesterId): ?>
<section class="panel clear-danger-zone">
    <h2>Selected records</h2>
    <p><?= e((string) $sessionCount) ?> attendance session(s) and <?= e((string) $recordCount) ?> attendance record(s) will be deleted.</p>
    <?php if ($sessionCount): ?><form method="post" onsubmit="return confirm('This permanently deletes the selected attendance data. Continue?')"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="batch_id" value="<?= $batchId ?>"><input type="hidden" name="semester_id" value="<?= $semesterId ?>"><input type="hidden" name="subject_id" value="<?= $subjectId ?>"><input type="hidden" name="session_id" value="<?= $sessionId ?>"><label>HOD Password<input type="password" name="password" required autocomplete="current-password"></label><label>Type CLEAR to confirm<input type="text" name="confirmation" required pattern="CLEAR" autocomplete="off"></label><button type="submit" class="button danger">Clear Selected Records Permanently</button></form><?php else: ?><p>No attendance sessions match these filters.</p><?php endif ?>
</section>
<?php endif; require __DIR__ . '/../includes/footer.php'; ?>
