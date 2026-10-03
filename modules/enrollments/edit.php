<?php
session_start();

require_once '../../config/database.php';
require_once '../../includes/language.php';

// Only Admin and Staff
if (
    !isset($_SESSION['user_id']) ||
    !in_array($_SESSION['role'], ['admin', 'staff'], true)
) {
    header('Location: ../../login.php');
    exit;
}

$enrollmentId = $_GET['id'] ?? $_POST['id'] ?? '';

if ($enrollmentId === '' || !ctype_digit($enrollmentId)) {
    header('Location: index.php');
    exit;
}

$enrollmentId = (int)$enrollmentId;

$errors = [];

$student_id = '';
$batch_id = '';
$enrollment_date = '';
$original_fee = '';
$discount = '0';
$actual_fee = '';
$status = 'active';
$notes = '';

$allowedStatuses = [
    'active',
    'completed',
    'suspended',
    'cancelled'
];

/*
|--------------------------------------------------------------------------
| Load Current Enrollment
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        e.id,
        e.student_id,
        e.batch_id,
        e.enrollment_date,
        e.original_fee,
        e.discount,
        e.actual_fee,
        e.status,
        e.notes,

        s.student_code,
        s.full_name AS student_name,

        b.batch_code,
        b.start_date,
        b.end_date,
        b.capacity,
        b.status AS batch_status,

        c.course_code,
        c.course_name,
        c.default_fee

    FROM enrollments e

    INNER JOIN students s
        ON e.student_id = s.id

    INNER JOIN batches b
        ON e.batch_id = b.id

    INNER JOIN courses c
        ON b.course_id = c.id

    WHERE e.id = :id
    LIMIT 1
");

$stmt->execute([
    'id' => $enrollmentId
]);

$enrollment = $stmt->fetch();

if (!$enrollment) {
    header('Location: index.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Existing Values
|--------------------------------------------------------------------------
*/

$student_id = (string)$enrollment['student_id'];
$batch_id = (string)$enrollment['batch_id'];
$enrollment_date = $enrollment['enrollment_date'];
$original_fee = (string)(float)$enrollment['default_fee'];
$discount = (string)(float)$enrollment['discount'];
$actual_fee = (string)(float)$enrollment['actual_fee'];
$status = $enrollment['status'];
$notes = $enrollment['notes'] ?? '';

/*
|--------------------------------------------------------------------------
| Fetch Students
|--------------------------------------------------------------------------
|
| Include the current student even if their current status changed.
|--------------------------------------------------------------------------
*/

$stmtStudents = $pdo->query("
    SELECT
        id,
        student_code,
        full_name,
        status

    FROM students

    WHERE status != 'Inactive'
       OR id = " . (int)$student_id . "

    ORDER BY full_name ASC
");

$students = $stmtStudents->fetchAll();

/*
|--------------------------------------------------------------------------
| Fetch Batches
|--------------------------------------------------------------------------
|
| Include the current batch even if its status is completed/cancelled.
|--------------------------------------------------------------------------
*/

$stmtBatches = $pdo->query("
    SELECT
        b.id,
        b.batch_code,
        b.start_date,
        b.end_date,
        b.capacity,
        b.status,

        c.course_code,
        c.course_name,
        c.default_fee,

        (
            SELECT COUNT(*)
            FROM enrollments e2
            WHERE e2.batch_id = b.id
              AND e2.status != 'cancelled'
              AND e2.id != " . $enrollmentId . "
        ) AS enrolled_count

    FROM batches b

    INNER JOIN courses c
        ON b.course_id = c.id

    WHERE (
        b.status IN ('planned', 'active')
        AND c.status = 'active'
    )
    OR b.id = " . (int)$batch_id . "

    ORDER BY b.start_date ASC, b.batch_code ASC
");

$batches = $stmtBatches->fetchAll();

/*
|--------------------------------------------------------------------------
| POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $student_id = trim($_POST['student_id'] ?? '');
    $batch_id = trim($_POST['batch_id'] ?? '');
    $enrollment_date = trim($_POST['enrollment_date'] ?? '');
    $discount = trim($_POST['discount'] ?? '0');
    $status = trim($_POST['status'] ?? 'active');
    $notes = trim($_POST['notes'] ?? '');

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if (
        $student_id === '' ||
        !ctype_digit($student_id)
    ) {
        $errors[] = $lang === 'ar' ? 'يرجى اختيار طالب.' : 'Please select a student.';
    }

    if (
        $batch_id === '' ||
        !ctype_digit($batch_id)
    ) {
        $errors[] = $lang === 'ar' ? 'يرجى اختيار دفعة.' : 'Please select a batch.';
    }

    if ($enrollment_date === '') {

        $errors[] =
            $lang === 'ar' ? 'تاريخ التسجيل مطلوب.' : 'Enrollment date is required.';

    } else {

        $dateObject =
            DateTime::createFromFormat(
                'Y-m-d',
                $enrollment_date
            );

        if (
            !$dateObject ||
            $dateObject->format('Y-m-d') !==
            $enrollment_date
        ) {
            $errors[] =
                $lang === 'ar' ? 'تاريخ التسجيل غير صالح.' : 'Invalid enrollment date.';
        }
    }

    if (
        !is_numeric($discount) ||
        (float)$discount < 0
    ) {
        $errors[] =
            $lang === 'ar' ? 'يجب أن يكون الخصم مبلغًا صالحًا.' : 'Discount must be a valid amount.';
    }

    if (
        !in_array(
            $status,
            $allowedStatuses,
            true
        )
    ) {
        $errors[] =
            $lang === 'ar' ? 'حالة التسجيل غير صالحة.' : 'Invalid enrollment status.';
    }

    /*
    |--------------------------------------------------------------------------
    | Get Student
    |--------------------------------------------------------------------------
    */

    $student = null;

    if (
        $student_id !== '' &&
        ctype_digit($student_id)
    ) {

        $stmt = $pdo->prepare("
            SELECT
                id,
                student_code,
                full_name,
                status

            FROM students

            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => (int)$student_id
        ]);

        $student = $stmt->fetch();

        if (!$student) {

            $errors[] =
                $lang === 'ar' ? 'الطالب المحدد غير موجود.' : 'Selected student was not found.';

        } elseif (
            $student['status'] === 'Inactive'
        ) {

            $errors[] =
                $lang === 'ar' ? 'لا يمكن تسجيل الطلاب غير النشطين.' : 'Inactive students cannot be enrolled.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Get Batch
    |--------------------------------------------------------------------------
    */

    $batch = null;

    if (
        $batch_id !== '' &&
        ctype_digit($batch_id)
    ) {

        $stmt = $pdo->prepare("
            SELECT
                b.id,
                b.batch_code,
                b.start_date,
                b.end_date,
                b.capacity,
                b.status,

                c.course_code,
                c.course_name,
                c.default_fee,
                c.status AS course_status

            FROM batches b

            INNER JOIN courses c
                ON b.course_id = c.id

            WHERE b.id = :id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => (int)$batch_id
        ]);

        $batch = $stmt->fetch();

        if (!$batch) {

            $errors[] =
                $lang === 'ar' ? 'الدفعة المحددة غير موجودة.' : 'Selected batch was not found.';

        } else {

            /*
            | If changing to a different batch,
            | it must be planned or active.
            */

            if (
                (int)$batch_id !==
                (int)$enrollment['batch_id']
            ) {

                if (
                    !in_array(
                        $batch['status'],
                        ['planned', 'active'],
                        true
                    )
                ) {
                    $errors[] =
                        $lang === 'ar' ? 'يمكن نقل التسجيل فقط إلى دفعة مخططة أو نشطة.' : 'You can only move an enrollment to a planned or active batch.';
                }

                if (
                    $batch['course_status'] !== 'active'
                ) {
                    $errors[] =
                        $lang === 'ar' ? 'الدفعة المحددة تابعة لمقرر غير نشط.' : 'The selected batch belongs to an inactive course.';
                }
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Original Fee
    |--------------------------------------------------------------------------
    |
    | Always get the current fee from the selected Course.
    |--------------------------------------------------------------------------
    */

    if ($batch) {

        $original_fee_value =
            (float)$batch['default_fee'];

    } else {

        $original_fee_value = 0;
    }

    /*
    |--------------------------------------------------------------------------
    | Discount
    |--------------------------------------------------------------------------
    */

    $discount_value =
        is_numeric($discount)
            ? (float)$discount
            : 0;

    if (
        $discount_value >
        $original_fee_value
    ) {

        $errors[] =
            $lang === 'ar' ? 'لا يمكن أن يكون الخصم أكبر من الرسوم الأصلية.' : 'Discount cannot be greater than the original fee.';
    }

    /*
    |--------------------------------------------------------------------------
    | Actual Fee
    |--------------------------------------------------------------------------
    */

    $actual_fee_value =
        max(
            0,
            $original_fee_value -
            $discount_value
        );

    $original_fee =
        (string)$original_fee_value;

    $actual_fee =
        (string)$actual_fee_value;

    /*
    |--------------------------------------------------------------------------
    | Duplicate Enrollment Check
    |--------------------------------------------------------------------------
    */

    if (
        empty($errors) &&
        $student &&
        $batch
    ) {

        $stmt = $pdo->prepare("
            SELECT
                id,
                status

            FROM enrollments

            WHERE student_id = :student_id
              AND batch_id = :batch_id
              AND id != :id

            LIMIT 1
        ");

        $stmt->execute([
            'student_id' => (int)$student_id,
            'batch_id' => (int)$batch_id,
            'id' => $enrollmentId
        ]);

        $duplicate = $stmt->fetch();

        if ($duplicate) {

            $errors[] =
                $lang === 'ar' ? 'هذا الطالب مسجل بالفعل في الدفعة المحددة.' : 'This student is already enrolled in the selected batch.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Capacity Check
    |--------------------------------------------------------------------------
    */

    if (
        empty($errors) &&
        $batch
    ) {

        $stmt = $pdo->prepare("
            SELECT COUNT(*)

            FROM enrollments

            WHERE batch_id = :batch_id
              AND status != 'cancelled'
              AND id != :id
        ");

        $stmt->execute([
            'batch_id' => (int)$batch_id,
            'id' => $enrollmentId
        ]);

        $enrolledCount =
            (int)$stmt->fetchColumn();

        if (
            (int)$batch['capacity'] > 0 &&
            $enrolledCount >=
            (int)$batch['capacity']
        ) {

            $errors[] =
                $lang === 'ar' ? 'وصلت الدفعة المحددة إلى الحد الأقصى للسعة.' : 'The selected batch has reached its maximum capacity.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Update Enrollment
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        try {

            $pdo->beginTransaction();

            /*
            | Final duplicate check
            */

            $stmt = $pdo->prepare("
                SELECT id

                FROM enrollments

                WHERE student_id = :student_id
                  AND batch_id = :batch_id
                  AND id != :id

                LIMIT 1
            ");

            $stmt->execute([
                'student_id' => (int)$student_id,
                'batch_id' => (int)$batch_id,
                'id' => $enrollmentId
            ]);

            if ($stmt->fetch()) {

                throw new Exception(
                    $lang === 'ar' ? 'هذا الطالب مسجل بالفعل في الدفعة المحددة.' : 'This student is already enrolled in the selected batch.'
                );
            }

            /*
            | Update
            */

            $stmt = $pdo->prepare("
                UPDATE enrollments

                SET
                    student_id = :student_id,
                    batch_id = :batch_id,
                    enrollment_date = :enrollment_date,
                    original_fee = :original_fee,
                    discount = :discount,
                    actual_fee = :actual_fee,
                    status = :status,
                    notes = :notes,
                    updated_at = NOW()

                WHERE id = :id
            ");

            $stmt->execute([
                'student_id' =>
                    (int)$student_id,

                'batch_id' =>
                    (int)$batch_id,

                'enrollment_date' =>
                    $enrollment_date,

                'original_fee' =>
                    $original_fee_value,

                'discount' =>
                    $discount_value,

                'actual_fee' =>
                    $actual_fee_value,

                'status' =>
                    $status,

                'notes' =>
                    $notes !== ''
                        ? $notes
                        : null,

                'id' =>
                    $enrollmentId
            ]);

            $pdo->commit();

            header(
                'Location: view.php?id=' .
                $enrollmentId .
                '&updated=1'
            );

            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] =
                ($lang === 'ar' ? 'فشل تحديث التسجيل: ' : 'Failed to update enrollment: ') .
                $e->getMessage();
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

    <title>
        <?= $lang === 'ar' ? 'تعديل التسجيل - مركز العلماء للتدريب' : 'Edit Enrollment - ALAWLAMA TRAINING CENTER' ?>
    </title>

    <link
        rel="stylesheet"
        href="../../assets/css/style.css"
    >

    <style>

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
            border-bottom: 1px solid #374151;
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
            padding: 0 12px 10px;

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
            width: calc(100% - 250px);

            /*
             * IMPORTANT:
             * No min-height: 100vh here.
             * This removes the unnecessary empty vertical space.
             */
            padding: 35px 40px;
        }


        .page-container {
            padding: 30px;
            max-width: 1100px;
        }

        .page-header {
            margin-bottom: 25px;
        }

        .page-header-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 17px;
            border-radius: 6px;
            border: 1px solid #d5d9df;
            background: #fff;
            color: #333;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
            white-space: nowrap;
        }

        .back-btn:hover {
            background: #f5f6f8;
        }

        .page-header h1 {
            margin: 0;
            font-size: 28px;
        }

        .page-header p {
            margin: 6px 0 0;
            color: #666;
        }

        .form-card {
            background: #fff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
            margin-bottom: 20px;
        }

        .form-card h2 {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 20px;
        }

        .form-grid {
            display: grid;
            grid-template-columns:
                repeat(2, minmax(0, 1fr));

            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            font-weight: 600;
            font-size: 14px;
            color: #444;
        }

        .required {
            color: #dc3545;
        }

        .form-control {
            width: 100%;
            box-sizing: border-box;
            padding: 11px 12px;
            border: 1px solid #d5d9df;
            border-radius: 6px;
            background: #fff;
            font-size: 14px;
        }

        .form-control:focus {
            outline: none;
            border-color: #1f6feb;
        }

        .readonly-field {
            background: #f5f6f8;
        }

        textarea.form-control {
            min-height: 110px;
            resize: vertical;
        }

        .help-text {
            font-size: 12px;
            color: #777;
        }

        .alert {
            padding: 14px 16px;
            border-radius: 7px;
            margin-bottom: 20px;
        }

        .alert-danger {
            background: #f8d7da;
            color: #842029;
            border: 1px solid #f5c2c7;
        }

        .alert-danger ul {
            margin: 0;
            padding-left: 20px;
        }

        .fee-box {
            background: #f8f9fa;
            border: 1px solid #e1e4e8;
            border-radius: 8px;
            padding: 18px;
        }

        .fee-row {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            padding: 8px 0;
        }

        .fee-row.total {
            border-top: 1px solid #ddd;
            margin-top: 8px;
            padding-top: 15px;
            font-size: 18px;
            font-weight: 700;
        }

        .batch-info {
            margin-top: 8px;
            padding: 10px 12px;
            background: #f8f9fa;
            border-radius: 6px;
            font-size: 13px;
            color: #555;
        }

        .batch-info strong {
            color: #333;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 25px;
        }

        .btn {
            display: inline-block;
            padding: 10px 17px;
            border-radius: 6px;
            border: none;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-primary {
            background: #1f6feb;
            color: #fff;
        }

        .btn-primary:hover {
            background: #1557b0;
        }

        .btn-secondary {
            background: #e9ecef;
            color: #333;
        }

        /* RTL layout: sidebar on the right, content on the left */
        html[dir="rtl"] .sidebar {
            left: auto;
            right: 0;
        }

        html[dir="rtl"] .main-content {
            margin-left: 0;
            margin-right: 250px;
        }

        @media (max-width: 700px) {

            .main-content {
                margin-left: 0;
                margin-right: 0;
                width: 100%;
            }

            .sidebar {
                position: relative;
                left: auto;
                right: auto;
                width: 100%;
                height: auto;
            }

            .page-container {
                padding: 20px 15px;
            }

            .page-header-top {
                flex-direction: column;
                align-items: stretch;
            }

            .back-btn {
                align-self: flex-start;
            }

            .form-card {
                padding: 20px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full-width {
                grid-column: auto;
            }

            .form-actions {
                flex-direction: column;
            }

            .form-actions .btn {
                width: 100%;
                text-align: center;
            }
        }

    </style>

</head>

<body>

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
           style="flex:1; text-align:center; padding:7px 6px; border-radius:6px; background:<?= $lang === 'en' ? '#2563eb' : '#1f2937' ?>; color:#fff; font-size:12px;">
            English
        </a>
        <a href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['lang' => 'ar']))) ?>"
           style="flex:1; text-align:center; padding:7px 6px; border-radius:6px; background:<?= $lang === 'ar' ? '#2563eb' : '#1f2937' ?>; color:#fff; font-size:12px;">
            العربية
        </a>
    </div>


    <!-- MAIN -->

    <div class="menu-section">

        <div class="menu-title">
            <?= $lang === 'ar' ? 'الرئيسية' : 'Main' ?>
        </div>

        <a href="../../modules/dashboard/index.php">
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
            <?= $lang === 'ar' ? 'الدورات' : 'Courses' ?>
        </a>

        <a href="../batches/index.php">
            <?= $lang === 'ar' ? 'المجموعات' : 'Batches' ?>
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
                <?= $lang === 'ar' ? 'الإدارة' : 'Administration' ?>
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

<div class="main-content">

    <div class="page-container">

        <div class="page-header">

            <div class="page-header-top">

                <div>
                    <h1><?= $lang === 'ar' ? 'تعديل التسجيل' : 'Edit Enrollment' ?></h1>

                    <p>
                        <?= $lang === 'ar' ? 'تحديث معلومات التسجيل والرسوم.' : 'Update enrollment information and fees.' ?>
                    </p>
                </div>

                <a
                    href="index.php"
                    class="back-btn"
                >
                    <?= $lang === 'ar' ? '← رجوع' : '← Back' ?>
                </a>

            </div>

        </div>


        <?php if (!empty($errors)): ?>

            <div class="alert alert-danger">

                <ul>

                    <?php foreach ($errors as $error): ?>

                        <li>
                            <?= htmlspecialchars($error) ?>
                        </li>

                    <?php endforeach; ?>

                </ul>

            </div>

        <?php endif; ?>


        <form
            method="POST"
            action="edit.php?id=<?= $enrollmentId ?>"
        >

            <input
                type="hidden"
                name="id"
                value="<?= $enrollmentId ?>"
            >


            <!-- Enrollment Information -->

            <div class="form-card">

                <h2><?= $lang === 'ar' ? 'معلومات التسجيل' : 'Enrollment Information' ?></h2>

                <div class="form-grid">

                    <!-- Student -->

                    <div class="form-group">

                        <label for="student_id">

                            <?= $lang === 'ar' ? 'الطالب' : 'Student' ?>

                            <span class="required">*</span>

                        </label>

                        <select
                            name="student_id"
                            id="student_id"
                            class="form-control"
                            required
                        >

                            <option value="">
                                <?= $lang === 'ar' ? 'اختر الطالب' : 'Select Student' ?>
                            </option>

                            <?php foreach ($students as $student): ?>

                                <option
                                    value="<?= (int)$student['id'] ?>"
                                    <?= (string)$student_id ===
                                        (string)$student['id']
                                        ? 'selected'
                                        : ''
                                    ?>
                                >

                                    <?= htmlspecialchars(
                                        $student['student_code']
                                    ) ?>

                                    -
                                    <?= htmlspecialchars(
                                        $student['full_name']
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- Batch -->

                    <div class="form-group">

                        <label for="batch_id">

                            <?= $lang === 'ar' ? 'الدفعة' : 'Batch' ?>

                            <span class="required">*</span>

                        </label>

                        <select
                            name="batch_id"
                            id="batch_id"
                            class="form-control"
                            required
                        >

                            <option value="">
                                <?= $lang === 'ar' ? 'اختر الدفعة' : 'Select Batch' ?>
                            </option>

                            <?php foreach ($batches as $batch): ?>

                                <option
                                    value="<?= (int)$batch['id'] ?>"

                                    data-fee="<?= htmlspecialchars(
                                        (string)$batch['default_fee']
                                    ) ?>"

                                    data-course="<?= htmlspecialchars(
                                        $batch['course_name']
                                    ) ?>"

                                    data-start="<?= htmlspecialchars(
                                        $batch['start_date']
                                    ) ?>"

                                    data-end="<?= htmlspecialchars(
                                        $batch['end_date']
                                    ) ?>"

                                    data-capacity="<?= (int)$batch['capacity'] ?>"

                                    data-enrolled="<?= (int)$batch['enrolled_count'] ?>"

                                    data-status="<?= htmlspecialchars(
                                        $batch['status']
                                    ) ?>"

                                    <?= (string)$batch_id ===
                                        (string)$batch['id']
                                        ? 'selected'
                                        : ''
                                    ?>
                                >

                                    <?= htmlspecialchars(
                                        $batch['batch_code']
                                    ) ?>

                                    -
                                    <?= htmlspecialchars(
                                        $batch['course_name']
                                    ) ?>

                                    |

                                    <?= htmlspecialchars(
                                        $batch['start_date']
                                    ) ?>

                                    <?php if (
                                        $batch['status'] ===
                                        'cancelled'
                                    ): ?>

                                        (<?= $lang === 'ar' ? 'ملغاة' : 'CANCELLED' ?>)

                                    <?php elseif (
                                        $batch['status'] ===
                                        'completed'
                                    ): ?>

                                        (<?= $lang === 'ar' ? 'مكتملة' : 'COMPLETED' ?>)

                                    <?php endif; ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                        <div
                            id="batchInfo"
                            class="batch-info"
                        ></div>

                    </div>


                    <!-- Enrollment Date -->

                    <div class="form-group">

                        <label for="enrollment_date">

                            <?= $lang === 'ar' ? 'تاريخ التسجيل' : 'Enrollment Date' ?>

                            <span class="required">*</span>

                        </label>

                        <input
                            type="date"
                            name="enrollment_date"
                            id="enrollment_date"
                            class="form-control"
                            value="<?= htmlspecialchars(
                                $enrollment_date
                            ) ?>"
                            required
                        >

                    </div>


                    <!-- Status -->

                    <div class="form-group">

                        <label for="status">

                            <?= $lang === 'ar' ? 'الحالة' : 'Status' ?>

                            <span class="required">*</span>

                        </label>

                        <select
                            name="status"
                            id="status"
                            class="form-control"
                            required
                        >

                            <option
                                value="active"
                                <?= $status === 'active'
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                <?= $lang === 'ar' ? 'نشط' : 'Active' ?>
                            </option>

                            <option
                                value="completed"
                                <?= $status === 'completed'
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                <?= $lang === 'ar' ? 'مكتمل' : 'Completed' ?>
                            </option>

                            <option
                                value="suspended"
                                <?= $status === 'suspended'
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                <?= $lang === 'ar' ? 'موقوف' : 'Suspended' ?>
                            </option>

                            <option
                                value="cancelled"
                                <?= $status === 'cancelled'
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                <?= $lang === 'ar' ? 'ملغى' : 'Cancelled' ?>
                            </option>

                        </select>

                    </div>

                </div>

            </div>


            <!-- Fee Information -->

            <div class="form-card">

                <h2><?= $lang === 'ar' ? 'معلومات الرسوم' : 'Fee Information' ?></h2>

                <div class="form-grid">

                    <div class="form-group">

                        <label for="original_fee">
                            <?= $lang === 'ar' ? 'الرسوم الأصلية' : 'Original Fee' ?>
                        </label>

                        <input
                            type="text"
                            id="original_fee"
                            class="form-control readonly-field"
                            value="<?= htmlspecialchars(
                                $original_fee
                            ) ?>"
                            readonly
                        >

                        <span class="help-text">
                            <?= $lang === 'ar' ? 'يتم أخذها تلقائيًا من المقرر المحدد.' : 'Automatically taken from the selected course.' ?>
                        </span>

                    </div>


                    <div class="form-group">

                        <label for="discount">
                            <?= $lang === 'ar' ? 'الخصم' : 'Discount' ?>
                        </label>

                        <input
                            type="number"
                            name="discount"
                            id="discount"
                            class="form-control"
                            min="0"
                            step="0.01"
                            value="<?= htmlspecialchars(
                                $discount
                            ) ?>"
                        >

                        <span class="help-text">
                            <?= $lang === 'ar' ? 'أدخل قيمة خصم ثابتة.' : 'Enter a fixed discount amount.' ?>
                        </span>

                    </div>


                    <div class="form-group full-width">

                        <div class="fee-box">

                            <div class="fee-row">

                                <span>
                                    <?= $lang === 'ar' ? 'الرسوم الأصلية' : 'Original Fee' ?>
                                </span>

                                <strong id="summaryOriginal">
                                    0
                                </strong>

                            </div>

                            <div class="fee-row">

                                <span>
                                    <?= $lang === 'ar' ? 'الخصم' : 'Discount' ?>
                                </span>

                                <strong id="summaryDiscount">
                                    0
                                </strong>

                            </div>

                            <div class="fee-row total">

                                <span>
                                    <?= $lang === 'ar' ? 'الرسوم الفعلية' : 'Actual Fee' ?>
                                </span>

                                <strong id="summaryActual">
                                    0
                                </strong>

                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <!-- Notes -->

            <div class="form-card">

                <h2><?= $lang === 'ar' ? 'ملاحظات' : 'Notes' ?></h2>

                <div class="form-group">

                    <label for="notes">
                        <?= $lang === 'ar' ? 'ملاحظات إضافية' : 'Additional Notes' ?>
                    </label>

                    <textarea
                        name="notes"
                        id="notes"
                        class="form-control"
                        placeholder="<?= $lang === 'ar' ? 'ملاحظات اختيارية...' : 'Optional notes...' ?>"
                    ><?= htmlspecialchars($notes) ?></textarea>

                </div>

            </div>


            <!-- Actions -->

            <div class="form-actions">

                <a
                    href="view.php?id=<?= $enrollmentId ?>"
                    class="btn btn-secondary"
                >
                    <?= $lang === 'ar' ? 'إلغاء' : 'Cancel' ?>
                </a>

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    <?= $lang === 'ar' ? 'تحديث التسجيل' : 'Update Enrollment' ?>
                </button>

            </div>

        </form>

    </div>

</div>


<script>

document.addEventListener('DOMContentLoaded', function () {

    const batchSelect =
        document.getElementById('batch_id');

    const discountInput =
        document.getElementById('discount');

    const originalFeeInput =
        document.getElementById('original_fee');

    const batchInfo =
        document.getElementById('batchInfo');

    const summaryOriginal =
        document.getElementById('summaryOriginal');

    const summaryDiscount =
        document.getElementById('summaryDiscount');

    const summaryActual =
        document.getElementById('summaryActual');


    function formatNumber(value) {

        return new Intl.NumberFormat(
            <?= json_encode($lang === 'ar' ? 'ar-SA' : 'en-US') ?>,
            {
                minimumFractionDigits: 0,
                maximumFractionDigits: 2
            }
        ).format(value);

    }


    function escapeHtml(text) {

        const div =
            document.createElement('div');

        div.textContent =
            text;

        return div.innerHTML;

    }


    function updateFee() {

        const selected =
            batchSelect.options[
                batchSelect.selectedIndex
            ];

        let fee = 0;

        if (
            selected &&
            selected.dataset.fee
        ) {

            fee =
                parseFloat(
                    selected.dataset.fee
                ) || 0;

        }

        const discount =
            parseFloat(
                discountInput.value
            ) || 0;

        const actual =
            Math.max(
                0,
                fee - discount
            );


        originalFeeInput.value =
            fee;

        summaryOriginal.textContent =
            formatNumber(fee);

        summaryDiscount.textContent =
            formatNumber(discount);

        summaryActual.textContent =
            formatNumber(actual);


        /*
        |--------------------------------------------------------------------------
        | Batch Information
        |--------------------------------------------------------------------------
        */

        if (
            selected &&
            selected.value !== ''
        ) {

            const course =
                selected.dataset.course || '';

            const start =
                selected.dataset.start || '';

            const end =
                selected.dataset.end || '';

            const capacity =
                parseInt(
                    selected.dataset.capacity || '0'
                );

            const enrolled =
                parseInt(
                    selected.dataset.enrolled || '0'
                );

            const status =
                selected.dataset.status || '';


            let capacityText =
                <?= json_encode($lang === 'ar' ? 'سعة غير محدودة' : 'Unlimited capacity') ?>;

            if (capacity > 0) {

                capacityText =
                    enrolled +
                    ' / ' +
                    capacity +
                    <?= json_encode($lang === 'ar' ? ' طالب' : ' students') ?>;

            }


            batchInfo.innerHTML =
                <?= json_encode($lang === 'ar' ? '<strong>المقرر:</strong> ' : '<strong>Course:</strong> ') ?> +
                escapeHtml(course) +
                '<br>' +

                <?= json_encode($lang === 'ar' ? '<strong>الفترة:</strong> ' : '<strong>Period:</strong> ') ?> +
                escapeHtml(start) +
                ' → ' +
                escapeHtml(end) +
                '<br>' +

                <?= json_encode($lang === 'ar' ? '<strong>السعة:</strong> ' : '<strong>Capacity:</strong> ') ?> +
                escapeHtml(capacityText) +
                '<br>' +

                <?= json_encode($lang === 'ar' ? '<strong>الحالة:</strong> ' : '<strong>Status:</strong> ') ?> +
                escapeHtml(status);

            batchInfo.style.display =
                'block';

        } else {

            batchInfo.style.display =
                'none';

        }

    }


    batchSelect.addEventListener(
        'change',
        updateFee
    );


    discountInput.addEventListener(
        'input',
        updateFee
    );


    updateFee();

});

</script>

</body>
</html>