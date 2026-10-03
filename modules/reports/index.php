<?php
session_start();

require_once '../../config/database.php';
require_once '../../includes/language.php';

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ../../login.php');
    exit;
}

$role = strtolower((string) $_SESSION['role']);

if (!in_array($role, ['admin', 'staff', 'instructor'], true)) {
    http_response_code(403);
    die($lang === 'ar' ? 'الوصول مرفوض.' : 'Access denied.');
}

$reports = [
    [
        'key' => 'students',
        'title' => $lang === 'ar' ? 'تقرير الطلاب' : 'Student Report',
        'description' => $lang === 'ar'
            ? 'عرض وطباعة سجلات الطلاب مع معلومات الحالة والتسجيل.'
            : 'View and print student records with status and registration information.',
        'roles' => ['admin', 'staff', 'instructor'],
    ],
    [
        'key' => 'enrollments',
        'title' => $lang === 'ar' ? 'تقرير التسجيلات' : 'Enrollment Report',
        'description' => $lang === 'ar'
            ? 'عرض التسجيلات حسب المقرر والدفعة والطالب وحالة التسجيل.'
            : 'View enrollments by course, batch, student, and enrollment status.',
        'roles' => ['admin', 'staff', 'instructor'],
    ],
    [
        'key' => 'payments',
        'title' => $lang === 'ar' ? 'تقرير المدفوعات' : 'Payment Report',
        'description' => $lang === 'ar'
            ? 'عرض المدفوعات والمبالغ المدفوعة والأرصدة المستحقة.'
            : 'View payments, paid amounts, and outstanding balances.',
        'roles' => ['admin', 'staff'],
    ],
    [
        'key' => 'attendance',
        'title' => $lang === 'ar' ? 'تقرير الحضور' : 'Attendance Report',
        'description' => $lang === 'ar'
            ? 'عرض سجلات الحضور ونسب الحضور حسب الدفعة.'
            : 'View attendance records and attendance percentages by batch.',
        'roles' => ['admin', 'staff', 'instructor'],
    ],
    [
        'key' => 'grades',
        'title' => $lang === 'ar' ? 'تقرير الدرجات' : 'Grade Report',
        'description' => $lang === 'ar'
            ? 'عرض نتائج التقييم النهائي باستخدام أوزان الدرجات 20% / 35% / 45%.'
            : 'View final assessment results using the 20% / 35% / 45% grading weights.',
        'roles' => ['admin', 'staff', 'instructor'],
    ],
];
?>

<!DOCTYPE html>
<html lang="<?= $lang === 'ar' ? 'ar' : 'en' ?>" dir="<?= $dir ?>">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= $lang === 'ar'
            ? 'التقارير - مركز العلومة للتدريب'
            : 'Reports - ALAWALAMA TRAINING CENTER'
        ?>
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f6f9;
            color: #1f2937;
        }

        .main-page {
            padding: 30px;
            max-width: 1250px;
            margin: 0 auto;
        }

        .page-header {
            margin-bottom: 25px;
        }

        .page-header h1 {
            margin: 0 0 8px;
            font-size: 28px;
        }

        .page-header p {
            margin: 0;
            color: #64748b;
        }

        .reports-grid {
            display: grid;
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        .report-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 24px;

            box-shadow:
                0 2px 8px rgba(15, 23, 42, 0.04);

            display: flex;
            flex-direction: column;
        }

        .report-card h2 {
            margin: 0 0 10px;
            font-size: 20px;
        }

        .report-card p {
            margin: 0 0 22px;
            color: #64748b;
            line-height: 1.55;
            flex: 1;
        }

        .btn {
            display: inline-block;
            text-decoration: none;
            border-radius: 8px;
            padding: 11px 16px;

            font-weight: 600;
            text-align: center;

            background: #2563eb;
            color: #ffffff;
        }

        .btn:hover {
            background: #1d4ed8;
        }

        .note {
            margin-top: 22px;

            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;

            padding: 16px;

            color: #64748b;
            font-size: 14px;
        }

        @media (max-width: 700px) {

            .main-page {
                padding: 20px;
            }

            .reports-grid {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>

<body>

<?php require_once '../../includes/sidebar.php'; ?>


<div class="main-page">

    <!-- =========================
         PAGE HEADER
    ========================== -->

    <div class="page-header">

        <h1>
            <?= $lang === 'ar' ? 'التقارير' : 'Reports' ?>
        </h1>

        <p>
            <?= $lang === 'ar'
                ? 'إنشاء وتصفية وطباعة التقارير الإدارية والأكاديمية.'
                : 'Generate, filter, and print operational and academic reports.'
            ?>
        </p>

    </div>


    <!-- =========================
         REPORT CARDS
    ========================== -->

    <div class="reports-grid">

        <?php foreach ($reports as $report): ?>

            <?php
            if (!in_array(
                $role,
                $report['roles'],
                true
            )) {
                continue;
            }
            ?>

            <div class="report-card">

                <h2>
                    <?= htmlspecialchars(
                        $report['title']
                    ) ?>
                </h2>

                <p>
                    <?= htmlspecialchars(
                        $report['description']
                    ) ?>
                </p>

                <a
                    class="btn"
                    href="report.php?type=<?= urlencode(
                        $report['key']
                    ) ?>"
                >
                    <?= $lang === 'ar'
                        ? 'فتح التقرير'
                        : 'Open Report'
                    ?>
                </a>

            </div>

        <?php endforeach; ?>

    </div>


    <!-- =========================
         INSTRUCTOR NOTE
    ========================== -->

    <?php if ($role === 'instructor'): ?>

        <div class="note">

            <?= $lang === 'ar'
                ? 'تقارير المدفوعات المالية مقتصرة على المسؤول والموظفين.'
                : 'Financial payment reports are restricted to Admin and Staff.'
            ?>

        </div>

    <?php endif; ?>


</div>

</body>

</html>