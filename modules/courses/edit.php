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

$courseId = (int) ($_GET['id'] ?? 0);

if ($courseId <= 0) {
    header('Location: index.php');
    exit;
}

// =========================
// GET COURSE
// =========================
$stmt = $pdo->prepare("
    SELECT *
    FROM courses
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$courseId]);

$course = $stmt->fetch();

if (!$course) {
    die(($lang === 'ar') ? 'الدورة غير موجودة.' : 'Course not found.');
}

// =========================
// GET ACTIVE CATEGORIES
// =========================
$categoryStmt = $pdo->query("
    SELECT id, name
    FROM categories
    WHERE status = 'active'
       OR id = (
           SELECT category_id
           FROM courses
           WHERE id = {$courseId}
       )
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
       OR id = (
           SELECT level_id
           FROM courses
           WHERE id = {$courseId}
       )
    ORDER BY name ASC
");

$levels = $levelStmt->fetchAll();

$error = '';

// =========================
// UPDATE COURSE
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $courseCode  = trim($_POST['course_code'] ?? '');
    $courseName  = trim($_POST['course_name'] ?? '');
    $categoryId  = (int) ($_POST['category_id'] ?? 0);
    $levelId     = (int) ($_POST['level_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $defaultFee  = trim($_POST['default_fee'] ?? '');
    $duration    = trim($_POST['duration'] ?? '');
    $status      = $_POST['status'] ?? '';

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

        $error = ($lang === 'ar') ? 'يرجى إدخال رسوم افتراضية صالحة.' : 'Please enter a valid default fee.';

    } elseif ((float) $defaultFee < 0) {

        $error = ($lang === 'ar') ? 'لا يمكن أن تكون الرسوم الافتراضية سالبة.' : 'Default fee cannot be negative.';

    } elseif (!in_array($status, ['active', 'inactive'], true)) {

        $error = ($lang === 'ar') ? 'الحالة غير صالحة.' : 'Invalid status.';

    } else {

        // =========================
        // CHECK DUPLICATE CODE
        // =========================

        $check = $pdo->prepare("
            SELECT id
            FROM courses
            WHERE course_code = ?
              AND id != ?
            LIMIT 1
        ");

        $check->execute([
            $courseCode,
            $courseId
        ]);

        if ($check->fetch()) {

            $error = ($lang === 'ar') ? 'توجد دورة أخرى تستخدم رمز الدورة هذا بالفعل.' : 'Another course already uses this course code.';

        } else {

            // =========================
            // VERIFY CATEGORY
            // =========================

            $checkCategory = $pdo->prepare("
                SELECT id
                FROM categories
                WHERE id = ?
                LIMIT 1
            ");

            $checkCategory->execute([$categoryId]);

            if (!$checkCategory->fetch()) {

                $error = ($lang === 'ar') ? 'الفئة المحددة غير موجودة.' : 'Selected category does not exist.';

            } else {

                // =========================
                // VERIFY LEVEL
                // =========================

                $checkLevel = $pdo->prepare("
                    SELECT id
                    FROM levels
                    WHERE id = ?
                    LIMIT 1
                ");

                $checkLevel->execute([$levelId]);

                if (!$checkLevel->fetch()) {

                    $error = ($lang === 'ar') ? 'المستوى المحدد غير موجود.' : 'Selected level does not exist.';

                } else {

                    // =========================
                    // UPDATE
                    // =========================

                    $update = $pdo->prepare("
                        UPDATE courses
                        SET
                            course_code = ?,
                            course_name = ?,
                            category_id = ?,
                            level_id = ?,
                            description = ?,
                            default_fee = ?,
                            duration = ?,
                            status = ?
                        WHERE id = ?
                    ");

                    $update->execute([
                        $courseCode,
                        $courseName,
                        $categoryId,
                        $levelId,
                        $description !== '' ? $description : null,
                        (float) $defaultFee,
                        $duration !== '' ? $duration : null,
                        $status,
                        $courseId
                    ]);

                    header('Location: index.php?updated=1');
                    exit;
                }
            }
        }
    }

    // Keep submitted values if validation fails
    $course['course_code'] = $courseCode;
    $course['course_name'] = $courseName;
    $course['category_id'] = $categoryId;
    $course['level_id'] = $levelId;
    $course['description'] = $description;
    $course['default_fee'] = $defaultFee;
    $course['duration'] = $duration;
    $course['status'] = $status;
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

.readonly-field {
    background: #f3f4f6 !important;
    color: #6b7280;
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

    <h1><?= $lang === 'ar' ? 'تعديل الدورة' : 'Edit Course' ?></h1>

    <p>
        <?= $lang === 'ar' ? 'تحديث معلومات الدورة' : 'Update course information' ?>
    </p>

    <div class="top-actions">

        <a
            href="index.php"
            class="btn btn-back"
        >
            ← <?= $lang === 'ar' ? 'العودة إلى الدورات' : 'Back to Courses' ?>
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

        <div class="form-grid">

            <!-- Course ID -->

            <div class="form-group">

                <label><?= $lang === 'ar' ? 'معرّف الدورة' : 'Course ID' ?></label>

                <input
                    type="text"
                    value="<?= (int) $course['id'] ?>"
                    class="readonly-field"
                    readonly
                >

            </div>


            <!-- Course Code -->

            <div class="form-group">

                <label><?= $lang === 'ar' ? 'رمز الدورة *' : 'Course Code *' ?></label>

                <input
                    type="text"
                    name="course_code"
                    maxlength="50"
                    required
                    value="<?= htmlspecialchars($course['course_code']) ?>"
                >

                <span class="form-help">
                    <?= $lang === 'ar' ? 'يجب أن يكون رمز الدورة فريدًا.' : 'Course code must be unique.' ?>
                </span>

            </div>


            <!-- Course Name -->

            <div class="form-group">

                <label><?= $lang === 'ar' ? 'اسم الدورة *' : 'Course Name *' ?></label>

                <input
                    type="text"
                    name="course_name"
                    maxlength="150"
                    required
                    value="<?= htmlspecialchars($course['course_name']) ?>"
                >

            </div>


            <!-- Category -->

            <div class="form-group">

                <label><?= $lang === 'ar' ? 'الفئة *' : 'Category *' ?></label>

                <select name="category_id" required>

                    <option value="">
                        <?= $lang === 'ar' ? '-- اختر الفئة --' : '-- Select Category --' ?>
                    </option>

                    <?php foreach ($categories as $category): ?>

                        <option
                            value="<?= (int) $category['id'] ?>"
                            <?= ((int) $course['category_id'] === (int) $category['id'])
                                ? 'selected'
                                : '' ?>
                        >
                            <?= htmlspecialchars($category['name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- Level -->

            <div class="form-group">

                <label><?= $lang === 'ar' ? 'المستوى *' : 'Level *' ?></label>

                <select name="level_id" required>

                    <option value="">
                        <?= $lang === 'ar' ? '-- اختر المستوى --' : '-- Select Level --' ?>
                    </option>

                    <?php foreach ($levels as $level): ?>

                        <option
                            value="<?= (int) $level['id'] ?>"
                            <?= ((int) $course['level_id'] === (int) $level['id'])
                                ? 'selected'
                                : '' ?>
                        >
                            <?= htmlspecialchars($level['name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- Default Fee -->

            <div class="form-group">

                <label><?= $lang === 'ar' ? 'الرسوم الافتراضية *' : 'Default Fee *' ?></label>

                <input
                    type="number"
                    name="default_fee"
                    min="0"
                    step="0.01"
                    required
                    value="<?= htmlspecialchars($course['default_fee']) ?>"
                >

            </div>


            <!-- Duration -->

            <div class="form-group">

                <label><?= $lang === 'ar' ? 'المدة' : 'Duration' ?></label>

                <input
                    type="text"
                    name="duration"
                    maxlength="100"
                    value="<?= htmlspecialchars($course['duration'] ?? '') ?>"
                    placeholder="<?= $lang === 'ar' ? 'مثال: 3 أشهر' : 'e.g. 3 Months' ?>"
                >

            </div>


            <!-- Status -->

            <div class="form-group">

                <label><?= $lang === 'ar' ? 'الحالة *' : 'Status *' ?></label>

                <select name="status" required>

                    <option
                        value="active"
                        <?= $course['status'] === 'active'
                            ? 'selected'
                            : '' ?>
                    >
                        <?= $lang === 'ar' ? 'نشطة' : 'Active' ?>
                    </option>

                    <option
                        value="inactive"
                        <?= $course['status'] === 'inactive'
                            ? 'selected'
                            : '' ?>
                    >
                        <?= $lang === 'ar' ? 'غير نشطة' : 'Inactive' ?>
                    </option>

                </select>

            </div>


            <!-- Description -->

            <div class="form-group full-width">

                <label><?= $lang === 'ar' ? 'الوصف' : 'Description' ?></label>

                <textarea
                    name="description"
                    placeholder="<?= $lang === 'ar' ? 'وصف الدورة...' : 'Course description...' ?>"
                ><?= htmlspecialchars($course['description'] ?? '') ?></textarea>

            </div>

        </div>


        <div class="form-actions">

            <button
                type="submit"
                class="btn btn-primary"
            >
                <?= $lang === 'ar' ? 'حفظ التغييرات' : 'Save Changes' ?>
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