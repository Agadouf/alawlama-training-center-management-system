<?php

session_start();

require_once '../../config/database.php';
require_once '../../includes/language.php';

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$role = $_SESSION['role'] ?? '';

/*
|--------------------------------------------------------------------------
| Grades are Instructor Only
|--------------------------------------------------------------------------
*/

if ($role !== 'instructor') {
    http_response_code(403);
    die($lang === 'ar' ? 'ليس لديك صلاحية للوصول.' : 'Access denied.');
}

$userId = (int) $_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| Get Instructor ID
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id
    FROM instructors
    WHERE user_id = ?
    LIMIT 1
");

$stmt->execute([$userId]);

$instructor = $stmt->fetch();

if (!$instructor) {
    die($lang === 'ar' ? 'لم يتم العثور على ملف المدرس.' : 'Instructor profile not found.');
}

$instructorId = (int) $instructor['id'];

/*
|--------------------------------------------------------------------------
| Get Assigned Batches
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        b.id,
        b.batch_code,
        c.course_name
    FROM batches b
    INNER JOIN courses c
        ON c.id = b.course_id
    INNER JOIN batch_instructors bi
        ON bi.batch_id = b.id
    WHERE bi.instructor_id = ?
    ORDER BY b.start_date DESC, b.batch_code ASC
");

$stmt->execute([$instructorId]);

$batches = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Selected Batch
|--------------------------------------------------------------------------
*/

$selectedBatchId = isset($_GET['batch_id'])
    ? (int) $_GET['batch_id']
    : 0;

/*
|--------------------------------------------------------------------------
| Verify Selected Batch Belongs to Instructor
|--------------------------------------------------------------------------
*/

if ($selectedBatchId > 0) {

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM batch_instructors
        WHERE batch_id = ?
        AND instructor_id = ?
    ");

    $stmt->execute([
        $selectedBatchId,
        $instructorId
    ]);

    if ((int) $stmt->fetchColumn() === 0) {
        $selectedBatchId = 0;
    }
}

/*
|--------------------------------------------------------------------------
| Get Students in Selected Batch
|--------------------------------------------------------------------------
*/

$students = [];

if ($selectedBatchId > 0) {

    $stmt = $pdo->prepare("
        SELECT
            e.id AS enrollment_id,
            s.student_code,
            s.full_name,
            s.gender,
            g.id AS grade_id,
            g.score,
            g.notes
        FROM enrollments e

        INNER JOIN students s
            ON s.id = e.student_id

        LEFT JOIN grades g
            ON g.enrollment_id = e.id

        WHERE e.batch_id = ?

        AND e.status != 'cancelled'

        ORDER BY s.full_name ASC
    ");

    $stmt->execute([$selectedBatchId]);

    $students = $stmt->fetchAll();
}

/*
|--------------------------------------------------------------------------
| Page Title
|--------------------------------------------------------------------------
*/

$pageTitle = ($lang === 'ar') ? 'الدرجات' : 'Grades';

?>

<?php require_once '../../includes/sidebar.php'; ?>

<div class="grades-page">

    <!-- =========================================================
         PAGE HEADER
    ========================================================== -->

    <div class="page-header">

        <div class="header-content">
            <div class="title-icon">📊</div>
            <div>
                <h1><?= $lang === 'ar' ? 'الدرجات' : 'Grades' ?></h1>

                <p>
                    <?= $lang === 'ar' ? 'إضافة وتعديل وإدارة درجات الطلاب.' : 'Add, edit and manage student grades.' ?>
                </p>
            </div>
        </div>

        <a href="../dashboard/index.php" class="btn-back">
            <span class="back-arrow">←</span>
            <?= $lang === 'ar' ? 'العودة إلى لوحة التحكم' : 'Back to Dashboard' ?>
        </a>

    </div>


    <!-- =========================================================
         BATCH SELECTION
    ========================================================== -->

    <div class="card">

        <div class="card-header">

            <div>
                 <h2><?= $lang === 'ar' ? 'اختيار الدفعة' : 'Select Batch' ?></h2>

                <p>
                    <?= $lang === 'ar' ? 'اختر إحدى الدفعات المخصصة لك.' : 'Select one of your assigned batches.' ?>
                </p>
            </div>

        </div>

        <form method="GET" class="batch-form">

            <div class="form-group">

                <label for="batch_id">
                    <?= $lang === 'ar' ? 'الدفعة' : 'Batch' ?>
                </label>

                <select
                    name="batch_id"
                    id="batch_id"
                    onchange="this.form.submit()"
                >

                    <option value="">
                        -- <?= $lang === 'ar' ? 'اختر الدفعة' : 'Select Batch' ?> --
                    </option>

                    <?php foreach ($batches as $batch): ?>

                        <option
                            value="<?= (int) $batch['id'] ?>"
                            <?= $selectedBatchId === (int) $batch['id'] ? 'selected' : '' ?>
                        >

                            <?= htmlspecialchars($batch['batch_code']) ?>
                            -
                            <?= htmlspecialchars($batch['course_name']) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

        </form>

    </div>


    <?php if ($selectedBatchId > 0): ?>

        <!-- =====================================================
             STUDENTS / GRADES
        ====================================================== -->

        <div class="card">

            <div class="card-header">

                <div>

                    <h2><?= $lang === 'ar' ? 'درجات الطلاب' : 'Student Grades' ?></h2>

                    <p>
                        <?= $lang === 'ar' ? 'يمكن للمدرس إضافة الدرجات الجديدة وتعديل الدرجات الموجودة.' : 'Instructors can add new grades and edit existing grades.' ?>
                    </p>

                </div>

                <div class="permission-badge">
                    <span>✓</span>
                    <?= $lang === 'ar' ? 'إضافة وتعديل' : 'Add & Edit' ?>
                </div>

            </div>


            <?php if (empty($students)): ?>

                <div class="empty-state">

                    <div class="empty-icon">
                        📚
                    </div>

                    <h3><?= $lang === 'ar' ? 'لا يوجد طلاب' : 'No Students Found' ?></h3>

                    <p>
                        <?= $lang === 'ar' ? 'لا يوجد طلاب مسجلون في هذه الدفعة.' : 'There are no enrolled students in this batch.' ?>
                    </p>

                </div>

            <?php else: ?>

                <form
                    method="POST"
                    action="save.php"
                    id="gradesForm"
                >

                    <input
                        type="hidden"
                        name="batch_id"
                        value="<?= $selectedBatchId ?>"
                    >


                    <div class="table-wrapper">

                        <table class="grades-table">

                            <thead>

                                <tr>

                                    <th width="60">
                                        #
                                    </th>

                                    <th>
                                        <?= $lang === 'ar' ? 'رقم الطالب' : 'Student ID' ?>
                                    </th>

                                    <th>
                                        <?= $lang === 'ar' ? 'اسم الطالب' : 'Student Name' ?>
                                    </th>

                                    <th>
                                        <?= $lang === 'ar' ? 'الجنس' : 'Gender' ?>
                                    </th>

                                    <th width="160">
                                        <?= $lang === 'ar' ? 'الدرجة' : 'Score' ?>
                                    </th>

                                    <th width="120">
                                        <?= $lang === 'ar' ? 'النسبة' : 'Percentage' ?>
                                    </th>

                                    <th width="120">
                                        <?= $lang === 'ar' ? 'الحالة' : 'Status' ?>
                                    </th>

                                    <th>
                                        <?= $lang === 'ar' ? 'ملاحظات' : 'Notes' ?>
                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach ($students as $index => $student): ?>

                                    <?php

                                    $score = $student['score'];

                                    $percentage = '';

                                    if (
                                        $score !== null &&
                                        $score !== ''
                                    ) {
                                        $percentage = number_format(
                                            (float) $score,
                                            2
                                        ) . '%';
                                    }

                                    ?>

                                    <tr>

                                        <!-- Number -->

                                        <td>
                                            <?= $index + 1 ?>
                                        </td>


                                        <!-- <?= $lang === 'ar' ? 'رقم الطالب' : 'Student ID' ?> -->

                                        <td>

                                            <span class="student-code">

                                                <?= htmlspecialchars(
                                                    $student['student_code']
                                                ) ?>

                                            </span>

                                        </td>


                                        <!-- <?= $lang === 'ar' ? 'اسم الطالب' : 'Student Name' ?> -->

                                        <td>

                                            <strong>

                                                <?= htmlspecialchars(
                                                    $student['full_name']
                                                ) ?>

                                            </strong>

                                        </td>


                                        <!-- <?= $lang === 'ar' ? 'الجنس' : 'Gender' ?> -->

                                        <td>

                                            <?= htmlspecialchars(
                                                $student['gender']
                                            ) ?>

                                        </td>


                                        <!-- <?= $lang === 'ar' ? 'الدرجة' : 'Score' ?> -->

                                        <td>

                                            <input
                                                type="number"
                                                name="scores[<?= (int) $student['enrollment_id'] ?>]"
                                                class="score-input"
                                                min="0"
                                                max="100"
                                                step="0.01"
                                                value="<?= $score !== null ? htmlspecialchars($score) : '' ?>"
                                                placeholder="<?= $lang === 'ar' ? '0 - 100' : '0 - 100' ?>"
                                            >

                                        </td>


                                        <!-- <?= $lang === 'ar' ? 'النسبة' : 'Percentage' ?> -->

                                        <td>

                                            <span
                                                class="percentage"
                                                data-score="<?= $score !== null ? htmlspecialchars($score) : '' ?>"
                                            >

                                                <?= $percentage !== ''
                                                    ? $percentage
                                                    : '-' ?>

                                            </span>

                                        </td>


                                        <!-- Grade status -->

                                        <td>
                                            <?php if ($student['grade_id']): ?>
                                                <span class="grade-status status-edit">
                                                    <?= $lang === 'ar' ? 'موجودة — تعديل' : 'Saved — Edit' ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="grade-status status-add">
                                                    <?= $lang === 'ar' ? 'جديدة — إضافة' : 'New — Add' ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>


                                        <!-- <?= $lang === 'ar' ? 'ملاحظات' : 'Notes' ?> -->

                                        <td>

                                            <input
                                                type="text"
                                                name="notes[<?= (int) $student['enrollment_id'] ?>]"
                                                class="notes-input"
                                                value="<?= htmlspecialchars(
                                                    $student['notes'] ?? ''
                                                ) ?>"
                                                placeholder="<?= $lang === 'ar' ? 'ملاحظات اختيارية' : 'Optional notes' ?>"
                                            >

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>


                    <!-- =================================================
                         SAVE BUTTON
                    ================================================== -->

                    <div class="form-actions">

                        <button
                            type="submit"
                            class="btn-save"
                        >

                            <?= $lang === 'ar' ? 'حفظ الدرجات' : 'Save Grades' ?>

                        </button>

                    </div>

                </form>

            <?php endif; ?>

        </div>

    <?php else: ?>

        <!-- =====================================================
             NO BATCH SELECTED
        ====================================================== -->

        <div class="card empty-card">

            <div class="empty-state">

                <div class="empty-icon">
                    📋
                </div>

                <h3><?= $lang === 'ar' ? 'اختر دفعة' : 'Select a Batch' ?></h3>

                <p>
                    <?= $lang === 'ar' ? 'يرجى اختيار دفعة أعلاه لعرض الطلاب المسجلين وإدخال درجاتهم.' : 'Please select a batch above to view enrolled students and enter their grades.' ?>
                </p>

            </div>

        </div>

    <?php endif; ?>

</div>


<!-- =============================================================
     JAVASCRIPT
============================================================== -->

<script>

document.addEventListener('DOMContentLoaded', function () {

    const scoreInputs =
        document.querySelectorAll('.score-input');


    scoreInputs.forEach(function (input) {

        input.addEventListener('input', function () {

            let value = parseFloat(this.value);

            const row = this.closest('tr');

            if (!row) {
                return;
            }

            const percentage =
                row.querySelector('.percentage');

            if (!percentage) {
                return;
            }

            if (isNaN(value)) {

                percentage.textContent = '-';

                return;
            }


            /*
            |--------------------------------------------------------------------------
            | Keep score between 0 and 100
            |--------------------------------------------------------------------------
            */

            if (value < 0) {

                value = 0;
                this.value = 0;

            }

            if (value > 100) {

                value = 100;
                this.value = 100;

            }


            percentage.textContent =
                value.toFixed(2) + '%';

            // Update the visual state while typing.
            percentage.classList.remove('score-low', 'score-mid', 'score-high');
            if (value < 50) {
                percentage.classList.add('score-low');
            } else if (value < 75) {
                percentage.classList.add('score-mid');
            } else {
                percentage.classList.add('score-high');
            }

        });

    });

});

</script>


<!-- =============================================================
     CSS
============================================================== -->

<style>
/* =========================================================
   GRADES PAGE — MODERN RESPONSIVE DESIGN
   The shared sidebar.php owns the main layout.
   ========================================================= */

.grades-page {
    margin: 0 !important;
    padding: 28px 32px 40px;
    width: 100% !important;
    max-width: none !important;
    min-height: calc(100vh - 20px);
    box-sizing: border-box;
    background: #f5f7fb;
}

/* Header */
.grades-page .page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    margin: 0 0 24px;
    padding: 26px 28px;
    border: 1px solid #e7eaf0;
    border-radius: 18px;
    background: linear-gradient(135deg, #ffffff 0%, #f8fbff 100%);
    box-shadow: 0 8px 28px rgba(15, 23, 42, .06);
}

.grades-page .page-header h1 {
    margin: 0;
    color: #111827;
    font-size: 30px;
    line-height: 1.2;
    font-weight: 800;
    letter-spacing: -.5px;
}

.grades-page .page-header p {
    margin: 8px 0 0;
    color: #64748b;
    font-size: 14px;
    line-height: 1.6;
}

.grades-page .header-content {
    display: flex;
    align-items: center;
    gap: 14px;
}

.grades-page .title-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 48px;
    height: 48px;
    flex: 0 0 48px;
    border-radius: 14px;
    background: #eff6ff;
    font-size: 23px;
}

.grades-page .btn-back {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 42px;
    padding: 0 15px;
    border: 1px solid #dbe2ea;
    border-radius: 10px;
    background: #fff;
    color: #344054;
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
    white-space: nowrap;
    transition: .2s ease;
}

.grades-page .btn-back:hover {
    border-color: #2563eb;
    color: #2563eb;
    background: #f8fbff;
    transform: translateY(-1px);
}

.grades-page .back-arrow {
    font-size: 17px;
    line-height: 1;
}

/* Cards */
.grades-page .card {
    width: 100%;
    box-sizing: border-box;
    margin: 0 0 22px;
    padding: 24px;
    background: #fff;
    border: 1px solid #e6eaf0;
    border-radius: 16px;
    box-shadow: 0 6px 24px rgba(15, 23, 42, .055);
}

.grades-page .card-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 18px;
    margin: 0 0 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid #eef1f5;
}

.grades-page .card-header h2 {
    margin: 0;
    color: #172033;
    font-size: 19px;
    line-height: 1.35;
    font-weight: 750;
}

.grades-page .card-header p {
    margin: 6px 0 0;
    color: #718096;
    font-size: 13px;
    line-height: 1.5;
}

.grades-page .permission-badge {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 11px;
    border: 1px solid #d1fae5;
    border-radius: 999px;
    background: #ecfdf3;
    color: #047857;
    font-size: 11px;
    font-weight: 800;
    white-space: nowrap;
}

.grades-page .permission-badge span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #10b981;
    color: #fff;
    font-size: 11px;
}

/* Batch selector */
.grades-page .batch-form {
    width: 100%;
}

.grades-page .form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
    max-width: 620px;
}

.grades-page .form-group label {
    color: #344054;
    font-size: 13px;
    font-weight: 700;
}

.grades-page .form-group select {
    width: 100%;
    min-height: 46px;
    padding: 0 42px 0 14px;
    border: 1px solid #d7dce5;
    border-radius: 10px;
    background-color: #fff;
    color: #172033;
    font-size: 14px;
    outline: none;
    cursor: pointer;
    transition: border-color .2s, box-shadow .2s, transform .2s;
}

.grades-page .form-group select:hover {
    border-color: #aeb7c7;
}

.grades-page .form-group select:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 4px rgba(37, 99, 235, .10);
}

/* Table */
.grades-page .table-wrapper {
    width: 100%;
    overflow-x: auto;
    border: 1px solid #e5e9f0;
    border-radius: 12px;
    background: #fff;
    -webkit-overflow-scrolling: touch;
}

.grades-page .grades-table {
    width: 100%;
    min-width: 950px;
    border-collapse: separate;
    border-spacing: 0;
}

.grades-page .grades-table th {
    position: sticky;
    top: 0;
    z-index: 2;
    padding: 14px 13px;
    background: #f8fafc;
    color: #475467;
    border-bottom: 1px solid #e4e7ec;
    font-size: 12px;
    font-weight: 800;
    text-align: left;
    white-space: nowrap;
}

.grades-page .grades-table td {
    padding: 13px;
    color: #344054;
    background: #fff;
    border-bottom: 1px solid #eef1f5;
    font-size: 13px;
    vertical-align: middle;
    transition: background .15s;
}

.grades-page .grades-table tbody tr:hover td {
    background: #f8fbff;
}

.grades-page .grades-table tbody tr:last-child td {
    border-bottom: 0;
}

.grades-page .grades-table th:first-child,
.grades-page .grades-table td:first-child {
    padding-left: 16px;
}

.grades-page .grades-table th:last-child,
.grades-page .grades-table td:last-child {
    padding-right: 16px;
}

/* Student identity */
.grades-page .student-code {
    display: inline-flex;
    align-items: center;
    min-height: 28px;
    padding: 0 9px;
    border: 1px solid #e2e8f0;
    border-radius: 7px;
    background: #f8fafc;
    color: #475467;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 12px;
    font-weight: 650;
}

.grades-page .grades-table td strong {
    color: #172033;
    font-weight: 700;
}

/* Grade status */
.grades-page .grade-status {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 28px;
    padding: 0 9px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 800;
    white-space: nowrap;
}

.grades-page .status-edit {
    background: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #dbeafe;
}

.grades-page .status-add {
    background: #fff7ed;
    color: #c2410c;
    border: 1px solid #fed7aa;
}

/* Inputs */
.grades-page .score-input,
.grades-page .notes-input {
    box-sizing: border-box;
    border: 1px solid #d7dce5;
    background: #fff;
    color: #172033;
    outline: none;
    transition: border-color .2s, box-shadow .2s, background .2s;
}

.grades-page .score-input {
    width: 120px;
    min-height: 42px;
    padding: 0 10px;
    border-radius: 9px;
    text-align: center;
    font-size: 14px;
    font-weight: 700;
}

.grades-page .notes-input {
    width: 100%;
    min-width: 220px;
    min-height: 42px;
    padding: 0 11px;
    border-radius: 9px;
    font-size: 13px;
}

.grades-page .score-input:hover,
.grades-page .notes-input:hover {
    border-color: #b8c1cf;
}

.grades-page .score-input:focus,
.grades-page .notes-input:focus {
    border-color: #2563eb;
    background: #fbfdff;
    box-shadow: 0 0 0 4px rgba(37, 99, 235, .10);
}

/* Percentage */
.grades-page .percentage {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 66px;
    min-height: 30px;
    padding: 0 8px;
    border-radius: 999px;
    background: #eff6ff;
    color: #1d4ed8;
    font-size: 12px;
    font-weight: 800;
}

.grades-page .percentage.score-low {
    background: #fef2f2;
    color: #b91c1c;
}

.grades-page .percentage.score-mid {
    background: #fffbeb;
    color: #b45309;
}

.grades-page .percentage.score-high {
    background: #ecfdf3;
    color: #047857;
}

/* Save area */
.grades-page .form-actions {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 12px;
    margin-top: 20px;
    padding-top: 18px;
    border-top: 1px solid #eef1f5;
}

.grades-page .btn-save {
    min-height: 44px;
    border: 0;
    border-radius: 10px;
    padding: 0 22px;
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    color: #fff;
    font-size: 14px;
    font-weight: 750;
    cursor: pointer;
    box-shadow: 0 5px 14px rgba(37, 99, 235, .22);
    transition: transform .15s, box-shadow .2s, filter .2s;
}

.grades-page .btn-save:hover {
    filter: brightness(1.03);
    box-shadow: 0 8px 20px rgba(37, 99, 235, .28);
    transform: translateY(-1px);
}

.grades-page .btn-save:active {
    transform: translateY(0);
}

/* Empty states */
.grades-page .empty-card {
    min-height: 300px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.grades-page .empty-state {
    max-width: 480px;
    padding: 48px 24px;
    text-align: center;
}

.grades-page .empty-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 68px;
    height: 68px;
    margin: 0 auto 18px;
    border-radius: 20px;
    background: #eff6ff;
    font-size: 34px;
}

.grades-page .empty-state h3 {
    margin: 0 0 8px;
    color: #172033;
    font-size: 19px;
    font-weight: 800;
}

.grades-page .empty-state p {
    margin: 0;
    color: #718096;
    font-size: 14px;
    line-height: 1.7;
}

/* RTL */
html[dir="rtl"] .grades-page .grades-table th,
html[dir="rtl"] .grades-page .grades-table td {
    text-align: right;
}

html[dir="rtl"] .grades-page .page-header,
html[dir="rtl"] .grades-page .card-header {
    text-align: right;
}

html[dir="rtl"] .grades-page .form-actions {
    justify-content: flex-start;
}

html[dir="rtl"] .grades-page .form-group select {
    padding: 0 14px 0 42px;
}

html[dir="rtl"] .grades-page .grades-table th:first-child,
html[dir="rtl"] .grades-page .grades-table td:first-child {
    padding-left: 13px;
    padding-right: 16px;
}

html[dir="rtl"] .grades-page .grades-table th:last-child,
html[dir="rtl"] .grades-page .grades-table td:last-child {
    padding-right: 13px;
    padding-left: 16px;
}

/* Tablet */
@media (max-width: 900px) {
    .grades-page {
        padding: 20px;
    }

    .grades-page .page-header {
        padding: 22px;
    }

    .grades-page .card {
        padding: 18px;
    }
}

/* Mobile */
@media (max-width: 768px) {
    .grades-page {
        padding: 15px 12px 28px;
    }

    .grades-page .page-header {
        display: block;
        padding: 20px 18px;
        border-radius: 14px;
        margin-bottom: 16px;
    }

    .grades-page .header-content {
        align-items: flex-start;
    }

    .grades-page .btn-back {
        width: 100%;
        justify-content: center;
        margin-top: 16px;
    }

    .grades-page .page-header h1 {
        font-size: 24px;
    }

    .grades-page .card {
        padding: 15px;
        border-radius: 13px;
        margin-bottom: 16px;
    }

    .grades-page .card-header {
        margin-bottom: 16px;
        padding-bottom: 13px;
    }

    .grades-page .card-header h2 {
        font-size: 17px;
    }

    .grades-page .form-group {
        max-width: none;
    }

    .grades-page .form-actions {
        justify-content: stretch;
    }

    .grades-page .btn-save {
        width: 100%;
    }

    .grades-page .empty-card {
        min-height: 250px;
    }
}

@media (max-width: 480px) {
    .grades-page {
        padding: 10px 8px 22px;
    }

    .grades-page .page-header {
        padding: 17px 15px;
    }

    .grades-page .page-header h1 {
        font-size: 22px;
    }

    .grades-page .card {
        padding: 12px;
        border-radius: 11px;
    }

    .grades-page .grades-table {
        min-width: 900px;
    }
}
</style>