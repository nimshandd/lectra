<?php
require_once __DIR__ . '/../includes/auth.php';
requireHOD();
$pdo = db();
$departmentId = (int) $_SESSION['user']['department_id'];
$query = $pdo->prepare('SELECT id,batch_name FROM batches WHERE department_id=? ORDER BY batch_name');
$query->execute([$departmentId]);
$batches = $query->fetchAll();
$semesters = $pdo->query('SELECT id,semester_name FROM semesters ORDER BY semester_number')->fetchAll();
$title = 'Eligibility Report';
require __DIR__ . '/../includes/header.php';
?>
<section class="panel eligibility-filters">
    <h2>Attendance eligibility</h2>
    <form id="eligibility-form" class="inline-form" data-api-url="<?= e(baseUrl('/api/hod/eligibility.php')) ?>" novalidate>
        <label>Batch<select name="batch_id" required><option value="">Select batch</option><?php foreach ($batches as $batch): ?><option value="<?= $batch['id'] ?>"><?= e($batch['batch_name']) ?></option><?php endforeach ?></select></label>
        <label>Semester<select name="semester_id" required><option value="">Select semester</option><?php foreach ($semesters as $semester): ?><option value="<?= $semester['id'] ?>"><?= e($semester['semester_name']) ?></option><?php endforeach ?></select></label>
        <label>Eligibility Limit (%)<input type="number" name="eligibility_limit" min="0" max="100" step="0.1" required placeholder="80"></label>
        <button type="submit">Generate Report</button>
        <button type="button" id="export-google-sheet" class="secondary" hidden>Download for Google Sheets</button>
    </form>
    <div id="eligibility-message" class="report-message" role="status" aria-live="polite"></div>
</section>
<section class="panel report-panel" aria-live="polite">
    <div id="eligibility-loading" class="report-state" hidden>Generating report...</div>
    <div id="eligibility-empty" class="report-state">Choose a batch, semester, and eligibility limit to generate the report.</div>
    <div id="eligibility-grid" class="table-wrap eligibility-table-wrap" hidden></div>
</section>
<script src="<?= baseUrl('/assets/js/eligibility-report.js') ?>?v=<?= filemtime(__DIR__ . '/../assets/js/eligibility-report.js') ?>"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
