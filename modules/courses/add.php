<?php
session_start();

require_once '../../config/database.php';
require_once '../../includes/language.php';

// =========================
// AUTHORIZATION
// =========================
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

if (!in_array($_SESSION['role'], ['admin', 'staff'], true)) {
    die(($lang === 'ar') ? 'غير مسموح بالوصول.' : 'Access denied.');
}

$error = '';

// =========================
// GET ACTIVE CATEGORIES
// =========================
$categoryStmt = $pdo->query("
    SELECT id, name
    FROM categories
    WHERE status = 'active'
    ORDER BY name ASC
");

$categories = $categoryStmt->fetchAll();

// =========================
// GET ACTIVE LEVELS
// =========================
$levelStmt = $pdo->query("
    SELECT id, name
    FROM levels
    WHERE status = 'active'
    ORDER BY name ASC
");

$levels = $levelStmt->fetchAll();

// =========================
// ADD COURSE
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $courseCode  = trim($_POST['course_code'] ?? '');
    $courseName  = trim($_POST['course_name'] ?? '');
    $categoryId  = (int) ($_POST['category_id'] ?? 0);
    $levelId     = (int) ($_POST['level_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $defaultFee  = trim($_POST['default_fee'] ?? '');
    $duration    = trim($_POST['duration'] ?? '');
    $status      = $_POST['status'] ?? 'active';

    // =========================
    // VALIDATION
    // =========================

    if ($courseCode === '') {

        $error = ($lang === 'ar') ? 'رمز الدورة مطلوب.' : 'Course code is required.';

    } elseif ($courseName === '') {

        $error = ($lang === 'ar') ? 'اسم الدورة مطلوب.' : 'Course name is required.';

    } elseif ($categoryId <= 0) {

        $error = ($lang === 'ar') ? 'يرجى اختيار الفئة.' : 'Please select a category.';

    } elseif ($levelId <= 0) {

        $error = ($lang === 'ar') ? 'يرجى اختيار المستوى.' : 'Please select a level.';

    } elseif ($defaultFee === '' || !is_numeric($defaultFee)) {

        $error = ($lang === 'ar') ? 'يرجى إدخال رسوم افتراضية صحيحة.' : 'Please enter a valid default fee.';

    } elseif ((float) $defaultFee < 0) {

        $error = ($lang === 'ar') ? 'لا يمكن أن تكون الرسوم الافتراضية سالبة.' : 'Default fee cannot be negative.';

    } elseif (!in_array($status, ['active', 'inactive'], true)) {

        $error = ($lang === 'ar') ? 'الحالة غير صالحة.' : 'Invalid status.';

    } else {

        // =========================
        // CHECK COURSE CODE
        // =========================

        $check = $pdo->prepare("
            SELECT id
            FROM courses
            WHERE course_code = ?
            LIMIT 1
        ");

        $check->execute([$courseCode]);

        if ($check->fetch()) {

            $error = ($lang === 'ar') ? 'رمز الدورة هذا موجود بالفعل.' : 'This course code already exists.';

        } else {

            // =========================
            // VERIFY CATEGORY
            // =========================

            $checkCategory = $pdo->prepare("
                SELECT id
                FROM categories
                WHERE id = ?
                  AND status = 'active'
                LIMIT 1
            ");

            $checkCategory->execute([$categoryId]);

            if (!$checkCategory->fetch()) {

                $error = ($lang === 'ar') ? 'الفئة المحددة غير صالحة أو غير نشطة.' : 'Selected category is invalid or inactive.';

            } else {

                // =========================
                // VERIFY LEVEL
                // =========================

                $checkLevel = $pdo->prepare("
                    SELECT id
                    FROM levels
                    WHERE id = ?
                      AND status = 'active'
                    LIMIT 1
                ");

                $checkLevel->execute([$levelId]);

                if (!$checkLevel->fetch()) {

                    $error = ($lang === 'ar') ? 'المستوى المحدد غير صالح أو غير نشط.' : 'Selected level is invalid or inactive.';

                } else {

                    // =========================
                    // INSERT COURSE
                    // =========================

                    $stmt = $pdo->prepare("
                        INSERT INTO courses (
                            course_code,
                            course_name,
                            category_id,
                            level_id,
                            description,
                            default_fee,
                            duration,
                            status
                        )
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                    $stmt->execute([
                        $courseCode,
                        $courseName,
                        $categoryId,
                        $levelId,
                        $description !== '' ? $description : null,
                        (float) $defaultFee,
                        $duration !== '' ? $duration : null,
                        $status
                    ]);

                    header('Location: index.php?success=1');
                    exit;
                }
            }
        }
    }
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
    background: #ffffff;
    border-radius: 10px;
    padding: 20px;
    max-width: 900px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

.form-group label {
    font-size: 12px;
    font-weight: 600;
    color: #374151;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    box-sizing: border-box;
    padding: 9px 10px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 13px;
    font-family: inherit;
    background: #fff;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #2563eb;
}

.form-group textarea {
    min-height: 100px;
    resize: vertical;
}

.form-help {
    font-size: 11px;
    color: #6b7280;
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
    max-width: 900px;
    padding: 10px 13px;
    border-radius: 6px;
    margin-bottom: 15px;
    font-size: 13px;
    background: #fee2e2;
    color: #991b1b;
}

.empty-message {
    padding: 12px;
    background: #fff7ed;
    color: #9a3412;
    border-radius: 6px;
    font-size: 12px;
    margin-bottom: 15px;
}

@media (max-width: 700px) {

    .form-grid {
        grid-template-columns: 1fr;
    }

    .form-group.full-width {
        grid-column: auto;
    }

}

</style>


<div class="page-header">

    <h1><?= $lang === 'ar' ? 'إضافة دورة' : 'Add Course' ?></h1>

    <p>
        <?= $lang === 'ar' ? 'إنشاء دورة جديدة' : 'Create a new course' ?>
    </p>

    <div class="top-actions">

        <a
            href="index.php"
            class="btn btn-back"
        >
            <?= $lang === 'ar' ? '← العودة إلى الدورات' : '← Back to Courses' ?>
        </a>

    </div>

</div>


<?php if ($error): ?>

    <div class="alert">
        <?= htmlspecialchars($error) ?>
    </div>

<?php endif; ?>


<?php if (empty($categories) || empty($levels)): ?>

    <div class="empty-message">

        <?= $lang === 'ar' ? 'قبل إنشاء دورة، تحتاج إلى' : 'Before creating a course, you need at least one' ?>
        <strong><?= $lang === 'ar' ? 'فئة نشطة' : 'active category' ?></strong> and one
        <strong><?= $lang === 'ar' ? 'ومستوى نشط' : 'active level' ?></strong>.

        <br><br>

        <a href="categories.php">
            <?= $lang === 'ar' ? 'إدارة الفئات' : 'Manage Categories' ?>
        </a>

        &nbsp; | &nbsp;

        <a href="levels.php">
            <?= $lang === 'ar' ? 'إدارة المستويات' : 'Manage Levels' ?>
        </a>

    </div>

<?php endif; ?>


<div class="card">

    <form method="POST">

        <div class="form-grid">

            <!-- Course Code -->

            <div class="form-group">

                <label>
                    <?= $lang === 'ar' ? 'رمز الدورة *' : 'Course Code *' ?>
                </label>

                <input
                    type="text"
                    name="course_code"
                    maxlength="50"
                    required
                    value="<?= htmlspecialchars($_POST['course_code'] ?? '') ?>"
                    placeholder="<?= $lang === 'ar' ? 'مثال: ENG-BEG-01' : 'e.g. ENG-BEG-01' ?>"
                >

                <span class="form-help">
                    <?= $lang === 'ar' ? 'يجب أن يكون فريدًا.' : 'Must be unique.' ?>
                </span>

            </div>


            <!-- Course Name -->

            <div class="form-group">

                <label>
                    <?= $lang === 'ar' ? 'اسم الدورة *' : 'Course Name *' ?>
                </label>

                <input
                    type="text"
                    name="course_name"
                    maxlength="150"
                    required
                    value="<?= htmlspecialchars($_POST['course_name'] ?? '') ?>"
                    placeholder="<?= $lang === 'ar' ? 'مثال: English Beginner' : 'e.g. English Beginner' ?>"
                >

            </div>


            <!-- Category -->

            <div class="form-group">

                <label>
                    <?= $lang === 'ar' ? 'الفئة *' : 'Category *' ?>
                </label>

                <select name="category_id" required>

                    <option value="">
                        <?= $lang === 'ar' ? '-- اختر الفئة --' : '-- Select Category --' ?>
                    </option>

                    <?php foreach ($categories as $category): ?>

                        <option
                            value="<?= (int) $category['id'] ?>"
                            <?= ((int) ($_POST['category_id'] ?? 0) === (int) $category['id']) ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($category['name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- Level -->

            <div class="form-group">

                <label>
                    <?= $lang === 'ar' ? 'المستوى *' : 'Level *' ?>
                </label>

                <select name="level_id" required>

                    <option value="">
                        <?= $lang === 'ar' ? '-- اختر المستوى --' : '-- Select Level --' ?>
                    </option>

                    <?php foreach ($levels as $level): ?>

                        <option
                            value="<?= (int) $level['id'] ?>"
                            <?= ((int) ($_POST['level_id'] ?? 0) === (int) $level['id']) ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($level['name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- Default Fee -->

            <div class="form-group">

                <label>
                    <?= $lang === 'ar' ? 'الرسوم الافتراضية *' : 'Default Fee *' ?>
                </label>

                <input
                    type="number"
                    name="default_fee"
                    min="0"
                    step="0.01"
                    required
                    value="<?= htmlspecialchars($_POST['default_fee'] ?? '0.00') ?>"
                    placeholder="0.00"
                >

                <span class="form-help">
                    <?= $lang === 'ar' ? 'أدخل رسوم الدورة القياسية.' : 'Enter the standard course fee.' ?>
                </span>

            </div>


            <!-- <?= $lang === 'ar' ? 'المدة' : 'Duration' ?> -->

            <div class="form-group">

                <label>
                    <?= $lang === 'ar' ? 'المدة' : 'Duration' ?>
                </label>

                <input
                    type="text"
                    name="duration"
                    maxlength="100"
                    value="<?= htmlspecialchars($_POST['duration'] ?? '') ?>"
                    placeholder="<?= $lang === 'ar' ? 'مثال: 3 أشهر' : 'e.g. 3 Months' ?>"
                >

            </div>


            <!-- Status -->

            <div class="form-group">

                <label>
                    <?= $lang === 'ar' ? 'الحالة *' : 'Status *' ?>
                </label>

                <select name="status" required>

                    <option
                        value="active"
                        <?= ($_POST['status'] ?? 'active') === 'active'
                            ? 'selected'
                            : '' ?>
                    >
                        <?= $lang === 'ar' ? 'نشط' : 'Active' ?>
                    </option>

                    <option
                        value="inactive"
                        <?= ($_POST['status'] ?? '') === 'inactive'
                            ? 'selected'
                            : '' ?>
                    >
                        <?= $lang === 'ar' ? 'غير نشط' : 'Inactive' ?>
                    </option>

                </select>

            </div>


            <!-- <?= $lang === 'ar' ? 'الوصف' : 'Description' ?> -->

            <div class="form-group full-width">

                <label>
                    <?= $lang === 'ar' ? 'الوصف' : 'Description' ?>
                </label>

                <textarea
                    name="description"
                    placeholder="<?= $lang === 'ar' ? 'وصف الدورة...' : 'Course description...' ?>"
                ><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>

            </div>

        </div>


        <!-- Actions -->

        <div class="form-actions">

            <button
                type="submit"
                class="btn btn-primary"
                <?= (empty($categories) || empty($levels)) ? 'disabled' : '' ?>
            >
                <?= $lang === 'ar' ? 'حفظ الدورة' : 'Save Course' ?>
            </button>

            <a
                href="index.php"
                class="btn btn-secondary"
            >
                <?= $lang === 'ar' ? 'إلغاء' : 'Cancel' ?>
            </a>

        </div>

    </form>

</div>

</div>