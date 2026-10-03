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

$categoryId = (int) ($_GET['id'] ?? 0);

if ($categoryId <= 0) {
    header('Location: categories.php');
    exit;
}

// =========================
// GET CATEGORY
// =========================
$stmt = $pdo->prepare("
    SELECT *
    FROM categories
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$categoryId]);

$category = $stmt->fetch();

if (!$category) {
    die(($lang === 'ar') ? 'الفئة غير موجودة.' : 'Category not found.');
}

$error = '';

// =========================
// UPDATE CATEGORY
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? '';

    $allowedStatuses = ['active', 'inactive'];

    if ($name === '') {

        $error = ($lang === 'ar') ? 'اسم الفئة مطلوب.' : 'Category name is required.';

    } elseif (!in_array($status, $allowedStatuses, true)) {

        $error = ($lang === 'ar') ? 'الحالة غير صالحة.' : 'Invalid status.';

    } else {

        // Check duplicate name
        $check = $pdo->prepare("
            SELECT id
            FROM categories
            WHERE name = ?
              AND id != ?
            LIMIT 1
        ");

        $check->execute([
            $name,
            $categoryId
        ]);

        if ($check->fetch()) {

            $error = ($lang === 'ar')
                ? 'توجد فئة أخرى تستخدم هذا الاسم بالفعل.'
                : 'Another category already uses this name.';

        } else {

            $update = $pdo->prepare("
                UPDATE categories
                SET
                    name = ?,
                    description = ?,
                    status = ?
                WHERE id = ?
            ");

            $update->execute([
                $name,
                $description !== '' ? $description : null,
                $status,
                $categoryId
            ]);

            header('Location: categories.php?updated=1');
            exit;
        }
    }

    // Keep submitted values in the form
    $category['name'] = $name;
    $category['description'] = $description;
    $category['status'] = $status;
}

?>

<?php require_once '../../includes/sidebar.php'; ?>

<style>

.page-header {
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
    margin-top: 12px;
}

.card {
    background: #fff;
    border-radius: 10px;
    padding: 20px;
    max-width: 800px;
    box-shadow: 0 2px 8px rgba(0,0,0,.05);
}

.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: block;
    margin-bottom: 6px;
    font-size: 12px;
    font-weight: 600;
    color: #374151;
}

.form-group input,
.form-group textarea,
.form-group select {
    width: 100%;
    box-sizing: border-box;
    padding: 9px 10px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 13px;
    font-family: inherit;
}

.form-group textarea {
    min-height: 100px;
    resize: vertical;
}

.form-actions {
    display: flex;
    gap: 8px;
    margin-top: 20px;
}

.btn {
    border: none;
    border-radius: 6px;
    padding: 9px 14px;
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

.btn-secondary {
    background: #e5e7eb;
    color: #111827;
}

.btn-secondary:hover {
    background: #d1d5db;
}

.btn-back {
    background: #6b7280;
    color: white;
}

.btn-back:hover {
    background: #4b5563;
}

.alert {
    max-width: 800px;
    padding: 10px 13px;
    border-radius: 6px;
    margin-bottom: 15px;
    font-size: 13px;
    background: #fee2e2;
    color: #991b1b;
}

.readonly-field {
    background: #f3f4f6;
    color: #6b7280;
}

</style>


<div class="page-header">

    <h1><?= $lang === 'ar' ? 'تعديل الفئة' : 'Edit Category' ?></h1>

    <p>
        <?= $lang === 'ar' ? 'تحديث معلومات الفئة' : 'Update category information' ?>
    </p>

    <div class="top-actions">

        <a
            href="categories.php"
            class="btn btn-back"
        >
            ← <?= $lang === 'ar' ? 'العودة إلى الفئات' : 'Back to Categories' ?>
        </a>

    </div>

</div>


<?php if ($error): ?>

    <div class="alert">
        <?= htmlspecialchars($error) ?>
    </div>

<?php endif; ?>


<div class="card">

    <form method="POST">

        <!-- Category ID -->

        <div class="form-group">

            <label><?= $lang === 'ar' ? 'معرّف الفئة' : 'Category ID' ?></label>

            <input
                type="text"
                value="<?= (int) $category['id'] ?>"
                class="readonly-field"
                readonly
            >

        </div>


        <!-- Name -->

        <div class="form-group">

            <label><?= $lang === 'ar' ? 'اسم الفئة' : 'Category Name' ?></label>

            <input
                type="text"
                name="name"
                maxlength="100"
                value="<?= htmlspecialchars($category['name']) ?>"
                required
            >

        </div>


        <!-- Description -->

        <div class="form-group">

            <label><?= $lang === 'ar' ? 'الوصف' : 'Description' ?></label>

            <textarea
                name="description"
            ><?= htmlspecialchars($category['description'] ?? '') ?></textarea>

        </div>


        <!-- Status -->

        <div class="form-group">

            <label><?= $lang === 'ar' ? 'الحالة' : 'Status' ?></label>

            <select name="status" required>

                <option
                    value="active"
                    <?= $category['status'] === 'active' ? 'selected' : '' ?>
                >
                    <?= $lang === 'ar' ? 'نشطة' : 'Active' ?>
                </option>

                <option
                    value="inactive"
                    <?= $category['status'] === 'inactive' ? 'selected' : '' ?>
                >
                    <?= $lang === 'ar' ? 'غير نشطة' : 'Inactive' ?>
                </option>

            </select>

        </div>


        <!-- Actions -->

        <div class="form-actions">

            <button
                type="submit"
                class="btn btn-primary"
            >
                <?= $lang === 'ar' ? 'حفظ التغييرات' : 'Save Changes' ?>
            </button>

            <a
                href="categories.php"
                class="btn btn-secondary"
            >
                <?= $lang === 'ar' ? 'إلغاء' : 'Cancel' ?>
            </a>

        </div>

    </form>

</div>

</div>
