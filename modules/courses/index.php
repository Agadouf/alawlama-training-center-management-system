<?php
session_start();

require_once '../../config/database.php';
require_once '../../includes/language.php';

// Admin and Staff only
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

if (!in_array($_SESSION['role'], ['admin', 'staff'])) {
    die(($lang === 'ar') ? 'غير مسموح بالوصول.' : 'Access denied.');
}

// Get courses
$stmt = $pdo->query("
    SELECT
        c.id,
        c.course_code,
        c.course_name,
        c.description,
        c.default_fee,
        c.duration,
        c.status,
        cat.name AS category_name,
        l.name AS level_name
    FROM courses c
    INNER JOIN categories cat
        ON c.category_id = cat.id
    INNER JOIN levels l
        ON c.level_id = l.id
    ORDER BY c.id DESC
");

$courses = $stmt->fetchAll();

?>

<?php require_once '../../includes/sidebar.php'; ?>

<style>

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.page-header h1 {
    margin: 0;
    font-size: 22px;
    color: #111827;
}

.page-header p {
    margin: 4px 0 0;
    font-size: 13px;
    color: #6b7280;
}

.card {
    background: #fff;
    border-radius: 10px;
    padding: 18px;
    box-shadow: 0 2px 8px rgba(0,0,0,.05);
}

.top-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.btn {
    display: inline-block;
    border: none;
    border-radius: 6px;
    padding: 9px 13px;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
}

.btn-primary {
    background: #2563eb;
    color: white;
}

.btn-secondary {
    background: #374151;
    color: white;
}

.btn-back {
    background: #6b7280;
    color: white;
}

.btn-primary:hover {
    background: #1d4ed8;
}

.btn-secondary:hover {
    background: #1f2937;
}

.btn-back:hover {
    background: #4b5563;
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}

thead {
    background: #f3f4f6;
}

th {
    text-align: left;
    padding: 10px 8px;
    white-space: nowrap;
    color: #374151;
}

td {
    padding: 10px 8px;
    border-top: 1px solid #e5e7eb;
    color: #374151;
}

.badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 600;
}

.badge-active {
    background: #dcfce7;
    color: #166534;
}

.badge-inactive {
    background: #fee2e2;
    color: #991b1b;
}

.empty-state {
    text-align: center;
    padding: 25px;
    color: #6b7280;
}

@media (max-width: 900px) {

    .page-header {
        align-items: flex-start;
        gap: 15px;
    }

    .top-actions {
        justify-content: flex-start;
    }

}

@media (max-width: 600px) {

    .page-header {
        flex-direction: column;
    }

    .top-actions {
        width: 100%;
    }

    .btn {
        padding: 8px 10px;
        font-size: 11px;
    }

}

</style>


<div class="page-header">

    <div>
        <h1>
            <?= $lang === 'ar' ? 'الدورات' : 'Courses' ?>
        </h1>

        <p>
            <?= $lang === 'ar'
                ? 'إدارة الدورات والفئات والمستويات'
                : 'Manage courses, categories and levels' ?>
        </p>
    </div>

    <div class="top-actions">

        <!-- Back to Dashboard -->
        <a href="../dashboard/index.php" class="btn btn-back">
            ← <?= $lang === 'ar' ? 'رجوع' : 'Back' ?>
        </a>

        <!-- Categories -->
        <a href="categories.php" class="btn btn-secondary">
            <?= $lang === 'ar' ? 'الفئات' : 'Categories' ?>
        </a>

        <!-- Levels -->
        <a href="levels.php" class="btn btn-secondary">
            <?= $lang === 'ar' ? 'المستويات' : 'Levels' ?>
        </a>

        <!-- Add Course -->
        <a href="add.php" class="btn btn-primary">
            + <?= $lang === 'ar' ? 'إضافة دورة' : 'Add Course' ?>
        </a>

    </div>

</div>


<div class="card">

    <div class="table-wrapper">

        <table>

            <thead>

                <tr>
                    <th>#</th>

                    <th>
                        <?= $lang === 'ar' ? 'رمز الدورة' : 'Course Code' ?>
                    </th>

                    <th>
                        <?= $lang === 'ar' ? 'اسم الدورة' : 'Course Name' ?>
                    </th>

                    <th>
                        <?= $lang === 'ar' ? 'الفئة' : 'Category' ?>
                    </th>

                    <th>
                        <?= $lang === 'ar' ? 'المستوى' : 'Level' ?>
                    </th>

                    <th>
                        <?= $lang === 'ar' ? 'الرسوم' : 'Fee' ?>
                    </th>

                    <th>
                        <?= $lang === 'ar' ? 'المدة' : 'Duration' ?>
                    </th>

                    <th>
                        <?= $lang === 'ar' ? 'الحالة' : 'Status' ?>
                    </th>

                    <th>
                        <?= $lang === 'ar' ? 'الإجراءات' : 'Actions' ?>
                    </th>
                </tr>

            </thead>

            <tbody>

                <?php if (empty($courses)): ?>

                    <tr>
                        <td colspan="9" class="empty-state">
                            <?= $lang === 'ar'
                                ? 'لم يتم العثور على دورات.'
                                : 'No courses found.' ?>
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($courses as $course): ?>

                        <tr>

                            <td>
                                <?= (int) $course['id'] ?>
                            </td>

                            <td>
                                <strong>
                                    <?= htmlspecialchars($course['course_code']) ?>
                                </strong>
                            </td>

                            <td>
                                <?= htmlspecialchars($course['course_name']) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($course['category_name']) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($course['level_name']) ?>
                            </td>

                            <td>
                                <?= number_format(
                                    (float) $course['default_fee'],
                                    2
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($course['duration'] ?? '-') ?>
                            </td>

                            <td>

                                <?php if ($course['status'] === 'active'): ?>

                                    <span class="badge badge-active">
                                        <?= $lang === 'ar' ? 'نشط' : 'Active' ?>
                                    </span>

                                <?php else: ?>

                                    <span class="badge badge-inactive">
                                        <?= $lang === 'ar' ? 'غير نشط' : 'Inactive' ?>
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <a
                                    href="edit.php?id=<?= (int) $course['id'] ?>"
                                    class="btn btn-secondary"
                                >
                                    <?= $lang === 'ar' ? 'تعديل' : 'Edit' ?>
                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

</div>