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
| Filters
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');
$paymentMethod = trim($_GET['payment_method'] ?? '');

/*
|--------------------------------------------------------------------------
| Allowed Payment Methods
|--------------------------------------------------------------------------
*/

$allowedMethods = [
    'Cash',
    'Bank Transfer'
];

/*
|--------------------------------------------------------------------------
| Get Payments
|--------------------------------------------------------------------------
*/

$sql = "
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

        s.student_code,
        s.full_name AS student_name,

        b.batch_code,

        c.course_code,
        c.course_name,

        e.actual_fee,

        COALESCE(
            (
                SELECT SUM(p2.amount)
                FROM payments p2
                WHERE p2.enrollment_id = p.enrollment_id
            ),
            0
        ) AS total_paid,

        COALESCE(
            (
                SELECT SUM(p2.amount)
                FROM payments p2
                WHERE p2.enrollment_id = p.enrollment_id
                  AND (
                      p2.payment_date < p.payment_date
                      OR (
                          p2.payment_date = p.payment_date
                          AND p2.id <= p.id
                      )
                  )
            ),
            0
        ) AS paid_to_date,

        GREATEST(
            e.actual_fee - COALESCE(
                (
                    SELECT SUM(p3.amount)
                    FROM payments p3
                    WHERE p3.enrollment_id = p.enrollment_id
                      AND (
                          p3.payment_date < p.payment_date
                          OR (p3.payment_date = p.payment_date AND p3.id <= p.id)
                      )
                ), 0
            ), 0
        ) AS remaining_balance,

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

    WHERE 1 = 1
";

$params = [];

/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $sql .= "
        AND (
            p.receipt_number LIKE ?
            OR s.student_code LIKE ?
            OR s.full_name LIKE ?
            OR b.batch_code LIKE ?
            OR c.course_code LIKE ?
            OR c.course_name LIKE ?
        )
    ";

    $searchTerm = '%' . $search . '%';

    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

/*
|--------------------------------------------------------------------------
| Payment Method Filter
|--------------------------------------------------------------------------
*/

if (
    $paymentMethod !== ''
    && in_array($paymentMethod, $allowedMethods, true)
) {

    $sql .= "
        AND p.payment_method = ?
    ";

    $params[] = $paymentMethod;
}

/*
|--------------------------------------------------------------------------
| Order
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY
        p.payment_date DESC,
        p.id DESC
";

/*
|--------------------------------------------------------------------------
| Execute
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$payments = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Count
|--------------------------------------------------------------------------
*/

$totalPayments = count($payments);

/*
|--------------------------------------------------------------------------
| Total Amount
|--------------------------------------------------------------------------
*/

$totalAmount = 0;

foreach ($payments as $payment) {
    $totalAmount += (float)$payment['amount'];
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
        Payments - ALAW LAMA Training Center
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

            color: #ffffff;

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

            color: #ffffff;
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

            letter-spacing: 0.5px;
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

        .language-switcher {
            display: flex;
            gap: 6px;
            margin: 0 0 18px;
        }

        .language-switcher a {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 7px 5px;
            margin: 0;
            border: 1px solid #374151;
            background: #1f2937;
            color: #d1d5db;
            border-radius: 5px;
            font-size: 12px;
            font-weight: bold;
            text-align: center;
        }

        .language-switcher a:hover {
            background: #374151;
            color: #ffffff;
        }

        .language-switcher a.active {
            background: #2563eb;
            border-color: #2563eb;
            color: #ffffff;
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
             * No min-height: 100vh
             * This prevents unnecessary empty space.
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
            margin:
                0 0 7px;

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

        .btn-small {
            padding:
                7px 11px;

            font-size: 13px;
        }

        /*
        |--------------------------------------------------------------------------
        | FILTER CARD
        |--------------------------------------------------------------------------
        */

        .filter-card {
            background: #ffffff;

            border-radius: 12px;

            padding: 24px;

            margin-bottom: 25px;

            box-shadow:
                0 4px 20px
                rgba(0, 0, 0, 0.06);

            border:
                1px solid #f0f0f0;
        }

        .filter-form {
            display: grid;

            grid-template-columns:
                minmax(250px, 1.8fr)
                minmax(180px, 1fr)
                auto
                auto;

            gap: 15px;

            align-items: end;
        }

        .form-group {
            min-width: 0;
        }

        .form-group label {
            display: block;

            margin-bottom: 8px;

            font-size: 14px;

            font-weight: bold;

            color: #374151;
        }

        input,
        select {
            width: 100%;

            height: 48px;

            padding:
                0 14px;

            border:
                1px solid #d1d5db;

            border-radius: 8px;

            background: #ffffff;

            color: #111827;

            font-family: inherit;

            font-size: 15px;

            outline: none;
        }

        input:focus,
        select:focus {
            border-color: #2563eb;

            box-shadow:
                0 0 0 3px
                rgba(37, 99, 235, 0.10);
        }

        /*
        |--------------------------------------------------------------------------
        | SUMMARY
        |--------------------------------------------------------------------------
        */

        .summary {
            display: flex;

            gap: 15px;

            margin-bottom: 20px;
        }

        .summary-card {
            background: #ffffff;

            border:
                1px solid #f0f0f0;

            border-radius: 10px;

            padding:
                16px 20px;

            box-shadow:
                0 3px 15px
                rgba(0, 0, 0, 0.05);
        }

        .summary-label {
            font-size: 12px;

            color: #6b7280;

            text-transform: uppercase;

            font-weight: bold;

            margin-bottom: 6px;
        }

        .summary-value {
            font-size: 22px;

            font-weight: bold;

            color: #111827;
        }

        /*
        |--------------------------------------------------------------------------
        | TABLE
        |--------------------------------------------------------------------------
        */

        .result-count {
            margin-bottom: 15px;

            color: #6b7280;

            font-size: 15px;
        }

        .table-card {
            width: 100%;

            background: #ffffff;

            border-radius: 12px;

            box-shadow:
                0 4px 20px
                rgba(0, 0, 0, 0.06);

            border:
                1px solid #f0f0f0;

            overflow: hidden;
        }

        .table-wrapper {
            width: 100%;

            overflow-x: auto;
        }

        table {
            width: 100%;

            min-width: 1350px;

            border-collapse: collapse;
        }

        th {
            padding:
                17px 14px;

            background: #fafafa;

            border-bottom:
                1px solid #e5e7eb;

            color: #374151;

            text-align: left;

            font-size: 14px;

            font-weight: bold;

            white-space: nowrap;
        }

        td {
            padding:
                16px 14px;

            border-bottom:
                1px solid #eeeeee;

            font-size: 14px;

            color: #374151;

            vertical-align: middle;

            white-space: nowrap;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tbody tr:hover {
            background: #fafafa;
        }

        /*
        |--------------------------------------------------------------------------
        | STUDENT
        |--------------------------------------------------------------------------
        */

        .student-name {
            font-weight: bold;

            color: #111827;

            margin-bottom: 4px;
        }

        .student-code {
            font-size: 12px;

            color: #6b7280;
        }

        /*
        |--------------------------------------------------------------------------
        | COURSE
        |--------------------------------------------------------------------------
        */

        .course-name {
            font-weight: bold;

            color: #111827;

            margin-bottom: 4px;
        }

        .course-code {
            font-size: 12px;

            color: #6b7280;
        }

        /*
        |--------------------------------------------------------------------------
        | BATCH
        |--------------------------------------------------------------------------
        */

        .batch-code {
            font-weight: bold;

            color: #111827;
        }

        /*
        |--------------------------------------------------------------------------
        | PAYMENT METHOD
        |--------------------------------------------------------------------------
        */

        .method-badge {
            display: inline-block;

            padding:
                6px 10px;

            border-radius: 999px;

            background: #f3f4f6;

            color: #374151;

            font-size: 12px;

            font-weight: bold;
        }

        /*
        |--------------------------------------------------------------------------
        | AMOUNT
        |--------------------------------------------------------------------------
        */

        .amount {
            font-weight: bold;

            color: #111827;
        }

        .paid-to-date {
            font-weight: bold;
            color: #16a34a;
        }

        .remaining-balance {
            font-weight: bold;
        }

        .remaining-balance.paid {
            color: #16a34a;
        }

        .remaining-balance.due {
            color: #dc2626;
        }

        /*
        |--------------------------------------------------------------------------
        | EMPTY STATE
        |--------------------------------------------------------------------------
        */

        .empty-state {
            text-align: center;

            padding:
                55px 20px;

            color: #6b7280;

            font-size: 15px;
        }

        /* =========================
           RTL SUPPORT
        ========================= */
        <?php if ($lang === 'ar'): ?>
        body {
            direction: rtl;
        }

        .sidebar {
            left: auto;
            right: 0;
        }

        .main-content {
            margin-left: 0;
            margin-right: 250px;
        }

        .brand,
        .menu-title,
        .sidebar a,
        .page-title,
        .form-group,
        .summary-card,
        .result-count,
        table {
            text-align: right;
        }

        .header-actions {
            direction: rtl;
        }

        .filter-form {
            direction: rtl;
        }

        .language-switcher {
            direction: rtl;
        }

        th {
            text-align: right;
        }
        <?php endif; ?>

        /*
        |--------------------------------------------------------------------------
        | RESPONSIVE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 1100px) {

            .filter-form {
                grid-template-columns:
                    1fr
                    1fr
                    auto
                    auto;
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

            <?php if ($lang === 'ar'): ?>
            .sidebar {
                left: auto;
                right: 0;
            }

            .main-content {
                margin-left: 0;
                margin-right: 210px;
            }
            <?php endif; ?>

            .page-header {
                flex-direction: column;
            }

            .filter-form {
                grid-template-columns: 1fr;
            }

            .summary {
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

            .filter-card {
                padding: 18px;
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


    <!-- LANGUAGE SWITCHER -->
    <div class="language-switcher">
        <a href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['lang' => 'en']))) ?>"
           class="<?= $lang === 'en' ? 'active' : '' ?>">
            English
        </a>
        <a href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['lang' => 'ar']))) ?>"
           class="<?= $lang === 'ar' ? 'active' : '' ?>">
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
                <?= $lang === 'ar' ? 'المدفوعات' : 'Payments' ?>
            </h1>

            <p>
                <?= $lang === 'ar'
                    ? 'إدارة مدفوعات الطلاب وسجلات الدفع.'
                    : 'Manage student payments and payment records.' ?>
            </p>

        </div>


        <div class="header-actions">

            <a
                href="../dashboard/index.php"
                class="btn btn-secondary"
            >
                <?= $lang === 'ar' ? 'رجوع ←' : '← Back' ?>
            </a>

            <a
                href="add.php"
                class="btn btn-primary"
            >
                <?= $lang === 'ar' ? 'تسجيل دفعة +' : '+ Record Payment' ?>
            </a>

        </div>

    </div>


    <!-- ============================================================
         FILTERS
    ============================================================= -->

    <div class="filter-card">

        <form
            method="GET"
            action="index.php"
            class="filter-form"
        >


            <!-- SEARCH -->

            <div class="form-group">

                <label for="search">
                    <?= $lang === 'ar' ? 'بحث' : 'Search' ?>
                </label>

                <input
                    type="text"
                    id="search"
                    name="search"
                    value="<?= e($search) ?>"
                    placeholder="<?= $lang === 'ar' ? 'رقم الإيصال، الطالب، المجموعة، المقرر...' : 'Receipt No., student, batch, course...' ?>"
                >

            </div>


            <!-- PAYMENT METHOD -->

            <div class="form-group">

                <label for="payment_method">
                    <?= $lang === 'ar' ? 'طريقة الدفع' : 'Payment Method' ?>
                </label>

                <select
                    id="payment_method"
                    name="payment_method"
                >

                    <option value="">
                        <?= $lang === 'ar' ? 'كل الطرق' : 'All Methods' ?>
                    </option>

                    <?php foreach ($allowedMethods as $method): ?>

                        <option
                            value="<?= e($method) ?>"
                            <?= $paymentMethod === $method
                                ? 'selected'
                                : '' ?>
                        >

                            <?= $lang === 'ar' && $method === 'Cash' ? 'نقداً' : ($lang === 'ar' && $method === 'Bank Transfer' ? 'تحويل بنكي' : e($method)) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- SEARCH -->

            <button
                type="submit"
                class="btn btn-primary"
            >
                <?= $lang === 'ar' ? 'بحث' : 'Search' ?>
            </button>


            <!-- CLEAR -->

            <a
                href="index.php"
                class="btn btn-secondary"
            >
                <?= $lang === 'ar' ? 'مسح' : 'Clear' ?>
            </a>


        </form>

    </div>


    <!-- ============================================================
         SUMMARY
    ============================================================= -->

    <div class="summary">

        <div class="summary-card">

            <div class="summary-label">
                <?= $lang === 'ar' ? 'المدفوعات الموجودة' : 'Payments Found' ?>
            </div>

            <div class="summary-value">
                <?= $totalPayments ?>
            </div>

        </div>


        <div class="summary-card">

            <div class="summary-label">
                <?= $lang === 'ar' ? 'إجمالي المبلغ' : 'Total Amount' ?>
            </div>

            <div class="summary-value">
                <?= money($totalAmount) ?>
            </div>

        </div>

    </div>


    <!-- ============================================================
         RESULT COUNT
    ============================================================= -->

    <div class="result-count">

        <?= $totalPayments ?>

        <?= $lang === 'ar'
            ? ($totalPayments === 1 ? 'دفعة موجودة' : 'دفعات موجودة')
            : ($totalPayments === 1 ? 'payment' : 'payments')
        ?>

        <?= $lang === 'ar' ? '' : 'found' ?>

    </div>


    <!-- ============================================================
         PAYMENTS TABLE
    ============================================================= -->

    <div class="table-card">

        <div class="table-wrapper">

            <?php if (!empty($payments)): ?>

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
                                <?= $lang === 'ar' ? 'الطالب' : 'Student' ?>
                            </th>

                            <th>
                                <?= $lang === 'ar' ? 'المقرر' : 'Course' ?>
                            </th>

                            <th>
                                <?= $lang === 'ar' ? 'المجموعة' : 'Batch' ?>
                            </th>

                            <th>
                                <?= $lang === 'ar' ? 'تاريخ الدفع' : 'Payment Date' ?>
                            </th>

                            <th>
                                <?= $lang === 'ar' ? 'المبلغ المدفوع' : 'Amount Paid' ?>
                            </th>

                            <th>
                                <?= $lang === 'ar' ? 'إجمالي المدفوع حتى الآن' : 'Paid to Date' ?>
                            </th>

                            <th>
                                <?= $lang === 'ar' ? 'المتبقي' : 'Remaining' ?>
                            </th>

                            <th>
                                <?= $lang === 'ar' ? 'الطريقة' : 'Method' ?>
                            </th>

                            <th>
                                <?= $lang === 'ar' ? 'سجل بواسطة' : 'Recorded By' ?>
                            </th>

                            <th>
                                <?= $lang === 'ar' ? 'الإجراءات' : 'Actions' ?>
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php foreach ($payments as $index => $payment): ?>

                        <tr>


                            <!-- NUMBER -->

                            <td>
                                <?= $index + 1 ?>
                            </td>


                            <!-- RECEIPT NUMBER -->

                            <td>

                                <strong>
                                    <?= e($payment['receipt_number']) ?>
                                </strong>

                            </td>


                            <!-- STUDENT -->

                            <td>

                                <div class="student-name">

                                    <?= e($payment['student_name']) ?>

                                </div>

                                <div class="student-code">

                                    <?= e($payment['student_code']) ?>

                                </div>

                            </td>


                            <!-- COURSE -->

                            <td>

                                <div class="course-name">

                                    <?= e($payment['course_name']) ?>

                                </div>

                                <div class="course-code">

                                    <?= e($payment['course_code']) ?>

                                </div>

                            </td>


                            <!-- BATCH -->

                            <td>

                                <div class="batch-code">

                                    <?= e($payment['batch_code']) ?>

                                </div>

                            </td>


                            <!-- DATE -->

                            <td>

                                <?= formatDate(
                                    $payment['payment_date']
                                ) ?>

                            </td>


                            <!-- AMOUNT PAID -->

                            <td>

                                <span class="amount">

                                    <?= money(
                                        $payment['amount']
                                    ) ?>

                                </span>

                            </td>


                            <!-- PAID TO DATE -->

                            <td>

                                <span class="paid-to-date">

                                    <?= money(
                                        $payment['paid_to_date']
                                    ) ?>

                                </span>

                            </td>


                            <!-- REMAINING BALANCE -->

                            <td>

                                <?php
                                $remainingBalance = max(
                                    0,
                                    (float)$payment['actual_fee'] - (float)$payment['paid_to_date']
                                );
                                ?>

                                <span class="remaining-balance <?= $remainingBalance <= 0 ? 'paid' : 'due' ?>">

                                    <?= money($remainingBalance) ?>

                                </span>

                            </td>


                            <!-- METHOD -->

                            <td>

                                <span class="method-badge">

                                    <?= e(
                                        $payment['payment_method']
                                    ) ?>

                                </span>

                            </td>


                            <!-- RECORDED BY -->

                            <td>

                                <?= e(
                                    $payment['recorded_by_username']
                                    ?: '-'
                                ) ?>

                            </td>


                            <!-- ACTIONS -->

                            <td>

                                <div
                                    style="
                                        display:flex;
                                        gap:7px;
                                    "
                                >

                                    <a
                                        href="view.php?id=<?= (int)$payment['id'] ?>"
                                        class="btn btn-secondary btn-small"
                                    >
                                        <?= $lang === 'ar' ? 'عرض' : 'View' ?>
                                    </a>


                                </div>

                            </td>


                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            <?php else: ?>

                <div class="empty-state">

                    <?= $lang === 'ar' ? 'لم يتم العثور على سجلات دفع.' : 'No payment records found.' ?>

                    <?php if (
                        $search !== ''
                        || $paymentMethod !== ''
                    ): ?>

                        <br><br>

                        <a
                            href="index.php"
                            class="btn btn-secondary"
                        >
                            <?= $lang === 'ar' ? 'مسح الفلاتر' : 'Clear Filters' ?>
                        </a>

                    <?php endif; ?>

                </div>

            <?php endif; ?>

        </div>

    </div>


</main>

</body>

</html>