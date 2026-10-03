<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

session_start();

require_once '../../config/database.php';
require_once '../../includes/language.php';

// ============================================================
// AUTHORIZATION
// ============================================================

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

if (!in_array($_SESSION['role'], ['admin', 'staff'], true)) {
    die($lang === 'ar' ? 'غير مسموح بالوصول.' : 'Access denied.');
}

// ============================================================
// SEARCH
// ============================================================

$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';

// ============================================================
// FETCH COMPLETED ENROLLMENTS + CERTIFICATE STATUS
// ============================================================

$sql = "
    SELECT
        e.id AS enrollment_id,
        e.enrollment_date,
        e.status AS enrollment_status,

        s.id AS student_id,
        s.student_code,
        s.full_name,

        b.id AS batch_id,
        b.batch_code,
        b.start_date,
        b.end_date,

        c.id AS course_id,
        c.course_code,
        c.course_name,

        cert.id AS certificate_id,
        cert.certificate_number,
        cert.issue_date,
        cert.completion_date

    FROM enrollments e

    INNER JOIN students s
        ON s.id = e.student_id

    INNER JOIN batches b
        ON b.id = e.batch_id

    INNER JOIN courses c
        ON c.id = b.course_id

    LEFT JOIN certificates cert
        ON cert.enrollment_id = e.id

";

$params = [];

if ($search !== '') {
    $sql .= "
        AND (
            s.full_name LIKE ?
            OR s.student_code LIKE ?
            OR c.course_name LIKE ?
            OR c.course_code LIKE ?
            OR b.batch_code LIKE ?
            OR cert.certificate_number LIKE ?
        )
    ";

    $searchValue = '%' . $search . '%';

    $params = [
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue
    ];
}

if ($statusFilter === 'issued') {
    $sql .= " AND cert.id IS NOT NULL ";
} elseif ($statusFilter === 'pending') {
    $sql .= " AND cert.id IS NULL ";
}

$sql .= "
    ORDER BY
        e.id DESC
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('<div style="font-family:Arial;padding:30px;color:#991b1b;background:#fee2e2;">' .
        '<h2>Certificate page database error</h2>' .
        '<p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p></div>');
}

?>

<?php require_once '../../includes/sidebar.php'; ?>

<style>
    .certificates-page {
        padding: 25px 30px 40px;
    }

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 20px;
        margin-bottom: 22px;
    }

    .page-header h1 {
        margin: 0 0 6px;
        font-size: 28px;
        color: #111827;
    }

    .page-header p {
        margin: 0;
        color: #6b7280;
        font-size: 14px;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 16px;
        margin-bottom: 22px;
    }

    .stat-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 18px 20px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    }

    .stat-label {
        color: #6b7280;
        font-size: 13px;
        margin-bottom: 7px;
    }

    .stat-value {
        color: #111827;
        font-size: 26px;
        font-weight: 700;
    }

    .filters-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 18px;
        margin-bottom: 20px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    }

    .filter-form {
        display: grid;
        grid-template-columns: minmax(220px, 1fr) 180px auto auto;
        gap: 12px;
        align-items: end;
    }

    .form-group label {
        display: block;
        margin-bottom: 6px;
        font-size: 13px;
        font-weight: 600;
        color: #374151;
    }

    .form-control {
        width: 100%;
        box-sizing: border-box;
        height: 42px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        padding: 0 12px;
        font-size: 14px;
        background: #fff;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 42px;
        padding: 0 16px;
        border: 0;
        border-radius: 8px;
        text-decoration: none;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        box-sizing: border-box;
    }

    .btn-primary {
        background: #2563eb;
        color: #fff;
    }

    .btn-secondary {
        background: #f3f4f6;
        color: #374151;
    }

    .btn-success {
        background: #059669;
        color: #fff;
    }

    .btn-info {
        background: #0f766e;
        color: #fff;
    }

    .table-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    }

    .table-wrapper {
        overflow-x: auto;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        min-width: 1050px;
    }

    th,
    td {
        padding: 13px 14px;
        border-bottom: 1px solid #eef0f2;
        text-align: left;
        vertical-align: middle;
        font-size: 13px;
    }

    th {
        background: #f9fafb;
        color: #374151;
        font-weight: 700;
        white-space: nowrap;
    }

    td {
        color: #4b5563;
    }

    tr:last-child td {
        border-bottom: 0;
    }

    .student-name {
        color: #111827;
        font-weight: 600;
    }

    .muted {
        color: #6b7280;
        font-size: 12px;
        margin-top: 3px;
    }

    .badge {
        display: inline-flex;
        align-items: center;
        padding: 5px 9px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
        white-space: nowrap;
    }

    .badge-issued {
        background: #dcfce7;
        color: #166534;
    }

    .badge-pending {
        background: #fef3c7;
        color: #92400e;
    }

    .actions {
        display: flex;
        flex-wrap: wrap;
        gap: 7px;
    }

    .empty-state {
        padding: 45px 20px;
        text-align: center;
        color: #6b7280;
    }

    @media (max-width: 900px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }

        .filter-form {
            grid-template-columns: 1fr;
        }
    }

    html[dir="rtl"] .page-header,
    html[dir="rtl"] .certificates-page,
    html[dir="rtl"] .filters-card {
        direction: rtl;
    }

    html[dir="rtl"] th,
    html[dir="rtl"] td {
        text-align: right;
    }
</style>

<div class="certificates-page">

    <div class="page-header">
        <div>
            <h1><?= $lang === 'ar' ? 'إدارة الشهادات' : 'Certificate Management' ?></h1>
            <p><?= $lang === 'ar' ? 'إصدار وإدارة شهادات إتمام الطلاب.' : 'Generate and manage student completion certificates.' ?></p>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">
                <?= $lang === 'ar' ? 'إجمالي التسجيلات' : 'Total Enrollments' ?>
            </div>
            <div class="stat-value"><?= count($enrollments) ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-label">
                <?= $lang === 'ar' ? 'الشهادات الصادرة' : 'Certificates Issued' ?>
            </div>
            <div class="stat-value">
                <?= count(array_filter($enrollments, static fn($row) => !empty($row['certificate_id']))) ?>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-label">
                <?= $lang === 'ar' ? 'بانتظار الإصدار' : 'Pending Certificates' ?>
            </div>
            <div class="stat-value">
                <?= count(array_filter($enrollments, static fn($row) => empty($row['certificate_id']))) ?>
            </div>
        </div>
    </div>

    <div class="filters-card">
        <form method="get" class="filter-form">

            <div class="form-group">
                <label for="search">
                    <?= $lang === 'ar' ? 'بحث' : 'Search' ?>
                </label>
                <input
                    type="text"
                    id="search"
                    name="search"
                    class="form-control"
                    value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
                    placeholder="<?= $lang === 'ar' ? 'اسم الطالب، الرقم، الدورة...' : 'Student name, code, course...' ?>"
                >
            </div>

            <div class="form-group">
                <label for="status">
                    <?= $lang === 'ar' ? 'حالة الشهادة' : 'Certificate Status' ?>
                </label>
                <select id="status" name="status" class="form-control">
                    <option value="">
                        <?= $lang === 'ar' ? 'الكل' : 'All' ?>
                    </option>
                    <option value="issued" <?= $statusFilter === 'issued' ? 'selected' : '' ?>>
                        <?= $lang === 'ar' ? 'صادرة' : 'Issued' ?>
                    </option>
                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>
                        <?= $lang === 'ar' ? 'بانتظار الإصدار' : 'Pending' ?>
                    </option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">
                <?= $lang === 'ar' ? 'بحث' : 'Search' ?>
            </button>

            <a href="index.php" class="btn btn-secondary">
                <?= $lang === 'ar' ? 'إعادة تعيين' : 'Reset' ?>
            </a>

        </form>
    </div>

    <div class="table-card">
        <div class="table-wrapper">
            <?php if (empty($enrollments)): ?>

                <div class="empty-state">
                    <?= $lang === 'ar'
                        ? 'لا توجد تسجيلات مطابقة للبحث.'
                        : 'No enrollments found.' ?>
                </div>

            <?php else: ?>

                <table>
                    <thead>
                        <tr>
                            <th><?= $lang === 'ar' ? 'الطالب' : 'Student' ?></th>
                            <th><?= $lang === 'ar' ? 'الدورة' : 'Course' ?></th>
                            <th><?= $lang === 'ar' ? 'الدفعة' : 'Batch' ?></th>
                            <th><?= $lang === 'ar' ? 'حالة التسجيل' : 'Enrollment Status' ?></th>
                            <th><?= $lang === 'ar' ? 'تاريخ الإكمال' : 'Completion Date' ?></th>
                            <th><?= $lang === 'ar' ? 'حالة الشهادة' : 'Certificate Status' ?></th>
                            <th><?= $lang === 'ar' ? 'رقم الشهادة' : 'Certificate No.' ?></th>
                            <th><?= $lang === 'ar' ? 'الإجراءات' : 'Actions' ?></th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($enrollments as $row): ?>
                            <tr>
                                <td>
                                    <div class="student-name">
                                        <?= htmlspecialchars($row['full_name'], ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                    <div class="muted">
                                        <?= htmlspecialchars($row['student_code'], ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['course_name'], ENT_QUOTES, 'UTF-8') ?>
                                    <div class="muted">
                                        <?= htmlspecialchars($row['course_code'], ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['batch_code'], ENT_QUOTES, 'UTF-8') ?>
                                </td>

                                <td>
                                    <?php
                                    $enrollmentStatus = strtolower((string) ($row['enrollment_status'] ?? ''));
                                    $statusLabels = [
                                        'active' => $lang === 'ar' ? 'نشط' : 'Active',
                                        'completed' => $lang === 'ar' ? 'مكتمل' : 'Completed',
                                        'suspended' => $lang === 'ar' ? 'موقوف' : 'Suspended',
                                        'cancelled' => $lang === 'ar' ? 'ملغى' : 'Cancelled'
                                    ];
                                    ?>
                                    <span class="badge <?= $enrollmentStatus === 'completed' ? 'badge-issued' : 'badge-pending' ?>">
                                        <?= htmlspecialchars($statusLabels[$enrollmentStatus] ?? ucfirst($enrollmentStatus), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>

                                <td>
                                    <?php if ($enrollmentStatus === 'completed'): ?>
                                        <?= !empty($row['completion_date'])
                                            ? htmlspecialchars($row['completion_date'], ENT_QUOTES, 'UTF-8')
                                            : htmlspecialchars($row['end_date'], ENT_QUOTES, 'UTF-8') ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if (!empty($row['certificate_id'])): ?>
                                        <span class="badge badge-issued">
                                            <?= $lang === 'ar' ? 'صادرة' : 'Issued' ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-pending">
                                            <?= $lang === 'ar' ? 'بانتظار الإصدار' : 'Pending' ?>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?= !empty($row['certificate_number'])
                                        ? htmlspecialchars($row['certificate_number'], ENT_QUOTES, 'UTF-8')
                                        : '—' ?>
                                </td>

                                <td>
                                    <div class="actions">
                                        <?php if (!empty($row['certificate_id'])): ?>
                                            <a
                                                href="view.php?id=<?= (int) $row['certificate_id'] ?>"
                                                class="btn btn-info"
                                            >
                                                <?= $lang === 'ar' ? 'عرض' : 'View' ?>
                                            </a>

                                            <a
                                                href="print.php?id=<?= (int) $row['certificate_id'] ?>"
                                                class="btn btn-secondary"
                                                target="_blank"
                                            >
                                                <?= $lang === 'ar' ? 'طباعة' : 'Print' ?>
                                            </a>
                                        <?php elseif ($enrollmentStatus === 'completed'): ?>
                                            <a
                                                href="create.php?enrollment_id=<?= (int) $row['enrollment_id'] ?>"
                                                class="btn btn-success"
                                            >
                                                <?= $lang === 'ar' ? 'إصدار الشهادة' : 'Generate Certificate' ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="badge badge-pending">
                                                <?= $lang === 'ar' ? 'غير مؤهل بعد' : 'Not Eligible Yet' ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

            <?php endif; ?>
        </div>
    </div>

</div>
