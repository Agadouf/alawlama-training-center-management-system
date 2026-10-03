<?php

session_start();

require_once '../../config/database.php';
require_once '../../includes/language.php';

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ../../login.php');
    exit;
}

$allowedRoles = ['admin', 'staff', 'instructor'];

if (!in_array($_SESSION['role'], $allowedRoles, true)) {
    http_response_code(403);
    die('Access denied.');
}

$role = $_SESSION['role'];
$userId = (int) $_SESSION['user_id'];

$totalStudents = 0;
$activeStudents = 0;
$totalInstructors = 0;
$totalCourses = 0;
$activeBatches = 0;
$totalEnrollments = 0;
$totalPayments = 0;
$totalFees = 0;
$paidAmount = 0;
$outstandingBalance = 0;
$totalAssessments = 0;
$totalAttendance = 0;

$recentEnrollments = [];
$recentPayments = [];

if ($role === 'admin' || $role === 'staff') {

    $totalStudents = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();

    $activeStudents = $pdo->query("
        SELECT COUNT(*) FROM students WHERE status = 'active'
    ")->fetchColumn();

    $totalInstructors = $pdo->query("
        SELECT COUNT(*) FROM instructors
    ")->fetchColumn();

    $totalCourses = $pdo->query("
        SELECT COUNT(*) FROM courses
    ")->fetchColumn();

    $activeBatches = $pdo->query("
        SELECT COUNT(*) FROM batches WHERE status = 'active'
    ")->fetchColumn();

    $totalEnrollments = $pdo->query("
        SELECT COUNT(*) FROM enrollments
    ")->fetchColumn();

    $totalPayments = $pdo->query("
        SELECT COALESCE(SUM(amount), 0) FROM payments
    ")->fetchColumn();

    $totalFees = $pdo->query("
        SELECT COALESCE(SUM(actual_fee), 0) FROM enrollments
    ")->fetchColumn();

    $paidAmount = $totalPayments;

    $outstandingBalance = max(
        0,
        (float) $totalFees - (float) $paidAmount
    );

    $recentEnrollments = $pdo->query("
        SELECT
            e.id,
            s.student_code,
            s.full_name,
            c.course_name,
            b.batch_code,
            e.enrollment_date
        FROM enrollments e
        INNER JOIN students s ON e.student_id = s.id
        INNER JOIN batches b ON e.batch_id = b.id
        INNER JOIN courses c ON b.course_id = c.id
        ORDER BY e.created_at DESC
        LIMIT 5
    ")->fetchAll();

    $recentPayments = $pdo->query("
        SELECT
            p.receipt_number,
            p.amount,
            p.payment_date,
            s.full_name
        FROM payments p
        INNER JOIN enrollments e ON p.enrollment_id = e.id
        INNER JOIN students s ON e.student_id = s.id
        ORDER BY p.created_at DESC
        LIMIT 5
    ")->fetchAll();

} else {

    $stmt = $pdo->prepare("
        SELECT id
        FROM instructors
        WHERE user_id = ?
        LIMIT 1
    ");

    $stmt->execute([$userId]);
    $instructor = $stmt->fetch();

    if (!$instructor) {
        http_response_code(403);
        die('Instructor profile not found.');
    }

    $instructorId = (int) $instructor['id'];

    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT e.student_id)
        FROM enrollments e
        INNER JOIN batch_instructors bi ON e.batch_id = bi.batch_id
        WHERE bi.instructor_id = ?
          AND e.status <> 'cancelled'
    ");

    $stmt->execute([$instructorId]);
    $totalStudents = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT e.student_id)
        FROM enrollments e
        INNER JOIN batch_instructors bi ON e.batch_id = bi.batch_id
        INNER JOIN students s ON e.student_id = s.id
        WHERE bi.instructor_id = ?
          AND e.status <> 'cancelled'
          AND s.status = 'active'
    ");

    $stmt->execute([$instructorId]);
    $activeStudents = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT bi.batch_id)
        FROM batch_instructors bi
        INNER JOIN batches b ON bi.batch_id = b.id
        WHERE bi.instructor_id = ?
          AND b.status IN ('planned', 'active')
    ");

    $stmt->execute([$instructorId]);
    $activeBatches = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT b.course_id)
        FROM batch_instructors bi
        INNER JOIN batches b ON bi.batch_id = b.id
        WHERE bi.instructor_id = ?
    ");

    $stmt->execute([$instructorId]);
    $totalCourses = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM assessments a
        INNER JOIN batch_instructors bi ON a.batch_id = bi.batch_id
        WHERE bi.instructor_id = ?
    ");

    $stmt->execute([$instructorId]);
    $totalAssessments = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM attendance
        WHERE recorded_by = ?
    ");

    $stmt->execute([$userId]);
    $totalAttendance = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT
            e.id,
            s.student_code,
            s.full_name,
            c.course_name,
            b.batch_code,
            e.enrollment_date
        FROM enrollments e
        INNER JOIN students s ON e.student_id = s.id
        INNER JOIN batches b ON e.batch_id = b.id
        INNER JOIN courses c ON b.course_id = c.id
        INNER JOIN batch_instructors bi ON b.id = bi.batch_id
        WHERE bi.instructor_id = ?
        ORDER BY e.created_at DESC
        LIMIT 5
    ");

    $stmt->execute([$instructorId]);
    $recentEnrollments = $stmt->fetchAll();
}

// Instructor intentionally has no payment/financial data loaded.

?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>" dir="<?= htmlspecialchars($dir) ?>">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= htmlspecialchars(__('dashboard')) ?> - ALAW LAMA Training Center
    </title>


    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }


        body {
            font-family: Arial, sans-serif;

            background: #f4f6f8;

            color: #1f2937;
        }


        /* =========================
           TOP HEADER
        ========================== */

        .header {
            background: #1f2937;

            color: white;

            padding: 18px 30px;

            display: flex;

            justify-content: space-between;

            align-items: center;
        }


        .header h1 {
            font-size: 22px;
        }


        .header-right {
            display: flex;

            align-items: center;

            gap: 20px;
        }


        .user-role {
            color: #d1d5db;

            font-size: 14px;
        }


        .logout {
            background: #dc2626;

            color: white;

            text-decoration: none;

            padding: 8px 14px;

            border-radius: 6px;

            font-size: 14px;
        }


        .logout:hover {
            background: #b91c1c;
        }


        /* =========================
           MAIN CONTENT
        ========================== */

        .container {
            padding: 30px;
        }


        .welcome {
            margin-bottom: 25px;
        }


        .welcome h2 {
            margin-bottom: 5px;

            font-size: 26px;
        }


        .welcome p {
            color: #6b7280;
        }


        /* =========================
           STATISTICS CARDS
        ========================== */

        .cards {
            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 20px;

            margin-bottom: 30px;
        }


        .card {
            background: white;

            padding: 22px;

            border-radius: 10px;

            box-shadow:
                0 2px 8px
                rgba(0, 0, 0, 0.05);

            border-left:
                4px solid #2563eb;
        }


        .card-title {
            color: #6b7280;

            font-size: 14px;

            margin-bottom: 10px;
        }


        .card-value {
            font-size: 28px;

            font-weight: bold;

            color: #111827;
        }


        .money {
            font-size: 22px;
        }


        /* =========================
           SECTIONS
        ========================== */

        .section {
            background: white;

            padding: 25px;

            border-radius: 10px;

            margin-bottom: 25px;

            box-shadow:
                0 2px 8px
                rgba(0, 0, 0, 0.05);
        }


        .section h3 {
            margin-bottom: 20px;

            font-size: 18px;
        }


        /* =========================
           TABLES
        ========================== */

        .table-wrapper {
            overflow-x: auto;
        }


        table {
            width: 100%;

            border-collapse: collapse;
        }


        th,
        td {
            padding: 12px;

            border-bottom:
                1px solid #e5e7eb;

            text-align: left;
        }


        th {
            background: #f9fafb;

            font-size: 14px;
        }


        td {
            font-size: 14px;
        }


        .empty {
            color: #6b7280;

            padding: 15px 0;
        }


        /* =========================
           RESPONSIVE
        ========================== */

        @media (max-width: 1100px) {

            .cards {
                grid-template-columns:
                    repeat(2, 1fr);
            }

        }


        @media (max-width: 768px) {

            .header {
                padding: 15px;
            }


            .container {
                padding: 20px;
            }

        }


        @media (max-width: 600px) {

            .cards {
                grid-template-columns: 1fr;
            }


            .header h1 {
                font-size: 17px;
            }


            .header-right {
                gap: 8px;
            }

        }

    </style>

</head>


<body>


<?php

// =============================
// Sidebar
// =============================

require_once '../../includes/sidebar.php';

?>


<!-- =========================
     HEADER
========================== -->

<header class="header">

    <h1>
        <?= htmlspecialchars(__('app_name')) ?>
    </h1>


    <div class="header-right">

        <span class="user-role">

            <?= htmlspecialchars($_SESSION['username']) ?>

            |

            <?= htmlspecialchars(
                ucfirst($_SESSION['role'])
            ) ?>

        </span>


        <a
            href="../../logout.php"
            class="logout"
        >
            <?= htmlspecialchars(__('logout')) ?>
        </a>

    </div>

</header>


<!-- =========================
     MAIN CONTENT
========================= -->

<main class="container">


    <!-- =========================
         WELCOME
    ========================== -->

    <div class="welcome">

        <h2>
            <?= htmlspecialchars(__('dashboard')) ?>
        </h2>


        <p>
            <?= $lang === 'ar'
                ? 'مرحبًا بك في نظام إدارة مركز العلوامة للتدريب.'
                : 'Welcome to the ALAW LAMA Training Center Management System.'
            ?>
        </p>

    </div>


    <!-- =========================
         STATISTICS
    ========================== -->

    <div class="cards">

        <div class="card">

            <div class="card-title">
                <?= htmlspecialchars(__('total_students')) ?>
            </div>

            <div class="card-value">
                <?= (int) $totalStudents ?>
            </div>

        </div>


        <div class="card">

            <div class="card-title">
                <?= $lang === 'ar'
                    ? 'الطلاب النشطون'
                    : 'Active Students'
                ?>
            </div>

            <div class="card-value">
                <?= (int) $activeStudents ?>
            </div>

        </div>


        <?php if ($role === 'admin' || $role === 'staff'): ?>

            <div class="card">

                <div class="card-title">
                    <?= htmlspecialchars(__('instructors')) ?>
                </div>

                <div class="card-value">
                    <?= (int) $totalInstructors ?>
                </div>

            </div>


            <div class="card">

                <div class="card-title">
                    <?= htmlspecialchars(__('courses')) ?>
                </div>

                <div class="card-value">
                    <?= (int) $totalCourses ?>
                </div>

            </div>


            <div class="card">

                <div class="card-title">
                    <?= htmlspecialchars(__('active_batches')) ?>
                </div>

                <div class="card-value">
                    <?= (int) $activeBatches ?>
                </div>

            </div>


            <div class="card">

                <div class="card-title">
                    <?= htmlspecialchars(__('total_enrollments')) ?>
                </div>

                <div class="card-value">
                    <?= (int) $totalEnrollments ?>
                </div>

            </div>


            <div class="card">

                <div class="card-title">
                    <?= htmlspecialchars(__('total_payments')) ?>
                </div>

                <div class="card-value money">
                    <?= number_format((float) $totalPayments, 0) ?>
                </div>

            </div>


            <div class="card">

                <div class="card-title">
                    <?= htmlspecialchars(__('outstanding_balances')) ?>
                </div>

                <div class="card-value money">
                    <?= number_format((float) $outstandingBalance, 0) ?>
                </div>

            </div>


        <?php else: ?>


            <div class="card">

                <div class="card-title">
                    <?= $lang === 'ar'
                        ? 'الفصول النشطة لدي'
                        : 'My Active Batches'
                    ?>
                </div>

                <div class="card-value">
                    <?= (int) $activeBatches ?>
                </div>

            </div>


            <div class="card">

                <div class="card-title">
                    <?= $lang === 'ar'
                        ? 'الدورات لدي'
                        : 'My Courses'
                    ?>
                </div>

                <div class="card-value">
                    <?= (int) $totalCourses ?>
                </div>

            </div>


            <div class="card">

                <div class="card-title">
                    <?= $lang === 'ar'
                        ? 'التقييمات لدي'
                        : 'My Assessments'
                    ?>
                </div>

                <div class="card-value">
                    <?= (int) $totalAssessments ?>
                </div>

            </div>


            <div class="card">

                <div class="card-title">
                    <?= $lang === 'ar'
                        ? 'سجلات الحضور'
                        : 'Attendance Records'
                    ?>
                </div>

                <div class="card-value">
                    <?= (int) $totalAttendance ?>
                </div>

            </div>


        <?php endif; ?>

    </div>


    <!-- =========================
         RECENT ENROLLMENTS
    ========================== -->

    <div class="section">

        <h3>
            <?= htmlspecialchars(__('recent_enrollments')) ?>
        </h3>


        <?php if (count($recentEnrollments) > 0): ?>

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                <?= htmlspecialchars(__('student_id')) ?>
                            </th>

                            <th>
                                <?= htmlspecialchars(__('full_name')) ?>
                            </th>

                            <th>
                                <?= htmlspecialchars(__('course')) ?>
                            </th>

                            <th>
                                <?= htmlspecialchars(__('batch')) ?>
                            </th>

                            <th>
                                <?= htmlspecialchars(__('date')) ?>
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php foreach (
                        $recentEnrollments
                        as $enrollment
                    ): ?>

                        <tr>

                            <td>

                                <?= htmlspecialchars(
                                    $enrollment['student_code']
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $enrollment['full_name']
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $enrollment['course_name']
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $enrollment['batch_code']
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $enrollment['enrollment_date']
                                ) ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <p class="empty">
                <?= $lang === 'ar'
                    ? 'لا توجد تسجيلات حتى الآن.'
                    : 'No enrollments yet.'
                ?>
            </p>

        <?php endif; ?>

    </div>


</main>


</body>

</html>