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
    header('Location: ../../login.php');
    exit;
}

$allowedRoles = ['admin', 'staff'];

if (!in_array($_SESSION['role'], $allowedRoles, true)) {
    http_response_code(403);
    die($lang === 'ar' ? 'غير مسموح بالوصول.' : 'Access denied.');
}

/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function money($value)
{
    return number_format((float)$value, 2);
}

/*
|--------------------------------------------------------------------------
| Variables
|--------------------------------------------------------------------------
*/

$errors = [];

$studentId = '';
$batchId = '';
$discount = '0';
$status = 'active';
$notes = '';

/*
|--------------------------------------------------------------------------
| Fetch Students
|--------------------------------------------------------------------------
|
| Inactive students are excluded.
|
*/

$studentStmt = $pdo->query("
    SELECT
        id,
        student_code,
        full_name,
        phone
    FROM students
    WHERE status <> 'inactive'
    ORDER BY full_name ASC
");

$students = $studentStmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Fetch Batches
|--------------------------------------------------------------------------
|
| Planned and active batches are available for new enrollment.
|
*/

$batchStmt = $pdo->query("
    SELECT
        b.id,
        b.batch_code,
        b.start_date,
        b.end_date,
        b.capacity,
        b.status,

        c.id AS course_id,
        c.course_code,
        c.course_name,
        c.default_fee

    FROM batches b

    INNER JOIN courses c
        ON c.id = b.course_id

    WHERE
        b.status IN ('planned', 'active')
        AND c.status = 'active'

    ORDER BY
        b.start_date ASC,
        b.batch_code ASC
");

$batches = $batchStmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Handle POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $studentId = filter_input(
        INPUT_POST,
        'student_id',
        FILTER_VALIDATE_INT
    );

    $batchId = filter_input(
        INPUT_POST,
        'batch_id',
        FILTER_VALIDATE_INT
    );

    $discount = trim($_POST['discount'] ?? '0');

    $status = trim($_POST['status'] ?? 'active');

    $notes = trim($_POST['notes'] ?? '');

    /*
    |--------------------------------------------------------------------------
    | Validate Student
    |--------------------------------------------------------------------------
    */

    if (!$studentId) {
        $errors[] = $lang === 'ar' ? 'يرجى اختيار طالب.' : 'Please select a student.';
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Batch
    |--------------------------------------------------------------------------
    */

    if (!$batchId) {
        $errors[] = $lang === 'ar' ? 'يرجى اختيار دفعة.' : 'Please select a batch.';
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Status
    |--------------------------------------------------------------------------
    */

    $allowedEnrollmentStatuses = [
        'active',
        'completed',
        'suspended',
        'cancelled'
    ];

    if (!in_array($status, $allowedEnrollmentStatuses, true)) {
        $errors[] = $lang === 'ar' ? 'حالة التسجيل غير صالحة.' : 'Invalid enrollment status.';
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Discount
    |--------------------------------------------------------------------------
    */

    if ($discount === '') {
        $discount = '0';
    }

    if (!is_numeric($discount)) {
        $errors[] = $lang === 'ar' ? 'يجب أن يكون الخصم رقمًا صالحًا.' : 'Discount must be a valid number.';
    }

    $discountAmount = (float)$discount;

    if ($discountAmount < 0) {
        $errors[] = $lang === 'ar' ? 'لا يمكن أن يكون الخصم سالبًا.' : 'Discount cannot be negative.';
    }

    /*
    |--------------------------------------------------------------------------
    | Continue Validation If No Basic Errors
    |--------------------------------------------------------------------------
    */

    $student = null;
    $batch = null;

    if (empty($errors)) {

        /*
        |--------------------------------------------------------------------------
        | Get Student
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT
                id,
                student_code,
                full_name,
                status
            FROM students
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$studentId]);

        $student = $stmt->fetch();

        if (!$student) {

            $errors[] = $lang === 'ar' ? 'لم يتم العثور على الطالب المحدد.' : 'Selected student was not found.';

        } elseif ($student['status'] === 'inactive') {

            $errors[] = $lang === 'ar' ? 'لا يمكن تسجيل الطلاب غير النشطين.' : 'Inactive students cannot be enrolled.';

        }


        /*
        |--------------------------------------------------------------------------
        | Get Batch + Course
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT
                b.id,
                b.batch_code,
                b.course_id,
                b.start_date,
                b.end_date,
                b.capacity,
                b.status AS batch_status,

                c.course_code,
                c.course_name,
                c.default_fee,
                c.status AS course_status

            FROM batches b

            INNER JOIN courses c
                ON c.id = b.course_id

            WHERE b.id = ?

            LIMIT 1
        ");

        $stmt->execute([$batchId]);

        $batch = $stmt->fetch();

        if (!$batch) {

            $errors[] = $lang === 'ar' ? 'لم يتم العثور على الدفعة المحددة.' : 'Selected batch was not found.';

        } else {

            /*
            |--------------------------------------------------------------------------
            | Batch Status
            |--------------------------------------------------------------------------
            */

            if (
                !in_array(
                    $batch['batch_status'],
                    ['planned', 'active'],
                    true
                )
            ) {

                $errors[] = $lang === 'ar'
                    ? 'يمكن تسجيل الطلاب فقط في الدفعات المخططة أو النشطة.'
                    : 'Students can only be enrolled in planned or active batches.';

            }


            /*
            |--------------------------------------------------------------------------
            | Course Status
            |--------------------------------------------------------------------------
            */

            if ($batch['course_status'] !== 'active') {

                $errors[] = $lang === 'ar'
                    ? 'المقرر المرتبط بهذه الدفعة غير نشط.'
                    : 'The course assigned to this batch is inactive.';

            }

        }


        /*
        |--------------------------------------------------------------------------
        | Check Duplicate Enrollment
        |--------------------------------------------------------------------------
        */

        if (empty($errors)) {

            $stmt = $pdo->prepare("
                SELECT id
                FROM enrollments
                WHERE
                    student_id = ?
                    AND batch_id = ?
                LIMIT 1
            ");

            $stmt->execute([
                $studentId,
                $batchId
            ]);

            $existingEnrollment = $stmt->fetch();

            if ($existingEnrollment) {

                $errors[] = $lang === 'ar'
                    ? 'هذا الطالب مسجل بالفعل في الدفعة المحددة.'
                    : 'This student is already enrolled in the selected batch.';

            }

        }


        /*
        |--------------------------------------------------------------------------
        | Check Batch Capacity
        |--------------------------------------------------------------------------
        */

        if (empty($errors)) {

            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM enrollments
                WHERE
                    batch_id = ?
                    AND status IN ('active', 'suspended')
            ");

            $stmt->execute([$batchId]);

            $currentEnrollmentCount =
                (int)$stmt->fetchColumn();

            $capacity =
                (int)$batch['capacity'];

            if (
                $capacity > 0
                && $currentEnrollmentCount >= $capacity
            ) {

                $errors[] = $lang === 'ar'
                    ? 'لقد وصلت هذه الدفعة إلى سعتها القصوى.'
                    : 'This batch has reached its maximum capacity.';

            }

        }


        /*
        |--------------------------------------------------------------------------
        | Calculate Fee
        |--------------------------------------------------------------------------
        */

        if (empty($errors)) {

            $originalFee =
                (float)$batch['default_fee'];

            if ($discountAmount > $originalFee) {

                $errors[] = $lang === 'ar'
                    ? 'لا يمكن أن يكون الخصم أكبر من الرسوم الأصلية للمقرر.'
                    : 'Discount cannot be greater than the original course fee.';

            }

            $actualFee =
                $originalFee - $discountAmount;

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Insert Enrollment
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        try {

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO enrollments (
                    student_id,
                    batch_id,
                    enrollment_date,
                    original_fee,
                    discount,
                    actual_fee,
                    status,
                    notes
                )
                VALUES (
                    ?,
                    ?,
                    CURDATE(),
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");

            $stmt->execute([
                $studentId,
                $batchId,
                $originalFee,
                $discountAmount,
                $actualFee,
                $status,
                $notes !== '' ? $notes : null
            ]);

            $pdo->commit();

            header('Location: index.php?success=1');
            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] = $lang === 'ar'
                ? 'فشل إنشاء التسجيل. يرجى المحاولة مرة أخرى.'
                : 'Failed to create enrollment. Please try again.';

        }

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

    <title><?= $lang === 'ar' ? 'إضافة تسجيل - مركز العلماء للتدريب' : 'Add Enrollment - ALAW LAMA Training Center' ?></title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;

            font-family:
                Georgia,
                "Times New Roman",
                serif;

            background: #ffffff;

            color: #111827;
        }

        /*
        |--------------------------------------------------------------------------
        | SIDEBAR
        |--------------------------------------------------------------------------
        */

        .sidebar {
            position: fixed;

            top: 0;
            left: 0;

            width: 250px;
            height: 100vh;

            background: #111827;

            color: #ffffff;

            padding: 30px 18px;

            overflow-y: auto;

            z-index: 1000;
        }

        .brand {
            padding: 0 12px 22px;

            border-bottom:
                1px solid #374151;

            margin-bottom: 25px;
        }

        .brand h2 {
            margin: 0;

            font-size: 21px;

            color: #ffffff;
        }

        .brand p {
            margin: 8px 0 0;

            font-size: 12px;

            color: #9ca3af;
        }

        .menu-section {
            margin-bottom: 25px;
        }

        .menu-title {
            padding:
                0 12px 10px;

            font-size: 12px;

            font-weight: bold;

            color: #9ca3af;

            text-transform: uppercase;

            letter-spacing: 0.5px;
        }

        .sidebar a {
            display: block;

            padding: 10px 12px;

            margin-bottom: 3px;

            color: #f3f4f6;

            text-decoration: none;

            border-radius: 7px;

            font-size: 15px;

            transition: 0.2s;
        }

        .sidebar a:hover {
            background: #1f2937;
        }

        html[dir="rtl"] .sidebar {
            left: auto;
            right: 0;
        }

        html[dir="rtl"] .main-content {
            margin-left: 0;
            margin-right: 250px;
        }


        .sidebar a.active {
            background: #2563eb;

            color: #ffffff;

            font-weight: bold;
        }

        /*
        |--------------------------------------------------------------------------
        | MAIN CONTENT
        |--------------------------------------------------------------------------
        */

        .main-content {
            margin-left: 250px;

            width:
                calc(100% - 250px);

            /*
             * IMPORTANT:
             * No min-height: 100vh.
             */
            padding:
                35px 40px;
        }

        /*
        |--------------------------------------------------------------------------
        | PAGE HEADER
        |--------------------------------------------------------------------------
        */

        .page-header {
            display: flex;

            justify-content:
                space-between;

            align-items:
                flex-start;

            gap: 20px;

            margin-bottom: 28px;
        }

        .page-title h1 {
            margin: 0 0 7px;

            font-size: 34px;

            color: #111827;
        }

        .page-title p {
            margin: 0;

            font-size: 17px;

            color: #6b7280;
        }

        .header-actions {
            display: flex;

            align-items: center;

            gap: 10px;

            flex-wrap: wrap;
        }

        /*
        |--------------------------------------------------------------------------
        | BUTTONS
        |--------------------------------------------------------------------------
        */

        .btn {
            display: inline-flex;

            align-items: center;

            justify-content: center;

            padding:
                12px 18px;

            border-radius: 8px;

            text-decoration: none;

            font-size: 14px;

            font-weight: 600;

            cursor: pointer;

            border:
                1px solid transparent;

            white-space: nowrap;

            font-family: inherit;
        }

        .btn-primary {
            background: #2563eb;

            color: #ffffff;

            border-color: #2563eb;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-secondary {
            background: #ffffff;

            color: #374151;

            border-color: #d1d5db;
        }

        .btn-secondary:hover {
            background: #f3f4f6;
        }

        /*
        |--------------------------------------------------------------------------
        | FORM CARD
        |--------------------------------------------------------------------------
        */

        .form-card {
            width: 100%;

            max-width: 1050px;

            background: #ffffff;

            border:
                1px solid #f0f0f0;

            border-radius: 12px;

            box-shadow:
                0 4px 20px
                rgba(0, 0, 0, 0.06);

            overflow: hidden;
        }

        .card-header {
            padding:
                20px 25px;

            border-bottom:
                1px solid #e5e7eb;

            background: #fafafa;
        }

        .card-header h2 {
            margin: 0;

            font-size: 20px;

            color: #111827;
        }

        .card-header p {
            margin:
                6px 0 0;

            font-size: 14px;

            color: #6b7280;
        }

        .card-body {
            padding: 28px;
        }

        /*
        |--------------------------------------------------------------------------
        | ERROR BOX
        |--------------------------------------------------------------------------
        */

        .error-box {
            margin-bottom: 25px;

            padding: 15px 18px;

            background: #fef2f2;

            border:
                1px solid #fecaca;

            border-radius: 8px;

            color: #991b1b;
        }

        .error-box strong {
            display: block;

            margin-bottom: 7px;
        }

        .error-box ul {
            margin:
                0 0 0 18px;

            padding: 0;
        }

        .error-box li {
            margin-bottom: 4px;
        }

        /*
        |--------------------------------------------------------------------------
        | FORM GRID
        |--------------------------------------------------------------------------
        */

        .form-grid {
            display: grid;

            grid-template-columns:
                repeat(2, minmax(0, 1fr));

            gap:
                22px;
        }

        .form-group {
            min-width: 0;
        }

        .form-group.full-width {
            grid-column:
                1 / -1;
        }

        .form-group label {
            display: block;

            margin-bottom: 8px;

            font-size: 14px;

            font-weight: bold;

            color: #374151;
        }

        .required {
            color: #dc2626;
        }

        input,
        select,
        textarea {
            width: 100%;

            border:
                1px solid #d1d5db;

            border-radius: 8px;

            background: #ffffff;

            color: #111827;

            font-family: inherit;

            font-size: 15px;

            outline: none;
        }

        input,
        select {
            height: 48px;

            padding:
                0 14px;
        }

        textarea {
            min-height: 120px;

            padding:
                12px 14px;

            resize: vertical;
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: #2563eb;

            box-shadow:
                0 0 0 3px
                rgba(37, 99, 235, 0.10);
        }

        /*
        |--------------------------------------------------------------------------
        | FEE PREVIEW
        |--------------------------------------------------------------------------
        */

        .fee-card {
            grid-column:
                1 / -1;

            padding: 20px;

            background: #f9fafb;

            border:
                1px solid #e5e7eb;

            border-radius: 9px;
        }

        .fee-grid {
            display: grid;

            grid-template-columns:
                repeat(3, minmax(0, 1fr));

            gap: 15px;
        }

        .fee-item {
            padding: 15px;

            background: #ffffff;

            border:
                1px solid #e5e7eb;

            border-radius: 8px;
        }

        .fee-label {
            font-size: 12px;

            color: #6b7280;

            font-weight: bold;

            text-transform: uppercase;

            margin-bottom: 6px;
        }

        .fee-value {
            font-size: 20px;

            font-weight: bold;

            color: #111827;
        }

        /*
        |--------------------------------------------------------------------------
        | FORM ACTIONS
        |--------------------------------------------------------------------------
        */

        .form-actions {
            display: flex;

            justify-content:
                flex-end;

            gap: 10px;

            margin-top: 28px;

            padding-top: 22px;

            border-top:
                1px solid #e5e7eb;
        }

        /*
        |--------------------------------------------------------------------------
        | HELP TEXT
        |--------------------------------------------------------------------------
        */

        .help-text {
            margin-top: 6px;

            font-size: 12px;

            color: #6b7280;

            line-height: 1.5;
        }

        /*
        |--------------------------------------------------------------------------
        | RESPONSIVE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 900px) {

            .sidebar {
                width: 210px;
            }

            .main-content {
                margin-left: 210px;

                width:
                    calc(100% - 210px);

                padding:
                    25px;
            }

            html[dir="rtl"] .main-content {
                margin-left: 0;
                margin-right: 210px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full-width {
                grid-column:
                    auto;
            }

            .fee-card {
                grid-column:
                    auto;
            }

            .fee-grid {
                grid-template-columns: 1fr;
            }

            .page-header {
                flex-direction: column;
            }

        }

        @media (max-width: 600px) {

            .sidebar {
                display: none;
            }

            .main-content {
                margin-left: 0;

                width: 100%;

                padding:
                    20px 15px;
            }

            html[dir="rtl"] .main-content {
                margin-right: 0;
            }

            .page-title h1 {
                font-size: 27px;
            }

            .page-title p {
                font-size: 15px;
            }

            .header-actions {
                width: 100%;
            }

            .header-actions .btn {
                flex: 1;
            }

            .card-body {
                padding: 20px;
            }

            .form-actions {
                flex-direction: column-reverse;
            }

            .form-actions .btn {
                width: 100%;
            }

        }

    </style>

</head>

<body>


<!-- ================================================================
     SIDEBAR
================================================================ -->

<aside class="sidebar">

    <div class="brand">

        <h2>
            ALAW LAMA
        </h2>

        <p>
            <?= $lang === 'ar' ? 'إدارة مركز التدريب' : 'Training Center Management' ?>
        </p>

    </div>

    <div style="display:flex; gap:6px; margin:0 12px 20px;">
        <a href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['lang' => 'en']))) ?>"
           style="padding:7px 10px; margin:0; font-size:12px; text-align:center; background:<?= $lang === 'en' ? '#2563eb' : '#1f2937' ?>;">
            English
        </a>
        <a href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['lang' => 'ar']))) ?>"
           style="padding:7px 10px; margin:0; font-size:12px; text-align:center; background:<?= $lang === 'ar' ? '#2563eb' : '#1f2937' ?>;">
            العربية
        </a>
    </div>


    <!-- MAIN -->

    <div class="menu-section">

        <div class="menu-title">
            <?= $lang === 'ar' ? 'الرئيسية' : 'Main' ?>
        </div>

        <a href="../dashboard/index.php">
            <?= $lang === 'ar' ? 'لوحة التحكم' : 'Dashboard' ?>
        </a>

    </div>


    <!-- MANAGEMENT -->

    <div class="menu-section">

        <div class="menu-title">
            <?= $lang === 'ar' ? 'الإدارة' : 'Management' ?>
        </div>

        <a href="../students/index.php">
            <?= $lang === 'ar' ? 'الطلاب' : 'Students' ?>
        </a>

        <a href="../instructors/index.php">
            <?= $lang === 'ar' ? 'المدرسون' : 'Instructors' ?>
        </a>

        <a href="../courses/index.php">
            <?= $lang === 'ar' ? 'المقررات' : 'Courses' ?>
        </a>

        <a href="../batches/index.php">
            <?= $lang === 'ar' ? 'الدفعات' : 'Batches' ?>
        </a>

        <a
            href="index.php"
            class="active"
        >
            <?= $lang === 'ar' ? 'التسجيلات' : 'Enrollments' ?>
        </a>

        <a href="../payments/index.php">
            <?= $lang === 'ar' ? 'المدفوعات' : 'Payments' ?>
        </a>

        <a href="../attendance/index.php">
            <?= $lang === 'ar' ? 'الحضور' : 'Attendance' ?>
        </a>

        <a href="../assessments/index.php">
            <?= $lang === 'ar' ? 'التقييمات والدرجات' : 'Assessments & Grades' ?>
        </a>

    </div>


    <!-- REPORTS -->

    <div class="menu-section">

        <div class="menu-title">
            <?= $lang === 'ar' ? 'التقارير' : 'Reports' ?>
        </div>

        <a href="../reports/index.php">
            <?= $lang === 'ar' ? 'التقارير' : 'Reports' ?>
        </a>

    </div>


    <!-- ADMINISTRATION -->

    <?php if ($_SESSION['role'] === 'admin'): ?>

        <div class="menu-section">

            <div class="menu-title">
                <?= $lang === 'ar' ? 'إدارة النظام' : 'Administration' ?>
            </div>

            <a href="../users/index.php">
                <?= $lang === 'ar' ? 'إدارة المستخدمين' : 'Manage Users' ?>
            </a>

        </div>

    <?php endif; ?>


    <!-- ACCOUNT -->

    <div class="menu-section">

        <div class="menu-title">
            <?= $lang === 'ar' ? 'الحساب' : 'Account' ?>
        </div>

        <a href="../../change-password.php">
            <?= $lang === 'ar' ? 'تغيير كلمة المرور' : 'Change Password' ?>
        </a>

        <a href="../../logout.php">
            <?= $lang === 'ar' ? 'تسجيل الخروج' : 'Logout' ?>
        </a>

    </div>

</aside>


<!-- ================================================================
     MAIN CONTENT
================================================================ -->

<main class="main-content">


    <!-- ============================================================
         PAGE HEADER
    ============================================================= -->

    <div class="page-header">

        <div class="page-title">

            <h1>
                <?= $lang === 'ar' ? 'إضافة تسجيل' : 'Add Enrollment' ?>
            </h1>

            <p>
                <?= $lang === 'ar' ? 'تسجيل طالب في دفعة تدريبية.' : 'Enroll a student in a training batch.' ?>
            </p>

        </div>


        <div class="header-actions">

            <a
                href="index.php"
                class="btn btn-secondary"
            >
                ← <?= $lang === 'ar' ? 'رجوع' : 'Back' ?>
            </a>

        </div>

    </div>


    <!-- ============================================================
         FORM CARD
    ============================================================= -->

    <div class="form-card">


        <div class="card-header">

            <h2>
                <?= $lang === 'ar' ? 'معلومات التسجيل' : 'Enrollment Information' ?>
            </h2>

            <p>
                <?= $lang === 'ar' ? 'اختر طالبًا ودفعة، ثم راجع رسوم التسجيل.' : 'Select a student and batch, then review the enrollment fee.' ?>
            </p>

        </div>


        <div class="card-body">


            <!-- ====================================================
                 ERRORS
            ===================================================== -->

            <?php if (!empty($errors)): ?>

                <div class="error-box">

                    <strong>
                        <?= $lang === 'ar' ? 'يرجى تصحيح الأخطاء التالية:' : 'Please fix the following:' ?>
                    </strong>

                    <ul>

                        <?php foreach ($errors as $error): ?>

                            <li>
                                <?= e($error) ?>
                            </li>

                        <?php endforeach; ?>

                    </ul>

                </div>

            <?php endif; ?>


            <!-- ====================================================
                 FORM
            ===================================================== -->

            <form
                method="POST"
                action=""
                id="enrollmentForm"
            >


                <div class="form-grid">


                    <!-- ==========================================
                         STUDENT
                    =========================================== -->

                    <div class="form-group">

                        <label for="student_id">

                            <?= $lang === 'ar' ? 'الطالب' : 'Student' ?>

                            <span class="required">
                                *
                            </span>

                        </label>

                        <select
                            name="student_id"
                            id="student_id"
                            required
                        >

                            <option value="">
                                <?= $lang === 'ar' ? 'اختر الطالب' : 'Select Student' ?>
                            </option>

                            <?php foreach ($students as $student): ?>

                                <option
                                    value="<?= (int)$student['id'] ?>"
                                    <?= (string)$studentId === (string)$student['id']
                                        ? 'selected'
                                        : '' ?>
                                >

                                    <?= e($student['student_code']) ?>

                                    —
                                    <?= e($student['full_name']) ?>

                                    <?php if (!empty($student['phone'])): ?>

                                        —
                                        <?= e($student['phone']) ?>

                                    <?php endif; ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                        <div class="help-text">

                            <?= $lang === 'ar' ? 'الطلاب غير النشطين غير متاحين للتسجيل.' : 'Inactive students are not available for enrollment.' ?>

                        </div>

                    </div>


                    <!-- ==========================================
                         BATCH
                    =========================================== -->

                    <div class="form-group">

                        <label for="batch_id">

                            <?= $lang === 'ar' ? 'الدفعة' : 'Batch' ?>

                            <span class="required">
                                *
                            </span>

                        </label>

                        <select
                            name="batch_id"
                            id="batch_id"
                            required
                        >

                            <option value="">
                                <?= $lang === 'ar' ? 'اختر الدفعة' : 'Select Batch' ?>
                            </option>

                            <?php foreach ($batches as $batch): ?>

                                <option
                                    value="<?= (int)$batch['id'] ?>"
                                    data-fee="<?= e($batch['default_fee']) ?>"
                                    <?= (string)$batchId === (string)$batch['id']
                                        ? 'selected'
                                        : '' ?>
                                >

                                    <?= e($batch['batch_code']) ?>

                                    —
                                    <?= e($batch['course_name']) ?>

                                    (<?= e($batch['course_code']) ?>)

                                </option>

                            <?php endforeach; ?>

                        </select>

                        <div class="help-text">

                            <?= $lang === 'ar' ? 'الدفعات المخططة والنشطة فقط متاحة.' : 'Only planned and active batches are available.' ?>

                        </div>

                    </div>


                    <!-- ==========================================
                         DISCOUNT
                    =========================================== -->

                    <div class="form-group">

                        <label for="discount">
                            <?= $lang === 'ar' ? 'الخصم' : 'Discount' ?>
                        </label>

                        <input
                            type="number"
                            name="discount"
                            id="discount"
                            value="<?= e($discount) ?>"
                            min="0"
                            step="0.01"
                            placeholder="0.00"
                        >

                        <div class="help-text">

                            <?= $lang === 'ar' ? 'أدخل قيمة خصم ثابتة.' : 'Enter a fixed discount amount.' ?>

                        </div>

                    </div>


                    <!-- ==========================================
                         STATUS
                    =========================================== -->

                    <div class="form-group">

                        <label for="status">

                            <?= $lang === 'ar' ? 'حالة التسجيل' : 'Enrollment Status' ?>

                            <span class="required">
                                *
                            </span>

                        </label>

                        <select
                            name="status"
                            id="status"
                            required
                        >

                            <option
                                value="active"
                                <?= $status === 'active'
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= $lang === 'ar' ? 'نشط' : 'Active' ?>
                            </option>

                            <option
                                value="completed"
                                <?= $status === 'completed'
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= $lang === 'ar' ? 'مكتمل' : 'Completed' ?>
                            </option>

                            <option
                                value="suspended"
                                <?= $status === 'suspended'
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= $lang === 'ar' ? 'موقوف' : 'Suspended' ?>
                            </option>

                            <option
                                value="cancelled"
                                <?= $status === 'cancelled'
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= $lang === 'ar' ? 'ملغى' : 'Cancelled' ?>
                            </option>

                        </select>

                    </div>


                    <!-- ==========================================
                         FEE PREVIEW
                    =========================================== -->

                    <div class="fee-card">

                        <div class="fee-grid">


                            <div class="fee-item">

                                <div class="fee-label">
                                    <?= $lang === 'ar' ? 'الرسوم الأصلية' : 'Original Fee' ?>
                                </div>

                                <div
                                    class="fee-value"
                                    id="originalFee"
                                >
                                    0.00
                                </div>

                            </div>


                            <div class="fee-item">

                                <div class="fee-label">
                                    <?= $lang === 'ar' ? 'الخصم' : 'Discount' ?>
                                </div>

                                <div
                                    class="fee-value"
                                    id="discountPreview"
                                >
                                    0.00
                                </div>

                            </div>


                            <div class="fee-item">

                                <div class="fee-label">
                                    <?= $lang === 'ar' ? 'الرسوم الفعلية' : 'Actual Fee' ?>
                                </div>

                                <div
                                    class="fee-value"
                                    id="actualFee"
                                >
                                    0.00
                                </div>

                            </div>


                        </div>

                    </div>


                    <!-- ==========================================
                         NOTES
                    =========================================== -->

                    <div class="form-group full-width">

                        <label for="notes">
                            <?= $lang === 'ar' ? 'ملاحظات' : 'Notes' ?>
                        </label>

                        <textarea
                            name="notes"
                            id="notes"
                            placeholder="<?= $lang === 'ar' ? 'ملاحظات تسجيل اختيارية...' : 'Optional enrollment notes...' ?>"
                        ><?= e($notes) ?></textarea>

                    </div>


                </div>


                <!-- =================================================
                     FORM ACTIONS
                ================================================== -->

                <div class="form-actions">

                    <a
                        href="index.php"
                        class="btn btn-secondary"
                    >
                        <?= $lang === 'ar' ? 'إلغاء' : 'Cancel' ?>
                    </a>

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        <?= $lang === 'ar' ? 'حفظ التسجيل' : 'Save Enrollment' ?>
                    </button>

                </div>


            </form>


        </div>

    </div>


</main>


<!-- ================================================================
     JAVASCRIPT
================================================================ -->

<script>

    const batchSelect =
        document.getElementById('batch_id');

    const discountInput =
        document.getElementById('discount');

    const originalFeeElement =
        document.getElementById('originalFee');

    const discountPreviewElement =
        document.getElementById('discountPreview');

    const actualFeeElement =
        document.getElementById('actualFee');


    function updateFeePreview() {

        const selectedOption =
            batchSelect.options[
                batchSelect.selectedIndex
            ];


        let originalFee = 0;

        if (selectedOption) {

            originalFee =
                parseFloat(
                    selectedOption.dataset.fee
                ) || 0;

        }


        let discount =
            parseFloat(
                discountInput.value
            ) || 0;


        if (discount < 0) {
            discount = 0;
        }


        if (discount > originalFee) {
            discount = originalFee;
        }


        const actualFee =
            originalFee - discount;


        originalFeeElement.textContent =
            originalFee.toFixed(2);


        discountPreviewElement.textContent =
            discount.toFixed(2);


        actualFeeElement.textContent =
            actualFee.toFixed(2);

    }


    batchSelect.addEventListener(
        'change',
        updateFeePreview
    );


    discountInput.addEventListener(
        'input',
        updateFeePreview
    );


    updateFeePreview();

</script>


</body>

</html>