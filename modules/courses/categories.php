<?php
session_start();

require_once '../../config/database.php';
require_once '../../includes/language.php';

// Only Admin and Staff
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

if (!in_array($_SESSION['role'], ['admin', 'staff'])) {
    die(($lang === 'ar') ? 'غير مسموح بالوصول.' : 'Access denied.');
}

$message = '';
$error = '';

// =========================
// ADD CATEGORY
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {

    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($name === '') {
        $error = ($lang === 'ar') ? 'اسم الفئة مطلوب.' : 'Category name is required.';
    } else {

        // Check duplicate
        $check = $pdo->prepare("
            SELECT id
            FROM categories
            WHERE name = ?
            LIMIT 1
        ");

        $check->execute([$name]);

        if ($check->fetch()) {
            $error = ($lang === 'ar') ? 'هذه الفئة موجودة بالفعل.' : 'This category already exists.';
        } else {

            $stmt = $pdo->prepare("
                INSERT INTO categories (name, description, status)
                VALUES (?, ?, 'active')
            ");

            $stmt->execute([
                $name,
                $description !== '' ? $description : null
            ]);

            $message = ($lang === 'ar') ? 'تمت إضافة الفئة بنجاح.' : 'Category added successfully.';
        }
    }
}

// =========================
// ACTIVATE / DEACTIVATE
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {

    $categoryId = (int) ($_POST['category_id'] ?? 0);

    if ($categoryId > 0) {

        $stmt = $pdo->prepare("
            SELECT status
            FROM categories
            WHERE id = ?
        ");

        $stmt->execute([$categoryId]);

        $category = $stmt->fetch();

        if ($category) {

            $newStatus = $category['status'] === 'active'
                ? 'inactive'
                : 'active';

            $update = $pdo->prepare("
                UPDATE categories
                SET status = ?
                WHERE id = ?
            ");

            $update->execute([
                $newStatus,
                $categoryId
            ]);

            $message = ($lang === 'ar') ? 'تم تحديث حالة الفئة بنجاح.' : 'Category status updated successfully.';
        }
    }
}

// =========================
// SEARCH
// =========================
$search = trim($_GET['search'] ?? '');

if ($search !== '') {

    $stmt = $pdo->prepare("
        SELECT *
        FROM categories
        WHERE name LIKE ?
           OR description LIKE ?
        ORDER BY id DESC
    ");

    $keyword = '%' . $search . '%';

    $stmt->execute([
        $keyword,
        $keyword
    ]);

} else {

    $stmt = $pdo->query("
        SELECT *
        FROM categories
        ORDER BY id DESC
    ");
}

$categories = $stmt->fetchAll();

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

.top-actions {
    display: flex;
    gap: 8px;
}

.card {
    background: #ffffff;
    border-radius: 10px;
    padding: 18px;
    margin-bottom: 18px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.card h2 {
    margin: 0 0 15px;
    font-size: 17px;
    color: #111827;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 2fr auto;
    gap: 12px;
    align-items: end;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.form-group label {
    font-size: 12px;
    font-weight: 600;
    color: #374151;
}

.form-group input,
.form-group textarea {
    width: 100%;
    box-sizing: border-box;
    padding: 9px 10px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 13px;
    font-family: inherit;
}

.form-group textarea {
    resize: vertical;
    min-height: 38px;
}

.btn {
    border: none;
    border-radius: 6px;
    padding: 9px 13px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
}

.btn-primary {
    background: #2563eb;
    color: white;
}

.btn-primary:hover {
    background: #1d4ed8;
}

.btn-back {
    background: #6b7280;
    color: white;
}

.btn-back:hover {
    background: #4b5563;
}

.btn-edit {
    background: #f59e0b;
    color: white;
}

.btn-edit:hover {
    background: #d97706;
}

.btn-danger {
    background: #dc2626;
    color: white;
}

.btn-success {
    background: #16a34a;
    color: white;
}

.alert {
    padding: 10px 13px;
    border-radius: 6px;
    margin-bottom: 15px;
    font-size: 13px;
}

.alert-success {
    background: #dcfce7;
    color: #166534;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
}

.search-bar {
    display: flex;
    gap: 8px;
    margin-bottom: 15px;
}

.search-bar input {
    flex: 1;
    height: 36px;
    padding: 0 10px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 13px;
}

.btn-search {
    background: #374151;
    color: white;
}

.btn-reset {
    background: #e5e7eb;
    color: #111827;
    text-decoration: none;
    display: flex;
    align-items: center;
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
    color: #374151;
    font-weight: 700;
    white-space: nowrap;
}

td {
    padding: 10px 8px;
    border-top: 1px solid #e5e7eb;
    color: #374151;
    vertical-align: middle;
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

.actions {
    display: flex;
    gap: 6px;
    flex-wrap: nowrap;
}

.actions form {
    margin: 0;
}

.empty-state {
    text-align: center;
    padding: 25px;
    color: #6b7280;
}

@media (max-width: 800px) {

    .form-grid {
        grid-template-columns: 1fr;
    }

    .page-header {
        align-items: flex-start;
        gap: 10px;
        flex-direction: column;
    }

    .top-actions {
        width: 100%;
    }

}

</style>


<div class="page-header">

    <div>
        <h1><?= $lang === 'ar' ? 'الفئات' : 'Categories' ?></h1>
        <p><?= $lang === 'ar' ? 'إدارة فئات الدورات' : 'Manage course categories' ?></p>
    </div>

    <div class="top-actions">

        <!-- Back to Courses -->
        <a href="index.php" class="btn btn-back">
            ← Back to Courses
        </a>

    </div>

</div>


<?php if ($message): ?>

    <div class="alert alert-success">
        <?= htmlspecialchars($message) ?>
    </div>

<?php endif; ?>


<?php if ($error): ?>

    <div class="alert alert-error">
        <?= htmlspecialchars($error) ?>
    </div>

<?php endif; ?>


<!-- =========================
     ADD CATEGORY
========================= -->

<div class="card">

    <h2><?= $lang === 'ar' ? 'إضافة فئة' : 'Add Category' ?></h2>

    <form method="POST">

        <div class="form-grid">

            <div class="form-group">

                <label><?= $lang === 'ar' ? 'اسم الفئة' : 'Category Name' ?></label>

                <input
                    type="text"
                    name="name"
                    maxlength="100"
                    required
                    placeholder="<?= $lang === 'ar' ? 'مثال: English' : 'e.g. English' ?>"
                >

            </div>


            <div class="form-group">

                <label><?= $lang === 'ar' ? 'الوصف' : 'Description' ?></label>

                <textarea
                    name="description"
                    placeholder="<?= $lang === 'ar' ? 'وصف الفئة...' : 'Category description...' ?>"
                ></textarea>

            </div>


            <div>

                <button
                    type="submit"
                    name="add_category"
                    class="btn btn-primary"
                >
                    + Add Category
                </button>

            </div>

        </div>

    </form>

</div>


<!-- =========================
     CATEGORY LIST
========================= -->

<div class="card">

    <h2><?= $lang === 'ar' ? 'قائمة الفئات' : 'Category List' ?></h2>

    <form method="GET" class="search-bar">

        <input
            type="text"
            name="search"
            value="<?= htmlspecialchars($search) ?>"
            placeholder="<?= $lang === 'ar' ? 'البحث عن فئة...' : 'Search category...' ?>"
        >

        <button
            type="submit"
            class="btn btn-search"
        >
            Search
        </button>

        <?php if ($search !== ''): ?>

            <a
                href="categories.php"
                class="btn btn-reset"
            >
                Reset
            </a>

        <?php endif; ?>

    </form>


    <div class="table-wrapper">

        <table>

            <thead>

                <tr>
                    <th>#</th>
                    <th><?= $lang === 'ar' ? 'الاسم' : 'Name' ?></th>
                    <th><?= $lang === 'ar' ? 'الوصف' : 'Description' ?></th>
                    <th><?= $lang === 'ar' ? 'الحالة' : 'Status' ?></th>
                    <th><?= $lang === 'ar' ? 'تاريخ الإنشاء' : 'Created' ?></th>
                    <th><?= $lang === 'ar' ? 'الإجراءات' : 'Actions' ?></th>
                </tr>

            </thead>

            <tbody>

                <?php if (empty($categories)): ?>

                    <tr>
                        <td colspan="6" class="empty-state">
                            No categories found.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($categories as $category): ?>

                        <tr>

                            <td>
                                <?= (int) $category['id'] ?>
                            </td>

                            <td>
                                <strong>
                                    <?= htmlspecialchars($category['name']) ?>
                                </strong>
                            </td>

                            <td>
                                <?= htmlspecialchars($category['description'] ?? '-') ?>
                            </td>

                            <td>

                                <?php if ($category['status'] === 'active'): ?>

                                    <span class="badge badge-active">
                                        Active
                                    </span>

                                <?php else: ?>

                                    <span class="badge badge-inactive">
                                        Inactive
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>
                                <?= htmlspecialchars($category['created_at']) ?>
                            </td>

                            <td>

                                <div class="actions">

                                    <a
                                        href="edit-category.php?id=<?= (int) $category['id'] ?>"
                                        class="btn btn-edit"
                                    >
                                        Edit
                                    </a>


                                    <form method="POST">

                                        <input
                                            type="hidden"
                                            name="category_id"
                                            value="<?= (int) $category['id'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            name="toggle_status"
                                            class="btn <?= $category['status'] === 'active' ? 'btn-danger' : 'btn-success' ?>"
                                        >
                                            <?= $category['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                                        </button>

                                    </form>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

</div>