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
| Helper
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/*
|--------------------------------------------------------------------------
| Variables
|--------------------------------------------------------------------------
*/

$errors = [];

$selectedEnrollmentId = (int)($_POST['enrollment_id'] ?? 0);
$amount = $_POST['amount'] ?? '';
$paymentDate = $_POST['payment_date'] ?? date('Y-m-d');
$paymentMethod = $_POST['payment_method'] ?? '';
$notes = trim($_POST['notes'] ?? '');

$selectedEnrollment = null;

/*
|--------------------------------------------------------------------------
| Generate Receipt Number
|--------------------------------------------------------------------------
*/

function generateReceiptNumber($pdo)
{
    $prefix = 'ALW-' . date('Ymd') . '-';

    $stmt = $pdo->prepare("
        SELECT receipt_number
        FROM payments
        WHERE receipt_number LIKE ?
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmt->execute([
        $prefix . '%'
    ]);

    $lastReceipt = $stmt->fetchColumn();

    if ($lastReceipt) {

        $lastNumber = (int)substr(
            $lastReceipt,
            strrpos($lastReceipt, '-') + 1
        );

        $nextNumber = $lastNumber + 1;

    } else {

        $nextNumber = 1;
    }

    return $prefix . str_pad(
        $nextNumber,
        4,
        '0',
        STR_PAD_LEFT
    );
}

/*
|--------------------------------------------------------------------------
| Load Enrollment Information
|--------------------------------------------------------------------------
*/

if ($selectedEnrollmentId > 0) {

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

            s.student_code,
            s.full_name AS student_name,

            b.batch_code,

            c.course_code,
            c.course_name

        FROM enrollments e

        INNER JOIN students s
            ON s.id = e.student_id

        INNER JOIN batches b
            ON b.id = e.batch_id

        INNER JOIN courses c
            ON c.id = b.course_id

        WHERE e.id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $selectedEnrollmentId
    ]);

    $selectedEnrollment = $stmt->fetch();

    if ($selectedEnrollment) {

        $paidStmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0)
            FROM payments
            WHERE enrollment_id = ?
        ");

        $paidStmt->execute([
            $selectedEnrollmentId
        ]);

        $selectedEnrollment['total_paid'] =
            (float)$paidStmt->fetchColumn();

        $selectedEnrollment['remaining_balance'] =
            max(
                0,
                (float)$selectedEnrollment['actual_fee']
                -
                (float)$selectedEnrollment['total_paid']
            );
    }
}

/*
|--------------------------------------------------------------------------
| POST - Save Payment
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    | Validate Enrollment
    */

    if ($selectedEnrollmentId <= 0) {

        $errors[] =
            ($lang === 'ar' ? 'يرجى اختيار تسجيل.' : 'Please select an enrollment.');
    }

    /*
    | Validate Payment Method
    */

    $allowedMethods = [
        'Cash',
        'Bank Transfer'
    ];

    if (!in_array($paymentMethod, $allowedMethods, true)) {

        $errors[] =
            ($lang === 'ar' ? 'يرجى اختيار طريقة دفع صحيحة.' : 'Please select a valid payment method.');
    }

    /*
    | Validate Payment Date
    */

    if (empty($paymentDate)) {

        $errors[] =
            ($lang === 'ar' ? 'تاريخ الدفع مطلوب.' : 'Payment date is required.');
    }

    /*
    | Validate Amount
    */

    if ($amount === '' || !is_numeric($amount)) {

        $errors[] =
            ($lang === 'ar' ? 'يرجى إدخال مبلغ دفع صحيح.' : 'Please enter a valid payment amount.');

    } elseif ((float)$amount <= 0) {

        $errors[] =
            ($lang === 'ar' ? 'يجب أن يكون مبلغ الدفع أكبر من صفر.' : 'Payment amount must be greater than zero.');
    }

    /*
    |--------------------------------------------------------------------------
    | Reload Enrollment For Secure Validation
    |--------------------------------------------------------------------------
    */

    if ($selectedEnrollmentId > 0) {

        $stmt = $pdo->prepare("
            SELECT
                e.id,
                e.student_id,
                e.batch_id,
                e.original_fee,
                e.discount,
                e.actual_fee,
                e.status,

                s.student_code,
                s.full_name AS student_name,

                b.batch_code,

                c.course_code,
                c.course_name

            FROM enrollments e

            INNER JOIN students s
                ON s.id = e.student_id

            INNER JOIN batches b
                ON b.id = e.batch_id

            INNER JOIN courses c
                ON c.id = b.course_id

            WHERE e.id = ?

            LIMIT 1
        ");

        $stmt->execute([
            $selectedEnrollmentId
        ]);

        $selectedEnrollment =
            $stmt->fetch();

        if (!$selectedEnrollment) {

            $errors[] =
                ($lang === 'ar' ? 'لم يتم العثور على التسجيل المحدد.' : 'Selected enrollment was not found.');

        } else {

            /*
            | Get Total Paid
            */

            $paidStmt = $pdo->prepare("
                SELECT COALESCE(SUM(amount), 0)
                FROM payments
                WHERE enrollment_id = ?
            ");

            $paidStmt->execute([
                $selectedEnrollmentId
            ]);

            $totalPaid =
                (float)$paidStmt->fetchColumn();

            $actualFee =
                (float)$selectedEnrollment['actual_fee'];

            $remainingBalance =
                max(
                    0,
                    $actualFee - $totalPaid
                );

            $selectedEnrollment['total_paid'] =
                $totalPaid;

            $selectedEnrollment['remaining_balance'] =
                $remainingBalance;

            /*
            |--------------------------------------------------------------------------
            | Check Enrollment Status
            |--------------------------------------------------------------------------
            */

            if (
                $selectedEnrollment['status']
                === 'cancelled'
            ) {

                $errors[] =
                    ($lang === 'ar' ? 'لا يمكن تسجيل دفعة لتسجيل ملغى.' : 'Payment cannot be recorded for a cancelled enrollment.');
            }

            /*
            |--------------------------------------------------------------------------
            | Check Already Paid
            |--------------------------------------------------------------------------
            */

            if ($remainingBalance <= 0) {

                $errors[] =
                    ($lang === 'ar' ? 'تم سداد رسوم هذا التسجيل بالكامل بالفعل.' : 'This enrollment has already been fully paid.');
            }

            /*
            |--------------------------------------------------------------------------
            | Check Payment Amount
            |--------------------------------------------------------------------------
            */

            if (
                is_numeric($amount)
                && (float)$amount > $remainingBalance
            ) {

                $errors[] =
                    'Payment amount cannot exceed the remaining balance of '
                    . number_format($remainingBalance, 2)
                    . '.';
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Save Payment
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        try {

            $pdo->beginTransaction();

            /*
            | Generate Receipt Number
            */

            $receiptNumber =
                generateReceiptNumber($pdo);

            /*
            | Insert Payment
            */

            $stmt = $pdo->prepare("
                INSERT INTO payments (
                    enrollment_id,
                    receipt_number,
                    amount,
                    payment_date,
                    payment_method,
                    recorded_by,
                    notes
                )
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $selectedEnrollmentId,
                $receiptNumber,
                (float)$amount,
                $paymentDate,
                $paymentMethod,
                $_SESSION['user_id'],
                $notes !== ''
                    ? $notes
                    : null
            ]);

            $pdo->commit();

            /*
            | Redirect To Payment List
            */

            header(
                'Location: index.php?success=1'
            );

            exit;

        } catch (Exception $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] =
                ($lang === 'ar' ? 'فشل تسجيل الدفعة. يرجى المحاولة مرة أخرى.' : 'Failed to record payment. Please try again.');
        }
    }
}

/*
|--------------------------------------------------------------------------
| Get Available Enrollments
|--------------------------------------------------------------------------
|
| Only active/completable enrollments that still have
| an outstanding balance are shown.
|--------------------------------------------------------------------------
*/

$enrollmentStmt = $pdo->query("
    SELECT
        e.id,
        e.original_fee,
        e.discount,
        e.actual_fee,
        e.status,

        s.student_code,
        s.full_name AS student_name,

        b.batch_code,

        c.course_code,
        c.course_name,

        COALESCE(
            (
                SELECT SUM(p.amount)
                FROM payments p
                WHERE p.enrollment_id = e.id
            ),
            0
        ) AS total_paid

    FROM enrollments e

    INNER JOIN students s
        ON s.id = e.student_id

    INNER JOIN batches b
        ON b.id = e.batch_id

    INNER JOIN courses c
        ON c.id = b.course_id

    WHERE e.status IN (
        'active',
        'completed'
    )

    ORDER BY
        s.full_name ASC
");

$enrollments = $enrollmentStmt->fetchAll();

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
        <?= $lang === 'ar' ? 'تسجيل دفعة - مركز العلومة للتدريب' : 'Record Payment - ALAW LAMA Training Center' ?>
    </title>

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

            color: white;

            padding: 30px 18px;

            overflow-y: auto;

            z-index: 1000;
        }

        .brand {
            padding:
                0 12px 22px;

            border-bottom:
                1px solid #374151;

            margin-bottom: 25px;
        }

        .brand h2 {
            margin: 0;

            font-size: 21px;
        }

        .brand p {
            margin:
                8px 0 0;

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
        }

        .sidebar a {
            display: block;

            padding:
                10px 12px;

            margin-bottom: 3px;

            color: #f3f4f6;

            text-decoration: none;

            border-radius: 7px;

            font-size: 15px;
        }

        .sidebar a:hover {
            background: #1f2937;
        }

        .sidebar a.active {
            background: #2563eb;

            color: white;

            font-weight: bold;
        }

        /*
        |--------------------------------------------------------------------------
        | MAIN
        |--------------------------------------------------------------------------
        */

        .main-content {
            margin-left: 250px;

            width:
                calc(100% - 250px);

            padding:
                35px 40px;
        }

        /*
        |--------------------------------------------------------------------------
        | HEADER
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
            margin:
                0 0 7px;

            font-size: 34px;
        }

        .page-title p {
            margin: 0;

            color: #6b7280;

            font-size: 17px;
        }

        .header-actions {
            display: flex;

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

            font-family: inherit;

            font-size: 14px;

            font-weight: 600;

            cursor: pointer;

            border:
                1px solid transparent;
        }

        .btn-primary {
            background: #2563eb;

            color: white;

            border-color: #2563eb;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-secondary {
            background: white;

            color: #374151;

            border-color: #d1d5db;
        }

        .btn-secondary:hover {
            background: #f3f4f6;
        }

        /*
        |--------------------------------------------------------------------------
        | ERROR
        |--------------------------------------------------------------------------
        */

        .error-box {
            background: #fef2f2;

            border:
                1px solid #fecaca;

            color: #991b1b;

            border-radius: 10px;

            padding:
                16px 20px;

            margin-bottom: 20px;
        }

        .error-box ul {
            margin:
                8px 0 0 20px;

            padding: 0;
        }

        /*
        |--------------------------------------------------------------------------
        | FORM CARD
        |--------------------------------------------------------------------------
        */

        .form-card {
            max-width: 950px;

            background: white;

            border:
                1px solid #f0f0f0;

            border-radius: 12px;

            box-shadow:
                0 4px 20px
                rgba(0, 0, 0, 0.06);

            padding: 30px;
        }

        .section-title {
            font-size: 20px;

            font-weight: bold;

            color: #111827;

            margin:
                0 0 20px;

            padding-bottom:
                12px;

            border-bottom:
                1px solid #e5e7eb;
        }

        .form-grid {
            display: grid;

            grid-template-columns:
                repeat(2, minmax(0, 1fr));

            gap: 20px;
        }

        .form-group {
            min-width: 0;
        }

        .full-width {
            grid-column:
                1 / -1;
        }

        label {
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

            padding:
                12px 14px;

            font-family: inherit;

            font-size: 15px;

            background: white;

            color: #111827;

            outline: none;
        }

        input,
        select {
            height: 48px;
        }

        textarea {
            min-height: 110px;

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
        | ENROLLMENT INFO
        |--------------------------------------------------------------------------
        */

        .enrollment-info {
            margin-top: 25px;

            background: #f8fafc;

            border:
                1px solid #e5e7eb;

            border-radius: 10px;

            padding: 20px;

            display: none;
        }

        .enrollment-info.show {
            display: block;
        }

        .info-title {
            font-size: 17px;

            font-weight: bold;

            margin-bottom: 15px;
        }

        .info-grid {
            display: grid;

            grid-template-columns:
                repeat(3, minmax(0, 1fr));

            gap: 15px;
        }

        .info-item {
            background: white;

            border:
                1px solid #e5e7eb;

            border-radius: 8px;

            padding: 13px;
        }

        .info-label {
            font-size: 11px;

            color: #6b7280;

            text-transform: uppercase;

            font-weight: bold;

            margin-bottom: 5px;
        }

        .info-value {
            font-size: 15px;

            font-weight: bold;

            color: #111827;
        }

        /*
        |--------------------------------------------------------------------------
        | BALANCE
        |--------------------------------------------------------------------------
        */

        .balance-box {
            margin-top: 20px;

            padding: 18px;

            border-radius: 10px;

            background: #eff6ff;

            border:
                1px solid #bfdbfe;
        }

        .balance-row {
            display: flex;

            justify-content:
                space-between;

            gap: 20px;

            padding: 6px 0;

            font-size: 15px;
        }

        .balance-row.total {
            padding-top: 12px;

            margin-top: 8px;

            border-top:
                1px solid #bfdbfe;

            font-size: 18px;

            font-weight: bold;
        }

        .balance-row.remaining {
            color: #1d4ed8;

            font-size: 20px;

            font-weight: bold;
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

            margin-top: 30px;

            padding-top: 20px;

            border-top:
                1px solid #e5e7eb;
        }

        /*
        |--------------------------------------------------------------------------
        | HELP TEXT
        |--------------------------------------------------------------------------
        */

        .help-text {
            margin-top: 7px;

            font-size: 12px;

            color: #6b7280;
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

                padding: 25px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .info-grid {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
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

            .page-header {
                flex-direction: column;
            }

            .header-actions {
                width: 100%;
            }

            .header-actions .btn {
                flex: 1;
            }

            .form-card {
                padding: 20px;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }

            .form-actions .btn {
                width: 100%;
            }

        }


        /* Language direction */
        [dir="rtl"] body { direction: rtl; }
        [dir="ltr"] body { direction: ltr; }

        [dir="rtl"] .sidebar {
            left: auto;
            right: 0;
        }

        [dir="rtl"] .main-content {
            margin-left: 0;
            margin-right: 250px;
            width: calc(100% - 250px);
        }

        @media (max-width: 900px) {
            [dir="rtl"] .main-content {
                margin-left: 0;
                margin-right: 210px;
                width: calc(100% - 210px);
            }
        }

        @media (max-width: 600px) {
            [dir="rtl"] .main-content {
                margin-right: 0;
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

    <div style="display:flex; gap:6px; margin:0 0 25px; padding:0 12px;">
        <a href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['lang' => 'en']))) ?>" style="flex:1; text-align:center; padding:9px 6px; margin:0; background:<?= $lang === 'en' ? '#2563eb' : '#1f2937' ?>; border:1px solid #374151; border-radius:7px;">English</a>
        <a href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['lang' => 'ar']))) ?>" style="flex:1; text-align:center; padding:9px 6px; margin:0; background:<?= $lang === 'ar' ? '#2563eb' : '#1f2937' ?>; border:1px solid #374151; border-radius:7px;">العربية</a>
    </div>


    <div class="menu-section">

        <div class="menu-title">
            <?= $lang === 'ar' ? 'الرئيسية' : 'Main' ?>
        </div>

        <a href="../dashboard/index.php">
            <?= $lang === 'ar' ? 'لوحة التحكم' : 'Dashboard' ?>
        </a>

    </div>


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

        <a href="../enrollments/index.php">
            <?= $lang === 'ar' ? 'التسجيلات' : 'Enrollments' ?>
        </a>

        <a
            href="index.php"
            class="active"
        >
            <?= $lang === 'ar' ? 'المدفوعات' : 'Payments' ?>
        </a>

        <a href="../attendance/index.php">
            <?= $lang === 'ar' ? 'الحضور' : 'Attendance' ?>
        </a>

        <a href="../assessments/index.php">
            <?= $lang === 'ar' ? 'التقييمات والدرجات' : 'Assessments & Grades' ?>
        </a>

    </div>


    <div class="menu-section">

        <div class="menu-title">
            <?= $lang === 'ar' ? 'التقارير' : 'Reports' ?>
        </div>

        <a href="../reports/index.php">
            <?= $lang === 'ar' ? 'التقارير' : 'Reports' ?>
        </a>

    </div>


    <?php if ($_SESSION['role'] === 'admin'): ?>

        <div class="menu-section">

            <div class="menu-title">
                Administration
            </div>

            <a href="../users/index.php">
                <?= $lang === 'ar' ? 'إدارة المستخدمين' : 'Manage Users' ?>
            </a>

        </div>

    <?php endif; ?>


    <div class="menu-section">

        <div class="menu-title">
            Account
        </div>

        <a href="../../change-password.php">
            Change Password
        </a>

        <a href="../../logout.php">
            Logout
        </a>

    </div>

</aside>


<!-- ================================================================
     MAIN CONTENT
================================================================ -->

<main class="main-content">


    <!-- HEADER -->

    <div class="page-header">

        <div class="page-title">

            <h1>
                <?= $lang === 'ar' ? 'تسجيل دفعة' : 'Record Payment' ?>
            </h1>

            <p>
                <?= $lang === 'ar' ? 'تسجيل دفعة جديدة للطالب.' : 'Record a new student payment.' ?>
            </p>

        </div>


        <div class="header-actions">

            <a
                href="index.php"
                class="btn btn-secondary"
            >
                <?= $lang === 'ar' ? '← رجوع' : '← Back' ?>
            </a>

        </div>

    </div>


    <!-- ERRORS -->

    <?php if (!empty($errors)): ?>

        <div class="error-box">

            <strong>
                <?= $lang === 'ar' ? 'يرجى تصحيح ما يلي:' : 'Please fix the following:' ?>
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


    <!-- FORM -->

    <div class="form-card">

        <form
            method="POST"
            action="add.php"
        >


            <!-- ====================================================
                 PAYMENT INFORMATION
            ===================================================== -->

            <h2 class="section-title">
                <?= $lang === 'ar' ? 'معلومات الدفع' : 'Payment Information' ?>
            </h2>


            <div class="form-grid">


                <!-- ENROLLMENT -->

                <div class="form-group full-width">

                    <label for="enrollment_id">

                        <?= $lang === 'ar' ? 'الطالب / التسجيل' : 'Student / Enrollment' ?>

                        <span class="required">
                            *
                        </span>

                    </label>

                    <select
                        name="enrollment_id"
                        id="enrollment_id"
                        required
                    >

                        <option value="">
                            <?= $lang === 'ar' ? '-- اختر تسجيل الطالب --' : '-- Select Student Enrollment --' ?>
                        </option>

                        <?php foreach ($enrollments as $enrollment): ?>

                            <?php

                            $remaining =
                                max(
                                    0,
                                    (float)$enrollment['actual_fee']
                                    -
                                    (float)$enrollment['total_paid']
                                );

                            ?>

                            <?php if ($remaining > 0): ?>

                                <option
                                    value="<?= (int)$enrollment['id'] ?>"
                                    <?= $selectedEnrollmentId === (int)$enrollment['id']
                                        ? 'selected'
                                        : '' ?>
                                >

                                    <?= e($enrollment['student_code']) ?>
                                    -
                                    <?= e($enrollment['student_name']) ?>

                                    |
                                    <?= e($enrollment['course_name']) ?>

                                    |
                                    <?= e($enrollment['batch_code']) ?>

                                    |
                                    <?= $lang === 'ar' ? 'الرصيد: ' : 'Balance: ' ?>
                                    <?= number_format($remaining, 2) ?>

                                </option>

                            <?php endif; ?>

                        <?php endforeach; ?>

                    </select>

                    <div class="help-text">
                        <?= $lang === 'ar' ? 'تظهر فقط التسجيلات التي عليها رصيد مستحق.' : 'Only enrollments with an outstanding balance are shown.' ?>
                    </div>

                </div>


                <!-- PAYMENT AMOUNT -->

                <div class="form-group">

                    <label for="amount">

                        <?= $lang === 'ar' ? 'مبلغ الدفع' : 'Payment Amount' ?>

                        <span class="required">
                            *
                        </span>

                    </label>

                    <input
                        type="number"
                        name="amount"
                        id="amount"
                        value="<?= e($amount) ?>"
                        min="0.01"
                        step="0.01"
                        placeholder="Enter payment amount"
                        required
                    >

                    <div class="help-text">
                        <?= $lang === 'ar' ? 'لا يمكن أن يتجاوز المبلغ الرصيد المتبقي.' : 'The amount cannot exceed the remaining balance.' ?>
                    </div>

                </div>


                <!-- PAYMENT DATE -->

                <div class="form-group">

                    <label for="payment_date">

                        <?= $lang === 'ar' ? 'تاريخ الدفع' : 'Payment Date' ?>

                        <span class="required">
                            *
                        </span>

                    </label>

                    <input
                        type="date"
                        name="payment_date"
                        id="payment_date"
                        value="<?= e($paymentDate) ?>"
                        required
                    >

                </div>


                <!-- PAYMENT METHOD -->

                <div class="form-group">

                    <label for="payment_method">

                        <?= $lang === 'ar' ? 'طريقة الدفع' : 'Payment Method' ?>

                        <span class="required">
                            *
                        </span>

                    </label>

                    <select
                        name="payment_method"
                        id="payment_method"
                        required
                    >

                        <option value="">
                            <?= $lang === 'ar' ? '-- اختر الطريقة --' : '-- Select Method --' ?>
                        </option>

                        <option
                            value="Cash"
                            <?= $paymentMethod === 'Cash'
                                ? 'selected'
                                : '' ?>
                        >
                            <?= $lang === 'ar' ? 'نقداً' : 'Cash' ?>
                        </option>

                        <option
                            value="Bank Transfer"
                            <?= $paymentMethod === 'Bank Transfer'
                                ? 'selected'
                                : '' ?>
                        >
                            <?= $lang === 'ar' ? 'تحويل بنكي' : 'Bank Transfer' ?>
                        </option>

                    </select>

                </div>


                <!-- NOTES -->

                <div class="form-group full-width">

                    <label for="notes">
                        <?= $lang === 'ar' ? 'ملاحظات' : 'Notes' ?>
                    </label>

                    <textarea
                        name="notes"
                        id="notes"
                        placeholder="Optional payment notes..."
                    ><?= e($notes) ?></textarea>

                </div>

            </div>


            <!-- ====================================================
                 ENROLLMENT INFORMATION
            ===================================================== -->

            <div
                id="enrollmentInfo"
                class="enrollment-info
                    <?= $selectedEnrollment
                        ? 'show'
                        : '' ?>"
            >

                <div class="info-title">
                    <?= $lang === 'ar' ? 'معلومات التسجيل' : 'Enrollment Information' ?>
                </div>


                <div class="info-grid">


                    <!-- STUDENT -->

                    <div class="info-item">

                        <div class="info-label">
                            <?= $lang === 'ar' ? 'الطالب' : 'Student' ?>
                        </div>

                        <div
                            class="info-value"
                            id="infoStudent"
                        >

                            <?= $selectedEnrollment
                                ? e($selectedEnrollment['student_name'])
                                : '-' ?>

                        </div>

                    </div>


                    <!-- STUDENT ID -->

                    <div class="info-item">

                        <div class="info-label">
                            <?= $lang === 'ar' ? 'رقم الطالب' : 'Student ID' ?>
                        </div>

                        <div
                            class="info-value"
                            id="infoStudentCode"
                        >

                            <?= $selectedEnrollment
                                ? e($selectedEnrollment['student_code'])
                                : '-' ?>

                        </div>

                    </div>


                    <!-- COURSE -->

                    <div class="info-item">

                        <div class="info-label">
                            <?= $lang === 'ar' ? 'الدورة' : 'Course' ?>
                        </div>

                        <div
                            class="info-value"
                            id="infoCourse"
                        >

                            <?= $selectedEnrollment
                                ? e($selectedEnrollment['course_name'])
                                : '-' ?>

                        </div>

                    </div>


                    <!-- BATCH -->

                    <div class="info-item">

                        <div class="info-label">
                            <?= $lang === 'ar' ? 'المجموعة' : 'Batch' ?>
                        </div>

                        <div
                            class="info-value"
                            id="infoBatch"
                        >

                            <?= $selectedEnrollment
                                ? e($selectedEnrollment['batch_code'])
                                : '-' ?>

                        </div>

                    </div>


                    <!-- ACTUAL FEE -->

                    <div class="info-item">

                        <div class="info-label">
                            <?= $lang === 'ar' ? 'الرسوم الفعلية' : 'Actual Fee' ?>
                        </div>

                        <div
                            class="info-value"
                            id="infoActualFee"
                        >

                            <?= $selectedEnrollment
                                ? number_format(
                                    (float)$selectedEnrollment['actual_fee'],
                                    2
                                )
                                : '-' ?>

                        </div>

                    </div>


                    <!-- TOTAL PAID -->

                    <div class="info-item">

                        <div class="info-label">
                            <?= $lang === 'ar' ? 'إجمالي المدفوع' : 'Total Paid' ?>
                        </div>

                        <div
                            class="info-value"
                            id="infoTotalPaid"
                        >

                            <?= $selectedEnrollment
                                ? number_format(
                                    (float)$selectedEnrollment['total_paid'],
                                    2
                                )
                                : '-' ?>

                        </div>

                    </div>


                </div>


                <!-- BALANCE -->

                <div class="balance-box">

                    <div class="balance-row">

                        <span>
                            <?= $lang === 'ar' ? 'الرسوم الفعلية' : 'Actual Fee' ?>
                        </span>

                        <strong id="balanceActualFee">
                            <?= $selectedEnrollment
                                ? number_format(
                                    (float)$selectedEnrollment['actual_fee'],
                                    2
                                )
                                : '0.00' ?>
                        </strong>

                    </div>


                    <div class="balance-row">

                        <span>
                            <?= $lang === 'ar' ? 'إجمالي المدفوع' : 'Total Paid' ?>
                        </span>

                        <strong id="balancePaid">
                            <?= $selectedEnrollment
                                ? number_format(
                                    (float)$selectedEnrollment['total_paid'],
                                    2
                                )
                                : '0.00' ?>
                        </strong>

                    </div>


                    <div class="balance-row remaining">

                        <span>
                            <?= $lang === 'ar' ? 'الرصيد المتبقي' : 'Remaining Balance' ?>
                        </span>

                        <strong id="balanceRemaining">
                            <?= $selectedEnrollment
                                ? number_format(
                                    (float)$selectedEnrollment['remaining_balance'],
                                    2
                                )
                                : '0.00' ?>
                        </strong>

                    </div>

                </div>

            </div>


            <!-- ====================================================
                 ACTIONS
            ===================================================== -->

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
                    <?= $lang === 'ar' ? 'تسجيل دفعة' : 'Record Payment' ?>
                </button>

            </div>


        </form>

    </div>


</main>


<script>

/*
|--------------------------------------------------------------------------
| Enrollment Data
|--------------------------------------------------------------------------
*/

const enrollmentData = {

<?php foreach ($enrollments as $enrollment): ?>

    <?= (int)$enrollment['id'] ?>: {

        student:
            <?= json_encode($enrollment['student_name']) ?>,

        studentCode:
            <?= json_encode($enrollment['student_code']) ?>,

        course:
            <?= json_encode($enrollment['course_name']) ?>,

        batch:
            <?= json_encode($enrollment['batch_code']) ?>,

        actualFee:
            <?= (float)$enrollment['actual_fee'] ?>,

        totalPaid:
            <?= (float)$enrollment['total_paid'] ?>,

        remaining:
            <?= max(
                0,
                (float)$enrollment['actual_fee']
                -
                (float)$enrollment['total_paid']
            ) ?>

    },

<?php endforeach; ?>

};


/*
|--------------------------------------------------------------------------
| Elements
|--------------------------------------------------------------------------
*/

const enrollmentSelect =
    document.getElementById('enrollment_id');

const amountInput =
    document.getElementById('amount');

const enrollmentInfo =
    document.getElementById('enrollmentInfo');

const infoStudent =
    document.getElementById('infoStudent');

const infoStudentCode =
    document.getElementById('infoStudentCode');

const infoCourse =
    document.getElementById('infoCourse');

const infoBatch =
    document.getElementById('infoBatch');

const infoActualFee =
    document.getElementById('infoActualFee');

const infoTotalPaid =
    document.getElementById('infoTotalPaid');

const balanceActualFee =
    document.getElementById('balanceActualFee');

const balancePaid =
    document.getElementById('balancePaid');

const balanceRemaining =
    document.getElementById('balanceRemaining');


/*
|--------------------------------------------------------------------------
| Format Money
|--------------------------------------------------------------------------
*/

function formatMoney(value)
{
    return Number(value).toLocaleString(
        'en-US',
        {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }
    );
}


/*
|--------------------------------------------------------------------------
| Update Enrollment Information
|--------------------------------------------------------------------------
*/

function updateEnrollment()
{
    const id =
        enrollmentSelect.value;

    if (!id || !enrollmentData[id]) {

        enrollmentInfo.classList.remove('show');

        amountInput.removeAttribute('max');

        return;
    }

    const data =
        enrollmentData[id];

    enrollmentInfo.classList.add('show');


    infoStudent.textContent =
        data.student;

    infoStudentCode.textContent =
        data.studentCode;

    infoCourse.textContent =
        data.course;

    infoBatch.textContent =
        data.batch;

    infoActualFee.textContent =
        formatMoney(data.actualFee);

    infoTotalPaid.textContent =
        formatMoney(data.totalPaid);

    balanceActualFee.textContent =
        formatMoney(data.actualFee);

    balancePaid.textContent =
        formatMoney(data.totalPaid);

    balanceRemaining.textContent =
        formatMoney(data.remaining);


    /*
    | Limit payment amount
    */

    amountInput.max =
        data.remaining;


    /*
    | If entered amount is greater,
    | automatically reduce it.
    */

    if (
        amountInput.value !== ''
        &&
        Number(amountInput.value) > data.remaining
    ) {

        amountInput.value =
            data.remaining;
    }
}


/*
|--------------------------------------------------------------------------
| Enrollment Change
|--------------------------------------------------------------------------
*/

enrollmentSelect.addEventListener(
    'change',
    updateEnrollment
);


/*
|--------------------------------------------------------------------------
| Initial Load
|--------------------------------------------------------------------------
*/

updateEnrollment();

</script>


</body>

</html>