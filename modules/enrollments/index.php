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
    die('Access denied.');
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

function money($value)
{
    return number_format((float)$value, 2);
}

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/
$search = trim($_GET['search'] ?? '');
$courseId = filter_input(INPUT_GET, 'course_id', FILTER_VALIDATE_INT);
$status = trim($_GET['status'] ?? '');

/*
|--------------------------------------------------------------------------
| Get Courses For Filter
|--------------------------------------------------------------------------
*/
$courseStmt = $pdo->query("
    SELECT
        id,
        course_code,
        course_name
    FROM courses
    ORDER BY course_name ASC
");

$courses = $courseStmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Allowed Enrollment Statuses
|--------------------------------------------------------------------------
*/
$allowedStatuses = [
    'active',
    'completed',
    'suspended',
    'cancelled'
];

/*
|--------------------------------------------------------------------------
| Build Enrollment Query
|--------------------------------------------------------------------------
*/
$sql = "
    SELECT
        e.id,
        e.enrollment_date,
        e.original_fee,
        e.discount,
        e.actual_fee,
        e.status,
        e.notes,

        s.student_code,
        s.full_name AS student_name,
        s.phone AS student_phone,

        b.batch_code,
        b.start_date,
        b.end_date,
        b.status AS batch_status,

        c.id AS course_id,
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
            s.student_code LIKE ?
            OR s.full_name LIKE ?
            OR s.phone LIKE ?
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
| Course Filter
|--------------------------------------------------------------------------
*/
if ($courseId) {

    $sql .= " AND c.id = ? ";

    $params[] = $courseId;
}

/*
|--------------------------------------------------------------------------
| Status Filter
|--------------------------------------------------------------------------
*/
if ($status !== '' && in_array($status, $allowedStatuses, true)) {

    $sql .= " AND e.status = ? ";

    $params[] = $status;
}

/*
|--------------------------------------------------------------------------
| Order
|--------------------------------------------------------------------------
*/
$sql .= "
    ORDER BY e.id DESC
";

/*
|--------------------------------------------------------------------------
| Execute
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$enrollments = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Count
|--------------------------------------------------------------------------
*/
$totalEnrollments = count($enrollments);
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
        <?= $lang === 'ar' ? 'التسجيلات - مركز العوامة للتدريب' : 'Enrollments - ALAW LAMA Training Center' ?>
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Georgia, "Times New Roman", serif;
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

        /* RTL: move the sidebar to the right and content to the left */
        html[dir="rtl"] .sidebar {
            left: auto;
            right: 0;
        }

        html[dir="rtl"] .main-content {
            margin-left: 0;
            margin-right: 250px;
            direction: rtl;
        }

        html[dir="rtl"] .brand,
        html[dir="rtl"] .menu-title,
        html[dir="rtl"] .sidebar a {
            text-align: right;
        }

        /*
        |--------------------------------------------------------------------------
        | PAGE HEADER
        |--------------------------------------------------------------------------
        */

        .page-header {
            display: flex;

            justify-content: space-between;
            align-items: flex-start;

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

            padding: 12px 18px;

            border-radius: 8px;

            text-decoration: none;

            font-size: 14px;

            cursor: pointer;

            border: 1px solid transparent;

            white-space: nowrap;
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
            padding: 7px 11px;

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
                0 4px 20px rgba(0, 0, 0, 0.06);

            border: 1px solid #f0f0f0;
        }

        .filter-form {
            display: grid;

            grid-template-columns:
                minmax(240px, 1.8fr)
                minmax(170px, 1fr)
                minmax(170px, 1fr)
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

            padding: 0 14px;

            border: 1px solid #d1d5db;

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
                0 0 0 3px rgba(37, 99, 235, 0.10);
        }

        /*
        |--------------------------------------------------------------------------
        | RESULT COUNT
        |--------------------------------------------------------------------------
        */

        .result-count {
            margin-bottom: 15px;

            color: #6b7280;

            font-size: 15px;
        }

        /*
        |--------------------------------------------------------------------------
        | TABLE
        |--------------------------------------------------------------------------
        */

        .table-card {
            width: 100%;

            background: #ffffff;

            border-radius: 12px;

            box-shadow:
                0 4px 20px rgba(0, 0, 0, 0.06);

            border: 1px solid #f0f0f0;

            overflow: hidden;
        }

        .table-wrapper {
            width: 100%;

            overflow-x: auto;
        }

        table {
            width: 100%;

            min-width: 1100px;

            border-collapse: collapse;
        }

        th {
            padding: 17px 14px;

            background: #fafafa;

            border-bottom: 1px solid #e5e7eb;

            color: #374151;

            text-align: left;

            font-size: 14px;

            font-weight: bold;

            white-space: nowrap;
        }

        td {
            padding: 16px 14px;

            border-bottom: 1px solid #eeeeee;

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

            margin-bottom: 4px;
        }

        .batch-date {
            font-size: 12px;

            color: #6b7280;
        }

        /*
        |--------------------------------------------------------------------------
        | STATUS
        |--------------------------------------------------------------------------
        */

        .status-badge {
            display: inline-block;

            padding: 6px 11px;

            border-radius: 999px;

            font-size: 12px;

            font-weight: bold;

            white-space: nowrap;
        }

        .status-active {
            background: #dcfce7;

            color: #166534;
        }

        .status-completed {
            background: #dbeafe;

            color: #1e40af;
        }

        .status-suspended {
            background: #fef3c7;

            color: #92400e;
        }

        .status-cancelled {
            background: #fee2e2;

            color: #991b1b;
        }

        /*
        |--------------------------------------------------------------------------
        | PAYMENT STATUS
        |--------------------------------------------------------------------------
        */

        .payment-paid {
            color: #166534;

            font-weight: bold;
        }

        .payment-partial {
            color: #92400e;

            font-weight: bold;
        }

        .payment-unpaid {
            color: #991b1b;

            font-weight: bold;
        }

        /*
        |--------------------------------------------------------------------------
        | ACTIONS
        |--------------------------------------------------------------------------
        */

        .actions {
            display: flex;

            gap: 7px;
        }

        /*
        |--------------------------------------------------------------------------
        | EMPTY STATE
        |--------------------------------------------------------------------------
        */

        .empty-state {
            text-align: center;

            padding: 55px 20px;

            color: #6b7280;

            font-size: 15px;
        }

        /*
        |--------------------------------------------------------------------------
        | RESPONSIVE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 1200px) {

            .filter-form {
                grid-template-columns:
                    1fr
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

                width: calc(100% - 210px);

                padding: 25px;
            }

            html[dir="rtl"] .main-content {
                margin-left: 0;
                margin-right: 210px;
                width: calc(100% - 210px);
            }

            .page-header {
                flex-direction: column;
            }

            .filter-form {
                grid-template-columns: 1fr;
            }

        }

        @media (max-width: 600px) {

            .sidebar {
                display: none;
            }

            .main-content {
                margin-left: 0;

                width: 100%;

                padding: 20px 15px;
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
                <?= $lang === 'ar' ? 'التسجيلات' : 'Enrollments' ?>
            </h1>

            <p>
                <?= $lang === 'ar' ? 'إدارة تسجيلات الطلاب والرسوم وحالة التسجيل.' : 'Manage student enrollments, fees, and enrollment status.' ?>
            </p>

        </div>


        <div class="header-actions">

            <!-- BACK BUTTON -->

            <a
                href="../../modules/dashboard/index.php"
                class="btn btn-secondary"
            >
                ← <?= $lang === 'ar' ? 'رجوع' : 'Back' ?>
            </a>


            <!-- ADD ENROLLMENT -->

            <a
                href="add.php"
                class="btn btn-primary"
            >
                + <?= $lang === 'ar' ? 'إضافة تسجيل' : 'Add Enrollment' ?>
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
                    placeholder="Student ID, name, batch, course..."
                >

            </div>


            <!-- COURSE -->

            <div class="form-group">

                <label for="course_id">
                    <?= $lang === 'ar' ? 'الدورة' : 'Course' ?>
                </label>

                <select
                    id="course_id"
                    name="course_id"
                >

                    <option value="">
                        <?= $lang === 'ar' ? 'كل الدورات' : 'All Courses' ?>
                    </option>

                    <?php foreach ($courses as $course): ?>

                        <option
                            value="<?= (int)$course['id'] ?>"
                            <?= $courseId == $course['id'] ? 'selected' : '' ?>
                        >

                            <?= e($course['course_name']) ?>

                            (<?= e($course['course_code']) ?>)

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- STATUS -->

            <div class="form-group">

                <label for="status">
                    <?= $lang === 'ar' ? 'الحالة' : 'Status' ?>
                </label>

                <select
                    id="status"
                    name="status"
                >

                    <option value="">
                        <?= $lang === 'ar' ? 'كل الحالات' : 'All Statuses' ?>
                    </option>

                    <?php foreach ($allowedStatuses as $itemStatus): ?>

                        <option
                            value="<?= e($itemStatus) ?>"
                            <?= $status === $itemStatus ? 'selected' : '' ?>
                        >

                            <?= e(ucfirst($itemStatus)) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- SEARCH BUTTON -->

            <button
                type="submit"
                class="btn btn-primary"
            >
                <?= $lang === 'ar' ? 'بحث' : 'Search' ?>
            </button>


            <!-- CLEAR BUTTON -->

            <a
                href="index.php"
                class="btn btn-secondary"
            >
                <?= $lang === 'ar' ? 'مسح' : 'Clear' ?>
            </a>


        </form>

    </div>


    <!-- ============================================================
         RESULT COUNT
    ============================================================= -->

    <div class="result-count">

        <?= $totalEnrollments ?>

        <?= $lang === 'ar'
            ? ($totalEnrollments === 1 ? 'تسجيل' : 'تسجيلات')
            : ($totalEnrollments === 1 ? 'enrollment' : 'enrollments')
        ?>

        <?= $lang === 'ar' ? 'موجودة' : 'found' ?>

    </div>


    <!-- ============================================================
         ENROLLMENTS TABLE
    ============================================================= -->

    <div class="table-card">

        <div class="table-wrapper">

            <?php if (!empty($enrollments)): ?>

                <table>

                    <thead>

                        <tr>

                            <th>
                                #
                            </th>

                            <th>
                                <?= $lang === 'ar' ? 'الطالب' : 'Student' ?>
                            </th>

                            <th>
                                <?= $lang === 'ar' ? 'الدورة' : 'Course' ?>
                            </th>

                            <th>
                                <?= $lang === 'ar' ? 'المجموعة' : 'Batch' ?>
                            </th>

                            <th>
                                <?= $lang === 'ar' ? 'تاريخ التسجيل' : 'Enrollment Date' ?>
                            </th>

                            <th>
                                <?= $lang === 'ar' ? 'الرسوم الأصلية' : 'Original Fee' ?>
                            </th>

                            <th>
                                <?= $lang === 'ar' ? 'الخصم' : 'Discount' ?>
                            </th>

                            <th>
                                <?= $lang === 'ar' ? 'الرسوم الفعلية' : 'Actual Fee' ?>
                            </th>

                            <th>
                                <?= $lang === 'ar' ? 'المدفوع' : 'Paid' ?>
                            </th>

                            <th>
                                <?= $lang === 'ar' ? 'الرصيد المتبقي' : 'Balance' ?>
                            </th>

                            <th>
                                <?= $lang === 'ar' ? 'حالة الدفع' : 'Payment Status' ?>
                            </th>

                            <th>
                                <?= $lang === 'ar' ? 'حالة التسجيل' : 'Enrollment Status' ?>
                            </th>

                            <th>
                                <?= $lang === 'ar' ? 'الإجراءات' : 'Actions' ?>
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php foreach ($enrollments as $index => $enrollment): ?>

                        <?php

                        $actualFee = (float)$enrollment['actual_fee'];

                        $totalPaid = (float)$enrollment['total_paid'];

                        $balance = max(
                            0,
                            $actualFee - $totalPaid
                        );


                        if ($totalPaid <= 0) {

                            $paymentStatus = ($lang === 'ar') ? 'غير مدفوع' : 'Unpaid';

                            $paymentClass = 'payment-unpaid';

                        } elseif ($totalPaid < $actualFee) {

                            $paymentStatus = ($lang === 'ar') ? 'مدفوع جزئياً' : 'Partially Paid';

                            $paymentClass = 'payment-partial';

                        } else {

                            $paymentStatus = ($lang === 'ar') ? 'مدفوع' : 'Paid';

                            $paymentClass = 'payment-paid';

                        }

                        ?>

                        <tr>


                            <!-- NUMBER -->

                            <td>
                                <?= $index + 1 ?>
                            </td>


                            <!-- STUDENT -->

                            <td>

                                <div class="student-name">

                                    <?= e($enrollment['student_name']) ?>

                                </div>

                                <div class="student-code">

                                    <?= e($enrollment['student_code']) ?>

                                </div>

                            </td>


                            <!-- COURSE -->

                            <td>

                                <div class="course-name">

                                    <?= e($enrollment['course_name']) ?>

                                </div>

                                <div class="course-code">

                                    <?= e($enrollment['course_code']) ?>

                                </div>

                            </td>


                            <!-- BATCH -->

                            <td>

                                <div class="batch-code">

                                    <?= e($enrollment['batch_code']) ?>

                                </div>

                                <div class="batch-date">

                                    <?= e($enrollment['start_date']) ?>

                                    →

                                    <?= e($enrollment['end_date']) ?>

                                </div>

                            </td>


                            <!-- ENROLLMENT DATE -->

                            <td>

                                <?= e($enrollment['enrollment_date']) ?>

                            </td>


                            <!-- ORIGINAL FEE -->

                            <td>

                                <?= money($enrollment['original_fee']) ?>

                            </td>


                            <!-- DISCOUNT -->

                            <td>

                                <?= money($enrollment['discount']) ?>

                            </td>


                            <!-- ACTUAL FEE -->

                            <td>

                                <strong>
                                    <?= money($actualFee) ?>
                                </strong>

                            </td>


                            <!-- PAID -->

                            <td>

                                <?= money($totalPaid) ?>

                            </td>


                            <!-- BALANCE -->

                            <td>

                                <strong>
                                    <?= money($balance) ?>
                                </strong>

                            </td>


                            <!-- PAYMENT STATUS -->

                            <td>

                                <span class="<?= e($paymentClass) ?>">

                                    <?= e($paymentStatus) ?>

                                </span>

                            </td>


                            <!-- ENROLLMENT STATUS -->

                            <td>

                                <?php

                                $statusClass = match (
                                    strtolower($enrollment['status'])
                                ) {

                                    'active'
                                        => 'status-active',

                                    'completed'
                                        => 'status-completed',

                                    'suspended'
                                        => 'status-suspended',

                                    'cancelled'
                                        => 'status-cancelled',

                                    default
                                        => ''

                                };

                                ?>

                                <span
                                    class="status-badge <?= e($statusClass) ?>"
                                >

                                    <?= e(
                                        ucfirst(
                                            $enrollment['status']
                                        )
                                    ) ?>

                                </span>

                            </td>


                            <!-- ACTIONS -->

                            <td>

                                <div class="actions">

                                    <a
                                        href="view.php?id=<?= (int)$enrollment['id'] ?>"
                                        class="btn btn-secondary btn-small"
                                    >
                                        <?= $lang === 'ar' ? 'عرض' : 'View' ?>
                                    </a>


                                    <a
                                        href="edit.php?id=<?= (int)$enrollment['id'] ?>"
                                        class="btn btn-primary btn-small"
                                    >
                                        <?= $lang === 'ar' ? 'تعديل' : 'Edit' ?>
                                    </a>

                                </div>

                            </td>


                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            <?php else: ?>

                <div class="empty-state">

                    <?= $lang === 'ar' ? 'لم يتم العثور على تسجيلات.' : 'No enrollments found.' ?>

                    <?php if (
                        $search !== ''
                        || $courseId
                        || $status !== ''
                    ): ?>

                        <br><br>

                        <a
                            href="index.php"
                            class="btn btn-secondary"
                        >
                            <?= $lang === 'ar' ? 'مسح عوامل التصفية' : 'Clear Filters' ?>
                        </a>

                    <?php endif; ?>

                </div>

            <?php endif; ?>

        </div>

    </div>


</main>

</body>

</html>