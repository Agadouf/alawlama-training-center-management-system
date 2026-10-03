<?php
session_start();

require_once '../../config/database.php';
require_once '../../includes/language.php';

$currentLang = $lang ?? 'en';
$currentDir = $dir ?? ($currentLang === 'ar' ? 'rtl' : 'ltr');

// ======================================================
// AUTHENTICATION
// ======================================================

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ../auth/login.php');
    exit;
}

$role = strtolower(trim((string) $_SESSION['role']));

if (!in_array($role, ['admin', 'staff', 'instructor'], true)) {
    http_response_code(403);
    die($currentLang === 'ar' ? 'غير مصرح لك بالوصول.' : 'Access denied.');
}

// ======================================================
// GET BATCH ID
// ======================================================

$batchId = isset($_GET['batch_id'])
    ? (int) $_GET['batch_id']
    : 0;

// Optional student filter for individual result printing.
$studentId = isset($_GET['student_id'])
    ? (int) $_GET['student_id']
    : 0;

// If no batch is selected, load available batches and show the selector
// using the same report design. This avoids breaking the existing layout.
$availableBatches = [];

if ($batchId <= 0) {
    if ($role === 'instructor') {
        $userId = (int) $_SESSION['user_id'];

        $stmt = $pdo->prepare("
            SELECT DISTINCT
                b.id,
                b.batch_code,
                b.start_date,
                b.end_date,
                c.course_name
            FROM batches b
            INNER JOIN courses c ON c.id = b.course_id
            INNER JOIN batch_instructors bi ON bi.batch_id = b.id
            INNER JOIN instructors i ON i.id = bi.instructor_id
            WHERE i.user_id = ?
            ORDER BY b.start_date DESC, b.id DESC
        ");
        $stmt->execute([$userId]);
        $availableBatches = $stmt->fetchAll();
    } else {
        $stmt = $pdo->query("
            SELECT
                b.id,
                b.batch_code,
                b.start_date,
                b.end_date,
                c.course_name
            FROM batches b
            INNER JOIN courses c ON c.id = b.course_id
            ORDER BY b.start_date DESC, b.id DESC
        ");
        $availableBatches = $stmt->fetchAll();
    }
}

// ======================================================
// ======================================================
// BATCH SELECTION PAGE
// ======================================================

if ($batchId <= 0):
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentLang) ?>" dir="<?= htmlspecialchars($currentDir) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(__('grades_results')) ?></title>
    <style>
*{box-sizing:border-box}
body{margin:0;padding:0;font-family:Arial,Helvetica,sans-serif;color:#172033;background:#f4f7fb;direction:<?= $currentDir ?>}
.report{max-width:1180px;margin:0 auto}
.topbar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:20px}
.brand{font-size:13px;font-weight:800;letter-spacing:.08em;color:#2563eb;text-transform:uppercase}
.actions{display:flex;gap:8px;flex-wrap:wrap}
.btn{display:inline-flex;align-items:center;justify-content:center;min-height:40px;padding:9px 14px;border:1px solid #d8e0ec;border-radius:9px;background:#fff;color:#243047;text-decoration:none;font-size:13px;font-weight:700;transition:.18s}
.btn:hover{transform:translateY(-1px);box-shadow:0 6px 16px rgba(15,23,42,.08)}
.btn-primary{background:#2563eb;border-color:#2563eb;color:#fff}
.lang-active{background:#eef4ff;border-color:#b8ccff;color:#1d4ed8}
.hero{background:linear-gradient(135deg,#0f172a,#1e3a8a);color:#fff;border-radius:18px;padding:30px;margin-bottom:20px;box-shadow:0 14px 35px rgba(15,23,42,.14)}
.hero h1{margin:0 0 7px;font-size:29px}.hero p{margin:0;color:#dbeafe;font-size:14px}
.selection-box{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:24px;box-shadow:0 8px 24px rgba(15,23,42,.05)}
.selection-box h3{margin:0 0 5px;font-size:18px}.selection-note{margin:0 0 20px;color:#64748b;font-size:13px}
.batch-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px}
.batch-card{display:block;padding:20px;border:1px solid #e2e8f0;border-radius:13px;color:#172033;background:#fff;transition:.18s}
.batch-card:hover{border-color:#93b4ff;box-shadow:0 10px 24px rgba(37,99,235,.10);transform:translateY(-2px)}
.batch-code{font-weight:800;color:#1d4ed8;font-size:18px;margin-bottom:8px}.batch-course{font-weight:700;font-size:15px;margin-bottom:9px}.batch-dates{font-size:12px;color:#64748b}
.batch-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:16px}.batch-actions .btn{min-height:36px;padding:8px 11px}
.empty{text-align:center;padding:55px 20px;color:#64748b}.empty-icon{font-size:42px;margin-bottom:10px}
@media(max-width:700px){body{padding:0}.hero{padding:24px 20px}.selection-box{padding:17px}.topbar .actions{width:100%}.topbar .actions .btn{flex:1}}
</style>
</head>
<body>

<?php require_once '../../includes/sidebar.php'; ?>
<style>
    .main-content { padding: 30px 34px; }
    .report { width: 100%; }

    /* Results page: only Grades / Results should be highlighted. */
    .sidebar a[href="../assessments/index.php"].active {
        background: transparent !important;
        color: #d1d5db !important;
    }

    .sidebar a[href="../assessments/results.php"].active {
        background: #2563eb !important;
        color: #fff !important;
    }
    @media (max-width: 768px) {
        .main-content { padding: 22px 20px; }
    }
    @media print {
        .sidebar { display:none !important; }
        .main-content { margin:0 !important; width:100% !important; padding:0 !important; }
    }
</style>

<div class="report">
    <div class="topbar">
        <div class="brand"><?= $currentLang === 'ar' ? 'معهد العلماء السودان' : 'Al Ulama Sudan Institute' ?></div>
        <div class="actions">
            <a class="btn" href="../dashboard/index.php">← <?= $currentLang === 'ar' ? 'العودة إلى لوحة التحكم' : 'Back to Dashboard' ?></a>
            <!-- Language switcher is provided by the sidebar. -->
        </div>
    </div>
    <div class="hero">
        <h1><?= $currentLang === 'ar' ? 'الدرجات والنتائج' : 'Grades & Results' ?></h1>
        <p><?= $currentLang === 'ar' ? 'اختر الدفعة لعرض النتائج وإدارة الدرجات.' : 'Select a batch to view results and manage grades.' ?></p>
    </div>
    <div class="selection-box">
        <h3><?= $currentLang === 'ar' ? 'اختر الدفعة' : 'Select Batch' ?></h3>
        <p class="selection-note"><?= $currentLang === 'ar' ? 'اختر إحدى الدفعات المتاحة.' : 'Choose one of the available batches.' ?></p>

        <?php if (!empty($availableBatches)): ?>
            <div class="batch-grid">
                <?php foreach ($availableBatches as $availableBatch): ?>
                    <div class="batch-card">
                        <div class="batch-code">
                            <?= htmlspecialchars($availableBatch['batch_code']) ?>
                        </div>
                        <div class="batch-course">
                            <?= htmlspecialchars($availableBatch['course_name']) ?>
                        </div>
                        <div class="batch-dates">
                            <?= htmlspecialchars($availableBatch['start_date']) ?>
                            —
                            <?= htmlspecialchars($availableBatch['end_date']) ?>
                        </div>
                        <div class="batch-actions">
                            <a class="btn btn-primary" href="?batch_id=<?= (int)$availableBatch['id'] ?>">
                                👁 <?= $currentLang === 'ar' ? 'عرض النتائج' : 'View Results' ?>
                            </a>
                            <?php if ($role === 'instructor'): ?>
                                <a class="btn" href="../grades/index.php?batch_id=<?= (int)$availableBatch['id'] ?>">
                                    ✎ <?= $currentLang === 'ar' ? 'إضافة / تعديل الدرجات' : 'Add / Edit Grades' ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty">
                <?= htmlspecialchars(__('no_batches_found')) ?>
            </div>
        <?php endif; ?>
    </div>
</div>
</div>
</body>
</html>
<?php
exit;
endif;

// ======================================================
// VERIFY BATCH ACCESS
// ======================================================

if ($role === 'instructor') {

    $userId = (int) $_SESSION['user_id'];

    $stmt = $pdo->prepare("
        SELECT
            i.id AS instructor_id,
            b.id,
            b.batch_code,
            b.start_date,
            b.end_date,
            c.course_name
        FROM batches b
        INNER JOIN courses c
            ON c.id = b.course_id
        INNER JOIN batch_instructors bi
            ON bi.batch_id = b.id
        INNER JOIN instructors i
            ON i.id = bi.instructor_id
        WHERE b.id = ?
          AND i.user_id = ?
        LIMIT 1
    ");

    $stmt->execute([$batchId, $userId]);
    $batch = $stmt->fetch();

    if (!$batch) {
        http_response_code(403);
        die($currentLang === 'ar' ? 'أنت غير مسند إلى هذه الدفعة.' : 'You are not assigned to this batch.');
    }

} else {

    $stmt = $pdo->prepare("
        SELECT
            b.id,
            b.batch_code,
            b.start_date,
            b.end_date,
            c.course_name
        FROM batches b
        INNER JOIN courses c
            ON c.id = b.course_id
        WHERE b.id = ?
        LIMIT 1
    ");

    $stmt->execute([$batchId]);
    $batch = $stmt->fetch();

    if (!$batch) {
        http_response_code(404);
        die($currentLang === 'ar' ? 'الدفعة غير موجودة.' : 'Batch not found.');
    }
}

// ======================================================
// GET STUDENTS
// ======================================================


// ======================================================

$studentParams = [$batchId];
$studentFilter = '';

if ($studentId > 0) {
    $studentFilter = ' AND s.id = ?';
    $studentParams[] = $studentId;
}

$stmt = $pdo->prepare("
    SELECT
        e.id AS enrollment_id,
        s.id AS student_id,
        s.student_code,
        s.full_name
    FROM enrollments e
    INNER JOIN students s
        ON s.id = e.student_id
    WHERE e.batch_id = ?
      AND e.status != 'cancelled'
      $studentFilter
    ORDER BY s.full_name ASC
");

$stmt->execute($studentParams);

$students = $stmt->fetchAll();

// ======================================================
// GET ASSESSMENTS
// ======================================================

$stmt = $pdo->prepare("
    SELECT
        id,
        title,
        type,
        max_score
    FROM assessments
    WHERE batch_id = ?
      AND status = 'active'
    ORDER BY
        CASE
            WHEN type = 'assignment' THEN 1
            WHEN type = 'midterm' THEN 2
            WHEN type = 'final' THEN 3
        END,
        id ASC
");

$stmt->execute([$batchId]);

$assessments = $stmt->fetchAll();

// ======================================================
// SEPARATE ASSESSMENTS
// ======================================================

$assignmentAssessments = [];
$midterm = null;
$finalExam = null;

foreach ($assessments as $assessment) {

    if ($assessment['type'] === 'assignment') {
        $assignmentAssessments[] = $assessment;
    }

    if ($assessment['type'] === 'midterm') {
        $midterm = $assessment;
    }

    if ($assessment['type'] === 'final') {
        $finalExam = $assessment;
    }
}

// ======================================================
// GET SCORES
// ======================================================

$stmt = $pdo->prepare("
    SELECT
        assessment_id,
        enrollment_id,
        score
    FROM assessment_scores
    WHERE assessment_id IN (
        SELECT id
        FROM assessments
        WHERE batch_id = ?
    )
");

$stmt->execute([$batchId]);

$scoreRows = $stmt->fetchAll();

// ======================================================
// SCORE LOOKUP
// ======================================================

$scores = [];

foreach ($scoreRows as $row) {

    $scores[
        (int) $row['enrollment_id']
    ][
        (int) $row['assessment_id']
    ] = $row['score'];
}

// ======================================================
// CALCULATE RESULTS
// ======================================================

$results = [];

foreach ($students as $student) {

    $enrollmentId =
        (int) $student['enrollment_id'];

    // --------------------------------------------------
    // ASSIGNMENTS
    // --------------------------------------------------

    $assignmentPercentages = [];

    foreach ($assignmentAssessments as $assignment) {

        $assessmentId =
            (int) $assignment['id'];

        $maxScore =
            (float) $assignment['max_score'];

        $score =
            $scores[$enrollmentId][$assessmentId]
            ?? null;

        if (
            $score !== null &&
            $maxScore > 0
        ) {

            $assignmentPercentages[] =
                ((float) $score / $maxScore) * 100;
        }
    }

    if (!empty($assignmentPercentages)) {

        $assignmentAverage =
            array_sum($assignmentPercentages)
            / count($assignmentPercentages);

    } else {

        $assignmentAverage = null;
    }

    // --------------------------------------------------
    // MIDTERM
    // --------------------------------------------------

    $midtermPercentage = null;

    if ($midterm) {

        $assessmentId =
            (int) $midterm['id'];

        $maxScore =
            (float) $midterm['max_score'];

        $score =
            $scores[$enrollmentId][$assessmentId]
            ?? null;

        if (
            $score !== null &&
            $maxScore > 0
        ) {

            $midtermPercentage =
                ((float) $score / $maxScore) * 100;
        }
    }

    // --------------------------------------------------
    // FINAL
    // --------------------------------------------------

    $finalPercentage = null;

    if ($finalExam) {

        $assessmentId =
            (int) $finalExam['id'];

        $maxScore =
            (float) $finalExam['max_score'];

        $score =
            $scores[$enrollmentId][$assessmentId]
            ?? null;

        if (
            $score !== null &&
            $maxScore > 0
        ) {

            $finalPercentage =
                ((float) $score / $maxScore) * 100;
        }
    }

    // --------------------------------------------------
    // FINAL SCORE
    //
    // Assignment = 20%
    // Midterm    = 35%
    // Final      = 45%
    // --------------------------------------------------

    $finalScore = null;

    if (
        $assignmentAverage !== null &&
        $midtermPercentage !== null &&
        $finalPercentage !== null
    ) {

        $finalScore =
            ($assignmentAverage * 0.20)
            +
            ($midtermPercentage * 0.35)
            +
            ($finalPercentage * 0.45);
    }

    // --------------------------------------------------
    // GRADE
    // --------------------------------------------------

    $grade = '-';

    if ($finalScore !== null) {

        if ($finalScore >= 90) {
            $grade = 'A';

        } elseif ($finalScore >= 80) {
            $grade = 'B';

        } elseif ($finalScore >= 70) {
            $grade = 'C';

        } elseif ($finalScore >= 60) {
            $grade = 'D';

        } else {
            $grade = 'F';
        }
    }

    $results[] = [
        'enrollment_id' => $enrollmentId,
        'student_id' => (int) $student['student_id'],
        'student_code' => $student['student_code'],
        'full_name' => $student['full_name'],
        'assignment_average' => $assignmentAverage,
        'midterm_percentage' => $midtermPercentage,
        'final_percentage' => $finalPercentage,
        'final_score' => $finalScore,
        'grade' => $grade
    ];
}

?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentLang) ?>" dir="<?= htmlspecialchars($currentDir) ?>">

<head>

    <meta charset="UTF-8">

    <title>
        <?= htmlspecialchars(__('batch_final_results')) ?> -
        <?= htmlspecialchars($batch['batch_code']) ?>
    </title>

    <style>
*{box-sizing:border-box}
body{margin:0;padding:0;font-family:Arial,Helvetica,sans-serif;color:#172033;background:#f4f7fb;direction:<?= $currentDir ?>}
.report{max-width:1200px;margin:0 auto}
.report-topbar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:20px}
.report-actions{display:flex;gap:8px;flex-wrap:wrap}
.action-btn{display:inline-flex;align-items:center;justify-content:center;min-height:40px;padding:9px 14px;border:1px solid #d8e0ec;border-radius:9px;background:#fff;color:#243047;text-decoration:none;font-size:13px;font-weight:700;cursor:pointer;transition:.18s}
.action-btn:hover{transform:translateY(-1px);box-shadow:0 6px 16px rgba(15,23,42,.08)}
.action-primary{background:#2563eb;border-color:#2563eb;color:#fff}.lang-active{background:#eef4ff;border-color:#b8ccff;color:#1d4ed8}
.report-header{background:linear-gradient(135deg,#0f172a,#1e3a8a);color:#fff;border-radius:18px;padding:28px;margin-bottom:18px;box-shadow:0 14px 35px rgba(15,23,42,.14);text-align:center}
.report-header h1{margin:0 0 7px;font-size:27px;text-align:center}.report-header h2{margin:0 0 7px;font-size:17px;color:#dbeafe;text-align:center}.report-header p{margin:0;color:#bfdbfe;font-size:13px;text-align:center}
.batch-info{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:18px}.info-item{background:#fff;border:1px solid #e2e8f0;border-radius:11px;padding:10px 13px;font-size:12px;box-shadow:0 4px 12px rgba(15,23,42,.04)}.info-item strong{color:#475569;margin-inline-end:5px}
.table-card{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:16px;box-shadow:0 8px 24px rgba(15,23,42,.05)}.table-wrapper{width:100%;overflow-x:auto}
table{width:100%;min-width:900px;border-collapse:separate;border-spacing:0}th,td{padding:13px 11px;font-size:12px;border-bottom:1px solid #edf1f6}th{background:#f8fafc;color:#475569;font-weight:800;text-align:center;white-space:nowrap}td{text-align:center;color:#334155}tbody tr:hover{background:#f8fbff}tbody tr:last-child td{border-bottom:none}
td.name{text-align:<?= $currentLang === 'ar' ? 'right' : 'left' ?>;font-weight:700;color:#172033}
.student-id{display:inline-block;padding:5px 8px;border-radius:7px;background:#f1f5f9;color:#475569;font-family:monospace;font-size:11px;font-weight:700}
.score-pill,.grade-pill{display:inline-flex;justify-content:center;min-width:46px;padding:6px 9px;border-radius:8px;font-weight:800}.score-pill{background:#eff6ff;color:#1d4ed8}.grade-pill{background:#ecfdf5;color:#047857;font-size:13px}
.action-cell{white-space:nowrap}.print-student{display:inline-flex;padding:7px 10px;border:1px solid #2563eb;border-radius:7px;color:#2563eb;text-decoration:none;font-size:12px;font-weight:700}.print-student:hover{background:#eff6ff}
.empty-results{text-align:center;padding:55px 20px;color:#64748b}.empty-results .icon{font-size:42px;margin-bottom:10px}.footer{margin-top:18px;padding:16px;text-align:center;color:#64748b;font-size:11px}
.print-button{display:block;margin:20px auto;padding:10px 20px;border:0;border-radius:8px;background:#2563eb;color:#fff;cursor:pointer;font-size:14px;font-weight:700}
@media(max-width:700px){body{padding:0}.report-header{padding:22px 18px}.report-header h1{font-size:23px}.report-actions{width:100%}.report-actions .action-btn{flex:1}.print-button{width:100%;max-width:320px}.batch-info{display:grid;grid-template-columns:1fr}.table-card{padding:10px}}
@media print{body{padding:0;background:#fff}.report-topbar,.action-cell,.print-button{display:none!important}.report{max-width:100%}.report-header{background:#fff!important;color:#111827!important;box-shadow:none;border-bottom:2px solid #111827;border-radius:0;text-align:center}.report-header h2,.report-header p{color:#374151!important;text-align:center}.table-card{border:none;box-shadow:none;padding:0}@page{size:A4 landscape;margin:12mm}}
</style>

</head>

<body>

<?php require_once '../../includes/sidebar.php'; ?>
<style>
    .main-content { padding: 30px 34px; }
    .report { width: 100%; }
    @media (max-width: 768px) {
        .main-content { padding: 22px 20px; }
    }
    @media print {
        .sidebar { display:none !important; }
        .main-content { margin:0 !important; width:100% !important; padding:0 !important; }
    }
</style>

<div class="report">
    <div class="report-topbar">
        <div class="report-actions">
            <a class="action-btn" href="results.php?lang=<?= urlencode($currentLang) ?>">← <?= $currentLang === 'ar' ? 'العودة إلى الدفعات' : 'Back to Batches' ?></a>
            <?php if ($role === 'instructor'): ?>
                <a class="action-btn action-primary" href="../grades/index.php?batch_id=<?= (int)$batchId ?>">✎ <?= $currentLang === 'ar' ? 'إضافة / تعديل الدرجات' : 'Add / Edit Grades' ?></a>
            <?php endif; ?>
            <button type="button" class="action-btn" onclick="window.print()">🖨 <?= $currentLang === 'ar' ? 'طباعة' : 'Print' ?></button>
        </div>
        <div class="report-actions"></div>
    </div>
    <div class="report-header">

        <h1>
            <?= htmlspecialchars(__('app_name')) ?>
        </h1>

        <h2>
            <?= htmlspecialchars(__('batch_final_results')) ?>
        </h2>

        <p>
            <?= htmlspecialchars(__('student_assessment_report')) ?>
        </p>

    </div>


    <!-- ================================================
         BATCH INFORMATION
    ================================================= -->

    <div class="batch-info">
        <div class="info-item"><strong><?= $currentLang === 'ar' ? 'الدفعة' : 'Batch' ?></strong> <?= htmlspecialchars($batch['batch_code']) ?></div>
        <div class="info-item"><strong><?= $currentLang === 'ar' ? 'الدورة' : 'Course' ?></strong> <?= htmlspecialchars($batch['course_name']) ?></div>
        <div class="info-item"><strong><?= $currentLang === 'ar' ? 'الفترة' : 'Period' ?></strong> <?= htmlspecialchars($batch['start_date']) ?> — <?= htmlspecialchars($batch['end_date']) ?></div>
    </div>


    <!-- ================================================
         RESULTS TABLE
    ================================================= -->

    <?php if (!empty($results)): ?>

        <div class="table-card"><div class="table-wrapper"><table>

            <thead>

                <tr>

                    <th><?= $currentLang === 'ar' ? 'م' : 'No.' ?></th>

                    <th>
                        <?= htmlspecialchars(__('student_id')) ?>
                    </th>

                    <th>
                        <?= htmlspecialchars(__('student_name')) ?>
                    </th>

                    <th>
                        <?= htmlspecialchars(__('assignment')) ?><br>(20%)
                    </th>

                    <th>
                        <?= htmlspecialchars(__('midterm')) ?><br>(35%)
                    </th>

                    <th>
                        <?= htmlspecialchars(__('final')) ?><br>(45%)
                    </th>

                    <th>
                        <?= htmlspecialchars(__('final_score')) ?>
                    </th>

                    <th>
                        <?= htmlspecialchars(__('grade')) ?>
                    </th>

                    <?php if ($studentId <= 0): ?>
                    <th>
                        <?= htmlspecialchars($lang === 'ar' ? 'إجراء' : 'Action') ?>
                    </th>
                    <?php endif; ?>

                </tr>

            </thead>

            <tbody>

            <?php foreach ($results as $index => $result): ?>

                <tr>

                    <td>
                        <?= $index + 1 ?>
                    </td>

                    <td><span class="student-id"><?= htmlspecialchars($result['student_code']) ?></span></td>

                    <td class="name">
                        <?= htmlspecialchars(
                            $result['full_name']
                        ) ?>
                    </td>

                    <td>

                        <?= $result['assignment_average'] !== null
                            ? number_format(
                                $result['assignment_average'],
                                2
                            ) . '%'
                            : '-'
                        ?>

                    </td>

                    <td>

                        <?= $result['midterm_percentage'] !== null
                            ? number_format(
                                $result['midterm_percentage'],
                                2
                            ) . '%'
                            : '-'
                        ?>

                    </td>

                    <td>

                        <?= $result['final_percentage'] !== null
                            ? number_format(
                                $result['final_percentage'],
                                2
                            ) . '%'
                            : '-'
                        ?>

                    </td>

                    <td><span class="score-pill"><?= $result['final_score'] !== null ? number_format($result['final_score'], 2) : '-' ?></span></td>

                    <td><span class="grade-pill"><?= htmlspecialchars($result['grade']) ?></span></td>

                    <?php if ($studentId <= 0): ?>
                    <td class="action-cell">
                        <a class="print-student" href="?batch_id=<?= (int) $batchId ?>&student_id=<?= (int) $result['student_id'] ?>">
                            🖨 <?= htmlspecialchars($lang === 'ar' ? 'طباعة' : 'Print') ?>
                        </a>
                    </td>
                    <?php endif; ?>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table></div></div>

    <?php else: ?>
        <div class="table-card"><div class="empty-results"><div class="icon">📊</div>
            <?= $currentLang === 'ar' ? 'لا توجد نتائج أو طلاب مسجلون في هذه الدفعة.' : 'No results or enrolled students were found for this batch.' ?>
        </div></div>
    <?php endif; ?>


    <!-- ================================================
         PRINT
    ================================================= -->

    <?php if ($studentId > 0 && !empty($results)): ?>
        <a class="back-button print-button" href="?batch_id=<?= (int) $batchId ?>">
            ← <?= htmlspecialchars($lang === 'ar' ? 'العودة إلى جميع النتائج' : 'Back to All Results') ?>
        </a>
    <div class="print-actions">


        <button
            type="button"
            class="print-button"
            onclick="window.print()"
        >
            🖨 <?= htmlspecialchars($lang === 'ar' ? 'طباعة هذه النتيجة' : 'Print This Result') ?>
        </button>
    </div>
    <?php else: ?>
        <button
            type="button"
            class="print-button"
            onclick="window.print()"
        >
            🖨 <?= htmlspecialchars(__('print_all_students')) ?>
        </button>
    <?php endif; ?>


    <!-- ================================================
         FOOTER
    ================================================= -->

    <div class="footer">

        <?= htmlspecialchars(__('app_name')) ?>

        <br>

        <?= htmlspecialchars(__('assessment_weights')) ?>:
        <?= htmlspecialchars(__('assignment')) ?> 20% |
        <?= htmlspecialchars(__('midterm')) ?> 35% |
        <?= htmlspecialchars(__('final_exam')) ?> 45%

    </div>

</div>
</div>
</body>

</html>