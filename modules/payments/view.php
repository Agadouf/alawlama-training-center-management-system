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

function formatDate($date)
{
    if (!$date) {
        return '-';
    }

    return date('d M Y', strtotime($date));
}

/*
|--------------------------------------------------------------------------
| Get Payment ID
|--------------------------------------------------------------------------
*/

$paymentId = (int)($_GET['id'] ?? 0);

if ($paymentId <= 0) {
    header('Location: index.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Get Payment
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        p.id,
        p.enrollment_id,
        p.receipt_number,
        p.amount,
        p.payment_date,
        p.payment_method,
        p.recorded_by,
        p.notes,
        p.created_at,

        s.id AS student_id,
        s.student_code,
        s.full_name AS student_name,
        s.gender,
        s.phone AS student_phone,
        s.email AS student_email,
        s.address AS student_address,

        e.enrollment_date,
        e.original_fee,
        e.discount,
        e.actual_fee,
        e.status AS enrollment_status,

        b.id AS batch_id,
        b.batch_code,
        b.start_date,
        b.end_date,
        b.room,

        c.id AS course_id,
        c.course_code,
        c.course_name,
        c.description AS course_description,

        u.username AS recorded_by_username

    FROM payments p

    INNER JOIN enrollments e
        ON e.id = p.enrollment_id

    INNER JOIN students s
        ON s.id = e.student_id

    INNER JOIN batches b
        ON b.id = e.batch_id

    INNER JOIN courses c
        ON c.id = b.course_id

    LEFT JOIN users u
        ON u.id = p.recorded_by

    WHERE p.id = ?

    LIMIT 1
");

$stmt->execute([$paymentId]);

$payment = $stmt->fetch();

if (!$payment) {
    http_response_code(404);
    die($lang === 'ar' ? 'سجل الدفع غير موجود.' : 'Payment record not found.');
}

/*
|--------------------------------------------------------------------------
| Get Total Paid For Enrollment
|--------------------------------------------------------------------------
*/

$paidStmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0)
    FROM payments
    WHERE enrollment_id = ?
");

$paidStmt->execute([
    $payment['enrollment_id']
]);

$totalPaid = (float)$paidStmt->fetchColumn();

$actualFee = (float)$payment['actual_fee'];

$remainingBalance = max(
    0,
    $actualFee - $totalPaid
);

/*
|--------------------------------------------------------------------------
| Payment Status
|--------------------------------------------------------------------------
*/

if ($totalPaid <= 0) {
    $paymentStatus = 'Unpaid';
} elseif ($totalPaid >= $actualFee) {
    $paymentStatus = 'Paid';
} else {
    $paymentStatus = 'Partially Paid';
}

/*
|--------------------------------------------------------------------------
| Get Payment History
|--------------------------------------------------------------------------
*/

$historyStmt = $pdo->prepare("
    SELECT
        p.id,
        p.receipt_number,
        p.amount,
        p.payment_date,
        p.payment_method,
        u.username AS recorded_by_username,
        (SELECT COALESCE(SUM(p2.amount), 0) FROM payments p2 WHERE p2.enrollment_id = p.enrollment_id AND (p2.payment_date < p.payment_date OR (p2.payment_date = p.payment_date AND p2.id <= p.id))) AS cumulative_paid

    FROM payments p

    LEFT JOIN users u
        ON u.id = p.recorded_by

    WHERE p.enrollment_id = ?

    ORDER BY
        p.payment_date ASC,
        p.id ASC
");

$historyStmt->execute([
    $payment['enrollment_id']
]);

$paymentHistory = $historyStmt->fetchAll();

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
        <?= $lang === 'ar' ? 'تفاصيل الدفع - مركز العولمة للتدريب' : 'Payment Details - ALAWLAMA Training Center' ?>
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

        html[dir="rtl"] .sidebar {
            left: auto;
            right: 0;
        }

        html[dir="rtl"] .main-content {
            margin-left: 0;
            margin-right: 250px;
        }

        html[dir="rtl"] .page-header {
            direction: rtl;
        }

        html[dir="rtl"] .header-actions {
            direction: rtl;
        }

        html[dir="rtl"] th,
        html[dir="rtl"] td {
            text-align: right;
        }

        @media (max-width: 900px) {
            html[dir="rtl"] .main-content {
                margin-left: 0;
                margin-right: 210px;
            }
        }

        @media (max-width: 650px) {
            html[dir="rtl"] .main-content {
                margin-right: 0;
            }
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
        | MAIN CONTENT
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

            margin-bottom: 25px;
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
        | CARDS
        |--------------------------------------------------------------------------
        */

        .card {
            background: white;

            border:
                1px solid #f0f0f0;

            border-radius: 12px;

            box-shadow:
                0 4px 20px
                rgba(0, 0, 0, 0.06);

            padding: 28px;

            margin-bottom: 22px;
        }

        .card-title {
            margin:
                0 0 20px;

            padding-bottom:
                12px;

            border-bottom:
                1px solid #e5e7eb;

            font-size: 20px;
        }

        /*
        |--------------------------------------------------------------------------
        | RECEIPT HEADER
        |--------------------------------------------------------------------------
        */

        .receipt-header {
            text-align: center;

            padding-bottom: 22px;

            border-bottom:
                2px solid #111827;

            margin-bottom: 25px;
        }

        .receipt-header h2 {
            margin:
                0 0 7px;

            font-size: 27px;

            letter-spacing: 0.5px;
        }

        .receipt-header p {
            margin: 0;

            color: #6b7280;

            font-size: 14px;
        }

        .receipt-number {
            margin-top: 15px;

            font-size: 16px;

            font-weight: bold;
        }

        /*
        |--------------------------------------------------------------------------
        | INFORMATION GRID
        |--------------------------------------------------------------------------
        */

        .info-grid {
            display: grid;

            grid-template-columns:
                repeat(2, minmax(0, 1fr));

            gap: 15px 30px;
        }

        .info-item {
            padding:
                12px 0;

            border-bottom:
                1px solid #f0f0f0;
        }

        .info-label {
            margin-bottom: 5px;

            color: #6b7280;

            font-size: 12px;

            font-weight: bold;

            text-transform: uppercase;
        }

        .info-value {
            color: #111827;

            font-size: 16px;

            font-weight: 600;
        }

        /*
        |--------------------------------------------------------------------------
        | PAYMENT SUMMARY
        |--------------------------------------------------------------------------
        */

        .summary-grid {
            display: grid;

            grid-template-columns:
                repeat(4, minmax(0, 1fr));

            gap: 15px;
        }

        .summary-box {
            padding: 18px;

            border:
                1px solid #e5e7eb;

            border-radius: 10px;

            background: #fafafa;
        }

        .summary-box .label {
            color: #6b7280;

            font-size: 12px;

            text-transform: uppercase;

            font-weight: bold;

            margin-bottom: 7px;
        }

        .summary-box .value {
            font-size: 21px;

            font-weight: bold;

            color: #111827;
        }

        /*
        |--------------------------------------------------------------------------
        | STATUS
        |--------------------------------------------------------------------------
        */

        .status {
            display: inline-block;

            padding:
                6px 12px;

            border-radius: 999px;

            font-size: 12px;

            font-weight: bold;
        }

        .status-paid {
            background: #dcfce7;

            color: #166534;
        }

        .status-partial {
            background: #fef3c7;

            color: #92400e;
        }

        .status-unpaid {
            background: #fee2e2;

            color: #991b1b;
        }

        /*
        |--------------------------------------------------------------------------
        | PAYMENT HISTORY
        |--------------------------------------------------------------------------
        */

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;

            min-width: 750px;

            border-collapse: collapse;
        }

        th {
            padding:
                14px;

            text-align: left;

            background: #fafafa;

            border-bottom:
                1px solid #e5e7eb;

            font-size: 14px;

            color: #374151;
        }

        td {
            padding:
                14px;

            border-bottom:
                1px solid #eeeeee;

            font-size: 14px;

            color: #374151;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .current-payment {
            background: #eff6ff;
        }

        /*
        |--------------------------------------------------------------------------
        | NOTES
        |--------------------------------------------------------------------------
        */

        .notes {
            background: #f9fafb;

            border:
                1px solid #e5e7eb;

            border-radius: 8px;

            padding: 15px;

            line-height: 1.6;

            color: #374151;
        }

        /*
        |--------------------------------------------------------------------------
        | PRINT
        |--------------------------------------------------------------------------
        */

        .print-receipt {
            display: none;
        }

        /*
        |--------------------------------------------------------------------------
        | RESPONSIVE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 1000px) {

            .summary-grid {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }

        }

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

        }

        @media (max-width: 650px) {

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

            .card {
                padding: 20px;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .summary-grid {
                grid-template-columns: 1fr;
            }

        }

        /*
        |--------------------------------------------------------------------------
        | PRINT STYLES
        |--------------------------------------------------------------------------
        */

        @media print {

            body {
                background: white;
            }

            .sidebar,
            .page-header,
            .no-print {
                display: none !important;
            }

            .main-content {
                margin: 0;

                width: 100%;

                padding: 0;
            }

            .card {
                box-shadow: none;

                border: none;

                padding: 0;

                margin: 0 0 20px;
            }

            .receipt-header {
                display: block;
            }

            .print-receipt {
                display: block;
            }

            .summary-box {
                background: white;
            }

            .payment-history-card {
                display: none;
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
            Training Center Management
        </p>

    </div>

    <div style="display:flex; gap:6px; margin:0 12px 20px;">
        <a href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['lang' => 'en']))) ?>"
           style="padding:8px 10px; text-align:center; background:<?= $lang === 'en' ? '#2563eb' : '#1f2937' ?>;">English</a>
        <a href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['lang' => 'ar']))) ?>"
           style="padding:8px 10px; text-align:center; background:<?= $lang === 'ar' ? '#2563eb' : '#1f2937' ?>;">العربية</a>
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
            <?= $lang === 'ar' ? 'المقررات' : 'Courses' ?>
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
                <?= $lang === 'ar' ? 'الإدارة' : 'Administration' ?>
            </div>

            <a href="../users/index.php">
                <?= $lang === 'ar' ? 'إدارة المستخدمين' : 'Manage Users' ?>
            </a>

        </div>

    <?php endif; ?>


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


    <!-- PAGE HEADER -->

    <div class="page-header">

        <div class="page-title">

            <h1>
                <?= $lang === 'ar' ? 'تفاصيل الدفع' : 'Payment Details' ?>
            </h1>

            <p>
                <?= $lang === 'ar' ? 'عرض معلومات الدفع وتفاصيل الإيصال.' : 'View payment information and receipt details.' ?>
            </p>

        </div>


        <div class="header-actions no-print">

            <a
                href="index.php"
                class="btn btn-secondary"
            >
                <?= $lang === 'ar' ? '← رجوع' : '← Back' ?>
            </a>

            <button
                type="button"
                onclick="window.print()"
                class="btn btn-primary"
            >
                <?= $lang === 'ar' ? '🖨 طباعة الإيصال' : '🖨 Print Receipt' ?>
            </button>

        </div>

    </div>


    <!-- ============================================================
         RECEIPT
    ============================================================= -->

    <div class="card">


        <!-- RECEIPT HEADER -->

        <div class="receipt-header">

            <h2>
                ALAWLAMA TRAINING CENTER
            </h2>

            <p>
                <?= $lang === 'ar' ? 'إيصال دفع الطالب' : 'Student Payment Receipt' ?>
            </p>

            <div class="receipt-number">

                <?= $lang === 'ar' ? 'رقم الإيصال:' : 'Receipt No:' ?>
                <?= e($payment['receipt_number']) ?>

            </div>

        </div>


        <!-- ========================================================
             PAYMENT INFORMATION
        ========================================================= -->

        <h3 class="card-title">
            <?= $lang === 'ar' ? 'معلومات الدفع' : 'Payment Information' ?>
        </h3>


        <div class="info-grid">


            <div class="info-item">

                <div class="info-label">
                    <?= $lang === 'ar' ? 'رقم الإيصال' : 'Receipt Number' ?>
                </div>

                <div class="info-value">

                    <?= e(
                        $payment['receipt_number']
                    ) ?>

                </div>

            </div>


            <div class="info-item">

                <div class="info-label">
                    <?= $lang === 'ar' ? 'تاريخ الدفع' : 'Payment Date' ?>
                </div>

                <div class="info-value">

                    <?= formatDate(
                        $payment['payment_date']
                    ) ?>

                </div>

            </div>


            <div class="info-item">

                <div class="info-label">
                    <?= $lang === 'ar' ? 'طريقة الدفع' : 'Payment Method' ?>
                </div>

                <div class="info-value">

                    <?= e(
                        $payment['payment_method']
                    ) ?>

                </div>

            </div>


            <div class="info-item">

                <div class="info-label">
                    <?= $lang === 'ar' ? 'سجّل بواسطة' : 'Recorded By' ?>
                </div>

                <div class="info-value">

                    <?= e(
                        $payment['recorded_by_username']
                        ?: '-'
                    ) ?>

                </div>

            </div>


        </div>


        <!-- ========================================================
             STUDENT INFORMATION
        ========================================================= -->

        <h3
            class="card-title"
            style="margin-top:30px;"
        >
            <?= $lang === 'ar' ? 'معلومات الطالب' : 'Student Information' ?>
        </h3>


        <div class="info-grid">


            <div class="info-item">

                <div class="info-label">
                    <?= $lang === 'ar' ? 'رقم الطالب' : 'Student ID' ?>
                </div>

                <div class="info-value">

                    <?= e(
                        $payment['student_code']
                    ) ?>

                </div>

            </div>


            <div class="info-item">

                <div class="info-label">
                    <?= $lang === 'ar' ? 'الاسم الكامل' : 'Full Name' ?>
                </div>

                <div class="info-value">

                    <?= e(
                        $payment['student_name']
                    ) ?>

                </div>

            </div>


            <div class="info-item">

                <div class="info-label">
                    <?= $lang === 'ar' ? 'النوع' : 'Gender' ?>
                </div>

                <div class="info-value">

                    <?= e(
                        $payment['gender']
                    ) ?>

                </div>

            </div>


            <div class="info-item">

                <div class="info-label">
                    <?= $lang === 'ar' ? 'الهاتف' : 'Phone' ?>
                </div>

                <div class="info-value">

                    <?= e(
                        $payment['student_phone']
                        ?: '-'
                    ) ?>

                </div>

            </div>


        </div>


        <!-- ========================================================
             COURSE INFORMATION
        ========================================================= -->

        <h3
            class="card-title"
            style="margin-top:30px;"
        >
            <?= $lang === 'ar' ? 'معلومات المقرر والمجموعة' : 'Course & Batch Information' ?>
        </h3>


        <div class="info-grid">


            <div class="info-item">

                <div class="info-label">
                    <?= $lang === 'ar' ? 'رمز المقرر' : 'Course Code' ?>
                </div>

                <div class="info-value">

                    <?= e(
                        $payment['course_code']
                    ) ?>

                </div>

            </div>


            <div class="info-item">

                <div class="info-label">
                    <?= $lang === 'ar' ? 'اسم المقرر' : 'Course Name' ?>
                </div>

                <div class="info-value">

                    <?= e(
                        $payment['course_name']
                    ) ?>

                </div>

            </div>


            <div class="info-item">

                <div class="info-label">
                    <?= $lang === 'ar' ? 'المجموعة' : 'Batch' ?>
                </div>

                <div class="info-value">

                    <?= e(
                        $payment['batch_code']
                    ) ?>

                </div>

            </div>


            <div class="info-item">

                <div class="info-label">
                    <?= $lang === 'ar' ? 'تاريخ التسجيل' : 'Enrollment Date' ?>
                </div>

                <div class="info-value">

                    <?= formatDate(
                        $payment['enrollment_date']
                    ) ?>

                </div>

            </div>


        </div>


        <!-- ========================================================
             PAYMENT SUMMARY
        ========================================================= -->

        <h3
            class="card-title"
            style="margin-top:30px;"
        >
            <?= $lang === 'ar' ? 'ملخص الدفع' : 'Payment Summary' ?>
        </h3>


        <div class="summary-grid">


            <div class="summary-box">

                <div class="label">
                    <?= $lang === 'ar' ? 'الرسوم الفعلية' : 'Actual Fee' ?>
                </div>

                <div class="value">
                    <?= money($actualFee) ?>
                </div>

            </div>


            <div class="summary-box">

                <div class="label">
                    <?= $lang === 'ar' ? 'إجمالي المدفوع' : 'Total Paid' ?>
                </div>

                <div class="value">
                    <?= money($totalPaid) ?>
                </div>

            </div>


            <div class="summary-box">

                <div class="label">
                    <?= $lang === 'ar' ? 'هذا الدفع' : 'This Payment' ?>
                </div>

                <div class="value">
                    <?= money($payment['amount']) ?>
                </div>

            </div>


            <div class="summary-box">

                <div class="label">
                    <?= $lang === 'ar' ? 'المتبقي' : 'Remaining' ?>
                </div>

                <div class="value">
                    <?= money($remainingBalance) ?>
                </div>

            </div>


        </div>


        <!-- STATUS -->

        <div
            style="
                margin-top:20px;
                text-align:right;
            "
        >

            <strong>
                <?= $lang === 'ar' ? 'حالة الدفع:' : 'Payment Status:' ?>
            </strong>

            <?php if ($paymentStatus === 'Paid'): ?>

                <span class="status status-paid">
                    <?= $lang === 'ar' ? 'مدفوع' : 'Paid' ?>
                </span>

            <?php elseif ($paymentStatus === 'Partially Paid'): ?>

                <span class="status status-partial">
                    <?= $lang === 'ar' ? 'مدفوع جزئيًا' : 'Partially Paid' ?>
                </span>

            <?php else: ?>

                <span class="status status-unpaid">
                    <?= $lang === 'ar' ? 'غير مدفوع' : 'Unpaid' ?>
                </span>

            <?php endif; ?>

        </div>


        <!-- ========================================================
             NOTES
        ========================================================= -->

        <?php if (!empty($payment['notes'])): ?>

            <h3
                class="card-title"
                style="margin-top:30px;"
            >
                <?= $lang === 'ar' ? 'ملاحظات' : 'Notes' ?>
            </h3>

            <div class="notes">

                <?= nl2br(
                    e($payment['notes'])
                ) ?>

            </div>

        <?php endif; ?>


        <!-- PRINT FOOTER -->

        <div
            class="print-receipt"
            style="
                margin-top:45px;
                text-align:center;
                color:#555;
                font-size:13px;
            "
        >

            <p>
                <?= $lang === 'ar' ? 'شكرًا لك على الدفع.' : 'Thank you for your payment.' ?>
            </p>

            <p>
                ALAWLAMA TRAINING CENTER
            </p>

        </div>


    </div>


    <!-- ============================================================
         PAYMENT HISTORY
    ============================================================= -->

    <div class="card payment-history-card">

        <h2 class="card-title">
            <?= $lang === 'ar' ? 'سجل المدفوعات' : 'Payment History' ?>
        </h2>


        <div class="table-wrapper">

            <?php if (!empty($paymentHistory)): ?>

                <table>

                    <thead>

                        <tr>

                            <th>
                                #
                            </th>

                            <th>
                                <?= $lang === 'ar' ? 'رقم الإيصال' : 'Receipt No.' ?>
                            </th>

                            <th>
                                <?= $lang === 'ar' ? 'التاريخ' : 'Date' ?>
                            </th>

                            <th>
                                <?= $lang === 'ar' ? 'المبلغ المدفوع' : 'Amount Paid' ?>
                            </th>

                            <th>
                                <?= $lang === 'ar' ? 'إجمالي المدفوع' : 'Total Paid' ?>
                            </th>

                            <th>
                                <?= $lang === 'ar' ? 'المتبقي' : 'Remaining' ?>
                            </th>

                            <th>
                                <?= $lang === 'ar' ? 'الطريقة' : 'Method' ?>
                            </th>

                            <th>
                                <?= $lang === 'ar' ? 'سجّل بواسطة' : 'Recorded By' ?>
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php foreach (
                        $paymentHistory
                        as $index => $history
                    ): ?>

                        <tr
                            class="<?= (int)$history['id']
                                === $paymentId
                                ? 'current-payment'
                                : '' ?>"
                        >

                            <td>
                                <?= $index + 1 ?>
                            </td>

                            <td>

                                <strong>

                                    <?= e(
                                        $history['receipt_number']
                                    ) ?>

                                </strong>

                                <?php if (
                                    (int)$history['id']
                                    === $paymentId
                                ): ?>

                                    <small
                                        style="
                                            color:#2563eb;
                                            margin-left:5px;
                                        "
                                    >
                                        <?= $lang === 'ar' ? 'الحالي' : 'Current' ?>
                                    </small>

                                <?php endif; ?>

                            </td>

                            <td>

                                <?= formatDate(
                                    $history['payment_date']
                                ) ?>

                            </td>

                            <td>
                                <strong><?= money($history['amount']) ?></strong>
                            </td>

                            <td>
                                <?= money($history['cumulative_paid']) ?>
                            </td>

                            <td>
                                <?= money(max(0, $actualFee - (float)$history['cumulative_paid'])) ?>
                            </td>

                            <td>
                                <?= e($history['payment_method']) ?>
                            </td>

                            <td>

                                <?= e(
                                    $history['recorded_by_username']
                                    ?: '-'
                                ) ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            <?php else: ?>

                <p>
                    <?= $lang === 'ar' ? 'لا يوجد سجل للمدفوعات.' : 'No payment history found.' ?>
                </p>

            <?php endif; ?>

        </div>

    </div>


</main>

</body>

</html>