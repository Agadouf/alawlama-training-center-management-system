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
| Get <?= $lang === 'ar' ? 'رقم التسجيل' : 'Enrollment ID' ?>
|--------------------------------------------------------------------------
*/
$enrollmentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$enrollmentId) {
    header('Location: index.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Fetch Enrollment Details
|--------------------------------------------------------------------------
|
| IMPORTANT:
| categories.name and levels.name are used because the actual database
| columns are "name", not "category_name" or "level_name".
|
*/
$stmt = $pdo->prepare("
    SELECT
        e.id,
        e.enrollment_date,
        e.original_fee,
        e.discount,
        e.actual_fee,
        e.status AS enrollment_status,
        e.notes,
        e.created_at,
        e.updated_at,

        s.id AS student_id,
        s.student_code,
        s.full_name AS student_name,
        s.gender,
        s.date_of_birth,
        s.phone AS student_phone,
        s.email AS student_email,
        s.address,
        s.guardian_name,
        s.guardian_phone,
        s.registration_date,
        s.status AS student_status,

        b.id AS batch_id,
        b.batch_code,
        b.start_date,
        b.end_date,
        b.room,
        b.capacity,
        b.status AS batch_status,

        c.id AS course_id,
        c.course_code,
        c.course_name,
        c.description AS course_description,
        c.default_fee,
        c.duration,
        c.status AS course_status,

        cat.name AS category_name,
        lvl.name AS level_name

    FROM enrollments e

    INNER JOIN students s
        ON s.id = e.student_id

    INNER JOIN batches b
        ON b.id = e.batch_id

    INNER JOIN courses c
        ON c.id = b.course_id

    INNER JOIN categories cat
        ON cat.id = c.category_id

    INNER JOIN levels lvl
        ON lvl.id = c.level_id

    WHERE e.id = ?

    LIMIT 1
");

$stmt->execute([$enrollmentId]);

$enrollment = $stmt->fetch();

if (!$enrollment) {
    http_response_code(404);
    die('Enrollment not found.');
}

/*
|--------------------------------------------------------------------------
| Fetch Payments
|--------------------------------------------------------------------------
*/
$paymentStmt = $pdo->prepare("
    SELECT
        p.id,
        p.receipt_number,
        p.amount,
        p.payment_date,
        p.payment_method,
        p.notes,
        p.created_at,

        u.username AS recorded_by_username

    FROM payments p

    LEFT JOIN users u
        ON u.id = p.recorded_by

    WHERE p.enrollment_id = ?

    ORDER BY p.payment_date DESC, p.id DESC
");

$paymentStmt->execute([$enrollmentId]);

$payments = $paymentStmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Calculate Payment Summary
|--------------------------------------------------------------------------
*/
$totalPaid = 0;

foreach ($payments as $payment) {
    $totalPaid += (float) $payment['amount'];
}

$actualFee = (float) $enrollment['actual_fee'];

$remainingBalance = max(0, $actualFee - $totalPaid);

if ($totalPaid <= 0) {
    $paymentStatus = 'Unpaid';
} elseif ($totalPaid < $actualFee) {
    $paymentStatus = 'Partially Paid';
} else {
    $paymentStatus = 'Paid';
}

/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/
function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function formatMoney($amount)
{
    return number_format((float) $amount, 2);
}

function formatDate($date)
{
    if (empty($date)) {
        return '-';
    }

    return date('d M Y', strtotime($date));
}

function statusClass($status)
{
    return match (strtolower((string) $status)) {
        'active' => 'status-active',
        'completed' => 'status-completed',
        'suspended' => 'status-suspended',
        'cancelled' => 'status-cancelled',
        'planned' => 'status-planned',
        'inactive' => 'status-inactive',
        default => 'status-default'
    };
}
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= $lang === 'ar' ? 'تفاصيل التسجيل | مركز العلماء للتدريب' : 'Enrollment Details | ALAW LAMA Training Center' ?></title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f5f7fb;
            color: #1f2937;
        }

        /*
        |--------------------------------------------------------------------------
        | Main Content
        |--------------------------------------------------------------------------
        */

        .main-content {
            margin-left: 250px;
            width: calc(100% - 250px);
            min-height: 100vh;
            padding: 30px;
        }

        /*
        |--------------------------------------------------------------------------
        | Page Header
        |--------------------------------------------------------------------------
        */

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
        }

        .page-title h1 {
            margin: 0 0 7px;
            font-size: 28px;
            color: #111827;
        }

        .page-title p {
            margin: 0;
            color: #6b7280;
            font-size: 14px;
        }

        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        /*
        |--------------------------------------------------------------------------
        | Buttons
        |--------------------------------------------------------------------------
        */

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 16px;
            border-radius: 7px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            border: 1px solid transparent;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .btn-primary {
            background: #2563eb;
            color: #fff;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-secondary {
            background: #fff;
            color: #374151;
            border-color: #d1d5db;
        }

        .btn-secondary:hover {
            background: #f9fafb;
        }

        .btn-print {
            background: #111827;
            color: #fff;
        }

        .btn-print:hover {
            background: #000;
        }

        /*
        |--------------------------------------------------------------------------
        | Cards
        |--------------------------------------------------------------------------
        */

        .card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            margin-bottom: 20px;
            overflow: hidden;
        }

        .card-header {
            padding: 18px 22px;
            border-bottom: 1px solid #e5e7eb;
            background: #fafafa;
        }

        .card-header h2 {
            margin: 0;
            font-size: 18px;
            color: #111827;
        }

        .card-body {
            padding: 22px;
        }

        /*
        |--------------------------------------------------------------------------
        | Information Grid
        |--------------------------------------------------------------------------
        */

        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
        }

        .info-item {
            min-width: 0;
        }

        .info-label {
            font-size: 12px;
            font-weight: 700;
            color: #6b7280;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .info-value {
            font-size: 15px;
            color: #111827;
            word-break: break-word;
        }

        .info-value strong {
            font-size: 16px;
        }

        /*
        |--------------------------------------------------------------------------
        | Status Badges
        |--------------------------------------------------------------------------
        */

        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
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

        .status-planned {
            background: #ede9fe;
            color: #5b21b6;
        }

        .status-inactive {
            background: #f3f4f6;
            color: #374151;
        }

        .status-default {
            background: #e5e7eb;
            color: #374151;
        }

        /*
        |--------------------------------------------------------------------------
        | Payment Summary
        |--------------------------------------------------------------------------
        */

        .payment-summary {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 15px;
        }

        .summary-box {
            padding: 18px;
            border: 1px solid #e5e7eb;
            border-radius: 9px;
            background: #fff;
        }

        .summary-label {
            font-size: 12px;
            color: #6b7280;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .summary-value {
            font-size: 22px;
            font-weight: 700;
            color: #111827;
        }

        /*
        |--------------------------------------------------------------------------
        | Tables
        |--------------------------------------------------------------------------
        */

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 13px 14px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            font-size: 14px;
            white-space: nowrap;
        }

        th {
            background: #f9fafb;
            color: #4b5563;
            font-size: 12px;
            text-transform: uppercase;
            font-weight: 700;
        }

        td {
            color: #374151;
        }

        tr:last-child td {
            border-bottom: none;
        }

        /*
        |--------------------------------------------------------------------------
        | <?= $lang === 'ar' ? 'ملاحظات' : 'Notes' ?>
        |--------------------------------------------------------------------------
        */

        .notes-box {
            padding: 15px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            line-height: 1.6;
            white-space: pre-wrap;
        }

        /*
        |--------------------------------------------------------------------------
        | Empty State
        |--------------------------------------------------------------------------
        */

        .empty-state {
            text-align: center;
            padding: 30px;
            color: #6b7280;
        }

        /*
        |--------------------------------------------------------------------------
        | Print
        |--------------------------------------------------------------------------
        */

        @media print {

            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 0;
            }

            .header-actions {
                display: none;
            }

            body {
                background: #fff;
            }

            .card {
                border: 1px solid #ddd;
                break-inside: avoid;
            }

            .card-header {
                background: #fff;
            }

        }

        /*
        |--------------------------------------------------------------------------
        | Responsive
        |--------------------------------------------------------------------------
        */

        @media (max-width: 1100px) {

            .info-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .payment-summary {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

        }

        @media (max-width: 768px) {

            .main-content {
                margin-left: 210px;
                width: calc(100% - 210px);
                padding: 20px;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .payment-summary {
                grid-template-columns: 1fr;
            }

        }

        @media (max-width: 550px) {

            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 15px;
            }

            .page-title h1 {
                font-size: 23px;
            }

            .card-body {
                padding: 16px;
            }

        }

    .main-content {
            direction: <?= $dir ?>;
        }

        <?php if ($lang === 'ar'): ?>
        th, td {
            text-align: right;
        }
        .page-header {
            direction: rtl;
        }
        .header-actions {
            direction: rtl;
        }
        <?php endif; ?>

    </style>

</head>

<body>

<?php require_once '../../includes/sidebar.php'; ?>

    <!-- ==============================================================
         PAGE HEADER
    ============================================================== -->

    <div class="page-header">

        <div class="page-title">

            <h1><?= $lang === 'ar' ? 'تفاصيل التسجيل' : 'Enrollment Details' ?></h1>

            <p>
                <?= $lang === 'ar' ? 'التسجيل رقم' : 'Enrollment #' ?> <?= e($enrollment['id']) ?>
                —
                <?= e($enrollment['student_name']) ?>
            </p>

        </div>

        <div class="header-actions">

            <a
                href="index.php"
                class="btn btn-secondary"
            >
                ← <?= $lang === 'ar' ? 'رجوع' : 'Back' ?>
            </a>

            <a
                href="edit.php?id=<?= (int) $enrollment['id'] ?>"
                class="btn btn-primary"
            >
                <?= $lang === 'ar' ? 'تعديل التسجيل' : 'Edit Enrollment' ?>
            </a>

            <button
                type="button"
                class="btn btn-print"
                onclick="window.print()"
            >
                <?= $lang === 'ar' ? 'طباعة' : 'Print' ?>
            </button>

        </div>

    </div>


    <!-- ==============================================================
         ENROLLMENT INFORMATION
    ============================================================== -->

    <div class="card">

        <div class="card-header">

            <h2><?= $lang === 'ar' ? 'معلومات التسجيل' : 'Enrollment Information' ?></h2>

        </div>

        <div class="card-body">

            <div class="info-grid">

                <div class="info-item">

                    <div class="info-label">
                        <?= $lang === 'ar' ? 'رقم التسجيل' : 'Enrollment ID' ?>
                    </div>

                    <div class="info-value">
                        #<?= e($enrollment['id']) ?>
                    </div>

                </div>


                <div class="info-item">

                    <div class="info-label">
                        <?= $lang === 'ar' ? 'تاريخ التسجيل' : 'Enrollment Date' ?>
                    </div>

                    <div class="info-value">
                        <?= formatDate($enrollment['enrollment_date']) ?>
                    </div>

                </div>


                <div class="info-item">

                    <div class="info-label">
                        <?= $lang === 'ar' ? 'الحالة' : 'Status' ?>
                    </div>

                    <div class="info-value">

                        <span class="status-badge <?= e(statusClass($enrollment['enrollment_status'])) ?>">
                            <?= e($lang === 'ar' ? (match (strtolower((string)$enrollment['enrollment_status'])) { 'active' => 'نشط', 'completed' => 'مكتمل', 'suspended' => 'موقوف', 'cancelled' => 'ملغى', default => ucfirst($enrollment['enrollment_status']) }) : ucfirst($enrollment['enrollment_status'])) ?>
                        </span>

                    </div>

                </div>


                <div class="info-item">

                    <div class="info-label">
                        <?= $lang === 'ar' ? 'الرسوم الأصلية' : 'Original Fee' ?>
                    </div>

                    <div class="info-value">
                        <strong>
                            <?= formatMoney($enrollment['original_fee']) ?>
                        </strong>
                    </div>

                </div>


                <div class="info-item">

                    <div class="info-label">
                        <?= $lang === 'ar' ? 'الخصم' : 'Discount' ?>
                    </div>

                    <div class="info-value">
                        <?= formatMoney($enrollment['discount']) ?>
                    </div>

                </div>


                <div class="info-item">

                    <div class="info-label">
                        <?= $lang === 'ar' ? 'الرسوم الفعلية' : 'Actual Fee' ?>
                    </div>

                    <div class="info-value">

                        <strong>
                            <?= formatMoney($enrollment['actual_fee']) ?>
                        </strong>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- ==============================================================
         STUDENT INFORMATION
    ============================================================== -->

    <div class="card">

        <div class="card-header">

            <h2><?= $lang === 'ar' ? 'معلومات الطالب' : 'Student Information' ?></h2>

        </div>

        <div class="card-body">

            <div class="info-grid">

                <div class="info-item">

                    <div class="info-label">
                        <?= $lang === 'ar' ? 'رقم الطالب' : 'Student ID' ?>
                    </div>

                    <div class="info-value">

                        <strong>
                            <?= e($enrollment['student_code']) ?>
                        </strong>

                    </div>

                </div>


                <div class="info-item">

                    <div class="info-label">
                        <?= $lang === 'ar' ? 'الاسم الكامل' : 'Full Name' ?>
                    </div>

                    <div class="info-value">
                        <?= e($enrollment['student_name']) ?>
                    </div>

                </div>


                <div class="info-item">

                    <div class="info-label">
                        <?= $lang === 'ar' ? 'الجنس' : 'Gender' ?>
                    </div>

                    <div class="info-value">
                        <?= e($enrollment['gender']) ?>
                    </div>

                </div>


                <div class="info-item">

                    <div class="info-label">
                        <?= $lang === 'ar' ? 'تاريخ الميلاد' : 'Date of Birth' ?>
                    </div>

                    <div class="info-value">
                        <?= formatDate($enrollment['date_of_birth']) ?>
                    </div>

                </div>


                <div class="info-item">

                    <div class="info-label">
                        Phone
                    </div>

                    <div class="info-value">
                        <?= e($enrollment['student_phone'] ?: '-') ?>
                    </div>

                </div>


                <div class="info-item">

                    <div class="info-label">
                        Email
                    </div>

                    <div class="info-value">
                        <?= e($enrollment['student_email'] ?: '-') ?>
                    </div>

                </div>


                <div class="info-item">

                    <div class="info-label">
                        <?= $lang === 'ar' ? 'اسم ولي الأمر' : 'Guardian Name' ?>
                    </div>

                    <div class="info-value">
                        <?= e($enrollment['guardian_name'] ?: '-') ?>
                    </div>

                </div>


                <div class="info-item">

                    <div class="info-label">
                        <?= $lang === 'ar' ? 'هاتف ولي الأمر' : 'Guardian Phone' ?>
                    </div>

                    <div class="info-value">
                        <?= e($enrollment['guardian_phone'] ?: '-') ?>
                    </div>

                </div>


                <div class="info-item">

                    <div class="info-label">
                        <?= $lang === 'ar' ? 'حالة الطالب' : 'Student Status' ?>
                    </div>

                    <div class="info-value">

                        <span class="status-badge <?= e(statusClass($enrollment['student_status'])) ?>">
                            <?= e($lang === 'ar' ? (match (strtolower((string)$enrollment['student_status'])) { 'active' => 'نشط', 'completed' => 'مكتمل', 'suspended' => 'موقوف', 'cancelled' => 'ملغى', 'planned' => 'مخطط', 'inactive' => 'غير نشط', default => ucfirst($enrollment['student_status']) }) : ucfirst($enrollment['student_status'])) ?>
                        </span>

                    </div>

                </div>

            </div>


            <div style="margin-top:20px;">

                <div class="info-label">
                    <?= $lang === 'ar' ? 'العنوان' : 'Address' ?>
                </div>

                <div class="info-value">
                    <?= nl2br(e($enrollment['address'] ?: '-')) ?>
                </div>

            </div>

        </div>

    </div>


    <!-- ==============================================================
         COURSE INFORMATION
    ============================================================== -->

    <div class="card">

        <div class="card-header">

            <h2><?= $lang === 'ar' ? 'معلومات الدورة' : 'Course Information' ?></h2>

        </div>

        <div class="card-body">

            <div class="info-grid">

                <div class="info-item">

                    <div class="info-label">
                        <?= $lang === 'ar' ? 'رمز الدورة' : 'Course Code' ?>
                    </div>

                    <div class="info-value">
                        <strong>
                            <?= e($enrollment['course_code']) ?>
                        </strong>
                    </div>

                </div>


                <div class="info-item">

                    <div class="info-label">
                        <?= $lang === 'ar' ? 'اسم الدورة' : 'Course Name' ?>
                    </div>

                    <div class="info-value">
                        <?= e($enrollment['course_name']) ?>
                    </div>

                </div>


                <div class="info-item">

                    <div class="info-label">
                        <?= $lang === 'ar' ? 'الفئة' : 'Category' ?>
                    </div>

                    <div class="info-value">
                        <?= e($enrollment['category_name']) ?>
                    </div>

                </div>


                <div class="info-item">

                    <div class="info-label">
                        <?= $lang === 'ar' ? 'المستوى' : 'Level' ?>
                    </div>

                    <div class="info-value">
                        <?= e($enrollment['level_name']) ?>
                    </div>

                </div>


                <div class="info-item">

                    <div class="info-label">
                        <?= $lang === 'ar' ? 'المدة' : 'Duration' ?>
                    </div>

                    <div class="info-value">
                        <?= e($enrollment['duration'] ?: '-') ?>
                    </div>

                </div>


                <div class="info-item">

                    <div class="info-label">
                        <?= $lang === 'ar' ? 'الرسوم الافتراضية' : 'Default Fee' ?>
                    </div>

                    <div class="info-value">
                        <?= formatMoney($enrollment['default_fee']) ?>
                    </div>

                </div>

            </div>


            <?php if (!empty($enrollment['course_description'])): ?>

                <div style="margin-top:20px;">

                    <div class="info-label">
                        <?= $lang === 'ar' ? 'وصف الدورة' : 'Course Description' ?>
                    </div>

                    <div class="notes-box">
                        <?= e($enrollment['course_description']) ?>
                    </div>

                </div>

            <?php endif; ?>

        </div>

    </div>


    <!-- ==============================================================
         BATCH INFORMATION
    ============================================================== -->

    <div class="card">

        <div class="card-header">

            <h2><?= $lang === 'ar' ? 'معلومات المجموعة' : 'Batch Information' ?></h2>

        </div>

        <div class="card-body">

            <div class="info-grid">

                <div class="info-item">

                    <div class="info-label">
                        <?= $lang === 'ar' ? 'رمز المجموعة' : 'Batch Code' ?>
                    </div>

                    <div class="info-value">

                        <strong>
                            <?= e($enrollment['batch_code']) ?>
                        </strong>

                    </div>

                </div>


                <div class="info-item">

                    <div class="info-label">
                        <?= $lang === 'ar' ? 'تاريخ البدء' : 'Start Date' ?>
                    </div>

                    <div class="info-value">
                        <?= formatDate($enrollment['start_date']) ?>
                    </div>

                </div>


                <div class="info-item">

                    <div class="info-label">
                        <?= $lang === 'ar' ? 'تاريخ الانتهاء' : 'End Date' ?>
                    </div>

                    <div class="info-value">
                        <?= formatDate($enrollment['end_date']) ?>
                    </div>

                </div>


                <div class="info-item">

                    <div class="info-label">
                        <?= $lang === 'ar' ? 'القاعة' : 'Room' ?>
                    </div>

                    <div class="info-value">
                        <?= e($enrollment['room'] ?: '-') ?>
                    </div>

                </div>


                <div class="info-item">

                    <div class="info-label">
                        <?= $lang === 'ar' ? 'السعة' : 'Capacity' ?>
                    </div>

                    <div class="info-value">
                        <?= e($enrollment['capacity']) ?>
                    </div>

                </div>


                <div class="info-item">

                    <div class="info-label">
                        <?= $lang === 'ar' ? 'حالة المجموعة' : 'Batch Status' ?>
                    </div>

                    <div class="info-value">

                        <span class="status-badge <?= e(statusClass($enrollment['batch_status'])) ?>">
                            <?= e($lang === 'ar' ? (match (strtolower((string)$enrollment['batch_status'])) { 'active' => 'نشط', 'completed' => 'مكتمل', 'suspended' => 'موقوف', 'cancelled' => 'ملغى', 'planned' => 'مخطط', 'inactive' => 'غير نشط', default => ucfirst($enrollment['batch_status']) }) : ucfirst($enrollment['batch_status'])) ?>
                        </span>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- ==============================================================
         PAYMENT SUMMARY
    ============================================================== -->

    <div class="card">

        <div class="card-header">

            <h2><?= $lang === 'ar' ? 'ملخص المدفوعات' : 'Payment Summary' ?></h2>

        </div>

        <div class="card-body">

            <div class="payment-summary">

                <div class="summary-box">

                    <div class="summary-label">
                        <?= $lang === 'ar' ? 'الرسوم الفعلية' : 'Actual Fee' ?>
                    </div>

                    <div class="summary-value">
                        <?= formatMoney($actualFee) ?>
                    </div>

                </div>


                <div class="summary-box">

                    <div class="summary-label">
                        <?= $lang === 'ar' ? 'إجمالي المدفوع' : 'Total Paid' ?>
                    </div>

                    <div class="summary-value">
                        <?= formatMoney($totalPaid) ?>
                    </div>

                </div>


                <div class="summary-box">

                    <div class="summary-label">
                        <?= $lang === 'ar' ? 'الرصيد المتبقي' : 'Remaining Balance' ?>
                    </div>

                    <div class="summary-value">
                        <?= formatMoney($remainingBalance) ?>
                    </div>

                </div>


                <div class="summary-box">

                    <div class="summary-label">
                        <?= $lang === 'ar' ? 'حالة الدفع' : 'Payment Status' ?>
                    </div>

                    <div style="margin-top:8px;">

                        <?php if ($paymentStatus === 'Paid'): ?>

                            <span class="status-badge status-active">
                                <?= $lang === 'ar' ? 'مدفوع' : 'Paid' ?>
                            </span>

                        <?php elseif ($paymentStatus === 'Partially Paid'): ?>

                            <span class="status-badge status-suspended">
                                <?= $lang === 'ar' ? 'مدفوع جزئياً' : 'Partially Paid' ?>
                            </span>

                        <?php else: ?>

                            <span class="status-badge status-cancelled">
                                <?= $lang === 'ar' ? 'غير مدفوع' : 'Unpaid' ?>
                            </span>

                        <?php endif; ?>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- ==============================================================
         PAYMENT HISTORY
    ============================================================== -->

    <div class="card">

        <div class="card-header">

            <h2><?= $lang === 'ar' ? 'سجل المدفوعات' : 'Payment History' ?></h2>

        </div>

        <div class="card-body" style="padding:0;">

            <?php if (!empty($payments)): ?>

                <div class="table-wrapper">

                    <table>

                        <thead>

                            <tr>

                                <th><?= $lang === 'ar' ? 'رقم الإيصال' : 'Receipt No.' ?></th>

                                <th><?= $lang === 'ar' ? 'تاريخ الدفع' : 'Payment Date' ?></th>

                                <th>Amount</th>

                                <th><?= $lang === 'ar' ? 'طريقة الدفع' : 'Method' ?></th>

                                <th><?= $lang === 'ar' ? 'سجّل بواسطة' : 'Recorded By' ?></th>

                                <th><?= $lang === 'ar' ? 'ملاحظات' : 'Notes' ?></th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php foreach ($payments as $payment): ?>

                            <tr>

                                <td>
                                    <?= e($payment['receipt_number']) ?>
                                </td>

                                <td>
                                    <?= formatDate($payment['payment_date']) ?>
                                </td>

                                <td>

                                    <strong>
                                        <?= formatMoney($payment['amount']) ?>
                                    </strong>

                                </td>

                                <td>
                                    <?= e($payment['payment_method']) ?>
                                </td>

                                <td>
                                    <?= e($payment['recorded_by_username'] ?: '-') ?>
                                </td>

                                <td>
                                    <?= e($payment['notes'] ?: '-') ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="empty-state">

                    <?= $lang === 'ar' ? 'لم يتم تسجيل أي مدفوعات لهذا التسجيل.' : 'No payments have been recorded for this enrollment.' ?>

                </div>

            <?php endif; ?>

        </div>

    </div>


    <!-- ==============================================================
         ENROLLMENT NOTES
    ============================================================== -->

    <?php if (!empty($enrollment['notes'])): ?>

        <div class="card">

            <div class="card-header">

                <h2>Enrollment <?= $lang === 'ar' ? 'ملاحظات' : 'Notes' ?></h2>

            </div>

            <div class="card-body">

                <div class="notes-box">
                    <?= e($enrollment['notes']) ?>
                </div>

            </div>

        </div>

    <?php endif; ?>

</div>

</body>

</html>