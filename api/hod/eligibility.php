<?php
require_once __DIR__ . '/../../includes/auth.php';
requireHOD();
header('Content-Type: application/json; charset=utf-8');

function reportError(string $message, int $status = 400): never
{
    http_response_code($status);
    echo json_encode(['error' => $message]);
    exit;
}

$batchId = filter_input(INPUT_GET, 'batch_id', FILTER_VALIDATE_INT);
$semesterId = filter_input(INPUT_GET, 'semester_id', FILTER_VALIDATE_INT);
$limit = filter_input(INPUT_GET, 'eligibility_limit', FILTER_VALIDATE_FLOAT);
if (!$batchId || !$semesterId || $limit === false || $limit === null || $limit < 0 || $limit > 100) {
    reportError('Choose a batch and semester, and enter an eligibility limit between 0 and 100.');
}

$pdo = db();
$departmentId = (int) $_SESSION['user']['department_id'];
$check = $pdo->prepare('SELECT id FROM batches WHERE id=? AND department_id=?');
$check->execute([$batchId, $departmentId]);
if (!$check->fetchColumn()) reportError('The selected batch is not available.', 403);

$studentsQuery = $pdo->prepare("SELECT id,registration_number,student_name FROM students WHERE batch_id=? AND status='active' ORDER BY registration_number");
$studentsQuery->execute([$batchId]);
$students = $studentsQuery->fetchAll();
$subjectsQuery = $pdo->prepare("SELECT bs.subject_id id,s.subject_code,s.subject_name FROM batch_subjects bs JOIN subjects s ON s.id=bs.subject_id AND s.status='active' WHERE bs.batch_id=? AND bs.semester_id=? AND s.department_id=? ORDER BY s.subject_code");
$subjectsQuery->execute([$batchId, $semesterId, $departmentId]);
$subjects = $subjectsQuery->fetchAll();

$totals = [];
$hasAttendanceData = false;
if ($students && $subjects) {
    $attendanceQuery = $pdo->prepare("SELECT st.id student_id,bs.subject_id,COUNT(DISTINCT a.id) total_sessions,COALESCE(SUM(CASE WHEN ar.status IN ('present','medical') THEN 1 ELSE 0 END),0) attended_sessions FROM students st JOIN batch_subjects bs ON bs.batch_id=st.batch_id AND bs.semester_id=? JOIN subjects s ON s.id=bs.subject_id AND s.status='active' AND s.department_id=? LEFT JOIN attendance_sessions a ON a.batch_subject_id=bs.id LEFT JOIN attendance_records ar ON ar.attendance_session_id=a.id AND ar.student_id=st.id WHERE st.batch_id=? AND st.status='active' GROUP BY st.id,bs.subject_id");
    $attendanceQuery->execute([$semesterId, $departmentId, $batchId]);
    foreach ($attendanceQuery as $row) {
        $hasAttendanceData = $hasAttendanceData || (int) $row['total_sessions'] > 0;
        $totals[$row['student_id']][$row['subject_id']] = [(int) $row['total_sessions'], (int) $row['attended_sessions']];
    }
}

foreach ($students as &$student) {
    $student['name'] = $student['student_name'] ?: 'Name not entered';
    unset($student['student_name']);
    $student['attendance'] = [];
    foreach ($subjects as $subject) {
        [$total, $attended] = $totals[$student['id']][$subject['id']] ?? [0, 0];
        $student['attendance'][(string) $subject['id']] = $total ? round(100 * $attended / $total, 1) : null;
    }
}
unset($student);
echo json_encode(['limit' => (float) $limit, 'has_attendance_data' => $hasAttendanceData, 'students' => $students, 'subjects' => $subjects]);