<?php
require_once __DIR__ . '/../includes/auth.php';
requireLecturer();
$pdo = db();
$userId = $_SESSION['user']['id'];
$classesQuery = $pdo->prepare('SELECT bs.id,b.batch_code,s.subject_code,s.subject_name,se.semester_name FROM batch_subjects bs JOIN batches b ON b.id=bs.batch_id JOIN subjects s ON s.id=bs.subject_id JOIN semesters se ON se.id=bs.semester_id WHERE bs.lecturer_id=? AND b.status="active" ORDER BY b.batch_code,s.subject_code');
$classesQuery->execute([$userId]);
$classes = $classesQuery->fetchAll();
$classId = (int) ($_REQUEST['class_id'] ?? 0);
$class = null;
foreach ($classes as $item) if ((int) $item['id'] === $classId) $class = $item;
$mode = $_POST['mode'] ?? '';
$error = '';
$nextNumber = 1;
if ($class) {
    $numberQuery = $pdo->prepare('SELECT COALESCE(MAX(lecture_number),0)+1 FROM attendance_sessions WHERE batch_subject_id=?');
    $numberQuery->execute([$classId]);
    $nextNumber = (int) $numberQuery->fetchColumn();
}
if ($mode === 'save' && $class) {
    verifyCsrf();
    $type = ($_POST['session_type'] ?? '') === 'practical' ? 'practical' : 'lecture';
    $date = $_POST['attendance_date'] ?? '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $error = 'Choose a valid attendance date.';
    else try {
        $pdo->beginTransaction();
        $numberQuery = $pdo->prepare('SELECT COALESCE(MAX(lecture_number),0)+1 FROM attendance_sessions WHERE batch_subject_id=? FOR UPDATE');
        $numberQuery->execute([$classId]);
        $number = (int) $numberQuery->fetchColumn();
        $pdo->prepare('INSERT INTO attendance_sessions(batch_subject_id,lecture_number,session_type,attendance_date,created_by) VALUES(?,?,?,?,?)')->execute([$classId,$number,$type,$date,$userId]);
        $sessionId = $pdo->lastInsertId();
        $studentsQuery = $pdo->prepare("SELECT id FROM students WHERE batch_id=(SELECT batch_id FROM batch_subjects WHERE id=?) AND status='active'");
        $studentsQuery->execute([$classId]);
        $recordQuery = $pdo->prepare('INSERT INTO attendance_records(attendance_session_id,student_id,status) VALUES(?,?,?)');
        foreach ($studentsQuery as $student) $recordQuery->execute([$sessionId,$student['id'],($_POST['status'][$student['id']] ?? 'present') === 'absent' ? 'absent' : 'present']);
        $pdo->commit();
        flash('Attendance saved successfully.');
        redirect('/lecturer/history.php');
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = 'Attendance could not be saved. Please try again.';
        $mode = 'sheet';
    }
}
$title = 'Take Attendance';
require __DIR__ . '/../includes/header.php';
if (!$class || $mode === ''):
?>
<section class="panel"><h2>Attendance Selection</h2><form method="post">
<input type="hidden" name="mode" value="sheet">
<label>Assigned Class<select name="class_id" required><?php foreach ($classes as $item): ?><option value="<?= $item['id'] ?>" <?= $classId === (int) $item['id'] ? 'selected' : '' ?>><?= e($item['batch_code'] . ' / ' . $item['semester_name'] . ' / ' . $item['subject_code']) ?></option><?php endforeach ?></select></label>
<label>Attendance Date<input type="date" name="attendance_date" value="<?= date('Y-m-d') ?>" required></label>
<label>Next Lecture Number<input value="<?= $nextNumber ?>" readonly aria-describedby="lecture-number-help"></label>
<small id="lecture-number-help">The number is assigned automatically from previous sessions.</small>
<label>Session Type<select name="session_type"><option value="lecture">Lecture</option><option value="practical">Practical</option></select></label>
<button>Continue</button></form></section>
<?php else:
    $number = $nextNumber;
    $type = ($_POST['session_type'] ?? '') === 'practical' ? 'practical' : 'lecture';
    $date = $_POST['attendance_date'];
    $studentsQuery = $pdo->prepare("SELECT * FROM students WHERE batch_id=(SELECT batch_id FROM batch_subjects WHERE id=?) AND status='active' ORDER BY registration_number");
    $studentsQuery->execute([$classId]);
?>
<section class="panel"><h2><?= e($class['subject_name']) ?></h2><p><?= e($class['batch_code']) ?> / <?= e($class['semester_name']) ?> / <?= e($date) ?> / Lecture <?= str_pad((string) $number, 2, '0', STR_PAD_LEFT) ?> / <?= e(ucfirst($type)) ?></p>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif ?><button type="button" data-mark-all>Mark All Present</button>
<form method="post" class="confirm-save"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="mode" value="save"><input type="hidden" name="class_id" value="<?= $classId ?>"><input type="hidden" name="lecture_number" value="<?= $number ?>"><input type="hidden" name="session_type" value="<?= e($type) ?>"><input type="hidden" name="attendance_date" value="<?= e($date) ?>">
<div class="table-wrap"><table><tr><th>Registration</th><th>Name</th><th>Attendance</th></tr><?php foreach ($studentsQuery as $student): ?><tr><td><?= e($student['registration_number']) ?></td><td><?= e($student['student_name'] ?: 'Name not entered') ?></td><td><input type="hidden" name="status[<?= $student['id'] ?>]" value="present"><button type="button" class="attendance-toggle is-present" data-attendance-toggle aria-pressed="false">Present</button></td></tr><?php endforeach ?></table></div><p><button>Save Attendance</button></p></form></section>
<?php endif; require __DIR__ . '/../includes/footer.php'; ?>
