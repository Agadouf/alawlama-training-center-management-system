<?php
session_start();

require_once '../../config/database.php';
require_once '../../includes/language.php';

/*
|--------------------------------------------------------------------------
| AUTHENTICATION & ROLE ACCESS
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ../auth/login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$role   = $_SESSION['role'];

$isAdmin      = ($role === 'admin');
$isStaff      = ($role === 'staff');
$isInstructor = ($role === 'instructor');

if (!$isAdmin && !$isStaff && !$isInstructor) {
    http_response_code(403);
    die(($lang === 'ar') ? 'غير مسموح بالوصول.' : 'Access denied.');
}

/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$selectedBatchId = isset($_GET['batch_id'])
    ? (int) $_GET['batch_id']
    : (int) ($_POST['batch_id'] ?? 0);

$selectedDate = $_GET['attendance_date']
    ?? $_POST['attendance_date']
    ?? date('Y-m-d');

$message = '';
$error   = '';

$instructorId = 0;

/*
|--------------------------------------------------------------------------
| GET INSTRUCTOR PROFILE
|--------------------------------------------------------------------------
|
| Only instructors need an instructor profile because they are the only
| users who can modify attendance.
|
*/

if ($isInstructor) {
    $stmt = $pdo->prepare("
        SELECT id, full_name
        FROM instructors
        WHERE user_id = ?
          AND status = 'active'
        LIMIT 1
    ");

    $stmt->execute([$userId]);
    $instructor = $stmt->fetch();

    if (!$instructor) {
        http_response_code(403);
        die(($lang === 'ar') ? 'ملف المدرس غير موجود أو غير نشط.' : 'Instructor profile not found or inactive.');
    }

    $instructorId = (int) $instructor['id'];
}

/*
|--------------------------------------------------------------------------
| GET BATCHES
|--------------------------------------------------------------------------
|
| Admin/Staff:
|   Can view attendance for all batches.
|
| Instructor:
|   Can view/manage attendance only for assigned batches.
|
*/

if ($isInstructor) {
    $stmt = $pdo->prepare("
        SELECT
            b.id,
            b.batch_code,
            b.start_date,
            b.end_date,
            b.status,
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
} else {
    $stmt = $pdo->query("
        SELECT
            b.id,
            b.batch_code,
            b.start_date,
            b.end_date,
            b.status,
            c.course_name
        FROM batches b
        INNER JOIN courses c
            ON c.id = b.course_id
        ORDER BY b.start_date DESC, b.batch_code ASC
    ");
}

$batches = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| SAVE ATTENDANCE
|--------------------------------------------------------------------------
|
| Only instructors are allowed to save attendance.
|
*/

if (
    $isInstructor
    && $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['save_attendance'])
) {
    $selectedBatchId = (int) ($_POST['batch_id'] ?? 0);
    $selectedDate    = trim($_POST['attendance_date'] ?? '');

    $statuses = $_POST['status'] ?? [];
    $notes    = $_POST['notes'] ?? [];

    /*
    |--------------------------------------------------------------------------
    | VALIDATE DATE
    |--------------------------------------------------------------------------
    */

    $dateObject = DateTime::createFromFormat('Y-m-d', $selectedDate);

    if (
        !$dateObject
        || $dateObject->format('Y-m-d') !== $selectedDate
    ) {
        $error = ($lang === 'ar') ? 'يرجى اختيار تاريخ حضور صحيح.' : 'Please select a valid attendance date.';
    } elseif ($selectedBatchId <= 0) {
        $error = ($lang === 'ar') ? 'يرجى اختيار مجموعة.' : 'Please select a batch.';
    } else {
        /*
        |--------------------------------------------------------------------------
        | VERIFY INSTRUCTOR ASSIGNMENT
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT
                b.id,
                b.batch_code,
                b.start_date,
                b.end_date,
                b.status,
                c.course_name
            FROM batches b
            INNER JOIN courses c
                ON c.id = b.course_id
            INNER JOIN batch_instructors bi
                ON bi.batch_id = b.id
            WHERE b.id = ?
              AND bi.instructor_id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $selectedBatchId,
            $instructorId
        ]);

        $selectedBatch = $stmt->fetch();

        if (!$selectedBatch) {
            $error = ($lang === 'ar') ? 'أنت غير مخصص لهذه المجموعة.' : 'You are not assigned to this batch.';
        } elseif ($selectedBatch['status'] === 'cancelled') {
            $error = ($lang === 'ar') ? 'لا يمكن تسجيل الحضور لمجموعة ملغاة.' : 'Attendance cannot be recorded for a cancelled batch.';
        } else {
            /*
            |--------------------------------------------------------------------------
            | VERIFY DATE IS WITHIN BATCH PERIOD
            |--------------------------------------------------------------------------
            */

            if (
                $selectedDate < $selectedBatch['start_date']
                || $selectedDate > $selectedBatch['end_date']
            ) {
                $error = ($lang === 'ar') ? 'يجب أن يكون تاريخ الحضور ضمن تاريخ بداية ونهاية المجموعة.' : 'Attendance date must be within the batch start and end dates.';
            }
        }

        /*
        |--------------------------------------------------------------------------
        | SAVE RECORDS
        |--------------------------------------------------------------------------
        */

        if ($error === '') {
            try {
                $pdo->beginTransaction();

                /*
                |--------------------------------------------------------------------------
                | Get valid enrollments for this batch.
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM enrollments
                    WHERE batch_id = ?
                      AND status != 'cancelled'
                ");

                $stmt->execute([$selectedBatchId]);

                $validEnrollmentIds = [];

                foreach ($stmt->fetchAll() as $row) {
                    $validEnrollmentIds[(int) $row['id']] = true;
                }

                foreach ($statuses as $enrollmentId => $status) {
                    $enrollmentId = (int) $enrollmentId;

                    if (!isset($validEnrollmentIds[$enrollmentId])) {
                        continue;
                    }

                    if (!in_array($status, ['Present', 'Absent'], true)) {
                        continue;
                    }

                    $note = isset($notes[$enrollmentId])
                        ? trim((string) $notes[$enrollmentId])
                        : '';

                    /*
                    |--------------------------------------------------------------------------
                    | Check existing record.
                    |--------------------------------------------------------------------------
                    */

                    $stmt = $pdo->prepare("
                        SELECT id
                        FROM attendance
                        WHERE enrollment_id = ?
                          AND attendance_date = ?
                        LIMIT 1
                    ");

                    $stmt->execute([
                        $enrollmentId,
                        $selectedDate
                    ]);

                    $existingAttendance = $stmt->fetch();

                    if ($existingAttendance) {
                        /*
                        |--------------------------------------------------------------------------
                        | UPDATE
                        |--------------------------------------------------------------------------
                        */

                        $stmt = $pdo->prepare("
                            UPDATE attendance
                            SET
                                status = ?,
                                recorded_by = ?,
                                notes = ?
                            WHERE id = ?
                        ");

                        $stmt->execute([
                            $status,
                            $userId,
                            $note !== '' ? $note : null,
                            (int) $existingAttendance['id']
                        ]);
                    } else {
                        /*
                        |--------------------------------------------------------------------------
                        | INSERT
                        |--------------------------------------------------------------------------
                        */

                        $stmt = $pdo->prepare("
                            INSERT INTO attendance
                            (
                                enrollment_id,
                                attendance_date,
                                status,
                                recorded_by,
                                notes
                            )
                            VALUES (?, ?, ?, ?, ?)
                        ");

                        $stmt->execute([
                            $enrollmentId,
                            $selectedDate,
                            $status,
                            $userId,
                            $note !== '' ? $note : null
                        ]);
                    }
                }

                $pdo->commit();

                $message = ($lang === 'ar') ? 'تم حفظ الحضور بنجاح.' : 'Attendance saved successfully.';
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error = ($lang === 'ar') ? 'فشل حفظ الحضور. يرجى المحاولة مرة أخرى.' : 'Failed to save attendance. Please try again.';
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| LOAD SELECTED BATCH
|--------------------------------------------------------------------------
*/

$selectedBatch = null;
$students      = [];

if ($selectedBatchId > 0) {
    /*
    |--------------------------------------------------------------------------
    | Verify selected batch.
    |--------------------------------------------------------------------------
    */

    if ($isInstructor) {
        $stmt = $pdo->prepare("
            SELECT
                b.id,
                b.batch_code,
                b.start_date,
                b.end_date,
                b.status,
                c.course_name
            FROM batches b
            INNER JOIN courses c
                ON c.id = b.course_id
            INNER JOIN batch_instructors bi
                ON bi.batch_id = b.id
            WHERE b.id = ?
              AND bi.instructor_id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $selectedBatchId,
            $instructorId
        ]);
    } else {
        $stmt = $pdo->prepare("
            SELECT
                b.id,
                b.batch_code,
                b.start_date,
                b.end_date,
                b.status,
                c.course_name
            FROM batches b
            INNER JOIN courses c
                ON c.id = b.course_id
            WHERE b.id = ?
            LIMIT 1
        ");

        $stmt->execute([$selectedBatchId]);
    }

    $selectedBatch = $stmt->fetch();

    /*
    |--------------------------------------------------------------------------
    | GET STUDENTS + ATTENDANCE
    |--------------------------------------------------------------------------
    */

    if ($selectedBatch) {
        $stmt = $pdo->prepare("
            SELECT
                e.id AS enrollment_id,
                s.id AS student_id,
                s.student_code,
                s.full_name,
                s.gender,
                e.status AS enrollment_status,

                a.id AS attendance_id,
                a.status AS attendance_status,
                a.notes AS attendance_notes

            FROM enrollments e

            INNER JOIN students s
                ON s.id = e.student_id

            LEFT JOIN attendance a
                ON a.enrollment_id = e.id
                AND a.attendance_date = ?

            WHERE e.batch_id = ?
              AND e.status != 'cancelled'

            ORDER BY s.full_name ASC
        ");

        $stmt->execute([
            $selectedDate,
            $selectedBatchId
        ]);

        $students = $stmt->fetchAll();
    } elseif ($selectedBatchId > 0) {
        http_response_code(403);
        die(($lang === 'ar') ? 'ليس لديك صلاحية لعرض هذه المجموعة.' : 'You do not have permission to view this batch.');
    }
}

?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title><?= $lang === 'ar' ? 'الحضور - مركز العولمة للتدريب' : 'Attendance - ALAWALAMA TRAINING CENTER' ?></title>

    <style>

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            width: 100%;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f6f9;
            color: #1f2937;
        }

        /*
        |--------------------------------------------------------------------------
        | PAGE
        |--------------------------------------------------------------------------
        |
        | sidebar.php already creates .main-content.
        | Therefore this page uses .attendance-page instead of creating
        | another .main-content wrapper.
        |
        */

        .attendance-page {
            width: 100%;
            min-height: 100vh;
            padding: 28px;
        }

        /*
        |--------------------------------------------------------------------------
        | HEADER
        |--------------------------------------------------------------------------
        */

        .page-header {
            margin: 0 0 22px;
        }

        .page-header h1 {
            margin: 0;
            font-size: 30px;
            line-height: 1.2;
            font-weight: 700;
            color: #172033;
        }

        .page-header p {
            margin: 7px 0 0;
            font-size: 15px;
            color: #64748b;
        }

        /*
        |--------------------------------------------------------------------------
        | ALERTS
        |--------------------------------------------------------------------------
        */

        .alert {
            margin: 0 0 18px;
            padding: 12px 16px;
            border-radius: 9px;
            font-size: 14px;
            line-height: 1.5;
        }

        .alert-success {
            color: #166534;
            background: #dcfce7;
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            color: #991b1b;
            background: #fee2e2;
            border: 1px solid #fecaca;
        }

        /*
        |--------------------------------------------------------------------------
        | FILTER CARD
        |--------------------------------------------------------------------------
        */

        .filter-card {
            width: 100%;
            margin-bottom: 18px;
            padding: 20px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .filter-form {
            width: 100%;
            display: grid;
            grid-template-columns: minmax(220px, 1fr) minmax(190px, 240px) auto auto;
            gap: 14px;
            align-items: end;
        }

        .form-group {
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .form-group label {
            font-size: 13px;
            font-weight: 700;
            color: #374151;
        }

        .form-control {
            width: 100%;
            height: 44px;
            padding: 0 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: #ffffff;
            color: #111827;
            font-size: 14px;
        }

        .form-control:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.10);
        }

        /*
        |--------------------------------------------------------------------------
        | BUTTONS
        |--------------------------------------------------------------------------
        */

        .btn {
            min-height: 44px;
            padding: 0 17px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            white-space: nowrap;
        }

        .btn-primary {
            background: #2563eb;
            color: #ffffff;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-secondary {
            background: #6b7280;
            color: #ffffff;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        /*
        |--------------------------------------------------------------------------
        | ATTENDANCE CARD
        |--------------------------------------------------------------------------
        */

        .attendance-card {
            width: 100%;
            margin: 0;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        /*
        |--------------------------------------------------------------------------
        | BATCH HEADER
        |--------------------------------------------------------------------------
        */

        .attendance-header {
            padding: 18px 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            border-bottom: 1px solid #e5e7eb;
        }

        .batch-info {
            min-width: 0;
        }

        .batch-info h2 {
            margin: 0;
            font-size: 23px;
            line-height: 1.25;
            color: #111827;
        }

        .batch-info p {
            margin: 5px 0 0;
            font-size: 14px;
            color: #64748b;
        }

        .date-info {
            font-size: 14px;
            color: #64748b;
            white-space: nowrap;
        }

        .date-info strong {
            color: #334155;
        }

        /*
        |--------------------------------------------------------------------------
        | TABLE TITLE
        |--------------------------------------------------------------------------
        */

        .table-title {
            padding: 16px 22px;
            border-bottom: 1px solid #e5e7eb;
        }

        .table-title h3 {
            margin: 0;
            font-size: 19px;
            line-height: 1.25;
            color: #111827;
        }

        .table-title p {
            margin: 5px 0 0;
            font-size: 14px;
            color: #64748b;
        }

        /*
        |--------------------------------------------------------------------------
        | TABLE
        |--------------------------------------------------------------------------
        */

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 900px;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 12px 14px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            font-size: 14px;
            vertical-align: middle;
        }

        th {
            background: #f8fafc;
            color: #334155;
            font-weight: 700;
            white-space: nowrap;
        }

        td {
            color: #475569;
        }

        tbody tr:hover {
            background: #f8fafc;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .student-number {
            width: 45px;
            color: #64748b;
            font-weight: 600;
        }

        .student-code {
            color: #2563eb;
            font-weight: 600;
            white-space: nowrap;
        }

        .student-name {
            color: #111827;
            font-weight: 600;
        }

        /*
        |--------------------------------------------------------------------------
        | ATTENDANCE OPTIONS
        |--------------------------------------------------------------------------
        */

        .attendance-options {
            display: flex;
            align-items: center;
            gap: 18px;
            white-space: nowrap;
        }

        .attendance-option {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }

        .attendance-option input {
            width: 16px;
            height: 16px;
            margin: 0;
            cursor: pointer;
        }

        .attendance-option input:disabled {
            cursor: default;
        }

        .present {
            color: #15803d;
        }

        .absent {
            color: #b91c1c;
        }

        /*
        |--------------------------------------------------------------------------
        | NOTES
        |--------------------------------------------------------------------------
        */

        .notes-input {
            min-width: 190px;
        }

        .notes-input:disabled {
            background: #f8fafc;
            color: #64748b;
            cursor: not-allowed;
        }

        /*
        |--------------------------------------------------------------------------
        | SAVE FOOTER
        |--------------------------------------------------------------------------
        */

        .save-section {
            min-height: 68px;
            padding: 12px 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            border-top: 1px solid #e5e7eb;
            background: #ffffff;
        }

        .student-count {
            color: #64748b;
            font-size: 14px;
        }

        .student-count strong {
            color: #111827;
        }

        .view-only {
            color: #64748b;
            font-size: 13px;
        }

        /*
        |--------------------------------------------------------------------------
        | EMPTY STATE
        |--------------------------------------------------------------------------
        */

        .empty-state {
            padding: 45px 25px;
            text-align: center;
            color: #64748b;
        }

        .empty-state h3 {
            margin: 0 0 7px;
            font-size: 19px;
            color: #334155;
        }

        .empty-state p {
            margin: 0;
            font-size: 14px;
        }

        /*
        |--------------------------------------------------------------------------
        | RESPONSIVE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 1100px) {

            .filter-form {
                grid-template-columns: 1fr 1fr;
            }

            .filter-form .form-group:first-child {
                grid-column: 1 / -1;
            }

        }

        @media (max-width: 700px) {

            .attendance-page {
                padding: 18px;
            }

            .page-header h1 {
                font-size: 26px;
            }

            .filter-form {
                grid-template-columns: 1fr;
            }

            .filter-form .form-group:first-child {
                grid-column: auto;
            }

            .btn {
                width: 100%;
            }

            .attendance-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .date-info {
                white-space: normal;
            }

            .save-section {
                align-items: flex-start;
                flex-direction: column;
            }

        }


        html[dir="rtl"] body { direction: rtl; }
        html[dir="rtl"] .sidebar { left: auto; right: 0; }
        html[dir="rtl"] .main-content { margin-left: 0; margin-right: 250px; }
        html[dir="ltr"] .sidebar { left: 0; right: auto; }
        @media (max-width: 900px) { html[dir="rtl"] .main-content { margin-left: 0; margin-right: 210px; } }
        @media (max-width: 650px) { html[dir="rtl"] .main-content { margin-right: 0; } }
    </style>

</head>

<body>

<?php require_once '../../includes/sidebar.php'; ?>

<div class="attendance-page">

    <!-- ==================================================
         HEADER
    ================================================== -->

    <div class="page-header">

        <h1><?= $lang === 'ar' ? 'الحضور' : 'Attendance' ?></h1>

        <p>
            <?php if ($isInstructor): ?>
                <?= $lang === 'ar' ? 'تسجيل وإدارة الحضور للمجموعات المخصصة لك.' : 'Record and manage attendance for your assigned batches.' ?>
            <?php else: ?>
                <?= $lang === 'ar' ? 'عرض سجلات الحضور لجميع المجموعات.' : 'View attendance records across all batches.' ?>
            <?php endif; ?>
        </p>

    </div>


    <!-- ==================================================
         ALERTS
    ================================================== -->

    <?php if ($message): ?>

        <div class="alert alert-success">
            <?= htmlspecialchars($message) ?>
        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="alert alert-error">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>


    <!-- ==================================================
         BATCH + DATE FILTER
    ================================================== -->

    <div class="filter-card">

        <form
            method="GET"
            class="filter-form"
        >

            <div class="form-group">

                <label for="batch_id">
                    <?= $lang === 'ar' ? 'المجموعة' : 'Batch' ?>
                </label>

                <select
                    name="batch_id"
                    id="batch_id"
                    class="form-control"
                    required
                >

                    <option value="">
                        <?= $lang === 'ar' ? 'اختر المجموعة' : 'Select Batch' ?>
                    </option>

                    <?php foreach ($batches as $batch): ?>

                        <option
                            value="<?= (int) $batch['id'] ?>"
                            <?= $selectedBatchId === (int) $batch['id']
                                ? 'selected'
                                : '' ?>
                        >
                            <?= htmlspecialchars($batch['batch_code']) ?>
                            -
                            <?= htmlspecialchars($batch['course_name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="form-group">

                <label for="attendance_date">
                    <?= $lang === 'ar' ? 'تاريخ الحضور' : 'Attendance Date' ?>
                </label>

                <input
                    type="date"
                    name="attendance_date"
                    id="attendance_date"
                    class="form-control"
                    value="<?= htmlspecialchars($selectedDate) ?>"
                    required
                >

            </div>


            <button
                type="submit"
                class="btn btn-primary"
            >
                <?= $lang === 'ar' ? 'تحميل الطلاب' : 'Load Students' ?>
            </button>


            <a
                href="index.php"
                class="btn btn-secondary"
            >
                <?= $lang === 'ar' ? 'مسح' : 'Clear' ?>
            </a>

        </form>

    </div>


    <?php if ($selectedBatch): ?>

        <!-- ==================================================
             ATTENDANCE CARD
        ================================================== -->

        <form
            method="POST"
            class="attendance-card"
        >

            <input
                type="hidden"
                name="batch_id"
                value="<?= (int) $selectedBatchId ?>"
            >

            <input
                type="hidden"
                name="attendance_date"
                value="<?= htmlspecialchars($selectedDate) ?>"
            >


            <!-- ==============================================
                 BATCH INFORMATION
            =============================================== -->

            <div class="attendance-header">

                <div class="batch-info">

                    <h2>
                        <?= htmlspecialchars($selectedBatch['batch_code']) ?>
                    </h2>

                    <p>
                        <?= htmlspecialchars($selectedBatch['course_name']) ?>
                    </p>

                </div>


                <div class="date-info">

                    <?= $lang === 'ar' ? 'تاريخ الحضور' : 'Attendance Date' ?>:

                    <strong>
                        <?= htmlspecialchars(
                            date('d M Y', strtotime($selectedDate))
                        ) ?>
                    </strong>

                </div>

            </div>


            <!-- ==============================================
                 TABLE TITLE
            =============================================== -->

            <div class="table-title">

                <h3>
                    <?= $lang === 'ar' ? 'حضور الطلاب' : 'Student Attendance' ?>
                </h3>

                <p>
                    <?php if ($isInstructor): ?>
                        <?= $lang === 'ar' ? 'حدد حالة كل طالب: حاضر أو غائب.' : 'Mark each student as Present or Absent.' ?>
                        <?= $lang === 'ar' ? 'يمكن تعديل السجلات الموجودة.' : 'Existing records can be corrected.' ?>
                    <?php else: ?>
                        Attendance records are view-only for administrators
                        and staff.
                    <?php endif; ?>
                </p>

            </div>


            <!-- ==============================================
                 STUDENTS
            =============================================== -->

            <?php if (empty($students)): ?>

                <div class="empty-state">

                    <h3>
                        <?= $lang === 'ar' ? 'لم يتم العثور على طلاب' : 'No Students Found' ?>
                    </h3>

                    <p>
                        <?= $lang === 'ar' ? 'لا يوجد طلاب نشطون مسجلون في هذه المجموعة.' : 'There are no active students enrolled in this batch.' ?>
                    </p>

                </div>

            <?php else: ?>

                <div class="table-wrapper">

                    <table>

                        <thead>

                            <tr>

                                <th>#</th>

                                <th><?= $lang === 'ar' ? 'رقم الطالب' : 'Student ID' ?></th>

                                <th><?= $lang === 'ar' ? 'اسم الطالب' : 'Student Name' ?></th>

                                <th><?= $lang === 'ar' ? 'الجنس' : 'Gender' ?></th>

                                <th><?= $lang === 'ar' ? 'الحضور' : 'Attendance' ?></th>

                                <th><?= $lang === 'ar' ? 'ملاحظات' : 'Notes' ?></th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php foreach ($students as $index => $student): ?>

                            <?php
                            $currentStatus =
                                $student['attendance_status'] ?? '';
                            ?>

                            <tr>

                                <td class="student-number">
                                    <?= $index + 1 ?>
                                </td>

                                <td>
                                    <span class="student-code">
                                        <?= htmlspecialchars(
                                            $student['student_code']
                                        ) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="student-name">
                                        <?= htmlspecialchars(
                                            $student['full_name']
                                        ) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $student['gender']
                                    ) ?>
                                </td>

                                <td>

                                    <div class="attendance-options">

                                        <label class="attendance-option present">

                                            <input
                                                type="radio"
                                                name="status[<?= (int) $student['enrollment_id'] ?>]"
                                                value="Present"
                                                <?= $currentStatus === 'Present'
                                                    ? 'checked'
                                                    : '' ?>
                                                <?= !$isInstructor
                                                    ? 'disabled'
                                                    : '' ?>
                                            >

                                            <?= $lang === 'ar' ? 'حاضر' : 'Present' ?>

                                        </label>


                                        <label class="attendance-option absent">

                                            <input
                                                type="radio"
                                                name="status[<?= (int) $student['enrollment_id'] ?>]"
                                                value="Absent"
                                                <?= $currentStatus === 'Absent'
                                                    ? 'checked'
                                                    : '' ?>
                                                <?= !$isInstructor
                                                    ? 'disabled'
                                                    : '' ?>
                                            >

                                            <?= $lang === 'ar' ? 'غائب' : 'Absent' ?>

                                        </label>

                                    </div>

                                </td>

                                <td>

                                    <input
                                        type="text"
                                        name="notes[<?= (int) $student['enrollment_id'] ?>]"
                                        class="form-control notes-input"
                                        placeholder="<?= $lang === 'ar' ? 'ملاحظة اختيارية...' : 'Optional note...' ?>"
                                        value="<?= htmlspecialchars(
                                            $student['attendance_notes'] ?? ''
                                        ) ?>"
                                        <?= !$isInstructor
                                            ? 'disabled'
                                            : '' ?>
                                    >

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>


                <!-- ==============================================
                     FOOTER
                =============================================== -->

                <div class="save-section">

                    <div class="student-count">

                        <?= $lang === 'ar' ? 'إجمالي الطلاب:' : 'Total Students:' ?>

                        <strong>
                            <?= count($students) ?>
                        </strong>

                    </div>


                    <?php if ($isInstructor): ?>

                        <button
                            type="submit"
                            name="save_attendance"
                            value="1"
                            class="btn btn-primary"
                        >
                            <?= $lang === 'ar' ? 'حفظ الحضور' : 'Save Attendance' ?>
                        </button>

                    <?php else: ?>

                        <span class="view-only">
                            <?= $lang === 'ar' ? 'عرض فقط — لا يمكن للمسؤولين أو الموظفين تعديل الحضور.' : 'View only — ' . ($isAdmin ? 'administrators' : 'staff') . ' cannot modify attendance.' ?>
                        </span>

                    <?php endif; ?>

                </div>

            <?php endif; ?>

        </form>

    <?php else: ?>

        <!-- ==================================================
             NO BATCH SELECTED
        ================================================== -->

        <div class="attendance-card">

            <div class="empty-state">

                <h3>
                    <?= $lang === 'ar' ? 'اختر مجموعة' : 'Select a Batch' ?>
                </h3>

                <p>
                    <?= $lang === 'ar' ? 'اختر مجموعة وتاريخ حضور لعرض' : 'Choose a batch and an attendance date to view' ?>
                    <?= $lang === 'ar' ? 'سجلات الحضور.' : 'attendance records.' ?>
                </p>

            </div>

        </div>

    <?php endif; ?>

</div>

</body>
</html>
